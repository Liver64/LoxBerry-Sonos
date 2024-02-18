#!/bin/sh

# Bash script which is executed in case of an update (if this plugin is already
# installed on the system). This script is executed as very first step (*BEFORE*
# preinstall.sh) and can be used e.g. to save existing configfiles to /tmp 
# during installation. Use with caution and remember, that all systems may be
# different!
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
LBHOMEDIR=$5  # Comes from /etc/environment now. Fifth argument is
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


DIR=$LBPDATA/$PDIR/backup

if [ -d "$DIR" ]; then
  echo "<INFO> Delete previous Plugin Backup folder"
  rm -r $LBPDATA/$PDIR/backup
else
  echo "<INFO> No Backup folder exist"
fi

echo "<INFO> Creating Plugin Backup folders"
mkdir -p $LBPDATA/$PDIR/backup
mkdir -p $LBPDATA/$PDIR/backup/bin
mkdir -p $LBPDATA/$PDIR/backup/templates
mkdir -p $LBPDATA/$PDIR/backup/webfrontend
mkdir -p $LBPDATA/$PDIR/backup/webfrontend/html
mkdir -p $LBPDATA/$PDIR/backup/webfrontend/htmlauth
mkdir -p $LBPDATA/$PDIR/backup/config
mkdir -p $LBPDATA/$PDIR/backup/cron
mkdir -p $LBPDATA/$PDIR/backup/daemon

echo "<INFO> Copy existing CONFIG files to Backup folder"
cp -p -v -r $5/config/plugins/$3/ $LBPDATA/$PDIR/backup/config

echo "<INFO> Copy existing BIN files to Backup folder"
cp -p -v -r $5/bin/plugins/$3/ $LBPDATA/$PDIR/backup/bin

echo "<INFO> Copy existing TEMPLATE files to Backup folder"
cp -p -v -r $5/templates/plugins/$3/ $LBPDATA/$PDIR/backup/templates

echo "<INFO> Copy existing HMTL files to Backup folder"
cp -p -v -r $5/webfrontend/html/plugins/$3/ $LBPDATA/$PDIR/backup/webfrontend/html

echo "<INFO> Copy existing HTMLAUTH files to Backup folder"
cp -p -v -r $5/webfrontend/htmlauth/plugins/$3/ $LBPDATA/$PDIR/backup/webfrontend/htmlauth

echo "<INFO> Copy existing CRON files to Backup folder"
cp -p -v -r $5/system/cron/cron.01min/Sonos $LBPDATA/$PDIR/backup/cron/Sonos.cron01min
cp -p -v -r $5/system/cron/cron.03min/Sonos $LBPDATA/$PDIR/backup/cron/Sonos.cron03min
cp -p -v -r $5/system/cron/cron.hourly/Sonos $LBPDATA/$PDIR/backup/cron/Sonos.cron.hourly
cp -p -v -r $5/system/cron/cron.daily/Sonos $LBPDATA/$PDIR/backup/cron/Sonos.cron.daily
cp -p -v -r $5/system/cron/cron.weekly/Sonos $LBPDATA/$PDIR/backup/cron/Sonos.cron.weekly

echo "<INFO> Copy existing DAEMON file to Backup folder"
cp -p -v -r $5/system/daemons/plugins/Sonos $LBPDATA/$PDIR/backup/daemon

echo "<INFO> Creating temporary folders for upgrading"
mkdir -p /tmp/$1\_upgrade
mkdir -p /tmp/$1\_upgrade/config
mkdir -p /tmp/$1\_upgrade/log
mkdir -p /tmp/$1\_upgrade/data
mkdir -p /tmp/$1\_upgrade/templates
mkdir -p /tmp/$1\_upgrade/webfrontend

echo "<INFO> Backing up existing config files"
cp -p -v -r $5/config/plugins/$3/ /tmp/$1\_upgrade/config

echo "<INFO> Backing up existing Sonos image files"
cp -p -v -r $5/webfrontend/html/plugins/$3/images/icon* /tmp/$1\_upgrade/webfrontend

echo "<INFO> Backing up existing log files"
cp -p -v -r $5/log/plugins/$3/ /tmp/$1\_upgrade/log

echo "<INFO> Backing up existing MP3 files"
cp -p -v -r $5/data/plugins/$3/ /tmp/$1\_upgrade/data

echo "<INFO> Backing up existing Text files"
cp -v $5/templates/plugins/$3/lang/t2s-text_*.* /tmp/$1\_upgrade/templates

# Exit with Status 0
exit 0