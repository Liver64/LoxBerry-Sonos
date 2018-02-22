<?php

/**
* Submodul: Play_T2S
*
**/

/**
* New Function for T2S: say --> replacement/enhancement for sendmessage/sendgroupmessage
*
* @param: empty
* @return: nothing
**/

function say() {
	#include_once("text2speech.php");
		
	if(!isset($_GET['member'])) {
		sendmessage();
	} else {
		sendgroupmessage();
	}	
}


/**
* Function : create_tts --> creates an MP3 File based on Text Input
*
* @param: 	Text of Messasge ID
* @return: 	MP3 File
**/		

function create_tts() {
	global $sonos, $config, $filename, $MessageStorepath, $player, $messageid, $textstring, $home, $time_start, $tmp_batch;
						
	$messageid = !empty($_GET['messageid']) ? $_GET['messageid'] : '0';
	$rampsleep = $config['TTS']['rampto'];
	
	isset($_GET['text']) ? $text = $_GET['text'] : $text = '';
	if(isset($_GET['weather'])) {
		// calls the weather-to-speech Function
		if(isset($_GET['lang']) and $_GET['lang'] == "nb-NO" or @$_GET['voice'] == "Liv") {
			include_once("addon/weather-to-speech_no.php");
		} else {
			include_once("addon/weather-to-speech.php");
		}
		$textstring = substr(w2s(), 0, 500);
		} 
	elseif (isset($_GET['clock'])) {
		// calls the clock-to-speech Function
		include_once("addon/clock-to-speech.php");
		$textstring = c2s();
		}
	elseif (isset($_GET['pollen'])) {
		// calls the pollen-to-speech Function
		include_once("addon/pollen-to-speach.php");
		$textstring = substr(p2s(), 0, 500);
		}
	elseif (isset($_GET['warning'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/weather-warning-to-speech.php");
		$textstring = substr(ww2s(), 0, 500);
	}
	elseif (isset($_GET['distance'])) {
		// calls the time-to-destination-speech Function
		include_once("addon/time-to-destination-speech.php");
		$textstring = substr(tt2t(), 0, 500);
		}
	elseif (isset($_GET['witz'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$textstring = substr(GetWitz(), 0, 1000);
		}
	elseif (isset($_GET['bauernregel'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$textstring = urlencode(substr(GetTodayBauernregel(), 0, 500));
		}
	elseif (isset($_GET['abfall'])) {
		// calls the wastecalendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$textstring = substr(muellkalender(), 0, 500);
		}
	elseif (isset($_GET['calendar'])) {
		// calls the calendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$textstring = substr(calendar(), 0, 500);
		}
	elseif (isset($_GET['sonos'])) {
		// calls the sonos-to-speech Function
		include_once("addon/sonos-to-speech.php");
		$textstring = s2s();
		$rampsleep = false;
		}
	elseif ((empty($messageid)) && (!isset($_GET['text'])) and (isset($_GET['playbatch']))) {
		echo 'The input is invalid. Please enter text';
		exit();
		}
	elseif (!empty($messageid)) { # && ($rawtext != '')) {
		// takes the messageid
		$messageid = $_GET['messageid'];
		}
	elseif ((empty($messageid)) && ($text <> '')) {
		// prepares the T2S message
		$textstring = urlencode(substr($_GET['text'], 0, 500));	
		}	
	
	// encrypt MP3 file as MD5 Hash
	$filename  = md5($textstring);
	#echo 'messageid: '.$messageid.'<br>';
	#echo 'textstring: '.$textstring.'<br>';
	#echo 'filename: '.$filename.'<br>';
	#exit;
	// calls the various T2S engines depending on config)
	if (($messageid == '0') && ($textstring != '')) {
		if ($config['TTS']['t2s_engine'] == 1001) {
			include_once("voice_engines/VoiceRSS.php");
		}
		if ($config['TTS']['t2s_engine'] == 3001) {
			include_once("voice_engines/MAC_OSX.php");
		}
		if ($config['TTS']['t2s_engine'] == 6001) {
			include_once("voice_engines/ResponsiveVoice.php");
		}
		if ($config['TTS']['t2s_engine'] == 7001) {
			include_once("voice_engines/Google.php");
		}
		if ($config['TTS']['t2s_engine'] == 8001) {
			include_once("voice_engines/micro.php");
		}
		if ($config['TTS']['t2s_engine'] == 5001) {
			include_once("voice_engines/Pico_tts.php");
		}
		if ($config['TTS']['t2s_engine'] == 4001) {
			include_once("voice_engines/Polly.php");
		}
		
	t2s($messageid, $MessageStorepath, $textstring, $filename);
	return $messageid;
	}
}


/**
* Function : play_tts --> play T2S or MP3 File
*
* @param: 	MessageID, Parameter zur Unterscheidung ob Gruppen oder EInzeldurchsage
* @return: empty
**/		

function play_tts($messageid) {
	global $volume, $config, $sonos, $text, $messageid, $sonoszone, $sonoszonen, $master, $myMessagepath, $coord, $actual, $player, $time_start, $t2s_batch, $filename, $textstring, $home;
		
		$sonos = new PHPSonos($coord[0]);
		if (isset($_GET['messageid'])) {
			// Set path if messageid
			$mpath = $myMessagepath."".$config['MP3']['MP3path'];
			chmod_r();
		} else {
			// Set path if T2S
			$mpath = $myMessagepath;
		}
		// if Playbar is in Modus TV switch to Playlist 1st
		if (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")  {  
		$sonos->SetQueue("x-rincon-queue:".$coord[1]."#0");
		}
		// ***** Alter Teil ****** ($player wurde durch $master ersetzt)
		#if (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")  {  
		#	$sonos->SetQueue("x-rincon-queue:" . $config['sonoszonen'][$player][0] . "#0"); //Playliste aktivieren
		#}
		// ***** Ende alter Teil ******
		$save_plist = count($sonos->GetCurrentPlaylist());
		// Playlist is playing
		// if Playlist has more then 998 entries
		if ($save_plist > 998) {
			// save temporally playlist
			SavePlaylist();
			$sonos->ClearQueue();
			$message_pos = 1;
		}
		// if Playlist has more then 1 or less then 999 entries
		if ($save_plist >= 1 && $save_plist <= 998) {
			$message_pos = count($sonos->GetCurrentPlaylist()) + 1;
		} else {
			// No Playlist is playing
			$message_pos = count($save_plist);
		}
		// Playgong/jingle to be played upfront
		if(isset($_GET['playgong']) && ($_GET['playgong'] == "yes")) {
			$jinglepath = $myMessagepath."".$config['MP3']['MP3path']."/".$config['MP3']['file_gong'];
			$sonos->AddToQueue("x-file-cifs:".$jinglepath.".mp3");
		}
		// if batch has been created add all T2S
		$filename = "t2s_batch.txt";
		if ((file_exists($filename)) and (!isset($_GET['playbatch']))){
			$t2s_batch = read_txt_file_to_array($t2s_batch);
			foreach ($t2s_batch as $t2s => $messageid) {
				$sonos->AddToQueue('x-file-cifs:'.$mpath."/".trim($messageid).".mp3");
			}
		} else {
			// if no batch has been created add single T2S
			$sonos->AddToQueue('x-file-cifs:'.$mpath."/".$messageid.".mp3");
		}
		#$t = 'x-file-cifs:'.$mpath."/".$messageid.".mp3";
		$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
		$sonos->SetPlayMode('NORMAL');
		$sonos->SetTrack($message_pos);
		$sonos->SetGroupMute(false);
		try {
			$sonos->Play();
		} catch (Exception $e) {
			trigger_error("The T2S message(s) could not be played!", E_USER_NOTICE);
		}
		$abort = false;
		sleep($config['TTS']['sleeptimegong']); // wait according to config
		while ($sonos->GetTransportInfo()==1) {
			usleep(200000); // check every 200ms
		}
		// if Playlist has more than 998 entries
		if ($save_plist > 998) {
			$sonos->ClearQueue();
			LoadPlaylist("temp_t2s");
			DelPlaylist();
		// if Playlist has less than or equal 998 entries
		} else {
			// If batch T2S has been be played
			if ((!empty($t2s_batch)) and (!isset($_GET['playbatch'])))  {
				$i = $message_pos;
				foreach ($t2s_batch as $t2si => $value) {
					$mess_pos = $message_pos;
					$sonos->RemoveFromQueue($mess_pos);
					$i++;
				} 
			} else {
				// If single T2S has been be played
				$sonos->RemoveFromQueue($message_pos);
			}
			if(isset($_GET['playgong']) && ($_GET['playgong'] == "yes")) {		
				$sonos->RemoveFromQueue($message_pos);
			}
		}
		return $actual;
		
		
}


/**
* Function : sendmessage --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendmessage() {
			global $text, $master, $messageid, $logging, $textstring, $voice, $config, $actual, $player, $volume, $sonos, $coord, $time_start, $filename, $sonoszone, $tmp_batch, $mode;
						
			// if batch has been choosed save filenames to a txt file and exit
			if(isset($_GET['batch'])) {
				if((isset($_GET['volume'])) or (isset($_GET['rampto'])) or (isset($_GET['playmode']))) {
					trigger_error("The parameter volume, rampto and playmode are not allowed to be used in conjunction with batch. Please remove from syntax!", E_USER_NOTICE);
					exit;
				}
				create_tts();
				// creates file to store T2S filenames
				$filenamebatch = "t2s_batch.txt";
				$file = fopen($filenamebatch, "a+");
				if($file == false ) {
					trigger_error("There is no T2S batch file to be written!", E_USER_WARNING);
					exit();
				}
				if ($messageid == '0') {
					fwrite($file, "$filename\n" );
				} else {
					$mp3_path = $config['MP3']['MP3path'];
					fwrite($file, "$mp3_path/$messageid\n" );
				}
				fclose($file);
				exit;
			}
			#var_dump($modeback = GetVolumeModeConnect());
			if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
				$volume = $_GET['volume'];
			} else 	{
				// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
				$volume = $config['sonoszonen'][$master][3];
			}
			checkaddon();
			checkTTSkeys();
			$save = saveZonesStatus(); // saves all Zones Status
			SetVolumeModeConnect($mode = '0', $master);
			$return = getZoneStatus($master); // get current Zone Status (Single, Member or Master)
			if($return == 'member') {
				if(isset($_GET['sonos'])) { // check if Zone is Group Member, then abort
					trigger_error("The specified zone is part of a group! There are no information available.", E_USER_NOTICE);
				exit;
				}
			}
			create_tts();
			// stop 1st before Song Name been played
			$test = $sonos->GetPositionInfo();
			if (($return == 'master') or ($return == 'member')) {
				$sonos->BecomeCoordinatorOfStandaloneGroup();  // in case Member or Master then remove Zone from Grouop
			}
			if (substr($test['TrackURI'], 0, 18) !== "x-sonos-htastream:") {
				$sonos->SetQueue("x-rincon-queue:". $sonoszone[$master][1] ."#0");
			}
			#if ((substr($test, 0, 18) !== "x-sonos-htastream:") and (!isset($_GET['sonos'])))  {
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
				usleep(500000);
			}
			// get Coordinator of (maybe) pair or single player
			$coord = getRoomCoordinator($master);
			$sonos = new PHPSonos($coord[0]); 
			$sonos->SetMute(false);
			$sonos->SetVolume($volume);
			play_tts($messageid);
			#$time_end = microtime(true);
			#$t2s_time = $time_end - $time_start;
			#echo "Die T2S dauerte $t2s_time Sekunden.\n";
			restoreSingleZone();
			$mode = "";
			$actual[$master]['CONNECT'] == 'true' ? $mode = '1' : $mode = '0';
			SetVolumeModeConnect($mode, $master);
			#logging();
			delmp3();
	}

/**
* Function : sendgroupmessage --> translate a text into speech for a group of zones
*
* @param: Text or messageid (Number)
* @return: 
**/
			
function sendgroupmessage() {			
			global $coord, $sonos, $text, $sonoszone, $member, $master, $zone, $messageid, $logging, $textstring, $voice, $config, $mute, $membermaster, $getgroup, $checkgroup, $time_start, $mode, $modeback, $actual;
						
			if(isset($_GET['batch'])) {
				trigger_error("The parameter batch is not allowed to be used in groups. Please use single message to prepare your batch!", E_USER_NOTICE);
				exit;
			}
			if(isset($_GET['volume']) or isset($_GET['groupvolume']))  { 
				isset($_GET['volume']) ? $groupvolume = $_GET['volume'] : $groupvolume = $_GET['groupvolume'];
				if ((!is_numeric($groupvolume)) or ($groupvolume < 0) or ($groupvolume > 200)) {
					trigger_error("The entered volume of ".$groupvolume." must be even numeric or between 0 and 200! Please correct", E_USER_ERROR);	
				}
			}
			if(isset($_GET['sonos'])) {
				trigger_error("The parameter 'sonos' can not be used for group T2S!", E_USER_NOTICE);
				exit;
			}
			
			#$save_plist = count($sonos->GetCurrentPlaylist());
			#if ($save_plist > 998) {
			#	trigger_error("The T2S could not be played because the current Playlist contains 1000 entries! Please reduce playlist.", E_USER_ERROR);
			#}
			checkaddon();
			checkTTSkeys();
			$master = $_GET['zone'];
			$member = $_GET['member'];
			// if parameter 'all' has been entered all zones were grouped
			if($member === 'all') {
				$member = array();
				foreach ($sonoszone as $zone => $ip) {
					// exclude master Zone
					if ($zone != $master) {
						array_push($member, $zone);
					}
				}
			} else {
				$member = explode(',', $member);
			}
			if (in_array($master, $member)) {
				trigger_error("The zone ".$master." could not be entered as member again. Please remove from Syntax '&member=".$master."' !", E_USER_ERROR);
			}
			// prüft alle Member ob Sie Online sind und löscht ggf. Member falls nicht Online
			checkZonesOnline($member);
			$coord = getRoomCoordinator($master);
			// speichern der Zonen Zustände
			$save = saveZonesStatus(); // saves all Zones Status
			foreach($member as $newzone) {
				SetVolumeModeConnect($mode = '0', $newzone);
				SetVolumeModeConnect($mode = '0', $master);
			}
			// create Group for Announcement
			$masterrincon = $coord[1]; 
			$sonos = new PHPSonos($coord[0]);
			$sonos->BecomeCoordinatorOfStandaloneGroup();
			// grouping
			foreach ($member as $zone) {
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				if ($zone != $master) {
					$sonos->SetAVTransportURI("x-rincon:" . $masterrincon); 
				}
			}
			#sleep($config['TTS']['sleepgroupmessage']); // warten gemäß config.php bis Gruppierung abgeschlossen ist
			$sonos = new PHPSonos($coord[0]);
			$sonos->SetPlayMode('NORMAL'); 
			$sonos->SetQueue("x-rincon-queue:". $coord[1] ."#0");
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
			}
			create_tts();
			$group = $member;
			// master der array hinzufügen
			array_push($group, $master);
			// Regelung des Volumes für T2S
			foreach ($group as $memplayer => $zone2) {
				$sonos = new PHPSonos($sonoszone[$zone2][0]);
				if(isset($_GET['volume']) or isset($_GET['groupvolume']))  { 
					isset($_GET['volume']) ? $groupvolume = $_GET['volume'] : $groupvolume = $_GET['groupvolume'];
					if(isset($_GET['volume'])) {
						$final_vol = $groupvolume;
					} else {
						$newvolume = $sonos->GetVolume();
						$final_vol = $newvolume + ($newvolume * ($groupvolume / 100));  // multiplizieren
						// prüfen ob errechnete Volume > 100 ist, falls ja max. auf 100 setzen
						$final_vol > 100 ? $final_vol = 100 : $final_vol;
					}
				} else {
					$final_vol = $sonoszone[$zone2][3];
				}
				$sonos->SetVolume($final_vol);
				$sonos->SetMute(false);
				#echo 'Zone: '.$zone2.'; Volume: '.$final_vol.'<br>';
			}
			play_tts($messageid);
			// wiederherstellen der Ursprungszustände
			restoreGroupZone();
			foreach($member as $newzone) {
				$mode = "";
				$actual[$newzone]['CONNECT'] == 'true' ? $mode = '1' : $mode = '0';
				SetVolumeModeConnect($mode, $newzone);
			}
			$mode = "";
			$actual[$master]['CONNECT'] == 'true' ? $mode = '1' : $mode = '0';
			SetVolumeModeConnect($mode, $master);
			#$modeback = '1' ? $mode = '1' : $mode = '0';
			#SetVolumeModeConnect($mode);
			#logging();
			delmp3();
			
}

/**
* New Function for T2S: t2s_playbatch --> allows T2S to be played in batch mode
*
* @param: empty
* @return: T2S
**/
function t2s_playbatch() {
	global $textstring;
			
	$textstring = true;
	$filename = "t2s_batch.txt";
	if (!file_exists($filename)) {
		trigger_error("There is no T2S batch file to be played!", E_USER_WARNING);
		exit();
	}
	say();
}


/**
* Function : say_radio_station --> announce radio station before playing appropriate
*
* @param: 
* @return: 
**/
function say_radio_station() {
			
	# nach nextradio();
	global $text, $master, $messageid, $sonoszone, $logging, $textstring, $voice, $config, $actual, $player, $volume, $sonos, $coord, $time_start, $filename, $tmp_batch;
	include_once("text2speech.php");
	include_once("addon/sonos-to-speech.php");
	
	// if batch has been choosed abort
	if(isset($_GET['batch'])) {
		trigger_error("The parameter batch could not be used to anounce the radio station!", E_USER_NOTICE);
		exit;
	}
	$sonos->Stop();
	if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
		$volume = $_GET['volume'];
	} else 	{
		// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
		$volume = $config['sonoszonen'][$master][3];
	}
	saveZonesStatus(); // saves all Zones Status
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$temp_radio = $sonos->GetMediaInfo();
	# Generiert und kodiert Ansage des laufenden Senders
	$text = utf8_encode('Radio '.$temp_radio['title']);
	$textstring = urlencode($text);
	$rawtext = md5($textstring);
	$filename = "$rawtext";
	$messageid = $filename;
	select_t2s_engine();
	t2s($messageid);
	// get Coordinator of (maybe) pair or single player
	$coord = getRoomCoordinator($master);
	$sonos = new PHPSonos($coord[0]); 
	$sonos->SetMute(false);
	$sonos->SetVolume($volume);
	play_tts($messageid);
	restoreSingleZone();
}

?>