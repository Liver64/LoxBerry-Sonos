<?php
/**
* Funktion : ermittelt automatisch die IP Adressen der sich im Netzwerk befindlichen Sonos Komponenten
* @param: 	$ip = Multicast Adresse
*			$port = Port
*
* @return: Array mit allen gefunden Zonen, IP-Adressen, Rincon-ID's und Sonos Modell
**/

header('Content-Type: text/html; charset=utf-8');

#echo "<PRE>";

require_once "sonosAccess.php";
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "logging.php";
require_once "error.php";

register_shutdown_function('shutdown');

$lb_hostname = lbhostname();
$lb_version = LBSystem::lbversion();
$L = LBSystem::readlanguage("sonos.ini");
#$pluginversion = LBSystem::pluginversion();
$pluginversion_temp = LBSystem::plugindata();
$pluginversion = $pluginversion_temp['PLUGINDB_VERSION'];
$POnline = "/run/shm/s4lox_sonoszone.json";	
$home = $lbhomedir;
$folder = $lbpplugindir;
$myIP = $_SERVER["SERVER_ADDR"];

error_reporting(E_ALL);
ini_set("display_errors", "off");
define('ERROR_LOG_FILE', "$lbplogdir/sonos.log");

//calling custom error handler
set_error_handler("handleError");

// Test error handler
#print_r($arra); // undefined variable

$params = [	"name" => "Sonos",
			"filename" => "$lbplogdir/sonos.log",
			"append" => 1,
			"addtime" => 1,
			];
$log = LBLog::newLog($params);
#LOGSTART("Scan started");

