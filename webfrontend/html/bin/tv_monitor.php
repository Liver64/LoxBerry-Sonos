<?php

/**
* Submodul: TV Monitoring (Refactored)
*
**/

require_once "loxberry_system.php";
require_once "loxberry_log.php";
include($lbphtmldir."/system/sonosAccess.php");
include($lbphtmldir."/Grouping.php");
include($lbphtmldir."/Speaker.php");
include($lbphtmldir."/Helper.php");
include($lbphtmldir."/Info.php");

ini_set('max_execution_time', 30); 	
register_shutdown_function('shutdown');

// ============================================================================
// KONSTANTEN UND GLOBALE VARIABLEN
// ============================================================================

$configfile			= "s4lox_config.json";								// configuration file
$TV_safe_file		= "s4lox_TV_save";									// saved Values of all SB's
$status_file		= "s4lox_TV_on";									// TV has been turned on
$restore_file		= "s4lox_restore";									// Settings restore file
$mask 				= 's4lox_TV*.*';									// mask for deletion
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";				// Folder and file name for Player Status
$statusNight 		= "s4lox_TV_night_on";								// Folder and file name for Night Modus

$Stunden 			= date("H:i");
// Für Debugging only
#$Stunden 			= "07:00";
$time_start 		= microtime(true);

global $soundbars, $grouping, $sonoszone;

// ============================================================================
// FUNKTIONEN
// ============================================================================

/**
 * Lädt die Konfigurationsdatei und initialisiert Grundeinstellungen
 */
function loadConfiguration($configfile) {
	
	global $lbpconfigdir, $folfilePlOn, $mask;
	
	if (!file_exists($lbpconfigdir . "/" . $configfile)) {
		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
		exit;
	}
	
	$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	$GLOBALS['CONFIG'] = $config;
	
	// check if no TV Volume turned on
	if (is_disabled($config['VARIOUS']['tvmon'])) {
		echo "TV Monitor off".PHP_EOL;
		DelFiles($mask);
		exit(1);
	} else {
		echo "TV Monitor on".PHP_EOL;
		echo "<br>";
	}
	return $config;
}

/**
 * Verarbeitet Soundbar wenn TV ausgeschaltet wurde
 */
function processSoundbarTVOff($key, $TV_safe_file) {
	
	global $lbpplugindir;
	
	echo "Soundbar TV Mode for ".$key." has been turned off".PHP_EOL;
	
	if (file_exists("/run/shm/".$lbpplugindir."/".$TV_safe_file."_".$key.".json")) {
		$actual = json_decode(file_get_contents("/run/shm/".$lbpplugindir."/".$TV_safe_file."_".$key.".json"), true);
		startlog();
		// Restore previous Zone settings
		restoreFromJson($actual);
		@array_map('unlink', glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$key.'*.*'));
		LOGDEB("bin/tv_monitor.php: Soundbar TV Mode for ".$key." has been turned off and previous settings has been restored.");
	}
}

/**
 * Verarbeitet erste TV-Einschaltung der Soundbar
 */
