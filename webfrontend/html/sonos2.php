<?php

##############################################################################################################################
#
# Version: 	2.0.0
# Datum: 	31.0.2017
# veröffentlicht in: http://plugins.loxberry.de/
# 
# Change History:
# ----------------------------------------------------------------------------------------------------------------------------
# 1.0.0		Initiales Release des Plugin (Stable Version)
# 1.0.1		[Feature] Online Provider Amazon Polly hinzugefügt
# 			[Feature] Offline Engine Pico2Wave hinzugefügt
#			[Bugfix] ivona_tts.php: Großschreibung der Endung .MP3 in .mp3 geändert. Problem trat außer bei PLAY:3 und PLAY:5 bei allen anderen Modellen auf
#			[Bugfix] MP3path in der Config auf Kleinschreibung korrigiert (wird per Installationsscript korrigiert)
#					 Beim Abspielen von gespeicherten MP3 Files gabe es Probleme dass das angegebene File nicht gefunden wurde.
# 			[Feature] Online Provider responsiveVoice hinzugefügt
#			[Feature] Für Non-LoxBerry User besteht nun die Möglichkeit in ihrer Config die pws: für Wunderground anzugeben
# 1.0.2		[Bugfix] Fehlernachricht an Loxone und Zurücksetzen des Fehlers korrigiert. Funktion war nicht aktiv.
#			[Bugfix] UDP-Port für Inbound Daten korrigiert. Skript nimmt jetzt UDP-Port aus der Plugin Config statt der MS Config.
# 1.0.3     [Bugfix] Support für XAMPP Windows hinzugefügt
#			[Feature] Online Provider Google hinzugefügt
#			[Bugfix] Korrektur bei Einzel T2S aus Gruppe heraus
# 1.0.4		[New] Datei grouping.php hinzugefügt
#			[New] Datei helper.php hinzugefügt
#			[New] Datei text2speech.php hinzugefügt
#			[Bugfix] Support für Stereopaar hinzugefügt
#			[Feature] Neue Funktion createstereopair die aus zwei gleichen Modellen ein Stereopaar erstellt. Die zone=<DEINE ZONE> 
#					  ist dann der Raumname des neuen Paares
#			[Feature] Neue Funktion seperatestereopair die ein bestehendes Stereopaar wieder trennt
#			[Feature] delcoord --> Subfunction für Gruppenmanagement (RinconID von Member)
# 1.0.5		[Feature] playmode ist in case insensitive nutzbar
#			[Bugfix] Funktion Softstop überarbeitet. Es wird solange gespielt bis die Lautstärke 0 ist, dann Pause betätigt
#					 und die Lautstärke wieder auf den Wert vor Softstop angehoben.
# 1.0.6		[Bugfix] network.php geändert - Fehler beim Scannen der Zonen bei Neuinstallation korrigiert
# 2.0.0		[Feature] Parameter rampto für Radiosender hinzugefügt
#			[Feature] Neuer Parameter für Lox Daten Übertragung hinzugefügt (Radio=1 oder Playlist=2)
#			[Bugfix] addmember/removemember gefixt um mehr als eine Zone zum Master hinzuzufügen
#			[Bugfix] Fehlermeldung an Loxone Text Eingangsverbinder falls ein Fehler im Sonos Plugin auftrat
#			[Bugfix] Die Eingangsverbindung zu Loxone wurde optimiert, es wird nur noch MINISERVER1 mit lokaler IP unterstützt.
#			[Feature] zusätzliche Parameter radio&radio=SENDER und playlist&playlist=NAME DER PLAYLISTE (gilt für Zone als auch für Gruppe)
#			[Feature] vereinfachte T2S Durchsage mit parameter 'say'. Es gibt keine Differenzierung mehr zwischen Gruppen- oder Einzel-
#			          durchsage. (Details siehe Wiki)
#			[Feature] Multilinguale Sprachansagen für alle Provider hinzugefügt (Details siehe Wiki).
#					  AWS Polly SDK nicht mehr notwendig
#			[Bugfix] Komplette Überarbeitung der Gruppenfunktionen bzw. Gruppendurchsagen
#			
#
######## Script Code (ab hier bitte nichts ändern) ###################################

