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
	
	$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
	$url = "http://" . $sonoszone[$master][0] . ":1400/xml/device_description.xml";
	$xml = simpleXML_load_file($url);
	$model = $xml->device->modelNumber;
	$model = allowLineIn($model);
	if ($model == true) {
		LOGGING("speaker.php: Line in has been selected successful",6);
		$sonos->SetAVTransportURI("x-rincon-stream:" . $sonoszone[$master][1]);
		$sonos->Play();	
	} else {
		LOGGING("speaker.php: The specified Zone does not support Line-in to be selected!", 3);
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
	global $sonoszone, $sonos, $mode, $time_start;
	
	$sonos = new SonosAccess($sonoszone[$zonenew][0]);
	$getModel = $sonoszone[$zonenew][2];
	$model = OnlyCONNECT($getModel);
	if ($model === true) {
		$uuid = $sonoszone[$zonenew][1];
		$sonos->SetVolumeMode($mode, $uuid);
		#LOGGING("speaker.php: Type of volume for CONNECT has been set successful",6);
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
	$sonos = new SonosAccess($sonoszone[$player][0]);
	$getModel = $sonoszone[$player][2];
	$model = OnlyCONNECT($getModel);
	if ($model === true) {
		$uuid = $sonoszone[$player][1];
		$modeback = $sonos->GetVolumeMode($uuid);
		$modeback === true ? $modeback = 'true' : $modeback = 'false';
		#LOGGING("speaker.php: Type of volume for CONNECT has been detected",6);
	}
	return $modeback;
}


/**
* Funktion : 	GetAutoplayRoomUUID --> erfragt die Auto Play Rincon-ID
*
* @param: none
* @return: array
**/

function GetAutoplayRoomUUID($key)  {
	
	global $soundbars;
	
	$sonos = new SonosAccess($soundbars[$key][0]);
	$AutoPlayUUID = $sonos->GetAutoplayRoomUUID();
	print_r($AutoPlayUUID);
	return $AutoPlayUUID;
}


/**
* Funktion : 	SetAutoplayRoomUUID --> schaltet Autoplay Ein
*
* @param: none
* @return: none
**/

function SetAutoplayRoomUUID($key, $rincon)  {
	
	global $soundbars;
	
	try {
		$sonos = new SonosAccess($soundbars[$key][0]);
		$sonos->SetAutoplayRoomUUID($rincon);
		if (!empty($rincon))   {
			LOGGING("/bin/speaker.php: TV Autoplay mode for Player ".$key." has been set activ.", 7);
			echo "TV Autoplay mode for Player ".$key." has been set activ.\n";
		} else {
			LOGGING("/bin/speaker.php: TV Autoplay mode for Player ".$key." has been set inactiv.", 7);
			echo "TV Autoplay mode for Player ".$key." has been set inactiv.\n";
		}
	} catch (Exception $e) {
		LOGGING("/bin/speaker.php: Player ".$key." could not be set to TV Autoplay mode.", 3);
		echo "Player ".$key." could not be set to TV Autoplay mode.";
	}
}


/**
* Funktion : 	GetAutoplayLinkedZones --> erfragt ob AutoPlay linked Zones aktiv/inaktiv ist
*
* @param: none
* @return: array
**/

function GetAutoplayLinkedZones()  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$AutoPlayZones = $sonos->GetAutoplayLinkedZones();
	print_r($AutoPlayZones);
}


/**
* Funktion : 	SetAutoplayLinkedZones --> schaltet AutoPlay linked Zones aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetAutoplayLinkedZones()  {
	
	global $sonoszone, $master;
	
	if (isset($_GET['status']))   {
		$value = ($_GET['status']);
		try {
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$AutoPlayZones = $sonos->SetAutoplayLinkedZones($value);
			LOGGING("/bin/speaker.php: Include linked zones for Player ".$master." has been set to ".$value." in TV Autoplay mode.", 7);
			echo "Include linked zones for Player ".$master." has been set to ".$value." in TV Autoplay mode.";
		} catch (Exception $e) {
			LOGGING("/bin/speaker.php: Include linked zones for Player ".$master." could not be set to TV Autoplay mode.", 3);
			echo "Include linked zones for Player ".$master." could not be set to TV Autoplay mode.";
		}
	} else {
		LOGGING("/bin/speaker.php: For Player ".$master." the status is missing. Please add '&status=true' or '&status=false' to your syntax", 3);
		echo "For Player ".$master." the status is missing. Please add '&status=true' or '&status=false' to your syntax";
	}
}


/**
* Funktion : 	GetAutoplayVolume --> erfragt Volume für AutoPlay
*
* @param: none
* @return: array
**/

