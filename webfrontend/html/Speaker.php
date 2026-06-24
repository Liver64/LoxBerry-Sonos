<?php

/**
* Submodul: Speaker
* Version: SPEAKER_HARDENING_V01_2026_06_19
*
* Player/model helper functions moved here from Helper.php.
*
**/

/* =================================================================================================
 * Local hardening helpers for this legacy non-src file.
 * Keep logging on native LoxBerry functions here; src/Support/Logger.php is only used by src files.
 * ================================================================================================= */

function s4lox_speaker_log_value($value) {
	return str_replace(array("\r", "\n"), ' ', (string)$value);
}

function s4lox_speaker_model_has($model, $models) {
	return isset($models[(string)$model]);
}

function s4lox_speaker_validate_zone($zone) {
	global $sonoszone;

	$zone = trim((string)$zone);
	if ($zone === '' || empty($sonoszone[$zone][0])) {
		LOGWARN("Speaker.php: Unknown or incomplete Sonos zone '" . s4lox_speaker_log_value($zone) . "'. Request aborted.");
		return false;
	}

	return true;
}

function s4lox_speaker_get_volume_param($name, $required) {
	if (!isset($_GET[$name]) || trim((string)$_GET[$name]) === '') {
		if ($required) {
			LOGWARN("Speaker.php: Required URL parameter '" . $name . "' is missing. Please check the request syntax.");
		}
		return null;
	}

	$value = trim((string)$_GET[$name]);
	if (!is_numeric($value)) {
		LOGWARN("Speaker.php: URL parameter '" . $name . "' must be numeric between 0 and 100. Entered value: '" . s4lox_speaker_log_value($value) . "'.");
		return null;
	}

	$value = (int)$value;
	if ($value < 0 || $value > 100) {
		LOGWARN("Speaker.php: URL parameter '" . $name . "' must be between 0 and 100. Entered value: '" . $value . "'.");
		return null;
	}

	return $value;
}

function s4lox_speaker_get_bool_param($name, $required, $default) {
	if (!isset($_GET[$name]) || trim((string)$_GET[$name]) === '') {
		if ($required) {
			LOGWARN("Speaker.php: Required URL parameter '" . $name . "' is missing. Use true or false.");
		}
		return $default;
	}

	$value = strtolower(trim((string)$_GET[$name]));
	$trueValues = array('true', '1', 'on', 'yes');
	$falseValues = array('false', '0', 'off', 'no');

	if (in_array($value, $trueValues, true)) {
		return 'true';
	}
	if (in_array($value, $falseValues, true)) {
		return 'false';
	}

	LOGWARN("Speaker.php: Invalid boolean URL parameter '" . $name . "' value '" . s4lox_speaker_log_value($value) . "'. Use true or false.");
	return null;
}

function s4lox_speaker_get_mode_param($fallbackMode) {
	$mode = isset($_GET['mode']) ? $_GET['mode'] : $fallbackMode;
	$mode = strtolower(trim((string)$mode));

	if (!in_array($mode, array('on', 'off'), true)) {
		LOGWARN("Speaker.php: Invalid mode value '" . s4lox_speaker_log_value($mode) . "'. Use 'on' or 'off'.");
		return null;
	}

	return $mode;
}

function s4lox_speaker_delete_file($file, $label) {
	if (!is_string($file) || $file === '' || !file_exists($file)) {
		return true;
	}

	if (!is_file($file) && !is_link($file)) {
		LOGWARN("Speaker.php: " . $label . " was not deleted because it is not a regular file or symlink.");
		return false;
	}

	if (!unlink($file)) {
		LOGWARN("Speaker.php: Could not delete " . $label . ".");
		return false;
	}

	return true;
}

function s4lox_speaker_write_file($file, $content, $label) {
	if (file_put_contents($file, $content, LOCK_EX) === false) {
		LOGWARN("Speaker.php: Could not write " . $label . ".");
		return false;
	}

	return true;
}

function s4lox_speaker_profile_setting($profileDetails, $player, $name, $default) {
	if (isset($profileDetails[0]['Player'][$player][0][$name])) {
		return $profileDetails[0]['Player'][$player][0][$name];
	}

	return $default;
}

function s4lox_speaker_set_tv_dialog_mode($mode, $dialogKey, $label) {
	global $sonoszone, $master;

	$mode = s4lox_speaker_get_mode_param($mode);
	if ($mode === null) {
		return;
	}

	if (!s4lox_speaker_validate_zone($master)) {
		return;
	}

	try {
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$pos = $sonos->GetPositionInfo();
		$trackUri = is_array($pos) ? (string)($pos['TrackURI'] ?? '') : '';

		if (substr($trackUri, 0, 18) !== 'x-sonos-htastream:') {
			LOGWARN("Speaker.php: Player '" . s4lox_speaker_log_value($master) . "' is not in TV mode. " . $label . " was not changed.");
			return;
		}

		$sonos->SetDialogLevel($mode === 'on' ? '1' : '0', $dialogKey);
		LOGOK("Speaker.php: " . $label . " for player '" . s4lox_speaker_log_value($master) . "' has been turned " . $mode . ".");
	} catch (Exception $e) {
		LOGERR("Speaker.php: " . $label . " could not be changed for player '" . s4lox_speaker_log_value($master) . "': " . $e->getMessage());
	}
}



/* =================================================================================================
 * Player/model helper functions moved from Helper.php (HelperRelocation Speaker V01).
 * Function names are intentionally unchanged for legacy URL/code compatibility.
 * ================================================================================================= */