ini_set('max_execution_time', 120); // Max. Skriptlaufzeit auf 120 Sekunden

include("system/PHPSonos.php");
include("grouping.php");
include("helper.php");
#include("system/PHPSonosController.php");

date_default_timezone_set(date("e"));
$valid_playmodes = array("NORMAL","REPEAT_ALL","REPEAT_ONE","SHUFFLE_NOREPEAT","SHUFFLE","SHUFFLE_REPEAT_ONE");
echo "<pre>"; 


if (!function_exists('posix_getpwuid')) {
	$home = @getenv('DOCUMENT_ROOT');
} else {
	$home = posix_getpwuid(posix_getuid());
	$home = $home['dir'];
}
$myIP = $_SERVER["SERVER_ADDR"];


$psubfolder = __FILE__;
$psubfolder = preg_replace('/(.*)\/(.*)\/(.*)$/',"$2", $psubfolder);

if(substr($home,0,4) == "/opt") 
{
#-- Ab hier Loxberry spezifisch ------------------------------------------------------------------

	$myFolder = "$home/config/plugins/$psubfolder/";
	$myMessagepath = "//$myIP/sonos_tts/";
	$myMessageStorepath = "$home/loxberry/data/plugins/$psubfolder/tts/";
	chmod("$home/data/plugins/$psubfolder/tts/mp3/", 0644);

	// Parsen der Konfigurationsdatei sonos.cfg
	if (!file_exists($myFolder.'/sonos.cfg')) {
		trigger_error('The file sonos.cfg could not be opened, please try again!', E_USER_NOTICE);
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
	}
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		trigger_error('The file player.cfg  could not be opened, please try again!', E_USER_NOTICE);
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
		trigger_error('The file sonos_nolb.cfg could not be opened, please try again!', E_USER_NOTICE);
	} else {
		$tmpsonos =  parse_ini_file("./system/sonos_nolb.cfg", TRUE);
	}
	// Parsen der Konfigurationsdatei player_noLB.cfg (Non Loxberry)
	if (!file_exists("./system/player_nolb.cfg")) {
		trigger_error('The file player_nolb.cfg could not be opened, please try again!', E_USER_NOTICE);
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
	$myMessageStorepath = $config['SYSTEM']['messageStorePath'];
	
	$logpath = "log";
	if (is_dir($logpath)) { 
	} else { 
	mkdir ($logpath, 0777); 
	} 
}

#-- Ende NICHT Loxberry spezifisch ---------------------------------------------------------------------

#-- Ab hier allgemeiner Teil ----------------------------------------------------------------------

$debug = $config['SYSTEM']['debuggen'];
if($debug == 1) { 
	echo "<pre><br>"; 
}
 
// Übernahme und Deklaration von Variablen aus der Konfiguration
$sonoszonen = $config['sonoszonen'];

// prüft den Onlinestatus jeder Zone
	foreach($sonoszonen as $zonen => $ip) {
		$port = 1400;
		$timeout = 3;
		$handle = @stream_socket_client("$ip[0]:$port", $errno, $errstr, $timeout);
		if($handle) {
			$sonoszone[$zonen] = $ip;
			fclose($handle);
		}
	}
	$sonoszone;

// Umbennennen des ursprünglichen Array Keys
$config['SYSTEM']['myMessageStorepath'] = $config['SYSTEM']['messagespath'];
unset($config['SYSTEM']['messagespath']);			
	

#$sonoszone = $sonoszonen;
#print_r($sonoszone);
#print_r($config);
#exit;
	
#}


// Setzen des Error Handler
if($debug == 0) {set_error_handler("errorHandler"); }
if($debug == 1) {echo '<br>'; }

