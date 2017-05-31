<?php
# grouping.php

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
	$sonos = new PHPSonos($sonoszone[$lf][0]);
	$temp = $sonos->CreateStereoPair($ChannelMapSet);
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
		trigger_error("The specified zones are not a stereoparar or the syntax is reversed! Please correct", E_USER_ERROR);
	}
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
		trigger_error("Please specify the second zone for creating a stereopair!!", E_USER_ERROR);
	}
	$lfbox = $config['sonoszonen'][$lf][2];
	$rfbox = $config['sonoszonen'][$rf][2];
	// check if zones are not doubled in syntax
	if ($lf == $rf) {
		trigger_error("The Zone ".$rf." can not be added itself to ".$lf.". Please correct!!", E_USER_ERROR);
	}
		// check if zones are supported devices for pairing
		$lfmod = checkZonePairingAllowed($lfbox);
		if ($lfmod != true) {
			trigger_error("The Zone ".$lf." can't be used to create a stereopair, only PLAY: 1, PLAY: 3 and PLAY: 5 are allowed. Please correct!!", E_USER_ERROR);
		}
			$rfmod = checkZonePairingAllowed($rfbox);
			if ($rfmod != true) {
				trigger_error("The Zone ".$rf." can't be used to create a stereopair, only PLAY: 1, PLAY: 3 and PLAY: 5 are allowed. Please correct!!!!", E_USER_ERROR);
			}
				// check if both zones are exactly the same model
				if ($lfbox != $rfbox) {
					trigger_error("The entered Zone ".$rf." isn't the same type as zone ".$lf.". Only the same models can be used to create a stereopair. Please correct!!", E_USER_ERROR);
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
		trigger_error("Please enter the second zone to seperate the stereopair!!", E_USER_ERROR);
	}
	$lfbox = $config['sonoszonen'][$lf][2];
	$rfbox = $config['sonoszonen'][$rf][2];
	// check if zones are not doubled in syntax
	if ($lf == $rf) {
		trigger_error("The Zone ".$rf." can't remove itself. Please correct!!", E_USER_ERROR);
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
        "PLAY:1"    =>  "PLAY:1",
        "PLAY:3"    =>  "PLAY:3",
        "PLAY:5"    =>  "PLAY:5",
        ];
    return in_array($model, array_keys($models));
}

 

/**
* Function: getRoomCoordinator --> identify the Coordinator for provided room (typically for StereoPair)
*
* @param:  $room
* @return: array of (0) IP address and (1) Rincon-ID of Master
*/

function getRoomCoordinator($room){
	global $sonoszone, $zone, $debug, $master, $sonosclass, $config;
		
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
	$zonename = recursive_array_search($config['sonoszonen'][$master][1],$coordinators);
	$ipadr = $coordinators[$zonename][0]['Host'];
	$rinconid = $coordinators[$zonename][0]['Rincon'];
	$coord = array($ipadr, $rinconid); 
	if($debug == 1) { 
		echo 'Group Coordinator-IP: ';
		print_r ($coord[0]);
		echo '<br><br>';
	}
	#print_r($coord[0]);
	return $coord;
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
	$sonos = new PHPSonos($sonoszone[$room][0]);
	$group = $sonos->GetZoneGroupAttributes();
	$tmp_name = $group["CurrentZoneGroupName"];
	$group = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]);
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
		if($debug == 1) { 
			#print_r($grouping);
		}
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
		if($debug == 1) { 
			#print_r($groups);
			
		}
		#return($groups);
	}
	#print_r($groups);
	return($groups);
 }
 
 
/**
* Function for T2S: getZoneStatus --> identify Player's current Status: Single Zone, Master or Member of a group
*
* @param: room
* @return: single, member or master
**/

 function getZoneStatus($room) {
	global $sonoszone, $sonos, $grouping, $debug, $config;	
	
	if(empty($room)) {
		$room = $_GET['zone'];
	}
	$sonos = new PHPSonos($sonoszone[$room][0]);
	$group = $sonos->GetZoneGroupAttributes();
	$tmp_name = $group["CurrentZoneGroupName"];
	$group = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]);
	if(empty($tmp_name)) {
		$restore = 'member';
	} 
	if(!empty($tmp_name) && count($group) > 1) {
		$restore = 'master';
	}
	if(!empty($tmp_name) && count($group) < 2) {
		$restore = 'single';
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
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$newMaster = $delta[0];
		print_r($newMaster);
		return $newMaster;
		#exit;
		$sonos->DelegateGroupCoordinationTo($config['sonoszonen'][$delta[0]][1], 1);
	}
	return $newMaster;
}

?>