function processSoundbarTVFirstOn($key, $soundbars, $sonoszonen, $status_file) {
	
	global $lbpplugindir,$sonos,$state;
	
	echo "TV Mode for Soundbar '".$key."' has been turned On".PHP_EOL;
	
	$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
	
	try {
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		sleep(1);
		echo "Player '".$key."' been seperated".PHP_EOL;
	} catch (Exception $e) {
		echo "Player '".$key."' already been seperated".PHP_EOL;
	}
	
	startlog();
	$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
	
	try {
		$dialog = Getdialoglevel();
		$dialog['Volume'] = $sonos->GetVolume();
		$dialog['Treble'] = $sonos->GetTreble();
		$dialog['Bass'] = $sonos->GetBass();
		#print_r($dialog);
		
		// Save Original settings
		file_put_contents("/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json", json_encode($dialog, JSON_PRETTY_PRINT));
	} catch (Exception $e) {
		echo "DialogLevel could not be obtained, nore file has been saved".PHP_EOL;
		LOGWARN("bin/tv_monitor.php: DialogLevel could not be obtained, nore file has been saved");
		@LOGEND($logname);	
	}
	
	$sonos->SetVolume($soundbars[$key][14]['tvvol']);
	LOGDEB("bin/tv_monitor.php: Volume for '".$key."' has been set to: ".$soundbars[$key][14]['tvvol']);
	echo "Volume for '".$key."' has been set to: ".$soundbars[$key][14]['tvvol'].PHP_EOL;
	
	$treble = null;
	$bass = null;
	$tvsublevel = null;
	$tvsurrlevel = null;
	
	if (isset($soundbars[$key][14]['tvtreble']) && $soundbars[$key][14]['tvtreble'] !== "") {
		$sonos->SetTreble((int)$soundbars[$key][14]['tvtreble']);
		$treble = (int)$soundbars[$key][14]['tvtreble'];
		echo "Treble for '".$key."' has been set to: ".$treble.PHP_EOL;
		LOGDEB("bin/tv_monitor.php: Treble for '".$key."' has been set to: ".$treble);
	}

	if (isset($soundbars[$key][14]['tvbass']) && $soundbars[$key][14]['tvbass'] !== "") {
		$sonos->SetBass((int)$soundbars[$key][14]['tvbass']);
		$bass = (int)$soundbars[$key][14]['tvbass'];
		echo "Bass for '".$key."' has been set to: ".$bass.PHP_EOL;
		LOGDEB("bin/tv_monitor.php: Bass for '".$key."' has been set to: ".$bass);
	}
	
	if (!empty($soundbars[$key][14]['tvgrpstop'])) {
		processTvGroupStop($soundbars, $sonoszonen);
	}
	
	$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
	$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
	
	try {
		// Turn Speech/Surround/Dialog Mode On and Mute Off
		$dia = is_enabled($soundbars[$key][14]['tvmonspeech']) ? "On" : "Off";
		$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonspeech']), 'DialogLevel');
		echo "Speech Mode for Soundbar ".$key." has been turned ".$dia."".PHP_EOL;
		LOGDEB("bin/tv_monitor.php: Speech Mode for Soundbar ".$key." has been turned ".$dia."");
		
		$sur = is_enabled($soundbars[$key][14]['tvmonsurr']) ? "On" : "Off";
		$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonsurr']), 'SurroundEnable');
		echo "Surround for Soundbar ".$key." has been turned ".$sur."".PHP_EOL;
		LOGDEB("bin/tv_monitor.php: Surround for Soundbar ".$key." has been turned ".$sur);
		
		if ($sur == "On")   {
			if (isset($soundbars[$key][14]['tvsurrlevel']) && $soundbars[$key][14]['tvsurrlevel'] !== "") {
				$sonos->SetDialogLevel((int)$soundbars[$key][14]['tvsurrlevel'], 'SurroundLevel');
				$tvsurrlevel = (int)$soundbars[$key][14]['tvsurrlevel'];
				echo "Surround Level for '".$key."' has been set to: ".$tvsurrlevel.PHP_EOL;
				LOGDEB("bin/tv_monitor.php: Surround Level for '".$key."' has been set to: ".$tvsurrlevel);
			}
		}
		
		$sub = is_enabled($soundbars[$key][14]['tvmonnightsub']) ? "On" : "Off";
		$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonnightsub']), 'SubEnable');
		echo "Subwoofer for Soundbar ".$key." has been turned ".$sub."".PHP_EOL;
		LOGDEB("bin/tv_monitor.php: Subwoofer for Soundbar ".$key." has been turned ".$sub);
		
		if ($sub == "On")   {
			if (isset($soundbars[$key][14]['tvsublevel']) && $soundbars[$key][14]['tvsublevel'] !== "") {
				@$sonos->SetDialogLevel((int)$soundbars[$key][14]['tvsublevel'], 'SubGain');
				$tvsublevel = (int)$soundbars[$key][14]['tvsublevel'];
				echo "Subwoofer Level for '".$key."' has been set to: ".$tvsublevel.PHP_EOL;
				LOGDEB("bin/tv_monitor.php: Subwoofer Level for '".$key."' has been set to: ".$tvsublevel);
			}
		}
		
		@$sonos->SetMute(false);

	} catch (Exception $e) {
		echo "Speech/Surround/Night Mode/Subwoofer could'nt been turned On for: ".$key."".PHP_EOL;
		LOGWARN("bin/tv_monitor.php: Speech/Surround/Night Mode/Subwoofer could'nt been turned On for: ".$key);
		@LOGEND($logname);	
	}
	LOGDEB("bin/tv_monitor.php: Soundbar ".$key." is On and in TV Mode.");
}

/**
 * Verarbeitet laufenden TV-Modus (Night Mode Settings)
 */
function processSoundbarTVRunning($key, $soundbars, $Stunden, $statusNight) {
	
	global $lbpplugindir,$state;
	
	echo "TV Mode for Soundbar '".$key."' is already running.".PHP_EOL;
	
	if ($soundbars[$key][14]['fromtime'] != "false") {
		// set Nightmode and Subgain
		if ((string)$Stunden >= (string)$soundbars[$key][14]['fromtime']) { 
			if (!file_exists("/run/shm/".$lbpplugindir."/".$statusNight."_".$key.".json")) {
				$sonos = new SonosAccess($soundbars[$key][0]);
				
				// Turn Night Mode On/Off
				startlog();
				$night = is_enabled($soundbars[$key][14]['tvmonnight']) ? "On" : "Off";
				$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonnight']), 'NightMode');
				echo "Night Mode for Soundbar ".$key." has been turned to ".$night."".PHP_EOL;
				LOGDEB("bin/tv_monitor.php: NightMode for Soundbar ".$key." has been turned to ".$night);
				
				// Turn Subwoofer On/Off
				$subnight = is_enabled($soundbars[$key][14]['tvsubnight']) ? "On" : "Off";
				$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvsubnight']), 'SubEnable');
				echo "Subwoofer for Soundbar ".$key." has been turned to ".$subnight."".PHP_EOL;
				LOGDEB("bin/tv_monitor.php: Subwoofer for Soundbar ".$key." has been turned to ".$subnight);

				// Set Sub Level
				if ($subnight == "On")    {
					if (isset($soundbars[$key][14]['tvmonnightsublevel']) && $soundbars[$key][14]['tvmonnightsublevel'] !== "") {
						$sublevel = (int)$soundbars[$key][14]['tvmonnightsublevel'];
						$sonos->SetDialogLevel($sublevel, 'SubGain');
						echo "Subwoofer Level for Soundbar ".$key." has been set to: ".$sublevel." for night".PHP_EOL;
						LOGDEB("bin/tv_monitor.php: Night Subwoofer Level for Soundbar ".$key." has been set to: ".$sublevel." for night");
					}
				}
				
				file_put_contents("/run/shm/".$lbpplugindir."/".$statusNight."_".$key.".json",json_encode("1", JSON_PRETTY_PRINT));
			}
		}
		echo "Soundbar ".$key." is On and in TV Mode, all settings has been set previously".PHP_EOL;
	}
}

