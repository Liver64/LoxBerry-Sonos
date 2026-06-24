<?php
/**
 * Sonos4Lox - PlayerOnTest endpoint
 *
 * PLAYER_ON_TEST_LOGGING_V02_2026_06_19
 *
 * Direct AJAX endpoint for test voice output from the player/zone table.
 *
 * Responsibilities:
 *  - Accept POST parameters room, text and optional T2S parameters.
 *  - Validate the room against s4lox_config.json.
 *  - Resolve the default Sonos volume from sonoszonen[room][4] if no POST volume was supplied.
 *  - Remove possible TTS cache files for the requested text.
 *  - Trigger the regular Sonos4Lox T2S flow via index.php?action=say&nocache=1.
 *
 * JSON response:
 *  { success: bool, message: string, deleted: array }
 */

const S4L_PLAYER_ON_TEST_CONTEXT = 'src/Support/PlayerOnTest.php';
const S4L_PLAYER_ON_TEST_VERSION = 'PLAYER_ON_TEST_LOGGING_V02_2026_06_19';

/**
 * Load LoxBerry system library from include_path, the default absolute path,
 * or the path derived from this plugin source location.
 */
function s4l_player_on_test_load_loxberry_system(): bool
{
    if (class_exists('LBSystem') || function_exists('lbwebserverport')) {
        return true;
    }

    $candidates = [
        'loxberry_system.php',
        'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php',
        dirname(__DIR__, 6) . '/libs/phplib/loxberry_system.php',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === 'loxberry_system.php' || is_readable($candidate)) {
            @include_once $candidate;
            if (class_exists('LBSystem') || function_exists('lbwebserverport')) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Write a log entry without polluting the JSON response.
 */
function s4l_player_on_test_log(string $message, int $level = 7): void
{
    $loggerFile = __DIR__ . '/Logger.php';
    if (!class_exists('S4L_Logger') && is_readable($loggerFile)) {
        require_once $loggerFile;
    }

    if (class_exists('S4L_Logger')) {
        S4L_Logger::write($message, $level, __FILE__);
        return;
    }

    $line = S4L_PLAYER_ON_TEST_CONTEXT . ': ' . $message;
    $function = s4l_player_on_test_native_log_function($level);
    if ($function !== '' && function_exists($function)) {
        $function($line);
        return;
    }

    error_log($line);
}

function s4l_player_on_test_native_log_function(int $level): string
{
    switch ($level) {
        case 3:
            return 'LOGERR';
        case 4:
            return 'LOGWARN';
        case 5:
            return 'LOGOK';
        case 6:
            return 'LOGINF';
        case 7:
            return 'LOGDEB';
        default:
            return 'LOGDEB';
    }
}

/**
 * Send JSON and stop execution.
 */
function s4l_player_on_test_json(array $response, int $httpStatus = 200): void
{
    if (!headers_sent()) {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Resolve the plugin HTML directory.
 */
function s4l_player_on_test_plugin_html_dir(): string
{
    global $lbphtmldir;

    if (defined('LBPHTMLDIR') && LBPHTMLDIR !== '') {
        return rtrim(LBPHTMLDIR, '/');
    }

    if (!empty($lbphtmldir)) {
        return rtrim($lbphtmldir, '/');
    }

    // This file lives in src/Support, therefore two levels up is the plugin root.
    return rtrim(dirname(__DIR__, 2), '/');
}

/**
 * Resolve the plugin config directory.
 */
function s4l_player_on_test_plugin_config_dir(): string
{
    global $lbpconfigdir;

    if (defined('LBPCONFIGDIR') && LBPCONFIGDIR !== '') {
        return rtrim(LBPCONFIGDIR, '/');
    }

    if (!empty($lbpconfigdir)) {
        return rtrim($lbpconfigdir, '/');
    }

    return 'REPLACELBHOMEDIR/config/plugins/sonos4lox';
}

/**
 * Load the Sonos4Lox config safely.
 */
function s4l_player_on_test_load_config(string $configPath): array
{
    if (!is_readable($configPath)) {
        throw new RuntimeException("Configuration file 's4lox_config.json' could not be loaded.");
    }

    $raw = file_get_contents($configPath);
    if ($raw === false || trim($raw) === '') {
        throw new RuntimeException("Configuration file 's4lox_config.json' is empty or not readable.");
    }

    $config = json_decode($raw, true);
    if (!is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Configuration file 's4lox_config.json' is not valid JSON: " . json_last_error_msg());
    }

    if (!isset($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
        throw new RuntimeException("Configuration does not contain a valid 'sonoszonen' section.");
    }

    return $config;
}

/**
 * Remove possible cache files for the given TTS text.
 */
function s4l_player_on_test_clear_tts_cache_for_text(string $text, array $config): array
{
    $deleted = [];
    $hash = md5($text);
    $candidates = [];

    if (!empty($config['SYSTEM']['ttspath'])) {
        $ttspath = rtrim((string)$config['SYSTEM']['ttspath'], '/');
        $candidates[] = $ttspath . '/' . $hash . '.mp3';
        $candidates[] = $ttspath . '/' . $hash . '.wav';
    }

    if (!empty($config['SYSTEM']['mp3path'])) {
        $mp3path = rtrim((string)$config['SYSTEM']['mp3path'], '/');
        $candidates[] = $mp3path . '/' . $hash . '.mp3';
        $candidates[] = $mp3path . '/' . $hash . '.wav';
    }

    if (!empty($config['SYSTEM']['cifsinterface']) && strpos((string)$config['SYSTEM']['cifsinterface'], '://') === false) {
        $cifs = rtrim((string)$config['SYSTEM']['cifsinterface'], '/');
        $candidates[] = $cifs . '/' . $hash . '.mp3';
        $candidates[] = $cifs . '/' . $hash . '.wav';
        $candidates[] = $cifs . '/mp3/' . $hash . '.mp3';
        $candidates[] = $cifs . '/mp3/' . $hash . '.wav';
    }

    $candidates = array_values(array_unique(array_filter($candidates)));

    foreach ($candidates as $file) {
        if (!is_file($file)) {
            continue;
        }

        if (@unlink($file)) {
            $deleted[] = $file;
            s4l_player_on_test_log("Deleted TTS cache file '$file'.", 7);
        } else {
            s4l_player_on_test_log("Could not delete TTS cache file '$file'.", 4);
        }
    }

    return $deleted;
}

/**
 * Execute optional legacy cache cleanup while keeping the JSON response clean.
 */
function s4l_player_on_test_delete_all_cache_if_available(): void
{
    if (!function_exists('delete_all_cache')) {
        return;
    }

    ob_start();
    try {
        delete_all_cache();
    } catch (Throwable $e) {
        ob_end_clean();
        s4l_player_on_test_log('delete_all_cache() failed: ' . $e->getMessage(), 4);
        return;
    }
    $output = ob_get_clean();

    if (trim((string)$output) !== '') {
        s4l_player_on_test_log('delete_all_cache() produced output which was suppressed for JSON response.', 7);
    }
}

/**
 * Build the local plugin base URL.
 */
function s4l_player_on_test_local_plugin_url(): string
{
    $lbIp = '127.0.0.1';
    if (class_exists('LBSystem')) {
        try {
            $detectedIp = LBSystem::get_localip();
            if (!empty($detectedIp)) {
                $lbIp = $detectedIp;
            }
        } catch (Throwable $e) {
            s4l_player_on_test_log('Could not resolve local LoxBerry IP, using 127.0.0.1: ' . $e->getMessage(), 4);
        }
    }

    $webport = 80;
    if (function_exists('lbwebserverport')) {
        $resolvedPort = (int)lbwebserverport();
        if ($resolvedPort > 0) {
            $webport = $resolvedPort;
        }
    }

    $protocol = ($webport === 443) ? 'https' : 'http';
    $hostPart = ($webport === 80 || $webport === 443) ? $lbIp : $lbIp . ':' . $webport;

    return $protocol . '://' . $hostPart . '/plugins/sonos4lox';
}

/**
 * Call the regular Sonos4Lox T2S endpoint.
 */
function s4l_player_on_test_call_tts(array $params): array
{
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $ttsUrl = s4l_player_on_test_local_plugin_url() . '/index.php?' . $query;

    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 20,
            'header'  => "Connection: close\r\n",
        ],
    ]);

    $http_response_header = [];
    $result = @file_get_contents($ttsUrl, false, $context);

    $statusCode = null;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $headerLine, $matches)) {
                $statusCode = (int)$matches[1];
            }
        }
    }

    if ($result === false) {
        return [false, 'Call to internal TTS endpoint failed. Please check Sonos4Lox logs.'];
    }

    if ($statusCode !== null && $statusCode >= 400) {
        return [false, "Internal TTS endpoint returned HTTP status $statusCode. Please check Sonos4Lox logs."];
    }

    return [true, 'Internal TTS endpoint accepted the request.'];
}