function GetAutoplayVolume()  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$AutoPlayVol = $sonos->GetAutoplayVolume();
	print_r($AutoPlayVol);
}


/**
* Funktion : 	SetAutoplayVolume --> setzt Volume für AutoPlay
*
* @param: none
* @return: none
**/

function SetAutoplayVolume()  {
	
	global $sonoszone, $master;
	
	if (isset($_GET['volume']))   {
		$value = ($_GET['volume']);
		try {
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$AutoPlayZones = $sonos->SetAutoplayVolume($value);
			LOGGING("/bin/speaker.php: Autoplay Volume for Player '".$master."' has been set to ".$value." in TV Autoplay mode.", 7);
			echo "Autoplay Volume for Player '".$master."' has been set to ".$value." in TV Autoplay mode.";
		} catch (Exception $e) {
			LOGGING("/bin/speaker.php: Autoplay Volume for Player ".$master." could not be set!", 3);
			echo "Autoplay Volume for Player ".$master." could not be set!";
		}
	} else {
		LOGGING("/bin/speaker.php: For Player ".$master." the volume is missing. Please add '&volume=<VALUE>' to your syntax", 3);
		echo "For Player ".$master." the volume is missing. Please add '&volume=<VALUE>' to your syntax";
	}
}


/**
* Funktion : 	GetUseAutoplayVolume --> erfragt Volume für AutoPlay aktiv/inaktiv ist
*
* @param: none
* @return: array
**/

function GetUseAutoplayVolume()  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$AutoPlayUseVol = $sonos->GetUseAutoplayVolume();
	print_r($AutoPlayUseVol);
}


/**
* Funktion : 	SetUseAutoplayVolume --> schaltet Use of Volume für AutoPlay aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetUseAutoplayVolume()  {
	
	global $sonoszone, $master;
	
	if (isset($_GET['status']))   {
		$value = ($_GET['status']);
		try {
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$AutoPlayZones = $sonos->SetUseAutoplayVolume($value);
			LOGGING("/bin/speaker.php: Use Auto Play Volume for Player ".$master." has been set to ".$value." in TV Autoplay mode.", 7);
			echo "Use Auto Play Volume for Player ".$master." has been set to ".$value." in TV Autoplay mode.";
		} catch (Exception $e) {
			LOGGING("/bin/speaker.php: Use Auto Play Volume for Player ".$master." could not be set!", 3);
			echo "Use Auto Play Volume for Player ".$master." could not be set!";
		}
	} else {
		LOGGING("/bin/speaker.php: For Player ".$master." the status is missing. Please add '&status=true' or '&status=false' to your syntax", 3);
		echo "For Player ".$master." the status is missing. Please add '&status=true' or '&status=false' to your syntax";
	}
}


/**
* Funktion : 	GetButtonLockState() --> erfragt Button Lock state
*
* @param: none
* @return: string	(On or Off)
**/

function GetButtonLockState()  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$lockstate = $sonos->GetButtonLockState();
	print_r($lockstate);
}


/**
* Funktion : 	SetButtonLockState() --> schaltet Button Lock state aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetButtonLockState()  {
	
	global $sonoszone, $master;
	
	if (isset($_GET['status']))   {
		$value = ($_GET['status']);
		try {
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$AutoPlayZones = $sonos->SetButtonLockState($value);
			LOGGING("/bin/speaker.php: Button lock state for Player ".$master." has been set to ".$value." in TV Autoplay mode.", 7);
			echo "Button lock state for Player ".$master." has been set to ".$value." in TV Autoplay mode.";
		} catch (Exception $e) {
			LOGGING("/bin/speaker.php: Button lock state for Player ".$master." could not be set!", 3);
			echo "Button lock state for Player ".$master." could not be set!";
		}
	} else {
		LOGGING("/bin/speaker.php: For Player ".$master." the status is missing. Please add '&status=true' or '&status=false' to your syntax", 3);
		echo "For Player ".$master." the status is missing. Please add '&status=true' or '&status=false' to your syntax";
	}
}


/**
* Funktion : 	GetHtMode --> erfragt den HT Status (Streaming, TV on/off)
*
* @param: none
* @return: none
**/

