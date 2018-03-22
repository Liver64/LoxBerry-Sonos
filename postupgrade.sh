#!/bin/sh

# Bash script which is executed in case of an update (if this plugin is already
# installed on the system). This script is executed as very last step (*AFTER*
# postinstall) and can be for example used to save back or convert saved
# userfiles from /tmp back to the system. Use with caution and remember, that
# all systems may be different!
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


echo "<INFO> Copy back existing config files"
cp -p -v -r /tmp/$1\_upgrade/config/$3/* $5/config/plugins/$3/ 

echo "<INFO> Copy back existing log files"
cp -p -v -r /tmp/$1\_upgrade/log/$3/* $5/log/plugins/$3/ 

echo "<INFO> Copy back existing MP3 files"
cp -p -v -r /tmp/$1\_upgrade/data/$3/* $5/data/plugins/$3/ 

echo "<INFO> Remove temporary folders"
rm -r /tmp/$1\_upgrade

exit 0
