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
	global $sonosplayer, $sonosnet;
	$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	socket_set_option($sock, getprotobyname('ip'), IP_MULTICAST_TTL, 2);

		$data = <<<DATA
M-SEARCH * HTTP/1.1
HOST: {$ip}:reservedSSDPport
MAN: ssdp:discover
MX: 1
ST: urn:schemas-upnp-org:device:ZonePlayer:1
DATA;

	socket_sendto($sock, $data, strlen($data), null, $ip, $port);

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
	// based on scanned IPs retrieve Zone Details
	getSonosDevices($devices);
	// load configuration file
	parse_cfg_file();
	// prüft ob player.cfg leer war
	if(empty($sonosnet)) {
		$finalzones = $sonosplayer;
	} else {
		// computes the difference of arrays with additional index check
		$finalzones = array_diff_assoc($sonosplayer, $sonosnet);
	}
	// save array as JSON file
	$d = array2json($finalzones);
	$fh = fopen('/opt/loxberry/config/plugins/sonos4lox/tmp_player.json', 'w');
	fwrite($fh, json_encode($finalzones));
	fclose($fh);
	
	

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
	

/******************************************************************************
/* Funktion: 	getSonosDevices --> Ermittelt die gesammte Sonos Topology
/*
/* @param:     output von getSonosDevicesIP.php
/* @return:    Array<Key => Array<Node>>  
/******************************************************************************/
 function getSonosDevices($devices){
	global $sonosplayer;
	
	$zonen = array();
	foreach ($devices as $zoneip) {
		$url = "http://" . $zoneip . ":1400/xml/device_description.xml";
		$xml = simpleXML_load_file($url);
		$ipadr = $xml->device->friendlyName;
		$rinconid = $xml->device->UDN;
		$model = $xml->device->modelNumber;
		$roomraw = $xml->device->roomName;
		$device = $xml->device->displayName;
		# Ersetzen der Umlaute
		$search = array('Ä','ä','Ö','ö','Ü','ü','ß');
		$replace = array('Ae','ae','Oe','oe','Ue','ue','ss');
		# kleinschreibung
		$room = strtolower(str_replace($search,$replace,$roomraw));
		if(isSpeaker($model) == true) {
			$room = strtolower(str_replace($search,$replace,$roomraw));
			$zonen = 	[$room, 
						substr($ipadr, 0, strpos($ipadr,' ')),
						substr($rinconid, 5, 50),
						(string)$device,
						'',
						'', 						
						''
						];
			$raum = array_shift($zonen);
		}
		$sonosplayer[$raum] = $zonen;
	}
	echo "<pre>";
	print_r($sonosplayer);
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
		"S12"    =>  "PLAY:1",
        "S3"    =>  "PLAY:3",
        "S5"    =>  "PLAY:5",
        "S6"    =>  "PLAY:5",
        "S9"    =>  "PLAYBAR",
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
	#if (!file_exists('/opt/loxberry/config/plugins/sonos4lox/player.cfg')) {
	#	trigger_error("Die Datei /opt/loxberry/config/plugins/sonos4lox/player.cfg ist nicht vorhanden. Bitte zuerst die Zonen auf der Config Seite anlegen lassen!", E_USER_NOTICE);
	#} else {
	// Laden der Zonen Konfiguration aus player.cfg
	$tmp = parse_ini_file('/opt/loxberry/config/plugins/sonos4lox/player.cfg', true);
	$player = ($tmp['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	}
	return $sonosnet;
	}


/********************************************************************************************
/* Funktion : 	array2json --> konvertiert array in JSON Format
/* http://www.bin-co.com/php/scripts/array2json/
/* 
/* @return: JSON string
/********************************************************************************************/
function array2json($arr) { 
    if(function_exists('json_encode')) return json_encode($arr); //Lastest versions of PHP already has this functionality.
    $parts = array(); 
    $is_list = false; 

    //Find out if the given array is a numerical array 
    $keys = array_keys($arr); 
    $max_length = count($arr)-1; 
    if(($keys[0] == 0) and ($keys[$max_length] == $max_length)) {//See if the first key is 0 and last key is length - 1 
        $is_list = true; 
        for($i=0; $i<count($keys); $i++) { //See if each key correspondes to its position 
            if($i != $keys[$i]) { //A key fails at position check. 
                $is_list = false; //It is an associative array. 
                break; 
            } 
        } 
    } 

    foreach($arr as $key=>$value) { 
        if(is_array($value)) { //Custom handling for arrays 
            if($is_list) $parts[] = array2json($value); /* :RECURSION: */ 
            else $parts[] = '"' . $key . '":' . array2json($value); /* :RECURSION: */ 
        } else { 
            $str = ''; 
            if(!$is_list) $str = '"' . $key . '":'; 

            //Custom handling for multiple data types 
            if(is_numeric($value)) $str .= $value; //Numbers 
            elseif($value === false) $str .= 'false'; //The booleans 
            elseif($value === true) $str .= 'true'; 
            else $str .= '"' . addslashes($value) . '"'; //All other things 
            // :TODO: Is there any more datatype we should be in the lookout for? (Object?) 

            $parts[] = $str; 
        } 
    } 
    $json = implode(',',$parts); 
     
    if($is_list) return '[' . $json . ']';//Return numerical JSON 
    return '{' . $json . '}';//Return associative JSON 
} 


?>