function errorHandler($errno, $errstr, $errfile, $errline) {
	global $logpath, $loxuser, $loxpassword, $loxip, $master, $home;
	
	ini_set("display_errors", 0);
	if(substr($home,0,4) == "/opt") {
	#-- Ab hier Loxberry spezifisch ------------------------------------------------------------------	
		$tmp_lox =  parse_ini_file("$home/config/system/general.cfg", TRUE);
		$tmp_loxip = $tmp_lox['MINISERVER1']['IPADDRESS'];
		$loxport = $tmp_lox['MINISERVER1']['PORT'];
		$loxuser = $tmp_lox['MINISERVER1']['ADMIN'];
		$loxpassword = $tmp_lox['MINISERVER1']['PASS'];
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
	echo("An Error occured. Please check ".$logpath."sonos_error.log.<br>");
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
		$sonos->SetPlayMode($playmode);
	}  else {
		trigger_error('incorrect PlayMode selected. Please correct!', E_USER_NOTICE);
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
					sendUDPdata();
					$sonos->Play();
				} else {
					sendUDPdata();
					$sonos->Play();
				}
			} else {
				trigger_error("No tracks in play list to play.", E_USER_NOTICE);
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
				trigger_error("No other title in the playlist to be played", E_USER_NOTICE);
			}
			break;

		case 'previous';
				$sonos->Previous();
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
				trigger_error('Wrong Mute Parameter selected. Please correct', E_USER_NOTICE);
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
				while ($sonos->GetVolume() > 0) {
					sleep('1');
				}
				$sonos->Pause();
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
			// see valid_playmodes under Configuratio section for a list of valid modes
			if (in_array($playmode, $valid_playmodes)) {
				$sonos->SetPlayMode($playmode);
			} else {
				trigger_error('Wrong PlayMode Parameter selected. Please correct', E_USER_NOTICE);
			}    
			break;           
	  
		case 'crossfade':
			if((is_numeric($_GET['crossfade'])) && ($_GET['crossfade'] == 0) || ($_GET['crossfade'] == 1)) { 
				$crossfade = $_GET['crossfade'];
			} else {
				trigger_error("Wrong Crossfade entered -> 0 = off / 1 = on", E_USER_NOTICE);
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
				trigger_error("No tracks in Playlist to play.", E_USER_NOTICE);
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
				sendUDPdata();
			} else {
				trigger_error('Wrong range of values for the volume been entered, only 0-100 is permitted', E_USER_NOTICE);
			}
			break;  
		  
		case 'volumeup': 
			$volume = $sonos->GetVolume();
			if($volume < 100) {
				$volume = $volume + $config['MP3']['volumeup'];
				$sonos->SetVolume($volume);
				sendUDPdata();
			}      
			break;
			
		case 'volumedown':
			$volume = $sonos->GetVolume();
			if($volume > 0) {
				$volume = $volume - $config['MP3']['volumedown'];
				$sonos->SetVolume($volume);
				sendUDPdata();
			}
			break;   

			
		case 'setloudness':
			if(($_GET['loudness'] == 1) || ($_GET['loudness'] == 0)) {
				$loud = $_GET['loudness'];
				$sonos->SetLoudness($loud);
			} else {
				trigger_error('Wrong LoudnessMode', E_USER_NOTICE);
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
			$member = explode(',', $member);
			foreach ($member as $value) {
				$masterrincon = $config['sonoszonen'][$master][1];
				$sonos = new PHPSonos($sonoszone[$value][0]);
				$sonos->SetAVTransportURI("x-rincon:" . $masterrincon);
			}
		break;

		
		case 'removemember':
		global $sonoszone, $sonos;
			$member = $_GET['member'];
			$member = explode(',', $member);
			foreach ($member as $value) {
				$masterrincon = $config['sonoszonen'][$master][1];
				$sonos = new PHPSonos($sonoszone[$value][0]);
				$sonos->BecomeCoordinatorOfStandaloneGroup();
			}
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
				trigger_error("There are no Radio Stations maintained in the configuration. Please follow up!", E_USER_NOTICE);
				exit;
			}
			$playstatus = $sonos->GetTransportInfo();
			$radiovolume = $sonos->GetVolume();
			$radiosender = $sonos->GetPositionInfo();
			$radioname = $sonos->GetMediaInfo();
			#$senderuri = $radiosender["URI"];
			$senderuri = $radioname["title"];
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
			playlist();
		break;
		  
		case 'groupsonosplaylist':
			playlist();
			logging();
		break;

		case 'radioplaylist':
			radio();
			logging();
		break;
		
		case 'groupradioplaylist': 
			radio();
			logging();
		break;
		
		case 'radio': 
			radio();
			logging();
		break;
		
		case 'playlist':
			playlist();
			logging();
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
				#echo debug();
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
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=previous" target="_blank">Back</a>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=play" target="_blank">Cancel</a>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=pause" target="_blank">Pause</a>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=stop" target="_blank">Stop</a>
						<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=next" target="_blank">Next</a>
					</table>
				';
			break;
			
		
		
	case 'sendgroupmessage':
		global $sonos, $coord, $text, $member, $master, $zone, $messageid, $logging, $words, $voice, $accesskey, $secretkey, $rampsleep, $config, $save_status, $mute, $membermaster, $groupvol, $checkgroup;
		include_once("text2speech.php");
		#$time_start = microtime(true);
		sendgroupmessage();
	break;
		
		
	case 'sendmessage':
		global $text, $coord, $master, $messageid, $logging, $words, $voice, $config, $actual, $player, $volume, $coord, $time_start;
		include_once("text2speech.php");
		#$time_start = microtime(true);
		sendmessage();
	break;
			
	case 'say':
		include_once("text2speech.php");
		say();
	break;
		
			
	case 'group':
		logging();
		# Alle Zonen gruppieren
		foreach ($sonoszone as $zone => $ip) {
			if($zone != $_GET['zone']) {
				$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos lox_ipesse
				$sonos->SetAVTransportURI("x-rincon:" . $config['sonoszonen'][$master][1]); 
			}
		}
	break;
		
	case 'ungroup':
		logging();
		# Alle Zonen Gruppierungen aufheben
		foreach($sonoszone as $zone => $ip) {
			$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos lox_ipesse
			$sonos->SetQueue("x-rincon-queue:" . $config['sonoszonen'][$zone][1] . "#0");
		}
	break;
	

	case 'getsonosinfo':
		sendUDPdata();
		sendTEXTdata();
	break; 
	
	
	# Debug Bereich ------------------------------------------------------

		case 'checksonos':
			if($debug == 1) { 
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
			}
			break;
			
		case 'getzonestatus':
				echo '<PRE>';
				getZoneStatus($master);
				echo '<PRE>';
			break;
			
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
		
		if(isset($_GET['timer']) && is_numeric($_GET['timer']) && $_GET['timer'] > 0 && $_GET['timer'] < 60) {
			$timer = $_GET['timer'];
			if($_GET['timer'] < 10) {
				$timer = '00:0'.$_GET['timer'].':00';
			} else {
				$timer = '00:'.$_GET['timer'].':00';
				$timer = $sonos->Sleeptimer($timer);
			}
		} else {
		trigger_error('The entered time is not correct, please correct', E_USER_NOTICE);
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
				trigger_error('Please correct input. Only On or off is allowed', E_USER_NOTICE);
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
		
		case 'clearlox': // Loxone Fehlerhinweis zurücksetzen
			if(substr($home,0,4) == "/opt")  {
				clear_error();
			} else {
				$handle = fopen("http://$loxuser:$loxpassword@$loxip/dev/sps/io/S-Error/''", "r");
			}
			
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
				
		case 'createstereopair':
			echo '<PRE>';
				CreateStereoPair();
			echo '</PRE>';
		break;
		
		case 'seperatestereopair':
			echo '<PRE>';
				SeperateStereoPair();
			echo '</PRE>';
		break;
		
		
		case 'getroomcoordinator':
			echo '<PRE>';
				getRoomCoordinator($master);
			echo '</PRE>';
		break;
			
		case 'delcoord':
			echo '<PRE>';
				$to = $_GET['to'];
				$newzone = $sonoszone[$to][1];
				$sonos->DelegateGroupCoordinationTo($newzone, 1);
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
		
		case 'networkstatus';
			networkstatus();
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
		
		
		case 'getplayerlist':
			echo '<PRE>';
			getPlayerList();
			echo '</PRE>';
		break;
		
		case 'getivonavoices':
			echo '<PRE>';
			getIvonaVoices();
			echo '</PRE>';
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
			require_once("text2speech.php");
			saveZonesStatus();
		break;
		
		case 'say':
			say();
		break;
		
		case 'addzones':
			addZones();
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
		   trigger_error("This command is not known. <br>index.php?zone=SONOSPLAYER&action=FUNCTION&VALUE=Option", E_USER_NOTICE);
		} 
	} else 	{
	trigger_error("The Zone ".$master." is not available or offline. Please check and if necessary add in the Config the zone", E_USER_NOTICE);
}




