#!/usr/bin/perl -w

# Copyright 2018 Oliver Lewald, olewald64@gmail.com
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
# 
#     http://www.apache.org/licenses/LICENSE-2.0
# 
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.


##########################################################################
# Modules
##########################################################################

use LoxBerry::System;
use LoxBerry::Web;
use LoxBerry::Log;
use LoxBerry::Storage;
use LoxBerry::IO;
use LoxBerry::JSON;

use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use CGI;
use LWP::Simple;
use LWP::UserAgent;
use File::HomeDir;
use Cwd 'abs_path';
use Scalar::Util qw/reftype/;
use JSON qw( decode_json );
use utf8;
use warnings;
use strict;
use Data::Dumper;
#use Config::Simple '-strict';
no strict "refs"; # we need it for template system

##########################################################################
# Generic exception handler
##########################################################################

# Every non-handled exceptions sets the @reason variable that can
# be written to the logfile in the END function

$SIG{__DIE__} = sub { our @reason = @_ };

##########################################################################
# Variables
##########################################################################

my $template_title;
my $saveformdata = 0;
my $do = "form";
my $helplink;
my $maxzap;
my $helptemplate;
my $i;
my $response;
my $response_conf;
my $resp;
our $lbv;
our $countplayers;
our $countsoundbars;
our $rowssonosplayer;
our $rowsvolplayer;
our $miniserver;
our $template;
our $content;
our %navbar;
our $mqttcred;
our $cfgm;
our $cgi;

my $helptemplatefilename		= "help/help.html";
my $languagefile 				= "sonos.ini";
my $maintemplatefilename	 	= "sonos.html";
my $pluginlogfile				= "sonos.log";
# my $XML_file					= "VIU_Sonos_UDP.xml";
my $lbip 						= LoxBerry::System::get_localip();
my $host						= LoxBerry::System::lbhostname();
my $lbport						= lbwebserverport();
my $ttsfolder					= "tts";
my $mp3folder					= "mp3";
my $urlfile						= "https://raw.githubusercontent.com/Liver64/LoxBerry-Sonos/master/webfrontend/html/release/info.txt";
my $log 						= LoxBerry::Log->new ( name => 'Sonos UI', filename => $lbplogdir ."/". $pluginlogfile, append => 1, addtime => 1 );
my $plugintempplayerfile	 	= "tmp_player.json";
my $scanzonesfile	 			= "network.php";
my $udp_file	 				= "ms_inbound.php";
my $azureregion					= "westeurope"; # Change here if you have a Azure API key for diff. region
my $helplink 					= "http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone";
our $error_message				= "";
my $countvolprof;

my $configfile 					= "s4lox_config.json";
my $volumeconfigfile 			= "s4lox_vol_profiles.json";
my $jsonobj 					= LoxBerry::JSON->new();
our $cfg 						= $jsonobj->open(filename => $lbpconfigdir . "/" . $configfile, writeonclose => 0);

# Set new config options for upgrade installations

