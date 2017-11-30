<?php
# helper.php

/**
* Function: getPlayerList --> generates Sonos Topology
*
* @param:  empty
* @return: array(	Rincon-ID
*					Group-ID,
*					Coordinator
*					IP-Adresse  )
**/
	
function getPlayerList(){
	global $sonoszone;
		
	if(!$xml=deviceCmdRaw('/status/topology')){
		return false;
	}	
	$topology = simplexml_load_string($xml);
	$myself = null;
	$coordinators = [];
	// Loop players, build map of coordinators and find myself
	foreach ($topology->ZonePlayers->ZonePlayer as $player)	{
		$player_data = $player->attributes();
		$name=utf8_decode((string)$player);
		$group=(string)$player_data->group[0];
		$ip = parse_url((string)$player_data->location)['host'];
		$port = parse_url((string)$player_data->location)['port'];
		$zonename = recursive_array_search($ip,$sonoszone);
		$player = array(
			'Host' =>"$ip",
			'Sonos Name' =>utf8_encode($zonename),
			'Master' =>((string)$player_data->coordinator == 'true'),
			#'Group-ID' => $group,
			'Rincon' =>'RINCON_'.explode('RINCON_',(string)$player_data->uuid)[1]
		);
		$sonostopology[$group][] = $player;
	}
	print_r($sonostopology);
	return($sonostopology);
}
	
	

/**
* Function : objectToArray --> konvertiert ein Object (Class) in eine Array.
* https://www.if-not-true-then-false.com/2009/php-tip-convert-stdclass-object-to-multidimensional-array-and-convert-multidimensional-array-to-stdclass-object/
*
* @param: 	Object (Class)
* @return: array
**/

 function objectToArray($d) {
    if (is_object($d)) {
        // Gets the properties of the given object
        // with get_object_vars function
        $d = get_object_vars($d);
    }
	if (is_array($d)) {
        /*
        * Return array converted to object
        * Using __FUNCTION__ (Magic constant)
        * for recursive call
        */
        return array_map(__FUNCTION__, $d);
    } else {
        // Return array
        return $d;
    }
}

	
/**
* Function : get_file_content --> übermittelt die Titel/Interpret Info an Loxone
* http://stackoverflow.com/questions/697472/php-file-get-contents-returns-failed-to-open-stream-http-request-failed
*
* @param: 	URL = virtueller Texteingangsverbinder
* @return: string (Titel/Interpret Info)
**/

function get_file_content($url) {
	
	$curl_handle=curl_init();
	curl_setopt($curl_handle, CURLOPT_URL,$url);
	curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_handle, CURLOPT_USERAGENT, 'LOXONE');
	$query = curl_exec($curl_handle);
	curl_close($curl_handle);
}


/**
* Function : recursive_array_search --> durchsucht eine Array nach einem Wert und gibt 
* den dazugehörigen key zurück
* @param: 	$needle = Wert der gesucht werden soll
*			$haystack = Array die durchsucht werden soll
*
* @return: $key
**/

function recursive_array_search($needle,$haystack) {
    foreach($haystack as $key=>$value) {
        $current_key=$key;
        if($needle===$value OR (is_array($value) && recursive_array_search($needle,$value) !== false)) {
            return $current_key;
        }
    }
    return false;
}

/**
* Function : searchForKey --> search threw a multidimensionales array for a specific value and return key
*
* @return: string key
**/

function searchForKey($id, $array) {
   foreach ($array as $key => $val) {
       if ($val[1] === $id) {
           return $key;
       }
   }
   return null;
}


/**
/* Function : checkZonesOnline --> Prüft ob  Member Online sind
/*
/* @param:  Array der Member die geprüft werden soll
/* @return: Array aller Member Online Zonen
**/

function checkZonesOnline($member) {
	global $sonoszonen, $zonen, $debug, $config;
	
	$memberzones = $member;
	foreach($memberzones as $zonen) {
		if(!array_key_exists($zonen, $sonoszonen)) {
			trigger_error("Die angegebene Zone (Member) existiert nicht. Bitte korrigieren!!", E_USER_NOTICE);
		}
	}
	foreach($memberzones as $zonen) {
		if(!$socket = @fsockopen($sonoszonen[$zonen][0], 1400, $errno, $errstr, 2)) {
			echo '<br>';
		} else {
			$member[] = $zonen;
		}
	}
	// print_r($member);
	return($member);
}


/**
* Function : array_multi_search --> search threw a multidimensionales array for a specific value
* Optional you can search more detailed on a specific key'
* https://sklueh.de/2012/11/mit-php-ein-mehrdimensionales-array-durchsuchen/
*
* @return: array with result
**/

 function array_multi_search($mSearch, $aArray, $sKey = "")
{
    $aResult = array();
    foreach( (array) $aArray as $aValues) {
        if($sKey === "" && in_array($mSearch, $aValues)) $aResult[] = $aValues;
        else 
        if(isset($aValues[$sKey]) && $aValues[$sKey] == $mSearch) $aResult[] = $aValues;
    }
    return $aResult;
}


