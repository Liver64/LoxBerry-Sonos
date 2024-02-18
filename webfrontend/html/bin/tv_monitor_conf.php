<?php

include "loxberry_system.php";
include "loxberry_log.php";
require_once($lbphtmldir.'/system/logging.php');
require_once($lbphtmldir."/system/sonosAccess.php");
require_once($lbphtmldir."/Speaker.php");

$configfile			= "s4lox_config.json";							// configuration file
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";			// Folder and file name for Player Status

echo "<PRE>";

$params = [	"name" => "TV Monitor",
				"filename" => "$lbplogdir/sonos.log",
				"append" => 1,
				"addtime" => 1,
				];
$level = LBSystem::pluginloglevel();
$log = LBLog::newLog($params);
LOGSTART("TV Monitor");

	# load Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
		exit;
	}
	$soundbars = identSB($config['sonoszonen'], $folfilePlOn);
	#print_r($soundbars);
			
	# turn on Autoplay for each soundbar
	foreach($soundbars as $key => $value)   {
		$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
		if ($config['VARIOUS']['tvmon'] == true)   {
			SetAutoplayRoomUUID($key, $soundbars[$key][1]);
			SetAutoplayLinkedZones('false', $soundbars[$key][0], $key);
		}
	}
#@LOGEND("TV Monitor");
?>