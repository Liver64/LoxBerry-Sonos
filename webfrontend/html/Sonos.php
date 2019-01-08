<?php

##############################################################################################################################
#
# Version: 	3.5.6
# Datum: 	04.01.2019
# veröffentlicht in: https://github.com/Liver64/LoxBerry-Sonos/releases
# 
##############################################################################################################################


// ToDo

// Error handling falls User vergisst nach Zonen zu scannen
// Error handling (full stop) falls getsonosinfo noch aktiv ist - DONE
// Error handling falls t2s_ZONE noch nicht aktiv im MS vorhanden ist - DONE

ini_set('max_execution_time', 60); 							// Max. Skriptlaufzeit auf 120 Sekunden

include("system/PHPSonos.php");
include("system/Tracks.php");
include("Grouping.php");
include("Helper.php");
include("Alarm.php");
include("Playlist.php");
include("Queue.php");
include("Info.php");
include("Play_T2S.php");
include("Radio.php");
include("Restore_T2S.php");
include("Save_T2S.php");
include("Speaker.php");
include('system/logging.php');

register_shutdown_function('shutdown');

// setze korrekte Zeitzone
date_default_timezone_set(date("e"));

# prepare variables
$home = $lbhomedir;
$hostname = gethostname();										// hostname LoxBerry
$myIP = $_SERVER["SERVER_ADDR"];								// get IP of LoxBerry
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
$MP3path = "mp3";												// path to preinstalled numeric MP§ files
$sleeptimegong = "3";											// waiting time before playing t2s
$maxzap = '60';													// waiting time before zapzone been initiated again
$lbport = lbwebserverport();									// get loxberry port

#echo '<PRE>';

$params = [	"name" => "Sonos PHP",
			"filename" => "$lbplogdir/sonos.log",
			"append" => 1,
			"addtime" => 1,
			];
$log = LBLog::newLog($params);

$level = LBSystem::pluginloglevel();
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
LOGGING("called syntax: ".$myIP."".urldecode($syntax),5);
	
