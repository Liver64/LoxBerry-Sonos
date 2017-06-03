<?php
# text2speech.php

/**
* Function : saveZonesStatus --> saves current details for each Zone
*
* @param: 	empty
* @return: 	array
**/
	
	
function saveZonesStatus() {
	global $sonoszone, $config, $sonos, $player, $actual;
	
	// save each Zone Status
	foreach ($sonoszone as $player => $value) {
		$sonos = new PHPSonos($config['sonoszonen'][$player][0]); 
		$actual[$player]['Mute'] = $sonos->GetMute($player);
		$actual[$player]['Volume'] = $sonos->GetVolume($player);
		$actual[$player]['MediaInfo'] = $sonos->GetMediaInfo($player);
		$actual[$player]['PositionInfo'] = $sonos->GetPositionInfo($player);
		$actual[$player]['TransportInfo'] = $sonos->GetTransportInfo($player);
		$actual[$player]['TransportSettings'] = $sonos->GetTransportSettings($player);
		$actual[$player]['Group-ID'] = $sonos->GetZoneGroupAttributes($player);
		$actual[$player]['Grouping'] = getGroup($player);
		$actual[$player]['ZoneStatus'] = getZoneStatus($player);
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

function create_tts($text, $messageid) {
	global $sonos, $config, $fileolang, $fileo, $actual, $player, $messageid, $words, $home;
						
	$messageid = !empty($_GET['messageid']) ? $_GET['messageid'] : '0';
	$messageid = _assertNumeric($messageid);
	$rampsleep = $config['TTS']['rampto'];
							
	if(isset($_GET['weather'])) {
		// calls the weather-to-speech Function
		if(substr($home,0,4) == "/opt") {	
			include_once("addon/weather-to-speech.php");
		} else {
			include_once("addon/weather-to-speech_nolb.php");
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
		echo 'The input is invalid. Please enter text';
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
		}
		if ($config['TTS']['t2s_engine'] == 4001) {
			include_once("voice_engines/Polly.php");
		}
		if ($config['TTS']['t2s_engine'] == 8001) {
			include_once("voice_engines/microsoft.php");
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

function play_tts($messageid) {
	global $volume, $config, $sonos, $text, $messageid, $sonoszone, $sonoszonen, $master, $myMessagepath, $coord, $actual, $player;
	
		$sonos = new PHPSonos($coord[0]);
		if (isset($_GET['messageid'])) {
			// Set path if messageid
			$mpath = $myMessagepath."".$config['MP3']['MP3path'];
		} else {
			// Set path if T2S
			$mpath = $myMessagepath;
		}
		// if Playbar is in Modus TV switch to Playlist 1st
		if (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")  {  
			$sonos->SetQueue("x-rincon-queue:" . $config['sonoszonen'][$player][0] . "#0"); //Playliste aktivieren
		}
		// if modus playing is playlist set the playmode to NORMAL
		$save_plist = count($sonos->GetCurrentPlaylist());
		if ($save_plist > 998) {
			trigger_error("The T2S could not be played because the current Playlist contains 1000 entries! Please reduce playlist.", E_USER_ERROR);
		}
		// Playlist is playing
		if ($save_plist >= 1) {
			## -- Neuer Teil mit temporären Speichern der Playliste -- 
			# save temporally playlist
			// SavePlaylist();
			// $sonos->ClearQueue();
			## -- Ende Neuer Teil --
			$message_pos = count($sonos->GetCurrentPlaylist()) + 1;
		} else {
			$message_pos = count($save_plist);
		}
		// Playgong/jingle to be played upfront
		if(isset($_GET['playgong']) && ($_GET['playgong'] == "yes")) {
			$jinglepath = $myMessagepath."".$config['MP3']['MP3path']."/".$config['MP3']['file_gong'];
			$sonos->AddToQueue("x-file-cifs:".$jinglepath.".mp3");
		}
		## -- Neuer Teil mit temporären Speichern der Playliste -- 
		// $message_pos = 1;
		## -- Ende Neuer Teil --
		$sonos->AddToQueue('x-file-cifs:'.$mpath."/".$messageid.".mp3");
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		$sonos->SetTrack($message_pos);
		$sonos->SetGroupMute(false);
		$sonos->SetPlayMode('NORMAL');
		#$sonos->SetVolume($volume);
		try {
			$sonos->Play();
		} catch (Exception $e) {
			trigger_error("The T2S message could not be played!", E_USER_NOTICE);
		}
		$abort = false;
		sleep($config['TTS']['sleeptimegong']); // wait according to config
		while ($sonos->GetTransportInfo()==1) {
			usleep(200000); // check every 200ms
		}
		$sonos->RemoveFromQueue($message_pos);
		if(isset($_GET['playgong']) && ($_GET['playgong'] == "yes")) {		
			$sonos->RemoveFromQueue($message_pos);
		}
		## -- Neuer Teil mit temporären Speichern der Playliste -- 
		// $sonos->ClearQueue();
		// LoadPlaylist("temp_t2s");
		// DelPlaylist();
		## -- Ende Neuer Teil --
		return $actual;
		
		
}


/**
* Function : sendmessage --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendmessage() {
			global $text, $master, $messageid, $logging, $words, $voice, $config, $actual, $player, $volume, $sonos, $coord, $time_start;
			include_once("text2speech.php");
					
			if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
				$volume = $_GET['volume'];
			} else 	{
				// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
				$volume = $config['sonoszonen'][$master][3];
			}
			checkaddon();
			checkTTSkeys();
			$save = saveZonesStatus(); // saves all Zones Status
			$return = getZoneStatus($master); // get current Zone Status (Single, Member or Master)
			if($return == 'member') {
				if(isset($_GET['sonos'])) { // check if Zone is Group Member, then abort
					trigger_error("The specified zone is part of a group! There are no information available.", E_USER_NOTICE);
				exit;
				}
			}
			if (($return == 'master') or ($return == 'member')) {
				$sonos->BecomeCoordinatorOfStandaloneGroup();  // in case Member or Master then remove Zone from Grouop
			}
			// stop 1st before Song Name been played
			if(!isset($_GET['sonos'])) {
				$sonos->Stop();
			}
			create_tts($text, $messageid);
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
			logging();
			delmp3();
}

/**
* Function : sendgroupmessage --> translate a text into speech for a group of zones
*
* @param: Text or messageid (Number)
* @return: 
**/
			
function sendgroupmessage() {			
			global $coord, $sonos, $text, $sonoszone, $member, $master, $zone, $messageid, $logging, $words, $voice, $config, $mute, $membermaster, $getgroup, $checkgroup;
			include_once("text2speech.php");
			
			if(isset($_GET['volume']) && $_GET['volume'] > 100) {
				trigger_error("The specified value for volume is not valid. Allowed values are 0 to 100, please check!", E_USER_ERROR);
				exit;
			}
			if(isset($_GET['sonos'])) {
				trigger_error("The parameter Sonos can not be used for group T2S!", E_USER_NOTICE);
				exit;
			}
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
			$coord = getRoomCoordinator($master);
			// speichern der Zonen Zustände
			$save = saveZonesStatus(); // saves all Zones Status
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
			$sonos->SetGroupMute(true); // --> prüfen ob korrekt so
			$sonos->SetPlayMode('NORMAL'); 
			if(!isset($_GET['sonos'])) {
				$sonos->Stop();
			}
			create_tts($text, $messageid);
			// Setzen der T2S Lautstärke je Member Zone
			foreach ($member as $memplayer => $zone) {
				$sonos = new PHPSonos($coord[0]); 
				$newvolume = $sonos->SetVolume($sonoszone[$zone][3]);
			}
			// Setzen der T2S Lautstärke für Master Zone
			$sonos = new PHPSonos($coord[0]); 
			$newmastervolume = $sonos->SetVolume($sonoszone[$master][3]);
			// erhöht oder verringert die Defaultwerte aus der config.php um xx Prozent
			if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
				$groupvolume = $_GET['volume'];
				$sonos = new PHPSonos($coord[0]); 
				$sonos = SetGroupVolume($groupvolume);
			}
			play_tts($messageid);
			// wiederherstellen der Ursprungszustände
			restoreGroupZone();
			logging();
			delmp3();
}


/**
* Function : restoreSingleZone --> restores previous Zone settings before a single message has been played 
*
* @param:  empty
* @return: previous settings
**/		

function restoreSingleZone() {
	global $sonoszone, $sonos, $master, $actual;
	
	$restore = $actual;
	switch ($restore) {
		// Zone was playing in Single Mode
		case $actual[$master]['ZoneStatus'] == 'single':
			$prevStatus = "single";
			if (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 5) == "npsdy" || 
				substr($actual[$master]['PositionInfo']["TrackURI"], 0, 11) == "x-file-cifs" || 
				substr($actual[$master]['PositionInfo']["TrackURI"], 0, 12) == "x-sonos-http" ||
				substr($actual[$master]['PositionInfo']["TrackURI"], 0, 15) == "x-sonos-spotify") { // Es läuft eine Musikliste 
				$sonos->SetTrack($actual[$master]['PositionInfo']["Track"]);
				$sonos->Seek($actual[$master]['PositionInfo']["RelTime"],"NONE");
				if($actual[$master]['TransportSettings']['shuffle'] == 1) {
					$sonos->SetPlayMode('SHUFFLE_NOREPEAT'); // schaltet Zufallswiedergabe wieder ein 
				} else {
					$sonos->SetPlayMode('NORMAL'); // spielt im Normal Modus weiter
				}
				} 
				# TV Playbar
				elseif (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
					$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]); 
					} 
				# Radio
				elseif (($actual[$master]['PositionInfo']["TrackDuration"] == '') && ($actual[$master]['PositionInfo']["title"] <> '')){
					$radioURL = $actual[$master]['PositionInfo']["TrackURI"];
					$radioName = ($actual[$master]['MediaInfo']['title']);
					#$radioName = str_replace(' ','',$radioName);
					$sonos->SetRadio($radioURL, $radioName);
				}
				$sonos->SetVolume($actual[$master]['Volume']);
				$sonos->SetMute($actual[$master]['Mute']);
				if ($actual[$master]['TransportInfo'] == 1) {
					$sonos->Play();	
				}
		break;
		
		// Zone was Member of a group
		case $actual[$master]['ZoneStatus'] == 'member':
			$prevStatus = "member";
			$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]); 
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
		break;
		
		// Zone was Master of a group
		case $actual[$master]['ZoneStatus'] == 'master':
			$prevStatus = "master";
			$oldGroup = $actual[$master]['Grouping'];
			$exMaster = array_shift($oldGroup); // deletes previous master from array
			foreach ($oldGroup as $newMaster) {
				// loop threw former Members in order to get the New Coordinator
				$sonos = new PHPSonos($sonoszone[$newMaster][0]);
				$check = $sonos->GetPositionInfo($newMaster);
				$checkMaster = $check['trackURI'];
				if (empty($checkMaster)) {
					// if trackURI is empty add Zone to New Coordinator
					$sonos = new PHPSonos($sonoszone[$master][0]);
					$sonos->SetVolume($actual[$master]['Volume']);
					$sonos->SetMute($actual[$master]['Mute']);
					$sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$newMaster][1]);
				}
			}
		break;
		}
	}