/**
 * Verarbeitet Musik-Modus
 */
function processSoundbarMusic($key, $state, $TV_safe_file) {
	
	global $lbpplugindir,$state;
	
	echo "Music on ".$key." is loaded...".PHP_EOL;
	$actual = PrepSaveZonesStati();
	@file_put_contents("/run/shm/".$lbpplugindir."/".$TV_safe_file."_".$key.".json",json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));
	
	if ($state == 1) {	
		echo "...and streaming".PHP_EOL;
	} else {
		echo "...but paused or stopped".PHP_EOL;
	}
}

/**
 * Verarbeitet einzelne Soundbar während der aktiven Zeiten
 */
function processSoundbar($key, $soundbars, $sonoszonen, $TV_safe_file, $status_file, $statusNight, $Stunden, $restore_file) {
	
	global $lbpplugindir,$state,$status_file;
	
	// ********************************************
	// If Soundbar has been configured On
	// ********************************************
	if ((bool)is_enabled($soundbars[$key][14]['usesb'])) {
		if (file_exists("/run/shm/".$lbpplugindir."/".$restore_file."_".$key.".json")) {
			unlink("/run/shm/".$lbpplugindir."/".$restore_file."_".$key.".json");
		}
		
		try {
			$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
			$tvmodi = $sonos->GetZoneInfo();
			$posinfo = $sonos->GetPositionInfo();
			$state = $sonos->GetTransportInfo();
			$master = $key;
			
			// **********************************************
			// Soundbar if off
			// **********************************************
			if ($tvmodi['HTAudioIn'] == 0) {
				processSoundbarTVOff($key, $TV_safe_file);
				
			// ***********************************************
			// Soundbar has been turned On 1st time 
			// ***********************************************
			} elseif ($tvmodi['HTAudioIn'] > 21 or (substr($posinfo["TrackURI"], 0, 17) == "x-sonos-htastream")) {
				
				// TV has been turned on
				if (!file_exists("/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json")) {
					processSoundbarTVFirstOn($key, $soundbars, $sonoszonen, $status_file);
					
				// ******************************************************
				// Soundbar is already running
				// ******************************************************
				} else {
					processSoundbarTVRunning($key, $soundbars, $Stunden, $statusNight);
				}
				
			// ******************************************************
			// Music is loaded/playing
			// ******************************************************
			} else {
				processSoundbarMusic($key, $state, $TV_safe_file);
			}
			
			echo "Current incoming source for ".$key." at HDMI/SPDIF: " . getAudioSourceName($tvmodi['HTAudioIn']) . " (".$tvmodi['HTAudioIn'].")". PHP_EOL;	
			
		} catch (Exception $e) {
			echo "Soundbar '".$key."' has not responded , maybe Soundbar is offline, we skip here...".PHP_EOL;
		}	
		
	// ********************************************
	// If Soundbar is turned Off in Plugin
	// ********************************************
	} else {
		@array_map('unlink', glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$key.'*.*'));				
		echo "TV Monitor for Soundbar '".$key."' is turned off in Plugin Config".PHP_EOL;
	}
}


/**
 * Restores previous soundbar settings outside active hours
 * - per soundbar individually
 * - only if HTAudioIn is no longer active
 * - only once per soundbar (controlled by $restore_file)
 */
