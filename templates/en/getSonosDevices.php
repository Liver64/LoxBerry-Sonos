<?php
/********************************************************************************************
/* Funktion : getdevices --> ermittelt die IP Adressen der im Netz befindlichen Sonos Zonen
/* @param: 	$ip = Multicast Adresse
/*			$port = Zum scannen
/*
/* @return: array mit IP Adressen
/********************************************************************************************/

// Multicast Adresse und Port
$ip = '239.255.255.250';
$port = 1900;
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
		// Erstelle Array
		$devices = array();
		foreach ($data as $datum) {
			$url = parse_url($datum['location']);
			$devices[] = ($url['host']);
		}
		echo "<pre>";
		#print_r($devices);
		getSonosDevices($devices);
		#return $devices;
	

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
 /* @param:     output von getSonosDevicesIP.php
 /*
 /* @return:    Array<Key => Array<Node>>  
 /****************************************************************************/
 
function getSonosDevices($devices){
		if(!$xml=deviceCmdRaw('/status/topology')){
			return false;
		}	
		$topology = simplexml_load_string($xml);
		$myself = null;
		$coordinators = [];
		// Loop players, build map of coordinators and find myself
		foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
			$player_data = $player->attributes();
			#$name=utf8_decode((string)$player);
			$name=(string)$player;
			// Ersetzen der deutschen Sonderzeichen
			$search = array('ä','ö','ü','ß','Ä','Ü','Ö');
			$replace = array('ae','oe','ue','ss','Ae','Oe','Ue');
			$name = strtolower(str_replace($search,$replace,$name));
			$ip = parse_url((string)$player_data->location)['host'];
			$player = array(
				#'Zone' =>utf8_encode($name),
				'IP-Adresse' =>"$ip",
				'Rincon-ID' =>'RINCON_'.explode('RINCON_',(string)$player_data->uuid)[1]
			);
			$coordinators[$name] = $player; // Zeile 100 auskommentieren
			#$coordinators[] = $player;
		}
	
	print_r($coordinators);
	return $coordinators;
}
	
	
 function deviceCmdRaw($url, $ip='', $port=1400) {
	global $devices;
		
	$url = "http://{$devices[0]}:{$port}{$url}";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
 }
?>