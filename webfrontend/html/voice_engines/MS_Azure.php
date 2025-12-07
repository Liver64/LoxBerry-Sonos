<?php

/**
* Copyright (c) Microsoft Corporation
* All rights reserved. 
* MIT License
*
* Modified by Oliver Lewald 2025
**/	

function t2s(array $t2s_param): void
{
    global $config;

    // --- Extract parameters with defaults ---
    $region      = $t2s_param['region']
                   ?? ($config['TTS']['regionms'] ?? 'westeurope');
    $apiKey      = $t2s_param['apikey']    ?? '';
    $lang        = $t2s_param['language']  ?? 'en-US';
    $textstring  = $t2s_param['text']      ?? '';
    $voice_ms    = $t2s_param['voice']     ?? 'en-US-GuyNeural';
    $filename    = $t2s_param['filename']  ?? 'tts_output';

    // --- Validate required parameters ---
    if (!$apiKey || !$textstring) {
        LOGERR("voice_engines/MS_Azure.php: API key or text is missing");
        return;
    }

    LOGINF("voice_engines/MS_Azure.php: Using region='$region', lang='$lang', voice='$voice_ms' for filename='$filename'");

    // --- Define Azure endpoint URIs ---
    $accessTokenUri = "https://{$region}.api.cognitive.microsoft.com/sts/v1.0/issueToken";
    $ttsServiceUri  = "https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1";

    try {
        // --- Step 1: Get Access Token ---
        $ch = curl_init($accessTokenUri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Ocp-Apim-Subscription-Key: {$apiKey}",
            "Content-Length: 0"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $accessToken = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr     = curl_error($ch);
        curl_close($ch);

        if (!$accessToken || $httpCode !== 200) {
            LOGERR("voice_engines/MS_Azure.php: Failed to get access token. HTTP Code: {$httpCode}");
            if (!empty($curlErr)) {
                LOGDEB("voice_engines/MS_Azure.php: cURL error while getting token: {$curlErr}");
            }
            return;
        }

        LOGINF("voice_engines/MS_Azure.php: Access token received successfully");

        // --- Step 2: Prepare SSML payload ---
        $doc  = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement("speak");
        $root->setAttribute("version", "1.0");
        $root->setAttribute("xml:lang", substr($lang, 0, 5));

        $voice = $doc->createElement("voice", htmlspecialchars($textstring, ENT_XML1));
        $voice->setAttribute("xml:lang", substr($lang, 0, 5));
        $voice->setAttribute("name", $voice_ms);

        $root->appendChild($voice);
        $doc->appendChild($root);
        $ssml = $doc->saveXML();

        // --- Step 3: Send TTS request to Azure ---
        $ch = curl_init($ttsServiceUri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/ssml+xml",
            "X-Microsoft-OutputFormat: audio-48khz-192kbitrate-mono-mp3",
            "Authorization: Bearer {$accessToken}",
            "User-Agent: Sonos4Lox-TTSPHP"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $ssml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $audioData = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr   = curl_error($ch);
        curl_close($ch);

        if (!$audioData || $httpCode !== 200) {
            LOGERR("voice_engines/MS_Azure.php: Failed to create MP3. HTTP Code: {$httpCode}");
            if (!empty($curlErr)) {
                LOGDEB("voice_engines/MS_Azure.php: cURL error during TTS request: {$curlErr}");
            }
            // Azure liefert bei 4xx oft ein JSON mit Fehlerdetails:
            if (!empty($audioData)) {
                $snippet = substr($audioData, 0, 300);
                LOGDEB("voice_engines/MS_Azure.php: Azure response snippet: " . $snippet);
            }
            return;
        }

        // --- Step 4: Save audio to file ---
        $filePath = rtrim($config['SYSTEM']['ttspath'], '/') . "/{$filename}.mp3";
        file_put_contents($filePath, $audioData);

        LOGOK("voice_engines/MS_Azure.php: MP3 successfully created at {$filePath}");

    } catch (Exception $e) {
        // --- Step 5: Handle any exceptions ---
        LOGERR("voice_engines/MS_Azure.php: Exception: " . $e->getMessage());
    }
}
?>
