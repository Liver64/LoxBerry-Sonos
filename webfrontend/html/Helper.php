<?php

/**
 * Submodul: Helper
 * Version: HELPER_HEALTH_CLI_LOGGING_FIX_V01_2026_06_19
 *
 * HelperRelocation Final V01 moves the remaining selected helper functions into their owning PHP files.
 * Helper.php keeps loading the related legacy PHP files for compatibility with global calls.
 */

if (file_exists(__DIR__ . '/src/Support/Logger.php')) {
	require_once __DIR__ . '/src/Support/Logger.php';
}

/* Compatibility: player/model helper functions were moved to Speaker.php. */
require_once __DIR__ . '/Speaker.php';

/* Compatibility: group/topology helper functions were moved to Grouping.php. */
require_once __DIR__ . '/Grouping.php';

/* Compatibility: playlist/playmode helper functions were moved to Playlist.php. */
require_once __DIR__ . '/Playlist.php';

/* Compatibility: metadata helper functions were moved to Metadata.php. */
require_once __DIR__ . '/Metadata.php';

/* Compatibility: queue/playback helper functions were moved to Queue.php. */
require_once __DIR__ . '/Queue.php';

/* Compatibility: TTS helper functions were moved to Play_T2S.php. */
require_once __DIR__ . '/Play_T2S.php';


/**
/* Function : checkZoneOnline --> Prüft ob einzelner Player Online ist
/*
/* @param:  Player der geprüft werden soll
/* @return: true or nothing
**/

function checkZoneOnline($MemberTest)   {
	
	global $sonoszone, $sonoszonen, $debug, $config, $folfilePlOn;

	if ($MemberTest == 'all')   {
		return false;
	}
	if(!array_key_exists($MemberTest, $sonoszonen)) {
		LOGERR("Helper.php: The entered Zone '".$MemberTest."' does not exist. Please correct your syntax!!");
		exit;
	}
	$handle = is_file($folfilePlOn."".$MemberTest.".txt");
	if($handle === true) {
		if (array_key_exists($MemberTest, $sonoszone)) {
			$zoneon = true;
			return($zoneon);
		}
	}
}

/**
/* Function : checkOnline --> Checks whether a configured player is currently available
/*
/* @param:  Player name
/* @return: string "true" or "false"
**/

function checkOnline($MemberTest) {
	global $sonoszone, $sonoszonen, $folfilePlOn;

	if ($MemberTest == 'all' || $MemberTest === '') {
		return "false";
	}

	if (!isset($sonoszonen) || !is_array($sonoszonen) || !array_key_exists($MemberTest, $sonoszonen)) {
		LOGWARN("Helper.php: The entered Zone '".$MemberTest."' does not exist. Returning offline state.");
		return "false";
	}

	$handle = isset($folfilePlOn) ? is_file($folfilePlOn."".$MemberTest.".txt") : false;
	if ($handle === true && isset($sonoszone) && is_array($sonoszone) && array_key_exists($MemberTest, $sonoszone)) {
		return "true";
	}

	return "false";
}

/**
/* Function : sonoszonen_on --> welche Player Online ist
/*
/* @param:  
/* @return: file created
**/
function sonoszonen_on()    {
	
	global $config, $sonoszonen, $folfilePlOn;
	
	$sonoszone = array();
	$memberon = array();
	$act_time = date("H:i"); #"16:58"
	foreach($sonoszonen as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			if (is_enabled($config['SYSTEM']['checkonline'])) {
				if ($sonoszonen[$zonen][15] != "" and $sonoszonen[$zonen][16] != "")   {
					$startime = $sonoszonen[$zonen][15]; #"07:15"
					$endtime = $sonoszonen[$zonen][16]; #"20:32"
					if ((string)$startime <= (string)$act_time and (string)$endtime >= (string)$act_time)   {
						$sonoszone[$zonen] = $ip;
						echo $zonen;
					}
				} else {
					$sonoszone[$zonen] = $ip;
				}
			} else {
				$sonoszone[$zonen] = $ip;
			}
		}
	}
	return $sonoszone;
}

/**
*
* Function : get_file_content --> übermittelt die Titel/Interpret Info an Loxone
* http://stackoverflow.com/questions/697472/php-file-get-contents-returns-failed-to-open-stream-http-request-failed
*
* @param: 	URL = virtueller Texteingangsverbinder
* @return: string (Titel/Interpret Info)
**/

function get_file_content($url) {
	
	$curl_handle=curl_init();
	curl_setopt($curl_handle, CURLOPT_URL,$url);
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_handle, CURLOPT_USERAGENT, 'LOXONE');
	$query = curl_exec($curl_handle);
	curl_close($curl_handle);
}