#-- Start Preparation ------------------------------------------------------------------
	
	// Parsen der Konfigurationsdatei sonos.cfg
	if (!file_exists($myFolder.'/sonos.cfg')) {
		LOGGING('The file sonos.cfg could not be opened, please try again!', 4);
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
		LOGGING("Sonos config has been loaded",7);
	}
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		LOGGING('The file player.cfg could not be opened, please try again!', 4);
	} else {
		$tmpplayer = parse_ini_file($myFolder.'/player.cfg', true);
		LOGGING("Player config has been loaded",7);
	}
	$player = ($tmpplayer['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	} 
	$sonoszonen['sonoszonen'] = $sonosnet;
	// finale config für das Script
	$config = array_merge($sonoszonen, $tmpsonos);
	
	// Übernahme und Deklaration von Variablen aus der Konfiguration
	$sonoszonen = $config['sonoszonen'];
	
	if (!isset($config['SYSTEM']['checkonline']))  {
		$checkonline = true;
	} else if ($config['SYSTEM']['checkonline'] == "1")  {
		$checkonline = true;
	} else {
		$checkonline = false;
	}
	$zonesoff = "";
	if ($checkonline === true)  {
		// prüft den Onlinestatus jeder Zone
		$zonesonline = array();
		LOGGING("Online check for Players will be executed",7);
		foreach($sonoszonen as $zonen => $ip) {
			$port = 1400;
			$timeout = 3;
			$handle = @stream_socket_client("$ip[0]:$port", $errno, $errstr, $timeout);
			if($handle) {
				$sonoszone[$zonen] = $ip;
				array_push($zonesonline, $zonen);
				fclose($handle);
			} else {
				LOGGING("Zone $zonen seems to be Offline, please check your power/network settings",4);
			}
		}
		$zoon = implode(", ", $zonesonline);
		LOGGING("Zone(s) $zoon are Online",7);
	} else {
		LOGGING("You have not turned on Function to check if all your Players are powered on/online. PLease turn on function 'checkonline' in Plugin Config in order to secure your requests!", 4);
		$sonoszone = $sonoszonen;
	}
	LOGGING("All variables has been collected",7);
	
	#$sonoszone = $sonoszonen;
	#print_r($sonoszonen);
	#print_r($config);
	#exit;
	
	# check if LBPort already exist in config (sonos.cfg), if not force user to save config
	$checklb = explode(':', $config['SYSTEM']['httpinterface']);
	$checklbport = explode('/', $checklb[2]);
	if ($checklbport[0] <> $lbport)  {
		LOGGING(htmlspecialchars($L['ERRORS.ERR_CHECK_LBPORT']), 3);
		exit;
	}
	
	# select language file for text-to-speech
	$t2s_langfile = "t2s-text_".substr($config['TTS']['messageLang'],0,2).".ini";				// language file for text-speech

	# Standardpath for saving MP3
	$MessageStorepath = $config['SYSTEM']['ttspath'];

	create_symlinks();
	
		
#-- End Preparation ---------------------------------------------------------------------


#-- Start allgemeiner Teil ----------------------------------------------------------------------

$valid_playmodes = array("NORMAL","REPEAT_ALL","REPEAT_ONE","SHUFFLE_NOREPEAT","SHUFFLE","SHUFFLE_REPEAT_ONE");

# Start des eigentlichen Srcipts
if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
	$volume = $_GET['volume'];
	$master = $_GET['zone'];
	// prüft auf Max. Lautstärke und korrigiert diese ggf.
	if($volume >= $config['sonoszonen'][$master][5]) {
		$volume = $config['sonoszonen'][$master][5];
	} else {
		$volume = $_GET['volume'];
	}
} else {
	$master = $_GET['zone'];
	$sonos = new PHPSonos($sonoszonen[$master][0]);
	$tmp_vol = $sonos->GetVolume();
	if ($tmp_vol >= $config['sonoszonen'][$master][5]) {
		$volume = $config['sonoszonen'][$master][5];
	}
	$volume = $config['sonoszonen'][$master][4];
}

if(isset($_GET['playmode'])) { 
	$playmode = preg_replace("/[^a-zA-Z0-9_]+/", "", strtoupper($_GET['playmode']));
	if (in_array($playmode, $valid_playmodes)) {
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		$sonos->SetPlayMode($playmode);
	}  else {
		LOGGING('incorrect PlayMode selected. Please correct!', 4);
	}
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
	$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
	switch($_GET['action'])	{
		case 'play';
			$posinfo = $sonos->GetPositionInfo();
			if(!empty($posinfo['TrackURI'])) {
				if(empty($config['TTS']['volrampto'])) {
					$config['TTS']['volrampto'] = "25";
					LOGGING("Rampto Volume in config has not been set. Default of 25% Volume has been taken, please update Plugin Config (T2S Optionen).", 4);
				}
				if($sonos->GetVolume() <= $config['TTS']['volrampto']) {
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					checkifmaster($master);
					$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
					$sonos->Play();
				} else {
					checkifmaster($master);
					$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
					$sonos->Play();
				}
			} else {
				LOGGING("No tracks in Queue to be played.", 4);
			}
		break;
		
		
		case 'pause';
			checkifmaster($master);
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->Pause();
			LOGGING("Pause been executed.", 7);
		break;
		
				
		case 'next';
			$titelgesammt = $sonos->GetPositionInfo();
			$titelaktuel = $titelgesammt["Track"];
			$playlistgesammt = count($sonos->GetCurrentPlaylist());
			if (($titelaktuel < $playlistgesammt) or (substr($titelgesammt["TrackURI"], 0, 9) == "x-rincon:")) {
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->Next();
			} else {
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->SetTrack("1");
			}
			LOGGING("Next been executed.", 7);
		break;
		
		
		case 'previous';
			$titelgesammt = $sonos->GetPositionInfo();
			$titelaktuel = $titelgesammt["Track"];
			$playlistgesammt = count($sonos->GetCurrentPlaylist());
			if ($titelaktuel <> '1') {
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->Previous();
			} else {
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->SetTrack("$playlistgesammt");
			}
			LOGGING("Previous been executed.", 7);
		break; 
		
			
		case 'rewind':
			checkifmaster($master);
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->Rewind();
			LOGGING("Rewind been executed.", 7);
		break;
		
		
		case 'mute';
			if($_GET['mute'] == 'false') {
				$sonos->SetMute(false);
			}
			else if($_GET['mute'] == 'true') {
				$sonos->SetMute(true);
			} else {
				LOGGING('Wrong Mute Parameter selected. Please correct', 3);
				exit;
			}       
			LOGGING("Mute/Unmute been executed.", 7);
		break;
		
		
		case 'telefonmute';
			if($_GET['mute'] == 'false') {
				 $MuteStat = $sonos->GetMute();
				 if($MuteStat == 'true') {
					$SaveVol = $sonos->GetVolume();
					$sonos->SetVolume(5);
					$sonos->SetMute(false);
					$sonos->RampToVolume("ALARM_RAMP_TYPE", $SaveVol);
				}
			}
			else if($_GET['mute'] == 'true') {
				 $sonos->SetMute(true);
				 $SaveVol = $sonos->GetVolume();
				 $sonos->SetVolume($SaveVol);
			}
		break;
		
		
		case 'stop';
			checkifmaster($master);
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->Stop();
			LOGGING("Stop been executed.", 7);
		break; 
		
			
		case 'stopall';
			foreach ($sonoszone as $zone => $player) {
				checkifmaster($zone);
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				$state = $sonos->GetTransportInfo();
				if ($state == '1') {
					$return = getZoneStatus($zone); // get current Zone Status (Single, Member or Master)
					if($return <> 'member') {
						$sonos->Pause();
					}
				}
			}
			LOGGING("Stop/Pause all been executed.", 7);
		break; 
		

		case 'softstop':
			$save_vol_stop = $sonos->GetVolume();
			$sonos->RampToVolume("SLEEP_TIMER_RAMP_TYPE", "0");
			while ($sonos->GetVolume() > 0) {
				sleep('1');
			}
			checkifmaster($master);
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->Stop();
			$sonos->SetVolume($save_vol_stop);
			LOGGING("Softstop been executed.", 7);
		break; 
		
		  
		case 'toggle':
			if($sonos->GetTransportInfo() == 1)  {
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->Stop();
			} else {
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->Play();
			}
			LOGGING("Toggle been executed.", 7);
		break; 
		
					
		case 'playmode';
			// see valid_playmodes under Configuratio section for a list of valid modes
			if (in_array($playmode, $valid_playmodes)) {
				$sonos->SetPlayMode($playmode);
			} else {
				LOGGING('Wrong PlayMode Parameter selected. Please correct', 4);
			}   
			LOGGING("Playmode been executed.", 7);
		break; 
		
	  
		case 'crossfade':
			if((is_numeric($_GET['crossfade'])) && ($_GET['crossfade'] == 0) || ($_GET['crossfade'] == 1)) { 
				$crossfade = $_GET['crossfade'];
			} else {
				LOGGING("Wrong Crossfade entered -> 0 = off / 1 = on", 4);
				exit;
			}
				$sonos->SetCrossfadeMode($crossfade);
				LOGGING("Crossfade been executed.", 7);
		break; 
		
		  
		case 'remove':
			if(is_numeric($_GET['remove'])) {
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->RemoveFromQueue($_GET['remove']);
				LOGGING("Remove Song been executed.", 7);
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
					LOGGING("Rampto Volume in config has not been set. Default of 25% Volume has been taken, please update Plugin Config (T2S Optionen).", 4);
				}
				if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					$sonos->Play();
				} else{
					$sonos->Play();
				}
			} else {
				LOGGING("No tracks in Playlist to play.", 5);
			}
			LOGGING("Playqueue been executed.", 7);
		break;
		
		
		case 'clearqueue':
			$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$master][1]) . "#0");
			checkifmaster($master);
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->ClearQueue();
			LOGGING("Queue has been cleared",7);
		break;
		
		  
		case 'volume':
			if(isset($volume)) {
				$sonos->SetVolume($volume);
			} else {
				LOGGING('Wrong range of values for volume been entered, only 0-100 is permitted', 4);
				exit;
			}
		break; 
		
		  
		case 'volumeup': 
			$volume = $sonos->GetVolume();
			if($volume < 100) {
				$volume = $volume + $config['MP3']['volumeup'];
				$sonos->SetVolume($volume);
			}      
		break;
		
			
		case 'volumedown':
			$volume = $sonos->GetVolume();
			if($volume > 0) {
				$volume = $volume - $config['MP3']['volumedown'];
				$sonos->SetVolume($volume);
			}
		break;
		
			
		case 'setloudness':
			if(($_GET['loudness'] == 1) || ($_GET['loudness'] == 0)) {
				$loud = $_GET['loudness'];
				$sonos->SetLoudness($loud);
			} else {
				LOGGING('Wrong Loudness Mode selected', 5);
			}    
			LOGGING("Loudness been executed.", 7);
		break;
		
		
		case 'settreble':
			$Treble = $_GET['treble'];
			$sonos->SetTreble($Treble);
			LOGGING("Treble been executed.", 7);
		break;
		
		
		case 'setbass':
			$Bass = $_GET['bass'];
			$sonos->SetBass($Bass);
			LOGGING("Bass been executed.", 7);
		break;	
		
		
		case 'addmember':
			addmember();
		break;

		
		case 'removemember':
			removemember();
		break;
		
		
		case 'nextpush':
			checkifmaster($master);
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$posinfo = $sonos->GetPositionInfo();
			$duration = $posinfo['duration'];
			$state = $posinfo['TrackURI'];
			// Nichts läuft
			((empty($state)) and empty($duration)) ? nextradio() : '';
			// TV / Radio läuft
			((!empty($state)) and empty($duration)) ? nextradio() : '';
			// Playliste läuft
			((!empty($state)) and !empty($duration)) ? next_dynamic() : '';
			LOGGING("Nextpush been executed.", 7);
		break;
		
		
		case 'nextradio':
			checkifmaster($master);
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			nextradio();
		break;
		
							  
		case 'sonosplaylist':
			playlist();
		break;
		
		  
		case 'groupsonosplaylist':
			AddMemberTo();
			playlist();
		break;
		

		case 'radioplaylist':
			radio();
		break;
		
		
		case 'groupradioplaylist': 
			AddMemberTo();
			radio();
		break;
		
		
		case 'radio': 
			AddMemberTo();
			radio();
		break;
		
		
		case 'playlist':
			AddMemberTo();
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
		global $sonos, $coord, $text, $member, $master, $zone, $messageid, $logging, $words, $voice, $accesskey, $secretkey, $rampsleep, $config, $save_status, $mute, $membermaster, $groupvol, $checkgroup;
		#$time_start = microtime(true);
		sendgroupmessage();
	break;
		
		
	case 'sendmessage':
		global $text, $coord, $master, $messageid, $logging, $words, $voice, $config, $actual, $player, $volume, $coord, $time_start;
		#$time_start = microtime(true);
		sendmessage();
	break;
	
			
	case 'say':
		#$time_start = microtime(true);
		say();
	break;
		
			
	case 'group':
		group_all();
	break;
	
		
	case 'ungroup':
		ungroup_all();
	break;
	

	#case 'getsonosinfo':
	
	
	
	
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
			LOGGING("Checksonos been executed.", 7);
		break;
		
			
		case 'getzonestatus':
			getZoneStatus($master);
		break;
			

		case 'getmediainfo':
			echo '</PRE>';
			print_r($sonos->GetMediaInfo());
		break;
		

		case 'getmute':
			echo '<PRE>';
			print_r($sonos->GetMute());
		break;


		case 'getpositioninfo':
			echo '<PRE>';
			print_r($sonos->GetPositionInfo());
		break; 
		

		case 'gettransportsettings':
			echo '<PRE>';
			print_r($sonos->GetTransportSettings());
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
		break;

		  
		case 'getvolume':
			echo '<PRE>';
			print_r($sonos->GetVolume());
		break;
			
		
		case 'getuser':
			echo '<PRE>';
			echo get_current_user();
		break;	
		
		  
		case 'masterplayer':
			Global $zone, $master;	
			foreach ($sonoszone as $player => $ip) {
				$sonos = new PHPSonos($ip[0]); //Slave Sonos ZP IPAddress
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
		
		
		case 'becomegroupcoordinator':
			echo '<PRE>';
			$sonos->BecomeCoordinatorOfStandaloneGroup();
			LOGGING("Zone ".$master." is playing in single mode", 7);
			echo '</PRE>';
		break;
		
		
		case 'getgroupmute':
			$GetGroupMute = $sonos->GetGroupMute();
		break;
		
		
		case 'setgroupmute':
			if(($_GET['mute'] == 1) || ($_GET['mute'] == 0)) {
				$mute = $_GET['mute'];
				$sonos->SetGroupMute($mute);
			} else {
				LOGGING("Der Mute Mode ist unbekannt", 4);
			}
		break;
		

		case 'getgroupvolume':
			$sonos->SnapshotGroupVolume();
			$GetGroupVolume = $sonos->GetGroupVolume();
			print_r($GetGroupVolume);
		break;
		
		
		case 'setgroupvolume':
			$GroupVolume = $_GET['volume'];
			$sonos->SnapshotGroupVolume();
			$GroupVolume = $sonos->SetGroupVolume($GroupVolume);
		break;
		
		
		case 'setrelativegroupvolume':
			$sonos->SnapshotGroupVolume();
			$RelativeGroupVolume = $_GET['volume'];
			$RelativeGroupVolume = $sonos->SetRelativeGroupVolume($RelativeGroupVolume);
		break;
		
		
		case 'snapshotgroupvolume':
			$SnapshotGroupVolume = $sonos->SnapshotGroupVolume();
		break;
		
		
		case 'groupmanagement':
			$sonos = new PHPSonos($sonoszone[$master][0]);
			$ip = $sonoszone[$master][0];
			$SubscribeZPGroupManagement = $sonos->SubscribeZPGroupManagement('http://'.$ip.':6666');
		break;
		
		
		case 'sleeptimer':
			sleeptimer();
		break;
		

		case 'getsonosplaylists':
			echo '<PRE>';
			print_r($sonos->GetSonosPlaylists());
		break;
		
			
		case 'getaudioinputattributes':	
			echo '<PRE>';
			print_r($sonos->GetAudioInputAttributes());
		break;
		
		
		case 'getzoneattributes':
			echo '<PRE>';
			print_r($sonos->GetZoneAttributes());
		break;
		
		
		case 'getzonegroupattributes':
			echo '<PRE>';
			print_r($sonos->GetZoneGroupAttributes());
		break;
		
		
		case 'getcurrenttransportactions':
			echo '<PRE>';
			print_r($sonos->GetCurrentTransportActions());
		break;
		
		
		case 'getcurrentplaylist':
			echo '<PRE>';
			print_r($sonos->GetCurrentPlaylist());
		break;
		
		
		case 'getimportedplaylists':
			echo '<PRE>';
			print_r($sonos->GetImportedPlaylists());
		break;
		
		
		case 'listalarms':
			echo '<PRE>';
			print_r($sonos->ListAlarms());
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
		break;
		
		
		case 'setledstate':
			echo '<PRE>';
			if(($_GET['state'] == "On") || ($_GET['state'] == "Off")) {
				$state = $_GET['state'];
				$sonos->SetLEDState($state);
			} else {
				LOGGING('Please correct input. Only On or off is allowed', 4);
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
		break;
		
		
		case 'getloudness':
			echo '<PRE>';
			print_r($sonos->GetLoudness());
		break;
		
		
		case 'gettreble':
			echo '<PRE>';
			print_r($sonos->GetTreble());
		break;
		
					
		case 'getbass':
			echo '</PRE>';
			print_r($sonos->GetBass());
		break;
		
		
		case 'clearlox': // Loxone Fehlerhinweis zurücksetzen
			clear_error();
		break;
		
		
		case 'zapzone':
			zapzone();
		break;
		
		case 'getsonosinfo':
			
		break;
		
		
		case 'delsonosplaylist':
			$sonos->DelSonosPlaylist('SQ:96');
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
		
		
		case 'favoriten':
			GetSonosFavorites();
		break;
		
		
		case 'networkstatus';
			networkstatus();
		break;  
		
		
		case 'getloxonedata':
			getLoxoneData();
		break;
		
		
		case 'getplayerlist':
			getPlayerList();
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
		
		
		case 'say':
			say();
		break;
		
		
		case 'addzones':
			addZones();
		break;
		
		
		case 'playbatch':
			t2s_playbatch();
		break;
		
		
		case 'alarmstop':
			$sonos->Stop();
			if(isset($_GET['member'])) {
				restoreGroupZone();
			} else {
				restoreSingleZone();
			}
		break;
		
		
		case 'zapzones':
			zapzone();
		break;
		
		
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
			var_dump($test);
		break;
		
		
		case 'spotify':
			require_once("services/Spotify.php");
			AddSpotify();
		break;	
		
		
		case 'amazon':
			require_once("services/Amazon.php");
			AddAmazon();
		break;
		
		
		case 'google':
			require_once("services/Google.php");
			AddGoogle();
		break;	
		
		
		case 'apple':
			require_once("services/Apple.php");
			AddApple();
		break;	
		
		
		case 'napster':
			require_once("services/Napster.php");
			AddNapster();
		break;	
		
		
		case 'track':
			require_once("services/Local_Track.php");
			AddTrack();
		break;	
		
		
		case 'randomplaylist':
			random_playlist();
		break;
		
		
		case 'randomradio':
			random_radio();
		break;
		
		case 'browse':
		$t = createMetaDataXml('x-sonos-http:track%3a295580498.mp3?sid=160&amp;flags=8224&amp;sn=3');
		print_r($t);
		break;
		
		case 'files':
			mp3_files();
		break;
		
		case 'test':
			$sonos->FavTest();
		break;
		
		case 'balance':
			SetBalance();
		break;
		
		case 'queue':
			#file("http://192.168.50.95/plugins/sonos4lox/index.php/?zone=kueche&action=sendmessage&text=Hallo Olli&volume=20");
			#file("http://192.168.50.95/plugins/sonos4lox/index.php/?zone=kueche&action=sendmessage&clock");
			$ch = curl_init();

			// set URL and other appropriate options
			curl_setopt($ch, CURLOPT_URL, "http://192.168.50.95/plugins/sonos4lox/index.php/?zone=kueche&action=sendmessage&text=Hallo Olli&volume=20");
			curl_setopt($ch, CURLOPT_HEADER, 0);

			// grab URL and pass it to the browser
			curl_exec($ch);

			// close cURL resource, and free up system resources
			curl_close($ch);
		break;
		
		case 'resetbasic':
			$sonos->ResetBasicEQ();
			LOGGING("EQ Settings for Player ".$master." has been reset.", 7);
		break;
		
		case 'ttsp':
			$text = ($_GET['text']);
			isset($_GET['greet']) ? $greet = 1 : $greet = 0;
			$jsonstr = t2s_post_request($text, $greet);  // 
			$json = json_decode($jsonstr, True);
			#print_r($jsonstr);
			#sleep(2);
			echo $json['full-httpinterface'];
			$sonos->AddToQueue($json['full-httpinterface']);
			$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
			$sonos->SetTrack(1);
			$sonos->SetVolume($volume);
			$sonos->Play();
			usleep($json['duration-ms'] * 1000);
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
		break;
		  
		default:
		   LOGGING("This command is not known. Pls use syntax as: 'http://<IP or HOSTENAME of LoxBerry>/plugins/sonos4lox/index.php/?zone=sonosplayer&action=function&value=option'", 3);
		} 
	} else 	{
	LOGGING("The Zone ".$master." is not available or offline. Please check and if necessary add zone to Config", 4);
	
}
exit;

