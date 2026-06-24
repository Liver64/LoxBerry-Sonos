<?php

/**
* Submodul: Alarm
* Version: LOG_NORMALIZATION_V01_2026_06_19
*
**/


/**
* Function: turn_off_alarms --> turns off all Sonos alarms
*
* @param: empty
* @return: disabled alarms
**/
function turn_off_alarms() {
	global $sonoszone, $master, $psubfolder, $home, $alarm_off_file;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$alarm = $sonos->ListAlarms();
	file_put_contents($alarm_off_file, json_encode($alarm, JSON_PRETTY_PRINT));
	$quan = count($alarm);
	for ($i=0; $i<$quan; $i++) {
		$sonos->UpdateAlarm($alarm[$i]['ID'], $alarm[$i]['StartTime'], $alarm[$i]['Duration'], $alarm[$i]['Recurrence'], 
		$alarm[$i]['Enabled'] = 0, $alarm[$i]['RoomUUID'], $alarm[$i]['ProgramURI'], $alarm[$i]['ProgramMetaData'], 
		$alarm[$i]['PlayMode'], $alarm[$i]['Volume'], $alarm[$i]['IncludeLinkedZones']);
	}
	LOGOK("Alarm.php: All Sonos alarms have been turned off.");
}


/**
* Function: restore_alarms --> turns on all previous saved Sonos alarms
*
* @param: empty
* @return: disabled alarms
**/
function restore_alarms() {
	global $sonos, $sonoszone, $psubfolder, $home, $master, $alarm_off_file;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	if (file_get_contents($alarm_off_file) === false)   {
		LOGERR("Alarm.php: Sonos alarms could not be restored because the backup file could not be opened.");
		exit;
	} else {
		$alarm = json_decode(file_get_contents($alarm_off_file), TRUE);
	}
	$quan = count($alarm);
	for ($i=0; $i<$quan; $i++) {
		$sonos->UpdateAlarm($alarm[$i]['ID'], $alarm[$i]['StartTime'], $alarm[$i]['Duration'], $alarm[$i]['Recurrence'], 
		$alarm[$i]['Enabled'], $alarm[$i]['RoomUUID'], $alarm[$i]['ProgramURI'], $alarm[$i]['ProgramMetaData'], 
		$alarm[$i]['PlayMode'], $alarm[$i]['Volume'], $alarm[$i]['IncludeLinkedZones']);
	}
	@unlink($alarm_off_file);
	LOGOK("Alarm.php: Previously saved Sonos alarms have been restored.");
		
}



/**
* Function: sleeptimer --> setzt einen Sleeptimer
*
* @param: empty
* @return: 
**/
function sleeptimer() {
	
	global $sonoszone, $master;
	
	if(isset($_GET['timer']) && is_numeric($_GET['timer']) && $_GET['timer'] > 0 && $_GET['timer'] <= 120) {
		$timer = $_GET['timer'];
		if($_GET['timer'] < 10) {
			$hours = "00";
			$minutes = "0".$_GET['timer'];
			$seconds = "00";
		} else if ($_GET['timer'] > 60) {
			$hours = "0".intval($timer / 60);
			$minutes = intval($timer % 60);
			if ($minutes < 10)  {
				$minutes = "0".$minutes;
			} else {
				$minutes;
			}
			$seconds = "00";
		} else {
			$hours = "00";
			$minutes = $_GET['timer'];
			$seconds = "00";
		}
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->SetSleeptimer($hours, $minutes, $seconds);
		LOGOK("Alarm.php: Sleep timer has been switched on. Time to sleep for zone '".$master."' is '".$timer."' minute(s).");
	} else {
		LOGWARN('Alarm.php: The entered time is not correct. Please use minutes between 1 and 120.');
	}
}


/**
* Function: delay --> verzögert die Ausführung des Scriptes
*
* @param: empty
* @return: 
**/
function delay() {
	
	global $sonoszone, $master;
	
	if(isset($_GET['wait']) && is_numeric($_GET['wait']) && $_GET['wait'] > 0 && $_GET['wait'] <= 900) {
		$timer = $_GET['wait'];
		$sonos = new SonosAccess($sonoszone[$master][0]);
		sleep($timer);
		LOGOK("Alarm.php: Delay for zone '".$master."' finished after '".$timer."' second(s).");
	} else {
		LOGWARN('Alarm.php: The entered delay is not correct. Please use seconds between 1 and 900.');
	}
}


