#!/usr/bin/env php
<?php
require_once "loxberry_log.php";

# Create an informational notification for the group "Sonos" (part of postupgradscript.sh)
notify(LBPPLUGINDIR, "Sonos", "Please update your config by selecting min. 1 Zone in Column 'T2S' for Voice Notification.\n The Zone(s) should be your main zone to reach out people at your home and Zone(s) should always be Online. Voice notifications only take place between 9am to 9pm");

?>