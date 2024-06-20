<?php

##############################################################################################################################
#
# Version: 	5.5.5
# Datum: 	06.2024
# veröffentlicht in: https://github.com/Liver64/LoxBerry-Sonos/releases
#
# http://<IP>:1400/xml/device_description.xml
# http://<IP>:1400/support/review
# 
##############################################################################################################################


// ToDo

ini_set('max_execution_time', 60); 							// Max. Skriptlaufzeit auf 60 Sekunden

include("system/sonosAccess.php");
include("Grouping.php");
include("Helper.php");
include("Alarm.php");
include("Playlist.php");
include("Metadata.php");
include("Queue.php");
include("Info.php");
include("Play_T2S.php");
include("Radio.php");
include("Restore_T2S.php");
include("Save_T2S.php");
include("Speaker.php");
include("follow.php");
include("bin/MpegAudio.php");
include("bin/MpegAudioFrameHeader.php");
include('system/logging.php');
include('system/bin/openssl_file.class.php');

register_shutdown_function('shutdown');

// setze korrekte Zeitzone
date_default_timezone_set(date("e"));

# prepare variables
$home = $lbhomedir;
$hostname = gethostname();										// hostname LoxBerry
$myIP = LBSystem::get_localip();								// get IP of LoxBerry
$syntax = $_SERVER['REQUEST_URI'];								// get syntax
$psubfolder = $lbpplugindir;									// get pluginfolder
$lbversion = LBSystem::lbversion();								// get LoxBerry Version
$path = LBSCONFIGDIR; 											// get path to general.cfg
$myFolder = "$lbpconfigdir";									// get config folder
$pathlanguagefile = "$lbphtmldir/voice_engines/langfiles/";		// get languagefiles
$logpath = "$lbplogdir/$psubfolder";							// get log folder
$templatepath = "$lbptemplatedir";								// get templatedir
$t2s_text_stand = "t2s-text_en.ini";							// T2S text Standardfile
$sambaini = $lbhomedir.'/system/samba/smb.conf';				// path to Samba file smb.conf
$searchfor = '[plugindata]';									// search for already existing Samba share
$MP3path = "mp3";												// path to preinstalled numeric MP3 files
$sleeptimegong = "3";											// waiting time before playing t2s
$sleepaddmember = "2";											// waiting time in seconds during adding member to master (decimal allowed)
$maxzap = '60';													// waiting time before zapzone been initiated again
$sPassword = 'loxberry';
$lbport = lbwebserverport();									// get loxberry port
// Temp Files in RAM vor ceratin functions
$lastVol = "/run/shm/s4lox_PhoneMute.log";						// File for phonemute/phoneunmute function
$lastExeLog = "/run/shm/s4lox_LastExeSonosInfo.log";			// File if old function getsonosinfo been called
$tmp_tts = "/run/shm/s4lox_tmp_tts";							// path/file for T2S functions
$tmp_phone = "/run/shm/s4lox_tmp_phonemute.tmp";				// path/file for phonemute function
$off_file = $lbplogdir."/s4lox_off.tmp";						// path/file for script off
$alarm_off_file = $lbpdatadir."/s4lox_alarm_off.json";			// path/file for Alarms turned off
$tmp_error = "/run/shm/s4lox_errorMP3Stream.json";				// path/file for error message
$check_date = "/run/shm/s4lox_date";							// store date execution
$configfile	= "s4lox_config.json";								// configuration file
$maxvolfile	= "/run/shm/s4lox_max_volume.json";					// max Volume restriction
$zapname = "/run/shm/s4lox_zap_zone.json";						// queue.php: file containig running zones
$pltmp = "/run/shm/s4lox_pl_play_tmp_".$_GET['zone'].".json";	// queue.php: temp file for playlisten
$filenst = "/run/shm/s4lox_t2s_stat.tmp";						// Temp Statusfile für messages
$folfilePlOn = "$lbpdatadir/PlayerStatus/s4lox_on_";			// Folder and file name for Player Status
$debuggingfile = "$lbpdatadir/s4lox_debug_config.json";			// Folder and file name for Debug Config
$file = $lbphtmldir."/bin/check_player_dup.txt";				// File to check for duplicate player
$save_status_file = "s4lox_follow";								// Status file for follow function	
$vol_config	= "s4lox_vol_profiles";								// Config file for Volume Profiles function
$guid = "7bfca5bf-165e-419b-a4c1-a64b895e95d7";					// GUID for Sonos AudioClip Function


# Files for ONE-click functions
if (isset($_GET['zone']))  {
	$radiofav = "/run/shm/s4lox_fav_all_radio_".$_GET['zone'].".json";				// Radio Stations in PlayAllFavorites
	$queuetmp = "/run/shm/s4lox_fav_queue_tmp_".$_GET['zone'].".json";				// Temp file if function is running in PlayAllFavorites
	$favtmp = "/run/shm/s4lox_fav_fav_tmp_".$_GET['zone'].".json";					// Temp file if function is running in Play Specific Favorite
	$radiofavtmp = "/run/shm/s4lox_fav_all_radio_tmp_".$_GET['zone'].".json";		// Temp file to detect Radio Stations in PlayAllFavorites
	$queuetracktmp = "/run/shm/s4lox_fav_track_tmp_".$_GET['zone'].".json";			// Temp file if function is running in PlayTrack Favorites
	$queueradiotmp = "/run/shm/s4lox_fav_radio_tmp_".$_GET['zone'].".json";			// Radio Stations in PlayRadioFavorites
	$queuepltmp = "/run/shm/s4lox_fav_pl_tmp_".$_GET['zone'].".json";				// Temp file Playlists from Sonos Favorites
	$tuneinradiotmp = "/run/shm/s4lox_fav_tunein_radio_".$_GET['zone'].".json";		// Temp file for Favorit Radio Stations in TuneIn
	$sonospltmp = "/run/shm/s4lox_pl_sonos_tmp_".$_GET['zone'].".json";				// Temp file for Sonos Playlist
	$debugfile = $lbpdatadir."/s4lox_debug_meta_fav.json";							// Debug file of Browse
}

echo '<PRE>';

if (!isset($_GET['debug']))    {
	$params = [	"name" => "Sonos PHP",
				"filename" => "$lbplogdir/sonos.log",
				"append" => 1,
				"addtime" => 1,
				];
	$level = LBSystem::pluginloglevel();
} else {
	$heute = date("dmY"); 
	$files = glob($lbplogdir.'/s4lox_debug_*');
	foreach($files as $file) {
		@unlink($file);
	}
	$params = [	"name" => "Sonos PHP",
				"filename" => "$lbplogdir/s4lox_debug_".$heute.".log",
				"append" => 1,
				"addtime" => 1,
				"loglevel" => 7,
				];
	$level = "7";
	$time_start = microtime(true);
	
	#@unlink($lbplogdir."/SOAP-Log-".$heute.".log");
}

$log = LBLog::newLog($params);
$plugindata = LBSystem::plugindata();
$L = LBSystem::readlanguage("sonos.ini");
$ms = LBSystem::get_miniservers();

// prüfen ob User noch getsonosinfo in Nutzung hat
$check_info = urldecode($syntax);
if ($getsonos = strrpos($check_info, "getsonosinfo") != false)  {
	getsonosinfo();
	exit(0);
}

LOGSTART("PHP started");
LOGGING("sonos.php: called syntax: ".$myIP."".urldecode($syntax),5);

# Prüfung ob Script ausgeschaltet ist
$script_on = $_GET['action'];
if (file_exists($off_file) and $script_on != "on")  {
	LOGGING("sonos.php: Script is off",5);
	echo "sonos.php: Script is off! Please turn on using ...action=on";
	exit;
}


