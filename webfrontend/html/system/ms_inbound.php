#!/usr/bin/php
<?php

/**
/* Funktion : erstellt UDP Template für Loxone
/*
/* @param: 
/* @return: 
**/	

require_once "sonosAccess.php";
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "bin/loxberry_loxonetemplatebuilder.php";

register_shutdown_function('shutdown');

global $sonoszonen, $config, $myIP, $LBPCONFIG;
echo '<PRE>';

// Deklaration Variablen
$myFolder = "$lbpconfigdir";
$myIP = LBSystem::get_localip();
$configfile	= "s4lox_config.json";

$params = [	"name" => "Sonos PHP",
			"filename" => "$lbplogdir/sonos.log",
			"append" => 1,
			"addtime" => 1,
			];
$log = LBLog::newLog($params);

LOGSTART("Sonos PHP");	

// laden der config Dateien
if (is_file($lbpconfigdir . "/" . $configfile))    {
	$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
} else {
	LOGCRIT('system/ms_inbound.php: The configuration file could not be loaded, the file may be disrupted. We have to abort :-(');
	exit;
}
#print_r($config);

if ($config['LOXONE']['LoxDaten'] != true)   {
	LOGWARN('system/ms_inbound.php: The Communication to Loxone is switched off, please turn on 1st, save config and try again in order to use the template in Loxone!');
	exit(1);
}
if (count($config['sonoszonen']) < 1)  {
	LOGERR('system/ms_inbound.php: There are no Sonos Players already fully configured, please check/complete your Plugin Config and save your config before downloading your Template!');
	exit(1);
}

if ($config['LOXONE']['LoxDatenMQTT'] != true and empty($config['LOXONE']['LoxPort']))  {
	LOGERR('system/ms_inbound.php: The Loxone UDP port is missing in your config, please check/complete your Plugin Config and save your config before downloading your Template!');
	exit(1);
}
if (empty($config['LOXONE']['Loxone']))  {
	LOGWARN('system/ms_inbound.php: You have not selected appropriate Miniserver for inbound communication, please check/complete your Plugin Config and save your config before downloading your Template!');
	exit(1);
}

if ($config['LOXONE']['LoxDatenMQTT'] === true)   {
	# check MQTT Connection
	if (!is_file($lbhomedir.'/config/plugins/mqttgateway/mqtt.json')) {
		LOGDEB('system/ms_inbound.php: MQTT is either not installed or not activated.');
	} 
}

LOGDEB("system/ms_inbound.php: All Information has been collected successful");

# Filenames
$xmldoc 			= "VI_UDP_Sonos.xml";
$xmlfilename 		= "VI_MQTT_HTTP_Sonos.xml";
$xmlUDPfilename 	= "VI_MQTT_UDP_Sonos.xml";

// prüfen ob Datei existiert, falls ja vorher löschen
if (file_exists($xmldoc)) {
	unlink ($lbphtmldir."/system/".$xmldoc);
	unlink ($lbphtmldir."/system/".$xmlfilename);
	unlink ($lbphtmldir."/system/".$xmlUDPfilename);
	LOGDEB("system/ms_inbound.php: All files has been deleted");
	
}
LOGDEB("system/ms_inbound.php: All Players and commands has been collected. Start writing files");

// Vorbereitung der XML Datei (MQTT HTTP Inbound)
$VIhttp = new VirtualInHttp( [
    "Title" => "Sonos4lox",
    "Address" => "$myIP"
] );
 
foreach ($config['sonoszonen'] as $zone => $key)  {
	$linenr = $VIhttp->VirtualInHttpCmd ( ["Title" => "Volume ".$zone."", "Check" => "Sonos4lox_vol_".$zone.""] );
	$linenr = $VIhttp->VirtualInHttpCmd ( ["Title" => "Playstate ".$zone."", "Check" => "Sonos4lox_stat_".$zone.""] );
	$linenr = $VIhttp->VirtualInHttpCmd ( ["Title" => "Groupstate ".$zone."", "Check" => "Sonos4lox_grp_".$zone.""] );
	$linenr = $VIhttp->VirtualInHttpCmd ( ["Title" => "Mute ".$zone."", "Check" => "Sonos4lox_mute_".$zone.""] );
}
$xml = $VIhttp->output();
// Add BOM to string
$xml = chr(239) . chr(187) . chr(191) . $xml;
file_put_contents($lbphtmldir."/system/".$xmlfilename, $xml);


// Vorbereitung der XML Datei (UDP Inbound)
$text = '<?xml version="1.0" encoding="utf-8"?>';
$text .= "\n" . '<VirtualInUdp Title="Sonos4lox" Comment="" Address="" Port="'.$config['LOXONE']['LoxPort'].'">' . "\r\n";
foreach ($config['sonoszonen'] as $zone1 => $key1)  {
	$text .= '	<VirtualInUdpCmd Title="Volume '.$zone1.'" Comment="" Address="" Check="vol_'.$zone1.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="100"/>' . "\r\n";
	$text .= '	<VirtualInUdpCmd Title="Playstate '.$zone1.'" Comment="" Address="" Check="stat_'.$zone1.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="3"/>' . "\r\n";
	$text .= '	<VirtualInUdpCmd Title="Groupstate '.$zone1.'" Comment="" Address="" Check="grp_'.$zone1.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="3"/>' . "\r\n";
	$text .= '	<VirtualInUdpCmd Title="Mute '.$zone1.'" Comment="" Address="" Check="mute_'.$zone1.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="3"/>' . "\r\n";
}
$text .= '</VirtualInUdp>';
file_put_contents($lbphtmldir."/system/".$xmldoc, $text);


// Vorbereitung der XML Datei (MQTT UDP Inbound)
$VIudp = new VirtualInUdp( [
    "Title" => "Sonos4lox",
    "Address" => "",
    "Port" => $config['Main']['udpport']
] );

foreach ($config['sonoszonen'] as $zone2 => $key2)  { 
	$linenr = $VIudp->VirtualInUdpCmd ( [ "Title" => "Volume ".$zone2."",  "Check" => "MQTT:\iSonos4lox/vol/".$zone2."=\i\\v", "Analog" => true ] );
	$linenr = $VIudp->VirtualInUdpCmd ( [ "Title" => "Playstate ".$zone2."",  "Check" => "MQTT:\iSonos4lox/stat/".$zone2."=\i\\v", "Analog" => true ] );
	$linenr = $VIudp->VirtualInUdpCmd ( [ "Title" => "Groupstate ".$zone2."",  "Check" => "MQTT:\iSonos4lox/grp/".$zone2."=\i\\v", "Analog" => true ] );
	$linenr = $VIudp->VirtualInUdpCmd ( [ "Title" => "Mute ".$zone2."",  "Check" => "MQTT:\iSonos4lox/mute/".$zone2."=\i\\v", "Analog" => true ] );
}

$xmludp = $VIudp->output();
// Add BOM to string
$xmludp = chr(239) . chr(187) . chr(191) . $xmludp;
file_put_contents($lbphtmldir."/system/".$xmlUDPfilename, $xmludp);

LOGOK("system/ms_inbound.php: virtual inputs successfull executed, files temporally saved in '".$lbphtmldir."/system/'");
#return;



function shutdown()
{
	global $log;
	#$log->LOGEND("PHP finished");
	#$log = LOGEND("");
	
}

?>