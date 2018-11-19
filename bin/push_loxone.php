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

register_shutdown_function('shutdown');

$ms = LBSystem::get_miniservers();
#$log = LBLog::newLog( [ "name" => "Push Data", "addtime" => 1, "filename" => "$lbplogdir/sonos.log", "append" => 1 ] );

#LOGSTART("push data");
#echo '<PRE>'; 

$myFolder = "$lbpconfigdir";
$htmldir = "$lbphtmldir";
$tmp_play = "stat.txt";
$stat = $htmldir."/".$tmp_play;

$mem_sendall = 1;
$mem_sendall_sec = 3600;
#use LoxBerry::IO;

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
	#print_r($my_ms);
	
	// if zone is currently playing... 
	if (count($playing) > 0)  {
		send_udp();
		send_vit();
		@unlink($stat);
		exit;
	} else {
		// ... if not, push data one more time...
		if (@!file_exists($stat))  {
			file_put_contents($stat, "1");
			send_udp();
			send_vit();
		#} else {
		#	//... don't push data any more
		#	exit;
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
	
	global $config, $my_ms, $sonoszone, $sonoszonen, $sonos; 
	
		// LoxBerry **********************
		# send UDP data
		$sonos_array_diff = @array_diff_key($sonoszonen, $sonoszone);
		$sonos_array_diff = @array_keys($sonos_array_diff);
		$server_ip = $my_ms['IPAddress'];
		$server_port = $config['LOXONE']['LoxPort'];
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
				$message = "vol_$zone@".$temp_volume."; stat_$zone@".$gettransportinfo."; grp_$zone@".$zone_stat;
				array_push($tmp_array, $message);
			}
		} else {
			LOGERR("Can't create UDP socket to $server_ip");
			exit(1);
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
			LOGERR("The connection to Loxone could not be initiated!");	
			exit(1);
		}
		socket_close($socket);
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
		$lox_ip		 = $my_ms['IPAddress'];
		$lox_port 	 = $my_ms['Port'];
		$loxuser 	 = $my_ms['Admin'];
		$loxpassword = $my_ms['Pass'];
		$loxip = $lox_ip.':'.$lox_port;
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
				$valueurl = rawurlencode($value);
				$valuesplit[0] = rawurlencode($valuesplit[0]);
				$valuesplit[1] = rawurlencode($valuesplit[1]);
					try {
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/titint_$zone/$valueurl"); // Titel- und Interpretinfo für Loxone
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/tit_$zone/$valuesplit[0]"); // Nur Titelinfo für Loxone
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/int_$zone/$valuesplit[1]"); // Nur Interpreteninfo für Loxone
						$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/source_$zone/$source"); // Radio oder Playliste
					} catch (Exception $e) {
						LOGERR("The connection to Loxone could not be initiated!");	
						exit;
					}							
			}
		}
		#LOGINF ("Push");
	}
	

 
 function shutdown()  {
	global $log;
	#$log->LOGEND("");
}

?>