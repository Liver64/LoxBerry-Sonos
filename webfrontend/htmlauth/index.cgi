#!/usr/bin/perl

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

use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use CGI;
use LWP::Simple;
use LWP::UserAgent;
use File::HomeDir;
use Cwd 'abs_path';
use JSON qw( decode_json );
use utf8;
use warnings;
use strict;
#use Data::Dumper;
#no strict "refs"; # we need it for template system

##########################################################################
# Variables
##########################################################################

my $namef;
my $value;
my %query;
my $template_title;
my $error;
my $saveformdata = 0;
my $do = "form";
my $msselectlist;
my $helpurl;
my $miniserver;
my $maxzap;
my $helptemplate;
my $lblang;
my $i;
our $countplayers;
our $rowssonosplayer;
our $miniserver;

my $helptemplatefilename		= "help.html";
my $languagefile 				= "sonos.ini";
my $maintemplatefilename	 	= "sonos.html";
my $successtemplatefilename 	= "success.html";
my $errortemplatefilename 		= "error.html";
my $noticetemplatefilename 		= "notice.html";
my $no_error_template_message	= "<b>Sonos4lox:</b> The error template is not readable. We must abort here. Please try to reinstall the plugin.";
my $pluginconfigfile 			= "sonos.cfg";
my $pluginplayerfile 			= "player.cfg";
my $pluginlogfile				= "sonos.log";
my $urlfile						= "https://raw.githubusercontent.com/Liver64/LoxBerry-Sonos/master/webfrontend/html/release/info.txt";
my $log 						= LoxBerry::Log->new ( name => 'Sonos', filename => $lbplogdir ."/". $pluginlogfile, append => 1 );
my $plugintempplayerfile	 	= "tmp_player.json";
my $scanzonesfile	 			= "network.php";
my $helplink 					= "http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone";
my $pcfg 						= new Config::Simple($lbpconfigdir . "/" . $pluginconfigfile);
my %Config 						= $pcfg->vars() if ( $pcfg );
our $error_message				= "";


##########################################################################
# Read Settings
##########################################################################

my $cfg         = new Config::Simple("$lbsconfigdir/general.cfg");
my $miniservers	= $cfg->param("BASE.MINISERVERS");
my $MiniServer	= $cfg->param("MINISERVER1.IPADDRESS");
my $MSWebPort	= $cfg->param("MINISERVER1.PORT");
my $MSUser		= $cfg->param("MINISERVER1.ADMIN");
my $MSPass		= $cfg->param("MINISERVER1.PASS");

# read language
my $lblang = lblanguage();
#my %SL = LoxBerry::System::readlanguage($template, $languagefile);


#---** GEHT NICHT **--
#$template->param("LOGFILE" , $lbplogdir . "/" . $pluginlogfile );

# Read Plugin Version
my $sversion = LoxBerry::System::pluginversion();

# Read LoxBerry Version
my $lbversion = LoxBerry::System::lbversion();

# read all POST-Parameter in namespace "R".
my $cgi = CGI->new;
$cgi->import_names('R');

# check if logfile is empty
if (-z $lbplogdir."/".$pluginlogfile) {
	system("/usr/bin/date > $pluginlogfile");
	$log->open;
	LOGSTART "Logfile started.\n Loxberry: v".$lbversion." \n Sonos Plugin: v".$sversion." \n --------------------------------------------------------------------------------";
}

##########################################################################

# deletes the log file sonos.log
if ( $R::delete_log )
{
	LOGDEB "Logfile will be deleted. ".$R::delete_log;
	LOGWARN "Delete Logfile: ".$pluginlogfile;
	my $pluginlogfile = $log->close;
	system("/usr/bin/date > $pluginlogfile");
	$log->open;
	LOGSTART "Logfile started.\n Loxberry: v".$lbversion." \n Sonos Plugin: v".$sversion." \n --------------------------------------------------------------------------------";
	print "Content-Type: text/plain\n\nOK";
	exit;
}


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



