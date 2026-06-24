#!/usr/bin/php
<?php

/**
 * Sonos4Lox - Online Check / CheckState
 * Version: CHECKSTATE_ERROR_HANDLER_RELOCATION_V03_2026_06_15
 *
 * Purpose:
 * - Check configured Sonos players for online/offline state.
 * - Maintain PlayerStatus marker files.
 * - Apply autoplay/default volume once a player comes online.
 *
 * Notes:
 * - This file was moved from bin/ to src/Core/Runtime/.
 * - Logging is lazy-started only when there is something relevant to log.
 * - Every explicit log message starts with the relative file context.
 */

require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";
require_once($lbphtmldir . "/src/Support/Logger.php");
require_once($lbphtmldir . "/src/Core/Sonos/sonosAccess.php");
require_once($lbphtmldir . "/src/Support/ErrorHandler.php");
require_once($lbphtmldir . "/Helper.php");

ini_set('max_execution_time', '30');
register_shutdown_function('shutdown');

const S4L_CHECKSTATE_CONTEXT = 'src/Core/Runtime/CheckState.php';

$configfile       = "s4lox_config.json";
$off_file         = $lbplogdir . "/s4lox_off.tmp";                            // Path/file for script turned off
$updatefile       = "/run/shm/" . $lbpplugindir . "/Sonos4lox_update.json";   // Status file during Sonos update
$http_timeout_sec = 3;
$icon_timeout_sec = 3;

$GLOBALS['ONLINE_CHECK_STARTED'] = false;

echo "<PRE>";

// Check if script/Sonos plugin is off.
if (file_exists($off_file)) {
    s4l_log('WARNING', 'Sonos plugin is turned off. Please turn it on.');
    exit(0);
}

// Check if a Sonos firmware update is running.
if (file_exists($updatefile)) {
    s4l_log('ERROR', 'Sonos update is currently running; online check aborted.');
    exit(0);
}

$configPath = $lbpconfigdir . "/" . $configfile;
$config = read_config_file($configPath);
if ($config === null) {
    s4l_log('ERROR', "Configuration file '{$configPath}' could not be loaded or parsed; online check aborted.");
    exit(1);
}

// Variables from config.
$sonoszonen = $config['sonoszonen'] ?? [];
if (!is_array($sonoszonen)) {
    s4l_log('ERROR', "Configuration key 'sonoszonen' is missing or invalid; online check aborted.");
    exit(1);
}

// Check if online check is turned off in config.
if (($config['SYSTEM']['checkonline'] ?? 'false') === 'false') {
    s4l_log('WARNING', 'Online check is not active.');
    exit(0);
}

// Ensure status folder exists.
$statusDir = $lbpdatadir . "/PlayerStatus";
if (!is_dir($statusDir)) {
    if (!@mkdir($statusDir, 0755, true) && !is_dir($statusDir)) {
        s4l_log('ERROR', "Status directory '{$statusDir}' could not be created; online check aborted.");
        exit(1);
    }
    s4l_log('INFO', "Status directory '{$statusDir}' has been created.");
}

// Check online status per zone.
foreach ($sonoszonen as $zonen => $ip) {

    if (!is_array($ip)) {
        s4l_log('WARNING', "Zone '{$zonen}' has an invalid configuration entry and was skipped.");
        continue;
    }

    // Expected indices:
    // [0]  = IP address
    // [4]  = desired autoplay volume
    // [7]  = model icon key
    // [13] = device type ("SB" for soundbar)
    $playerIp           = trim((string)($ip[0] ?? ''));
    $desiredAutoplayVol = normalize_autoplay_volume($ip[4] ?? 0);
    $iconKey            = (string)($ip[7] ?? '');
    $deviceType         = (string)($ip[13] ?? '');
    $isSoundbar         = ($deviceType === 'SB');
    $onFile             = "{$statusDir}/s4lox_on_{$zonen}.txt";

    if ($playerIp === '') {
        if (file_exists($onFile)) {
            if (@unlink($onFile)) {
                s4l_log('INFO', "Player online file 's4lox_on_{$zonen}.txt' has been deleted because the zone has no valid IP address.");
            } else {
                s4l_log('ERROR', "Player online file 's4lox_on_{$zonen}.txt' could not be deleted although the zone has no valid IP address.");
            }
        }
        continue;
    }

    $online = is_sonos_device_online($playerIp, $http_timeout_sec);

    if ($online) {
        if (!file_exists($onFile)) {
            if (@file_put_contents($onFile, 'on', LOCK_EX) === false) {
                s4l_log('ERROR', "Player online file 's4lox_on_{$zonen}.txt' could not be created.");
                continue;
            }

            s4l_log('OK', "Player online file 's4lox_on_{$zonen}.txt' has been created.");

            $img = $lbphtmldir . "/images/icon-{$iconKey}.png";
            fetch_icon_if_missing($playerIp, $iconKey, $img, $icon_timeout_sec, $zonen);
            handle_player_autoplay($zonen, $playerIp, $desiredAutoplayVol, $isSoundbar);
        }
    } else {
        if (file_exists($onFile)) {
            if (@unlink($onFile)) {
                s4l_log('INFO', "Player online file 's4lox_on_{$zonen}.txt' has been deleted.");
            } else {
                s4l_log('ERROR', "Player online file 's4lox_on_{$zonen}.txt' could not be deleted.");
            }
        }
    }
}

