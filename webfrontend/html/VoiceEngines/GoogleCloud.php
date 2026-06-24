<?php
/**
 * Sonos4Lox - Google Cloud Text-to-Speech with Chirp3 support
 * Version: VOICE_ENGINE_ROBUSTNESS_V03_2026_06_15
 */

require_once __DIR__ . '/VoiceEngineHelper.php';

if (!defined('S4L_GOOGLE_CONTEXT')) {
    define('S4L_GOOGLE_CONTEXT', 'VoiceEngines/GoogleCloud.php');
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('s4l_google_chunk_text')) {
    function s4l_google_chunk_text(string $text, int $limit = 4800): array
    {
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

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}

if (!function_exists('s4l_google_call')) {
    function s4l_google_call(string $url, string $json, string $accessToken): array
    {
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
        ];

        if ($accessToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'LoxBerry-Sonos4Lox/1.0',
        ]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = (int)curl_errno($ch);
        curl_close($ch);

        return [$resp, $err, $code, $errno];
    }
}

function t2s(array $t2s_param)
{
    global $config;

    s4l_ve_log(S4L_GOOGLE_CONTEXT, 'INFO', 'Start.');

    $filename = (string)($t2s_param['filename'] ?? 'tts_output');
    $text = (string)($t2s_param['text'] ?? '');
    $voiceName = trim((string)($t2s_param['voice'] ?? ''));
    $language = trim((string)($t2s_param['language'] ?? ''));

    if ($text === '') {
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', 'Empty text.');
        return false;
    }

    if ($voiceName === '' || $language === '') {
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', 'Missing voice or language in t2s_param.');
        return false;
    }

    s4l_ve_log(S4L_GOOGLE_CONTEXT, 'INFO', "Voice='$voiceName', Language='$language'.");

    $mp3Path = s4l_ve_output_path($config, $filename, S4L_GOOGLE_CONTEXT);
    if ($mp3Path === '') {
        return false;
    }

    if (is_file($mp3Path) && filesize($mp3Path) > 0) {
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'INFO', "Cache hit ($mp3Path).");
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'OK', 'Done (from cache).');
        return basename($mp3Path, '.mp3');
    }

    $apiKey = trim((string)($t2s_param['apikey'] ?? ($config['TTS']['apikey'] ?? '')));
    $accessToken = trim((string)($t2s_param['access_token'] ?? ($config['TTS']['access_token'] ?? '')));

    $endpoint = 'https://texttospeech.googleapis.com/v1/text:synthesize';
    if ($accessToken === '' && $apiKey !== '') {
        $endpoint .= '?key=' . rawurlencode($apiKey);
    }

    if ($accessToken === '' && $apiKey === '') {
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', 'No Google Cloud credentials available.');
        return false;
    }

    $speakingRate = (float)($t2s_param['speakingRate'] ?? 1.0);
    $pitch = (float)($t2s_param['pitch'] ?? 0.0);

    $chunks = s4l_google_chunk_text($text);
    if (empty($chunks)) {
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', 'No text chunks generated.');
        return false;
    }

    $basePayload = [
        'voice' => [
            'languageCode' => $language,
            'name' => $voiceName,
        ],
        'audioConfig' => [
            'audioEncoding' => 'MP3',
            'speakingRate' => $speakingRate,
            'pitch' => $pitch,
        ],
    ];

    if (!str_contains($voiceName, '-Chirp3-')) {
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'INFO', 'Voice is non-Chirp or standard voice.');
    }

    @unlink($mp3Path);
    $fh = @fopen($mp3Path, 'ab');
    if (!$fh) {
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', "Cannot write $mp3Path.");
        return false;
    }

    $okAll = true;
    $minSize = 1024;
    $chunkCount = count($chunks);

    foreach ($chunks as $i => $chunk) {
        $payload = $basePayload;
        $payload['input'] = ['text' => $chunk];
        $jsonPayload = json_encode($payload);
        if (!is_string($jsonPayload)) {
            s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', 'Could not encode Google TTS payload as JSON.');
            $okAll = false;
            break;
        }

        $success = false;
        $wait = 1;
        $maxRetries = 3;

        for ($r = 0; $r < $maxRetries; $r++) {
            [$resp, $err, $code, $errno] = s4l_google_call($endpoint, $jsonPayload, $accessToken);

            if ($errno !== 0 || $err !== '') {
                s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', "cURL error [$errno]: $err");
            }

            if ($code === 200 && is_string($resp)) {
                $data = json_decode($resp, true);
                if (!isset($data['audioContent'])) {
                    s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', 'Google response is missing audioContent.');
                    break;
                }

                $bin = base64_decode((string)$data['audioContent'], true);
                if ($bin !== false && strlen($bin) > 0) {
                    fwrite($fh, $bin);
                    s4l_ve_log(S4L_GOOGLE_CONTEXT, 'INFO', 'Chunk ' . ($i + 1) . "/$chunkCount OK.");
                    $success = true;
                }
                break;
            }

            if ($code === 429 || $code >= 500) {
                s4l_ve_log(S4L_GOOGLE_CONTEXT, 'WARNING', "Retry $r HTTP=$code waiting {$wait}s.");
                sleep($wait);
                $wait *= 2;
                continue;
            }

            $snippet = is_string($resp) ? substr($resp, 0, 300) : '';
            s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', "HTTP $code from Google Cloud TTS.");
            if ($snippet !== '') {
                s4l_ve_log(S4L_GOOGLE_CONTEXT, 'DEBUG', 'Google response snippet: ' . $snippet);
            }
            break;
        }

        if (!$success) {
            $okAll = false;
            break;
        }
    }

    fclose($fh);

    if (!$okAll || !is_file($mp3Path) || filesize($mp3Path) < $minSize) {
        @unlink($mp3Path);
        s4l_ve_log(S4L_GOOGLE_CONTEXT, 'ERROR', 'Failed to generate MP3.');
        return false;
    }

    @chmod($mp3Path, 0664);
    s4l_ve_log(S4L_GOOGLE_CONTEXT, 'OK', 'Done (size=' . filesize($mp3Path) . ' bytes).');
    return basename($mp3Path, '.mp3');
}