# Funktionen Bereich ------------------------------------------------------

# Hilfs Funktionen für Skripte ------------------------------------------------------

 

 
 /*****************************************************************************************************
/* Funktion : delmp3 --> löscht die hash5 codierten MP3 Dateien aus dem Verzeichnis 'messageStorePath'
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
							echo $dateiname.' has been deleted<br>';
						else
							echo $dateiname.' could not be deleted<br>';
					}
			}
        }
    }
	if($debug == 1) { 
		echo "<br>All files according to the criteria were successfully deleted";
	}
    $folder->close();
    exit; 	 
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
	$GroupVolume = $_GET['volume'];
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
	$RelativeGroupVolume = $_GET['volume'];
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


/********************************************************************************************
/* Funktion : playlist --> lädt eine Playliste in eine Zone/Gruppe
/*
/* @param: Playliste                             
/* @return: nichts
/********************************************************************************************/
function playlist() {
	Global $debug, $sonos, $master, $sonoszone, $config, $volume;
	if(isset($_GET['playlist'])) {
		$sonos->SetQueue("x-rincon-queue:" . getRINCON($sonoszone[$master][0]) . "#0"); 
		$playlist = $_GET['playlist'];
	} else {
		trigger_error("No playlist with the specified name found.", E_USER_NOTICE);
	}
	
	# Sonos Playlist ermitteln und mit übergebene vergleichen	
	$sonoslists=$sonos->GetSONOSPlaylists();
	$pleinzeln = 0;
	$gefunden = 0;
	while ($pleinzeln < count($sonoslists) ) {
		if($playlist == $sonoslists[$pleinzeln]["title"]) {
			$plfile = urldecode($sonoslists[$pleinzeln]["file"]);
			$sonos->ClearQueue();
			$sonos->AddToQueue($plfile); //Datei hinzufügen
			$sonos->SetQueue("x-rincon-queue:". getRINCON($sonoszone[$master][0]) ."#0"); 
			if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
				$sonos->RampToVolume($config['TTS']['rampto'], $volume);
				$sonos->Play();
			} else {
				$sonos->Play();
			}
			$gefunden = 1;
		}
		$pleinzeln++;
			if (($pleinzeln == count($sonoslists) ) && ($gefunden != 1)) {
				$sonos->Stop();
				trigger_error("No playlist with the specified name found.", E_USER_NOTICE);
				exit;
			}
		}			
}


