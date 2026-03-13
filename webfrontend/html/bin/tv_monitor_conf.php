<?php

include "loxberry_system.php";
include "loxberry_log.php";
require_once($lbphtmldir.'/system/logging.php');
require_once($lbphtmldir."/system/sonosAccess.php");
require_once($lbphtmldir."/Speaker.php");

$configfile			= "s4lox_config.json";							// configuration file
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";			// Folder and file name for Player Status

echo "<PRE>";

	# load Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		exit;
	}
	$soundbars = identSB($config['sonoszonen'], $folfilePlOn);
	#print_r($soundbars);
			
	# turn on Autoplay for each soundbar
	foreach($soundbars as $key => $value)   {
		$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
		if ($config['VARIOUS']['tvmon'] == true)   {
			$sonos->SetAutoplayRoomUUID($soundbars[$key][1], "TV");
			$sonos->SetAutoplayLinkedZones("false", "TV");
		}
	}
?>