# Funktionen für Skripte ------------------------------------------------------

 
/** NICHT AKTIV
/*
/* Funktion : delmp3 --> löscht die hash5 codierten MP3 Dateien aus dem Verzeichnis 'messageStorePath'
/*
/* @param:  nichts
/* @return: nichts
**/
 function delmp3() {
	global $config, $debug, $time_start;
	
	# http://www.php-space.info/php-tutorials/75-datei,nach,alter,loeschen.html	
	$dir = $MessageStorepath;
    $folder = dir($dir);
	$store = '-'.$config['MP3']['MP3store'].' days';
	while ($dateiname = $folder->read()) {
	    if (filetype($dir.$dateiname) != "dir") {
            if (strtotime($store) > @filemtime($dir.$dateiname)) {
					if (strlen($dateiname) == 36) {
						if (@unlink($dir.$dateiname) != false)
							LOGGING($dateiname.' has been deleted<br>', 7);
						else
							LOGGING($dateiname.' could not be deleted<br>', 7);
					}
			}
        }
    }
	LOGGING("All files according to criteria were successfully deleted", 7);
	$folder->close();
    return; 	 
 }
 

/**
/* Funktion : SetGroupVolume --> setzt Volume für eine Gruppe
/*
/* @param: 	Volume
/* @return: 
**/	
function SetGroupVolume($groupvolume) {
	global $sonos, $sonoszone, $master;
	$sonos = new PHPSonos($sonoszone[$master][0]); 
	$sonos->SnapshotGroupVolume();
	#$GroupVolume = $_GET['volume'];
	$GroupVolume = $sonos->SetGroupVolume($groupvolume);
 }

