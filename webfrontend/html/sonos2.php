<?php

##############################################################################################################################
#
# Version: 	0.8.0
# Datum: 	07.12.2016
# veröffentlicht in forum: https://www.loxforum.com/
# 
# Change History:
# ----------------------------------------------------------------------------------------------------------------------------
# 0.8.0		Initiale Version des Plugin (Alpha Version)
#			getSonosTitInt() muss noch überarbeitet werden wenn DNS Cloud geht - erledigt
#			"playmode" kann jetzt in Syntaxkombination genutzt werden
#			"volume" ist jetzt auf max Vol. gemäß Config restrektiert
#			prüft auf das Vorhandensein von Addon's
#
######## Script Code (ab hier bitte nichts ändern) ###################################

header('Content-Type: text/html; charset=utf-8');

ini_set('max_execution_time', 120); // Max. Skriptlaufzeit auf 120 Sekunden
include("system/PHPSonos.php");

date_default_timezone_set(date("e"));

$home = posix_getpwuid(posix_getuid());
$home = $home['dir'];
$myIP = $_SERVER["SERVER_ADDR"];

$psubfolder = __FILE__;
$psubfolder = preg_replace('/(.*)\/(.*)\/(.*)$/',"$2", $psubfolder);

if(substr($home,0,4) == "/opt") 
{
#-- Ab hier Loxberry spezifisch ------------------------------------------------------------------

	$myFolder = "$home/config/plugins/$psubfolder/";
	$myMessagepath = "//$myIP/loxberry/data/plugins/$psubfolder/tts/";
	$myMessageStorepath = "$home/loxberry/data/plugins/$psubfolder/tts/";

	// Parsen der Konfigurationsdatei sonos.cfg
	if (!file_exists($myFolder.'/sonos.cfg')) {
		trigger_error('Die Datei sonos.cfg konnte nicht geöffnet werden, bitte erneut versuchen!', E_USER_NOTICE);
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
	}
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		trigger_error('Die Datei player.cfg konnte nicht geöffnet werden, bitte erneut versuchen!', E_USER_NOTICE);
	} else {
		$tmpplayer = parse_ini_file($myFolder.'/player.cfg', true);
	}
	$player = ($tmpplayer['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	} 
	$sonoszonen['sonoszonen'] = $sonosnet;
	// finale config für das Script
	$config = array_merge($sonoszonen, $tmpsonos);
	$logpath = $config['SYSTEM']['logpath'];	
	
#-- Ende Loxberry spezifisch ---------------------------------------------------------------------

#-- Ab hier NICHT Loxberry spezifisch ------------------------------------------------------------

} else {
	// Parsen der Konfigurationsdatei sonos_nolb.cfg (Non Loxberry)
	if (!file_exists("./system/sonos_nolb.cfg")) {
		trigger_error('Die Datei sonos_nolb.cfg konnte nicht geöffnet werden, bitte erneut versuchen!', E_USER_NOTICE);
	} else {
		$tmpsonos =  parse_ini_file("./system/sonos_nolb.cfg", TRUE);
	}
	// Parsen der Konfigurationsdatei player_noLB.cfg (Non Loxberry)
	if (!file_exists("./system/player_nolb.cfg")) {
		trigger_error('Die Datei player_nolb.cfg konnte nicht geöffnet werden, bitte erneut versuchen!', E_USER_NOTICE);
	} else {
		$tmpplayer = parse_ini_file("./system/player_nolb.cfg", true);
	}
	$player = ($tmpplayer['SONOSZONEN']);
	
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	} 
	$sonoszonen['sonoszonen'] = $sonosnet;
	// finale config für das Script
	$config = array_merge($sonoszonen, $tmpsonos);	
	
	$tmp_loxip = $tmpsonos['LOXONE']['LoxIP'];
	$tmp_loxport = $tmpsonos['LOXONE']['LoxPort'];
	$loxip = $tmp_loxip.':'.$tmp_loxport;
	$loxuser = $tmpsonos['LOXONE']['LoxUser'];
	$loxpassword = $tmpsonos['LOXONE']['LoxPassword'];
	$myMessagepath = $config['SYSTEM']['messagespath'];
	$myMessageStorepath = $config['SYSTEM']['messagespath'];
	
	$logpath = "log";
	if (is_dir($logpath)) { 
	} else { 
	mkdir ($logpath, 0777); 
	} 
}

	// Schaltet den virtuellen Eingang in Loxone an/aus
	if($config['LOXONE']['LoxDaten'] == 1) {
		#turnonlox('Ein');
	} else {
		#turnonlox('Aus');
	}

#-- Ende NICHT Loxberry spezifisch ---------------------------------------------------------------------

#-- Ab hier allgemeiner Teil ----------------------------------------------------------------------

$debug = $config['SYSTEM']['debuggen'];
if($debug == 1) { 
	echo "<pre><br>"; 
}
 
// Übernahme und Deklaration von Variablen aus der Konfiguration
$sonoszonen = $config['sonoszonen'];
#$logpath = $config['SYSTEM']['logpath'];

// prüft den Onlinestatus jeder Zone
#function playeron() {
	foreach($sonoszonen as $zonen => $ip) {
		$port = 1400;
		$timeout = 3;
			#$handle = @fsockopen($ip[0], $port, $errno, $errstr, $timeout);
			$handle = @stream_socket_client("$ip[0]:$port", $errno, $errstr, $timeout);
		if($handle) {
			$sonoszone[$zonen] = $ip;
			#fclose($handle);
		} else {
			echo '';
		}
	}
	fclose($handle);
	$sonoszone;

// Umbennennen des ursprünglichen Array Keys
$config['SYSTEM']['myMessageStorepath'] = $config['SYSTEM']['messagespath'];
unset($config['SYSTEM']['messagespath']);			
	
echo "<pre>"; 
#$sonoszone = $sonoszonen;
#print_r($sonoszone);
#print_r($config);
#exit;
	
#}

#*** ANSCHAUEN OB NICHT BESSER INNERHALB DER FUNKTION ***
// Delta zwischen Sonoszonen (siehe config) und Zonen die Online sind 
// relevant für senden von UDP Daten an Loxone
#$sonos_array_diff = @array_diff_key($sonoszonen, $sonoszone);
#$sonos_array_diff = array_keys($sonos_array_diff);

// Setzen des Error Handler
if($debug == 0) {set_error_handler("errorHandler"); }
if($debug == 1) {echo '<br>'; }

function errorHandler($errno, $errstr, $errfile, $errline) {
	global $logpath, $loxuser, $loxpassword, $loxip, $master;
	
	ini_set("display_errors", 0);
	if(substr($home,0,4) == "/opt") {
	#-- Ab hier Loxberry spezifisch ------------------------------------------------------------------	
	
		$msdata = getMS1data();
		$tmp_loxip = $msdata['Host'];
		$loxport = $msdata['Port'];
		$loxuser = $msdata['User'];
		$loxpassword = $msdata['PW'];
		$loxip = $tmp_loxip.':'.$loxport;
	}
	#-- Ende Loxberry spezifisch ---------------------------------------------------------------------
	switch ($errno) {
        case E_NOTICE:
        case E_USER_NOTICE:
			$message = date("Y-m-d H:i:s - ");
			$message .= "USER defined NOTICE: [" . $errno ."], " . "$errstr in $errfile in line $errline, \r\n";
			error_log($message, 3, $logpath."/sonos_error.log");
			#echo("Ein Fehler trat auf. Bitte Datei ".$logpath."sonos_error.log pruefen.<br>");
			break;
			
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
        case E_STRICT:
			$message = date("Y-m-d H:i:s - ");
			$message .= "STRICT error: [" . $errno ."], " . "$errstr in $errfile in line $errline, \r\n";
			error_log($message, 3, $logpath."/sonos_error.log");
			#echo("Ein Fehler trat auf. Bitte Datei ".$logpath."sonos_error.log pruefen.<br>");
            break;
 
        case E_WARNING:
        case E_USER_WARNING:
			$message = date("Y-m-d H:i:s - ");
			$message .= "USER defined WARNING: [" . $errno ."], " . "$errstr in $errfile in line $errline, \r\n";
			error_log($message, 3, $logpath."/sonos_error.log");
			#echo("Ein Fehler trat auf. Bitte Datei ".$logpath."sonos_error.log pruefen.<br>");
            break;
 
        case E_ERROR:
        case E_USER_ERROR:
        case E_RECOVERABLE_ERROR:
			$message = date("Y-m-d H:i:s - ");
			$message .= "FATAL error: [" . $errno ."], " . "$errstr in $errfile in line $errline, \r\n";
			error_log($message, 3, $logpath."/sonos_error.log");
			#echo("Ein Fehler trat auf. Bitte Datei ".$logpath."sonos_error.log pruefen.<br>");
 
        default:
			#$message = date("Y-m-d H:i:s - ");
			#$message .= "Unknown error at $errfile in line $errline, \r\n";
			#error_log($message, 3, $logpath."/sonos_error.log");
			#echo("Ein unbekannter Fehler trat auf. Bitte Datei /".$logpath."/sonos_error.log pruefen.");
    }
	echo("Ein Fehler trat auf. Bitte Datei ".$logpath."sonos_error.log pruefen.<br>");
	#-- Loxone Uebermittlung eines Fehlerhinweises ----------------------------------------------
	$ErrorS = rawurlencode("Sonos Fehler. Bitte log pruefen");
	$handle = @fopen("http://$loxuser:$loxpassword@$loxip/dev/sps/io/S-Error/$ErrorS", "r");
	#--------------------------------------------------------------------------------------------
}

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
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$tmp_vol = $sonos->GetVolume();
	if ($tmp_vol >= $config['sonoszonen'][$master][5]) {
		$volume = $config['sonoszonen'][$master][5];
	}
	$volume = $config['sonoszonen'][$master][4];
}

