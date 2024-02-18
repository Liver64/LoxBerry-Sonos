<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";

echo "<PRE>";
	
$version = LBSystem::pluginversion();

# if function called directly by URL then overwrite actual LB Version to lower in order to execute script
$call = "false";
try {
	if (!isset($_SERVER['REQUEST_URI']))  {
		echo "<OK> URL could not be obtainend, we move forward".PHP_EOL;
	} else {
		$syntax = $_SERVER['REQUEST_URI'];
		$pos = strrpos($syntax, "/");
		$rest = substr($syntax, $pos+1, $pos+20); 
		if ($rest == "create_config.php")    {
			$version = "5.3.8";
			$call = "true";
			echo "<OK> Your actual Version has been temporally resetted to v5.3.8".PHP_EOL;
		}
	}
} catch (Exception $e) {
	echo "<OK> URL could not be obtainend".PHP_EOL;
}
	
if ($version < "5.4.0")   {
	create_JSON_config();
	echo "<OK> New JSON configuration file was required. Your actual Version is now: v".LBSystem::pluginversion()."".PHP_EOL;
	#LOGOK("bin/create_config.php: New JSON configuration file required. Your actual Version is: v".$version);
} else {
	echo "<INFO> The JSON configuration is up-to-date, nothing to do :-)".PHP_EOL;
	#LOGINF("bin/create_config.php: The JSON configuration is up-to-date, nothing to do.");
}


function create_JSON_config()    {
	
	global $lbpconfigdir, $lbpplugindir, $lbpdatadir, $tmpplayer, $call, $tmpsonos;
	
	$configfile	= $lbpconfigdir."/s4lox_config.json";
	
	# Path to player.cfg files
	$pathfileplayer = $lbpconfigdir.'/player.cfg';
	$pathbackupfileplayer = $lbpdatadir.'/Backup/'.$lbpplugindir.'/config/player.cfg';
	
	# Path to sonos.cfg files
	$pathfilesonos = $lbpconfigdir.'/sonos.cfg';
	$pathbackupfilesonos = $lbpdatadir.'/Backup/'.$lbpplugindir.'/config/sonos.cfg';	
	
	$mask = 'player*.*';	// mask for deletion
	
	// check to parse der Konfigurationsdatei sonos.cfg
	if (is_file($pathfilesonos)) {
		parsesonosconfig($pathfilesonos);
		echo "<INFO> The file sonos.cfg has been parsed from config directory".PHP_EOL;
	} elseif (is_file($pathbackupfilesonos)) {
		parsesonosconfig($pathbackupfilesonos);
		echo "<INFO> The file sonos.cfg has been parsed from Backup directory".PHP_EOL;
	} else {
		#LOGERR('bin/create_config.php: The file sonos.cfg could not be opened neither in config, nore in backup folder. We skip...');
		echo "<INFO> The file sonos.cfg could not be opened neither in config, nore in backup folder. We skip...')".PHP_EOL;
		exit(1);
	}
	
	// check to parse der Sonos Zonen Konfigurationsdatei player.cfg
	if (is_file($pathfileplayer)) {
		parseplayerconfig($pathfileplayer);
		echo "<INFO> The file player.cfg has been parsed from config directory".PHP_EOL;
	} elseif (is_file($pathbackupfileplayer)) {
		parseplayerconfig($pathbackupfileplayer);
		echo "<INFO> The file player.cfg has been parsed from Backup directory".PHP_EOL;
	} else {
		#LOGERR('bin/create_config.php: The file player.cfg could not be opened neither in config, nore in backup folder. We skip...');
		echo "<INFO> The file player.cfg could not be opened neither in config, nore in backup folder. We skip...')".PHP_EOL;
		exit(1);
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
		file_put_contents($configfile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		echo "<INFO> JSON configuration file has been updated".PHP_EOL;
	} else {
		file_put_contents($configfile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		echo "<INFO> New JSON configuration file has been created".PHP_EOL;
	}
	if ($call == "true")    {
		$version = LBSystem::pluginversion();
		include('updateplayer.php');
	}
	array_map('unlink', glob($lbpconfigdir."/".$mask));
	@unlink($lbpconfigdir.'/sonos.cfg');
}
	
	
/**
/* Function : parseplayerconfig --> parse old player.cfg file
/*
/* @param:  path to file
/* @return: array()
**/
	function parseplayerconfig($value)    {
		
		global $tmpplayer;
		
		$tmpplayer = parse_ini_file($value, true);
		if ($tmpplayer === false)  {
			LOGERR('bin/create_config.php: The file player.cfg could not be parsed, the file may be disrupted');
			exit(1);
		}
		LOGDEB("bin/create_config.php: Player config has been loaded");
		return $tmpplayer;
	}

/**
/* Function : parsesonosconfig --> parse old sonos.cfg file
/*
/* @param:  path to file
/* @return: array()
**/	
	function parsesonosconfig($value)    {
		
		global $tmpsonos;
		
		$tmpsonos = parse_ini_file($value, TRUE, INI_SCANNER_RAW);
		if ($tmpsonos === false)  {
			LOGERR('bin/create_config.php: The file sonos.cfg could not be parsed, the file may be disrupted');
			exit(1);
		}
		LOGDEB("bin/create_config.php: Sonos config has been loaded");
		return $tmpsonos;
	}
	
?>