if ((isset($_GET['text'])) or (isset($_GET['messageid'])) or 
	(isset($_GET['sonos'])) or (isset($_GET['weather'])) or 
	(isset($_GET['abfall'])) or (isset($_GET['witz'])) or 
	(isset($_GET['pollen'])) or (isset($_GET['warning'])) or
	(isset($_GET['distance'])) or (isset($_GET['clock'])) or 
	(isset($_GET['calendar'])) or 
	(isset($_GET['playlist'])) or (isset($_GET['playlisturi'])) or
	(isset($_GET['albumuri'])) or (isset($_GET['file']))
	)  {
	# FIFO for T2S
	if (file_exists($tmp_tts))  {
		while (file_exists($tmp_tts))  {
			usleep(200000); // check every 200ms
		}
		LOGINF("sonos.php: Currently a T2S is running, we have to wait...");
		sleep(5);
	}
	# Exit during phonecall
	if (file_exists($tmp_phone))  {
		LOGINF("sonos.php: Currently a Phonecall is running, we abort...");
		exit(0);
	}
	
	# check if NULL or 0 has been entered (Loxone Status)
	if (isset($_GET['text']))  {
		if (($_GET['text'] === "null") or ($_GET['text'] === "0"))  {
			LOGGING("sonos.php: NULL or 0 or Text from Loxone Status has been entered, therefor T2S been skipped", 6);	
			exit;
		}
	}
}

	# check if any Favorite function has been executed, if not delete files
	if (($_GET['action'] == "playallfavorites") || ($_GET['action'] == "playtrackfavorites") || ($_GET['action'] == "playtuneinfavorites")
		|| ($_GET['action'] == "playradiofavorites") || ($_GET['action'] == "playsonosplaylist")|| ($_GET['action'] == "audioclip") || ($_GET['action'] == "say") || ($_GET['action'] == "playfavorite")
		|| ($_GET['action'] == "play") || ($_GET['action'] == "stop") || ($_GET['action'] == "toggle") || ($_GET['action'] == "playplfavorites")
		|| ($_GET['action'] == "next") || ($_GET['action'] == "previous") || ($_GET['action'] == "volume") || ($_GET['action'] == "pause") || ($_GET['action'] == "zapzone")
		|| (isset($_GET['volume']) === true) || ($_GET['action'] == "sendmessage") || ($_GET['action'] == "sonosplaylist") || ($_GET['action'] == "sendgroupmessage")
		|| (isset($_GET['keepvolume']) === true) || (isset($_GET['groupvolume']) === true) || ($_GET['action'] == "volumeup") || ($_GET['action'] == "gettransportinfo") || ($_GET['action'] == "volumedown")
		|| ($_GET['action'] == "leave") || ($_GET['action'] == "follow"))  
		{
		LOGGING("sonos.php: No Exception to delete TempFiles has been called", 7);
	} else {
		DeleteTmpFavFiles();
		LOGGING("sonos.php: Exception to delete TempFiles has been called. ONE-click functions are resetted!", 6);

	}

	
#-- Start Preparation ------------------------------------------------------------------
	
	// Laden der Konfigurationsdatei s4lox_config.json
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		LOGGING("sonos.php: No Exception to delete TempFiles has been called", 2);
		exit;
	}
	#print_r($config);	
	// Übernahme und Deklaration von Variablen aus der Konfiguration
	$sonoszonen = $config['sonoszonen'];

	// check for player duplicates based on roomname once
	/**
	if (is_file($file))  {
		$player = array();
		foreach($sonoszonen as $checkzone)     {
			array_push($player, $checkzone[0]);
		}
		$invalid_player = validate_player($player);
		#print_r($invalid_player);
		if (count($invalid_player) > 0)    {
			LOGGING("sonos.php: Minimum ONE Player has a duplicate (Roomname), this may cause problems! Please remove them from your config, rename them in Sonos App and re-scan for the Player",3);
		}
	}
	**/
	
	// prüft den Onlinestatus jeder Zone
	#exec('/usr/bin/php -f bin/check_on_state.php');
	$sonoszone = array();
	$zonesonline = array();
	LOGGING("sonos.php: Backup Online check for Players will be executed",7);
	foreach($sonoszonen as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		#var_dump($handle);
		if($handle === true) {
			$sonoszone[$zonen] = $ip;
			array_push($zonesonline, $zonen);
		}
	}

	# check if valid zone has been entered
	if (!array_key_exists($_GET['zone'], $sonoszone))  {
		LOGGING("sonos.php: Requested ...zone=".$_GET['zone']." seems to be Offline. Check your Power/Onlinestatus.",4);
		exit;
	}
	LOGGING("sonos.php: All variables has been collected",7);
	
	# To predict any T2S if T2S has been turned off, except the T2S is marked with &urgent
	if (is_disabled($config['TTS']['t2son']) and (!isset($_GET['urgent'])))   {
		if ((isset($_GET['text'])) or (isset($_GET['messageid'])) )   {
			LOGGING("sonos.php: Text-to-speech blocked because T2S function has been turned off via Plugin Config!", 4);
			exit(1);
		}
	}
	
	# check if LBPort already exist in config, if not force user to save config
	$checklb = explode(':', $config['SYSTEM']['httpinterface']);
	$checklbport = explode('/', $checklb[2]);
	if ($checklbport[0] <> $lbport)  {
		LOGGING(htmlspecialchars($L['ERRORS.ERR_CHECK_LBPORT']), 3);
		exit;
	}
	# select language file for text-to-speech
	$t2s_langfile = "t2s-text_".substr($config['TTS']['messageLang'],0,2).".ini";			// language file for text-speech

	# Standardpath for saving MP3
	$MessageStorepath = $config['SYSTEM']['ttspath'];
	$min_vol = $config['TTS']['phonemute'];													// min.vol as exception for current volume
	$min_sec = $config['TTS']['waiting'];													// min. in seconds before same mp3 been played again (Statubsaustein)
	create_symlinks();
	#volume_group();
	
	
		
#-- End Preparation ---------------------------------------------------------------------


#-- Start allgemeiner Teil ----------------------------------------------------------------------

$valid_playmodes = array("NORMAL","REPEAT_ALL","REPEAT_ONE","SHUFFLE_NOREPEAT","SHUFFLE","SHUFFLE_REPEAT_ONE");

# Start des eigentlichen Srcipts

if (isset($_GET['profile']) and isset($_GET['volume']))   {
	LOGGING("sonos.php: optional parameter 'volume' in conjunction with 'profile' could not be used. Please correct your syntax!", 3);
	exit;
}

# volume for master
if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100 or isset($_GET['keepvolume'])) {
	# volume get from syntax
	if (isset($_GET['volume']))  {
		$volume = $_GET['volume'];
		$master = $_GET['zone'];
		// prüft auf Max. Lautstärke und korrigiert diese ggf.
		if($volume >= $sonoszone[$master][5]) {
			$volume = $sonoszone[$master][5];
			LOGGING("sonos.php: Volume for Player ".$master." has been reduced to: ".$volume, 7);
		} else {
			LOGGING("sonos.php: Volume for Player ".$master." has been set to: ".$volume, 7);
		}
	# current volume should be used
	} elseif (isset($_GET['keepvolume']))  {
		$master = $_GET['zone'];
		$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
		$tm_volume = $sonos->GetVolume();
		# if current volume is less then treshold then take standard from config
		if ($tm_volume >= $min_vol)  {
			$volume = $tm_volume;
			LOGGING("sonos.php: Volume for Player ".$master." has been set to current volume", 7);
		} else {
			if (isset($_GET['text']) or isset($_GET['messageid']) or
				(isset($_GET['sonos'])) or (isset($_GET['weather'])) or 
				(isset($_GET['abfall'])) or (isset($_GET['witz'])) or 
				(isset($_GET['pollen'])) or (isset($_GET['warning'])) or
				(isset($_GET['distance'])) or (isset($_GET['clock'])) or 
				(isset($_GET['calendar'])) or ($_GET['action'] == "playbatch"))	{
				$volume = $sonoszone[$master][3];
				LOGGING("sonos.php: T2S Volume for Player ".$master." is less then ".$min_vol." and has been set exceptional to Standard volume ".$config['sonoszonen'][$master][3], 7);
			} else {
				$volume = $sonoszone[$master][4];
				LOGGING("sonos.php: Volume for Player ".$master." is less then ".$min_vol." and has been set exceptional to Standard volume ".$config['sonoszonen'][$master][4], 7);
			}
		}
	}
} else {
	if (!isset($_GET['volume']) && !isset($_GET['profile']))   {
		# use standard volume from config
		$master = $_GET['zone'];
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$tmp_vol = $sonos->GetVolume();
		// prüft auf Max. Lautstärke und korrigiert diese ggf.
		if ($tmp_vol >= $sonoszone[$master][5]) {
			$volume = $sonoszone[$master][5];
		}
		if (isset($_GET['text']) or isset($_GET['messageid']) or
			(isset($_GET['sonos'])) or (isset($_GET['weather'])) or 
			(isset($_GET['abfall'])) or (isset($_GET['witz'])) or 
			(isset($_GET['pollen'])) or (isset($_GET['warning'])) or
			(isset($_GET['distance'])) or (isset($_GET['clock'])) or 
			(isset($_GET['calendar'])) or ($_GET['action'] == "playbatch"))	{
			$volume = $sonoszone[$master][3];
			LOGGING("sonos.php: Standard T2S Volume for Player ".$master." has been set to: ".$volume, 7);
		} else {
			$volume = $sonoszone[$master][4];
			LOGGING("sonos.php: Standard Sonos Volume for Player ".$master." has been set to: ".$volume, 7);
		}
	} elseif (isset($_GET['profile']) or isset($_GET['Profile']))   {
		VolumeProfiles();
	}
}