/**
* Function: turn_off_alarm --> disable specific Sonos alarms
*
* @param: empty
* @return: disable alarm
**/
function turn_off_alarm() {
	
	global $master, $sonoszone, $lbpdatadir, $psubfolder, $home, $single_alarm_off;
	
	$alarmid = $_GET['id'];
	$single_alarm_off = $lbpdatadir."/s4lox_alarm_ID_".$alarmid."_off.json";				// path/file for specific Alarm turned off
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$alarm = $sonos->ListAlarms();
	foreach ($alarm as $key => $value)    {
		$rinc = $value['RoomUUID'];
		$search = recursive_array_search($rinc, $sonoszone);
		if ($search === false)    {
			$alarm[$key]['Room'] = "NO ROOM";
		} else {
			$alarm[$key]['Room'] = $search;
		}
	}
	$alarmi = str_replace(' ','',$alarmid); 
	$alarmarr = explode(',', $alarmi);
	foreach ($alarmarr as $alarmid)  {
		$arrid = recursive_array_search($alarmid, $alarm);
		if ($arrid === false) {
			LOGWARN("Alarm.php: The entered alarm ID 'ID=".$alarmid."' seems to be invalid. Please run '...action=listalarms' in the browser and check your syntax.");
			continue;
		}
		$sonos->UpdateAlarm($alarm[$arrid]['ID'], $alarm[$arrid]['StartTime'], $alarm[$arrid]['Duration'], $alarm[$arrid]['Recurrence'], 
		$alarm[$arrid]['Enabled'] = 0, $alarm[$arrid]['RoomUUID'], ($alarm[$arrid]['ProgramURI']), ($alarm[$arrid]['ProgramMetaData']), 
		$alarm[$arrid]['PlayMode'], $alarm[$arrid]['Volume'], $alarm[$arrid]['IncludeLinkedZones']);
		LOGOK("Alarm.php: Sonos alarm ID '".$alarmid."' for player '".$alarm[$arrid]['Room']."' has been turned off.");
	}
	#print_r($alarm);
	file_put_contents($single_alarm_off, json_encode($alarm, JSON_PRETTY_PRINT));
	LOGOK("Alarm.php: Alarm backup file has been saved.");
}


/**
* Function: restore_alarm --> enable specific Sonos alarms
*
* @param: empty
* @return: enable alarm
**/
function restore_alarm() {
	
	global $sonoszone, $psubfolder, $lbpdatadir, $home, $master, $single_alarm_off;
	
	$alarmid = $_GET['id'];
	$single_alarm_off = $lbpdatadir."/s4lox_alarm_ID_".$alarmid."_off.json";				// path/file for specific Alarm turned off
	if (!file_exists($single_alarm_off))   {
		LOGERR("Alarm.php: File for alarm ID '".$alarmid."' does not exist. The request was aborted.");
		exit(1);
	}
	$alarm = json_decode(file_get_contents($single_alarm_off), TRUE);
	$sonos = new SonosAccess($sonoszone[$master][0]);
	#$alarm = $sonos->ListAlarms();
	$alarmi = str_replace(' ','',$alarmid); 
	$alarmarr = explode(',', $alarmi);
	foreach ($alarmarr as $alarmid)  {
		$arrid = recursive_array_search($alarmid, $alarm);
		if ($arrid === false) {
			LOGWARN("Alarm.php: The entered alarm ID 'ID=".$alarmid."' seems to be invalid. Please run '...action=listalarms' in the browser and check your syntax.");
			continue;
		}
		$sonos->UpdateAlarm($alarm[$arrid]['ID'], $alarm[$arrid]['StartTime'], $alarm[$arrid]['Duration'], $alarm[$arrid]['Recurrence'], 
		$alarm[$arrid]['Enabled'] = 1, $alarm[$arrid]['RoomUUID'], ($alarm[$arrid]['ProgramURI']), ($alarm[$arrid]['ProgramMetaData']), 
		$alarm[$arrid]['PlayMode'], $alarm[$arrid]['Volume'], $alarm[$arrid]['IncludeLinkedZones']);
		LOGOK("Alarm.php: Sonos alarm ID '".$alarmid."' for player '".$alarm[$arrid]['Room']."' has been enabled.");
	}
	#print_r($alarm);
	@unlink($single_alarm_off);
}



?>