function restoreSoundbarSettings($soundbars, $restore_file) {
	
	global $lbpplugindir, $status_file;
	
	echo "TV Monitor is not active (outside predefined hours)".PHP_EOL;
	
	foreach ($soundbars as $subkey => $value) {
		
		// Only handle enabled soundbars
		if (!isset($soundbars[$subkey][14]['usesb']) || !is_enabled($soundbars[$subkey][14]['usesb'])) {
			continue;
		}
		
		// Validate IP
		if (!isset($soundbars[$subkey][0]) || empty($soundbars[$subkey][0])) {
			echo "Skipping restore for '".$subkey."' because no IP is available".PHP_EOL;
			LOGWARN("bin/tv_monitor.php: Skipping restore for '".$subkey."' because no IP is available");
			continue;
		}
		
		$ip         	= $soundbars[$subkey][0];
		$restoremark	= "/run/shm/".$lbpplugindir."/".$restore_file."_".$subkey.".json";
		$statusfile 	= "/run/shm/".$lbpplugindir."/".$status_file."_".$subkey.".json";
		
		// Restore only once per soundbar
		if (file_exists($restoremark)) {
			echo "Restore for '".$subkey."' has already been done - skipping".PHP_EOL;
			continue;
		}
		
		// No saved restore values available
		if (!file_exists($statusfile)) {
			echo "No saved file found for '".$subkey."' - skipping".PHP_EOL;
			continue;
		}
		
		try {
			$sonos = new SonosAccess($ip);
			$tvmodi = $sonos->GetZoneInfo();
			$htAudioIn = isset($tvmodi['HTAudioIn']) ? (int)$tvmodi['HTAudioIn'] : 0;
			
			echo "Outside time window - current incoming source for ".$subkey." at HDMI/SPDIF: "
				. getAudioSourceName($htAudioIn) . " (".$htAudioIn.")".PHP_EOL;
			#$htAudioIn = 20;
			// Restore only if TV input is no longer active
			if ($htAudioIn < 100) {
				
				
				// Only restore if TV monitoring had previously set temp/status data
				if (file_exists($statusfile)) {
					$restored = restoreSoundbarSettingsFromJson($subkey, $ip, $lbpplugindir, $status_file);

					if ($restored) {
						// Delete only TV temp files for this soundbar
						$tempfiles = glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$subkey.'*.*');
						if (is_array($tempfiles)) {
							foreach ($tempfiles as $tempfile) {
								@unlink($tempfile);
							}
						}
						
						echo "TV Monitor has been restored for '".$subkey."'".PHP_EOL;
						file_put_contents($restoremark, json_encode("1", JSON_PRETTY_PRINT));
						LOGDEB("bin/tv_monitor.php: TV Monitor has been restored for '".$subkey."'");
						
					} else {
						echo "Restore failed for '".$subkey."'".PHP_EOL;
						LOGWARN("bin/tv_monitor.php: Restore failed for '".$subkey."'");
					}
					
				} else {
					echo "Skipping restore for '".$subkey."' because no TV status file exists".PHP_EOL;
					LOGINF("bin/tv_monitor.php: Skipping restore for '".$subkey."' because no TV status file exists");
				}
				
			} else {
				echo "Skipping restore for '".$subkey."' because TV input is still active".PHP_EOL;
				LOGINF("bin/tv_monitor.php: Skipping restore for '".$subkey."' because TV input is still active");
			}
			
		} catch (Exception $e) {
			echo "Restore check for Soundbar '".$subkey."' failed, maybe soundbar is offline, skipping...".PHP_EOL;
			LOGWARN("bin/tv_monitor.php: Restore check for Soundbar '".$subkey."' failed: ".$e->getMessage());
		}
	}
}

/**
 * Restore all saved soundbar settings from JSON file
 * for exactly one soundbar ($subkey)
 */
