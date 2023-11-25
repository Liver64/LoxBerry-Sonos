<?php
/**
* Function: Discover all Sonos Components in your network
* @param: 	$ip = IP Address
*			$port = Port
*
* @return:  array
**/

#echo "<PRE>";

	require_once "sonosAccess.php";
	require_once "loxberry_system.php";
	require_once "loxberry_log.php";
	require_once "logging.php";
	require_once "error.php";
	require_once $lbphtmldir."/Helper.php";

	register_shutdown_function('shutdown');

	$home = $lbhomedir;

	error_reporting(E_ALL);
	ini_set("display_errors", "off");
	define('ERROR_LOG_FILE', "$lbplogdir/sonos.log");

	//calling custom error handler
	set_error_handler("handleError");

	$params = [	"name" => "Sonos",
				"filename" => "$lbplogdir/sonos.log",
				"append" => 1,
				"addtime" => 1,
				];
	$log = LBLog::newLog($params);

	$ip = '239.255.255.250';
	$port = 1900;
	
	LOGDEB('Start scanning for Sonos Players using MULTICAST IP: '.$ip.':'.$port);
		
	global $sonosfinal, $sonosnet, $devices;

	$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	$level = getprotobyname("ip");
	socket_set_option($sock, $level, IP_MULTICAST_TTL, 2);
	
		
	$data = "M-SEARCH * HTTP/1.1\r\n";
	$data .= "HOST: {$ip}:reservedSSDPport\r\n";
	$data .= "MAN: ssdp:discover\r\n";
	$data .= "MX: 1\r\n";
	$data .= "ST: urn:schemas-upnp-org:device:ZonePlayer:1\r\n";

	socket_sendto($sock, $data, strlen($data), 0, $ip, $port);
	
	# All passed by ref
	$read = [$sock];
    $write = [];
    $except = [];
    $name = null;
    $port = null;
    $tmp = "";
	
    $response = "";
    while (socket_select($read, $write, $except, 1)) {
        socket_recvfrom($sock, $tmp, 2048, 0, $name, $port);
        $response .= $tmp;
    }
    $devices = [];
    foreach (explode("\r\n\r\n", $response) as $reply) {
		if (!$reply) {
            continue;
        }
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
    if (empty($devices)) {
		LOGWARN('system/network.php: System has not detected any Sonos devices by scanning MULTICAST in your network!');
		# if no multicast addresses were detected run for broadcast addresses
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
	#print_r($devicecheck);
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
		# computes the difference of arrays with additional index check
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
	
	# convert array to JSON
	$post_json = json_encode($finalzones);
	
	if (!empty($finalzones)) {
		LOGINF("system/network.php: New Players has been detected and data converted to JSON");
		LOGOK("system/network.php: JSON data has been successfully passed to application");
	} 
	echo $post_json;
		


/**
* Function: 	getSonosDevices --> collect need details for setting up base config
*
* @param:     discovered devices
* @return:    Array  
**/
 function getSonosDevices($devices){
	 
	global $sonosfinal, $sodevices, $lbphtmldir;
	
	# http://192.168.50.65:1400/xml/device_description.xml
	# http://<IP>:1400/info
	
	$sodevices = $devices[0];
	$zonen = array();
	
	foreach ($devices as $zoneip) {
		$info = json_decode(file_get_contents('http://' . $zoneip . ':1400/info'), true);
		$model = $info['device']['model'];
		$roomraw = $info['device']['name'];
		$device = $info['device']['modelDisplayName'];		
		$rinconid = $info['device']['id'];	
		$search = array('Ä','ä','Ö','ö','Ü','ü','ß');
		$replace = array('Ae','ae','Oe','oe','Ue','ue','ss');
		$room = strtolower(str_replace($search,$replace,$roomraw));
		$groupId = $info['groupId'];
		$householdId = $info['householdId'];
		$deviceId = $info['device']['serialNumber'];
		$zonen = 		[$room, 
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
		# Check if Soundbar has been detected
		if(isSoundbar($model) == true) {
			array_push($zonen, "SB");
			LOGINF("system/network.php: Player '".$room."' (".(string)strtoupper($device).") has been identified as Soundbar.");
		}
		$raum = array_shift($zonen);
		$sonosplayerfinal[$raum] = $zonen;
		# Get Player icons and Save them for UI
		$url = 'http://'.$zoneip.':1400/img/icon-'.$model.'.png';
		$img = $lbphtmldir.'/images/icon-'.$model.'.png';
		if (!file_exists($img)) {
			file_put_contents($img, file_get_contents($url));
		}
	}
	if (count($sonosplayerfinal) === 0)   {
		LOGERR("system/network.php: Something went wrong... Device(s) has been found but could not be added to your system! We skip here...");
		return false;
	}
	#print_r($sonosplayerfinal);
	if (isset($sonosplayerfinal) && is_array($sonosplayerfinal) && count($sonosplayerfinal) > 0) {
		try {			
			ksort($sonosplayerfinal);
		} catch (Exception $e) {
			LOGERR("system/network.php: Array of devices could not be re-indexed! We skip");
			return false;
		}
	} else {
		LOGERR("system/network.php: Something during searching for new devices went wrong! We skip");
		return false;
	}
	$sonosfinal = $sonosplayerfinal;
	return $sonosfinal;	
}


/**
* Function : 	parse_cfg_file --> parsed existing player.cfg in an array
*
* @return: 		$array --> saved Sonos Zonen
**/

function parse_cfg_file() {
	global $sonosnet, $home, $lbpplugindir;
	# Load Player from existing config
	$tmp = parse_ini_file($home.'/config/plugins/'.$lbpplugindir.'/player.cfg', true);
	$player = ($tmp['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	}
	LOGOK("system/network.php: Existing configuration file 'player.cfg' has been loaded successfully.");
	return $sonosnet;
	}


/**
* Function : broadcast_scan --> Subfunction necessary to read Sonos Topology
* @param: 	empty
*
* @return: array
**/ 
function broadcast_scan($devices) {
	
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

	socket_sendto($sock, $data, strlen($data), 0, $broadcastip, $port);
	
	# All passed by ref
	$read = [$sock];
    $write = [];
    $except = [];
    $name = null;
    $port = null;
    $tmp = "";
    $response = "";
    while (socket_select($read, $write, $except, 1)) {
        socket_recvfrom($sock, $tmp, 2048, 0, $name, $port);
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
	
}


?>