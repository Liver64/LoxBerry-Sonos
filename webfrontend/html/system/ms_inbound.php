#!/usr/bin/php
<?php

/**
/* Funktion : erstellt UDP Template für Loxone
/*
/* @param: 
/* @return: 
**/	

include("PHPSonos.php");
require_once "loxberry_system.php";
require_once "loxberry_log.php";

global $sonoszonen, $config, $myIP;
#echo '<PRE>';

// Deklaration Variablen
$myFolder = "$lbpconfigdir";
$myIP = $_SERVER["SERVER_ADDR"];

// laden der config Dateien
if (!file_exists($myFolder.'/sonos.cfg')) {
	LOGERR('The file sonos.cfg could not be opened, please try again!');
} else {
	$tmpconfig = parse_ini_file($myFolder.'/sonos.cfg', true);
}
if (!file_exists($myFolder.'/player.cfg')) {
	LOGERR('The file player.cfg  could not be opened, please try again!');
} else {
	$tmpplayer = parse_ini_file($myFolder.'/player.cfg', true);
}

$xmldoc = "VIU_Sonos_UDP.xml";

// prüfen ob Datei existiert, falls ja vorher löschen
if (file_exists($xmldoc)) {
	unlink ($xmldoc);
}
$fh = fopen($xmldoc, 'a+');

// Vorbereitung der XML Datei
$text = '<?xml version="1.0" encoding="utf-8"?>';
$text .= "\n" . '<VirtualInUdp Title="Sonos4lox" Comment="by Sonos4lox" Address="" Port="'.$tmpconfig['LOXONE']['LoxPort'].'">' . "\r\n";
foreach ($tmpplayer['SONOSZONEN'] as $zone => $key)  {
	$text .= '	<VirtualInUdpCmd Title="Sonos: Volume '.$zone.'" Comment="" Address="" Check="vol_'.$zone.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="100"/>' . "\r\n";
	$text .= '	<VirtualInUdpCmd Title="Sonos: Playstate '.$zone.'" Comment="" Address="" Check="stat_'.$zone.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="3"/>' . "\r\n";
	$text .= '	<VirtualInUdpCmd Title="Sonos: Groupstate '.$zone.'" Comment="" Address="" Check="grp_'.$zone.'@\v" Signed="true" Analog="true" SourceValLow="0" DestValLow="0" SourceValHigh="100" DestValHigh="100" DefVal="0" MinVal="0" MaxVal="3"/>' . "\r\n";
}
$text .= '</VirtualInUdp>';

#$fh = fopen($xmldoc, +a);
fwrite($fh, $text);
fclose($fh);
LOGINF("virtual input executed");

#file_put_contents($xmldoc, $text);

?>