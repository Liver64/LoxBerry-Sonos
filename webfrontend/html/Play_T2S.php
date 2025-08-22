<?php

/**
* Submodul: Play_T2S
*
**/

require_once "loxberry_system.php";

$lbhostname = lbhostname();
$lbwebport = lbwebserverport();
$myLBip = LBSystem::get_localip();


/**
* New Function for T2S: say --> replacement/enhancement for sendmessage/sendgroupmessage
*
* @param: empty
* @return: nothing
**/

function say() {
	
	global $sonos, $profile, $master, $lbpconfigdir, $config, $vol_config, $group, $textstring, $result;
	
	presence_detection();
	if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
		LOGGING("play_t2s.php: There is no T2S engine/language selected in Plugin config. Please select before using T2S functionality.", 3);
		exit();
	}
	if(isset($_GET['profile']) and isset($_GET['member']))  {
		$tocall = "Error!! Both parameters where entered";
		LOGGING("play_t2s.php: Parameter 'member' and 'profile' could not be used in conjunction! Please correct your syntax/URL", 3);
		exit();
	}
	check_S1_player();
	if(isset($_GET['ic']))    {
		$ic = true;
		$textstring = "Hallo Oliver";
		require_once("bin/interface.php");
		$result = send_data_curl();
		LOGGING("play_t2s.php: T2S Interface has been called");
	}
	$profile = false;
	if(!isset($_GET['member']) && !isset($_GET['profile'])) {
		if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])) && (!isset($errortext)) && (!isset($_GET['sonos'])) &&
			(!isset($_GET['text'])) && (!isset($_GET['weather'])) && (!isset($_GET['abfall'])) && (!isset($_GET['pollen'])) && (!isset($_GET['warning'])) &&
			(!isset($_GET['distance'])) && (!isset($_GET['clock'])) && 
			(!isset($_GET['calendar']))) {
			$tocall = "Error!! Data/Input is missing";
			LOGGING("play_t2s.php: Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' in combination with &clip are necessary to play an anouncement. (check Wiki)", 3);	
			exit;
		}
		if(isset($_GET['clip']))  {
			#check_S1_player();
			$tocall = "Single Clip";
			LOGGING("play_t2s.php: 'Single Clip' has been identified", 6);	
			sendAudioSingleClip();
		} else {
			$tocall = "Single T2S";
			LOGGING("play_t2s.php: 'Single T2S' has been identified", 6);
			sendmessage();			
		}
	} else {
		if(isset($_GET['clip']) and !isset($_GET['profile']) and isset($_GET['member']))  {
			$tocall = "Multi Clip Member";
			LOGGING("play_t2s.php: 'Multi Clip Member' has been identified", 6);
			$profile = true;
			sendAudioMultiClip();
		} elseif(isset($_GET['clip']) and isset($_GET['profile']) and !isset($_GET['member']))  {
			$group = checkGroupProfile();
			if ($group == true)   {
				$tocall = "Multi Clip Profile";
				LOGGING("play_t2s.php: 'Multi Clip Profile' has been identified", 6);
				#createArrayFromGroupProfile();					
				sendAudioMultiClip();
			} else {
				$tocall = "Single Clip Profile";
				LOGGING("play_t2s.php: 'Single Clip Profile' has been identified", 6);	
				sendAudioSingleClip();
			}
		} elseif(!isset($_GET['clip']) and isset($_GET['member']) and !isset($_GET['profile'])) {
			$tocall = "Group T2S";
			LOGGING("play_t2s.php: 'Group T2S' has been identified", 6);	
			$profile = true;
			sendgroupmessage();
		} elseif(!isset($_GET['clip']) and !isset($_GET['member']) and isset($_GET['profile'])) {
			$group = checkGroupProfile();
			if ($group == true)   {
				$tocall = "Group T2S Profile";
				LOGGING("play_t2s.php: 'Group T2S Profile' has been identified", 6);
				$profile = true;	
				sendgroupmessage();
			} else {
				$tocall = "Single T2S Profile";
				LOGGING("play_t2s.php: 'Single T2S Profile' has been identified", 6);
				createArrayFromGroupProfile();				
				sendmessage();
			}
		}
	}
	echo "Profile '$tocall' has been identified";
}


/**
* Function : playAudioClip --> playing T2S or file 
*
* @param: T2S or messageid (Number)
* @return: 
**/

function playAudioClip() {

	global $config, $prio, $profile_details, $memberon, $master, $lbpconfigdir, $vol_config, $roomcord, $sonos, $errortext, $time_start, $zones, $source, $messageid, $playstat, $filename;

	if (isset($_GET['profile'])) {
		get_profile_details();
		if ($profile_details[0]['Group'] == "Group")   {
			$source = "multi";
		} else {
			#createArrayFromGroupProfile();
			$source = "Single";
		}
	}
	if (isset($_GET['member']) and !isset($_GET['profile'])) {
		$source = "multi";
	} 
	if (!isset($_GET['member'])and !isset($_GET['profile'])) {
		$source = "single";
	}
	# check if filename is there or T2S generated and saved
	check_file();
	# call prio to set for clip
	handle_prio();
	# call playgong check upfront
	handle_playgong($zones, $source);
	# call messageid/T2S announcement
	handle_message($zones, $source);
}



/**
* Function : handle_prio() --> get prio for clip from URL 
*
* @param: 
* @return: 
**/

function handle_prio() {
	
	global $prio, $time_start;
	
	if(isset($_GET['high']) and isset($_GET['clip'])) {
		$prio = "HIGH";
		LOGDEB("play_t2s.php: Audioclip: Priority for Notification has been set to HIGH");
	} else {
		$prio = "LOW";
		LOGDEB("play_t2s.php: Audioclip: Standard Priority LOW for Notification will be used ");
	}
	return $prio;
}


/**
* Function : check_file() --> check if filename is available 
*
* @param: 
* @return: 
**/

function check_file() {
	
	global $sonos, $config, $filename, $messageid, $time_start;
	
	# pre check for MP3 Stream
	if (isset($_GET['messageid']))  {
		$messageid = $_GET['messageid'];
		$filenamecheck = $config['SYSTEM']['ttspath']."/mp3/".$messageid.".mp3";
	} else {
		$filenamecheck = $config['SYSTEM']['ttspath']."/".$filename.".mp3";
	}

	# check if T2S has been successful created, if not wait until finished
	while (!file_exists($filenamecheck) and !filesize($filenamecheck)):
		LOGDEB("play_t2s.php: Audioclip: Notification creation not yet finished, we have to wait...");
		usleep(200000); //check every 200ms
	endwhile;
	return;
}	
	
	
	
/**
* Function : create_tts --> creates an MP3 File based on Text Input
*
* @param: 	Text of Messasge ID
* @return: 	MP3 File
**/		

