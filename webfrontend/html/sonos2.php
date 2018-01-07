<?php

##############################################################################################################################
#
# Version: 	2.1.6
# Datum: 	06.01.2018
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
#			[Feature] Neuer Text Eingangsparameter für Lox Daten Übertragung hinzugefügt (Radio=1 oder Playlist=2)
#					  Neuer UDP Parameter für Lox Daten Übertragung hinzugefügt (Single Zone=1, Master=3 oder Member=3)
#			[Bugfix] addmember/removemember gefixt um mehr als eine Zone zum Master hinzuzufügen
#			[Bugfix] Fehlermeldung an Loxone Text Eingangsverbinder falls ein Fehler im Sonos Plugin auftrat
#			[Bugfix] Die Eingangsverbindung zu Loxone wurde optimiert, es wird nur noch MINISERVER1 mit lokaler IP unterstützt.
#			[Feature] zusätzliche Parameter radio&radio=SENDER und playlist&playlist=NAME DER PLAYLISTE (gilt für Zone als auch für Gruppe)
#			[Feature] vereinfachte T2S Durchsage mit parameter 'say'. Es gibt keine Differenzierung mehr zwischen Gruppen- oder Einzel-
#			          durchsage. (Details siehe Wiki)
#			[Feature] Multilinguale Sprachansagen für alle Engines hinzugefügt (Details siehe Wiki).
#					  AWS Polly SDK nicht mehr notwendig
#			[Bugfix] Komplette Überarbeitung der Gruppenfunktionen bzw. Gruppendurchsagen
# 2.0.1		[Bugfix] nextradio optimiert um Änderungen von Sonos zu korrigieren (siehe Wiki)
#			[Bugfix] Korrektur der Lautstärke bei Gruppendurchsage
#			[Bugfix] Sonos Ansage optimiert: Bei Playliste Titel und Interpret Ansage, bei Radio Sender Ansage
#			[Feature] Pollenflug Ansage (Quelle: Deutscher Wetterdienst)
#			[Feature] Wetterhinweis bzw. Wetterwarnung Ansage (Quelle: Deutscher Wetterdienst)
# 			[Bugfix] T2S Engine Ivona entfernt da Service zum 30.06.2017 eingestellt wird.
# 2.0.2		[Bugfix] Pollen und Wetterwarnung Ansage korrigiert. Es werden jetzt jeweils Ansagen getätigt.
# 2.0.3		[Bugfix] Es wird nur angesagt falls eine Wetterwarnung vorliegt.
#			[Bugfix] Umlaute bei Nutzung von VoiceRSS korrigiert
# 			[Feature] Auswahlmöglichkeit des Miniservers für die Schnittstelle zu Loxone in der Config einstellbar
#			[Bugfix] Datenübertragung bei Standardbefehlen optimiert
#			[Bugfix] Gruppenmanagement optmiert
#			[Feature] Möglichkeit der gleichzeitigen Gruppierung bei der Auswahl von Radio bzw. Playlisten
# 2.0.4		[Bugfix] Broadcast IP beim Scannen hinzugefügt
#			[Bugfix] Sortierfunktion der Zonen korrigiert	
# 2.0.5		[Feature] Möglichkeit zum Abspielen von T2S im batch modus (siehe Wiki)
#			[Bugfix] Fehler Meldungen auf der Config Seite gefixt
#			[Bugfix] Sortierfunktion der Zonen wieder entfernt, Config konnte nicht gespeichert werden.	
# 2.0.6		[Bugfix] Fehler bei Play() korrigiert
#			[Bugfix] Fehler bei Zonen Scan in Verbindung mit Stereopaaren behoben
# 2.0.7		[Bugfix] Fehler bei Wetterwarnungen und Orten die Umlaute enthalten korrigiert
#			[Feature] Neue Funktion alarmoff um alle Sonos Alarme/Wecker auszuschalten
#			[Feature] Neue Funktion alarmon um alle Sonos Alarme/Wecker wieder gemäß Ursprungszustand wieder einzuschalten
# 2.0.8		[Bugfix] Korrektur Wetterwarnung bei Gruppendurchsage bzw. Stadt/Gemeinde mit Sonderzeichen
#			[Feature] Time-to-destination-speech --> Ansage der ca. Fahrzeit von Standort zu einem Ziel (Google Maps)
#			[Feature] Klickfunktion zapzone um sich durch die Sonos Komponenten zu zappen, falls aktuell keine Zone
#					  spielt wird weiter durch die Radio Favoriten gezappt.
#			[Feature] Fehler Mitteilung an MS nur noch wenn Sonos FEHLER auftrat (keine WARNUNG und keine INFO mehr)
#			[Bugfix] Rückbau des Broadcast Scans bei Ersteinrichtung, Protokollierung hinzugefügt.
#			[Feature] Ansage des Radiosenders bei nextradio oder zapzone (siehe Wiki)
# 2.0.9		[Bugfix] Re-Gruppierung nach Einzelansage korrigiert
#			[Feature] Neues Addon zur Ansage eines Abfallkalenders.
#			[Feature] Neue Funktion (aus der Kategorie Spaß) say&witz = gibt einen Zufallswitz aus
#			[Feature] Neue Funktion (aus der Kategorie Spaß) say&bauernregel = gibt die Bauernregel für den jeweiligen Tag aus
# 2.1.0		[Feature] Prüfung auf gültige LoxBerry Version hinzugefügt
#			[Feature] Prüfung auf korrekt beendete Plugin Installation hinzugefügt
#			[Feature] Auswahl des LineIn Einganges bei PLAY:5, CONNECT und CONNECT:AMP wird unterstützt.
#			[Feature] Angabe des Parameters standardvolume bei Gruppenauswahl für Playlist oder Radiosender wird jetzt unterstützt.
#					  Es werden je Zone in der Gruppe die in der Config hinterlegten Sonos Volumen Einstellungen übernommen.
#			[Feature] Bei der Abfallkalender Durchsage werden jetzt auch 2 Termine an einem Tag berücksichtigt
#			[Bugfix] Optimierung der Wiederherstellung von Zonen Status nach erfolgter Einzeldurchsage wobei sich die Zone vorher in einer Gruppe befand
#			[Feature] Unterstützung beim Scan für Sonos PLAYBASE und PLAY:1 mit Alexa wurde hinzugefügt.
#			[Feature] Das Error LogFile ist über die LoxBerry Sonos Konfiguration errreichbar
#			[Bugfix] Optimierung der Zonen Scan Funktion um Doppelscans zu verhindern
#			[Bugfix] Beim Modell CONNECT kann die Lautstärke variabel oder festeingestellt sein, was eine T2S Ansage verhindert
#					 Während einer T2S wird die Lautstärke temporär auf variabel gesetzt, dann wieder auf festeingestellt.
#			[Bugfix] Bei Gruppennachrichten konnte der Parameter volume genutzt werden, wurde jetzt ersetzt durch groupvolume
#			[Bugfix] Problem bei T2S auf PLAYBAR wenn diese im TV Modus ist behoben
# 2.1.1		[Feature] Alle Dateien im "mp3" Verzeichnis werden vom Script jetzt automatisch auf Rechte 0644 gesetzt
#			[Bugfix] Problem bei T2S auf PLAYBAR wenn diese im TV Modus ist behoben
#			[Bugfix] Bei der Ansage des Müllkalenders wurde u.U. nichts angesagt wenn der erste vom CALDav Plugin ausgegebene Termin -1 ist.
#		 	[Feature] Bei Befehlen an eine Zone welche Member einer Gruppe ist wird jetzt automatisch der Master ermittelt
#					  dies gilt aber nur für folgende Befehle: play, stop, pause, next, previous, toggle, rewind, seek
#			[Feature] bei ...messageid=..." können jetzt auch nicht numerische MP3 files (z.B. mein_sonos_gong) genutzt werden.
# 2.1.2		[Feature] Debugging tool added
#			[Bugfix] Korrektur beim Laden einer Playliste wenn vorher Radio/TV lief oder Mute EIN war
#			[Bugfix] Korrektur der Lautstärkeregelung/Anpassung bei Gruppendurchsagen
#			[Bugfix] Scan Zonen Funktion von LoxBerry auch für Non-LoxBerry Versionen aktualisiert und beide optimiert (Trennung von Gruppen vorm 
#					 Speichern der Config)
#			[Bugfix] Englische Version der GUI aktualisiert
# 2.1.3		[Feature] Möglichkeit des Abspielens von Spotify, Amazon, Napster und Apple Playlisten/Alben (Details siehe Wiki)
#			[Feature] Möglichkeit des Abspielens von lokalen Track's (NAS, USB-Sticks, Laufwerken, Remote PCs) -> Details siehe Wiki
#			[Feature] Prüfung bei Gruppenfunktionen ob die angesprochene Zone (zone=...) auch der Master ist, falls nicht ermitelt das System den Master
#			[Bugfix] Korrektur bei T2S wenn Playliste im Shufflemodus läuft
#			[Feature] Funktion 'nextpush'. PL läuft -> next track, Ende PL -> 1st track, Radio -> nextradio im Loop, leer -> nextradio im Loop
#			[Feature] Funktion 'next' und 'previous' optimiert. next - (letzter Track -> Track #1), 'previous - (erster Track -> letzter Track)
# 2.1.4		[Bugfix] Funktion 'radio' (radioplaylist, groupradioplaylist) korrigiert. Bei input quelle SPDIF (Playbar, Playbase) 
#					 wurde kein Radiosender geladen.
#			[Bugfix] Korrektur der Zonen Scan Funktion (temporäre Datei wird nicht mehr gelöscht)
#			[Bugfix] Korrektur der Zonen Scan Funktion nach Update Sonos auf 8.1
#			[Bugfix] Korrektur bei Einzel T2S an Master einer Gruppe. Nach Durchsage wurde Urprungszustand nicht mehr wiederhergestellt
#			[Bugfix] Erweiterung der TransportSettings (shuffle, repeat, etc.)
# 2.1.5		[Bugfix] Korrektur der Zonen Scanfunktion für Nicht LoxBerry Nutzer
#			[Feature] Neue Funktion zum Laden und Abspielen von Sonos Playlisten per Zufallsgenerator. Es könne auch Exceptions angegeben werden (siehe Wiki)
#			[Feature] Neue Funktion zum Laden und Abspielen von Sonos Radiosender per Zufallsgenerator. Es könne auch Exceptions angegeben werden (siehe Wiki)
#			[Feature] Aktualisierte Funktion um user-spezifische Playlisten zu laden (gilt nur für Spotify)
# 2.1.6		[Bugfix] Fehler bei Non LoxBerry beseitigt, es wurde versucht eine LoxBerry Berechtigung zu setzen
#			[Bugfix] SHUFFLE Wiedergabe wird jetzt nach erfolgter T2S korrekt weitergespielt
#
#
######## Script Code (ab hier bitte nichts ändern) ###################################

