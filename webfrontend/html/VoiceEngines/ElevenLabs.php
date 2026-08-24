<?php
/**
 * ElevenLabs TTS Integration
 * Version: VOICE_ENGINE_ROBUSTNESS_V02_2026_08_24
 *
 * Uses voice_id directly to avoid "voice not found" errors.
 */

require_once __DIR__ . '/VoiceEngineHelper.php';

function t2s(array $params)
{
    global $config;

    $context = 'VoiceEngines/ElevenLabs.php';
    s4l_ve_clear_last_error();

    $params['filename'] = $params['filename'] ?? 'tts_output';

    if (!s4l_ve_require_params($params, ['apikey', 'text', 'voice'], $context)) {
        return false;
    }

    $apikey   = (string)$params['apikey'];
    $filename = s4l_ve_safe_filename((string)$params['filename']);
    $text     = (string)$params['text'];
    $voice_id = (string)$params['voice'];

    // Default TTS settings
    $audio_format      = 'mp3_44100_128';
    $model_id          = 'eleven_multilingual_v2';
    $stability         = 0.5;
    $similarity_boost  = 0.75;
    $style             = 0;
    $use_speaker_boost = true;

    s4l_ve_log($context, 'OK', 'ElevenLabs TTS selected.');

    return elevenlabs_generate_speech(
        $text,
        $voice_id,
        $filename,
        $apikey,
        $model_id,
        $audio_format,
        $stability,
        $similarity_boost,
        $style,
        $use_speaker_boost,
        $context,
        $config
    );
}

/**
 * Generate Text-to-Speech using ElevenLabs API.
 *
 * @return string|false filename without extension on success, false on failure
 */
function elevenlabs_generate_speech(
    string $text,
    string $voice_id,
    string $filename,
    string $apikey,
    string $model_id,
    string $audio_format,
    float $stability,
    float $similarity_boost,
    float $style,
    bool $use_speaker_boost,
    string $context,
    array $config
) {
    $payload = [
        'model_id' => $model_id,
        'text' => $text,
        'voice_settings' => [
            'stability' => $stability,
            'similarity_boost' => $similarity_boost,
            'style' => $style,
            'use_speaker_boost' => $use_speaker_boost,
        ],
    ];

    $url = 'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($voice_id) . '?output_format=' . rawurlencode($audio_format);

    $response = s4l_ve_curl_request($url, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'xi-api-key: ' . $apikey,
        ],
        CURLOPT_TIMEOUT => 45,
    ], $context, 'elevenlabs');

    if ($response === false) {
        return false;
    }

    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['detail'])) {
        $error = s4l_ve_resolve_provider_error('elevenlabs', 200, $response);
        s4l_ve_log_provider_error($context, $error);
        return false;
    }

    $file = s4l_ve_output_path($config, $filename, $context);
    return s4l_ve_write_mp3($file, $response, $context);
}
?>
