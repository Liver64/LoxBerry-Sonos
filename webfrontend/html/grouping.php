<?php
# grouping.php

/**
* Function: CreateStereoPair --> creates a StereoPair of 2 Single Zones
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
	// Example: $ChannelMapSet=(RINCON_000E5872D8AC01400:LF,LF;RINCON_000E5872D59801400:RF,RF)
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
		trigger_error("Die angegebenen Zonen sind kein Stereopaar oder in der Syntax vertauscht! Bitte korrigieren", E_USER_ERROR);
	}
 }


	
/**
* Function: CompletionCheckCreate --> check completness of syntax during creation of Stereo Pair
*
* @param:  left Zone, right Zone
* @return: true or false
*/

 function CompletionCheckCreate($lf, $rf){
	global $config, $sonoszone;
	
	// check if member for pairing has been entered
	if (empty($rf)) {
		trigger_error("Bitte die zweite Zone zur Erstellung eines Stereopaares angeben!!", E_USER_ERROR);
	}
	$lfbox = $config['sonoszonen'][$lf][2];
	$rfbox = $config['sonoszonen'][$rf][2];
	// check if zones are not doubled in syntax
	if ($lf == $rf) {
		trigger_error("Der Player ".$rf." kann sich nicht selbst zu ".$lf." hinzufügen. Bitte korrigieren!!", E_USER_ERROR);
	}
		// check if zones are supported devices for pairing
		$lfmod = checkZonePairingAllowed($lfbox);
		if ($lfmod != true) {
			trigger_error("Der Player ".$lf." kann nicht zur Bildung eines Stereopaares genutzt werden, es sind nur PLAY:1, PLAY:3 und PLAY:5 erlaubt. Bitte korrigieren!!", E_USER_ERROR);
		}
			$rfmod = checkZonePairingAllowed($rfbox);
			if ($rfmod != true) {
				trigger_error("Der Player ".$rf." kann nicht zur Bildung eines Stereopaares genutzt werden, es sind nur PLAY:1, PLAY:3 und PLAY:5 erlaubt. Bitte korrigieren!!", E_USER_ERROR);
			}
				// check if both zones are exactly the same model
				if ($lfbox != $rfbox) {
					trigger_error("Der angegebene Player ".$rf." ist nicht der gleiche Typ wie der Player ".$lf.". Es können jeweils nur gleiche Modelle zur Bildung eines Stereopaares genutzt werden. Bitte korrigieren!!", E_USER_ERROR);
				} 
 }
 
 
 /**
* Function: CompletionCheckSeperate --> check completness of syntax during seperation of Stereo Pair
*
* @param:  left Zone, right Zone
* @return: true or false
*/

 function CompletionCheckSeperate($lf, $rf){
	global $config, $sonoszone;
	
	// check if member for pairing has been entered
	if (empty($rf)) {
		trigger_error("Bitte die zweite Zone zur Auflösung eines Stereopaares angeben!!", E_USER_ERROR);
	}
	$lfbox = $config['sonoszonen'][$lf][2];
	$rfbox = $config['sonoszonen'][$rf][2];
	// check if zones are not doubled in syntax
	if ($lf == $rf) {
		trigger_error("Der Player ".$rf." kann sich nicht selbst entfernen. Bitte korrigieren!!", E_USER_ERROR);
	}
 
 }

 
 /**
* Function: checkZonePairingAllowed --> check input if devices been entered are suitable for pairing
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
* Function: getCoordinatorByRoom --> identify the Coordinator for provided room
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
				'Rincon' =>'RINCON_'.explode('RINCON_',(string)$player_data->uuid)[1]
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
		print_r ($coord);
	}
	return $coord;
 }
	


?>