/********************************************************************************************
/* Funktion : radio --> lädt einen Radiosender in eine Zone/Gruppe
/*
/* @param: Sender                             
/* @return: nichts
/********************************************************************************************/
function radio(){
	Global $sonos, $volume, $config;
			
	if(isset($_GET['radio'])) {
        $playlist = $_GET['radio'];		
	} elseif (isset($_GET['playlist'])) {
		$playlist = $_GET['playlist'];		
	} else {
		trigger_error("No radio stations found.", E_USER_NOTICE);
    }
	$sonos->Stop();
    # Sonos Radio Playlist ermitteln und mit übergebene vergleichen   
    $radiolists = $sonos->Browse("R:0/0","c");
	$radioplaylist = urldecode($playlist);
	$rleinzeln = 0;
    while ($rleinzeln < count($radiolists)) {
	if ($radioplaylist == $radiolists[$rleinzeln]["title"]) {
		$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]),$radiolists[$rleinzeln]["title"]);
		#$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]));
		if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
			$sonos->RampToVolume($config['TTS']['rampto'], $volume);
			$sonos->Play();
		} else {
			$sonos->SetVolume($volume);
			$sonos->Play();
		}
    }
    $rleinzeln++;
	}   
}


 /*************************************************************************************************************
/* Funktion : deviceCmdRaw --> Subfunction necessary to read Sonos Topology
/* @param: 	URL, IP-Adresse, port
/*
/* @return: data
/*************************************************************************************************************/
	
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
				trigger_error("The weather-to-speech Addon Is currently not installed!", E_USER_NOTICE);
				exit;
			} else {
				if(!file_exists("$home/config/plugins/wu4lox/wu4lox.cfg")) {
					trigger_error("Bitte zuerst das Wunderground Plugin installieren!", E_USER_NOTICE);
					exit;
				}
			}
		} else {
			if(!file_exists('addon/weather-to-speech_nolb.php')) {
				trigger_error("The weather-to-speech Addon is currently not installed!", E_USER_NOTICE);
				exit;
			}
		}
	} elseif (isset($_GET['clock'])) {
		# ruft die clock-to-speech Funktion auf
		if(!file_exists('addon/clock-to-speech.php')) {
			trigger_error("The clock-to-speech addon is currently not installed!", E_USER_NOTICE);
			exit;
		}
	} elseif (isset($_GET['sonos'])) {
		# ruft die sonos-to-speech Funktion auf
		if(!file_exists('addon/sonos-to-speech.php')) {
			trigger_error("The sonos-to-speech addon Is currently not installed!", E_USER_NOTICE);
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
			trigger_error("VoiceRSS is currently not available. Please install!", E_USER_NOTICE);
		} else {
			if(strlen($config['TTS']['API-key']) !== 32) {
				trigger_error("The specified VoiceRSS API key is invalid. Please correct!", E_USER_NOTICE);
			}
		}
	}
	if ($config['TTS']['t2s_engine'] == 3001) {
		if (!file_exists("voice_engines/MAC_OSX.php")) {
			trigger_error("MAC OSX is currently not available. Please install!", E_USER_NOTICE);
		}
	}
	if ($config['TTS']['t2s_engine'] == 6001) {
		if (!file_exists("voice_engines/ResponsiveVoice.php")) {
			trigger_error("ResponsiveVoice is currently not available. Please install!", E_USER_NOTICE);
		}
	}
	if ($config['TTS']['t2s_engine'] == 5001) {
		if (!file_exists("voice_engines/Pico_tts.php")) {
			trigger_error("Pico2Wave is currently not available. Please install!", E_USER_NOTICE);
		}
	}
	if ($config['TTS']['t2s_engine'] == 2001) {
		if (!file_exists("voice_engines/Ivona.php")) {
			trigger_error("Ivona is currently not available. Please install!", E_USER_NOTICE);
		} else {
			if((strlen($config['TTS']['API-key']) !== 20) or (strlen($config['TTS']['secret-key']) !== 40)) {
				trigger_error("The specified Ivona API key is invalid. Please correct!", E_USER_NOTICE);
			}
		}
	}
	if ($config['TTS']['t2s_engine'] == 4001) {
		if (!file_exists("voice_engines/Polly.php")) {
			trigger_error("Amazon Polly is currently not available. Please install!", E_USER_NOTICE);
		} else {
			if((strlen($config['TTS']['API-key']) !== 20) or (strlen($config['TTS']['secret-key']) !== 40)) {
				trigger_error("The specified AWS Polly API key is invalid. Please correct!!", E_USER_NOTICE);
			}
		}
	}
}



