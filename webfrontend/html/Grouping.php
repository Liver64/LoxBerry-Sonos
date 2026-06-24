<?php

/**
* Submodul: Grouping
* Version: LOG_NORMALIZATION_V01_2026_06_19
*
**/

/**
* Function: CreateStereoPair --> creates a StereoPair of 2 Single Zones
* Example: $ChannelMapSet=(RINCON_000E5872D8AC01400:LF,LF;RINCON_000E5872D59801400:RF,RF)
*
* @param:  empty
* @return: a pair of 2 Single Zones
*/

function CreateStereoPair() {
	global $config, $sonos, $sonoszone;
	
	$lf = $_GET['zone']; // is automatically Master
	$rf = $_GET['pair'];
	$check = CompletionCheckCreate($lf, $rf);
	$rinconlf = $sonoszone[$lf][1];
	$rinconrf = $sonoszone[$rf][1];
	$ChannelMapSet = (string)$rinconlf.':LF,LF;'.$rinconrf.':RF,RF';
	$sonos = new SonosAccess($sonoszone[$lf][0]);
	$temp = $sonos->CreateStereoPair($ChannelMapSet);
	LOGOK('Grouping.php: Stereo pair for zone '.$lf.' has been created.');
}


/**
* Function: SeperateStereoPair --> split an existing Stereo Pair into Single Zones
*
* @param:  empty
* @return: 2 Single Zones
*/
	
 function SeperateStereoPair() {
	global $config, $sonos, $sonoszone;
	
	$lf = $_GET['zone']; // is automatically Master
	$rf = $_GET['pair'];
	$check = CompletionCheckSeperate($lf, $rf);
	$rinconlf = $sonoszone[$lf][1];
	$rinconrf = $sonoszone[$rf][1];
	$ChannelMapSet = (string)$rinconlf.':LF,LF;'.$rinconrf.':RF,RF';
	try {
		$temp = $sonos->SeparateStereoPair($ChannelMapSet);
	} catch (Exception $e) {
		LOGWARN("Grouping.php: The specified zones are not a stereo pair or the zone syntax is reversed. Please check the request.");
		LOGWARN("Grouping.php: SeparateStereoPair failed: " . $e->getMessage());
		exit;
	}
	LOGOK('Grouping.php: Stereo pair ('.$lf.', '.$rf.') has been separated into single zones.');
 }


	
/**
* Subfunction: CompletionCheckCreate --> check completness of syntax during creation of Stereo Pair
*
* @param:  left Zone, right Zone
* @return: true or false
*/

 function CompletionCheckCreate($lf, $rf){
	global $config, $sonoszone;
	
	// check if member for pairing has been entered
	if (empty($rf)) {
		LOGWARN("Grouping.php: Please specify the second zone for creating a stereo pair.");
		exit;
	}
	$lfbox = $sonoszone[$lf][2];
	$rfbox = $sonoszone[$rf][2];
	// check if zones are not doubled in syntax
	if ($lf == $rf) {
		LOGWARN("Grouping.php: Zone ".$rf." cannot be added to itself. Please correct the request.");
		exit;
	}
		// check if zones are supported devices for pairing
		$lfmod = checkZonePairingAllowed($lfbox);
		if ($lfmod != true) {
			LOGWARN("Grouping.php: Zone ".$lf." cannot be used to create a stereo pair. Only PLAY:1, PLAY:3 and PLAY:5 are allowed.");
			exit;
		}
			$rfmod = checkZonePairingAllowed($rfbox);
			if ($rfmod != true) {
				LOGWARN("Grouping.php: Zone ".$rf." cannot be used to create a stereo pair. Only PLAY:1, PLAY:3 and PLAY:5 are allowed.");
				exit;
			}
				// check if both zones are exactly the same model
				if ($lfbox != $rfbox) {
					LOGWARN("Grouping.php: Zone ".$rf." is not the same model as zone ".$lf.". Only matching models can be used to create a stereo pair.");
					exit;
				} 
 }
 
 
 /**
* Subfunction: CompletionCheckSeperate --> check completness of syntax during seperation of Stereo Pair
*
* @param:  left Zone, right Zone
* @return: true or false
*/

 function CompletionCheckSeperate($lf, $rf){
	global $config, $sonoszone;
	
	// check if member for pairing has been entered
	if (empty($rf)) {
		LOGWARN("Grouping.php: Please specify the second zone to separate the stereo pair.");
		exit;
	}
	$lfbox = $sonoszone[$lf][2];
	$rfbox = $sonoszone[$rf][2];
	// check if zones are not doubled in syntax
	if ($lf == $rf) {
		LOGWARN("Grouping.php: Zone ".$rf." cannot remove itself. Please correct the request.");
		exit;
	}
 
 }

 
 /**
* Subfunction: checkZonePairingAllowed --> check input if devices been entered are suitable for pairing
*
* @param:  $model --> Devices
* @return: true or false
*/
 
 function checkZonePairingAllowed($model) {
		
    $models = [
        "PLAY:1"    		=>  "PLAY:1",
        "PLAY:3"    		=>  "PLAY:3",
        "PLAY:5"    		=>  "PLAY:5",
		"SYMFONISK LAMP"   	=>  "SYMFONISK LAMP",
		"SYMFONISK WALL"   	=>  "SYMFONISK WALL",
		"ONE"    			=>  "ONE",
		"SYMFONISK"			=>  "SYMFONISK",
		"ROAM"				=>  "ROAM",
		"MOVE"				=>  "MOVE",
		"FIVE"				=>  "FIVE",
        ];
    return in_array($model, array_keys($models));
}

 
// NOT IN USE - aufgrund des Wegfalles von ststus/topology nicht mehr nutzbar

