#!/usr/bin/php
<?php

require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";
require_once("$lbphtmldir/system/sonosAccess.php");

require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/Helper.php");

ini_set('max_execution_time', 30); 	
register_shutdown_function('shutdown');

$configfile     = "s4lox_config.json";
$off_file       = $lbplogdir . "/s4lox_off.tmp";                            // path/file for Script turned off
$updatefile     = "/run/shm/" . $lbpplugindir . "/Sonos4lox_update.json";   // Status file during Sonos Update

// ---- Settings ----
$http_timeout_sec = 3;
$icon_timeout_sec = 3;

echo "<PRE>";

// check if script/Sonos Plugin is off
if (file_exists($off_file)) {
	startlog();
    echo "<WARNING> Sonos Plugin is turned off. Please turn on" . PHP_EOL;
	LOGWARN("Sonos Plugin is turned off. Please turn on");
    exit(0);
}

// check if Sonos Firmware Update is running
if (file_exists($updatefile)) {
	startlog();
	LOGERR("Sonos Update is currently running, we abort here...");
    echo "<ERROR> Sonos Update is currently running, we abort here..." . PHP_EOL;
    exit(0);
}

if (file_exists($lbpconfigdir . "/" . $configfile)) {
    $config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), true);
} else {
	startlog();
    echo "<ERROR> The configuration file could not be loaded, the file may be disrupted. We have to abort :-(" . PHP_EOL;
	LOGERR("The configuration file could not be loaded, the file may be disrupted. We have to abort :-(");
    exit(1);
}

// Variables from config
$sonoszonen = $config['sonoszonen'] ?? [];

