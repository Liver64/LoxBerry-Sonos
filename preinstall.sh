#!/bin/sh

# Bash script which is executed by bash *BEFORE* installation is started (but
# *AFTER* preupdate). Use with caution and remember, that all systems may be
# different! Better to do this in your own Pluginscript if possible.
#
# Exit code must be 0 if executed successfull.
#
# Will be executed as user "loxberry".
#
# We add 5 arguments when executing the script:
# command <TEMPFOLDER> <NAME> <FOLDER> <VERSION> <BASEFOLDER>
#
# For logging, print to STDOUT. You can use the following tags for showing
# different colorized information during plugin installation:
#
# <OK> This was ok!"
# <INFO> This is just for your information."
# <WARNING> This is a warning!"
# <ERROR> This is an error!"
# <FAIL> This is a fail!"


#$version = sed -nr "/^\[BASE\]/ { :l /^VERSION[ ]*=/ { s/.*=[ ]*//; p; q;}; n; b l;}" .$ARGV3/loxberry/config/system/general.cfg
#echo $version
#if [ "$version"  == "0.2.2" ]
#then
	#echo "<ERROR> You must update LoxBerry to Version 0.2.3 before installing Sonos Script"
	#exit
#fi

find /tmp/uploads/$ARGV1 -type f -print0 | xargs -0 dos2unix -q
echo "<OK> Fehlerhafte EOL's wurden erfolgreich konvertiert!"

# Exit with Status 0
exit 0
