<?php

/**
/* Funktion : sendUDP --> sendet Statusdaten per UDP
/*
/* @param: string $value                             
/* @return: 
**/

function sendUDP($value, $name)    {
	
	global $lbphtmldir, $config;
	
	require_once("$lbphtmldir/system/io-modul.php");
	require_once("loxberry_io.php");
	
	$mem_sendall = 0;
	$mem_sendall_sec = 3600;
	
	$tmp_array = array();
	$server_port = $config['LOXONE']['LoxPort'];
	$no_ms = $config['LOXONE']['Loxone'];
	$tmp_array[$name] = $value;	
	
	$response = udp_send_mem($no_ms, $server_port, "Sonos4lox", $tmp_array);
	return;
}


/**
/* Funktion : sendMQTT --> sendet Statusdaten per MQTT
/*
/* @param: string $value                             
/* @return: 
**/

function sendMQTT($value, $name)    {
	
	global $lbphtmldir;
	
	require_once "loxberry_io.php";
	require_once "$lbphtmldir/bin/phpmqtt/phpMQTT.php";
	require_once "$lbphtmldir/system/io-modul.php";
	
	# Get the MQTT Gateway connection details from LoxBerry
	$creds = mqtt_connectiondetails();
	# MQTT requires a unique client id
	$client_id = uniqid(gethostname()."_client");
	$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'],  $creds['brokerport'], $client_id);
	$mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass']);
	$mqtt->publish('Sonos4lox/'.$name.'', $value, 0, 1);
	return;
}
?>