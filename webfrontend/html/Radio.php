<?php

/**
* Submodul: Radio
*
**/

/**
/* Funktion : radio --> lädt einen Radiosender aus den TuneIn "Meine Radiosender" in eine Zone/Gruppe
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
	$orgpl = $playlist;
	# initial load of favorite
	if(isset($_GET['playlist']) or isset($_GET['radio']))   {
		$playlist = mb_strtolower($playlist);	
	} else {
		LOGERR("radio.php: You have maybe a typo! Correct syntax is: &action=radioplaylist&playlist=<PLAYLIST> or <RADIO>");
		exit;
	}
	$check_stat = getZoneStatus($master);
	if ($check_stat != (string)"single")  {
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
	}
	$sonos = new SonosAccess($config['sonoszonen'][$master][0]);
	$coord = $master;
	$roomcord = getRoomCoordinator($coord);
	$sonosroom = new SonosAccess($roomcord[0]); //Sonos IP Adresse
	$sonosroom->SetQueue("x-rincon-queue:".$roomcord[1]."#0");
    $radiolists = $sonos->BrowseContentDirectory("R:0/0","BrowseDirectChildren");
	print_r($radiolists);
	foreach ($radiolists as $val => $item)  {
		$radiolists[$val]['titlelow'] = mb_strtolower($radiolists[$val]['title']);
	}
	$found = array();
	foreach ($radiolists as $key)    {
		if ($playlist === $key['titlelow'])   {
			$playlist = $key['titlelow'];
			array_push($found, array_multi_search($playlist, $radiolists, "titlelow"));
		}
	}
	$playlist = urldecode($playlist);
	if (count($found) > 1)  {
		LOGERR ("radio.php: Your entered Radio Station '".$playlist."' has more then 1 hit! Please specify more detailed.");
		exit;
	} elseif (count($found) == 0)  {
		LOGERR ("radio.php: Your entered Radio Station '".$orgpl."' could not be found.");
		exit;
	} else {
		LOGGING("radio.php: Radio Station '".$found[0][0]["title"]."' has been found.", 5);
	}
	$countradio = count($found);
	if ($countradio > 0)   {
		$sonos->SetRadio(urldecode($found[0][0]["res"]),$found[0][0]["title"]);
		if(!isset($_GET['load'])) {
			$sonosroom->SetMute(false);
			$sonosroom->Stop();
			$sonosroom->SetVolume($volume);
			$sonosroom->Play();
		}
		LOGGING("radio.php: Radio Station '".$found[0][0]["title"]."' has been loaded successful",6);
	} else {
		LOGGING("radio.php: Radio Station '".$found[0][0]["title"]."' could not be loaded. Please check your input.",3);
		#if(isset($_GET['member'])) {
		#	removemember();
		#	LOGINF ("radio.php: Member has been removed");
		#}
		exit;
	}
	if(isset($_GET['member']))   {
		AddMemberTo();
		LOGGING("radio.php: Group Radio has been called.", 7);
	}
}



/**
* Function: nextradio --> iterate through Radio Favorites (endless)
*
* @param: empty
* @return: 
**/
function nextradio() {
	global $sonos, $config, $lookup, $master, $debug, $min_vol, $volume, $tmp_tts, $sonoszone, $tmp_error, $stst;
	
	$radioanzahl_check = count($config['RADIO']);
	if($radioanzahl_check == 0)  {
		LOGGING("radio.php: There are no Radio Stations maintained in the config. Pls update before using function NEXTRADIO or ZAPZONE!", 3);
		exit;
	}
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
			LOGINF("Sonos: radio.php: Info of broken Radio URL has been announced once.");
		}
		#exit;
	}
	$sonos = new SonosAccess($config['sonoszonen'][$master][0]);
	$sonos->ClearQueue();
		$playstatus = $sonos->GetTransportInfo();
	$radioname = $sonos->GetMediaInfo();
	#print_r($radioname);
	if (!empty($radioname["title"])) {
		$senderuri = $radioname["title"];
	} else {
		$senderuri = "";
	}
	$radio = $config['RADIO']['radio'];
	ksort($radio);
	#print_r($radio);
	$radioanzahl = count($config['RADIO']['radio']);
	$radio_name = array();
	$radio_adresse = array();
	$radio_coverurl = array();
	foreach ($radio as $key) {
		$radiosplit = explode(',',$key);
		array_push($radio_name, $radiosplit[0]);
		array_push($radio_adresse, $radiosplit[1]);
		if (array_key_exists("2", $radiosplit)) {
			array_push($radio_coverurl, $radiosplit[2]);
		} else {
			array_push($radio_coverurl, "");
		}
	}
	#print_r($radio_coverurl);
	$senderaktuell = array_search($senderuri, $radio_name);
	if ($senderaktuell < ($radioanzahl) - 1 ) {
		$sonos->SetRadio('x-rincon-mp3radio://'.trim($radio_adresse[$senderaktuell + 1]), trim($radio_name[$senderaktuell + 1]), trim($radio_coverurl[$senderaktuell + 1]));
		$act = $radio_name[$senderaktuell + 1];
	}
    if ($senderaktuell == $radioanzahl - 1) {
	    $sonos->SetRadio('x-rincon-mp3radio://'.trim($radio_adresse[0]), trim($radio_name[0]), trim($radio_coverurl[0]));
		$act = $radio_name[0];
	}
	if ($config['VARIOUS']['announceradio'] == 1 and $textan == "0") {
		#$check_stat = getZoneStatus($master);
		say_radio_station();
	}
	$coord = getRoomCoordinator($master);
	$sonos = new SonosAccess($coord[0]);
	$sonos->SetMute(false);
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	}
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
	global $sonos, $lookup, $sonoszone, $master, $volume, $min_vol, $config, $tmp_tts;
	
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
	$sonoslists = $sonos->BrowseContentDirectory("R:0/0","BrowseDirectChildren");
	#print_r($sonoslists);
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
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	}
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
	$sonos = new SonosAccess($coord[0]); 
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
	#print_r($valid_languages);
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


