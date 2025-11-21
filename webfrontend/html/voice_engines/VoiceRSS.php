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
        $voices = json_decode(@file_get_contents($voiceFilePath), true);
        if (is_array($voices)) {

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

    // =========================
    // 3.5 Pre-API-check (connectivity & key validation)
    // =========================
    $pingUrl = "https://api.voicerss.org/?key=" . urlencode($apikey) . "&hl=en-us&src=ping";
    $ctxPing = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: LoxBerry-T2S/1.0\r\n",
            'timeout' => 8,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ]
    ]);

    $pingResult = @file_get_contents($pingUrl, false, $ctxPing);
    if ($pingResult === false) {
        LOGERR("voice_engines/VoiceRSS.php: Pre-check failed — cannot reach VoiceRSS API endpoint.");
        return false;
    }

    if (stripos($pingResult, 'ERROR') !== false) {
        LOGERR("voice_engines/VoiceRSS.php: Pre-check response indicates an error from API: " . trim($pingResult));
        return false;
    }

    LOGDEB("voice_engines/VoiceRSS.php: Pre-check successful — VoiceRSS API reachable and key seems valid.");

    // =========================
    // 4. Prepare API request (force MP3!)
    // =========================
    $query = [
        'key' => $apikey,
        'src' => $text,
        'hl'  => $language,
        'c'   => 'MP3'
    ];
    // Stimme nur setzen, wenn wir eine haben – sonst nimmt VoiceRSS die Standard-Voice
    if ($voiceName !== '') {
        $query['v'] = $voiceName;
    }

    $apiUrl = "https://api.voicerss.org/?" . http_build_query($query);

    LOGOK("voice_engines/VoiceRSS.php: Sending TTS request to VoiceRSS API (lang='$language', voice='" . ($voiceName ?: 'DEFAULT') . "').");

    // =========================
    // 5. Fetch audio from VoiceRSS
    // =========================
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
    if ($audioData === false || strlen($audioData) < 50 || stripos($audioData, 'ERROR') !== false) {
        LOGERR("voice_engines/VoiceRSS.php: Failed to fetch audio data from VoiceRSS API.");
        if ($audioData && stripos($audioData, 'ERROR') !== false) {
            LOGERR("voice_engines/VoiceRSS.php: API returned error: " . trim($audioData));
        }
        return false;
    }

    // =========================
    // 6. Save MP3 file
    // =========================
    $outputDir = rtrim($config['SYSTEM']['ttspath'], '/');
    if (!is_dir($outputDir) && !@mkdir($outputDir, 0775, true)) {
        LOGERR("voice_engines/VoiceRSS.php: Output directory '$outputDir' does not exist and could not be created.");
        return false;
    }

    $safeName   = preg_replace('~[^a-f0-9]~i', '', (string)$filename);
    $outputFile = $outputDir . "/" . $safeName . ".mp3";

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