/**
* Funktion : 	allowLineIn --> filtert die gefunden Sonos Devices nach Zonen
* 				die den LineIn Eingang unterstützen
*
* @param: $model --> alle gefundenen Devices
* @return: $models --> Sonos Zonen
**/

 function allowLineIn($model) {
    $models = [
        "S5"    =>  "PLAY:5",
        "S6"    =>  "PLAY:5",
		"S23"   =>  "PORT",
        "ZP80"  =>  "CONNECT",
        "ZP90"  =>  "CONNECT",
		"S15"   =>  "CONNECT",
		"S16"   =>  "CONNECT:AMP",
        "ZP100" =>  "CONNECT:AMP",
        "ZP120" =>  "CONNECT:AMP",
        ];
    return s4lox_speaker_model_has($model, $models);
}

/**
* Funktion : 	OnlyCONNECT --> filtert die gefunden Sonos Devices nach Model CONNECT
*
* @param: $model --> alle gefundenen Devices
* @return: $models --> TRUE or FALSE
**/

function OnlyCONNECT($model) {
    $models = [
        "ZP80" => true,
        "ZP90" => true,
		"S15"  => true,
        ];
    return s4lox_speaker_model_has($model, $models);
}

/*******
* Funktion : 	isSoundbar --> filtert die Sonos Devices nach Zonen die Soundbars sind
*
* @param: 	$model --> alle gefundenen Soundbars
* @return: 	$soundb --> true

*******/

 function isSoundbar($model) {
    $soundb = [
				"S9"    =>  "PLAYBAR",
				"S11"   =>  "PLAYBASE",
				"S14"   =>  "BEAM",
				"S31"   =>  "BEAM",
				"S15"   =>  "CONNECT",
				"S19"   =>  "ARC",
				"S45"   =>  "ARC ULTRA",
				"S16"   =>  "AMP",
				"S36"   =>  "RAY",
			];
    return s4lox_speaker_model_has($model, $soundb);
}

/* @return: array of room names
**/