if(isset($_GET['playmode'])) { 
	if(($_GET['playmode'] == "normal") || ($_GET['playmode'] == "repeat_all")
		|| ($_GET['playmode'] == "shuffle_norepeat") || ($_GET['playmode'] == "shuffle") 
		|| ($_GET['playmode'] == "repeat_one") || ($_GET['playmode'] == "shuffle_repeat_one")) {
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$sonos->SetPlayMode(strtoupper($_GET['playmode']));
	}  else {
		trigger_error('falscher PlayMode ausgewählt. Bitte korrigieren!', E_USER_NOTICE);
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
	$master = $_GET['zone'];
	$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
		
	switch($_GET['action'])	{
		case 'play';
			$posinfo = $sonos->GetPositionInfo();
			if(!empty($posinfo['TrackURI'])) {
			if($sonos->GetVolume() <= $config['TTS']['volrampto']) {
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					getSonosStatVol();
					$sonos->Play();
				} else {
					getSonosStatVol();
					$sonos->Play();
				}
			} else {
				trigger_error("Keine Titel in der Playliste zum Abspielen.", E_USER_NOTICE);
			}
			break;
		
		case 'pause';
				$sonos->Pause();
			break;
			
		
		
		case 'next';
			$titelgesammt = $sonos->GetPositionInfo();
			$titelaktuel = $titelgesammt["Track"];
			$playlistgesammt = count($sonos->GetCurrentPlaylist());
						
			if ($titelaktuel < $playlistgesammt) {
			$sonos->Next();
			} else {
				trigger_error("Kein weiterer Titel in der Playlist vorhanden", E_USER_NOTICE);
			}
			break;

		case 'previous';
				$sonos->Previous();
			break;  
			
		case 'getzonesonline';
				getzonesonline();
		break;  
		
		
		case 'networkstatus';
				networkstatus();
		break;  
			

			case 'rewind':
				$sonos->Rewind();
			break; 

		case 'mute';
			if($_GET['mute'] == 'false') {
				$sonos->SetMute(false);
				logging();
			}
			else if($_GET['mute'] == 'true') {
				$sonos->SetMute(true);
				logging();
			} else {
				trigger_error('Falscher Mute Parameter', E_USER_NOTICE);
			}       
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
				$sonos->Stop();
			break;  
			
		
		case 'stopall';
			foreach ($sonoszonen as $zone => $player) {
				$sonos = new PHPSonos($sonoszonen[$zone][0]);
				$sonos->Stop();
			}	
		break;  

			
		case 'softstop':
				$save_vol_stop = $sonos->GetVolume();
				$sonos->RampToVolume("SLEEP_TIMER_RAMP_TYPE", "0");
				sleep('17');
				$sonos->Stop();
				$sonos->SetVolume($save_vol_stop);
			break;      
		  
		case 'toggle':
			if($sonos->GetTransportInfo() == 1)  {
				$sonos->Pause();
			} else {
				$sonos->Play();
			}
			break;  
					
		case 'playmode';
			// NORMAL
			// REPEAT_ALL
			// REPEAT_ONE
			// SHUFFLE_NOREPEAT
			// SHUFFLE
			// SHUFFLE_REPEAT_ONE
			if( ($_GET['playmode'] == "normal") || ($_GET['playmode'] == "repeat_all") || ($_GET['playmode'] == "shuffle_norepeat") || ($_GET['playmode'] == "shuffle") || ($_GET['playmode'] == "repeat_one") || ($_GET['playmode'] == "shuffle_repeat_one")) {
				$sonos->SetPlayMode(strtoupper($_GET['playmode']));
			} else {
				trigger_error('falscher PlayMode ausgewählt', E_USER_NOTICE);
			}    
			break;           
	  
		case 'crossfade':
			if((is_numeric($_GET['crossfade'])) && ($_GET['crossfade'] == 0) || ($_GET['crossfade'] == 1)) { 
				$crossfade = $_GET['crossfade'];
			} else {
				trigger_error("falscher Crossfade ausgewählt -> 0 = aus / 1 = an", E_USER_NOTICE);
			}
				$sonos->SetCrossfadeMode($crossfade);
			break; 
		  
		case 'remove':
			if(is_numeric($_GET['remove'])) {
				$sonos->RemoveFromQueue($_GET['remove']);
			} 
			break;   
		
		case 'playqueue':
			$titelgesammt = $sonos->GetPositionInfo();
			$titelaktuel = $titelgesammt["Track"];
			$playlistgesammt = count($sonos->GetCurrentPlaylist());
						
			if ($titelaktuel < $playlistgesammt) {
			$sonos->SetQueue("x-rincon-queue:" . getRINCON($sonoszone[$master][0]) . "#0");
				if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					$sonos->Play();
				} else{
					$sonos->Play();
				}
			logging();
			} else {
				trigger_error("Keine Titel in der Playlist zum Abspielen.", E_USER_NOTICE);
			}
			break;
		
		case 'clearqueue':
				$sonos->SetQueue("x-rincon-queue:" . getRINCON($sonoszone[$master][0]) . "#0");
				$sonos->ClearQueue();
				logging();
			break;  
		  
		case 'volume':
			if(isset($volume)) {
				$sonos->SetVolume($volume);
				getSonosStatVol();
			} else {
				trigger_error('falscher Wertebereich für die Lautstärke, 0-100 ist nur erlaubt', E_USER_NOTICE);
			}
			break;  
		  
		case 'volumeup': 
			$volume = $sonos->GetVolume();
			if($volume < 100) {
				$volume = $volume + $config['MP3']['volumeup'];
				$sonos->SetVolume($volume);
				getSonosStatVol();
			}      
			break;
			
		case 'volumedown':
			$volume = $sonos->GetVolume();
			if($volume > 0) {
				$volume = $volume - $config['MP3']['volumedown'];
				$sonos->SetVolume($volume);
				getSonosStatVol();
			}
			break;   

			
		case 'setloudness':
			if(($_GET['loudness'] == 1) || ($_GET['loudness'] == 0)) {
				$loud = $_GET['loudness'];
				$sonos->SetLoudness($loud);
			} else {
				trigger_error('falscher LoudnessMode', E_USER_NOTICE);
			}    
		break;
		
		
		case 'settreble':
			$Treble = $_GET['treble'];
			$sonos->SetTreble($Treble);
		break;
		
		
		case 'setbass':
			$Bass = $_GET['bass'];
			$sonos->SetBass($Bass);
		break;	
		
		
		case 'setbass':
			$Bass = $_GET['bass'];
			$sonos->SetBass($Bass);
		break;			

		
		case 'addmember':
		global $sonoszone, $sonos;
			$member = $_GET['member'];
			$masterrincon = getRINCON($sonoszone[$master][0]); 
			$sonos = new PHPSonos($sonoszone[$member][0]); 
			$sonos->SetAVTransportURI("x-rincon:" . $masterrincon); 
		break;

		
		case 'removemember':
		global $sonoszone, $sonos;
			$member = $_GET['member'];
			$sonos = new PHPSonos($sonoszone[$member][0]);
			$sonos->BecomeCoordinatorOfStandaloneGroup();
		break;
		
		
		case 'nextradio1':
			global $nextRadioUrl, $nextRadio, $currentRadio;
			
			#echo $currentRadio.'<br>';
			$radio = ($config['RADIO']['radio']);
			$radioanzahl = $result = count($config['RADIO']['radio']);
			$i = 1;
			for ($i; $i <= $radioanzahl; $i++) {
				$radiosplit = explode(',',$radio[$i]);
				$radioStations[$radiosplit[0]] = $radiosplit[1];
			}
			#print_r($radioStations);
			#echo'<br>';
			$playstatus = $sonos->GetTransportInfo();
			$radiovolume = $sonos->GetVolume();
			#$radiosender = $sonos->GetPositionInfo();
			#$senderuri = $radiosender["URI"];
			$radiosender = $sonos->GetMediaInfo();
			$senderuri = $radiosender["CurrentURI"];
			if(empty($radiosender["title"])) {
                reset($radioStations);
                $currentRadio = key($radioStations);
            } else {
				$currentRadio;
			} 
			foreach ($radioStations as $key => $value) {
				if($key == $currentRadio) {
                $nextRadioUrl = next($radioStations);
                $nextRadio    = key($radioStations);
                //if last element catched, move to first element
                if(empty($nextRadioUrl)) {
                    $nextRadioUrl = reset($radioStations);
                    $nextRadio    = key($radioStations);
                }
                    break;
                } else {
                    next($radioStations);
                }
            }
			$currentRadio = $nextRadio;
			$sonos->SetRadio($nextRadioUrl, $nextRadio);
			if($playstatus == 1) {
				$sonos->SetVolume($radiovolume);
				$sonos->Play();
			} else {
				$sonos->RampToVolume($config['TTS']['rampto'], $volume);
				$sonos->Play();
			}
		  break;
		  
		  
		case 'nextradio':
			$radioanzahl_check = $result = count($config['RADIO']);
			if($radioanzahl_check == 0)  {
				trigger_error("Es sind keine Sender in der Konfiguration vorhanden. Bitte nachpflegen!", E_USER_NOTICE);
				exit;
			}
			$playstatus = $sonos->GetTransportInfo();
			$radiovolume = $sonos->GetVolume();
			$radiosender = $sonos->GetPositionInfo();
			$senderuri = $radiosender["URI"];
			$radio = $config['RADIO']['radio'];
			$radioanzahl = $result = count($config['RADIO']['radio']);
			$radio_name = array();
			$radio_adresse = array();
			$i = 1;
			for ($i; $i <= $radioanzahl; $i++) {
				$radiosplit = explode(',',$radio[$i]);
				array_push($radio_name, $radiosplit[0]);
				array_push($radio_adresse, $radiosplit[1]);
			}
			$senderaktuell = array_search($senderuri, $radio_adresse);
			# Wenn nextradio aufgerufen wird ohne eine vorherigen Radiosender
			if( $senderaktuell == "" && $senderuri == "" || substr($senderuri,0,12) == "x-file-cifs:" ) {
				$sonos->SetRadio($radio_adresse[0], $radio_name[0]);
			}
			if ($senderaktuell == $radioanzahl - 1) {
				$sonos->SetRadio($radio_adresse[0], $radio_name[0]);
			} else {
				$sonos->SetRadio($radio_adresse[$senderaktuell + 1], $radio_name[$senderaktuell + 1]);
			}
			if( $debug == 1) {
				echo "Senderuri vorher: " . $senderuri . "<br>";
				echo "Sender aktuell: " . $senderaktuell . "<br>";
				echo "Radioanzahl: " .$radioanzahl . "<br>";
			}
			if($radiovolume >= 10) {
				$sonos->SetVolume($radiovolume);
				$sonos->Play();
			} else {
				$sonos->SetVolume($config['sonoszonen'][$master][4]);
				$sonos->Play();
			}
		  break;

							  
		case 'sonosplaylist':
			logging();
			groupplaylist();
			$sonos = new PHPSonos($sonoszone[$master][0]); 
			$sonos->Play();
		break;
		  
		
		case 'groupsonosplaylist':
			global $debug, $sonos;
			logging();
			$master = $_GET['zone'];
			$groupvol = "1";
			groupplaylist();
			save_current_gr($groupvol);
			$sonos = new PHPSonos($sonoszone[$master][0]); 
			$sonos->Play();
		break;

		case 'radioplaylist':
			logging();
			groupradioplaylist();
			$sonos = new PHPSonos($sonoszone[$master][0]); 
			$sonos->Play();
		break;
		
		
		case 'groupradioplaylist': 
			global $debug, $sonos;
			logging();
			$master = $_GET['zone'];
			$groupvol = "1";
			groupradioplaylist();
			save_current_gr($groupvol);
			$sonos = new PHPSonos($sonoszone[$master][0]); 
			$sonos->Play();
		break;
		
			
		case 'info':
      		 $PositionInfo = $sonos->GetPositionInfo();
			 $GetMediaInfo = $sonos->GetMediaInfo();
			 $radio = $sonos->RadiotimeGetNowPlaying();

			 $title = $PositionInfo["title"];
			 $album = $PositionInfo["album"];
			 $artist = $PositionInfo["artist"];
			 $albumartist = $PositionInfo["albumArtist"];
			 $reltime = $PositionInfo["RelTime"];
			 $bild = $PositionInfo["albumArtURI"];
			 $streamContent = $PositionInfo["streamContent"];
			 if($sonos->GetTransportInfo() == 1 )  {
				# Play
				$status = 'Play';
			 } else {
				# Pause
				$status = 'Pause';
			 }  
			 if($PositionInfo["albumArtURI"] == '')  {
				# Kein Cover - Dann Radio Cover
				$bild = $radio["logo"];
			 }
			 if($PositionInfo["albumArtURI"] == '')  {
				# Kein Title - Dann Radio Title
				$title = $GetMediaInfo["title"];
			 }   
			 if($PositionInfo["album"] == '')  {
				# Kein Album - Dann Radio Stream Info
				$album = $PositionInfo["streamContent"];
			 }   
			 echo'
				  cover: <tab>' . $bild . '<br>   
				  title: <tab>' . $title . '<br>
				  album: <tab>' . $album . '<br>
				  artist: <tab>' . $artist . '<br>
				  time: <tab>' . $reltime . '<br>
				  status: <tab>' . $status . '<br>
				';
		break;
      
    
		case 'cover':
			$PositionInfo = $sonos->GetPositionInfo();
			$radio = $sonos->RadiotimeGetNowPlaying();
			$bild = $PositionInfo["albumArtURI"];
			if($PositionInfo["albumArtURI"] == '')  {
				# Kein Cover - Dann Radio Cover
				$bild = $radio["logo"];
			}
			echo' ' . $bild . ' ';
		break;   
		
		
		case 'title':
			$PositionInfo = $sonos->GetPositionInfo();
			$GetMediaInfo = $sonos->GetMediaInfo();
			$radio = $sonos->RadiotimeGetNowPlaying();
			$title = $PositionInfo["title"];
			if($PositionInfo["albumArtURI"] == '')  {
				# Kein Title - Dann Radio Title
				$title = $GetMediaInfo["title"];
			}
			echo' ' . $title . ' ';
		break;   
			 
		
		case 'artist':
			$PositionInfo = $sonos->GetPositionInfo();
			$GetMediaInfo = $sonos->GetMediaInfo();
			$title = $PositionInfo["title"];
			$album = $PositionInfo["album"];
			$artist = $PositionInfo["artist"];
			$albumartist = $PositionInfo["albumArtist"];
			$reltime = $PositionInfo["RelTime"];
			$bild = $PositionInfo["albumArtURI"];
			echo' ' . $artist . ' ';      
		break;   
		
			 
		case 'album':
			$PositionInfo = $sonos->GetPositionInfo();
			$GetMediaInfo = $sonos->GetMediaInfo();
			$radio = $sonos->RadiotimeGetNowPlaying();
			$album = $PositionInfo["album"];
			if($PositionInfo["album"] == '')  {
				# Kein Album - Dann Radio Stream Info
				$album = $PositionInfo["streamContent"];
			}
			echo'' . $album . '';
		break;

		
		case 'titelinfo':
		if($debug == 1) {
				echo debug();
			}
			$PositionInfo = $sonos->GetPositionInfo();
			$GetMediaInfo = $sonos->GetMediaInfo();
			$title = $PositionInfo["title"];
			$album = $PositionInfo["album"];
			$artist = $PositionInfo["artist"];
			$albumartist = $PositionInfo["albumArtist"];
			$reltime = $PositionInfo["RelTime"];
			$bild = $PositionInfo["albumArtURI"];
				echo'
					<table>
						<tr>
							<td><img src="' . $bild . '" width="200" height="200" border="0"></td>
							<td>
							Titel: ' . $title . '<br><br>
							Album: ' . $album . '<br><br>
							Artist: ' . $artist . '</td>
						</tr>
						<tr>
						<td>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=previous" target="_blank">Zurück</a>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=play" target="_blank">Abspielen</a>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=pause" target="_blank">Pause</a>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=stop" target="_blank">Stop</a>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=next" target="_blank">Nächster</a>
					</table>
				';
			break;
			
		
		
		case 'sendgroupmessage':
			global $sonos, $text, $member, $master, $zone, $messageid, $logging, $words, $voice, $accesskey, $secretkey, $rampsleep, $config, $save_status, $mute, $membermaster, $groupvol, $getgroup, $checkgroup;
			
			#networkstatus();
			if(isset($_GET['volume'])) {
				trigger_error("Die Angabe des Parameters Volume ist innerhalb dieser Syntax nicht zulässig!", E_USER_ERROR);
				exit;
			}
			if(isset($_GET['groupvolume']) && $_GET['groupvolume'] > 100) {
				trigger_error("Der angegebene Wert für groupvolume ist nicht gültig. Erlaubte Werte sind 0 bis 100, bitte prüfen!", E_USER_ERROR);
				exit;
			}
			if(isset($_GET['sonos'])) {
				trigger_error("Der Parameter sonos kann nicht für Gruppendurchsagen verwendet werden!", E_USER_NOTICE);
				exit;
			}
			checkaddon();
			checkTTSkeys();
			$groupvol = "1";
			$master = $_GET['zone'];
			$member = $_GET['member'];
			$member = explode(',', $member);
			$member = getmemberonline($member);
			// speichern der Zonen Zustände und Erstellen der Gruppe
			save_current_gr($groupvol);
			#sleep($config['TTS']['sleepgroupmessage']); // warten gemäß config.php bis Gruppierung abgeschlossen ist
			$sonos = new PHPSonos($sonoszone[$master][0]);
			$sonos->SetGroupMute(true);
			$sonos->SetPlayMode('NORMAL'); 
			if(!isset($_GET['sonos'])) {
				$sonos->Stop();
			}
			create_tts($text, $messageid);
			// Setzen der T2S Lautstärke je Member Zone
			foreach ($member as $player => $zone) {
				$sonos = new PHPSonos($sonoszone[$zone][0]); 
				$newvolume = $sonos->SetVolume($config['sonoszonen'][$zone][3]);
			}
			// Setzen der T2S Lautstärke für Master Zone
			$sonos = new PHPSonos($sonoszone[$master][0]); 
			$newmastervolume = $sonos->SetVolume($config['sonoszonen'][$master][3]);
			// erhöht oder verringert die Defaultwerte aus der config.php um xx Prozent
			if(isset($_GET['groupvolume']) && is_numeric($_GET['groupvolume']) && $_GET['groupvolume'] >= 0 && $_GET['groupvolume'] <= 100) {
				$groupvolume = $_GET['groupvolume'];
				$sonos = new PHPSonos($sonoszone[$master][0]); 
				$sonos = SetGroupVolume($groupvolume);
			}
			play_tts($messageid, $groupvol);
			// wiederherstellen der Ursprungszustände
			restore_previous_gr();
			logging();
			delmp3();
		break;
		
		
		case 'sendmessage':
			global $text, $master, $messageid, $logging, $words, $voice, $accesskey, $secretkey, $rampsleep, $config, $save_status, $membermaster, $groupvol;
			
			#networkstatus();
			if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
				$volume = $_GET['volume'];
			} else 	{
				// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
				$volume = $config['sonoszonen'][$master][3];
			}
			checkaddon();
			checkTTSkeys();
			$groupvol = "0";
			// prüft ob Zone in einer Gruppe ist
			$checkgroup = sonosgroupzone();
			if($checkgroup == true) {
				if(isset($_GET['sonos'])) {
					trigger_error("Die angegebene Zone befindet sich in einer Gruppe! Es stehen keine Informationen zur Verfügung.", E_USER_NOTICE);
				exit;
				}
				save_current_group_ez();
			} else {
				save_current_ez();
			}
			if(!isset($_GET['sonos'])) {
				$sonos->Stop();
			}
			create_tts($text, $messageid);
			play_tts($messageid, $groupvol);
			if($checkgroup == true) {
				restore_previous_group_ez();
			} else {
				restore_previous_ez($save_status);
			}
			logging();
			delmp3();
		break;
		
			
	case 'group':
		logging();
		# Alle Zonen gruppieren
		$masterrincon = getRINCON($sonoszone[$master][0]);
		foreach ($sonoszone as $zone => $ip) {
			if($zone != $_GET['zone']) {
				$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos lox_ipesse
				$sonos->SetAVTransportURI("x-rincon:" . $masterrincon); 
			}
		}
	break;
		
	case 'ungroup':
		logging();
		# Alle Zonen Gruppierungen aufheben
		foreach($sonoszone as $zone => $ip) {
			$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos lox_ipesse
			$sonos->SetQueue("x-rincon-queue:" . getRINCON($sonoszone[$zone][0]) . "#0");
		}
	break;
	

	case 'getsonosinfo':
		getSonosStatVol();
		getSonosTitInt();
	break; 
	
	
	# Debug Bereich ------------------------------------------------------

		case 'getmediainfo':
				echo '<PRE>';
				print_r($sonos->GetMediaInfo());
				echo '</PRE>';
			break;
		
		case 'getmute':
				echo '<PRE>';
				print_r($sonos->GetMute());
				echo '<PRE>';
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
		  
		case 'gettransportinfo':
			# 1 = PLAYING
			# 2 = PAUSED_PLAYBACK
			# 3 = STOPPED

				echo '<PRE>';
					print_r($sonos->GetTransportInfo());
				echo '</PRE>';
			break;        
		
		case 'getradiotimegetnowplaying':
				$radio = $sonos->RadiotimeGetNowPlaying();
				print_r($radio);
			break;

		  
		case 'getvolume':
				echo '<PRE>';
					print_r($sonos->GetVolume());
				echo '</PRE>';
			break;
			
		
		case 'getuser':
				echo '<PRE>';
					echo get_current_user();
				echo '</PRE>';
			break;	
		  
		case 'masterplayer':
			Global $zone, $master;	
			foreach ($sonoszone as $player => $ip) {
				$sonos = new PHPSonos($ip[0]); //Slave Sonos ZP IPAddress
				$temp = $sonos->GetPositionInfo($ip);
				foreach ($sonoszone as $masterplayer => $ip) {
					# hinzugefügt am 18.01 weil Fehler bei Gruppierung auflösen
					$masterrincon = substr($temp["TrackURI"], 9, 24);
					if(getRINCON($ip[0]) == $masterrincon) {
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
				echo "Der Mute Mode ist unbekannt";
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
			$RelativeGroupVolume = $_GET['groupvolume'];
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
		
		if(isset($_GET['timer']) && is_numeric($_GET['timer']) && $_GET['timer'] > 0 && $_GET['timer'] < 60) {
			$timer = $_GET['timer'];
			if($_GET['timer'] < 10) {
				$timer = '00:0'.$_GET['timer'].':00';
			} else {
				$timer = '00:'.$_GET['timer'].':00';
				$timer = $sonos->Sleeptimer($timer);
			}
		} else {
		trigger_error('Die eingegebene Zeitspanne ist nicht korrekt, bitte korrigieren', E_USER_NOTICE);
		}
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
		
		case 'getzonerincon':
			echo '<PRE>';
				getZoneRINCON();
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
					print_r($sonos->ListAlarms());
			echo '</PRE>';
		
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
				trigger_error('Bitte Eingabe korrigieren. On oder Off ist nur erlaubt', E_USER_NOTICE);
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
		
				
		case 'getbass':
			
			echo '<PRE>';
					print_r($sonos->GetBass());
			echo '</PRE>';
		break;
		
		
		case 'getmaster':
			echo '<PRE>';
			global $soplayer;
			getmaster($soplayer);	
			echo '</PRE>';
		break;
				
		case 'getgroup':
			echo '<PRE>';
			Global $sozone;
				getgroup($sozone);
			echo '</PRE>';
		break;
		
		case 'clearlox': // Loxone Fehlerhinweis zurücksetzen
				$handle = fopen("http://$loxuser:$loxpassword@$loxip/dev/sps/io/S-Error/''", "r");
		break;
		
		case 'getzoneplayerlist':
			echo '<PRE>';
				getZonePlayerList();
			echo '</PRE>';
		break;
		
		case 'zonegroups':
			echo '<PRE>';
				zonegroups();
			echo '</PRE>';
		break;
		
		case 'gettopology':
			echo '<PRE>';
				gettopology();
			echo '</PRE>';
		break;
		
		case 'allgroupsmaster':
			echo '<PRE>';
				allgroupsmaster();
			echo '</PRE>';
		break;		
		
		case 'getgcordrincon':
			echo '<PRE>';
				getgcordrincon();
			echo '</PRE>';
		break;		
		
		case 'getgroupstatus':
			echo '<PRE>';
				getgroupstatus();
			echo '</PRE>';
		break;
		
		case 'intelliplay':
			echo '<PRE>';
				IntelliPlay();
			echo '</PRE>';
		break;
		
		
		case 'delsonosplaylist':
			echo '<PRE>';
				$sonos->DelSonosPlaylist('SQ:96');
			echo '</PRE>';
		break;
				
				
		case 'delegategroupcoordinationto':
			echo '<PRE>';
				$sonos->DelegateGroupCoordinationTo('RINCON_000E583BB98E01400','1');
			echo '</PRE>';
		break;
		
		
		case 'favoriten':
			echo '<PRE>';
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos ZP lox_ipesse 
				$browselist = $sonos->Browse("FV:2","c"); 
				#$browselist = $sonos->GetSonosFavorites();
				print_r($browselist);  
			echo '</PRE>';
		break;
		
		
		case 'delmp3':
			echo '<PRE>';
				delmp3();
			echo '</PRE>';
		break;
		
		case 'getpluginfolder':
			echo '<PRE>';
				getPluginFolder();
			echo '</PRE>';
		break;
		
		case 'getmsip':
			echo '<PRE>';
				getMS1data();
			echo '</PRE>';
		break;
		
		case 'getloxonedata':
			echo '<PRE>';
				getLoxoneData();
			echo '</PRE>';
		break;
		
		case 'getip':
			echo '<PRE>';
				getdnsip();
			echo '</PRE>';
		break;
		
		case 'getivonavoices':
			echo '<PRE>';
			getIvonaVoices();
			echo '</PRE>';
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
			$zoneplayerip = getRINCON(substr($GetZoneInfo['IPAddress'], 0 , 13));
			echo '<PRE>';
			echo "RinconID: " . $zoneplayerip;
		break;
		  
		default:
		   trigger_error("Dieser Befehl ist nicht bekannt. <br>index.php?zone=SONOSPLAYER&action=BEFEHL&BEFEHL=Option", E_USER_NOTICE);
		} 
	} else 	{
	trigger_error("Die Zone ".$master." ist nicht vorhanden oder Offline. Bitte prüfen und ggf. in der Config die Zone hinzufügen", E_USER_NOTICE);
}




# Funktionen Bereich ------------------------------------------------------

# Hilfs Funktionen für Skripte ------------------------------------------------------

/********************************************************************************************
/* Funktion : getRINCON --> ermittelt die Rincon-ID der angegebenen Zone
/*
/* @param: 	IP-Adresse der Zone
/* @return: Rincon-ID
/********************************************************************************************/
 function getRINCON($zoneplayerIp) { // gibt die RINCON der Sonos Zone zurück
  $url = "http://" . $zoneplayerIp . ":1400/status/zp";
  $xml = simpleXML_load_file($url);
  $uid = $xml->ZPInfo->LocalUID;
  return $uid;  
  return $playerIP;
 }
 
/*******************************************************************************************
/* Funktion : getZoneRINCON --> erstellt Array und gibt Netzwerkinfos (Rincon, Zone, IP 
/* und MAC Adresse) der Sonos Zonen zurück
/*
/* @return: 	[Zone] <NAME>
/* 				[Rincon] <RINCON-ID>
/*				[Group-ID] <GROUP-ID>
/*				[Coordinator] <TRUE> or <FALSE>
/*				[IP ADRESSE] <IP ADRESSE>
/*				[MAC ADRESSE] <MAC ADRESSE>
/********************************************************************************************/
 function getZoneRINCON() { // erstellt Array und gibt Netzwerkinfos aller Sonos Zonen zurück
	global $sonoszone;
	echo "Übersicht Info je Zone.<br><br>";
	$network = array();
	$i = 0;
	foreach ($sonoszone as $player => $ip) {
		$url = "http://" . $ip[0] . ":1400/status/zp";
		$xml = simpleXML_load_file($url);
		$uid = $xml->ZPInfo->LocalUID;
		$zn = $xml->ZPInfo->ZoneName;
		$ipa = $xml->ZPInfo->IPAddress;
		$mac = $xml->ZPInfo->MACAddress;
		$group = getgroup($player);
		$master = getmaster($player);
		$network[$group] = array('Zone' => $player,
									'Rincon-ID' => (string) $uid, 
									#'Group-ID' => $group,
									'Coordinator' => (string) $master,
									'IP Address' => (string) $ipa,
									'MAC Address' => (string) $mac
									);
						
								
		$i++;
	}
// später entfernen
	print_r ($network)."<br>";	
	return array($network);
 }	

/*****************************************************************************************************
/* Funktion : random --> generiert eine Zufallszahl zwischen 90 und 99
/*
/* @return: Zahl
/******************************************************************************************************/
 function random() {
	$zufallszahl = mt_rand(90,99); 
	return $zufallszahl;
 } 
 
 
 /*****************************************************************************************************
/* Funktion : delmp3 --> löscht die hash5 codierten MP§ Dateien aus dem Verzeichnis 'messageStorePath'
/*
/* @param:  nichts
/* @return: nichts
/******************************************************************************************************/
 function delmp3() {
	global $config, $debug;
	
	# http://www.php-space.info/php-tutorials/75-datei,nach,alter,loeschen.html	
	$dir = $config['SYSTEM']['messageStorePath'];
    $folder = dir($dir);
	$store = '-'.$config['MP3']['MP3store'].' days';
	while ($dateiname = $folder->read()) {
	    if (filetype($dir.$dateiname) != "dir") {
            if (strtotime($store) > @filemtime($dir.$dateiname)) {
					if (strlen($dateiname) == 36) {
						if (@unlink($dir.$dateiname) != false)
							echo $dateiname.' wurde gelöscht<br>';
						else
							echo $dateiname.' konnte nicht gelöscht werden<br>';
					}
			}
        }
    }
	if($debug == 1) { 
		echo "<br>Alle Dateien entsprechend den Kriterien wurden erfolgreich gelöscht";
	}
    $folder->close();
    exit; 	 
 }
 
/*****************************************************************************************************
/* Funktion : _assertNumeric --> Prüft ob ein Eingabe numerisch ist
/*
/* @param: 	Eingabe die geprüft werden soll
/* @return: TRUE or FALSE
/******************************************************************************************************/
 function _assertNumeric($number) {
	// prüft ob eine Eingabe numerisch ist
    if(!is_numeric($number)) {
        trigger_error("Die Eingabe ist nicht numerisch. Bitte wiederholen", E_USER_NOTICE);
    }
    return $number;
 }
 
/*****************************************************************************************************
/* Funktion : networkstatus --> Prüft ob alle Zonen Online sind
/*
/* @return: TRUE or FALSE
/******************************************************************************************************/
function networkstatus() {
	global $sonoszonen, $zonen, $config, $debug;
	
	foreach($sonoszonen as $zonen => $ip) {
		$start = microtime(true);
		if (!$socket = @fsockopen($ip[0], 1400, $errno, $errstr, 3)) {
			echo "Die Zone ".$zonen." mit IP: ".$ip[0]." ==> Offline :-( Bitte dringend Status überprüfen!<br/>"; 
		} else { 
			$latency = microtime(true) - $start;
			$latency = round($latency * 10000);
			echo "Die Zone ".$zonen." mit IP: ".$ip[0]." ==> Online :-) Die Antwortzeit betrug ".$latency." Millisekunden <br/>";
		}
	}
	
}


/*****************************************************************************************************
/* Funktion : getzonesonline --> Prüft ob alle Zonen Online sind
/*
/* @return: Array aller Online Zonen
/******************************************************************************************************/
function getzonesonline() {
	global $sonoszonen, $zonen, $config, $debug, $debug;
	
	foreach($sonoszonen as $zonen => $ip) {
		if (!$socket = @fsockopen($ip[0], 1400, $errno, $errstr, 2)) {
			echo '<br>';
		} else {
			$sonoszone[$zonen] = $ip;
		}
	}
	print_r($sonoszone);
}


/*****************************************************************************************************
/* Funktion : getmemberonline --> Prüft ob  Member Online sind
/*
/* @param:  Array der Member die geprüft werden soll
/* @return: Array aller Member Online Zonen
/******************************************************************************************************/
function getmemberonline($member) {
	global $sonoszonen, $zonen, $debug, $config;
	
	$memberzones = $member;
	foreach($memberzones as $zonen) {
		if(!array_key_exists($zonen, $sonoszonen)) {
			trigger_error("Die angegebene Zone (Member) existiert nicht. Bitte korrigieren!!", E_USER_NOTICE);
		}
	}
	foreach($memberzones as $zonen) {
		if(!$socket = @fsockopen($sonoszonen[$zonen][0], 1400, $errno, $errstr, 2)) {
			echo '<br>';
		} else {
			$members[] = $zonen;
		}
	}
	// print_r($members);
	return($members);
}


/*****************************************************************************************************
/* Funktion : URL_Encode --> ersetzt Steuerzeichen durch URL Encode
/*
/* @param: 	Zeichen das geprüft werden soll
/* @return: Sonderzeichen
/******************************************************************************************************/	
function URL_Encode($string) { 
    $entities = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D'); 
    $replacements = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]"); 
    return str_replace($entities, $replacements, urlencode($string)); 
} 

/*****************************************************************************************************
/* Funktion : File_Put_Array_As_JSON --> erstellt eine JSON Datei aus einer Array
/*
/* @param: 	Dateiname
/*			Array die gespeichert werden soll			
/* @return: Datei
/******************************************************************************************************/	
function File_Put_Array_As_JSON($FileName, $ar, $zip=false) {
	if (! $zip) {
		return file_put_contents($FileName, json_encode($ar));
    } else {
		return file_put_contents($FileName, gzcompress(json_encode($ar)));
    }
}

/*****************************************************************************************************
/* Funktion : File_Get_Array_From_JSON --> liest eine JSON Datei ein und erstellt eine Array
/*
/* @param: 	Dateiname
/* @return: Array
/******************************************************************************************************/	
function File_Get_Array_From_JSON($FileName, $zip=false) {
	// liest eine JSON Datei und erstellt eine Array
    if (! is_file($FileName)) 	{ trigger_error("Fatal: Die Datei $FileName gibt es nicht.", E_USER_NOTICE); }
	    if (! is_readable($FileName))	{ trigger_error("Fatal: Die Datei $FileName ist nicht lesbar.", E_USER_NOTICE); }
            if (! $zip) {
				return json_decode(file_get_contents($FileName), true);
            } else {
				return json_decode(gzuncompress(file_get_contents($FileName)), true);
	    }
}
	
   
/*****************************************************************************************************
/* Funktion : debug --> gibt verschiedene Info bzgl. der Zone aus
/*
/* @return: GetPositionInfo, GetMediaInfo, GetTransportInfo, GetTransportSettings, GetCurrentPlaylist
/******************************************************************************************************/
  function debug() {
 	global $sonos, $sonoszone;
	$GetPositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$GetTransportInfo = $sonos->GetTransportInfo();
	$GetTransportSettings = $sonos->GetTransportSettings();
	$GetCurrentPlaylist = $sonos->GetCurrentPlaylist();
	
	echo '<PRE>';
	echo '<br />GetPositionInfo:';
	print_r($GetPositionInfo);

	echo '<br />GetMediaInfo:';
	print_r ($GetMediaInfo); // Radio

	echo '<br />GetTransportInfo:';
	print_r ($GetTransportInfo);
	
	echo '<br />GetTransportSettings:';
	print_r ($GetTransportSettings);  
	
	echo '<br />GetCurrentPlaylist:';
	print_r ($GetCurrentPlaylist);
	echo '</PRE>';
}

/*****************************************************************************************************
/* Funktion : logging_alt --> erstellt einfache Log Datei
/*
/* @return: Log Datei
/******************************************************************************************************/
 function logging_alt() // Nicht mehr in Nutzung
 { 
 	global $log, $config, $logpath;
	if($log == true) {
		$content = date("d.m.Y - H:i:s") . ' ' . $_SERVER['REQUEST_URI'] . "\r\n";
		$handle = fopen ($logpath."/".$config['SYSTEM']['logfile'], 'a');
		fwrite ($handle, $content);
		fclose ($handle);
		return;
	}
		trigger_error("Logging ist derzeit ausgeschaltet. Bitte in der config.php aktivieren", E_USER_NOTICE);
 }
 
/*****************************************************************************************************
/* Funktion : logging --> erstellt monatliche Log Datei
/*
/* @return: Log Datei
/******************************************************************************************************/
 function logging() {
 global $master, $log, $logpath;

	$format = "txt"; //Moeglichkeiten: csv und txt
 	$datum_zeit = date("d.m.Y H:i:s");
	$ip = $_SERVER["REMOTE_ADDR"];
	$site = $_SERVER['REQUEST_URI'];
	$browser = $master;
 
	$monate = array(1=>"Januar", 2=>"Februar", 3=>"Maerz", 4=>"April", 5=>"Mai", 6=>"Juni", 7=>"Juli", 8=>"August", 9=>"September", 10=>"Oktober", 11=>"November", 12=>"Dezember");
	$monat = date("n");
	$jahr = date("y");
 
	$dateiname=$logpath."/log_".$monate[$monat]."_$jahr.$format";
 	$header = array("Datum/Uhrzeit", "    Zone  ", "Syntax");
	$infos = array($datum_zeit, $master, $site);
 	if($format == "csv") {
		$eintrag= '"'.implode('", "', $infos).'"';
	} else { 
		$eintrag = implode("\t", $infos);
	}
 	$write_header = file_exists($dateiname);
 	$datei=fopen($dateiname,"a");
 	if(!$write_header) {
		if($format == "csv") {
			$header_line = '"'.implode('", "', $header).'"';
		} else {
			$header_line = implode("\t", $header);
		}
	fputs($datei, $header_line."\n");
	}
	fputs($datei,$eintrag."\n");
	fclose($datei);
	
 }
 


 

/*****************************************************************************************************
/* Funktion : SetGroupVolume --> setzt Volume für eine Gruppe
/*
/* @param: 	Volume
/* @return: 
/******************************************************************************************************/	
function SetGroupVolume($groupvolume) {
	global $sonos;
	$sonos->SnapshotGroupVolume();
	$GroupVolume = $_GET['groupvolume'];
	$GroupVolume = $sonos->SetGroupVolume($GroupVolume);
 }

/*****************************************************************************************************
/* Funktion : SetRelativeGroupVolume --> setzt relative Volume für eine Gruppe
/*
/* @param: 	Volume
/* @return: 
/******************************************************************************************************/	
function SetRelativeGroupVolume($volume) {
	global $sonos;
	$sonos->SnapshotGroupVolume();
	$RelativeGroupVolume = $_GET['groupvolume'];
	$RelativeGroupVolume = $sonos->SetRelativeGroupVolume($RelativeGroupVolume);
}

/*****************************************************************************************************
/* Funktion : SnapshotGroupVolume --> ermittelt das prozentuale Volume Verhältnis der einzelnen Zonen
/* einer Gruppe (nur vor SetGroupVolume oder SetRelativeGroupVolume nutzen)
/*
/* @return: Volume Verhältnis
/******************************************************************************************************/	
function SnapshotGroupVolume() {
	global $sonos;
	$SnapshotGroupVolume = $sonos->SnapshotGroupVolume();
	return $SnapshotGroupVolume;
}

/*****************************************************************************************************
/* Funktion : SetGroupMute --> setzt alle Zonen einer Gruppe auf Mute/Unmute
/* einer Gruppe
/*
/* @param: 	MUTE or UNMUTE
/* @return: 
/******************************************************************************************************/	
 function SetGroupMute($mute) {
	global $sonos;
		$sonos->SetGroupMute($mute);
 }

#-- ab hier T2S Funktionen ------------------------------------------------------------------------------------------

/*****************************************************************************************************
/* Funktion : create_tts --> erstellt MP3 Datei basierend auf Text
/*
/* @param: 	Text oder Messasge ID
/* @return: mp3 Datei
/******************************************************************************************************/		
function create_tts($text, $messageid) {
	global $sonos, $text, $member, $master, $zone, $messageid, $logging, $words, $voice, $accesskey, $secretkey, $rampsleep, $config, $save_status, $mute;
	global $fileolang, $fileo, $volume, $home;
				
	# erlaubt das Abspielen einer Nachricht ohne messageid
	$messageid = !empty($_GET['messageid']) ? $_GET['messageid'] : '0';
	$messageid = _assertNumeric($messageid);
	$rampsleep = $config['TTS']['rampto'];
							
	if(isset($_GET['weather'])) {
		# ruft die weather-to-speech Funktion auf
		if(substr($home,0,4) == "/opt") {	
			include_once("addon/weather-to-speech.php");
		} else {
			include_once("addon/weather-to-speech_noLB.php");
		}
		$fileo = w2s($text);
		$words = substr($fileo, 0, 500); // Begrenzung des Textes auf 500 Zeichen
		$words = urlencode($fileo);
		} 
	elseif (isset($_GET['clock'])) {
		# ruft die clock-to-speech Funktion auf
		include_once("addon/clock-to-speech.php");
		$fileo = c2s($text);
		$words = urlencode($fileo);
		}
	elseif (isset($_GET['sonos'])) {
		# ruft die sonos-to-speech Funktion auf
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
		# spielt die angegebene Nachricht ab
		$fileo = $_GET['messageid'];
		}
	elseif (($messageid == 0) && ($text == '')) {
		# vorbereiten der TTS Nachricht an t2s
		$fileo = !empty($_GET['text']) ? $_GET['text'] : ''; 
		$words = substr($_GET['text'], 0, 500); // Begrenzung des Textes auf 500 Zeichen
		$words = urlencode($_GET['text']);				
		}	
	# Name der MP3 als MD5 Hash zum Speichern codieren
	$fileo  = md5($words);
	#$fileo  = $words;
	$fileolang = "$fileo";
	# ruft die zur Verfügung stehenden T2S Engines auf (je nach config)					
	if (($messageid == '0') && ($fileo != '')) {
		if ($config['TTS']['t2s_engine'] == 1001) {
			include_once("voice_engines/VoiceRSS.php");
		}
		if ($config['TTS']['t2s_engine'] == 3001) {
			include_once("voice_engines/MAC_OSX.php");
		}
		if ($config['TTS']['t2s_engine'] == 2001) {
			include_once("voice_engines/Ivona.php");
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

/*****************************************************************************************************
/* WICHTIGER TEIL DER GRUPPEN T2S DURCHSAGE
/*
/* Funktion : save_current_gr --> erstellt eine Gruppe von Zonen und speichert vorher die Zustände
/*
/* @param: 	Zonen die zu einer Gruppe gehören sollen
/* @return: 
/******************************************************************************************************/	
function save_current_gr($groupvol) {
	global $sonoszone, $master, $sonos, $config, $zonevolume, $save_status, $membermaster, $player, $fvolume, $members, $zonegroups, $zone, $sz, $groupid;
	
	$file = 'tmp_sz.json'; 
	$master = $_GET['zone'];
	$zonen = array();
	foreach ($sonoszone as $player => $sz) {
		array_push($zonen, $player);
	}
	$groupid = allgroupsmaster();
	foreach ($zonen as $player => $sz) {
		foreach ($zonen as $szone => $zone) {
			$sonos = new PHPSonos($sonoszone[$sz][0]); //Sonos IP Adresse
			$save_status[$sz]['Mute'] = $sonos->GetMute($sz);
			$save_status[$sz]['Volume'] = $sonos->GetVolume($sz);
			$save_status[$sz]['MediaInfo'] = $sonos->GetMediaInfo($sz);
			$save_status[$sz]['PositionInfo'] = $sonos->GetPositionInfo($sz);
			$save_status[$sz]['TransportInfo'] = $sonos->GetTransportInfo($sz);
			$save_status[$sz]['TransportSettings'] = $sonos->GetTransportSettings($sz);
			#if($save_status[$sz]['TransportSettings']['shuffle'] == 1) {
				#$save_status[$sz]['Shuffle'] = $sonos->SaveQueue($sz).'true';
			#}
			$save_status[$sz]['Topology'] = gettopology($sz);
			if(!empty($groupid)) {
				if (array_key_exists($save_status[$sz]['Topology']['IP-Adresse'], $groupid)) {
					$save_status[$sz]['GroupCoordinator'] = 'true';
					$save_status[$sz]['Groupmember'] = zonegroups($sz);
				} else {
					$save_status[$sz]['GroupCoordinator'] = 'false';
				}
			}
		}
	}
	// konvertiert Daten in JSON und speichert in Datei
	File_Put_Array_As_JSON($file, $save_status);
	// erstellt Gruppe für T2S
	$member = $_GET['member'];
	$member = explode(',', $member);
	#$member = getmemberonline($member);
	$masterrincon = getRINCON($sonoszone[$master][0]); 
	$sonos = new PHPSonos($sonoszone[$master][0]);
	#$sonos->SetAVTransportURI("");
	$sonos->BecomeCoordinatorOfStandaloneGroup();
	$member = getmemberonline($member);
	#print_r($member);
	foreach ($member as $zone) {
		$sonos = new PHPSonos($sonoszone[$zone][0]);
		$sonos->SetAVTransportURI("x-rincon:" . $masterrincon); 
	}
	#print_r($save_status);
	#return $save_status;
}

/**************************************************************************************************************
/* WICHTIGER TEIL DER GRUPPEN T2S DURCHSAGE
/*
/* Funktion : restore_previous_gr --> nimmt angegebene Zone(n) aus Gruppe heraus und stellt Original Zustand wieder her
/*
/* @param: 	Zonen die aus einer Gruppe entfernt werden sollen
/* @return: 
/**************************************************************************************************************/		
function restore_previous_gr() {
	global $sonoszone, $logpath, $master, $sonos, $config, $zonevolume, $groupvol, $save_status, $results, $membermaster, $member, $player, $grouping, $zone, $rinconid;
	
	$file = 'tmp_sz.json'; 
	// nimmt angegebene Zone(n) aus der Gruppe heraus
	$member = $_GET['member'];
	$master = $_GET['zone'];
	$member = explode(',', $member);
	$member = getmemberonline($member);
	foreach ($member as $zone) {
		$sonos = new PHPSonos($sonoszone[$zone][0]);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
	}
	// Importiert die Daten mit den gespeicherten Einstellungen in eine Array
	$import = array();
	$import = File_Get_Array_From_JSON($file, $zip=false);
	// fügt den Master der Array hinzu
	array_push($member, $master);
	#print_r($import);
	// Wiederherstellen der Ursprungszustände
	foreach($member as $player => $zone) {
		$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos IP Adresse
		// zum Wiederherstellen es lief:
		//******************************
		# Playliste
		if (substr($import[$zone]['PositionInfo']["TrackURI"], 0, 5) == "npsdy" || 
			substr($import[$zone]['PositionInfo']["TrackURI"], 0, 11) == "x-file-cifs" || 
			substr($import[$zone]['PositionInfo']["TrackURI"], 0, 12) == "x-sonos-http" || 
			substr($import[$zone]['PositionInfo']["TrackURI"], 0, 15) == "x-sonos-spotify" && ($import[$zone]['GroupCoordinator'] == 'false')) { // Es läuft eine Musikliste
			$sonos->SetTrack($import[$zone]['PositionInfo']['Track']);
			$sonos->Seek($import[$zone]['PositionInfo']['RelTime'],"NONE");
				if($import[$zone]['TransportSettings']['shuffle'] == 1) {
					$sonos->SetPlayMode('SHUFFLE_NOREPEAT'); // schaltet Zufallswiedergabe wieder ein 
				} else {
					$sonos->SetPlayMode('NORMAL'); // spielt im Normal Modus weiter
				}
			} 
			# TV Playbar
			elseif (substr($import[$zone]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
				$sonos->SetAVTransportURI($import[$zone]['PositionInfo']["TrackURI"]); 
			} 
			# Radio
			elseif (($import[$zone]['PositionInfo']["TrackDuration"] == '') && ($import[$zone]['PositionInfo']["title"] <> '')){
				$radioname = $import[$zone]['MediaInfo']["title"];
				$sonos->SetRadio($import[$zone]['PositionInfo']["TrackURI"], $radioname);
			}
			# Die Zone ist in einer Gruppe
			if(isset($import[$zone]['GroupCoordinator'])) {
				if ((substr($import[$zone]['PositionInfo']["TrackURI"], 0, 9) == "x-rincon:") or ($import[$zone]['GroupCoordinator'] == 'true')) {
					
					# Die Zone ist Master einer Gruppe
					if (($import[$zone]['GroupCoordinator'] = 'true') && (substr($import[$zone]['PositionInfo']["TrackURI"], 0, 9) != "x-rincon:")) {
						$members = $import[$zone]['Groupmember'];
						foreach ($members as $memberg => $rinconid) {
							$gmaster = getgcordrincon($rinconid);
							if($gmaster == 'true') {
								if(getRINCON($sonoszone[$zone][0]) != $rinconid) {
									#echo 'Master gmaster<br>';
									$sonos = new PHPSonos($sonoszone[$zone][0]);
									$sonos->SetAVTransportURI('x-rincon:'.$rinconid);
								}
							}
						}
					} 
					# Die Zone ist Member einer Gruppe
					if (substr($import[$zone]['PositionInfo']["TrackURI"], 0, 9) == "x-rincon:") { 
						$rinconid = (substr($import[$zone]['PositionInfo']["TrackURI"], 9, 24));
						$gmaster = getgcordrincon($rinconid);
						if($gmaster == 'true') {
							#echo 'Test gmaster '.$zone.' = true<br>';
							$sonos = new PHPSonos($sonoszone[$zone][0]);
							$sonos->SetAVTransportURI('x-rincon:'.$rinconid);
						}
						if($gmaster == 'false') {
							#echo 'Test gmaster '.$zone.' = false';
							$rincon = (substr($import[$zone]['PositionInfo']["TrackURI"], 9, 24));
							$fzone = recursive_array_search($rincon,$import);
							$rinconz = ($import[$fzone]['Topology']['Rincon-ID']);
							$sonos = new PHPSonos($sonoszone[$zone][0]);
							$sonos->SetAVTransportURI('x-rincon:'.$rinconz);
					}
				}
			}				
		}
		$sonos->SetVolume($import[$zone]['Volume']);
		$sonos->SetMute($import[$zone]['Mute']);
		if($import[$zone]['TransportInfo'] != 1) {
			$sonos->Stop();
		} else {
			$sonos->Play();
		}
		
	}
	#unlink($file); 
}	

/**************************************************************************************************************
/* Funktion : play_tts --> spielt die vorher generierte mp3 datei ab
/*
/* @param: 	MessageID, Parameter zur Unterscheidung ob Gruppen oder EInzeldurchsage
/* @return: nichts
/**************************************************************************************************************/		
function play_tts($messageid, $groupvol) {
	global $volume, $config, $sonos, $messageid, $save_status, $sonoszone, $master, $groupvol, $getgroup, $group, $myMessagepath, $save_gr_status;
	// wenn Single T2S dann Volume und Mute setzten
	if($groupvol == "0") {
		$sonos->SetMute(false);
		$sonos->SetVolume($volume);
	}
	$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
	if(isset($_GET['messageid'])) {
		$mp3 = $_GET['messageid'];
		if((!empty($config['MP3']['MP3path'])) || (!empty($mp3))) {
			$mpath = $myMessagepath."".$config['MP3']['MP3path'];
		} 
	} else {
		$mpath = $myMessagepath;
	}
	if(isset($_GET['playgong'])) {
		if(isset($_GET['playgong']) && ($_GET['playgong'] == "yes")) {
			if((!empty($config['MP3']['MP3path'])) || (!empty($mp3))) {
				$mpath = $myMessagepath."".$config['MP3']['MP3path'];
			}
			$sonos->AddToQueue("x-file-cifs:" . $mpath . "/" . $config['MP3']['file_gong'] . ".mp3");
		}
		if(isset($_GET['playgong']) && ($_GET['playgong'] == is_numeric($_GET['playgong']))) {
			$sonos->AddToQueue("x-file-cifs:" . $mpath . "/" . $_GET['playgong'] . ".mp3");	
		}
		if($groupvol == "1") { // Gruppen T2S Durchsage
			$save_plist = $sonos->GetCurrentPlaylist();
			$message_pos = count($save_plist);
		}
		elseif(($groupvol == "0") and (empty($save_status['PositionInfo']["duration"]))) { // Einzel T2S Durchsage an Gruppe
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$save_plist = $sonos->GetCurrentPlaylist();
			$message_pos = count($save_plist);
		} else { // Einzel T2S Durchsage an Single Zone
			$message_pos = count($save_status['CurrentPlaylist']) + 1;
		}
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$sonos->SetQueue("x-rincon-queue:" . getRINCON($sonoszone[$master][0]) . "#0"); //Playliste aktivieren
		$sonos->SetGroupMute(false);
		$sonos->SetPlayMode('NORMAL');
		try {
			$sonos->SetTrack($message_pos);
			$sonos->Play();   // Abspielen
		} catch (Exception $e) {
			trigger_error("Die T2S Message konnte nicht abgespielt werden!", E_USER_NOTICE);
		}
		$abort = false;
		# Prüfen ob Meldung zu Ende gespielt ist
		sleep($config['TTS']['sleeptimegong']); // warten gemäß config.php
		while ($sonos->GetTransportInfo()==1) {
			usleep(200000); // Alle 200ms wird abgefragt
		}
		# Message wieder aus Queue entfernen
		$sonos->RemoveFromQueue($message_pos); 
		}
		#-- Ende Jingle  ------------------------------------------------------------------------------------------
									
		#-- TTS Durchsage abspielen	--------------------------------------------------------------------------------
		$mess = isset($_GET['sendmessage']);
		if (!isset($_GET['messageid'])) {
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->AddToQueue('x-file-cifs:'.$myMessagepath . "" . $messageid . ".mp3");
		} else {
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->AddToQueue('x-file-cifs:'.$mpath . "/" . $messageid . ".mp3");
		}
		#echo "x-file-cifs:".$myMessagepath . "" . $messageid . ".mp3<br>";
		#echo "x-file-cifs:".$mpath . "/" . $messageid . ".mp3";
		#echo 'groupvol: '.$groupvol;
		$gmaster = getmaster($master);
		if($groupvol == "1") { // Gruppen T2S Durchsage
			$save_plist = $sonos->GetCurrentPlaylist();
			$message_pos = count($save_plist);
		}
		elseif(($groupvol == "0") and (empty($save_status['PositionInfo']["duration"]))) { // Einzel T2S Durchsage an Gruppe
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			$save_plist = $sonos->GetCurrentPlaylist();
			$message_pos = count($save_plist);
		} else { // Einzel T2S Durchsage an Single Zone
			$message_pos = count($save_status['CurrentPlaylist']) + 1;
		}
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$sonos->SetQueue("x-rincon-queue:" . getRINCON($sonoszone[$master][0]) . "#0"); //Playliste aktivieren
		$sonos->SetGroupMute(false);
		$sonos->SetPlayMode('NORMAL');
		#try {
			$sonos->SetTrack($message_pos);
			$sonos->Play();   // Abspielen
		#} catch (Exception $e) {
		#	trigger_error("Die T2S Message konnte nicht abgespielt werden!", E_USER_NOTICE);
		#}
		$abort = false;
		# Prüfen ob Meldung zu Ende gespielt ist
		sleep($config['TTS']['sleeptimegong']); // warten gemäﬂ config.php
		while ($sonos->GetTransportInfo()==1) {
			usleep(200000); // Alle 200ms wird abgefragt
		}
		# Message wieder aus Queue entfernen
		$sonos->RemoveFromQueue($message_pos); 
		#sleep($config['TTS']['sleeptimegong']);   // Wartezeit bis alter Zustand wieder hergestellt wird
		
}

/**************************************************************************************************************
/* WICHTIGER TEIL DER EINZEL T2S DURCHSAGE (Zone befindet sich NICHT in einer Gruppe)
/*
/* Funktion : save_current_ez --> speichert Zustand der Zone
/*
/* @param: 	leer
/* @return: Details der Zone vorm abspielen der T2S
/**************************************************************************************************************/
function save_current_ez() {
	global $master, $config, $sonoszone, $sonos, $messageid, $rampsleep, $save_status;
	$save_status['MediaInfo'] = $sonos->GetMediaInfo();
	$save_status['PositionInfo'] = $sonos->GetPositionInfo();
	$save_status['Mute'] = $sonos->GetMute();
	$save_status['Volume'] = $sonos->GetVolume();
	$save_status['TransportInfo'] = $sonos->GetTransportInfo();
	$save_status['TransportSettings'] = $sonos->GetTransportSettings();
	$save_status['CurrentPlaylist'] = $sonos->GetCurrentPlaylist();
	if(($save_status['TransportInfo'] == 2) || ($save_status['TransportInfo'] == 3) || ($messageid == '100') || ($sonos->GetVolume() < 10)) {
		sleep('1');
	} else { 
	if($rampsleep === true) {
		$sonos->RampToVolume("SLEEP_TIMER_RAMP_TYPE", "0");
		sleep('10');
		}
	}

	if ($save_status['PositionInfo']["TrackDuration"] == '')  { 
		# zum Wiederherstellen es lief ein Radio Sender
		$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
	}
	if (substr($save_status['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")  {  
		# zum Wiederherstellen es lief die TV Playbar
		$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
		$sonos->SetQueue("x-rincon-queue:" . getRINCON($sonoszone[$master][0]) . "#0"); //Playliste aktivieren
	}
	# Wenn Playliste läuft erst den Playmodus auf Normal setzen
	if (substr($save_status['PositionInfo']["TrackURI"], 0, 5) == "npsdy" || 
		substr($save_status['PositionInfo']["TrackURI"], 0, 11) == "x-file-cifs" || 
		substr($save_status['PositionInfo']["TrackURI"], 0, 12) == "x-sonos-http" ||
		substr($save_status['PositionInfo']["TrackURI"], 0, 15) == "x-sonos-spotify") { // Es läuft eine Musikliste
		$sonos->SetPlayMode('NORMAL');
	}
	#print_r ($save_status);
	return ($save_status);
}

/**************************************************************************************************************
/* WICHTIGER TEIL DER EINZEL T2S DURCHSAGE (Zone befindet sich NICHT in einer Gruppe)
/*
/* Funktion : restore_previous_ez --> stellt den vorheigen Zustand der Zone wieder her
/*
/* @param: 	Details der Zone vorm abspielen der T2S
/* @return: leer
/**************************************************************************************************************/
function restore_previous_ez($save_status, $groupmember = false) {
	global $save_status, $sonos, $rampsleep, $groupmember;
	
	# Playliste
	if (substr($save_status['PositionInfo']["TrackURI"], 0, 5) == "npsdy" || 
		substr($save_status['PositionInfo']["TrackURI"], 0, 11) == "x-file-cifs" || 
		substr($save_status['PositionInfo']["TrackURI"], 0, 12) == "x-sonos-http" ||
		substr($save_status['PositionInfo']["TrackURI"], 0, 15) == "x-sonos-spotify") { // Es läuft eine Musikliste 
		$sonos->SetTrack($save_status['PositionInfo']["Track"]);
		$sonos->Seek($save_status['PositionInfo']["RelTime"],"NONE");
		if($save_status['TransportSettings']['shuffle'] == 1) {
			$sonos->SetPlayMode('SHUFFLE_NOREPEAT'); // schaltet Zufallswiedergabe wieder ein 
		} else {
			$sonos->SetPlayMode('NORMAL'); // spielt im Normal Modus weiter
		}
		} 
		# TV Playbar
		elseif (substr($save_status['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
			$sonos->SetAVTransportURI($save_status['PositionInfo']["TrackURI"]); 
			} 
		# Radio
		elseif (($save_status['PositionInfo']["TrackDuration"] == '') && ($save_status['PositionInfo']["title"] <> '')){
			$sonos->SetRadio($save_status['PositionInfo']["TrackURI"], $save_status['MediaInfo']["title"]);
			}
		# und für alle Volume, Mute und Play
		$sonos->SetVolume($save_status['Volume']);
		$sonos->SetMute($save_status['Mute']);
		if ($save_status['TransportInfo'] == 1) {
			if ($rampsleep === true) {
				$sonos->RampToVolume("ALARM_RAMP_TYPE", $save_status['Volume']);	# alternativ AUTOPLAY_RAMP_TYPE
			} else {
				$sonos->SetVolume($save_status['Volume']);
			}
		$sonos->Play();	
	}
}


/********************************************************************************************
/* WICHTIGER TEIL DER EINZEL T2S DURCHSAGE (Zone befindet sich in einer Gruppe)
/*
/* Funktion : save_current_group_ez --> falls Zone in einer Gruppe ist wird geprüft ob die Zone
/* Master oder Member ist und Zone wird vor T2S aus der Gruppe genommen
/*
/* @param:	leer
/* @return: true oder false
/********************************************************************************************/
function save_current_group_ez() {
	global $sonos, $master, $sonoszone, $getgroup, $mastergroup, $save_vol, $zone, $groupvol, $save_gr_status;
	
		$file = 'tmp_gr.json'; 
		$master = $_GET['zone'];
		if (function_exists('getzoneplayerlist')) {
			$topology = getzoneplayerlist();
		}
		File_Put_Array_As_JSON($file, $topology);
		// Zone aus Gruppe entfernen
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$save_gr_status['PositionInfo'] = $sonos->GetPositionInfo();
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		$save_vol = $sonos->GetVolume($master);
		$check = getgroupstatus($master);
		if($check = 'master') {
			$save_gr_status['PositionInfo'];
			#$groupvol = '3';
		}
		#print_r($groupvol);
		#return $groupvol;
	} 
#}

/********************************************************************************************
/* WICHTIGER TEIL DER EINZEL T2S DURCHSAGE (Zone befindet sich in einer Gruppe)
/*
/* Funktion : restore_previous_group_ez --> falls Zone in einer Gruppe war wird sie dieser 
/* nach erfolgter T2S wieder hinzugefügt
/*
/* @param: leer                             
/* @return: nichts
/********************************************************************************************/
function restore_previous_group_ez() {
	global $sonoszone, $sonos, $getgroup, $master, $mastergroup, $save_vol, $zone, $coordinators, $save_play, $groupm;
	
	$file = 'tmp_gr.json';
	$import_data = array();
	$import_data = File_Get_Array_From_JSON($file, $zip=false);
	$master = $_GET['zone'];
	$key = recursive_array_search($master,$import_data);
	$topology = getzoneplayerlistnew(); 
	if($import_data[$key][0]['Master'] = '1')  { 
		if ($import_data[$key][0]['Sonos Name'] == $master) {
			$group_data = $topology[$key][0]['Rincon']; // Zone war Master
		} else {
			if($import_data[$key][0]['Master'] = '1'){
				$group_data = $import_data[$key][0]['Rincon']; // Zone war Member
			}
		}
	}
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$sonos->SetAVTransportURI("x-rincon:" . $group_data);
	$sonos->SetVolume($save_vol);
	unlink($file);
}

/********************************************************************************************
/* Funktion : groupplaylist --> lädt eine Playliste in eine Gruppe
/*
/* @param: Playliste                             
/* @return: nichts
/********************************************************************************************/
function groupplaylist() {
	Global $debug, $sonos, $master, $sonoszone, $config, $volume;
	if($debug == 1) {
		#echo $sonoszone[$master][0] . "<br>";
		#echo getRINCON($sonoszone[$master][0]) . "<br>";	
	}
	if(isset($_GET['playlist'])) {
		$sonos->SetQueue("x-rincon-queue:" . getRINCON($sonoszone[$master][0]) . "#0"); 
		$playlist = $_GET['playlist'];
	} else {
		trigger_error("Keine Playliste mit dem angegebenen Namen gefunden.", E_USER_NOTICE);
	}
	
	# Sonos Playlist ermitteln und mit übergebene vergleichen	
	$sonoslists=$sonos->GetSONOSPlaylists();
	$pleinzeln = 0;
	while ($pleinzeln < count($sonoslists) ) {
		if($playlist == $sonoslists[$pleinzeln]["title"]) {
			$plfile = urldecode($sonoslists[$pleinzeln]["file"]);
			$sonos->ClearQueue();
			$sonos->AddToQueue($plfile); //Datei hinzufügen
			$sonos->SetQueue("x-rincon-queue:". getRINCON($sonoszone[$master][0]) ."#0"); 
			if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
				$sonos->RampToVolume($config['TTS']['rampto'], $volume);
				#$sonos->Play();
			} else {
				#$sonos->Play();
			}
				$gefunden = 1;
		}
		$pleinzeln++;
			if (($pleinzeln == count($sonoslists) ) && ($gefunden != 1)) {
				trigger_error("Keine Playliste mit dem angegebenen Namen gefunden.", E_USER_NOTICE);
			}
		}			
}


/********************************************************************************************
/* Funktion : groupradioplaylist --> lädt einen Radiosender in eine Gruppe
/*
/* @param: Sender                             
/* @return: nichts
/********************************************************************************************/
function groupradioplaylist(){
	Global $sonos, $volume;
			
	if(isset($_GET['playlist'])) {
        $playlist = $_GET['playlist'];
    } else {
		trigger_error("Keine Radio Playlist gefunden.", E_USER_NOTICE);
    }
	$sonos->Stop();
    # Sonos Radio Playlist ermitteln und mit übergebene vergleichen   
    $radiolists = $sonos->Browse("R:0/0","c");
	$radioplaylist = urldecode($playlist);
	$rleinzeln = 0;
    while ($rleinzeln < count($radiolists)) {
	if ($radioplaylist == $radiolists[$rleinzeln]["title"]) {
			$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]));
            $sonos->SetVolume($volume);
            #$sonos->Play();
    }
    $rleinzeln++;
	}   
}


 /***************************************************************************
 /* Funktion: getZonePlayerList --> Ermittelt die Sonos Topology
 /* @param:     nichts
 /*
 /* @return:    Array<Key => Array<Node>>  
 /****************************************************************************/
function getZonePlayerList($zone=""){
	global $sonoszone, $zone, $master, $sonosclass, $config, $debug;
		
		if(!$xml=deviceCmdRaw('/status/topology')){
			return false;
		}	
		$topology = simplexml_load_string($xml);
		$myself = null;
		$coordinators = [];
		// Loop players, build map of coordinators and find myself
		foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
			$player_data = $player->attributes();
			$name=utf8_decode((string)$player);
			$group=(string)$player_data->group[0];
			$ip = parse_url((string)$player_data->location)['host'];
			$port = parse_url((string)$player_data->location)['port'];
			$zonename = recursive_array_search($ip,$sonoszone);
			$player = array(
				'Host' =>"$ip",
				'Sonos Name' =>utf8_encode($zonename),
				'Master' =>((string)$player_data->coordinator == 'true'),
				'Group-ID' => $group,
				'Rincon' =>'RINCON_'.explode('RINCON_',(string)$player_data->uuid)[1]
			);
			$coordinators[$group][] = $player;
		}
	if(!function_exists('cmp')) {
		function cmp($a, $b) {
	if ($a['Master'] == $b['Master']) {
		if($a['Sonos Name'] == $b['Sonos Name']) 
			return 0;
		else 
			return ($a['Sonos Name'] > $b['Sonos Name']) ? 1 : -1;;
		}
		return ($a['Master'] === TRUE) ? -1 : 1;
	}
	foreach ($coordinators as $key=>$coordinator){
		usort($coordinators[$key], "cmp");
	}
	if($debug == 1) { 
		print_r($coordinators);
	}
	return $coordinators;
}
 }
	
	
 function deviceCmdRaw($url, $ip='', $port=1400) {
	global $sonoszone, $master, $zone;
		
	$url = "http://{$sonoszone[$master][0]}:{$port}{$url}"; // ($sonoszone[$master][0])
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
 }
 #*********************************************************************************************************
 
 /***************************************************************************
 /* Funktion: getZonePlayerListNew --> Ermittelt die Sonos Topology
 /* @param:     nichts
 /*
 /* @return:    Array<Key => Array<Node>>  
 /****************************************************************************/
function getZonePlayerListNew($zone=""){
	global $sonoszone, $zone, $master, $sonosclass, $config;
		
		if(!$xml=deviceCmdRaw('/status/topology')){
			return false;
		}	
		$topology = simplexml_load_string($xml);
		$myself = null;
		$coordinators = [];
		// Loop players, build map of coordinators and find myself
		foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
			$player_data = $player->attributes();
			$name=utf8_decode((string)$player);
			$group=(string)$player_data->group[0];
			$ip = parse_url((string)$player_data->location)['host'];
			$port = parse_url((string)$player_data->location)['port'];
			$zonename = recursive_array_search($ip,$sonoszone);
			$player = array(
				'Host' =>"$ip",
				'Sonos Name' =>utf8_encode($zonename),
				'Master' =>((string)$player_data->coordinator == 'true'),
				'Rincon' =>'RINCON_'.explode('RINCON_',(string)$player_data->uuid)[1]
			);
			$coordinators[$group][] = $player;
		}
 function cnp($a, $b) {
	if ($a['Master'] == $b['Master']) {
		if($a['Sonos Name'] == $b['Sonos Name']) 
			return 0;
		else 
			return ($a['Sonos Name'] > $b['Sonos Name']) ? 1 : -1;;
		}
		return ($a['Master'] === TRUE) ? -1 : 1;
	}
	foreach ($coordinators as $key=>$coordinator){
		usort($coordinators[$key], "cnp");
	}
	#print_r($coordinators);
	return $coordinators;
 }
 #*********************************************************************************************************
 
 
 /***************************************************************************
/* Funktion: settimestamp --> Timestamp in Datei schreiben
/* @param: leer
/*
/* @return: Datei
/****************************************************************************/
 function settimestamp() {
	$myfile = fopen("timestamps.txt","w") or die ("Kann Timestamp Datei nicht schreiben!");
	fwrite($myfile, time());
	fclose($myfile);
 }


 /***************************************************************************
/* Funktion: gettimestamp --> Timestamp aus Datei lesen
/* @param: leer
/*
/* @return: derzeit nichts
/****************************************************************************/
 function gettimestamp() {
	$myfile = fopen("timestamps.txt","r") or die ("Kann Timestamp Datei nicht lesen!");
	$zeit = fread($myfile, 999);
	fclose($myfile);
	if( time() % $zeit > 200 )
	{
		$was_soll_ich_jetzt_tun;
	}
}


/********************************************************************************************
/* Funktion : sonosgroupzone --> ermittelt ob die angegebene Zone in einer Gruppe ist und gibt 
/* Rincon-ID des Groupccodinators zurück
/* @param: 	<leer> oder Einzelne Zone
/*
/* @return: Rincon-ID des dazugehörigen Groupcoordinators
/********************************************************************************************/
function sonosgroupzone() { // ermittelt die Gruppeninformationen
	global $sonoszone, $sonos;
	
	$master = $_GET['zone'];
	$k = false;
	$sonos = new PHPSonos($sonoszone[$master][0]); // Master Sonos ZP IP-Address
	$group = $sonos->GetZoneGroupAttributes();
	$zonegroupname = $group["CurrentZoneGroupName"];
	$currentUUIDingroup = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]);
	if(count($currentUUIDingroup) > 1) {
		$k = getmasterid($currentUUIDingroup);
		if($debug = 1) {
			#echo '<br>function => sonosgroupzone: '.$k.'<br>';
		}
	}
	return ($k);
}

/********************************************************************************************
/* Funktion : getmasterid --> ermittelt den Groupcoordinator und gibt die Rincon-ID zurück
/* @param: 	alle Rincon-ID's einer Gruppe
/*
/* @return: Groupccordinator (Master)
/********************************************************************************************/
function getmasterid($currentUUIDingroup) {
	if(!$xml=deviceCmdRaw('/status/topology')){
		return false;
	}	
	$topology = simplexml_load_string($xml);
	// Loop players and get master
	$UUID = array();
	foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
		$player_data = $player->attributes();
		$rincon = (string)$player_data->uuid[0];
		if((string)$player_data->coordinator == 'true') {
			array_push($UUID, $rincon);
		}
	}
	foreach ($currentUUIDingroup as $master) {
		if (in_array($master,$UUID)) {
			return($master);
		} 
	}	
}

