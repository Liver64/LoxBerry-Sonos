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
	
	global $sonosfinal, $sonosnet, $devices;

	$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	$level = getprotobyname("ip");
	socket_set_option($sock, $level, IP_MULTICAST_TTL, 2);
		
	$data = "M-SEARCH * HTTP/1.1\r\n";
	$data .= "HOST: {$ip}:reservedSSDPport\r\n";
	$data .= "MAN: ssdp:discover\r\n";
	$data .= "MX: 1\r\n";
	$data .= "ST: urn:schemas-upnp-org:device:ZonePlayer:1\r\n";

	socket_sendto($sock, $data, strlen($data), null, $ip, $port);
	
	// All passed by ref
	$read = [$sock];
    $write = [];
    $except = [];
    $name = null;
    $port = null;
    $tmp = "";
    $response = "";
    while (socket_select($read, $write, $except, 1)) {
        socket_recvfrom($sock, $tmp, 2048, null, $name, $port);
        $response .= $tmp;
    }
    $devices = [];
    foreach (explode("\r\n\r\n", $response) as $reply) {
        if (!$reply) {
            continue;
        }
        $data = [];
        foreach (explode("\r\n", $reply) as $line) {
            if (!$pos = strpos($line, ":")) {
                continue;
            }
            $key = strtolower(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            $data[$key] = $val;
        }
        $devices[] = $data;
    }
    $return = [];
    $unique = [];
    foreach ($devices as $device) {
        if ($device["st"] !== "urn:schemas-upnp-org:device:ZonePlayer:1") {
            continue;
        }
        if (in_array($device["usn"], $unique)) {
            continue;
        }
        $url = parse_url($device["location"]);
        $ip = $url["host"];
        $return[] = $ip;
        $unique[] = $device["usn"];
    }
	$devices = $return;
	if (empty($devices)) {
		trigger_error(count($devices).' System has not detected any Sonos devices in your network!', E_USER_ERROR);
	} else {
		trigger_error(count($devices).' Sonos devices IP-adresses has been successful detected!', E_USER_NOTICE);	
	}
	require_once("PHPSonos.php");
	foreach ($devices as $scanzone) {
		try {
			$sonos = New PHPSonos($scanzone);
			$sonos->BecomeCoordinatorOfStandaloneGroup();
			#usleep(100000); // warte 200ms
		} catch (Exception $e) {
			trigger_error("Minimum One Stereo pair or a Surround Config has been identified!", E_USER_NOTICE);	
		}
	}
	// get Zone Details based on scanned IPs 
	getSonosDevices($devices);
	$devicelist = implode(" / ", $devices);
	trigger_error("Following Sonos Player IP addresses has been detected (exclude Boost, Subwoofer, Bridges) :". $devicelist, E_USER_NOTICE);
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
	global $sonosfinal, $sodevices;
	
	$sodevices = $devices[0];
	$soplayer = getRoomCoordinator($sodevices);
	$soplayernew = getRoomCoordinatorSetup($sodevices);
	$zonen = array();
	foreach ($soplayer as $zoneip) {
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
		$sonosplayerfinal[$raum] = $zonen;
	}
	$match = @array_intersect_assoc($soplayernew, $sonosplayerfinal);
	$sonosfinal = @array_merge_recursive($match, $sonosplayerfinal);
	ksort($sonosfinal);
	#echo "<PRE>";
	#print_r($sonosfinal);
	return $sonosfinal;	
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
		trigger_error('All player details has been collected (room, IP, Rincon-ID and Model).', E_USER_NOTICE);
		return $coordinators;
}


/**
* Function: getRoomCoordinator --> filter for Master IP addresses
*
* @param:  $room
* @return: array of (0) IP address
*/

function getRoomCoordinator($devices){
	global $sonoszone, $zone, $debug, $master, $sonosclass, $config;
		
		#$room = $master;
		if(!$xml=deviceCmdRawSetup('/status/topology')){
			return false;
		}	
		$topology = simplexml_load_string($xml);
		$myself = null;
		$playernew = [];
		// Loop players, build map of coordinators and find myself
		foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
			$player_data = $player->attributes();
			$ip = parse_url((string)$player_data->location)['host'];
			if ($player_data->coordinator == 'true') {
				array_push($playernew, $ip);
			}
		}
		return $playernew;
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
 
 




?>