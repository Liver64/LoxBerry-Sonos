<?php

/**
* Submodul: Helper
*
**/


/*************************************************************************************************************
/* Funktion : deviceCmdRaw --> Subfunction necessary to read Sonos Topology
/* @param: 	URL, IP-Adresse, port
/*
/* @return: data
/*************************************************************************************************************/
	
 function deviceCmdRaw($url, $ip='', $port=1400) {
	global $sonoszone, $master, $zone;
		
	$url = "http://{$sonoszone[$master][0]}:{$port}{$url}"; // ($sonoszone[$master][0])
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
 }
 


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
			'Group-ID' => $group,
			'Rincon' =>'RINCON_'.explode('RINCON_',(string)$player_data->uuid)[1]
		);
		$sonostopology[] = $player;
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
			LOGGING("The entered member zone does not exist, please correct your syntax!!", 3);
			exit;
		}
	}
	foreach($memberzones as $zonen) {
		$connection = @fsockopen($sonoszonen[$zonen][0], 1400, $errno, $errstr, 2);
		if(!$connection === false) {
			LOGGING("The zone ".$zonen." is OFFLINE!!", 4);
		} else {
			LOGGING("Zone ".$zonen." is ONLINE!!", 6);
			$member[] = $zonen;
		}
	}
	// print_r($member);
	return($member);
}



/**
/* Function : checkZoneOnline --> Prüft ob einzelner Player Online ist
/*
/* @param:  Player der geprüft werden soll
/* @return: true or nothing
**/

function checkZoneOnline($member) {
	global $sonoszonen, $zonen, $debug, $config;
	
	if(!array_key_exists($member, $sonoszonen)) {
		LOGGING("The entered member zone does not exist, please correct your syntax!!", 3);
		exit;
	}
	$connection = @fsockopen($sonoszonen[$member][0], 1400, $errno, $errstr, 2);
	if(!$connection === false) {
		$zoneon = true;
		return($zoneon);
	}
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
			echo "Player ".strtoupper($zonen)." using IP: ".$ip[0]." ==> Offline :-( Please check status!<br/>"; 
		} else { 
			$latency = microtime(true) - $start;
			$latency = round($latency * 10000);
			echo "Player ".strtoupper($zonen)." using IP: ".$ip[0]." ==> Online :-) Response time was ".$latency." Milliseconds <br/>";
		}
	}
	
}