ini_set('max_execution_time', 120); // Max. Skriptlaufzeit auf 120 Sekunden

include("system/PHPSonos.php");
include("system/Services.php");
include("grouping.php");
include("helper.php");
require_once('system/function.debug.php');
__debug(false); // true = enable or false = disable


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

if(substr($home,0,4) == "/opt") {
	
#-- Ab hier Loxberry spezifisch ------------------------------------------------------------------
	
	$path = "$home/config/system/general.cfg";
	$general = parse_ini_file($path, TRUE);
	if ($general['BASE']['VERSION'] === "0.2.2") {
		trigger_error('The Sonos4lox Plugin require minimum LoxBerry Version 0.2.3! Please upgrade LoxBerry', E_USER_NOTICE);
		exit;
	}

	$myFolder = "$home/config/plugins/$psubfolder/";
	$myMessagepath = "//$myIP/sonos_tts/";
	$MessageStorepath = "$home/data/plugins/$psubfolder/tts/";
	#$MessageStorepath = "$home/data/plugins/sonos4lox/tts/";
	$logpath = "$home/log/plugins/$psubfolder";

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
		
	// Umbennennen des ursprünglichen Array Keys
	$config['SYSTEM']['messageStorePath'] = $MessageStorepath;
		
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
	#$myMessageStorepath = $config['SYSTEM']['messageStorePath'];
	
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

	
#$sonoszone = $sonoszonen;
#print_r($sonoszone);
#print_r($config);
#exit;

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
			break;
 
        case E_ERROR:
        case E_USER_ERROR:
			$message = date("Y-m-d H:i:s - ");
			$message .= "FATAL error: [" . $errno ."], " . "$errstr in $errfile in line $errline, \r\n";
			error_log($message, 3, $logpath."/sonos_error.log");

			echo("An Error occured. Please check ".$logpath."sonos_error.log.<br>");
			#-- Loxone Uebermittlung eines Fehlerhinweises ----------------------------------------------
			$ErrorS = rawurlencode("Sonos Fehler. Bitte log pruefen");
			$handle = @fopen("http://$loxuser:$loxpassword@$loxip/dev/sps/io/S-Error/$ErrorS", "r");
			#--------------------------------------------------------------------------------------------
        case E_RECOVERABLE_ERROR:
			$message = date("Y-m-d H:i:s - ");
			$message .= "RECOVERABLE error: [" . $errno ."], " . "$errstr in $errfile in line $errline, \r\n";
			error_log($message, 3, $logpath."/sonos_error.log");

			echo("An Error occured. Please check ".$logpath."sonos_error.log.<br>");
			#-- Loxone Uebermittlung eines Fehlerhinweises ----------------------------------------------
			$ErrorS = rawurlencode("Sonos Fehler. Bitte log pruefen");
			$handle = @fopen("http://$loxuser:$loxpassword@$loxip/dev/sps/io/S-Error/$ErrorS", "r");
			#--------------------------------------------------------------------------------------------
		default:
			#$message = date("Y-m-d H:i:s - ");
			#$message .= "Unknown error at $errfile in line $errline, \r\n";
			#error_log($message, 3, $logpath."/sonos_error.log");
			#echo("Ein unbekannter Fehler trat auf. Bitte Datei /".$logpath."/sonos_error.log pruefen.");
    }
	#echo("An Error occured. Please check ".$logpath."sonos_error.log.<br>");
	#-- Loxone Uebermittlung eines Fehlerhinweises ----------------------------------------------
	#$ErrorS = rawurlencode("Sonos Fehler. Bitte log pruefen");
	#$handle = @fopen("http://$loxuser:$loxpassword@$loxip/dev/sps/io/S-Error/$ErrorS", "r");
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
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
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
					if($config['LOXONE']['LoxDaten'] == 1) {
						sendUDPdata();
					}
					checkifmaster($master);
					$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
					$sonos->Play();
				} else {
					if($config['LOXONE']['LoxDaten'] == 1) {
						sendUDPdata();
					}
					checkifmaster($master);
					$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
					$sonos->Play();
				}
			} else {
				trigger_error("No tracks in play list to play.", E_USER_NOTICE);
			}
			break;
		
		case 'pause';
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->Pause();
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
			break;  
			
		case 'rewind':
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
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
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
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
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->Stop();
				$sonos->SetVolume($save_vol_stop);
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
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->RemoveFromQueue($_GET['remove']);
			} 
			break;   
		
		case 'playqueue':
			$titelgesammt = $sonos->GetPositionInfo();
			$titelaktuel = $titelgesammt["Track"];
			$playlistgesammt = count($sonos->GetCurrentPlaylist());
						
			if ($titelaktuel < $playlistgesammt) {
			$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$master][1]) . "#0");
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
				$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$master][1]) . "#0");
				checkifmaster($master);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->ClearQueue();
				logging();
			break;  
		  
		case 'volume':
			if(isset($volume)) {
				$sonos->SetVolume($volume);
				if($config['LOXONE']['LoxDaten'] == 1) {
					sendUDPdata();
				}
			} else {
				trigger_error('Wrong range of values for the volume been entered, only 0-100 is permitted', E_USER_NOTICE);
			}
			break;  
		  
		case 'volumeup': 
			$volume = $sonos->GetVolume();
			if($volume < 100) {
				$volume = $volume + $config['MP3']['volumeup'];
				$sonos->SetVolume($volume);
				if($config['LOXONE']['LoxDaten'] == 1) {
					sendUDPdata();
				}
			}      
			break;
			
		case 'volumedown':
			$volume = $sonos->GetVolume();
			if($volume > 0) {
				$volume = $volume - $config['MP3']['volumedown'];
				$sonos->SetVolume($volume);
				if($config['LOXONE']['LoxDaten'] == 1) {
					sendUDPdata();
				}
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
		break;
		
		case 'nextradio':
			checkifmaster($master);
			$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
			nextradio();
		break;
		
							  
		case 'sonosplaylist':
			logging();
			playlist();
		break;
		  
		case 'groupsonosplaylist':
			AddMemberTo();
			playlist();
			logging();
		break;

		case 'radioplaylist':
			radio();
			logging();
		break;
		
		case 'groupradioplaylist': 
			AddMemberTo();
			radio();
			logging();
		break;
		
		case 'radio': 
			AddMemberTo();
			radio();
			logging();
		break;
		
		case 'playlist':
			AddMemberTo();
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
		#$time_start = microtime(true);
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
		
		case 'alarmoff':
			echo '<PRE>';
				turn_off_alarms();
			echo '</PRE>';
		
		break;
		
		case 'alarmon':
			echo '<PRE>';
				restore_alarms();
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
		
		case 'zapzone':
			echo '<PRE>';
				zapzone();
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
				GetSonosFavorites();
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
		
		case 'playbatch':
			t2s_playbatch();
		break;
		
		case 'alarmstop':
			$sonos->Stop();
			include_once("text2speech.php");
			if(isset($_GET['member'])) {
				restoreGroupZone();
			} else {
				restoreSingleZone();
			}
		break;
		
		case 'zapzones':
			$ZapZones = New Zap_Sonos_Zones;
			#$ZapZones->ZapZones();
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
			#$mode = 0;
			$uuid = $sonoszone[$master][1];
			#$sonos->SetVolumeMode($mode, $uuid);
			$test = $sonos->GetVolumeMode($uuid);
			var_dump($test);
		
		
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
	global $sonos, $sonoszone, $master;
	$sonos = new PHPSonos($sonoszone[$master][0]); 
	$sonos->SnapshotGroupVolume();
	#$GroupVolume = $_GET['volume'];
	$GroupVolume = $sonos->SetGroupVolume($groupvolume);
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
		$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$master][1]) . "#0"); 
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
			#$sonos->SetMute(false);
			$sonos->AddToQueue($plfile); //Datei hinzufügen
			$sonos->SetQueue("x-rincon-queue:". trim($sonoszone[$master][1]) ."#0"); 
			if ((isset($_GET['member'])) and isset($_GET['standardvolume'])) {
				$member = $_GET['member'];
				$member = explode(',', $member);
				foreach ($member as $zone) {
					$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos IP Adresse
					$sonos->SetMute(false);
					$volume = $config['sonoszonen'][$zone][4];
					$sonos->SetVolume($config['sonoszonen'][$zone][4]);
				}
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->SetMute(false);
				$sonos->SetVolume($config['sonoszonen'][$master][4]);
				$sonos->Play();
			} else {
				if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					$sonos->Play();
				} else {
					$sonos->Play();
				}
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
	Global $sonos, $volume, $config, $sonoszone, $master;
			
	if(isset($_GET['radio'])) {
        $playlist = $_GET['radio'];		
	} elseif (isset($_GET['playlist'])) {
		$playlist = $_GET['playlist'];		
	} else {
		trigger_error("No radio stations found.", E_USER_NOTICE);
    }
	$coord = $master;
	$roomcord = getRoomCoordinator($coord);
	$sonosroom = new PHPSonos($roomcord[0]); //Sonos IP Adresse
	$sonosroom->SetQueue("x-rincon-queue:".$roomcord[1]."#0");
	$sonosroom->SetMute(false);
	$sonosroom->Stop();
    # Sonos Radio Playlist ermitteln und mit übergebene vergleichen   
    $radiolists = $sonos->Browse("R:0/0","c");
	$radioplaylist = urldecode($playlist);
	$rleinzeln = 0;
    while ($rleinzeln < count($radiolists)) {
	if ($radioplaylist == $radiolists[$rleinzeln]["title"]) {
		$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]),$radiolists[$rleinzeln]["title"]);
		#$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]));
		if (isset($_GET['member'])) {
			$member = $_GET['member'];
			$member = explode(',', $member);
			if (isset($_GET['standardvolume'])) {
				foreach ($member as $zone) {
					$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos IP Adresse
					$volume = $config['sonoszonen'][$zone][4];
					$sonos->SetVolume($config['sonoszonen'][$zone][4]);
				}
			}
			$sonos = new PHPSonos($roomcord[0]); //Sonos IP Adresse
			$sonosroom->SetVolume($config['sonoszonen'][$master][4]);
		} else {
			if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
				$sonos->RampToVolume($config['TTS']['rampto'], $volume);
			} else {
				$sonos->SetVolume($volume);
			}
		}
		$sonos->Play();
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
 
 
/**
/* Funktion : sendUDPdata --> send for each Zone as UDP package Volume and Playmode Info
/*			  Playmode: Play=1/Stop=3/Pause=2
/* @param: 	empty
/*
/* @return: Volume and Play Status per Zone
**/

 function sendUDPdata() {
	global $config, $sonoszone, $sonoszonen, $mstopology, $sonos_array_diff, $home, $tmp_lox;
	
	$tmp_lox =  parse_ini_file("$home/config/system/general.cfg", TRUE);
	if($config['LOXONE']['LoxDaten'] == 1) {
		if(substr($home,0,4) == "/opt") {		
			// LoxBerry **********************
			$sonos_array_diff = @array_diff_key($sonoszonen, $sonoszone);
			$sonos_array_diff = @array_keys($sonos_array_diff);
			$server_ip = $tmp_lox[$config['LOXONE']['Loxone']]['IPADDRESS'];
			$server_port = $config['LOXONE']['LoxPort'];
		} else {
			// Non LoxBerry ******************
			$server_ip = $config['LOXONE']['LoxIP'];
			$server_port = $config['LOXONE']['LoxUDPPort'];
		}
		$tmp_array = array();
		if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
			foreach ($sonoszone as $zone => $player) {
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				$orgsource = $sonos->GetPositionInfo();
				$temp_volume = $sonos->GetVolume();
				$zoneStatus = getZoneStatus($zone);
				if ($zoneStatus === 'single') {
					$zone_stat = 1;
				}
				if ($zoneStatus === 'master') {
					$zone_stat = 2;
				}
				if ($zoneStatus === 'member') {
					$zone_stat = 3;
				}
				// Zone ist Member einer Gruppe
				if (substr($orgsource['TrackURI'] ,0 ,9) == "x-rincon:") {
					$tmp_rincon = substr($orgsource['TrackURI'] ,9 ,24);
					$newMaster = searchForKey($tmp_rincon, $sonoszone);
					$sonos = new PHPSonos($sonoszone[$newMaster][0]);
					$gettransportinfo = $sonos->GetTransportInfo();
				// Zone ist Master einer Gruppe oder Single Zone
				} else {
					$gettransportinfo = $sonos->GetTransportInfo();
				}
				#$message = "vol_$zone@".$sonos->GetVolume()."; stat_$zone@".$sonos->GetTransportInfo();
				$message = "vol_$zone@".$temp_volume."; stat_$zone@".$gettransportinfo."; grp_$zone@".$zone_stat;
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
			$lox_ip		 = $tmp_lox[$config['LOXONE']['Loxone']]['IPADDRESS'];
			$lox_port 	 = $tmp_lox[$config['LOXONE']['Loxone']]['PORT'];
			$loxuser 	 = $tmp_lox[$config['LOXONE']['Loxone']]['ADMIN'];
			$loxpassword = $tmp_lox[$config['LOXONE']['Loxone']]['PASS'];
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
			// Zone ist Member einer Gruppe
			if (substr($temp['TrackURI'] ,0 ,9) == "x-rincon:") {
				$tmp_rincon = substr($temp['TrackURI'] ,9 ,24);
				$newMaster = searchForKey($tmp_rincon, $sonoszone);
				$sonos = new PHPSonos($sonoszone[$newMaster][0]);
				$temp = $sonos->GetPositionInfo();
				$tempradio = $sonos->GetMediaInfo();
				$gettransportinfo = $sonos->GetTransportInfo();
				// Zone ist Master einer Gruppe oder Single Zone
			} else {
				$tempradio = $sonos->GetMediaInfo();
				$gettransportinfo = $sonos->GetTransportInfo();
			}
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
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				#echo $value.'<br>';
				#echo $zone;
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
 

/** 
* Sub Function for T2S: SavePlaylist --> save temporally Playlist
*
* @param: empty
* @return: playlist "temp_t2s" saved
**/

function SavePlaylist() {
	global $sonos, $id;
	try {
		$sonos->SaveQueue("temp_t2s");
	} catch (Exception $e) {
		trigger_error("The temporary Playlist (PL) could not be saved because the list contains min. 1 Song (URL) which is not longer valid! Please check or remove the list!", E_USER_ERROR);
	}
}


/**
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


/**
* New Function for T2S: t2s_playbatch --> allows T2S to be played in batch mode
*
* @param: empty
* @return: T2S
**/
function t2s_playbatch() {
	global $words;
			
	$words = true;
	$filename = "t2s_batch.txt";
	if (!file_exists($filename)) {
		trigger_error("There is no T2S batch file to be played!", E_USER_WARNING);
		exit();
	}
	say();
}


/**
* Function: turn_off_alarms --> turns off all Sonos alarms
*
* @param: empty
* @return: disabled alarms
**/
function turn_off_alarms() {
	global $sonos, $sonoszone, $master, $home, $psubfolder;
	
	$filename = $home.'/webfrontend/html/plugins/'.$psubfolder.'/tmp_alarms.json';
	if (file_exists($filename)) {
		trigger_error("Sonos alarms could not be disabled! A file already exists, please delete before executing or run action=alarmon.", E_USER_ERROR);
	}
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$alarm = $sonos->ListAlarms();
	File_Put_Array_As_JSON($filename, $alarm);
	$quan = count($alarm);
	for ($i=0; $i<$quan; $i++) {
		$sonos->UpdateAlarm($alarm[$i]['ID'], $alarm[$i]['StartTime'], $alarm[$i]['Duration'], $alarm[$i]['Recurrence'], 
		$alarm[$i]['Enabled'] = 0, $alarm[$i]['RoomUUID'], $alarm[$i]['ProgramURI'], $alarm[$i]['ProgramMetaData'], 
		$alarm[$i]['PlayMode'], $alarm[$i]['Volume'], $alarm[$i]['IncludeLinkedZones']);
	}
}


/**
* Function: restore_alarms --> turns on all previous saved Sonos alarms
*
* @param: empty
* @return: disabled alarms
**/
function restore_alarms() {
	global $sonos, $sonoszone, $home, $psubfolder, $alarm;
	
	$filename = $home.'/webfrontend/html/plugins/'.$psubfolder.'/tmp_alarms.json';
	if (!file_exists($filename)) {
		trigger_error("Sonos alarms could not be restored! There is no file available to restore.", E_USER_ERROR);
	}
	$alarm = File_Get_Array_From_JSON($filename);
	$quan = count($alarm);
	for ($i=0; $i<$quan; $i++) {
		$sonos->UpdateAlarm($alarm[$i]['ID'], $alarm[$i]['StartTime'], $alarm[$i]['Duration'], $alarm[$i]['Recurrence'], 
		$alarm[$i]['Enabled'], $alarm[$i]['RoomUUID'], $alarm[$i]['ProgramURI'], $alarm[$i]['ProgramMetaData'], 
		$alarm[$i]['PlayMode'], $alarm[$i]['Volume'], $alarm[$i]['IncludeLinkedZones']);
	}
	unlink($filename); 
}



/**
* Function: zapzone --> checks each zone in network and if playing add current zone as member
*
* @param: empty
* @return: 
**/

function zapzone() {
	global $config, $sonos, $sonoszone, $master, $playzones, $count;
	include_once("text2speech.php");

	$sonos = new PHPSonos($sonoszone[$master][0]);
	if (substr($sonos->GetPositionInfo()["TrackURI"], 0, 15) == "x-rincon:RINCON") {
		$sonos->BecomeCoordinatorOfStandaloneGroup();
	}
	play_zones();
	$playingzones = $_SESSION["playingzone"];
	#print_r($playingzones);
	$max_loop = count($playingzones);
	$count = countzones();
	// if no zone is playing switch to nextradio
	if (empty($playingzones) or $count > count($playingzones)) {
		nextradio();
		sleep($config['VARIOUS']['maxzap']);
		if(file_exists("count.txt"))  {
			unlink("count.txt");
		}
		exit;
	}
	$currentZone = currentZone();
	// finally loop by call through array
    foreach ($playingzones as $key => $value) {
		if($key == $currentZone) {
            $nextZoneUrl 	= next($playingzones);
            $nextZoneKey    = key($playingzones);
            //if last element catched, move to first element
            if(!$nextZoneUrl)  {
                $nextZoneUrl 	= reset($playingzones);
                $nextZoneKey    = key($playingzones);
			}
			break;
        } else {
			next($playingzones);
		}
	}
	if (empty($nextZoneKey)) {
		$nextZoneKey = $key;
	}
	#echo '<br>Zone: ['.$nextZoneKey.']';
	saveCurrentZone($nextZoneKey);
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	$sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$nextZoneKey][1]);
	$sonos->SetMute(false);
	}


/**
* Sub-Function for zapzone: saveCurrentZone --> saves current playing zone to file
*
* @param: Zone
* @return: 
**/

function saveCurrentZone($nextZoneKey) {
    if(!touch('curr_Zone.txt')) {
		trigger_error("No permission to write to curr_Zone.txt", E_USER_ERROR);
    }
	$handle = fopen ('curr_Zone.txt', 'w');
    fwrite ($handle, $nextZoneKey);
    fclose ($handle);                
} 


/**
* Sub-Function for zapzone: currentZone --> open file and read last playing zone
*
* @param: 
* @return: last playing zone 
**/      

function currentZone() {
	global $config, $master;

	$playingzones = $_SESSION["playingzone"];
	if(!touch('curr_Zone.txt')) {
		trigger_error("Could not open file curr_Zone.txt", E_USER_ERROR);
    }
	$currentZone = file('curr_Zone.txt');
	if(empty($currentZone)) {
		reset($playingzones);
        $currentZone[0] = key($playingzones);
        saveCurrentZone($currentZone[0]);
    }
	return $currentZone[0];
}


/**
* Sub-Function for zapzone: play_zones --> scans through Sonos Network and create array of currently playing zones
*
* @param: 
* @return: array of zones 
**/

function play_zones() {
	global $sonoszone, $master, $sonos, $playingzones;
	
	$playzone = $sonoszone;
	unset($playzone[$master]); 
	foreach ($playzone as $key => $val) {
		$sonos = new PHPSonos($playzone[$key][0]);
		// only zones which are not a group member
		$zonestatus = getZoneStatus($key);
		if ($zonestatus <> 'member') {
			// check if zone is currently playing and add to array
			if($sonos->GetTransportInfo() == 1) {
				$playingzones[$key] = $val[1];
			}
		}
	}
	$_SESSION["playingzone"] = $playingzones;
	return array($playingzones);
}


/**
* Sub-Function for zapzone: countzones --> increment counter by each click
*
* @param: 
* @return: amount if clicks
**/

function countzones() {
	if(!file_exists("count.txt")){
        fopen("count.txt", "a" );
        $aufruf=0;
	}
	$counter=fopen("count.txt","r+"); $output=fgets($counter,100);
	$output=$output+1;
	rewind($counter);
	fputs($counter,$output);
	return $output;
}


/**
* Function: nextradio --> iterate through Radio Favorites (endless)
*
* @param: empty
* @return: 
**/
function nextradio() {
	global $sonos, $config, $master, $debug, $volume;
	
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	$radioanzahl_check = $result = count($config['RADIO']);
	if($radioanzahl_check == 0)  {
		trigger_error("There are no Radio Stations maintained in the configuration. Pls update before using function NEXTRADIO or ZAPZONE!", E_USER_ERROR);
	}
	$playstatus = $sonos->GetTransportInfo();
	$radiovolume = $sonos->GetVolume();
	$radioname = $sonos->GetMediaInfo();
	if (!empty($radioname["title"])) {
		$senderuri = $radioname["title"];
	} else {
		$senderuri = "";
	}
	$radio = $config['RADIO']['radio'];
	$radioanzahl = count($config['RADIO']['radio']);
	$radio_name = array();
	$radio_adresse = array();
	foreach ($radio as $key) {
		$radiosplit = explode(',',$key);
		array_push($radio_name, $radiosplit[0]);
		array_push($radio_adresse, $radiosplit[1]);
	}
	$senderaktuell = array_search($senderuri, $radio_name);
	# Wenn nextradio aufgerufen wird ohne eine vorherigen Radiosender
	if( $senderaktuell == "" && $senderuri == "" || substr($senderuri, 0, 12) == "x-file-cifs:" ) {
		$senderaktuell = -1;
	}
    if ($senderaktuell < ($radioanzahl) ) {
		@$sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[$senderaktuell + 1], $radio_name[$senderaktuell + 1]);
	}
    if ($senderaktuell == $radioanzahl - 1) {
	    $sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[0], $radio_name[0]);
		    }
    if( $debug == 2) {
        echo "Senderuri vorher: " . $senderuri . "<br>";
        echo "Sender aktuell: " . $senderaktuell . "<br>";
        echo "Radioanzahl: " .$radioanzahl . "<br>";
    }
	if ($config['VARIOUS']['announceradio'] == 1) {
		include_once("text2speech.php");
		say_radio_station();
	}
    if($playstatus == 1) {
		$sonos->SetVolume($radiovolume);
		$sonos->Play();
	} else {
		$sonos->RampToVolume($config['TTS']['rampto'], $volume);
		$sonos->Play();
	}
	#print_r($radio_name);
}