/**
/* Funktion : SetRelativeGroupVolume --> setzt relative Volume für eine Gruppe
/*
/* @param: 	Volume
/* @return: 
**/	
function SetRelativeGroupVolume($volume) {
	global $sonos;
	$sonos->SnapshotGroupVolume();
	$RelativeGroupVolume = $_GET['volume'];
	$RelativeGroupVolume = $sonos->SetRelativeGroupVolume($RelativeGroupVolume);
}

/**
/* Funktion : SnapshotGroupVolume --> ermittelt das prozentuale Volume Verhältnis der einzelnen Zonen
/* einer Gruppe (nur vor SetGroupVolume oder SetRelativeGroupVolume nutzen)
/*
/* @return: Volume Verhältnis
**/	
function SnapshotGroupVolume() {
	global $sonos;
	$SnapshotGroupVolume = $sonos->SnapshotGroupVolume();
	return $SnapshotGroupVolume;
}

/**
/* Funktion : SetGroupMute --> setzt alle Zonen einer Gruppe auf Mute/Unmute
/* einer Gruppe
/*
/* @param: 	MUTE or UNMUTE
/* @return: 
**/	
 function SetGroupMute($mute) {
	global $sonos;
		$sonos->SetGroupMute($mute);
 }


/** NICHT LIVE - EXPERIMENTAL**
*
* Funktion : 	GetSonosFavorites --> lädt die Sonos Favoriten in die Queue (kein Radio)
*
* @param: empty
* @return: Favoriten in der Queue
**/

