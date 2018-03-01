<?php

/**
* Submodul: Alarm
*
**/


/**
* Function: turn_off_alarms --> turns off all Sonos alarms
*
* @param: empty
* @return: disabled alarms
**/
function turn_off_alarms() {
	global $sonos, $sonoszone, $master, $home, $psubfolder;
	
	$filename = $home.'/webfrontend/html/plugins/'.$psubfolder.'/tmp_alarms.json';
	if (file_exists($filename)) {
		LOGGING("Sonos alarms could not be disabled! A file already exists, please delete before executing or run action=alarmon.", 3);
		exit;
	}
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$alarm = $sonos->ListAlarms();
	File_Put_Array_As_JSON($filename, $alarm);
	$quan = count($alarm);
	for ($i=0; $i<$quan; $i++) {
		$sonos->UpdateAlarm($alarm[$i]['ID'], $alarm[$i]['StartTime'], $alarm[$i]['Duration'], $alarm[$i]['Recurrence'], 
		$alarm[$i]['Enabled'] = 0, $alarm[$i]['RoomUUID'], $alarm[$i]['ProgramURI'], $alarm[$i]['ProgramMetaData'], 
		$alarm[$i]['PlayMode'], $alarm[$i]['Volume'], $alarm[$i]['IncludeLinkedZones']);
	}
	LOGGING("All Sonos alarms has been turned off.", 6);
}


/**
* Function: restore_alarms --> turns on all previous saved Sonos alarms
*
* @param: empty
* @return: disabled alarms
**/
function restore_alarms() {
	global $sonos, $sonoszone, $home, $psubfolder, $alarm;
	
	$filename = $home.'/webfrontend/html/plugins/'.$psubfolder.'/tmp_alarms.json';
	if (!file_exists($filename)) {
		LOGGING("Sonos alarms could not be restored! There is no file available to restore.", 3);
		exit;
	}
	$alarm = File_Get_Array_From_JSON($filename);
	$quan = count($alarm);
	for ($i=0; $i<$quan; $i++) {
		$sonos->UpdateAlarm($alarm[$i]['ID'], $alarm[$i]['StartTime'], $alarm[$i]['Duration'], $alarm[$i]['Recurrence'], 
		$alarm[$i]['Enabled'], $alarm[$i]['RoomUUID'], $alarm[$i]['ProgramURI'], $alarm[$i]['ProgramMetaData'], 
		$alarm[$i]['PlayMode'], $alarm[$i]['Volume'], $alarm[$i]['IncludeLinkedZones']);
	}
	unlink($filename); 
	LOGGING("All Sonos alarms has been switched on again.", 6);
}



/**
* Function: sleeptimer --> setzt einen Sleeptimer
*
* @param: empty
* @return: 
**/
function sleeptimer() {
	
	if(isset($_GET['timer']) && is_numeric($_GET['timer']) && $_GET['timer'] > 0 && $_GET['timer'] < 60) {
		$timer = $_GET['timer'];
		if($_GET['timer'] < 10) {
			$timer = '00:0'.$_GET['timer'].':00';
		} else {
			$sonos = new PHPSonos($sonoszone[$master][0]);
			$timer = '00:'.$_GET['timer'].':00';
			$timer = $sonos->Sleeptimer($timer);
		}
		LOGGING("Sleeptimer has been switched on. Time to sleep is: ".$timer, 6);
	} else {
		LOGGING('The entered time is not correct, please correct', 4);
	}
}

?>