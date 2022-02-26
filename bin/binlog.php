#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once("$lbphtmldir/system/error.php");
require_once("$lbphtmldir/system/logging.php");

function binlog($topic, $logmessage)   {
	global $lbplogdir;
	
	$check_date_once = "/run/shm/bin_err";	
	
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