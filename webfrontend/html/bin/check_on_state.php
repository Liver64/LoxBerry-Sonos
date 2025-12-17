#!/usr/bin/php
<?php

require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";

require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/Helper.php");

$configfile     = "s4lox_config.json";
$off_file       = $lbplogdir . "/s4lox_off.tmp";              				// path/file for Script turned off
$updatefile     = "/run/shm/" . $lbpplugindir. "/Sonos4lox_update.json";    // Status file during Sonos Update

// ---- Settings ----
$http_timeout_sec = 3;
$icon_timeout_sec = 3;

echo "<PRE>";

// check if script/Sonos Plugin is off
if (file_exists($off_file)) {
    echo "<WARNING> Sonos Plugin is turned off. Please turn on" . PHP_EOL;
    exit(0);
}

// check if Sonos Firmware Update is running
if (file_exists($updatefile)) {
    echo "<ERROR> Sonos Update is currently running, we abort here..." . PHP_EOL;
    exit(0);
}

if (file_exists($lbpconfigdir . "/" . $configfile)) {
    $config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), true);
} else {
    echo "<ERROR> The configuration file could not be loaded, the file may be disrupted. We have to abort :-(" . PHP_EOL;
    exit(1);
}

// Variables from config
$sonoszonen = $config['sonoszonen'] ?? [];

// check if Online check is turned off in config
if (($config['SYSTEM']['checkonline'] ?? "false") === "false") {
    echo "<ERROR> Online check is not active" . PHP_EOL;
    exit(0);
}

// ensure folder exists
$statusDir = "$lbpdatadir/PlayerStatus";
if (!is_dir($statusDir)) {
    mkdir($statusDir, 0755, true);
}

/**
 * http_get
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
        ]
    ]);

    $data = @file_get_contents($url, false, $ctx);
    $status = 0;

    if (isset($http_response_header) && is_array($http_response_header)) {
        // parse "HTTP/1.1 200 OK"
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
    }
    return [$status, $data];
}

/**
 * is_sonos_device_online
 * Checks Sonos via device description xml.
 */
function is_sonos_device_online(string $ip, int $timeoutSec): bool
{
    $url = "http://{$ip}:1400/xml/device_description.xml";
    [$status, $xml] = http_get($url, $timeoutSec);

    if ($status !== 200 || empty($xml)) {
        return false;
    }

    // quick sanity check before parsing
    if (stripos($xml, '<root') === false || stripos($xml, '<device') === false) {
        return false;
    }

    // XML parse (suppress warnings)
    $sx = @simplexml_load_string($xml);
    if (!$sx) return false;

    // Expect a Sonos-ish device description structure: root->device->modelName or friendlyName exists
    $model = (string)($sx->device->modelName ?? '');
    $friendly = (string)($sx->device->friendlyName ?? '');

    return ($model !== '' || $friendly !== '');
}

/**
 * fetch_icon_if_missing
 */
function fetch_icon_if_missing(string $ip, string $modelIconKey, string $targetImgPath, int $timeoutSec): void
{
    if (file_exists($targetImgPath)) {
        return;
    }
    if ($modelIconKey === '') {
        return;
    }

    $url = "http://{$ip}:1400/img/icon-{$modelIconKey}.png";
    [$status, $png] = http_get($url, $timeoutSec);

    if ($status === 200 && !empty($png)) {
        @file_put_contents($targetImgPath, $png);
    }
}

// check online status per zone
foreach ($sonoszonen as $zonen => $ip) {

    // expecting $ip[0] = ip address, $ip[7] = model icon key (as in your current code)
    $playerIp = $ip[0] ?? '';
    $iconKey  = (string)($ip[7] ?? '');

    if ($playerIp === '') {
        // config entry incomplete
        $onFile = "{$statusDir}/s4lox_on_{$zonen}.txt";
        if (file_exists($onFile)) {
            unlink($onFile);
            echo "<INFO> Player Online file 's4lox_on_{$zonen}.txt' has been deleted (invalid config entry)" . PHP_EOL;
        }
        continue;
    }

    $online = is_sonos_device_online($playerIp, $http_timeout_sec);

    $onFile = "{$statusDir}/s4lox_on_{$zonen}.txt";

    if ($online) {
        // create tmp file for each zone online
        if (!file_exists($onFile)) {
            file_put_contents($onFile, "on");
            echo "<OK> Player Online file 's4lox_on_{$zonen}.txt' has been created" . PHP_EOL;
        }

        // get Player Mini PNG (if missing)
        $img = $lbphtmldir . "/images/icon-{$iconKey}.png";
        fetch_icon_if_missing($playerIp, $iconKey, $img, $icon_timeout_sec);

    } else {
        if (file_exists($onFile)) {
            unlink($onFile);
            echo "<INFO> Player Online file 's4lox_on_{$zonen}.txt' has been deleted" . PHP_EOL;
        }
        // optional: you could log WARN here, but keeping your current behavior
    }
}

echo "<INFO> Online check for Players has been successful executed" . PHP_EOL;

?>