if(isset($_GET['playmode'])) { 
	$playmode = preg_replace("/[^a-zA-Z0-9_]+/", "", strtoupper($_GET['playmode']));
	$sonos = new SonosAccess($sonoszone[$master][0]);
	if (in_array($playmode, $valid_playmodes)) {
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		SetPlaymodes($master, $playmode);
		LOGGING('sonos.php: PlayMode "'.$playmode.'" has been set for Player "'.$master.'"', 7);
	}  else {
		LOGGING('sonos.php: incorrect PlayMode selected. Please correct!', 4);
	}
}   

# Verzögerung für Befehl
if(isset($_GET['wait'])) { 
	delay();
} 

# Timer für Befehl
if(isset($_GET['timer'])) { 
	sleeptimer();
}

if(isset($_GET['rampto'])) {
		switch($_GET['rampto'])	{
			case 'sleep';
				$config['TTS']['rampto'] = "SLEEP_TIMER_RAMP_TYPE";
				break;
			case 'alarm';
				$config['TTS']['rampto'] = "ALARM_RAMP_TYPE";
				break;
			case 'auto';
				$config['TTS']['rampto'] = "AUTOPLAY_RAMP_TYPE";
				break;
		}
	} else {
		switch($config['TTS']['rampto']) {
			case 'sleep';
				$config['TTS']['rampto'] = "SLEEP_TIMER_RAMP_TYPE";
				break;
			case 'alarm';
				$config['TTS']['rampto'] = "ALARM_RAMP_TYPE";
				break;
			case 'auto';
				$config['TTS']['rampto'] = "AUTOPLAY_RAMP_TYPE";
				break;
		}
	}

