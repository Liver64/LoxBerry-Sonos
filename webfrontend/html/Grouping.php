<?php

/**
* Submodul: Grouping
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
	LOGGING('grouping.php: A Stereo Pair called '.$lf.' has been created',6);
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
		$temp = $sonos->SeperateStereoPair($ChannelMapSet);
	} catch (Exception $e) {
		LOGGING("grouping.php: The specified zones are not a stereopair or the zones in syntax is reversed! Please correct", 3);
		exit;
	}
	LOGGING('grouping.php: A Stereo Pair ('.$lf.', '.$rf.') has been seperated into Single Zones.',6);
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
		LOGGING("grouping.php: Please specify the second zone for creating a stereopair!!", 3);
		exit;
	}
	$lfbox = $config['sonoszonen'][$lf][2];
	$rfbox = $config['sonoszonen'][$rf][2];
	// check if zones are not doubled in syntax
	if ($lf == $rf) {
		LOGGING("grouping.php: The Zone ".$rf." can not be added itself to ".$lf.". Please correct!!", 3);
		exit;
	}
		// check if zones are supported devices for pairing
		$lfmod = checkZonePairingAllowed($lfbox);
		if ($lfmod != true) {
			LOGGING("grouping.php: The Zone ".$lf." can't be used to create a stereopair, only PLAY: 1, PLAY: 3 and PLAY: 5 are allowed. Please correct!!", 3);
			exit;
		}
			$rfmod = checkZonePairingAllowed($rfbox);
			if ($rfmod != true) {
				LOGGING("grouping.php: The Zone ".$rf." can't be used to create a stereopair, only PLAY: 1, PLAY: 3 and PLAY: 5 are allowed. Please correct!!!!", 3);
				exit;
			}
				// check if both zones are exactly the same model
				if ($lfbox != $rfbox) {
					LOGGING("grouping.php: The entered Zone ".$rf." isn't the same type as zone ".$lf.". Only the same models can be used to create a stereopair. Please correct!!", 3);
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
		LOGGING("grouping.php: Please enter the second zone to seperate the stereopair!!", 3);
		exit;
	}
	$lfbox = $config['sonoszonen'][$lf][2];
	$rfbox = $config['sonoszonen'][$rf][2];
	// check if zones are not doubled in syntax
	if ($lf == $rf) {
		LOGGING("grouping.php: The Zone ".$rf." can't remove itself. Please correct!!", 3);
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
* Subfunction: getGroup --> collect all Zones if device is part of a group
*
* @param:  $room
* @return: array of rooms from a group where index (0) is always the Coordinator
*/ 

 function getGroup($room = "") {
	 
	global $sonoszone, $sonos, $grouping, $debug, $config;	
	
	if($room == "") {
		$room = $_GET['zone'];
	}
	#print_r($sonoszone[$room][0]);
	$sonos = new SonosAccess($sonoszone[$room][0]);
	$group = $sonos->GetZoneGroupAttributes();
	$tmp_name = $group["CurrentZoneGroupName"];
	($group = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]));
	$grouping = array();
	
	if(!empty($tmp_name)) {
		if(count($group) > 1) {
			foreach ($group as $zone) {
				$zone = recursive_array_search($zone, $config['sonoszonen']);
				array_push($grouping,$zone);
			}
		}
	}
	if(!empty($grouping)) {
		#if($debug == 1) { 
			#print_r($grouping);
			#echo 'OLLI';
		#}
		#print_r($grouping);
		return $grouping;
	} else {
		return false;
	}
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
		$groups[] = getGroup($room);
		#if($debug == 1) { 
			print_r($groups);
			
		#}
		#return($groups);
	}
	print_r($groups);
	#return($groups);
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
		$sonos->DelegateGroupCoordinationTo($config['sonoszonen'][$delta[0]][1], 1);
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
		echo $master;
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
			$sonos->SetAVTransportURI("x-rincon:" . $config['sonoszonen'][$master][1]); 
		}
	}
	LOGGING('grouping.php: All Sonos Player has been grouped',6);
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
			$sonos->SetQueue("x-rincon-queue:" . $config['sonoszonen'][$zone][1] . "#0");
		}
	LOGGING('grouping.php: All Sonos Player has been ungrouped',6);
}


/**
* OBSOLTE: (replaced by helper.php AddMemberTo()
*
* Funktion : 	addmember --> fügt angegebene Zone einer Gruppe hinzu
*
* @param: empty 
* @return: Gruppe
**/

function addmember() {
	
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
			if ($zone != $master)   {
				try {
					$sonos->SetAVTransportURI("x-rincon:" . trim($sonoszone[$master][1])); 
					LOGGING("helper.php: Zone: ".$zone." has been added to master: ".$master,6);
				} catch (Exception $e) {
					LOGGING("helper.php: Zone: ".$zone." could not be added to master: ".$master,4);
				}
			}
		}
	}
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
		LOGGING("grouping.php: The zone ".$master." could not be entered as member again. Please remove from Syntax '&member=".$master."' !", 4);
	}
	$memberon = array();
	foreach ($member as $value) {
		$zoneon = checkZoneOnline($value);
		if ($zoneon === (bool)true)  {
			array_push($memberon, $value);
		#} else {
			#LOGGING("grouping.php: Player '".$value."' wasn't part of the Group!!", 4);
		}
	}
	foreach ($memberon as $value) {
		$zoneon = checkZoneOnline($value);
		$masterrincon = $config['sonoszonen'][$master][1];
		$sonos = new SonosAccess($sonoszone[$value][0]);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGGING("grouping.php: Player '".$value."' has been removed from Group '".$master."'",6);
	}
}


?>