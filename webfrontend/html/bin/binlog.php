#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/system/logging.php");

$check_date_once = "/run/shm/s4lox_bin_err";
$Stunden = intval(strftime("%H"));	

function binlog($topic, $logmessage)   {
	
global $lbplogdir, $check_date_once, $Stunden;
	

	# Exception for battery check
	if ($topic == "Battery check")   {
		if ($Stunden >=8 && $Stunden <21)   {	
			$log = LBLog::newLog( [ "name" => "$topic", "addtime" => 1, "filename" => "$lbplogdir/sonos.log", "append" => 1 ] );
			LOGSTART("$topic");
			$log>LOGINF($logmessage);
			$log>LOGOK("Battery check ended successful");
			file_put_contents($check_date_once, "1");
			LOGEND("$topic end");
			return true;
		}
	}
	
	# from here check once per day
	if (is_file($check_date_once))   {
		exit;
	}
	$log = LBLog::newLog( [ "name" => "$topic", "addtime" => 1, "filename" => "$lbplogdir/sonos.log", "append" => 1 ] );
	LOGSTART("$topic");
	$log>LOGERR($logmessage);
	file_put_contents($check_date_once, "1");
	LOGEND("$topic end");
	}

?>