#!/usr/bin/perl -w

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
use JSON::XS qw( decode_json );
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
our $selectedinstanz1;
our $selectedinstanz2;
our $selectedinstanz3;
our $rsender;
our $rsenderurl;
our @radioarray;
our $i;

##########################################################################
# Read Settings
##########################################################################

# Version of this script
$version = "1.0.0";

# Figure out in which subfolder we are installed
$psubfolder = abs_path($0);
$psubfolder =~ s/(.*)\/(.*)\/(.*)$/$2/g;

$cfg             = new Config::Simple("$home/config/system/general.cfg");
$installfolder   = $cfg->param("BASE.INSTALLFOLDER");
$lang            = $cfg->param("BASE.LANG");
$miniservers     = $cfg->param("BASE.MINISERVERS");

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
if ( !$query{'lang'} ) {
	if ( param('lang') ) {
		$lang = quotemeta(param('lang'));
	} else {
		$lang = "de";
	}
} else {
	$lang = quotemeta($query{'lang'}); 
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

# Default value for Miniserverport
if (!$udpport) {$udpport = "80";}
if (!$rmpvol) {$rmpvol = "25";}
$step++;

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

	$pcfg             = new Config::Simple("$installfolder/config/plugins/$psubfolder/sonos.cfg");
	$LoxDaten		  = $pcfg->param("LOXONE.LoxDaten");
	$udpport	  	  = $pcfg->param("LOXONE.LoxPort");
	$apikey 		  = $pcfg->param("TTS.API-key");
	$seckey 		  = $pcfg->param("TTS.secret-key");
	$t2s_engine       = $pcfg->param("TTS.t2s_engine");
	$voice	 		  = $pcfg->param("TTS.voice");
	$rampto	 		  = $pcfg->param("TTS.rampto");
	$rmpvol	 	  	  = $pcfg->param("TTS.volrampto");
	$lang			  = $pcfg->param("TTS.messageLang");
	$MP3store 		  = $pcfg->param("MP3.MP3store");
	$volume		  	  = $pcfg->param("MP3.volumedown");
	$file_gong		  = $pcfg->param("MP3.file_gong");
	$rsender		  = $pcfg->param("RADIO.radio_name[]");
	$rsenderurl		  = $pcfg->param("RADIO.radio_adresse[]");
		
	# Call Subroutine to scan/import Sonos Zones
	&scan;
		
	# Filter
	#$apikey   = quotemeta($apikey);
	
	# Prepare form defaults
	# T2S_ENGINE
	if ($t2s_engine eq "2001") {
	  $selectedinstanz1 = "checked=checked";
	} elsif ($t2s_engine eq "1001") {
	  $selectedinstanz2 = "checked=checked";
	} elsif ($t2s_engine eq "3001") {
	  $selectedinstanz3 = "checked=checked";
	} else {
	  $selectedinstanz1 = "checked=checked";
	} 
	# VOICE
	if ($voice eq "Marlene") {
	  $selectedvoice1 = "selected=selected";
	} elsif ($voice eq "Hans") {
	  $selectedvoice2 = "selected=selected";
	} else {
	  $selectedvoice1 = "selected=selected";
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
	$volume 		= param('volume');
	$rampto		 	= param('rampto');
	$rmpvol		 	= param('rmpvol');
	
		
	
	# Filter
	#$MP3Store   	= quotemeta($MP3store);
	#$t2s_engine   	= quotemeta($t2s_engine);
	#$apikey   		= quotemeta($apikey);
	#$seckey 		= quotemeta($seckey);
	#$voice 		= quotemeta($voice);
	$file_gong 		= quotemeta($file_gong);
	#$LoxDaten 		= quotemeta($LoxDaten);
	#$volume 		= quotemeta($volume);
	#$rampto 		= quotemeta($rampto);
	$rmpvol 		= quotemeta($rmpvol);
	$udpport 		= quotemeta($udpport);
	#$lang 			= quotemeta($lang);
	
	
	# OK - now installing...

	# Write configuration file(s)
	$pcfg->param("LOXONE.LoxDaten", "$LoxDaten");
	$pcfg->param("LOXONE.LoxPort", "$udpport");
	$pcfg->param("TTS.t2s_engine", "$t2s_engine");
	$pcfg->param("TTS.rampto", "$rampto");
	$pcfg->param("TTS.volrampto", "$rmpvol");
	$pcfg->param("TTS.messageLang", "$lang");
	$pcfg->param("TTS.API-key", "$apikey");
	$pcfg->param("TTS.secret-key", "$seckey");
	$pcfg->param("TTS.voice", "$voice");
	$pcfg->param("MP3.file_gong", "$file_gong");
	$pcfg->param("MP3.volumedown", "$volume");
	$pcfg->param("MP3.volumeup", "$volume");
	$pcfg->param("MP3.MP3store", "$MP3store");
	#$pcfg->param("RADIO.radio_name[]", "$rsender");
	#$pcfg->param("RADIO.radio_adresse[]", "$rsenderurl");
	
	for ($i = 1; $i <= 6; $i++) {
	if (!param("rsender$i") ne "" ) {
		my $rsender = param("rsender$i");
		$pcfg->param("RADIO.radio_name[]", "$rsender$i");
		}
	}
		
	$pcfg->save();

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
	# führt das PHP script aus (liest zuerst sonos.cfg und fügt ggf. neue Zonen hinzu)
	# und erstellt im config Verzeichnis die Datei tmp_player.json
	#my $response = qx(/usr/bin/php $installfolder/webfrontend/html/plugins/$psubfolder/System/network.php);
	
	#importiert die Sonos Player aus JSON Datei (Button "Scan Zonen")
	if (param('scan') and param('scan') eq 'Scan Zonen') {
		open (my $fh, '<:raw', "$installfolder/config/plugins/$psubfolder/tmp_player.json") or die("Die Datei: $config_path konnte nicht geöffnet werden! $!\n");
			my $file; { local $/; $file = <$fh>; }
			my $config = decode_json($file);
	}
		
	# Alternativ über CPAN Perl Modul ohne speichern der PHP array als JSON Datei (derzeit nicht installiert)
	#---------------------------------------------------------------------------------------------------------
	#use PHP::Include;
	#include_php_vars('$installfolder/webfontend/html/plugins/$psubfolder/System/getSonosDevices.php' );
	#my $test = \%config;
	#print $test->{'sonoszonen'};
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
	  #$helplink = "http://www.loxwiki.eu:80/x/uYCm";
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