$plugindata = LBSystem::plugindata();

	$ip = '239.255.255.250';
	$port = 1900;
	
	LOGDEB('Start scanning for Sonos Players using MULTICAST IP: '.$ip.':'.$port);
		
	global $sonosfinal, $sonosnet, $devices, $POnline;

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
	#print_r($response);
	#$search = "urn:schemas-upnp-org:device:ZonePlayer:1";

    $devices = [];
    foreach (explode("\r\n\r\n", $response) as $reply) {
		if (!$reply) {
            continue;
        }
		#var_dump(strpos($reply, $search));
		# Only attempt to parse responses from Sonos speakers
        if (@strpos($reply, $search) === false) {
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
	#print_r($devices);
	#unset($devices);				// für BROADCAST testzwecke
    if (empty($devices)) {
		LOGWARN('system/network.php: System has not detected any Sonos devices by scanning MULTICAST in your network!');
		// if no multicast addresses were detected run for broadcast addresses
		broadcast_scan($devices);
	} else {
		LOGINF('system/network.php: IP-adresses from Sonos devices has been successful detected by MULTICAST!');
	}
	# exclude RF of Stereo Pair
	$devicecheck = [];
	foreach ($devices as $newzoneip) {
		$sonos = new SonosAccess($newzoneip);
		$zone_details = $sonos->GetZoneGroupAttributes();
		if (!empty($zone_details['CurrentZonePlayerUUIDsInGroup']))  {
			array_push($devicecheck, $newzoneip);
		} else {
			LOGGING("system/network.php: IP-address '". $newzoneip. "' seems to be a part of a Stereopair/Surround setup and has not been added.",6);
		}
	}
	//print_r($devicecheck);
	$devices = $devicecheck;	
	getSonosDevices($devices);
	$devicelist = implode(", ", $devices);
	LOGGING("system/network.php: Following Sonos IP-addresses has been detected: ". $devicelist,5);
	
	parse_cfg_file(); // $sonosnet
	#print_r($sonosnet);
	if(empty($sonosnet)) {
		$finalzones = $sonosfinal;
		$count_player = count($finalzones);
		foreach ($finalzones as $found_zones => $key)  {
			LOGINF("system/network.php: New Sonos Player: '".$key[2]."' called: '".$found_zones."' using IP: '".$key[0]."' and Rincon-ID: '".$key[1]."' will be added to your Plugin.");
		}
	} else {
		// computes the difference of arrays with additional index check
		$finalzones = @array_diff_assoc($sonosfinal, $sonosnet);
		if (empty($finalzones))  {
			LOGINF("system/network.php: No new Sonos Player has been detected.");
		} else {
			foreach ($finalzones as $found_zones => $key)  {
				LOGOK("system/network.php: New Sonos Player: '".$key[2]."' called: '".$found_zones."' using IP: '".$key[0]."' and Rincon-ID: '".$key[1]."' will be added to your Plugin.");
			}
		}
	}
	LOGINF("The initial setup has been completed.",7);
	#print_r($finalzones);
	
	// convert array to JSON
	#$post_json = array2json($finalzones);
	$post_json = json_encode($finalzones);
	
	if (!empty($finalzones)) {
		LOGINF("system/network.php: New Players has been detected and data converted to JSON");
		LOGOK("system/network.php: JSON data has been successfully passed to application");
	} 
	echo $post_json;
		


	
	

/**
* Funktion: 	getSonosDevices --> Ermittelt die Sonos Topology
*
* @param:     discovered devices
* @return:    Array  
**/
 function getSonosDevices($devices){
	 
	global $sonosfinal, $sodevices;
	
	# http://<IP>:1400/xml/device_description.xml
	# http://<IP>:1400/info
	
	
	$sodevices = $devices[0];
	#$soplayer = getRoomCoordinator($sodevices);
	#$soplayernew = getRoomCoordinatorSetup($sodevices);
	$zonen = array();
	foreach ($devices as $zoneip) {
		$info = json_decode(file_get_contents('http://' . $zoneip . ':1400/info'), true);
		$model = $info['device']['model'];
		$roomraw = $info['device']['name'];
		$device = $info['device']['modelDisplayName'];		
		$rinconid = $info['device']['id'];	
		# Ersetzen der Umlaute
		$search = array('Ä','ä','Ö','ö','Ü','ü','ß');
		$replace = array('Ae','ae','Oe','oe','Ue','ue','ss');
		# kleinschreibung
		$room = strtolower(str_replace($search,$replace,$roomraw));
		$groupId = $info['groupId'];
		$householdId = $info['householdId'];
		$deviceId = $info['device']['serialNumber'];
		if(isSpeaker($model) == true) {
			$zonen = 	[$room, 
						$zoneip,
						(string)$rinconid,
						(string)strtoupper($device),
						'',
						'', 
						'',
						'',
						(string)$model,
						(string)$groupId,
						(string)$householdId,
						(string)$deviceId						
						];
			$raum = array_shift($zonen);
			
		} else {
			LOGGING("system/network.php: Sorry, the Sonos model '".$model."' for room '".$room."' has been found, but this model is currently not supported by the Plugin!",3);
			LOGGING("system/network.php: Please send a mail to 'olewald64gmail.com' or place a post in Loxone Forum in order to get model added to the Plugin, Thanks in advance.",3);
			continue;
		}
		$sonosplayerfinal[$raum] = $zonen;
	}
	#print_r($sonosplayerfinal);
	#$match = @array_intersect_assoc($soplayernew, $sonosplayerfinal);
	#$sonosfinal = @array_merge_recursive($match, $sonosplayerfinal);
	ksort($sonosplayerfinal);
	$sonosfinal = $sonosplayerfinal;
	#$rooms = implode(", ", array_keys($sonosfinal));
	#$countroom = count(array_keys($sonosfinal));
	#LOGGING("system/network.php: Following ".$countroom." rooms has been detected: ".$rooms,5);
	return $sonosfinal;	
	#return $sonosplayerfinal;
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
            "S3"    =>  "PLAY:3",
            "S5"    =>  "PLAY:5",
            "S6"    =>  "PLAY:5",
			"S24"   =>  "PLAY:5",
            "S9"    =>  "PLAYBAR",
            "S11"   =>  "PLAYBASE",
            "S13"   =>  "ONE",
			"S18"   =>  "ONE",
            "S14"   =>  "BEAM",
			"S31"   =>  "BEAM",
			"S15"   =>  "CONNECT",
			"S17"   =>  "MOVE",
			"S19"   =>  "ARC",
			"S20"   =>  "SYMFONISK LAMP",
			"S21"   =>  "SYMFONISK WALL",
			"S33"   =>  "SYMFONISK",
			"S22"   =>  "ONE SL",
			"S38"   =>  "ONE SL",
			"S23"   =>  "PORT",
			"S27"   =>  "ROAM",
			"S35"   =>  "ROAM SL",
			"S29"   =>  "SYMFONISK FRAME",
            "ZP80"  =>  "ZONEPLAYER",
			"ZP90"  =>  "CONNECT",
			"S16"  	=>  "CONNECT:AMP",
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
	LOGOK("system/network.php: Existing configuration file 'player.cfg' has been loaded successfully.");
	return $sonosnet;
	}


/**        NOT IN USE ANYMORE
* Funktion : 	array2json --> konvertiert array in JSON Format
* http://www.bin-co.com/php/scripts/array2json/
* 
* @return: JSON string
**/

function array2json($arr) { 
    if(function_exists('json_encode')) return json_encode($arr); // Lastest versions of PHP already has this functionality.
    $parts = array(); 
    $is_list = false; 

    // Find out if the given array is a numerical array 
    $keys = array_keys($arr); 
    $max_length = count($arr)-1; 
    if(($keys[0] == 0) and ($keys[$max_length] == $max_length)) {// See if the first key is 0 and last key is length - 1 
        $is_list = true; 
        for($i=0; $i<count($keys); $i++) { // See if each key correspondes to its position 
            if($i != $keys[$i]) { // A key fails at position check. 
                $is_list = false; // It is an associative array. 
                break; 
            } 
        } 
    } 

    foreach($arr as $key=>$value) { 
        if(is_array($value)) { // Custom handling for arrays 
            if($is_list) $parts[] = array2json($value); /* :RECURSION: */ 
            else $parts[] = '"' . $key . '":' . array2json($value); /* :RECURSION: */ 
        } else { 
            $str = ''; 
            if(!$is_list) $str = '"' . $key . '":'; 

            // Custom handling for multiple data types 
            if(is_numeric($value)) $str .= $value; // Numbers 
            elseif($value === false) $str .= 'false'; // The booleans 
            elseif($value === true) $str .= 'true'; 
            else $str .= '"' . addslashes($value) . '"'; // All other things 
            $parts[] = $str; 
        } 
    } 
    $json = implode(',',$parts); 
     
    if($is_list) return '[' . $json . ']';// Return numerical JSON 
    return '{' . $json . '}'; // Return associative JSON 
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
		#print_r($coordinators);
		LOGDEB("system/network.php: All player details has been collected (room, IP, Rincon-ID and Model).");
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
		print_r($playernew);
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
 
 
/**
* Funktion : broadcast_scan --> Subfunction necessary to read Sonos Topology
* @param: 	empty
*
* @return: array
**/ 
function broadcast_scan($devices) {
	
	#$ip = '239.255.255.250';
	$broadcastip = '255.255.255.255';
	$port = 1900;
	
	global $sonosfinal, $sonosnet, $devices;
	
	LOGINF('Start scanning for Sonos Players using BROADCAST IP: '.$broadcastip.':'.$port);

	$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	$level = getprotobyname("broadcastip");
	socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
		
	$data = "M-SEARCH * HTTP/1.1\r\n";
	$data .= "HOST: {broadcastip}:reservedSSDPport\r\n";
	$data .= "MAN: ssdp:discover\r\n";
	$data .= "MX: 1\r\n";
	$data .= "ST: urn:schemas-upnp-org:device:ZonePlayer:1\r\n";

	socket_sendto($sock, $data, strlen($data), null, $broadcastip, $port);
	
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
		LOGWARN('system/network.php: System has not detected any Sonos devices by scanning BROADCAST in your network!');
		exit;
	} else {
		LOGINF('system/network.php: IP-adresses from Sonos devices has been successful detected by BROADCAST.');
	}
	return $devices;
}

function shutdown()
{
	global $log;
	#$log->LOGEND("Scan finished");
	#LOGEND("PHP finished");
	
}


?>