s4l_console('INFO', 'Online check for players has been executed successfully.');

/**
 * Prefixes all explicit messages with the relative file context.
 */
function s4l_message(string $message): string
{
    return S4L_CHECKSTATE_CONTEXT . ': ' . $message;
}

/**
 * Writes a console message without forcing the LoxBerry log to start.
 */
function s4l_console(string $level, string $message): void
{
    echo '<' . strtoupper($level) . '> ' . s4l_message($message) . PHP_EOL;
}

/**
 * Writes a console and LoxBerry log message.
 */
function s4l_log(string $level, string $message): void
{
    s4l_console($level, $message);
    startlog();

    $levelMap = [
        'OK'      => S4L_Logger::LEVEL_OK,
        'WARNING' => S4L_Logger::LEVEL_WARNING,
        'WARN'    => S4L_Logger::LEVEL_WARNING,
        'ERROR'   => S4L_Logger::LEVEL_ERROR,
        'FAIL'    => S4L_Logger::LEVEL_ERROR,
        'INFO'    => S4L_Logger::LEVEL_INFO,
    ];

    $logLevel = $levelMap[strtoupper($level)] ?? S4L_Logger::LEVEL_INFO;
    S4L_Logger::write($message, $logLevel, __FILE__);
}

/**
 * Reads and validates the JSON configuration file.
 */
function read_config_file(string $configPath): ?array
{
    if (!is_file($configPath) || !is_readable($configPath)) {
        return null;
    }

    $raw = @file_get_contents($configPath);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $config = json_decode($raw, true);
    if (!is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $config;
}

/**
 * Simple HTTP GET with timeout.
 */
function http_get(string $url, int $timeoutSec): array
{
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeoutSec,
            'ignore_errors' => true,
            'header'        => "User-Agent: Sonos4Lox-OnlineCheck\r\n",
        ],
    ]);

    $data = @file_get_contents($url, false, $ctx);
    $status = 0;

    if (isset($http_response_header) && is_array($http_response_header)) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
    }

    return [$status, $data];
}

/**
 * Checks Sonos via device description XML.
 */
function is_sonos_device_online(string $ip, int $timeoutSec): bool
{
    $url = "http://{$ip}:1400/xml/device_description.xml";
    [$status, $xml] = http_get($url, $timeoutSec);

    if ($status !== 200 || empty($xml)) {
        return false;
    }

    if (stripos($xml, '<root') === false || stripos($xml, '<device') === false) {
        return false;
    }

    $sx = @simplexml_load_string($xml);
    if (!$sx) {
        return false;
    }

    $model = (string)($sx->device->modelName ?? '');
    $friendly = (string)($sx->device->friendlyName ?? '');

    return ($model !== '' || $friendly !== '');
}

/**
 * Fetches the Sonos model icon if it does not exist yet.
 */
function fetch_icon_if_missing(string $ip, string $modelIconKey, string $targetImgPath, int $timeoutSec, string $zone): void
{
    if (file_exists($targetImgPath) || $modelIconKey === '') {
        return;
    }

    $url = "http://{$ip}:1400/img/icon-{$modelIconKey}.png";
    [$status, $png] = http_get($url, $timeoutSec);

    if ($status === 200 && !empty($png)) {
        if (@file_put_contents($targetImgPath, $png, LOCK_EX) !== false) {
            s4l_log('INFO', "Icon file for zone '{$zone}' has been downloaded to '{$targetImgPath}'.");
        } else {
            s4l_log('WARNING', "Icon file for zone '{$zone}' could not be written to '{$targetImgPath}'.");
        }
    }
}

/**
 * Ensures the configured autoplay volume is a valid integer between 0 and 100.
 */
