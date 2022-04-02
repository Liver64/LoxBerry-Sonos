#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "loxberry_io.php";
require_once "$lbpbindir/phpmqtt/phpMQTT.php";

require_once("$lbphtmldir/system/PHPSonos.php");
require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/system/logging.php");
require_once("$lbphtmldir/Helper.php");
require_once("$lbphtmldir/Grouping.php");
require_once("$lbphtmldir/system/io-modul.php");
include("$lbpbindir/binlog.php");


#echo '<PRE>';
#echo "<br>";

# check if T2S is currently running, if yes we skip
$tmp_tts = "/run/shm/s4lox_tmp_tts";
if (is_file($tmp_tts))   {
	exit;
}

$myFolder = "$lbpconfigdir";
$htmldir = "$lbphtmldir";
$tmp_play = "stat.txt";
$stat = $htmldir."/".$tmp_play;

$mem_sendall = 0;
$mem_sendall_sec = 3600;

global $mem_sendall, $mem_sendall_sec, $nextr;

// Parsen der Konfigurationsdatei sonos.cfg
	if (!file_exists($myFolder.'/sonos.cfg')) {
		binlog("Push data", "/bin/push_loxone.php: The file sonos.cfg could not be opened, please try again! We skip here.");
		exit(1);
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
	}
		
	// check if Data transmission is switched off
	if(!is_enabled($tmpsonos['LOXONE']['LoxDaten'])) {
		exit;
	}
	
	# get MS
	$ms = LBSystem::get_miniservers();
	
	// Get the MQTT Gateway connection details from LoxBerry
	$creds = mqtt_connectiondetails();
	 
	// MQTT requires a unique client id
	$client_id = uniqid(gethostname()."_client");

	$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'],  $creds['brokerport'], $client_id);
	if( $mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass'] ) ) {
		$mqttstat = "1";
	} else {
		$mqttstat = "0";
	}
	
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		binlog("Push data", "/bin/push_loxone.php: The file player.cfg could not be opened, please try again! We skip here.");
		exit(1);
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
		
	// Übernahme und Deklaration von Variablen aus der Konfiguration
	$sonoszonen = $config['sonoszonen'];
	