$response = [
    'success' => false,
    'message' => 'Unknown error',
    'deleted' => [],
];

if (!s4l_player_on_test_load_loxberry_system()) {
    s4l_player_on_test_log('LoxBerry system library could not be loaded.', 3);
    s4l_player_on_test_json([
        'success' => false,
        'message' => 'LoxBerry system library could not be loaded.',
        'deleted' => [],
    ], 500);
}

$pluginHtmlDir = s4l_player_on_test_plugin_html_dir();
$helperFile = $pluginHtmlDir . '/helper.php';

if (is_readable($helperFile)) {
    ob_start();
    try {
        require_once $helperFile;
    } catch (Throwable $e) {
        ob_end_clean();
        s4l_player_on_test_log('Could not load helper.php: ' . $e->getMessage(), 4);
    }
    $helperOutput = ob_get_clean();
    if (trim((string)$helperOutput) !== '') {
        s4l_player_on_test_log('helper.php produced output which was suppressed for JSON response.', 7);
    }
} else {
    s4l_player_on_test_log("Optional helper.php was not found at '$helperFile'.", 7);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    s4l_player_on_test_json([
        'success' => false,
        'message' => 'Invalid request method. Use POST.',
        'deleted' => [],
    ], 405);
}

$room = trim((string)($_POST['room'] ?? ''));
$text = trim((string)($_POST['text'] ?? ''));
$volume = isset($_POST['volume']) && $_POST['volume'] !== '' ? (int)$_POST['volume'] : null;