if(array_key_exists($_GET['zone'], $sonoszone)){ 

	global $json;
	
	$master = $_GET['zone'];
	$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
	switch($_GET['action'])	{
		case 'play';
			$posinfo = $sonos->GetPositionInfo();
			if(!empty($posinfo['TrackURI'])) {
				if(empty($config['TTS']['volrampto'])) {
					$config['TTS']['volrampto'] = "25";
					LOGGING("sonos.php: Rampto Volume in config has not been set. Default of 25% Volume has been taken, please update Plugin Config (T2S Optionen).", 4);
				}
				if($sonos->GetVolume() <= $config['TTS']['volrampto']) {
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					checkifmaster($master);
					$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
					$pos = $sonos->GetPositionInfo();
					if (substr($posinfo["TrackURI"], 0, 18) === "x-sonos-htastream:")  {
						$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
					}
					if (substr($posinfo["UpnpClass"], 0, 32) === "object.item.audioItem.musicTrack")  {
						#$sonos->ClearQueue();
						$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
					}
					$sonos->Play();
				} else {
					checkifmaster($master);
					$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
					if (substr($posinfo["TrackURI"], 0, 18) === "x-sonos-htastream:")  {
						$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
					}
					if (substr($posinfo["UpnpClass"], 0, 32) === "object.item.audioItem.musicTrack")  {
						#$sonos->ClearQueue();
						$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
					}
					$sonos->Play();
				}
			} else {
				LOGGING("sonos.php: No tracks in Queue to be played.", 4);
			}
		break;
		
		case 'pause';
			checkifmaster($master);
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->Pause();
			LOGGING("sonos.php: Pause been executed.", 7);
		break;

		case 'next';
			$titelgesammt = $sonos->GetPositionInfo();
			$titelaktuel = $titelgesammt["Track"];
			$playlistgesammt = count($sonos->GetCurrentPlaylist());
			if ($titelaktuel < $playlistgesammt) {
			#if (($titelaktuel < $playlistgesammt) or (substr($titelgesammt["TrackURI"], 0, 9) == "x-rincon:")) {
				checkifmaster($master);
				$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
				@NextTrack();
			#} else {
			#	checkifmaster($master);
			#	$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			#	$sonos->SetTrack("1");
			#	LOGGING("sonos.php: Last track been played.", 7);
			}
			#LOGGING("sonos.php: Next been executed.", 7);
		break;
		
		
		case 'previous';
			$titelgesammt = $sonos->GetPositionInfo();
			$titelaktuel = $titelgesammt["Track"];
			$playlistgesammt = count($sonos->GetCurrentPlaylist());
			if ($titelaktuel <> '1') {
				checkifmaster($master);
				$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->Previous();
				LOGGING("sonos.php: Previous been executed.", 7);
			#} else {
			#	checkifmaster($master);
			#	$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			#	$sonos->SetTrack("$playlistgesammt");
			}
			#LOGGING("sonos.php: Previous been executed.", 7);
		break; 
		
			
		case 'rewind':
			checkifmaster($master);
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->Rewind();
			LOGGING("sonos.php: Rewind been executed.", 7);
		break;
		
		
		case 'mute';
			if($_GET['mute'] == 'false') {
				$sonos->SetMute(false);
			}
			else if($_GET['mute'] == 'true') {
				$sonos->SetMute(true);
			} else {
				LOGGING('sonos.php: Wrong Mute Parameter selected. Please correct', 3);
				exit;
			}       
			LOGGING("sonos.php: Mute/Unmute been executed.", 7);
		break;
		
		case 'togglemute';
			$mute = $sonos->GetMute();
			if($mute === true) {
				$sonos->SetMute(false);
			} else {
				$sonos->SetMute(true);
			}       
			LOGGING("sonos.php: Mute/Unmute been executed.", 7);
		break;
		
		
		case 'phonemute';
			phonemute();
		break;
		
		
		case 'phoneunmute';
			phoneunmute();
		break;
		
		
		case 'stop';
			checkifmaster($master);
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->Stop();
			LOGGING("sonos.php: Stop been executed.", 7);
		break; 
		
			
		case 'stopall';
			foreach ($sonoszone as $zone => $player) {
				checkifmaster($zone);
				$sonos = new SonosAccess($sonoszone[$zone][0]);
				$state = $sonos->GetTransportInfo();
				#echo $state."<br>";
				if ($state == '1') {
					$return = getZoneStatus($zone); // get current Zone Status (Single, Member or Master)
					if($return <> 'member') {
						$sonos->Pause();
					}
				}
			}
			LOGGING("sonos.php: Stop/Pause all been executed.", 7);
		break; 
		

		case 'softstop':
			$save_vol_stop = $sonos->GetVolume();
			$sonos->RampToVolume("SLEEP_TIMER_RAMP_TYPE", "0");
			while ($sonos->GetVolume() > 0) {
				sleep('1');
			}
			checkifmaster($master);
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			try {			
				$sonos->Pause();
			} catch (Exception $e) {
				$sonos->Stop();
			}
			$sonos->SetVolume($save_vol_stop);
			LOGGING("sonos.php: Softstop been executed.", 7);
		break; 
		
		
		case 'softstopall':
		foreach ($sonoszone as $zone => $player) {
			checkifmaster($zone);
			$sonos = new SonosAccess($sonoszone[$zone][0]);
			$state = $sonos->GetTransportInfo();
			if ($state == '1') {
				$return = getZoneStatus($zone); // get current Zone Status (Single, Member or Master)
				if($return <> 'member') {
					//echo $zone."<br>";
					$save_vol_stop = $sonos->GetVolume();
					$sonos->RampToVolume("SLEEP_TIMER_RAMP_TYPE", "0");
					while ($sonos->GetVolume() > 0) {
						sleep('1');
					}
					checkifmaster($zone);
					$sonos = new SonosAccess($sonoszone[$zone][0]); //Sonos IP Adresse
					$sonos->Pause();
					$sonos->SetVolume($save_vol_stop);
				}
			}
		}
		LOGGING("sonos.php: Softstopall been executed.", 7);
		break; 
		
		  
		case 'toggle':
			checkifmaster($master);
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			if($sonos->GetTransportInfo() == 1)  {
				$sonos->Pause();
			} else {
				$sonos->Play();
			}
			LOGGING("sonos.php: Toggle been executed.", 7);
		break; 
		
					
		case 'playmode';
			// see valid_playmodes under Configuration section for a list of valid modes
			if (in_array($playmode, $valid_playmodes)) {
				$mode = SetPlaymodes($master, $playmode);
				echo "playmode: ".$mode;
				LOGGING("sonos.php: Playmode '".$playmode."' for Player '".$master."' has been executed.", 7);
			} else {
				LOGGING('sonos.php: Wrong PlayMode Parameter selected. Please correct', 4);
			}   
			#LOGGING("sonos.php: Playmode been executed.", 7);
		break; 
		
	  
		case 'crossfade':
			if((is_numeric($_GET['crossfade'])) && ($_GET['crossfade'] == 0) || ($_GET['crossfade'] == 1)) { 
				$crossfade = $_GET['crossfade'];
			} else {
				LOGGING("sonos.php: Wrong Crossfade entered -> 0 = off / 1 = on", 4);
				exit;
			}
				$sonos->SetCrossfadeMode($crossfade);
				LOGGING("sonos.php: Crossfade been executed.", 7);
		break; 
		
		  
		case 'remove':
			if(is_numeric($_GET['remove'])) {
				checkifmaster($master);
				$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->RemoveFromQueue($_GET['remove']);
				LOGGING("sonos.php: Remove Song been executed.", 7);
			} 
		break; 
		
		
		case 'playqueue':
			$titelgesammt = $sonos->GetPositionInfo();
			$titelaktuel = $titelgesammt["Track"];
			$playlistgesammt = count($sonos->GetCurrentPlaylist());
						
			if ($titelaktuel < $playlistgesammt) {
			$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$master][1]) . "#0");
				if(empty($config['TTS']['volrampto'])) {
					$config['TTS']['volrampto'] = "25";
					LOGGING("sonos.php: Rampto Volume in config has not been set. Default of 25% Volume has been taken, please update Plugin Config (T2S Optionen).", 4);
				}
				if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					$sonos->Play();
				} else{
					$sonos->Play();
				}
			} else {
				LOGGING("sonos.php: No tracks in Playlist to play.", 5);
			}
			LOGGING("sonos.php: Playqueue been executed.", 7);
		break;
		
		
		case 'clearqueue':
			$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$master][1]) . "#0");
			checkifmaster($master);
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->ClearQueue();
			LOGGING("sonos.php: Queue has been cleared",7);
		break;
		
		  
		case 'volume':
			if(isset($volume)) {
				$sonos->SetVolume($volume);
			} else {
				LOGGING('sonos.php: Wrong range of values for volume been entered, only 0-100 is permitted', 4);
				exit;
			}
		break; 
		
		  
		case 'volumeup': 
			$volume = $sonos->GetVolume();
			$volume = $volume + $config['MP3']['volumeup'];
			$sonos->SetVolume($volume);
		break;
		
		case 'grvolup': 
			$sonos = new SonosAccess($sonoszone[$master][0]); 
			$volume = $sonos->GetGroupVolume();
			$volumenew = $volume + $config['MP3']['volumeup'];
			sleep(1);
			SetGroupVolume($volumenew);
		break;
		
			
		case 'volumedown':
			$volume = $sonos->GetVolume();
			$volume = $volume - $config['MP3']['volumedown'];
			$sonos->SetVolume($volume);
		break;
		
		case 'grvoldown':
			$sonos = new SonosAccess($sonoszone[$master][0]); 
			$volume = $sonos->GetGroupVolume();
			$volumenew = $volume - $config['MP3']['volumeup'];
			sleep(1);
			SetGroupVolume($volumenew);
		break;
		
			
		case 'setloudness':
			if(($_GET['loudness'] == 1) || ($_GET['loudness'] == 0)) {
				$loud = $_GET['loudness'];
				$sonos->SetLoudness($loud);
			} else {
				LOGGING('sonos.php: Wrong Loudness Mode selected', 5);
			}    
			LOGGING("sonos.php: Loudness been executed.", 7);
		break;
		
		
		case 'settreble':
			$Treble = $_GET['treble'];
			$sonos->SetTreble($Treble);
			LOGGING("sonos.php: Treble been executed.", 7);
		break;
		
		
		case 'setbass':
			$Bass = $_GET['bass'];
			$sonos->SetBass($Bass);
			LOGGING("sonos.php: Bass been executed.", 7);
		break;	
		
		
		case 'addmember':
			AddMemberTo();
		break;

		
		case 'removemember':
			removemember();
		break;
		
		case 'nextdynamic':
			next_dynamic();
		break;
		
		
		case 'nextpush':
			checkifmaster($master);
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			$posinfo = $sonos->GetPositionInfo();
			#print_r($posinfo);
			
			// Nichts läuft
			if ((empty($posinfo['TrackURI'])) and (empty($posinfo["UpnpClass"])))  {
				nextradio();
				LOGDEB("sonos.php Nextpush has been executed. Queue was empty");
			}
			// Radio läuft
			if ($posinfo["UpnpClass"] === "object.item")  {
				nextradio();
				LOGDEB("sonos.php Nextpush has been executed. Radio Station was running");
			}
			// TV läuft
			if (substr($posinfo["TrackURI"], 0, 18) === "x-sonos-htastream:")  {
				nextradio();
				LOGDEB("sonos.php Nextpush has been executed. TV was running");
			}
			// Playliste läuft
			if ((!empty($posinfo['TrackURI'])) and (!empty($posinfo['TrackDuration'])))  {
				LOGDEB("sonos.php Nextpush has been executed. Playlist was running");
				next_dynamic();
			}
			if (isset($_GET['profile']) or isset($_GET['Profile']))    {
				$volume = $lookup[0]['Player'][$master][0]['Volume'];
			} 
			$sonos->SetVolume($volume);
		break;
		
		
		case 'nextradio':
			checkifmaster($master);
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			nextradio();
		break;
		
							  
		case 'sonosplaylist':
			playlist();
		break;
		
		  
		case 'groupsonosplaylist':
			playlist();
		break;
		

		case 'radioplaylist':
			radio();
		break;
		

		case 'groupradioplaylist': 
			radio();
		break;
		
		
		case 'radio': 
			radio();
		break;
		
		
		case 'playlist':
			playlist();
		break;
		
			
		case 'info':
			info();
		break;
      
    
		case 'cover':
			cover();
		break;   
			
			
		case 'title':
			title();
		break;   
				 
			
		case 'artist':
			artist();
		break;   
			
				 
		case 'album':
			album();
		break;

			
		case 'titelinfo':
			titelinfo();
		break;
		

		case 'sendgroupmessage':
			#global $sonos, $coord, $text, $min_sec, $member, $master, $zone, $messageid, $logging, $words, $voice, $accesskey, $secretkey, $rampsleep, $config, $save_status, $mute, $membermaster, $groupvol, $checkgroup;
			LOGGING("sonos.php: function 'action=sendgroupmessage...' has been depreciated. Please change your syntax to 'action=say...'", 6); 
			$oldtext="old";
			$newtext="new";
			$last=time();

			if (isset($_GET["text"])) $newtext=$_GET["text"];

			if (file_exists($filenst)) {
				$last=time()-filemtime($filenst);
				$myfile = fopen($filenst, "r") or LOGGING('sonos.php: Unable to open file!', 4);
				$oldtext=fread($myfile,8192);
				fclose($myfile);
				unlink($filenst);
			}	
			if ((($oldtext==$newtext) AND( $last > $min_sec)) OR ($oldtext!=$newtext))  {
				if(isset($_GET['clip'])) {
					LOGGING("sonos.php: Audioclip Notification for group messages is not available.", 3);
					exit;
				} else {
					say();
					LOGGING("sonos.php: we switch to function 'say'", 5);
					$myfile = fopen($filenst, "w") or LOGGING('sonos.php: Unable to open file!', 4);
					fwrite($myfile,$newtext);
					fclose($myfile);
				}
			} else {
				LOGGING("sonos.php: Same text has been announced within the last ".$min_sec." seconds. We skip this anouncement", 5); 
			}
		break;
			
			
		case 'sendmessage':
			#global $text, $coord, $master, $min_sec, $messageid, $logging, $words, $voice, $config, $actual, $player, $volume, $coord, $time_start;
			LOGGING("sonos.php: function 'action=sendmessage...' has been depreciated. Please change your syntax to 'action=say...'", 6); 
			$oldtext="old";
			$newtext="new";
			$last=time();

			if (isset($_GET["text"])) $newtext=$_GET["text"];

			if (file_exists($filenst)) {
				$last=time()-filemtime($filenst);
				$myfile = fopen($filenst, "r") or LOGGING('sonos.php: Unable to open file!', 4);
				$oldtext=fread($myfile,8192);
				fclose($myfile);
				unlink($filenst);
			}	
			if ((($oldtext==$newtext) AND( $last > $min_sec)) OR ($oldtext!=$newtext))  {
				if(isset($_GET['clip'])) {
					LOGGING("sonos.php: we switch to function 'say'", 5);
					say();
				} else {
					LOGGING("sonos.php: we switch to function 'say'", 5);
					say();
					$myfile = fopen($filenst, "w") or LOGGING('sonos.php: Unable to open file!', 4);
					fwrite($myfile,$newtext);
					fclose($myfile);
				}	
			} else {
				LOGGING("sonos.php: Same text has been announced within the last ".$min_sec." seconds. We skip this anouncement", 5); 
			}
		break;

		case 'audioclip':
			LOGDEB("sonos.php: audioclip called");
			say();
		break;
		
		case 'doorbell':
			LOGDEB("sonos.php: Doorbell called");
			doorbell(); 
		break;
		
		case 'say':
			$oldtext="old";
			$newtext="new";
			$last=time();

			if (isset($_GET["text"])) $newtext=$_GET["text"];

			if (file_exists($filenst)) {
				$last=time()-filemtime($filenst);
				$myfile = fopen($filenst, "r") or LOGGING('sonos.php: Unable to open file!', 4);
				$oldtext=fread($myfile,8192);
				fclose($myfile);
				unlink($filenst);
			}	
			if ((($oldtext==$newtext) AND( $last > $min_sec)) OR ($oldtext!=$newtext))  {
				say();
				$myfile = fopen($filenst, "w") or LOGGING('sonos.php: Unable to open file!', 4);
				fwrite($myfile,$newtext);
				fclose($myfile);
			} else {
				LOGGING("sonos.php: Same text has been announced within the last ".$min_sec." seconds. We skip this anouncement", 5); 
			}
		break;
		
		
		case 'group':
			group_all();
		break;
		
			
		case 'ungroup':
			ungroup_all();
		break;
		
		

	
	# Debug Bereich ------------------------------------------------------

		case 'checksonos':
				echo '<PRE>';
				echo 'Test GetMediaInfo()';
				echo '<br>';
				print_r($sonos->GetMediaInfo());
				echo '<br><br>';
				echo 'Test GetPositionInfo()';
				echo '<br>';
				print_r($sonos->GetPositionInfo());
				echo '<br><br>';
				echo 'Test GetTransportSettings()';
				echo '<br>';
				print_r($sonos->GetTransportSettings());
				echo '<br><br>';
				echo 'Test GetTransportInfo()';
				echo '<br>';
				print_r($sonos->GetTransportInfo());
				echo '<br><br>';
				echo 'Test GetZoneAttributes()';
				echo '<br>';
				print_r($sonos->GetZoneAttributes());
				echo '<br><br>';
				echo 'Test GetZoneGroupAttributes()';
				echo '<br>';
				print_r($sonos->GetZoneGroupAttributes());
				echo '<br><br>';
				echo '</PRE>';
			LOGGING("sonos.php: Checksonos been executed.", 7);
		break;
		
			
		case 'getzonestatus':
			print_r(getZoneStatus($master));
		break;
			

		case 'getmediainfo':
			echo '<PRE>';
			print_r($sonos->GetMediaInfo());
			echo '</PRE>';
		break;
		

		case 'getmute':
			echo '<PRE>';
			print_r($sonos->GetMute());
			echo '</PRE>';
		break;


		case 'getpositioninfo':
			echo '<PRE>';
			print_r($sonos->GetPositionInfo());
			echo '</PRE>';
		break; 
		

		case 'gettransportsettings':
			echo '<PRE>';
			print_r($sonos->GetTransportSettings());
			echo '</PRE>';
		break; 
		
		case 'getdialoglevel':
			Getdialoglevel();
		break;
		
		
		case 'getzonegroupstate':
			GetZoneState();
		break;
		
		
		case 'gettransportinfo':
			# 1 = PLAYING
			# 2 = PAUSED_PLAYBACK
			# 3 = STOPPED

				echo '<PRE>';
					print_r($sonos->GetTransportInfo());
				echo '</PRE>';
			break;        
		
		case 'getradiotimegetnowplaying':
			echo '<PRE>';
			$radio = $sonos->RadiotimeGetNowPlaying();
			print_r($radio);
			echo '</PRE>';
		break;

		  
		case 'getvolume':
			echo '<PRE>';
			print_r($sonos->GetVolume());
			echo '</PRE>';
		break;
			
		
		case 'setmaxvolume':
			# Sets in combination with cronjob the volume per zone to max.
			if (is_enabled($config['VARIOUS']['volmax']))   {
				if (isset($_GET['volume']))  {
					$maxvol = $_GET['volume'];
					$zonesf['volume'] = $maxvol;
					$zonesf['zones'][] = $sonoszonen[$master][0];
					if (isset($_GET['member']))  {
						$mem = explode(",", $_GET['member']);
						foreach ($mem as $mem3)    {
							array_push($zonesf['zones'], $sonoszone[$mem3][0]);
						}
					}
					file_put_contents($maxvolfile, json_encode($zonesf));
				}
				if (isset($_GET['reset']))  {
					@unlink($maxvolfile);
				}
				LOGGING("Max. Volume has been set to: ".$maxvol, 5);
			} else {
				LOGGING("Function to set max. Volume is turned off! Please turn on in Sonos Plugin Config", 3);
			}
		break;

		case 'testing':
			$meta = '&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;'.$rand.'cexplore%3aplaylist%3a%3app.'.$pl.'&quot; parentID=&quot;'.$rand.'explore%3atag%3aplaylists%3a%3atag.156763213&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;/dc:title&gt;&lt;upnp:class&gt;object.container.playlistContainer&lt;/upnp:class&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;'.$reg.'&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;';
			print_R($meta);
		break;

		case 'getfavorites':
			echo '<PRE>';
			GetFavorites();
			echo '</PRE>';
		break;

		case 'browse':
			echo '<PRE>';
			#$test = $sonos->GetFavorites("FV:2","BrowseDirectChildren");				// browse Sonos favorites
			$test = AddDetailsToMetadata();												// browse Sonos favorites + Service and sid
			#$test = $sonos->BrowseContentDirectory("R:0/0","BrowseDirectChildren");	// browse TuneIn/Meine Radiosender
			#$test = $sonos->BrowseContentDirectory("R:0/1","BrowseDirectChildren");	// 
			#$test = $sonos->GetFavorites("Q:0","BrowseDirectChildren");				// browse current Queue
			#$test = $sonos->GetFavorites("S:","BrowseDirectChildren");					// browse local Playlists
			#$test = $sonos->GetFavorites("SQ:","BrowseDirectChildren");				// browse Sonos Playlists
			#$test = "";
			print_r($test);
			echo "<br>";
		break;		
		
		case 'playfavorite':
			PlayFavorite();
		break;	
		
		case 'playallfavorites':
			PlayAllFavorites();
		break;

		case 'playtrackfavorites':
			PlayTrackFavorites();
		break;	

		case 'playradiofavorites':
			PlayRadioFavorites();
		break;

		case 'playsonosplaylist':
			PlaySonosPlaylist();
		break;	

		case 'playtuneinfavorites':
			PlayTuneInPlaylist();
		break;
		
		case 'playplfavorites':
			PlayPlaylistFavorites();
		break;
		
		case 'savesonos':
			$favorites 	= AddDetailsToMetadata();
			$tunein 	= $sonos->BrowseContentDirectory("R:0/0","BrowseDirectChildren");
			$sonospl	= $sonos->BrowseContentDirectory("SQ:","BrowseDirectChildren");
			$debugdata  = array_merge($favorites, $tunein, $sonospl);
			file_put_contents($debugfile, json_encode($debugdata));
			LOGOK ("sonos.php: File '".$debugfile."' has been saved");
		break;
		
		case 'currstatus':
			$stat = saveZonesStatus();
			print_r($stat);
		break;

		case 'masterplayer':
			Global $zone, $master;	
			foreach ($sonoszone as $player => $ip) {
				$sonos = new SonosAccess($ip[0]); //Slave Sonos ZP IPAddress
				$temp = $sonos->GetPositionInfo($ip);
				foreach ($sonoszone as $masterplayer => $ip) {
					# hinzugefügt am 18.01 weil Fehler bei Gruppierung auflösen
					$masterrincon = substr($temp["TrackURI"], 9, 24);
					if(trim($sonoszone[$masterplayer][1]) == $masterrincon) {
						echo "<br>" . $player . " -> ";
						echo "Master des Players: " . $masterplayer;
					}
				}
				$masterrincon = "";
			}
			return $player;
			return $masterplayer;
		break;
		
		case 'radiourl':
			$GetPositionInfo = $sonos->GetPositionInfo();
			echo "Die Radio URL lautet: " . $GetPositionInfo["URI"];
		break;
		
		case 'add':
			AddDetailsToMetadata();
		break;
		
		
		case 'becomegroupcoordinator':
			echo '<PRE>';
			$sonos->BecomeCoordinatorOfStandaloneGroup();
			LOGGING("sonos.php: Zone ".$master." is now in single mode", 7);
			echo '</PRE>';
		break;
		
		
		case 'getgroupmute':
			$GetGroupMute = $sonos->GetGroupMute();
		break;
		
		
		case 'setgroupmute':
			if(($_GET['mute'] == 1) || ($_GET['mute'] == 0)) {
				$mute = $_GET['mute'];
				$sonos->SetGroupMute($mute);
				LOGGING("sonos.php: Group Mute has been set to ".$mute, 6);
			} else {
				LOGGING("sonos.php: Unknown Group Mute!", 4);
			}
		break;
		

		case 'getgroupvolume':
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$sonos->SnapshotGroupVolume();
			$GetGroupVolume = $sonos->GetGroupVolume();
			echo $GetGroupVolume;
		break;
		
		case 'setgroupvolume':
			$groupvolume = $_GET['volume'];
			SetGroupVolume($groupvolume);
			LOGGING("sonos.php: Group Volume has been set", 6);
		break;
		
		
		case 'setrelativegroupvolume':
			$groupvolume = $_GET['volume'];
			SetRelativeGroupVolume($groupvolume);
			LOGGING("sonos.php: Relative Group Volume has been set", 6);
		break;
		
		
		case 'snapshotgroupvolume':
			$SnapshotGroupVolume = $sonos->SnapshotGroupVolume();
		break;
		
		
		case 'groupmanagement':
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$ip = $sonoszone[$master][0];
			$SubscribeZPGroupManagement = $sonos->SubscribeZPGroupManagement('http://'.$ip.':6666');
		break;
		
		
		case 'sleeptimer':
			sleeptimer();
		break;
		

		case 'getsonosplaylists':
			echo '<PRE>';
			print_r($sonos->GetSonosPlaylists());
			echo '</PRE>';
		break;
		
			
		case 'getaudioinputattributes':	
			echo '<PRE>';
			print_r($sonos->GetAudioInputAttributes());
			echo '</PRE>';
		break;
		
		
		case 'getzoneattributes':
			echo '<PRE>';
			print_r($sonos->GetZoneAttributes());
			echo '</PRE>';
		break;
		
		
		case 'getzonegroupattributes':
			echo '<PRE>';
			print_r($sonos->GetZoneGroupAttributes());
			echo '</PRE>';
		break;
		
		
		case 'getcurrenttransportactions':
			echo '<PRE>';
			print_r($sonos->GetCurrentTransportActions());
			echo '</PRE>';
		break;
		
		
		case 'getcurrentplaylist':
			echo '<PRE>';
			print_r($sonos->GetCurrentPlaylist());
			echo '</PRE>';
		break;
		
		
		case 'getimportedplaylists':
			echo '<PRE>';
			print_r($sonos->GetImportedPlaylists());
			echo '</PRE>';
		break;
		
		
		case 'listalarms':
			echo '<PRE>';
			$allAlarms = $sonos->ListAlarms();
			# add Minutes past Midnight to array
			foreach ($allAlarms as $key => $value)    {
				$ex = explode(":", $value['StartTime']);
				# calculate Mintues after midnight - 10 Minutes
				$result = (($ex[0] * 60) + $ex[1]) - 10 ;
				$allAlarms[$key]['minpastmid'] = $result;
			}
			# add Room Name to array
			foreach ($allAlarms as $key => $value)    {
				$rinc = $value['RoomUUID'];
				$search = recursive_array_search($rinc, $sonoszonen);
				if ($search === false)    {
					$allAlarms[$key]['Room'] = "NO ROOM";
					$allAlarms[$key]["min_".$search."_ID_".$allAlarms[$key]['ID']] = $allAlarms[$key]['minpastmid'];
					$allAlarms[$key]["stat_".$search."_ID_".$allAlarms[$key]['ID']] = $allAlarms[$key]['Enabled'];
				} else {
					$allAlarms[$key]['Room'] = $search;
					$allAlarms[$key]["min_".$search."_ID_".$allAlarms[$key]['ID']] = $allAlarms[$key]['minpastmid'];
					$allAlarms[$key]["stat_".$search."_ID_".$allAlarms[$key]['ID']] = $allAlarms[$key]['Enabled'];
				}
			}
			print_r($allAlarms);
			
			echo '</PRE>';
		break;
		
		
		case 'alarmoff':
			if (isset($_GET['id'])) {
				turn_off_alarm();
			} else {
				turn_off_alarms();
			}
		break;
		
		
		case 'alarmon':
			if (isset($_GET['id'])) {
				restore_alarm();
			} else {
				restore_alarms();
			}
		break;
		
			
		case 'getledstate':
			echo '<PRE>';
			die ($sonos->GetLEDState());
			echo '</PRE>';
		break;
		
		
		case 'setledstate':
			echo '<PRE>';
			if(($_GET['state'] == "On") || ($_GET['state'] == "Off")) {
				$state = $_GET['state'];
				$sonos->SetLEDState($state);
			} else {
				LOGGING('sonos.php: Please correct input. Only On or off is allowed', 4);
				echo '</PRE>';
			}
		break;
		
		
		#case 'getinvisible':
			#echo '<PRE>';
			#		print_r($sonos->GetInvisible());
			#echo '</PRE>';
		#break;
				
		
		case 'getcurrentplaylist':
			echo '<PRE>';
			print_r($sonos->GetCurrentPlaylist());
			echo '</PRE>';
		break;
		
		
		case 'getloudness':
			echo '<PRE>';
			print_r($sonos->GetLoudness());
			echo '</PRE>';
		break;
		
		
		case 'gettreble':
			echo '<PRE>';
			print_r($sonos->GetTreble());
			echo '</PRE>';
		break;
	
		
		case 'checkradiourl':
			$f = $lbphtnldir."/bin/url.php";
			include($f);
		break;
			
		case 'sayradio':
		$coord = getRoomCoordinator($master);
			$sonos = new SonosAccess($coord[0]);
			$temp = $sonos->GetPositionInfo();
			if(!empty($temp["duration"])) {
				LOGGING('sonos.php: No radio is playing!',4);
				exit;
			} else {
				say_radio_station();
				$sonos->SetMute(false);
				$sonos->SetVolume($volume);
				$sonos->Play();
			}
		break;
			
		case 'encrypt':
			#$sFilename = 'system/service';
			#OpenSSLFile::encrypt($sFilename, $sPassword);
		break;
		
		case 'decrypt':
			#$sFilename = 'system/service.dat';
			#OpenSSLFile::decrypt($sFilename, $sPassword);
		break;
		
		case 'getbass':
			echo '</PRE>';
			print_r($sonos->GetBass());
			echo '</PRE>';
		break;
		
		
		case 'clearlox': // Loxone Fehlerhinweis zurücksetzen
			clear_error();
		break;
		
		
		case 'zapzone':
			#zapzone();
			zap();
		break;
		

		case 'follow':
			follow();	
		break;
		
		
		case 'leave':
			leave();
		break;
				
		case 'createstereopair':
			CreateStereoPair();
		break;
		
		
		case 'seperatestereopair':
			SeperateStereoPair();
		break;
		
		
		case 'getroomcoordinator':
			getRoomCoordinator($master);
		break;
		
			
		case 'delcoord':
			$to = $_GET['to'];
			$newzone = $sonoszone[$to][1];
			$sonos->DelegateGroupCoordinationTo($newzone, 1);
		break;
		
		
		case 'networkstatus';
			networkstatus();
		break;  
		
		
		case 'getloxonedata':
			getLoxoneData();
		break;
		
		
		case 'grouping':
			Group($master);
		break;
		
		
		case 'getgroups':
			getGroups();
		break;
		
		
		case 'getgroup':
			getGroup();
		break;
		
		
		case 'save':
			saveZonesStatus();
		break;
		
		
		case 'addzones':
			addZones();
		break;
		
		
		case 'playbatch':
			t2s_playbatch();
		break;
		
		
		case 'alarmstop':
			$sonos->Pause();
			if(isset($_GET['member'])) {
				restoreGroupZone();
			} else {
				restoreSingleZone();
			}
		break;
		
		
		#case 'zapzones':
			#zapzone();
		#break;
		
		
		case 'calendar':
			include_once("addon/calendar.php");
			muellkalender();
		break;
		
		
		case 'linein':
			LineIn();
		break;
		
		
		case 'playfile':
			PlayAudioFile();
		break;
		
		
		case 'checkifmaster':
			checkifmaster($master);
		break;
		
		
		case 'volmode':
			$uuid = $sonoszone[$master][1];
			$test = $sonos->GetVolumeMode($uuid);
			#var_dump($test);
		break;
		
		
		case 'spotify':
			require_once("MusicService.php");
			AddSpotify();
		break;	
		
		
		case 'amazon':
			require_once("MusicService.php");
			AddAmazon();
		break;
		
		
		case 'google':
			require_once("MusicService.php");
			AddGoogle();
		break;	
		
		
		case 'apple':
			require_once("MusicService.php");
			AddApple();
		break;	
		
		
		case 'napster':
			require_once("MusicService.php");
			AddNapster();
		break;	
		
		
		case 'track':
			require_once("MusicService.php");
			AddTrack();
		break;	
		
		
		case 'randomplaylist':
			random_playlist();
		break;
		
		case 'pluginradio':
			PluginRadio();
		break;
		
		
		case 'randomradio':
			random_radio();
		break;
		
		case 'files':
			mp3_files();
		break;
		
		case 'balance':
			SetBalance();
		break;
		
		case 'off':
			scriptoff();
		break;
		
		case 'on':
			scripton();
		break;
		
		case 'battery':
			echo 'php '.$lbphtmldir.'/system/battery.php';
			$out = shell_exec('php '.$lbphtmldir.'/system/battery.php');
			echo "<br>";
			print_r($out);
		break;
		
		case 'batteryinfo':
			batteryinfo();
		break;
		
		case 'updateplayer':
			$output = shell_exec('php system/updateplayer.php');
			LOGGING("sonos.php: Player configuration has been updated :-)", 7);
		break;
		
		case 'resetbasic':
			$sonos->ResetBasicEQ();
			LOGGING("sonos.php: EQ Settings for Player ".$master." has been reset.", 7);
		break;
		
		case 'surround':
			SetSurroundMode();
		break;
		
		case 'subbass':
			SetBassMode();
		break;

		case 'speech':
			SetSpeechMode();
		break;
		
		case 'nightmode':
			SetNightMode();
		break;
		
		case 'getuuid':
			GetAutoplayRoomUUID();
		break;
		
		case 'setuuid':
			SetAutoplayRoomUUID();
		break;
		
		case 'getautolinkedzones':
			GetAutoplayLinkedZones();
		break;
		
		case 'setautolinkedzones':
			SetAutoplayLinkedZones();
		break;
		
		case 'getautoplayvolume':
			GetAutoplayVolume();
		break;
		
		case 'setautoplayvolume':
			SetAutoplayVolume();
		break;
		
		case 'getuseautoplayvolume':
			GetUseAutoplayVolume();
		break;
		
		case 'setuseautoplayvolume':
			SetUseAutoplayVolume();
		break;
		
		case 'getbuttonlockstate':
			GetButtonLockState();
		break;
		
		case 'setbuttonlockstate':
			SetButtonLockState();
		break;
		
		case 'debuginfo':
			debugInfo();
		break;
		
		case 'streammode':
			GetHtMode();
		break;
		
		case 'update':
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$update = $sonos->CheckForUpdate();
			#print_r($update);
		break;
		
		case 'volumeout':
			curr_volume();
		break;
		
		case 'profile':
			//VolumeProfiles();
		break;
		
		case 'json':
			$vol_config			= "s4lox_vol_profiles";
			$config1 = json_decode(file_get_contents($lbpconfigdir . "/" . $vol_config.".json"), TRUE);
			print_r($config1);
		break;
		
		case 'ttsp':
			$text = ($_GET['text']);
			isset($_GET['greet']) ? $greet = 1 : $greet = 0;
			#$r = 'http://192.168.50.95/plugins/text2speech/index.php?text=Guten%20Morgen%20lieber%20Oliver';
			#echo $r;
			$jsonstr = t2s_post_request($text, $greet);  // 
			$json = json_decode($jsonstr, True);
			#print_r($json);
			# open Plugin Logfile to add logging received
			$file = fopen($lbplogdir."/sonos.log","a",1);
			# if error from Text-to-speech been received
			if ($json['success'] == "2" or $json['success'] == "3")   {
				fwrite($file, date("H:i:s")." <ERROR> Interface: Text-to-speech ".$json['warning']."\n");
			}
			# loop through Logging array and write entries to Log File
			foreach($json['logging'] as $key => $value) {
				foreach($value as $log => $text) {
					fwrite($file, date("H:i:s")." <".$log."> Interface: Text-to-speech ".$text."\n");
				}
			}
			# close file
			fclose($file);
			# goahead with plugin actions
			$sonos->AddToQueue($json['fullcifsinterface']);
			$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
			$sonos->SetTrack(1);
			$sonos->SetVolume($volume);
			$sonos->Play();
			usleep($json['durationms'] * 1000);
			$sonos->ClearQueue();
		break;
		
			
		case 'getzoneinfo':				
			$GetZoneInfo = $sonos->GetzoneInfo();
			echo '<PRE>';
			echo "Technische Details der ausgewaehlten Zone: " . $master;
			echo '<PRE>';
			echo '<PRE>';
			echo "IP Adresse: " . substr($GetZoneInfo['IPAddress'], 0, 30);
			echo '<PRE>';
			echo "Serial Number: " . substr($GetZoneInfo['SerialNumber'], 0, 50);
			echo '<PRE>';
			echo "Software Version: " . substr($GetZoneInfo['SoftwareVersion'], 0, 30);
			echo '<PRE>';
			echo "Hardware Version: " . substr($GetZoneInfo['HardwareVersion'], 0, 30);
			echo '<PRE>';
			echo "MAC Adresse: " . substr($GetZoneInfo['MACAddress'], 0, 30);
			echo '<PRE>';
			echo '<PRE>';
			echo "RinconID: " . trim($sonoszone[$master][1]);
			echo '</PRE>';
		break;
		  
		default:
			LOGGING("sonos.php: Please use syntax as: 'http://<IP or HOSTENAME>/plugins/sonos4lox/index.php/?zone=<SONOSPLAYERNAME>&action=<FUNCTION>&value=-OPTION>'", 3);
			LOGGING("sonos.php: Your entered command ".$myIP."".urldecode($syntax)." is not known ",3);
		} 
	} else 	{
	LOGGING("sonos.php: The Zone ".$master." is not available or offline. Please check and if necessary add zone to Config", 4);
	
}
exit;

