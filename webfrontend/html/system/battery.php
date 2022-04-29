#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "loxberry_io.php";
require_once("$lbphtmldir/system/sonosAccess.php");
require_once("$lbphtmldir/system/logging.php");
require_once("$lbphtmldir/Helper.php");
require_once("$lbphtmldir/Play_T2S.php");
require_once("$lbphtmldir/Grouping.php");
require_once("$lbphtmldir/Restore_T2S.php");
require_once("$lbphtmldir/Save_T2S.php");
require_once("$lbphtmldir/Speaker.php");
require_once("$lbphtmldir/voice_engines/GoogleCloud.php");
require_once("$lbphtmldir/system/bin/openssl_file.class.php");
require_once("$lbpbindir/binlog.php");

$myFolder = "$lbpconfigdir";									// get config folder
$myConfigFile = "player.cfg";									// get config file
$pathlanguagefile = "$lbphtmldir/voice_engines/langfiles";		// get languagefiles
$configfile	= "/run/shm/s4lox_config.json";						// configuration file
$Stunden = intval(strftime("%H"));
$sPassword = 'loxberry';
$off_file 			= $lbplogdir."/s4lox_off.tmp";					// path/file for Script turned off

# check if script/Sonos Plugin is off
if (file_exists($off_file)) {
	exit;
}

# Execute Cronjob manually
# sh /etc/cron.d/Sonos

ini_set('max_execution_time', 30); 		
register_shutdown_function('shutdown');
$ms = LBSystem::get_miniservers();

#echo "<PRE>";

# only between 8am till 21pm
if ($Stunden >=8 && $Stunden <22)   {
	
	global $master, $main, $zone, $ms, $batlevel, $configfile, $config;
	
	# load Player Configuration
	$config = parseConfigFile();

	$sonoszonen = ($config['sonoszonen']);
	$sonoszone = $sonoszonen;
	#print_r($config);	

	$battzone = array();
	# check if MOVE or ROAM there
	foreach ($sonoszonen as $zone => $player) {
		$src = $sonoszonen[$zone][7];
		if ($src == "S27" or $src == "S17")   {
			array_push($battzone, $src);
		}
	}
	if (count($battzone) < 1)  {
		# No ROAM or MOVE then exit w/o logging
		exit;
	}
	# Start Logging
	$params = [	"name" => "Cronjobs",
				"filename" => "$lbplogdir/sonos.log",
				"append" => 1,
				"stderr" => 1,
				"addtime" => 1,
				];
	$log = LBLog::newLog($params);

	LOGSTART("Check Battery state");

	$mainpl = array();
	$errortext = '';
	foreach ($sonoszonen as $zone => $player) {
		$src = $sonoszonen[$zone][7];
		$ip = $sonoszonen[$zone][0];
		# get Main Player(s) for TTS
		$main = $sonoszonen[$zone][6];
		if ($main == "on")  {
			array_push($mainpl, $zone);
		}
	}
	# check if min. ONE player has been marked for T2S Announcement
	#if (count($mainpl) < 1)  {
	#	LOGINF('system/battery.php: No Zone for T2S Voice Notification has been marked in your Plugin Config.');
	#	exit(1);
	#}

	#print_r($mainpl);
	foreach ($sonoszonen as $zone => $player) {
		$src = $sonoszonen[$zone][7];
		$ip = $sonoszonen[$zone][0];
		# only check MOVE or ROAM devices
		if ($src == "S27" or $src == "S17")   {
			$port = 1400;
			$timeout = 3;
			$handle = @stream_socket_client("$ip:$port", $errno, $errstr, $timeout);
			# if Online check battery status
			if($handle) {
				# get battery status
				$url = "http://".$ip.":1400/status/batterystatus";
				$xml = simpleXML_load_file($url);
				$batlevel = $xml->LocalBatteryStatus->Data[1];
				$temperature = $xml->LocalBatteryStatus->Data[2];
				$health = $xml->LocalBatteryStatus->Data[0];
				$PowerSource = $xml->LocalBatteryStatus->Data[3];
				# check only if MOVE or ROAM is not charging and battery level is less then 30%
				if ($PowerSource != "SONOS_CHARGING_RING" and $batlevel <= 30)  {
					LOGWARN('system/battery.php: The battery level of "'.$zone.'" is about '.$batlevel.'%. Please charge your device!');
					#binlog("Battery check", "system/battery.php:: The battery level of '".$zone."' is about ".$batlevel."%. Please charge your device!");
					foreach ($mainpl as $main)   {
						$master = $main;
						$volume = $config['sonoszonen'][$master][3] + ($config['sonoszonen'][$master][3] * $config['TTS']['correction'] / 100);
						if (count($mainpl) > 0)  {
							$errortext = select_lang();
							sendmessage($errortext);
							LOGDEB('system/battery.php: Voice Notification has been announced on '.$main);
						} else {
							LOGINF('system/battery.php: No Zone for T2S Voice Notification has been marked in your Plugin Config.');
						}
						sleep(2);
					}
				}
				fclose($handle);
			} else {
				#binlog("Battery check", "bin/battery.php Zone '".$zone."' seems to be Offline, please check your power/network settings");
			}
		}
	}

	#print_r($mainpl);
	LOGOK("system/battery.php: Battery check has been performed");
}


