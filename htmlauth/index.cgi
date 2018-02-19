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
use Data::Dumper;
#use warnings;
#use strict;
#no strict "refs"; # we need it for template system

##########################################################################
# Variables
##########################################################################

my $pcfg;
my $namef;
my $value;
my %query;
my $template_title;
my $error;
my $saveformdata = 0;
my $output;
my $message;
my $nexturl;
my $do = "form";
my $step;
my $miniservers;
my $volume;
my $msselectlist;
my $wastecal;
my $cal;
my $code;
my @radioarray;
my $i;

my $helptemplatefilename		= "help.html";
my $languagefile 				= "sonos.ini";
my $maintemplatefilename	 	= "sonos.html";
my $successtemplatefilename 	= "success.html";
my $errortemplatefilename 		= "error.html";
my $no_error_template_message	= "<b>Sonos4lox:</b> The error template is not readable. We must abort here. Please try to reinstall the plugin.";
my $pluginconfigfile 			= "sonos.cfg";
my $pluginplayerfile 			= "player.cfg";
my $pluginlogfile				= "sonos.log";
my $log 						= LoxBerry::Log->new ( name => 'Sonos', filename => $lbplogdir ."/". $pluginlogfile, append => 1 );
my $plugintempplayerfile	 	= "tmp_player.json";
my $scanzonesfile	 			= "network.php";
my $helplink 					= "http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone";
my $pcfg 						= new Config::Simple($lbpconfigdir . "/" . $pluginconfigfile);


##########################################################################
# Read Settings
##########################################################################

my $cfg         = new Config::Simple("$lbsconfigdir/general.cfg");
my $miniservers	= $cfg->param("BASE.MINISERVERS");
my $MiniServer	= $cfg->param("MINISERVER1.IPADDRESS");
my $MSWebPort	= $cfg->param("MINISERVER1.PORT");
my $MSUser		= $cfg->param("MINISERVER1.ADMIN");
my $MSPass		= $cfg->param("MINISERVER1.PASS");

# Create a logging object
$LoxBerry::System::DEBUG		= 1;
$LoxBerry::Web::DEBUG 			= 1;

my $template = HTML::Template->new(
			filename => $lbptemplatedir . "/" . $maintemplatefilename,
			global_vars => 1,
			loop_context_vars => 1,
			die_on_bad_params=> 0,
			associate => $pcfg,
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
	$template		->param("T2S_ENGINE" 	=> $pcfg->param("TTS.t2s_engine"));
	$template		->param("VOICE" 		=> $pcfg->param("TTS.voice"));
	$template		->param("CODE" 			=> $pcfg->param("TTS.messageLang"));
	
	# Load saved values for "select"
	
	my $t2s_engine		  = $pcfg->param("TTS.t2s_engine");
	my $rmpvol	 	  	  = $pcfg->param("TTS.volrampto");
			
	# Radiosender auslesen
	our $countradios = 0;
	our $rowsradios;
	
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
	if (!$rmpvol) {$rmpvol = "25"};
	if (!$miniserver) {$miniserver = "MINISERVER1"};
	if (!$maxzap) {$maxzap = "40"};
	
	LOGOK "Everything is OK - Test";
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
	#print $template_title;
	#LoxBerry::Web::lbfooter();
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
	$pcfg{'MP3.file_gong'};
	
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
	#print Dumper(%so_config);
	# executes PHP network.php script (reads player.cfg and add new zones if been added)
	my $response = qx(/usr/bin/php $lbphtmldir/system/$scanzonesfile);
	
	#import Sonos Player from JSON file
	open (my $fh, '<:raw', $lbpconfigdir . '/' . $plugintempplayerfile) or die("Die Datei: $plugintempplayerfile konnte nicht geöffnet werden! $!\n");
	our $file; { local $/; $file = <$fh>; }
	our $config = decode_json($file);
	
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
	