# Funktionen für Skripte ------------------------------------------------------

  

/**
/* Funktion : t2s_post_request --> generiert einen POST request zum text2speech Plugin
/*
/* @param: 	$text, $greet
/* @return: JSON
**/	
  
function t2s_post_request($text, $greet) {
	 
	global $myIP;
	
	// API Url
	$url = 'http://'.$myIP.'/plugins/text2speech/index.php';
	#$url = 'http://'.$myIP.'/plugins/text2speech/index.php?json=1';
	
	// Initiate cURL.
	$ch = curl_init($url);
	 
	// populate JSON data.
	$jsonData = array(
		'text' => $text,
		'greet' => $greet
	);
		 
	// Encode the array into JSON.
	$jsonDataEncoded = json_encode($jsonData);
		 
	// Tell cURL that we want to send a POST request.
	curl_setopt($ch, CURLOPT_POST, 1);
	 
	// Attach our encoded JSON string to the POST fields.
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
	 
	// Set the content type to application/json
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
	
	// Request response from Call
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		 
	// Execute the request
	$result = curl_exec($ch);
	
	// was the request successful?
	if($result === false)  {
		LOGGIN("Der POST Request war nicht erfolgreich!", 7);
	} else {
		LOGGING("sonos.php: Der POST Request war erfolgreich!", 7);
	}
	// close cURL
	curl_close($ch);
	return $result;
}



