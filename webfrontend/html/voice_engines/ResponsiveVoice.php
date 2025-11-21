<?php
/**
 * Text-to-Speech (TTS) with ResponsiveVoice
 *
 * This function generates an MP3 audio file from a given text using the ResponsiveVoice API
 * and saves it locally.
 *
 * @param array $t2s_param [
 *     'filename' => (string) File name without extension,
 *     'text'     => (string) The text to be spoken,
 *     'language' => (string) Language code, e.g. "de-de"
 * ]
 * @return string|false Returns the filename on success or false on failure
 */
function t2s($t2s_param)
{
    global $config, $pathlanguagefile;

    // === Extract parameters ===
    $filename  = $t2s_param['filename'] ?? null;
    $text      = $t2s_param['text'] ?? null;
    $language  = $t2s_param['language'] ?? null;

    // === Basic validation ===
    if (empty($filename) || empty($text) || empty($language)) {
        LOGERR("ResponsiveVoice.php: Missing required parameters (filename, text, language).");
        return false;
    }

    // === ResponsiveVoice API key ===
    $apiKey = "WQAwyp72"; // Your API key (should ideally be stored in config)

    // === Load valid language list from JSON ===
    $langFilePath = LBPHTMLDIR . "/voice_engines/langfiles/respvoice.json";
    if (!file_exists($langFilePath)) {
        LOGERR("ResponsiveVoice.php: Language file '$langFilePath' not found.");
        return false;
    }

    $validLanguages = json_decode(file_get_contents($langFilePath), true);
    if (!is_array($validLanguages)) {
        LOGERR("ResponsiveVoice.php: Language file '$langFilePath' is invalid or corrupted.");
        return false;
    }

    // === Check if provided language is supported ===
    $allowedLanguages = array_column($validLanguages, 'value');
    if (!in_array($language, $allowedLanguages, true)) {
        LOGERR("ResponsiveVoice.php: Language '$language' is not supported.");
        return false;
    }

    // === Encode text for URL ===
    $encodedText = urlencode($text);

    // === Define output path for MP3 file ===
    $outputFile = rtrim($config['SYSTEM']['ttspath'], "/") . "/" . $filename . ".mp3";

    // === Build API request URL ===
    $apiUrl = sprintf(
        'https://code.responsivevoice.org/getvoice.php?t=%s&tl=%s&key=%s',
        $encodedText,
        urlencode($language),
        $apiKey
    );

    LOGOK("ResponsiveVoice.php: Sending TTS request to API: $apiUrl");

    // === Fetch MP3 data from API ===
    $mp3Data = my_curl($apiUrl);

    // === Validate API response ===
    if ($mp3Data === false || strlen($mp3Data) < 100) {
        LOGERR("ResponsiveVoice.php: API request failed or returned an empty/invalid response.");
        return false;
    }

    // === Save MP3 file ===
    if (file_put_contents($outputFile, $mp3Data) === false) {
        LOGERR("ResponsiveVoice.php: Failed to save MP3 file '$outputFile'.");
        return false;
    }

    LOGOK("ResponsiveVoice.php: MP3 file successfully created: $outputFile");

    return $filename;
}

/**
 * Helper function for HTTP requests using cURL
 *
 * @param string $url
 * @return string|false
 */
function my_curl($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $data = curl_exec($ch);

    if (curl_errno($ch)) {
        LOGERR("cURL error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        LOGERR("ResponsiveVoice.php: HTTP error: Status code $httpCode for URL: $url");
        return false;
    }

    return $data;
}
?>