function CheckSubSur($val)    {

	global $sonos, $config;

	if ($val != "SW" and $val != "LR")   {
		LOGWARN("Speaker.php: Invalid CheckSubSur value '" . s4lox_speaker_log_value($val) . "'. Use SW or LR.");
		return "invalid entries";
	} elseif ($val == "SW")  {
		$key = "SUB";
	} elseif ($val == "LR")  {
		$key = "SUR";
	}
	$folfilePlOn = LBPDATADIR."/PlayerStatus/s4lox_on_";
	require_once __DIR__ . '/src/Support/Xml/XmlToArray.php';
	
	$int = array();
	if (empty($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
		LOGWARN("Speaker.php: Sonos zone configuration is not available for CheckSubSur.");
		return "false";
	}

	foreach($config['sonoszonen'] as $zonen => $ip) {
		if (is_file($folfilePlOn."".$zonen.".txt")) {
			array_push($int, $zonen);
		}
	}

	if (empty($int) || empty($config['sonoszonen'][$int[0]][0])) {
		LOGWARN("Speaker.php: No online Sonos zone is available for CheckSubSur.");
		return "false";
	}

	try {
		$sonos = new SonosAccess($config['sonoszonen'][$int[0]][0]);
		$xml = $sonos->GetZoneStates();
		$array = XmlToArray::convert($xml);
	} catch (Exception $e) {
		LOGWARN("Speaker.php: Could not read Sonos zone state for CheckSubSur: " . $e->getMessage());
		return "false";
	}

	$interim = isset($array['ZoneGroupState']['ZoneGroups']['ZoneGroup']) ? $array['ZoneGroupState']['ZoneGroups']['ZoneGroup'] : array();
	if (!is_array($interim)) {
		return "false";
	}
	if (isset($interim['ZoneGroupMember'])) {
		$interim = array($interim);
	}
	
	$subsur = array();
	foreach($interim as $groupKey => $value)     {
		$member = isset($value['ZoneGroupMember']) ? $value['ZoneGroupMember'] : array();
		if (isset($member['attributes'])) {
			$member = array($member);
		}
		if (!is_array($member)) {
			continue;
		}

		foreach ($member as $memberEntry) {
			$attributes = isset($memberEntry['attributes']) && is_array($memberEntry['attributes']) ? $memberEntry['attributes'] : array();
			if (empty($attributes['HTSatChanMapSet']) || empty($attributes['ZoneName'])) {
				continue;
			}
			$parts = explode(";", $attributes['HTSatChanMapSet']);
			foreach ($parts as $a)   {
				$a = substr($a, -2);
				if ($a == $val)    {
					$subsur[strtolower($attributes['ZoneName'])] = $groupKey;
				}
			}
		}
	}
	if (empty($subsur))    {
		$subsur = "false";
	}
	return $subsur;
}

/**
* Funktion : 	LineIn --> schaltet die angegebene Zone auf LineIn um (Cinch Eingang)
*
* @param: empty
* @return: empty
**/

function LineIn() {
	
	global $sonoszone, $master;
	/**
	$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
	$url = "http://" . $sonoszone[$master][0] . ":1400/xml/device_description.xml";
	$xml = simpleXML_load_file($url);
	$model = $xml->device->modelNumber;
	$model = allowLineIn($model);
	if ($model == true) {
		LOGOK("Speaker.php: Line-in has been selected successfully.");
		$sonos->SetAVTransportURI("x-rincon-stream:" . $sonoszone[$master][1]);
		$sonos->Play();	
	} else {
		
	}
	**/
	try {
		$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
		$sonos->SetAVTransportURI("x-rincon-stream:" . $sonoszone[$master][1]);
		$sonos->Play();	
		LOGOK("Speaker.php: Line-in has been selected successfully.");
	} catch (Exception $e) {
		LOGWARN("Speaker.php: The specified player '" . s4lox_speaker_log_value($master) . "' does not support Line-in to be selected.");
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
		# Legacy logging call removed.
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
		# Legacy logging call removed.
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
	#print_r($AutoPlayUUID);
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
		$sonos->SetAutoplayRoomUUID($rincon, $source = "");
		if (!empty($rincon))   {
			#LOGINF("Speaker.php: TV Autoplay mode for Player ".$key." has been configured.");
			#echo "TV Autoplay mode for Player ".$key." has been set activ".PHP_EOL;
		} else {
			LOGINF("Speaker.php: TV Autoplay mode for Player ".$key." has been set inactiv.");
			echo "TV Autoplay mode for Player ".$key." has been set inactiv".PHP_EOL;
		}
	} catch (Exception $e) {
		#startlog("TV Monitor", "tv_monitor");
		# Legacy logging call removed.
		echo "Player ".$key." could not be set to TV Autoplay mode".PHP_EOL;
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

function SetAutoplayLinkedZones($data, $soundbars, $key)  {
	
	global $soundbars, $sonoszone, $master, $key;
	
	if (isset($_GET['status']))   {
		$value = ($_GET['status']);
	} else {
		$value = $data;
	}
	#print_r("data: ".$data);
	try {
		$sonos = new SonosAccess($soundbars[$key][0]);
		$AutoPlayZones = $sonos->SetAutoplayLinkedZones($value);
		# Legacy logging call removed.
		echo "Include linked zones for Player ".$key." has been set to ".$value." in TV Autoplay mode.".PHP_EOL;
	} catch (Exception $e) {
		#startlog("TV Monitor", "tv_monitor");
		# Legacy logging call removed.
		echo "Include linked zones for Player ".$key." could not be set to TV Autoplay mode.".PHP_EOL;
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
	
	$value = s4lox_speaker_get_volume_param('volume', true);
	if ($value === null) {
		echo "For Player " . $master . " the volume is missing or invalid. Please add '&volume=<VALUE>' to your syntax";
		return;
	}

	if (!s4lox_speaker_validate_zone($master)) {
		return;
	}

	try {
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->SetAutoplayVolume($value);
		LOGOK("Speaker.php: Autoplay volume for player '" . s4lox_speaker_log_value($master) . "' has been set to " . $value . " in TV Autoplay mode.");
		echo "Autoplay Volume for Player '".$master."' has been set to ".$value." in TV Autoplay mode.";
	} catch (Exception $e) {
		LOGWARN("Speaker.php: Autoplay volume for player '" . s4lox_speaker_log_value($master) . "' could not be set: " . $e->getMessage());
		echo "Autoplay Volume for Player ".$master." could not be set!";
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
	
	$value = s4lox_speaker_get_bool_param('status', true, null);
	if ($value === null) {
		echo "For Player " . $master . " the status is missing or invalid. Please add '&status=true' or '&status=false' to your syntax";
		return;
	}

	if (!s4lox_speaker_validate_zone($master)) {
		return;
	}

	try {
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->SetUseAutoplayVolume($value);
		LOGOK("Speaker.php: Use Autoplay Volume for player '" . s4lox_speaker_log_value($master) . "' has been set to " . $value . " in TV Autoplay mode.");
		echo "Use Auto Play Volume for Player ".$master." has been set to ".$value." in TV Autoplay mode.";
	} catch (Exception $e) {
		LOGWARN("Speaker.php: Use Autoplay Volume for player '" . s4lox_speaker_log_value($master) . "' could not be set: " . $e->getMessage());
		echo "Use Auto Play Volume for Player ".$master." could not be set!";
	}
}




function AutoplayConfig()   {
	
	global $config, $sonoszone, $master;

	try {
		$sonos = new SonosAccess($sonoszone[$master][0]);

		/* =========================================================
		 * 1) Current Autoplay target room UUID
		 * ========================================================= */
		$currentRoomUuid = $sonos->GetAutoplayRoomUUID();
		LOGINF("Speaker.php: AutoplayRoomUUID current value: " . print_r($currentRoomUuid, true));

		/* =========================================================
		 * 2) Example: set Autoplay target room UUID
		 * IMPORTANT:
		 * Replace the UUID below with a REAL room UUID from your system.
		 * On a Beam this is usually not needed for your volume issue.
		 * ========================================================= */
		$targetRoomUuid = $sonoszone[$master][1];

		// Uncomment only if you really want to change it
		// $sonos->SetAutoplayRoomUUID($targetRoomUuid);
		// LOGINF("Speaker.php: AutoplayRoomUUID set to: " . $targetRoomUuid);

		/* =========================================================
		 * 3) Read current autoplay volumes
		 * ========================================================= */
		$tvAutoplayVolume    = $sonos->GetAutoplayVolume("TV");
		$musicAutoplayVolume = $sonos->GetAutoplayVolume("Music");

		LOGINF("Speaker.php: Current TV Autoplay Volume: " . print_r($tvAutoplayVolume, true));
		LOGINF("Speaker.php: Current Music Autoplay Volume: " . print_r($musicAutoplayVolume, true));

		/* =========================================================
		 * 4) Example: set autoplay volumes
		 * ========================================================= */
		$desiredTvVolume    = 12;
		$desiredMusicVolume = 18;

		$sonos->SetAutoplayVolume($desiredTvVolume, "TV");
		$sonos->SetAutoplayVolume($desiredMusicVolume, "Music");

		LOGINF("Speaker.php: TV Autoplay Volume set to: " . $desiredTvVolume);
		LOGINF("Speaker.php: Music Autoplay Volume set to: " . $desiredMusicVolume);

		/* =========================================================
		 * 5) Read back for verification
		 * ========================================================= */
		$tvAutoplayVolumeCheck    = $sonos->GetAutoplayVolume("TV");
		$musicAutoplayVolumeCheck = $sonos->GetAutoplayVolume("Music");

		LOGINF("Speaker.php: Verify TV Autoplay Volume: " . print_r($tvAutoplayVolumeCheck, true));
		LOGINF("Speaker.php: Verify Music Autoplay Volume: " . print_r($musicAutoplayVolumeCheck, true));

	} catch (Exception $e) {
		LOGERR("Speaker.php: Autoplay test failed: " . $e->getMessage());
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
	
	$value = s4lox_speaker_get_bool_param('status', true, null);
	if ($value === null) {
		echo "For Player " . $master . " the status is missing or invalid. Please add '&status=true' or '&status=false' to your syntax";
		return;
	}

	if (!s4lox_speaker_validate_zone($master)) {
		return;
	}

	try {
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->SetButtonLockState($value);
		LOGOK("Speaker.php: Button lock state for player '" . s4lox_speaker_log_value($master) . "' has been set to " . $value . ".");
		echo "Button lock state for Player ".$master." has been set to ".$value.".";
	} catch (Exception $e) {
		LOGWARN("Speaker.php: Button lock state for player '" . s4lox_speaker_log_value($master) . "' could not be set: " . $e->getMessage());
		echo "Button lock state for Player ".$master." could not be set!";
	}
}


/**
* Funktion : 	GetHtMode --> erfragt den HT Status (Streaming, TV on/off)
*
* @param: none
* @return: value
**/

function GetHtMode()  {
	
	global $sonoszone, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$posinfo = $sonos->GetPositionInfo();
	$media = $sonos->GetMediaInfo();
	$tvmodi = $sonos->GetZoneInfo();
	$zonestatus = getZoneStatus($master);
	
	if ($sonoszone[$master][13] == "SB")  {
		#echo $tvmodi['HTAudioIn'];
		if ($zonestatus === 'single')   {
			if (substr($posinfo["UpnpClass"], 0, 32) == "object.item.audioItem.musicTrack" 
				or substr($media["UpnpClass"], 0, 36) == "object.item.audioItem.audioBroadcast")  {
					echo "Value at HDMI/SPDIF for Soundbar in Music/Radio mode: ".$tvmodi['HTAudioIn'];
			} elseif (substr($posinfo["TrackURI"], 0, 18) === "x-sonos-htastream:")  {
				echo "Value at HDMI/SPDIF for Soundbar TV mode On: ".$tvmodi['HTAudioIn'];
			} else {
				echo "No Input detected, Queue may be empty!";
			}
		} elseif ($zonestatus === 'master')  {
			echo "Value at HDMI/SPDIF for Soundbar as Master of a Group: ".$tvmodi['HTAudioIn'];
		} elseif ($zonestatus === 'member')  {
			echo "Value at HDMI/SPDIF for Soundbar as Member of a Group: ".$tvmodi['HTAudioIn'];
		} else {
			echo "No Input detected, Queue may be empty!";
		}
	}
}


/**
/* Funktion : Getdialoglevel --> zeigt Informationen bzgl. DialogLevel der Zone an
/*
/* @param: 	empty
/* @return: 
**/	
		
function Getdialoglevel()  {
	
	global $sonos;
	
	$dialog = array();
	#echo '<PRE>';
	$NightMode = $sonos->GetDialogLevel('NightMode');
	$dialog['NightMode'] = $NightMode;
	$SurroundEnable = $sonos->GetDialogLevel('SurroundEnable');
	$dialog['SurroundEnable'] = $SurroundEnable;
	$DialogLevel = $sonos->GetDialogLevel('DialogLevel');
	$dialog['DialogLevel'] = $DialogLevel;
	$SubGain = $sonos->GetDialogLevel('SubGain');
	$dialog['SubGain'] = $SubGain;
	$SubEnable = $sonos->GetDialogLevel('SubEnable');
	$dialog['SubEnable'] = $SubEnable;
	$SurroundLevel = $sonos->GetDialogLevel('SurroundLevel');
	$dialog['SurroundLevel'] = $SurroundLevel;
	#print_r($dialog);
	return $dialog;
}

/**
* Funktion : 	SetSpeechMode() --> schaltet den Speech Mode aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetSpeechMode($mode)  {
	
	s4lox_speaker_set_tv_dialog_mode($mode, 'DialogLevel', 'Speech enhancement');
}


/**
* Funktion : 	SetNightMode() --> schaltet den Night Mode aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetNightMode($mode)  {
	
	s4lox_speaker_set_tv_dialog_mode($mode, 'NightMode', 'Night mode');
}


/**
* Funktion : 	SetSurroundMode() --> schaltet den Surround Mode aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetSurroundMode($mode)  {
	
	s4lox_speaker_set_tv_dialog_mode($mode, 'SurroundEnable', 'Surround');
}


/**
* Funktion : 	SetBassMode() --> schaltet den Bass Mode aktiv/inaktiv
*
* @param: none
* @return: none
**/

function SetBassMode($mode)  {
	
	s4lox_speaker_set_tv_dialog_mode($mode, 'SubEnable', 'Subwoofer');
}

/**
/* Function : identSB --> identify Soundbars
/*
/* @param:  Array(Player), file
/* @return: array
**/

function identSB($sonoszone, $file)    {
	
	# Extract predefined soundbars only (marked with SB)
	$soundbars = array();
	foreach($sonoszone as $zone => $ip) {
		#$existsb = array_key_exists('13', $ip);
		if ($sonoszone[$zone][13] == "SB")  {
			$soundbars[$zone] = $ip;
		}
	}

	# ... and then check for their Online Status
	$zonesonline = array();	
	foreach($soundbars as $zonen => $ip) {
		$handle = is_file($file."".$zonen.".txt");
		if($handle == true) {
			$zonesonline[$zonen] = $ip;
		} else {
			$message = "Speaker.php: Player '".$zonen."' seems to be offline, please check and run again.";
			if (function_exists('LOGWARN')) {
				LOGWARN($message);
			} elseif (isset($GLOBALS['log']) && is_object($GLOBALS['log']) && method_exists($GLOBALS['log'], 'LOGWARN')) {
				$GLOBALS['log']->LOGWARN($message);
			} else {
				error_log($message);
			}
		}
	}
	$soundbars = $zonesonline;
	return $soundbars;
}


/**
/* Funktion : curr_volume --> collect alle Volume Infos
/*
/* @param: empty                             
/* @return: On Screen Volume Infos
**/

function curr_volume()   {
	
	global $sonoszone, $master;

	foreach ($sonoszone as $key => $value)   {
		$group = getGroup($key);
		if (is_array($group))   {
			echo "-------------------------------------------------".PHP_EOL;
			foreach ($group as $key1)   {
				$sonos = new SonosAccess($sonoszone[$key1][0]);
				$volume = $sonos->GetVolume();
				echo "Volume for ".$key1." in Group = ".$volume.PHP_EOL;
			}
			$room = $group[0];
			$sonos = new SonosAccess($sonoszone[$room][0]);
			$groupvolume = $sonos->GetGroupVolume();
			$gr = implode(", ", $group);
			echo "Groupvolume for Group: ".$gr." = ".$groupvolume.PHP_EOL;
			echo "-------------------------------------------------".PHP_EOL;
		} else {
			$sonos = new SonosAccess($sonoszone[$key][0]);
			$volume = $sonos->GetVolume();
			echo "Volume for ".$key." = ".$volume.PHP_EOL;
		}
	}
	echo "<br>";
	echo "<br>";
}

/**
/* Funktion : SetGroupVolume --> setzt Volume für eine Gruppe
/*
/* @param: 	Volume
/* @return: 
**/	
function SetGroupVolume($groupvolume) {
	
	global $sonoszone, $master;
	
	VolumeOut();
	$sonos = new SonosAccess($sonoszone[$master][0]); 
	$sonos->SnapshotGroupVolume();
	$sonos->SetGroupVolume($groupvolume);
	VolumeOut();
 }

/**
/* Funktion : SetRelativeGroupVolume --> setzt relative Volume für eine Gruppe
/*
/* @param: 	Volume
/* @return: 
**/	
function SetRelativeGroupVolume($groupvolume) {
	
	global $sonoszone, $master;
	
	VolumeOut();
	$sonos = new SonosAccess($sonoszone[$master][0]); 
	$sonos->SnapshotGroupVolume();
	$sonos->SetRelativeGroupVolume($groupvolume);
	VolumeOut();
}

/**
/* Sub Funktion : SnapshotGroupVolume --> ermittelt das prozentuale Volume Verhältnis der einzelnen Zonen
/* einer Gruppe (nur vor SetGroupVolume oder SetRelativeGroupVolume nutzen)
/*
/* @return: Volume Verhältnis
**/	
function SnapshotGroupVolume() {
	
	global $sonos;
	
	$SnapshotGroupVolume = $sonos->SnapshotGroupVolume();
	return $SnapshotGroupVolume;
}

/**
/* Funktion : SetGroupMute --> setzt alle Zonen einer Gruppe auf Mute/Unmute
/* einer Gruppe
/*
/* @param: 	MUTE or UNMUTE
/* @return: 
**/	
 function SetGroupMute($mute) {
	 
	global $sonos;
	
	$sonos->SetGroupMute($mute);
 }




/**
/* Funktion : SetBalance --> setzt die Balance für angegeben Zone
/* einer Gruppe
/*
/* @param: 	balance=LF oder RF, wert 
/* @return: 
**/	

function SetBalance()  {
	
	global $sonos, $master, $sonoszone;
	
	if (isset($_GET['member']))  {
		LOGWARN("Speaker.php: Balance cannot be changed for groups. Please remove the member parameter.");
		exit;
	}
	if (!isset($_GET['balance']) || !isset($_GET['value'])) {
		LOGWARN("Speaker.php: Missing balance request parameter. Use balance=LF or balance=RF and value=0..100.");
		exit;
	}

	$value = s4lox_speaker_get_volume_param('value', true);
	if ($value === null) {
		exit;
	}

	$balance_dir = strtoupper(trim((string)$_GET['balance']));
	$valid_directions = array('LF' => 'left speaker', 'RF' => 'right speaker');
	if (!array_key_exists($balance_dir, $valid_directions)) {
		LOGWARN("Speaker.php: Invalid balance direction '" . s4lox_speaker_log_value($_GET['balance']) . "'. Use LF or RF.");
		exit;
	}

	try {
		if (!is_object($sonos)) {
			if (!s4lox_speaker_validate_zone($master)) {
				exit;
			}
			$sonos = new SonosAccess($sonoszone[$master][0]);
		}
		$sonos->SetBalance($balance_dir, $value);
		LOGOK("Speaker.php: Balance for " . $valid_directions[$balance_dir] . " of player '" . s4lox_speaker_log_value($master) . "' has been set to " . $value . ".");
	} catch (Exception $e) {
		LOGERR("Speaker.php: Balance could not be changed for player '" . s4lox_speaker_log_value($master) . "': " . $e->getMessage());
		exit;
	}
}


/**
/* Funktion : VolumeOut --> gibt die Werte an UI
/*
/* @param: 	&out in URL
/* @return: 
**/	

function VolumeOut() {
	
	if (isset($_GET['out']))   {
		curr_volume();
		return;
	}
}


/**
/* Funktion : VolumeProfiles --> ändert die Audioeinstellungen gemäß dem gewählten Profil
/*
/* @param: 	
/* @return: array of selected profile
/* @constant: Array
(
    [0] => nele
    [1] => kueche
    [2] => bad
) 
where [0] is always Master 
**/	

function VolumeProfiles() {
	
	global $sonos, $master, $volume, $profile, $memberarray, $profile_details, $sonoszone, $vol_config, $lbpconfigdir, $profile, $playerprof, $memberincl, $masterzone, $profile_selected;
	
	if(isset($_GET['profile']) and isset($_GET['member']))  {
		LOGWARN("Speaker.php: Parameters 'member' and 'profile' cannot be used together. Please correct your URL syntax.");
		exit();
	}
	
	if(isset($_GET['profile']) || (($_GET['action'] ?? '') == "Profile"))    {
		get_profile_details();			
		$checkprof = check_VolumeProfile();
		if ($checkprof == true)   {
			# member been selected
			#echo "Profile running";
			#echo "<br>";
			#file_put_contents($profile_selected, $_GET['profile']);
			LOGINF("Speaker.php: Selected Profile is still in use");
			foreach (SONOSZONE as $player => $value)   {
				if (is_enabled($profile_details[0]['Player'][$player][0]['Master']))    {
					$master = $player;
				}
			}
			define("PROFILAUDIO", "empty");
			define("MEMBER", "empty");
			define("GROUPMASTER", $master);
		} else {
			#echo "Profile New";
			#echo "<br>";
			# profile been selected
			s4lox_speaker_write_file($profile_selected, (string)($_GET['profile'] ?? ''), 'selected profile marker');
			s4lox_speaker_delete_file($memberarray, 'member array temp file');
			create_member_sound();
			VolumeProfilesSound($playerprof);
			define("PROFILAUDIO", $playerprof);
			define("MEMBER", $memberincl);
			define("GROUPMASTER", $masterzone);
			LOGINF("Speaker.php: Sound settings from profile '" . s4lox_speaker_log_value($_GET['profile'] ?? '') . "' have been set successfully.");
		}
		#print_r(PROFILAUDIO);
		#print_r(MEMBER);
		#print_r(GROUPMASTER);
		#print_r($lookup);
		#exit;
		AddMemberTo();
		return;
	}
	
	if(isset($_GET['member']))    {
		CreateMember();
	}
}

/**
/* Funktion : VolumeProfilesArrayURL --> array of all players from URL
/*
/* @param: array of player                        
/* @return: 
**/

function VolumeProfilesArrayURL()   {
	
	global $sonoszone, $masterzone, $profile, $profile_selected, $playerprof;

	# Prepare array of players for audio
	$master = $_GET['zone'];
	$playerprof[] = $master;
	if(isset($_GET['member'])) {
		$member = $_GET['member'];
		if($member === 'all') {
			$member = array();
			foreach ($sonoszone as $zone => $ip) {
				# exclude master Zone
				if ($zone != $master) {
					array_push($playerprof, $zone);
				}
			}
		} else {
			$member = explode(',', $member);
			$playerprof = array_merge($playerprof, $member);
		}
	}
	#print_r($playerprof);
	return $playerprof;
}



/**
/* Funktion : VolumeProfilesSound --> array of all players
/*
/* @param: array of player                        
/* @return: 
**/

function VolumeProfilesSound($playerprof) {

	global $sonoszone, $profile_details, $profile_selected, $profile, $config;

	foreach ($playerprof as $key) {
		try {
			if (empty($sonoszone[$key][0])) {
				LOGWARN("Speaker.php: Unknown or incomplete player '" . s4lox_speaker_log_value($key) . "' in volume profile. Player skipped.");
				continue;
			}

			$sonos = new SonosAccess($sonoszone[$key][0]);

			$volumeSetting = s4lox_speaker_profile_setting($profile_details, $key, 'Volume', '');
			if ($volumeSetting !== "" && is_numeric($volumeSetting)) {
				$volumeSetting = max(0, min(100, (int)$volumeSetting));
				$sonos->SetVolume($volumeSetting);
				LOGINF("Speaker.php: Volume for '".$key."' has been set to: ".$volumeSetting);
			} else {
				LOGWARN("Speaker.php: No valid volume entered in profile for '" . s4lox_speaker_log_value($key) . "'. Volume was not changed.");
			}

			$trebleSetting = s4lox_speaker_profile_setting($profile_details, $key, 'Treble', '');
			if ($trebleSetting !== "" && is_numeric($trebleSetting)) {
				$sonos->SetTreble((int)$trebleSetting);
				LOGDEB("Speaker.php: Treble for '".$key."' has been set to: ".$trebleSetting);
			} else {
				LOGWARN("Speaker.php: No valid treble entered in profile for '" . s4lox_speaker_log_value($key) . "'. Treble was not changed.");
			}

			$bassSetting = s4lox_speaker_profile_setting($profile_details, $key, 'Bass', '');
			if ($bassSetting !== "" && is_numeric($bassSetting)) {
				$sonos->SetBass((int)$bassSetting);
				LOGDEB("Speaker.php: Bass for '".$key."' has been set to: ".$bassSetting);
			} else {
				LOGWARN("Speaker.php: No valid bass entered in profile for '" . s4lox_speaker_log_value($key) . "'. Bass was not changed.");
			}

			$loudnessSetting = s4lox_speaker_profile_setting($profile_details, $key, 'Loudness', 'false');
			$ldstate = is_enabled($loudnessSetting) ? "1" : "0";
			$sonos->SetLoudness($ldstate);
			$ld = is_enabled($loudnessSetting) ? "On" : "Off";
			LOGDEB("Speaker.php: Loudness for '".$key."' has been switched ".$ld);

			$surroundSetting = s4lox_speaker_profile_setting($profile_details, $key, 'Surround', 'na');
			if ($surroundSetting != "na") {
				$sonos->SetDialogLevel(is_enabled($surroundSetting), 'SurroundEnable');
				$sur = is_enabled($surroundSetting) ? "On" : "Off";
				LOGDEB("Speaker.php: Surround for '".$key."' has been switched ".$sur);
			}

			$subwooferSetting = s4lox_speaker_profile_setting($profile_details, $key, 'Subwoofer', 'na');
			if ($subwooferSetting != "na") {

				$sub_enabled = (bool)is_enabled($subwooferSetting);

				$sonos->SetDialogLevel($sub_enabled, 'SubEnable');
				$sub = $sub_enabled ? "On" : "Off";
				LOGDEB("Speaker.php: Subwoofer for '".$key."' has been switched ".$sub);

				if ($sub_enabled) {
					$sub_level = s4lox_speaker_profile_setting($profile_details, $key, 'Subwoofer_level', 0);

					if ($sub_level === "" || !is_numeric($sub_level)) {
						$sub_level = 0;
					}

					$sonos->SetDialogLevel((int)$sub_level, 'SubGain');
					LOGDEB("Speaker.php: Subwoofer Bass for '".$key."' has been set to: ".$sub_level);
				} else {
					$sonos->SetDialogLevel(0, 'SubGain');
					LOGDEB("Speaker.php: Subwoofer Bass for '".$key."' has been reset to: 0");
				}
			}
		} catch (Exception $e) {
			LOGERR("Speaker.php: Player '".$key."' does not respond. Please check your settings: " . $e->getMessage());
			continue;
		}
	}
	return;
}


function check_VolumeProfile()   {
	
	global $profile_selected, $new_profile, $profile;
	
	$new_profile = false;
	if (file_exists($profile_selected))   {
		$saved_profile = file_get_contents($profile_selected);
		$entered_profile = (string)($_GET['profile'] ?? $profile ?? '');
		if ($saved_profile === false) {
			LOGWARN("Speaker.php: Could not read selected profile marker.");
			return false;
		}
		if ($saved_profile == $entered_profile)   {
			$new_profile = true;
		} else {
			$new_profile = false;
			s4lox_speaker_delete_file($profile_selected, 'selected profile marker');
		}	
	}
	return $new_profile;
}



function create_member_sound()   {
	
	global $profile_details, $playerprof, $masterzone, $memberarray, $memberincl;
	
	$sonoszone = SONOSZONE;
	
		#print_r($profile_details[0]);
		#echo "<br>";
		#print_r($lookup[0]['Group']);
		#echo "<br>";
		if ($profile_details[0]['Group'] == "Group")    {
			$playerprof = array();
			$memberincl = array();
			foreach ($sonoszone as $zone => $ip) {
				if ($profile_details[0]['Player'][$zone][0]['Master'] == "true")   {
					$playerprof[0] = $zone;
					# check wether master Zone is Master of existing group
					$sonos = new SonosAccess($sonoszone[$zone][0]);
					$state = getZoneStatus($zone);
					if ($state == "master")   {
						$sonos = new SonosAccess($sonoszone[$zone][0]);
						$sonos->BecomeCoordinatorOfStandaloneGroup();
						LOGINF("Speaker.php: Player '".$zone."' has been removed from existing Group");
					}
					$masterzone = $zone;
				} else {
					$sonos = new SonosAccess($sonoszone[$zone][0]);
					$sonos->BecomeCoordinatorOfStandaloneGroup();
				}
			}
			#print_r($masterzone);
			#$hasmaster = true;
			foreach ($sonoszone as $zone => $ip) {
				# add member to Master
				if ($profile_details[0]['Player'][$zone][0]['Member'] == "true")   {
					array_push($playerprof, $zone);
					array_push($memberincl, $zone);
				}
			}
			#print_r($memberincl);
			LOGOK("Speaker.php: Array of Speakers from Sound Profile '".$_GET['profile']."' has been created");
		} elseif ($profile_details[0]['Group'] == "Single")   {
			#echo "Single";
			$playerprof = array();
			$memberincl = array();
			foreach ($sonoszone as $zone => $ip) {
				if ($profile_details[0]['Player'][$zone][0]['Master'] == "true")   {
					$playerprof[0] = $zone;
					# check wether master Zone is Master of existing group
					$state = getZoneStatus($zone);
					if ($state == "master")   {
						$sonos = new SonosAccess($sonoszone[$zone][0]);
						$sonos->BecomeCoordinatorOfStandaloneGroup();
						LOGINF("Speaker.php: Player '".$zone."' has been removed from existing Group");
					}
					$masterzone = $zone;
				}
			}
			$memberincl[] = $masterzone;
		} elseif ($profile_details[0]['Group'] == "NoGroup")   {
			$playerprof = array();
			foreach ($sonoszone as $zone => $ip) {
				# add member to Master
				array_push($playerprof, $zone);
				#$masterzone = $zone;
			}
			#$masterzone = $_GET['zone'];
			$memberincl[] = $_GET['zone'];
			LOGOK("Speaker.php: Array of Speakers from Sound Profile '".$_GET['profile']."' has been created");
		} elseif ($profile_details[0]['Group'] == "Error")  {
			# Profile with Group selected, but no Master clicked, just one member
			LOGERR("Speaker.php: Grouping for Sound Profile '".$_GET['profile']."' failed due to missing Master! Please correct your Sound Profile Config.");
			exit(1);
		} else {
			# NoGroup, pick from URL
			$playerprof = VolumeProfilesArrayURL();
			LOGOK("Speaker.php: Sound Profile '".$_GET['profile']."' has been adopted by URL!");
		}
		#define("PROFILAUDIO", $playerprof);
		#define("PROFILMEMBER", $memberincl);
		#define("PROFILMASTER", $masterzone);
		#define("GROUPMASTER", $masterzone);
		#echo "Sound Array<br>";
		#print_r($playerprof);
		#echo "Member Array<br>";
		#print_r($memberincl);
		#echo "Masterzone: ".$masterzone;
		#echo "<br>";
		#exit;
		return;
	
	}


/**
/* Funktion : check_S1_player --> check if S1 device is master
/*
/* @param: none                        
/* @return: 
**/

function check_S1_player()   {
	
global $app, $master, $sonoszone, $config;
	
	# check master for S1
	$swgen = $sonoszone[$master][9];
	if ($swgen == "1")   {
		# check for usage of clip
		#if (isset($_GET['clip']))    {
			LOGERR("Speaker.php: Player '".$master."' has been identified as Gen 1 or you are using Sonos S1 App.");
			LOGERR("Speaker.php: Therefore this Player can't be used as master, only member of a Group is possible.");
			exit;
		#}
		#LOGERR("Speaker.php: Player '".$master."' has been identified as Generation S1 or you are using Sonos S1 App.");
		#LOGERR("Speaker.php: Both variants support only Shares using SMB1, but actually the Loxberry Samba Share is on SMB2.");
		#LOGERR("Speaker.php: You may replace/delete '".$master."' or update your App to Sonos S2! By updating only you can't use '".$master."' for Single TTS or Master of a group");
		#notify( LBPPLUGINDIR, "Sonos", "Player '".$room."' has been identified as Generation S1 and will only supported with certain restrictions.", "warning");
		exit(1);
	}
}



/**
/* Function : IdentPausedPlayers --> identify all players currently NOT playing (PAUSED/STOPPED)
/*
/* @param:  Array(sonoszone)
/* @return: Array
				(
					[bad] => Array
						(
							[0] => 192.168.50.114
							[1] => RINCON_542A1BB8523001400
							[2] => ROAM
							[3] => 32
							[4] => 30
							[5] => 100
							[6] => off
							[7] => S27
							[8] => NOSUB
							[9] => 2
							[10] => NOSUR
							[11] => 1
							[12] => 1
							[13] => NOSB
							[14] => false
						)
				)
**/

function IdentPausedPlayers()    {
	
	global $sonoszone, $sonos;
	
	$pausedplayer = array();
	foreach($sonoszone as $zone => $ip) {
		$sonos = new SonosAccess($ip[0]);
		$posinfo = $sonos->GetPositionInfo();
		if (substr($posinfo["TrackURI"], 0, 17) != "x-sonos-htastream")    {
			# check single or master zones
			if (getZoneStatus($zone) == "single" or getZoneStatus($zone) == "master")   {
				if ($sonos->GetTransportInfo() != "1")   {
					$pausedplayer[$zone] = $ip;
				}
			} elseif (getZoneStatus($zone) == "member")   {
				# get master of member zone to check play status
				$r = array_multi_search(substr($sonos->GetMediaInfo()['CurrentURI'], 9, 40), $sonoszone, $sKey = "");
				$sonos = new SonosAccess($r[0][0]);
				if ($sonos->GetTransportInfo() != "1")   {
					$pausedplayer[$zone] = $ip;
				}
			}
		} else {
			# check if Playbar is in TV mode and (not) running
			if ($sonos->GetZoneInfo()['HTAudioIn'] < 35000)   {
				$pausedplayer[$zone] = $ip;
			}
		}
	}
	LOGINF("Speaker.php: Currently not playing Players has been identified.");
	#print_r($pausedplayer);
	return $pausedplayer;
}