function restoreSoundbarSettingsFromJson($subkey, $ip, $lbpplugindir, $status_file)
{
	#global ,$sonos;
	startlog();
	$jsonfile = "/run/shm/" . $lbpplugindir . "/" . $status_file . "_" . $subkey . ".json";
	#print_r($jsonfile);
	if (!file_exists($jsonfile)) {
		LOGERR("bin/tv_monitor.php: No restore file found for '".$subkey."': ".$jsonfile);
		return false;
	}

	$json = file_get_contents($jsonfile);
	if ($json === false || trim($json) === '') {
		LOGERR("bin/tv_monitor.php: Could not read restore file or file is empty for '".$subkey."': ".$jsonfile);
		return false;
	}

	$saved = json_decode($json, true);

	if (!is_array($saved)) {
		LOGERR("bin/tv_monitor.php: Invalid JSON in '".$jsonfile."'");
		return false;
	}

	LOGDEB("bin/tv_monitor.php: Restoring saved settings for '".$subkey."' from '".$jsonfile."'");

	// helper: convert JSON boolean-like values safely to 0/1
	$toBoolInt = function ($value) {
		if (is_bool($value)) {
			return $value ? 1 : 0;
		}

		$value = strtolower(trim((string)$value));
		return in_array($value, array("1", "true", "yes", "on"), true) ? 1 : 0;
	};

	// helper: convert numeric values safely to integer
	$toInt = function ($value, $default = 0) {
		return is_numeric($value) ? (int)$value : (int)$default;
	};
	
	$sonos = new SonosAccess($ip); //Sonos IP Adresse
	// Restore soundbar dialog/sub/night settings
	if (array_key_exists("NightMode", $saved)) {
		$sonos->SetDialogLevel($toBoolInt($saved["NightMode"]), "NightMode");
		LOGDEB("bin/tv_monitor.php: Night Mode restored to '".boolToOnOff($saved["NightMode"])."' for '".$subkey."'");
	}

	if (array_key_exists("SurroundEnable", $saved)) {
		$sonos->SetDialogLevel($toBoolInt($saved["SurroundEnable"]), "SurroundEnable");
		LOGDEB("bin/tv_monitor.php: Surround Mode restored to '".boolToOnOff($saved["SurroundEnable"])."' for '".$subkey."'");
	}

	if (
		array_key_exists("SurroundLevel", $saved) &&
		array_key_exists("SurroundEnable", $saved) &&
		$toBoolInt($saved["SurroundEnable"]) === 1
	) {
		$sonos->SetDialogLevel($saved["SurroundLevel"], "SurroundLevel");
		LOGDEB("bin/tv_monitor.php: Surround Level restored to '".$saved["SurroundLevel"]."' for '".$subkey."'");
	} elseif (array_key_exists("SurroundLevel", $saved)) {
		LOGDEB("bin/tv_monitor.php: Surround Level restore skipped because Surround is Off for '".$subkey."'");
	}

	if (array_key_exists("DialogLevel", $saved)) {
		$sonos->SetDialogLevel($toBoolInt($saved["DialogLevel"]), "DialogLevel");
		LOGDEB("bin/tv_monitor.php: Speech Mode restored to '".boolToOnOff($saved["DialogLevel"])."' for '".$subkey."'");
	}

	if (array_key_exists("SubEnable", $saved)) {
		$sonos->SetDialogLevel($toBoolInt($saved["SubEnable"]), "SubEnable");
		LOGDEB("bin/tv_monitor.php: Subwoofer Mode restored to '".boolToOnOff($saved["SubEnable"])."' for '".$subkey."'");
	}

	if (
		array_key_exists("SubGain", $saved) &&
		array_key_exists("SubEnable", $saved) &&
		$toBoolInt($saved["SubEnable"]) === 1
	) {
		$sonos->SetDialogLevel($saved["SubGain"], "SubGain");
		LOGDEB("bin/tv_monitor.php: Subwoofer Level restored to '".$saved["SubGain"]."' for '".$subkey."'");
	} elseif (array_key_exists("SubGain", $saved)) {
		LOGDEB("bin/tv_monitor.php: Subwoofer Level restore skipped because Subwoofer is Off for '".$subkey."'");
	}

	// Restore audio settings
	if (array_key_exists("Volume", $saved)) {
		$sonos->SetVolume($saved["Volume"]);
		LOGDEB("bin/tv_monitor.php: Volume restored to '".$saved["Volume"]."' for '".$subkey."'");
	}

	if (array_key_exists("Treble", $saved)) {
		$sonos->SetTreble($saved["Treble"]);
		LOGDEB("bin/tv_monitor.php: Treble restored to '".$saved["Treble"]."' for '".$subkey."'");
	}

	if (array_key_exists("Bass", $saved)) {
		$sonos->SetBass($saved["Bass"]);
		LOGDEB("bin/tv_monitor.php: Bass restored to '".$saved["Bass"]."' for '".$subkey."'");
	}

	LOGDEB("bin/tv_monitor.php: Restore finished successfully for '".$subkey."'");
	return true;
}

// turns bool into Off/On
function boolToOnOff($value)
{
	if (is_bool($value)) {
		return $value ? "On" : "Off";
	}

	$value = strtolower(trim((string)$value));
	return in_array($value, array("1", "true", "yes", "on"), true) ? "On" : "Off";
}

// ============================================================================
// HAUPTPROGRAMM
// ============================================================================

echo "<PRE>";

// Preparation & Load Configuration
$config = loadConfiguration($configfile);
$soundbars = identSB($config['sonoszonen'], $folfilePlOn);
$GLOBALS['soundbars'];

// extract all Players and identify those were Online
$sonoszonen = $config['sonoszonen'];
$sonoszone = sonoszonen_on();
#print_r($config);

// ********************************************************
// Prüfe ob innerhalb der definierten Zeiten
// ********************************************************
if (isWithinTimeWindow($Stunden, $config['VARIOUS']['starttime'], $config['VARIOUS']['endtime'])) {
	// Start script - Process each soundbar
	foreach($soundbars as $key => $value) {
		processSoundbar($key, $soundbars, $sonoszonen, $TV_safe_file, $status_file, $statusNight, $Stunden, $restore_file);
	}
// ********************************************************
// restore previous soundbar settings 
// ********************************************************
} else {
	restoreSoundbarSettings($soundbars, $restore_file);
}

#print_r($config);
$time_end = microtime(true);
$process_time = $time_end - $time_start;
echo "Processing request tooks about ".round($process_time, 2)." seconds.".PHP_EOL;	

# TV Modus values
	
	/*******
	values depend on what input is running (tested with BEAM Gen 2 and Samsung TV Frame)
	
	Single Stream:
	TV 				= 33554434
	Stream/Radio 	= 21
	off 			= 0
	
	Grouping:
	Member 			= 21
	Master 			= 21
	*******/