function getsonosinfo() {
	
	global $lastExeLog;
	
    if(!touch($lastExeLog)) {
		LOGGING("sonos.php: No permission to write file", 3);
		exit;
    }
	// check if file already exist
	if (file_exists($lastExeLog)) {
		$lastRun = file_get_contents($lastExeLog);
		// echo time() - $lastRun;
		if (time() - $lastRun >= 86400) {
			 // it's been more than a day
			LOGGING("sonos.php: Function 'getsonosinfo' has been replaced by Cron Job scheduled every 10 seconds. Please remove ALL 'getsonosinfo' tasks from your Miniserver config.", 4);
			notify( LBPPLUGINDIR, "Sonos", "Function 'getsonosinfo' has been replaced by Cron Job scheduled every 10 seconds. Please remove ALL 'getsonosinfo' tasks from your Miniserver config.", "warning");
			// update LastExeSonosInfo with current time
			file_put_contents($lastExeLog, time());
		}
	} else {
		LOGGING("sonos.php: Function 'getsonosinfo' has been replaced by Cron Job scheduled every 10 seconds. Please remove ALL 'getsonosinfo' tasks from your Miniserver config.", 4);
		notify( LBPPLUGINDIR, "Sonos", "Function 'getsonosinfo' has been replaced by Cron Job scheduled every 10 seconds. Please remove ALL 'getsonosinfo' tasks from your Miniserver config.", "warning");			
		file_put_contents($lastExeLog, time());
	}
}