/**
* Function: getRoomCoordinator --> identify the Coordinator for provided room (typically for StereoPair)
*
* @param:  $room
* @return: array of (0) IP address and (1) Rincon-ID of Master
*/

function getRoomCoordinator_OLD($room){
	global $sonoszone, $zone, $debug, $master, $sonosclass, $config, $time_start;
		print_r($room);
		#$room = $master;
		if(!$xml=deviceCmdRaw('/status/topology')){
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
			$player = array(
				'Host' =>"$ip",
				'Master' =>((string)$player_data->coordinator == 'true'),
				'Rincon' =>'RINCON_'.explode('RINCON_',(string)$player_data->uuid)[1],
				'Sonos Name' => utf8_encode($room)
			);
			$coordinators[$room][] = $player;
		}
		
 function cnp($a, $b) {
	if ($a['Master'] == $b['Master']) {
		if($a['Sonos Name'] == $b['Sonos Name']) 
			return 0;
		else 
			return ($a['Sonos Name'] > $b['Sonos Name']) ? 1 : -1;;
		}
		return ($a['Master'] === TRUE) ? -1 : 1;
	}
	foreach ($coordinators as $key=>$coordinator){
		usort($coordinators[$key], "cnp");
	}
	// search for room in topology
	$zonename = recursive_array_search(trim($sonoszone[$master][1]),$coordinators);
	#print_r($zonename);
	$ipadr = $coordinators[$zonename][0]['Host'];
	$rinconid = $coordinators[$zonename][0]['Rincon'];
	$coord = array($ipadr, $rinconid); 
	if($debug == 1) { 
		echo 'Group Coordinator-IP: '.$coord[0].'<br><br>';
	}
	return $coord;
 }
 
 
 /**
* Function: getRoomCoordinator --> identify the Coordinator for provided room (typically for StereoPair)
*
* @param:  $room
* @return: array of (0) IP address and (1) Rincon-ID of Master
*/
 function getRoomCoordinator($room){
	 
	global $sonoszonen, $zone, $debug, $master, $sonosclass, $config, $time_start;
	
	$coord = array($sonoszonen[$room][0], $sonoszonen[$room][1]);
	#print_r($coord);
	return $coord;
 }
 
 
 /**
* Function: getCoordinator --> identify the Coordinator for provided room (typically for StereoPair)
*
* @param:  $room
* @return: room name
*/
 function getCoordinator($room){
	 
	global $sonoszone, $zone, $debug, $master, $sonosclass, $config, $time_start;
	
	$sonos = new SonosAccess($sonoszone[$room][0]);
	$roomcheck = $sonos->GetZoneGroupAttributes($room);
	$roomrincon = explode(",", $roomcheck["CurrentZonePlayerUUIDsInGroup"]);
	$roomcord = recursive_array_search($roomrincon[0],$sonoszone);

	return $roomcord;
 }
 

/**
 * Neue getGroup() — nutzt die echte Sonos ZoneGroupTopology
 * Rückgabe:
 *   [0] => master (Zonenname, lowercase)
 *   [1] => member1
 *   [2] => member2
 *   ...
 */
