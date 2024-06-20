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
	
	global $sonos;
			
	if(!isset($_GET['member'])) {
		if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])) && (!isset($errortext)) && (!isset($_GET['sonos'])) &&
			(!isset($_GET['text'])) && (!isset($_GET['weather'])) && (!isset($_GET['abfall'])) &&
			(!isset($_GET['witz'])) && (!isset($_GET['pollen'])) && (!isset($_GET['warning'])) &&
			(!isset($_GET['bauernregel'])) && (!isset($_GET['distance'])) && (!isset($_GET['clock'])) && 
			(!isset($_GET['calendar']))) {
			LOGGING("play_t2s.php: Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' in combination with &clip are necessary to play an anouncement. (check Wiki)", 3);	
			exit;
		}
		if(isset($_GET['clip'])) {
			LOGDEB("play_t2s.php: Single Notification been called");
			sendaudioclip();
		} else {
			LOGDEB("play_t2s.php: Single TTS been called");
			sendmessage();
		}
	} else {
		if(isset($_GET['clip'])) {
			// to be added
		} else {
			sendgroupmessage();
		}
	}	
}


/**
* New Function: audioclip
*
* @param: empty
* @return: nothing
**/

function playAudioclip() {
	
	global $sonoszone, $messageid, $filename, $volume, $config, $sonos, $act_player, $playstat, $roomcord, $playg;
	
	# pre check for MP3 Stream
	if (isset($_GET['messageid']))  {
		$filenamecheck = $config['SYSTEM']['ttspath']."/mp3/".$messageid.".mp3";
	} else {
		$filenamecheck = $config['SYSTEM']['ttspath']."/".$filename.".mp3";
	}

	# check if T2S has been successful created, if not wait until finished
	while (!file_exists($filenamecheck) and !filesize($filenamecheck)):
		LOGDEB("Notification creation not yet finished, we have to wait...");
		usleep(200000); //check every 200ms
	endwhile;

	#echo "Roomcord: ".$roomcord;
	# check if T2S has been successful created
	#if (file_exists($config['SYSTEM']['ttspath']."/".$filename.".mp3"))   {
		#if (filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3") > 0)   {
			#LOGDEB("File exist and is OK");
			#sleep(1);
		#} else {
			#exit;
		#}
	#}
	
	if(isset($_GET['high'])) {
		$prio = "HIGH";
		LOGDEB("play_t2s.php: Audioclip: Priority for Notification has been set to HIGH");
	} else {
		$prio = "LOW";
		LOGDEB("play_t2s.php: Audioclip: Standard Priority LOW for Notification will be used ");
	}
	
		// Playgong/jingle to be played upfront
		#*****************************************************************************************************
		if(isset($_GET['playgong'])) {
			
			if ($_GET['playgong'] == 'no')	{
				LOGGING("play_t2s.php: Audioclip: 'playgong=no' could not be used in syntax, only 'playgong=yes' or 'playgong=file' are allowed", 3);
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
					$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($file);
					$duration = round(\falahati\PHPMP3\MpegAudio::fromFile($config['SYSTEM']['httpinterface']."/mp3/".$file)->getTotalDuration());
					audioclip_post_request($act_player[0], $act_player[1], "CUSTOM", $prio, $jinglepath);
					$playg = "true";
					LOGGING("play_t2s.php: Audioclip: Individual jingle '".trim($file)."' has been played as Notification", 7);	
				} else {
					LOGGING("play_t2s.php: Audioclip: Entered jingle '".$file."' for playgong is not valid or nothing has been entered. Please correct your syntax", 3);
					exit;
				}
			} else {
				$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($config['MP3']['file_gong']);
				$duration = round(\falahati\PHPMP3\MpegAudio::fromFile($config['SYSTEM']['httpinterface']."/mp3/".$config['MP3']['file_gong'])->getTotalDuration());
				audioclip_post_request($act_player[0], $act_player[1], "CUSTOM", $prio, $jinglepath);
				$playg = "true";
				LOGGING("play_t2s.php: Audioclip: Standard file '".trim($config['MP3']['file_gong'])."' has been played as Notification", 7);	
			}
			LOGGING("play_t2s.php: Audioclip: Duration of playgong file is~ ".$duration." seconds", 7);	
			#$concatenante = \falahati\PHPMP3\MpegAudio::fromFile($jinglepath)->append(\falahati\PHPMP3\MpegAudio::fromData(base64_decode("/**BASE64-DATA**/")))->saveFile($config['SYSTEM']['ttspath']."/".$filename.".mp3");
			sleep($duration);
		}
		#******* End of Playgong *********************************************************************
		
	# check wether messageid or TTS been requested	
	if (isset($_GET['messageid'])) {
		# messageid
		$duration = round(\falahati\PHPMP3\MpegAudio::fromFile($config['SYSTEM']['httpinterface']."/mp3/".$messageid.".mp3")->getTotalDuration());
		audioclip_post_request($act_player[0], $act_player[1], "CUSTOM", $prio, $config['SYSTEM']['cifsinterface']."/mp3/".$messageid.".mp3");
		LOGGING("play_t2s.php: Audioclip: Messageid has been played as Notification", 7);
	} else {
		$duration = round(\falahati\PHPMP3\MpegAudio::fromFile($config['SYSTEM']['httpinterface']."/".$filename.".mp3")->getTotalDuration());
		# Text-to-speech
		audioclip_post_request($act_player[0], $act_player[1], "CUSTOM", $prio, $config['SYSTEM']['cifsinterface']."/".$filename.".mp3");
		LOGDEB("play_t2s.php: Audioclip: TTS '".$filename."' has been played as Notification");
		# Workaround if jingle has been played upfront
		if ($playg == "true" and $playstat == "1")   {
			sleep(2);
			$sonos = new SonosAccess($sonoszone[$roomcord][0]);
			$sonos->Play();
			LOGDEB("play_t2s.php: Audioclip: Play executed");
		}
	}
	LOGGING("play_t2s.php: Audioclip: Duration of Notification file is~ ".$duration." seconds", 7);	
}




