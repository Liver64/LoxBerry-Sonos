#!/usr/bin/php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";

require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/Helper.php");

register_shutdown_function('shutdown');

$configfile		= "s4lox_config.json";
$off_file 		= $lbplogdir."/s4lox_off.tmp";					// path/file for Script turned off

	# check if script/Sonos Plugin is off
	if (file_exists($off_file)) {
		exit;
	}

	$params = [	"name" => "Sonos PHP",
				"filename" => "$lbplogdir/sonos.log",
				"append" => 1,
				"addtime" => 1,
				];
	$log = LBLog::newLog($params);
	#LOGSTART("CronJob started");

	#echo '<PRE>';
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		LOGCRIT('Sonos: bin/check_on_state.php: The configuration file could not be loaded, the file may be disrupted. We have to abort :-(');
		exit;
	}
	// Übernahme und Deklaration von Variablen aus der Konfiguration
	$sonoszonen = $config['sonoszonen'];
	
	# check if folder exists
	if (!is_dir("$lbpdatadir/PlayerStatus/"))   {
		mkdir("$lbpdatadir/PlayerStatus", 0755);
	}
	
	# Delete all Files
	@array_map('unlink', glob("$lbpdatadir/PlayerStatus/s4lox_on_*.txt"));
	
	// prüft den Onlinestatus jeder Zone
	$zonesonline = array();
	foreach($sonoszonen as $zonen => $ip) {
		$port = 1400;
		$timeout = 3;
		$handle = @stream_socket_client("$ip[0]:$port", $errno, $errstr, $timeout);
		if($handle) {
			$sonoszone[$zonen] = $ip;
			array_push($zonesonline, $zonen);
			fclose($handle);
			# create tmp file for each zone are online
			file_put_contents("$lbpdatadir/PlayerStatus/s4lox_on_".$zonen.".txt", "on");
			echo "<INFO> Player Online file 's4lox_on_".$zonen.".txt' has been created".PHP_EOL;
		} else {
			echo "<WARNING> File for Player '".$zonen."' has not been created. Maybe the Player is off... if so, please put Online and reboot Loxberry!".PHP_EOL;
			#LOGWARN("Sonos: bin/check_on_state.php: Zone $zonen seems to be Offline, please check your power/network settings");
		}
	}
	
	
function shutdown()  {
	
	#$log->LOGEND("CronJob finished");
	#LOGEND("PHP finished");
}
?>