function getGroup(string $zonename)
{
    global $sonoszone;

    // Zone bekannt?
    if (!isset($sonoszone[$zonename])) {
        return [];
    }

    // 1) IP & UUID (RINCON) des angesprochenen Players
    $playerIp    = $sonoszone[$zonename][0];
    $playerRincon = $sonoszone[$zonename][1];

    // 2) Topologie abrufen
    try {
        $groups = sonosGetZoneGroups($playerIp);
    } catch (Exception $e) {
        // Fallback: Single-Zone
        LOGWARN("Grouping.php: getGroup: sonosGetZoneGroups failed for '".$zonename."' (".$e->getMessage().") – fallback to single zone.");
        return [$zonename];
    }
	#echo "Groups Once: ";
	#print_r($groups);

    // 3) Mapping-Tabelle RINCON -> Zonename aufbauen
    $rinconToZone = [];
    foreach ($sonoszone as $name => $data) {
        // $data[1] = RINCON
        $rinconToZone[$data[1]] = $name;
    }

    // 4) Gruppe suchen, in der der Player Member ist
    foreach ($groups as $groupId => $g) {

        if (!isset($g['members'][$playerRincon])) {
            continue;
        }

        // Koordinator-RINCON -> Zonename
        $masterRincon = $g['coordinator'];
        $masterName   = $rinconToZone[$masterRincon] ?? $zonename;

        $masterName = strtolower($masterName);
        $result     = [$masterName]; // erstes Element: master

        // 5) Alle Mitglieder in Zonennamen umwandeln
        foreach ($g['members'] as $rincon => $info) {

            if (!isset($rinconToZone[$rincon])) {
                continue; // unbekannte Zone, z.B. alte Konfigreste
            }

            $mName = strtolower($rinconToZone[$rincon]);

            if ($mName !== $masterName && !in_array($mName, $result, true)) {
                $result[] = $mName;
            }
        }
		#print_r("Result Group: ");
		#print_r($result);
		#echo "<br>";
        return $result;
    }
	#print_r("Result Single: ".[$zonename]);
	#echo "<br>";
    // --- Wenn Player in keiner Gruppe -> Single zone ---
    return [$zonename];
}


/**
* Function for T2S: getGroups --> collect all groups where index (0) is always Group Coordinator
*
* @param: empty
* @return: array of devices from each group
**/

function getGroups() {
	global $sonoszone, $debug;

	foreach ($sonoszone as $room => $value) {
		$group = getGroup($room);
		#if($debug == 1) { 
			print_r($group);
			
		#}
		#return($groups);
	}
	#print_r($groups);
	return($group);
 }
 
 
/**
* Function for T2S: getZoneStatus --> identify Player's current Status: Single Zone, Master or Member of a group
*
* @param: room
* @return: single, member or master
**/

 function getZoneStatus($room) {
	 
	global $sonoszone, $sonos, $grouping, $debug, $config, $time_start;	

	if(empty($room)) {
		$room = $_GET['zone'];
	}
	$sonos = new SonosAccess($sonoszone[$room][0]);
	try {
		$group = $sonos->GetZoneGroupAttributes();
	} catch (Exception $e) {
		$restore = "unknown";
		return $restore;
	}
	$tmp_name = $group["CurrentZoneGroupName"];
	$group = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]);
	if(empty($tmp_name)) {
		$restore = 'member';
	} 
	elseif(!empty($tmp_name) && count($group) > 1) {
		$restore = 'master';
	}
	elseif(!empty($tmp_name) && count($group) < 2) {
		$restore = 'single';
	}
	else {
		$restore = 'unknown';
	}
	if($debug == 2) { 
		echo '<br>';
		echo 'Die angegebene Zone '.$room.' hat den Status '.$restore;
		echo '<br>';
	}
	return $restore;
}



/** --> OBSOLETE
* Function for T2S: checkDeltaArray() --> identify differencies between Group area and current grouping
*
* @param: empty
* @return: array of Delta
**/

