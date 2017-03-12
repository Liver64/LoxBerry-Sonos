<?php
# text2speech.php

/**
* Function : saveZonesStatus --> saves Status for each Zone in Network
*
* @param: 	empty
* @return: 	array
**/
	
	
function saveZonesStatus() {
	global $sonoszone, $sonos;
	
	// save each Zone Status
	foreach ($sonoszone as $player => $sz) {
		$sonos = new PHPSonos($sonoszone[$sz][0]); 
		$actual[$sz]['Mute'] = $sonos->GetMute($sz);
		$actual[$sz]['Volume'] = $sonos->GetVolume($sz);
		$actual[$sz]['MediaInfo'] = $sonos->GetMediaInfo($sz);
		$actual[$sz]['PositionInfo'] = $sonos->GetPositionInfo($sz);
		$actual[$sz]['TransportInfo'] = $sonos->GetTransportInfo($sz);
		$actual[$sz]['TransportSettings'] = $sonos->GetTransportSettings($sz);
		$actual[$sz]['Topology'] = gettopology($sz);
		if(!empty($groupid)) {
			if (array_key_exists($save_status[$sz]['Topology']['IP-Adresse'], $groupid)) {
				$save_status[$sz]['GroupCoordinator'] = 'true';
				$save_status[$sz]['Groupmember'] = zonegroups($sz);
			} else {
				$save_status[$sz]['GroupCoordinator'] = 'false';
			}
		}
	}
	#print_r($actual);
	return $actual;
}



/**
* Function : create_tts --> creates an MP3 File based on Text Input
*
* @param: 	Text of Messasge ID
* @return: 	MP3 File
**/		

function create_tts_new($text, $messageid) {
	global $sonos, $config, $fileolang, $fileo, $actual;
					
	$messageid = !empty($_GET['messageid']) ? $_GET['messageid'] : '0';
	$messageid = _assertNumeric($messageid);
	$rampsleep = $config['TTS']['rampto'];
							
	if(isset($_GET['weather'])) {
		// calls the weather-to-speech Function
		if(substr($home,0,4) == "/opt") {	
			include_once("addon/weather-to-speech.php");
		} else {
			include_once("addon/weather-to-speech_noLB.php");
		}
		$fileo = w2s($text);
		$words = substr($fileo, 0, 500);
		$words = urlencode($fileo);
		} 
	elseif (isset($_GET['clock'])) {
		// calls the clock-to-speech Function
		include_once("addon/clock-to-speech.php");
		$fileo = c2s($text);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['sonos'])) {
		// calls the sonos-to-speech Function
		include_once("addon/sonos-to-speech.php");
		$fileo = s2s($text);
		$words = urlencode($fileo);
		$rampsleep = false;
		}
	elseif (($messageid == 0) && ($_GET['text'] == '')) {
		echo 'Die Eingabe ist ungueltig. Bitte den Text eingeben';
		exit();
		}
	elseif (is_numeric($messageid > 0)) { # && ($fileo != '')) {
		// takes the messageid
		$fileo = $_GET['messageid'];
		}
	elseif (($messageid == 0) && ($text == '')) {
		// prepares the T2S message
		$fileo = !empty($_GET['text']) ? $_GET['text'] : ''; 
		$words = substr($_GET['text'], 0, 500); 
		$words = urlencode($_GET['text']);				
		}	
	// encrypt MP3 file as MD5 Hash
	$fileo  = md5($words);
	$fileolang = "$fileo";
	// calls the various T2S engines depending on config)
	if (($messageid == '0') && ($fileo != '')) {
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
		if ($config['TTS']['t2s_engine'] == 5001) {
			include_once("voice_engines/Pico_tts.php");
		}
		if ($config['TTS']['t2s_engine'] == 2001) {
			include_once("voice_engines/Ivona.php");
			if(!isset($_GET['voice'])) {
				$voice = $config['TTS']['voice'];	
			} elseif (($_GET['voice'] == 'Marlene') or ($_GET['voice'] == 'Hans')) {
				$voice = $_GET['voice'];
			}
		}
		if ($config['TTS']['t2s_engine'] == 4001) {
			include_once("voice_engines/Polly.php");
			if(!isset($_GET['voice'])) {
				$voice = $config['TTS']['voice'];	
			} elseif (($_GET['voice'] == 'Marlene') or ($_GET['voice'] == 'Hans')) {
				$voice = $_GET['voice'];
			}
		}
	t2s($messageid);
	return $messageid;
	}
}

/**
* Function : play_tts --> play T2S or MP3 File
*
* @param: 	MessageID, Parameter zur Unterscheidung ob Gruppen oder EInzeldurchsage
* @return: empty
**/		

function play_tts_new($messageid) {
	global $volume, $config, $sonos, $messageid, $sonoszone, $master, $myMessagepath, $coord, $actual;
	
		$coord = getRoomCoordinator($master);
		$sonos = new PHPSonos($coord[0]);
		#$sonos = new PHPSonos($sonoszone[$master][0]);
		if (isset($_GET['messageid'])) {
			// Set path if messageid
			$mpath = $myMessagepath."".$config['MP3']['MP3path'];
		} else {
			// Set path if T2S
			$mpath = $myMessagepath;
		}
		$save_plist = count($sonos->GetCurrentPlaylist());
		// Playlist is playing
		if ($save_plist >= 1) {
			// save temporally playlist
			SavePlaylist();
			$sonos->ClearQueue();
		}
		// Playgong/jingle to be played upfront
		if(isset($_GET['playgong']) && ($_GET['playgong'] == "yes")) {
			$jinglepath = $myMessagepath."".$config['MP3']['MP3path']."/".$config['MP3']['file_gong'];
			$sonos->AddToQueue("x-file-cifs:".$jinglepath.".mp3");
		}
		$message_pos = 1;
		$sonos->AddToQueue('x-file-cifs:'.$mpath."/".$messageid.".mp3");
		$sonos->SetQueue("x-rincon-queue:".$coord[1]."#0");
		#$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		$sonos->SetTrack($message_pos);
		$sonos->SetGroupMute(false);
		$sonos->SetPlayMode('NORMAL');
		try {
			$sonos->Play();
		} catch (Exception $e) {
			trigger_error("Die T2S Message konnte nicht abgespielt werden!", E_USER_NOTICE);
		}
		$abort = false;
		sleep($config['TTS']['sleeptimegong']); // wait according to config
		while ($sonos->GetTransportInfo()==1) {
			usleep(200000); // check every 200ms
		}
		$sonos->ClearQueue();
		LoadPlaylist("temp_t2s");
		DelPlaylist();
		#sleep($config['TTS']['sleeptimegong']);  
		
}

/**
* Function : PlayList --> load previous saved Playlist back into Queue
*
* @param: PlayList                             
* @return: empty
**/

function LoadPlayList($playlist) {
	Global $sonos, $master, $sonoszone, $coord;
	
	$sonoslists=$sonos->GetSONOSPlaylists();
	$pleinzeln = 0;
	while ($pleinzeln < count($sonoslists) ) {
		if($playlist == $sonoslists[$pleinzeln]["title"]) {
			$plfile = urldecode($sonoslists[$pleinzeln]["file"]);
			$sonos->AddToQueue($plfile);
			$sonos->SetQueue("x-rincon-queue:".$coord[1]."#0"); 
			#$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0"); 
			$gefunden = 1;
		}
		$pleinzeln++;
	}			
}





?>