#!/usr/bin/env php
<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_log.php";

$myFolder = "$lbpconfigdir";									// get config folder
$myConfigFile = "player.cfg";									// get config file
$configfile	= "/run/shm/s4lox_config.json";						// configuration file

# load Sonos Configuration
	if (@!$data = file_get_contents($configfile)) {
		$config = parseConfigFile();
	} else {
		$config = json_decode(file_get_contents($configfile), TRUE);
	}
	$sonoszonen = ($config['sonoszonen']);
	$sonoszone = $sonoszonen;	
	
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


?>