// check if zones are connected	
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
		foreach($sonoszonen as $zonen => $ip) {
			$port = 1400;
			$timeout = 2;
			$handle = @stream_socket_client("$ip[0]:$port", $errno, $errstr, $timeout);
			if($handle) {
				$sonoszone[$zonen] = $ip;
				array_push($zonesonline, $zonen);
				fclose($handle);
			}
		}
		$zoon = implode(", ", $zonesonline);
	} else {
		$sonoszone = $sonoszonen;
	}
	#print_r($zonesonline);
	
	// identify those zones which are not single/master
	$tmp_playing = array();
	foreach ($sonoszone as $zone => $player) {
		$sonos = new PHPSonos($sonoszone[$zone][0]);
		$zoneStatus = getZoneStatus($zone);
		if ($zoneStatus === 'single') {
			array_push($tmp_playing, $zone);
		}
		if ($zoneStatus === 'master') {
			array_push($tmp_playing, $zone);
		}
	}	
	#print_r($tmp_playing);	
	
	// identify single/master zone currently playing
	$playing = array();
	foreach ($tmp_playing as $tmp_player)  {
		$sonos = new PHPSonos($sonoszone[$tmp_player][0]);
		if ($sonos->GetTransportInfo() == "1")  {
			array_push($playing, $tmp_player);
		}
	}
	#print_r($playing);
		
	// ceck if configured MS is fully configured
	if (!isset($ms[$config['LOXONE']['Loxone']])) {
		binlog("Push data", "/bin/push_loxone.php: Your selected Miniserver from Sonos4lox Plugin config seems not to be fully configured. Please check your LoxBerry miniserver config!");
		exit(1);
	}
	
	// obtain selected Miniserver from config
	$my_ms = $ms[$config['LOXONE']['Loxone']];
	
	$_SESSION['stat'] = "0";
	
	// if zone is currently playing... 
	if (count($playing) > 0)  {
		send_udp();
		send_vit();
		unset($_SESSION['stat']);
	} else {
		// ... if not, push data one more time...
		if (@$pl_stat != "1")  {
			$pl_stat = $_SESSION['stat'] = "1";
			send_udp();
			send_vit();
		}
	}
	exit;

	
	/**
	/* Funktion : send_udp --> sendet Statusdaten je player Daten an UDP
	/*
	/* @param: nichts                             
	/* @return: div. Stati je Player
	**/
	
	function send_udp()  {	
	
	global $config, $mqtt, $mqttstat, $my_ms, $sonoszone, $sonoszonen, $sonos, $mem_sendall_sec, $mem_sendall, $response, $nextr; 
	
		// LoxBerry **********************
		# send UDP data
		$sonos_array_diff = @array_diff_key($sonoszonen, $sonoszone);
		$sonos_array_diff = @array_keys($sonos_array_diff);
		$server_ip = $my_ms['IPAddress'];
		$server_port = $config['LOXONE']['LoxPort'];
		$no_ms = $config['LOXONE']['Loxone'];
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
				$tmp_array["vol_$zone"] = $temp_volume;
				$tmp_array["stat_$zone"] = $gettransportinfo;
				$tmp_array["grp_$zone"] = $zone_stat;
				if ($mqttstat == "1")   {
					$mqtt->publish('Sonos4lox/vol/'.$zone, $tmp_array["vol_$zone"], 0, 1);
					$mqtt->publish('Sonos4lox/stat/'.$zone, $tmp_array["stat_$zone"], 0, 1);
					$mqtt->publish('Sonos4lox/grp/'.$zone, $tmp_array["grp_$zone"], 0, 1);
				}
			}
		} else {
			LOGERR("Can't create UDP socket to $server_ip");
			exit(1);
		}
		
		$response = udp_send_mem($no_ms, $server_port, "Sonos4lox", $tmp_array);
		
	
	/**
	/* Funktion : send_vit --> sendet Titel/Interpret/Radiosender Daten an virtuelle Texteingänge
	/*
	/* @param: nichts                             
	/* @return: Texte je Player
	**/
	
	function send_vit()  {
		
		global $config, $mqtt, $mqttstat, $my_ms, $sonoszone, $sonoszonen, $sonos, $valuesplit, $split, $station, $nextr; 

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
				// Normales Radio wird gerade gespielt
				$haystack = $tempradio["CurrentURI"];
				$needle = "sid=254";		// sid=254 für alle Radiosender
				$needleSonos = "sid=303";		// sid=303 für Sonos Radiosender
				$contain = mb_strpos($haystack, $needle) !== false;
				$containSonos = mb_strpos($haystack, $needleSonos) !== false;
				if ($contain === true or substr($tempradio['CurrentURI'] ,0 ,18) == "x-rincon-mp3radio:")   {
					$valuesplit[0] = ' ';
					$valuesplit[1] = ' ';
					$value = ' ';
					$station = $tempradio["title"];
					$source = 1;
				} 
				// TV läuft
				if (substr($temp["TrackURI"], 0, 17) == "x-sonos-htastream" && $containSonos === false && $contain === false && substr($tempradio['CurrentURI'] ,0 ,18) != "x-rincon-mp3radio:") {	
					$value = "TV";
					$valuesplit[0] = "TV";
					$valuesplit[1] = "TV";
					$station = ' ';
					$source = 3;
				} 
				// Playliste wird gerade gespielt
				if (substr($temp["TrackURI"], 0, 17) != "x-sonos-htastream" && $containSonos === false && $contain === false && substr($tempradio['CurrentURI'] ,0 ,18) != "x-rincon-mp3radio:") {	
					$artist = substr($temp["artist"], 0, 30);
					$title = substr($temp["title"], 0, 50);
					if ($artist <> "")  { 
						$value = $artist." - ".$title; 	// kombinierte Titel- und Interpretinfo
						$valuesplit[0] = $title; 		// Nur Titelinfo
						$valuesplit[1] = $artist;		// Nur Interpreteninfo
					} else {
						$value = $tempradio["title"];
						$split = explode(' - ', $value);
						$valuesplit[0] = $split[0]; 	// Nur Titelinfo
						$valuesplit[1] = $split[1];		// Nur Interpreteninfo
					}
					$station = ' ';
					$source = 2;
				}
				// Sonos Radio wird gerade gespielt
				if ($containSonos === true)  {	
					$artist = substr($temp["artist"], 0, 30);
					$title = substr($temp["title"], 0, 50);
					if ($artist <> "")  { 
						$value = $artist." - ".$title; 	// kombinierte Titel- und Interpretinfo
						$valuesplit[0] = $title; 		// Nur Titelinfo
						$valuesplit[1] = $artist;		// Nur Interpreteninfo
						$station = "Sonos Radio - ".$tempradio["title"];
						$source = 1;
					}
				}
				// Übergabe der Titelinformation an Loxone (virtueller Texteingang)
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				$valueurl = ($value);
				try {
					$data['titint_'.$zone] = $valueurl;
					$data['tit_'.$zone] = $valuesplit[0];
					$data['int_'.$zone] = $valuesplit[1];
					$data['radio_'.$zone] = $station;
					$data['source_'.$zone] = $source;
					if ($mqttstat == "1")   {
						$mqtt->publish('Sonos4lox/titint/'.$zone, $data['titint_'.$zone], 0, 1);
						$mqtt->publish('Sonos4lox/tit/'.$zone, $data['tit_'.$zone], 0, 1);
						$mqtt->publish('Sonos4lox/int/'.$zone, $data['int_'.$zone], 0, 1);
						$mqtt->publish('Sonos4lox/radio/'.$zone, $data['radio_'.$zone], 0, 1);
						$mqtt->publish('Sonos4lox/source/'.$zone, $data['source_'.$zone], 0, 1);
					}
				} catch (Exception $e) {
					LOGERR("The connection to Loxone could not be initiated!");	
					exit;
				}
			} else {
				$valuesplit[0] = ' ';
				$valuesplit[1] = ' ';
				$valueurl = ' ';
				$station = ' ';
				$source = 0;
				$data['titint_'.$zone] = $valueurl;
				$data['tit_'.$zone] = $valuesplit[0];
				$data['int_'.$zone] = $valuesplit[1];
				$data['radio_'.$zone] = $station;
				$data['source_'.$zone] = $source;
				if ($mqttstat == "1")   {
					$mqtt->publish('Sonos4lox/titint/'.$zone, $data['titint_'.$zone], 0, 1);
					$mqtt->publish('Sonos4lox/tit/'.$zone, $data['tit_'.$zone], 0, 1);
					$mqtt->publish('Sonos4lox/int/'.$zone, $data['int_'.$zone], 0, 1);
					$mqtt->publish('Sonos4lox/radio/'.$zone, $data['radio_'.$zone], 0, 1);
					$mqtt->publish('Sonos4lox/source/'.$zone, 0, 0, 1);
				}
			}
		}
		ms_send_mem($config['LOXONE']['Loxone'], $data, $value = null);
		$mqtt->close();
		#print_r($data);
	}
	
}	
 	
 

?>