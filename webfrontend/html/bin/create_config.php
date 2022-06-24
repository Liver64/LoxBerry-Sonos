#!/usr/bin/env php
<?php

	require_once "loxberry_system.php";
	require_once "loxberry_log.php";
	
	#echo "<PRE>";
	$configfile	= $lbpconfigdir."/s4lox_config.json";	
	
	// Parsen der Konfigurationsdatei sonos.cfg
	if (!is_file($lbpconfigdir.'/sonos.cfg')) {
		LOGWARN('sonos.php: The file sonos.cfg could not be opened, please try again!', 4);
	} else {
		$tmpsonos = parse_ini_file($lbpconfigdir.'/sonos.cfg', TRUE);
		if ($tmpsonos === false)  {
			LOGERR('sonos.php: The file sonos.cfg could not be parsed, the file may be disruppted. Please check/save your Plugin Config or check file "sonos.cfg" manually!');
			exit(1);
		}
		LOGDEB("sonos.php: Sonos config has been loaded",7);
	}
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!is_file($lbpconfigdir.'/player.cfg')) {
		LOGWARN('sonos.php: The file player.cfg could not be opened, please try again!', 4);
	} else {
		$tmpplayer = parse_ini_file($lbpconfigdir.'/player.cfg', true);
		if ($tmpplayer === false)  {
			LOGERR('sonos.php: The file player.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "player.cfg" manually!');
			exit(1);
		}
		LOGDEB("sonos.php: Player config has been loaded",7);
	}

	$player = ($tmpplayer['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	} 
	$sonoszonen['sonoszonen'] = $sonosnet;
	
	// finale config für das Script
	$config = array_merge($sonoszonen, $tmpsonos);
	if (file_exists($configfile)) {
		@unlink($configfile);
		file_put_contents($configfile, json_encode($config, JSON_PRETTY_PRINT));
	} else {
		file_put_contents($configfile, json_encode($config, JSON_PRETTY_PRINT));
		echo "<INFO> The JSON configuration file has been created".PHP_EOL;
	}
	#print_r($sonoszonen);
	#print_r($tmpsonos);
?>