function GetSonosFavorites() {
	global $sonoszone, $master;
	
	$sonos = new PHPSonos($sonoszone[$master][0]); 
	$sonos->ClearQueue();
	$favoriteslist = $sonos->GetSonosFavorites("FV:2","BrowseDirectChildren"); 
	print_r($favoriteslist);
	$posinfo = $sonos->GetPositionInfo();
	foreach ($favoriteslist as $favorite) {
		$scope = $favorite['res'];
		if ((substr($scope,0,11) != "x-sonosapi-") and (substr($scope,0,11) != "x-rincon-cp")) {
			$finalstring = urldecode($scope);
			$track = $favorite['res'];
			$title = $favorite['title'];
			$artist = $favorite['artist'];
			$posinfo = $sonos->GetPositionInfo();
			$metadata = $posinfo['TrackMetaData'].'<br>';
			$sonos->AddFavoritesToQueue($finalstring, $metadata);
		}	
	}
	$currlist = $sonos->GetCurrentPlaylist();
}


/**
/* Funktion : SetBalance --> setzt die Balance für angegeben Zone
/* einer Gruppe
/*
/* @param: 	balance=LF oder RF, wert 
/* @return: 
**/	

function SetBalance()  {
	global $sonos, $master;
	
	if (isset($_GET['member']))  {
		LOGGING('For groups the function could not be used, please correct!', 3);
		exit;
	}
	if ((isset($_GET['balance'])) && (isset($_GET['value']))) {
		if(is_numeric($_GET['value']) && $_GET['value'] >= 0 && $_GET['value'] <= 100) {
			$balance_dir = $_GET['balance'];
			$valid_directions = array('LF' => 'left speaker','RF' => 'right speaker', 'lf' => 'left speaker', 'rf' => 'right speaker');
			if (array_key_exists($balance_dir, $valid_directions)) {
				$sonos->SetBalance($balance_dir, $_GET['value']);
				LOGGING('Balance for '.$valid_directions[$balance_dir].' of Player '.$master.' has been set to '.$_GET['value'].'.', 5);
			} else {
				LOGGING('Entered balance direction for Player '.$master.' is not valid. Only "LF/lf" or "RF/rf" are allowed, please correct!', 3);
				exit;
			}
		} else {
			LOGGING('Entered balance '.$_GET['value'].' for Player '.$master.' is even not numeric or not between 1 and 100, please correct!', 3);
			exit;
		}
	} else {
		LOGGING('No valid entry for Balance has been entered or syntax is incomplete, please correct!', 3);
		exit;
	}
}


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
		LOGGING("Der POST Request war erfolgreich!", 7);
	}
	// close cURL
	curl_close($ch);
	return $result;
}



