<?php
// ======================================================================
// GoogleCloud.php — Google Cloud Text-to-Speech (mit CHIRP3 Support)
// FINAL FIX — KEINE Voice-Überschreibungen, KEIN model-Feld
// ======================================================================

// ---- Polyfill für PHP < 8 ----
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

function t2s(array $t2s_param)
{
    global $config;

    LOGINF("voice_engines/GoogleCloud.php: Start");

    // ==================================================================
    // 1) Parameter EINMALIG aus $t2s_param laden (kein $_GET!)
    // ==================================================================
    $filename   = $t2s_param['filename'] ?? 'tts_output';
    $textstring = $t2s_param['text']     ?? '';
    $voiceName  = $t2s_param['voice']    ?? '';
    $language   = $t2s_param['language'] ?? '';

    if ($textstring === '') {
        LOGERR("GoogleCloud.php: Empty text.");
        return false;
    }

    if ($voiceName === '' || $language === '') {
        LOGERR("GoogleCloud.php: Missing voice or language in t2s_param.");
        return false;
    }

    LOGINF("GoogleCloud.php: Voice='{$voiceName}', Language='{$language}'");

    // ==================================================================
    // 2) Zielpfad & Cache
    // ==================================================================
    $ttspath = rtrim($config['SYSTEM']['ttspath'] ?? '/tmp', '/');
    @mkdir($ttspath, 0775, true);

    $mp3Path = "{$ttspath}/{$filename}.mp3";

    if (is_file($mp3Path) && filesize($mp3Path) > 0) {
        LOGINF("GoogleCloud.php: Cache hit ($mp3Path)");
        LOGOK ("GoogleCloud.php: Done (from cache)");
        return basename($mp3Path, '.mp3');
    }

    // ==================================================================
    // 3) Auth
    // ==================================================================
    $apiKey      = $t2s_param['apikey']       ?? ($config['TTS']['apikey'] ?? '');
    $accessToken = $t2s_param['access_token'] ?? ($config['TTS']['access_token'] ?? '');

    $endpoint = "https://texttospeech.googleapis.com/v1/text:synthesize";

    if ($accessToken === '' && $apiKey !== '') {
        $endpoint .= "?key=" . rawurlencode($apiKey);
    }

    if ($accessToken === '' && $apiKey === '') {
        LOGERR("GoogleCloud.php: No credentials.");
        return false;
    }

    // ==================================================================
    // 4) Prosodie
    // ==================================================================
    $speakingRate = (float)($t2s_param['speakingRate'] ?? 1.0);
    $pitch        = (float)($t2s_param['pitch'] ?? 0.0);

    // ==================================================================
    // 5) Chunking
    // ==================================================================
    if (!function_exists('chunkTextForGoogleTTS')) {
        function chunkTextForGoogleTTS(string $text, int $limit = 4800): array {
            $chunks = [];
            $current = '';

            foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                if (strlen($current . $char) > $limit) {
                    $chunks[] = $current;
                    $current = $char;
                } else {
                    $current .= $char;
                }
            }

            if ($current !== '') $chunks[] = $current;
            return $chunks;
        }
    }

    $chunks = chunkTextForGoogleTTS($textstring);

    // ==================================================================
    // 6) Basis-Payload — KEIN model-Feld!
    // ==================================================================
    $basePayload = [
        "voice" => [
            "languageCode" => $language,
            "name"         => $voiceName
        ],
        "audioConfig" => [
            "audioEncoding" => "MP3",
            "speakingRate"  => $speakingRate,
            "pitch"         => $pitch
        ]
    ];

    // Prüfen: Voice muss technischer Name sein
    if (!str_contains($voiceName, "-Chirp3-")) {
        LOGINF("GoogleCloud.php: Voice is non-Chirp or standard voice");
    }

    // ==================================================================
    // 7) HTTP Caller
    // ==================================================================
    $callGoogle = static function (string $url, string $json, string $accessToken) {
        $ch = curl_init($url);

        $headers = [
            "Content-Type: application/json",
            "Content-Length: " . strlen($json)
        ];

        if ($accessToken !== '') {
            $headers[] = "Authorization: Bearer " . $accessToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        return [$resp, $err, $code];
    };

    // ==================================================================
    // 8) MP3 erzeugen
    // ==================================================================
    @unlink($mp3Path);
    $fh = fopen($mp3Path, 'ab');
    if (!$fh) {
        LOGERR("GoogleCloud.php: Cannot write $mp3Path");
        return false;
    }

    $okAll   = true;
    $minSize = 1024;

    foreach ($chunks as $i => $chunk) {

        $payload = $basePayload;
        $payload["input"] = ["text" => $chunk];

        $jsonPayload = json_encode($payload);

        $maxRetries = 3;
        $wait = 1;
        $success = false;

        for ($r = 0; $r < $maxRetries; $r++) {

            [$resp, $err, $code] = $callGoogle($endpoint, $jsonPayload, $accessToken);

            if ($err) {
                LOGERR("GoogleCloud.php: CURL error: $err");
            }

            if ($code === 200) {
                $data = json_decode($resp, true);

                if (!isset($data['audioContent'])) {
                    LOGERR("GoogleCloud.php: Missing audioContent");
                    break;
                }

                $bin = base64_decode($data['audioContent']);

                if ($bin !== false && strlen($bin) > 0) {
                    fwrite($fh, $bin);
                    LOGINF("GoogleCloud.php: Chunk " . ($i+1) . "/" . count($chunks) . " OK");
                    $success = true;
                }
                break;
            }

            // Retry
            if ($code == 429 || $code >= 500) {
                LOGWARN("GoogleCloud.php: Retry ($r) HTTP=$code waiting {$wait}s");
                sleep($wait);
                $wait *= 2;
                continue;
            }

            LOGERR("GoogleCloud.php: HTTP $code => $resp");
            break;
        }

        if (!$success) {
            $okAll = false;
            break;
        }
    }

    fclose($fh);

    if (!$okAll || filesize($mp3Path) < $minSize) {
        @unlink($mp3Path);
        LOGERR("GoogleCloud.php: Failed to generate MP3");
        return false;
    }

    LOGOK("GoogleCloud.php: Done (size=" . filesize($mp3Path) . " bytes)");
    return basename($mp3Path, '.mp3');
}
