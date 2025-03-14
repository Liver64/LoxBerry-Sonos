#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "loxberry_io.php";
require_once("$lbphtmldir/system/sonosAccess.php");
require_once("$lbphtmldir/system/logging.php");
require_once("$lbphtmldir/Helper.php");
require_once("$lbphtmldir/Play_T2S.php");
require_once("$lbphtmldir/Grouping.php");
require_once("$lbphtmldir/Restore_T2S.php");
require_once("$lbphtmldir/Save_T2S.php");
require_once("$lbphtmldir/Speaker.php");
require_once("$lbphtmldir/voice_engines/GoogleCloud.php");
require_once("$lbphtmldir/system/bin/openssl_file.class.php");
require_once("$lbphtmldir/bin/binlog.php");
require_once("$lbphtmldir/bin/phpmqtt/phpMQTT.php");

$pathlanguagefile 	= "$lbphtmldir/voice_engines/langfiles";		// get languagefiles
$configfile			= "s4lox_config.json";							// configuration file
$off_file 			= "$lbplogdir/s4lox_off.tmp";					// path/file for Script turned off
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";			// Folder and file name for Player Status
$Stunden 			= intval(strftime("%H"));
$sPassword 			= 'loxberry';

# check if script/Sonos Plugin is off
if (file_exists($off_file)) {
	exit;
}

# Execute Cronjob manually
# sh /etc/cron.d/Sonos

ini_set('max_execution_time', 30); 		
register_shutdown_function('shutdown');
$ms = LBSystem::get_miniservers();

echo "<PRE>";

# only between 8am till 21pm
if ($Stunden >=8 && $Stunden <22)   {
	global $master, $main, $lbpconfigdir, $zone, $ms, $batlevel, $configfile, $folfilePlOn, $sonoszone, $config;
	
	# load Player Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
		exit;
	}
	$sonoszonen = ($config['sonoszonen']);
	
	# check ONLINE Status of each zone
	foreach($sonoszonen as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			$sonoszone[$zonen] = $ip;
		}
	}
	#print_r($sonoszone);
	
	foreach ($sonoszone as $zone => $player)   {
		$ip = $sonoszone[$zone][0];
		# check for battery devices
		$url = "http://".$ip.":1400/status/batterystatus";
		$xml = simpleXML_load_file($url);
		@$batlevel = $xml->LocalBatteryStatus->Data[1];
		@$batlevel = $batlevel[0];
		if ($batlevel === NULL)   {
			continue;
		}
		#$temperature = $xml->LocalBatteryStatus->Data[2];
		#$health = $xml->LocalBatteryStatus->Data[0];
		$PowerSource = $xml->LocalBatteryStatus->Data[3];
		$PowerSource = $PowerSource[0];
		# check only if battery devices are currently not charging and battery level is less then 20%
		if ($PowerSource == "BATTERY" && $batlevel <= 20)  {
		#if ($batlevel <= 20)  {
						
			# Start Logging
			$params = [	"name" => "Cronjobs",
						"filename" => "$lbplogdir/sonos.log",
						"append" => 1,
						"stderr" => 1,
						"addtime" => 1,
						];
			$log = LBLog::newLog($params);
			LOGSTART("Check Battery state");

			LOGWARN('system/battery.php: The battery level of "'.$zone.'" is about '.$batlevel.'%. Please charge your device!');
		}
	}
}


/** - OBSOLETE -
* Funktion : 	select_lang --> wählt die Sprache der error message aus 
*
* @param: empty
* @return: translations form error.json file
**/

function select_lang() {
	
	global $config, $pathlanguagefile, $ms, $batlevel, $main, $zone, $errortext, $errorvoice, $errorlang;
	
	$file = "battery.json";
	$url = $pathlanguagefile."/".$file;
	$valid_languages = json_decode(file_get_contents($url), true);
	$language = substr($config['TTS']['messageLang'], 0, 5);
	$isvalid = array_multi_search($language, $valid_languages, $sKey = "language");
	if (!empty($isvalid)) {
		$errortext = $isvalid[0]['value']; // Text
		$errorvoice = $isvalid[0]['voice']; // de-DE-Standard-A
		$errorlang = $isvalid[0]['language']; // de-DE
	} else {
		# if no translation for error in local language available, then exit and use use English (Google Cloud)
		$errortext = "The battery level of zone {$zone} is about {$batlevel} percent. Next check in about 1hour";
		$errorvoice = 'en-GB-Wavenet-A';
		$errorlang = 'en-GB';
		LOGINF("system/battery.php: Translation for your Standard language is not available, EN has been selected");	
	}
	$my_variable_name = 'zone';
	$my_value = $main; 
	$my_msg= $errortext; 
	$my_variable_name = $my_value;
	$my_msg = eval("return \"$my_msg\";");
	$errortext = $my_msg;
	sendmessage($errortext);
	return $errortext;
}

/**
* Funktion : 	sendbatt --> sendet Batteriestatus an MS.
*
* @param: Batterylevel
* @return: 
**/

function sendbatt($batlevel) {
	
	global $config, $batlevel, $zone;
	
	// check if Data transmission is switched off
	if(!is_enabled($config['LOXONE']['LoxDaten'])) {
		echo "Communication to Miniserver is off".PHP_EOL;
		return false;
	}
	
	$server_port = $config['LOXONE']['LoxPort'];
	$no_ms = $config['LOXONE']['Loxone'];
	$mem_sendall = 0;
	$mem_sendall_sec = 3600;
	
	# get MS
	$ms = LBSystem::get_miniservers();
	
	if(is_enabled($config['LOXONE']['LoxDatenMQTT'])) {
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
	// obtain selected Miniserver from config
	$my_ms = $ms[$config['LOXONE']['Loxone']];
	
	$tmp_array["battery_".$zone] = $batlevel;
	#print_r($batlevel);
	if ($mqttstat == "1")   {
		$mqtt->publish('Sonos4lox/battery/'.$zone, $batlevel, 0, 1);
	}
	if ($mqttstat == "0")   {
		$response = udp_send_mem($no_ms, $server_port, "Sonos4lox", $tmp_array);
	}
	return;
}



function shutdown()
{
	global $log;
	
	LOGEND("Battery check finished");
}


?>