function normalize_autoplay_volume($value): int
{
    if ($value === '' || $value === null || !is_numeric($value)) {
        return 0;
    }

    $value = (int)$value;

    if ($value < 0) {
        return 0;
    }

    if ($value > 100) {
        return 100;
    }

    return $value;
}

/**
 * Tries to normalize different possible SonosAccess return formats to an integer.
 */
function extract_autoplay_volume_value($result): ?int
{
    if (is_numeric($result)) {
        return (int)$result;
    }

    if (is_array($result)) {
        if (isset($result['CurrentVolume']) && is_numeric($result['CurrentVolume'])) {
            return (int)$result['CurrentVolume'];
        }
        if (isset($result['Volume']) && is_numeric($result['Volume'])) {
            return (int)$result['Volume'];
        }
        if (isset($result[0]) && is_numeric($result[0])) {
            return (int)$result[0];
        }
    }

    if (is_object($result)) {
        if (isset($result->CurrentVolume) && is_numeric($result->CurrentVolume)) {
            return (int)$result->CurrentVolume;
        }
        if (isset($result->Volume) && is_numeric($result->Volume)) {
            return (int)$result->Volume;
        }
    }

    return null;
}

/**
 * Handles autoplay/default volume for a player that just became online.
 */
function handle_player_autoplay(string $zone, string $playerIp, int $desiredAutoplayVol, bool $isSoundbar): void
{
    try {
        $sonos = new SonosAccess($playerIp);

        if ($isSoundbar) {
            s4l_console('INFO', "Zone '{$zone}' is a soundbar.");
            apply_autoplay_volume_if_needed($sonos, $zone, 'TV', $desiredAutoplayVol);
            apply_autoplay_volume_if_needed($sonos, $zone, 'Music', $desiredAutoplayVol);
            @$sonos->SetUseAutoplayVolume(true, 'TV');
        } else {
            s4l_console('INFO', "Zone '{$zone}' is not a soundbar.");
            apply_autoplay_volume_if_needed($sonos, $zone, 'Music', $desiredAutoplayVol);
        }

        @$sonos->SetUseAutoplayVolume(true, 'Music');
        @$sonos->SetVolume($desiredAutoplayVol);
        s4l_log('INFO', "Default volume for '{$zone}' has been set to {$desiredAutoplayVol}.");
    } catch (Throwable $e) {
        s4l_log('ERROR', "Zone '{$zone}': Autoplay handling failed - " . $e->getMessage());
    }
}

/**
 * Applies Sonos autoplay volume only if it differs from the configured value.
 */
function apply_autoplay_volume_if_needed($sonos, string $zone, string $source, int $desiredAutoplayVol): void
{
    $currentRaw = @$sonos->GetAutoplayVolume($source);
    $currentVol = extract_autoplay_volume_value($currentRaw);

    s4l_console(
        'INFO',
        "Zone '{$zone}': Current {$source} autoplay volume is " . ($currentVol !== null ? (string)$currentVol : 'unknown') . '.'
    );

    if ($currentVol === null || $currentVol !== $desiredAutoplayVol) {
        @$sonos->SetAutoplayVolume($desiredAutoplayVol, $source);
        s4l_log('OK', "Zone '{$zone}': {$source} autoplay volume differed from config and has been set to {$desiredAutoplayVol}.");
        return;
    }

    s4l_console('INFO', "Zone '{$zone}': {$source} autoplay volume already matches config ({$desiredAutoplayVol}).");
}

/**
 * Starts the LoxBerry log lazily.
 */
function startlog(): void
{
    if (!empty($GLOBALS['ONLINE_CHECK_STARTED'])) {
        return;
    }

    require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";

    global $lbplogdir, $lbpplugindir, $log;

    $params = [
        'name'     => 'Online Check',
        'package'  => $lbpplugindir,
        'filename' => $lbplogdir . '/online_check.log',
        'append'   => 1,
        'addtime'  => 1,
        'loglevel' => 7,
    ];

    $log = LBLog::newLog($params);

    if (empty($log)) {
        echo 'ERROR: ' . s4l_message('Could not initialize LoxBerry log.') . PHP_EOL;
        return;
    }

    $GLOBALS['ONLINE_CHECK_STARTED'] = true;
    $log->LOGSTART('Online Check');
}

/**
 * Ends the LoxBerry log if it was started.
 */
function shutdown(): void
{
    require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";
    global $log;

    if (!empty($GLOBALS['ONLINE_CHECK_STARTED']) && !empty($log)) {
        $log->LOGEND('Online Check');
    }
}

?>