function checkDeltaArray() {
	global $member, $master, $sonoszone, $config, $debug;
	
	$master = $_GET['zone'];
	$member = $_GET['member'];
	$member = explode(',', $member);
	// add Master to Array Position 0
	array_unshift($member, $master);
	$group = getGroup($master);
	print_r($group);
	// create delta between arrays and reset key to zero
	if (empty($group)) {
		$delta = $member;
	} else {
		$delta = array_diff($group, $member);
		$delta = array_values($delta);
	}
	$newMaster = "tttt";
	if (!empty($delta)) {
		if($debug == 1) { 
			echo 'member<br>';
			print_r($member);
			echo 'existing group<br>';
			print_r($group);
			echo 'Delta member to group<br>';
			print_r ($delta);
		}
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$newMaster = $delta[0];
		print_r($newMaster);
		return $newMaster;
		#exit;
		$sonos->DelegateGroupCoordinationTo($sonoszone[$delta[0]][1], 1);
	}
	return $newMaster;
}


/**
* Funktion : 	checkifmaster --> prüft ob die Zone aus der Syntax auch der Master ist, falls nicht wird der Gruppenmaster zurückgegeben
*
* @param: $master 
* @return: $master --> neuer master
**/

function checkifmaster($master) {
	
	global $sonoszone, $config, $master;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$posinfo = $sonos->GetPositionInfo();
	if (substr($posinfo["TrackURI"], 0, 9) == "x-rincon:") {
		$GroupMaster = trim(substr($posinfo["TrackURI"], 9, 30));
		$master = recursive_array_search($GroupMaster,$sonoszone);
		#echo $master;
		return $master;
	}      
}


/**
* Funktion : 	group_all --> gruppiert alle vorhanden Zonen
*
* @param: empty
* @return: group
**/

function group_all() {
	global $sonoszone, $config, $master;
	
	# Alle Zonen gruppieren
	foreach ($sonoszone as $zone => $ip) {
		if($zone != $_GET['zone']) {
			$sonos = new SonosAccess($sonoszone[$zone][0]);
			$sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$master][1]); 
		}
	}
	LOGOK('Grouping.php: All Sonos players have been grouped.');
}

	
/**
* Funktion : 	ungroup_all --> seperiert alle Zonen zu Single Player
*
* @param: empty 
* @return: single Zone
**/
		
function ungroup_all() {
	global $sonoszone, $config;
		
		# Alle Zonen Gruppierungen aufheben
		foreach($sonoszone as $zone => $ip) {
			$sonos = new SonosAccess($sonoszone[$zone][0]);
			$sonos->SetQueue("x-rincon-queue:" . $sonoszone[$zone][1] . "#0");
		}
	LOGOK('Grouping.php: All Sonos players have been ungrouped.');
}



/**
* Funktion : 	removemember --> seperiert angegebene Zone aus einer Gruppe
*
* @param: empty 
* @return: single Zone
**/
	
function removemember() {
	global $sonoszone, $config, $master;
	
	$member = $_GET['member'];
	$member = explode(',', $member);
	if (in_array($master, $member)) {
		LOGWARN("Grouping.php: Zone ".$master." cannot be added as member of itself. Please remove '&member=".$master."' from the request.");
	}
	$memberon = array();
	foreach ($member as $value) {
		$zoneon = checkZoneOnline($value);
		if ($zoneon === (bool)true)  {
			array_push($memberon, $value);
		#} else {
			# Legacy debug log removed: player was not part of the group.
		}
	}
	foreach ($memberon as $value) {
		$zoneon = checkZoneOnline($value);
		$masterrincon = $sonoszone[$master][1];
		$sonos = new SonosAccess($sonoszone[$value][0]);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGOK("Grouping.php: Player '".$value."' has been removed from group '".$master."'.");
	}
}




/* ==============================================================================================
 * Compatibility functions relocated from Helper.php
 * Package: HELPER_RELOCATION_LOG_REFERENCES_V02_2026_06_17
 * ----------------------------------------------------------------------------------------------
 * Function names intentionally stay unchanged for URL/code compatibility.
 * ============================================================================================== */

/* === Relocated from Grouping.php: deviceCmdRaw() === */
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

/* === Relocated from Grouping.php: recursive_array_search() === */
function recursive_array_search($needle,$haystack) {
    foreach($haystack as $key=>$value) {
        $current_key=$key;
        if($needle===$value OR (is_array($value) && recursive_array_search($needle,$value) !== false)) {
            return $current_key;
        }
    }
    return false;
}

/* === Relocated from Grouping.php: AddMemberTo() === */
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
            LOGWARN("Grouping.php: Zone '".$zone."' could not be prepared for joining the group.");
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
            LOGOK("Grouping.php: Zone '".$req["zone"]."' joined '".$master."'.");
        } else {
            LOGWARN("Grouping.php: Zone '".$req["zone"]."' join failed (HTTP ".$code.").");
        }

        curl_multi_remove_handle($mh, $req["handle"]);
        curl_close($req["handle"]);
    }

    curl_multi_close($mh);
}