/**
* Function : File_Get_Array_From_JSON --> liest eine JSON Datei ein und erstellt eine Array
*
* @param: 	Dateiname
* @return: Array
**/	

function File_Get_Array_From_JSON($FileName, $zip=false) {
    if (! is_file($FileName)) 	{ LOGERR("Helper.php: The file $FileName does not exist."); exit; }
		if (! is_readable($FileName))	{ LOGERR("Helper.php: The file $FileName could not be loaded."); exit;}
            if (! $zip) {
				return json_decode(file_get_contents($FileName), true);
            } else {
				return json_decode(gzuncompress(file_get_contents($FileName)), true);
	    }
}


/**
* Function : create_symlinks() --> check if symlinks for interface are there, if not create them
*
* @param: empty
* @return: symlinks created 
**/

function create_symlinks()  {

	global $config, $ttsfolder, $mp3folder, $myFolder, $lbphtmldir, $myip;

	$symcurr_path = $config['SYSTEM']['path'];
	$symttsfolder = $config['SYSTEM']['ttspath'];
	$symmp3folder = $config['SYSTEM']['mp3path'];

	$copy = false;
	if (!is_dir($symmp3folder)) {
		$copy = true;
	}

	/* --- Create folders (logging only on WARNING/ERROR) --- */

	if (!is_dir($symttsfolder)) {
		$ok = @mkdir($symttsfolder, 0755, true);
		if (!$ok && !is_dir($symttsfolder)) {
			$err = error_get_last();
			$msg = $err['message'] ?? 'unknown error';
			LOGERR("Helper.php: Error creating folder '".$symttsfolder."': ".$msg);
		}
	}

	if (!is_dir($symmp3folder)) {
		$ok = @mkdir($symmp3folder, 0755, true);
		if (!$ok && !is_dir($symmp3folder)) {
			$err = error_get_last();
			$msg = $err['message'] ?? 'unknown error';
			LOGERR("Helper.php: Error creating folder '".$symmp3folder."': ".$msg);
		}
	}

	/* --- Symlinks (logging only on WARNING/ERROR) --- */

	$link1 = $myFolder . "/interfacedownload";
	if (!is_link($link1)) {
		$ok = @symlink($symttsfolder, $link1);
		if (!$ok && !is_link($link1)) {
			$err = error_get_last();
			$msg = $err['message'] ?? 'unknown error';
			LOGERR("Helper.php: Error creating symlink '".$link1."' -> '".$symttsfolder."': ".$msg);
		}
	}

	$link2 = $lbphtmldir . "/interfacedownload";
	if (!is_link($link2)) {
		$ok = @symlink($symttsfolder, $link2);
		if (!$ok && !is_link($link2)) {
			$err = error_get_last();
			$msg = $err['message'] ?? 'unknown error';
			LOGERR("Helper.php: Error creating symlink '".$link2."' -> '".$symttsfolder."': ".$msg);
		}
	}

	/* --- Copy MP3 folder on first install (log only on WARNING/ERROR) --- */

	if ($copy === true) {
		$src = $myFolder . "/" . $mp3folder;
		$dst = $symcurr_path . "/" . $mp3folder;

		$ok = true;
		try {
			xcopy($src, $dst);
		} catch (Throwable $e) {
			$ok = false;
			LOGERR("Helper.php: Error copying files from '".$src."' to '".$dst."': ".$e->getMessage());
		}

		// If xcopy() doesn't throw, but still failed silently:
		if ($ok === false) {
			// already logged above
		} else {
			// no success logging (per your request)
		}
	}
}

/**
 * Copy a file, or recursively copy a folder and its contents
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.0.1
 * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
 * @param       string   $source    Source path
 * @param       string   $dest      Destination path
 * @param       int      $permissions New folder creation permissions
 * @return      bool     Returns true on success, false on failure
 */
function xcopy($source, $dest, $permissions = 0755)
{
    // Check for symlinks
	echo $source;
    if (is_link($source)) {
        return symlink(readlink($source), $dest);
    }
    // Simple copy for a file
    if (is_file($source)) {
        return copy($source, $dest);
    }
    // Make destination directory
    if (!is_dir($dest)) {
        mkdir($dest, $permissions);
    }
    // Loop through the folder
    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
        // Skip pointers
        if ($entry == '.' || $entry == '..') {
            continue;
        }
        // Deep copy directories
        xcopy("$source/$entry", "$dest/$entry", $permissions);
    }
    // Clean up
    $dir->close();
    return true;
}


/**
/* Funktion : contains --> check if string contain
/*
/* @param: $haystack = string, $needle = search string                             
/* @return: bool(true) or bool(false)
**/

