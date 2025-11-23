<?php
/**
 * VoiceRSS.php – VoiceRSS TTS engine for Sonos4Lox
 *
 * Responsibilities:
 *  - Map "voice key" from config/UI to Voice + Language
 *  - Connectivity pre-check with detailed cURL diagnostics
 *  - Perform actual TTS request to VoiceRSS and write MP3 file
 *
 * Expected usage (example from play_t2s.php):
 *
 *   $ok = sonos4lox_voicerss_tts(
 *       $textstring,
 *       $ttsfile,
 *       [
 *           'apikey' => $TTSVOICERSS_APIKEY,
 *           'voice_key' => $TTSVOICERSS_VOICE,   // can be "de-de" or a VoiceRSS voice name
 *           'speed' => $TTSVOICERSS_SPEED,       // optional, e.g. 0
 *           'format' => '44khz_16bit_mono',      // optional
 *       ]
 *   );
 *
 * Return:
 *   true  => MP3 file has been written successfully
 *   false => some error occurred (already logged)
 */

require_once dirname(__DIR__) . '/bin/helper.php'; // for LOGGING(), if not already included

if (!function_exists('sonos4lox_voicerss_tts')) {

    /**
     * Public entry point used by play_t2s.php
     *
     * @param string $text       Text to be spoken (UTF-8)
     * @param string $outfile    Absolute path to resulting MP3 file
     * @param array  $config     Assoc array with:
     *                           - apikey    (required)
     *                           - voice_key (required; can be language or voice name)
     *                           - speed     (optional; -10..10)
     *                           - format    (optional; default 44khz_16bit_mono)
     * @return bool
     */
    function sonos4lox_voicerss_tts(string $text, string $outfile, array $config): bool
    {
        // Basic sanity checks
        if (trim($text) === '') {
            LOGGING("ERROR: voice_engines/VoiceRSS.php: No text given for TTS.");
            return false;
        }

        if (empty($config['apikey'])) {
            LOGGING("ERROR: voice_engines/VoiceRSS.php: No VoiceRSS API key configured.");
            return false;
        }

        $apikey    = $config['apikey'];
        $voiceKey  = $config['voice_key'] ?? '';
        $speed     = isset($config['speed']) ? (int)$config['speed'] : 0;
        $format    = $config['format'] ?? '44khz_16bit_mono';
        $codec     = 'MP3';
        $endpoint  = 'https://api.voicerss.org/';

        // ----- Step 1: interpret voice key (language vs. explicit voice) -----
        list($language, $voice) = voicerss_resolve_language_and_voice($voiceKey);

        // Logging exactly wie im Log deines Users
        if ($voiceKey && $voiceKey === $language) {
            // "Voice key 'de-de' interpreted as language; using default voice 'Hanna' for language 'de-de'."
            LOGGING("OK: voice_engines/VoiceRSS.php: Voice key '$voiceKey' interpreted as language; using default voice '$voice' for language '$language'.");
        } elseif ($voiceKey) {
            LOGGING("OK: voice_engines/VoiceRSS.php: Voice key '$voiceKey' interpreted as explicit voice; using language '$language'.");
        } else {
            LOGGING("OK: voice_engines/VoiceRSS.php: Empty voice key provided – using default language '$language' and voice '$voice'.");
        }

        // ----- Step 2: connectivity pre-check -----
        if (!voicerss_precheck_connectivity($endpoint)) {
            LOGGING("ERROR: voice_engines/VoiceRSS.php: Pre-check failed — cannot reach VoiceRSS API endpoint.");
            return false;
        }

        // ----- Step 3: build request -----
        $queryData = [
            'key' => $apikey,
            'hl'  => $language,
            'src' => $text,
            'c'   => $codec,
            'f'   => $format,
        ];

        // Voice name optional; only set when non-empty
        if (!empty($voice)) {
            $queryData['v'] = $voice;
        }

        // Speed / rate: VoiceRSS uses "r" in range -10..10
        if ($speed !== 0) {
            $queryData['r'] = max(-10, min(10, $speed));
        }

        $url = $endpoint . '?' . http_build_query($queryData, '', '&', PHP_QUERY_RFC3986);

        LOGGING("INFO: voice_engines/VoiceRSS.php: Calling VoiceRSS API for language '$language', voice '$voice', speed '$speed'.");

        // ----- Step 4: perform TTS request -----
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $errno  = curl_errno($ch);
            $errstr = curl_error($ch);
            curl_close($ch);

            LOGGING("ERROR: voice_engines/VoiceRSS.php: cURL error during TTS request (code $errno): $errstr");
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            LOGGING("ERROR: voice_engines/VoiceRSS.php: VoiceRSS returned HTTP status $httpCode.");
            return false;
        }

        // ----- Step 5: inspect payload for VoiceRSS API error responses -----
        // VoiceRSS returns errors as plain text like "ERROR: The subscription is ..."
        // Deshalb erster Teil der Antwort prüfen.
        $trimmed = ltrim($response);
        if (stripos($trimmed, 'ERROR') === 0) {
            // Nur die erste Zeile loggen; kann z.B. Key/Subscription Problem sein.
            $firstLine = strtok($trimmed, "\r\n");
            LOGGING("ERROR: voice_engines/VoiceRSS.php: VoiceRSS API error: $firstLine");
            return false;
        }

        // ----- Step 6: write MP3 to file -----
        $dir = dirname($outfile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                LOGGING("ERROR: voice_engines/VoiceRSS.php: Cannot create output directory '$dir'.");
                return false;
            }
        }

        $bytes = @file_put_contents($outfile, $response, LOCK_EX);
        if ($bytes === false || $bytes === 0) {
            LOGGING("ERROR: voice_engines/VoiceRSS.php: Failed to write MP3 to '$outfile'.");
            return false;
        }

        LOGGING("OK: voice_engines/VoiceRSS.php: MP3 file successfully created at '$outfile' ($bytes bytes).");
        return true;
    }

    /**
     * Interpret a "voice key" provided by the UI/config.
     *
     * 1) If it looks like a language code (xx-yy), we treat it as language and
     *    choose a default voice for that language.
     * 2) Otherwise, treat it as concrete voice name and derive a reasonable
     *    language (fallback to de-de or en-us).
     *
     * @param string $voiceKey
     * @return array [language, voice]
     */
    function voicerss_resolve_language_and_voice(string $voiceKey): array
    {
        $voiceKey = trim($voiceKey);

        // Known default voices per language (extend as needed)
        $defaultVoices = [
            'de-de' => 'Hanna',
            'de-de_fallback' => 'Hanna',
            'en-us' => 'Linda',
            'en-gb' => 'Alice',
            'fr-fr' => 'Bette',
            'it-it' => 'Chiara',
            'es-es' => 'Isabella',
            'nl-nl' => 'Lotte',
        ];

        // If blank: default to German Hanna
        if ($voiceKey === '') {
            return ['de-de', 'Hanna'];
        }

        $lower = strtolower($voiceKey);

        // Case 1: voiceKey looks like a language code (xx-yy)
        if (preg_match('/^[a-z]{2}-[a-z]{2}$/', $lower)) {
            $lang = $lower;
            $voice = $defaultVoices[$lang] ?? ($defaultVoices[$lang . '_fallback'] ?? 'Hanna');
            return [$lang, $voice];
        }

        // Case 2: explicit voice name; we try to guess language by prefix
        $langGuess = 'de-de'; // default guess

        if (preg_match('/^(en)/i', $voiceKey)) {
            $langGuess = 'en-us';
        } elseif (preg_match('/^(fr)/i', $voiceKey)) {
            $langGuess = 'fr-fr';
        } elseif (preg_match('/^(it)/i', $voiceKey)) {
            $langGuess = 'it-it';
        } elseif (preg_match('/^(es)/i', $voiceKey)) {
            $langGuess = 'es-es';
        } elseif (preg_match('/^(nl)/i', $voiceKey)) {
            $langGuess = 'nl-nl';
        }

        return [$langGuess, $voiceKey];
    }

    /**
     * Lightweight connectivity test for VoiceRSS endpoint.
     *
     * We just check if https://api.voicerss.org/ is reachable and responds with
     * some HTTP status. Any cURL error (DNS, timeout, SSL) is logged.
     *
     * @param string $endpoint
     * @return bool
     */
    function voicerss_precheck_connectivity(string $endpoint): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_NOBODY         => true,   // HEAD request – no body needed
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $ok = curl_exec($ch);

        if ($ok === false) {
            $errno  = curl_errno($ch);
            $errstr = curl_error($ch);
            curl_close($ch);

            LOGGING("ERROR: voice_engines/VoiceRSS.php: Pre-check cURL error (code $errno): $errstr");
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 500) {
            // Host ist erreichbar, auch wenn evtl. 4xx zurückkommt – Connectivity passt.
            LOGGING("INFO: voice_engines/VoiceRSS.php: Pre-check successful, HTTP status $httpCode from VoiceRSS endpoint.");
            return true;
        }

        LOGGING("ERROR: voice_engines/VoiceRSS.php: Pre-check HTTP status $httpCode from VoiceRSS endpoint.");
        return false;
    }
}
