<?php
header('Content-Type: text/html; charset=utf-8');

include "loxberry_system.php";
include "loxberry_io.php";
require_once "loxberry_log.php";
include "system/error.php";

error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", "off");
define('ERROR_LOG_FILE', "$lbplogdir/sonos.log");
#echo "<PRE>";
//calling custom error handler
set_error_handler("handleError");

// Testcases
#print_r($arra); // undefined variable
#print_r($dssdfdfgg); // undefined variable
#include_once 'file.php'; // No such file or directory

require_once 'Sonos.php';



?>
