<?php

/**
* Submodul: Speaker
*
**/

/**
* Funktion : 	LineIn --> schaltet die angegebene Zone auf LineIn um (Cinch Eingang)
*
* @param: empty
* @return: empty
**/

function LineIn() {
	global $sonoszone, $master;
	
	$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
	$url = "http://" . $sonoszone[$master][0] . ":1400/xml/device_description.xml";
	$xml = simpleXML_load_file($url);
	$model = $xml->device->modelNumber;
	$model = allowLineIn($model);
	if ($model == true) {
		LOGGING("Line in has been selected successful",6);
		$sonos->SetAVTransportURI("x-rincon-stream:" . $sonoszone[$master][1]);
		$sonos->Play();	
	} else {
		LOGGING("The specified Zone does not support Line-in to be selected!", 3);
		exit;
	}
	
}



/**
* Funktion : 	SetVolumeModeConnect --> setzt für CONNECT ggf. die Lautstärke von fix auf variabel
*
* @param: $model --> alle gefundenen Devices
* @return: $models --> 0 or 1
**/

function SetVolumeModeConnect($mode, $zonenew)  {
	global $sonoszone, $sonos, $mode;
	
	$sonos = new PHPSonos($sonoszone[$zonenew][0]);
	$getModel = $sonoszone[$zonenew][2];
	$model = OnlyCONNECT($getModel);
	if ($model === true) {
		$uuid = $sonoszone[$zonenew][1];
		$sonos->SetVolumeMode($mode, $uuid);
		#LOGGING("Type of volume for CONNECT has been set successful",6);
	}
}


/**
* Funktion : 	GetVolumeModeConnect --> erfragt für CONNECT ggf. die Lautstärke von fix auf variabel
*
* @param: $model --> alle gefundenen Devices
* @return: $models --> true (Volume fixed) or false (Volume flexible)
**/

function GetVolumeModeConnect($player)  {
	global $sonoszone, $master, $sonos, $modeback, $player;
	
	$modeback = "";
	$sonos = new PHPSonos($sonoszone[$player][0]);
	$getModel = $sonoszone[$player][2];
	$model = OnlyCONNECT($getModel);
	if ($model === true) {
		$uuid = $sonoszone[$player][1];
		$modeback = $sonos->GetVolumeMode($uuid);
		$modeback === true ? $modeback = 'true' : $modeback = 'false';
		#LOGGING("Type of volume for CONNECT has been detected",6);
	}
	return $modeback;
}




?>