function volume_group()  {
	
	global $sonoszone, $sonos, $master, $volume, $config, $sonoszonen, $min_vol, $lookup;

	$master = $_GET['zone'];
	$sonos = new SonosAccess($sonoszone[$master][0]);
	#$sonos->SetMute(true);
	if (isset($_GET['member']))  {
		$member = $_GET['member'];
		if($member === 'all') {
			$memberon = array();
			foreach ($sonoszone as $zone => $ip) {
				$zoneon = checkZoneOnline($zone);
				if ($zoneon === (bool)true and $zone != $master)  {
					array_push($memberon, $zone);
				}
			}
			$member = $memberon;
			LOGGING("sonos.php: Players has been grouped to Player ".$master, 5);	
		} else {
			$member = explode(',', $member);
			$memberon = array();
			foreach ($member as $value) {
				$zoneon = checkZoneOnline($value);
				if ($zoneon === (bool)true)  {
					array_push($memberon, $value);
				} else {
					LOGGING("sonos.php: Player '".$value."' could not be added to the group!!", 4);
				}
			}
			$member = $memberon;
		}
		if (in_array($master, $member)) {
			LOGGING("sonos.php: The zone ".$master." could not be entered as member again. Please remove from Syntax '&member=".$master."' !", 3);
			exit;
		}


		foreach ($member as $memplayer => $zone2) {
			$sonos = new SonosAccess($sonoszone[$zone2][0]);

			if(isset($_GET['volume']) or isset($_GET['groupvolume']) or isset($_GET['keepvolume']))  { 
				//isset($_GET['volume']) ? $groupvolume = $_GET['volume'] : $groupvolume = $_GET['groupvolume'];
				if(isset($_GET['volume'])) {
					# Volume from Syntax/URL
					$volume = $_GET['volume'];
					LOGGING("sonos.php: Volume for Group Member ".$zone2." has been set to: ".$volume, 7);
				} elseif (isset($_GET['groupvolume'])) {
					# Groupvolume from Syntax/URL
					$newvolume = $sonos->GetVolume();
					$volume = $newvolume + ($newvolume * ($groupvolume / 100));  // multiplizieren
					// prüfen ob errechnete Volume > 100 ist, falls ja max. auf 100 setzen
					$volume > 100 ? $volume = 100 : $volume;
					LOGGING("sonos.php: Group Volume for Member ".$zone2." has been set to: ".$volume, 7);
				} elseif (isset($_GET['keepvolume'])) {
					# current volume from Syntax/URL
					$tmg_volume = $sonos->GetVolume();
					# if current volume is less then treshold then take standard from config
					if ($tmg_volume >= $min_vol)  {
						$volume = $tmg_volume;
						LOGGING("sonos.php: Volume for Member ".$zone2." has been set to current volume", 7);
					} else {
						if (isset($_GET['text']) or isset($_GET['messageid']) or
							(isset($_GET['sonos'])) or (isset($_GET['weather'])) or 
							(isset($_GET['abfall'])) or (isset($_GET['witz'])) or 
							(isset($_GET['pollen'])) or (isset($_GET['warning'])) or
							(isset($_GET['distance'])) or (isset($_GET['clock'])) or 
							(isset($_GET['calendar'])) or ($_GET['action'] == "playbatch"))	{
							$volume = $config['sonoszonen'][$zone2][3];
							LOGGING("sonos.php: T2S Volume for Member ".$zone2." is less then ".$min_vol." and has been set exceptional to Standard volume ".$config['sonoszonen'][$zone2][3], 7);
						} else {
							$volume = $config['sonoszonen'][$zone2][4];
							LOGGING("sonos.php: Volume for Member ".$zone2." is less then ".$min_vol." and has been set exceptional to Standard volume ".$config['sonoszonen'][$zone2][4], 7);
						}
					}
				}
			} else {
				# No volume from Syntax/URL
				if (isset($_GET['text']) or isset($_GET['messageid']) or
					(isset($_GET['sonos'])) or (isset($_GET['weather'])) or 
					(isset($_GET['abfall'])) or (isset($_GET['witz'])) or 
					(isset($_GET['pollen'])) or (isset($_GET['warning'])) or
					(isset($_GET['distance'])) or (isset($_GET['clock'])) or 
					(isset($_GET['calendar'])) or ($_GET['action'] == "playbatch"))	{
					# T2S Standard Volume
					$volume = $config['sonoszonen'][$zone2][3];
				} else {
					# Sonos Standard Volume
					if (isset($_GET['profile']) or isset($_GET['Profile']))    {
						$volume = $lookup[0]['Player'][$zone2][0]['Volume'];
					} else {
						$volume = $config['sonoszonen'][$zone2][4];
						LOGGING("sonos.php: Standard Sonos Volume for Group Member ".$zone2." has been set to: ".$volume, 7);
					}
				}
			}
			@$sonos->SetMute(false);
			$sonos->SetVolume($volume);
		}
	}
	return;
}



