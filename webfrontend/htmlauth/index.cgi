#!/usr/bin/perl

# Copyright 2016 Michael Schlenstedt, michael@loxberry.de
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

use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use CGI;
use LWP::UserAgent;
use File::HomeDir;
use Cwd 'abs_path';
use JSON qw( decode_json );
use utf8;
use warnings;
#use strict;
#no strict "refs"; # we need it for template system

##########################################################################
# Variables
##########################################################################

our $cfg;
our $pcfg;
our $namef;
our $value;
our %query;
our $lang;
our $template_title;
our $help;
our @help;
our $helptext;
our $helplink;
our $planguagefile;
our $error;
our $saveformdata = 0;
our $output;
our $message;
our $nexturl;
our $do = "form";
our $lbphtmldir;
our $pname;
our $languagefileplugin;
our $pphrase;
our $step;
our $miniservers;
our $config_path;
our $t2s_engine;
our $MP3store;
our $apikey;
our $seckey;
our $voice;
our $file_gong;
our $LoxDaten;
our $udpport;
our $volume;
our $ttssinglewait;
our $rampto;
our $rmpvol;
our $debugging;
our $town;
our $region;
our $rsender;
our $rsenderurl;
our $miniserver;
our $msselectlist;
our $googlekey;
our $googletown;
our $googlestreet;
our $announceradio;
our $selectedannounceradio1;
our $selectedannounceradio2;
our $maxzap;
our $wastecal;
our $cal;
our $code;
our $language;
our $pluginlogfile;
our @radioarray;
our $i;

$LoxBerry::System::DEBUG		= 1;
$LoxBerry::Web::DEBUG 			= 1;

my $helptemplatefilename		= "help.html";
my $languagefile 				= "sonos.ini";
my $maintemplatefilename	 	= "sonos.html";
my $successtemplatefilename 	= "success.html";
my $errortemplatefilename 		= "error.html";
my $no_error_template_message	= "<b>Sonos4lox:</b> The error template is not readable. We must abort here. Please try to reinstall the plugin.";
my $pluginconfigfile 			= "sonos.cfg";
my $pluginplayerfile 			= "player.cfg";
my $plugintempplayerfile	 	= "tmp_player.json";
my $scanzonesfile	 			= "network.php";
my $helplink 					= "http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone";
my $pcfg 						= new Config::Simple($lbpconfigdir . "/" . $pluginconfigfile);
my %so_config 					= $pcfg->vars() if ( $pcfg );

##########################################################################
# Read Settings
##########################################################################

my $cfg         = new Config::Simple("$lbsconfigdir/general.cfg");
my $lang        = $cfg->param("BASE.LANG");
my $miniservers	= $cfg->param("BASE.MINISERVERS");
my $MiniServer	= $cfg->param("MINISERVER1.IPADDRESS");
my $MSWebPort	= $cfg->param("MINISERVER1.PORT");
my $MSUser		= $cfg->param("MINISERVER1.ADMIN");
my $MSPass		= $cfg->param("MINISERVER1.PASS");

# Load sonos config data into hash
my %so_config;
tie %so_config, "Config::Simple", "$lbpconfigdir/$pluginconfigfile";

# Load player data into hash
my %pl_config;
tie %pl_config, "Config::Simple", "$lbpconfigdir/$pluginplayerfile";


my $template = HTML::Template->new(
			filename => $lbptemplatedir . "/" . $maintemplatefilename,
			global_vars => 1,
			loop_context_vars => 1,
			die_on_bad_params=> 0,
			associate => $cfg,
			);
			
# read language
my %SL = LoxBerry::System::readlanguage($template, $languagefile);

# übergibt Plugin Verzeichnis an HTML
$template->param(PLUGINDIR => $lbpplugindir);

# Read Plugin Version
my $sversion = LoxBerry::System::pluginversion();

# read all POST-Parameter in namespace "R".
my $cgi = CGI->new;
$cgi->import_names('R');



#########################################################################
# Parameter
#########################################################################

