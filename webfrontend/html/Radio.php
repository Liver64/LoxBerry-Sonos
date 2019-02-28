<?php

/**
* Submodul: Radio
*
**/

/**
/* Funktion : radio --> lädt einen Radiosender in eine Zone/Gruppe
/*
/* @param: Sender                             
/* @return: nichts
**/

function radio(){
	Global $sonos, $volume, $config, $sonoszone, $master;
			
	if(isset($_GET['radio'])) {
        $playlist = $_GET['radio'];		
	} elseif (isset($_GET['playlist'])) {
		$playlist = $_GET['playlist'];		
	} else {
		LOGGING("No radio stations found.", 4);
    }
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	$coord = $master;
	$roomcord = getRoomCoordinator($coord);
	$sonosroom = new PHPSonos($roomcord[0]); //Sonos IP Adresse
	$sonosroom->SetQueue("x-rincon-queue:".$roomcord[1]."#0");
	$sonosroom->SetMute(false);
	$sonosroom->Stop();
    # Sonos Radio Playlist ermitteln und mit übergebene vergleichen   
    $radiolists = $sonos->Browse("R:0/0","c");
	$radioplaylist = urldecode($playlist);
	$rleinzeln = 0;
    while ($rleinzeln < count($radiolists)) {
	if ($radioplaylist == $radiolists[$rleinzeln]["title"]) {
		$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]),$radiolists[$rleinzeln]["title"]);
		#$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]));
		if(!isset($_GET['load'])) {
			$sonos->SetVolume($volume);
			$sonos->Play();
		}
    }
	$rleinzeln++;
	}   
	LOGGING("Radio Station '".$playlist."' has been loaded successful",6);
}

/**
* Function: nextradio --> iterate through Radio Favorites (endless)
*
* @param: empty
* @return: 
**/
function nextradio() {
	global $sonos, $config, $master, $debug, $volume, $tmp_tts, $sonoszone;
	
	if (file_exists($tmp_tts))  {
		LOGGING("Currently a T2S is running, we skip nextradio for now. Please try again later.",4);
		exit;
	}
	
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	$radioanzahl_check = count($config['RADIO']);
	if($radioanzahl_check == 0)  {
		LOGGING("There are no Radio Stations maintained in the config. Pls update before using function NEXTRADIO or ZAPZONE!", 3);
		exit;
	}
	$playstatus = $sonos->GetTransportInfo();
	$radioname = $sonos->GetMediaInfo();
	if (!empty($radioname["title"])) {
		$senderuri = $radioname["title"];
	} else {
		$senderuri = "";
	}
	$radio = $config['RADIO']['radio'];
	ksort($radio);
	$radioanzahl = count($config['RADIO']['radio']);
	$radio_name = array();
	$radio_adresse = array();
	foreach ($radio as $key) {
		$radiosplit = explode(',',$key);
		array_push($radio_name, $radiosplit[0]);
		array_push($radio_adresse, $radiosplit[1]);
	}
	$senderaktuell = array_search($senderuri, $radio_name);
	# Wenn nextradio aufgerufen wird ohne eine vorherigen Radiosender
	if( $senderaktuell == "" && $senderuri == "" || substr($senderuri, 0, 12) == "x-file-cifs:" ) {
		$senderaktuell = -1;
	}
	if ($senderaktuell == ($radioanzahl) ) {
		$sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[0], $radio_name[0]);
	}
    if ($senderaktuell < ($radioanzahl) ) {
		@$sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[$senderaktuell + 1], $radio_name[$senderaktuell + 1]);
	}
    if ($senderaktuell == $radioanzahl - 1) {
	    $sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[0], $radio_name[0]);
	}
	$info_r = "\r\n Senderuri vorher: " . $senderuri . "\r\n";
	$info_r .= "Sender aktuell: " . $senderaktuell . "\r\n";
	$info_r .= "Radioanzahl: " .$radioanzahl;
	LOGGING('Next Radio Info: '.($info_r),7);
    if ($config['VARIOUS']['announceradio'] == 1) {
		say_radio_station();
	}
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$sonos->SetVolume($volume);
	$sonos->Play();
	LOGGING("Radio Station '".$radioname['title']."' has been loaded successful by nextradio",6);
}


/**
* Funktion : 	random_radio --> lädt per Zufallsgenerator einen Radiosender und spielt ihn ab.
*
* @param: empty
* @return: Radio Sender
**/

function random_radio() {
	global $sonos, $sonoszone, $master, $volume, $config;
	
	if (isset($_GET['member'])) {
		LOGGING("This function could not be used with groups!", 3);
		exit;
	}
	$sonoslists = $sonos->Browse("R:0/0","c");
	print_r($sonoslists);
	if(!isset($_GET['except'])) {
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	} else {
		$except = $_GET['except'];
		$exception = explode(',',$except);
		for($i = 0; $i < count($exception); $i++) {
			$exception[$i] = str_replace(' ', '', $exception[$i]);
		}
		foreach ($exception as $key => $val) {
			unset($sonoslists[$val]);
		}
		$sonoslists = array_values($sonoslists);
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	}
	$sonos->ClearQueue();
	$sonos->SetMute(false);
	$sonos->SetRadio(urldecode($sonoslists[$random]["res"]),$sonoslists[$random]["title"]);
	$sonos->SetVolume($volume);
	$sonos->Play();
	LOGGING("Radio Station '".$sonoslists[$random]["title"]."' has been loaded successful by randomradio",6);
}



/**
* Function : say_radio_station --> announce radio station before playing Station
*
* @param: 
* @return: 
**/

function say_radio_station() {
			
	# nach nextradio();
	global $master, $sonoszone, $config, $volume, $actual, $sonos, $coord, $messageid, $filename, $MessageStorepath, $nextZoneKey;
	require_once("addon/sonos-to-speech.php");
	
	// if batch has been choosed abort
	if(isset($_GET['batch'])) {
		LOGGING("The parameter batch could not be used to announce the radio station!", 4);
		exit;
	}
	$sonos->Stop();
	saveZonesStatus(); // saves all Zones Status
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$temp_radio = $sonos->GetMediaInfo();
	#********************** NEW get text variables **********************
	$TL = LOAD_T2S_TEXT();
	$play_stat = $TL['SONOS-TO-SPEECH']['ANNOUNCE_RADIO'] ; 
	#********************************************************************
	# Generiert und kodiert Ansage des laufenden Senders
	$text = ($play_stat.' '.$temp_radio['title']);
	$textstring = ($text);
	$rawtext = md5($textstring);
	$filename = "$rawtext";
	select_t2s_engine();
	t2s($textstring, $filename);
	// get Coordinator of (maybe) pair or single player
	$coord = getRoomCoordinator($master);
	LOGGING("Room Coordinator been identified", 7);		
	$sonos = new PHPSonos($coord[0]); 
	$sonos->SetMute(false);
	$volume = $volume + $config['TTS']['correction'];
	LOGGING("Radio Station Announcement has been played", 6);		
	play_tts($filename);
	restoreSingleZone();
	if(isset($_GET['volume'])) {
		$volume = $_GET['volume'];
	} else {
		$volume = $config['sonoszonen'][$master][4];
	}
	return $volume;
}


?>