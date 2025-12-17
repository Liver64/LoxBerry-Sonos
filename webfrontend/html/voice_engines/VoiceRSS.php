<?php
/**
 * Text-to-Speech (TTS) using VoiceRSS API
 *
 * Creates an MP3 file from the provided text using VoiceRSS and saves it locally.
 *
 * @param array $t2s_param [
 *     'apikey'   => (string) VoiceRSS API key,
 *     'filename' => (string) Output filename (without extension),
 *     'text'     => (string) Text to convert,
 *     'voice'    => (string) Voice identifier
 *                    - voice name (e.g. "Hanna")
 *                    - or language code (e.g. "de-DE" / "de-de")
 * ]
 *
 * @return string|false Returns the filename on success or false on failure
 */
function t2s($t2s_param)
{
    global $config;

    // =========================
    // 1. Validate input parameters
    // =========================
    $apikey   = $t2s_param['apikey']   ?? null;
    $filename = $t2s_param['filename'] ?? null;
    $text     = $t2s_param['text']     ?? null;
    $voiceKey = $t2s_param['voice']    ?? null;

    // Debug-Log der Eingangsparameter (ohne API-Key & Text)
    $voiceKeyDebug = $voiceKey ?? '';
    $textLen       = is_string($text) ? strlen($text) : 0;
    LOGDEB("voice_engines/VoiceRSS.php: t2s() called with filename='" . (string)$filename . "', voiceKey='" . (string)$voiceKeyDebug . "', textLen=" . $textLen . ".");

    if (empty($apikey) || empty($filename) || empty($text)) {
        LOGERR("voice_engines/VoiceRSS.php: Missing required parameters (apikey, filename or text).");
        return false;
    }

    // voiceKey kann leer sein -> dann messageLang aus Config verwenden
    $defaultLang = $config['TTS']['messageLang'] ?? 'en-us';
    $voiceKey    = trim((string)($voiceKey ?? ''));

    if ($voiceKey === '') {
        $voiceKey = $defaultLang;
        LOGDEB("voice_engines/VoiceRSS.php: No voice specified, using messageLang '$voiceKey' from config.");
    }

    $voiceKeyNorm = strtolower($voiceKey);

    // =========================
    // 2. Optional: Stimme aus JSON ermitteln
    // =========================
    $voiceName = '';   // VoiceRSS Voice-Name, z.B. "Hanna"
    $language  = '';   // VoiceRSS Language-Tag, z.B. "de-de"

    $voiceFilePath = LBPHTMLDIR . "/voice_engines/langfiles/voicerss_voices.json";
    if (is_file($voiceFilePath)) {
        $jsonRaw = @file_get_contents($voiceFilePath);
        if ($jsonRaw === false) {
            LOGWARN("voice_engines/VoiceRSS.php: Could not read voices file '$voiceFilePath' – continuing with fallback.");
        } else {
            $voices = json_decode($jsonRaw, true);
            if (is_array($voices)) {

                LOGDEB("voice_engines/VoiceRSS.php: Loaded " . count($voices) . " VoiceRSS voice entries from '$voiceFilePath'.");

                // 2a) Direkte Namenszuordnung (z.B. "Hanna")
                foreach ($voices as $voice) {
                    $name = $voice['name'] ?? null;
                    if ($name !== null && $name === $voiceKey) {
                        $voiceName = $name;
                        $language  = $voice['language'] ?? '';
                        LOGOK("voice_engines/VoiceRSS.php: Voice name '$voiceKey' found in configuration (language='$language').");
                        break;
                    }
                }

                // 2b) Noch nichts gefunden -> voiceKey als Sprachcode interpretieren (z.B. "de-de")
                if ($voiceName === '') {
                    foreach ($voices as $voice) {
                        $langRaw  = $voice['language'] ?? '';
                        $langNorm = strtolower($langRaw);
                        if ($langNorm !== '' && $langNorm === $voiceKeyNorm) {
                            $voiceName = $voice['name'] ?? '';
                            $language  = $langRaw;
                            LOGOK(
                                "voice_engines/VoiceRSS.php: Voice key '$voiceKey' interpreted as language; " .
                                "using default voice '{$voiceName}' for language '{$language}'."
                            );
                            break;
                        }
                    }
                }

            } else {
                LOGWARN("voice_engines/VoiceRSS.php: Invalid JSON in '$voiceFilePath' – continuing with fallback.");
            }
        }
    } else {
        LOGWARN("voice_engines/VoiceRSS.php: Voices file '$voiceFilePath' not found – continuing with fallback.");
    }

    // =========================
    // 3. Fallback: keine passende Stimme gefunden
    // =========================
    if ($language === '') {
        // voiceKey wie Sprachcode? -> direkt als Sprache nehmen
        if (preg_match('/^[a-z]{2,3}-[a-z]{2}$/i', $voiceKey)) {
            $language = strtolower($voiceKey);
            LOGWARN("voice_engines/VoiceRSS.php: No voice mapping for '$voiceKey'. Using language '$language' with VoiceRSS default voice.");
        } else {
            // sonst auf Default-Sprache zurückfallen
            $language = strtolower($defaultLang);
            LOGWARN("voice_engines/VoiceRSS.php: Voice '$voiceKey' not mapped. Falling back to default language '$language' with VoiceRSS default voice.");
        }
        $voiceName = ''; // keine explizite Stimme -> VoiceRSS nimmt Standard-Voice
    }

    LOGDEB(
        "voice_engines/VoiceRSS.php: Final VoiceRSS selection: language='$language', " .
        "voiceName='" . ($voiceName !== '' ? $voiceName : 'DEFAULT') . "'."
    );

    // =========================
    // 4. Prepare API request (force MP3!)
    // =========================
    $query = [
        'key' => $apikey,
        'src' => $text,
        'hl'  => $language,
        'c'   => 'MP3',
    ];

    // Stimme nur setzen, wenn wir eine haben – sonst nimmt VoiceRSS die Standard-Voice
    if ($voiceName !== '') {
        $query['v'] = $voiceName;
    }

    $apiUrl = "https://api.voicerss.org/?" . http_build_query($query);

    // API-URL debuggen, aber API-Key maskieren
    $debugUrl = preg_replace('/key=[^&]+/', 'key=***', $apiUrl);
    LOGDEB("voice_engines/VoiceRSS.php: VoiceRSS request URL (masked): $debugUrl");

    LOGOK("voice_engines/VoiceRSS.php: Sending TTS request to VoiceRSS API (lang='$language', voice='" . ($voiceName ?: 'DEFAULT') . "').");

    // =========================
    // 5. Fetch audio from VoiceRSS
    // =========================
    $audioData   = false;
    $httpCode    = 0;
    $curlErrNo   = 0;
    $curlErrText = '';
    $responseSnippet = '';

    if (function_exists('curl_init')) {

        // ---- Variante mit cURL (bevorzugt) ----
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'LoxBerry-T2S/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $audioData   = curl_exec($ch);
        $curlErrNo   = curl_errno($ch);
        $curlErrText = curl_error($ch);
        $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $len = is_string($audioData) ? strlen($audioData) : 0;
        LOGDEB("voice_engines/VoiceRSS.php: cURL finished with HTTP $httpCode, curlErrNo=$curlErrNo, dataLen=$len.");

        if ($audioData !== false && $len > 0) {
            $responseSnippet = substr($audioData, 0, 200);
        }

    } else {

        // ---- Fallback auf file_get_contents ----
        LOGWARN("voice_engines/VoiceRSS.php: PHP cURL extension not available – using file_get_contents fallback.");

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: LoxBerry-T2S/1.0\r\n",
                'timeout' => 20,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ]
        ]);

        $audioData = @file_get_contents($apiUrl, false, $ctx);

        // HTTP-Header auswerten (falls vorhanden)
        $len = is_string($audioData) ? strlen($audioData) : 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $hdr, $m)) {
                    $httpCode = (int)$m[1];
                    break;
                }
            }
            LOGDEB("voice_engines/VoiceRSS.php: file_get_contents HTTP headers: " . implode(' | ', $http_response_header));
        }

        LOGDEB("voice_engines/VoiceRSS.php: file_get_contents finished with HTTP $httpCode (if detected), dataLen=$len.");

        if ($audioData !== false && $len > 0) {
            $responseSnippet = substr($audioData, 0, 200);
        }
    }

    // ---- Fehlerauswertung VoiceRSS-Antwort ----

    $hasErrorString = false;
    if (is_string($audioData) && stripos($audioData, 'ERROR') !== false) {
        $hasErrorString = true;
    }

    if ($audioData === false || $httpCode >= 400 || $httpCode === 0 || $hasErrorString || strlen((string)$audioData) < 50) {

        LOGERR("voice_engines/VoiceRSS.php: Failed to fetch audio data from VoiceRSS API (HTTP $httpCode, curlErrNo=$curlErrNo).");

        if ($curlErrNo !== 0 || $curlErrText !== '') {
            LOGERR("voice_engines/VoiceRSS.php: cURL error: [$curlErrNo] $curlErrText");
        }

        if ($hasErrorString) {
            LOGERR("voice_engines/VoiceRSS.php: API returned error: " . trim($responseSnippet));
        } elseif (!empty($responseSnippet)) {
            LOGDEB("voice_engines/VoiceRSS.php: VoiceRSS response snippet: " . $responseSnippet);
        } else {
            LOGDEB("voice_engines/VoiceRSS.php: VoiceRSS response is empty or too short.");
        }

        return false;
    }

    // =========================
    // 6. Save MP3 file
    // =========================
    $outputDir = rtrim($config['SYSTEM']['ttspath'], '/');
    LOGDEB("voice_engines/VoiceRSS.php: Output directory for MP3 is '$outputDir'.");

    if (!is_dir($outputDir) && !@mkdir($outputDir, 0775, true)) {
        LOGERR("voice_engines/VoiceRSS.php: Output directory '$outputDir' does not exist and could not be created.");
        return false;
    }

    $safeName   = preg_replace('~[^a-f0-9]~i', '', (string)$filename);
    $outputFile = $outputDir . "/" . $safeName . ".mp3";

    LOGDEB("voice_engines/VoiceRSS.php: Writing " . strlen($audioData) . " bytes to '$outputFile'.");

    if (@file_put_contents($outputFile, $audioData) === false) {
        LOGERR("voice_engines/VoiceRSS.php: Failed to save MP3 file to '$outputFile'.");
        return false;
    }

    LOGOK("voice_engines/VoiceRSS.php: MP3 file successfully saved as '$outputFile'.");

    // =========================
    // 7. Return the filename
    // =========================
    return $safeName;
}
?>