/********************************************************************************************
/* Funktion : getmaster --> prüft ob Zone Groupcoordinator ist
/* @param: 	Rincon-ID einer Zone
/*
/* @return: TRUE oder FALSE
/********************************************************************************************/
function getmaster($soplayer="") {
	global $sonoszone, $debug, $sozone;
	
	if(!$xml=deviceCmdRaw('/status/topology')){
		return false;
	}
	#$soplayer = array();
	if(empty($soplayer)) {
		$soplayer = array($_GET['zone']);
	} else {
		$soplayer = array($soplayer);
	}
	#print_r($soplayer);
	foreach ($soplayer as $splayer) {
		$zonerincon = getRINCON($sonoszone[$splayer][0]);
		#echo '$zonerincon $splayer: '.$zonerincon.'<br>';
	}
	$topology = simplexml_load_string($xml);
	// Loop und erstellen eines Array aller Rincon-ID's und Group-ID's
	foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
		$player_data = $player->attributes();
		$rincon = (string)$player_data->uuid[0];
		$zgroup = (string)$player_data->coordinator[0];
		$UUID = array('Rincon-ID' => $rincon,
					  'Coordinator' => $zgroup
					  );	
		foreach ($zonerincon as $rincon) {
			if (in_array($rincon,array($UUID['Rincon-ID']))) {
				#echo 'Die Zone '.$soplayer. ' ist Group Coordinator: '.($UUID['Coordinator'].'<br>');
				#echo '$getmaster: '.$UUID['Coordinator'].'<br>';
				return $UUID['Coordinator'];
			} 
		}	
	}
}