function getAudioSourceName($value)
{
    $value = (int)$value;

    // TV / HDMI (Bit 0x02000000 gesetzt)
    if ($value > 21) {
        return 'TV on';
    }

    // Stream / Radio (konkreter Wert)
    if ($value === 21) {
        return 'Stream/Radio';
    }

    // TV off
    if ($value === 0) {
        return 'TV off';
    }

    return 'Unknown (' . $value . ')';
}
		

/**
/* Function : DelFiles --> delete tmp files
/*
/* @param:  none
/* @return: none
**/

function DelFiles($mask)    {
	
	global $mask,$lbpplugindir;
	
	array_map('unlink', glob("/run/shm/".$lbpplugindir."/".$mask));
}



/**
/* Function : RestorePrevSBsettings --> Restore previous settings before TV Monitor starts
/*
/* @param:  none
/* @return: none
**/

function RestorePrevSBsettings($soundbars)    {
	
	global $status_file, $logname, $lbpplugindir;
	
	startlog();
	foreach($soundbars as $key => $value)   {
		$restorelevel = json_decode(file_get_contents("/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json"), true);
		$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
		$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
		$sonos->SetDialogLevel(is_enabled(json_encode($restorelevel['NightMode'])), 'NightMode');
		$sonos->SetDialogLevel($restorelevel['SubGain'], 'SubGain');
		$sonos->SetDialogLevel($restorelevel['SurroundLevel'], 'SurroundLevel');
		echo "Previous Soundbar settings for '".$key."' has been restored.".PHP_EOL;
		LOGDEB("bin/tv_monitor.php: Previous Soundbar settings for '".$key."' has been restored");	
	}
}


/**
/* Function : PrepSaveZonesStati --> start Preparation for save zones
/*
/* @param:  none
/* @return: array saved details 
**/

function PrepSaveZonesStati() {
	
	global $sonoszone, $soundbars, $sonos, $player, $actual, $time_start, $log, $folfilePlOn;
	
	# identify if Soundbars are grouped
	foreach($soundbars as $zonen => $ip) {
		$sonos = new SonosAccess($soundbars[$zonen][0]); //Sonos IP Adresse
		$relzones = getGroup($zonen);
	}

	# Filter Player by grouped Players 
	if (!empty($relzones))    {
		$filtered = array();
			foreach($sonoszone as $zone => $ip) {
				$exist = in_array($zone, $relzones);
				if ($exist == true)  {
					$filtered[$zone] = $ip;
				}
			}
		$sonoszone = $filtered;
	} else {
		$sonoszone = $soundbars;
	}
	$actual = saveZonesStati($sonoszone);
	#print_r($actual);
	return $actual;
}


/**
/* Function : saveZonesStati --> saving of all needed info to restore later
/*
/* @param:  none
/* @return: none
**/

function saveZonesStati($sonoszone) {
	
	global $sonoszone, $sonos, $player, $actual, $time_start, $log, $folfilePlOn;

	// save each Zone Status
	foreach ($sonoszone as $player => $value) {
		@$sonos = new SonosAccess($sonoszone[$player][0]); 
		$actual[$player]['Mute'] = $sonos->GetMute($player);
		$actual[$player]['Volume'] = $sonos->GetVolume($player);
		$actual[$player]['Bass'] = $sonos->GetBass($player);
		$actual[$player]['Treble'] = $sonos->GetTreble($player);
		$actual[$player]['MediaInfo'] = $sonos->GetMediaInfo($player);
		$actual[$player]['PositionInfo'] = $sonos->GetPositionInfo($player);
		$actual[$player]['TransportInfo'] = $sonos->GetTransportInfo($player);
		$actual[$player]['TransportSettings'] = $sonos->GetTransportSettings($player);
		$actual[$player]['Group-ID'] = $sonos->GetZoneGroupAttributes($player);
		$actual[$player]['Grouping'] = getGroup($player);
		$actual[$player]['ZoneStatus'] = getZoneStatus($player);
		$posinfo = $actual[$player]['PositionInfo'];
		$media = $actual[$player]['MediaInfo'];
		$zonestatus = $actual[$player]['ZoneStatus'];
		if ($zonestatus != "member")    {
			if (substr($posinfo["TrackURI"], 0, 18) == "x-sonos-htastream:")  {
				$actual[$player]['Type'] = "TV";
			} elseif (substr($actual[$player]['MediaInfo']["UpnpClass"] ,0 ,36) == "object.item.audioItem.audioBroadcast")  {
				$actual[$player]['Type'] = "Radio";
			} elseif (substr($posinfo["TrackURI"], 0, 15) == "x-rincon-stream")   {
				$actual[$player]['Type'] = "LineIn";
			} elseif (empty($posinfo["CurrentURIMetaData"]))   {
				$actual[$player]['Type'] = "";
			} else {
				$actual[$player]['Type'] = "Track";
			}
		}
	}
	return $actual;
}


