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

use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use LWP::UserAgent;
use Config::Simple;
use File::HomeDir;
use Cwd 'abs_path';
use JSON qw( decode_json );
use utf8;
#use warnings;
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
our $installfolder;
our $planguagefile;
our $version;
our $error;
our $saveformdata = 0;
our $output;
our $message;
our $nexturl;
our $do = "form";
my  $home = File::HomeDir->my_home;
our $psubfolder;
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
our $selectedinstanz1;
our $selectedinstanz2;
our $selectedinstanz3;
our $selectedinstanz4;
our $selectedinstanz5;
our $selectedinstanz6;
our $selectedinstanz7;
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
our $pluginlogfile;
our @radioarray;
our $i;

##########################################################################
# Read Settings
##########################################################################

# Version of this script
<<<<<<< HEAD:webfrontend/cgi/index.cgi
$version = "2.1.7_1";
=======
$version = "2.1.7.2";
>>>>>>> origin/master:webfrontend/htmlauth/index.cgi

# Figure out in which subfolder we are installed
$psubfolder = abs_path($0);
$psubfolder =~ s/(.*)\/(.*)\/(.*)$/$2/g;

$cfg             = new Config::Simple("$home/config/system/general.cfg");
$installfolder   = $cfg->param("BASE.INSTALLFOLDER");
$lang            = $cfg->param("BASE.LANG");
$miniservers     = $cfg->param("BASE.MINISERVERS");
my $MiniServer	 = $cfg->param("MINISERVER1.IPADDRESS");
my $MSWebPort	 = $cfg->param("MINISERVER1.PORT");
my $MSUser		 = $cfg->param("MINISERVER1.ADMIN");
my $MSPass		 = $cfg->param("MINISERVER1.PASS");

#########################################################################
# Parameter
#########################################################################

