<?php
header('Content-Type: text/html; charset=utf-8');

#error_reporting(E_ALL);
#ini_set('display_errors', true);
#ini_set('html_errors', true);

error_reporting(~E_ALL & ~E_STRICT);     // Alle Fehler reporten (Außer E_STRICT)
ini_set("display_errors", false);        // Fehler nicht direkt via PHP ausgeben
ini_set('html_errors', false);			 

require_once "loxberry_system.php";
require_once "loxberry_log.php";

$L = LBSystem::readlanguage("sonos.ini");
ini_set("log_errors", 1);
ini_set("error_log", LBPLOGDIR."/sonos.log");

require_once 'Sonos.php';
?>
