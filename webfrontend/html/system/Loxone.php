<?php

/**
* Submodul: Loxone
*
**/

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
		// LoxBerry **********************
		$sonos_array_diff = @array_diff_key($sonoszonen, $sonoszone);
		$sonos_array_diff = @array_keys($sonos_array_diff);
		$server_ip = $tmp_lox[$config['LOXONE']['Loxone']]['IPADDRESS'];
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
		$lox_ip		 = $tmp_lox[$config['LOXONE']['Loxone']]['IPADDRESS'];
		$lox_port 	 = $tmp_lox[$config['LOXONE']['Loxone']]['PORT'];
		$loxuser 	 = $tmp_lox[$config['LOXONE']['Loxone']]['ADMIN'];
		$loxpassword = $tmp_lox[$config['LOXONE']['Loxone']]['PASS'];
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
	
	$tmp_lox =  parse_ini_file("$home/config/system/general.cfg", TRUE);
	$lox_ip		 = $tmp_lox[$config['LOXONE']['Loxone']]['IPADDRESS'];
	$lox_port 	 = $tmp_lox[$config['LOXONE']['Loxone']]['PORT'];
	$loxuser 	 = $tmp_lox[$config['LOXONE']['Loxone']]['ADMIN'];
	$loxpassword = $tmp_lox[$config['LOXONE']['Loxone']]['PASS'];
	$loxip = $lox_ip.':'.$lox_port;
	try {
		$handle = fopen("http://$loxuser:$loxpassword@$loxip/dev/sps/io/S-Error/''", "r");
	} catch (Exception $e) {
		trigger_error("The error message could not be deleted!", E_USER_NOTICE);	
	}							
	echo '<PRE>';
 }

?>