/********************************************************************************************
/* Funktion : allgroupsmaster --> erstellt Array aller Rincon-IDs von Gruppen
/* @param: 	leer
/*
/* @return:	$key = Rincon-ID vom Groupcoordinator
/* 			$value = Rincon-IDs der GruppenMember
/********************************************************************************************/
function allgroupsmaster() {
	Global $sonoszone, $sonos, $groupid, $debug;	
	
	foreach ($sonoszone as $zone => $ip) {
		$lox_ip = $ip[0];
		$sonos = new PHPSonos($sonoszone[$zone][0]);
		$group = $sonos->GetZoneGroupAttributes();
		$tmp_name = $group["CurrentZoneGroupName"];
		$tmp_group = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]);
		if(!empty($tmp_name)) {
			if(count($tmp_group) > 1) {
				$new_array = array('member' => $tmp_group);
				$groupcord = array_shift($new_array['member']);
				$groupid[$lox_ip] = $new_array['member'];
			}
		}
	}
	return $groupid;
}


/********************************************************************************************
/* Funktion : zonegroups --> erstellt Array der angegebenen Rincon-IDs von GroupCoordinators
/* @param: 	leer
/*
/* @return:	$key = Rincon-ID vom Groupcoordinator
/* 			$value = Rincon-IDs der GruppenMember
/********************************************************************************************/
function zonegroups($soplayer = "") {
	Global $sonoszone, $sonos, $grouping, $debug;	
	
	if($soplayer == "") {
		$soplayer = $_GET['zone'];
	}
	$lox_ip = $sonoszone[$soplayer][0];
	$sonos = new PHPSonos($sonoszone[$soplayer][0]);
	$group = $sonos->GetZoneGroupAttributes();
	$tmp_name = $group["CurrentZoneGroupName"];
	$tmp_group = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]);
	if(!empty($tmp_name)) {
		if(count($tmp_group) > 1) {
			$new_array = array('member' => $tmp_group);
			$groupcord = array_shift($new_array['member']);
			$grouping = $new_array['member'];
		}
	}
	return $grouping;
}


