<?php
# text2speech.php

/**
* Function : saveZonesStatus --> saves current details for each Zone
*
* @param: 	empty
* @return: 	array
**/
	
	
function saveZonesStatus() {
	global $sonoszone, $config, $sonos, $player, $actual, $time_start;
	
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
		$actual[$player]['CONNECT'] = GetVolumeModeConnect($player);
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
	global $sonos, $config, $fileolang, $fileo, $actual, $player, $messageid, $words, $home, $time_start, $tmp_batch, $words;
						
	$messageid = !empty($_GET['messageid']) ? $_GET['messageid'] : '0';
	#$messageid = _assertNumeric($messageid);
	#print $messageid = rawurlencode($messageid);
	$rampsleep = $config['TTS']['rampto'];
							
	if(isset($_GET['weather'])) {
		// calls the weather-to-speech Function
		if(substr($home,0,4) == "/opt") {	
			if(isset($_GET['lang']) and $_GET['lang'] == "nb-NO" or $_GET['voice'] == "Liv") {
				include_once("addon/weather-to-speech_no.php");
			} else {
				include_once("addon/weather-to-speech.php");
			}
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
	elseif (isset($_GET['pollen'])) {
		// calls the pollen-to-speech Function
		include_once("addon/pollen-to-speach.php");
		$fileo = p2s($text);
		$words = substr($fileo, 0, 500);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['warning'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/weather-warning-to-speech.php");
		$fileo = ww2s($text);
		$words = substr($fileo, 0, 500);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['distance'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/time-to-destination-speech.php");
		$fileo = tt2t($text);
		$words = substr($fileo, 0, 500);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['witz'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$fileo = GetWitz($text);
		#$words = substr($fileo, 0, 500);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['bauernregel'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$fileo = GetTodayBauernregel($text);
		$words = substr($fileo, 0, 500);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['abfall'])) {
		// calls the wastecalendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$fileo = muellkalender($text);
		$words = substr($fileo, 0, 500);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['calendar'])) {
		// calls the calendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$fileo = calendar($text);
		$words = substr($fileo, 0, 500);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['sonos'])) {
		// calls the sonos-to-speech Function
		include_once("addon/sonos-to-speech.php");
		$fileo = s2s($text);
		$words = urlencode($fileo);
		$rampsleep = false;
		}
	elseif ((empty($messageid)) && (!isset($_GET['text'])) and (isset($_GET['playbatch']))) {
		echo 'The input is invalid. Please enter text';
		exit();
		}
	elseif (!empty($messageid)) { # && ($fileo != '')) {
		// takes the messageid
		$fileo = $_GET['messageid'];
		}
	elseif ((empty($messageid)) && ($text == '')) {
		if ($words == true) {
			$words = '';
		} else {
			// prepares the T2S message
			$fileo = !empty($_GET['text']) ? $_GET['text'] : ''; 
			$words = substr($_GET['text'], 0, 500); 
			$words = urlencode($_GET['text']);	
		}
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
		if ($config['TTS']['t2s_engine'] == 8001) {
			include_once("voice_engines/micro.php");
		}
		if ($config['TTS']['t2s_engine'] == 5001) {
			include_once("voice_engines/Pico_tts.php");
		}
		if ($config['TTS']['t2s_engine'] == 4001) {
			include_once("voice_engines/Polly.php");
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
	global $volume, $config, $sonos, $text, $messageid, $sonoszone, $sonoszonen, $master, $myMessagepath, $coord, $actual, $player, $time_start, $t2s_batch, $filename, $words, $home;
		
		$sonos = new PHPSonos($coord[0]);
		if (isset($_GET['messageid'])) {
			// Set path if messageid
			$mpath = $myMessagepath."".$config['MP3']['MP3path'];
			if(substr($home,0,4) == "/opt") {
				chmod_r();
			}
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
			global $text, $master, $messageid, $logging, $words, $voice, $config, $actual, $player, $volume, $sonos, $coord, $time_start, $fileolang, $sonoszone, $tmp_batch, $mode;
			include_once("text2speech.php");
			
			// if batch has been choosed save filenames to a txt file and exit
			if(isset($_GET['batch'])) {
				if((isset($_GET['volume'])) or (isset($_GET['rampto'])) or (isset($_GET['playmode']))) {
					trigger_error("The parameter volume, rampto and playmode are not allowed to be used in conjunction with batch. Please remove from syntax!", E_USER_NOTICE);
					exit;
				}
				create_tts($text, $messageid);
				// creates file to store T2S filenames
				$filename = "t2s_batch.txt";
				$file = fopen($filename, "a+");
				if($file == false ) {
					trigger_error("There is no T2S batch file to be written!", E_USER_WARNING);
					exit();
				}
				fwrite($file, "$fileolang\n" );
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
			create_tts($text, $messageid);
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
			global $coord, $sonos, $text, $sonoszone, $member, $master, $zone, $messageid, $logging, $words, $voice, $config, $mute, $membermaster, $getgroup, $checkgroup, $time_start, $mode, $modeback, $actual;
			include_once("text2speech.php");
			
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
			create_tts($text, $messageid);
			$group = $member;
			// master der array hinzufügen
			array_push($group, $master);
			// Regelung des Volumes für T2S
			foreach ($group as $memplayer => $zone2) {
				$sonos = new PHPSonos($sonoszone[$zone2][0]);
				if(isset($_GET['volume']) or isset($_GET['groupvolume']))  { 
					isset($_GET['volume']) ? $groupvolume = $_GET['volume'] : $groupvolume = $_GET['groupvolume'];
					$sonos->SetVolume($sonoszone[$zone2][3]);					
					$newvolume = $sonos->GetVolume();
					#$tmp_vol = $newvolume + $groupvolume; // addieren
					$tmp_vol = $newvolume + ($newvolume * ($groupvolume / 100));  // multiplizieren
					// prüfen ob errechnete Volume > 100 ist, falls ja max. auf 100 setzen
					$tmp_vol > 100 ? $tmp_vol = 100 : $tmp_vol;
					$sonos->SetVolume($tmp_vol);
					$final_vol = $sonos->GetVolume();
				} else {
					$newvolume = $sonos->SetVolume($sonoszone[$zone2][3]);
					$final_vol = $sonos->GetVolume();
				}
				$sonos->SetMute(false);
				#echo $zone2.' = '.$final_vol.'<br>';
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
	global $sonoszone, $sonos, $master, $actual, $time_start, $mode;
	
	$restore = $actual;
	switch ($restore) {
		// Zone was playing in Single Mode
		case $actual[$master]['ZoneStatus'] == 'single':
			$prevStatus = "single";
			restore_details($master);
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
			if (!empty($actual[$master]['PositionInfo']["duration"]))  {
				SetShuffle($actual, $master);
			}
			if (($actual[$master]['TransportInfo'] == 1)) {
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
		
		if (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
			#echo "LineIn";
			# Zone was Master of a group
			$sonos = new PHPSonos($sonoszone[$master][0]);
			$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]);
			
			# Restore Zone Members
			$tmp_group = $actual[$master]['Grouping'];
			$tmp_group1st = array_shift($tmp_group);
			foreach ($tmp_group as $groupmem) {
				$sonos = new PHPSonos($sonoszone[$groupmem][0]);
				$sonos->SetAVTransportURI($actual[$groupmem]['PositionInfo']["TrackURI"]);
				$sonos->SetVolume($actual[$groupmem]['Volume']);
				$sonos->SetMute($actual[$groupmem]['Mute']);
			}
			# Start restore Master settings
			$sonos = new PHPSonos($sonoszone[$master][0]);
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
		} else {
			#echo "Normal";
			$prevStatus = "master";
			$oldGroup = $actual[$master]['Grouping'];
			$exMaster = array_shift($oldGroup); // deletes previous master from array
			foreach ($oldGroup as $newMaster) {
				// loop threw former Members in order to get the New Coordinator
				$sonos = new PHPSonos($sonoszone[$newMaster][0]);
				$check = $sonos->GetPositionInfo();
				$checkMaster = $check['TrackURI'];
				if (empty($checkMaster)) {
					// if TrackURI is empty add Zone to New Coordinator
					$sonos_old = new PHPSonos($sonoszone[$master][0]);
					$sonos_old->SetVolume($actual[$master]['Volume']);
					$sonos_old->SetMute($actual[$master]['Mute']);
					$sonos_old->SetAVTransportURI("x-rincon:" . $sonoszone[$newMaster][1]);
				}
			}
			# add previous master back to group and restore settings
			$sonos = new PHPSonos($sonoszone[$exMaster][0]);
			$sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$newMaster][1]);
			$sonos->SetVolume($actual[$exMaster]['Volume']);
			$sonos->SetMute($actual[$exMaster]['Mute']);
		try {
			$sonos = new PHPSonos($sonoszone[$newMaster][0]);
			$sonos->DelegateGroupCoordinationTo($sonoszone[$master][1], 1);
		} catch (Exception $e) {
			trigger_error("Assignment to new GroupCoordinator " . $master . " failed.", E_USER_NOTICE);	
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
	global $sonoszone, $logpath, $master, $sonos, $config, $member, $player, $actual, $coord, $time_start;
	
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
			if ((substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) !== "x-sonos-htastream:") &&
				($actual[$player]['PositionInfo']["TrackDuration"] != '')) {			
				SetShuffle($actual, $player);
				} 
				# TV Playbar
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# LineIn
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# Radio Station
				elseif (($actual[$player]['PositionInfo']["TrackDuration"] == '') or ($actual[$player]['MediaInfo']["title"] <> '')) {
					@$radioname = $actual[$player]['MediaInfo']["title"];
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
			if ((substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) !== "x-sonos-htastream:") &&
				($actual[$player]['PositionInfo']["TrackDuration"] != '')) {			
				SetShuffle($actual, $player);
				} 
				# TV Playbar
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# LineIn
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# Radio Station
				elseif (($actual[$player]['PositionInfo']["TrackDuration"] == '') && ($actual[$player]['MediaInfo']["title"] <> '')){
					$radionam = $actual[$player]['MediaInfo']["title"];
					#echo $radionam;
					$sonos->SetRadio($actual[$player]['PositionInfo']['TrackURI'],"$radionam");
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
			$gefunden = 1;
		}
		$pleinzeln++;
	}			
}


/**
* Function : read_txt_file_to_array --> Subfunction of batch T2S to read a txt file into an array
*
* @param: file
* @return: array
**/

function read_txt_file_to_array() {
	global $t2s_batch, $filename;
	
	$filename = "t2s_batch.txt";
    if (!file_exists($filename)) {
		trigger_error("There is no T2S batch file to be played!", E_USER_WARNING);
        exit();
	}
	$t2s_batch = file("t2s_batch.txt");
	unlink($filename);
	#print_r($t2s_batch);
	return $t2s_batch;
}


/**
* Function : select_t2s_engine --> selects the configured t2s engine for speech creation
*
* @param: empty
* @return: 
**/

function select_t2s_engine()  {
	global $config;
	
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
	if ($config['TTS']['t2s_engine'] == 4001) {
		include_once("voice_engines/Polly.php");
	}
}


/**
* Function : say_radio_station --> announce radio station before playing appropriate
*
* @param: 
* @return: 
**/
function say_radio_station() {
			
	# nach nextradio();
	global $text, $master, $messageid, $sonoszone, $logging, $words, $voice, $config, $actual, $player, $volume, $sonos, $coord, $time_start, $fileolang, $tmp_batch;
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
	$words = urlencode($text);
	$fileo = md5($words);
	$fileolang = "$fileo";
	$messageid = $fileolang;
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



/**
* Function : restore_details() --> restore the details of master zone
*
* @param: 
* @return: 
**/

function restore_details($zone) {
	global $sonoszone, $sonos, $master, $actual, $j, $browselist, $senderName;
	
	# Playlist
	if ((substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 18) !== "x-sonos-htastream:") && ($actual[$zone]['PositionInfo']["TrackDuration"] != '')) {			
		$sonos->SetTrack($actual[$zone]['PositionInfo']['Track']);
		$sonos->Seek($actual[$zone]['PositionInfo']['RelTime'],"NONE");
		
		#$mode = $actual[$zone]['TransportSettings'];
		#playmode_detection($zone, $mode);
	} 
	# TV Playbar
	elseif (substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
	} 
	# LineIn
	elseif (substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
	} 
	# Radio Station
	elseif (($actual[$zone]['PositionInfo']["TrackDuration"] == '') or ($actual[$zone]['MediaInfo']["title"] <> '')) {
		@$radioname1 = $actual[$zone]['MediaInfo']["title"];
		$sonos->SetRadio(urldecode($actual[$zone]['PositionInfo']["TrackURI"]), "$radioname1");
	}
}





/**
* Function : SetShuffle() --> Restore previous playmode settings
*
* @param: array $actual array of saved status, string $player Masterplayer
* @return: static
**/

function SetShuffle($actual, $player) {
	global $sonoszonen;
	
	$sonos = new PHPSonos($sonoszonen[$player][0]);
	$mode = $actual[$player]['TransportSettings'];
	playmode_detection($player, $mode);
	$pl = $sonos->GetCurrentPlaylist();
	$titel = (string)$actual[$player]['PositionInfo']['title'];
	// falls irgendein SHUFFLE ein
	if ($mode['shuffle'] == 1)  { 
		$trackNoSearch = recursive_array_search($titel, $pl);
		$track = (string)$pl[$trackNoSearch]['listid'];
		$sonos->SetTrack($track);
		$sonos->Seek($actual[$player]['PositionInfo']['RelTime'],"NONE");	
	// falls SHUFFLE aus
	} else {
		$sonos->SetTrack($actual[$player]['PositionInfo']['Track']);
		$sonos->Seek($actual[$player]['PositionInfo']['RelTime'],"NONE");	
	}
}
	





?>