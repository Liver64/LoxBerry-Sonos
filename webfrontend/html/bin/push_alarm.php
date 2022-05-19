#!/usr/bin/env php
<?php

require_once("loxberry_system.php");
require_once "loxberry_log.php";
require_once "loxberry_io.php";
require_once("$lbphtmldir/system/sonosAccess.php");
require_once("$lbphtmldir/Helper.php");
require_once("$lbphtmldir/system/logging.php");
require_once "$lbphtmldir/bin/phpmqtt/phpMQTT.php";
require_once("$lbphtmldir/system/io-modul.php");
include("$lbphtmldir/bin/binlog.php");

$configfile	= "s4lox_config.json";
echo '<PRE>';

$alarm_off_file 	= $lbplogdir."/s4lox_alarm_off.tmp";			// path/file for Alarms turned off
$off_file 			= $lbplogdir."/s4lox_off.tmp";					// path/file for Script turned off

	# check if script/Sonos Plugin is off
	if (file_exists($off_file)) {
		exit;
	}
	# check if alarms are turned off
	if (file_exists($alarm_off_file)) {
		exit;
	}

	# Laden der Konfigurationsdatei
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$tmpsonos = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		binlog("Push data", "/bin/push_alarm.php: The configuration file could not be loaded! We skip here :-(");
		exit(1);
	}
	
	# check if Data transmission is switched off
	if(!is_enabled($tmpsonos['LOXONE']['LoxDaten'])) {
		exit;
	}
		
	$mem_sendall = 0;
	$mem_sendall_sec = 3600;
	
	# get MS
	$ms = LBSystem::get_miniservers();
	
	// ********************** Get the MQTT Gateway connection details from LoxBerry *****************************************
	if(is_enabled($tmpsonos['LOXONE']['LoxDatenMQTT'])) {
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
	# create variable
	$sonoszonen = $tmpsonos['sonoszonen'];

	# select one player by random
	$selectOneRandom = array_rand($sonoszonen, 1);
	
	# check if player is Online
	$zoneon = checkZoneOnline($selectOneRandom);
	if ($zoneon === false)   {
		return false;
	}
	# get list of alarms
	$sonos = new SonosAccess($sonoszonen[$selectOneRandom][0]);
	$allAlarms = $sonos->ListAlarms();
	# check if alarms are maintained
	if (count($allAlarms) < 1)    {
		exit;
	}
	# add Minutes past Midnight to array
	foreach ($allAlarms as $key => $value)    {
		$ex = explode(":", $value['StartTime']);
		# calculate Mintues after midnight - 10 Minutes
		$result = (($ex[0] * 60) + $ex[1]) - 10 ;
		$allAlarms[$key]['minpastmid'] = $result;
	}
	# add Room Name to array
	foreach ($allAlarms as $key => $value)    {
		$rinc = $value['RoomUUID'];
		$search = recursive_array_search($rinc, $sonoszonen);
		if ($search === false)    {
			$allAlarms[$key]['Room'] = "NO ROOM";
		} else {
			$allAlarms[$key]['Room'] = $search;
		}
	}
	# create necessary array to send details
	$tmp_array = array();
	foreach ($allAlarms as $key)    {
		$tmp_array["min_".$key['Room']."_ID_".$key['ID']] = $key['minpastmid'];
		$tmp_array["stat_".$key['Room']."_ID_".$key['ID']] = $key['Enabled'];
	}
	# get MS Details
	$no_ms = $tmpsonos['LOXONE']['Loxone'];
	$server_port = $tmpsonos['LOXONE']['LoxPort'];
	# send UDP to MS
	if ($mqttstat == "0")   {
		$response = udp_send_mem($no_ms, $server_port, "Sonos4lox", $tmp_array);
	}
	# send to MQTT
	if ($mqttstat == "1")   {
		foreach ($tmp_array as $key => $value)   {
			$mqtt->publish("Sonos4lox/".$key, $value, 0, 1);;
		}
	$mqtt->close();	
	}
	#print_r($tmp_array);
    #sprint_r($allAlarms);
	

?>