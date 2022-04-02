#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once("$lbphtmldir/system/PHPSonos.php");

# check if file exist
$maxvolfile	= "/run/shm/s4lox_max_volume.json";";	

if (!is_file($maxvolfile))   {
	exit;
} else {
	$result = json_decode(file_get_contents($maxvolfile), true);
	// set Volume per zone
	foreach ($result['zones'] as $key)    {
		$sonos = new PHPSonos($key);
		$currvol = $sonos->GetVolume();
		if ($currvol > $result['volume'])   {
			$sonos->SetVolume($result['volume']);
		}
	}
}

?>