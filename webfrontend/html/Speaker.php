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
	/**
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
		
	}
	**/
	try {
		$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
		$sonos->SetAVTransportURI("x-rincon-stream:" . $sonoszone[$master][1]);
		$sonos->Play();	
		LOGGING("speaker.php: Line in has been selected successful",6);
	} catch (Exception $e) {
		LOGGING("speaker.php: The specified Player '".$master."' does not support Line-in to be selected!", 3);
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
		$sonos->SetAutoplayRoomUUID($rincon);
		if (!empty($rincon))   {
			LOGGING("/bin/speaker.php: TV Autoplay mode for Player ".$key." has been set activ.", 7);
			echo "TV Autoplay mode for Player ".$key." has been set activ".PHP_EOL;
		} else {
			LOGGING("/bin/speaker.php: TV Autoplay mode for Player ".$key." has been set inactiv.", 7);
			echo "TV Autoplay mode for Player ".$key." has been set inactiv".PHP_EOL;
		}
	} catch (Exception $e) {
		startlog("TV Monitor", "tv_monitor");
		LOGGING("/bin/speaker.php: Player ".$key." could not be set to TV Autoplay mode.", 4);
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
	try {
		$sonos = new SonosAccess($soundbars[$key][0]);
		$AutoPlayZones = $sonos->SetAutoplayLinkedZones($value);
		LOGGING("/bin/speaker.php: Include linked zones for Player ".$master." has been set to ".$value." in TV Autoplay mode.", 7);
		echo "Include linked zones for Player ".$key." has been set to ".$value." in TV Autoplay mode.";
	} catch (Exception $e) {
		startlog("TV Monitor", "tv_monitor");
		LOGGING("/bin/speaker.php: Include linked zones for Player ".$master." could not be set to TV Autoplay mode.", 3);
		echo "Include linked zones for Player ".$key." could not be set to TV Autoplay mode.";
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
			$sonos->SetDialogLevel('1', 'DialogLevel');
			LOGGING("/bin/speaker.php: Speech enhancement for Player ".$master." has been turned on.", 7);
		} else {
			$sonos->SetDialogLevel('0', 'DialogLevel');
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
			LOGGING("/bin/tv_monitor_conf.php: Player '".$zonen."' seems to be Offline, please check and run again.", 4);
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
	
	global $sonos, $master;
	
	if (isset($_GET['member']))  {
		LOGGING('sonos.php: For groups the function could not be used, please correct!', 3);
		exit;
	}
	if ((isset($_GET['balance'])) && (isset($_GET['value']))) {
		if(is_numeric($_GET['value']) && $_GET['value'] >= 0 && $_GET['value'] <= 100) {
			$balance_dir = $_GET['balance'];
			$valid_directions = array('LF' => 'left speaker','RF' => 'right speaker', 'lf' => 'left speaker', 'rf' => 'right speaker');
			if (array_key_exists($balance_dir, $valid_directions)) {
				$sonos->SetBalance($balance_dir, $_GET['value']);
				LOGGING('sonos.php: Balance for '.$valid_directions[$balance_dir].' of Player '.$master.' has been set to '.$_GET['value'].'.', 5);
			} else {
				LOGGING('sonos.php: Entered balance direction for Player '.$master.' is not valid. Only "LF/lf" or "RF/rf" are allowed, please correct!', 3);
				exit;
			}
		} else {
			LOGGING('sonos.php: Entered balance '.$_GET['value'].' for Player '.$master.' is even not numeric or not between 1 and 100, please correct!', 3);
			exit;
		}
	} else {
		LOGGING('sonos.php: No valid entry for Balance has been entered or syntax is incomplete, please correct!', 3);
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
		LOGGING("speaker.php: Parameter 'member' and 'profile' could not be used in conjunction! Please correct your syntax/URL", 3);
		exit();
	}
	
	if(isset($_GET['profile']) or $_GET['action'] == "Profile")    {
		get_profile_details();			
		$checkprof = check_VolumeProfile();
		if ($checkprof == true)   {
			# member been selected
			#echo "Profile running";
			#echo "<br>";
			#file_put_contents($profile_selected, $_GET['profile']);
			LOGINF("speaker.php: Selected Profile is still in use");
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
			file_put_contents($profile_selected, $_GET['profile']);
			@unlink($memberarray);
			create_member_sound();
			VolumeProfilesSound($playerprof);
			define("PROFILAUDIO", $playerprof);
			define("MEMBER", $memberincl);
			define("GROUPMASTER", $masterzone);
			LOGINF("speaker.php: Sound Settings from Profile '".$_GET['profile']."' has been set sucessfull.");
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

function VolumeProfilesSound($playerprof)   {
	
global $sonoszone, $profile_details, $profile_selected, $profile, $config;

foreach ($playerprof as $key)  {
	try {
		@$sonos = new SonosAccess($sonoszone[$key][0]);
		#@$sonos->SetMute(true)
		# Set Volume	
		if ($profile_details[0]['Player'][$key][0]['Volume'] != "")	{
			$sonos->SetVolume($profile_details[0]['Player'][$key][0]['Volume']);
			$volume = $profile_details[0]['Player'][$key][0]['Volume'];
			LOGINF("speaker.php: Volume for '".$key."' has been set to: ".$profile_details[0]['Player'][$key][0]['Volume']);
		} else {
			LOGWARN("speaker.php: No Volume entered in Profile, so we could not set Volume");
		}
		# Set Treble
		if ($profile_details[0]['Player'][$key][0]['Treble'] != "")	{
			$sonos->SetTreble($profile_details[0]['Player'][$key][0]['Treble']);
			LOGDEB("speaker.php: Treble for '".$key."' has been set to: ".$profile_details[0]['Player'][$key][0]['Treble']);
		} else {
			LOGWARN("speaker.php: No Treble entered in Profile, so we could not set Treble");
		}
		# Set Bass
		if ($profile_details[0]['Player'][$key][0]['Bass'] != "")	{
			$sonos->SetBass($profile_details[0]['Player'][$key][0]['Bass']);
			LOGDEB("speaker.php: Bass for '".$key."' has been set to: ".$profile_details[0]['Player'][$key][0]['Bass']);
			} else {
			LOGWARN("speaker.php: No Bass entered in Profile, so we could not set Bass");
		}
		# Set Loudness
		if ((bool)is_enabled($profile_details[0]['Player'][$key][0]['Loudness'] === "true" ? $ldstate = "1" : $ldstate = "0"));
		$sonos->SetLoudness($ldstate);
		if ((bool)is_enabled($profile_details[0]['Player'][$key][0]['Loudness']) === true ? $ld = "On" : $ld = "Off");
		LOGDEB("speaker.php: Loudness for '".$key."' has been switched ".$ld);
		# Set Surround
		if ($profile_details[0]['Player'][$key][0]['Surround'] != "na")   {
			$sonos->SetDialogLevel(is_enabled($profile_details[0]['Player'][$key][0]['Surround']), 'SurroundEnable');
			if ((bool)is_enabled($profile_details[0]['Player'][$key][0]['Surround']) === true ? $sur = "On" : $sur = "Off");
			LOGDEB("speaker.php: Surround for '".$key."' has been switched ".$sur);
		}
		# Set Subwoofer and Subwoofer Bass Level
		if ($profile_details[0]['Player'][$key][0]['Subwoofer'] != "na")   {
			$sonos->SetDialogLevel(is_enabled($profile_details[0]['Player'][$key][0]['Subwoofer']), 'SubEnable');
			if ((bool)is_enabled($profile_details[0]['Player'][$key][0]['Subwoofer']) === true ? $sub = "On" : $sub = "Off");
				LOGDEB("speaker.php: Subwoofer for '".$key."' has been switched ".$sub);
				$sonos->SetDialogLevel($profile_details[0]['Player'][$key][0]['Subwoofer_level'], 'SubGain');
				LOGDEB("speaker.php: Subwoofer Bass for '".$key."' has been set to: ".$profile_details[0]['Player'][$key][0]['Subwoofer_level']);
			}
		} catch (Exception $e) {
			LOGERR("speaker.php: Player '".$key."' does not respond. Please check your settings");
			continue;
		}
	}
return;
}


function check_VolumeProfile()   {
	
	global $profile_selected, $new_profile, $profile;
	
	If (file_exists($profile_selected))   {
		$saved_profile = file_get_contents($profile_selected);
		$entered_profile = $_GET['profile'];
		if ($saved_profile == $entered_profile)   {
			$new_profile = true;
		} else {
			$new_profile = false;
			@unlink($profile_selected);
		}	
	}
	#var_dump($new_profile);
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
						LOGINF("speaker.php: Player '".$zone."' has been removed from existing Group");
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
			LOGOK("speaker.php: Array of Speakers from Sound Profile '".$_GET['profile']."' has been created");
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
						LOGINF("speaker.php: Player '".$zone."' has been removed from existing Group");
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
			LOGOK("speaker.php: Array of Speakers from Sound Profile '".$_GET['profile']."' has been created");
		} elseif ($profile_details[0]['Group'] == "Error")  {
			# Profile with Group selected, but no Master clicked, just one member
			LOGERR("speaker.php: Grouping for Sound Profile '".$_GET['profile']."' failed due to missing Master! Please correct your Sound Profile Config.");
			exit(1);
		} else {
			# NoGroup, pick from URL
			$playerprof = VolumeProfilesArrayURL();
			LOGOK("speaker.php: Sound Profile '".$_GET['profile']."' has been adopted by URL!");
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
			LOGERR("speaker.php: Player '".$master."' has been identified as Gen 1 or you are using Sonos S1 App.");
			LOGERR("speaker.php: Therefore this Player can't be used as master, only member of a Group is possible.");
			exit;
		#}
		#LOGERR("speaker.php: Player '".$master."' has been identified as Generation S1 or you are using Sonos S1 App.");
		#LOGERR("speaker.php: Both variants support only Shares using SMB1, but actually the Loxberry Samba Share is on SMB2.");
		#LOGERR("speaker.php: You may replace/delete '".$master."' or update your App to Sonos S2! By updating only you can't use '".$master."' for Single TTS or Master of a group");
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
	LOGINF("speaker.php: Currently not playing Players has been identified.");
	#print_r($pausedplayer);
	return $pausedplayer;
}
?>