/********************************************************************************************
/* Funktion : getgroup --> ermittelt die Group-ID der angegebenen Zone
/* @param: leer                             
/*
/* @return: Group-ID
/********************************************************************************************/
function getgroup($soplayer = "") {
	global $sonoszone, $debug, $sozone;
	
	if(!$xml=deviceCmdRaw('/status/topology')){
		return false;
	}	
	if($soplayer == "") {
		$soplayer = $_GET['zone'];
	}
	$zonerincon = getRINCON($sonoszone[$soplayer][0]);
	$topology = simplexml_load_string($xml);
	// Loop und erstellen einer Array aller Rincon-ID's und Group-ID's
	foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
		$player_data = $player->attributes();
		$rincon = (string)$player_data->uuid[0];
		$zgroup = (string)$player_data->group[0];
		$coord = (string)$player_data->coordinator[0];
		$UUID = array('Rincon-ID' => $rincon,
					  'Group-ID' => $zgroup,
					  'Coordinator' => $coord
					  );				  
		foreach ($zonerincon as $rincon) {
			if (in_array($rincon,array($UUID['Rincon-ID']))) {
				return $UUID['Group-ID'];
			} 
		}	
	}
}

/********************************************************************************************
/* Funktion : getgcordrincon --> prüft ob welche der Rincon-IDs GroupCoordinator ist
/* @param: Rincon-IDs                             
/*
/* @return: true oder false
/********************************************************************************************/
function getgcordrincon($rinconid) {
	global $sonoszone, $debug, $sozone;
	
	if(!$xml=deviceCmdRaw('/status/topology')){
		return false;
	}	
	$rinconid = array($rinconid);
	$topology = simplexml_load_string($xml);
	// Loop und erstellen einer Array aller Rincon-ID's und Group-ID's
	foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
		$player_data = $player->attributes();
		$rincon = (string)$player_data->uuid[0];
		$zgroup = (string)$player_data->group[0];
		$coord = (string)$player_data->coordinator[0];
		$UUID = array('Rincon-ID' => $rincon,
					  'Coordinator' => $coord
					  );
		foreach ($rinconid as $rincon) {
			if (in_array($rincon,array($UUID['Rincon-ID']))) {
				return $UUID['Coordinator'];
			}
		}	
	}
}