/**
* Function : create_tts --> creates an MP3 File based on Text Input
*
* @param: 	Text of Messasge ID
* @return: 	MP3 File
**/		

function create_tts($text ='') {
	global $sonos, $config, $dist, $filename, $MessageStorepath, $errortext, $act_player, $messageid, $textstring, $home, $time_start, $tmp_batch, $MP3path, $filenameplay, $textstring, $volume, $tts_stat;
	
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
	elseif (isset($_GET['witz'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$textstring = substr(GetWitz(), 0, 1000);
		LOGGING("play_t2s.php: Joke plugin has been called", 7);
		}
	elseif (isset($_GET['bauernregel'])) {
		// calls the weather warning-to-speech Function
		include_once("addon/gimmicks.php");
		$textstring = substr(GetTodayBauernregel(), 0, 500);
		LOGGING("play_t2s.php: Bauernregeln plugin has been called", 7);
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
		#if ($config['TTS']['t2s_engine'] == 9012 and empty($errortext)) {
		#	include_once("voice_engines/Lovo.php");	
		#}
		#echo filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3");
	
	if(file_exists($config['SYSTEM']['ttspath']."/".$filename.".mp3") && empty($_GET['nocache'])) {
		LOGGING("play_t2s.php: MP3 grabbed from cache: '$textstring' ", 6);
	} else {
		
		t2s($textstring, $filename);
		if (($config['TTS']['t2s_engine'] == 6001) or ($config['TTS']['t2s_engine'] == 7001) or ($config['TTS']['t2s_engine'] == 4001) or ($config['TTS']['t2s_engine'] == 8001))    {
			// ** generiere MP3 ID3 Tags **
			#require_once("system/bin/getid3/getid3.php");
			#$getID3 = new getID3;
			#write_MP3_IDTag($textstring);
		}
		
		// check if filename is < 1 Byte
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
		
		echo $textstring;
		
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
	global $volume, $config, $dist, $messageid, $sonos, $text, $errortext, $lbphtmldir, $messageid, $sleeptimegong, $sonoszone, $sonoszonen, $master, $coord, $actual, $textstring, $act_player, $time_start, $t2s_batch, $filename, $textstring, $home, $MP3path, $lbpplugindir, $logpath, $try_play, $MessageStorepath, $filename, $tts_stat;
		
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
					print_r($config['SYSTEM']['cifsinterface']."/".$filename.".mp3");
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
		$sonos->SetVolume($volume);
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
		LOGGING("play_t2s.php: T2S play process has been successful finished", 6);
		return $actual;
		
		
		
}


/**
* Function : sendmessage --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendmessage($errortext= '') {
			global $text, $dist, $master, $messageid, $errortext, $logging, $textstring, $voice, $config, $actual, $act_player, $volume, $source, $sonos, $coord, $time_start, $filename, $sonoszone, $sonoszonen, $tmp_batch, $mode, $MP3path, $tts_stat;
			
			if(isset($_GET['member'])) {
				sendgroupmessage();
				LOGGING("play_t2s.php: Member has been entered for a single Zone function, we switch to 'sendgroupmessage'. Please correct your syntax!", 4);
			}	
			
			$time_start = microtime(true);
			if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
				LOGGING("play_t2s.php: There is no T2S engine/language selected in Plugin config. Please select before using T2S functionality.", 3);
				exit();
			}
			#if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])) && (!isset($errortext)) && (!isset($_GET['sonos'])) &&
			#	(!isset($_GET['text'])) && (!isset($_GET['weather'])) && (!isset($_GET['abfall'])) &&
			#	(!isset($_GET['witz'])) && (!isset($_GET['pollen'])) && (!isset($_GET['warning'])) &&
			#	(!isset($_GET['bauernregel'])) && (!isset($_GET['distance'])) && (!isset($_GET['clock'])) && 
			#	(!isset($_GET['calendar'])) && (!isset($_GET['action'])) == 'playbatch') {
			#	LOGGING("play_t2s.php: Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' are necessary to play an anouncement. (check Wiki)", 3);	
			#	exit;
			#}
			
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
			#if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
			#	$volume = $_GET['volume'];
				#LOGGING("play_t2s.php: Volume from syntax been adopted", 7);		
			#} else 	{
				// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
			#	$volume = $config['sonoszonen'][$master][3];
				#LOGGING("play_t2s.php: Standard Volume from zone ".$master."  been used", 7);		
			#}
			#checkaddon();
			#checkTTSkeys();
			$save = saveZonesStatus(); // saves all Zones Status
			SetVolumeModeConnect($mode = '0', $master);
			$return = getZoneStatus($master); // get current Zone Status (Single, Member or Master)
			if($return == 'member') {
				if(isset($_GET['sonos'])) { // check if Zone is Group Member, then abort
					LOGGING("play_t2s.php: The specified zone is part of a group! There are no information available.", 4);
				exit;
				}
			}
			create_tts($errortext);
			// stop 1st before Song Name been played
			$test = $sonos->GetPositionInfo();
			if (($return == 'master') or ($return == 'member')) {
				$sonos->BecomeCoordinatorOfStandaloneGroup();  // in case Member or Master then remove Zone from Group
				LOGGING("play_t2s.php: Zone ".$master." has been removed from group", 6);		
			}
			if (substr($test['TrackURI'], 0, 18) == "x-sonos-htastream:") {
				$sonos->SetQueue("x-rincon-queue:". $sonoszone[$master][1] ."#0");
				LOGGING("play_t2s.php: Streaming/TV endet successful", 7);		
			}
			#if ((substr($test, 0, 18) !== "x-sonos-htastream:") and (!isset($_GET['sonos'])))  {
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
				usleep(200000);
			}
			// get Coordinator of (maybe) pair or single player
			$coord = getRoomCoordinator($master);
			LOGGING("play_t2s.php: Room Coordinator has been identified", 7);		
			$sonos = new SonosAccess($coord[0]); 
			$sonos->SetMute(false);
			play_tts($messageid);
			restoreSingleZone();
			$mode = "";
			$actual[$master]['CONNECT'] == 'true' ? $mode = '1' : $mode = '0';
			SetVolumeModeConnect($mode, $master);
			$time_end = microtime(true);
			$t2s_time = $time_end - $time_start;
			#echo "Die T2S dauerte ".round($t2s_time, 2)." Sekunden.\n";
			LOGGING("play_t2s.php: The requested single T2S tooks ".round($t2s_time, 2)." seconds to be processed.", 5);	
			#return;		
	}
	
/**
* Function : sendaudioclip --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendaudioclip($errortext = "") {
	
	global $config, $volume, $master, $filename, $messageid, $sonoszone, $sonos, $act_player, $playstat, $roomcord, $playg;
	
	#print_r($sonoszone);
	$time_start = microtime(true);
	if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
		LOGGING("play_t2s.php: Audioclip: There is no T2S engine/language selected in Plugin config. Please select before using any T2S functionality.", 3);
		exit();
	}
	if(isset($_GET['member'])) {
		LOGERR("play_t2s.php: Audioclip: Group Notifications are not supported! Please use &clip only for Single Notification.");
		exit;
	}
	
	$act_player = $sonoszone[$master];
	LOGDEB("play_t2s.php: Audioclip: Notification for Player '". $master ."' has been called.");
	
	$playg = "false";
	
	# Get Group Coordinator
	$roomcord = getCoordinator($master);
	$sonos = new SonosAccess($sonoszone[$roomcord][0]);
	$playstat = $sonos->GetTransportInfo($act_player);
	
	# get ZoneStatus (Member, Master, Single)
	$stat = getZoneStatus($master);
	
	# check if Player is capable for AUDIO_CLIP
	if(!isset($act_player[11])) {
		LOGERR("play_t2s.php: Audioclip: Player does not support Notification to be played. Please remove &clip from URL to switch to Plugin Standard.");
		exit;
	}
	# determine if Player is fully supported/partial supported  for AUDIO_CLIP
	if(isset($act_player[11]) and $act_player[11] == true) {
		if(isset($act_player[12]) and $act_player[12] == true) {
			$full_support = true;
			LOGDEB("play_t2s.php: Audioclip: Player '". $master ."' is fully supported for Notification.");
		} else {
			$full_support = false;
			LOGDEB("play_t2s.php: Audioclip: Player '". $master ."' has limited support for Notification.");
		}
	}
	# check wether zone is only partial supported and is in group
	if ($full_support == false and $stat != "single")   {
		$save = saveZonesStatus(); // saves all Zones Status
		LOGDEB("play_t2s.php: Audioclip: Player '". $master ."' Status has been saved.");
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGDEB("play_t2s.php: Audioclip: Player '". $master ."' has been ungrouped.");
	}
	
	create_tts($errortext);
	playAudioclip();
	if ($full_support == false and $stat != "single")   {
		$sonos = new SonosAccess($sonoszone[$master][0]);
		restoreSingleZone();
		LOGDEB("play_t2s.php: Audioclip: Player '". $master ."' has been restored.");
	}
	$time_end = microtime(true);
	$t2s_time = $time_end - $time_start;
	LOGGING("play_t2s.php: Audioclip: The requested Notification tooks ".round($t2s_time, 2)." seconds to be processed.", 5);	
	}
	

/**
* Function : doorbell --> playing file as doorbell
*
* @param: CHIME or messageid (Number)
* @return: 
**/

function doorbell() {

	global $config, $master, $sonos;

	if(isset($_GET['playgong'])) {
		LOGERR("play_t2s.php: Audioclip: playgong could not be used im combination with function 'doorbell'");
		exit;
	}

	$time_start = microtime(true);
	$prio = "HIGH";
	$zones = $master;

	if (isset($_GET['member'])) {
		$zones = array_merge($zones, audioclip_handle_members($_GET['member']));
	}
	
	if (isset($_GET['file'])) {
		$file = $_GET['file'];
		$file = $file.'.mp3';
		$valid = mp3_files($file);
		if ($valid === true) {
			$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($file);
			LOGGING("play_t2s.php: Audioclip: Doorbell '".trim($file)."' with Priority HIGH has been announced", 7);	
			audioclip_multi_post_request($zones, "CUSTOM", $prio, $jinglepath);
		} else {
			if ($_GET['file'] = "chime")   {
				LOGGING("play_t2s.php: Audioclip: Sonos build-in Doorbell CHIME with Priority HIGH has been announced", 7);	
				audioclip_multi_post_request($zones, "CHIME", $prio);
			} else {
				LOGGING("play_t2s.php: Audioclip: Entered file '".$file."' for doorbell is not valid or nothing has been entered. Please correct your syntax", 3);
				exit;
			}
		}
	} 
	$time_end = microtime(true);
	$t2s_time = $time_end - $time_start;
	LOGGING("play_t2s.php: Audioclip: Doorbell tooks ".round($t2s_time, 2)." seconds to be processed.", 5);	
}


/**
* Function : sendgroupmessage --> translate a text into speech for a group of zones
*
* @param: Text or messageid (Number)
* @return: 
**/
			
function sendgroupmessage() {			
			global $coord, $sonos, $text, $sonoszone, $errortext, $member, $master, $zone, $messageid, $logging, $textstring, $voice, $config, $mute, $volume, $membermaster, $getgroup, $checkgroup, $time_start, $mode, $modeback, $actual, $errortext;
			
			$time_start = microtime(true);
			if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
				LOGGING("play_t2s.php: There is no T2S engine/language selected in Plugin config. Please select before using T2S functionality.", 3);
				exit();
			}
			#if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])) && (!isset($_GET['sonos'])) &&
			#	(!isset($_GET['text'])) && (!isset($_GET['weather'])) && (!isset($_GET['abfall'])) &&
			#	(!isset($_GET['witz'])) && (!isset($_GET['pollen'])) && (!isset($_GET['warning'])) &&
			#	(!isset($_GET['bauernregel'])) && (!isset($_GET['distance'])) && (!isset($_GET['clock'])) && 
			#	(!isset($_GET['calendar'])) && (!isset($_GET['action'])) == 'playbatch') {
			#	LOGGING("play_t2s.php: Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' are necessary to play an anouncement. (check Wiki)", 3);	
			#	exit;
			#}
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
			#checkaddon();
			#checkTTSkeys();
			#$master = $_GET['zone'];
			$member = $_GET['member'];
			create_tts($errortext);
			// if parameter 'all' has been entered all zones were grouped
			if($member === 'all') {
				#$member = array();
				$memberon = array();
				foreach ($sonoszone as $zone => $ip) {
					$zoneon = checkZoneOnline($zone);
					// exclude master Zone
					if ($zone != $master) {
						if ($zoneon === (bool)true)  {
							array_push($memberon, $zone);
						}
					}
				}
				$member = $memberon;
				LOGGING("play_t2s.php: All Players has been grouped to Player ".$master, 5);	
			} else {
				$member = explode(',', $member);
				$memberon = array();
				foreach ($member as $value) {
					$zoneon = checkZoneOnline($value);
					if ($zoneon === (bool)true)  {
						array_push($memberon, $value);
					} else {
						LOGGING("play_t2s.php: Player '".$value."' could not be added to the group!!", 4);
					}
				}
				$member = $memberon;
			}
			if (in_array($master, $member)) {
				LOGGING("play_t2s.php: The zone ".$master." could not be entered as member again. Please remove from Syntax '&member=".$master."' !", 3);
				exit;
			}
			// prüft alle Member ob Sie Online sind und löscht ggf. Member falls nicht Online
			#checkZonesOnline($member);
			$coord = getRoomCoordinator($master);
			LOGGING("play_t2s.php: Room Coordinator has been identified", 7);		
			// speichern der Zonen Zustände
			$save = saveZonesStatus(); // saves all Zones Status
			foreach($member as $newzone) {
				SetVolumeModeConnect($mode = '0', $newzone);
				SetVolumeModeConnect($mode = '0', $master);
			}
			// create Group for Announcement
			$masterrincon = $coord[1]; 
			$sonos = new SonosAccess($coord[0]);
			$sonos->BecomeCoordinatorOfStandaloneGroup();
			LOGGING("play_t2s.php: Group Coordinator has been made to single zone", 7);		
			// grouping
			foreach ($member as $zone) {
				$sonos = new SonosAccess($sonoszone[$zone][0]);
				if ($zone != $master) {
					$sonos->SetAVTransportURI("x-rincon:" . $masterrincon); 
					$sonos->SetMute(false);
					LOGGING("play_t2s.php: Member '$zone' is now connected to Master Zone", 7);		
				}
			}
			#sleep($config['TTS']['sleepgroupmessage']); // warten gemäß config.php bis Gruppierung abgeschlossen ist
			$sonos = new SonosAccess($coord[0]);
			$sonos->SetPlayMode('0'); 
			$sonos->SetQueue("x-rincon-queue:". $coord[1] ."#0");
			if (!isset($_GET['sonos']))  {
				$sonos->Stop();
			}
			#create_tts();
			$group = $member;
			// master der array hinzufügen
			array_push($group, $master);
			// Regelung des Volumes für T2S
			$sonos->SetVolume($volume);
			volume_group();
			play_tts($messageid);
			// wiederherstellen der Ursprungszustände
			LOGGING("play_t2s.php: *** Restore previous settings will be called ***", 6);	
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
			$time_end = microtime(true);
			$t2s_time = $time_end - $time_start;
			#echo "Die T2S dauerte ".round($t2s_time, 2)." Sekunden.\n";
			LOGGING("play_t2s.php: The requested group T2S tooks ".round($t2s_time, 2)." seconds to be processed.", 5);	
			#return;			
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
	
	global $config, $tmp_tts, $sonoszone, $sonoszonen, $master, $ms, $tts_stat, $lbphtmldir;
	
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
	
	// ceck if configured MS is fully configured
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
	global $sonoszone;

	$memberon = array();
	$members = explode(',', $member);

	foreach ($sonoszone as $zone => $zoneData) {
		if ($member === 'all' || ($members && in_array($zone, $members))) {
			$zoneon = checkZoneOnline($zone);
			if ($zoneon === (bool)true)  {
				array_push($memberon, $zone);
			}
		}
	}

	return $memberon;
}

function audioclip_multi_post_request($zones, $clipType="CUSTOM", $priority="LOW", $tts="") {

	global $volume, $guid;
	
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
	global $sonoszone;

	$zoneData = $sonoszone[$zone];
	if ($zoneData) return audioclip_url($zoneData[0], $zoneData[1]);

	return false;
}

function audioclip_zone_max_volume($zone, $volume) {
	global $sonoszone;

	$zoneData = $sonoszone[$zone];
	if ($zoneData) return min($volume, $zoneData[5]);

	return $volume;
}

function audioclip_url($ip, $rincon) {
	return 'https://'.$ip.':1443/api/v1/players/'.$rincon.'/audioClip';
}

/**
* Funktion : audioclip_post_request --> POST to https url of player
*
* @param: 	$text, $greet
* @return: JSON
**/	
  
function audioclip_post_request($ip, $rincon, $clipType="CUSTOM", $priority="LOW", $tts="") {
	
	global $myLBip, $volume, $lbhostname, $lbwebport, $filename, $streamUrl, $config, $guid;
	
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
	#print_r($result);
	return $result;
}


?>