function contains($haystack, $needle) {
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
}


/**
/* Funktion :  sendInfoMS --> send info to MS
/*
/* @param: $abbr = Shortname for Inbound Port to be send
/* @param: $player = Name of player to be send
/* @param: $val = value to be send
/*
/* @return: error or nothing
**/

function sendInfoMS($abbr, $player, $val)    {

	global $sonos, $lbphtmldir, $ms, $config, $master;
	
	require_once $lbphtmldir . "/src/Core/Communication/io-modul.php";
	require_once $lbphtmldir . "/src/Core/Mqtt/phpMQTT.php";

	// check if Data transmission is switched off
	if(!is_enabled($config['LOXONE']['LoxDaten'])) {
		LOGINF("Helper.php: Communication to Loxone is turned off.");
		return;
	}
	
	if(is_enabled($config['LOXONE']['LoxDatenMQTT'])) {
		$creds = mqtt_connectiondetails();
		$client_id = uniqid(gethostname()."_client");
		$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'],  $creds['brokerport'], $client_id);
		$mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass']);
		$mqttstat = "1";
	} else {
		$mqttstat = "0";
	}
	
	// ceck if configured MS is fully configured
	if (!isset($ms[$config['LOXONE']['Loxone']])) {
		LOGERR ("Helper.php: Your selected Miniserver from Sonos4lox Plugin config seems not to be fully configured. Please check your LoxBerry Miniserver config!") ;
		return;
	}
	
		// obtain selected Miniserver from Plugin config
		$my_ms = $ms[$config['LOXONE']['Loxone']];
		# send TEXT data
		$lox_ip			= $my_ms['IPAddress'];
		$lox_port 	 	= $my_ms['Port'];
		$loxuser 	 	= $my_ms['Admin'];
		$loxpassword 	= $my_ms['Pass'];
		$loxip = $lox_ip.':'.$lox_port;
		try {
			LOGDEB("Helper.php: Trying to send Info for Zone '".$player."'.");	
			if ($mqttstat == "1")   {
				$err = $mqtt->publish('Sonos4lox/'.$abbr.'/'.$player, $val, 0, 1);
				LOGDEB("Helper.php: Requested Info for Zone '".$player."' has been send to MQTT. Pls. check your MQTT incoming overview for: 'Sonos4lox_".$abbr."_".$player."' or UDP for: 'MQTT:\iSonos4lox/".$abbr."/".$player."=\\i\\v' and create in Loxone an Virtual Inbound.");	
				echo "Requested Info for Zone '".$player."' has been send to MQTT. Pls. check your MQTT incoming HTTP overview for: 'Sonos4lox_".$abbr."_".$player."' or UDP for: 'MQTT:\iSonos4lox/".$abbr."/".$player."=\\i\\v' and create in Loxone an Virtual Inbound.";
			} else {			
				$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/$abbr_$player/$val"); // Radio oder Playliste
				LOGDEB("Helper.php: Requested Info for Zone '".$player."' has been send to UDP. Pls. check your UDP incoming overview for: '".$abbr."_$player' and create in Loxone an Virtual Inbound.");	
				echo "Requested Info for Zone '".$player."' has been send to UDP. Pls. check your Miniserver UDP incoming monitor for: '".$abbr."_$player' and create in Loxone an Virtual Inbound.";
			}
		} catch (Exception $e) {
			LOGWARN("Helper.php: Sending Info for Zone '".$player."' failed, we skip here...");	
			return false;
		}
		
		if ($mqttstat == "1")   {
			$mqtt->close();
		}
}

	


if (!function_exists('s4lox_helper_health_log')) {
    /**
     * Write health related messages safely in both web and CLI/systemd contexts.
     * The Event Listener can load Helper.php without the native LoxBerry LOG* helpers.
     */
    function s4lox_helper_health_log($level, $message)
    {
        $level = strtoupper((string)$level);
        $message = (string)$message;

        $functionMap = [
            'ERROR'   => 'LOGERR',
            'WARNING' => 'LOGWARN',
            'WARN'    => 'LOGWARN',
            'OK'      => 'LOGOK',
            'INFO'    => 'LOGINF',
            'DEBUG'   => 'LOGDEB',
            'DEB'     => 'LOGDEB',
        ];

        $fn = $functionMap[$level] ?? 'LOGDEB';
        if (function_exists($fn)) {
            $fn($message);
            return;
        }

        if (class_exists('S4L_Logger')) {
            switch ($level) {
                case 'ERROR':
                    S4L_Logger::error($message);
                    return;
                case 'WARNING':
                case 'WARN':
                    S4L_Logger::warning($message);
                    return;
                case 'OK':
                    S4L_Logger::ok($message);
                    return;
                case 'INFO':
                    S4L_Logger::info($message);
                    return;
                case 'DEBUG':
                case 'DEB':
                default:
                    S4L_Logger::debug($message);
                    return;
            }
        }

        error_log($message);
    }
}