# Everything from URL
foreach (split(/&/,$ENV{'QUERY_STRING'})){
  ($namef,$value) = split(/=/,$_,2);
  $namef =~ tr/+/ /;
  $namef =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $value =~ tr/+/ /;
  $value =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $query{$namef} = $value;
}

# Set parameters coming in - get over post
if ( !$query{'saveformdata'} ) { 
	if ( param('saveformdata') ) { 
		$saveformdata = quotemeta(param('saveformdata')); 
	} else { 
		$saveformdata = 0;
	} 
} else { 
	$saveformdata = quotemeta($query{'saveformdata'}); 
}

if ( !$query{'do'} ) { 
	if ( param('do')) {
		$do = quotemeta(param('do'));
	} else {
		$do = "form";
	}
} else {
	$do = quotemeta($query{'do'});
}


# Everything we got from forms
$saveformdata         = param('saveformdata');
defined $saveformdata ? $saveformdata =~ tr/0-1//cd : undef;


##########################################################################
# Various checks
##########################################################################

# Check, if filename for the maintemplate is readable, if not raise an error
$error_message = $ERR{'ERRORS.ERR_MAIN_TEMPLATE_NOT_READABLE'};
stat($lbptemplatedir . "/" . $maintemplatefilename);
&error if !-r _;

#**************************************************************************

# Check, if filename for the errortemplate is readable
stat($lbptemplatedir . "/" . $errortemplatefilename);
if ( !-r _ )
{
	$error_message = $no_error_template_message;
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	print $error_message;
	LoxBerry::Web::lbfooter();
	exit;
}


# Filename for the errortemplate is ok, preparing template";
my $errortemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $errortemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		associate => $cgi,
		%htmltemplate_options,
		debug => 1,
		);
my %ERR = LoxBerry::System::readlanguage($errortemplate, $languagefile);

#**************************************************************************

# Check, if filename for the successtemplate is readable
stat($lbptemplatedir . "/" . $successtemplatefilename);
if ( !-r _ )
{
	$error_message = $ERR{'ERRORS.ERR_SUCCESS_TEMPLATE_NOT_READABLE'};
	&error;
}
#LOGDEB "Filename for the successtemplate is ok, preparing template";
my $successtemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $successtemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		associate => $cgi,
		%htmltemplate_options,
		debug => 1,
		);
my %SUC = LoxBerry::System::readlanguage($successtemplate, $languagefile);

#**************************************************************************

# Check, if filename for the maintemplate is readable, if not raise an error
$error_message = $ERR{'ERRORS.ERR_MAIN_TEMPLATE_NOT_READABLE'};
stat($lbptemplatedir . "/" . $maintemplatefilename);
&error if !-r _;
my $maintemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $maintemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		%htmltemplate_options,
		debug => 1
		);
my %L = LoxBerry::System::readlanguage($maintemplate, $languagefile);

#**************************************************************************

# Check if plugin config file is readable
if (!-r $lbpconfigdir . "/" . $pluginconfigfile) 
{
	$error_message = $ERR{'ERRORS.ERR_NO_CONFIG_FILE'};
	&error;
	exit;	
}

#**************************************************************************

# Check if plugin player details file is readable
if (!-r $lbpconfigdir . "/" . $pluginplayerfile) 
{
	$error_message = $ERR{'ERRORS.ERR_NO_CONFIG_FILE'};
	&error;
	exit;	
}


##########################################################################
# Language Settings
##########################################################################

my $lang = lblanguage();
$template->param("LBHOSTNAME", lbhostname());
$template->param("LANG", $lang);
$template->param("SELFURL", $ENV{REQUEST_URI});


##########################################################################
# Main program
##########################################################################

if ($saveformdata) {
  &save;

} else {
  &form;

}

exit;

#####################################################
# Subroutines
#####################################################

#####################################################
# Form-Sub
#####################################################