function phonemute()  {
	global $sonoszone, $sonos, $min_vol, $lastVol, $config, $master, $tmp_phone, $tts_stat;
	
	# if Phonestop is true avoid T2S during phone call
	if ($config['VARIOUS']['phonestop'] == "1") {
		$tts_stat = "1";
		if(!touch($tmp_phone)) {
			LOGGING("sonos.php: No permission to write file", 3);
			exit;
		}
		$handle = fopen ($tmp_phone, 'w');
		fwrite ($handle, $tts_stat);
		fclose ($handle); 
	} 
	
	$state = getZoneStatus($master);
	//echo $min_vol;
	switch ($state)  {
		case 'single':
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$actual[$master]['Volume'] = $sonos->GetVolume();
			file_put_contents($lastVol, serialize($actual));
			LOGGING("sonos.php: Volume for a Single Player has been saved", 7);
			while ($sonos->GetVolume() > $min_vol)  {
				$sonos->SetVolume($sonos->GetVolume() - $config['MP3']['volumeup']);
				usleep(400000);
			}
			LOGGING("sonos.php: Phonemute for Single Player has been executed", 6);
			exit(1);
		break;
		
		case 'member' or 'master':
			$your_master = checkifmaster($master);
			$coord_mute = getGroup($your_master);
			//print_r($coord_mute);
			foreach ($coord_mute as $mutezone => $value)  {
				$sonos = new SonosAccess($sonoszone[$value][0]);
				$actual[$value]['Volume'] = $sonos->GetVolume();
				while ($sonos->GetVolume() > $min_vol)  {
					$sonos->SetVolume($sonos->GetVolume() - $config['MP3']['volumeup']);
					usleep(300000);
				}
			}
			file_put_contents($lastVol, serialize($actual));	
			LOGGING("sonos.php: Volume for a Group of Players has been saved", 7);
			LOGGING("sonos.php: Phonemute for Group has been executed", 6);
			exit(1);
		break;
	}
}



function phoneunmute()  {
	global $sonoszone, $sonos, $min_vol, $lastVol, $config, $master, $tmp_phone, $tmp_tts;
	
	$array = unserialize(@file_get_contents($lastVol));
	if ($array == false)  {
		LOGGING("sonos.php: No file exist to recover. Please execute phonemute first!", 4);
		exit;
    } else {
		//print_r($array);
		foreach ($array as $key => $value) {
			$sonos = new SonosAccess($sonoszone[$key][0]);
			$oldVolume = $array[$key]['Volume'];
			//echo $oldVolume;
			while ($sonos->GetVolume() < $oldVolume)  {
				$sonos->SetVolume($sonos->GetVolume() + $config['MP3']['volumeup']);
				usleep(400000);
			}
		}
		LOGGING("sonos.php: Volume has been restored", 7);
	}
	@unlink($tmp_phone);
	@unlink($lastVol);
	LOGGING("sonos.php: Phoneunmute has been executed", 6);
}


function scriptoff()  {
	global $sonos, $config, $lbplogdir, $lbpconfigdir, $off_file, $lbhomedir, $lbpplugindir;
	
	if(!touch($off_file)) {
		LOGGING("sonos.php: No permission to write file", 3);
		exit;
	}
	$handle = fopen ($off_file, 'w');
	fwrite ($handle, $config['VARIOUS']['cron']);
	fclose ($handle);
	echo "sonos.php: Script has been turned OFF";
	LOGGING("sonos.php: All actions for Sonos4lox has been turned OFF", 5);
}



function scripton()  {
	global $sonos, $config, $lbplogdir, $lbphtmldir, $lbhomedir, $lbpconfigdir, $off_file, $lbhomedir, $lbpplugindir;
	
	if (file_exists($off_file) === false)  {
		LOGGING("sonos.php: Onlinecheck, UDP, HTTP und Sonos4lox has not been turned off previously", 5);
		exit;
	} 
	@unlink($off_file);
	echo "sonos.php: Script has been turned ON";
	LOGGING("sonos.php: All actions for Sonos4lox has been turned ON", 5);
	exit;
}



function shutdown()
{
	global $log, $tts_stat, $check_info, $tmp_tts, $time_start;
	
	# FALLBACK --> setze 0 für virtuellen Texteingang (T2S End) falls etwas schief lief
	if ($tts_stat == 1)  {
		$tts_stat = 0;
		send_tts_source($tts_stat);
	}
	if (isset($_GET['debug']))    {
		debugInfo();
		$time_end = microtime(true);
		$process_time = $time_end - $time_start;
		LOGGING("Processing request tooks about ".round($process_time, 3)." seconds.\n", 6);
	} else {
		#@unlink($lbplogdir."/SOAP-Log-".$heute.".log");
		#@unlink($lbplogdir."/s4lox_debug_".$heute.".log");
	}
	if ($getsonos = strrpos($check_info, "getsonosinfo") === false)  {
		LOGEND("PHP finished");
	}
	@unlink($tmp_tts);
}


?>