#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/system/logging.php");

$check_date_once = "/run/shm/s4lox_bin_err";	

function binlog($topic, $logmessage)   {
	
	global $lbplogdir, $check_date_once;
	
	# Exception for battery check
	if ($topic == "Battery check")   {
		$log = LBLog::newLog( [ "name" => "$topic", "addtime" => 1, "filename" => "$lbplogdir/sonos.log", "append" => 1 ] );
		LOGSTART("Error $topic");
		$log>LOGWARN($logmessage);
		$log>LOGOK("Battery check ended successful");
		file_put_contents($check_date_once, "1");
		LOGEND("Error $topic end");
		return true;
	}

	if (is_file($check_date_once))   {
		exit;
	}

	$log = LBLog::newLog( [ "name" => "$topic", "addtime" => 1, "filename" => "$lbplogdir/sonos.log", "append" => 1 ] );
	LOGSTART("Error $topic");
	$log>LOGERR($logmessage);
	file_put_contents($check_date_once, "1");
	LOGEND("Error $topic end");
}

?>