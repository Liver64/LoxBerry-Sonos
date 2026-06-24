<?php
/**
 * Text-to-Speech (TTS) with ResponsiveVoice
 * Version: VOICE_ENGINE_ROBUSTNESS_V02_2026_06_15
 *
 * @return string|false Returns the filename on success or false on failure
 */

require_once __DIR__ . '/VoiceEngineHelper.php';

function t2s($t2s_param)
{
    global $config;

    $context = 'VoiceEngines/ResponsiveVoice.php';

    $filename = s4l_ve_safe_filename((string)($t2s_param['filename'] ?? 'tts_output'));
    $text     = $t2s_param['text'] ?? '';
    $language = $t2s_param['language'] ?? '';

    if (!s4l_ve_require_params([
        'filename' => $filename,
        'text' => $text,
        'language' => $language,
    ], ['filename', 'text', 'language'], $context)) {
        return false;
    }

    // Keep legacy default key, but allow future config/parameter override.
    // Important: Some callers provide an empty 'apikey' field. The old engine
    // always used the bundled ResponsiveVoice key in that case. Treat empty
    // values as missing to preserve that behavior.
    $apiKey = trim((string)($t2s_param['apikey'] ?? ''));
    if ($apiKey === '') {
        $apiKey = trim((string)($config['TTS']['responsivevoice_key'] ?? ''));
    }
    if ($apiKey === '') {
        $apiKey = 'WQAwyp72';
    }

    $langFilePath = LBPHTMLDIR . '/VoiceEngines/langfiles/respvoice.json';
    $validLanguages = s4l_ve_load_json($langFilePath, $context);
    if ($validLanguages === false) {
        return false;
    }

    $allowedLanguages = array_column($validLanguages, 'value');
    if (!in_array($language, $allowedLanguages, true)) {
        s4l_ve_log($context, 'ERROR', "Language '$language' is not supported.");
        return false;
    }

    // Preserve the legacy request format exactly enough for ResponsiveVoice:
    // urlencode() encodes spaces as '+', matching the pre-helper implementation.
    $apiUrl = sprintf(
        'https://code.responsivevoice.org/getvoice.php?t=%s&tl=%s&key=%s',
        urlencode((string)$text),
        urlencode((string)$language),
        urlencode((string)$apiKey)
    );

    $maskedUrl = preg_replace('/([?&]key=)[^&]*/', '$1***', $apiUrl);
    s4l_ve_log($context, 'OK', "Sending TTS request to ResponsiveVoice API: $maskedUrl");

    $mp3Data = s4l_ve_curl_request($apiUrl, [
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 Sonos4Lox ResponsiveVoice TTS',
        CURLOPT_HTTPHEADER => [
            'Accept: audio/mpeg, audio/*;q=0.9, */*;q=0.8',
        ],
    ], $context);

    if ($mp3Data === false || strlen((string)$mp3Data) < 100) {
        s4l_ve_log($context, 'WARNING', 'ResponsiveVoice request failed or returned an empty/invalid response. Falling back to next configured TTS engine if available.');
        return false;
    }

    $outputFile = s4l_ve_output_path($config, $filename, $context);
    return s4l_ve_write_mp3($outputFile, $mp3Data, $context);
}
?>
