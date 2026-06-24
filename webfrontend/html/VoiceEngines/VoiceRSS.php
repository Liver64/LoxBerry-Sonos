<?php
/**
 * Sonos4Lox - VoiceRSS Text-to-Speech
 * Version: VOICE_ENGINE_ROBUSTNESS_V03_2026_06_15
 */

require_once __DIR__ . '/VoiceEngineHelper.php';

if (!defined('S4L_VOICERSS_CONTEXT')) {
    define('S4L_VOICERSS_CONTEXT', 'VoiceEngines/VoiceRSS.php');
}

if (!function_exists('s4l_voicerss_resolve_voice')) {
    function s4l_voicerss_resolve_voice(array $voices, string $voiceKey, string $defaultLang): array
    {
        $voiceKeyNorm = strtolower($voiceKey);
        $voiceName = '';
        $language = '';

        foreach ($voices as $voice) {
            if (!is_array($voice)) {
                continue;
            }
            $name = (string)($voice['name'] ?? '');
            if ($name !== '' && $name === $voiceKey) {
                return [$name, (string)($voice['language'] ?? '')];
            }
        }

        foreach ($voices as $voice) {
            if (!is_array($voice)) {
                continue;
            }
            $langRaw = (string)($voice['language'] ?? '');
            if ($langRaw !== '' && strtolower($langRaw) === $voiceKeyNorm) {
                return [(string)($voice['name'] ?? ''), $langRaw];
            }
        }

        if (preg_match('/^[a-z]{2,3}-[a-z]{2}$/i', $voiceKey)) {
            return ['', strtolower($voiceKey)];
        }

        return ['', strtolower($defaultLang)];
    }
}

function t2s($t2s_param)
{
    global $config;

    if (!is_array($t2s_param)) {
        s4l_ve_log(S4L_VOICERSS_CONTEXT, 'ERROR', 'Invalid parameter type. Expected array.');
        return false;
    }

    $apikey = trim((string)($t2s_param['apikey'] ?? ''));
    $filename = (string)($t2s_param['filename'] ?? '');
    $text = (string)($t2s_param['text'] ?? '');
    $voiceKey = trim((string)($t2s_param['voice'] ?? ''));

    $textLen = strlen($text);
    s4l_ve_log(S4L_VOICERSS_CONTEXT, 'DEBUG', "t2s() called with filename='$filename', voiceKey='$voiceKey', textLen=$textLen.");

    if ($apikey === '' || $filename === '' || $text === '') {
        s4l_ve_log(S4L_VOICERSS_CONTEXT, 'ERROR', 'Missing required parameters: apikey, filename or text.');
        return false;
    }

    $defaultLang = (string)($config['TTS']['messageLang'] ?? 'en-us');
    if ($voiceKey === '') {
        $voiceKey = $defaultLang;
        s4l_ve_log(S4L_VOICERSS_CONTEXT, 'DEBUG', "No voice specified, using messageLang '$voiceKey' from config.");
    }

    $voices = [];
    $voiceFilePath = LBPHTMLDIR . '/VoiceEngines/langfiles/voicerss_voices.json';
    if (is_file($voiceFilePath)) {
        $loaded = s4l_ve_load_json($voiceFilePath, S4L_VOICERSS_CONTEXT);
        if (is_array($loaded)) {
            $voices = $loaded;
            s4l_ve_log(S4L_VOICERSS_CONTEXT, 'DEBUG', 'Loaded ' . count($voices) . " VoiceRSS voice entries from '$voiceFilePath'.");
        } else {
            s4l_ve_log(S4L_VOICERSS_CONTEXT, 'WARNING', "Invalid or unreadable voices file '$voiceFilePath' – continuing with fallback.");
        }
    } else {
        s4l_ve_log(S4L_VOICERSS_CONTEXT, 'WARNING', "Voices file '$voiceFilePath' not found – continuing with fallback.");
    }

    [$voiceName, $language] = s4l_voicerss_resolve_voice($voices, $voiceKey, $defaultLang);
    if ($language === '') {
        $language = strtolower($defaultLang);
    }

    s4l_ve_log(
        S4L_VOICERSS_CONTEXT,
        'DEBUG',
        "Final VoiceRSS selection: language='$language', voiceName='" . ($voiceName !== '' ? $voiceName : 'DEFAULT') . "'."
    );

    $query = [
        'key' => $apikey,
        'src' => $text,
        'hl' => $language,
        'c' => 'MP3',
    ];
    if ($voiceName !== '') {
        $query['v'] = $voiceName;
    }

    $apiUrl = 'https://api.voicerss.org/?' . http_build_query($query);
    $debugUrl = preg_replace('/key=[^&]+/', 'key=***', $apiUrl);
    s4l_ve_log(S4L_VOICERSS_CONTEXT, 'DEBUG', "VoiceRSS request URL (masked): $debugUrl");
    s4l_ve_log(S4L_VOICERSS_CONTEXT, 'OK', "Sending TTS request to VoiceRSS API (lang='$language', voice='" . ($voiceName ?: 'DEFAULT') . "').");

    $audioData = false;
    $httpCode = 0;
    $curlErrNo = 0;
    $curlErrText = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'LoxBerry-T2S/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $audioData = curl_exec($ch);
        $curlErrNo = (int)curl_errno($ch);
        $curlErrText = (string)curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $len = is_string($audioData) ? strlen($audioData) : 0;
        s4l_ve_log(S4L_VOICERSS_CONTEXT, 'DEBUG', "cURL finished with HTTP $httpCode, curlErrNo=$curlErrNo, dataLen=$len.");
    } else {
        s4l_ve_log(S4L_VOICERSS_CONTEXT, 'WARNING', 'PHP cURL extension not available – using file_get_contents fallback.');
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: LoxBerry-T2S/1.0\r\n",
                'timeout' => 20,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $audioData = @file_get_contents($apiUrl, false, $ctx);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $hdr, $m)) {
                    $httpCode = (int)$m[1];
                    break;
                }
            }
        }
        $len = is_string($audioData) ? strlen($audioData) : 0;
        s4l_ve_log(S4L_VOICERSS_CONTEXT, 'DEBUG', "file_get_contents finished with HTTP $httpCode, dataLen=$len.");
    }

    $responseSnippet = is_string($audioData) ? substr($audioData, 0, 200) : '';
    $hasErrorString = is_string($audioData) && stripos($audioData, 'ERROR') !== false;

    if ($audioData === false || $httpCode >= 400 || $httpCode === 0 || $hasErrorString || strlen((string)$audioData) < 50) {
        s4l_ve_log(S4L_VOICERSS_CONTEXT, 'ERROR', "Failed to fetch audio data from VoiceRSS API (HTTP $httpCode, curlErrNo=$curlErrNo).");
        if ($curlErrNo !== 0 || $curlErrText !== '') {
            s4l_ve_log(S4L_VOICERSS_CONTEXT, 'ERROR', "cURL error: [$curlErrNo] $curlErrText");
        }
        if ($hasErrorString) {
            s4l_ve_log(S4L_VOICERSS_CONTEXT, 'ERROR', 'API returned error: ' . trim($responseSnippet));
        } elseif ($responseSnippet !== '') {
            s4l_ve_log(S4L_VOICERSS_CONTEXT, 'DEBUG', 'VoiceRSS response snippet: ' . $responseSnippet);
        }
        return false;
    }

    $outputFile = s4l_ve_output_path($config, $filename, S4L_VOICERSS_CONTEXT);
    return s4l_ve_write_mp3($outputFile, $audioData, S4L_VOICERSS_CONTEXT);
}
