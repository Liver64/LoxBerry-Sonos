#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "PHPSonos.php";
require_once('logging.php');
require_once("../Helper.php");
require_once("../Play_T2S.php");
require_once("../Grouping.php");
require_once("../Restore_T2S.php");
require_once("../Save_T2S.php");
require_once("../Speaker.php");
require_once("../voice_engines/GoogleCloud.php");
require_once("bin/openssl_file.class.php");

$myFolder = "$lbpconfigdir";									// get config folder
$myConfigFile = "player.cfg";									// get config file
$pathlanguagefile = "$lbphtmldir/voice_engines/langfiles";		// get languagefiles
$Stunden = intval(strftime("%H"));
$sPassword = 'loxberry';

header( 'Content-type: text/html; charset=utf-8' );
ini_set('max_execution_time', 30); 		
register_shutdown_function('shutdown');
$ms = LBSystem::get_miniservers();

# only between 8am till 21pm
if ($Stunden >=8 && $Stunden <21)   {
	
	global $master, $main, $zone, $ms, $batlevel;
	
	$log = LBLog::newLog( [ "name" => "Cronjobs", "stderr" => 1, "addtime" => 1 ] );
	echo "<PRE>";
	LOGSTART("Check Battery state");
	
	// Parsen der Konfigurationsdatei sonos.cfg
	if (!file_exists($myFolder.'/sonos.cfg')) {
		LOGGING('system/battery.php: The file sonos.cfg could not be opened, please try again!', 4);
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
		if ($tmpsonos === false)  {
			LOGERR('system/battery.php: The file sonos.cfg could not be parsed, the file may be disruppted. Please check/save your Plugin Config or check file "sonos.cfg" manually!');
			exit(1);
		}
		LOGGING("system/battery.php: Sonos config has been loaded",7);
	}
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		LOGGING('sonos.php: The file player.cfg could not be opened, please try again!', 4);
	} else {
		$tmpplayer = parse_ini_file($myFolder.'/player.cfg', true);
		if ($tmpplayer === false)  {
			LOGERR('system/battery.php: The file player.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "player.cfg" manually!');
			exit(1);
		}
		LOGGING("system/battery.php: Player config has been loaded",7);
	}
	$player = ($tmpplayer['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	} 
	$sonoszonen['sonoszonen'] = $sonosnet;
	
	// finale config für das Script
	$config = array_merge($sonoszonen, $tmpsonos);
	$sonoszonen = ($config['sonoszonen']);
	$sonoszone = $sonoszonen;
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
		LOGINF('system/battery.php: No Zone for T2S Voice Notification has been marked in your Plugin Config.');
		LOGOK("system/battery.php: Battery check has been performed");
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
				# check only if MOVE or ROAM is not charging and battery level is less then 20%
				if ($PowerSource == "BATTERY" and $batlevel <= 30)  {
					LOGWARN('system/battery.php: The battery level of "'.$zone.'" is about '.$batlevel.'%. Please charge your device!');
					foreach ($mainpl as $main)   {
						$master = $main;
						$volume = $config['sonoszonen'][$master][3] + ($config['sonoszonen'][$master][3] * $config['TTS']['correction'] / 100);
						$errortext = select_lang();
						sendmessage($errortext);
						LOGINF('system/battery.php: Voice Notification has been announced on '.$main);
						sleep(4);
					}
				} else {
					LOGOK('system/battery.php: The battery level of "'.$zone.'" is about '.$batlevel.'%. Next check in about 2hours');
				}
				fclose($handle);
			} else {
				LOGWARN("system/battery.php: Zone '".$zone."' seems to be Offline, please check your power/network settings");
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
	$valid_languages = (file_get_contents($url));
	$valid_languages = json_decode($valid_languages, true);
	#print_r($valid_languages);
	$language = $config['TTS']['messageLang'];
	$language = substr($language, 0, 5);
	$isvalid = array_multi_search($language, $valid_languages, $sKey = "language");
	#print_r($isvalid);
	if (!empty($isvalid)) {
		$errortext = $isvalid[0]['value']; // Text
		$errorvoice = $isvalid[0]['voice']; // de-DE-Standard-A
		$errorlang = $isvalid[0]['language']; // de-DE
	} else {
		# if no translation for error exit use English
		$errortext = "The battery level of zone {$zone} is about {$batlevel} percent. Next check in about 2hours";
		$errorvoice = 'en-GB-Wavenet-A';
		$errorlang = 'en-GB';
		LOGGING("system/battery.php: Translation for your Standard language is not available, EN has been selected", 6);	
	}
	$my_variable_name = 'zone';
	$my_value = $main; 
	$my_msg= $errortext; 
	$my_variable_name = $my_value;
	$my_msg = eval("return \"$my_msg\";");
	$errortext = $my_msg;
	return $errortext;

	

}



function shutdown()
{
	global $log;
	LOGEND("check finished");
	
}

?>