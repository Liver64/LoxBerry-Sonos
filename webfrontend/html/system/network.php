<?php
/**
* Funktion : ermittelt automatisch die IP Adressen der sich im Netzwerk befindlichen Sonos Komponenten
* @param: 	$ip = Multicast Adresse
*			$port = Port
*
* @return: Array mit allen gefunden Zonen, IP-Adressen, Rincon-ID's und Sonos Modell
**/

// ermittelt das home Verzeichnis für Loxberry
if (!function_exists('posix_getpwuid')) {
	$home = @getenv('DOCUMENT_ROOT');
} else {
	$home = posix_getpwuid(posix_getuid());
	$home = $home['dir'];
}

// ermittelt das Plugin Verzeichnis für Loxberry
$path = $_SERVER["SCRIPT_FILENAME"];
$folder_array = explode('/', $path);
$folder =($folder_array[6]);

echo "<PRE>"; 
trigger_error('The home and plugin folder has been successful detected!', E_USER_NOTICE);

#unlink($home.'/config/plugins/'.$folder.'/tmp_player.json');

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
	// ** OBSOLETE **
	// if no multicast addresses were detected run for broadcast addresses
	#if (empty($devices)) {
	#	$devices[] = broadcast_scan();
	#}
	//**
	// in case there are groups destroy them
	require_once("PHPSonos.php");
	foreach ($devices as $scanzone) {
		$sonos = New PHPSonos($scanzone);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		usleep(200000); // warte 200ms
	}
	// based on scanned IPs retrieve Zone Details
	getSonosDevices($devices);
	$devicelist = implode(" / ", $devices);
	trigger_error("Following Sonos Player IP addresses has been detected (exclude Boost, Subwoofer, Bridges) :". $devicelist, E_USER_NOTICE);
	// load configuration file
	parse_cfg_file();
	// prüft ob player.cfg leer war
	if(empty($sonosnet)) {
		$finalzones = $sonosplayer;
	} else {
		// computes the difference of arrays with additional index check
		$finalzones = @array_diff_assoc($sonosplayer, $sonosnet);
	}
	// save array as JSON file
	$d = array2json($finalzones);
	$fh = fopen($home.'/config/plugins/'.$folder.'/tmp_player.json', 'w');
	$checkInst = file_exists($home.'/config/plugins/'.$folder.'/tmp_player.json');
	if ($checkInst === false) {
		trigger_error("Error during installation, please re-install the Plugin. The Scan could not be completed due to missing temporary file!!", E_USER_ERROR);
	}
	trigger_error('The setup has been completed.', E_USER_NOTICE);
	fwrite($fh, json_encode($finalzones));
	fclose($fh);
	trigger_error('System setup passed over to LoxBerry Configuration.', E_USER_NOTICE);
	#unlink($home.'/config/plugins/sonos4lox_dev/tmp_player.json');
	
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
	echo "<PRE>";
	#print_r($sonosplayer);
	return $sonosplayer;	
 }

  
  
/**
* Funktion : 	isSpeaker --> filtert die gefunden Sonos Devices nach Zonen
* 				Subwoofer, Bridge und Dock werden nicht berücksichtigt
*
* @param: 	$model --> alle gefundenen Devices
* @return: $models --> Sonos Zonen
**/
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
        "ZP80"  =>  "CONNECT",
        "ZP90"  =>  "CONNECT",
        "ZP100" =>  "CONNECT:AMP",
        "ZP120" =>  "CONNECT:AMP",
        ];
    return in_array($model, array_keys($models));
}


/**
* Funktion : 	parse_cfg_file --> parsed die player.cfg in eine Array
* 				Subwoofer, Bridge und Dock werden nicht berücksichtigt
*
* @return: $array --> gespeicherte Sonos Zonen
**/
function parse_cfg_file() {
	global $sonosnet, $home, $folder;
	// Laden der Zonen Konfiguration aus player.cfg
	$tmp = parse_ini_file($home.'/config/plugins/'.$folder.'/player.cfg', true);
	$player = ($tmp['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	}
	trigger_error('Existing configuration file player.cfg has been loaded successfully.', E_USER_NOTICE);
	return $sonosnet;
	}


/**
* Funktion : 	array2json --> konvertiert array in JSON Format
* http://www.bin-co.com/php/scripts/array2json/
* 
* @return: JSON string
**/

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
		trigger_error('All player details and Stereo Pairs has been collected (room, IP, Rincon-ID and Model).', E_USER_NOTICE);
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
 
// ** OBSOLETE ** 
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