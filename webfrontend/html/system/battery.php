#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "loxberry_io.php";
require_once("$lbphtmldir/system/PHPSonos.php");
require_once("$lbphtmldir/system/logging.php");
require_once("$lbphtmldir/Helper.php");
require_once("$lbphtmldir/Play_T2S.php");
require_once("$lbphtmldir/Grouping.php");
require_once("$lbphtmldir/Restore_T2S.php");
require_once("$lbphtmldir/Save_T2S.php");
require_once("$lbphtmldir/Speaker.php");
require_once("$lbphtmldir/voice_engines/GoogleCloud.php");
require_once("$lbphtmldir/system/bin/openssl_file.class.php");

$myFolder = "$lbpconfigdir";									// get config folder
$myConfigFile = "player.cfg";									// get config file
$pathlanguagefile = "$lbphtmldir/voice_engines/langfiles";		// get languagefiles
$configfile	= "/run/shm/s4lox_config.json";						// configuration file
$Stunden = intval(strftime("%H"));
$sPassword = 'loxberry';

# Execute Cronjob manually
# sh /etc/cron.d/Sonos

ini_set('max_execution_time', 30); 		
register_shutdown_function('shutdown');
$ms = LBSystem::get_miniservers();

#echo "<PRE>";

# only between 9am till 21pm
if ($Stunden >=9 && $Stunden <21)   {
	
	global $master, $main, $zone, $ms, $batlevel, $configfile, $config;
	
	# load Sonos Configuration
	if (@!$data = file_get_contents($configfile)) {
		$config = parseConfigFile();
	} else {
		$config = json_decode(file_get_contents($configfile), TRUE);
	}
	$sonoszonen = ($config['sonoszonen']);
	$sonoszone = $sonoszonen;	
	$battzone = array();
	# check if MOVE or ROAM there
	foreach ($sonoszonen as $zone => $player) {
		$src = $sonoszonen[$zone][7];
		if ($src == "S27" or $src == "S17")   {
			array_push($battzone, $src);
		}
	}
	if (count($battzone) < 1)  {
		# No ROAM or MOVE
		exit;
	}
	# Start logging	
	#$log = LBLog::newLog( [ "name" => "Cronjobs", "stderr" => 1, "addtime" => 1 ] );

	#LOGSTART("Check Battery state");
	
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
	if (count($mainpl) < 1)  {
		#LOGINF('bin/battery.php No Zone for T2S Voice Notification has been marked in your Plugin Config.');
		#LOGOK("bin/battery.php Battery check has been performed");
		exit(1);
	}
	#print_r($mainpl);
	foreach ($sonoszonen as $zone => $player) {
		$src = $sonoszonen[$zone][7];
		$ip = $sonoszonen[$zone][0];
		# only check MOVE or ROAM devices
		if ($src == "S27" or $src == "S17")   {
			$port = 1400;
			$timeout = 1;
			$handle = @stream_socket_client("$ip:$port", $errno, $errstr, $timeout);
			# if Online check battery status
			if($handle) {
				# request battery status
				$url = "http://".$ip.":1400/status/batterystatus";
				$xml = simpleXML_load_file($url);
				$batlevel = $xml->LocalBatteryStatus->Data[1];
				$temperature = $xml->LocalBatteryStatus->Data[2];
				$health = $xml->LocalBatteryStatus->Data[0];
				$PowerSource = $xml->LocalBatteryStatus->Data[3];
				# check only if MOVE or ROAM is not charging and battery level is less then 30%
				if ($PowerSource == "BATTERY" and $batlevel <= 30)  {
					#LOGWARN('bin/battery.php The battery level of "'.$zone.'" is about '.$batlevel.'%. Please charge your device!');
					foreach ($mainpl as $main)   {
						$master = $main;
						$volume = $config['sonoszonen'][$master][3] + ($config['sonoszonen'][$master][3] * $config['TTS']['correction'] / 100);
						$errortext = select_lang();
						sendmessage($errortext);
						#LOGINF('bin/battery.php Voice Notification has been announced on '.$main);
						sleep(4);
					}
				} else {
					#LOGOK('bin/battery.php The battery level of "'.$zone.'" is about '.$batlevel.'%. Next check in about 2hours');
				}
				fclose($handle);
			} else {
				#LOGWARN("bin/battery.php Zone '".$zone."' seems to be Offline, please check your power/network settings");
			}
		}
	}
	#print_r($mainpl);
	LOGOK("bin/battery.php Battery check has been performed");
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
	$valid_languages = (file_get_contents($url));
	$valid_languages = json_decode($valid_languages, true);
	$language = $config['TTS']['messageLang'];
	$language = substr($language, 0, 5);
	$isvalid = array_multi_search($language, $valid_languages, $sKey = "language");
	if (!empty($isvalid)) {
		$errortext = $isvalid[0]['value']; // Text
		$errorvoice = $isvalid[0]['voice']; // de-DE-Standard-A
		$errorlang = $isvalid[0]['language']; // de-DE
	} else {
		# if no translation for error exit use English
		$errortext = "The battery level of zone {$zone} is about {$batlevel} percent. Next check in about 1hour";
		$errorvoice = 'en-GB-Wavenet-A';
		$errorlang = 'en-GB';
		#LOGINF("bin/battery.php Translation for your Standard language is not available, EN has been selected");	
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
		LOGWARN('bin/battery.php The file sonos.cfg could not be opened, please try again!');
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
		if ($tmpsonos === false)  {
			#LOGERR('bin/battery.php The file sonos.cfg could not be parsed, the file may be disruppted. Please check/save your Plugin Config or check file "sonos.cfg" manually!');
			exit(1);
		}
		#LOGDEB("bin/battery.php Sonos config has been loaded");
	}
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		#LOGWARN('bin/battery.php: The file player.cfg could not be opened, please try again!');
	} else {
		$tmpplayer = parse_ini_file($myFolder.'/player.cfg', true);
		if ($tmpplayer === false)  {
			#LOGERR('bin/battery.php The file player.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "player.cfg" manually!');
			exit(1);
		}
		#LOGDEB("bin/battery.php Player config has been loaded");
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
	#LOGEND("check finished");
}


?>