/**
* Function : getLoxoneData --> Zeigt die Verbindung zu Loxone an
* @param: leer                             
*
* @return: ausgabe
**/

function getLoxoneData() {
	global $loxip, $loxuser, $loxpassword;
	echo "The following connection is used for data transmission to Loxone:<br><br>";

	echo 'IP-Address/Port: '.$loxip.'<br>';
	echo 'User: '.$loxuser.'<br>';
	echo 'Password: '.$loxpassword.'<br>';
}


/**
* Function : getPluginFolder --> ermittelt den Plugin Folder
* @param: leer                             
*
* @return: Plugin Folder
**/

function getPluginFolder(){
	$logpath = $_SERVER["SCRIPT_FILENAME"].'<br>';
	$folder = explode('/', $logpath);
	print_r ($folder[6]);
	return($folder);
}


/**
* Function: settimestamp --> Timestamp in Datei schreiben
* @param: leer
* @return: Datei
**/

 function settimestamp() {
	$myfile = fopen("timestamps.txt","w") or die ("Can't write the timestamp file!");
	fwrite($myfile, time());
	fclose($myfile);
 }


/**
* Function: gettimestamp --> Timestamp aus Datei lesen
* @param: leer
* @return: derzeit nichts
**/

 function gettimestamp() {
	$myfile = fopen("timestamps.txt","r") or die ("Can't read the timestamp file!");
	$zeit = fread($myfile, 999);
	fclose($myfile);
	if( time() % $zeit > 200 )
	{
		$was_soll_ich_jetzt_tun;
	}
}


/**
* Function : networkstatus --> Prüft ob alle Zonen Online sind
*
* @return: TRUE or FALSE
**/

function networkstatus() {
	global $sonoszonen, $zonen, $config, $debug;
	
	foreach($sonoszonen as $zonen => $ip) {
		$start = microtime(true);
		if (!$socket = @fsockopen($ip[0], 1400, $errno, $errstr, 3)) {
			echo "The Zone ".$zonen." using IP: ".$ip[0]." ==> Offline :-( Please check status!<br/>"; 
		} else { 
			$latency = microtime(true) - $start;
			$latency = round($latency * 10000);
			echo "The Zone ".$zonen." using IP: ".$ip[0]." ==> Online :-) The response time was ".$latency." Milliseconds <br/>";
		}
	}
	
}


/**
* Function : debug --> gibt verschiedene Info bzgl. der Zone aus
*
* @return: GetPositionInfo, GetMediaInfo, GetTransportInfo, GetTransportSettings, GetCurrentPlaylist
**/

  function debug() {
 	global $sonos, $sonoszone;
	$GetPositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$GetTransportInfo = $sonos->GetTransportInfo();
	$GetTransportSettings = $sonos->GetTransportSettings();
	$GetCurrentPlaylist = $sonos->GetCurrentPlaylist();
	
	echo '<PRE>';
	echo '<br />GetPositionInfo:';
	print_r($GetPositionInfo);

	echo '<br />GetMediaInfo:';
	print_r ($GetMediaInfo); // Radio

	echo '<br />GetTransportInfo:';
	print_r ($GetTransportInfo);
	
	echo '<br />GetTransportSettings:';
	print_r ($GetTransportSettings);  
	
	echo '<br />GetCurrentPlaylist:';
	print_r ($GetCurrentPlaylist);
	echo '</PRE>';
}


/**
* Function : File_Put_Array_As_JSON --> erstellt eine JSON Datei aus einer Array
*
* @param: 	Dateiname
*			Array die gespeichert werden soll			
* @return: Datei
**/	

function File_Put_Array_As_JSON($FileName, $ar, $zip=false) {
	if (! $zip) {
		return file_put_contents($FileName, json_encode($ar));
    } else {
		return file_put_contents($FileName, gzcompress(json_encode($ar)));
    }
}

/**
* Function : File_Get_Array_From_JSON --> liest eine JSON Datei ein und erstellt eine Array
*
* @param: 	Dateiname
* @return: Array
**/	

function File_Get_Array_From_JSON($FileName, $zip=false) {
	// liest eine JSON Datei und erstellt eine Array
    if (! is_file($FileName)) 	{ trigger_error("Fatal: Die Datei $FileName gibt es nicht.", E_USER_NOTICE); }
	    if (! is_readable($FileName))	{ trigger_error("Fatal: Die Datei $FileName ist nicht lesbar.", E_USER_NOTICE); }
            if (! $zip) {
				return json_decode(file_get_contents($FileName), true);
            } else {
				return json_decode(gzuncompress(file_get_contents($FileName)), true);
	    }
}