/**
* Funktion : 	select_lang --> wählt die Sprache der error message aus.
*
* @param: empty
* @return: translations form error.json file
**/

function select_lang() {
	
	global $config, $pathlanguagefile, $ms, $batlevel, $main, $zone, $errortext, $errorvoice, $errorlang;
	
	$file = "battery.json";
	$url = $pathlanguagefile."/".$file;
	$valid_languages = json_decode(file_get_contents($url), true);
	$language = substr($config['TTS']['messageLang'], 0, 5);
	$isvalid = array_multi_search($language, $valid_languages, $sKey = "language");
	if (!empty($isvalid)) {
		$errortext = $isvalid[0]['value']; // Text
		$errorvoice = $isvalid[0]['voice']; // de-DE-Standard-A
		$errorlang = $isvalid[0]['language']; // de-DE
	} else {
		# if no translation for error in local language available, then exit and use use English (Google Cloud)
		$errortext = "The battery level of zone {$zone} is about {$batlevel} percent. Next check in about 1hour";
		$errorvoice = 'en-GB-Wavenet-A';
		$errorlang = 'en-GB';
		LOGINF("system/battery.php: Translation for your Standard language is not available, EN has been selected");	
	}
	$my_variable_name = 'zone';
	$my_value = $main; 
	$my_msg= $errortext; 
	$my_variable_name = $my_value;
	$my_msg = eval("return \"$my_msg\";");
	$errortext = $my_msg;
	return $errortext;
}



/**
* Funktion : 	parseConfigFile --> backup falls die Configdatei nicht vorhanden ist
*
* @param: empty
* @return: array($config)
**/
function parseConfigFile()    {
	
	global $master, $main, $zone, $ms, $batlevel, $config, $sonoszonen, $sonoszone, $myFolder;
	
	// Parsen der Konfigurationsdatei sonos.cfg
	if (!file_exists($myFolder.'/sonos.cfg')) {
		LOGWARN('system/battery.php: The file sonos.cfg could not be opened, please try again!');
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
		if ($tmpsonos === false)  {
			#binlog("Battery check", 'system/battery.php: The file sonos.cfg could not be parsed, the file may be disruppted. Please check/save your Plugin Config or check file "sonos.cfg" manually!');
			LOGERR('system/battery.php: The file sonos.cfg could not be parsed, the file may be disruppted. Please check/save your Plugin Config or check file "sonos.cfg" manually!');
			exit(1);
		}
		#LOGDEB("system/battery.php: Sonos config has been loaded");
	}
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		LOGWARN('system/battery.php: The file player.cfg could not be opened, please try again!');
	} else {
		$tmpplayer = parse_ini_file($myFolder.'/player.cfg', true);
		if ($tmpplayer === false)  {
			#binlog("Battery check", 'system/battery.php: The file player.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "player.cfg" manually!');
			LOGERR('system/battery.php: The file player.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "player.cfg" manually!');
			exit(1);
		}
		#LOGDEB("system/battery.php: Player config has been loaded");
	}
	$player = ($tmpplayer['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	} 
	$sonoszonen['sonoszonen'] = $sonosnet;
	
	// finale config für das Script
	$config = array_merge($sonoszonen, $tmpsonos);
	return $config;
}



function shutdown()
{
	global $log;
	LOGEND("Battery check finished");
}


?>