# Everything from URL
foreach (split(/&/,$ENV{'QUERY_STRING'}))
{
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


# Clean up saveformdata variable
$saveformdata =~ tr/0-1//cd;
$saveformdata = substr($saveformdata,0,1);

# Init Language
# Clean up lang variable
$lang =~ tr/a-z//cd;
$lang = substr($lang,0,2);

# If there's no language phrases file for choosed language, use german as default
if (!-e "$installfolder/templates/plugins/$psubfolder/$lang/language.dat") {
	$lang = "de";
}

# Read translations / phrases
$planguagefile	= "$installfolder/templates/plugins/$psubfolder/$lang/language.dat";
$pphrase = new Config::Simple($planguagefile);

# Set variables
$pluginlogfile    = $installfolder."/log/plugins/".$psubfolder."/sonos_error.log";

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
# 
# Subroutines
#
#####################################################

#####################################################
# Form-Sub
#####################################################

sub form {

	$pcfg			  = new Config::Simple("$installfolder/config/plugins/$psubfolder/sonos.cfg");
	$debugging		  = $pcfg->param("SYSTEM.debuggen");
	$LoxDaten		  = $pcfg->param("LOXONE.LoxDaten");
	$udpport	  	  = $pcfg->param("LOXONE.LoxPort");
	$miniserver	  	  = $pcfg->param("LOXONE.Loxone");
	$apikey 		  = $pcfg->param("TTS.API-key");
	$seckey 		  = $pcfg->param("TTS.secret-key");
	$t2s_engine		  = $pcfg->param("TTS.t2s_engine");
	$voice	 		  = $pcfg->param("TTS.voice");
	$rampto	 		  = $pcfg->param("TTS.rampto");
	$rmpvol	 	  	  = $pcfg->param("TTS.volrampto");
	#$lang			  = $pcfg->param("TTS.messageLang");
	$MP3store 		  = $pcfg->param("MP3.MP3store");
	$volume		  	  = $pcfg->param("MP3.volumedown");
	$file_gong		  = $pcfg->param("MP3.file_gong");
	$town		  	  = $pcfg->param("LOCATION.town");
	$region		  	  = $pcfg->param("LOCATION.region");
	$googlekey		  = $pcfg->param("LOCATION.googlekey");
	$googletown		  = $pcfg->param("LOCATION.googletown");
	$googlestreet	  = $pcfg->param("LOCATION.googlestreet");
	$announceradio	  = $pcfg->param("VARIOUS.announceradio");
	$maxzap			  = $pcfg->param("VARIOUS.maxzap");
	$wastecal		  = $pcfg->param("VARIOUS.CALDavMuell");
	$cal			  = $pcfg->param("VARIOUS.CALDav2");
	
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
		$rowsradios .= "<tr><td colspan=3>" . $pphrase->param("TXT0007") . "</td></tr>\n";
	}
	$rowsradios .= "<input type='hidden' id='countradios' name='countradios' value='$countradios'>\n";

	# Filter
	#$apikey   = quotemeta($apikey);

	# Als erstes vorhandene Player aus player.cfg einlesen
	our $countplayers = 0;
	our $rowssonosplayer;

	my $playercfg = new Config::Simple("$installfolder/config/plugins/$psubfolder/player.cfg");
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
		$rowssonosplayer .= "<tr><td colspan=6>" . $pphrase->param("TXT0006") . "</td></tr>\n";
	}
	$rowssonosplayer .= "<input type='hidden' id='countplayers' name='countplayers' value='$countplayers'>\n";
	
	
	# Fill Miniserver selection dropdown
	for (my $i = 1; $i <= $cfg->param('BASE.MINISERVERS');$i++) {
	    if ("MINISERVER$i" eq $miniserver) {
		    $msselectlist .= '<option selected value="'.$i.'">'.$cfg->param("MINISERVER$i.NAME")."</option>\n";
		} else {
		    $msselectlist .= '<option value="'.$i.'">'.$cfg->param("MINISERVER$i.NAME")."</option>\n";
		}
	}
	
	
	# Prepare form defaults

	# T2S_ENGINE
	if ($t2s_engine eq "2001") {
	  $selectedinstanz1 = "checked=checked";
	} elsif ($t2s_engine eq "1001") {
	  $selectedinstanz2 = "checked=checked";
	} elsif ($t2s_engine eq "3001") {
	  $selectedinstanz3 = "checked=checked";
	} elsif ($t2s_engine eq "4001") {
      $selectedinstanz4 = "checked=checked";
	} elsif ($t2s_engine eq "5001") {
      $selectedinstanz5 = "checked=checked";
	} elsif ($t2s_engine eq "6001") {
      $selectedinstanz6 = "checked=checked";
	} elsif ($t2s_engine eq "7001") {
      $selectedinstanz7 = "checked=checked";
    } else {
	  $selectedinstanz1 = "checked=checked";
	} 

	# VOICE
	if ($voice eq "Marlene") {
	  $selectedvoice1 = "selected=selected";
	} elsif ($voice eq "Hans") {
	  $selectedvoice2 = "selected=selected";
	} elsif ($voice eq "Vicki") {
	  $selectedvoice3 = "selected=selected";
	} else {
	  $selectedvoice1 = "selected=selected";
	} 

	# DEBUGGING
	if ($debugging eq "0") {
	  $selecteddebug1 = "selected=selected";
	} elsif ($debugging eq "1") {
	  $selecteddebug2 = "selected=selected";
	} else {
	  $selecteddebug1 = "selected=selected";
	} 
	
	# LOXONE
	if ($LoxDaten eq "false") {
	  $selectedsendlox1 = "selected=selected";
	} elsif ($LoxDaten eq "true") {
	  $selectedsendlox2 = "selected=selected";
	} else {
	  $selectedsendlox1 = "selected=selected";
	} 

	# MP3STORE
	if ($MP3store eq "1") {
	  $mp3store1 = "selected=selected";
	} elsif ($MP3store eq "2") {
	  $mp3store2 = "selected=selected";
	} elsif ($MP3store eq "3") {
	  $mp3store3 = "selected=selected";
	} elsif ($MP3store eq "4") {
	  $mp3store4 = "selected=selected";
	} elsif ($MP3store eq "5") {
	  $mp3store5 = "selected=selected";
	} elsif ($MP3store eq "6") {
	  $mp3store6 = "selected=selected";
	} elsif ($MP3store eq "7") {
	  $mp3store7 = "selected=selected";
	} else {
	  $mp3store5 = "selected=selected";
	}

	# VOLUMEUP OR DONW
	if ($volume eq "3") {
	  $volume1 = "selected=selected";
	} elsif ($volume eq "5") {
	  $volume2 = "selected=selected";
	} elsif ($volume eq "7") {
	  $volume3 = "selected=selected";
	} elsif ($volume eq "10") {
	  $volume4 = "selected=selected";
	} else {
	  $volume2 = "selected=selected";
	}
		
	# RAMPTO
	if ($rampto eq "sleep") {
	  $selectedrampto1 = "checked=checked";
	} elsif ($rampto eq "alarm") {
	  $selectedrampto2 = "checked=checked";
	} elsif ($rampto eq "auto") {
	  $selectedrampto3 = "checked=checked";
	} else {
	  $selectedrampto2 = "checked=checked";
	} 
	
	# REGION
	if ($region eq "baw") {
	  $region1 = "selected=selected";
	} elsif ($region eq "bay") {
	  $region2 = "selected=selected";
	} elsif ($region eq "bbb") {
	  $region3 = "selected=selected";
	} elsif ($region eq "hes") {
	  $region4 = "selected=selected";
	} elsif ($region eq "mvp") {
	  $region5 = "selected=selected";
	} elsif ($region eq "nib") {
	  $region6 = "selected=selected";
	} elsif ($region eq "nrw") {
	  $region7 = "selected=selected";
	} elsif ($region eq "rps") {
	  $region8 = "selected=selected";
	} elsif ($region eq "sac") {
	  $region9 = "selected=selected";
	} elsif ($region eq "saa") {
	  $region10 = "selected=selected";
	} elsif ($region eq "shh") {
	  $region11 = "selected=selected";
	} elsif ($region eq "thu") {
	  $region12 = "selected=selected";
	} else {
	  $region1 = "selected=selected";
	}
	
	# VARIOUS
	if ($announceradio eq "0") {
	  $selectedannounceradio1 = "selected=selected";;
	} elsif ($announceradio eq "1") {
	  $selectedannounceradio2 = "selected=selected";
	} else {
	  $selectedannounceradio1 = "selected=selected";
	} 
	
	# Various default values
	#if (!$udpport) {$udpport = "80"};
	if (!$rmpvol) {$rmpvol = "25"};
	if (!$miniserver) {$miniserver = "MINISERVER1"};
	if (!$maxzap) {$maxzap = "40"};
		
	print "Content-Type: text/html\n\n";
	
	$template_title = $pphrase->param("TXT0000") . ": " . $pphrase->param("TXT0001");
	
	# Print Template
	&lbheader;
	open(F,"$installfolder/templates/plugins/$psubfolder/$lang/settings.html") || die "Missing template plugins/$psubfolder/$lang/settings.html";
	  while (<F>) 
	  {
	    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
	    print $_;
	  }
	close(F);
	&footer;
	exit;

}