/********************************************************************************************
/* Funktion : gettopology --> ermittelt die Topology relevanten Informationen zur Wieder-
/* herstellung der jeweiligen Zonen nach erfolgter Gruppen T2S
/* @param: leer                             
/*
/* @return: array(	Rincon-ID
/*					Group-ID,
/*					Coordinator
/*					IP-Adresse  )
/********************************************************************************************/
function gettopology($soplayer = "") {
	global $sonoszone, $debug, $sozone;
	
	if(!$xml=deviceCmdRaw('/status/topology')){
		return false;
	}	
	#$xml=deviceCmdRaw('/status/topology');
	if($soplayer == "") {
		$soplayer = $_GET['zone'];
	}
	$zonerincon = getRINCON($sonoszone[$soplayer][0]);
	$topology = simplexml_load_string($xml);
	// Loop und erstellen einer Array aller Rincon-ID's, Group-ID's und Group Coordinator
	foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
		$ip = $sonoszone[$soplayer][0];
		$player_data = $player->attributes();
		$rincon = (string)$player_data->uuid[0];
		$coord = (string)$player_data->coordinator[0];
		$group = (string)$player_data->group[0];
		$UUID = array('Rincon-ID' => $rincon,
					  'Group-ID' => $group,
					  'Coordinator' => $coord,
					  'IP-Adresse' => $ip
					  );		  
		foreach ($zonerincon as $rincon) {
			if (in_array($rincon,array($UUID['Rincon-ID']))) {
				return $UUID;
			} 
		}
	}
}


