#!/bin/sh
# Will be executed as user "root".


file="$5/system/cron/cron.d/Sonos"

if [ -f "$file" ] ; then
    rm "$file"
	echo "<INFO> Cronjob cron.d/Sonos has been successful deleted"
fi

exit 0