/* === Relocated from Grouping.php: CreateMember() === */
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
        LOGINF("Grouping.php: CreateMember: Called again with same master/member within one request – skipping (idempotent).");
        return;
    }

    $alreadyRun      = true;
    $lastMaster      = $master;
    $lastMemberParam = $currentMemberParam;

    // --- Master prüfen ----------------------------------------------------
    if (empty($master) || !isset($sonoszone[$master])) {
        LOGERR("Grouping.php: CreateMember: Master is not set or unknown – aborting grouping.");
        return;
    }

    $masterRincon = $sonoszone[$master][1];

    // --- 1) member= Parameter auslesen ------------------------------------
    if (!isset($_GET['member']) || trim($_GET['member']) === '') {
        LOGINF("Grouping.php: CreateMember: No 'member' parameter in URL – nothing to group.");
        return;
    }

    $rawMember = trim($_GET['member']);
    LOGOK("Grouping.php: Member has been entered");

    $targets = [];

    // --- member=all -> alle bekannten Zonen außer Master ------------------
    if (strtolower($rawMember) === 'all') {
        foreach ($sonoszone as $zone => $zoneData) {
            if ($zone === $master) {
                continue;
            }
            $targets[] = $zone;
        }
        LOGOK("Grouping.php: All Players will be added to Player: ".$master);
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
                LOGWARN("Grouping.php: CreateMember: Unknown player '".$z."' in member list – skipped.");
                continue;
            }
            $targets[] = $z;
        }
        LOGOK("Grouping.php: Selected Players from URL will be added to Player: ".$master);
    }

    // Dubletten entfernen
    $targets = array_values(array_unique($targets));

    if (empty($targets)) {
        LOGWARN("Grouping.php: CreateMember: No valid members found after filtering – nothing to do.");
        return;
    }

    // --- 2) Online-State prüfen ------------------------------------------
    $finalTargets = [];
    foreach ($targets as $zone) {

        if (function_exists('checkZoneOnline')) {
            if (!checkZoneOnline($zone)) {
                LOGWARN("Grouping.php: CreateMember: Player '".$zone."' seems to be offline – skipped.");
                continue;
            }
        }

        $finalTargets[] = $zone;
        LOGOK("Grouping.php: Member '".$zone."' has been prepared to Member array");
    }

    if (empty($finalTargets)) {
        LOGWARN("Grouping.php: CreateMember: After online check no members remain – aborting.");
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
        LOGINF("Grouping.php: MEMBER constant defined with ".count($member)." entries.");
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
                "Grouping.php: CreateMember: Current group (topology) for '".$master."' = [".
                implode(", ", $currentGroupNorm)."], requested = [".
                implode(", ", $wantedNorm)."]"
            );

            // Nur dann optimieren, wenn unser $master auch wirklich Koordinator ist
            if ($coordinatorName === strtolower($master)) {

                // 3b-1) Perfektes Match: Gruppe ist bereits exakt wie gewünscht
                if ($sortedCurrent === $sortedWanted) {
                    LOGINF("Grouping.php: CreateMember: Sonos group already matches requested constellation (master + members) – skipping JOIN.");
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
                    LOGINF("Grouping.php: CreateMember: All requested members are already part of master's group (extra members present) – skipping JOIN.");
                    return;
                }

                LOGINF(
                    "Grouping.php: CreateMember: Existing group partially matches – will JOIN only missing members: ".
                    implode(", ", $zonesToJoin)
                );
            } else {
                // Master ist aktuell nur Mitglied in einer fremd-koordinierten Gruppe
                LOGINF(
                    "Grouping.php: CreateMember: Master '".$master.
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

                LOGINF("Grouping.php: Zone '".$zone."' joined '".$master."' (attempt ".$attempt.")");
                $success = true;

            } catch (Exception $e) {
                $lastErr = $e->getMessage();
                LOGWARN("Grouping.php: Zone '".$zone."' JOIN FAILED on attempt ".$attempt." (".$lastErr.")");

                if ($attempt < $maxRetries) {
                    usleep($retryDelayUs);
                }
            }
        }

        if (!$success) {
            LOGWARN("Grouping.php: Zone '".$zone."' JOIN FAILED permanently after ".$maxRetries." attempts (".$lastErr.")");
        }
    }
}

