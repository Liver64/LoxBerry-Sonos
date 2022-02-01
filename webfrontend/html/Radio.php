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
		LOGGING("radio.php: No radio stations found.", 4);
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
	LOGGING("radio.php: Radio Station '".$playlist."' has been loaded successful",6);
}

/**
* Function: nextradio --> iterate through Radio Favorites (endless)
*
* @param: empty
* @return: 
**/
function nextradio() {
	global $sonos, $config, $master, $debug, $min_vol, $volume, $tmp_tts, $sonoszone, $tmp_error, $stst;
	
	if (file_exists($tmp_tts))  {
		LOGGING("radio.php: Currently a T2S is running, we skip nextradio for now. Please try again later.",6);
		exit;
	}
	$textan = "0";
	if (file_exists($tmp_error)) {
		$err = json_decode(file_get_contents($tmp_error));
		foreach ($err as $key => $value) {
			LOGWARN("Sonos: radio.php: ".$value);
		}
		check_date_once();
		if ($stst == "true") {
			select_error_lang();
			$errortext = "Placeholder";
			say_radio_station($errortext);
			$textan = "1";
			LOGINF("Sonos: radio.php: Anouncement of broken Radio URL has been announced once.");
		}
		#exit;
	}
	#if (isset($_GET['member']))  {
	#	LOGGING("radio.php: Function could not be used within Groups!!", 6);
	#	exit;
	#}
	#try {
	#	$sonos->BecomeCoordinatorOfStandaloneGroup();
		#LOGGING("radio.php: Player ".$master." has been ungrouped!", 6);
	#} catch (Exception $e) {
		#LOGGING("radio.php: Player ".$master." is Single!", 7);
	#}
	#$coord = getRoomCoordinator($master);
	#$masterrincon = $coord[1]; 
	#$sonos = new PHPSonos($coord[0]);
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	$sonos->ClearQueue();
	$radioanzahl_check = count($config['RADIO']);
	if($radioanzahl_check == 0)  {
		LOGGING("radio.php: There are no Radio Stations maintained in the config. Pls update before using function NEXTRADIO or ZAPZONE!", 3);
		exit;
	}
	$playstatus = $sonos->GetTransportInfo();
	$radioname = $sonos->GetMediaInfo();
	print_r($radioname);
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
	if ($senderaktuell < ($radioanzahl) - 1 ) {
		$sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[$senderaktuell + 1], $radio_name[$senderaktuell + 1]);
		$act = $radio_name[$senderaktuell + 1];
	}
    if ($senderaktuell == $radioanzahl - 1) {
	    $sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[0], $radio_name[0]);
		$act = $radio_name[0];
	}
	if ($config['VARIOUS']['announceradio'] == 1 and $textan == "0") {
		$check_stat = getZoneStatus($master);
		say_radio_station();
	}
	$coord = getRoomCoordinator($master);
	$sonos = new PHPSonos($coord[0]);
	$sonos->SetMute(false);
	$sonos->SetVolume($volume);
	$sonos->Play();
	LOGGING("radio.php: Radio Station '".$act."' has been loaded successful by nextradio",6);
}


/**
* Funktion : 	random_radio --> lädt per Zufallsgenerator einen Radiosender und spielt ihn ab.
*
* @param: empty
* @return: Radio Sender
**/

function random_radio() {
	global $sonos, $sonoszone, $master, $volume, $min_vol, $config, $tmp_tts;
	
	if (file_exists($tmp_tts))  {
		LOGGING("radio.php: Currently a T2S is running, we skip nextradio for now. Please try again later.",6);
		exit;
	}
	#if (isset($_GET['member']))  {
	#	LOGGING("radio.php: Function could not be used within Groups!!", 6);
	#	exit;
	#}
	#try {
	#	$sonos->BecomeCoordinatorOfStandaloneGroup();
		#LOGGING("radio.php: Player ".$master." has been ungrouped!", 6);
	#} catch (Exception $e) {
		#LOGGING("radio.php: Player ".$master." is Single!", 7);
	#}
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
	LOGGING("radio.php: Radio Station '".$sonoslists[$random]["title"]."' has been loaded successful by randomradio",6);
}



/**
* Function : say_radio_station --> announce radio station before playing Station
*
* @param: 
* @return: 
**/

