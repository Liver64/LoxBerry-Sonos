#!/usr/bin/php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";

require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/Helper.php");

$configfile		= "s4lox_config.json";
$off_file 		= $lbplogdir."/s4lox_off.tmp";					// path/file for Script turned off
$updatefile 	= "/run/shm/Sonos4lox_update.json";				// Status file during Sonos Update

	echo '<PRE>';
	# check if script/Sonos Plugin is off
	if (file_exists($off_file)) {
		exit;
	}
	# check if Sonos Firmware Update is running
	if (file_exists($updatefile)) {
		echo "<WARNING> Sonos Update is currently running, we abort here...".PHP_EOL;
		exit;
	}

	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		#$params = [	"name" => "Sonos PHP",
		#		"filename" => "$lbplogdir/sonos.log",
		#		"append" => 1,
		#		"addtime" => 1,
		#		];
		#$log = LBLog::newLog($params);
		#LOGCRIT('Sonos: bin/check_on_state.php: The configuration file could not be loaded, the file may be disrupted. We have to abort :-(');
		exit;
	}
	// Übernahme und Deklaration von Variablen aus der Konfiguration
	$sonoszonen = $config['sonoszonen'];
	#print_r($sonoszonen);
	
	# check if folder exists
	if (!is_dir("$lbpdatadir/PlayerStatus/"))   {
		mkdir("$lbpdatadir/PlayerStatus", 0755);
	}
	
	# Delete all Files
	@array_map('unlink', glob("$lbpdatadir/PlayerStatus/s4lox_on_*.txt"));
	
	// prüft den Onlinestatus jeder Zone
	#$zonesonline = array();
	foreach($sonoszonen as $zonen => $ip) {
		$port = 1400;
		$timeout = 3;
		$handle = @stream_socket_client("$ip[0]:$port", $errno, $errstr, $timeout);
		if($handle) {
			#$sonoszone[$zonen] = $ip;
			#array_push($zonesonline, $zonen);
			fclose($handle);
			# create tmp file for each zone are online
			file_put_contents("$lbpdatadir/PlayerStatus/s4lox_on_".$zonen.".txt", "on");
			echo "<INFO> Player Online file 's4lox_on_".$zonen.".txt' has been created".PHP_EOL;
			# get Player Mini PNG
			$url = 'http://'.$ip[0].':1400/img/icon-'.$ip[7].'.png';
			$img = $lbphtmldir.'/images/icon-'.$ip[7].'.png';
			if (!file_exists($img)) {
				file_put_contents($img, file_get_contents($url));
			}
		} else {
			echo "<WARNING> File for Player '".$zonen."' has not been created. Maybe the Player is off... if so, please put Online and reboot Loxberry!".PHP_EOL;
			#LOGWARN("Sonos: bin/check_on_state.php: Zone $zonen seems to be Offline, please check your power/network settings");
		}	
	}
	
?>