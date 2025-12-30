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
	
	global $sonoszonen, $sonoszone, $zonen, $debug, $config, $folfilePlOn;
	
	$memberzones = $member;
	
	foreach($memberzones as $zonen) {
		if(!array_key_exists($zonen, $sonoszonen)) {
			LOGGING("helper.php: The entered member zone if Offline, time restricted or does not exist, please correct your syntax!!", 3);
			exit;
		}
	}

	$zonesonline = array();
	LOGGING("sonos.php: Backup Online check for Players will be executed",7);
	foreach($sonoszonen as $zonen => $ip) {
		$handle = file_exists($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			if (array_key_exists($zonen, $sonoszone)) {
				#$sonoszone[$zonen] = $ip;
				array_push($zonesonline, $zonen);
			}
		}
	}
	$member = $zonesonline;
	#print_r($member);
	return($member);
}



/**
/* Function : checkZoneOnline --> Prüft ob einzelner Player Online ist
/*
/* @param:  Player der geprüft werden soll
/* @return: true or nothing
**/

function checkZoneOnline($MemberTest)   {
	
	global $sonoszone, $sonoszonen, $debug, $config, $folfilePlOn;

	if ($MemberTest == 'all')   {
		return false;
	}
	#print_r($MemberTest);
	if(!array_key_exists($MemberTest, $sonoszonen)) {
		LOGERR("helper.php: The entered Zone '".$MemberTest."' does not exist. Please correct your syntax!!");
		exit;
	}
	#if(!array_key_exists($MemberTest, $sonoszone)) {
		#LOGWARN("helper.php: The entered Zone '".$MemberTest."' is Offline or time restriction is maintained. Please correct your syntax!!");
		#return false;
	#}
	$handle = is_file($folfilePlOn."".$MemberTest.".txt");
	if($handle === true) {
		if (array_key_exists($MemberTest, $sonoszone)) {
			$zoneon = true;
			return($zoneon);
		}
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

    global $sonoszone, $master;

    if (MEMBER == "empty") {
        return;
    }

    $masterUID = trim($sonoszone[$master][1]);

    // Array für parallele Handles
    $requests = [];

    // 1) PARALLELE JOIN-REQUESTS anstoßen
    foreach (MEMBER as $zone) {

        if ($zone == $master) {
            continue;
        }

        try {

            $endpoint = $sonoszone[$zone][0];   // IP des Mitglieds

            // SOAP-Body vorbereiten
            $body = '
                <s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"
                            s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
                    <s:Body>
                        <u:SetAVTransportURI xmlns:u="urn:schemas-upnp-org:service:AVTransport:1">
                            <InstanceID>0</InstanceID>
                            <CurrentURI>x-rincon:' . $masterUID . '</CurrentURI>
                            <CurrentURIMetaData></CurrentURIMetaData>
                        </u:SetAVTransportURI>
                    </s:Body>
                </s:Envelope>';

            // CURL parallel vorbereiten
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://".$endpoint.":1400/MediaRenderer/AVTransport/Control");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: text/xml; charset=\"utf-8\"",
                "SOAPAction: \"urn:schemas-upnp-org:service:AVTransport:1#SetAVTransportURI\"",
                "Connection: close"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);

            $requests[] = [
                "zone" => $zone,
                "handle" => $ch
            ];

        } catch (Exception $e) {
            LOGGING("helper.php: Zone '".$zone."' could not be prepared for join", 4);
        }
    }

    // 2) Alle Requests parallel ausführen
    $mh = curl_multi_init();
    foreach ($requests as $req) {
        curl_multi_add_handle($mh, $req["handle"]);
    }

    do {
        $status = curl_multi_exec($mh, $active);
    } while ($active && $status == CURLM_OK);

    // 3) Ergebnisse prüfen
    foreach ($requests as $req) {
        $resp = curl_multi_getcontent($req["handle"]);
        $code = curl_getinfo($req["handle"], CURLINFO_HTTP_CODE);

        if ($code == 200) {
            LOGGING("helper.php: Zone '".$req["zone"]."' joined '".$master."'", 6);
        } else {
            LOGGING("helper.php: Zone '".$req["zone"]."' JOIN FAILED (HTTP $code)", 4);
        }

        curl_multi_remove_handle($mh, $req["handle"]);
        curl_close($req["handle"]);
    }

    curl_multi_close($mh);
}


/**
 * CreateMember()
 *
 * Baut aus member=... (inkl. member=all) die Member-Liste auf,
 * definiert die globale Konstante MEMBER (Array!),
 * definiert GROUPMASTER,
 * und joint alle relevanten Player zum Master.
 *
 * Erweiterungen:
 *  - Nutzt getGroup($master), um die echte Sonos-Topologie zu prüfen.
 *  - Wenn Master + Member bereits exakt als Gruppe existieren (Master ist Koordinator),
 *    werden keine JOIN-Kommandos mehr gesendet.
 *  - Wenn nur einzelne gewünschte Member fehlen, werden nur diese gejoint.
 *  - Schutz: Mehrfachaufrufe mit identischem master/member in *einem* Request
 *    werden erkannt und übersprungen (idempotent).
 */