#####################################################
# Save-Sub
#####################################################

sub save 
{

	# Read Config
	$pcfg    = new Config::Simple("$installfolder/config/plugins/$psubfolder/sonos.cfg");
	$pname   = $pcfg->param("SYSTEM.Scriptname");

	# Everything from Forms
	$t2s_engine 	= param('t2s_engine');
	$MP3store 		= param('mp3store');
	$apikey 		= param('apikey');
	$seckey 		= param('seckey');
	$voice 			= param('voice');
	$file_gong 		= param('file_gong');
	$LoxDaten 		= param('sendlox');
	$udpport 		= param('udpport');
	$debugging 		= param('debugging');
	$volume 		= param('volume');
	$rampto		 	= param('rampto');
	$rmpvol		 	= param('rmpvol');
	$town		 	= param('town');
	$region		 	= param('region');
	$countplayers	= param('countplayers');
	$countradios 	= param('countradios');
	$miniserver		= param('miniserver');
	$googlekey		= param('googlekey');
	$googletown		= param('googletown');
	$googlestreet	= param('googlestreet');
	$announceradio	= param('announceradio');
	$maxzap			= param('maxzap');
	$wastecal		= param('wastecal');
	$cal			= param('cal');
	
	# Filter
	$MP3Store   	= quotemeta($MP3store);
	$t2s_engine   	= quotemeta($t2s_engine);
	#$apikey   		= quotemeta($apikey);
	#$seckey 		= quotemeta($seckey);
	$voice 			= quotemeta($voice);
	#$file_gong 	= quotemeta($file_gong);
	#$LoxDaten 		= quotemeta($LoxDaten);
	$debugging		= quotemeta($debugging);
	$volume 		= quotemeta($volume);
	$rampto 		= quotemeta($rampto);
	$rmpvol 		= quotemeta($rmpvol);
	$udpport 		= quotemeta($udpport);
	$lang 			= quotemeta($lang);
	#$town 			= quotemeta($town);
	$region 		= quotemeta($region);
	$miniserver		= quotemeta($miniserver);
	$maxzap 		= quotemeta($maxzap);
	#$countplayers	= quotemeta($countplayers);
	#$countradios	= quotemeta($countradios);
	
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
	#$pcfg->param("TTS.messageLang", "$lang");
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

	$pcfg->save();

	# save all Sonos devices
	my $playercfg = new Config::Simple("$installfolder/config/plugins/$psubfolder/player.cfg");

	for ($i = 1; $i <= $countplayers; $i++) {
		if ( param("chkplayers$i") ) { # if player should be deleted
			$playercfg->delete( "SONOSZONEN." . param("zone$i") . "[]" );
		} else { # save
			$playercfg->param( "SONOSZONEN." . param("zone$i") . "[]", param("ip$i") . "," . param("rincon$i") . "," . param("model$i") . "," . param("t2svol$i") . "," . param("sonosvol$i") . "," . param("maxvol$i") );
		}
	}

	$playercfg->save();

	$template_title = $pphrase->param("TXT0000") . " - " . $pphrase->param("TXT0001");
	$message = $pphrase->param("TXT0005");
	$nexturl = "./index.cgi?do=form";

	print "Content-Type: text/html\n\n"; 
	&lbheader;
	open(F,"$installfolder/templates/system/$lang/success.html") || die "Missing template system/$lang/success.html";
	while (<F>) 
	{
		$_ =~ s/<!--\$(.*?)-->/${$1}/g;
		print $_;
	}
	close(F);
	&footer;
	exit;
		
}