/**
* Function : restoreFromJson --> restores previous Zone settings
*
* @param:  array
* @return: previous settings
**/		

function restoreFromJson($actual)
{
    global $sonoszone;
	
    LOGDEB("bin/tv_monitor.php: Starting full Sonos restore from JSON.");
    if (empty($actual)) {
        LOGWARN("bin/tv_monitor.php: Restore aborted - JSON empty.");
        return;
    }
    /*
        MASTER ERMITTELN
    */
    $firstZone = array_key_first($actual);
    if (!empty($actual[$firstZone]['Grouping'])) {
        $master = $actual[$firstZone]['Grouping'][0];
    } else {
        $master = $firstZone;
    }
    LOGDEB("bin/tv_monitor.php: Master zone detected: ".$master);
    /*
        GRUPPE WIEDERHERSTELLEN
    */
    $group = $actual[$master]['Grouping'] ?? [];
    if (count($group) > 1) {
        foreach ($group as $member) {
            if ($member == $master) {
                continue;
            }
            try {
                $sonos = new SonosAccess($sonoszone[$member][0]);
                $sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$master][1]);
                LOGDEB("bin/tv_monitor.php: '".$member."' joined '".$master."'");
            } catch (Exception $e) {
                LOGWARN("bin/tv_monitor.php: Group join failed for ".$member);
            }
        }
    }
    /*
        QUELLE WIEDERHERSTELLEN
    */
    $sourceRestored = false;
    $uri  = $actual[$master]['MediaInfo']['CurrentURI'] ?? "";
    $meta = $actual[$master]['MediaInfo']['CurrentURIMetaData'] ?? "";
    
    // Nur wiederherstellen wenn eine gültige URI vorhanden ist
    #if (!empty($uri) && $uri != "" && !strpos($uri, 'x-sonos-htastream')) {
	if (!empty($uri) && $uri != "" && strpos($uri, 'x-sonos-htastream') === false) {
        try {
            $sonos = new SonosAccess($sonoszone[$master][0]);
            if (!empty($meta)) {
                $meta = htmlspecialchars_decode($meta);
            }
            $sonos->SetAVTransportURI($uri, $meta);
            $sourceRestored = true;
            LOGDEB("bin/tv_monitor.php: Source restored on '".$master."' (URI: ".$uri.")");
        } catch (Exception $e) {
            LOGWARN("bin/tv_monitor.php: Source restore failed on ".$master);
        }
    } else {
        LOGDEB("bin/tv_monitor.php: No valid source to restore on '".$master."' - skipping (URI was: ".($uri ?: "empty").")");
    }
    /*
        AUDIO SETTINGS
    */
    foreach ($actual as $zone => $data) {
        try {
            $sonos = new SonosAccess($sonoszone[$zone][0]);
            if (isset($data['Volume']))
                $sonos->SetVolume($data['Volume']);
            if (isset($data['Bass']))
                $sonos->SetBass($data['Bass']);
            if (isset($data['Treble']))
                $sonos->SetTreble($data['Treble']);
            if (isset($data['Mute']))
                $sonos->SetMute($data['Mute']);
            LOGDEB("bin/tv_monitor.php: Settings restored for '".$zone."'");
        } catch (Exception $e) {
            LOGWARN("bin/tv_monitor.php: Restore settings failed for ".$zone);
        }
    }
    
    /*
        POSITION WIEDERHERSTELLEN (nur bei seekbaren Quellen)
    */
    if ($sourceRestored) {
        // Liste der URI-Patterns, die NICHT seekbar sind
        $nonSeekablePatterns = [
            'x-sonosapi-stream',    // Radio-Streams (SWR3, etc.)
            'x-sonosapi-radio',     // TuneIn Radio
            'x-sonosapi-hls',       // HTTP Live Streaming
            'x-rincon-stream',      // Line-In
            'x-sonos-htastream'     // TV/HDMI (sollte bereits oben gefiltert sein)
        ];
        
        // Prüfe ob URI seekbar ist
        $isSeekable = true;
        foreach ($nonSeekablePatterns as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                $isSeekable = false;
                LOGDEB("bin/tv_monitor.php: Source is a live stream - seek not applicable");
                break;
            }
        }
        
        // Nur bei seekbaren Quellen Position wiederherstellen
        if ($isSeekable) {
            $posinfo = $actual[$master]['PositionInfo'] ?? [];
            $reltime = $posinfo['RelTime'] ?? "";
            
            if (!empty($reltime) && $reltime != "0:00:00" && $reltime != "NOT_IMPLEMENTED") {
                try {
                    $sonos = new SonosAccess($sonoszone[$master][0]);
                    $sonos->Seek("REL_TIME", $reltime);
                    LOGDEB("bin/tv_monitor.php: Seek restored to ".$reltime." on '".$master."'");
                } catch (Exception $e) {
                    LOGDEB("bin/tv_monitor.php: Seek not supported for this source type");
                }
            }
        }
    }
    
    /*
        PLAY STATUS (nur wenn Quelle erfolgreich wiederhergestellt wurde)
    */
    if ($sourceRestored && !empty($actual[$master]['TransportInfo'])) {
        try {
            $sonos = new SonosAccess($sonoszone[$master][0]);
            if ($actual[$master]['TransportInfo'] == 1) {
                $sonos->Play();
                LOGDEB("bin/tv_monitor.php: Playback restarted on '".$master."'");
            } else {
                $sonos->Pause();
                LOGDEB("bin/tv_monitor.php: Playback paused on '".$master."'");
            }
        } catch (Exception $e) {
            LOGWARN("bin/tv_monitor.php: Playback restore failed on ".$master);
        }
    } elseif (!$sourceRestored) {
        LOGDEB("bin/tv_monitor.php: Skipping playback restore - no source was loaded");
    }
    
    LOGDEB("bin/tv_monitor.php: Restore finished.");
}


