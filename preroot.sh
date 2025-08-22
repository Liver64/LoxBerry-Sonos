#!/bin/sh
# Will be executed as user "root".


file="$5/system/cron/cron.d/Sonos"

if [ -f "$file" ] ; then
    rm "$file"
	echo "<INFO> Cronjob cron.d/Sonos has been successful deleted"
fi

if [ -d $5/bin/plugins/$3/piper/ ]; then
	rm -r $5/bin/plugins/$3/piper/
	echo "<OK> Piper TTS has been successful deleted"
else
	echo "<INFO> Piper TTS wasn't there"
fi

if [ -L /usr/bin/piper ]; then
	ln -sf /usr/local/bin/piper/piper /usr/bin/piper
	echo "<OK> Symlink /usr/bin/piper has been updated"
else
	echo "<INFO> No Symlink /usr/bin/piper exist"
fi
exit 0