#####################################################
# Scan Sonos Player - Sub
#####################################################

sub scan 
{

	# executes PHP network.php script (reads player.cfg and add new zones if been added)
	my $response = qx(/usr/bin/php $installfolder/webfrontend/html/plugins/$psubfolder/system/network.php);
	
	#import Sonos Player from JSON file
	open (my $fh, '<:raw', "$installfolder/config/plugins/$psubfolder/tmp_player.json") or die("Die Datei: $config_path konnte nicht geöffnet werden! $!\n");
			our $file; { local $/; $file = <$fh>; }
			our $config = decode_json($file);
	
	# creates table of Sonos devices
	foreach $key (keys %{$config})
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
	#$result = unlink ("$installfolder/config/plugins/$psubfolder/tmp_player.json");
	return();
	

}
	
#####################################################
# Error-Sub
#####################################################

sub error 
{
	$template_title = $pphrase->param("TXT0000") . " - " . $pphrase->param("TXT0001");
	print "Content-Type: text/html\n\n"; 
	&lbheader;
	open(F,"$installfolder/templates/system/$lang/error.html") || die "Missing template system/$lang/error.html";
	while (<F>) 
	{
		$_ =~ s/<!--\$(.*?)-->/${$1}/g;
		print $_;
	}
	close(F);
	&footer;
	exit;
}

#####################################################
# Page-Header-Sub
#####################################################

	sub lbheader 
	{
		 # Create Help page
	  $helplink = "http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone";
	  open(F,"$installfolder/templates/plugins/$psubfolder/$lang/help.html") || die "Missing template plugins/$psubfolder/$lang/help.html";
	    @help = <F>;
	    foreach (@help)
	    {
	      s/[\n\r]/ /g;
	      $_ =~ s/<!--\$(.*?)-->/${$1}/g;
	      $helptext = $helptext . $_;
	    }
	  close(F);
	  open(F,"$installfolder/templates/system/$lang/header.html") || die "Missing template system/$lang/header.html";
	    while (<F>) 
	    {
	      $_ =~ s/<!--\$(.*?)-->/${$1}/g;
	      print $_;
	    }
	  close(F);
	}

#####################################################
# Footer
#####################################################

	sub footer 
	{
	  open(F,"$installfolder/templates/system/$lang/footer.html") || die "Missing template system/$lang/footer.html";
	    while (<F>) 
	    {
	      $_ =~ s/<!--\$(.*?)-->/${$1}/g;
	      print $_;
	    }
	  close(F);
	}