function say_radio_station($errortext ='') {
			
	global $master, $sonoszone, $config, $min_vol, $volume, $actual, $sonos, $coord, $messageid, $filename, $MessageStorepath, $nextZoneKey, $member, $errortext, $errorvoice, $errorlang;
	require_once("addon/sonos-to-speech.php");
	
	// if batch has been choosed abort
	if(isset($_GET['batch'])) {
		LOGGING("radio.php: The parameter batch could not be used to announce the radio station!", 4);
		exit;
	}
	$sonos->Stop();
	saveZonesStatus(); // saves all Zones Status
	$coord = getRoomCoordinator($master);
	LOGGING("radio.php: Room Coordinator been identified", 7);		
	$sonos = new PHPSonos($coord[0]); 
	$temp_radio = $sonos->GetMediaInfo();
	#********************** NEW get text variables **********************
	$TL = LOAD_T2S_TEXT();
	if ($TL != "") {
		$play_stat = $TL['SONOS-TO-SPEECH']['ANNOUNCE_RADIO'] ; 
	} else {
		$play_stat = 'Placeholder';
	}
	#$play_stat = $TL['SONOS-TO-SPEECH']['ANNOUNCE_RADIO'] ; 
	#********************************************************************
	# Generiert und kodiert Ansage des laufenden Senders
	if (strncmp($temp_radio['title'], $play_stat, strlen($play_stat))===0 or empty($indtext)) {
    	# Nur Titel des Senders ansagen, falls Titel mit dem Announce-Radio Text übereinstimmt
	    $text = $temp_radio['title'];
	} else {
	    # Ansage von 'Radio' gefolgt vom Titel des Senders
	    $text = ($play_stat.' '.$temp_radio['title']);
	}
	if ($errortext != '')  {
		$text = $errortext;
		$textstring = ($text);
		$rawtext = md5($textstring);
		$filename = "$rawtext";
		include_once("voice_engines/GoogleCloud.php");
	} else {
		$textstring = ($text);
		$rawtext = md5($textstring);
		$filename = "$rawtext";
		select_t2s_engine();
		t2s($textstring, $filename);
	}
	t2s($textstring, $filename);
	$sonos->SetMute(false);
	$tmp_volume = $sonos->GetVolume();
	$volume = $volume + $config['TTS']['correction'];
	LOGGING("radio.php: Radio Station Announcement has been announced", 6);		
	play_tts($filename);
	if(isset($_GET['member'])) {
	    // TODO should this be loaded by a helper function? or already be loaded before calling say_radio_station() 
	    // Or should say_radio_station() use sendgroupmessage() to play T2S if zones are grouped?
	    $member = $_GET['member'];
	    $member = explode(',', $member);
	    restoreGroupZone();
	} else {
	    restoreSingleZone();
	}
	if(isset($_GET['volume'])) {
		$volume = $_GET['volume'];
	} elseif (isset($_GET['keepvolume'])) {
		if ($tmp_volume >= $min_vol)  {
			$volume = $tmp_volume;
		} else {
			$volume = $config['sonoszonen'][$master][4];
		}
	} else {
		$volume = $config['sonoszonen'][$master][4];
	}
	return $volume;
}


/**
* Funktion : 	select_error_lang --> wählt die Sprache der error message aus.
*
* @param: empty
* @return: translations form error.json file
**/

function select_error_lang() {
	
	global $config, $pathlanguagefile, $errortext, $errorvoice, $errorlang;
	
	$file = "error.json";
	$url = $pathlanguagefile."".$file;
	$valid_languages = File_Get_Array_From_JSON($url, $zip=false);
	print_r($valid_languages);
	$language = $config['TTS']['messageLang'];
	$language = substr($language, 0, 5);
	#echo $language;
	$isvalid = array_multi_search($language, $valid_languages, $sKey = "language");
	if (!empty($isvalid)) {
		$errortext = $isvalid[0]['value']; // Text
		$errorvoice = $isvalid[0]['voice']; // de-DE-Standard-A
		$errorlang = $isvalid[0]['language']; // de-DE
	} else {
		# if no translation for error exit use English
		$errortext = 'the function nextradio is not working, please check Sonos Plugin error log.';
		$errorvoice = 'en-US-Wavenet-A';
		$errorlang = 'en-US';
		LOGGING("radio.php: Translation for your Standard language is not available, EN has been selected", 6);	
	}
	#print_r($valid_languages);
	

}

/**
* Funktion : 	check_date_once --> check for execution once a day (cronjob daily deletes file)
*
* @param: empty
* @return: true or false
**/

function check_date_once() {
	
	global $check_date, $stst, $tmp_error;
	
	if (file_exists($check_date) and file_exists($tmp_error)) {
		$stst = "false";
		return $stst;
	} else {
		$now = date("d.m.Y");
		file_put_contents($check_date, $now);
		$stst = "true";
		return $stst;
	};
}
	



?>