// check if Online check is turned off in config
if (($config['SYSTEM']['checkonline'] ?? "false") === "false") {
	startlog();
    echo "<ERROR> Online check is not active" . PHP_EOL;
	LOGWARN("Online check is not active");
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

/**
 * normalize_autoplay_volume
 * Ensures the configured autoplay volume is a valid integer between 0 and 100.
 */
function normalize_autoplay_volume($value): int
{
    if ($value === '' || $value === null || !is_numeric($value)) {
        return 0;
    }

    $value = (int)$value;

    if ($value < 0) {
        $value = 0;
    }

    if ($value > 100) {
        $value = 100;
    }

    return $value;
}

/**
 * extract_autoplay_volume_value
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

// check online status per zone
foreach ($sonoszonen as $zonen => $ip) {

    // expecting:
    // [0]  = ip address
    // [4]  = desired autoplay volume
    // [7]  = model icon key
    // [13] = device type ("SB" for soundbar)
    $playerIp           = $ip[0] ?? '';
    $desiredAutoplayVol = normalize_autoplay_volume($ip[4] ?? 0);
    $iconKey            = (string)($ip[7] ?? '');
    $deviceType         = (string)($ip[13] ?? '');
    $isSoundbar         = ($deviceType === "SB");

    if ($playerIp === '') {
        $onFile = "{$statusDir}/s4lox_on_{$zonen}.txt";
        if (file_exists($onFile)) {
			startlog();
            unlink($onFile);
            echo "<INFO> Player Online file 's4lox_on_{$zonen}.txt' has been deleted (invalid config entry)" . PHP_EOL;
			LOGINF("Player Online file 's4lox_on_{$zonen}.txt' has been deleted (invalid config entry)");
        }
        continue;
    }

    $online = is_sonos_device_online($playerIp, $http_timeout_sec);
    $onFile = "{$statusDir}/s4lox_on_{$zonen}.txt";

    if ($online) {
        if (!file_exists($onFile)) {
		startlog();
            file_put_contents($onFile, "on");
			LOGOK("Player Online file 's4lox_on_{$zonen}.txt' has been created");
            echo "<OK> Player Online file 's4lox_on_{$zonen}.txt' has been created" . PHP_EOL;
 
			$img = $lbphtmldir . "/images/icon-{$iconKey}.png";
			fetch_icon_if_missing($playerIp, $iconKey, $img, $icon_timeout_sec);

			try {
				@$sonos = new SonosAccess($playerIp);

				if ($isSoundbar) {
					$currentTvAutoplayRaw = @$sonos->GetAutoplayVolume("TV");
					$currentTvAutoplayVol = extract_autoplay_volume_value($currentTvAutoplayRaw);

					echo "<INFO> Zone '{$zonen}' is a soundbar. Current TV Autoplay Volume: "
						. ($currentTvAutoplayVol !== null ? $currentTvAutoplayVol : "unknown") . PHP_EOL;

					if ($currentTvAutoplayVol === null || $currentTvAutoplayVol !== $desiredAutoplayVol) {
						@$sonos->SetAutoplayVolume($desiredAutoplayVol, "TV");
						echo "<OK> Zone '{$zonen}': TV Autoplay Volume differed from config and has been set to {$desiredAutoplayVol}" . PHP_EOL;
						LOGOK("Zone '{$zonen}': TV Autoplay Volume differed from config and has been set to {$desiredAutoplayVol}");
					} else {
						echo "<INFO> Zone '{$zonen}': TV Autoplay Volume already matches config ({$desiredAutoplayVol})" . PHP_EOL;
					}

					$currentMusicAutoplayRaw = @$sonos->GetAutoplayVolume("Music");
					$currentMusicAutoplayVol = extract_autoplay_volume_value($currentMusicAutoplayRaw);

					echo "<INFO> Zone '{$zonen}' is a soundbar. Current Music Autoplay Volume: "
						. ($currentMusicAutoplayVol !== null ? $currentMusicAutoplayVol : "unknown") . PHP_EOL;

					if ($currentMusicAutoplayVol === null || $currentMusicAutoplayVol !== $desiredAutoplayVol) {
						@$sonos->SetAutoplayVolume($desiredAutoplayVol, "Music");
						echo "<OK> Zone '{$zonen}': Music Autoplay Volume differed from config and has been set to {$desiredAutoplayVol}" . PHP_EOL;
						LOGOK("Zone '{$zonen}': Music Autoplay Volume differed from config and has been set to {$desiredAutoplayVol}");
					} else {
						echo "<INFO> Zone '{$zonen}': Music Autoplay Volume already matches config ({$desiredAutoplayVol})" . PHP_EOL;
					}

					@$sonos->SetUseAutoplayVolume(true, "TV");

				} else {
					$currentMusicAutoplayRaw = @$sonos->GetAutoplayVolume("Music");
					$currentMusicAutoplayVol = extract_autoplay_volume_value($currentMusicAutoplayRaw);

					echo "<INFO> Zone '{$zonen}' is no soundbar. Current Music Autoplay Volume: "
						. ($currentMusicAutoplayVol !== null ? $currentMusicAutoplayVol : "unknown") . PHP_EOL;

					if ($currentMusicAutoplayVol === null || $currentMusicAutoplayVol !== $desiredAutoplayVol) {
						@$sonos->SetAutoplayVolume($desiredAutoplayVol, "Music");
						echo "<OK> Zone '{$zonen}': Music Autoplay Volume differed from config and has been set to {$desiredAutoplayVol}" . PHP_EOL;
						LOGOK("Zone '{$zonen}': Music Autoplay Volume differed from config and has been set to {$desiredAutoplayVol}");
					} else {
						echo "<INFO> Zone '{$zonen}': Music Autoplay Volume already matches config ({$desiredAutoplayVol})" . PHP_EOL;
					}
				}

				@$sonos->SetUseAutoplayVolume(true, "Music");
				@$sonos->SetVolume($desiredAutoplayVol);
				echo "<INFO> Default Volume for '{$zonen}' has been set to ({$desiredAutoplayVol})" . PHP_EOL;
				LOGINF("Default Volume for '{$zonen}' has been set to ({$desiredAutoplayVol})");

			} catch (Exception $e) {
				startlog();
				echo "<ERROR> Zone '{$zonen}': Autoplay handling failed - " . $e->getMessage() . PHP_EOL;
				LOGERR("Zone '{$zonen}': Autoplay handling failed - " . $e->getMessage());
			}
		}
    } else {
        if (file_exists($onFile)) {
            unlink($onFile);
			startlog();
            echo "<INFO> Player Online file 's4lox_on_{$zonen}.txt' has been deleted" . PHP_EOL;
			LOGINF("Player Online file 's4lox_on_{$zonen}.txt' has been deleted");
        }
    }
}

echo "<INFO> Online check for Players has been successful executed" . PHP_EOL;

/**
/* Funktion : startlog --> startet logging
/*
/* @param: Name of Log, filename of Log                        
/* @return: 
**/

function startlog()
{
    if (!empty($GLOBALS['ONLINE_CHECK_STARTED'])) {
        return;
    }

    require_once "loxberry_log.php";
	
    global $lbplogdir, $lbpplugindir, $log;

    $params = [
        "name"     => "Online Check",
        "package"  => $lbpplugindir,
        "filename" => $lbplogdir . "/online_check.log",
        "append"   => 1,
        "addtime"  => 1,
        "loglevel" => 7,
    ];

    $log = LBLog::newLog($params);

    if (empty($log)) {
        echo "ERROR: Could not initialize LoxBerry log.\n";
        return;
    }

    $GLOBALS['ONLINE_CHECK_STARTED'] = true;
    $log->LOGSTART("Online Check");
}

function shutdown()
{
    require_once "loxberry_log.php";
    global $log;

    if (!empty($GLOBALS['ONLINE_CHECK_STARTED']) && !empty($log)) {
        $log->LOGEND("Online Check"); // Methode benutzen, nicht globales LOGEND()
    }
}

?>