/**
* Funktion : 	LineIn --> schaltet die angegebene Zone auf LineIn um (Cinch Eingang)
*
* @param: empty
* @return: empty
**/

function LineIn() {
	global $sonoszone, $master;
	
	$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
	$url = "http://" . $sonoszone[$master][0] . ":1400/xml/device_description.xml";
	$xml = simpleXML_load_file($url);
	$model = $xml->device->modelNumber;
	$model = allowLineIn($model);
	if ($model == true) {
		$sonos->SetAVTransportURI("x-rincon-stream:" . $sonoszone[$master][1]);
		$sonos->Play();	
	} else {
		trigger_error("The specified Zone does not support Line-in to be selected!", E_USER_ERROR);
		exit;
	}
	
}



/**
* Funktion : 	SetVolumeModeConnect --> setzt für CONNECT ggf. die Lautstärke von fix auf variabel
*
* @param: $model --> alle gefundenen Devices
* @return: $models --> 0 or 1
**/

function SetVolumeModeConnect($mode, $zonenew)  {
	global $sonoszone, $sonos, $mode;
	
	$sonos = new PHPSonos($sonoszone[$zonenew][0]);
	$getModel = $sonoszone[$zonenew][2];
	$model = OnlyCONNECT($getModel);
	if ($model === true) {
		$uuid = $sonoszone[$zonenew][1];
		$sonos->SetVolumeMode($mode, $uuid);
	}
}