/**
/* Funktion : getIvonaVoices --> lists all available Ivona voices
/* @param: empty                             
/*
/* @return: multidimensional array with gender, language and Name
**/

function getIvonaVoices() {

	require_once("voice_engines/ivona_tts/ivona.php");
	$ivona = new IvonaClient();
	$voices = $ivona->ListVoices();
	$voices = objectToArray($voices);
	#echo '<pre>';
	print_r($voices);
	return($voices);
}


/**
/* Funktion : sendUDPdata --> send for each Zone as UDP package Volume and Playmode Info
/*			  Playmode: Play=1/Stop=3/Pause=2
/* @param: 	empty
/*
/* @return: Volume and Play Status per Zone
**/

 function sendUDPdata() {
	global $config, $sonoszone, $sonoszonen, $mstopology, $sonos_array_diff, $home, $tmp_lox;
	
	if($config['LOXONE']['LoxDaten'] == 1) {
		if(substr($home,0,4) == "/opt") {		
			// LoxBerry **********************
			$tmp_lox =  parse_ini_file("$home/config/system/general.cfg", TRUE);
			$sonos_array_diff = @array_diff_key($sonoszonen, $sonoszone);
			$sonos_array_diff = @array_keys($sonos_array_diff);
			$server_ip = $tmp_lox['MINISERVER1']['IPADDRESS'];
			$server_port = $config['LOXONE']['LoxPort'];
		} else {
			// Non LoxBerry ******************
			$server_ip = $config['LOXONE']['LoxIP'];
			$server_port = $config['LOXONE']['LoxPort'];
		}
		$tmp_array = array();
		if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
			foreach ($sonoszone as $zone => $player) {
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				$orgsource = $sonos->GetPositionInfo();
				$message = "vol_$zone@".$sonos->GetVolume()."; stat_$zone@".$sonos->GetTransportInfo();
				array_push($tmp_array, $message);
			}
		} else {
			trigger_error("Can't create UDP socket to $server_ip", E_USER_WARNING);
		}
		// fügt die Offline Zonen hinzu
		if (!empty($sonos_array_diff)) {
			foreach ($sonos_array_diff as $zoneoff) {
				$messageoff = "vol_$zoneoff@0; stat_$zoneoff@3";
				array_push($tmp_array, $messageoff);
			}
		}
		$UDPmessage = implode("; ", $tmp_array);
		try {
			socket_sendto($socket, $UDPmessage, strlen($UDPmessage), 0, $server_ip, $server_port);
		} catch (Exception $e) {
			trigger_error("The connection to Loxone could not be initiated!", E_USER_NOTICE);	
		}
		socket_close($socket);
	} else { 
		trigger_error("Data transmission to Loxone is not active. Please activate!", E_USER_NOTICE); 
	}
}

