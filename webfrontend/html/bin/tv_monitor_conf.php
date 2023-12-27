<?php

include "loxberry_system.php";
include "loxberry_log.php";
require_once($lbphtmldir.'/system/logging.php');
require_once($lbphtmldir."/system/sonosAccess.php");
require_once($lbphtmldir."/Speaker.php");

$configfile			= "s4lox_config.json";							// configuration file
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";			// Folder and file name for Player Status

echo "<PRE>";

$params = [	"name" => "Sonos PHP",
				"filename" => "$lbplogdir/sonos.log",
				"append" => 1,
				"addtime" => 1,
				];
$level = LBSystem::pluginloglevel();
$log = LBLog::newLog($params);
LOGSTART("Sonos PHP");

	# load Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
		exit;
	}
	$sonoszone = ($config['sonoszonen']);
	# check Zones Online and create new temp array
	$zonesonline = array();
	foreach($sonoszone as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			$sonoszone[$zonen] = $ip;
			array_push($zonesonline, $zonen);
		}
	}
	# All Online Zones 
	$filtered = array_filter(
		$sonoszone,
		fn ($key) => in_array($key, $zonesonline),
		ARRAY_FILTER_USE_KEY
	);
	$sonoszone = $filtered;
	
	# Array with all predefined soundbars only
	#$soundbars = array_filter($sonoszone, fn($innerArr) => isset($innerArr[13]) && $innerArr[14] > 0);
	$soundbars = identSB();
		
	# turn on Autoplay for each soundbar
	foreach($soundbars as $key => $value)   {
		$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
		if ($config['VARIOUS']['tvmon'] == true)   {
			$rincon = $soundbars[$key][1];
			SetAutoplayRoomUUID($key, $rincon);
			LOGGING("/bin/tv_monitor_conf.php: TV Monitor for Player '".$key."' has been turned ON.", 5);
		} else {
			LOGGING("/bin/tv_monitor_conf.php: TV Monitor for Player '".$key."' has been turned OFF.", 5);
		}
	}


/**
/* Function : identSB --> identify Soundbars
/*
/* @param:  none
/* @return: array
**/

function identSB()    {
	
	global $sonoszone, $folfilePlOn;
	
	# Extract predefined soundbars only (marked with SB and Volume > 0)
	#$soundbars = array_filter($sonoszone, fn($innerArr) => isset($innerArr[11]) && $innerArr[12] > 0);
	$soundbars = array();
	foreach($sonoszone as $zone => $ip) {
		$existsb = array_key_exists('13', $ip);
		if ($existsb == true)  {
			$soundbars[$zone] = $ip;
		}
	}
	#print_r($soundbars);
	
	# ... and then check for their Online Status
	$zonesonline = array();	
	foreach($soundbars as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle == true) {
			$zonesonline[$zonen] = $ip;
		}
	}
	$soundbars = $zonesonline;
	return $soundbars;
}
	
#LOGEND("Sonos PHP");

?>