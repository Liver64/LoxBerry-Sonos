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
	global $sonos, $config, $filename, $MessageStorepath, $player, $messageid, $textstring, $home, $time_start, $tmp_batch, $MP3path;
						
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
		LOGGING("weather-to-speech plugin has been called", 7);
		} 
	elseif (isset($_GET['clock'])) {
		// calls the clock-to-speech Function
		include_once("addon/clock-to-speech.php");
		$textstring = c2s();
		LOGGING("clock-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['pollen'])) {
		// calls the pollen-to-speech Function
		include_once("addon/pollen-to-speach.php");
		$textstring = substr(p2s(), 0, 500);
		LOGGING("pollen-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['warning'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/weather-warning-to-speech.php");
		$textstring = substr(ww2s(), 0, 500);
		LOGGING("weather warning-to-speech plugin has been called", 7);
	}
	elseif (isset($_GET['distance'])) {
		// calls the time-to-destination-speech Function
		include_once("addon/time-to-destination-speech.php");
		$textstring = substr(tt2t(), 0, 500);
		LOGGING("time-to-distance speech plugin has been called", 7);
		}
	elseif (isset($_GET['witz'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$textstring = substr(GetWitz(), 0, 1000);
		LOGGING("Joke plugin has been called", 7);
		}
	elseif (isset($_GET['bauernregel'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$textstring = substr(GetTodayBauernregel(), 0, 500);
		LOGGING("Bauernregeln plugin has been called", 7);
		}
	elseif (isset($_GET['abfall'])) {
		// calls the wastecalendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$textstring = substr(muellkalender(), 0, 500);
		LOGGING("waste calendar-to-speech  plugin has been called", 7);
		}
	elseif (isset($_GET['calendar'])) {
		// calls the calendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$textstring = substr(calendar(), 0, 500);
		LOGGING("calendar-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['sonos'])) {
		// calls the sonos-to-speech Function
		include_once("addon/sonos-to-speech.php");
		$textstring = s2s();
		$rampsleep = false;
		LOGGING("sonos-to-speech plugin has been called", 7);
		}
	elseif ((empty($messageid)) && (!isset($_GET['text'])) and (isset($_GET['playbatch']))) {
		LOGGING("no text has been entered", 3);
		exit();
		}
	elseif (!empty($messageid)) { # && ($rawtext != '')) {
		// takes the messageid
		$messageid = $_GET['messageid'];
		if (file_exists($MessageStorepath."".$MP3path."/".$messageid.".mp3") === true)  {
			LOGGING("Messageid '".$messageid."' has been entered", 7);
		} else {
			LOGGING("The corrosponding messageid file '".$messageid.".mp3' does not exist or could not be played. Please check your directory or syntax!", 3);
			exit;
		}	
		}
	elseif ((empty($messageid)) && ($text <> '')) {
		// prepares the T2S message
		$textstring = (substr($_GET['text'], 0, 500));
		LOGGING("textstring has been entered", 7);		
		}	
	
	// encrypt MP3 file as MD5 Hash
	$filename  = md5($textstring);
	#echo 'messageid: '.$messageid.'<br>';
	#echo 'textstring: '.$textstring.'<br>';
	#echo 'filename: '.$filename.'<br>';
	// calls the various T2S engines depending on config)
	if (($messageid == '0') && ($textstring != '')) {
		if ($config['TTS']['t2s_engine'] == 1001) {
			include_once("voice_engines/VoiceRSS.php");
			LOGGING("VoiceRSS has been successful selected", 7);		
		}
		if ($config['TTS']['t2s_engine'] == 3001) {
			include_once("voice_engines/MAC_OSX.php");
			LOGGING("/MAC_OSX has been successful selected", 7);		
		}
		if ($config['TTS']['t2s_engine'] == 6001) {
			include_once("voice_engines/ResponsiveVoice.php");
			LOGGING("ResponsiveVoice has been successful selected", 7);		
		}
		if ($config['TTS']['t2s_engine'] == 7001) {
			include_once("voice_engines/Google.php");
			LOGGING("Google has been successful selected", 7);		
		}
		if ($config['TTS']['t2s_engine'] == 8001) {
			include_once("voice_engines/micro.php");
			LOGGING("Microsoft has been successful selected", 7);		
		}
		if ($config['TTS']['t2s_engine'] == 5001) {
			include_once("voice_engines/Pico_tts.php");
			LOGGING("Pico has been successful selected", 7);		
		}
		if ($config['TTS']['t2s_engine'] == 4001) {
			include_once("voice_engines/Polly.php");
			LOGGING("AWS Polly has been successful selected", 7);		
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
	global $volume, $config, $sonos, $text, $messageid, $sonoszone, $sonoszonen, $master, $myMessagepath, $coord, $actual, $player, $time_start, $t2s_batch, $filename, $textstring, $home, $MP3path, $sleeptimegong, $lbpplugindir, $logpath, $try_play, $MessageStorepath;
		
		$sonos = new PHPSonos($coord[0]);
		if (isset($_GET['messageid'])) {
			// Set path if messageid
			$mpath = $myMessagepath."".$MP3path;
			LOGGING("Path for messageid's been adopted", 7);		
			chmod_r();
		} else {
			// Set path if T2S
			$mpath = $myMessagepath;
			LOGGING("Path for T2S been adopted", 7);	
		}
		// if Playbar is in Modus TV switch to Playlist 1st
		if (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")  {  
			$sonos->SetQueue("x-rincon-queue:".$coord[1]."#0");
			LOGGING("Playbar was playing", 7);		
		}
		$save_plist = count($sonos->GetCurrentPlaylist());
		// Playlist is playing
		// if Playlist has more then 998 entries
		if ($save_plist > 998) {
			// save temporally playlist
			SavePlaylist();
			$sonos->ClearQueue();
			LOGGING("Queue has been cleared", 7);		
			$message_pos = 1;
			LOGGING("Playlist has more then 998 songs", 4);		
		}
		// if Playlist has more then 1 or less then 999 entries
		if ($save_plist >= 1 && $save_plist <= 998) {
			$message_pos = count($sonos->GetCurrentPlaylist()) + 1;
		} else {
			// No Playlist is playing
			$message_pos = count($save_plist);
		}
		// Playgong/jingle to be played upfront
		#*****************************************************************************************************
		if(isset($_GET['playgong'])) {
			if ($_GET['playgong'] == 'no')	{
				LOGGING("'playgong=no' could not be used in syntax, only 'playgong=yes' or 'playgong=file' are allowed", 3);
				exit;
			}
			if(empty($config['MP3']['file_gong'])) {
				LOGGING("Standard file for jingle is missing in Plugin config. Please maintain before usage.", 3);
				exit;	
			}
			if (($_GET['playgong'] != "yes") and ($_GET['playgong'] != "no") and ($_GET['playgong'] != " ")) {
				$file = $_GET['playgong'];
				$file = $file.'.mp3';
				$valid = mp3_files($file);
				if ($valid === true) {
					$jinglepath = $myMessagepath."".$MP3path."/".trim($file);
					$sonos->AddToQueue("x-file-cifs:".$jinglepath);
					LOGGING("Individual jingle '".trim($file)."' added to Queue", 7);	
				} else {
					LOGGING("Entered jingle '".$file."' for playgong is not valid or nothing has been entered. Please correct your syntax", 3);
					exit;
				}
			} else {
				$jinglepath = $myMessagepath."".$MP3path."/".trim($config['MP3']['file_gong']);
				$sonos->AddToQueue("x-file-cifs:".$jinglepath);
				LOGGING("Standard jingle '".trim($config['MP3']['file_gong'])."' added to Queue", 7);	
			}
		}
		#******************************************************************************************************
		// if batch has been created add all T2S
		$filename = "t2s_batch.txt";
		if ((file_exists($filename)) and (!isset($_GET['playbatch']))){
			$t2s_batch = read_txt_file_to_array($t2s_batch);
			foreach ($t2s_batch as $t2s => $messageid) {
				$sonos->AddToQueue('x-file-cifs:'.$mpath."/".trim($messageid).".mp3");
			}
			LOGGING("Messages from batch has been added to Queue", 7);	
		} else {
			// if no batch has been created add single T2S
			$t2s_file = file_exists($MessageStorepath."".$messageid.".mp3");
			$meid_file = file_exists($MessageStorepath."".$MP3path."/".$messageid.".mp3");
			if (($t2s_file  === true) or ($meid_file  === true))  {
				$sonos->AddToQueue('x-file-cifs:'.$mpath."/".trim($messageid).".mp3");
				LOGGING("T2S '".trim($messageid).".mp3' has been added to Queue", 7);
			} else {
				LOGGING("The file '".trim($messageid).".mp3' does not exist or could not be played. Please check your directory or your T2S settings!", 3);
				exit;
			}
		}
		$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
		$sonos->SetPlayMode('NORMAL');
		LOGGING("Playmode has been set to NORMAL", 7);		
		$sonos->SetTrack($message_pos);
		LOGGING("Message has been set to Position '".$message_pos."' in current Queue", 7);		
		$sonos->SetGroupMute(false);
		LOGGING("Mute for relevant Player(s) has been turned off", 7);		
		try {
			$try_play = $sonos->Play();
			LOGGING("T2S has been passed to Sonos Application", 5);	
			LOGGING("In case the announcement wasn't played please check any Messages appearing in the Sonos App during processing the request.", 5);	
		} catch (Exception $e) {
			LOGGING("The requested T2S message ".trim($messageid).".mp3 could not be played!", 3);
			$notification = array (	"PACKAGE" => $lbpplugindir,
									"NAME" => "Sonos",
									"MESSAGE" => "The requested T2S message could not be played!",
									"SEVERITY" => 3,
									"fullerror" => "the received error: ".$try_play,
									"LOGFILE" => LBPLOGDIR . "/sonos.log"
									);
			notify_ext($notification);
		}
		$abort = false;
		sleep($sleeptimegong); // wait according to config
		while ($sonos->GetTransportInfo()==1) {
			usleep(200000); // check every 200ms
		}
		// if Playlist has more than 998 entries
		if ($save_plist > 998) {
			$sonos->ClearQueue();
			LOGGING("Queue has been cleared", 7);		
			LoadPlaylist("temp_t2s");
			LOGGING("Temporary saved playlist 'temp_t2s' has been loaded back into Queue", 7);		
			DelPlaylist();
			LOGGING("Temporary playlist 'temp_t2s' has been finally deleted", 7);		
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
				LOGGING("T2S batch files has been removed from Queue", 7);		
			} else {
				// If single T2S has been be played
				$sonos->RemoveFromQueue($message_pos);
				LOGGING("T2S has been removed from Queue", 7);		
			}
			if(isset($_GET['playgong'])) {		
			#if(isset($_GET['playgong']) && ($_GET['playgong'] == "yes")) {		
				$sonos->RemoveFromQueue($message_pos);
				LOGGING("Jingle has been removed from Queue", 7);		
			}
		}
		LOGGING("T2S play process has been successful finished", 6);		
		return $actual;
		
		
}


/**
* Function : sendmessage --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendmessage() {
			global $text, $master, $messageid, $logging, $textstring, $voice, $config, $actual, $player, $volume, $sonos, $coord, $time_start, $filename, $sonoszone, $tmp_batch, $mode, $MP3path;
			
			$time_start = microtime(true);
			if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
				LOGGING("There is no T2S engine/language selected in Plugin config. Please select before using T2S functionality.", 3);
				exit();
			}
			#if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])))  {
			#	LOGGING("Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' is necessary to play an anouncement", 3);
			#	exit;
			#}
			// if batch has been choosed save filenames to a txt file and exit
			if(isset($_GET['batch'])) {
				if((isset($_GET['volume'])) or (isset($_GET['rampto'])) or (isset($_GET['playmode']))) {
					LOGGING("The parameter volume, rampto and playmode are not allowed to be used in conjunction with batch. Please remove from syntax!", 4);
					exit;
				}
				create_tts();
				// creates file to store T2S filenames
				$filenamebatch = "t2s_batch.txt";
				$file = fopen($filenamebatch, "a+");
				if($file == false ) {
					LOGGING("There is no T2S batch file to be written!", 3);
					exit();
				}
				if (strlen($messageid) == '32') {
					fwrite($file, "$filename\n" );
					LOGGING("T2S '".$filename.".mp3' has been added to batch", 7);
					LOGGING("Please ensure to call later '...action=playbatch', otherwise the messages could be played uncontrolled", 5);					
				} else {
					$mp3_path = $MP3path;
					fwrite($file, "$mp3_path/$messageid\n" );
					LOGGING("Messageid '".$messageid."' has been added to batch", 7);
					LOGGING("Please ensure to call later '...action=playbatch', otherwise the messages could be played uncontrolled", 5);										
				}
				fclose($file);
				exit;
			}
			if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
				$volume = $_GET['volume'];
				LOGGING("Volume from syntax been adopted", 7);		
			} else 	{
				// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
				$volume = $config['sonoszonen'][$master][3];
				LOGGING("Standard Volume from zone ".$master."  been used", 7);		
			}
			checkaddon();
			checkTTSkeys();
			$save = saveZonesStatus(); // saves all Zones Status
			SetVolumeModeConnect($mode = '0', $master);
			$return = getZoneStatus($master); // get current Zone Status (Single, Member or Master)
			if($return == 'member') {
				if(isset($_GET['sonos'])) { // check if Zone is Group Member, then abort
					LOGGING("The specified zone is part of a group! There are no information available.", 4);
				exit;
				}
			}
			create_tts();
			// stop 1st before Song Name been played
			$test = $sonos->GetPositionInfo();
			if (($return == 'master') or ($return == 'member')) {
				$sonos->BecomeCoordinatorOfStandaloneGroup();  // in case Member or Master then remove Zone from Group
				LOGGING("Zone ".$master." has been removed from group", 6);		
			}
			if (substr($test['TrackURI'], 0, 18) == "x-sonos-htastream:") {
				$sonos->SetQueue("x-rincon-queue:". $sonoszone[$master][1] ."#0");
				LOGGING("Streaming/TV endet successful", 7);		
			}
			#if ((substr($test, 0, 18) !== "x-sonos-htastream:") and (!isset($_GET['sonos'])))  {
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
				usleep(200000);
			}
			// get Coordinator of (maybe) pair or single player
			$coord = getRoomCoordinator($master);
			LOGGING("Room Coordinator has been identified", 7);		
			$sonos = new PHPSonos($coord[0]); 
			$sonos->SetMute(false);
			$sonos->SetVolume($volume);
			play_tts($messageid);
			restoreSingleZone();
			$mode = "";
			$actual[$master]['CONNECT'] == 'true' ? $mode = '1' : $mode = '0';
			SetVolumeModeConnect($mode, $master);
			delmp3();
			$time_end = microtime(true);
			$t2s_time = $time_end - $time_start;
			#echo "Die T2S dauerte ".round($t2s_time, 2)." Sekunden.\n";
			LOGGING("Deletion of no longer needed MP3 files has been executed", 7);		
			LOGGING("The requested single T2S tooks ".round($t2s_time, 2)." seconds to be processed.", 5);		
	}

/**
* Function : sendgroupmessage --> translate a text into speech for a group of zones
*
* @param: Text or messageid (Number)
* @return: 
**/
			
function sendgroupmessage() {			
			global $coord, $sonos, $text, $sonoszone, $member, $master, $zone, $messageid, $logging, $textstring, $voice, $config, $mute, $membermaster, $getgroup, $checkgroup, $time_start, $mode, $modeback, $actual;
			
			$time_start = microtime(true);
			if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
				LOGGING("There is no T2S engine/language selected in Plugin config. Please select before using T2S functionality.", 3);
				exit();
			}
			#if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])))  {
			#	LOGGING("Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' is necessary to play an anouncement", 3);
			#	exit;
			#}		
			if(isset($_GET['batch'])) {
				LOGGING("The parameter batch is not allowed to be used in groups. Please use single message to prepare your batch!", 4);
				exit;
			}
			if(isset($_GET['volume']) or isset($_GET['groupvolume']))  { 
				isset($_GET['volume']) ? $groupvolume = $_GET['volume'] : $groupvolume = $_GET['groupvolume'];
				if ((!is_numeric($groupvolume)) or ($groupvolume < 0) or ($groupvolume > 200)) {
					LOGGING("The entered volume of ".$groupvolume." must be even numeric or between 0 and 200! Please correct", 4);	
				}
			}
			if(isset($_GET['sonos'])) {
				LOGGING("The parameter 'sonos' couldn't be used for group T2S!", 4);
				exit;
			}
			checkaddon();
			checkTTSkeys();
			$master = $_GET['zone'];
			$member = $_GET['member'];
			create_tts();
			// if parameter 'all' has been entered all zones were grouped
			if($member === 'all') {
				$member = array();
				foreach ($sonoszone as $zone => $ip) {
					// exclude master Zone
					if ($zone != $master) {
						array_push($member, $zone);
						LOGGING("All zones has been grouped", 5);		
					}
				}
			} else {
				$member = explode(',', $member);
			}
			if (in_array($master, $member)) {
				LOGGING("The zone ".$master." could not be entered as member again. Please remove from Syntax '&member=".$master."' !", 3);
				exit;
			}
			// prüft alle Member ob Sie Online sind und löscht ggf. Member falls nicht Online
			#checkZonesOnline($member);
			$coord = getRoomCoordinator($master);
			LOGGING("Room Coordinator has been identified", 7);		
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
			LOGGING("Group Coordinator has been made to single zone", 7);		
			// grouping
			foreach ($member as $zone) {
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				if ($zone != $master) {
					$sonos->SetAVTransportURI("x-rincon:" . $masterrincon); 
					LOGGING("Member '$zone' is now connected to Master Zone", 7);		
				}
			}
			#sleep($config['TTS']['sleepgroupmessage']); // warten gemäß config.php bis Gruppierung abgeschlossen ist
			$sonos = new PHPSonos($coord[0]);
			$sonos->SetPlayMode('NORMAL'); 
			$sonos->SetQueue("x-rincon-queue:". $coord[1] ."#0");
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
			}
			#create_tts();
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
						$volumegroup = "Individual Volume per Player of the group has been set";	
					} else {
						$newvolume = $sonos->GetVolume();
						$final_vol = $newvolume + ($newvolume * ($groupvolume / 100));  // multiplizieren
						// prüfen ob errechnete Volume > 100 ist, falls ja max. auf 100 setzen
						$final_vol > 100 ? $final_vol = 100 : $final_vol;
						$volumegroup = "Individual Volume per Player of the group has been set";
					}
				} else {
					$final_vol = $sonoszone[$zone2][3];
					$volumegroup = "Standard Volume from config for all Zone Members of group has been set";
				}
				$sonos->SetVolume($final_vol);
				$sonos->SetMute(false);
			}
			LOGGING("$volumegroup", 7);		
			play_tts($messageid);
			// wiederherstellen der Ursprungszustände
			restoreGroupZone();
			LOGGING("Restore previous settings will be called", 7);		
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
			delmp3();
			$time_end = microtime(true);
			$t2s_time = $time_end - $time_start;
			#echo "Die T2S dauerte ".round($t2s_time, 2)." Sekunden.\n";
			LOGGING("Deletion of no longer needed MP3 files has been executed", 7);		
			LOGGING("The requested group T2S tooks ".round($t2s_time, 2)." seconds to be processed.", 5);					
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
		LOGGING("There is no T2S batch file to be played!", 4);
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
	global $master, $sonoszone, $config, $volume, $sonos, $coord, $messageid, $filename, $MessageStorepath;
	require_once("addon/sonos-to-speech.php");
	
	// if batch has been choosed abort
	if(isset($_GET['batch'])) {
		LOGGING("The parameter batch could not be used to announce the radio station!", 4);
		exit;
	}
	$sonos->Stop();
	if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
		$volume = $_GET['volume'];
		LOGGING("Volume from syntax been used", 7);		
	} else 	{
		// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
		$volume = $config['sonoszonen'][$master][3];
		LOGGING("Standard Volume from config been used", 7);		
	}
	saveZonesStatus(); // saves all Zones Status
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$temp_radio = $sonos->GetMediaInfo();
	# Generiert und kodiert Ansage des laufenden Senders
	$text = ('Radio '.$temp_radio['title']);
	$textstring = ($text);
	$rawtext = md5($textstring);
	$filename = "$rawtext";
	$messageid = $filename;
	select_t2s_engine();
	t2s($messageid, $MessageStorepath, $textstring, $filename);
	// get Coordinator of (maybe) pair or single player
	$coord = getRoomCoordinator($master);
	LOGGING("Room Coordinator been identified", 7);		
	$sonos = new PHPSonos($coord[0]); 
	$sonos->SetMute(false);
	$sonos->SetVolume($volume);
	LOGGING("Radio Station Announcement has been played", 6);		
	play_tts($messageid);
	restoreSingleZone();
}

?>