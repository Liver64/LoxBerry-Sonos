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
*  OBSOLETE
*
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
	
	global $sonoszonen, $zonen, $debug, $config, $folfilePlOn;
	
	$memberzones = $member;
	
	foreach($memberzones as $zonen) {
		if(!array_key_exists($zonen, $sonoszonen)) {
			LOGGING("helper.php: The entered member zone does not exist, please correct your syntax!!", 3);
			exit;
		}
	}

	$zonesonline = array();
	LOGGING("sonos.php: Backup Online check for Players will be executed",7);
	foreach($sonoszonen as $zonen => $ip) {
		$handle = file_exists($folfilePlOn."".$zonen.".txt");
		if($handle) {
			$sonoszone[$zonen] = $ip;
			array_push($zonesonline, $zonen);
		}
	}
	$member = $zonesonline;
	// print_r($member);
	return($member);
}



/**
/* Function : checkZoneOnline --> Prüft ob einzelner Player Online ist
/*
/* @param:  Player der geprüft werden soll
/* @return: true or nothing
**/

function checkZoneOnline($MemberTest) {
	
	global $sonoszone, $debug, $config, $folfilePlOn;

	if ($MemberTest == 'all')   {
		return false;
	}
	if(!array_key_exists($MemberTest, $sonoszone)) {
		LOGWARN("helper.php: The entered Zone '".$MemberTest."' does not exist, please correct your syntax!!");
		#return false;
	}
	#$handle = @fsockopen($sonoszonen[$MemberTest][0], 1400, $errno, $errstr, 2);
	$handle = file_exists($folfilePlOn."".$MemberTest.".txt");
	if(!$handle === false) {
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
    if (! is_file($FileName)) 	{ LOGGING("helper.php: The file $FileName does not exist.", 3); exit; }
		if (! is_readable($FileName))	{ LOGGING("helper.php: The file $FileName could not be loaded.", 3); exit;}
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
        LOGGING("helper.php: The input is not numeric. Please try again", 4);
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

	global $sonoszone, $master, $config, $sleepaddmember;

	if(isset($_GET['member'])) {
		$member = $_GET['member'];
		if($member === 'all') {
			$member = array();
			foreach ($sonoszone as $zone => $ip) {
				# exclude master Zone
				if ($zone != $master) {
					array_push($member, $zone);
				}
			}
		} else {
			$member = explode(',', $member);
		}
		
		# check if member is ON and create valid array
		$memberon = array();
		foreach ($member as $zone) {
			$zoneon = checkZoneOnline($zone);
			if ($zoneon === (bool)true)   {
				array_push($memberon, $zone);
			}
		}
		$member = $memberon;
		foreach ($member as $zone) {
			$sonos = new SonosAccess($sonoszone[$zone][0]);
			if ($zone != $master)    {
				try {
					$sonos->SetAVTransportURI("x-rincon:" . trim($sonoszone[$master][1])); 
					LOGGING("helper.php: Zone: ".$zone." has been added to master: ".$master,6);
				} catch (Exception $e) {
					LOGGING("helper.php: Zone: ".$zone." could not be added to master: ".$master,4);
				}
			}
			usleep((int)($sleepaddmember * 1000000));
		}
		volume_group();
		$sonos = new SonosAccess($sonoszone[$master][0]);
	}	
}


// check if current zone is streaming
function isStreaming() {
	
        $sonos = new SonosAccess($sonoszone[$master][0]);
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
				LOGGING("helper.php: The weather-to-speech Addon is currently not installed!", 4);
				exit;
			} else {
				if(!file_exists("$home/config/plugins/wu4lox/wu4lox.cfg")) {
					LOGGING("helper.php: Bitte zuerst das Wunderground Plugin installieren!", 4);
					exit;
				}
			}
		} else {
			if(!file_exists('addon/weather-to-speech_nolb.php')) {
				LOGGING("helper.php: The weather-to-speech Addon is currently not installed!", 4);
				exit;
			}
		}
	} elseif (isset($_GET['clock'])) {
		# ruft die clock-to-speech Funktion auf
		if(!file_exists('addon/clock-to-speech.php')) {
			LOGGING("helper.php: The clock-to-speech addon is currently not installed!", 4);
			exit;
		}
	} elseif (isset($_GET['sonos'])) {
		# ruft die sonos-to-speech Funktion auf
		if(!file_exists('addon/sonos-to-speech.php')) {
			LOGGING("helper.php: The sonos-to-speech addon Is currently not installed!", 4);
			exit;
		}
	} elseif (isset($_GET['abfall'])) {
		# ruft die waste-calendar-to-speech Funktion auf
		if(!file_exists('addon/waste-calendar-to-speech.php')) {
				LOGGING("helper.php: The waste-calendar-to-speech Addon is currently not installed!", 4);
				exit;
			} else {
				if(!file_exists("$home/config/plugins/caldav4lox/caldav4lox.conf")) {
					LOGGING("helper.php: Bitte zuerst das CALDAV4Lox Plugin installieren!", 4);
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
			LOGGING("helper.php: VoiceRSS is currently not available. Please install!", 4);
		} else {
			if(strlen($config['TTS']['apikey']) !== 32) {
				LOGGING("helper.php: The specified VoiceRSS API key is invalid. Please correct!", 4);
			}
		}
	}
	if ($config['TTS']['t2s_engine'] == 8001) {
		if (!file_exists("voice_engines/GoogleCloud.php")) {
			LOGGING("helper.php: GoogleCloudTTS is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 9001) {
		if (!file_exists("voice_engines/MS_Azure.php")) {
			LOGGING("helper.php: MS_Azure is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 9011) {
		if (!file_exists("voice_engines/ElevenLabs.php")) {
			LOGGING("helper.php: Elevenlabs is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 3001) {
		if (!file_exists("voice_engines/MAC_OSX.php")) {
			LOGGING("helper.php: MAC OSX is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 6001) {
		if (!file_exists("voice_engines/ResponsiveVoice.php")) {
			LOGGING("helper.php: ResponsiveVoice is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 5001) {
		if (!file_exists("voice_engines/Pico_tts.php")) {
			LOGGING("helper.php: Pico2Wave is currently not available. Please install!", 4);
		}
	}
	if ($config['TTS']['t2s_engine'] == 4001) {
		if (!file_exists("voice_engines/Polly.php")) {
			LOGGING("helper.php: Amazon Polly is currently not available. Please install!", 4);
		} else {
			if((strlen($config['TTS']['apikey']) !== 20) or (strlen($config['TTS']['secretkey']) !== 40)) {
				LOGGING("helper.php: The specified AWS Polly API key is invalid. Please correct!!", 4);
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

function playmode_detection($zone, $mode)  {
	
	global $master, $sonoszone;
	
	$sonos = new SonosAccess($sonoszone[$zone][0]);
	if ($mode == 0) {
		$sonos->SetPlayMode('0');
		$mode = 'NORMAL';
		
	} elseif ($mode == 1) {
		$sonos->SetPlayMode('1');
		$mode = 'REPEAT_ALL';
	
	} elseif ($mode == 3) {
		$sonos->SetPlayMode('3');
		$mode = 'SHUFFLE_NOREPEAT';
	
	} elseif ($mode == 5) {
		$sonos->SetPlayMode('5');
		$mode = 'SHUFFLE_REPEAT_ONE';
	
	} elseif ($mode == 4) {
		$sonos->SetPlayMode('4');
		$mode = 'SHUFFLE';
	
	} elseif ($mode == 2) {
		$sonos->SetPlayMode('2');
		$mode = 'REPEAT_ONE';
	}
	return $mode;
}



/**
* Funktion : 	SetPlaymodes --> setzt den Playmode bei Wiederherstllung gemäß der Eingabe in der URL
*
* @param: Sonos Zone
* @return: sting playmode
**/

function SetPlaymodes($zone, $mode)  {
	
	global $master, $sonoszone;
	
	$sonos = new SonosAccess($sonoszone[$zone][0]);
	if ($mode == 'NORMAL') {
		$sonos->SetPlayMode('0');
		$mode = 0;

	} elseif ($mode == 'REPEAT_ALL') {
		$sonos->SetPlayMode('1');
		$mode = 1;
	
	} elseif ($mode == 'SHUFFLE_NOREPEAT') {
		$sonos->SetPlayMode('3');
		$mode = 3;
	
	} elseif ($mode == 'SHUFFLE_REPEAT_ONE') {
		$sonos->SetPlayMode('5');
		$mode = 5;
	
	} elseif ($mode == 'SHUFFLE') {
		$sonos->SetPlayMode('4');
		$mode = 4;
	
	} elseif ($mode == 'REPEAT_ONE') {
		$sonos->SetPlayMode('2');
		$mode = 2;
	}
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
		"S16"   =>  "CONNECT:AMP",
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
	if ($config['TTS']['t2s_engine'] == 9001) {
		include_once("voice_engines/MS_Azure.php");
	}
	if ($config['TTS']['t2s_engine'] == 9011) {
		include_once("voice_engines/ElevenLabs.php");
	}
	if ($config['TTS']['t2s_engine'] == 8001) {
		include_once("voice_engines/GoogleCloud.php");
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
		LOGGING("helper.php: For selected T2S language no translation file still exist! Please go to LoxBerry Plugin translation and create a file for selected language ".substr($config['TTS']['messageLang'],0,2),4);
		$TL = "";
		#exit;
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
		require_once("system/bin/xml/XmlWriter.php");
		
		$xmlnew = New XmlWriterNew();
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
		print_R($metadata);
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
	global $config, $volume, $sonos, $sonoszone, $master;
	
	if(empty($config['TTS']['volrampto'])) {
		$ramptovol = "25";
		LOGGING("helper.php: Rampto Volume in config has not been set. Default Volume '".$sonoszone[$master][4]."' from Zone '".$master."' has been taken, please update Plugin Config (T2S Optionen).", 4);
	} else {
		$ramptovol = $config['TTS']['volrampto'];
		#LOGGING("helper.php: Rampto Volume from config has been set.", 7);
	}
	if(empty($config['TTS']['rampto'])) {
		$rampto = "ALARM_RAMP_TYPE";
		LOGGING("helper.php: Rampto Parameter (sleep, alarm, auto) in config has not been set. Default of 'auto' has been taken, please update Plugin Config (T2S Optionen).", 4);
	} else {
		$rampto = $config['TTS']['rampto'];	
		#LOGGING("helper.php: Rampto Parameter from config has been set.", 7);
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
	
	global $config, $ttsfolder, $mp3folder, $myFolder, $lbphtmldir, $myip;
	
	$symcurr_path = $config['SYSTEM']['path'];
	$symttsfolder = $config['SYSTEM']['ttspath'];
	$symmp3folder = $config['SYSTEM']['mp3path'];
	
	$copy = false;
	if (!is_dir($symmp3folder)) {
		$copy = true;
	}
	LOGGING("helper.php: check if folder/symlinks exists, if not create", 5);
	if (!is_dir($symttsfolder)) {
		mkdir($symttsfolder, 0755);
		LOGGING("helper.php: Folder: '".$symttsfolder."' has been created", 7);
	}
	if (!is_dir($symmp3folder)) {
		mkdir($symmp3folder, 0755);
		LOGGING("helper.php: Folder: '".$symmp3folder."' has been created", 7);
	}
	if (!is_link($myFolder."/interfacedownload")) {
		symlink($symttsfolder, $myFolder."/interfacedownload");
		LOGGING("helper.php: Symlink: '".$myFolder.'/interfacedownload'."' has been created", 7);
	}
	if (!is_link($lbphtmldir."/interfacedownload")) {
		symlink($symttsfolder, $lbphtmldir."/interfacedownload");
		LOGGING("helper.php: Symlink: '".$lbphtmldir.'/interfacedownload'."' has been created", 7);
	}
	if ($copy === true) {
		xcopy($myFolder."/".$mp3folder, $symcurr_path."/".$mp3folder);
		LOGGING("helper.php: All files has been copied from: '".$myFolder."/".$mp3folder."' to: '".$symcurr_path."/".$mp3folder."'", 5);
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
	echo $source;
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
	LOGDEB("Sonos: helper.php: Successfully wrote id3v2.3 tags");
		if (!empty($tagwriter->warnings)) {
			LOGWARN('Sonos: helper.php: There were some warnings:<br>'.implode($tagwriter->warnings));
		}
	} else {
		LOGERR('Sonos: helper.php: Failed to write tags!<br>'.implode($tagwriter->errors));
	}
	return ($TagData);
}	


// source: Laravel Framework
// https://github.com/laravel/framework/blob/8.x/src/Illuminate/Support/Str.php

/**
# Some simple Tests
$needle = "containerert1653";
$haystack = "x-rincon-cpcontainer:100d206cuser-fav-containerert1653";
$resultc = starts_with($haystack, $needle);
var_dump($resultc);
$results = contains($haystack, $needle);
var_dump($results);
$resulte = ends_with($haystack, $needle);
var_dump($resulte);
**/

/**
/* Funktion : starts_with --> check if string starts with
/*
/* @param: $haystack = string, $needle = search string                             
/* @return: bool(true) or bool(false)
**/

function starts_with($haystack, $needle) {
    return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
}

/**
/* Funktion : contains --> check if string contain
/*
/* @param: $haystack = string, $needle = search string                             
/* @return: bool(true) or bool(false)
**/

function contains($haystack, $needle) {
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
}

/**
/* Funktion : ends_with --> check if string ends with
/*
/* @param: $haystack = string, $needle = search string                             
/* @return: bool(true) or bool(false)
**/

function ends_with($haystack, $needle) {
    return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
}


/**
/* Funktion : DeleteTmpFavFilesh --> deletes the Favorite ONE-click Temp files
/*
/* @param: empty                             
/* @return: 
**/

function DeleteTmpFavFiles() {
	
	global $queuetracktmp, $radiofav, $queuetmp, $radiofavtmp, $queueradiotmp, $favtmp, $pltmp, $tuneinradiotmp, $queuepltmp;
    
	#@unlink($queuetracktmp);
	#@unlink($radiofav);
	#@unlink($queuetmp);
	#@unlink($radiofavtmp);
	#@unlink($queueradiotmp);
	#@unlink($favtmp);
	#@unlink($pltmp);
	#@unlink($tuneinradiotmp);
	#@unlink($queuepltmp);
	#@unlink($sonospltmp);
	@array_map('unlink', glob("/run/shm/s4lox_fav*.json"));
	@array_map('unlink', glob("/run/shm/s4lox_pl*.json"));
	LOGGING("helper.php: All Radio/Tracks/Playlist Temp Files has been deleted.", 7);
}


/**
/* Funktion : NextTrack --> skip to next track and play (in case it was stopped)
/*
/* @param: empty                             
/* @return: 
**/

function NextTrack() {
	
	global $sonos;
	
	$sonos->Next();
	sleep(1);
	LOGINF ("queue.php: Function 'next' has been executed");
	$currun = $sonos->GetTransportInfo();
	if ($currun != (int)"1")   {
		$sonos->Play();
	}
}

/**
/* Funktion : AddDetailsToMetadata --> add Service and sid of service to array
/*
/* @param: empty                             
/* @return: 
**/

function AddDetailsToMetadata() 
{
	
	global $sonos, $services;
    
	$browse = $sonos->GetFavorites();
	foreach ($browse as $key => $value)  {
		# identify sid based on CurrentURI
		$sid = substr(substr($value['resorg'], strpos($value['resorg'], "sid=") + 4), 0, strpos(substr($value['resorg'], strpos($value['resorg'], "sid=") + 4), "&"));
		if ($sid == "")   {
			# identify local track/Album and add sid
			if (substr($value['resorg'], 0, 11) == "x-file-cifs" or substr($value['resorg'], 0, 17) == "x-rincon-playlist")   {
				$sid = "999";
			# identify Sonos Playlist and add sid
			} elseif (substr($value['resorg'], 0, 4) == "file")   {
				$sid = "998";
			# if sid could not be obtained set default	
			} else {
				$sid = "000";
			}
		}
		
		isService($sid);
		$browse[$key]['Service'] = $services[$sid];
		$browse[$key]['sid'] = $sid;
	}
	#print_r($browse);
	return $browse;
	LOGGING("helper.php: All Radio/Tracks/Playlist Temp Files has been deleted.", 7);
}



/**
/* Funktion : getStreamingService --> get the Streaming Service/Source already playing
/*
/* @param: string $player                             
/* @return: string
**/

function getStreamingService($zone) 
{
		global $sonoszone, $sonos, $config, $services;
		
		# check ONLY playing zones
		$run = $sonos->GetTransportInfo();
		if ($run == "1")    {
			$data = $sonos->GetPositionInfo();
			$data1 = $sonos->GetMediaInfo();
			#print_r($data);
			#print_r($data1);
			$sid = substr(substr($data['TrackURI'], strpos($data['TrackURI'], "sid=") + 4), 0, strpos(substr($data['TrackURI'], strpos($data['TrackURI'], "sid=") + 4), "&"));
			if ($sid == "")   {
				# identify local track/Album and add sid
				if (substr($data['TrackURI'], 0, 11) == "x-file-cifs" or substr($data['TrackURI'], 0, 17) == "x-rincon-playlist")   {
					$sid = "999";
				# identify Sonos Playlist and add sid
				} elseif (substr($data['TrackURI'], 0, 4) == "file")   {
					$sid = "998";
				# try identify Radio Stations
				} elseif (substr($data1["UpnpClass"] ,0 ,36) == "object.item.audioItem.audioBroadcast")  {
					$sid = substr(substr($data1['CurrentURI'], strpos($data1['CurrentURI'], "sid=") + 4), 0, strpos(substr($data1['CurrentURI'], strpos($data1['CurrentURI'], "sid=") + 4), "&"));
				# if sid could not be obtained set default	
				} else {
					$sid = "000";
				}
			}
			isService($sid);
			$StrService = $services[$sid];
			LOGGING("helper.php: Currently '".$StrService."' is playing", 6);
			return $StrService;
		}
		#return $StrService;
}



/**
/* Funktion : validate_player --> check duplicate room name
/*
/* @param: array of IP                             
/* @return: error or nothing
**/
function validate_player($players)    {

/**	INPUT FORMAT

	Array
(
    [0] => max
    [1] => wohnzimmer
    [2] => kids
    [3] => schlafen
    [4] => wintergarten
    [5] => terrasse
)
**/	
	global $sonos, $lbphtmldir;
	
	$player = array();
	foreach ($players as $zoneip) {
		$info = json_decode(file_get_contents('http://' . $zoneip . ':1400/info'), true);
		$roomraw = $info['device']['name'];
		$search = array('Ä','ä','Ö','ö','Ü','ü','ß');
		$replace = array('Ae','ae','Oe','oe','Ue','ue','ss');
		$room = strtolower(str_replace($search,$replace,$roomraw));
		array_push($player, $room);
	}
	# **	ONLY FOR TESTING START
	
	#$arr = array(0 => "wohnzimmer", 1 => "kids", 3 => "wohnzimmer", 4 => "schlafen", 5 => "kids");
	#$unique = array_unique($arr);
	#$duplicate_player = array_diff_assoc($arr, $unique);
	
	# **	ONLY FOR TESTING END	
	$unique = array_unique($player);
	$duplicate_player = array_diff_assoc($player, $unique);
	if (count($duplicate_player) > 0 and is_file($lbphtmldir."/bin/check_player_dup.txt"))  {
		foreach($duplicate_player as $playzone)   {
			notify(LBPPLUGINDIR, "Sonos", "Player '".$playzone."' has been detected twice! Maybe a pair of new devices not added to your Sonos System like 'unnamed room' or duplicate room names! Please add to your Sonos System via App or rename min. 1 Player in your Sonos App in order to avoid problems using the Plugin. Once done please scan again for new Player in your Network.", "error");
		}
	}
	unlink($lbphtmldir."/bin/check_player_dup.txt");
	return $duplicate_player;
}




function vversion()    {
	global $sonos;
	
	$pversion = LBSystem::pluginversion();
	echo "Top Plugin V$pversion<br>";
	#$url = 'https://raw.githubusercontent.com/Liver64/LoxBerry-Sonos/master/plugin.cfg';
	$url = 'https://raw.githubusercontent.com/Liver64/LoxBerry-Sonos/master/webfrontend/html/release/release.cfg';
	$as = is_file($url);
	var_dump($as);
	$file = "/opt/loxberry/data/plugins/sonos4lox/plugin.cfg";
	file_put_contents($file, file_get_contents($url));
	$wq = json_decode(file_get_contents($file, TRUE));
	#print_r($wq);
	#json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	$t = json_decode($wq, true);
	print_R($t);
	var_dump($t);
		exit;
	var_dump($wq);
	$rt = "4.1.5";
	var_dump($rt);
	$w = substr($rt, 0, 1);
	echo $w;
}




/**
/* Funktion :  sendInfoMS --> send info to MS
/*
/* @param: $abbr = Shortname for Inbound Port to be send
/* @param: $player = Name of player to be send
/* @param: $val = value to be send
/*
/* @return: error or nothing
**/

function sendInfoMS($abbr, $player, $val)    {

	global $sonos, $lbphtmldir, $ms, $config, $master;
	
	require_once "$lbphtmldir/system/io-modul.php";
	#require_once "phpMQTT/phpMQTT.php";
	require_once "$lbphtmldir/bin/phpmqtt/phpMQTT.php";

	// check if Data transmission is switched off
	if(!is_enabled($config['LOXONE']['LoxDaten'])) {
		LOGGING("helper.php: Communication to Loxone is turned off!", 6);
		return;
	}
	
	if(is_enabled($config['LOXONE']['LoxDatenMQTT'])) {
		// Get the MQTT Gateway connection details from LoxBerry
		$creds = mqtt_connectiondetails();
		// MQTT requires a unique client id
		$client_id = uniqid(gethostname()."_client");
		$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'],  $creds['brokerport'], $client_id);
		$mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass']);
		$mqttstat = "1";
	} else {
		$mqttstat = "0";
	}
	
	// ceck if configured MS is fully configured
	if (!isset($ms[$config['LOXONE']['Loxone']])) {
		LOGERR ("helper.php: Your selected Miniserver from Sonos4lox Plugin config seems not to be fully configured. Please check your LoxBerry Miniserver config!") ;
		return;
	}
	
		// obtain selected Miniserver from Plugin config
		$my_ms = $ms[$config['LOXONE']['Loxone']];
		# send TEXT data
		$lox_ip			= $my_ms['IPAddress'];
		$lox_port 	 	= $my_ms['Port'];
		$loxuser 	 	= $my_ms['Admin'];
		$loxpassword 	= $my_ms['Pass'];
		$loxip = $lox_ip.':'.$lox_port;
		try {
			LOGDEB("helper.php: Trying to send Info for Zone '".$player."'.");	
			if ($mqttstat == "1")   {
				$err = $mqtt->publish('Sonos4lox/'.$abbr.'/'.$player, $val, 0, 1);
				LOGDEB("helper.php: Requested Info for Zone '".$player."' has been send to MQTT. Pls. check your MQTT incoming overview for: 'Sonos4lox_".$abbr."_".$player."' or UDP for: 'MQTT:\iSonos4lox/".$abbr."/".$player."=\\i\\v' and create in Loxone an Virtual Inbound.");	
				echo "Requested Info for Zone '".$player."' has been send to MQTT. Pls. check your MQTT incoming HTTP overview for: 'Sonos4lox_".$abbr."_".$player."' or UDP for: 'MQTT:\iSonos4lox/".$abbr."/".$player."=\\i\\v' and create in Loxone an Virtual Inbound.";
			} else {			
				$handle = @get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/$abbr_$player/$val"); // Radio oder Playliste
				LOGDEB("helper.php: Requested Info for Zone '".$player."' has been send to UDP. Pls. check your UDP incoming overview for: '".$abbr."_$player' and create in Loxone an Virtual Inbound.");	
				echo "Requested Info for Zone '".$player."' has been send to UDP. Pls. check your Miniserver UDP incoming monitor for: '".$abbr."_$player' and create in Loxone an Virtual Inbound.";
			}
		} catch (Exception $e) {
			LOGWARN("helper.php: Sending Info for Zone '".$player."' failed, we skip here...");	
			return false;
		}
		
		if ($mqttstat == "1")   {
			$mqtt->close();
		}
}

/*******
* Funktion : 	isSoundbar --> filtert die Sonos Devices nach Zonen die Soundbars sind
*
* @param: 	$model --> alle gefundenen Soundbars
* @return: 	$soundb --> true

*******/

 function isSoundbar($model) {
    $soundb = [
				"S9"    =>  "PLAYBAR",
				"S11"   =>  "PLAYBASE",
				"S14"   =>  "BEAM",
				"S31"   =>  "BEAM",
				"S15"   =>  "CONNECT",
				"S19"   =>  "ARC",
				"S16"   =>  "AMP",
				"S36"   =>  "RAY",
			];
    return in_array($model, array_keys($soundb));
}

	
/* Funktion :  GetZoneState --> check for Zones Online
/*
/* @param: none
/* @return: array

Array
(
    [0] => Wohnzimmer
    [1] => Bad
    [2] => Ben
    [3] => Nele
    [4] => Schlafen
)

**/

function GetZoneState()    {

	global $sonos;
	
	require_once('system/bin/XmlToArray.php');
	
	$xml = $sonos->GetZoneStates();
	# https://github.com/vyuldashev/xml-to-array/tree/master
	$array = XmlToArray::convert($xml);
	#print_r($array);
	$interim = $array['ZoneGroupState']['ZoneGroups']['ZoneGroup'];
	$final = array();
	$i = 0;
	foreach($interim as $key)     {
		$i++;
		#array_push($final, $key['ZoneGroupMember']['attributes']['ZoneName']);
		#array_push($final, $key['ZoneGroupMember']['attributes']['UUID']);
		foreach($key['ZoneGroupMember']['Satellite'] as $key1)      {
			@array_push($final, substr($key1['attributes']['HTSatChanMapSet'], -2));
			#$year = substr($flightDates->departureDate->year, -2);
		}
	}
	# remove empty values, remove duplicate values and re-index array
	$zoneson = array_unique(array_values(array_filter($final)));
	if (empty($zoneson))    {
		GetZoneState();
	}
	#print_r($zoneson);
	$subwoofer = recursive_array_search('SE',$zoneson);
	if ($subwoofer === false ? $sub = "false" : $sub = "true");
	echo $sub;
	return $zoneson;

}


/* Funktion :  CheckSub --> check for Subwoofer/Surround available
/*
/* @param: SW or LR
/* @return: array of room names
**/

function CheckSubSur($val)    {

	global $sonos, $config;

	if ($val != "SW" and $val != "LR")   {
		return "invalid entries";
	} elseif ($val == "SW")  {
		$key = "SUB";
	} elseif ($val == "LR")  {
		$key = "SUR";
	}
	$folfilePlOn = LBPDATADIR."/PlayerStatus/s4lox_on_";				// Folder and file name for Player Status
	require_once('system/bin/XmlToArray.php');
	
	# identify min 1 Zone Online to get IP
	$int = array();
	foreach($config['sonoszonen'] as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			array_push($int, $zonen);
		}
	}
	$sonos = new SonosAccess($config['sonoszonen'][$int[0]][0]); //Sonos IP Adresse
	$xml = $sonos->GetZoneStates();
	# https://github.com/vyuldashev/xml-to-array/tree/master
	$array = XmlToArray::convert($xml);
	$interim = $array['ZoneGroupState']['ZoneGroups']['ZoneGroup'];
	
	$subsur = array();
	foreach($interim as $key => $value)     {
		if (@$value['ZoneGroupMember']['attributes']['HTSatChanMapSet'])  {
			$int = explode(";", $value['ZoneGroupMember']['attributes']['HTSatChanMapSet']);
			foreach ($int as $a)   {
				$a = substr($a, -2);
				if ($a == $val)    {
					$subsur[strtolower($value['ZoneGroupMember']['attributes']['ZoneName'])] = $key;
				}
			}
		}
	}
	if (empty($subsur))    {
		$subsur = "false";
	}
	#print_r($subsur);
	return $subsur;
}


function checkOnline($zone)   {
	
	global $folfilePlOn;
		
	$handle = is_file($folfilePlOn."".$zone.".txt");
	#var_dump($handle);
	if($handle === true)   {
		$zoneon = "true";
	} else {
		$zoneon = "false";
	}
	return $zoneon;
}


/**
 * Recursively filter an array
 *
 * @param array $array
 * @param callable $callback
 *
 * @return array
 */
function array_filter_recursive( array $array, callable $callback = null ) {
    $array = is_callable( $callback ) ? array_filter( $array, $callback ) : array_filter( $array );
    foreach ( $array as &$value ) {
        if ( is_array( $value ) ) {
            $value = call_user_func( __FUNCTION__, $value, $callback );
        }
    }

    return $array;
}


/**
/* Funktion : startlog --> startet logging
/*
/* @param: Name of Log, filename of Log                        
/* @return: 
**/

function startlog($name, $file)   {

require_once "loxberry_system.php";	
require_once "loxberry_log.php";

$params = [	"name" => $name,
				"filename" => LBPLOGDIR."/".$file.".log",
				"append" => 1,
				"addtime" => 1,
				];
$level = LBSystem::pluginloglevel();
$log = LBLog::newLog($params);
LOGSTART($name);
return $name;
}

?>