/**
/* Funktion : sendTEXTdata --> send Title/Interpret or name of Radio Station data in case zone is in playmode 
/* @param: 	empty
/*
/* @return: title/Interpret for each Zone
**/

 function sendTEXTdata() {
	global $config, $countms, $sonoszone, $sonos, $lox_ip, $home, $sonoszonen, $tmp_lox; 
		
	if($config['LOXONE']['LoxDaten'] == 1) {	
		if(substr($home,0,4) == "/opt") {		
			// LoxBerry ***********************
			$lox_ip		 = $tmp_lox['MINISERVER1']['IPADDRESS'];
			$lox_port 	 = $tmp_lox['MINISERVER1']['PORT'];
			$loxuser 	 = $tmp_lox['MINISERVER1']['ADMIN'];
			$loxpassword = $tmp_lox['MINISERVER1']['PASS'];
			$loxip = $lox_ip.':'.$lox_port;
		} else {
			// Non LoxBerry *******************
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
					$value = @substr($tempradio["title"], 0, 40); 
					$valuesplit[0] = $value; 							
					$valuesplit[1] = $value;
					$source = 1;
				// Playliste wird gerade gespielt
				} else {
					$artist = substr($temp["artist"], 0, 30);
					$title = substr($temp["title"], 0, 50); 
					$value = $artist." - ".$title; 	// kombinierte Titel- und Interpretinfo
					$valuesplit[0] = $title; 		// Nur Titelinfo
					$valuesplit[1] = $artist;		// Nur Interpreteninfo
					$source = 2;
				}
				// Übergabe der Titelinformation an Loxone (virtueller Texteingang)
				$valueurl = rawurlencode($value);
				$valuesplit[0] = rawurlencode($valuesplit[0]);
				$valuesplit[1] = rawurlencode($valuesplit[1]);
					try {
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/titint_$zone/$valueurl"); // Titel- und Interpretinfo für Loxone
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/tit_$zone/$valuesplit[0]"); // Nur Titelinfo für Loxone
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/int_$zone/$valuesplit[1]"); // Nur Interpreteninfo für Loxone
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/source_$zone/$source"); // Radio oder Playliste
					} catch (Exception $e) {
						trigger_error("The connection to Loxone could not be initiated!", E_USER_NOTICE);	
					}							
				echo '<PRE>';
			}
		}
	} else { 
		trigger_error("Data transmission to Loxone is not active. Please activate!", E_USER_NOTICE); 
	}
 }
 
 