/**
* Function : processTvGroupStop --> stop grouped players 
*
* @param: array @soundbars, array @sonoszone
* @return: static
**/

function processTvGroupStop(array $soundbars, array $sonoszone) {

    foreach ($soundbars as $sbRoom => $sbData) {

        $tvgrpstop = $sbData[14]['tvgrpstop'] ?? [];
        if (empty($tvgrpstop)) {
            continue; // keine Räume zum Stoppen
        }
        echo "Processing Soundbar: $sbRoom\n";
        foreach ($tvgrpstop as $stopRoom) {
			// Prüfen ob Raum Online
			if (!file_exists(LBPDATADIR."/PlayerStatus/s4lox_on_".$stopRoom.".txt"))   {
				LOGDEB("bin/tv_monitor.php: Room '$stopRoom' seems to be not reachable");
				continue;
			}
            // Prüfen, ob IP/UUID existiert
            if (empty($sonoszone[$stopRoom][0])) {
                echo "ERROR: No Sonos IP/UUID for room '$stopRoom'\n";
				LOGERR("bin/tv_monitor.php: ERROR: No Sonos IP/UUID for room '$stopRoom'");
                continue;
            }
            echo "Processing stop room: $stopRoom\n";
            try {
                $sonos = new SonosAccess($sonoszone[$stopRoom][0]);
                // Status abfragen
                $status = getZoneStatus($stopRoom);
                // Aktion je nach Status
                if ($status === 'member' || $status === 'master') {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGDEB("bin/tv_monitor.php: '$stopRoom' is leaving group");
					echo "'$stopRoom' is leaving group\n";
                    sleep(1);
					if ($sonos->GetTransportInfo() == 1) {
						$sonos->Pause();
						echo "Pausing room '$stopRoom'\n";
						LOGDEB("bin/tv_monitor.php: Pausing room '$stopRoom'");
					}
                } elseif ($status === 'single') {
					if ($sonos->GetTransportInfo() == 1) {
						$sonos->Pause();
						echo "Pausing single room '$stopRoom'\n";
						LOGDEB("bin/tv_monitor.php: Pausing single room '$stopRoom'");
					}
                } else {
                    echo "No action for room '$stopRoom'\n";
                }
            } catch (Exception $e) {
				LOGERR("bin/tv_monitor.php: ERROR processing room '$stopRoom': " . $e->getMessage());
                echo "ERROR processing room '$stopRoom': " . $e->getMessage() . "\n";
            }
        }
    }
}

/**
/* Funktion : isWithinTimeWindow --> checks whether start/end time are correct
/*
/* @param: $now, $start, $end                        
/* @return: 
**/

function isWithinTimeWindow($now, $start, $end)
{
	if ($start === $end) {
		return true; // 24h aktiv
	}

	if ($start < $end) {
		return ($now >= $start && $now < $end);
	}

	// Fenster über Mitternacht
	return ($now >= $start || $now < $end);
}

/**
/* Funktion : startlog --> startet logging
/*
/* @param: Name of Log, filename of Log                        
/* @return: 
**/

function startlog()
{
    if (!empty($GLOBALS['TVMON_LOG_STARTED'])) {
        return;
    }

    require_once "loxberry_log.php";
    global $lbplogdir, $lbpplugindir, $log;

    $params = [
        "name"     => "TV Monitor",
        "package"  => $lbpplugindir,
        "filename" => $lbplogdir . "/tv_monitor.log",
        "append"   => 1,
        "addtime"  => 1,
        "loglevel" => 7,
    ];

    $log = LBLog::newLog($params);

    if (empty($log)) {
        echo "ERROR: Could not initialize LoxBerry log.\n";
        return;
    }

    $GLOBALS['TVMON_LOG_STARTED'] = true;
    $log->LOGSTART("TV Monitor");
}

function shutdown()
{
    require_once "loxberry_log.php";
    global $log;

    if (!empty($GLOBALS['TVMON_LOG_STARTED']) && !empty($log)) {
        $log->LOGEND("TV Monitor"); // Methode benutzen, nicht globales LOGEND()
    }
}

?>