/**
* Funktion : 	GetVolumeModeConnect --> setzt für CONNECT ggf. die Lautstärke von fix auf variabel
*
* @param: $model --> alle gefundenen Devices
* @return: $models --> true (Volume fixed) or false (Volume flexible)
**/

function GetVolumeModeConnect($player)  {
	global $sonoszone, $master, $sonos, $modeback, $player;
	
	$modeback = "";
	$sonos = new PHPSonos($sonoszone[$player][0]);
	$getModel = $sonoszone[$player][2];
	$model = OnlyCONNECT($getModel);
	if ($model === true) {
		$uuid = $sonoszone[$player][1];
		$modeback = $sonos->GetVolumeMode($uuid);
		$modeback === true ? $modeback = 'true' : $modeback = 'false';
	}
	return $modeback;
}



/** NICHT LIVE **
* Funktion : 	GetSonosFavorites --> lädt die Sonos Favoriten in die Queue (kein Radio)
*
* @param: empty
* @return: Favoriten in der Queue
**/

function GetSonosFavorites() {
	global $sonoszone, $master;
	
	$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos ZP lox_ipesse 
	$browselist = $sonos->GetSonosFavorites("FV:2","c"); 
	print_r($browselist);
	$posinfo = $sonos->GetPositionInfo();
	#echo $uri = $posinfo['TrackURI'];
	#echo '<br>';
	#echo $meta = $posinfo['TrackMetaData'];
	

	#$finalstring = urldecode($scope);
	#$sonos->AddFavToQueue($uri, $meta);	
	#exit;
	foreach ($browselist as $favorite) {
		$scope = $favorite['res'];
		if ((substr($scope,0,11) != "x-sonosapi-") and (substr($scope,0,11) != "x-rincon-cp")) {
			$finalstring = urldecode($scope);
			$track = $favorite['res'];
			$title = $favorite['title'];
			$artist = $favorite['artist'];
			$metadata = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/"><item id="-1" parentID="-1" restricted="true"><res protocolInfo="sonos.com-http:*:audio/mpeg:*>'.$track.'</res><r:streamContent></r:streamContent><upnp:albumArtURI></upnp:albumArtURI><dc:title>'.$title.'</dc:title><upnp:class>object.item.audioItem.musicTrack</upnp:class><dc:creator>'.$artist.'</dc:creator></item></DIDL-Lite>';
			#$metadata = '<DIDL-Lite xmlns:dc=http://purl.org/dc/elements/1.1/ xmlns:upnp=urn:schemas-upnp-org:metadata-1-0/upnp/ xmlns:r=urn:schemas-rinconnetworks-com:metadata-1-0/ xmlns=urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/item id=-1; parentID=-1; restricted=truedc:title'.$title.'/dc:titleupnp:classobject.item.audioItem.musicTrack.#editorialViewTracks.sonos-favorite/upnp:classdesc id=cdudn nameSpace=urn:schemas-rinconnetworks-com:metadata-1-0/SA_RINCON40967_X_#Svc40967-0-Token/desc/item/DIDL-Lite>';
			$sonos->AddFavToQueue($finalstring, $metadata);
			try {
			#$sonos->AddToQueue($track);	
			} catch (Exception $e) {
#			trigger_error("The connection to Loxone could not be initiated!", E_USER_NOTICE);	
		}	
		}
	}
	#$sonos->Play();
}