function CreateMember()
{
    global $master, $sonoszone, $member; // <-- $member explizit global

    // ---------------------------------------------------------------------
    // GUARD: Mehrfachaufrufe im selben Request mit identischem master/member
    //        vermeiden doppelte Verarbeitung und doppeltes Logging.
    // ---------------------------------------------------------------------
    static $alreadyRun       = false;
    static $lastMaster       = null;
    static $lastMemberParam  = null;

    $currentMemberParam = $_GET['member'] ?? '';

    if (
        $alreadyRun === true &&
        $lastMaster === $master &&
        $lastMemberParam === $currentMemberParam
    ) {
        LOGINF("helper.php: CreateMember: Called again with same master/member within one request – skipping (idempotent).");
        return;
    }

    $alreadyRun      = true;
    $lastMaster      = $master;
    $lastMemberParam = $currentMemberParam;

    // --- Master prüfen ----------------------------------------------------
    if (empty($master) || !isset($sonoszone[$master])) {
        LOGERR("helper.php: CreateMember: Master is not set or unknown – aborting grouping.");
        return;
    }

    $masterRincon = $sonoszone[$master][1];

    // --- 1) member= Parameter auslesen ------------------------------------
    if (!isset($_GET['member']) || trim($_GET['member']) === '') {
        LOGWARN("helper.php: CreateMember: No 'member' parameter in URL – nothing to group.");
        return;
    }

    $rawMember = trim($_GET['member']);
    LOGOK("helper.php: Member has been entered");

    $targets = [];

    // --- member=all -> alle bekannten Zonen außer Master ------------------
    if (strtolower($rawMember) === 'all') {
        foreach ($sonoszone as $zone => $zoneData) {
            if ($zone === $master) {
                continue;
            }
            $targets[] = $zone;
        }
        LOGOK("helper.php: All Players will be added to Player: ".$master);
    }
    // --- CSV: member=zone1,zone2,... -------------------------------------
    else {
        $parts = explode(',', $rawMember);
        foreach ($parts as $z) {
            $z = trim($z);
            if ($z === '' || $z === $master) {
                continue;
            }
            if (!isset($sonoszone[$z])) {
                LOGWARN("helper.php: CreateMember: Unknown player '".$z."' in member list – skipped.");
                continue;
            }
            $targets[] = $z;
        }
        LOGOK("helper.php: Selected Players from URL will be added to Player: ".$master);
    }

    // Dubletten entfernen
    $targets = array_values(array_unique($targets));

    if (empty($targets)) {
        LOGWARN("helper.php: CreateMember: No valid members found after filtering – nothing to do.");
        return;
    }

    // --- 2) Online-State prüfen ------------------------------------------
    $finalTargets = [];
    foreach ($targets as $zone) {

        if (function_exists('checkZoneOnline')) {
            if (!checkZoneOnline($zone)) {
                LOGWARN("helper.php: CreateMember: Player '".$zone."' seems to be offline – skipped.");
                continue;
            }
        }

        $finalTargets[] = $zone;
        LOGOK("helper.php: Member '".$zone."' has been prepared to Member array");
    }

    if (empty($finalTargets)) {
        LOGWARN("helper.php: CreateMember: After online check no members remain – aborting.");
        return;
    }

    // --- 3) Globale Member-Liste + Konstante setzen ----------------------
    // Diese Liste wird von restoreGroupZone() benutzt!
    $member = $finalTargets;

    if (!isset($member) || !is_array($member)) {
        $member = [];
    }

    if (!defined('MEMBER')) {
        define("MEMBER", $member);     // Single Source of Truth als Konstante
        LOGINF("helper.php: MEMBER constant defined with ".count($member)." entries.");
    }

    if (!defined('GROUPMASTER')) {
        define("GROUPMASTER", $master);
    }

    // ---------------------------------------------------------------------
    // 3b) Topologie-Check mit getGroup(), um unnötiges Re-Gruppieren
    //     zu vermeiden.
    // ---------------------------------------------------------------------
    $zonesToJoin = $member; // Default: alle Member joinen (altes Verhalten)

    if (function_exists('getGroup')) {
        try {
            $rawGroup = getGroup($master); // [0] => Koordinator, [1..] => weitere Member
        } catch (Exception $e) {
            $rawGroup = [];
        }

        if (!empty($rawGroup)) {

            // Koordinator-Name lt. Topologie (kann vom URL-Master abweichen)
            $coordinatorName = strtolower($rawGroup[0]);

            // Aktuelle Gruppen-Mitglieder normalisieren und auf bekannte Zonen filtern
            $currentGroupNorm = [];
            foreach ($rawGroup as $z) {
                $zLower = strtolower($z);
                if (isset($sonoszone[$zLower])) {
                    $currentGroupNorm[] = $zLower;
                }
            }

            // Gewünschte Konstellation = Master + Member
            $wanted     = array_merge([$master], $member);
            $wantedNorm = array_values(array_unique(array_map('strtolower', $wanted)));

            $sortedCurrent = $currentGroupNorm;
            $sortedWanted  = $wantedNorm;
            sort($sortedCurrent);
            sort($sortedWanted);

            // Logging der aktuellen vs. gewünschten Gruppe
            LOGINF(
                "helper.php: CreateMember: Current group (topology) for '".$master."' = [".
                implode(", ", $currentGroupNorm)."], requested = [".
                implode(", ", $wantedNorm)."]"
            );

            // Nur dann optimieren, wenn unser $master auch wirklich Koordinator ist
            if ($coordinatorName === strtolower($master)) {

                // 3b-1) Perfektes Match: Gruppe ist bereits exakt wie gewünscht
                if ($sortedCurrent === $sortedWanted) {
                    LOGINF("helper.php: CreateMember: Sonos group already matches requested constellation (master + members) – skipping JOIN.");
                    return;
                }

                // 3b-2) Teil-Match: einige Member fehlen noch -> nur fehlende joinen
                $zonesToJoin = [];
                foreach ($member as $z) {
                    if (!in_array(strtolower($z), $currentGroupNorm, true)) {
                        $zonesToJoin[] = $z;
                    }
                }

                if (empty($zonesToJoin)) {
                    LOGINF("helper.php: CreateMember: All requested members are already part of master's group (extra members present) – skipping JOIN.");
                    return;
                }

                LOGINF(
                    "helper.php: CreateMember: Existing group partially matches – will JOIN only missing members: ".
                    implode(", ", $zonesToJoin)
                );
            } else {
                // Master ist aktuell nur Mitglied in einer fremd-koordinierten Gruppe
                LOGINF(
                    "helper.php: CreateMember: Master '".$master.
                    "' is currently member in group of '".$coordinatorName.
                    "' – re-grouping to make '".$master."' master."
                );
                // $zonesToJoin bleibt = $member
            }
        }
    }

    // --- 4) Join-Logik mit Retry -----------------------------------------
    $maxRetries    = 2;
    $retryDelayUs  = 200000; // 200 ms

    foreach ($zonesToJoin as $zone) {
        $ip = $sonoszone[$zone][0];

        $attempt  = 0;
        $success  = false;
        $lastErr  = '';

        while ($attempt < $maxRetries && !$success) {
            $attempt++;

            try {
                $sonos = new SonosAccess($ip);

                // Join der Member-Zone zum Master
                $sonos->SetAVTransportURI("x-rincon:".$masterRincon);

                LOGINF("helper.php: Zone '".$zone."' joined '".$master."' (attempt ".$attempt.")");
                $success = true;

            } catch (Exception $e) {
                $lastErr = $e->getMessage();
                LOGWARN("helper.php: Zone '".$zone."' JOIN FAILED on attempt ".$attempt." (".$lastErr.")");

                if ($attempt < $maxRetries) {
                    usleep($retryDelayUs);
                }
            }
        }

        if (!$success) {
            LOGWARN("helper.php: Zone '".$zone."' JOIN FAILED permanently after ".$maxRetries." attempts (".$lastErr.")");
        }
    }
}