function create_tts($text ='') {
	global $sonos, $config, $dist, $filename, $MessageStorepath, $errortext, $zones, $messageid, $textstring, $home, $time_start, $tmp_batch, $MP3path, $filenameplay, $textstring, $volume, $tts_stat;
	
	# setze 1 für virtuellen Texteingang (T2S Start)
	$tts_stat = 1;
	if(!isset($_GET['clip'])) {
		send_tts_source($tts_stat);
	}
	if (isset($_GET['greet']))  {
		$Stunden = intval(strftime("%H"));
		$TL = LOAD_T2S_TEXT();
		switch ($Stunden) {
			# Gruß von 04:00 bis 10:00h
			case $Stunden >=4 && $Stunden <10:
				$greet = $TL['GREETINGS']['MORNING_'.mt_rand (1, 5)];
			break;
			# Gruß von 10:00 bis 17:00h
			case $Stunden >=10 && $Stunden <17:
				$greet = $TL['GREETINGS']['DAY_'.mt_rand (1, 5)];
			break;
			# Gruß von 17:00 bis 22:00h
			case $Stunden >=17 && $Stunden <22:
				$greet = $TL['GREETINGS']['EVENING_'.mt_rand (1, 5)];
			break;
			# Gruß nach 22:00h
			case $Stunden >=22:
				$greet = $TL['GREETINGS']['NIGHT_'.mt_rand (1, 5)];
			break;
			default:
				$greet = "";
			break;
		}
	} else {
		$greet = "";
	}
	// messageid has been entered so skip rest and start with play
	if (isset($_GET['messageid'])) {
		$messageid = $_GET['messageid'];
		if (file_exists($config['SYSTEM']['mp3path']."/".$messageid.".mp3") === true)  {
			LOGGING("play_t2s.php: Messageid '".$messageid."' has been entered", 7);
		} else {
			LOGGING("play_t2s.php: The corrosponding messageid file '".$messageid.".mp3' does not exist or could not be played. Please check your directory or syntax!", 3);
			exit;
		}	
		return;
	}
	$rampsleep = $config['TTS']['rampto'];
	if (isset($_GET['text']))   {
		$text = $_GET['text'];
	} elseif ($text <> '') {
		$text;
	} else {
		$text = '';
	}	

	if(isset($_GET['weather'])) {
		// calls the weather-to-speech Function
		if(isset($_GET['lang']) and $_GET['lang'] == "nb-NO" or @$_GET['voice'] == "Liv") {
			include_once("addon/weather-to-speech_no.php");
		} else {
			include_once("addon/weather-to-speech.php");
		}
		$textstring = substr(w2s(), 0, 500);
		LOGGING("play_t2s.php: weather-to-speech plugin has been called", 7);
		} 
	elseif (isset($_GET['clock'])) {
		// calls the clock-to-speech Function
		include_once("addon/clock-to-speech.php");
		$textstring = c2s();
		LOGGING("play_t2s.php: clock-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['pollen'])) {
		// calls the pollen-to-speech Function
		include_once("addon/pollen-to-speach.php");
		$textstring = substr(p2s(), 0, 500);
		LOGGING("play_t2s.php: pollen-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['warning'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/weather-warning-to-speech.php");
		$textstring = substr(ww2s(), 0, 500);
		LOGGING("play_t2s.php: weather warning-to-speech plugin has been called", 7);
	}
	elseif (isset($_GET['distance'])) {
		// calls the time-to-destination-speech Function
		include_once("addon/time-to-destination-speech.php");
		$textstring = substr(tt2t(), 0, 500);
		LOGGING("play_t2s.php: time-to-distance speech plugin has been called", 7);
		}
	elseif (isset($_GET['abfall'])) {
		// calls the wastecalendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$textstring = substr(muellkalender(), 0, 500);
		LOGGING("play_t2s.php: waste calendar-to-speech  plugin has been called", 7);
		}
	elseif (isset($_GET['calendar'])) {
		// calls the calendar-to-speech Function
		include_once("addon/waste-calendar-to-speech.php");
		$textstring = substr(calendar(), 0, 500);
		LOGGING("play_t2s.php: calendar-to-speech plugin has been called", 7);
		}
	elseif (isset($_GET['sonos'])) {
		// calls the sonos-to-speech Function
		include_once("addon/sonos-to-speech.php");
		$textstring = s2s();
		$rampsleep = false;
		LOGGING("play_t2s.php: sonos-to-speech plugin has been called", 7);
		}
	elseif ((!isset($_GET['text'])) and (isset($_GET['playbatch']))) {
		LOGGING("play_t2s.php: no text has been entered", 3);
		exit();
		}
	elseif ($text <> '') {
		// prepares the T2S message
		if (empty($greet))  {
			$textstring = $text;
			LOGGING("play_t2s.php: Textstring has been entered", 7);	
		} else {
			$textstring = $greet.". ".$text;
			LOGGING("play_t2s.php: Greeting + Textstring has been entered", 7);		
		}	
	}	
	
	// encrypt MP3 file as MD5 Hash
	$filename  = md5($textstring);
	#echo 'textstring: '.$textstring.'<br>';
	// calls the various T2S engines depending on config)
	#echo "Errortext: ".$errortext;
	if ($textstring != '') {
		if (!empty($errortext)) {
			include_once("voice_engines/GoogleCloud.php");
		}
		if ($config['TTS']['t2s_engine'] == 1001 and empty($errortext)) {
			include_once("voice_engines/VoiceRSS.php");
		}
		if ($config['TTS']['t2s_engine'] == 3001 and empty($errortext)) {
			include_once("voice_engines/MAC_OSX.php");	
		}
		if ($config['TTS']['t2s_engine'] == 6001 and empty($errortext)) {
			include_once("voice_engines/ResponsiveVoice.php");
		}
		if ($config['TTS']['t2s_engine'] == 7001 and empty($errortext)) {
			include_once("voice_engines/Google.php");	
		}
		if ($config['TTS']['t2s_engine'] == 5001 and empty($errortext)) {
			include_once("voice_engines/Pico_tts.php");	
		}
		if ($config['TTS']['t2s_engine'] == 4001 and empty($errortext)) {
			include_once("voice_engines/Polly.php");	
		}
		if ($config['TTS']['t2s_engine'] == 8001 and empty($errortext)) {
			include_once("voice_engines/GoogleCloud.php");	
		}
		if ($config['TTS']['t2s_engine'] == 9001 and empty($errortext)) {
			include_once("voice_engines/MS_Azure.php");	
		}
		if ($config['TTS']['t2s_engine'] == 9011 and empty($errortext)) {
			include_once("voice_engines/ElevenLabs.php");	
		}
		if ($config['TTS']['t2s_engine'] == 9012 and empty($errortext)) {
			include_once("voice_engines/Piper.php");	
		}
		#echo filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3");
	
	if(file_exists($config['SYSTEM']['ttspath']."/".$filename.".mp3") && empty($_GET['nocache'])) {
		LOGGING("play_t2s.php: MP3 grabbed from cache: '$textstring' ", 6);
	} elseif(file_exists($config['SYSTEM']['ttspath']."/".$filename.".wav") && empty($_GET['nocache'])) {
		LOGGING("play_t2s.php: WAV grabbed from cache: '$textstring' ", 6);
	} else {
		t2s($textstring, $filename);
		#sleep(5);
		#if (($config['TTS']['t2s_engine'] == 6001) or ($config['TTS']['t2s_engine'] == 7001) or ($config['TTS']['t2s_engine'] == 4001) or ($config['TTS']['t2s_engine'] == 8001))    {
			// ** generiere MP3 ID3 Tags **
			#require_once("system/bin/getid3/getid3.php");
			#$getID3 = new getID3;
			#write_MP3_IDTag($textstring);
		#}
		
		// check if MP3 filename is < 1 Byte
		if(file_exists($config['SYSTEM']['ttspath']."/".$filename.".mp3"))   {
			if (filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3") < 1)  {
				$heute = date("Y-m-d"); 
				$time = date("His"); 
				if (is_enabled($config['SYSTEM']['checkt2s']))  {
					rename($config['SYSTEM']['ttspath']."/".$filename.".mp3", $config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");
					LOGERR("play_t2s.php: Something went wrong :-( the message has not been saved. The bad file has been renamed to: ".$config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");	
					LOGERR("play_t2s.php: Please check...");
					LOGERR("play_t2s.php: ...your internet connection");	
					LOGERR("play_t2s.php: ...your storage device");	
					LOGERR("play_t2s.php: ...your T2S Engine settings");	
					LOGERR("play_t2s.php: Please try your requested URL in a browser or change temporally the T2S provider.");	
					LOGINF("play_t2s.php: If no success at all please add a thread in Loxone Forum");	
					$filename = "t2s_not_available";
					copy($config['SYSTEM']['mp3path']."/t2s_not_available.mp3", $config['SYSTEM']['ttspath']."/t2s_not_available.mp3");
					//@unlink($config['SYSTEM']['ttspath'] ."/". $filename . ".mp3");
				} else {
					rename($config['SYSTEM']['ttspath']."/".$filename.".mp3", $config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");
					LOGERR("play_t2s.php: Something went wrong :-( the message has not been saved. The bad file has been renamed to: ".$config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");	
					LOGERR("play_t2s.php: Please check...");
					LOGERR("play_t2s.php: ...your internet connection");	
					LOGERR("play_t2s.php: ...your storage device");	
					LOGERR("play_t2s.php: ...your T2S Engine settings");	
					LOGERR("play_t2s.php: Please try your requested URL in a browser or change temporally the T2S provider.");	
					LOGINF("play_t2s.php: If no success at all please add a thread in Loxone Forum");	
					//@unlink($config['SYSTEM']['ttspath'] ."/". $filename . ".mp3");
					exit;
				}
			}
		}
			

		
		echo $textstring;
		echo "<br>";
		
	}
	return $filename;
	}
}


/**
* Function : play_tts --> play T2S or MP3 File
*
* @param: 	MessageID, Parameter zur Unterscheidung ob Gruppen oder EInzeldurchsage
* @return: empty
**/		

function play_tts($filename) {
	global $volume, $config, $dist, $messageid, $sonos, $text, $errortext, $lbphtmldir, $messageid, $sleeptimegong, $sonoszone, $sonoszonen, $master, $coord, $actual, $textstring, $zones, $time_start, $t2s_batch, $filename, $textstring, $home, $MP3path, $lbpplugindir, $logpath, $try_play, $MessageStorepath, $filename, $tts_stat;
		
		if (defined('T2SMASTER'))   {
			$master = T2SMASTER;
		}
		
		$coord = getRoomCoordinator($master);
		$sonos = new SonosAccess($coord[0]);
		if (isset($_GET['messageid'])) {
			// Set path if messageid
			LOGGING("play_t2s.php: Path for messageid's been adopted", 7);
			$messageid = $_GET['messageid'];
		} else {
			// Set path if T2S
			LOGGING("play_t2s.php: Path for T2S been adopted", 7);	
		}
		// if BEAM etc. is in Modus TV switch to Playlist 1st
		if (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")  {  
			$sonos->SetQueue("x-rincon-queue:".$coord[1]."#0");
			LOGGING("play_t2s.php: TV was playing", 7);		
		}
		// Playlist is playing
		$save_plist = count($sonos->GetCurrentPlaylist());
		
		// if Playlist has more then 998 entries
		if ($save_plist > 998) {
			// save temporally playlist
			SavePlaylist();
			$sonos->ClearQueue();
			LOGGING("play_t2s.php: Queue has been cleared", 7);		
			$message_pos = 1;
			LOGGING("play_t2s.php: Playlist has more then 998 songs", 6);		
		}
		// if Playlist has more then 1 or less then 999 entries
		if ($save_plist >= 1 && $save_plist <= 998) {
			$message_pos = count($sonos->GetCurrentPlaylist()) + 1;
		} else {
			// No Playlist is playing
			$message_pos = 1;
		}
			
		// Playgong/jingle to be played upfront
		#*****************************************************************************************************
		if(isset($_GET['playgong'])) {
			if ($_GET['playgong'] == 'no')	{
				LOGGING("play_t2s.php: 'playgong=no' could not be used in syntax, only 'playgong=yes' or 'playgong=file' are allowed", 3);
				exit;
			}
			if(empty($config['MP3']['file_gong'])) {
				LOGGING("play_t2s.php: Standard file for jingle is missing in Plugin config. Please maintain before usage.", 3);
				exit;	
			}
			if (($_GET['playgong'] != "yes") and ($_GET['playgong'] != "no") and ($_GET['playgong'] != " ")) {
				$file = $_GET['playgong'];
				$file = $file.'.mp3';
				$valid = mp3_files($file);
				if ($valid === true) {
					$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($file);
					$sonos->AddToQueue($jinglepath);
					LOGGING("play_t2s.php: Individual jingle '".trim($file)."' added to Queue", 7);	
				} else {
					LOGGING("play_t2s.php: Entered jingle '".$file."' for playgong is not valid or nothing has been entered. Please correct your syntax", 3);
					exit;
				}
			} else {
				$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($config['MP3']['file_gong']);
				$sonos->AddToQueue($jinglepath);
				LOGGING("play_t2s.php: Standard jingle '".trim($config['MP3']['file_gong'])."' added to Queue", 7);	
			}
		}
		#******************************************************************************************************
		// if batch has been created add all T2S
		$filenamebatch = "t2s_batch.txt";
		if ((file_exists($filenamebatch)) and (!isset($_GET['playbatch']))){
			$t2s_batch = file($filenamebatch, FILE_IGNORE_NEW_LINES);
			foreach ($t2s_batch as $t2s => $t2s_value) {
				$sonos->AddToQueue($t2s_value.".mp3");
			}
			LOGGING("play_t2s.php: Messages from batch has been added to Queue", 7);	
		} else {
			// if no batch has been created add single T2S
			$t2s_file = file_exists($config['SYSTEM']['ttspath']."/".$filename.".mp3");
			$meid_file = file_exists($config['SYSTEM']['mp3path']."/".$messageid.".mp3");
			if (($t2s_file  === true) or ($meid_file  === true))  {
				if ($t2s_file  === true)  {
					# check if T2S has been saved/coded correctly
					if (filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3") < 1)  {
						$heute = date("Y-m-d"); 
						$time = date("His"); 
						#echo $config['SYSTEM']['checkt2s'];
						if (is_enabled($config['SYSTEM']['checkt2s']))  {
							rename($config['SYSTEM']['ttspath']."/".$filename.".mp3", $config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");
							LOGERR("play_t2s.php: Something went wrong :-( the message has not been saved. The bad file has been renamed to: ".$config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");	
							LOGERR("play_t2s.php: Please check...");
							LOGERR("play_t2s.php: ...your internet connection");	
							LOGERR("play_t2s.php: ...your storage device");	
							LOGERR("play_t2s.php: ...your T2S Engine settings");	
							LOGERR("play_t2s.php: Please try your requested URL in a browser or change temporally the T2S provider.");	
							LOGINF("play_t2s.php: If no success at all please add a thread in Loxone Forum");	
							LOGOK("play_t2s.php: Exception message has been announced!");	
							$filename = "t2s_not_available";
							copy($config['SYSTEM']['mp3path']."/t2s_not_available.mp3", $config['SYSTEM']['ttspath']."/t2s_not_available.mp3");
						} else {
							rename($config['SYSTEM']['ttspath']."/".$filename.".mp3", $config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");
							LOGERR("play_t2s.php: Something went wrong :-( the message has not been saved. The bad file has been renamed to: ".$config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");	
							LOGERR("play_t2s.php: Please check...");
							LOGERR("play_t2s.php: ...your internet connection");	
							LOGERR("play_t2s.php: ...your storage device");	
							LOGERR("play_t2s.php: ...your T2S Engine settings");	
							LOGERR("play_t2s.php: Please try your requested URL in a browser or change temporally the T2S provider.");	
							LOGINF("play_t2s.php: If no success at all please add a thread in Loxone Forum");	
							exit;
						}						
					}
					$sonos->AddToQueue($config['SYSTEM']['cifsinterface']."/".$filename.".mp3");
					LOGGING("play_t2s.php: T2S '".trim($filename).".mp3' has been added to Queue", 7);
				} else {
					$sonos->AddToQueue($config['SYSTEM']['cifsinterface']."/mp3/".$messageid.".mp3");
					LOGGING("play_t2s.php: MP3 File '".trim($messageid).".mp3' has been added to Queue", 7);
					$filename = $messageid;
				}
			} else {
				LOGGING("play_t2s.php: The file '".trim($filename).".mp3' does not exist or could not be played. Please check your directory or your T2S settings!", 3);
				exit;
			}
		}
		$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
		$sonos->SetPlayMode('0');
		LOGGING("play_t2s.php: Playmode has been set to NORMAL", 7);		
		$sonos->SetTrack($message_pos);
		LOGGING("play_t2s.php: Message has been set to Position '".$message_pos."' in current Queue", 7);		
		$sonos->SetMute(false);
		if(!isset($_GET['member']) && !isset($_GET['profile'])) {
			$sonos->SetVolume($volume);
		}
		LOGGING("play_t2s.php: Mute for relevant Player(s) has been turned off", 7);		
		try {
			$try_play = $sonos->Play();
			LOGGING("play_t2s.php: T2S has been passed to Sonos Application", 5);	
			LOGGING("play_t2s.php: In case the announcement wasn't played please check any Messages appearing in the Sonos App during processing the request.", 5);	
		} catch (Exception $e) {
			LOGGING("play_t2s.php: The requested T2S message ".trim($messageid).".mp3 could not be played!", 3);
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
		$sleeptimegong = "3";
		sleep($sleeptimegong); // wait according to config
		while ($sonos->GetTransportInfo() == 1) {
			usleep(200000); // check every 200ms
		}
		// If batch T2S has been be played
		if (!empty($t2s_batch))  {
			$i = $message_pos;
			foreach ($t2s_batch as $t2s => $value) {
				$mess_pos = $message_pos;
				$sonos->RemoveFromQueue($mess_pos);
				$i++;
			} 
			unlink ($filenamebatch);
			LOGGING("play_t2s.php: T2S batch files has been removed from Queue", 7);	
		} else {
			// If single T2S has been be played
			$sonos->RemoveFromQueue($message_pos);
			LOGGING("play_t2s.php: T2S has been removed from Queue", 7);	
			if(isset($_GET['playgong'])) {		
				$sonos->RemoveFromQueue($message_pos);
				LOGGING("play_t2s.php: Jingle has been removed from Queue", 7);	
			}	
		}	
		
		// if Playlist has more than 998 entries
		if ($save_plist > 998) {
			$sonos->ClearQueue();
			LOGGING("play_t2s.php: Queue has been cleared", 7);		
			LoadPlaylist("temp_t2s");
			LOGGING("play_t2s.php: Temporary saved playlist 'temp_t2s' has been loaded back into Queue", 7);		
			DelPlaylist();
			LOGGING("play_t2s.php: Temporary playlist 'temp_t2s' has been finally deleted", 7);		
		// if Playlist has less than or equal 998 entries
		}
		#exit;
		$time_end = microtime(true);
		$t2s_time = $time_end - $time_start;
		#echo "Die T2S dauerte ".round($t2s_time, 2)." Sekunden.\n";
		LOGGING("play_t2s.php: The requested T2S tooks ".round($t2s_time, 2)." seconds to be played.", 5);	
		LOGGING("play_t2s.php: T2S play process has been successful finished", 6);
		return $actual;
		
		
		
}


/**
* Function : sendmessage --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendmessage($errortext = "") {
			global $text, $dist, $master, $messageid, $errortext, $logging, $textstring, $voice, $config, $actual, $zones, $volume, $source, $sonos, $coord, $time_start, $filename, $sonoszone, $sonoszonen, $tmp_batch, $mode, $MP3path, $tts_stat;
			
			presence_detection();
			if(isset($_GET['member'])) {
				sendgroupmessage();
				LOGGING("play_t2s.php: Member has been entered for a single Zone function, we switch to 'sendgroupmessage'. Please correct your syntax!", 4);
			}	
			
			$time_start = microtime(true);
			
			// if batch has been choosed save filenames to a txt file and exit
			if(isset($_GET['batch'])) {
				if((isset($_GET['volume'])) or (isset($_GET['rampto'])) or (isset($_GET['playmode'])) or (isset($_GET['playgong']))) {
					LOGGING("play_t2s.php: The parameter volume, rampto, playmode or playgong are not allowed to be used in conjunction with batch. Please remove from syntax!", 4);
					exit;
				}
				if (isset($_GET['messageid'])) {
					$messageid = $_GET['messageid'];
				} else {
					create_tts();
				}
				// creates file to store T2S filenames
				$filenamebatch = "t2s_batch.txt";
				$file = fopen($filenamebatch, "a+");
				if($file == false ) {
					LOGGING("play_t2s.php: There is no T2S batch file to be written!", 3);
					exit();
				}
				if (strlen($filename) == '32') {
					fwrite($file, $config['SYSTEM']['cifsinterface']."/".$filename."\r\n");
					LOGGING("play_t2s.php: T2S '".$filename.".mp3' has been added to batch", 7);
					LOGGING("play_t2s.php: Please ensure to call later '...action=playbatch', otherwise the messages could be played uncontrolled", 5);					
				} else {
					fwrite($file, $config['SYSTEM']['cifsinterface']."/".$MP3path."/".$messageid."\r\n");
					LOGGING("play_t2s.php: Messageid '".$messageid."' has been added to batch", 7);
					LOGGING("play_t2s.php: Please ensure to call later '...action=playbatch', otherwise the messages could be played uncontrolled", 5);										
				}
				fclose($file);
				exit;
			}
			if (defined('T2SMASTER'))   {
				$master = T2SMASTER;
			}
			$sonos = new SonosAccess($sonoszone[$master][0]); 
			$return = getZoneStatus($master); // get current Zone Status (Single, Member or Master)
			$save = saveZonesStatus(); // saves all Zones Status
			if($return == 'member') {
				if(isset($_GET['sonos'])) { // check if Zone is Group Member, then abort
					LOGGING("play_t2s.php: The specified zone is part of a group! There are no information available.", 4);
				exit;
				}
			}
			create_tts($errortext);
			$sonos = new SonosAccess($sonoszone[$master][0]);
			// stop 1st before Song Name been played
			$test = $sonos->GetPositionInfo();
			if (($return == 'master') or ($return == 'member')) {
				$sonos->BecomeCoordinatorOfStandaloneGroup();  // in case Member or Master then remove Zone from Group
				LOGGING("play_t2s.php: Zone '$master' has been removed from group", 6);		
			}
			
			if (substr($test['TrackURI'], 0, 18) == "x-sonos-htastream:") {
				$sonos->SetQueue("x-rincon-queue:". $sonoszone[$master][1] ."#0");
				LOGGING("play_t2s.php: Streaming/TV end successful", 7);		
			}
			#if ((substr($test, 0, 18) !== "x-sonos-htastream:") and (!isset($_GET['sonos'])))  {
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
				usleep(200000);
			}
			$sonos = new SonosAccess($sonoszone[$master][0]); 
			$sonos->SetMute(false);
			play_tts($messageid);
			restoreSingleZone();
			$time_end = microtime(true);
			$t2s_time = $time_end - $time_start;
			#echo "Die T2S dauerte ".round($t2s_time, 2)." Sekunden.\n";
			proccessing_time();
	}
	
/**
* Function : sendAudioSingleClip --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendAudioSingleClip($errortext = "") {
	
	global $config, $volume, $master, $filename, $messageid, $sonoszone, $sonos, $zones, $playstat, $time_start, $roomcord;
	
	$time_start = microtime(true);
	
	if(isset($_GET['member'])) {
		$zones = $sonoszone[$master];
	} elseif (isset($_GET['profile'])) {
		$tmp = createArrayFromGroupProfile();
		$zones = $sonoszone[$tmp[0]];
		$master = $tmp[0];
	} else {
		$zones = $sonoszone[$master];
	}
	# determine if Player supports AUDIO_CLIP function
	if(isset($zones[11]) and $zones[11] == true and $zones[9] <> "1") {
		LOGDEB("play_t2s.php: Audioclip: Player '". $master ."' does support Audio Clip.");
	} else {
		LOGERR("play_t2s.php: Audioclip: Player '". $master ."' does not support Audio Clip! Please remove player from URL (zone=". $master ."&action= ....) or from Sound Profile");
		exit;
	}
	create_tts($errortext);
	playAudioClip();
		
	$time_end = microtime(true);
	$t2s_time = $time_end - $time_start;
	LOGGING("play_t2s.php: Audioclip: The requested Notification tooks ".round($t2s_time, 2)." seconds to be processed.", 5);	
	}
	
	
	
/**
* Function : sendAudioMultiClip --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendAudioMultiClip($errortext = "") {
	
	global $config, $volume, $master, $filename, $messageid, $sonoszone, $sonos, $time_start, $zones, $playstat, $roomcord, $profile_details, $zones_all;
	
	$time_start = microtime(true);
	
	LOGDEB("play_t2s.php: Audioclip: Notification for Player has been called.");
	
	$zones = array();
	$tmp_zones = array();
	if (isset($_GET['member']) and !isset($_GET['paused'])) {
		$zones_all = $_GET['member'];
		$zones = array_merge($zones, audioclip_handle_members($zones_all));
		$zones = array_keys($zones);
		$r = implode(',', $zones);
		LOGGING("play_t2s.php: Audioclip: Players ".$r." for audioclip retrieved from URL", 7);
	} elseif (isset($_GET['profile']) and !isset($_GET['paused']))   {
		$zones = createArrayFromGroupProfile();	
		$r = implode(',', $zones);
		LOGGING("play_t2s.php: Audioclip: Players ".$r." for audioclip retrieved from Profile", 7);
	}
	if (isset($_GET['paused']))    {
		$zones = IdentPausedPlayers();
		$zones = array_keys($zones);
		$r = implode(',', $zones);
		LOGGING("play_t2s.php: Audioclip: Players ".$r." for audioclip retrieved from currently not streaming player", 7);
	}
	foreach ($zones as $key)   {
		# determine if Player is fully supported/partial supported  for AUDIO_CLIP
		if(isset($sonoszone[$key][11]) and is_enabled($sonoszone[$key][11]) and $sonoszone[$key][9] <> "1") {
			LOGDEB("play_t2s.php: Audioclip: Player '". $key ."' does support Audio Clip");
			array_push($tmp_zones, $key);
		} else {
			# remove S1 player from clip
			LOGWARN("play_t2s.php: Audioclip: Player '". $key ."' does not support Audio Clip. The Player has been removed by plugin!");
		}
	}
	$zones = $tmp_zones;
	#print_r($zones);
	create_tts($errortext);
	playAudioClip();
	
	proccessing_time();
}
	

/**
* Function : doorbell --> playing file as doorbell
*
* @param: CHIME or messageid (Number)
* @return: 
**/

function doorbell() {

	global $config, $master, $sonos, $sonoszone, $zone_volumes, $time_start;

	if(isset($_GET['playgong'])) {
		LOGERR("play_t2s.php: Audioclip: playgong could not be used im combination with function 'doorbell'");
		exit;
	}

	$time_start = microtime(true);
	$prio = "HIGH";
	$zonesdoor = array();

	if (isset($_GET['member']) and !isset($_GET['paused'])) {
		$zones_all = $_GET['member'];
		$zonesdoor = array_merge($zonesdoor, audioclip_handle_members($zones_all));
		$zones = array_keys($zonesdoor);
		$r = implode(',', $zones);
		LOGGING("play_t2s.php: Audioclip: Players for doorbell ".$r." retrieved from URL", 7);
	} elseif (isset($_GET['profile']) and !isset($_GET['paused']))   {
		$zones = createArrayFromGroupProfile();	
		#print_r($zones);
		$r = implode(',', $zones);
		LOGGING("play_t2s.php: Audioclip: Players for doorbell ".$r." retrieved from Profile", 7);
	} else {
		$zones[0] = MASTER;
	}
	if (isset($_GET['paused']))    {
		$zones = IdentPausedPlayers();
		$zones = array_keys($zones);
		$r = implode(',', $zones);
		LOGGING("play_t2s.php: Audioclip: Players for doorbell ".$r." retrieved from currently not streaming player", 7);
	}
	
	$tmp_zones = array();
	foreach ($zones as $key)   {
		# determine if Player is fully supported/partial supported  for AUDIO_CLIP
		if(isset($sonoszone[$key][11]) and is_enabled($sonoszone[$key][11]) and $sonoszone[$key][9] <> "1")    {
			array_push($tmp_zones, $key);
			LOGDEB("play_t2s.php: Audioclip: Player '$key' does support Audio Clip (Doorbell)");
		} else {
			LOGWARN("play_t2s.php: Audioclip: Player '". $key ."' does not support Audio Clip. The Player has been removed by plugin!");
		}
	}
	$zones = $tmp_zones;
	
	if (isset($_GET['file'])) {
		$file = $_GET['file'];
		$file = $file.'.mp3';
		$valid = mp3_files($file);
		if ($valid === true) {
			$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($file);
			LOGGING("play_t2s.php: Audioclip: Doorbell '".trim($file)."' with Priority HIGH has been announced", 7);	
			audioclip_multi_post_request($zones, "CUSTOM", $prio, $jinglepath);
		} else {
			if ($_GET['file'] == "chime")   {
				LOGGING("play_t2s.php: Audioclip: Sonos build-in Doorbell CHIME with Priority HIGH has been announced", 7);	
				audioclip_multi_post_request($zones, "CHIME", $prio);
			} else {
				LOGGING("play_t2s.php: Audioclip: Entered file '".$file."' for doorbell is not valid or nothing has been entered. Please correct your syntax", 3);
				exit;
			}
		}
		sleep(3);
		foreach ($zone_volumes as $key => $value)   {
			$sonos = new SonosAccess($sonoszone[$key][0]);
			$sonos->SetVolume($value);
		}
	} else {
		LOGGING("play_t2s.php: Audioclip: File for Doorbell is missing! Use even ...action=doorbell&file=chime or ...action=doorbell&file=<MP3 File from tts/mp3 Folder>", 3);
		exit;		
	}
	#print_r($zones);
	proccessing_time();
}



/**
* Function : handle_playgong --> Playgong/jingle to be played upfront
*
* @param: Zone or array of zones
* @return: 
**/
			
function handle_playgong($zones, $source) {	

		global $sonos, $config, $prio, $time_start;
		
		if(isset($_GET['playgong'])) {
			
			if ($_GET['playgong'] == 'no')	{
				LOGGING("play_t2s.php: Audioclip: 'playgong=no' could not be used in syntax, only 'playgong=yes' or 'playgong=<file>' are allowed", 3);
				exit;
			}
			if(empty($config['MP3']['file_gong'])) {
				LOGGING("play_t2s.php: Audioclip: Standard file for jingle is missing in Plugin config. Please maintain before usage.", 3);
				exit;	
			}
			if (($_GET['playgong'] != "yes") and ($_GET['playgong'] != "no") and ($_GET['playgong'] != " ")) {
				$file = $_GET['playgong'];
				$file = $file.'.mp3';
				$valid = mp3_files($file);
				if ($valid === true) {
					# Replace whitespaces from filename
					$name = str_replace(" ", '%20', $file);
					$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($file);
					# check upfront if file is accessable
					if (@file_get_contents($config['SYSTEM']['httpinterface']."/mp3/".$name) === false)    {
						LOGGING("play_t2s.php: Audioclip: The provided playgong file could not be played due to unsupported characters or whitespaces in filename!! Please change filename accordingly", 3);	
						exit;
					}
					$duration = round(\falahati\PHPMP3\MpegAudio::fromFile($config['SYSTEM']['httpinterface']."/mp3/".$name)->getTotalDuration());
					if ($source === "multi")   {
						audioclip_multi_post_request($zones, "CUSTOM", $prio, $jinglepath);
					} else {
						audioclip_post_request($zones[0], $zones[1], "CUSTOM", $prio, $jinglepath);
					}
					LOGGING("play_t2s.php: Audioclip: Individual jingle '".trim($file)."' has been played as Playgong", 7);	
				} else {
					LOGGING("play_t2s.php: Audioclip: Entered jingle '".$file."' for playgong is not valid or nothing has been entered. Please correct your syntax", 3);
					exit;
				}
			} else {
				$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($config['MP3']['file_gong']);
				$name = str_replace(" ", '%20', $config['MP3']['file_gong']);
				if (@file_get_contents($config['SYSTEM']['httpinterface']."/mp3/".$name) === false)    {
					LOGGING("play_t2s.php: Audioclip: The standard playgong file could not be played due to unsupported characters or whitespaces in filename!! Please change filename accordingly", 3);	
					exit;
				}
				$duration = round(\falahati\PHPMP3\MpegAudio::fromFile($config['SYSTEM']['httpinterface']."/mp3/".$name)->getTotalDuration());
				if ($source === "multi")   {
					audioclip_multi_post_request($zones, "CUSTOM", $prio, $jinglepath);
				} else {
					audioclip_post_request($zones[0], $zones[1], "CUSTOM", $prio, $jinglepath);
				}
				LOGGING("play_t2s.php: Audioclip: Standard file '".trim($config['MP3']['file_gong'])."' has been played as Playgong", 7);	
			}
			sleep($duration);
		}
		return;
}


/**
* Function : handle_message --> message to be played
*
* @param: Zone or array of zones
* @return: 
**/
			
function handle_message($zones, $source) {	

	global $sonos, $config, $prio, $zones, $source, $memberon, $sonoszone, $errortext, $filename, $roomcord, $time_start;

	if (isset($_GET['messageid'])) {
		# messageid
		$messageid = $_GET['messageid'];
		if ($source === "multi")   {
			audioclip_multi_post_request($zones, "CUSTOM", $prio, $config['SYSTEM']['cifsinterface']."/mp3/".$messageid.".mp3");
		} else {
			audioclip_post_request($zones[0], $zones[1], "CUSTOM", $prio, $config['SYSTEM']['cifsinterface']."/mp3/".$messageid.".mp3");
		}
		LOGGING("play_t2s.php: Audioclip: Messageid has been played as Notification", 7);
	} else {
		# Text-to-speech
		if ($source === "multi")   {
			audioclip_multi_post_request($zones, "CUSTOM", $prio, $config['SYSTEM']['cifsinterface']."/".$filename.".mp3");
		} else {
			audioclip_post_request($zones[0], $zones[1], "CUSTOM", $prio, $config['SYSTEM']['cifsinterface']."/".$filename.".mp3");
		}
		LOGDEB("play_t2s.php: Audioclip: TTS '".$filename."' has been played as Notification");
	}
	return;
}


/**
* Function : sendgroupmessage --> translate a text into speech for a group of zones
*
* @param: Text or messageid (Number)
* @return: 
**/
			
function sendgroupmessage() {	
		
			global $coord, $sonos, $text, $folfilePlOn, $sonoszone, $sonoszonen, $errortext, $member, $master, $zone, $messageid, $logging, $textstring, $voice, $config, $mute, $volume, $membermaster, $getgroup, $checkgroup, $time_start, $mode, $modeback, $actual, $errortext;
			
			presence_detection();
			$time_start = microtime(true);
			
			if(isset($_GET['batch'])) {
				LOGGING("play_t2s.php: The parameter batch is not allowed to be used in groups. Please use single message to prepare your batch!", 4);
				exit;
			}
			if(isset($_GET['volume']) or isset($_GET['groupvolume']))  { 
				isset($_GET['volume']) ? $groupvolume = $_GET['volume'] : $groupvolume = $_GET['groupvolume'];
				if ((!is_numeric($groupvolume)) or ($groupvolume < 0) or ($groupvolume > 200)) {
					LOGGING("play_t2s.php: The entered volume of ".$groupvolume." must be even numeric or between 0 and 200! Please correct", 4);	
				}
			}
			if(isset($_GET['sonos'])) {
				LOGGING("play_t2s.php: The parameter 'sonos' couldn't be used for group T2S!", 4);
				exit;
			}
			if($sonoszone[$master][9] == "1") {
				LOGERR("play_t2s.php: Player '". $master ."' is an Generation S1 player and can't be Master of a group! Please remove player from URL (zone=". $master ."&action= ....) or from Sound Profile marked as Master!");
				exit;
			}

			create_tts($errortext);
			$save = saveZonesStatus(); // saves all Zones Status
			// create Group for Announcement
			
			if (isset($_GET['profile']))    {
				$member = createArrayFromGroupProfile();
				$masterrincon = $sonoszone[$master][1]; 
				#$sonos = new SonosAccess($sonoszone[$master][0]);
			} else {
				$masterrincon = $sonoszone[$master][1]; 
				$sonos = new SonosAccess($sonoszone[$master][0]);
				try {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGGING("play_t2s.php: Group Coordinator '$master' has been made to single zone", 7);	
				} catch (Exception $e) {
					LOGGING("play_t2s.php: Member '$master' could not be made to Single Zone! Something went wrong, please try again", 4);	
				}
				AddMember();
			}

			if (defined('T2SMASTER'))   {
				$master = T2SMASTER;
			}
			if (!defined('MEMBER')) {
				define("MEMBER", $member);
			}
			
			#if (in_array($master, $member)) {
				#LOGGING("play_t2s.php: The zone ".$master." could not be entered as member again. Please remove from Syntax '&member=".$master."' !", 3);
				#exit;
			#}
			
			if (isset($_GET['profile']))    {
				// grouping
				foreach ($member as $zone) {
					$handle = is_file($folfilePlOn."".$zone.".txt");
					if($handle === true) {
						$sonos = new SonosAccess($sonoszone[$zone][0]);
						if ($zone != $master) {
							try {
								$sonos->SetAVTransportURI("x-rincon:" . $masterrincon);
								LOGGING("play_t2s.php: Member '$zone' is now connected to Master Zone '$master'", 6);								
							} catch (Exception $e) {
								LOGGING("play_t2s.php: Member '$zone' could not be added to Master $master. Maybe Zone is Offline or Time restrictions entered!", 4);	
							}
							$sonos->SetMute(false);
						}
					}
				}
			}
			#sleep($config['TTS']['sleepgroupmessage']); // warten gemäß config.php bis Gruppierung abgeschlossen ist
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$sonos->SetPlayMode('0'); 
			$sonos->SetQueue("x-rincon-queue:". $masterrincon ."#0");
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
			}
			// Regelung des Volumes für T2S
			if (isset($_GET['volume']))  {
				$sonos->SetVolume($_GET['volume']);
			}
			volume_group();
			play_tts($messageid);
			// wiederherstellen der Ursprungszustände
			LOGGING("play_t2s.php: *** Restore previous settings will be called ***", 6);	
			restoreGroupZone();		
			LOGGING("play_t2s.php: *** Text-to-speech successful processed ***", 6);	
			proccessing_time();
}

/**
* New Function for T2S: t2s_playbatch --> allows T2S to be played in batch mode
*
* @param: empty
* @return: T2S
**/
function t2s_playbatch() {
	global $textstring, $time_start;
			
	$textstring = true;
	$filenamebatch = "t2s_batch.txt";
	if (!file_exists($filenamebatch)) {
		LOGGING("play_t2s.php: There is no T2S batch file to be played!", 4);
		exit();
	}
	say();
}



/**
* Function : send_tts_source --> sendet eine 1 zu Beginn von T2S und eine 0 am Ende
*
* @param: 0 oder 1
* @return: leer
**/

function send_tts_source($tts_stat)  {
	
	global $config, $tmp_tts, $sonoszone, $time_start, $sonoszonen, $master, $ms, $tts_stat, $lbphtmldir;
	
	require_once "$lbphtmldir/system/io-modul.php";
	#require_once "phpMQTT/phpMQTT.php";
	require_once "$lbphtmldir/bin/phpmqtt/phpMQTT.php";

	$tmp_tts = "/run/shm/s4lox_tmp_tts";

	if ($tts_stat == 1)  {
			if(!touch($tmp_tts)) {
				LOGGING("play_t2s.php: No permission to write file", 3);
				return;
			}
		$handle = fopen ($tmp_tts, 'w');
		fwrite ($handle, $tts_stat);
		fclose ($handle); 
	} 
	
	// check if Data transmission is switched off
	if(!is_enabled($config['LOXONE']['LoxDaten'])) {
		return;
	}
	
	if(is_enabled($config['LOXONE']['LoxDatenMQTT'])) {
		// Get the MQTT Gateway connection details from LoxBerry
		$creds = mqtt_connectiondetails();
		// MQTT requires a unique client id
		$client_id = uniqid(gethostname()."_client");
		$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'],  $creds['brokerport'], $client_id);
		$mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass']);
		$mqttstat = "1";
	} else {
		$mqttstat = "0";
	}
	
	// check if MS is fully configured
	if (!isset($ms[$config['LOXONE']['Loxone']])) {
		LOGERR ("play_t2s.php: Your selected Miniserver from Sonos4lox Plugin config seems not to be fully configured. Please check your LoxBerry Miniserver config!") ;
		return;
	}
	
		// obtain selected Miniserver from Plugin config
		$my_ms = $ms[$config['LOXONE']['Loxone']];
		# send TEXT data
		$lox_ip			= $my_ms['IPAddress'];
		$lox_port 	 	= $my_ms['Port'];
		$loxuser 	 	= $my_ms['Admin'];
		$loxpassword 	= $my_ms['Pass'];
		$loxip = $lox_ip.':'.$lox_port;
		
		$t2s_zones = array();
		if (isset($_GET['member']))   {
			$mem = $_GET['member'];
			$t2s_zones = explode(",", $mem);
			array_push($t2s_zones, $master);
		} else {
			array_push($t2s_zones, $master);
		}
		foreach ($t2s_zones as $value)    {
			try {
				$data['t2s_'.$value] = $tts_stat;
				if ($mqttstat == "1")   {
					$err = $mqtt->publish('Sonos4lox/t2s/'.$value, $data['t2s_'.$value], 0, 1);
				} else {			
					$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/t2s_$value/$tts_stat"); // Radio oder Playliste
				}
				#LOGDEB("play_t2s.php: T2S notification: '".$tts_stat."' has been send to: ".$value);	
			} catch (Exception $e) {
				LOGWARN("play_t2s.php: Sending T2S notification for Zone '".$value."' failed, we skip here...");	
				return;
			}
		}
		if ($mqttstat == "1")   {
			$mqtt->close();
		}

	return;
}


function guidv4($data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
	#print_r(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)));
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}


function audioclip_handle_members($member) {
	
	global $sonoszone, $sonoszonen, $time_start, $memberon, $profile_details, $master, $zones_all;

	$memberon = array();
	$members = explode(',', $member);
	if (isset($_GET['profile']))   {
		checkGroupProfile();
		exit;
	}
	foreach (SONOSZONE as $zone => $zoneData) {
		if ($zone == $master)   {
			$memberon[$master] = $zoneData;
			LOGGING("play_t2s.php: Audioclip: Player '".$master."' has been added", 5);
		}
		#print_R($member);
		if ($member === 'all' || ($members && in_array($zone, $members))) {
			$zoneon = checkZoneOnline($zone);
			if ($zoneon === (bool)true and $master != $zone)  {
				$memberon[$zone] = $zoneData;
				LOGGING("play_t2s.php: Audioclip: Member '".$zone."' has been added", 5);
			}
		} 
	}
	LOGGING("play_t2s.php: Audioclip: ".count($memberon)." Member has been identified (plus Master)", 7);
	#print_r($memberon);
	return $memberon;
}

function audioclip_multi_post_request($zones, $clipType="CUSTOM", $priority="LOW", $tts="") {

	global $volume, $guid, $memberon, $time_start;
	
	if(empty($zones)) return;

	$headers = [
		'Content-Type: application/json',
		'X-Sonos-Api-Key: '.$guid,
	];

	// Multi curl from: https://stackoverflow.com/a/63612667

	$mh = curl_multi_init();

	foreach ($zones as $zone) {
		
		$url = audioclip_zone_url($zone);

		if (!$url) continue;

		$jsonData = audiclip_json_data(audioclip_zone_max_volume($zone, $volume), $clipType, $priority, $tts);

		$worker = curl_init();
		curl_setopt_array($worker, [
			CURLOPT_URL => $url,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_HEADER => 0,
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYSTATUS => false,
			CURLOPT_RETURNTRANSFER => 1,
			// try to speed up things
			CURLOPT_USERAGENT => "PHP",
			CURLOPT_SSL_ENABLE_ALPN => false,
			CURLOPT_SSL_ENABLE_NPN => false,
			CURLOPT_SSL_FALSESTART => true,
			CURLOPT_TCP_NODELAY => true,
			CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // do not use ipv6 resolve
			CURLOPT_TCP_FASTOPEN => true,
		]);
		curl_multi_add_handle($mh, $worker);
	}

	for (;;) {
		$still_running = null;
		do {
			$err = curl_multi_exec($mh, $still_running);
		} while ($err === CURLM_CALL_MULTI_PERFORM);
		if ($err !== CURLM_OK) {
			// handle curl multi error?
		}
		if ($still_running < 1) {
			// all downloads completed
			break;
		}
		// some haven't finished downloading, sleep until more data arrives:
		curl_multi_select($mh, 1);
	}

	$results = [];
	while (false !== ($info = curl_multi_info_read($mh))) {
		if ($info["result"] !== CURLE_OK) {
			// handle download error?
		}
		$results[curl_getinfo($info["handle"], CURLINFO_EFFECTIVE_URL)] = curl_multi_getcontent($info["handle"]);
		curl_multi_remove_handle($mh, $info["handle"]);
		curl_close($info["handle"]);
	}
	curl_multi_close($mh);
	return $results;
}

function audiclip_json_data($volume, $clipType="CUSTOM", $priority="LOW", $tts="") {
	
	global $time_start;
	
	# Get Volume (Backup)
	if (isset($_GET['volume']))    {
		$volume = $_GET['volume'];
	}
	
	if ($clipType == "CUSTOM") {
		$jsonData = array(
			'name' => "AudioClip",
			'appId' => 'de.loxberry.sonos',
			'clipType' => "CUSTOM",
			'streamUrl' => $tts,
			'priority' => $priority,
			'volume' => $volume
		);
	}
	if ($clipType == "CHIME") {
		$jsonData = array(
			'name' => "AudioClip",
			'appId' => 'de.loxberry.sonos',
			'clipType' => "CHIME",
			'priority' => $priority,
			'volume' => $volume
		);
	}

	$jsonDataEncoded = json_encode($jsonData);
	return $jsonDataEncoded;
}

function audioclip_zone_url($zone) {
	
	global $sonoszone, $time_start;

	$zoneData = $sonoszone[$zone];
	if ($zoneData) return audioclip_url($zoneData[0], $zoneData[1]);

	return false;
}

function audioclip_zone_max_volume($zone, $volume) {
	global $sonoszone, $time_start;

	$zoneData = $sonoszone[$zone];
	if ($zoneData) return min($volume, $zoneData[5]);

	return $volume;
}

function audioclip_url($ip, $rincon) {
	global $time_start;
	
	return 'https://'.$ip.':1443/api/v1/players/'.$rincon.'/audioClip';
}

  
function audioclip_guid_selection() {
	global $guid, $time_start;
	
	return $guid = array("guid1" => "622493a2-4877-496c-9bba-abcb502908a5",
						 "guid2" => "123e4567-e89b-12d3-a456-426655440000",
						);
}
	

/**
* Funktion : audioclip_post_request --> POST to https url of player
*
* @param: 	$text, $greet
* @return: JSON
**/	
  
function audioclip_post_request($ip, $rincon, $clipType="CUSTOM", $priority="LOW", $tts="") {
	
	global $myLBip, $sonos, $volume, $lbhostname, $lbwebport, $filename, $streamUrl, $config, $guid, $time_start;
	
	/**
	echo "IP: ".$ip.PHP_EOL;
	echo "Rincon: ".$rincon.PHP_EOL;
	echo "clipType: ".$clipType.PHP_EOL;
	echo "tts: ".$tts.PHP_EOL;
	echo "volume: ".$volume.PHP_EOL;
	echo "priority: ".$priority.PHP_EOL;
	echo "guid: ".$guid.PHP_EOL;
	echo "filename: ".$filename.PHP_EOL;
	**/	
	
	// API Url
	$url = audioclip_url($ip, $rincon);
	/**
	echo "url: ".$url.PHP_EOL;
	echo "<br>";
	**/
	
	# Get Volume (Backup)
	if (isset($_GET['volume']))    {
		$volume = $_GET['volume'];
	}
						
	// Initiate cURL.
	$ch = curl_init($url);

	if ($clipType == "CUSTOM")    {
		$jsonData = array(
			'name' => "AudioClip",
			'appId' => 'de.loxberry.sonos',
			'clipType' => "CUSTOM",
			'httpAuthorization' => null,
			'clipLEDBehavior' => 'NONE',
			'streamUrl' => $tts,
			'priority' => $priority,
			'volume' => $volume
		);
	}
	if ($clipType == "CHIME")    {
		$jsonData = array(
			'name' => "AudioClip",
			'appId' => 'de.loxberry.sonos',
			'clipType' => "CHIME",
			'httpAuthorization' => null,
			'clipLEDBehavior' => 'NONE',
			'priority' => $priority,
			'volume' => $volume
		);
	}
		 
	// Encode the array into JSON.
	$jsonDataEncoded = json_encode($jsonData);

	// Tell cURL that we want to send a POST request.
	curl_setopt($ch, CURLOPT_POST, 1);
	 
	// Attach our encoded JSON string to the POST fields.
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
	 
	// Set the content type to application/json
	$headers = [
		'Content-Type: application/json',
		"Accept: application/json",
		'X-Sonos-Api-Key: '.$guid,
	];
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 

	// Accept peer SSL (HTTPS) certificate
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	
	// Request response from Call
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
	// Execute the request
	$result = curl_exec($ch);
	
	// Request info/details from Call
	$info = curl_getinfo($ch);
	
	// was the request successful?
	if($result === false or $info['http_code'] != "200")  {
		$result = json_decode($result, true);
		if (isset($result['_objectType']))  {
			$split = explode(",", $result['wwwAuthenticate']);
			try {
				LOGGING("play_t2s.php: cURL AudioClip error: ".$result['errorCode']." ".$split[2], 3);
				exit;
			} catch (Exception $e) {
				LOGGING("play_t2s.php: cURL AudioClip unknown error", 3);
				exit;
			}
		} else {
			LOGGING("play_t2s.php: cURL AudioClip error: ".curl_error($ch), 3);
			exit;
		}
	} else {
		LOGGING("play_t2s.php: cURL AudioClip request okay!", 7);
	}
	// close cURL
	curl_close($ch);
	return $result;
}


/**
* Function : checkGroupProfile --> check if selected Profile is a Group
*
* @param: 
* @return: true or false
**/

function checkGroupProfile()   {
	
	global $lbpconfigdir, $vol_config, $group, $profile_details;

	get_profile_details();
	if ($profile_details[0]['Group'] == "Group")   {
		$group = true;
	} else {
		$group = false;
	}
	return $group;
}


/**
* Function : createArrayFromGroupProfile --> create Array From Group Profile
*
* @param: 
* @return: true or false
**/

function createArrayFromGroupProfile()   {

	global $lbpconfigdir, $profile_details, $vol_config, $zone, $master, $zone_volumes, $sonoszone, $memberincl;

	get_profile_details();

	switch($profile_details[0]['Group'])    {
	case 'Group';
		foreach (SONOSZONE as $player => $value)   {
			if (is_enabled($profile_details[0]['Player'][$player][0]['Master']))    {
				$master = $player;
			}
		}
		$memberincl = array();
		foreach (SONOSZONE as $zone => $ip) {
			if ($profile_details[0]['Player'][$zone][0]['Master'] == "true")   {
				$memberincl[0] = $zone;
				# check wether master Zone is Master of existing group
				$state = getZoneStatus($zone);
				if ($state == "master")   {
					$sonos = new SonosAccess(SONOSZONE[$zone][0]);
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGINF("play_t2s.php: Player '".$zone."' has been removed from existing Group");
				}
				$masterzone = $zone;
			}
		}
		foreach (SONOSZONE as $zone => $ip) {
			# add member to Master
			if ($profile_details[0]['Player'][$zone][0]['Member'] == "true")   {
				array_push($memberincl, $zone);
			}
		}
		VolumeProfile($memberincl);
		# in case of Group T2S remove master (1st element) from member group
		if (!isset($_GET['clip']) && !isset($_GET['action']) == "doorbell")   {
			array_shift($memberincl);
		}
		if (!defined('MEMBER')) {
			define("MEMBER", $memberincl);
		}
		if (!defined('T2SMASTER')) {
			define("T2SMASTER", $master);
		}
		#print_r(MEMBER);
		#$member = $memberincl;
		return $memberincl;
		LOGOK("play_t2s.php: Array of Speakers from Sound Profile '".$_GET['profile']."' has been created");
	break;
	
	case 'Single';
		foreach (SONOSZONE as $player => $value)   {
			# in case only master marked
			if (is_enabled($profile_details[0]['Player'][$player][0]['Master']))    {
				$memberincl[0] = $player;
			}
		}
		VolumeProfile($memberincl);
		if (!defined('T2SMASTER')) {
			define("T2SMASTER", $memberincl[0]);
		}
		return $memberincl;
	break;
	
	case 'NoGroup';
		$memberincl[0] = MASTER;
		VolumeProfile($memberincl);
		return $memberincl;
	break;
	}
	
}


/**
/* Funktion : VolumeProfile --> set Volume for each player
/*
/* @param: array of player                        
/* @return: 
**/

function VolumeProfile($memberincl)   {
	
global $sonoszone, $profile_details, $profile_selected, $profile, $config, $memberincl, $zone_volumes;

	$zone_volumes = array();
	foreach ($memberincl as $key)  {
		try {
			$sonos = new SonosAccess($sonoszone[$key][0]);
			$zone_volumes[$key] = $sonos->GetVolume();
			#@$sonos->SetMute(true)
			# Set Volume	
			if ($profile_details[0]['Player'][$key][0]['Volume'] != "")	{
				$sonos->SetVolume($profile_details[0]['Player'][$key][0]['Volume']);
				$volume = $profile_details[0]['Player'][$key][0]['Volume'];
				LOGINF("play_t2s.php: Volume for '".$key."' has been set to: ".$profile_details[0]['Player'][$key][0]['Volume']);
			} else {
				LOGWARN("play_t2s.php: No Volume entered in Profile, so we could not set Volume");
			}		
		} catch (Exception $e) {
			LOGERR("play_t2s.php: Player '".$key."' does not respond. Please check your settings");
			continue;
		}
	}	
	#print_r($zone_volumes);
	return;
}
	


function get_profile_details()   {

	global $lbpconfigdir, $profile_details, $vol_config;

	$volprofil = $_GET['profile'];
	$volconfig = json_decode(file_get_contents($lbpconfigdir . "/" . $vol_config.".json"), TRUE);
	$profile_details = array_multi_search(strtolower($volprofil), $volconfig, $sKey = "");
	if (!$profile_details)   {
		LOGERR("play_t2s.php: Entered Sound Profile '".$_GET['profile']."' in URL could not be found. Please check your entry!");
		exit(1);
	} else {
		LOGINF("play_t2s.php: Sound Profile '".$_GET['profile']."' has been selected!");
	}
	#print_r($profile_details);
	return $profile_details;
}



function proccessing_time()
{
	global $time_start;
	
	$time_end = microtime(true);
	$t2s_time = $time_end - $time_start;
	LOGGING("play_t2s.php: The requested T2S tooks ".round($t2s_time, 2)." seconds to be processed completly.", 5);	
	
}
?>