/**
/* Funktion : PluginRadio --> lädt einen Radiosender aus den Plugin Radio Favoriten in eine Zone/Gruppe
/*
/* @param: Sender                             
/* @return: nichts
**/

function PluginRadio()   
{
	global $sonos, $sonoszone, $lookup, $master, $config, $volume;
	
	if (isset($_GET['radio'])) {
		if (empty($_GET['radio']))    {
			LOGGING("radio.php: No radio station been entered. Please use ...action=pluginradio&radio=<STATION>", 4);
			exit(1);
		}
    }
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$enteredRadio = mb_strtolower($_GET['radio']);
	$radios = $config['RADIO']['radio'];
	$valid = array();
	# pepare array and add details
	foreach ($radios as $val => $item)  {
		$split = explode(',' , $item);
		$split['lower'] = mb_strtolower($split[0]);
		#print_r($split);
		array_push($valid, $split);
	}
	$re = array();
	# iterate through array ans search
	foreach ($valid as $item)  {
		$radiocheck = contains($item['lower'], $enteredRadio);
		if ($radiocheck === true)   {
			$favorite = $item['lower'];
			array_push($re, array_multi_search($favorite, $valid));
		}
	}
	# if more then ONE Station been found
	if (count($re) > 1)  {
		LOGERR ("radio.php: Your entered favorite/keyword '".$enteredRadio."' has more then 1 hit! Please specify more detailed.");
		exit;
	}
	$check_stat = getZoneStatus($master);
	if ($check_stat != (string)"single")  {
		#$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
	}
	if(isset($_GET['member'])) {
		AddMemberTo();
		LOGINF ("radio.php: Member has been added");
	}
	# if no match has been found
	if (count($re) < 1)  {
		LOGERR ("radio.php: Your entered favorite/keyword '".$enteredRadio."' could not be found! Please specify more detailed.");
		exit;
	}
	#print_r($re);
	$sonos = new SonosAccess($sonoszone[$master][0]);
	try {
		$sonos->SetRadio('x-rincon-mp3radio://'.$re[0][0][1], $re[0][0][0], $re[0][0][2]);
		$sonos->SetGroupMute(false);
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $lookup[0]['Player'][$master][0]['Volume'];
		}
		$sonos->SetVolume($volume);
		$sonos->Play();
		LOGOK("radio.php: Your Radio '".$re[0][0][0]."' has been successful loaded and is playing!");
	} catch (Exception $e) {
		LOGERR("radio.php: Something went unexpected wrong! Please check your URL/entry and try again!");
		exit;
	}	
}
	



?>