$t2sengine = trim((string)($_POST['t2sengine'] ?? ''));
$language  = trim((string)($_POST['language'] ?? ''));
$voice     = trim((string)($_POST['voice'] ?? ''));
$apikey    = trim((string)($_POST['apikey'] ?? ''));
$secretkey = trim((string)($_POST['secretkey'] ?? ''));

if ($room === '') {
    s4l_player_on_test_json([
        'success' => false,
        'message' => 'No room has been provided.',
        'deleted' => [],
    ], 400);
}

if ($text === '') {
    $text = 'Test voice output for room ' . $room;
}

try {
    $configPath = s4l_player_on_test_plugin_config_dir() . '/s4lox_config.json';
    $config = s4l_player_on_test_load_config($configPath);

    $sonoszonen = $config['sonoszonen'];
    if (!array_key_exists($room, $sonoszonen)) {
        throw new RuntimeException("Room '$room' was not found in configuration.");
    }

    $zoneData = is_array($sonoszonen[$room]) ? $sonoszonen[$room] : [];

    if ($volume === null) {
        $volume = 35;
        if (isset($zoneData[4]) && is_numeric($zoneData[4])) {
            $volume = (int)$zoneData[4];
        }
    }

    $volume = max(0, min(100, $volume));

    $response['deleted'] = s4l_player_on_test_clear_tts_cache_for_text($text, $config);
    s4l_player_on_test_delete_all_cache_if_available();

    $params = [
        'zone'     => $room,
        'action'   => 'say',
        'text'     => $text,
        'volume'   => $volume,
        'playgong' => 'yes',
        'nocache'  => 1,
    ];

    if ($t2sengine !== '') { $params['t2sengine'] = $t2sengine; }
    if ($language  !== '') { $params['language']  = $language;  }
    if ($voice     !== '') { $params['voice']     = $voice;     }
    if ($apikey    !== '') { $params['apikey']    = $apikey;    }
    if ($secretkey !== '') { $params['secretkey'] = $secretkey; }

    [$success, $message] = s4l_player_on_test_call_tts($params);

    if (!$success) {
        s4l_player_on_test_log("T2S test call for room '$room' failed: $message", 4);
        $response['success'] = false;
        $response['message'] = $message;
        s4l_player_on_test_json($response, 502);
    }

    s4l_player_on_test_log("T2S test call for room '$room' started successfully with volume '$volume'.", 7);

    $response['success'] = true;
    $response['message'] = "T2S test call for room '$room' started successfully.";
    s4l_player_on_test_json($response);
} catch (Throwable $e) {
    s4l_player_on_test_log('Request failed: ' . $e->getMessage(), 4);
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    s4l_player_on_test_json($response, 500);
}