/**
* Funktion : 	next_dynamic --> Unterfunktion von 'nextpush', lädt PL oder Radio Favoriten, je nachdem was gerade läuft
*
* @param: empty
* @return: Playliste oder Radio
**/

function next_dynamic() {
	global $sonos, $sonoszone, $master;
	
	$titelgesammt = $sonos->GetPositionInfo();
	$titelaktuel = $titelgesammt["Track"];
	$playlistgesammt = count($sonos->GetCurrentPlaylist());
	$sonos->SetPlayMode('NORMAL');
	$sonos->SetMute(false);
	if (($titelaktuel < $playlistgesammt) or (substr($titelgesammt["TrackURI"], 0, 9) == "x-rincon:")) {
		checkifmaster($master);
		$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
		$sonos->Next();
	} else {
		checkifmaster($master);
		$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
		$sonos->SetTrack("1");
	}
	$sonos->Play();
}



/**
* Funktion : 	random_playlist --> lädt per Zufallsgenerator eine Playliste und spielt sie ab.
*
* @param: empty
* @return: Playliste
**/

function random_playlist() {
	global $sonos, $sonoszone, $master, $volume, $config;
	
	if (isset($_GET['member'])) {
		trigger_error("This function could not be used with groups!", E_USER_ERROR);
		exit;
	}
	$sonoslists = $sonos->GetSONOSPlaylists();
	print_r($sonoslists);
	if(!isset($_GET['except'])) {
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	} else {
		$except = $_GET['except'];
		$exception = explode(',',$except);
		for($i = 0; $i < count($exception); $i++) {
			$exception[$i] = str_replace(' ', '', $exception[$i]);
		}
		foreach ($exception as $key => $val) {
			unset($sonoslists[$val]);
		}
		$sonoslists = array_values($sonoslists);
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	}
	$plfile = urldecode($sonoslists[$random]["file"]);
	$sonos->ClearQueue();
	$sonos->SetMute(false);
	$sonos->AddToQueue($plfile);
	$sonos->SetQueue("x-rincon-queue:". trim($sonoszone[$master][1]) ."#0"); 
	if (!isset($_GET['volume'])) {
		if($sonos->GetVolume() <= $config['TTS']['volrampto']) {
			$sonos->RampToVolume($config['TTS']['rampto'], $volume);
		}	
	}
	$sonos->Play();
}