/* === Relocated from Grouping.php: GetZoneState() === */
function GetZoneState()    {

	global $sonos;
	
	require_once __DIR__ . '/src/Support/Xml/XmlToArray.php';
	
	$xml = $sonos->GetZoneStates();
	# https://github.com/vyuldashev/xml-to-array/tree/master
	$array = XmlToArray::convert($xml);
	#print_r($array);
	$interim = isset($array['ZoneGroupState']['ZoneGroups']['ZoneGroup'])
		? $array['ZoneGroupState']['ZoneGroups']['ZoneGroup']
		: array();
	$final = array();

	# A single ZoneGroup is returned as an associative array, multiple ZoneGroups as a list.
	if (isset($interim['attributes'])) {
		$interim = array($interim);
	}

	foreach($interim as $key)     {
		if (!is_array($key) || !isset($key['ZoneGroupMember'])) {
			continue;
		}

		$members = $key['ZoneGroupMember'];
		# A single member is returned as an associative array, multiple members as a list.
		if (isset($members['attributes'])) {
			$members = array($members);
		}

		if (!is_array($members)) {
			continue;
		}

		foreach ($members as $member) {
			if (!is_array($member) || !isset($member['Satellite'])) {
				continue;
			}

			$satellites = $member['Satellite'];
			# A single satellite is returned as an associative array, multiple satellites as a list.
			if (isset($satellites['attributes'])) {
				$satellites = array($satellites);
			}

			if (!is_array($satellites)) {
				continue;
			}

			foreach($satellites as $key1)      {
				if (!is_array($key1)
					|| !isset($key1['attributes']['HTSatChanMapSet'])
					|| $key1['attributes']['HTSatChanMapSet'] === '') {
					continue;
				}
				array_push($final, substr($key1['attributes']['HTSatChanMapSet'], -2));
			}
		}
	}

	# remove empty values, remove duplicate values and re-index array
	$zoneson = array_unique(array_values(array_filter($final)));
	#print_r($zoneson);
	$subwoofer = recursive_array_search('SE',$zoneson);
	if ($subwoofer === false ? $sub = "false" : $sub = "true");
	echo $sub;
	return $zoneson;

}

/* === Relocated from Grouping.php: member_on() === */
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
							LOGOK("Grouping.php: Member '$zone' has been prepared for the member array.");		
						} else {
							LOGWARN("Grouping.php: Member '$zone' could not be added to the member array. The zone may be offline or blocked by time restrictions.");	
						}
					} else {
						# add zones having no time restrictions
						array_push($member, $zone);
						LOGOK("Grouping.php: Member '$zone' has been prepared for the member array.");	
					}
				} else {
					array_push($member, $zone);
					LOGOK("Grouping.php: Member '$zone' has been prepared for the member array.");	
				}
			} else {
				LOGWARN("Grouping.php: Member '$zone' could not be added to the member array. The zone may be offline or blocked by time restrictions.");	
			}
		}
	}
	return $member;
}

/* === Relocated from Grouping.php: sonosGetZoneGroups() === */
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

/* === Relocated from Grouping.php: SyncGroupForPlaybackToMember() === */
function SyncGroupForPlaybackToMember()
{
    global $master, $sonoszone, $member;

    // 1) member= vorhanden?
    if (!isset($_GET['member']) || trim($_GET['member']) === '') {
        // kein member-Parameter → nichts zu tun
        return;
    }

    if (empty($master) || !isset($sonoszone[$master])) {
        LOGERR("Grouping.php: Master is not set or unknown – aborting.");
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
                LOGWARN("Grouping.php: Unknown zone '$z' in member list – skipped.");
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
        LOGINF("Grouping.php: Only master requested – nothing to group.");
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
        LOGINF("Grouping.php: Master '$master' became standalone group.");
    } catch (Exception $e) {
        LOGWARN("Grouping.php: Could not set master '$master' standalone: ".$e->getMessage());
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
            LOGINF("Grouping.php: Zone '$zone' joined master '$master'.");
        } catch (Exception $e) {
            LOGWARN("Grouping.php: Failed to join '$zone' to '$master': ".$e->getMessage());
        }
    }

    // 5) MEMBER-Array + Konstante setzen (für volume_group())
    $member = $wantedMembers;

    if (!defined('MEMBER')) {
        define('MEMBER', $member);
    }

    LOGINF("Grouping.php: MEMBER set to [".implode(', ', $member)."].");
}

?>