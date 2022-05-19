#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once("$lbphtmldir/system/sonosAccess.php");

# check if file exist
$maxvolfile		= "/run/shm/s4lox_max_volume.json";	
$off_file 		= $lbplogdir."/s4lox_off.tmp";					// path/file for Script turned off

# check if script/Sonos Plugin is off
if (file_exists($off_file)) {
	exit;
}

if (!is_file($maxvolfile))   {
	exit;
} else {
	$result = json_decode(file_get_contents($maxvolfile), true);
	// set Volume per zone
	foreach ($result['zones'] as $key)    {
		$sonos = new SonosAccess($key);
		$currvol = $sonos->GetVolume();
		if ($currvol > $result['volume'])   {
			$sonos->SetVolume($result['volume']);
		}
	}
}

?>