function GetHtMode()  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$tvmodi = $sonos->GetZoneInfo();
	echo $tvmodi['HTAudioIn'];
}


/**
* Funktion : 	SetSpeechMode() --> schaltet den Speech Mode aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetSpeechMode($mode)  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$pos = $sonos->GetPositionInfo();
	if (substr($pos["TrackURI"], 0, 18) === "x-sonos-htastream:")  {
		if (isset($_GET['mode']))   {
			$mode = $_GET['mode'];
		} else {
			$mode;
		}
		if ($mode == 'on')  {
			$sonos->SetDialogLevel('DialogLevel');
			LOGGING("/bin/speaker.php: Speech enhancement for Player ".$master." has been turned on.", 7);
		} else {
			$sonos->SetDialogLevel('DialogLevel');
			LOGGING("/bin/speaker.php: Speech enhancement for Player ".$master." has been turned off.", 7);
		}
	} else {
		LOGGING("/bin/speaker.php: Player ".$master." is not in TV mode.", 4);
	}
}


/**
* Funktion : 	SetNightMode() --> schaltet den Night Mode aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetNightMode($mode)  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$pos = $sonos->GetPositionInfo();
	if (substr($pos["TrackURI"], 0, 18) === "x-sonos-htastream:")  {
		if (isset($_GET['mode']))   {
			$mode = $_GET['mode'];
		} else {
			$mode;
		}
		if ($mode == 'on')  {
			$sonos->SetDialogLevel('1', 'NightMode');
			LOGGING("/bin/speaker.php: Nightmode for Player ".$master." has been turned on.", 7);
		} else {
			$sonos->SetDialogLevel('0', 'NightMode');
			LOGGING("/bin/speaker.php: Nightmode for Player ".$master." has been turned off.", 7);
		}
	} else {
		LOGGING("/bin/speaker.php: Player ".$master." is not in TV mode.", 4);
	}
}


/**
* Funktion : 	SetSurroundMode() --> schaltet den Surround Mode aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetSurroundMode($mode)  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$pos = $sonos->GetPositionInfo();
	if (substr($pos["TrackURI"], 0, 18) === "x-sonos-htastream:")  {
		if (isset($_GET['mode']))   {
			$mode = $_GET['mode'];
		} else {
			$mode;
		}
		if ($mode == 'on')  {
			$sonos->SetDialogLevel('1', 'SurroundEnable');
			LOGGING("/bin/speaker.php: Surround for Player ".$master." has been turned on.", 7);
		} else {
			$sonos->SetDialogLevel('0', 'SurroundEnable');
			LOGGING("/bin/speaker.php: Surround for Player ".$master." has been turned off.", 7);
		}
	} else {
		LOGGING("/bin/speaker.php: Player ".$master." is not in TV mode.", 4);
	}
}


/**
* Funktion : 	SetBassMode() --> schaltet den Bass Mode aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetBassMode($mode)  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$pos = $sonos->GetPositionInfo();
	if (substr($pos["TrackURI"], 0, 18) === "x-sonos-htastream:")  {
		if (isset($_GET['mode']))   {
			$mode = $_GET['mode'];
		} else {
			$mode;
		}
		if ($mode == 'on')  {
			$sonos->SetDialogLevel('1', 'SubEnable');
			LOGGING("/bin/speaker.php: SubBass for Player ".$master." has been turned on.", 7);
		} else {
			$sonos->SetDialogLevel('0', 'SubEnable');
			LOGGING("/bin/speaker.php: SubBass for Player ".$master." has been turned off.", 7);
		}
	} else {
		LOGGING("/bin/speaker.php: Player ".$master." is not in TV mode.", 4);
	}
}

?>