/**
 * Update Sonos Event Listener health.json
 *
 * @param array $allRooms      Liste ALLER bekannten Räume (Keys aus $sonoszone z.B.)
 * @param array $onlineRooms   Liste der aktuell online erreichbaren Räume
 * @param array $lastEvents    Assoziatives Array mit Zeitstempeln der letzten Events:
 *                             [
 *                               'avtransport'        => <unix-ts> | null,
 *                               'renderingcontrol'   => <unix-ts> | null,
 *                               'zonegrouptopology'  => <unix-ts> | null,
 *                             ]
 *
 * Aufruf idealerweise NACH einem erfolgreich verarbeiteten Sonos-Event.
 */

if (!function_exists('update_sonos_health')) {
    /**
     * Schreibt eine "leichte" health.json für die Sonos4lox-Web-UI
     * - players.online / players.total
     * - rooms_flags["raum"] => ["Online" => 0|1]
     * - events (AVT/RC/ZGT Timestamps als ISO)
     * KEINE online_rooms/offline_rooms Arrays, KEIN EQ.
     */
    function update_sonos_health(
        array $allRooms,
        array $onlineRooms,
        array $lastEvents = []
    )
    {
        global $lbpconfigdir;

        // Fallback, falls $lbpconfigdir nicht gesetzt ist
        if (empty($lbpconfigdir)) {
            $lbpconfigdir = 'REPLACELBHOMEDIR/config/plugins/sonos4lox';
        }

        $healthFile = $lbpconfigdir . '/health.json';

        $hostname   = trim(`hostname 2>/dev/null`) ?: 'unknown';
        $now        = time();
        $iso        = date('c', $now);
        $pid        = function_exists('getmypid') ? getmypid() : null;

        $total_rooms   = count($allRooms);
        $online_unique = array_values(array_unique($onlineRooms));

        // --- pro Raum Online-Flag aufbauen ---
        $roomsFlags = [];
        foreach ($allRooms as $roomName) {
            $roomsFlags[$roomName] = [
                'Online' => in_array($roomName, $online_unique, true) ? 1 : 0,
            ];
        }

        // Event-Timestamps in ISO wandeln (falls vorhanden)
        $eventsIso = [
            'last_avtransport'       => isset($lastEvents['avtransport']) && $lastEvents['avtransport'] > 0
                ? date('c', (int)$lastEvents['avtransport'])
                : null,
            'last_renderingcontrol'  => isset($lastEvents['renderingcontrol']) && $lastEvents['renderingcontrol'] > 0
                ? date('c', (int)$lastEvents['renderingcontrol'])
                : null,
            'last_zonegrouptopology' => isset($lastEvents['zonegrouptopology']) && $lastEvents['zonegrouptopology'] > 0
                ? date('c', (int)$lastEvents['zonegrouptopology'])
                : null,
        ];

        $data = [
            'sonos-event-listener' => [
                'service'   => 'sonos_event_listener',
                'hostname'  => $hostname,
                'pid'       => $pid,
                'timestamp' => $now,
                'iso_time'  => $iso,
                'players'   => [
                    'online' => count($online_unique),
                    'total'  => $total_rooms,
                ],
                // wichtig für deine UI: rooms_RAUM_Online
                'rooms_flags' => $roomsFlags,
                'events'      => $eventsIso,
            ],
        ];

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            s4lox_helper_health_log('WARNING', "Helper.php: Failed to encode health.json: " . json_last_error_msg());
            return;
        }

        if (!is_dir($lbpconfigdir)) {
            @mkdir($lbpconfigdir, 0775, true);
        }

        $tempFile = $healthFile . '.tmp';

        if (file_put_contents($tempFile, $json) === false) {
            s4lox_helper_health_log('WARNING', "Helper.php: Failed to write temporary health file '$tempFile'");
            return;
        }

        if (!@rename($tempFile, $healthFile)) {
            s4lox_helper_health_log('WARNING', "Helper.php: Failed to move temporary health file to '$healthFile'");
            return;
        }

        @chmod($healthFile, 0664);

        $onlineCnt = $data['sonos-event-listener']['players']['online'];
        s4lox_helper_health_log('INFO', "Helper.php: Updated Sonos Event Listener health.json (pid $pid, online $onlineCnt/$total_rooms).");
    }
}
?>