sub form {

	# fill saved values into form
	$template		->param("SELFURL", $ENV{REQUEST_URI});
	$template		->param("APIKEY" 		=> $pcfg->param("TTS.API-key"));
	$template		->param("SECKEY" 		=> $pcfg->param("TTS.secret-key"));
	$template		->param("DEBUGGING" 	=> $pcfg->param("SYSTEM.debuggen"));
	$template		->param("LOXDATEN" 		=> $pcfg->param("LOXONE.LoxDaten"));
	$template		->param("UDPPORT" 		=> $pcfg->param("LOXONE.LoxPort"));
	$template		->param("MINISERVER" 	=> $pcfg->param("LOXONE.Loxone"));
	$template		->param("T2S_ENGINE" 	=> $pcfg->param("TTS.t2s_engine"));
	$template		->param("VOICE" 		=> $pcfg->param("TTS.voice"));
	$template		->param("CODE" 			=> $pcfg->param("TTS.messageLang"));
	$template		->param("RAMPTO"		=> $pcfg->param("TTS.rampto"));
	$template		->param("RMPVOL"		=> $pcfg->param("TTS.volrampto"));
	$template		->param("MP3STORE"		=> $pcfg->param("MP3.MP3store"));
	$template		->param("VOLUMEDOWN"	=> $pcfg->param("MP3.volumedown"));
	$template		->param("FILE_GONG"		=> $pcfg->param("MP3.file_gong"));
	$template		->param("TOWN"			=> $pcfg->param("LOCATION.town"));
	$template		->param("REGION"		=> $pcfg->param("LOCATION.region"));
	$template		->param("GOOGLEKEY"		=> $pcfg->param("LOCATION.googlekey"));
	$template		->param("GOOGLETOWN"	=> $pcfg->param("LOCATION.googletown"));
	$template		->param("GOOGLESTREET"	=> $pcfg->param("LOCATION.googlestreet"));
	$template		->param("ANNOUNCRADIO"	=> $pcfg->param("VARIOUS.announceradio"));
	$template		->param("MAXZAP"		=> $pcfg->param("VARIOUS.maxzap"));
	$template		->param("WASTECAL"		=> $pcfg->param("VARIOUS.CALDavMuell"));
	$template		->param("CAL"			=> $pcfg->param("VARIOUS.CALDav2"));
	
	# Load saved values for "select"
	my $debugging		  = $pcfg->param("SYSTEM.debuggen");	
	my $LoxDaten		  = $pcfg->param("LOXONE.LoxDaten");
	my $miniserver	  	  = $pcfg->param("LOXONE.Loxone");
	my $t2s_engine		  = $pcfg->param("TTS.t2s_engine");
	my $rmpvol	 	  	  = $pcfg->param("TTS.volrampto");
	my $MP3store 		  = $pcfg->param("MP3.MP3store");
	my $volume		  	  = $pcfg->param("MP3.volumedown");
	my $announceradio	  = $pcfg->param("VARIOUS.announceradio");
	my $region		  	  = $pcfg->param("LOCATION.region");
		
	# Radiosender auslesen
	our $countradios = 0;
	our $rowsradios;
	
	my %SEL = LoxBerry::System::readlanguage($maintemplate);

	my %config = $pcfg->vars();	
	foreach my $key (keys %config) {
		if ( $key =~ /^RADIO/ ) {
			$countradios++;
			my @fields = $pcfg->param($key);
			$rowsradios .= "<tr><td style='height: 25px; width: 43px;' class='auto-style1'><INPUT type='checkbox' style='width: 20px' name='chkradios$countradios' id='chkradios$countradios' align='center'/></td>\n";
			$rowsradios .= "<td style='height: 28px'><input type='text' id='radioname$countradios' name='radioname$countradios' size='20' value='@fields[0]' /> </td>\n";
			$rowsradios .= "<td style='width: 888px; height: 28px'><input type='text' id='radiourl$countradios' name='radiourl$countradios' size='100' value='@fields[1]' style='width: 862px' /> </td></tr>\n";
		}
	}

	if ( $countradios < 1 ) {
		$rowsradios .= "<tr><td colspan=3>" . $SL{'RADIO.SONOS_EMPTY_RADIO'} . "</td></tr>\n";
	}
	$rowsradios .= "<input type='hidden' id='countradios' name='countradios' value='$countradios'>\n";
	$template->param("ROWSRADIO", $rowsradios);
	
	# *******************************************************************************************************************

	# Als erstes vorhandene Player aus player.cfg einlesen
	our $countplayers = 0;
	our $rowssonosplayer;

	my $playercfg = new Config::Simple($lbpconfigdir . "/" . $pluginplayerfile);
	my %config = $playercfg->vars();	

	foreach my $key (keys %config) {
		$countplayers++;
		my $room = $key;
		$room =~ s/^SONOSZONEN\.//g;
		$room =~ s/\[\]$//g;
		my @fields = $playercfg->param($key);
		$rowssonosplayer .= "<tr><td style='height: 25px; width: 43px;' class='auto-style1'><INPUT type='checkbox' style='width: 20px' name='chkplayers$countplayers' id='chkplayers$countplayers' align='center'/></td>\n";
		$rowssonosplayer .= "<td style='height: 25px; width: 196px;'><input type='text' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$room' style='width: 133px' /> </td>\n";
		$rowssonosplayer .= "<td style='height: 28px; width: 147px;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='@fields[2]' style='width: 153px' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='t2svol$countplayers' size='100' data-validation='number' data-validation-allowing='range[1;100]' data-validation-error-msg='T2S Vol: Please enter Volume between 1 to 100.' name='t2svol$countplayers' value='@fields[3]' style='width: 52px' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='sonosvol$countplayers' size='100' data-validation='number' data-validation-allowing='range[1;100]' data-validation-error-msg='Sonos Vol: Please enter Volume between 1 to 100.' name='sonosvol$countplayers' value='@fields[4]' style='width: 52px' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='maxvol$countplayers' size='100' data-validation='number' data-validation-allowing='range[1;100]' data-validation-error-msg='Max Vol: Please enter Volume between 1 to 100.' name='maxvol$countplayers' value='@fields[5]' style='width: 52px' /> </td> </tr>\n";
		$rowssonosplayer .= "<input type='hidden' id='ip$countplayers' name='ip$countplayers' value='@fields[0]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='rincon$countplayers' name='rincon$countplayers' value='@fields[1]'>\n";
	}
		
	# Call Subroutine to scan/import Sonos Zones
	if ( $do eq "scan" ) {
		&scan;
	}

	if ( $countplayers < 1 ) {
		$rowssonosplayer .= "<tr><td colspan=6>" . $SL{'ZONES.SONOS_EMPTY_ZONES'} . "</td></tr>\n";
	}
	$rowssonosplayer .= "<input type='hidden' id='countplayers' name='countplayers' value='$countplayers'>\n";
	$template->param("ROWSSONOSPLAYER", $rowssonosplayer);
	
	# read Miniservers
	$cfgd  = new Config::Simple($lbsconfigdir ."/general.cfg");
	for (my $i = 1; $i <= $cfgd->param('BASE.MINISERVERS');$i++) {
	    if ("MINISERVER$i" eq $miniserver) {
		    $msselectlist .= '<option selected value="'.$i.'">'.$cfgd->param("MINISERVER$i.NAME")."</option>\n";
		} else {
		    $msselectlist .= '<option value="'.$i.'">'.$cfgd->param("MINISERVER$i.NAME")."</option>\n";
		}
	}
	$template->param("MSSELECTLIST", $msselectlist);
	
	
	# Prepare form defaults
	
	# T2S_ENGINE
	if ($t2s_engine eq "1001") {
	  $selectedinstanz2 = "checked=checked";
	  $template->param("SELECTEDINSTANZ2", $selectedinstanz2);
	} elsif ($t2s_engine eq "3001") {
	  $selectedinstanz3 = "checked=checked";
	  $template->param("SELECTEDINSTANZ3", $selectedinstanz3);
	} elsif ($t2s_engine eq "4001") {
      $selectedinstanz4 = "checked=checked";
	  $template->param("SELECTEDINSTANZ4", $selectedinstanz4);
	} elsif ($t2s_engine eq "5001") {
      $selectedinstanz5 = "checked=checked";
	  $template->param("SELECTEDINSTANZ5", $selectedinstanz5);
	} elsif ($t2s_engine eq "6001") {
      $selectedinstanz6 = "checked=checked";
	  $template->param("SELECTEDINSTANZ6", $selectedinstanz6);
	} elsif ($t2s_engine eq "7001") {
      $selectedinstanz7 = "checked=checked";
	  $template->param("SELECTEDINSTANZ7", $selectedinstanz7);
    } else {
	  $selectedinstanz2 = "checked=checked";
	  $template->param("SELECTEDINSTANZ2", $selectedinstanz2);
	}
	
	
	# DEBUGGING
	if ($debugging eq "0") {
	  $selecteddebug1 = "selected=selected";
	  $template->param("SELECTEDDEBUG1", $selecteddebug1);
	} elsif ($debugging eq "1") {
	  $selecteddebug2 = "selected=selected";
	  $template->param("SELECTEDDEBUG2", $selecteddebug2);
	} elsif ($debugging eq "0") {
	  $selecteddebug1 = "selected=selected";
	  $template->param("SELECTEDDEBUG1", $selecteddebug1);
	} 
	
	# LOXONE
	if ($LoxDaten eq "false") {
	  $selectedsendlox1 = "selected=selected";
	  $template->param("SELECTEDSENDLOX1", $selectedsendlox1);
	} elsif ($LoxDaten eq "true") {
	  $selectedsendlox2 = "selected=selected";
	  $template->param("SELECTEDSENDLOX2", $selectedsendlox2);
	} else {
	  $selectedsendlox1 = "selected=selected";
	  $template->param("SELECTEDSENDLOX1", $selectedsendlox1);
	} 

	# MP3STORE
	if ($MP3store eq "1") {
	  $mp3store1 = "selected=selected";
	  $template->param("MP3STORE1", $mp3store1);
	} elsif ($MP3store eq "2") {
	  $mp3store2 = "selected=selected";
	  $template->param("MP3STORE2", $mp3store2);
	} elsif ($MP3store eq "3") {
	  $mp3store3 = "selected=selected";
	  $template->param("MP3STORE3", $mp3store3);
	} elsif ($MP3store eq "4") {
	  $mp3store4 = "selected=selected";
	  $template->param("MP3STORE4", $mp3store4);
	} elsif ($MP3store eq "5") {
	  $mp3store5 = "selected=selected";
	  $template->param("MP3STORE5", $mp3store5);
	} elsif ($MP3store eq "6") {
	  $mp3store6 = "selected=selected";
	  $template->param("MP3STORE6", $mp3store6);
	} elsif ($MP3store eq "7") {
	  $mp3store7 = "selected=selected";
	  $template->param("MP3STORE7", $mp3store7);
	} else {
	  $mp3store5 = "selected=selected";
	  $template->param("MP3STORE5", $mp3store5);
	}

	# VOLUMEUP OR DONW
	if ($volume eq "3") {
	  $volume1 = "selected=selected";
	  $template->param("VOLUME1", $volume1);
	} elsif ($volume eq "5") {
	  $volume2 = "selected=selected";
	  $template->param("VOLUME2", $volume2);
	} elsif ($volume eq "7") {
	  $volume3 = "selected=selected";
	  $template->param("VOLUME3", $volume3);
	} elsif ($volume eq "10") {
	  $volume4 = "selected=selected";
	  $template->param("VOLUME4", $volume4);
	} else {
	  $volume2 = "selected=selected";
	  $template->param("VOLUME2", $volume2);
	}
		
	# RAMPTO
	if ($rampto eq "sleep") {
	  $selectedrampto1 = "checked=checked";
	  $template->param("SELECTEDRAMPTO1", $selectedrampto1);
	} elsif ($rampto eq "alarm") {
	  $selectedrampto2 = "checked=checked";
	  $template->param("SELECTEDRAMPTO2", $selectedrampto2);
	} elsif ($rampto eq "auto") {
	  $selectedrampto3 = "checked=checked";
	  $template->param("SELECTEDRAMPTO3", $selectedrampto3);
	} else {
	  $selectedrampto2 = "checked=checked";
	  $template->param("SELECTEDRAMPTO2", $selectedrampto2);
	} 
	
	# REGION
	if ($region eq "baw") {
	  $region1 = "selected=selected";
	  $template->param("REGION1", $region1);
	} elsif ($region eq "bay") {
	  $region2 = "selected=selected";
	  $template->param("REGION2", $region2);
	} elsif ($region eq "bbb") {
	  $region3 = "selected=selected";
	  $template->param("REGION3", $region3);
	} elsif ($region eq "hes") {
	  $region4 = "selected=selected";
	  $template->param("REGION4", $region4);
	} elsif ($region eq "mvp") {
	  $region5 = "selected=selected";
	  $template->param("REGION5", $region5);
	} elsif ($region eq "nib") {
	  $region6 = "selected=selected";
	  $template->param("REGION6", $region6);
	} elsif ($region eq "nrw") {
	  $region7 = "selected=selected";
	  $template->param("REGION7", $region7);
	} elsif ($region eq "rps") {
	  $region8 = "selected=selected";
	  $template->param("REGION8", $region8);
	} elsif ($region eq "sac") {
	  $region9 = "selected=selected";
	  $template->param("REGION9", $region9);
	} elsif ($region eq "saa") {
	  $region10 = "selected=selected";
	  $template->param("REGION10", $region10);
	} elsif ($region eq "shh") {
	  $region11 = "selected=selected";
	  $template->param("REGION11", $region11);
	} elsif ($region eq "thu") {
	  $region12 = "selected=selected";
	  $template->param("REGION12", $region12);
	} else {
	  $region1 = "selected=selected";
	  $template->param("REGION1", $region1);
	}
	
	# VARIOUS
	if ($announceradio eq "0") {
	  $selectedannounceradio1 = "selected=selected";
	  $template->param("SELECTEDANNOUNCERADIO1", $selectedannounceradio1);
	} elsif ($announceradio eq "1") {
	  $selectedannounceradio2 = "selected=selected";
	  $template->param("SELECTEDANNOUNCERADIO2", $selectedannounceradio2);
	} else {
	  $selectedannounceradio1 = "selected=selected";
	  $template->param("SELECTEDANNOUNCERADIO1", $selectedannounceradio1);
	} 
	
	# Various default values
	#if (!$udpport) {$udpport = "80"};
	if (!$rmpvol) {$rmpvol = "25"};
	if (!$miniserver) {$miniserver = "MINISERVER1"};
	if (!$maxzap) {$maxzap = "40"};
	
	#** ALT** $template_title = $pphrase->param("TXT0000") . ": " . $pphrase->param("TXT0001");
	
	# Print Template
	# Version GEHT NICHT ???
	my $sversion = LoxBerry::System::pluginversion();
	$template_title = "$SL{'BASIS.MAIN_TITLE'}: v$sversion";
	LoxBerry::Web::head();
	LoxBerry::Web::pagestart($template_title, $helplink, $helptemplate);
	print $template->output();
	LoxBerry::Web::pageend();
	LoxBerry::Web::foot();
	exit;

}

