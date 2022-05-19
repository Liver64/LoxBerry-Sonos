#!/usr/bin/env php
<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_log.php";

#*** FILES ****
$configfile		= "s4lox_config.json";							// configuration file
$off_file 		= $lbplogdir."/s4lox_off.tmp";					// path/file for Script turned off

	# check if script/Sonos Plugin is off
	if (file_exists($off_file)) {
		exit;
	}

#echo "<PRE>";
	# load Sonos Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
		echo "<OK> Configuration file has been loaded".PHP_EOL;
	} else {
		echo "<ERROR> Configuration file could not be loaded".PHP_EOL;
		exit;
	}
		
	// Übernahme und Deklaration von Variablen aus der Konfiguration
	$sonoszonen = $config['sonoszonen'];
	
	$mainpl = array();
	# check if MOVE or ROAM there
	foreach ($sonoszonen as $zone => $player) {
		$src = $sonoszonen[$zone][6];
		if ($src == "on")   {
			array_push($mainpl, $src);
		}
	}
	if (count($mainpl) < 1)  {
		# No Player marked
		$L = LBSystem::readlanguage("sonos.ini");
		# Create an informational notification for the group "Sonos" (part of postupgradscript.sh)
		notify(LBPPLUGINDIR, "Sonos", $L['ERRORS.NOTE_UPGRADE']);
		echo "<OK> Notify to update Config has been created".PHP_EOL;
	} else {
		echo "<OK> Player already marked, nothing to do".PHP_EOL;
	}


?>