/********************************************************************************************
/* Funktion : getgroupstatus --> prüft welchen Status die Zone hat
/* @param: leer                             
/*
/* @return: Rincon-ID des Masters, 'master' oder 'leer'
/********************************************************************************************/
function getgroupstatus($player = 0){
	global $sonoszone, $zone, $master, $player;
		
	if(empty($player)) {
		$player = $_GET['zone'];
	}
	$sonos = new PHPSonos($sonoszone[$player][0]); 
	$posinfo = $sonos->GetPositionInfo();
	$masterrincon = getRINCON($sonoszone[$player][0]);
	foreach ($sonoszone as $member => $sz) {
		$sonos = new PHPSonos($sonoszone[$member][0]); 
		$posinfo = $sonos->GetPositionInfo();
		$rincon = substr($posinfo["TrackURI"], 9, 24);
		if($rincon == $masterrincon) {
			echo 'master<br>';
			return ('master');
		}
	}
	$masterrincon = "";
}


/*************************************************************************************************************
/* Funktion : checkaddon --> prüft vorhanden sein von Addon's
/* @param: 	leer
/*
/* @return: true oder Abbruch
/*************************************************************************************************************/
 function checkaddon() {
	global $home;
	
	if(isset($_GET['weather'])) {
		# ruft die weather-to-speech Funktion auf
		if(substr($home,0,4) == "/opt") {	
			if(!file_exists('addon/weather-to-speech.php')) {
				trigger_error("Das weather-to-speech Addon ist derzeit nicht installiert!", E_USER_NOTICE);
				exit;
			} else {
				if(!file_exists("$home/config/plugins/wu4lox/wu4lox.cfg")) {
					trigger_error("Bitte zuerst das Wunderground Plugin installieren!", E_USER_NOTICE);
					exit;
				}
			}
		} else {
			if(!file_exists('addon/weather-to-speech_nolb.php')) {
				trigger_error("Das weather-to-speech Addon ist derzeit nicht installiert!", E_USER_NOTICE);
				exit;
			}
		}
	} elseif (isset($_GET['clock'])) {
		# ruft die clock-to-speech Funktion auf
		if(!file_exists('addon/clock-to-speech.php')) {
			trigger_error("Das clock-to-speech addon ist derzeit nicht installiert!", E_USER_NOTICE);
			exit;
		}
	} elseif (isset($_GET['sonos'])) {
		# ruft die sonos-to-speech Funktion auf
		if(!file_exists('addon/sonos-to-speech.php')) {
			trigger_error("Das sonos-to-speech addon ist derzeit nicht installiert!", E_USER_NOTICE);
			exit;
		}
	}
 }


/********************************************************************************************
/* Funktion : checkTTSkeys --> prüft die verwendete TTS Instanz auf Korrektheit
/* @param: leer                             
/*
/* @return: falls OK --> nichts, andernfalls Abbruch und Eintrag in error log
/********************************************************************************************/
function checkTTSkeys() {
	Global $config;
	
	if ($config['TTS']['t2s_engine'] == 1001) {
		if (!file_exists("voice_engines/VoiceRSS.php")) {
			trigger_error("Die Instanz VoiceRSS ist derzeit nicht vorhanden. Bitte nachinstallieren!", E_USER_NOTICE);
		} else {
			if(strlen($config['TTS']['API-key']) !== 32) {
				trigger_error("Der angegebene VoiceRSS API-Key ist ungültig. Bitte korrigieren!", E_USER_NOTICE);
			}
		}
	}
	if ($config['TTS']['t2s_engine'] == 3001) {
		if (!file_exists("voice_engines/MAC_OSX.php")) {
			trigger_error("Die Instanz MAC OSX ist derzeit nicht vorhanden. Bitte nachinstallieren!", E_USER_NOTICE);
		}
	}
	if ($config['TTS']['t2s_engine'] == 2001) {
		if (!file_exists("voice_engines/Ivona.php")) {
			trigger_error("Die Instanz Ivona ist derzeit nicht vorhanden. Bitte nachinstallieren!", E_USER_NOTICE);
		} else {
			if((strlen($config['TTS']['API-key']) !== 20) or (strlen($config['TTS']['secret-key']) !== 40)) {
				trigger_error("Der angegebene Ivona access oder secret Key ist ungültig. Bitte korrigieren!", E_USER_NOTICE);
			}
		}
	}
}


/********************************************************************************************
/* Funktion : getIvonaVoices --> lädt alle Ivona Voice relevante Daten
/* @param: leer                             
/*
/* @return: multidimensionales array mit Geschlecht, Sprache und Name
/********************************************************************************************/
function getIvonaVoices() {

	require_once("voice_engines/ivona_tts/ivona.php");
	$ivona = new IvonaClient();
	$voices = $ivona->ListVoices();
	$voices = objectToArray($voices);
	echo '<pre>';
	print_r($voices);
}