/**
*
* Function : AddMember --> fügt ggf. Member zu Playlist oder Radio hinzu
*
* @param: 	empty
* @return:  create Group
**/
function AddMember() { 

	global $sonoszone, $master, $config, $memberon, $sleepaddmember;

	if(isset($_GET['member'])) {
		$memberraw = $_GET['member'];
		if($memberraw === 'all') {
			$memberon = array();
			foreach ($sonoszone as $zone => $ip) {
				# exclude master Zone
				if ($zone != $master) {
					array_push($memberon, $zone);
				}
			}
		} else {
			$memberon = explode(',', $memberraw);
		}

		$member = member_on($memberon);
		/**
		# check if member is ON and create valid array
		$memberon = array();
		$act_time = date("H:i"); #"16:58"
		foreach ($member as $zone) {
			$zoneon = checkZoneOnline($zone);
			if ($zoneon === (bool)true)   {
				if ($config['SYSTEM']['checkonline'] != false)   {
					# add zones having no time restrictions
					if ($sonoszone[$zone][15] != "" and $sonoszone[$zone][16] != "")   {
						$startime = $sonoszone[$zone][15]; #"07:15"
						$endtime = $sonoszone[$zone][16]; #"20:32"
						if ((string)$startime <= (string)$act_time and (string)$endtime >= (string)$act_time)   {
							array_push($memberon, $zone);
							LOGGING("helper.php: Member '$zone' has been prepared to Member array", 6);		
						} else {
							LOGGING("helper.php: Member '$zone' could not be added to Member array. Maybe Zone is Offline or Time restrictions entered!", 4);	
						}
					} else {
						# add zones having no time restrictions
						array_push($memberon, $zone);
						LOGGING("helper.php: Member '$zone' has been prepared to Member array", 6);	
					}
				} else {
					array_push($memberon, $zone);
					LOGGING("helper.php: Member '$zone' has been prepared to Member array", 6);	
				}
			} else {
				LOGGING("helper.php: Member '$zone' could not be added to Member array. Maybe Zone is Offline or Time restrictions entered!", 4);	
			}
		}
		$member = $memberon;
		**/
		# Define global Constante MEMBER
		if (!defined('MEMBER')) {
			define("MEMBER", $member);
		}
		if (!defined('GROUPMASTER')) {
			define("GROUPMASTER",$master);
		}
		if (!defined('T2SMASTER')) {
			define("T2SMASTER",$master);
		}
		foreach ($member as $zone) {
			$sonos = new SonosAccess($sonoszone[$zone][0]);
			if ($zone != $master)    {
				try {
					$sonos->SetAVTransportURI("x-rincon:" . trim($sonoszone[$master][1])); 
					LOGGING("helper.php: Zone: ".$zone." has been added to master: ".$master,6);
				} catch (Exception $e) {
					LOGGING("helper.php: Zone: ".$zone." could not be added to master: ".$master,4);
				}
				$sonos->SetMute(false);
			}
			usleep((int)($sleepaddmember * 1000000));
		}
		#volume_group();
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
		"S23"   =>  "PORT",
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
 * Function : select_t2s_engine --> includes the configured t2s engine file
 *
 * @param int|null $engineCode  Optional explicit engine code
 *                              (falls null, wird Config-Wert verwendet)
 * @return int  Effektiv verwendeter Engine-Code
 */
function select_t2s_engine(int $engineCode = null): int
{
    global $config;

    if ($engineCode === null || $engineCode === 0) {
        $engineCode = (int)($config['TTS']['t2s_engine'] ?? 0);
    }

    switch ($engineCode) {
        case 1001: // VoiceRSS
            include_once("voice_engines/VoiceRSS.php");
            break;

        case 6001: // ResponsiveVoice
            include_once("voice_engines/ResponsiveVoice.php");
            break;

        case 9012: // Piper
            include_once("voice_engines/Piper.php");
            break;

        case 4001: // Polly
            include_once("voice_engines/Polly.php");
            break;

        case 9001: // MS_Azure
            include_once("voice_engines/MS_Azure.php");
            break;

        case 9011: // ElevenLabs
            include_once("voice_engines/ElevenLabs.php");
            break;

        case 8001: // GoogleCloud
            include_once("voice_engines/GoogleCloud.php");
            break;

        default:
            if (function_exists('LOGERR')) {
                LOGERR("helper.php: select_t2s_engine(): Unknown TTS engine code '$engineCode'.");
            }
            break;
    }

    return $engineCode;
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

	/* --- Create folders (logging only on WARNING/ERROR) --- */

	if (!is_dir($symttsfolder)) {
		$ok = @mkdir($symttsfolder, 0755, true);
		if (!$ok && !is_dir($symttsfolder)) {
			$err = error_get_last();
			$msg = $err['message'] ?? 'unknown error';
			LOGGING("helper.php: ERROR creating folder '".$symttsfolder."': ".$msg, 3);
		}
	}

	if (!is_dir($symmp3folder)) {
		$ok = @mkdir($symmp3folder, 0755, true);
		if (!$ok && !is_dir($symmp3folder)) {
			$err = error_get_last();
			$msg = $err['message'] ?? 'unknown error';
			LOGGING("helper.php: ERROR creating folder '".$symmp3folder."': ".$msg, 3);
		}
	}

	/* --- Symlinks (logging only on WARNING/ERROR) --- */

	$link1 = $myFolder . "/interfacedownload";
	if (!is_link($link1)) {
		$ok = @symlink($symttsfolder, $link1);
		if (!$ok && !is_link($link1)) {
			$err = error_get_last();
			$msg = $err['message'] ?? 'unknown error';
			LOGGING("helper.php: ERROR creating symlink '".$link1."' -> '".$symttsfolder."': ".$msg, 3);
		}
	}

	$link2 = $lbphtmldir . "/interfacedownload";
	if (!is_link($link2)) {
		$ok = @symlink($symttsfolder, $link2);
		if (!$ok && !is_link($link2)) {
			$err = error_get_last();
			$msg = $err['message'] ?? 'unknown error';
			LOGGING("helper.php: ERROR creating symlink '".$link2."' -> '".$symttsfolder."': ".$msg, 3);
		}
	}

	/* --- Copy MP3 folder on first install (log only on WARNING/ERROR) --- */

	if ($copy === true) {
		$src = $myFolder . "/" . $mp3folder;
		$dst = $symcurr_path . "/" . $mp3folder;

		$ok = true;
		try {
			xcopy($src, $dst);
		} catch (Throwable $e) {
			$ok = false;
			LOGGING("helper.php: ERROR copying files from '".$src."' to '".$dst."': ".$e->getMessage(), 3);
		}

		// If xcopy() doesn't throw, but still failed silently:
		if ($ok === false) {
			// already logged above
		} else {
			// no success logging (per your request)
		}
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
	LOGINF ("helper.php: Function 'next' has been executed");
	$currun = $sonos->GetTransportInfo();
	if ($currun != (int)"1")   {
		$sonos->Play();
	}
}



/**
/* Funktion : RampTo --> control volume by diff rampto parameters
/*
/* @param: empty                             
/* @return: 
**/

function RampTo() {
	
	global $sonos, $master, $sonoszone;
	
	if (isset($_GET['rampto']))	   {
		if (isset($_GET['member']))	   {
			LOGGING("helper.php: rampto parameter does not work for member, Volume for member has been set to fixed Volume. Please correct your syntax", 4);
		}
		$rampto = $_GET['rampto'];
		$zero = isset($_GET['zero']);
		if (isset($_GET['volume']))    {
			$volume = $_GET['volume'];
			$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse
			$sonos->SetMute(false);
			if ($rampto == "sleep")  {
				$vol = $zero === true ? $sonos->SetVolume(0) : $sonos->SetVolume($volume);
				$sonos->RampToVolume("SLEEP_TIMER_RAMP_TYPE", $volume);
				LOGGING("helper.php: rampto Parameter '".$rampto."' for Zone '".$master."' has been entered and volume set to: '".$volume."'", 7);
			} elseif ($rampto == "alarm")   {
				$vol = $zero === true ? $sonos->SetVolume(0) : $sonos->SetVolume($volume);
				$sonos->RampToVolume("ALARM_RAMP_TYPE", $volume);
				LOGGING("helper.php: rampto Parameter '".$rampto."' for Zone '".$master."' has been entered and volume set to: '".$volume."'", 7);
			} elseif ($rampto == "auto")   {
				$vol = $zero === true ? $sonos->SetVolume(0) : $sonos->SetVolume($volume);
				$sonos->RampToVolume("AUTOPLAY_RAMP_TYPE", $volume);
				LOGGING("helper.php: rampto Parameter '".$rampto."' for Zone '".$master."' has been entered and volume set to: '".$volume."'", 7);
			} else {
				LOGGING("helper.php: The entered rampto Parameter '".$rampto."' does not exists. Please correct your syntax", 4);
				$volume = $_GET['volume'];
				$sonos->SetVolume($volume);
			}
			if(!isset($_GET['load']))   {
				try {
					$sonos->Play();
				} catch (Exception $e) {
					LOGGING("helper.php: play could not be executed. Please correct your syntax", 3);
				}
			} else {
				LOGINF("helper.php: Parameter 'load' has been used. Please execute play seperatelly");
			}
		} else {
			LOGGING("helper.php: The entered rampto Parameter '".$rampto."' requires volume. Please correct your syntax!", 4);
			$volume = $sonoszone[$master][4];
			$sonos->SetVolume($volume);
			LOGGING("helper.php: As backup the standard Sonos Volume for Zone '".$master."' has been adopted from config without rampto! Please correct your syntax", 4);
			if(!isset($_GET['load']))   {
				try {
					$sonos->Play();
				} catch (Exception $e) {
					LOGGING("helper.php: play could not be executed. Please correct your syntax", 3);
				}
			} else {
				LOGINF("helper.php: Parameter 'load' has been used. Please execute play seperatelly");
			}
		}
	}
	return;
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
	$file = "REPLACELBHOMEDIR/data/plugins/sonos4lox/plugin.cfg";
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
				"S45"   =>  "ARC ULTRA",
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
	#print_r($int);
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
	
	global $folfilePlOn, $config, $sonoszonen, $sonoszone;
		
	$handle = is_file($folfilePlOn."".$zone.".txt");
	
	if($handle === true)   {
		if (array_key_exists($zone, $sonoszone)) {
			$zoneon = "true";
		} else {
			$zoneon = "false";
		}
	} else {
		$zoneon = "false";
	}
	#echo $zoneon;
	return $zoneon;
}


function sonoszonen_on()    {
	
	global $config, $sonoszonen, $folfilePlOn;
	
	// prüft den Onlinestatus jeder Zone
	$sonoszone = array();
	$memberon = array();
	#LOGINF("sonos.php: Online check for Players will be executed");
	$act_time = date("H:i"); #"16:58"
	foreach($sonoszonen as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		#var_dump($handle);
		if($handle === true) {
			if ($config['SYSTEM']['checkonline'] != false)   {
				# add zones having time restrictions
				if ($sonoszonen[$zonen][15] != "" and $sonoszonen[$zonen][16] != "")   {
					$startime = $sonoszonen[$zonen][15]; #"07:15"
					$endtime = $sonoszonen[$zonen][16]; #"20:32"
					if ((string)$startime <= (string)$act_time and (string)$endtime >= (string)$act_time)   {
						$sonoszone[$zonen] = $ip;
					}
				} else {
					# add zones having no time restrictions
					$sonoszone[$zonen] = $ip;
				}
			} else {
				$sonoszone[$zonen] = $ip;
			}
		}
	}
	#print_r($sonoszone);
	return $sonoszone;
}


/* Funktion :  member_on --> add member to array if valid (Online)
/*
/* @param: array
/* @return: array of member
**/

function member_on($memberon)    {
	
	global $config, $member, $members, $master, $memberon, $sonoszonen, $folfilePlOn;

	// prüft den Onlinestatus jeder Zone
	#echo "function 'member_on()'";
	#echo "<br>";
	$member = array();
	$act_time = date("H:i"); #"16:58"
	foreach ($memberon as $zone) {
		$zoneon = checkZoneOnline($zone);
		if ($zone != $master) {
			if ($zoneon === (bool)true)   {
				if ($config['SYSTEM']['checkonline'] != false)   {
					# add zones having no time restrictions
					if ($sonoszonen[$zone][15] != "" and $sonoszonen[$zone][16] != "")   {
						$startime = $sonoszonen[$zone][15]; #"07:15"
						$endtime = $sonoszonen[$zone][16]; #"20:32"
						if ((string)$startime <= (string)$act_time and (string)$endtime >= (string)$act_time)   {
							array_push($member, $zone);
							LOGGING("helper.php: Member '$zone' has been prepared to Member array", 6);		
						} else {
							LOGGING("helper.php: Member '$zone' could not be added to Member array. Maybe Zone is Offline or Time restrictions entered!", 4);	
						}
					} else {
						# add zones having no time restrictions
						array_push($member, $zone);
						LOGGING("helper.php: Member '$zone' has been prepared to Member array", 6);	
					}
				} else {
					array_push($member, $zone);
					LOGGING("helper.php: Member '$zone' has been prepared to Member array", 6);	
				}
			} else {
				LOGGING("helper.php: Member '$zone' could not be added to Member array. Maybe Zone is Offline or Time restrictions entered!", 4);	
			}
		}
	}
	return $member;
}

/**
 * Eemove duplicates from array based on given key
 *
 * @param $array
 * @key key to compare duplicates
 *
 * @return array
 */
 
// https://stackoverflow.com/questions/307674/how-to-remove-duplicate-values-from-a-multi-dimensional-array-in-php
function remove_duplicates_array($array,$key)
    {
		$temp_array = [];
		foreach ($array as &$v) {
			if (!isset($temp_array[$v[$key]]))
			$temp_array[$v[$key]] =& $v;
		}
		$array = array_values($temp_array);
		return $array;
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

function sonosGetZoneGroups(string $anyPlayerIp): array {
    // 1) SOAP: GetZoneGroupState (ein Call, alle Gruppen)
    $soap = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"
            s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
  <s:Body>
    <u:GetZoneGroupState xmlns:u="urn:schemas-upnp-org:service:ZoneGroupTopology:1"/>
  </s:Body>
</s:Envelope>
XML;

    $ch = curl_init("http://{$anyPlayerIp}:1400/ZoneGroupTopology/Control");
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HTTPHEADER      => [
            'Content-Type: text/xml; charset="utf-8"',
            'SOAPACTION: "urn:schemas-upnp-org:service:ZoneGroupTopology:1#GetZoneGroupState"'
        ],
        CURLOPT_POSTFIELDS      => $soap,
        CURLOPT_TIMEOUT         => 2,
        CURLOPT_CONNECTTIMEOUT  => 1,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) throw new RuntimeException("ZGT request failed: ".curl_error($ch));
    curl_close($ch);

    // 2) Outer SOAP → inner XML aus ZoneGroupState extrahieren
    $dom = new DOMDocument();
    $dom->loadXML($resp);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
    $xpath->registerNamespace('u', 'urn:schemas-upnp-org:service:ZoneGroupTopology:1');

    $stateNode = $xpath->query('//u:GetZoneGroupStateResponse/ZoneGroupState')->item(0);
    if (!$stateNode) throw new RuntimeException("No ZoneGroupState in response");
    $zgsXml = $stateNode->nodeValue;

    // 3) Das eigentliche Topology-XML parsen
    $zgs = new SimpleXMLElement($zgsXml);

    $groups = []; // groupId => ['coordinator'=>rincon, 'members'=> [rincon => ['name'=>..., 'ip'=>..., 'location'=>...]]]
    foreach ($zgs->ZoneGroups->ZoneGroup as $zg) {
        $groupId    = (string)$zg['ID'];
        $coordinator= (string)$zg['Coordinator'];
        $groups[$groupId] = ['coordinator'=>$coordinator, 'members'=>[]];

        foreach ($zg->ZoneGroupMember as $m) {
            $uuid     = (string)$m['UUID'];                // RINCON_XXXXXXXXXXXXXX
            $name     = (string)$m['ZoneName'];
            $loc      = (string)$m['Location'];            // http://IP:1400/xml/...
            // IP aus Location schneiden:
            $ip = parse_url($loc, PHP_URL_HOST);

            $groups[$groupId]['members'][$uuid] = [
                'name'     => $name,
                'ip'       => $ip,
                'location' => $loc,
                'satMap'   => (string)$m['HTSatChanMapSet'] ?? null, // nützlich bei Surrounds
            ];
        }
    }
    return $groups;
}

// Optional: IP → RINCON direkt vom Player
function sonosGetRinconByIp(string $ip): ?string {
    $xml = @file_get_contents("http://{$ip}:1400/xml/device_description.xml");
    if (!$xml) return null;
    $sx = new SimpleXMLElement($xml);
    $udn = (string)$sx->device->UDN; // "uuid:RINCON_XXXXXXXXXX01400"
    return preg_match('~uuid:(RINCON_[A-Z0-9]+)~', $udn, $m) ? $m[1] : null;
}

// Beispiel: Gruppe eines bestimmten Players (per IP) holen
function getGroupMembersForPlayerIp(string $anyPlayerIp, string $targetIp): array {
    $groups = sonosGetZoneGroups($anyPlayerIp);
    $rincon = sonosGetRinconByIp($targetIp);
    if (!$rincon) return [];

    foreach ($groups as $g) {
        if (isset($g['members'][$rincon])) {
            return [
                'coordinator' => $g['coordinator'],
                'members'     => array_keys($g['members']) // Liste aller RINCONs
            ];
        }
    }
    return [];
}

/**
 * Update Sonos Event Listener health.json
 *
 * @param array $allRooms      Liste ALLER bekannten Räume (Keys aus $sonoszone z.B.)
 * @param array $onlineRooms   Liste der aktuell online erreichbaren Räume
 * @param array $lastEvents    Assoziatives Array mit Zeitstempeln der letzten Events:
 *                             [
 *                               'avtransport'        => <unix-ts> | null,
 *                               'renderingcontrol'   => <unix-ts> | null,
 *                               'zonegrouptopology'  => <unix-ts> | null,
 *                             ]
 *
 * Aufruf idealerweise NACH einem erfolgreich verarbeiteten Sonos-Event.
 */

if (!function_exists('update_sonos_health')) {
    /**
     * Schreibt eine "leichte" health.json für die Sonos4lox-Web-UI
     * - players.online / players.total
     * - rooms_flags["raum"] => ["Online" => 0|1]
     * - events (AVT/RC/ZGT Timestamps als ISO)
     * KEINE online_rooms/offline_rooms Arrays, KEIN EQ.
     */
    function update_sonos_health(
        array $allRooms,
        array $onlineRooms,
        array $lastEvents = []
    )
    {
        global $lbpconfigdir;

        // Fallback, falls $lbpconfigdir nicht gesetzt ist
        if (empty($lbpconfigdir)) {
            $lbpconfigdir = 'REPLACELBHOMEDIR/config/plugins/sonos4lox';
        }

        $healthFile = $lbpconfigdir . '/health.json';

        $hostname   = trim(`hostname 2>/dev/null`) ?: 'unknown';
        $now        = time();
        $iso        = date('c', $now);
        $pid        = function_exists('getmypid') ? getmypid() : null;

        $total_rooms   = count($allRooms);
        $online_unique = array_values(array_unique($onlineRooms));

        // --- pro Raum Online-Flag aufbauen ---
        $roomsFlags = [];
        foreach ($allRooms as $roomName) {
            $roomsFlags[$roomName] = [
                'Online' => in_array($roomName, $online_unique, true) ? 1 : 0,
            ];
        }

        // Event-Timestamps in ISO wandeln (falls vorhanden)
        $eventsIso = [
            'last_avtransport'       => isset($lastEvents['avtransport']) && $lastEvents['avtransport'] > 0
                ? date('c', (int)$lastEvents['avtransport'])
                : null,
            'last_renderingcontrol'  => isset($lastEvents['renderingcontrol']) && $lastEvents['renderingcontrol'] > 0
                ? date('c', (int)$lastEvents['renderingcontrol'])
                : null,
            'last_zonegrouptopology' => isset($lastEvents['zonegrouptopology']) && $lastEvents['zonegrouptopology'] > 0
                ? date('c', (int)$lastEvents['zonegrouptopology'])
                : null,
        ];

        $data = [
            'sonos-event-listener' => [
                'service'   => 'sonos_event_listener',
                'hostname'  => $hostname,
                'pid'       => $pid,
                'timestamp' => $now,
                'iso_time'  => $iso,
                'players'   => [
                    'online' => count($online_unique),
                    'total'  => $total_rooms,
                ],
                // wichtig für deine UI: rooms_RAUM_Online
                'rooms_flags' => $roomsFlags,
                'events'      => $eventsIso,
            ],
        ];

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            if (function_exists('LOGGING')) {
                LOGGING("helper.php: Failed to encode health.json: " . json_last_error_msg(), 4);
            }
            return;
        }

        if (!is_dir($lbpconfigdir)) {
            @mkdir($lbpconfigdir, 0775, true);
        }

        $tempFile = $healthFile . '.tmp';

        if (file_put_contents($tempFile, $json) === false) {
            if (function_exists('LOGGING')) {
                LOGGING("helper.php: Failed to write temporary health file '$tempFile'", 4);
            }
            return;
        }

        if (!@rename($tempFile, $healthFile)) {
            if (function_exists('LOGGING')) {
                LOGGING(
                    "helper.php: Failed to move temporary health file to '$healthFile'", 4);
            }
            return;
        }

        @chmod($healthFile, 0664);

        if (function_exists('LOGGING')) {
            $onlineCnt = $data['sonos-event-listener']['players']['online'];
            LOGGING("helper.php: Updated Sonos Event Listener health.json (pid $pid, online $onlineCnt/$total_rooms)",6);
        }
    }
}


/**
 * SyncGroupForPlaybackToMember()
 *
 * Wird für persistente Playback-Aktionen (pluginradio, playfavorite, …) verwendet.
 *
 * - Liest $_GET['member'] und den globalen $master
 * - Bildet die gewünschte Gruppe: master + member-Zonen
 * - Entfernt Zonen, die aktuell in der Gruppe sind, aber NICHT gewünscht sind
 * - joint fehlende gewünschte Member zum Master
 *
 * T2S bleibt unberührt, solange diese Funktion dort NICHT aufgerufen wird.
 */
function SyncGroupForPlaybackToMember()
{
    global $master, $sonoszone, $member;

    // 1) member= vorhanden?
    if (!isset($_GET['member']) || trim($_GET['member']) === '') {
        // kein member-Parameter → nichts zu tun
        return;
    }

    if (empty($master) || !isset($sonoszone[$master])) {
        LOGERR("helper.php: Master is not set or unknown – aborting.");
        return;
    }

    // 2) Gewünschte Member-Liste aus member=... bauen
    $wantedMembers = array();
    $rawMember     = trim($_GET['member']);

    if (strtolower($rawMember) === 'all') {
        // alle bekannten Zonen außer Master
        foreach ($sonoszone as $zone => $data) {
            if (strcasecmp($zone, $master) === 0) {
                continue;
            }
            $wantedMembers[] = $zone;
        }
    } else {
        $parts = explode(',', $rawMember);
        foreach ($parts as $z) {
            $z = trim($z);
            if ($z === '') {
                continue;
            }
            if (!isset($sonoszone[$z])) {
                LOGWARN("helper.php: Unknown zone '$z' in member list – skipped.");
                continue;
            }
            if (strcasecmp($z, $master) === 0) {
                // Master nicht als Member führen
                continue;
            }
            $wantedMembers[] = $z;
        }
    }

    // Duplikate entfernen
    $wantedMembers = array_values(array_unique($wantedMembers));

    if (empty($wantedMembers)) {
        LOGINF("helper.php: Only master requested – nothing to group.");
        // Trotzdem MEMBER leeren, damit volume_group() nichts versucht
        $member = array();
        if (!defined('MEMBER')) {
            define('MEMBER', $member);
        }
        return;
    }

    // 3) Master aus seiner bisherigen Gruppe lösen
    try {
        $sMaster = new SonosAccess($sonoszone[$master][0]);
        $sMaster->BecomeCoordinatorOfStandaloneGroup();
        LOGINF("helper.php: Master '$master' became standalone group.");
    } catch (Exception $e) {
        LOGWARN("helper.php: Could not set master '$master' standalone: ".$e->getMessage());
    }

    // 4) Gewünschte Member zum Master joinen
    $masterRincon = $sonoszone[$master][1];

    foreach ($wantedMembers as $zone) {

        if (!isset($sonoszone[$zone])) {
            continue;
        }

        try {
            $zSonos = new SonosAccess($sonoszone[$zone][0]);
            $zSonos->SetAVTransportURI("x-rincon:" . $masterRincon);
            LOGINF("helper.php: Zone '$zone' joined master '$master'.");
        } catch (Exception $e) {
            LOGWARN("helper.php: Failed to join '$zone' to '$master': ".$e->getMessage());
        }
    }

    // 5) MEMBER-Array + Konstante setzen (für volume_group())
    $member = $wantedMembers;

    if (!defined('MEMBER')) {
        define('MEMBER', $member);
    }

    LOGINF("helper.php: MEMBER set to [".implode(', ', $member)."].");
}


/**
/* Funktion : startlog --> startet logging
/*
/* @param: Name of Log, filename of Log                        
/* @return: 


function startlog($name, $file)   {

#require_once "loxberry_system.php";	
#require_once "loxberry_log.php";

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
**/

?>