#**************************************************************************

# Check, if filename for the errortemplate is readable
stat($lbptemplatedir . "/" . $errortemplatefilename);
if ( !-r _ )
{
	$error_message = $no_error_template_message;
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	print $error_message;
	LOGCRIT $error_message;
	LoxBerry::Web::lbfooter();
	LOGCRIT "Leave Sonos Plugin due to an critical error";
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
	LOGERR "The ".$successtemplatefilename." file could not be loaded. Abort plugin loading";
	LOGERR $error_message;
	&error;
}
#LOGDEB "Filename for the successtemplate is ok, preparing template";
my $successtemplate = 	HTML::Template->new(
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
# Logging
#**************************************************************************

if ($pcfg)
{
	$log->loglevel(int($Config{'SYSTEM.LOGLEVEL'}));
	$LoxBerry::System::DEBUG 	= 1 if int($Config{'SYSTEM.LOGLEVEL'}) eq 7;
	$LoxBerry::Web::DEBUG 		= 1 if int($Config{'SYSTEM.LOGLEVEL'}) eq 7;
}
else
{
	$log->loglevel(7);
	$LoxBerry::System::DEBUG 	= 1;
	$LoxBerry::Web::DEBUG 		= 1;
	$error_message				= $ERR{'ERRORS.ERR_NO_SONOS_CONFIG_FILE'};
	&error;
	exit;
}

# Check, if filename for the maintemplate is readable, if not raise an error
my $error_message = $ERR{'ERRORS.ERR_MAIN_TEMPLATE_NOT_READABLE'};
stat($lbptemplatedir . "/" . $maintemplatefilename);
&error if !-r _;

my $template =  HTML::Template->new(
				filename => $lbptemplatedir . "/" . $maintemplatefilename,
				global_vars => 1,
				loop_context_vars => 1,
				die_on_bad_params=> 0,
				associate => $pcfg,
				%htmltemplate_options,
				debug => 1
				);
my %SL = LoxBerry::System::readlanguage($template, $languagefile);			


##########################################################################
# Language Settings
##########################################################################

$template->param("LBHOSTNAME", lbhostname());
$template->param("LBLANG", $lblang);
$template->param("SELFURL", $ENV{REQUEST_URI});

LOGDEB "Read main settings from " . $languagefile . " for language " . $lblang;

#**************************************************************************

# übergibt Plugin Verzeichnis an HTML
$template->param("PLUGINDIR" => $lbpplugindir);

# Check if plugin config file is readable
if (!-r $lbpconfigdir . "/" . $pluginconfigfile) 
{
	$error_message = $ERR{'ERRORS.ERR_NO_SONOS_CONFIG_FILE'};
	LOGERR "The Sonos config file could not be loaded";
	&error;
	exit;	
}

LOGDEB "The Sonos config file has been loaded";

#**************************************************************************

# Check if plugin player details file is readable
if (!-r $lbpconfigdir . "/" . $pluginplayerfile) 
{
	$error_message = $ERR{'ERRORS.ERR_NO_SONOS_PLAYER_FILE'};
	LOGERR "The Sonos player file could not be loaded";
	&error;
	exit;	
}

LOGDEB "The Sonos player file has been loaded";

##########################################################################
# Main program
##########################################################################

if ($R::saveformdata) {
  &save;

} else {
  &form;

}

exit;


#####################################################
# Form-Sub
#####################################################

sub form {

	# read info file from Github and save in $info
	my $info = get($urlfile);
	$template		->param("INFO" 			=> "$info");
			
	# fill saved values into form
	$template		->param("SELFURL", $ENV{REQUEST_URI});
	$template		->param("T2S_ENGINE" 	=> $pcfg->param("TTS.t2s_engine"));
	$template		->param("VOICE" 		=> $pcfg->param("TTS.voice"));
	$template		->param("CODE" 			=> $pcfg->param("TTS.messageLang"));
		
	# Load saved values for "select"
	my $t2s_engine		  = $pcfg->param("TTS.t2s_engine");
	my $rmpvol	 	  	  = $pcfg->param("TTS.volrampto");
			
	# Radiosender auslesen
	our $countradios = 0;
	our $rowsradios;
	
	my %Config = $pcfg->vars();	
	foreach my $key (keys %Config) {
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
	LOGDEB "$countradios Radio Stations has been loaded.";
	$rowsradios .= "<input type='hidden' id='countradios' name='countradios' value='$countradios'>\n";
	$template->param("ROWSRADIO", $rowsradios);
	
	# *******************************************************************************************************************

	# Als erstes vorhandene Player aus player.cfg einlesen
	our $countplayers = 0;
	our $rowssonosplayer;
	
	my $error_volume = $ERR{'T2S.ERROR_VOLUME_PLAYER'};
	my $playercfg = new Config::Simple($lbpconfigdir . "/" . $pluginplayerfile);
	my %config = $playercfg->vars();	
	
	foreach my $key (keys %config) {
		$countplayers++;
		my $room = $key;
		$room =~ s/^SONOSZONEN\.//g;
		$room =~ s/\[\]$//g;
		my @fields = $playercfg->param($key);
		$rowssonosplayer .= "<tr><td style='height: 25px; width: 43px;' class='auto-style1'><INPUT type='checkbox' style='width: 20px' name='chkplayers$countplayers' id='chkplayers$countplayers' align='center'/></td>\n";
		$rowssonosplayer .= "<td style='height: 25px; width: 176px;'><input type='text' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$room' style='width: 196px; background-color: #e6e6e6;' /> </td>\n";
		$rowssonosplayer .= "<td style='height: 28px; width: 147px;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='@fields[2]' style='width: 153px; background-color: #e6e6e6;' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='t2svol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='t2svol$countplayers' value='@fields[3]' style='width: 52px' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='sonosvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='sonosvol$countplayers' value='@fields[4]' style='width: 52px' /> </td>\n";
		$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='maxvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='maxvol$countplayers' value='@fields[5]' style='width: 52px' /> </td> </tr>\n";
		$rowssonosplayer .= "<input type='hidden' id='ip$countplayers' name='ip$countplayers' value='@fields[0]'>\n";
		$rowssonosplayer .= "<input type='hidden' id='rincon$countplayers' name='rincon$countplayers' value='@fields[1]'>\n";
	}
	LOGDEB "$countplayers Sonos players has been loaded.";
	
	#####################################################
	# Subroutines
	#####################################################	
	
	# Call Subroutine to scan/import Sonos Zones
	if ( $do eq "scan" ) {
		&attention_scan;
	}
	
	# Call Subroutine to scan/import Sonos Zones
	if ( $do eq "scanning" ) {
		&scan;
	}
	#####################################################	

	if ( $countplayers < 1 ) {
		$rowssonosplayer .= "<tr><td colspan=6>" . $SL{'ZONES.SONOS_EMPTY_ZONES'} . "</td></tr>\n";
	}
	$rowssonosplayer .= "<input type='hidden' id='countplayers' name='countplayers' value='$countplayers'>\n";
	$template->param("ROWSSONOSPLAYER", $rowssonosplayer);
	
	# read Miniservers
	my $cfgd  = new Config::Simple($lbsconfigdir ."/general.cfg");
	for (my $i = 1; $i <= $cfgd->param('BASE.MINISERVERS');$i++) {
	    if ("MINISERVER$i" eq $miniserver) {
		    $msselectlist .= '<option selected value="'.$i.'">'.$cfgd->param("MINISERVER$i.NAME")."</option>\n";
		} else {
		    $msselectlist .= '<option value="'.$i.'">'.$cfgd->param("MINISERVER$i.NAME")."</option>\n";
		}
	}
	$template->param("MSSELECTLIST", $msselectlist);
	
	
	# Prepare form defaults
	if (!$rmpvol) {$rmpvol = "25"};
	if (!$miniserver) {$miniserver = "MINISERVER1"};
	if (!$maxzap) {$maxzap = "40"};
	
	LOGOK "Sonos Plugin has been successfully loaded.";
	
	# Print Template
	my $sversion = LoxBerry::System::pluginversion();
	$template_title = "$SL{'BASIS.MAIN_TITLE'}: v$sversion";
	LoxBerry::Web::head();
	LoxBerry::Web::pagestart($template_title, $helplink, $helptemplate);
	print $template->output();
	undef $template;	
	LoxBerry::Web::lbfooter();
	
	# Test Print to UI
	
	#my $template_title = $region;
	#LoxBerry::Web::lbheader($template_title);
	#print $content;
	#LoxBerry::Web::lbfooter();
	#exit;

}

#####################################################
# Save-Sub
#####################################################

sub save 
{
	# Everything from Forms
	my $countplayers	= param('countplayers');
	my $countradios 	= param('countradios');
		
	# turn on/off function "fetch_sonos"
	my $ms = LWP::UserAgent->new;
	if (my $LoxDaten eq "true") {
		my $req = $ms->get("http://$MSUser:$MSPass\@$MiniServer:$MSWebPort/dev/sps/io/fetch_sonos/Ein");
		LOGDEB "Coummunication to Miniserver is switched on";
	} else {
		my $req = $ms->get("http://$MSUser:$MSPass\@$MiniServer:$MSWebPort/dev/sps/io/fetch_sonos/Aus");
		LOGDEB "Coummunication to Miniserver is switched off.";
	}
	
	# OK - now installing...

	# Write configuration file(s)
	$pcfg->param("LOXONE.Loxone", "MINISERVER$R::miniserver");
	$pcfg->param("LOXONE.LoxDaten", "$R::sendlox");
	$pcfg->param("LOXONE.LoxPort", "$R::udpport");
	$pcfg->param("TTS.t2s_engine", "$R::t2s_engine");
	$pcfg->param("TTS.rampto", "$R::rampto");
	$pcfg->param("TTS.volrampto", "$R::rmpvol");
	$pcfg->param("TTS.messageLang", "$R::t2slang");
	$pcfg->param("TTS.API-key", "$R::apikey");
	$pcfg->param("TTS.secret-key", "$R::seckey");
	$pcfg->param("TTS.voice", "$R::voice");
	$pcfg->param("MP3.file_gong", "$R::file_gong");
	$pcfg->param("MP3.volumedown", "$R::volume");
	$pcfg->param("MP3.volumeup", "$R::volume");
	$pcfg->param("MP3.MP3store", "$R::mp3store");
	$pcfg->param("LOCATION.town", "\"$R::town\"");
	$pcfg->param("LOCATION.region", "$R::region");
	$pcfg->param("LOCATION.googlekey", "$R::googlekey");
	$pcfg->param("LOCATION.googletown", "$R::googletown");
	$pcfg->param("LOCATION.googlestreet", "$R::googlestreet");
	$pcfg->param("VARIOUS.announceradio", "$R::announceradio");
	$pcfg->param("VARIOUS.maxzap", "$R::maxzap");
	$pcfg->param("VARIOUS.CALDavMuell", "\"$R::wastecal\"");
	$pcfg->param("VARIOUS.CALDav2", "\"$R::cal\"");
	$pcfg->param("SYSTEM.LOGLEVEL", "$R::LOGLEVEL");
		
	# save all radiostations
	for ($i = 1; $i <= $countradios; $i++) {
		if ( param("chkradios$i") ) { # if radio should be deleted
			$pcfg->delete( "RADIO.radio" . "[$i]" );
		} else { # save
			$pcfg->param( "RADIO.radio" . "[$i]", param("radioname$i") . "," . param("radiourl$i") );
		}
	}
	LOGDEB "Radio Stations has been saved.";

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
	LOGDEB "Sonos Zones has been saved.";
	LOGOK "All settings has been saved successful";

	$playercfg->save() or &error; 
	my $lblang = lblanguage();
	$template_title = "$SL{'BASIS.MAIN_TITLE'}: v$sversion";
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
# Attention Scan Sonos Player - Sub
#####################################################

sub attention_scan
{
	# Filename for the notice template is ok, preparing template";
	my $noticetemplate = HTML::Template->new(
					filename => $lbptemplatedir . "/" . $noticetemplatefilename,
					global_vars => 1,
					loop_context_vars => 1,
					die_on_bad_params=> 0,
					associate => $cgi,
					%htmltemplate_options,
					debug => 1,
					);
	my %NOT = LoxBerry::System::readlanguage($noticetemplate, $languagefile);
	
	$template_title = "$SL{'BASIS.MAIN_TITLE'}: v$sversion";
	LoxBerry::Web::lbheader($template_title, $helpurl, $noticetemplatefilename);
	$noticetemplate->param('SONOS_SCAN_HEADER'		, $SUC{'ZONES.SONOS_SCAN_HEADER'});
	$noticetemplate->param('SONOS_SCAN_TEXT'		, $SUC{'ZONES.SONOS_SCAN_TEXT'});
	$noticetemplate->param('BUTTON_NEXT' 			, $SUC{'ZONES.BUTTON_NEXT'});
	$noticetemplate->param('BUTTON_BACK' 			, $SUC{'ZONES.BUTTON_BACK'});
	print $noticetemplate->output();
	LoxBerry::Web::lbfooter();
	exit;
}




#####################################################
# Scan Sonos Player - Sub
#####################################################

sub scan
{
	my $error_volume = $SL{'T2S.ERROR_VOLUME_PLAYER'};
	
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
			$rowssonosplayer .= "<td style='height: 25px; width: 176px;'><input type='text' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$key' style='width: 196px; background-color: #e6e6e6;' /> </td>\n";
			$rowssonosplayer .= "<td style='height: 28px; width: 147px;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='$config->{$key}->[2]' style='width: 153px; background-color: #e6e6e6;' /> </td>\n";
			$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='t2svol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='t2svol$countplayers' value='$config->{$key}->[3]' style='width: 52px' /> </td>\n";
			$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='sonosvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='sonosvol$countplayers' value='$config->{$key}->[4]' style='width: 52px' /> </td>\n";
			$rowssonosplayer .= "<td style='width: 98px; height: 28px;'><input type='text' id='maxvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='maxvol$countplayers' value='$config->{$key}->[5]' style='width: 52px' /> </td>\n";
			$rowssonosplayer .= "<input type='hidden' id='ip$countplayers' name='ip$countplayers' value='$config->{$key}->[0]'>\n";
			$rowssonosplayer .= "<input type='hidden' id='rincon$countplayers' name='rincon$countplayers' value='$config->{$key}->[1]'>\n";
		}
	LOGDEB "Scan for Sonos Zones has been executed.";
	$template->param("ROWSSONOSPLAYER", $rowssonosplayer);
	}
	return();
}
	
#####################################################
# Error-Sub
#####################################################

sub error 
{
	$template_title = $ERR{'ERRORS.MY_NAME'} . ": v$sversion - " . $ERR{'ERRORS.ERR_TITLE'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$errortemplate->param('ERR_MESSAGE'		, $error_message);
	$errortemplate->param('ERR_TITLE'		, $ERR{'ERRORS.ERR_TITLE'});
	$errortemplate->param('ERR_BUTTON_BACK' , $ERR{'ERRORS.ERR_BUTTON_BACK'});
	$successtemplate->param('ERR_NEXTURL'	, $ENV{REQUEST_URI});
	print $errortemplate->output();
	LoxBerry::Web::lbfooter();
	
	
}