/**
* Function : restoreGroupZone --> restores previous Group settings before a group message has been played 
*
* @param:  empty
* @return: previous settings
**/		

function restoreGroupZone() {
	global $sonoszone, $logpath, $master, $sonos, $config, $member, $player, $actual, $coord;
	
	foreach ($member as $zone) {
		$sonos = new PHPSonos($sonoszone[$zone][0]);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
	}
	// add Master to array
	array_push($member, $master);
	// Restore former settings for each Zone
	foreach($member as $zone => $player) {
		#echo $player.'<br>';
		$restore = $actual[$player]['ZoneStatus'];
		$sonos = new PHPSonos($sonoszone[$player][0]);
		switch($restore) {
		case 'single';  
			#echo 'Die Zone '.$player.' ist single<br>';
			// Zone was playing as Single Zone
			if (empty($actual[$player]['Grouping'])) {
			# Playlist
			if (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 5) == "npsdy" || 
				substr($actual[$player]['PositionInfo']["TrackURI"], 0, 11) == "x-file-cifs" || 
				substr($actual[$player]['PositionInfo']["TrackURI"], 0, 12) == "x-sonos-http" || 
				substr($actual[$player]['PositionInfo']["TrackURI"], 0, 15) == "x-sonos-spotify" && ($actual[$player]['GroupCoordinator'] == 'false')) { // Es läuft eine Musikliste
				$sonos->SetTrack($actual[$player]['PositionInfo']['Track']);
				$sonos->Seek($actual[$player]['PositionInfo']['RelTime'],"NONE");
					if($actual[$player]['TransportSettings']['shuffle'] == 1) {
						$sonos->SetPlayMode('SHUFFLE_NOREPEAT'); // schaltet Zufallswiedergabe wieder ein 
					} else {
						$sonos->SetPlayMode('NORMAL'); // spielt im Normal Modus weiter
					}
				} 
				# TV Playbar
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# Radio Station
				elseif (($actual[$player]['PositionInfo']["TrackDuration"] == '') && ($actual[$player]['PositionInfo']["title"] <> '')){
					$radioname = $actual[$player]['MediaInfo']["title"];
					$sonos->SetRadio($actual[$player]['PositionInfo']["TrackURI"], $radioname);
				}
			}
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			if($actual[$player]['TransportInfo'] != 1) {
				$sonos->Stop();
			} else {
				$sonos->Play();
			}
		break;
			
		case 'member';
			#echo 'Die Zone '.$player.' ist member<br>';
			# Zone was Member of a group
			$sonos = new PHPSonos($sonoszone[$player][0]);
			$tmp_checkmember = $actual[$player]['PositionInfo']["TrackURI"];
			$sonos->SetAVTransportURI($tmp_checkmember);
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
		break;
			
			
		case 'master';
			#echo 'Die Zone '.$player.' ist master<br>';
			# Zone was Master of a group
			$sonos = new PHPSonos($sonoszone[$player][0]);
			# Playlist
			if (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 5) == "npsdy" || 
				substr($actual[$player]['PositionInfo']["TrackURI"], 0, 11) == "x-file-cifs" || 
				substr($actual[$player]['PositionInfo']["TrackURI"], 0, 12) == "x-sonos-http" || 
				substr($actual[$player]['PositionInfo']["TrackURI"], 0, 15) == "x-sonos-spotify" && ($actual[$player]['GroupCoordinator'] == 'false')) { // Es läuft eine Musikliste
				$sonos->SetTrack($actual[$player]['PositionInfo']['Track']);
				$sonos->Seek($actual[$player]['PositionInfo']['RelTime'],"NONE");
					if($actual[$player]['TransportSettings']['shuffle'] == 1) {
						$sonos->SetPlayMode('SHUFFLE_NOREPEAT'); // schaltet Zufallswiedergabe wieder ein 
					} else {
						$sonos->SetPlayMode('NORMAL'); // spielt im Normal Modus weiter
					}
				} 
				# TV Playbar
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# Radio Station
				elseif (($actual[$player]['PositionInfo']["TrackDuration"] == '') && ($actual[$player]['PositionInfo']["title"] <> '')){
					$radioname = $actual[$player]['MediaInfo']["title"];
					$sonos->SetRadio($actual[$player]['PositionInfo']["TrackURI"], $radioname);
				}
			# Restore Zone Members
			$tmp_group = $actual[$player]['Grouping'];
			$tmp_group1st = array_shift($tmp_group);
			foreach ($tmp_group as $groupmem) {
				$sonos = new PHPSonos($sonoszone[$groupmem][0]);
				$sonos->SetAVTransportURI($actual[$groupmem]['PositionInfo']["TrackURI"]);
				$sonos->SetVolume($actual[$groupmem]['Volume']);
				$sonos->SetMute($actual[$groupmem]['Mute']);
			}
			# Start restore Master settings
			$sonos = new PHPSonos($sonoszone[$player][0]);
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			if($actual[$player]['TransportInfo'] != 1) {
				$sonos->Stop();
			} else {
				$sonos->Play();
			}
		break;			
		}
	}
}	

/** -> OBSOLETE
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
			$gefunden = 1;
		}
		$pleinzeln++;
	}			
}




?>