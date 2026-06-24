<?php
/**
 * Microsoft Azure Text-to-Speech engine
 * Version: VOICE_ENGINE_ROBUSTNESS_V01_2026_06_15
 *
 * Copyright (c) Microsoft Corporation
 * All rights reserved.
 * MIT License
 *
 * Modified by Oliver Lewald 2025/2026
 */

require_once __DIR__ . '/VoiceEngineHelper.php';

function t2s(array $t2s_param)
{
    global $config;

    $context = 'VoiceEngines/MS_Azure.php';

    $region     = $t2s_param['region'] ?? ($config['TTS']['regionms'] ?? 'westeurope');
    $apiKey     = $t2s_param['apikey'] ?? '';
    $lang       = $t2s_param['language'] ?? 'en-US';
    $textstring = $t2s_param['text'] ?? '';
    $voice_ms   = $t2s_param['voice'] ?? 'en-US-GuyNeural';
    $filename   = s4l_ve_safe_filename((string)($t2s_param['filename'] ?? 'tts_output'));

    if (!s4l_ve_require_params([
        'apikey' => $apiKey,
        'text' => $textstring,
        'filename' => $filename,
    ], ['apikey', 'text', 'filename'], $context)) {
        return false;
    }

    s4l_ve_log($context, 'INFO', "Using region='$region', lang='$lang', voice='$voice_ms' for filename='$filename'.");

    $accessTokenUri = "https://{$region}.api.cognitive.microsoft.com/sts/v1.0/issueToken";
    $ttsServiceUri  = "https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1";

    try {
        $accessToken = s4l_ve_curl_request($accessTokenUri, [
            CURLOPT_HTTPHEADER => [
                'Ocp-Apim-Subscription-Key: ' . $apiKey,
                'Content-Length: 0',
            ],
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ], $context);

        if ($accessToken === false || trim((string)$accessToken) === '') {
            s4l_ve_log($context, 'ERROR', 'Failed to get Azure access token.');
            return false;
        }

        s4l_ve_log($context, 'INFO', 'Access token received successfully.');

        $doc  = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement('speak');
        $root->setAttribute('version', '1.0');
        $root->setAttribute('xml:lang', substr($lang, 0, 5));

        $voice = $doc->createElement('voice', htmlspecialchars($textstring, ENT_XML1));
        $voice->setAttribute('xml:lang', substr($lang, 0, 5));
        $voice->setAttribute('name', $voice_ms);

        $root->appendChild($voice);
        $doc->appendChild($root);
        $ssml = $doc->saveXML();

        $audioData = s4l_ve_curl_request($ttsServiceUri, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/ssml+xml',
                'X-Microsoft-OutputFormat: audio-48khz-192kbitrate-mono-mp3',
                'Authorization: Bearer ' . $accessToken,
                'User-Agent: Sonos4Lox-TTSPHP',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $ssml,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ], $context);

        if ($audioData === false) {
            s4l_ve_log($context, 'ERROR', 'Failed to create MP3 with Azure TTS.');
            return false;
        }

        $filePath = s4l_ve_output_path($config, $filename, $context);
        return s4l_ve_write_mp3($filePath, $audioData, $context);

    } catch (Exception $e) {
        s4l_ve_log($context, 'ERROR', 'Exception: ' . $e->getMessage());
        return false;
    }
}
?>
