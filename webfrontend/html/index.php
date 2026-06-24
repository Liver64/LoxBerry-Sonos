<?php
header('Content-Type: text/html; charset=utf-8');

include "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
include "REPLACELBHOMEDIR/libs/phplib/loxberry_io.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";
include "src/Support/ErrorHandler.php";

error_reporting(E_ALL & ~E_NOTICE);
ini_set("display_errors", "off");
define('ERROR_LOG_FILE', "$lbplogdir/sonos.log");

set_error_handler("handleError");

require_once 'Sonos.php';
?>
