#!/bin/bash

# This is a sample cron file. According to it's name it will go to
# ~/system/cron/cron.hourly. You may also let your Pluginscript create a
# symbolic link dynamically in ~/system/cron/cron.10min which links to your
# cron-script instead (which is prefered). Use NAME from
# /data/system/plugindatabase.dat in that case as scriptname! Otherwise the
# cron script will not be uninstalled cleanly.

# Will be executed as user "loxberry".

if [ -e /run/shm/s4lox_zap_zone.json ]
then
    rm /run/shm/s4lox_zap_zone.json
fi
rm /run/shm/s4lox_fav*