/**
* Function : debug --> gibt verschiedene Info bzgl. der Zone aus
*
* @return: GetPositionInfo, GetMediaInfo, GetTransportInfo, GetTransportSettings, GetCurrentPlaylist
**/

  function debugsonos() {
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
    if (! is_file($FileName)) 	{ LOGGING("The file $FileName does not exist.", 3); exit; }
		if (! is_readable($FileName))	{ LOGGING("The file $FileName could not be loaded.", 3); exit;}
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
        LOGGING("The input is not numeric. Please try again", 4);
		exit;
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
	global $Path, $MessageStorepath, $config, $MP3path;
	
	$Path = $MessageStorepath."".$MP3path;
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


/*************************************************************************************************************
/* Funktion : checkaddon --> prüft vorhanden sein von Addon's
/* @param: 	leer
/*
/* @return: true oder Abbruch
/*************************************************************************************************************/
 function checkaddon() {
	global $home, $time_start;
	
	if(isset($_GET['weather'])) {
		# ruft die weather-to-speech Funktion auf
		if(substr($home,0,4) == "/opt") {	
			if(!file_exists('addon/weather-to-speech.php')) {
				LOGGING("The weather-to-speech Addon is currently not installed!", 4);
				exit;
			} else {
				if(!file_exists("$home/config/plugins/wu4lox/wu4lox.cfg")) {
					LOGGING("Bitte zuerst das Wunderground Plugin installieren!", 4);
					exit;
				}
			}
		} else {
			if(!file_exists('addon/weather-to-speech_nolb.php')) {
				LOGGING("The weather-to-speech Addon is currently not installed!", 4);
				exit;
			}
		}
	} elseif (isset($_GET['clock'])) {
		# ruft die clock-to-speech Funktion auf
		if(!file_exists('addon/clock-to-speech.php')) {
			LOGGING("The clock-to-speech addon is currently not installed!", 4);
			exit;
		}
	} elseif (isset($_GET['sonos'])) {
		# ruft die sonos-to-speech Funktion auf
		if(!file_exists('addon/sonos-to-speech.php')) {
			LOGGING("The sonos-to-speech addon Is currently not installed!", 4);
			exit;
		}
	} elseif (isset($_GET['abfall'])) {
		# ruft die waste-calendar-to-speech Funktion auf
		if(!file_exists('addon/waste-calendar-to-speech.php')) {
				LOGGING("The waste-calendar-to-speech Addon is currently not installed!", 4);
				exit;
			} else {
				if(!file_exists("$home/config/plugins/caldav4lox/caldav4lox.conf")) {
					LOGGING("Bitte zuerst das CALDAV4Lox Plugin installieren!", 4);
					exit;
				}
			}
	}
 }


/********************************************************************************************
/* Funktion : checkTTSkeys --> prüft die verwendete TTS Instanz auf Korrektheit
/* @param: leer                             
/*
/* @return: falls OK --> nichts, andernfalls Abbruch und Eintrag in error log
/********************************************************************************************/
function checkTTSkeys() {
	Global $config, $checkTTSkeys, $time_start;
	
	if ($config['TTS']['t2s_engine'] == 1001) {
		if (!file_exists("voice_engines/VoiceRSS.php")) {
			LOGGING("VoiceRSS is currently not available. Please install!", 4);
		} else {
			if(strlen($config['TTS']['API-key']) !== 32) {
				LOGGING("The specified VoiceRSS API key is invalid. Please correct!", 4);
			}
		}
	}
	if ($config['TTS']['t2s_engine'] == 3001) {
		if (!file_exists("voice_engines/MAC_OSX.php")) {
			LOGGING("MAC OSX is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 6001) {
		if (!file_exists("voice_engines/ResponsiveVoice.php")) {
			LOGGING("ResponsiveVoice is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 5001) {
		if (!file_exists("voice_engines/Pico_tts.php")) {
			LOGGING("Pico2Wave is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 4001) {
		if (!file_exists("voice_engines/Polly.php")) {
			LOGGING("Amazon Polly is currently not available. Please install!", 4);
		} else {
			if((strlen($config['TTS']['API-key']) !== 20) or (strlen($config['TTS']['secret-key']) !== 40)) {
				LOGGING("The specified AWS Polly API key is invalid. Please correct!!", 4);
			}
		}
	}
}



/**
* Funktion : 	playmode_selection --> setzt den Playmode bei Wiederherstllung gemäß der gespeicherten Werte
*
* @param: Sonos Zone
* @return: sting playmode
**/

function playmode_detection($zone, $settings)  {
	global $master, $sonoszonen;
	
	$sonos = new PHPSonos($sonoszonen[$zone][0]);
	#print_r($settings);
	if (($settings['repeat'] != 1) AND ($settings['repeat one'] != 1) AND ($settings['shuffle'] != 1)) {
		$sonos->SetPlayMode('NORMAL');
		$mode = 'NORMAL';
		
	} elseif (($settings['repeat'] == 1) AND ($settings['repeat one'] != 1) AND ($settings['shuffle'] != 1)) {
		$sonos->SetPlayMode('REPEAT_ALL');
		$mode = 'REPEAT_ALL';
	
	} elseif (($settings['repeat'] != 1) AND ($settings['repeat one'] != 1) AND ($settings['shuffle'] == 1)) {
		$sonos->SetPlayMode('SHUFFLE_NOREPEAT');
		$mode = 'SHUFFLE_NOREPEAT';
	
	} elseif (($settings['repeat'] != 1) AND ($settings['repeat one'] == 1) AND ($settings['shuffle'] == 1)) {
		$sonos->SetPlayMode('SHUFFLE_REPEAT_ONE');
		$mode = 'SHUFFLE_REPEAT_ONE';
	
	} elseif (($settings['repeat'] == 1) AND ($settings['repeat one'] != 1) AND ($settings['shuffle'] == 1)) {
		$sonos->SetPlayMode('SHUFFLE');
		$mode = 'SHUFFLE';
	
	} elseif (($settings['repeat'] != 1) AND ($settings['repeat one'] == 1) AND ($settings['shuffle'] != 1)) {
		$sonos->SetPlayMode('REPEAT_ONE');
		$mode = 'REPEAT_ONE';
	}
	#echo $mode;
	return $mode;
}


/**
* Funktion : 	allowLineIn --> filtert die gefunden Sonos Devices nach Zonen
* 				die den LineIn Eingang unterstützen
*
* @param: $model --> alle gefundenen Devices
* @return: $models --> Sonos Zonen
**/

 function allowLineIn($model) {
    $models = [
        "S5"    =>  "PLAY:5",
        "S6"    =>  "PLAY:5",
        "ZP80"  =>  "CONNECT",
        "ZP90"  =>  "CONNECT",
		"S15"   =>  "CONNECT",
        "ZP100" =>  "CONNECT:AMP",
        "ZP120" =>  "CONNECT:AMP",
        ];
    return in_array($model, array_keys($models));
}


/**
* Funktion : 	OnlyCONNECT --> filtert die gefunden Sonos Devices nach Model CONNECT
*
* @param: $model --> alle gefundenen Devices
* @return: $models --> TRUE or FALSE
**/

function OnlyCONNECT($model) {
    $models = [
        "CONNECT"  =>  "ZP80",
        "CONNECT"  =>  "ZP90",
		"CONNECT"  =>  "S15",
        ];
    return in_array($model, array_keys($models));
}


/**
* Funktion : 	AudioTypeIsSupported --> filtert die von Sonos unterstützten Audio Formate
*
* @param: $type --> Audioformat
* @return: $types --> TRUE or FALSE
**/

function AudioTypeIsSupported($type) {
    $types = [
        "mp3"   =>  "MP3 - MPEG-1 Audio Layer III oder MPEG-2 Audio Layer III",
        "wma"   =>  "WMA - Windows Media Audio",
		"aac"   =>  "AAC - Advanced Audio Coding",
		"ogg"   =>  "OGG - Ogg Vorbis Compressed Audio File",
		"flac"  =>  "FLAC - Free Lossless Audio Codec",
		"alac"  =>  "ALAC - Apple Lossless Audio Codec",
		"aiff"  =>  "AIFF - Audio Interchange File Format",
		"wav"   =>  "WAV - Waveform Audio File Format",
        ];
    return in_array($type, array_keys($types));
}


/**
* Function : select_t2s_engine --> selects the configured t2s engine for speech creation
*
* @param: empty
* @return: 
**/

function select_t2s_engine()  {
	global $config;
	
	if ($config['TTS']['t2s_engine'] == 1001) {
		include_once("voice_engines/VoiceRSS.php");
	}
	if ($config['TTS']['t2s_engine'] == 3001) {
		include_once("voice_engines/MAC_OSX.php");
	}
	if ($config['TTS']['t2s_engine'] == 6001) {
		include_once("voice_engines/ResponsiveVoice.php");
	}
	if ($config['TTS']['t2s_engine'] == 7001) {
		include_once("voice_engines/Google.php");
	}
	if ($config['TTS']['t2s_engine'] == 5001) {
		include_once("voice_engines/Pico_tts.php");
	}
	if ($config['TTS']['t2s_engine'] == 4001) {
		include_once("voice_engines/Polly.php");
	}
}



/**
* Function : load_t2s_text --> check if translation file exit and load into array
*
* @param: 
* @return: array 
**/

function load_t2s_text(){
	global $config, $t2s_langfile, $t2s_text_stand, $templatepath;
	
	$templatepath.'/lang/'.$t2s_langfile;
	if (file_exists($templatepath.'/lang/'.$t2s_langfile)) {
		$TL = parse_ini_file($templatepath.'/lang/'.$t2s_langfile, true);
	} else {
		LOGGING("For selected T2S language no translation file still exist! Please go to LoxBerry Plugin translation and create a file for selected language ".substr($config['TTS']['messageLang'],0,2),3);
		exit;
	}
	return $TL;
}



/**
* Function : check_sambashare --> check if what sambashare been used
*
* @param: 
* @return: array 
**/

function check_sambashare($sambaini, $searchfor, $sambashare) {
	global $hostname, $psubfolder, $lbpplugindir, $sambashare, $myIP;
	
	$contents = file_get_contents($sambaini);
	// escape special characters in the query
	$pattern = preg_quote($searchfor, '/');
	// finalise the regular expression, matching the whole line
	$pattern = "/^.*$pattern.*\$/m";
	if(preg_match_all($pattern, $contents, $matches))  {
		$myMessagepath = "//$myIP/plugindata/$psubfolder/tts/";
		$smbfolder = "Samba share 'plugindata' has been found";
	}
	else {
		$myMessagepath = "//$myIP/sonos_tts/";
		$smbfolder = "Samba share 'sonos_tts' has been found";
	}
	return $sambashare = array($myMessagepath, $smbfolder);
}


	/**
     * Create the xml metadata required by Sonos.
     *
     * @param string $id The ID of the track
     * @param string $parent The ID of the parent
     * @param array $extra An xml array of extra attributes for this item
     * @param string $service The Sonos service ID to use
     *
     * @return string
	 *
	 * https://github.com/duncan3dc/sonos/blob/master/src/Helper.php
     */
	 
	
	function createMetaDataXml(string $id, string $parent = "-1", array $extra = [], string $service = null): string
    {
		$xmlnew = New XmlWriter();
        if ($service !== null) {
            $extra["desc"] = [
                "_attributes"   =>  [
                    "id"        =>  "cdudn",
                    "nameSpace" =>  "urn:schemas-rinconnetworks-com:metadata-1-0/",
                ],
                "_value"        =>  "SA_RINCON{$service}_X_#Svc{$service}-0-Token",
            ];
        }
        $xml = $xmlnew->createXml([
            "DIDL-Lite" =>  [
                "_attributes"   =>  [
                    "xmlns:dc"      =>  "http://purl.org/dc/elements/1.1/",
                    "xmlns:upnp"    =>  "urn:schemas-upnp-org:metadata-1-0/upnp/",
                    "xmlns:r"       =>  "urn:schemas-rinconnetworks-com:metadata-1-0/",
                    "xmlns"         =>  "urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/",
                ],
                "item"  =>  array_merge([
                    "_attributes"   =>  [
                        "id"            =>  $id,
                        "parentID"      =>  $parent,
                        "restricted"    =>  "true",
                    ],
                ], $extra),
            ],
        ]);
        # Get rid of the xml header as only the DIDL-Lite element is required
        $metadata = explode("\n", $xml)[1];
        return $metadata;
    }


	
/**
* Function : mp3_files --> check if playgong mp3 file is valid in ../tts/mp3/
*
* @param: 
* @return: array 
**/

function mp3_files($playgongfile) {
	global $config;
	
	$scanned_directory = array_diff(scandir($config['SYSTEM']['mp3path'], SCANDIR_SORT_DESCENDING), array('..', '.'));
	$file_only = array();
	foreach ($scanned_directory as $file) {
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		if ($extension == 'mp3') {
			array_push($file_only, $file);
		}
	}
	#print_r($file_only);
	return (in_array($playgongfile, $file_only));
}


/**
* Function : check_rampto --> check if rampto settings in config are set
*
* @param: 
* @return: array 
**/

function check_rampto() {
	global $config, $volume, $sonos, $sonoszonen, $master;
	
	if(empty($config['TTS']['volrampto'])) {
		$ramptovol = "25";
		LOGGING("Rampto Volume in config has not been set. Default Volume '".$sonoszonen[$master][4]."' from Zone '".$master."' has been taken, please update Plugin Config (T2S Optionen).", 4);
	} else {
		$ramptovol = $config['TTS']['volrampto'];
		#LOGGING("Rampto Volume from config has been set.", 7);
	}
	if(empty($config['TTS']['rampto'])) {
		$rampto = "ALARM_RAMP_TYPE";
		LOGGING("Rampto Parameter (sleep, alarm, auto) in config has not been set. Default of 'auto' has been taken, please update Plugin Config (T2S Optionen).", 4);
	} else {
		$rampto = $config['TTS']['rampto'];	
		#LOGGING("Rampto Parameter from config has been set.", 7);
	}
	if($sonos->GetVolume() <= $ramptovol)	{
		$ramptovol = $volume;
	}
	$sonos->RampToVolume($rampto, $ramptovol);	
	return;	
}


/**
* Function : create_symlinks() --> check if symlinks for interface are there, if not create them
*
* @param: empty
* @return: symlinks created 
**/

function create_symlinks()  {
	
	global $config, $ttsfolder, $mp3folder, $myFolder, $lbphtmldir;
	
	$symcurr_path = $config['SYSTEM']['path'];
	$symttsfolder = $config['SYSTEM']['ttspath'];
	$symmp3folder = $config['SYSTEM']['mp3path'];
	$copy = false;
	if (!is_dir($symmp3folder)) {
		$copy = true;
	}
	LOGGING("check if folder/symlinks exists, if not create", 5);
	if (!is_dir($symttsfolder)) {
		mkdir($symttsfolder, 0755);
		LOGGING("Folder: '".$symttsfolder."' has been created", 7);
	}
	if (!is_dir($symmp3folder)) {
		mkdir($symmp3folder, 0755);
		LOGGING("Folder: '".$symmp3folder."' has been created", 7);
	}
	if (!is_link($myFolder."/interfacedownload")) {
		symlink($symttsfolder, $myFolder."/interfacedownload");
		LOGGING("Symlink: '".$myFolder.'/interfacedownload'."' has been created", 7);
	}
	if (!is_link($lbphtmldir."/interfacedownload")) {
		symlink($symttsfolder, $lbphtmldir."/interfacedownload");
		LOGGING("Symlink: '".$lbphtmldir.'/interfacedownload'."' has been created", 7);
	}
	if ($copy === true) {
		#LOGGING("Copy existing mp3 files from $myFolder/$mp3folder to $symcurr_path/$mp3folder", 6);
		xcopy($myFolder."/".$mp3folder, $symcurr_path."/".$mp3folder);
		LOGGING("All files has been copied from: '".$myFolder."/".$mp3folder."' to: '".$symcurr_path."/".$mp3folder."'", 5);
	}
	
}


/**
 * Copy a file, or recursively copy a folder and its contents
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.0.1
 * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
 * @param       string   $source    Source path
 * @param       string   $dest      Destination path
 * @param       int      $permissions New folder creation permissions
 * @return      bool     Returns true on success, false on failure
 */
function xcopy($source, $dest, $permissions = 0755)
{
    // Check for symlinks
    if (is_link($source)) {
        return symlink(readlink($source), $dest);
    }
    // Simple copy for a file
    if (is_file($source)) {
        return copy($source, $dest);
    }
    // Make destination directory
    if (!is_dir($dest)) {
        mkdir($dest, $permissions);
    }
    // Loop through the folder
    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
        // Skip pointers
        if ($entry == '.' || $entry == '..') {
            continue;
        }
        // Deep copy directories
        xcopy("$source/$entry", "$dest/$entry", $permissions);
    }
    // Clean up
    $dir->close();
    return true;
}


/**
/* Funktion : write_MP3_IDTag --> write MP3-ID Tags to file
/* @param: 	leer
/*
/* @return: Message
/**/	

function write_MP3_IDTag($income_text) {
	
	global $config, $data, $textstring, $filename, $TextEncoding, $text;
	
	require_once("system/bin/getid3/getid3.php");
	// Initialize getID3 engine
	$getID3 = new getID3;
	$getID3->setOption(array('encoding' => $TextEncoding));
	 
	require_once('system/bin/getid3/write.php');	
	// Initialize getID3 tag-writing module
	$tagwriter = new getid3_writetags;
	$tagwriter->filename = $config['SYSTEM']['ttspath']."/".$filename.".mp3";
	$tagwriter->tagformats = array('id3v2.3');

	// set various options (optional)
	$tagwriter->overwrite_tags    = true;  // if true will erase existing tag data and write only passed data; if false will merge passed data with existing tag data (experimental)
	$tagwriter->remove_other_tags = false; // if true removes other tag formats (e.g. ID3v1, ID3v2, APE, Lyrics3, etc) that may be present in the file and only write the specified tag format(s). If false leaves any unspecified tag formats as-is.
	$tagwriter->tag_encoding      = $TextEncoding;
	$tagwriter->remove_other_tags = true;

	// populate data array
	$TagData = array(
					'title'                  => array("$income_text"),
					'artist'                 => array('sonos4lox'),
					'album'                  => array(''),
					'year'                   => array(date("Y")),
					'genre'                  => array('text'),
					'comment'                => array('generated by LoxBerry Sonos Plugin'),
					'track'                  => array(''),
					#'popularimeter'          => array('email'=>'user@example.net', 'rating'=>128, 'data'=>0),
					#'unique_file_identifier' => array('ownerid'=>'user@example.net', 'data'=>md5(time())),
				);
	
	$tagwriter->tag_data = $TagData;
	
	// write tags
	if ($tagwriter->WriteTags()) {
	LOGDEB("Successfully wrote id3v2.3 tags");
		if (!empty($tagwriter->warnings)) {
			LOGWARN('There were some warnings:<br>'.implode($tagwriter->warnings));
		}
	} else {
		LOGERR('Failed to write tags!<br>'.implode($tagwriter->errors));
	}
	return ($TagData);
}	
 
 
?>
