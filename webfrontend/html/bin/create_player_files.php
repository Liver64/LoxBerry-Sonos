#!/usr/bin/php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";

require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/Helper.php");

$configfile		= "s4lox_config.json";

	echo '<PRE>';
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		echo "<ERROR> The configuration file could not be loaded, the file may be disrupted. We have to abort :-(".PHP_EOL;
		exit;
	}
	// Übernahme und Deklaration von Variablen aus der Konfiguration
	$sonoszonen = $config['sonoszonen'];
	#print_r($sonoszonen);
	
		# check if folder exists
	if (!is_dir("$lbpdatadir/PlayerStatus/"))   {
		mkdir("$lbpdatadir/PlayerStatus", 0755);
	}
	
	// prüft den Onlinestatus jeder Zone
	foreach($sonoszonen as $zonen => $ip) {
		if (!file_exists("$lbpdatadir/PlayerStatus/s4lox_on_".$zonen.".txt"))   {
			file_put_contents("$lbpdatadir/PlayerStatus/s4lox_on_".$zonen.".txt", "on");
		}
		echo "<OK> Player Online file 's4lox_on_".$zonen.".txt' has been created".PHP_EOL;
		# get Player Mini PNG
		$url = 'http://'.$ip[0].':1400/img/icon-'.$ip[7].'.png';
		$img = $lbphtmldir.'/images/icon-'.$ip[7].'.png';
		if (!file_exists($img)) {
			file_put_contents($img, file_get_contents($url));
		}
	}
	echo "<INFO> Players has been successful executed".PHP_EOL;
	
?>