/**
/* Funktion : clear_error --> löscht die Fehlermeldung in der Visu
/* @param: 	Ein/Aus
/*
/* @return: 0 oder 1
**/

 function clear_error() {
	global $config, $countms, $sonoszone, $home, $sonos, $lox_ip, $sonoszonen, $tmp_lox; 
	
	// LoxBerry ***********************
	if(substr($home,0,4) == "/opt") {		
		$tmp_lox =  parse_ini_file("$home/config/system/general.cfg", TRUE);
		$lox_ip		 = $tmp_lox['MINISERVER1']['IPADDRESS'];
		$lox_port 	 = $tmp_lox['MINISERVER1']['PORT'];
		$loxuser 	 = $tmp_lox['MINISERVER1']['ADMIN'];
		$loxpassword = $tmp_lox['MINISERVER1']['PASS'];
	} else {
	// Non LoxBerry *******************
		$lox_ip 		= $config['LOXONE']['LoxIP'];
		$lox_port 		= $config['LOXONE']['LoxPort'];
		$loxuser 		= $config['LOXONE']['LoxUser'];
		$loxpassword 	= $config['LOXONE']['LoxPassword'];
	}
	$loxip = $lox_ip.':'.$lox_port;
	try {
		$handle = fopen("http://$loxuser:$loxpassword@$loxip/dev/sps/io/S-Error/''", "r");
	} catch (Exception $e) {
		trigger_error("The error message could not be deleted!", E_USER_NOTICE);	
	}							
	echo '<PRE>';
 }
 

/** --> OBSOLETE
* Sub Function for T2S: SavePlaylist --> save temporally Playlist
*
* @param: empty
* @return: playlist "temp_t2s" saved
**/

function SavePlaylist() {
	global $sonos, $id;
	
	$sonos->SaveQueue("temp_t2s");
}


/** --> OBSOLETE
* Sub Function for T2S: DelPlaylist --> deletes previously saved temporally Playlist
*
* @param: empty
* @return: playlist "temp_t2s" deleted
**/

function DelPlaylist() {
	global $sonos;
	
	$playlists = $sonos->GetSonosPlaylists();
	$t2splaylist = recursive_array_search("temp_t2s",$playlists);
	if(!empty($t2splaylist)) {
		$sonos->DelSonosPlaylist($playlists[$t2splaylist]['id']);
	}
}

/**
* New Function for T2S: say --> replacement/enhancement for sendmessage/sendgroupmessage
*
* @param: empty
* @return: nothing
**/

function say() {
	include_once("text2speech.php");
	if(!isset($_GET['member'])) {
		sendmessage();
	} else {
		sendgroupmessage();
	}	
}

?>
