#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "loxberry_io.php";

require_once("$lbphtmldir/system/PHPSonos.php");
require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/system/logging.php");
require_once("$lbphtmldir/Helper.php");
require_once("$lbphtmldir/Grouping.php");
require_once("$lbphtmldir/system/io-modul.php");
#require_once("$lbphtmldir/system/io-modul-http.php");

register_shutdown_function('shutdown');

$ms = LBSystem::get_miniservers();
#$log = LBLog::newLog( [ "name" => "Push Data", "addtime" => 1, "filename" => "$lbplogdir/sonos.log", "append" => 1 ] );

#LOGSTART("push data");
 

$myFolder = "$lbpconfigdir";
$htmldir = "$lbphtmldir";
$tmp_play = "stat.txt";
$stat = $htmldir."/".$tmp_play;

$mem_sendall = 0;
$mem_sendall_sec = 3600;

#echo '<PRE>';

global $mem_sendall, $mem_sendall_sec;

// Parsen der Konfigurationsdatei sonos.cfg
	if (!file_exists($myFolder.'/sonos.cfg')) {
		LOGERR('The file sonos.cfg could not be opened, please try again!');
		exit(1);
	} else {
		$tmpsonos = parse_ini_file($myFolder.'/sonos.cfg', TRUE);
	}
		
	// check if Data transmission is switched off
	if(!is_enabled($tmpsonos['LOXONE']['LoxDaten'])) {
		exit;
	}
	
	// Parsen der Sonos Zonen Konfigurationsdatei player.cfg
	if (!file_exists($myFolder.'/player.cfg')) {
		LOGERR('The file player.cfg  could not be opened, please try again!');
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
			$timeout = 3;
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
		LOGWARN ("Your selected Miniserver from Sonos4lox Plugin config seems not to be fully configured. Please check your LoxBerry miniserver config!") ;
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
	
	global $config, $my_ms, $sonoszone, $sonoszonen, $sonos, $mem_sendall_sec, $mem_sendall, $response; 
	
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
				#echo $zone." ".$zoneStatus.'<br>';
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
				#$tmp_array["vol_$zone@"] = $temp_volume;
				#$tmp_array["stat_$zone@"] = $gettransportinfo;
				#$tmp_array["grp_$zone@"] = $zone_stat;
				$tmp_array["vol_$zone"] = $temp_volume;
				$tmp_array["stat_$zone"] = $gettransportinfo;
				$tmp_array["grp_$zone"] = $zone_stat;
				}
		} else {
			LOGERR("Can't create UDP socket to $server_ip");
			exit(1);
		}
		
		$response = udp_send_mem($no_ms, $server_port, "Sonos4lox", $tmp_array);
		#$response = msudp_send_mem($no_ms, $server_port, "Sonos4lox", $tmp_array, "@");
		
			
		#if (!isset($response)) {
		#	echo "Error sending to Miniserver";
		#} else {
		#	echo "Sent ok.";
		#}
	}
	
	
	/**
	/* Funktion : send_vit --> sendet Titel/Interpret/Radiosender Daten an virtuelle Texteingänge
	/*
	/* @param: nichts                             
	/* @return: Texte je Player
	**/
	
	function send_vit()  {
		
		global $config, $my_ms, $sonoszone, $sonoszonen, $sonos; 
		
		# send TEXT data
		#$lox_ip		 = $my_ms['IPAddress'];
		#$lox_port 	 = $my_ms['Port'];
		#$loxuser 	 = $my_ms['Admin'];
		#$loxpassword = $my_ms['Pass'];
		#$loxip = $lox_ip.':'.$lox_port;
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
				if(isset($tempradio["title"]) && (empty($temp["duration"])) && (substr($temp["TrackURI"], 0, 18) != "x-sonos-htastream:")) {	
					$stream_content = $temp["streamContent"];
					if (empty($stream_content))  {
						$value = @substr($tempradio["title"], 0, 40); 
						$valuesplit[0] = $value; 							
						$valuesplit[1] = $value;
					} else {
						$value = $stream_content;
						$valuesplit[0] = $stream_content; 
						$valuesplit[1] = $stream_content;
					}
					$source = 1;
				} 
				// TV läuft
				if((empty($temp["duration"])) && (substr($temp["TrackURI"], 0, 18) == "x-sonos-htastream:")) {	
					$value = "TV läuft";
					$valuesplit[0] = "TV läuft";
					$valuesplit[1] = "TV läuft";
					$source = 3;
				// Playliste wird gerade gespielt
				} 
				if((!empty($temp["duration"])) && (substr($temp["TrackURI"], 0, 18) != "x-sonos-htastream:")) {	
					$artist = substr($temp["artist"], 0, 30);
					$title = substr($temp["title"], 0, 50); 
					$value = $artist." - ".$title; 	// kombinierte Titel- und Interpretinfo
					$valuesplit[0] = $title; 		// Nur Titelinfo
					$valuesplit[1] = $artist;		// Nur Interpreteninfo
					$source = 2;
				}
				// Übergabe der Titelinformation an Loxone (virtueller Texteingang)
				$sonos = new PHPSonos($sonoszone[$zone][0]);
				#$valueurl = rawurlencode($value);
				#$valuesplit[0] = rawurlencode($valuesplit[0]);
				#$valuesplit[1] = rawurlencode($valuesplit[1]);
				$valueurl = ($value);
				#$valuesplit[0] = ($valuesplit[0]);
				#$valuesplit[1] = ($valuesplit[1]);
				try {
					$data['titint_'.$zone] = $valueurl;
					$data['tit_'.$zone] = $valuesplit[0];
					$data['int_'.$zone] = $valuesplit[1];
					$data['source_'.$zone] = $source;
				} catch (Exception $e) {
					LOGERR("The connection to Loxone could not be initiated!");	
					exit;
				}
			ms_send_mem($config['LOXONE']['Loxone'], $data, $value = null);
			}
		}
		#ms_send_mem($config['LOXONE']['Loxone'], $data, $value = null);
		#print_r($data);
		#LOGINF ("Push");
	}

 function shutdown()  {
	global $log;
	#$log->LOGEND("");
}

?>