/**
* Function : URL_Encode --> ersetzt Steuerzeichen durch URL Encode
*
* @param: 	Zeichen das geprüft werden soll
* @return: Sonderzeichen
**/	

function URL_Encode($string) { 
    $entities = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D'); 
    $replacements = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]"); 
    return str_replace($entities, $replacements, urlencode($string)); 
} 


/**
* Function : _assertNumeric --> Prüft ob ein Eingabe numerisch ist
*
* @param: 	Eingabe die geprüft werden soll
* @return: TRUE or FALSE
**/

 function _assertNumeric($number) {
	// prüft ob eine Eingabe numerisch ist
    if(!is_numeric($number)) {
        trigger_error("The input is not numeric. Please try again", E_USER_NOTICE);
    }
    return $number;
 }
 
 
/**
* Function : random --> generiert eine Zufallszahl zwischen 90 und 99
*
* @return: Zahl
**/

 function random() {
	$zufallszahl = mt_rand(90,99); 
	return $zufallszahl;
 } 
 
 
/** - OBSOLETE -
*
* Function : getRINCON --> ermittelt die Rincon-ID der angegebenen Zone
*
* @param: 	IP-Adresse der Zone
* @return: Rincon-ID
**/
 function getRINCON($zoneplayerIp) { // gibt die RINCON der Sonos Zone zurück
  $url = "http://" . $zoneplayerIp . ":1400/status/zp";
  $xml = simpleXML_load_file($url);
  $uid = $xml->ZPInfo->LocalUID;
  return $uid;  
  return $playerIP;
 }

 
 
/**
*
* Function : AddMemberTo --> fügt ggf. Member zu Playlist oder Radio hinzu
*
* @param: 	empty
* @return:  create Group
**/
function AddMemberTo() { 
global $sonoszone, $master, $config;

	if(isset($_GET['member'])) {
		$member = $_GET['member'];
		if($member === 'all') {
		$member = array();
		foreach ($sonoszone as $zone => $ip) {
			// exclude master Zone
			if ($zone != $master) {
				array_push($member, $zone);
			}
		}
	} else {
		$member = explode(',', $member);
	}
	foreach ($member as $zone) {
		$sonos = new PHPSonos($sonoszone[$zone][0]);
		if ($zone != $master) {
			$sonos->SetAVTransportURI("x-rincon:" . trim($sonoszone[$master][1])); 
		}
	}
}	
}


function previous_el($array, $current, $use_key = false)
{
    // we'll return null if $current is the first in the array (there is no previous)
    $previous = null;

    foreach ($array as $key => $value)
    {
        $matched = $use_key ? $key === $current : $value === $current;
        if ($matched)
        {
            return $previous;
        }
        $previous = $use_key ? $key : $value;
    }

    // we'll return false if $current does not exist in the array
    return false;
}



// return the key or value after whatever is in $current
function next_el($array, $current, $use_key = false)
{
    $found = false;

    foreach ($array as $key => $value)
    {
        // $current was found on the previous loop
        if ($found)
        {
            return $use_key ? $key : $value;
        }
        $matched = $use_key ? $key === $current : $value === $current;
        if ($matched)
        {
            $found = true;
        }
    }

    if ($found)
    {
        // we'll return null if $current was the last one (there is no next)
        return null;
    }
    else
    {
        // we'll return false if $current does not exist in the array
        return false;
    }
}


// check if current zone is streaming
function isStreaming() {
	
        $sonos = new PHPSonos($sonoszone[$master][0]);
		$media = $sonos->GetMediaInfo();
        $uri = $media["CurrentURI"];
        # Standard streams
        if (substr($uri, 0, 18) === "x-sonosapi-stream:") {
            return true;
        }
        # Line in
        if (substr($uri, 0, 16) === "x-rincon-stream:") {
            return true;
        }
        # Line in (playbar)
        if (substr($uri, 0, 18) === "x-sonos-htastream:") {
            return true;
        }
        return false;
    }
	
	
/**
* Funktion : 	chmod_r --> setzt für alle Dateien im MP3 Verzeichnis die Rechte auf 0644
* https://stackoverflow.com/questions/9262622/set-permissions-for-all-files-and-folders-recursively
*
* @param: $Path --> Pfad zum Verzeichnis
* @return: empty
**/

function chmod_r($Path="") {
	global $Path, $MessageStorepath, $config;
	
	$Path = $MessageStorepath."".$config['MP3']['MP3path'];
	#echo $Path;
	$dp = opendir($Path);
     while($File = readdir($dp)) {
       if($File != "." AND $File != "..") {
         if(is_dir($File)){
            chmod($File, 0755);
            chmod_r($Path."/".$File);
         }else{
             chmod($Path."/".$File, 0644);
         }
       }
     }
   closedir($dp);
}

 
 
?>