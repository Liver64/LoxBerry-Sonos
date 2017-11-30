<?php
/********************************************************************************************************
/* Funktion : ermittelt automatisch die IP Adressen der sich im Netzwerk befindlichen Sonos Komponenten
/* @param: 	$ip = Multicast Adresse
/*			$port = Port
/*
/* @return: Array mit allen gefunden Zonen, IP-Adressen, Rincon-ID's und Sonos Modell
/*********************************************************************************************************/

// Multicast IP-Adresse und Port for UPnP devices
$ip = '239.255.255.250';
$port = 1900;
	global $sonosplayer, $sonosnet, $devices;
	$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	$level = getprotobyname("ip");
	socket_set_option($sock, $level, IP_MULTICAST_TTL, 2);
	
		$data = <<<DATA
M-SEARCH * HTTP/1.1
HOST: {$ip}:reservedSSDPport
MAN: ssdp:discover
MX: 1
ST: urn:schemas-upnp-org:device:ZonePlayer:1
DATA;

	socket_sendto($sock, $data, strlen($data), null, $ip, $port);
	
	// All passed by ref
	$read = [$sock];
	$write = [];
	$except = [];
	$name = null;
	$port = null;
	$tmp = '';
	// Lese buffer
	$response = '';
	// Loop bis nichts mehr gefunden wird
	while (socket_select($read, $write, $except, 1)) {
		socket_recvfrom($sock, $tmp, 2048, null, $name, $port);
		$response .= $tmp;
	}
	trigger_error('Loop through network has been completed.', E_USER_NOTICE);
	// Parse buffer zu den Zonen
	$data = _parse_detection_replies($response);
	// create array
	$devices = [];
	foreach ($data as $datum) {
		if ($datum["st"] !== "urn:schemas-upnp-org:device:ZonePlayer:1") {
            continue;
        }
        if (in_array($datum["usn"], $unique)) {
            continue;
        }
		$url = parse_url($datum['location']);
		$devices[] = ($url['host']);
	}
	if (empty($devices)) {
		trigger_error(count($devices).' System has not detected any Sonos devices in your network!', E_USER_ERROR);
	} else {
		trigger_error(count($devices).' Sonos devices IP-adresses has been successful detected!', E_USER_NOTICE);	
	}
	// if no multicast addresses were detected run for broadcast addresses
	#if (empty($devices)) {
	#	$devices[] = broadcast_scan();
	#}
	require_once("PHPSonos.php");
	foreach ($devices as $scanzone) {
		$sonos = New PHPSonos($scanzone);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		usleep(200000); // warte 200ms
	}
	// get Zone Details based on scanned IPs 
	getSonosDevices($devices);
	// load configuration file
	parse_cfg_file();
	// check if player_nolb.cfg is empty
	if(empty($sonosnet)) {
		$finalzones = $sonosplayer;
	} else {
		// computes the difference of arrays with additional index check
		$finalzones = array_diff_assoc($sonosplayer, $sonosnet);
	}
	// write into file
	$fh = fopen('player_nolb.cfg', 'a+');
	foreach ($finalzones as $newzones => $value) {
		$tmp_values = implode(",",$value);
		$write = $newzones.'[]='.$tmp_values;
		fwrite($fh, $write."\r\n");
		#echo $write;
	}
	fclose($fh);
	if (!empty($write)) {
		echo "Die gefundenen Sonos Player wurden erfolgreich in der Datei /system/player_nolb.cfg gespeichert!<br><br>";
		echo "Bitte die Datei /system/player_nolb.cfg öffnen und die jeweilige Standardlautstärke<br>";
		echo "für T2S, Sonos und Sonos Maximal Volume aktualisieren.<br>";
	} else {
		echo "Es wurden keine neue Sonos Player gefunden.<br><br>";
	}
		

	
	

	function _parse_detection_replies($replies) {
		$out = array();
		// Loop durch jede Antwort
		foreach (explode("\r\n\r\n", $replies) as $reply) {
			if ( ! $reply) {
				continue;
			}
			// Neue Array
			$arr =& $out[];
			// Loop durch die Ergebnisse
			foreach (explode("\r\n", $reply) as $line) {
				// Ende von header name
				if (($colon = strpos($line, ':')) !== false) {
					$name = strtolower(substr($line, 0, $colon));
					$val = trim(substr($line, $colon + 1));
					$arr[$name] = $val;
				}
			}
		}
		return $out;
	}
	

/**
* Funktion: 	getSonosDevices --> Ermittelt die gesammte Sonos Topology
*
* @param:     output von getSonosDevicesIP.php
* @return:    Array<Key => Array<Node>>  
**/
 function getSonosDevices($devices){
	global $sonosplayer, $sodevices;
	
	$sodevices = $devices[0];
	$soplayer = getRoomCoordinatorSetup($sodevices);
	$zonen = array();
	foreach ($devices as $zoneip) {
		$url = "http://" . $zoneip . ":1400/xml/device_description.xml";
		$xml = simpleXML_load_file($url);
		$model = $xml->device->modelNumber;
		$roomraw = $xml->device->roomName;
		$device = $xml->device->displayName;
		# Ersetzen der Umlaute
		$search = array('Ä','ä','Ö','ö','Ü','ü','ß');
		$replace = array('Ae','ae','Oe','oe','Ue','ue','ss');
		# kleinschreibung
		$room = strtolower(str_replace($search,$replace,$roomraw));
		if(isSpeaker($model) == true) {
			$zonen = 	[$room, 
						(string)$device,
						'',
						'', 						
						''
						];
			$raum = array_shift($zonen);
		}
		$sonosplayer[$raum] = $zonen;
	}
	$sonosplayer = array_merge_recursive($soplayer, $sonosplayer);
	ksort($sonosplayer);
	#echo "<PRE>";
	#print_r($sonosplayer);
	return $sonosplayer;	
 }
  
  