function getsonosinfo() {
	
	$lastExeLog = "/run/shm/LastExeSonosInfo.log";
    if(!touch($lastExeLog)) {
		LOGGING("No permission to write file", 3);
		exit;
    }
	// check if file already exist
	if (file_exists($lastExeLog)) {
		$lastRun = file_get_contents($lastExeLog);
		// echo time() - $lastRun;
		if (time() - $lastRun >= 86400) {
			 // it's been more than a day
			LOGGING("Function 'getsonosinfo' has been replaced by Cron Job scheduled every 10 seconds. Please remove ALL 'getsonosinfo' tasks from your Miniserver config.", 4);
			notify( LBPPLUGINDIR, "Sonos", "Function 'getsonosinfo' has been replaced by Cron Job scheduled every 10 seconds. Please remove ALL 'getsonosinfo' tasks from your Miniserver config.", "warning");
			// update LastExeSonosInfo with current time
			file_put_contents($lastExeLog, time());
		}
	} else {
		LOGGING("Function 'getsonosinfo' has been replaced by Cron Job scheduled every 10 seconds. Please remove ALL 'getsonosinfo' tasks from your Miniserver config.", 4);
		notify( LBPPLUGINDIR, "Sonos", "Function 'getsonosinfo' has been replaced by Cron Job scheduled every 10 seconds. Please remove ALL 'getsonosinfo' tasks from your Miniserver config.", "warning");			
		file_put_contents($lastExeLog, time());
	}
}



function shutdown()
{
	global $log, $tts_stat, $check_info;
	# FALLBACK --> setze 0 für virtuellen Texteingang (T2S End) falls etwas schief lief
	// echo $tts_stat.'<br>';
	if ($tts_stat == 1)  {
		$tts_stat = 0;
		send_tts_source($tts_stat);
		LOGGING("Something went wrong with T2S. Fallback scenario set virtual textinbound to 0", 4);
	}
	if ($getsonos = strrpos($check_info, "getsonosinfo") === false)  {
		$log->LOGEND("PHP finished");
	}
	#LOGEND("PHP finished");
	
}
?>

