<?php

/**
 * ElevenLabs TTS Integration
 * Uses voice_id directly to avoid "voice not found" errors
 */

function t2s(array $params): void
{
    global $config;

    $apikey   = $params['apikey'] ?? '';
    $filename = $params['filename'] ?? 'tts_output';
    $text     = $params['text'] ?? '';
    $voice_id = $params['voice'] ?? ''; // Now directly the voice_id from ElevenLabs

    // Default TTS settings
    $audio_format      = "mp3_44100_128";
    $model_id          = "eleven_multilingual_v2";
    $stability         = 0.5;
    $similarity_boost  = 0.75;
    $style             = 0;
    $use_speaker_boost = true;

    // Validate parameters
    if (!$apikey || !$text || !$voice_id) {
        LOGERR("voice_engines/ElevenLabs.php: Missing required parameters for TTS.");
        return;
    }

    LOGOK("voice_engines/ElevenLabs.php: ElevenLabs TTS selected");

    // Generate speech directly using voice_id
    generateSpeech($text, $voice_id, $filename, $apikey, $model_id, $audio_format, $stability, $similarity_boost, $style, $use_speaker_boost);
}


/**
 * Generate Text-to-Speech using ElevenLabs API
 *
 * Steps:
 * 1. Build JSON payload for ElevenLabs API
 * 2. Send POST request via cURL with API key
 * 3. Check for cURL errors and API errors
 * 4. Save the resulting MP3 file
 */
function generateSpeech($text, $voice_id, $filename, $apikey, $model_id, $audio_format, $stability, $similarity_boost, $style, $use_speaker_boost)
{
    global $config;

    // Step 1: Prepare JSON payload
    $payload = [
        "model_id" => $model_id,
        "text" => $text,
        "voice_settings" => [
            "stability" => $stability,
            "similarity_boost" => $similarity_boost,
            "style" => $style,
            "use_speaker_boost" => $use_speaker_boost
        ]
    ];

    // Step 2: Send POST request to ElevenLabs TTS API
    $ch = curl_init("https://api.elevenlabs.io/v1/text-to-speech/$voice_id?output_format=$audio_format");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "xi-api-key: $apikey"
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    // Step 3: Handle errors
    if ($err) {
        LOGERR("voice_engines/ElevenLabs.php: cURL error: $err");
        return;
    }

    $result = json_decode($response, true);

    if (isset($result['detail'])) {
        LOGERR("voice_engines/ElevenLabs.php: API error: {$result['detail']['message']} (status {$result['detail']['status']})");
        return;
    }

    // Step 4: Save MP3 file
    $file = rtrim($config['SYSTEM']['ttspath'], '/') . "/$filename.mp3";
    file_put_contents($file, $response);
    LOGOK("voice_engines/ElevenLabs.php: MP3 file successfully saved to $file");
}

?>