/********************************************************************************************
/* Funktion : getLoxoneData --> Zeigt die Verbindung zu Loxone an
/* @param: leer                             
/*
/* @return: ausgabe
/********************************************************************************************/
function getLoxoneData() {
	global $loxip, $loxuser, $loxpassword;
	echo "Folgende Verbindung wird zur Datenübertragung zu Loxone genutzt:<br><br>";

	echo 'IP-Adresse/Port: '.$loxip.'<br>';
	echo 'User: '.$loxuser.'<br>';
	echo 'Passwort: '.$loxpassword.'<br>';
}


/********************************************************************************************
/* Funktion : getPluginFolder --> ermittelt den Plugin Folder
/* @param: leer                             
/*
/* @return: Plugin Folder
/********************************************************************************************/
function getPluginFolder(){
	$logpath = $_SERVER["SCRIPT_FILENAME"].'<br>';
	$folder = explode('/', $logpath);
	print_r ($folder[6]);
	return($folder);
}

/********************************************************************************************
/* Funktion : getMS1data --> übernimmt die Daten des MINISERVER1 aus dem Loxberry,
/* falls dieser mit loxone clouddns konfiguriert ist, wird die lokale IP ermittelt.
/* @param: 	leer
/*
/* @return: array mit den allen MS Daten
/********************************************************************************************/
function getMS1data() {
	
	global $home, $mstopology;
	// Parsen der Loxberry Config general.cfg um alle Miniserver zu übernehmen
	$tmp_lox =  parse_ini_file("$home/config/system/general.cfg", TRUE);
	$loxip = $tmp_lox['MINISERVER1']['IPADDRESS'];
	$msport = $tmp_lox['MINISERVER1']['PORT'];
	$useclouddns = $tmp_lox['MINISERVER1']['USECLOUDDNS'];
	$cloudurl = $tmp_lox['MINISERVER1']['CLOUDURL'];
	$loxuser = $tmp_lox['MINISERVER1']['ADMIN'];
	$loxpw = $tmp_lox['MINISERVER1']['PASS'];
	$ip = $loxip.":".$msport;
	// ermittelt die lokale IP Addresse des MS basierend auf DNS loxcloud Service
	if($useclouddns == 1) {
		$getprovip = objectToArray(json_decode(file_get_contents("http://dns.loxonecloud.com/?getip&snr=$cloudurl&json=true", "r")));
		$lokip = $getprovip['IP'];
		$tmp_ip = objectToArray(json_decode(file_get_contents("http://$loxuser:$loxpw@$lokip/jdev/cfg/ip", "r")));
		$loxip = $tmp_ip['LL']['value'];
	}
	// erstellt array
	$mstopology['MINISERVER'] = array('Host' => "$loxip",
									  'Port' => $msport,
									  'use DNS' => $useclouddns,
									  'Cloud URL' => $cloudurl,
									  'User' => $loxuser,
									  'PW' => $loxpw
	);
	#print_r($mstopology);
	return ($mstopology);
}

/********************************************************************************************
/* Funktion : getSonosStatVol --> sendet für alle Zonen die jeweilige Lautstärke und 
/*			  Play=1/Stop=3/Pause=2 per UDP an Loxone
/* @param: 	leer
/*
/* @return: Volume und Play Status je Zone
/********************************************************************************************/
 function getSonosStatVol() {
	global $config, $sonoszone, $sonoszonen, $mstopology, $sonos_array_diff, $home;
	
	if($config['LOXONE']['LoxDaten'] == 1) {
		if(substr($home,0,4) == "/opt") {		
			// LoxBerry ****************************************************************************************
			$mstopology = getMS1data();
			$sonos_array_diff = @array_diff_key($sonoszonen, $sonoszone);
			$sonos_array_diff = @array_keys($sonos_array_diff);
			$server_ip = $mstopology['MINISERVER']['Host'];
			$server_port = $mstopology['MINISERVER']['Port'];
			$cloud_url = $mstopology['MINISERVER']['Cloud URL'];
			$use_cloud = $mstopology['MINISERVER']['use DNS'];
		} else {
			// Non LoxBerry ************************************************************************************
			$server_ip = $config['LOXONE']['LoxIP'];
			$server_port = $config['LOXONE']['LoxPort'];
		}
		$tmp_array = array();
		if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
			foreach ($sonoszone as $zone => $player) {
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				$message = "vol_$zone@".$sonos->GetVolume()."; stat_$zone@".$sonos->GetTransportInfo();
				array_push($tmp_array, $message);
			}
		} else {
			trigger_error("Can't create UDP socket to $server_ip", E_USER_WARNING);
		}
		// fügt die Offline Zonen hinzu
		foreach ($sonos_array_diff as $zoneoff) {
			$messageoff = "vol_$zoneoff@0; stat_$zoneoff@3";
			array_push($tmp_array, $messageoff);
		}
		$UDPmessage = implode("; ", $tmp_array);
		try {
			socket_sendto($socket, $UDPmessage, strlen($UDPmessage), 0, $server_ip, $server_port);
		} catch (Exception $e) {
			if(substr($home,0,4) == "/opt") {	
				#getdnsip();
			}
			trigger_error("Die Verbindung zu Loxone konnte nicht initiiert werden!", E_USER_NOTICE);	
		}		
	} else { 
		trigger_error("Die Datenuebermittlung zu Loxone ist nicht aktiv. Bitte aktivieren!", E_USER_NOTICE); 
	}
	socket_close($socket);
}

/**********************************************************************************************************
/* Funktion : getSonosTitInt --> sendet für alle aktive Zonen (es läuft gerade irgendwas) 
/* die Titel-/Interpret, nur Titel und nur Interpret Info per http an virtuellen texteingang an Loxone
/* @param: 	leer
/*
/* @return: Titel und Interpret Info je Zone
/**********************************************************************************************************/
 function getSonosTitInt() {
	global $config, $countms, $sonoszone, $sonos, $lox_ip, $home, $sonoszonen; 
		
	if($config['LOXONE']['LoxDaten'] == 1) {	
		if(substr($home,0,4) == "/opt") {		
			// LoxBerry ***********************************************************************************
			$mstopology = getMS1data();
			#print_r($mstopology);
			$lox_ip		 = $mstopology['MINISERVER']['Host'];
			$lox_port 	 = $mstopology['MINISERVER']['Port'];
			$loxuser 	 = $mstopology['MINISERVER']['User'];
			$loxpassword = $mstopology['MINISERVER']['PW'];
			$loxip = $lox_ip.':'.$lox_port;
		} else {
			// Non LoxBerry *******************************************************************************
			$lox_ip 		= $config['LOXONE']['LoxIP'];
			$lox_port 		= $config['LOXONE']['LoxPort'];
			$loxuser 		= $config['LOXONE']['LoxUser'];
			$loxpassword 	= $config['LOXONE']['LoxPassword'];
			$loxip = $lox_ip.':'.$lox_port;
		}
		foreach ($sonoszone as $zone => $player) {
			$sonos = new PHPSonos($sonoszone[$zone][0]);
			$temp = $sonos->GetPositionInfo();
			$tempradio = $sonos->GetMediaInfo();
			$gettransportinfo = $sonos->GetTransportInfo();
			if ($gettransportinfo == 1) {
				// Radio wird gerade gespielt
				if(isset($tempradio["title"]) && (empty($temp["duration"]))) {	
					$value =  @substr($tempradio["title"], 0, 40); 
					$valuesplit[0] = $value; 							
					$valuesplit[1] = $value;							
				// Playliste wird gerade gespielt
				} elseif(!empty($temp["duration"]) && ($gettransportinfo == 1)) {
					$artist = substr($temp["artist"], 0, 30);
					$title = substr($temp["title"], 0, 50); 
					$value = $artist." - ".$title; 	// kombinierte Titel- und Interpretinfo
					$valuesplit[0] = $title; 		// Nur Titelinfo
					$valuesplit[1] = $artist;		// Nur Interpreteninfo
				}
				// Übergabe der Titelinformation an Loxone (virtueller Texteingang)
				$valueurl = rawurlencode($value);
				$valuesplit[0] = rawurlencode($valuesplit[0]);
				$valuesplit[1] = rawurlencode($valuesplit[1]);
					try {
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/titint_$zone/$valueurl"); // Titel- und Interpretinfo für Loxone
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/tit_$zone/$valuesplit[0]"); // Nur Titelinfo für Loxone
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/int_$zone/$valuesplit[1]"); // Nur Interpreteninfo für Loxone
					} catch (Exception $e) {
						if(substr($home,0,4) == "/opt") {	
							#getdnsip();
						}
						trigger_error("Die Verbindung zu Loxone konnte nicht initiiert werden!", E_USER_NOTICE);	
					}							
				echo '<PRE>';
			}
		}
	} else { 
		trigger_error("Die Datenuebermittlung zu Loxone ist nicht aktiv. Bitte aktivieren!", E_USER_NOTICE); 
	}
 }
 
/*************************************************************************************************************
/* Funktion : turnonlox --> schaltet den virtuellen Eingangsverbinder "push_sonos_loxone" ein/aus
/* @param: 	Ein/Aus
/*
/* @return: 0 oder 1
/*************************************************************************************************************/
 function turnonlox($status) {
	global $config, $countms, $sonoszone, $sonos, $lox_ip, $sonoszonen; 
		
		$mstopology = getMS1data();
		#print_r($mstopology);
		$i = 1;
		for ($i; $i <= $countms; $i++) {
			$lox_ip		 = $mstopology['MINISERVER'.$i.'']['Host'];
			$lox_port 	 = $mstopology['MINISERVER'.$i.'']['Port'];
			$loxuser 	 = $mstopology['MINISERVER'.$i.'']['User'];
			$loxpassword = $mstopology['MINISERVER'.$i.'']['PW'];
			$loxcloudurl = $mstopology['MINISERVER'.$i.'']['Cloud URL'];
			$loxclouddns = $mstopology['MINISERVER'.$i.'']['use DNS'];
			$loxcloud = $loxcloudurl.':'.$lox_port;
			if($loxclouddns == 1) {
				$lox_ip = getdnsip();
				$loxip = $lox_ip.':'.$lox_port;
			} else {
				$loxip = $lox_ip.':'.$lox_port;
			}
			try {
				$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/fetch_sonos/".$status); // Titel- und Interpretinfo für Loxone
			} catch (Exception $e) {
				trigger_error("Die Verbindung zu Loxone konnte nicht initiiert werden!", E_USER_NOTICE);	
			}							
			echo '<PRE>';
		}
}
 
/*************************************************************************************************************
/* Funktion : getdnsip --> ermittelt die lokale IP Addresse des MS basierend auf DNS loxcloud Service
/* CRONJOB oder Fehlerbehandlung
/* @param: 	leer
/*
/* @return: lokale IP Adresse
/*************************************************************************************************************/
 function getdnsip() {
	global $home, $mstopology, $home, $countms, $lox_ip;
	
	#$mstopology = getMS1data();
	$user = $mstopology['MINISERVER']['User'];
	$pw = $mstopology['MINISERVER']['PW'];
	$cloudurl = $mstopology['MINISERVER']['Cloud URL'];
	$use_cloud = $mstopology['MINISERVER']['use DNS'];
	if($use_cloud == 1) {
		$getprovip = objectToArray(json_decode(file_get_contents("http://dns.loxonecloud.com/?getip&snr=$cloudurl&json=true", "r")));
		$lokip = $getprovip['IP'];
		$tmp_ip = objectToArray(json_decode(file_get_contents("http://$user:$pw@$lokip/jdev/cfg/ip", "r")));
		$lox_ip = $tmp_ip['LL']['value'];
	}
	#echo $lox_ip;
	return $lox_ip;
 }
 
 
/********************************************************************************************
/* Funktion : recursive_array_search --> durchsucht eine Array nach einem Wert und gibt 
/* den dazugehörigen key zurück
/* @param: 	$needle = Wert der gesucht werden soll
/*			$haystack = Array die durchsucht werden soll
/*
/* @return: $key
/********************************************************************************************/
function recursive_array_search($needle,$haystack) {
    foreach($haystack as $key=>$value) {
        $current_key=$key;
        if($needle===$value OR (is_array($value) && recursive_array_search($needle,$value) !== false)) {
            return $current_key;
        }
    }
    return false;
}


/********************************************************************************************
/* Funktion : objectToArray --> konvertiert ein Object (Class) in eine Array.
/* https://www.if-not-true-then-false.com/2009/php-tip-convert-stdclass-object-to-multidimensional-array-and-convert-multidimensional-array-to-stdclass-object/
/*
/* @param: 	Object (Class)
/*
/* @return: array
/********************************************************************************************/
 function objectToArray($d) {
    if (is_object($d)) {
        // Gets the properties of the given object
        // with get_object_vars function
        $d = get_object_vars($d);
    }
	if (is_array($d)) {
        /*
        * Return array converted to object
        * Using __FUNCTION__ (Magic constant)
        * for recursive call
        */
        return array_map(__FUNCTION__, $d);
    } else {
        // Return array
        return $d;
    }
}

	
/********************************************************************************************
/* Funktion : get_file_content --> übermittelt die Titel/Interpret Info an Loxone
/* http://stackoverflow.com/questions/697472/php-file-get-contents-returns-failed-to-open-stream-http-request-failed
/*
/* @param: 	URL = virtueller Texteingangsverbinder
/*
/* @return: string (Titel/Interpret Info)
/********************************************************************************************/
function get_file_content($url) {
	
	$curl_handle=curl_init();
	curl_setopt($curl_handle, CURLOPT_URL,$url);
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_handle, CURLOPT_USERAGENT, 'LOXONE');
	$query = curl_exec($curl_handle);
	curl_close($curl_handle);
}



?>