/********************************************************************************************
/* Funktion : 	isSpeaker --> filtert die gefunden Sonos Devices nach Zonen
/* 				Subwoofer, Bridge und Dock werden nicht berücksichtigt
/*
/* @param: 	$model --> alle gefundenen Devices
/* @return: $models --> Sonos Zonen
/********************************************************************************************/
 function isSpeaker($model) {
    $models = [
        "S1"    =>  "PLAY:1",
		"S12"   =>  "PLAY:1",
		"S13"   =>  "PLAY:1",
        "S3"    =>  "PLAY:3",
        "S5"    =>  "PLAY:5",
        "S6"    =>  "PLAY:5",
        "S9"    =>  "PLAYBAR",
		"S11"   =>  "PLAYBASE",
        "ZP80"  =>  "ZONEPLAYER",
        "ZP90"  =>  "CONNECT",
        "ZP100" =>  "CONNECT:AMP",
        "ZP120" =>  "CONNECT:AMP",
        ];
    return in_array($model, array_keys($models));
}


/********************************************************************************************
/* Funktion : 	parse_cfg_file --> parsed die player.cfg in eine Array
/* 				Subwoofer, Bridge und Dock werden nicht berücksichtigt
/*
/* @return: $array --> gespeicherte Sonos Zonen
/********************************************************************************************/
function parse_cfg_file() {
	global $sonosnet;
	// Laden der Zonen Konfiguration aus player.cfg
	$tmp = parse_ini_file('player_nolb.cfg', true);
	$player = ($tmp['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	}
	return $sonosnet;
	}
	
	
/**
* Function: getRoomCoordinatorSetup --> identify the Coordinator for provided room (typically for StereoPair)
*
* @param:  $room
* @return: array of (0) IP address and (1) Rincon-ID of Master
*/

function getRoomCoordinatorSetup($devices){
	global $sonoszone, $zone, $debug, $master, $sonosclass, $config;
		
		#$room = $master;
		if(!$xml=deviceCmdRawSetup('/status/topology')){
			return false;
		}	
		$topology = simplexml_load_string($xml);
		$myself = null;
		$coordinators = [];
		// Loop players, build map of coordinators and find myself
		foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
			$player_data = $player->attributes();
			#echo '<PRE>';
			#print_r($player_data);
			$room = (string)$player;
			// replace german umlaute
			$search = array('Ä','ä','Ö','ö','Ü','ü','ß');
			$replace = array('Ae','ae','Oe','oe','Ue','ue','ss');
			$room = strtolower(str_replace($search,$replace,$room));
			$ip = parse_url((string)$player_data->location)['host'];
			if ($player_data->coordinator == 'true') {
				$player = array(
					$ip,
					'RINCON_'.explode('RINCON_',(string)$player_data->uuid)[1],
					);
				$coordinators[$room] = $player;
			}
		}
		ksort($coordinators);
		#echo "<PRE>";
		#print_r($coordinators);
		return $coordinators;
}


/**
* Funktion : deviceCmdRawSetup --> Subfunction necessary to read Sonos Topology
* @param: 	URL, IP-Adresse, port
*
* @return: data
**/

function deviceCmdRawSetup($url, $ip='', $port=1400) {
	global $sonoszone, $master, $zone, $sodevices;
			
	$url = "http://{$sodevices}:{$port}{$url}"; // ($sonoszone[$master][0])
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
 }
 
 
/**
* Funktion : broadcast_scan --> Subfunction necessary to read Sonos Topology
* @param: 	empty
*
* @return: array
**/ 

function broadcast_scan() {
 
 // Broadcast IP-Adresse und Port for UPnP devices
$ip = '239.255.255.250';
$broadcastip = '255.255.255.255';
$port = 1900;
	global $sonosplayer, $sonosnet;
	$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);

		$data = <<<DATA
M-SEARCH * HTTP/1.1
HOST: {$ip}:reservedSSDPport
MAN: ssdp:discover
MX: 1
ST: urn:schemas-upnp-org:device:ZonePlayer:1
DATA;

	socket_sendto($sock, $data, strlen($data), null, $broadcastip, $port);

	// All passed by ref
	$read = array($sock);
	$write = $except = array();
	$name = $port = null;
	$tmp = '';
	// Lese buffer
	$buff = '';
	// Loop bis nichts mehr gefunden wird
	while (socket_select($read, $write, $except, 1) && $read) {
		socket_recvfrom($sock, $tmp, 2048, null, $name, $port);
		$buff .= $tmp;
	}
	// Parse buffer zu den Zonen
	$data = _parse_detection_replies($buff);
	// create array
	$devices = array();
	foreach ($data as $datum) {
		$url = parse_url($datum['location']);
		$devices[] = ($url['host']);
	}
	return $devices;
}
 



?>