#####################################################
# Save-Sub
#####################################################

sub save 
{

	# Read Config
	my $pcfg    = new Config::Simple($lbpconfigdir . "/" . $pluginconfigfile);
	
	# Everything from Forms
	my $t2s_engine 		= param('t2s_engine');
	my $MP3store 		= param('mp3store');
	my $apikey 			= param('apikey');
	my $seckey 			= param('seckey');
	my $voice 			= param('voice');
	my $file_gong 		= param('file_gong');
	my $LoxDaten 		= param('sendlox');
	my $udpport 		= param('udpport');
	my $debugging 		= param('debugging');
	my $volume 			= param('volume');
	my $rampto		 	= param('rampto');
	my $rmpvol		 	= param('rmpvol');
	my $town		 	= param('town');
	my $region		 	= param('region');
	my $countplayers	= param('countplayers');
	my $countradios 	= param('countradios');
	my $miniserver		= param('miniserver');
	my $googlekey		= param('googlekey');
	my $googletown		= param('googletown');
	my $googlestreet	= param('googlestreet');
	my $announceradio	= param('announceradio');
	my $maxzap			= param('maxzap');
	my $wastecal		= param('wastecal');
	my $cal				= param('cal');
	my $code		    = param('lang');
	
	# turn on/off function "fetch_sonos"
	my $ms = LWP::UserAgent->new;
	if ($LoxDaten eq "true") {
		$req = $ms->get("http://$MSUser:$MSPass\@$MiniServer:$MSWebPort/dev/sps/io/fetch_sonos/Ein");
	} else {
		$req = $ms->get("http://$MSUser:$MSPass\@$MiniServer:$MSWebPort/dev/sps/io/fetch_sonos/Aus");
	}
	
	# OK - now installing...

	# Write configuration file(s)
	$pcfg->param("LOXONE.Loxone", "MINISERVER$miniserver");
	$pcfg->param("LOXONE.LoxDaten", "$LoxDaten");
	$pcfg->param("LOXONE.LoxPort", "$udpport");
	$pcfg->param("SYSTEM.debuggen", "$debugging");
	$pcfg->param("TTS.t2s_engine", "$t2s_engine");
	$pcfg->param("TTS.rampto", "$rampto");
	$pcfg->param("TTS.volrampto", "$rmpvol");
	$pcfg->param("TTS.messageLang", "$code");
	$pcfg->param("TTS.API-key", "$apikey");
	$pcfg->param("TTS.secret-key", "$seckey");
	$pcfg->param("TTS.voice", "$voice");
	$pcfg->param("MP3.file_gong", "$file_gong");
	$pcfg->param("MP3.volumedown", "$volume");
	$pcfg->param("MP3.volumeup", "$volume");
	$pcfg->param("MP3.MP3store", "$MP3store");
	$pcfg->param("LOCATION.town", "\"$town\"");
	$pcfg->param("LOCATION.region", "$region");
	$pcfg->param("LOCATION.googlekey", "$googlekey");
	$pcfg->param("LOCATION.googletown", "$googletown");
	$pcfg->param("LOCATION.googlestreet", "$googlestreet");
	$pcfg->param("VARIOUS.announceradio", "$announceradio");
	$pcfg->param("VARIOUS.maxzap", "$maxzap");
	$pcfg->param("VARIOUS.CALDavMuell", "\"$wastecal\"");
	$pcfg->param("VARIOUS.CALDav2", "\"$cal\"");
	
	# save all radiostations
	for ($i = 1; $i <= $countradios; $i++) {
		if ( param("chkradios$i") ) { # if radio should be deleted
			$pcfg->delete( "RADIO.radio" . "[$i]" );
		} else { # save
			$pcfg->param( "RADIO.radio" . "[$i]", param("radioname$i") . "," . param("radiourl$i") );
		}
	}

	$pcfg->save() or &error;;

	# save all Sonos devices
	my $playercfg = new Config::Simple($lbpconfigdir . "/" . $pluginplayerfile);

	for ($i = 1; $i <= $countplayers; $i++) {
		if ( param("chkplayers$i") ) { # if player should be deleted
			$playercfg->delete( "SONOSZONEN." . param("zone$i") . "[]" );
		} else { # save
			$playercfg->param( "SONOSZONEN." . param("zone$i") . "[]", param("ip$i") . "," . param("rincon$i") . "," . param("model$i") . "," . param("t2svol$i") . "," . param("sonosvol$i") . "," . param("maxvol$i") );
		}
	}

	$playercfg->save() or &error; 

	#$template_title = $pphrase->param("TXT0000") . " - " . $pphrase->param("TXT0001");
	#$message = $pphrase->param("TXT0005");
	#$nexturl = "./index.cgi?do=form";
	
	$template_title = $SUC{'SAVE.MY_NAME'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$successtemplate->param('SAVE_ALL_OK'		, $SUC{'SAVE.SAVE_ALL_OK'});
	$successtemplate->param('SAVE_MESSAGE'		, $SUC{'SAVE.SAVE_MESSAGE'});
	$successtemplate->param('SAVE_BUTTON_OK' 	, $SUC{'SAVE.SAVE_BUTTON_OK'});
	$successtemplate->param('SAVE_NEXTURL'		, $ENV{REQUEST_URI});
	print $successtemplate->output();
	LoxBerry::Web::lbfooter();
	exit;
		
}



#####################################################
# Scan Sonos Player - Sub
#####################################################

sub scan 
{

	# executes PHP network.php script (reads player.cfg and add new zones if been added)
	my $response = qx(/usr/bin/php $lbphtmldir/system/$scanzonesfile);
	
	
	#import Sonos Player from JSON file
	open (my $fh, '<:raw', $lbpconfigdir . '/' . $plugintempplayerfile) or die("Die Datei: $plugintempplayerfile konnte nicht geöffnet werden! $!\n");
	my $file;
	local $/ = undef;
	$file = <$fh>;
	close($fh);
	if ( $file ne "[]" ) {
	my $config = decode_json($file);
	
	# creates table of Sonos devices
	foreach my $key (keys %{$config})
	{
		$countplayers++;
		$rowssonosplayer .= "<tr><td style='height: 25px; width: 43px;' class='auto-style1'><INPUT type='checkbox' style='width: 20px' name='chkplayers$countplayers' id='chkplayers$countplayers' align='center'/></td>\n";
		$rowssonosplayer .= "<td style='height: 25px; width: 196px;'><input type='text' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$key' style='width: 133px' /> </td>\n";
		$rowssonosplayer .= "<td style='height: 28px; width: 147px;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='$config->{$key}->[2]' style='width: 153px' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='t2svol$countplayers' size='100' data-validation='number' data-validation-allowing='range[1;100]' data-validation-error-msg='T2S Vol: Please enter Volume between 1 to 100.' name='t2svol$countplayers' value='$config->{$key}->[3]' style='width: 52px' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='sonosvol$countplayers' size='100' data-validation='number' data-validation-allowing='range[1;100]' data-validation-error-msg='Sonos Vol: Please enter Volume between 1 to 100.' name='sonosvol$countplayers' value='$config->{$key}->[4]' style='width: 52px' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='maxvol$countplayers' size='100' data-validation='number' data-validation-allowing='range[1;100]' data-validation-error-msg='Max Vol: Please enter Volume between 1 to 100.' name='maxvol$countplayers' value='$config->{$key}->[5]' style='width: 52px' /> </td> </tr>\n";
		$rowssonosplayer .= "<input type='hidden' id='ip$countplayers' name='ip$countplayers' value='$config->{$key}->[0]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='rincon$countplayers' name='rincon$countplayers' value='$config->{$key}->[1]'>\n";
	}
	$template->param("ROWSSONOSPLAYER", $rowssonosplayer);
	
	#$template_title = "$SL{'BASIS.MAIN_TITLE'}: v$sversion";
	#LoxBerry::Web::head();
	#LoxBerry::Web::pagestart($template_title, $helplink, $helptemplate);
	#print $template->output();
	#LoxBerry::Web::pageend();
	#LoxBerry::Web::foot();
	}
	return();
	

}
	
#####################################################
# Error-Sub
#####################################################

sub error 
{
	$template_title = $ERR{'ERRORS.MY_NAME'} . " - " . $ERR{'ERRORS.ERR_TITLE'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$errortemplate->param('ERR_MESSAGE'		, $error_message);
	$errortemplate->param('ERR_TITLE'		, $ERR{'ERRORS.ERR_TITLE'});
	$errortemplate->param('ERR_BUTTON_BACK' , $ERR{'ERRORS.ERR_BUTTON_BACK'});
	$successtemplate->param('ERR_NEXTURL'	, $ENV{REQUEST_URI});
	print $errortemplate->output();
	LoxBerry::Web::lbfooter();
	
	
}

# Nun wird das Template ausgegeben.
#print $template->output();

#LoxBerry::Web::lbheader("Sonos4Lox v$version", "http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone", "help.html");
#LoxBerry::Web::lbfooter();
	
