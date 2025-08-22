#!/bin/bash

# Bashscript which is executed by bash *AFTER* complete installation is done
# (but *BEFORE* postupdate). Use with caution and remember, that all systems may
# be different!
#
# Exit code must be 0 if executed successfull. 
# Exit code 1 gives a warning but continues installation.
# Exit code 2 cancels installation.
#
# Will be executed as user "loxberry".
#
# You can use all vars from /etc/environment in this script.
#
# We add 5 additional arguments when executing this script:
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

# To use important variables from command line use the following code:
COMMAND=$0    # Zero argument is shell command
PTEMPDIR=$1   # First argument is temp folder during install
PSHNAME=$2    # Second argument is Plugin-Name for scipts etc.
PDIR=$3       # Third argument is Plugin installation folder
PVERSION=$4   # Forth argument is Plugin version
#LBHOMEDIR=$5 # Comes from /etc/environment now. Fifth argument is
              # Base folder of LoxBerry

# Combine them with /etc/environment
PCGI=$LBPCGI/$PDIR
PHTML=$LBPHTML/$PDIR
PTEMPL=$LBPTEMPL/$PDIR
PDATA=$LBPDATA/$PDIR
PLOG=$LBPLOG/$PDIR # Note! This is stored on a Ramdisk now!
PCONFIG=$LBPCONFIG/$PDIR
PSBIN=$LBPSBIN/$PDIR
PBIN=$LBPBIN/$PDIR

if [ -d /tmp/$1\_upgrade/webfrontend/piper-voices ]; then
	echo "<INFO> Piper-Voice already exist, we skip installation of Voices"
else
	mkdir -p $5/webfrontend/html/plugins/$3/voice_engines/piper-voices
	echo "<OK> Folder piper-voices has been created."
	wget -P $5/webfrontend/html/plugins/$3/voice_engines/piper-voices https://huggingface.co/Thorsten-Voice/Hessisch/resolve/main/Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx
	wget -P $5/webfrontend/html/plugins/$3/voice_engines/piper-voices https://huggingface.co/Thorsten-Voice/Hessisch/resolve/main/Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx.json
	#wget -P $5/webfrontend/html/plugins/$3/voice_engines/piper-voices https://huggingface.co/rhasspy/piper-voices/resolve/main/voices.json
	wget -P $5/webfrontend/html/plugins/$3/voice_engines/piper-voices https://huggingface.co/rhasspy/piper-voices/resolve/main/de/de_DE/thorsten/high/de_DE-thorsten-high.onnx
	wget -P $5/webfrontend/html/plugins/$3/voice_engines/piper-voices https://huggingface.co/rhasspy/piper-voices/resolve/main/de/de_DE/thorsten/high/de_DE-thorsten-high.onnx.json
	wget -P $5/webfrontend/html/plugins/$3/voice_engines/piper-voices https://huggingface.co/rhasspy/piper-voices/resolve/main/de/de_DE/thorsten_emotional/medium/de_DE-thorsten_emotional-medium.onnx
	wget -P $5/webfrontend/html/plugins/$3/voice_engines/piper-voices https://huggingface.co/rhasspy/piper-voices/resolve/main/de/de_DE/thorsten_emotional/medium/de_DE-thorsten_emotional-medium.onnx.json
	/usr/bin/php REPLACELBPHTMLDIR/bin/add_details_piper_tts.php
	echo "<INFO> Piper-Voices has been downloaded"
fi

# Exit with Status 0
exit 0
