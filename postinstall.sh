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

# Replace by subfolder
/bin/sed -i "s%REPLACEBYSUBFOLDER%$3%" $5/config/plugins/$3/sonos.cfg
/bin/sed -i "s%REPLACEBYSUBFOLDER%$3%" $5/webfrontend/html/plugins/$3/system/network.php
/bin/sed -i "s%sonos4lox_dev%$3%" $5/webfrontend/html/plugins/$3/system/network.php
/bin/sed -i "s%REPLACEBYDOMAIN%$5%" $5/webfrontend/html/plugins/$3/system/network.php

test=`cat /etc/samba/smb.conf | grep sonos_tts | wc -l`

if [ $test = 0 ]
then
	# to ensure that Sonos can read from folder structure
	#/bin/sed -i "s%guest ok = no%guest ok = yes%" $ARGV5/system/samba/smb.conf
	echo " " >> $5/system/samba/smb.conf
	echo "[sonos_tts]" >> $5/system/samba/smb.conf
	echo "   comment = Loxberry Files" >> $5/system/samba/smb.conf
	echo "   path = $ARGV5/data/plugins/$ARGV3/tts" >> $5/system/samba/smb.conf
	echo "   guest ok = yes" >> $5/system/samba/smb.conf
	echo "   read only = no" >> $5/system/samba/smb.conf
	echo "   directory mask = 0700" >> $5/system/samba/smb.conf
	echo "   create mask = 0700" >> $5/system/samba/smb.conf
	echo "<INFO> Samba file 'smb.conf' has been updated successfully."
fi

# Exit with Status 0
exit 0