/**
* Funktion : 	random_radio --> lädt per Zufallsgenerator einen Radiosender und spielt ihn ab.
*
* @param: empty
* @return: Radio Sender
**/

function random_radio() {
	global $sonos, $sonoszone, $master, $volume, $config;
	
	if (isset($_GET['member'])) {
		trigger_error("This function could not be used with groups!", E_USER_ERROR);
		exit;
	}
	$sonoslists = $sonos->Browse("R:0/0","c");
	print_r($sonoslists);
	if(!isset($_GET['except'])) {
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	} else {
		$except = $_GET['except'];
		$exception = explode(',',$except);
		for($i = 0; $i < count($exception); $i++) {
			$exception[$i] = str_replace(' ', '', $exception[$i]);
		}
		foreach ($exception as $key => $val) {
			unset($sonoslists[$val]);
		}
		$sonoslists = array_values($sonoslists);
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	}
	$sonos->ClearQueue();
	$sonos->SetMute(false);
	$sonos->SetRadio(urldecode($sonoslists[$random]["res"]),$sonoslists[$random]["title"]);
	if (!isset($_GET['volume'])) {
		if($sonos->GetVolume() <= $config['TTS']['volrampto']) {
			$sonos->RampToVolume($config['TTS']['rampto'], $volume);
		}	
	}
	$sonos->Play();
}




?>

