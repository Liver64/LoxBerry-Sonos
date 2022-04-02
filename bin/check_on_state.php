#!/usr/bin/php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";

require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/Helper.php");

register_shutdown_function('shutdown');

$myFolder = "$lbpconfigdir";								// get config folder
$FileName = "/run/shm/s4lox_sonoszone.json";

	$params = [	"name" => "Sonos PHP",
				"filename" => "$lbplogdir/sonos.log",
				"append" => 1,
				"addtime" => 1,
				];
	$log = LBLog::newLog($params);
	#LOGSTART("CronJob started");

	#echo '<PRE>';
	if (!file_exists($myFolder.'/sonos.cfg')) {
		LOGERR('Sonos: bin/check_on_state.php: The file sonos.cfg could not be opened, please try again!');
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
		if ($tmpsonos === false)  {
			LOGERR('Sonos: bin/check_on_state.php: The file sonos.cfg could not be parsed, the file may be disruppted. Please check/save your Plugin Config or check file "sonos.cfg" manually!');
			exit(1);
		}
		#LOGINF("Sonos: bin/check_on_state.php: Sonos config has been loaded");
	}
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		LOGERR('Sonos: bin/check_on_state.php: The file player.cfg could not be opened, please try again!');
	} else {
		$tmpplayer = parse_ini_file($myFolder.'/player.cfg', true);
		if ($tmpplayer === false)  {
			LOGERR('Sonos: bin/check_on_state.php: The file player.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "player.cfg" manually!');
			exit(1);
		}
		#LOGINF("Sonos: bin/check_on_state.php: Player config has been loaded");
	}
	$player = ($tmpplayer['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	} 
	$sonoszonen['sonoszonen'] = $sonosnet;
	// finale config für das Script
	$config = array_merge($sonoszonen, $tmpsonos);
	
	// Übernahme und Deklaration von Variablen aus der Konfiguration
	$sonoszonen = $config['sonoszonen'];
	
	if (!isset($config['SYSTEM']['checkonline']))  {
		$checkonline = true;
	} else if ($config['SYSTEM']['checkonline'] == "1")  {
		$checkonline = true;
	} else {
		$checkonline = false;
	}
	$zonesoff = "";
	if ($checkonline === true)  {
		// prüft den Onlinestatus jeder Zone
		$zonesonline = array();
		#LOGGING("Sonos: bin/check_on_state.php: Online check for Players will be executed",7);
		foreach($sonoszonen as $zonen => $ip) {
			$port = 1400;
			$timeout = 3;
			$handle = @stream_socket_client("$ip[0]:$port", $errno, $errstr, $timeout);
			if($handle) {
				$sonoszone[$zonen] = $ip;
				array_push($zonesonline, $zonen);
				fclose($handle);
			} else {
				LOGWARN("Sonos: bin/check_on_state.php: Zone $zonen seems to be Offline, please check your power/network settings");
			}
		}
		$zoon = implode(", ", $zonesonline);
		#LOGINF("Zone(s) $zoon are Online");
	} else {
		LOGWARN("Sonos: bin/check_on_state.php: You have not turned on Function to check if all your Players are powered on/online. PLease turn on function 'checkonline' in Plugin Config in order to secure your requests!");
		$sonoszone = $sonoszonen;
	}
	#LOGGING("All variables has been collected",7);
	
	#print_r($sonoszone);
	File_Put_Array_As_JSON($FileName, $sonoszone, $zip=false);
	
	
function shutdown()  {
	
	#$log->LOGEND("CronJob finished");
	#LOGEND("PHP finished");
}
?>