# add new parameter for cachesize
if (!defined $cfg->{"MP3"}->{cachesize}) {
	$cfg->{MP3}->{cachesize} = "100";
} 
# Rampto Volume
if ($cfg->{TTS}->{volrampto} eq '')  {
	$cfg->{TTS}->{volrampto} = "25";
}
# Rampto type
if ($cfg->{TTS}->{rampto} eq '')  {
	$cfg->{TTS}->{rampto} = "auto";
}
# add new parameter for Volume correction
if (!defined $cfg->{TTS}->{correction})  {
	$cfg->{TTS}->{correction} = "8";
}
# add new parameter for Azure TTS"
if (!defined $cfg->{TTS}->{regionms})  {
	$cfg->{TTS}->{regionms} = $azureregion;
	#$jsonobj->write();
}
# add new parameter for Volume phonemute
if (!defined $cfg->{TTS}->{phonemute})  {
	$cfg->{TTS}->{phonemute} = "8";
}
# add new parameter for waiting time in sec.
if (!defined $cfg->{TTS}->{waiting})  {
	$cfg->{TTS}->{waiting} = "10";
}
# add new parameter for phonestop
if (!defined $cfg->{VARIOUS}->{phonestop})  {
	$cfg->{VARIOUS}->{phonestop} = "0";
}
# Reset Time for zapzone
if (!defined $cfg->{VARIOUS}->{cron})  {
	$cfg->{VARIOUS}->{cron} = "1";
}
# checkonline
if ($cfg->{SYSTEM}->{checkt2s} eq '')  {
	$cfg->{SYSTEM}->{checkt2s} = "false";
}
# maxVolume
if (!defined $cfg->{VARIOUS}->{volmax})  {
	$cfg->{VARIOUS}->{volmax} = "0";
}
# Loxdaten an MQTT
if (!defined $cfg->{LOXONE}->{LoxDatenMQTT})  {
	$cfg->{LOXONE}->{LoxDatenMQTT} = "false";
}
# text-to-speech Status
if (!defined $cfg->{TTS}->{t2son})  {
	$cfg->{TTS}->{t2son} = "true";
}
# Starttime TV Monitoring
if (!defined $cfg->{VARIOUS}->{starttime})  {
	$cfg->{VARIOUS}->{starttime} = "10";
}
# Endtime TV Monitoring
if (!defined $cfg->{VARIOUS}->{endtime})  {
	$cfg->{VARIOUS}->{endtime} = "22";
}
# copy old API-key value to apikey
if (defined $cfg->{TTS}->{'API-key'})  {
	$cfg->{TTS}->{apikey} = $cfg->{TTS}->{'API-key'};
	delete $cfg->{TTS}->{'API-key'};
}
# copy global API-key to engine-API-key
if (!defined $cfg->{TTS}->{apikeys}) {
	$cfg->{TTS}->{apikeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{apikey};
}
# copy old secret-key value to secretkey
if (defined $cfg->{TTS}->{'secret-key'})  {
	$cfg->{TTS}->{secretkey} = $cfg->{TTS}->{'secret-key'};
	delete $cfg->{TTS}->{'secret-key'};
}
# copy global Secret-key to engine-secretkey
if (!defined $cfg->{TTS}->{secretkeys}) {
	$cfg->{TTS}->{secretkeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{secretkey};
}
# Follow host
if (!defined $cfg->{VARIOUS}->{follow_host})  {
	$cfg->{VARIOUS}->{follow_host} = "false";
}
# Leave follow host
if (!defined $cfg->{VARIOUS}->{follow_wait})  {
	$cfg->{VARIOUS}->{follow_wait} = "false";
}
#$jsonobj->write();


##########################################################################
# Read Settings
##########################################################################

# read language
my $lblang = lblanguage();
our %SL = LoxBerry::System::readlanguage($template, $languagefile);

# Read Plugin Version
my $sversion = LoxBerry::System::pluginversion();

# Read LoxBerry Version
my $lbversion = LoxBerry::System::lbversion();
#LOGDEB "Loxberry Version: " . $lbversion;

# read all POST-Parameter in namespace "R".
$cgi = CGI->new;
$cgi->import_names('R');

# Get MQTT Credentials
$mqttcred = LoxBerry::IO::mqtt_connectiondetails();

LOGSTART "Sonos UI started";


##########################################################################
# Init Main Template
##########################################################################

inittemplate();

#########################################################################
## Handle ajax requests 
#########################################################################

our $q = $cgi->Vars;

if( $q->{action} )
{
	print "Content-type: application/json\n\n";
	if( $q->{action} eq "soundbars" ) {
		print JSON::encode_json($cfg->{sonoszonen});
		exit;
	}

	if( $q->{action} eq "profiles" ) {
		our $vcfg = $jsonobj->open(filename => $lbpconfigdir . "/s4lox_vol_profiles.json", writeonclose => 0);
		print JSON::encode_json($vcfg);
		exit;
	}	

	if( $q->{action} eq "getradio" ) {
		print JSON::encode_json($cfg->{RADIO}->{radio});
		exit;
	}
}



if ($R::getkeys)
{
	getkeys();
}



#########################################################################
# Parameter
#########################################################################

#$saveformdata = defined $R::saveformdata ? $R::saveformdata : undef;
#$do = defined $R::do ? $R::do : "form";


##########################################################################
# Set LoxBerry SDK to debug in plugin if in debug
##########################################################################

if($log->loglevel() eq "7") {
	$LoxBerry::System::DEBUG 	= 1;
	$LoxBerry::Web::DEBUG 		= 1;
	$LoxBerry::Storage::DEBUG	= 1;
	$LoxBerry::Log::DEBUG		= 1;
	$LoxBerry::IO::DEBUG		= 1;
}


##########################################################################
# Language Settings
##########################################################################

$template->param("LBHOSTNAME", lbhostname());
$template->param("LBLANG", $lblang);
$template->param("SELFURL", $ENV{REQUEST_URI});

LOGDEB "Read main settings from " . $languagefile . " for language: " . $lblang;

#************************************************************************

# übergibt Plugin Verzeichnis an HTML
$template->param("PLUGINDIR" => $lbpplugindir);

# übergibt Log Verzeichnis und Dateiname an HTML
$template->param("LOGFILE" , $lbplogdir . "/" . $pluginlogfile);

##########################################################################
# check if config file exist and are readable
##########################################################################

if (!-r $lbpconfigdir . "/" . $configfile) 
{
	LOGCRIT "Plugin config file does not exist";
	$error_message = $SL{'ERRORS.ERR_CHECK_SONOS_CONFIG_FILE'};
	notify($lbpplugindir, "Sonos UI ", "Error loading Sonos configuration file. Please try again or check config folder!", 1);
	&error; 
} else {
	LOGDEB "The Sonos config file has been loaded";
}

LOGDEB "Loxberry Version: " . $lbversion;
$lbv = substr($lbversion,0,1);


##########################################################################
# Main program
##########################################################################


#our %navbar;
$navbar{1}{Name} = "$SL{'BASIS.MENU_SETTINGS'}";
$navbar{1}{URL} = './index.cgi';
$navbar{2}{Name} = "$SL{'BASIS.MENU_OPTIONS'}";
$navbar{2}{URL} = './index.cgi?do=details';
$navbar{3}{Name} = "$SL{'BASIS.MENU_VOLUME'}";
$navbar{3}{URL} = './index.cgi?do=volume';
$navbar{99}{Name} = "$SL{'BASIS.MENU_LOGFILES'}";
$navbar{99}{URL} = './index.cgi?do=logfiles';

# if MQTT credentials are valid and Communication turned ON --> insert navbar
if ($mqttcred and $cfg->{LOXONE}->{LoxDaten} eq "true")  {
	$navbar{4}{Name} = "$SL{'BASIS.MENU_MQTT'}";
	# Lower then LB Version 3
	if($lbv < 3)  {
		my $cfgfile = $lbhomedir.'/config/plugins/mqttgateway/mqtt.json';
		my $json = LoxBerry::JSON->new();
		$cfgm = $json->open(filename => $cfgfile);
		$navbar{3}{URL} = '/admin/plugins/mqttgateway/index.cgi';
	} else {
		my $cfgfile = $lbhomedir.'/config/system/mqttgateway.json';
		my $json = LoxBerry::JSON->new();
		$cfgm = $json->open(filename => $cfgfile);
		$navbar{4}{URL} = '/admin/system/mqtt.cgi';
	}
	$navbar{4}{target} = '_blank';
}

if ($R::saveformdata1) {
	$template->param( FORMNO => 'form' );
	&save;
}
if ($R::saveformdata2) {
	$template->param( FORMNO => 'details' );
	&save_details;
}
if ($R::saveformdata3) {
	$template->param( FORMNO => 'volume' );
	&save_volume;
}


# check if config already saved, if not highlight header text in RED
my $countplayer;
my $inst;

if(exists($cfg->{sonoszonen}))  { 
    $countplayer = 1;
	$inst = "true";
} else { 
    $countplayer = 0;
	$inst = "false"
} 


$template->param("PLAYERAVAILABLE", $countplayer);


if(!defined $R::do or $R::do eq "form") {
	$navbar{1}{active} = 1;
	$template->param("SETTINGS", "1");
	&form;
} elsif($R::do eq "details") {
	$navbar{2}{active} = 1;
	$template->param("DETAILS", "1");
	&form;
} elsif ($R::do eq "logfiles") {
	LOGTITLE "Show logfiles";
	$navbar{99}{active} = 1;
	$template->param("LOGFILES", "1");
	$template->param("LOGLIST_HTML", LoxBerry::Web::loglist_html());
	printtemplate();
} elsif ($R::do eq "scanning") {
	LOGTITLE "Execute Scan";
	&scan;
	$template->param("SETTINGS", "1");
	&form;
} elsif($R::do eq "volume") {
	$navbar{3}{active} = 1;
	$template->param("VOLUME", "1");
	volumes();
	&form;
	
} 

$error_message = "Invalid do parameter: ".$R::do;
&error;
exit;



#####################################################
# Form-Sub
#####################################################

sub form 
{
	$template->param(FORMNO => 'SETTINGS' );
	
	# check if path exist (upgrade from v3.5.1)
	if ($cfg->{SYSTEM}->{path} eq "")   {
		$cfg->{SYSTEM}->{path} = "$lbpdatadir";
		#$jsonobj->write();
		LOGINF("default path has been added to config");
	}
	
	# prepare Storage
	my $storage = LoxBerry::Storage::get_storage_html(
					formid => 'STORAGEPATH', 
					currentpath => $jsonobj->param("SYSTEM.path"),
					custom_folder => 1,
					type_all => 1, 
					readwriteonly => 1, 
					data_mini => 1,
					label => "$SL{'T2S.SAFE_DETAILS'}");
					
	$template->param("STORAGEPATH", $storage);
	
	# read info file from Github and save in $info
	my $info = get($urlfile);
	$template->param("INFO" 			=> "$info");
	
	# fill saved values into form
	$template->param("SELFURL", $SL{REQUEST_URI});
	$template->param("T2S_ENGINE" 	=> $cfg->{TTS}->{t2s_engine}); 
	$template->param("APIKEY"		=> $cfg->{TTS}->{apikeys}->{$cfg->{TTS}->{t2s_engine}});
	$template->param("SECKEY"		=> $cfg->{TTS}->{secretkeys}->{$cfg->{TTS}->{t2s_engine}});
	$template->param("VOICE" 		=> $cfg->{TTS}->{voice});
	$template->param("CODE" 		=> $cfg->{TTS}->{messageLang});
	$template->param("DATADIR" 		=> $cfg->{SYSTEM}->{path});
	$template->param("LOX_ON" 		=> $cfg->{LOXONE}->{LoxDaten});
	#$template->param('ERR_MESSAGE', $error_message);
		
	# Load saved values for "select"
	my $t2s_engine	 = $cfg->{TTS}->{t2s_engine};
	my $rmpvol	 	 = $cfg->{TTS}->{volrampto};
	my $storepath 	 = $cfg->{SYSTEM}->{path};
	
	# read Radiofavorites
	our $countradios = 0;
	our $rowsradios;
	my $radiofavorites = $cfg->{RADIO}->{radio};

	foreach my $key (keys %{$radiofavorites}) {
		$countradios++;
		my @fields = split(/,/,$cfg->{RADIO}->{radio}->{$countradios} );
		$rowsradios .= "<tr><td style='height: 25px; width: 43px;'><INPUT type='checkbox' style='width: 20px' name='chkradios$countradios' id='chkradios$countradios' align='center'/></td>\n";
		$rowsradios .= "<td style='height: 28px'><input type='text' id='radioname$countradios' name='radioname$countradios' size='20' value='$fields[0]' /> </td>\n";
		$rowsradios .= "<td style='width: 600px; height: 28px'><input type='text' id='radiourl$countradios' name='radiourl$countradios' size='100' value='$fields[1]' style='width: 100%' /> </td>\n";
		$rowsradios .= "<td style='width: 600px; height: 28px'><input type='text' id='coverurl$countradios' name='coverurl$countradios' size='100' value='$fields[2]' style='width: 100%' /> </td></tr>\n";
	}

	if ( $countradios < 1 ) {
		$rowsradios .= "<tr><td colspan=4>" . $SL{'RADIO.SONOS_EMPTY_RADIO'} . "</td></tr>\n";
	}
	LOGDEB "Radio Stations has been loaded.";
	$rowsradios .= "<input type='hidden' id='countradios' name='countradios' value='$countradios'>\n";
	$template->param("ROWSRADIO", $rowsradios);
	
	# *******************************************************************************************************************
	# Player einlesen
	
	our $rowssonosplayer;
	our $rowssoundbar;
	
	my $error_volume = $SL{'T2S.ERROR_VOLUME_PLAYER'};
	my $filename;
	my $config = $cfg->{sonoszonen};
		
	foreach my $key (sort keys %$config) {
		$countplayers++;
		our $room = $key;
		$filename = $lbphtmldir.'/images/icon-'.$config->{$key}->[7].'.png';
		our $statusfile = $lbpdatadir.'/PlayerStatus/s4lox_on_'.$room.'.txt';
		
		$rowssonosplayer .= "<tr>";
		$rowssonosplayer .= "<td style='height: 25px; width: 20px;'><input type='checkbox' name='chkplayers$countplayers' id='chkplayers$countplayers' align='middle'/></td>\n";
		if (-e $statusfile) {
			$rowssonosplayer .= "<td style='height: 28px; width: 16%;'><input type='text' class='pd-price' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$room' style='width:100%; background-color:#6dac20; color:white'></td>\n";
		} else {
			$rowssonosplayer .= "<td style='height: 28px; width: 16%;'><input type='text' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$room' style='width: 100%; background-color: #e6e6e6;'></td>\n";
		}	
		$rowssonosplayer .= "<td style='height: 25px; width: 6px;'><input type='checkbox' class='chk-checked' name='mainchk$countplayers' id='mainchk$countplayers' value='$config->{$key}->[6]' align='center'></td>\n";
		$rowssonosplayer .= "<td style='height: 28px; width: 15%;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='$config->{$key}->[2]' style='width: 100%; background-color: #e6e6e6;'></td>\n";
		# Column Sonos Player Logo
		if (-e $filename) {
			$rowssonosplayer .= "<td style='height: 28px; width: 2%;'><img src='/plugins/$lbpplugindir/images/icon-$config->{$key}->[7].png' border='0' width='50' height='50' align='middle'/></td>\n";
		} else {
			$rowssonosplayer .= "<td style='height: 28px; width: 2%;'><img src='/plugins/$lbpplugindir/images/sonos_logo_sm.png' border='0' width='50' height='50' align='middle'/></td>\n";
		}
		$rowssonosplayer .= "<td style='height: 28px; width: 17%;'><input type='text' id='ip$countplayers' name='ip$countplayers' size='30' value='$config->{$key}->[0]' style='width: 100%; background-color: #e6e6e6;'></td>\n";
		# Column Audioclip usage Pics green/red/yellow
		if (exists($config->{$key}[11]) and is_enabled($config->{$key}[11]))   {
			if (exists($config->{$key}[12]) and is_enabled($config->{$key}[12]))   {
				$rowssonosplayer .= "<td style='height: 30px; width: 10px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/green.png' border='0' width='26' height='28' align='center'/></div></td>\n";
			} else {
				$rowssonosplayer .= "<td style='height: 30px; width: 10px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/yellow.png' border='0' width='26' height='28' align='center'/></div></td>\n";
			}
		} else {
			$rowssonosplayer .= "<td style='height: 30px; width: 10px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/red.png' border='0' width='26' height='28' align='center'/></div></td>\n";
		}
		$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='t2svol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='t2svol$countplayers' value='$config->{$key}->[3]'></td>\n";
		$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='sonosvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='sonosvol$countplayers' value='$config->{$key}->[4]'></td>\n";
		$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='maxvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='maxvol$countplayers' value='$config->{$key}->[5]'></td>\n";
		$rowssonosplayer .= "</tr>";
		# Soundbar count
		if (($config->{$key}[13]) eq "SB")   {
			$countsoundbars++;
			$rowssonosplayer .= "<input type='hidden' id='sb$countplayers' name='sb$countplayers' value='$config->{$key}->[13]'>\n";
		} else {
			$rowssonosplayer .= "<input type='hidden' id='sb$countplayers' name='sb$countplayers' value='NOSB'>\n";
		}
		$rowssonosplayer .= "<input type='hidden' id='room$countplayers' name='room$countplayers' value=$room>\n";
		$rowssonosplayer .= "<input type='hidden' id='models$countplayers' name='models$countplayers' value='$config->{$key}->[7]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='sub$countplayers' name='sub$countplayers' value='$config->{$key}->[8]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='householdId$countplayers' name='householdId$countplayers' value='$config->{$key}->[9]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='sur$countplayers' name='sur$countplayers' value='$config->{$key}->[10]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='audioclip$countplayers' name='audioclip$countplayers' value='$config->{$key}->[11]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='voice$countplayers' name='voice$countplayers' value='$config->{$key}->[12]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='rincon$countplayers' name='rincon$countplayers' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' value='$config->{$key}->[1]'>\n";
		# Prepare Soundbars
		if (($config->{$key}[13]) eq "SB")   {
			$rowssoundbar .= "<tr class='tvmon_body'>\n";
			$rowssoundbar .= "<td style='height: 25px; width: 13%;'><fieldset align='center'><select id='usesb_$room' name='usesb_$room' data-role='flipswitch' style='width: 100%'><option value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
			$rowssoundbar .= "<div id='tvmonitor'><td style='height: 28px; width: 20%;'><input type='text' id='sbzone_$room' name='sbzone_$room' size='40' readonly='true' value='$room' vertical-align='center' style='width: 100%; background-color: #e6e6e6;'></td>\n";
			$rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonspeech_$room' name='tvmonspeech_$room' data-role='flipswitch' style='width: 100%'><option value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
			$rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonsurr_$room' name='tvmonsurr_$room' data-role='flipswitch' style='width: 100%'><option selected='selected' value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
			$rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonnightsub_$room' name='tvmonnightsub_$room' data-role='flipswitch' style='width: 100%'><option selected='selected' value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
			$rowssoundbar .= "<td style='width: 5%; height: 28px;'><div><input class='tvvol' type='text' id='tvvol_$room' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='tvvol_$room' value='$config->{$key}->[14]->{tvvol}'></div></td></div>\n";
			$rowssoundbar .= "<td style='width: 8%'><div id='tvmon_addend'><fieldset align='center'><select id='fromtime_$room' name='fromtime_$room' data-mini='true' data-native-menu='true' style='width: 100%'>
								<option value='false'>--</option>
								<option value='0'>0:00</option>
								<option value='1'>1:00</option>
								<option value='2'>2:00</option>
								<option value='3'>3:00</option>
								<option value='4'>4:00</option>
								<option value='5'>5:00</option>
								<option value='6'>6:00</option>
								<option value='7'>7:00</option>
								<option value='8'>8:00</option>
								<option value='9'>9:00</option>
								<option value='10'>10:00</option>
								<option value='11'>11:00</option>
								<option value='12'>12:00</option>
								<option value='13'>13:00</option>
								<option value='14'>14:00</option>
								<option value='15'>15:00</option>
								<option value='16'>16:00</option>
								<option value='17'>17:00</option>
								<option value='18'>18:00</option>
								<option selected='selected' value='19'>19:00</option>
								<option value='20'>20:00</option>
								<option value='21'>21:00</option>
								<option value='22'>22:00</option>
								<option value='23'>23:00</option>
							</select></fieldset></div></td>\n";
			$rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonnight_$room' name='tvmonnight_$room' data-role='flipswitch' style='width: 100%'><option selected='selected' value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
			$rowssoundbar .= "<td style='width: 8%'><div id='tvmon_addend'><fieldset align='center'>\n";
			$rowssoundbar .= "<select id='subgain_$room' name='subgain_$room' data-mini='true' data-native-menu='true' style='width: 100%'>";
			$rowssoundbar .= "	<option value='-15'>-15</option>
								<option value='-14'>-14</option>
								<option value='-12'>-12</option>
								<option value='-11'>-11</option>
								<option value='-10'>-10</option>
								<option value='-9'>-9</option>
								<option value='-8'>-8</option>
								<option value='-7'>-7</option>
								<option value='-6'>-6</option>
								<option value='-5'>-5</option>
								<option value='-4'>-4</option>
								<option value='-3'>-3</option>
								<option value='-2'>-2</option>
								<option value='-1'>-1</option>
								<option selected='selected' value='0'>0</option>
								<option value='1'>1</option>
								<option value='2'>2</option>
								<option value='3'>3</option>
								<option value='4'>4</option>
								<option value='5'>5</option>
								<option value='6'>6</option>
								<option value='7'>7</option>
								<option value='8'>8</option>
								<option value='9'>9</option>
								<option value='10'>10</option>
								<option value='11'>11</option>
								<option value='12'>12</option>
								<option value='13'>13</option>
								<option value='14'>14</option>
								<option value='15'>15</option>
								</select></fieldset></div></td>\n";
			$rowssoundbar .= "</tr>";
		}
	}
	
	if ( $countplayers < 1 ) {
		$rowssonosplayer .= "<tr><td colspan=10>" . $SL{'ZONES.SONOS_EMPTY_ZONES'} . "</td></tr>\n";
	}
	$rowssonosplayer .= "<input type='hidden' id='countplayers' name='countplayers' value='$countplayers'>\n";
	$template->param("ROWSSONOSPLAYER", $rowssonosplayer);
	LOGDEB "Sonos Player has been loaded.";	
	
	if ( $countsoundbars < 1 ) {
		$rowssoundbar .= "<tr class='tvmon_header'><td colspan=8>" . $SL{'ZONES.SONOS_EMPTY_SOUNDBARS'} . "</td></tr>\n";
	} 
	$rowssoundbar .= "<input type='hidden' id='countsoundbars' name='countsoundbars' value='$countsoundbars'>\n";
	$template->param("ROWSOUNDBARS", $rowssoundbar);
	LOGDEB "Sonos Soundbars has been discovered.";
	

	# *******************************************************************************************************************
	# Get Miniserver
	my $mshtml = LoxBerry::Web::mslist_select_html( 
							FORMID => 'ms',
							SELECTED => $jsonobj->{LOXONE}->{Loxone}, 
							DATA_MINI => 1,
							LABEL => "",
							);
	$template->param('MS', $mshtml);
		
	LOGDEB "List of available Miniserver(s) has been successful loaded";
	# *******************************************************************************************************************
		
	# fill dropdown with list of files from tts/mp3 folder
	my $dir = $lbpdatadir.'/'.$ttsfolder.'/'.$mp3folder.'/';
	my $mp3_list;
	
    opendir(DIR, $dir) or die $!;
	my @dots 
        = grep { 
            /\.mp3$/      # just files ending with .mp3
	    && -f "$dir/$_"   # and is a file
	} 
	readdir(DIR);
	my @sorted_dots = sort { $a <=> $b } @dots;		# sort files numericly
    # Loop through the array adding filenames to dropdown
    foreach my $file (@sorted_dots) {
		$mp3_list.= "<option value='$file'>" . $file . "</option>\n";
    }
	closedir(DIR);
	$template->param("MP3_LIST", $mp3_list);
	LOGDEB "List of MP3 files has been successful loaded";
	
	# check if MQTT is installed and valid credentials received
	if ($mqttcred)   {
		$template->param("MQTT" => "true");
		LOGDEB "MQTT Gateway is installed and valid credentials received.";
	} else {
		$template->param("MQTT" => "false");
		$cfg->{LOXONE}->{LoxDatenMQTT} = "false";
		#$jsonobj->write();
		LOGDEB "MQTT Gateway is not installed or wrong credentials received.";
	}
	
	
	# create list of host palyer for follow function	
	my $rowshostplayer;
	foreach my $key (keys %{$config}) {
		my $counthost++;
		$rowshostplayer .= "<option value=".$key." >".$key."</option>";
	}
	$template->param("ROWSHOSTPLAYER", $rowshostplayer);
	
	LOGOK "Sonos Plugin has been successfully loaded.";
	
	# Donation
	if (is_enabled($cfg->{VARIOUS}->{donate})) {
		$template->param("DONATE", 'checked="checked"');
	} else {
		$template->param("DONATE", '');
	}

	printtemplate();
	exit;
}



#####################################################
# Save_details-Sub
#####################################################

sub save_details
{
	my $countradios = param('countradios');
	
	LOGINF "Start writing details configuration file";
	
	$cfg->{TTS}->{volrampto} = "$R::rmpvol";
	$cfg->{TTS}->{rampto} = "$R::rampto";
	$cfg->{TTS}->{correction} = "$R::correction";
	$cfg->{MP3}->{waiting} = "$R::waiting";
	$cfg->{MP3}->{volumedown} = "$R::volume";
	$cfg->{MP3}->{volumeup} = "$R::volume";
	$cfg->{VARIOUS}->{announceradio} = "$R::announceradio";
	$cfg->{VARIOUS}->{announceradio_always} = "$R::announceradio_always";
	$cfg->{VARIOUS}->{phonemute} = "$R::phonemute";
	$cfg->{VARIOUS}->{phonestop} = "$R::phonestop";
	$cfg->{VARIOUS}->{volmax} = "$R::volmax";
	if ($R::follow_host ne "")   {
		$cfg->{VARIOUS}->{follow_host} = "$R::follow_host";
	}
	if ($R::follow_wait ne "")   {
		$cfg->{VARIOUS}->{follow_wait} = "$R::follow_wait";
	}
	$cfg->{LOCATION}->{town} = "$R::town";
	$cfg->{VARIOUS}->{CALDavMuell} = "$R::wastecal";
	$cfg->{VARIOUS}->{CALDav2} = "$R::cal";
	$cfg->{VARIOUS}->{cron} = "$R::cron";
	$cfg->{VARIOUS}->{selfunction} = "$R::func_list";
	$cfg->{SYSTEM}->{checkt2s} = "$R::checkt2s";
	$cfg->{SYSTEM}->{hw_update} = "$R::hw_update";
	$cfg->{SYSTEM}->{hw_update_day} = "$R::hw_update_day";
	$cfg->{SYSTEM}->{hw_update_time} = "$R::hw_update_time";
	$cfg->{SYSTEM}->{hw_update_power} = "$R::hw_update_power";
		
	$jsonobj->write();
	

	  if ($R::cron eq "1") 
	  {
	    system ("ln -s $lbphtmldir/bin/cronjob.sh $lbhomedir/system/cron/cron.01min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
		unlink ("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
		LOGOK "Cron job each Minute created";
	  }
	  if ($R::cron eq "3") 
	  {
	    system ("ln -s $lbphtmldir/bin/cronjob.sh $lbhomedir/system/cron/cron.03min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
		unlink ("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
		LOGOK "Cron job 3 Minutes created";
	  }
	  if ($R::cron eq "5") 
	 {
	    system ("ln -s $lbphtmldir/bin/cronjob.sh $lbhomedir/system/cron/cron.05min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
		unlink ("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
		LOGOK "Cron job 5 Minutes created";
	  }
	  if ($R::cron eq "10") 
	  {
	    system ("ln -s $lbphtmldir/bin/cronjob.sh $lbhomedir/system/cron/cron.10min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
		unlink ("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
		LOGOK "Cron job 10 Minutes created";
	  }
	  if ($R::cron eq "30") 
	  {
	    system ("ln -s $lbphtmldir/bin/cronjob.sh $lbhomedir/system/cron/cron.30min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
		unlink ("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
	    unlink ("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
		LOGOK "Cron job 30 Minutes created";
	  }
		
	LOGOK "Detail settings has been saved successful";
	sleep(3); 
	$navbar{2}{active} = 1;
	$template->param("DETAILS", "1");
	&form;
}

#####################################################
# Save-Sub
#####################################################

sub save 
{
	# Everything from Forms
	my $countplayers	= param('countplayers');
	my $countsoundbars	= param('countsoundbars');
	my $countradios 	= param('countradios');
	my $LoxDaten	 	= param('sendlox');
	my $selminiserver	= param('ms');
	
	# get Miniserver entry from former Versions prior to v3.5.2 (MINISERVER1) and extract last character
	my $sel_ms = substr($selminiserver, -1, 1);
	
	my $gcfg         = new Config::Simple("$lbsconfigdir/general.cfg");
	my $miniservers	= $gcfg->param("BASE.MINISERVERS");
	my $MiniServer	= $gcfg->param("MINISERVER$selminiserver.IPADDRESS");
	my $MSWebPort	= $gcfg->param("MINISERVER$selminiserver.PORT");
	my $MSUser		= $gcfg->param("MINISERVER$selminiserver.ADMIN");
	my $MSPass		= $gcfg->param("MINISERVER$selminiserver.PASS");
			
	# turn on/off MS inbound function 
	if ($LoxDaten eq "true") {
		LOGDEB "Coummunication to Miniserver is switched on";
	} else {
		LOGDEB "Coummunication to Miniserver is switched off.";
	}
		
	# OK - now installing...

	# Write configuration file(s)
	$cfg->{LOXONE}->{Loxone} = "$sel_ms";
	$cfg->{LOXONE}->{LoxDaten} = "$R::sendlox";
	$cfg->{LOXONE}->{LoxDatenMQTT} = "$R::sendloxMQTT";
	if ($R::sendlox eq "true")   {
		if ($R::sendloxMQTT eq "false")  {
			$cfg->{LOXONE}->{LoxPort} = "$R::udpport";
		} else {
			delete $cfg->{LOXONE}->{LoxPort};
		}
	}
	$cfg->{TTS}->{t2s_engine} = "$R::t2s_engine";
	$cfg->{TTS}->{messageLang} = "$R::t2slang";
	$cfg->{TTS}->{apikey} = "$R::apikey";
	$cfg->{TTS}->{apikeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{apikey};
	$cfg->{TTS}->{secretkey} = "$R::seckey";
	$cfg->{TTS}->{secretkeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{secretkey};
	$cfg->{TTS}->{voice} = "$R::voice";
	$cfg->{TTS}->{regionms} = $azureregion;
	$cfg->{TTS}->{t2son} = "$R::t2son";
	$cfg->{MP3}->{MP3store} = "$R::mp3store";
	$cfg->{MP3}->{cachesize} = "$R::cachesize";
	$cfg->{MP3}->{file_gong} = "$R::file_gong";
	$cfg->{VARIOUS}->{donate} = "$R::donate";
	$cfg->{VARIOUS}->{CALDavMuell} = "$R::wastecal";
	$cfg->{VARIOUS}->{CALDav2} = "$R::cal";
	$cfg->{VARIOUS}->{tvmon} = "$R::tvmon";
	$cfg->{VARIOUS}->{starttime} = "$R::starttime";
	$cfg->{VARIOUS}->{endtime} = "$R::endtime";
	$cfg->{LOCATION}->{region} = "$R::region";
	$cfg->{LOCATION}->{googlekey} = "$R::googlekey";
	$cfg->{LOCATION}->{googletown} = "$R::googletown";
	$cfg->{LOCATION}->{googlestreet} = "$R::googlestreet";
	$cfg->{LOCATION}->{town} = "$R::town";
	$cfg->{SYSTEM}->{mp3path} = "$R::STORAGEPATH/$ttsfolder/$mp3folder";
	$cfg->{SYSTEM}->{ttspath} = "$R::STORAGEPATH/$ttsfolder";
	$cfg->{SYSTEM}->{path} = "$R::STORAGEPATH";
	$cfg->{SYSTEM}->{cifsinterface} = "http://$lbip:$lbport/plugins/$lbpplugindir/interfacedownload";
	$cfg->{SYSTEM}->{smbinterface} = "smb://$lbip:$lbport/plugindata/$lbpplugindir/interfacedownload";
	$cfg->{SYSTEM}->{cifsinterface} = "x-file-cifs://$host/plugindata/$lbpplugindir/interfacedownload";
		
	LOGINF "Start writing settings to configuration file";
	
	# If storage folders does not exist, copy default mp3 files
	my $copy = 0;
	if (!-e "$R::STORAGEPATH/$ttsfolder/$mp3folder") {
		$copy = 1;
	}
	
	LOGINF "Creating folders and symlinks";
	system ("mkdir -p $R::STORAGEPATH/$ttsfolder/$mp3folder");
	system ("mkdir -p $R::STORAGEPATH/$ttsfolder");
	system ("rm $lbpdatadir/interfacedownload");
	system ("rm $lbphtmldir/interfacedownload");
	system ("ln -s $R::STORAGEPATH/$ttsfolder $lbpdatadir/interfacedownload");
	system ("ln -s $R::STORAGEPATH/$ttsfolder $lbphtmldir/interfacedownload");
	LOGOK "All folders and symlinks created successfully.";

	if ($copy) {
		LOGINF "Copy existing mp3 files from $lbpdatadir/$ttsfolder/$mp3folder to $R::STORAGEPATH/$ttsfolder/$mp3folder";
		system ("cp -r $lbpdatadir/$ttsfolder/$mp3folder/* $R::STORAGEPATH/$ttsfolder/$mp3folder");
	}
	
	# save radiostations
	for ($i = 1; $i <= $countradios; $i++)   {
		if ( param("chkradios$i") ) { # if radio should be deleted
			delete $cfg->{RADIO}->{radio}->{$i};
		} else { # save
			my $rname = param("radioname$i");
			my $rurl = param("radiourl$i");
			my $curl = param("coverurl$i");
			$rname =~ s/^\s+|\s+$//g;
			$rurl =~ s/^\s+|\s+$//g;
			$curl =~ s/^\s+|\s+$//g;
			$cfg->{RADIO}->{radio}->{$i} = $rname . "," . $rurl . "," . $curl;
		}
	}
	LOGDEB "Radio Stations has been saved.";
	
	# check if scan zones has been executed and min. 1 Player been added
	if ($countplayers < 1)  {
		$error_message = $SL{'ZONES.ERROR_NO_SCAN'};
		&error;
	}
	
	# save Sonos devices
	my $emergecalltts;
	
	for ($i = 1; $i <= $countplayers; $i++) {
		if ( param("chkplayers$i") ) { # if player should be deleted
			delete $cfg->{sonoszonen}->{param("zone$i")};
		} else { # save
			if (param("mainchk$i") eq "on")   {
				$emergecalltts = "on";
			} else {
				$emergecalltts = "off";
			}
			my @player = (  param("ip$i"), 
							param("rincon$i"), 
							param("model$i"), 
							param("t2svol$i"), 
							param("sonosvol$i"), 
							param("maxvol$i"), 
							$emergecalltts, 
							param("models$i"), 
							param("sub$i"), 
							param("householdId$i"), 
							param("sur$i"), 
							param("audioclip$i"), 
							param("voice$i"),
							param("sb$i")
						 );
						 
			if ($R::tvmon eq "true")  {
				if (param("sb$i") eq "SB")   {
					# add soundbar settings to zone
					my $room = param("zone$i");
					
					my $tvmonspeech = param("tvmonspeech_$room");
					my $usesb = param("usesb_$room");
					my $tvvol = param("tvvol_$room");
					my $tvmonsurr = param("tvmonsurr_$room");
					my $fromtime = param("fromtime_$room");
					my $tvmonnight = param("tvmonnight_$room");
					my $tvmonnightsub = param("tvmonnightsub_$room");
					my$tvmonnightsublevel = param("subgain_$room");
					my @sbs = (  {"tvmonspeech" => $tvmonspeech,
								"usesb" => $usesb,
								"tvvol" => $tvvol,
								"tvmonsurr" =>$tvmonsurr,
								"fromtime" => $fromtime,
								"tvmonnight" => $tvmonnight,
								"tvmonnightsub" => $tvmonnightsub,
								"tvmonnightsublevel" => $tvmonnightsublevel
								}					
							);
					push @player , @sbs;
				} else {
					# if no Soundbar
					my @sbs = ("false");
					push @player , @sbs;
				}
			} else {
				# TV Monitor turned off
				my @sbs = ("false");
				push @player , @sbs;
			}
			$cfg->{sonoszonen}->{param("zone$i")} = \@player;
		}
	}
	
	$jsonobj->write();
	LOGDEB "Sonos Zones has been saved.";
	
	# call to prepare XML Template during saving
	if ($R::sendlox eq "true") {
		&prep_XML;
	}
	
	my $tv = qx(/usr/bin/php $lbphtmldir/bin/tv_monitor_conf.php);	
	LOGOK "Main settings has been saved successful";
	
	#&print_save;
	my $on = qx(/usr/bin/php $lbphtmldir/bin/check_on_state.php);	
	return;
	
}





#####################################################
# Scan Sonos Player - Sub
#####################################################

sub scan
{

	my $error_volume = $SL{'T2S.ERROR_VOLUME_PLAYER'};
	
	LOGINF "Scan for Sonos Zones has been executed.";
	
	# executes PHP network.php script (read existing config and add new zones)
	my $response = qx(/usr/bin/php $lbphtmldir/system/$scanzonesfile);
			
	if ($response eq "[]") {
		LOGINF "No new Players has been added to Plugin.";
		return($countplayers);
	} elsif ($response eq "")  {
		$error_message = $SL{'ERRORS.ERR_SCAN'};
		&error;
	} else {
		LOGOK "JSON data from application has been succesfully received.";
		my $config = decode_json($response);
	
		# create table of Sonos devices
		foreach my $key (keys %{$config})
		{
			my $filename = $lbphtmldir.'/images/icon-'.$config->{$key}->[7].'.png';
				
			$countplayers++;
			$rowssonosplayer .= "<tr><td style='height: 25px; width: 4%;'><INPUT type='checkbox' style='width: 20px' name='chkplayers$countplayers' id='chkplayers$countplayers' align='center'/></td>\n";
			$rowssonosplayer .= "<td style='height: 28px; width: 16%;'><input type='text' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$key' style='width: 100%; background-color: #e6e6e6;'/></td>\n";
			$rowssonosplayer .= "<td style='height: 25px; width: 4%;'><DIV class='chk-group'><INPUT type='checkbox' class='chk-checked' name='mainchk$countplayers' id='mainchk$countplayers' value='$config->{$key}->[6]' align='center'/></DIV></td>\n";
			$rowssonosplayer .= "<td style='height: 28px; width: 15%;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='$config->{$key}->[2]' style='width: 100%; background-color: #e6e6e6;'/></td>\n";
			# Column Sonos Player Logo
			if (-e $filename) {
				$rowssonosplayer .= "<td style='height: 28px; width: 2%;'><img src='/plugins/$lbpplugindir/images/icon-$config->{$key}->[7].png' border='0' width='50' height='50' align='middle'/></td>\n";
			} else {
				$rowssonosplayer .= "<td style='height: 28px; width: 2%;'><img src='/plugins/$lbpplugindir/images/sonos_logo_sm.png' border='0' width='50' height='50' align='middle'/></td>\n";
			}
			$rowssonosplayer .= "<td style='height: 28px; width: 17%;'><input type='text' id='ip$countplayers' name='ip$countplayers' size='30' value='$config->{$key}->[0]' style='width: 100%; background-color: #e6e6e6;'/></td>\n";
			# Column Clip Pic green/yellow/red
			if ($config->{$key}->[11] and is_enabled($config->{$key}->[11]))   {
				if ($config->{$key}->[12] and is_enabled($config->{$key}->[12]))   {
					$rowssonosplayer .= "<td style='height: 30px; width: 30px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/green.png' border='0' width='26' height='28' align='center'/></div></td>\n";
				} else {
					$rowssonosplayer .= "<td style='height: 30px; width: 30px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/yellow.png' border='0' width='26' height='28' align='center'/></div></td>\n";
				}
			} else {
				$rowssonosplayer .= "<td style='height: 30px; width: 30px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/red.png' border='0' width='26' height='28' align='center'/></div></td>\n";
			}
			$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='t2svol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='t2svol$countplayers' value='$config->{$key}->[3]'' /></td>\n";
			$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='sonosvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='sonosvol$countplayers' value='$config->{$key}->[4]'' /></td>\n";
			$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='maxvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='maxvol$countplayers' value='$config->{$key}->[5]'' /></td>\n";
			# Column Soundbar Volume
			if (($config->{$key}[13]) eq "SB")   {
				$rowssonosplayer .= "<input type='hidden' id='sb$countplayers' size='100' name='sb$countplayers' value='$config->{$key}->[13]'>\n";
			} else {
				$rowssonosplayer .= "<input type='hidden' id='sb$countplayers' size='100' name='sb$countplayers' value='NOSB'>\n";
			}
			$rowssonosplayer .= "<input type='hidden' id='models$countplayers' name='models$countplayers' value='$config->{$key}->[7]'>\n";
			$rowssonosplayer .= "<input type='hidden' id='sub$countplayers' name='sub$countplayers' value='$config->{$key}->[8]'>\n";
			$rowssonosplayer .= "<input type='hidden' id='householdId$countplayers' name='householdId$countplayers' value='$config->{$key}->[9]'>\n";
			$rowssonosplayer .= "<input type='hidden' id='sur$countplayers' name='sur$countplayers' value='$config->{$key}->[10]'>\n";
			$rowssonosplayer .= "<input type='hidden' id='audioclip$countplayers' name='audioclip$countplayers' value='$config->{$key}->[11]'>\n";
			$rowssonosplayer .= "<input type='hidden' id='voice$countplayers' name='voice$countplayers' value='$config->{$key}->[12]'>\n";
			$rowssonosplayer .= "<input type='hidden' id='rincon$countplayers' name='rincon$countplayers' value='$config->{$key}->[1]'>\n";
		}
		$template->param("ROWSSONOSPLAYER", $rowssonosplayer);
		LOGOK "New Players has been added to Plugin.";
		return($countplayers);
	}
}

#####################################################
# Volume page
#####################################################

sub volumes
{	
	$template->param(FORMNO => 'VOLUME' );

	# check if Config file already exists
	if (-e $lbpconfigdir . "/" .$volumeconfigfile)    {
		LOGDEB("Volume Profiles Config file already exist");	
	} else {
		my $volfile = qx(/usr/bin/php $lbphtmldir/bin/vol_prof_ini.php);
		LOGDEB("New Volume Profiles Config file has been created");		
	}
	$rowsvolplayer;
	my @vcfg;

	# count Profiles
	my $jsonobjvol = LoxBerry::JSON->new();
	my $vcfg = $jsonobj->open(filename => $lbpconfigdir . "/" . $volumeconfigfile);
	
	my $last_id = (keys @$vcfg);
	my $config = $cfg->{sonoszonen};
	#LOGDEB(Dumper($vcfg));
	
	# create table header
	for (my $id = 1; $id <= $last_id; $id++)   {
		$countplayers = 0;
		$rowsvolplayer .= "<table class='tables' style='width:100%' id='tblvol_prof$id' name='tblvol_prof$id'>\n";
		$rowsvolplayer .= "<th align='left' style='height: 25px; width:100px'>&nbsp;Profile #$id</th>\n";
		$rowsvolplayer .= "<th align='middle' colspan='6'><div style='width: 180px; align: left'>\n";
		$rowsvolplayer .= "<input class='textfield' type='text' style='align: middle; width: 100%' id='profile$id' name='profile$id' value='' placeholder='Volume Profile Name'/>\n";
		$rowsvolplayer .= "<td valign='left'>";
		$rowsvolplayer .= "<img title='Load current values from Sonos devices' value='$id' id='btnload$id' name='btnload$id' class='ico-load' src='/plugins/$lbpplugindir/images/musik-note.png' border='0' width='30' height='30'>\n";
		if ($last_id > 1)   {
			$rowsvolplayer .= "<img title='Delete current Profile 'onclick='' value='$id' id='btndel$id' name='btndel$id' class='ico-delete' src='/plugins/$lbpplugindir/images/recycle-bin.png' border='0' width='30' height='30'></td>\n";
		}
		$rowsvolplayer .= "</th><tr><th style='background-color: #6dac20;' align='left'>&nbsp;Rooms</th><div class='form-group col-7'>\n";
		$rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>V</th>\n";
		$rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>T</th>\n";
		$rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>B</th>\n";
		$rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>L</th>\n";
		$rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>SR</th>\n";
		$rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>SW</th>\n";
		$rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>SWL</th>\n";
		$rowsvolplayer .= "</div></tr>";
		# create table rows	
		foreach my $key (sort keys %$config) {
			$countplayers++;
			$_ = "$countplayers";
			my $zid = $_."_".$id;
			my $error_volume = $SL{'T2S.ERROR_VOLUME_PLAYER'};
			my $error_treble_bass = $SL{'VOLUME_PROFILES.ERROR_TREBLE_BASS_PLAYER'};
			my $error_sbass = $SL{'VOLUME_PROFILES.ERROR_SUB_LEVEL_PLAYER'};
			$rowsvolplayer .= "<tr><div class='container'>";
			#my $statusfile = $lbpdatadir.'/PlayerStatus/s4lox_on_'.$key.'.txt';
			#if (-e $statusfile) {
				$rowsvolplayer .= "<td style='height: 15px; width: 160px;'><input type='text' id='zone_$zid' name='zone_$zid' readonly='true' value='$key' style='width: 100%; background-color: #e6e6e6;'></td>\n";	
			#} else {
				#$rowsvolplayer .= "<td style='height: 15px; width: 160px;'><input type='text' id='zone_$zid' name='zone_$zid' readonly='true' value='$key' style='width: 100%; background-color: #e6e6e6;'></td>\n";	
			#}
			$rowsvolplayer .= "<td style='width: 45px; height: 15px;'><input type='text' class='form-validation' id='vol_$zid' name='vol_$zid' size='100' data-validation-rule='special:number-min-max-value:0:100' data-validation-error-msg='$error_volume' value='$vcfg->[$id-1]->{Player}->{$key}->[0]->{Volume}'></td>\n";
			$rowsvolplayer .= "<td style='width: 45px; height: 15px;'><input type='text' class='form-validation' id='treble_$zid' name='treble_$zid' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='$error_treble_bass' value='$vcfg->[$id-1]->{Player}->{$key}->[0]->{Treble}'></td>\n";
			$rowsvolplayer .= "<td style='width: 45px; height: 15px;'><input type='text' class='form-validation' id='bass_$zid' name='bass_$zid' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='$error_treble_bass' value='$vcfg->[$id-1]->{Player}->{$key}->[0]->{Bass}'></td>\n";
			$rowsvolplayer .= "<td style='height: 10px; width: 5px; align: middle'>";
			$rowsvolplayer .= "<fieldset><select onchange='' id='loudness_$zid' name='loudness_$zid' data-role='flipswitch' style='width: 5%'>\n";
			$rowsvolplayer .= "<option value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option>";
			$rowsvolplayer .= "</select></fieldset></td>\n";
			$rowsvolplayer .= "<td style='height: 10px; width: 25px; align: middle'>";
			$rowsvolplayer .= "<fieldset><select onchange='' id='surround_$zid' name='surround_$zid' data-role='flipswitch' style='width: 5%'>\n";
			$rowsvolplayer .= "<option value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option>";
			$rowsvolplayer .= "</select></fieldset></td>\n";
			$rowsvolplayer .= "<td style='height: 10px; width: 25px; align: middle'>";
			$rowsvolplayer .= "<fieldset><select onchange='' id='subwoofer_$zid' name='subwoofer_$zid' data-role='flipswitch' style='width: 5%'>\n";
			$rowsvolplayer .= "<option value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option>";
			$rowsvolplayer .= "</select></fieldset></td>\n";
			$rowsvolplayer .= "<td style='width: 55px; height: 15px;'><input type='text' class='form-validation' id='sbass_$zid' name='sbass_$zid' size='100' data-validation-rule='special:number-min-max-value:-15:15' data-validation-error-msg='$error_sbass' value='$vcfg->[$id-1]->{Player}->{$key}->[0]->{Subwoofer_level}'></td>\n";
			$rowsvolplayer .= "</div></tr>";
		}
		$rowsvolplayer .= "<br>";
	}
	$rowsvolplayer .= "</table>";
	$rowsvolplayer .= "<input type='hidden' id='last_id' name='last_id' value='$last_id'>\n";
	$rowsvolplayer .= "<input type='hidden' id='new_id' name='new_id' value='$last_id'>\n";
	$rowsvolplayer .= "<input type='hidden' id='delprofil' name='delprofil' value=0>\n";
	$rowsvolplayer .= "<input type='hidden' id='countplayers' name='countplayers' value='$countplayers'>\n";
	$template->param("ROWSVOLPLAYER", $rowsvolplayer);
	LOGOK "Sound Profiles has been loaded successful.";
}



#####################################################
# Save Volume Profiles
#####################################################

sub save_volume
{

	# Everything from Forms
	my $countplayers	= param('countplayers');
	my $last_id			= param('last_id');
	my $new_id			= param('new_id');
	my $surround;
	my $subwoofer;
	my $Subwoofer_level;
	my $loudness;
	my @profiles;
	
	my $jsonobjvol = LoxBerry::JSON->new();
	my $vcfg = $jsonobjvol->open(filename => $lbpconfigdir . "/" . $volumeconfigfile);
	
	# delete selected profile
	if (param("delprofil") > 0) { # if player should be deleted
		my $jsonobjvol = LoxBerry::JSON->new();
		my $vcfg = $jsonobjvol->open(filename => $lbpconfigdir . "/" . $volumeconfigfile);
		my $c = param("delprofil") - 1;
		splice @{ $vcfg }, $c, 1;
		$jsonobjvol->write();
		LOGOK "Sound Profile has been deleted successful.";
		&volumes;
		$navbar{3}{active} = 1;
		$template->param("VOLUME", "1");
		&form;
	}
	
	# save profiles
	for ($i = 1; $i <= $new_id; $i++) {
		$vcfg->[$i - 1]->{Name} = lc(param("profile$i"));
		$_ = "$i";
		for (my $k = 1; $k <= $countplayers; $k++)   {
			my $zid = $k."_".$_;
			my $zone = param("zone_$zid");
			# prepare Surround
			if ($cfg->{sonoszonen}->{$zone}->[10] eq "NOSUR")   {
				$surround = "na";
			} else {
				if (is_enabled(param("surround_$zid")))   {
					$surround = "true";
				} else {
					$surround = "false";
				}
			}
			# prepare Subwoofer
			if ($cfg->{sonoszonen}->{$zone}->[8] eq "NOSUB")   {
				$subwoofer = "na";
				$Subwoofer_level = "";
			} else {
				$Subwoofer_level = param("sbass_$zid");
				if (is_enabled(param("subwoofer_$zid")))   {
					$subwoofer = "true";
				} else {
					$subwoofer = "false";
				}
			}
			# prepare Loudness
			if (is_enabled(param("loudness_$zid")))   {
				$loudness = "true";
			} else {
				$loudness = "false";
			}
			my $Volume = param("vol_$zid");
			my $Treble = param("treble_$zid"); 
			my $Bass = param("bass_$zid"); 
			my @profiles = (  	{"Volume" => $Volume, 
								"Treble" => $Treble, 
								"Bass" => $Bass,
								"Loudness" => $loudness, 
								"Surround" => $surround,
								"Subwoofer" => $subwoofer,
								"Subwoofer_level" => $Subwoofer_level
								}
						    );
			push @profiles;
			$vcfg->[$i - 1]->{Player}->{$zone} = \@profiles;
		}
	}
	$jsonobjvol->write();
	LOGOK "Sound Profile has been saved";
	&volumes;
	$navbar{3}{active} = 1;
	$template->param("VOLUME", "1");
	&form;
}

#####################################################
# Get Engine keys (AJAX)
#####################################################

sub getkeys
{
	print "Content-type: application/json\n\n";
	my $engine = defined $R::t2s_engine ? $R::t2s_engine : "";
	my $apikey = defined $cfg->{TTS}->{apikeys}->{$engine} ? $cfg->{TTS}->{apikeys}->{$engine} : "";
	my $secret = defined $cfg->{TTS}->{secretkeys}->{$engine} ? $cfg->{TTS}->{secretkeys}->{$engine} : "";
	print "{\"apikey\":\"$apikey\",\"seckey\":\"$secret\"}";
	exit;
}


#####################################################
# execute PHP script ot generate XML Template - Sub
#####################################################
 
 sub prep_XML
{
	# executes PHP script and saves XML Template local
	my $udp_temp = qx(/usr/bin/php $lbphtmldir/system/$udp_file);
	LOGOK "XML Template files generation has been called";
	return();
}
 
	
#####################################################
# Error-Sub
#####################################################

sub error 
{
	$template->param("SETTINGS", "1");
	$template->param('ERR_MESSAGE', $error_message);
	LOGERR($error_message);
	&form;
}


##########################################################################
# Init Template
##########################################################################

sub inittemplate
{
	# Check, if filename for the maintemplate is readable, if not raise an error
	stat($lbptemplatedir . "/" . $maintemplatefilename);
	if ( !-r _ )
	{
		$error_message = "Error: Main template not readable";
		LOGCRIT "The ".$maintemplatefilename." file could not be loaded. Abort plugin loading";
		LOGCRIT $error_message;
		&error;
	}
	$template =  HTML::Template->new(
				filename => $lbptemplatedir . "/" . $maintemplatefilename,
				global_vars => 1,
				loop_context_vars => 1,
				die_on_bad_params=> 0,
				associate => $jsonobj,
				%htmltemplate_options,
				debug => 1
				);
	%SL = LoxBerry::System::readlanguage($template, $languagefile);			

}


##########################################################################
# Print Template
##########################################################################

sub printtemplate
{	
	#our $htmlhead = '<link rel="stylesheet" type="text/css" href="css/flipswitch.css" media="screen" />';
	LoxBerry::Web::lbheader("$SL{'BASIS.MAIN_TITLE'}: v$sversion", $helplink, $helptemplate);
	print LoxBerry::Log::get_notifications_html($lbpplugindir);
	print $template->output();
	LoxBerry::Web::lbfooter();
	exit;
}



##########################################################################
# END routine - is called on every exit (also on exceptions)
##########################################################################
sub END 
{	
	our @reason;
	
	if ($log) {
		if (@reason) {
			#$template->param("SETTINGS", "1");
			#$template->param('ERR_MESSAGE', "Unhandled exception catched: ".@reason);
			LOGCRIT "Unhandled exception catched: ";
			LOGERR @reason;
			LOGEND "Finished with an exception";
			#&form;
		} elsif ($error_message) {
			LOGEND "Finished with error: ".$error_message;
		} else {
			LOGEND "Finished successful";
		}
	}
}
