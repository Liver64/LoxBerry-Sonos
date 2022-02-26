#!/usr/bin/env php
<?php
require_once "loxberry_web.php";
require_once "loxberry_log.php";

#echo "<PRE>";
$L = LBSystem::readlanguage("sonos.ini");

# Create an informational notification for the group "Sonos" (part of postupgradscript.sh)
notify(LBPPLUGINDIR, "Sonos", $L['ERRORS.NOTE_UPGRADE']);

?>