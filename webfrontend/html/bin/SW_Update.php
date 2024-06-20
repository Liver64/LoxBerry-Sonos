<?php

require_once "loxberry_system.php";
require_once($lbphtmldir."/system/sonosAccess.php");
require_once($lbphtmldir."/Helper.php");
require_once($lbphtmldir."/bin/communication_ms.php");

ini_set('max_execution_time', 2000); 	
register_shutdown_function('shutdown');

# declare general variables
$configfile		= "s4lox_config.json";
$folfilePlOn 	= "$lbpdatadir/PlayerStatus/s4lox_on_";
$Stunden 		= date("H");
$day 			= date("w");	//  0 for Sunday til 6 Saturday

echo "<PRE>";

# load config.json
if (file_exists($lbpconfigdir . "/" . $configfile))    {
	$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
} else {
	$logname = startlog("Sonos Software Update", "update");
	LOGERR("bin/SW_Update.php: The configuration file could not be loaded, the file may be disrupted. We have to abort...");
	exit;
}

# declare function variables
$hw_update 			= $config['SYSTEM']['hw_update'];
$hw_update_time 	= $config['SYSTEM']['hw_update_time'];
$hw_update_power 	= $config['SYSTEM']['hw_update_power'];
$hw_update_day 		= $config['SYSTEM']['hw_update_day'];
$updatefile 		= "/run/shm/Sonos4lox_update.json";

# for Testing only
#$hw_update 			= "true";
#$hw_update_day 		= "5";		// weekday
#$hw_update_time 	= "14";		// hour
#$hw_update_power 	= "true";	// if Power On is requested
#$Stunden 			= "14";
#$day 				= "5";

# check 1st if Software Update is turned On and scheduled
if ((is_enabled($hw_update) and $hw_update_time == $Stunden and $hw_update_day == $day) or (is_enabled($hw_update) and $hw_update_time == $Stunden and $hw_update_day == "10"))    {

	$logname = startlog("Sonos Software Update", "update");
	LOGOK("bin/SW_Update.php: Run Updatecheck for Players");
	
	# extract Sonoszonen only
	$sonoszonen = $config['sonoszonen'];
	
	# check Zones Online Status
	$sonoszone = checkZonesOn();
	# ++++++++++++++++++++++++++++++++
	# if Power On is turned on
	# ++++++++++++++++++++++++++++++++
	
	if (is_enabled($hw_update_power))    {
		// send power on trigger
		$send = send("1");
		# wait 5 minutes until Zones are up
		LOGDEB("bin/SW_Update.php: We wait ~7 Minutes until all Players are Online...");
		sleep(400);
		# Prepare Zones are Online
		require_once($lbphtmldir."/bin/check_on_state.php");
		# check again Zones Online Status
		$sonoszone = checkZonesOn();
		sleep(20);
		file_put_contents($updatefile, json_encode("1", JSON_PRETTY_PRINT));
	}

	$count = 0;
	$countmajor = 0;
	# get Software Update from major player(s)
	foreach($sonoszone as $zone => $ip) {
		if (is_enabled($sonoszone[$zone][6]))    {
			$countmajor++;
			try {
				$sonos = new SonosAccess($sonoszone[$zone][0]);
				$update = $sonos->CheckForUpdate();
				LOGOK("bin/SW_Update.php: Updatecheck for Player '".$zone."' executed. Actual Version is: 'v".$update['version']."' Build: '".$update['build']."'");
			} catch (Exception $e) {
				#if (is_enabled($hw_update_power))    {
				#	$send = send("0");
				#}
				LOGWARN("bin/SW_Update.php: Updatecheck could not be executed");
				exit;
			}
		}
	}

	if ($countmajor == 0)  {
		LOGERR("bin/SW_Update.php: Updatecheck could not be executed. Please check if min. 1 Player is marked for T2S and this Player is Online too!");
		#if (is_enabled($hw_update_power))    {
		#	$send = send("0");
		#}
		exit;
	}
	# execute check if Update needed
	$updateneed = array();
	foreach($sonoszone as $zone => $ip) {
		$info = json_decode(file_get_contents('http://' . $sonoszone[$zone][0] . ':1400/info'), true);
		$vers = $info['device']['softwareVersion'];
		if (!is_null($update['build']))   {
			# for Testing only
			#$vers = '78.1-51069';
			if ($vers != $update['build'])  {
				LOGINF("bin/SW_Update.php: Update for Player '".$zone."' required. Current Version is: '".$vers."' and will be updated to: '".$update['build']."'");
				#notify( LBPPLUGINDIR, "Sonos4lox", "New FW Version available");
				array_push($updateneed, $zone);
				$count++;
			} else {
				LOGDEB("bin/SW_Update.php: Update for Player '".$zone."' is not required. Current Version: '".$vers."' is the most actual");
			}
		}
	}

	# Log Player info Offline
	if (!empty($zonesoffline))   {
		$off = implode(", ", $zonesoffline);
		LOGWARN("bin/SW_Update.php: Updatecheck for Player '".$off."' could not be executed, may be they are Offline");
	}
	
	# if updated req. then execute
	if ($count > 0)  {
		foreach($updateneed as $key) {
			$sonos = new SonosAccess($sonoszone[$key][0]);
			#$update = $sonos->BeginSoftwareUpdate($update['updateurl']);
			sleep(1);
		}
		LOGDEB("bin/SW_Update.php: We wait 10 Minutes until all players were updated...");
		sleep(800);
		LOGOK("bin/SW_Update.php: Update for Playes Online finished successful.");
	} else {
		LOGDEB("bin/SW_Update.php: No update for Player Online are required.");
	}
}


/**
/* Funktion : checkZonesOn() --> prüft Onlinestatus der Player
/*
/* @param:                              
/* @return: 
**/

function checkZonesOn()    {
	
	global $sonoszonen, $sonoszone, $zonesonline, $zonesoffline, $folfilePlOn;

	$zonesonline = array();
	$zonesoffline = array();
	foreach($sonoszonen as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			$sonoszone[$zonen] = $ip;
			array_push($zonesonline, $zonen);
		} else {
			array_push($zonesoffline, $zonen);
		}
	}
	return $sonoszone;
}



/**
/* Funktion : send --> sendet Statusdaten entweder per MQTT oder UDP
/*
/* @param: string $value                             
/* @return: 
**/

function send($value)    {
	
	global $config, $send;
	
	// check if Data transmission is switched off
	if(!is_enabled($config['LOXONE']['LoxDaten'])) {
		LOGERR("bin/SW_Update.php: You have turned on Auto Update and marked Power-On before Update, but Communication to Loxone is switched off. Please turn on!!");
		notify( LBPPLUGINDIR, "Sonos4lox", "You have turned on Auto Update and marked Power-On before Update, but Communication to Loxone is switched off. Please turn on!!", 1);
		exit;
	}
	if(is_enabled($config['LOXONE']['LoxDatenMQTT'])) {
		sendMQTT($value, 'update');
		$value == "1" ? $val = "On" : $val = "Off";
		LOGINF("bin/SW_Update.php: Power ".$val." has been send to MS via MQTT");
		return $value;
	} else {
		sendUDP($value, 'update');
		$value == "1" ? $val = "On" : $val = "Off";
		LOGINF("bin/SW_Update.php:: Power ".$val." has been send to MS via UDP");
		return $value;
	}
}


function shutdown()
{
	global $logname, $send, $updatefile;
	
	# if Power on was requested send power off
	if ($send == "1")    {
		$send = send("0");
	}
	@unlink($updatefile);
	LOGEND($logname);
}
?>