#!/usr/bin/perl -w
# =============================================================================
# Sonos4Lox / Sonos UI - index.cgi
# =============================================================================
# Copyright 2025 Oliver Lewald
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
# =============================================================================

##########################################################################
# Modules
##########################################################################

use strict;
use warnings;
use utf8;

use LoxBerry::System;
use LoxBerry::Web;
use LoxBerry::Log;
use LoxBerry::Storage;
use LoxBerry::IO;
use LoxBerry::JSON;

use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use CGI ();

use LWP::UserAgent;

use File::Copy qw(copy);
use File::Compare;
use Time::HiRes qw(time);
use POSIX qw(strftime);

use Config::Simple;                 # REQUIRED (used in save())
use URI::Escape qw(uri_unescape);   # REQUIRED (used in _validate_url)
use Encode ();                      # REQUIRED (used in _validate_url)
use JSON qw(decode_json);           # decode_json used in several places
use JSON::PP ();                    # JSON::PP::true/false for consistent JSON booleans

use Scalar::Util qw/reftype/;
use List::MoreUtils qw(uniq);

# Template system needs symbol-table access (legacy LoxBerry plugin templates)
no strict "refs";

##########################################################################
# Generic exception handler
##########################################################################
# Every non-handled exception sets @reason which can be logged in END{}
our @reason;
$SIG{__DIE__} = sub {
    return if $^S;     # inside eval -> ignore (handled)
    @reason = @_;
};

##########################################################################
# Variables / Constants (consolidated)
##########################################################################

# --- Template / language ---
my $helptemplatefilename  = "help/help.html";
my $languagefile          = "sonos.ini";
my $maintemplatefilename  = "sonos.html";

# --- Logfile ---
my $pluginlogfile         = "sonos.log";

# --- Paths / folders ---
my $ttsfolder             = "tts";
my $mp3folder             = "mp3";

# --- System scripts/files ---
my $scanzonesfile         = "network.php";
my $udp_file              = "ms_inbound.php";

# --- Defaults ---
my $azureregion           = "westeurope";  # Change here if you use Azure API keys for another region

# --- (Legacy) Remote info file (currently not used) ---
my $urlfile               = "https://raw.githubusercontent.com/Liver64/LoxBerry-Sonos/master/webfrontend/html/release/info.txt";

# --- Config files ---
my $configfile            = "s4lox_config.json";
my $volumeconfigfile      = "s4lox_vol_profiles.json";

# --- LoxBerry runtime values ---
my $lbip                  = LoxBerry::System::get_localip();
my $host                  = LoxBerry::System::lbhostname();
my $lbport                = lbwebserverport();

# --- Help link ---
my $helplink              = "http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone";

# --- Runtime / globals used across subs / template association ---
our $template;
our %SL;
our $cgi;

our $jsonobj       = LoxBerry::JSON->new();
our $cfg           = $jsonobj->open(filename => $lbpconfigdir . "/" . $configfile, writeonclose => 0);

our $log           = LoxBerry::Log->new(
    name     => 'Sonos UI',
    filename => $lbplogdir . "/" . $pluginlogfile,
    append   => 1,
    addtime  => 1
);

our $mqttcred;
our $cfgm;

our $lbv;
our $countplayers      = 0;
our $countsoundbars    = 0;

our $rowssonosplayer   = '';
our $rowsvolplayer     = '';

our $vcfg;
our $jsonparser;
my  $size              = 0;

# Cache (RAM)
my  $cache_dir         = "/dev/shm/sonos4lox";
our $cache_file        = $cache_dir . "/discovery_cache.json";

# UI / error handling
our $error_message     = "";

##########################################################################
# Default config upgrade logic (write missing defaults once)
##########################################################################

my $t0 = time();

if (-r $lbpconfigdir . "/" . $volumeconfigfile) {
    $jsonparser = LoxBerry::JSON->new();
    $vcfg       = $jsonparser->open(filename => $lbpconfigdir . "/" . $volumeconfigfile);
    $size       = scalar @$vcfg;
}

my $defaultSave = "false";

# Add new parameter for cachesize
if (!defined $cfg->{"MP3"}->{cachesize}) {
    $cfg->{MP3}->{cachesize} = "100";
    $defaultSave = "true";
}

# Rampto Volume
if ($cfg->{TTS}->{volrampto} eq '') {
    $cfg->{TTS}->{volrampto} = "25";
    $defaultSave = "true";
}

# Rampto type
if ($cfg->{TTS}->{rampto} eq '') {
    $cfg->{TTS}->{rampto} = "auto";
    $defaultSave = "true";
}

# Add new parameter for Volume correction
if (!defined $cfg->{TTS}->{correction}) {
    $cfg->{TTS}->{correction} = "8";
    $defaultSave = "true";
}

# Add new parameter for Azure region
if (!defined $cfg->{TTS}->{regionms}) {
    $cfg->{TTS}->{regionms} = $azureregion;
    $defaultSave = "true";
}

# Add new parameter for Volume phone mute
if (!defined $cfg->{TTS}->{phonemute}) {
    $cfg->{TTS}->{phonemute} = "8";
    $defaultSave = "true";
}

# Add new parameter for waiting time in seconds
if (!defined $cfg->{TTS}->{waiting}) {
    $cfg->{TTS}->{waiting} = "10";
    $defaultSave = "true";
}

# Add new parameter for phonestop
if (!defined $cfg->{VARIOUS}->{phonestop}) {
    $cfg->{VARIOUS}->{phonestop} = "0";
    $defaultSave = "true";
}

# Reset time for zapzone
if (!defined $cfg->{VARIOUS}->{cron}) {
    $cfg->{VARIOUS}->{cron} = "1";
    $defaultSave = "true";
}

# checkonline
if (!defined $cfg->{SYSTEM}->{checkonline}) {
    $cfg->{SYSTEM}->{checkonline} = 3;
    $defaultSave = "true";
}

# maxVolume
if (!defined $cfg->{VARIOUS}->{volmax}) {
    $cfg->{VARIOUS}->{volmax} = "0";
    $defaultSave = "true";
}

# Loxone data to MQTT
if (!defined $cfg->{LOXONE}->{LoxDatenMQTT}) {
    $cfg->{LOXONE}->{LoxDatenMQTT} = "false";
    $defaultSave = "true";
}

# Text-to-speech status
if (!defined $cfg->{TTS}->{t2son}) {
    $cfg->{TTS}->{t2son} = "true";
    $defaultSave = "true";
}

# Engine status
if (!defined $cfg->{TTS}->{t2s_engine}) {
    $cfg->{TTS}->{t2s_engine} = "9012";
	$cfg->{TTS}->{voice} = "thorsten";
	$cfg->{TTS}->{messageLang} = "de_DE";
    $defaultSave = "true";
}

# Start time TV monitoring
if (!defined $cfg->{VARIOUS}->{starttime}) {
    $cfg->{VARIOUS}->{starttime} = "10";
    $defaultSave = "true";
}

# End time TV monitoring
if (!defined $cfg->{VARIOUS}->{endtime}) {
    $cfg->{VARIOUS}->{endtime} = "22";
    $defaultSave = "true";
}

# Copy old API-key value to apikey
if (defined $cfg->{TTS}->{'API-key'}) {
    $cfg->{TTS}->{apikey} = $cfg->{TTS}->{'API-key'};
    delete $cfg->{TTS}->{'API-key'};
}

# Copy global API-key to engine-API-key map
if (!defined $cfg->{TTS}->{apikeys}) {
    $cfg->{TTS}->{apikeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{apikey};
}

# Copy old secret-key value to secretkey
if (defined $cfg->{TTS}->{'secret-key'}) {
    $cfg->{TTS}->{secretkey} = $cfg->{TTS}->{'secret-key'};
    delete $cfg->{TTS}->{'secret-key'};
}

# Copy global Secret-key to engine-secretkey map
if (!defined $cfg->{TTS}->{secretkeys}) {
    $cfg->{TTS}->{secretkeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{secretkey};
}

# Follow host
if (!defined $cfg->{VARIOUS}->{follow_host}) {
    $cfg->{VARIOUS}->{follow_host} = "false";
    $defaultSave = "true";
}

# Leave/follow host
if (!defined $cfg->{VARIOUS}->{follow_wait}) {
    $cfg->{VARIOUS}->{follow_wait} = "false";
    $defaultSave = "true";
}

# TTS Presence
if (!defined $cfg->{TTS}->{presence}) {
    $cfg->{TTS}->{presence} = "true";
    $defaultSave = "true";
}

# Host or IP for CIFS
if (!defined $cfg->{TTS}->{hostip}) {
    $cfg->{TTS}->{hostip} = "host";
    $defaultSave = "true";
}

# Switch to MQTT if old LoxDaten is enabled but LoxDatenMQTT is disabled
if (is_disabled($cfg->{LOXONE}->{LoxDatenMQTT}) && is_enabled($cfg->{LOXONE}->{LoxDaten})) {
    $cfg->{LOXONE}->{LoxDatenMQTT} = "true";
    $defaultSave = "true";
}

if (is_enabled($defaultSave)) {
    $jsonobj->write();
}

##########################################################################
# Read settings / environment
##########################################################################

# Read language
my $lblang = lblanguage();
%SL = LoxBerry::System::readlanguage($template, $languagefile);

# Read plugin version
my $sversion = LoxBerry::System::pluginversion();

# Read LoxBerry version
my $lbversion = LoxBerry::System::lbversion();
$lbv = substr($lbversion, 0, 1);

# Read all POST parameters into namespace "R"
$cgi = CGI->new;
$cgi->import_names('R');

# Get MQTT credentials
$mqttcred = LoxBerry::IO::mqtt_connectiondetails();

#########################################################################
# Handle AJAX requests
#########################################################################

our $q = $cgi->Vars;
our $IS_AJAX_REQUEST = 0;
our $AJAX_ACTION     = '';

# ------------------------------------------------------------
# AJAX: Validator for ICS/JSON (called via ?action=validate_ics)
# ------------------------------------------------------------
if (defined $q->{action} && $q->{action} eq 'validate_ics') {

    my $url  = defined $q->{url}  ? $q->{url}  : '';
    my $mode = defined $q->{mode} ? $q->{mode} : 'ics';

    my ($ok, $msg, $events) = _validate_url($url, $mode);

    print "Content-Type: application/json; charset=utf-8\n";
    print "Cache-Control: no-store, no-cache, must-revalidate\n\n";

    print JSON::encode_json({
        ok     => $ok ? JSON::PP::true : JSON::PP::false,
        msg    => $ok ? undef : $msg,
        events => $ok ? ($events // 0) : undef,
    });

    exit;
}

# Early exit for AJAX actions (no template, no LOGSTART/LOGEND wrapper)
if ($q->{action}) {
    $IS_AJAX_REQUEST = 1;
    $AJAX_ACTION     = $q->{action} // '';

    print "Content-type: application/json\n\n";

    if ($AJAX_ACTION eq "soundbars") {
        print JSON::encode_json($cfg->{sonoszonen});
        exit;
    }

    if ($AJAX_ACTION eq "profiles") {
        $vcfg = $jsonobj->open(
            filename     => $lbpconfigdir . "/s4lox_vol_profiles.json",
            writeonclose => 0
        );
        print JSON::encode_json($vcfg);
        exit;
    }

    if ($AJAX_ACTION eq "getradio") {
        print JSON::encode_json($cfg->{RADIO}->{radio});
        exit;
    }

    if ($AJAX_ACTION eq "saveconfig") {
        print saveconfig();
        exit;
    }

    if ($AJAX_ACTION eq "restoreconfig") {
        print restoreconfig();
        exit;
    }

    if ($AJAX_ACTION eq "restart_listener") {
        # No INFO logging for actions; log only on error via $error_message/END handler
        my $rc = system('sudo', 'systemctl', 'restart', 'sonos_event_listener');
        if ($rc != 0) {
            $error_message = "AJAX restart_listener failed (rc=$rc)";
            print JSON::encode_json({ success => JSON::false, error => $error_message });
            exit;
        }
        print JSON::encode_json({ success => JSON::true });
        exit;
    }

    # Unknown action -> error (logged by END handler)
    $error_message = "Unknown AJAX action: $AJAX_ACTION";
    print JSON::encode_json({ success => JSON::false, error => $error_message });
    exit;
}

# Legacy AJAX: getkeys (namespace "R")
if ($R::getkeys) {
    $IS_AJAX_REQUEST = 1;
    $AJAX_ACTION     = 'getkeys';
    getkeys(); # exits
}

##########################################################################
# Init template / logging for normal page render
##########################################################################

inittemplate();
LOGSTART "Plugin GUI";

##########################################################################
# Inform user if defaults were written
##########################################################################
if (is_enabled($defaultSave)) {
    LOGOK("index.cgi: Missing defaults have been saved");
}

##########################################################################
# Set LoxBerry SDK debug if plugin is in debug level
##########################################################################
if ($log->loglevel() eq "7") {
    $LoxBerry::System::DEBUG   = 1;
    $LoxBerry::Web::DEBUG      = 1;
    $LoxBerry::Storage::DEBUG  = 1;
    $LoxBerry::Log::DEBUG      = 1;
    $LoxBerry::IO::DEBUG       = 1;
}

##########################################################################
# Language / template globals
##########################################################################

$template->param("LBHOSTNAME", lbhostname());
$template->param("LBLANG", $lblang);
$template->param("SELFURL", $ENV{REQUEST_URI});

LOGDEB "Read main settings from $languagefile for language: $lblang";

# Pass plugin directory to HTML
$template->param("PLUGINDIR" => $lbpplugindir);

# Pass log file path to HTML
$template->param("LOGFILE", $lbplogdir . "/" . $pluginlogfile);

##########################################################################
# Check if config exists
##########################################################################

if (!-r $lbpconfigdir . "/" . $configfile) {
    LOGCRIT "Plugin config file does not exist";
    $error_message = $SL{'ERRORS.ERR_CHECK_SONOS_CONFIG_FILE'};
    notify($lbpplugindir, "Sonos UI", "Error loading Sonos configuration file. Please try again or check config folder!", 1);
    error();
} else {
    LOGDEB "The Sonos config file has been loaded";
}

LOGDEB "LoxBerry Version: $lbversion";

##########################################################################
# Navbar
##########################################################################

our %navbar;
$navbar{1}{Name} = "$SL{'BASIS.MENU_SETTINGS'}";
$navbar{1}{URL}  = './index.cgi';
$navbar{2}{Name} = "$SL{'BASIS.MENU_OPTIONS'}";
$navbar{2}{URL}  = './index.cgi?do=details';
$navbar{3}{Name} = "$SL{'BASIS.MENU_VOLUME'}";
$navbar{3}{URL}  = './index.cgi?do=volume';
$navbar{99}{Name} = "$SL{'BASIS.MENU_LOGFILES'}";
$navbar{99}{URL}  = './index.cgi?do=logfiles';

# If MQTT credentials are valid AND communication is ON -> add MQTT navbar entry
if ($mqttcred and $cfg->{LOXONE}->{LoxDaten} eq "true")  {
    $navbar{4}{Name} = "$SL{'BASIS.MENU_MQTT'}";

    # Lower than LB version 3
    if ($lbv < 3) {
        my $cfgfile = $lbhomedir . '/config/plugins/mqttgateway/mqtt.json';
        my $json = LoxBerry::JSON->new();
        $cfgm = $json->open(filename => $cfgfile);
        $navbar{3}{URL} = '/admin/plugins/mqttgateway/index.cgi';
    } else {
        my $cfgfile = $lbhomedir . '/config/system/mqttgateway.json';
        my $json = LoxBerry::JSON->new();
        $cfgm = $json->open(filename => $cfgfile);
        $navbar{4}{URL} = '/admin/system/mqtt.cgi';
    }
    $navbar{4}{target} = '_blank';
}

##########################################################################
# Handle form submits
##########################################################################

if ($R::saveformdata1) {
    $template->param(FORMNO => 'form');
    save();
}
if ($R::saveformdata2) {
    $template->param(FORMNO => 'details');
    save_details();
}
if ($R::saveformdata3) {
    $template->param(FORMNO => 'volume');
    save_volume();
}

##########################################################################
# Installation state check
##########################################################################

our $countplayer;
my $inst;

if (exists($cfg->{sonoszonen})) {
    $countplayer = 1;
    $inst = "true";
} else {
    $countplayer = 0;
    $inst = "false";
}
$template->param("PLAYER_AVAILABLE", $countplayer);

# Check if basic plugin backup config exists
if (-r $lbhomedir . "/webfrontend/html/XL/" . $configfile) {
    my $compare = compare($lbpconfigdir . "/" . $configfile, $lbhomedir . "/webfrontend/html/XL/" . $configfile);

    # If different
    if ($compare == 1 && $inst eq "true") {
        LOGDEB("Main Config is not equal to Backup");
        $template->param("CONFIG_DIFFERENT", "1");
    }
    if ($compare == 1 && $inst eq "false") {
        $template->param("RESTORE_POSSIBLE", "1");
    }
} else {
    $template->param("CONFIG_DIFFERENT", "1");
}

# Check if sound profile backup config exists
if (-r $lbhomedir . "/webfrontend/html/XL/" . $volumeconfigfile) {
    my $compare = compare($lbpconfigdir . "/" . $volumeconfigfile, $lbhomedir . "/webfrontend/html/XL/" . $volumeconfigfile);

    if ($compare == 1 && $inst eq "true") {
        LOGDEB("Volume Profile Config is not equal to Backup");
        $template->param("CONFIG_DIFFERENT", "1");
    }
    if ($compare == 1 && $inst eq "false") {
        $template->param("RESTORE_POSSIBLE", "1");
    }
} else {
    $template->param("CONFIG_DIFFERENT", "1");
}

##########################################################################
# Main dispatch
##########################################################################

if (!defined $R::do or $R::do eq "form") {
    $navbar{1}{active} = 1;
    $template->param("SETTINGS", "1");
    form();
}
elsif ($R::do eq "details") {
    $navbar{2}{active} = 1;
    $template->param("DETAILS", "1");
    form();
}
elsif ($R::do eq "logfiles") {
    LOGTITLE "Show logfiles";
    $navbar{99}{active} = 1;
    $template->param("LOGFILES", "1");
    $template->param("LOGLIST_HTML", LoxBerry::Web::loglist_html());
    printtemplate();
}
elsif ($R::do eq "scanning") {
    LOGTITLE "Execute scan";
    scan();
    $template->param("SETTINGS", "1");
    form();
}
elsif ($R::do eq "volume") {
    $navbar{3}{active} = 1;
    $template->param("VOLUME", "1");
    volumes();
    form();
}

$error_message = "Invalid do parameter: " . ($R::do // '');
error();
exit;

#####################################################
# Form (main page renderer)
#####################################################

sub form
{
    $template->param(FORMNO => 'SETTINGS');

    $t0 = time();

    # If path is missing (upgrade from older versions), set default path
    if ($cfg->{SYSTEM}->{path} eq "") {
        $cfg->{SYSTEM}->{path} = "$lbpdatadir";
        LOGINF("Default path has been added to config");
    }

    # -------------------------------------------------------------------------
    # Storage HTML (CACHED): get_storage_html is expensive -> cache in /dev/shm
    # -------------------------------------------------------------------------
    my $cache_dir_local  = "/dev/shm/$lbpplugindir";
    my $cache_file_local = "$cache_dir_local/storage_html.cache";
    my $cache_ttl        = 300;  # seconds (5 min)

    if (!-d $cache_dir_local) {
        mkdir($cache_dir_local, 0775);
    }

    my $currentpath = $jsonobj->param("SYSTEM.path") // "";
    my $cache_key   = "PATH=$currentpath";

    my $storage;
    my $use_cache = 0;

    if (-r $cache_file_local) {
        my @st  = stat($cache_file_local);
        my $age = @st ? (time() - ($st[9] // 0)) : 999999;

        if ($age <= $cache_ttl) {
            if (open(my $fh, '<', $cache_file_local)) {
                my $first = <$fh>;
                chomp($first) if defined $first;

                if (defined $first && $first eq $cache_key) {
                    local $/ = undef;
                    $storage = <$fh>;
                    $use_cache = 1 if defined $storage && $storage ne '';
                }
                close($fh);
            }
        }
    }

    if ($use_cache) {
        LOGDEB("PERF: Storage HTML loaded from cache (ttl=${cache_ttl}s)");
    } else {
        $storage = LoxBerry::Storage::get_storage_html(
            formid        => 'STORAGEPATH',
            currentpath   => $currentpath,
            custom_folder => 1,
            type_all      => 1,
            readwriteonly => 1,
            data_mini     => 1,
            label         => "$SL{'T2S.SAFE_DETAILS'}"
        );

        if (open(my $fh, '>', $cache_file_local)) {
            print $fh $cache_key . "\n";
            print $fh $storage // '';
            close($fh);
        }
    }

    $template->param("STORAGEPATH", $storage // '');

    # Fill saved values into form
    $template->param("SELFURL", $SL{REQUEST_URI});
    $template->param("T2S_ENGINE" => $cfg->{TTS}->{t2s_engine});
    $template->param("APIKEY"     => $cfg->{TTS}->{apikeys}->{$cfg->{TTS}->{t2s_engine}});
    $template->param("SECKEY"     => $cfg->{TTS}->{secretkeys}->{$cfg->{TTS}->{t2s_engine}});
    $template->param("VOICE"      => $cfg->{TTS}->{voice});
    $template->param("CODE"       => $cfg->{TTS}->{messageLang});
    $template->param("DATADIR"    => $cfg->{SYSTEM}->{path});
    $template->param("LOX_ON"     => $cfg->{LOXONE}->{LoxDaten});

    # Show test voice/message area if language/voice already saved
    my $testvoice = $cfg->{TTS}->{voice};
    my $testlang  = $cfg->{TTS}->{messageLang};
    if ($testvoice ne "" || $testlang ne "") {
        $template->param("TESTVOICE", 1);
    }

    # --- Guard: RADIO can be broken (e.g. "RADIO": []), normalize to HASH ---
    if (ref($cfg->{RADIO}) ne 'HASH') {
        $cfg->{RADIO} = {};
    }
    if (ref($cfg->{RADIO}->{radio}) ne 'HASH') {
        $cfg->{RADIO}->{radio} = {};
    }

    # Load Radio favorites
    our $countradios = 0;
    our $rowsradios  = '';
    my $radiofavorites = $cfg->{RADIO}->{radio};

    foreach my $key (keys %{$radiofavorites}) {
        $countradios++;
        my @fields = split(/,/, $cfg->{RADIO}->{radio}->{$countradios});
        $rowsradios .= "<tr><td style='height: 25px; width: 43px;'><INPUT type='checkbox' style='width: 20px' name='chkradios$countradios' id='chkradios$countradios' align='center'/></td>\n";
        $rowsradios .= "<td style='height: 28px'><input type='text' id='radioname$countradios' name='radioname$countradios' size='20' value='$fields[0]' /> </td>\n";
        $rowsradios .= "<td style='width: 600px; height: 28px'><input type='text' id='radiourl$countradios' name='radiourl$countradios' size='100' value='$fields[1]' style='width: 100%' /> </td>\n";
        $rowsradios .= "<td style='width: 600px; height: 28px'><input type='text' id='coverurl$countradios' name='coverurl$countradios' size='100' value='$fields[2]' style='width: 100%' /> </td></tr>\n";
    }

    if ($countradios < 1) {
        $rowsradios .= "<tr><td colspan=4>" . $SL{'RADIO.SONOS_EMPTY_RADIO'} . "</td></tr>\n";
    }

    LOGDEB "Radio Stations have been loaded.";
    $rowsradios .= "<input type='hidden' id='countradios' name='countradios' value='$countradios'>\n";
    $template->param("ROWSRADIO", $rowsradios);

    # Auto-discovery scan (cached/TTL in network.php)
    scan();

    # ---------------------------------------------------------------------
    # Load Sonos players / zones
    # ---------------------------------------------------------------------

    $rowssonosplayer = '';
    our $rowssoundbar = '';
    our $currtime;

    my $error_treble_bass = $SL{'VOLUME_PROFILES.ERROR_TREBLE_BASS_PLAYER'};
    my $error_volume      = $SL{'T2S.ERROR_VOLUME_PLAYER'};

    my $config = $cfg->{sonoszonen};

    # Audioclip capability counters
    my $audioclip_ok_count = 0;

    $currtime = strftime("%H:%M", localtime());

    # Reset counters (important if form() is called multiple times)
    $countplayers   = 0;
    $countsoundbars = 0;

    foreach my $key (sort keys %$config) {

        $countplayers++;
        my $room = $key;

        my $filename   = $lbphtmldir . '/images/icon-' . $config->{$key}->[7] . '.png';
        my $statusfile = $lbpdatadir . '/PlayerStatus/s4lox_on_' . $room . '.txt';

        $rowssonosplayer .= "<tr>";
        $rowssonosplayer .= "<td style='height: 25px; width: 20px;'><input type='checkbox' name='chkplayers$countplayers' id='chkplayers$countplayers' align='middle'/></td>\n";

        if (-e $statusfile) {
            $rowssonosplayer .= "<td style='height: 28px; width: 16%;'><input type='text' class='pd-price' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$room' style='width:100%; background-color:#6dac20; color:white'></td>\n";
        } else {
            $rowssonosplayer .= "<td style='height: 28px; width: 16%;'><input type='text' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$room' style='width: 100%; background-color: #e6e6e6;'></td>\n";
        }

        $rowssonosplayer .= "<td style='height: 25px; width: 6px;'><input type='checkbox' class='chk-checked' name='mainchk$countplayers' id='mainchk$countplayers' value='$config->{$key}->[6]' align='center'></td>\n";

        # Highlight old S1 devices red
        if (($config->{$key}[9]) eq "1") {
            $rowssonosplayer .= "<td style='height: 28px; width: 15%;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='$config->{$key}->[2]' style='width: 100%; background-color: red; color:white'></td>\n";
            $template->param("SWGEN", "1");
        } else {
            $rowssonosplayer .= "<td style='height: 28px; width: 15%;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='$config->{$key}->[2]' style='width: 100%; background-color: #e6e6e6;'></td>\n";
        }

        # Column: Sonos Player Logo
        if (-e $filename) {
            $rowssonosplayer .= "<td style='height: 28px; width: 2%;'><img src='/plugins/$lbpplugindir/images/icon-$config->{$key}->[7].png' border='0' width='50' height='50' align='middle'/></td>\n";
        } else {
            $rowssonosplayer .= "<td style='height: 28px; width: 2%;'><img src='/plugins/$lbpplugindir/images/sonos_logo_sm.png' border='0' width='50' height='50' align='middle'/></td>\n";
        }

        $rowssonosplayer .= "<td style='height: 28px; width: 17%;'><input type='text' id='ip$countplayers' name='ip$countplayers' size='30' value='$config->{$key}->[0]' style='width: 100%; background-color: #e6e6e6;'></td>\n";

        # Audioclip capability indicator (green/red icon)
        my $audioclip_ok = ($config->{$key}[11]) ? 1 : 0;

        if ($audioclip_ok) {
            $rowssonosplayer .= "<td style='height: 30px; width: 10px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/green.png' border='0' width='26' height='28' align='center'/></div></td>\n";
            $audioclip_ok_count++;
        } else {
            $rowssonosplayer .= "<td style='height: 30px; width: 10px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/red.png' border='0' width='26' height='28' align='center'/></div></td>\n";
        }

        $rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='t2svol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='t2svol$countplayers' value='$config->{$key}->[3]'></td>\n";
        $rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='sonosvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='sonosvol$countplayers' value='$config->{$key}->[4]'></td>\n";
        $rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='maxvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='maxvol$countplayers' value='$config->{$key}->[5]'></td>\n";

        # Soundbar count
        if (($config->{$key}[13]) eq "SB") {
            $countsoundbars++;
            $rowssonosplayer .= "<input type='hidden' id='sb$countplayers' name='sb$countplayers' value='$config->{$key}->[13]'>\n";
        } else {
            $rowssonosplayer .= "<input type='hidden' id='sb$countplayers' name='sb$countplayers' value='NOSB'>\n";
        }

        # Time restrictions
        if ($config->{$key}->[15] ne "" || $config->{$key}->[16] ne "") {
            if ($currtime ge $config->{$key}->[15] && $currtime lt $config->{$key}->[16]) {
                $rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-start-time$countplayers' type='time' name='pl-start-time$countplayers' value='$config->{$key}->[15]'></td>\n";
                $rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-end-time$countplayers' type='time' name='pl-end-time$countplayers' value='$config->{$key}->[16]'></td></tr>\n";
            } else {
                $rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-start-time$countplayers' type='time' name='pl-start-time$countplayers' value='$config->{$key}->[15]' style='width:100%; background-color:orange; color:black'></td>\n";
                $rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-end-time$countplayers' type='time' name='pl-end-time$countplayers' value='$config->{$key}->[16]'style='width:100%; background-color:orange; color:black'></td></tr>\n";
            }
        } else {
            $rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-start-time$countplayers' type='time' name='pl-start-time$countplayers' value='$config->{$key}->[15]'></td>\n";
            $rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-end-time$countplayers' type='time' name='pl-end-time$countplayers' value='$config->{$key}->[16]'></td></tr>\n";
        }

        # Hidden fields per room
        $rowssonosplayer .= "<input type='hidden' id='room$countplayers' name='room$countplayers' value=$room>\n";
        $rowssonosplayer .= "<input type='hidden' id='models$countplayers' name='models$countplayers' value='$config->{$key}->[7]'>\n";
        $rowssonosplayer .= "<input type='hidden' id='sub$countplayers' name='sub$countplayers' value='$config->{$key}->[8]'>\n";
        $rowssonosplayer .= "<input type='hidden' id='householdId$countplayers' name='householdId$countplayers' value='$config->{$key}->[9]'>\n";
        $rowssonosplayer .= "<input type='hidden' id='sur$countplayers' name='sur$countplayers' value='$config->{$key}->[10]'>\n";
        $rowssonosplayer .= "<input type='hidden' id='audioclip$countplayers' name='audioclip$countplayers' value='$config->{$key}->[11]'>\n";
        $rowssonosplayer .= "<input type='hidden' id='voice$countplayers' name='voice$countplayers' value='$config->{$key}->[12]'>\n";
        $rowssonosplayer .= "<input type='hidden' id='rincon$countplayers' name='rincon$countplayers' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' value='$config->{$key}->[1]'>\n";

        # Prepare soundbar table if device is a soundbar
        if (($config->{$key}[13]) eq "SB") {
            $rowssoundbar .= "<tr class='tvmon_body'>\n";
            $rowssoundbar .= "<td style='height: 25px; width: 13%;'><fieldset align='center'><select id='usesb_$room' name='usesb_$room' data-role='flipswitch' style='width: 100%'><option value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
            $rowssoundbar .= "<div id='tvmonitor'><td style='height: 28px; width: 20%;'><input type='text' id='sbzone_$room' name='sbzone_$room' size='40' readonly='true' value='$room' vertical-align='center' style='width: 100%; background-color: #e6e6e6;'></td>\n";
            $rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonspeech_$room' name='tvmonspeech_$room' data-role='flipswitch' style='width: 100%'><option value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
            $rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonsurr_$room' name='tvmonsurr_$room' data-role='flipswitch' style='width: 100%'><option selected='selected' value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
            $rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonnightsub_$room' name='tvmonnightsub_$room' data-role='flipswitch' style='width: 100%'><option selected='selected' value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
            $rowssoundbar .= "<td style='width: 6%; height: 28px;'><div><input class='tvvol' type='text' id='tvvol_$room' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='tvvol_$room' value='$config->{$key}->[14]->{tvvol}'></div></td></div>\n";
            $rowssoundbar .= "<td style='width: 6%; height: 28px;'><div><input class='tvtreble' type='text' id='tvtreble_$room' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='$error_treble_bass' name='tvtreble_$room' value='$config->{$key}->[14]->{tvtreble}'></div></td></div>\n";
            $rowssoundbar .= "<td style='width: 6%; height: 28px;'><div><input class='tvbass' type='text' id='tvbass_$room' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='$error_treble_bass' name='tvbass_$room' value='$config->{$key}->[14]->{tvbass}'></div></td></div>\n";
            $rowssoundbar .= "<td style='width: 8%; height: 28px;'><div id='tvmon_addend'><input id='fromtime_$room' type='time' name='fromtime_$room' value='$config->{$key}->[13]->{fromtime}'></div></td>\n";
            $rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonnight_$room' name='tvmonnight_$room' data-role='flipswitch' style='width: 100%'><option selected='selected' value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
            $rowssoundbar .= "<td style='width: 8%'><fieldset align='center'><select id='tvmonnightsubn_$room' name='tvmonnightsubn_$room' data-role='flipswitch' style='width: 100%'><option selected='selected' value='false'>$SL{'T2S.LABEL_FLIPSWITCH_OFF'}</option><option value='true'>$SL{'T2S.LABEL_FLIPSWITCH_ON'}</option></select></fieldset></td>\n";
            $rowssoundbar .= "<td style='width: 8%'><div id='tvmon_addend'><fieldset align='center'>\n";
            $rowssoundbar .= "<select id='subgain_$room' name='subgain_$room' data-mini='true' data-native-menu='true' style='width: 100%'>";
            $rowssoundbar .= "    <option value='-15'>-15</option>
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

    if ($countplayers < 1) {
        $rowssonosplayer .= "<tr><td colspan=10>" . $SL{'ZONES.SONOS_EMPTY_ZONES'} . "</td></tr>\n";
    }

    $rowssonosplayer .= "<input type='hidden' id='countplayers' name='countplayers' value='$countplayers'>\n";
    $template->param("ROWSSONOSPLAYER", $rowssonosplayer);

    # Pass Audioclip stats to template
    $template->param(
        AUDIOCLIP_OK_COUNT    => $audioclip_ok_count,
        AUDIOCLIP_TOTAL_COUNT => $countplayers,
    );

    if ($countplayers > 0 && $audioclip_ok_count == $countplayers) {
        $template->param(AUDIOCLIP_ALL_SUPPORTED => 1);
    } else {
        $template->param(AUDIOCLIP_ALL_SUPPORTED => 0);
    }

    LOGDEB "Sonos Player list loaded. Audioclip capability: $audioclip_ok_count of $countplayers zones support Audioclip.";

    if ($countsoundbars < 1) {
        $rowssoundbar .= "<tr class='tvmon_header'><td colspan=8>" . $SL{'ZONES.SONOS_EMPTY_SOUNDBARS'} . "</td></tr>\n";
    }

    $rowssoundbar .= "<input type='hidden' id='countsoundbars' name='countsoundbars' value='$countsoundbars'>\n";
    $template->param("ROWSOUNDBARS", $rowssoundbar);
    LOGDEB "Sonos soundbars have been discovered.";

    # ---------------------------------------------------------------------
    # Miniserver selection dropdown
    # ---------------------------------------------------------------------
    my $mshtml = LoxBerry::Web::mslist_select_html(
        FORMID    => 'ms',
        SELECTED  => $jsonobj->{LOXONE}->{Loxone},
        DATA_MINI => 1,
        LABEL     => "",
    );
    $template->param('MS', $mshtml);
    LOGDEB "List of available Miniserver(s) has been loaded.";

    # ---------------------------------------------------------------------
    # MP3 dropdown (tts/mp3 folder)
    # ---------------------------------------------------------------------
    my $dir = $lbpdatadir . '/' . $ttsfolder . '/' . $mp3folder . '/';
    my $mp3_list = '';

    opendir(my $dh, $dir) or die $!;
    my @dots = grep { /\.mp3$/ && -f "$dir/$_" } readdir($dh);
    closedir($dh);

    my @sorted_dots = sort { $a <=> $b } @dots;  # numeric sort
    foreach my $file (@sorted_dots) {
        $mp3_list .= "<option value='$file'>$file</option>\n";
    }
    $template->param("MP3_LIST", $mp3_list);
    LOGDEB "List of MP3 files has been loaded.";

    # -------------------------------------------------------------------------
    # Piper voice index (FAST): rebuild only if piper-voices/*.json changed
    # -------------------------------------------------------------------------

    my $piper_dir        = $lbphtmldir . "/voice_engines/piper-voices/";
    my $piper_out_lang   = $lbphtmldir . "/voice_engines/langfiles/piper.json";
    my $piper_out_voices = $lbphtmldir . "/voice_engines/langfiles/piper_voices.json";

    my $cache_dir_piper  = "/dev/shm/$lbpplugindir";
    my $cache_meta       = $cache_dir_piper . "/piper_voices_cache.meta";

    if (!-d $cache_dir_piper) {
        mkdir($cache_dir_piper, 0775);
    }

    # Cheap change detection: max mtime + file count of *.json
    my $max_mtime  = 0;
    my $file_count = 0;

    if (-d $piper_dir) {
        if (opendir(my $pdh, $piper_dir)) {
            while (my $f = readdir($pdh)) {
                next if $f eq '.' || $f eq '..';
                next if $f !~ /\.json$/i;
                my $full = $piper_dir . $f;
                next if !-f $full;

                $file_count++;
                my @st = stat($full);
                if (@st && $st[9] && $st[9] > $max_mtime) {
                    $max_mtime = $st[9];
                }
            }
            closedir($pdh);
        }
    }

    my $cached_sig = "";
    if (-r $cache_meta) {
        if (open(my $cfh, '<', $cache_meta)) {
            chomp($cached_sig = <$cfh> // "");
            close($cfh);
        }
    }
    my $current_sig = $max_mtime . ";" . $file_count;

    my $need_rebuild = 1;
    if ($cached_sig ne "" && $cached_sig eq $current_sig && -r $piper_out_lang && -r $piper_out_voices) {
        $need_rebuild = 0;
        LOGDEB("Piper: voices cache valid ($current_sig) – skipping rebuild");
    }

    if ($need_rebuild) {
        LOGINF("Piper: rebuilding voice index (changed voices detected: $current_sig)");

        my @data_piper;
        my @data_piper_voices;
        my %seen_lang;

        if (opendir(my $dh2, $piper_dir)) {
            while (my $file = readdir($dh2)) {
                next if $file eq '.' || $file eq '..';
                next if $file !~ /\.json$/i;

                my $full = $piper_dir . $file;
                next if !-f $full;

                my $jsonparser_local = LoxBerry::JSON->new();
                my $config_local;
                eval {
                    $config_local = $jsonparser_local->open(filename => $full, writeonclose => 0);
                    1;
                } or do {
                    LOGERR("Piper: Could not parse $full – skipping");
                    next;
                };

                next if !$config_local || ref($config_local) ne 'HASH';
                next if !$config_local->{language} || ref($config_local->{language}) ne 'HASH';

                my $country = $config_local->{language}->{country_english} // '';
                my $code    = $config_local->{language}->{code}            // '';
                my $dataset = $config_local->{dataset}                     // '';
                next if $code eq '';

                # piper.json: languages unique by code
                if (!$seen_lang{$code}++) {
                    push @data_piper, { "country" => $country, "value" => $code };
                }

                # piper_voices.json: one entry per voice file
                (my $fname = $file) =~ s/\.json$//i;
                push @data_piper_voices, {
                    "name"     => $dataset,
                    "language" => $code,
                    "filename" => $fname
                };
            }
            closedir($dh2);
        }

        # Stable ordering (nice for diffs + UI determinism)
        @data_piper = sort {
            (lc($a->{country} // '') cmp lc($b->{country} // ''))
            || (lc($a->{value} // '') cmp lc($b->{value} // ''))
        } @data_piper;

        @data_piper_voices = sort {
            (lc($a->{language} // '') cmp lc($b->{language} // ''))
            || (lc($a->{name} // '') cmp lc($b->{name} // ''))
        } @data_piper_voices;

        # Write piper.json
        my $jsonobjpiper = LoxBerry::JSON->new();
        $jsonobjpiper->{jsonobj} = \@data_piper;
        $jsonobjpiper->write($piper_out_lang);

        # Write piper_voices.json
        my $jsonobjpiper_voice = LoxBerry::JSON->new();
        $jsonobjpiper_voice->{jsonobj} = \@data_piper_voices;
        $jsonobjpiper_voice->write($piper_out_voices);

        # Update cache signature
        if (open(my $wfh, '>', $cache_meta)) {
            print $wfh $current_sig;
            close($wfh);
        }

        LOGOK("Piper: voice index rebuilt (" . scalar(@data_piper) . " languages, " . scalar(@data_piper_voices) . " voices)");
    }

    # ---------------------------------------------------------------------
    # MQTT gateway installed?
    # ---------------------------------------------------------------------
    if ($mqttcred) {
        $template->param("MQTT" => "true");
        LOGDEB "MQTT Gateway is installed and valid credentials were received.";
    } else {
        $template->param("MQTT" => "false");
        $cfg->{LOXONE}->{LoxDatenMQTT} = "false";
        LOGDEB "MQTT Gateway is not installed or wrong credentials were received.";
    }

    # ---------------------------------------------------------------------
    # Event listener health status for UI
    # ---------------------------------------------------------------------
    my $sonos_health = get_sonos_health();

    $template->param(
        SONOS_HEALTH_PID            => $sonos_health->{pid},
        SONOS_HEALTH_STATUS         => $sonos_health->{status},
        SONOS_HEALTH_STATUS_CLASS   => $sonos_health->{status_class},
        SONOS_HEALTH_FORMATTED_TIME => $sonos_health->{formatted_time},
        SONOS_HEALTH_ONLINE         => $sonos_health->{players_online},
        SONOS_HEALTH_TOTAL          => $sonos_health->{players_total},

        # Template flag: 1 = error state
        SONOS_HEALTH_IS_ERROR       => (
            defined $sonos_health->{status_class}
            && $sonos_health->{status_class} eq 'error'
        ) ? 1 : 0,
    );

    # ---------------------------------------------------------------------
    # Host player list for "follow" function
    # ---------------------------------------------------------------------
    my $rowshostplayer = '';
    my $counthost = 0;
    foreach my $key (keys %{$config}) {
        $counthost++;
        $rowshostplayer .= "<option value=" . $key . " >" . $key . "</option>";
    }
    $template->param("ROWSHOSTPLAYER", $rowshostplayer);

    LOGOK "Sonos Plugin UI has been successfully loaded.";

    # Donation checkbox
    if (is_enabled($cfg->{VARIOUS}->{donate})) {
        $template->param("DONATE", 'checked="checked"');
    } else {
        $template->param("DONATE", '');
    }

    printtemplate();
    exit;
}

#####################################################
# Save details (options page)
#####################################################

sub save_details
{
    my $countradios = param('countradios');
    my $zapname = "/run/shm/s4lox_zap_zone.json";

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
    if ($R::follow_host ne "") {
        $cfg->{VARIOUS}->{follow_host} = "$R::follow_host";
    }
    if ($R::follow_wait ne "") {
        $cfg->{VARIOUS}->{follow_wait} = "$R::follow_wait";
    }
    $cfg->{VARIOUS}->{cron} = "$R::cron";
    $cfg->{VARIOUS}->{selfunction} = "$R::func_list";
    $cfg->{SYSTEM}->{checkt2s} = "$R::checkt2s";
    $cfg->{SYSTEM}->{hw_update} = "$R::hw_update";
    $cfg->{SYSTEM}->{hw_update_day} = "$R::hw_update_day";
    $cfg->{SYSTEM}->{hw_update_time} = "$R::hw_update_time";
    $cfg->{SYSTEM}->{hw_update_power} = "$R::hw_update_power";

    $jsonobj->write();

    # Create / update cron symlink based on selection
    if ($R::cron eq "1") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (each minute) created";
    }
    if ($R::cron eq "3") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (every 3 minutes) created";
    }
    if ($R::cron eq "5") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (every 5 minutes) created";
    }
    if ($R::cron eq "10") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.10min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (every 10 minutes) created";
    }
    if ($R::cron eq "30") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.30min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        LOGOK "Cron job (every 30 minutes) created";
    }

    if (-e $zapname) {
        unlink($zapname);
        LOGDEB "ZAPZONE status file has been deleted";
    }

    LOGOK "Detail settings have been saved successfully";
    sleep(3);

    $navbar{2}{active} = 1;
    $template->param("DETAILS", "1");
    form();
}

#####################################################
# Save main settings
#####################################################

sub save
{
    # Everything from forms
    my $countplayers    = param('countplayers');
    my $countsoundbars  = param('countsoundbars');
    my $countradios     = param('countradios');
    my $LoxDaten        = param('sendlox');
    my $selminiserver   = param('ms');

    # Extract last character for compatibility with older versions
    my $sel_ms = substr($selminiserver, -1, 1);

    my $gcfg = Config::Simple->new("$lbsconfigdir/general.cfg");
    my $miniservers = $gcfg->param("BASE.MINISERVERS");
    my $MiniServer  = $gcfg->param("MINISERVER$selminiserver.IPADDRESS");
    my $MSWebPort   = $gcfg->param("MINISERVER$selminiserver.PORT");
    my $MSUser      = $gcfg->param("MINISERVER$selminiserver.ADMIN");
    my $MSPass      = $gcfg->param("MINISERVER$selminiserver.PASS");

    # Communication flag
    if ($LoxDaten eq "true") {
        LOGDEB "Communication to Miniserver is switched ON";
    } else {
        LOGDEB "Communication to Miniserver is switched OFF";
    }

    # Write configuration
    $cfg->{LOXONE}->{Loxone}      = "$sel_ms";
    $cfg->{LOXONE}->{LoxDaten}    = "$R::sendlox";
    $cfg->{LOXONE}->{LoxDatenMQTT}= "$R::sendloxMQTT";

    $cfg->{TTS}->{t2s_engine}     = "$R::t2s_engine";
    $cfg->{TTS}->{messageLang}    = "$R::t2slang";
    $cfg->{TTS}->{apikey}         = "$R::apikey";
    $cfg->{TTS}->{apikeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{apikey};
    $cfg->{TTS}->{secretkey}      = "$R::seckey";
    $cfg->{TTS}->{secretkeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{secretkey};
    $cfg->{TTS}->{voice}          = "$R::voice";
    $cfg->{TTS}->{regionms}       = $azureregion;
    $cfg->{TTS}->{t2son}          = "$R::t2son";

    $cfg->{MP3}->{MP3store}       = "$R::mp3store";
    $cfg->{MP3}->{cachesize}      = "$R::cachesize";
    $cfg->{MP3}->{file_gong}      = "$R::file_gong";

    $cfg->{VARIOUS}->{donate}     = "$R::donate";
    $cfg->{VARIOUS}->{CALDavMuell}= "$R::wastecal";
    $cfg->{VARIOUS}->{CALDav2}    = "$R::cal";
    $cfg->{VARIOUS}->{tvmon}      = "$R::tvmon";
    $cfg->{VARIOUS}->{starttime}  = "$R::starttime";
    $cfg->{VARIOUS}->{endtime}    = "$R::endtime";

    $cfg->{LOCATION}->{region}      = "$R::region";
    $cfg->{LOCATION}->{googlekey}   = "$R::googlekey";
    $cfg->{LOCATION}->{googletown}  = "$R::googletown";
    $cfg->{LOCATION}->{googlestreet}= "$R::googlestreet";
    $cfg->{LOCATION}->{town}        = "$R::town";

    $cfg->{SYSTEM}->{checkonline}   = "$R::checkonline";
    $cfg->{SYSTEM}->{mp3path}       = "$R::STORAGEPATH/$ttsfolder/$mp3folder";
    $cfg->{SYSTEM}->{ttspath}       = "$R::STORAGEPATH/$ttsfolder";
    $cfg->{SYSTEM}->{path}          = "$R::STORAGEPATH";
    $cfg->{SYSTEM}->{httpinterface} = "http://$host:$lbport/plugins/$lbpplugindir/interfacedownload";
    $cfg->{SYSTEM}->{smbinterface}  = "smb://$lbip:$lbport/plugindata/$lbpplugindir/interfacedownload";
    $cfg->{SYSTEM}->{cifsinterface} = "x-file-cifs://$lbip/plugindata/$lbpplugindir/interfacedownload";

    LOGINF "Start writing settings to configuration file";

    # If storage folders do not exist, copy default mp3 files
    my $copy_needed = 0;
    if (!-e "$R::STORAGEPATH/$ttsfolder/$mp3folder") {
        $copy_needed = 1;
    }

    LOGINF "Creating folders and symlinks";
    system("mkdir -p $R::STORAGEPATH/$ttsfolder/$mp3folder");
    system("mkdir -p $R::STORAGEPATH/$ttsfolder");
    system("rm $lbpdatadir/interfacedownload");
    system("rm $lbphtmldir/interfacedownload");
    system("ln -s $R::STORAGEPATH/$ttsfolder $lbpdatadir/interfacedownload");
    system("ln -s $R::STORAGEPATH/$ttsfolder $lbphtmldir/interfacedownload");
    LOGOK "All folders and symlinks created successfully.";

    if ($copy_needed) {
        LOGINF "Copying existing mp3 files from $lbpdatadir/$ttsfolder/$mp3folder to $R::STORAGEPATH/$ttsfolder/$mp3folder";
        system("cp -r $lbpdatadir/$ttsfolder/$mp3folder/* $R::STORAGEPATH/$ttsfolder/$mp3folder");
    }

    # Save radio stations
    for (my $i = 1; $i <= $countradios; $i++) {
        if (param("chkradios$i")) {
            delete $cfg->{RADIO}->{radio}->{$i};
        } else {
            my $rname = param("radioname$i");
            my $rurl  = param("radiourl$i");
            my $curl  = param("coverurl$i");
            $rname =~ s/^\s+|\s+$//g;
            $rurl  =~ s/^\s+|\s+$//g;
            $curl  =~ s/^\s+|\s+$//g;
            $cfg->{RADIO}->{radio}->{$i} = $rname . "," . $rurl . "," . $curl;
        }
    }
    LOGDEB "Radio Stations have been saved.";

    # Ensure at least 1 player exists (scan has been executed)
    if ($countplayers < 1) {
        $error_message = $SL{'ZONES.ERROR_NO_SCAN'};
        error();
    }

    # Save / delete Sonos devices
    my $del = "false";

    for (my $i = 1; $i <= $countplayers; $i++) {

        if (param("chkplayers$i")) {
            # Delete selected player from config
            my $room1 = param("zone$i");
            delete $cfg->{sonoszonen}->{$room1};
            LOGOK "Sonos Zone '$room1' has been deleted from main config";

            # Delete selected player from volume profiles
            if (-r $lbpconfigdir . "/" . $volumeconfigfile) {
                for (my $e = 1; $e <= $size; $e++) {
                    delete $vcfg->[$e - 1]->{Player}->{$room1};
                    $del = "true";
                    unlink($cache_file);
                }
                LOGOK "Sonos Zone '$room1' has been deleted from Volume Profiles";
            }

        } else {

            my $emergecalltts = (param("mainchk$i") eq "on") ? "on" : "off";

            my @player = (
                param("ip$i"),
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
                param("sb$i"),
            );

            if ($R::tvmon eq "true") {
                if (param("sb$i") eq "SB") {

                    # Add soundbar settings to zone
                    my $room = param("zone$i");

                    my $tvmonspeech = param("tvmonspeech_$room");
                    my $usesb       = param("usesb_$room");
                    my $tvvol       = param("tvvol_$room");
                    $tvvol = "" if $tvvol eq "false";

                    my $tvtreble    = param("tvtreble_$room");
                    $tvtreble = "" if $tvtreble eq "false";

                    my $tvbass      = param("tvbass_$room");
                    $tvbass = "" if $tvbass eq "false";

                    my $tvmonsurr   = param("tvmonsurr_$room");
                    my $fromtime    = param("fromtime_$room");
                    my $tvmonnight  = param("tvmonnight_$room");
                    my $tvmonnightsub = param("tvmonnightsub_$room");
                    my $tvsubnight  = param("tvmonnightsubn_$room");
                    my $tvmonnightsublevel = param("subgain_$room");

                    my $starttime = param("pl-start-time$i");
                    my $endtime   = param("pl-end-time$i");

                    my @sbs = (
                        {
                            "tvmonspeech"        => $tvmonspeech,
                            "usesb"              => $usesb,
                            "tvvol"              => $tvvol,
                            "tvtreble"           => $tvtreble,
                            "tvbass"             => $tvbass,
                            "tvmonsurr"          => $tvmonsurr,
                            "fromtime"           => $fromtime,
                            "tvmonnight"         => $tvmonnight,
                            "tvmonnightsub"      => $tvmonnightsub,
                            "tvsubnight"         => $tvsubnight,
                            "tvmonnightsublevel" => $tvmonnightsublevel
                        },
                        $starttime,
                        $endtime
                    );

                    push @player, @sbs;

                } else {
                    # No soundbar
                    my @sbs = ("false", param("pl-start-time$i"), param("pl-end-time$i"));
                    push @player, @sbs;
                }

            } else {
                # TV monitor turned off
                my @sbs = ("false", param("pl-start-time$i"), param("pl-end-time$i"));
                push @player, @sbs;
            }

            $cfg->{sonoszonen}->{param("zone$i")} = \@player;
        }
    }

    if (-r $lbpconfigdir . "/" . $volumeconfigfile && $del eq "true") {
        $jsonparser->write();
    }

    $jsonobj->write();
    LOGDEB "Sonos Zones have been saved.";

    # Control Sonos services (LoxDaten On/Off)
    services();

    # Prepare XML template during saving
    if ($R::sendlox eq "true") {
        prep_XML();
    }

    my $tv = qx(/usr/bin/php $lbphtmldir/bin/tv_monitor_conf.php);
    LOGOK "Main settings have been saved successfully";

    return;
}

#####################################################
# Scan Sonos players / zones
#####################################################

sub scan
{
    LOGINF "Auto-Discovery: Scan for Sonos Zones has been executed.";

    # Keep it fast via TTL cache in network.php
    my $ttl = 120;

    my $cmd = "/usr/bin/php $lbphtmldir/system/$scanzonesfile --ttl=$ttl";
    my $response = qx($cmd);
    $response =~ s/^\s+|\s+$//g;

    if ($response eq "") {
        $error_message = $SL{'ERRORS.ERR_SCAN'};
        error();
        return($countplayers);
    }

    # No new players
    if ($response =~ /^\[\s*\]$/ || $response =~ /^\{\s*\}$/) {
        LOGINF "Auto-Discovery: No new players found.";
        return($countplayers);
    }

    LOGOK "Auto-Discovery: JSON data received from network.php.";

    my $newzones;
    eval { $newzones = decode_json($response); 1; } or do {
        LOGERR "Auto-Discovery: Invalid JSON from network.php: $@";
        $error_message = $SL{'ERRORS.ERR_SCAN'};
        error();
        return($countplayers);
    };

    if (ref($newzones) ne 'HASH') {
        LOGERR "Auto-Discovery: Unexpected JSON structure (not a HASH).";
        $error_message = $SL{'ERRORS.ERR_SCAN'};
        error();
        return($countplayers);
    }

    # Ensure config structure exists
    $cfg->{sonoszonen} = {} if ref($cfg->{sonoszonen}) ne 'HASH';

    my $added = 0;

    foreach my $room (keys %{$newzones}) {

        # Never overwrite existing zones (prevents wiping volumes/limits)
        next if exists $cfg->{sonoszonen}->{$room};

        # Merge new zone into in-memory config (this makes it visible in UI)
        $cfg->{sonoszonen}->{$room} = $newzones->{$room};

        # Keep volume profile defaults in sync (if profiles exist)
        my $sur = $newzones->{$room}->[10];
        my $sub = $newzones->{$room}->[8];
        save_zone($room, $sur, $sub);

        $added++;
    }

    if ($added > 0) {
        $jsonobj->write();
        LOGOK "Auto-Discovery: $added new player(s) added to config.";
    } else {
        LOGINF "Auto-Discovery: Scan returned players, but all already exist in config.";
    }

    return($countplayers);
}

#####################################################
# Save scanned zone into Volume Profiles
# FIXED: use passed arguments instead of accidental globals
#####################################################

sub save_zone
{
    my ($room, $sur, $sub) = @_;

    return $vcfg if !-r ($lbpconfigdir . "/" . $volumeconfigfile);

    my ($surround, $subwoofer, $Subwoofer_level);

    if ($sur eq "NOSUR") {
        $surround = "na";
    } else {
        $surround = "true";
    }

    if ($sub eq "NOSUB") {
        $subwoofer       = "na";
        $Subwoofer_level = "";
    } else {
        $subwoofer       = "true";
        $Subwoofer_level = "";
    }

    for (my $e = 1; $e <= $size; $e++) {
        my @voldetails = ({
            "Bass"            => "",
            "Loudness"        => "true",
            "Master"          => "false",
            "Member"          => "false",
            "Subwoofer"       => $subwoofer,
            "Subwoofer_level" => $Subwoofer_level,
            "Surround"        => $surround,
            "Treble"          => "",
            "Volume"          => "",
        });

        $vcfg->[$e - 1]->{Player}->{$room} = \@voldetails;
    }

    $jsonparser->write();
    return $vcfg;
}

#####################################################
# Volume page renderer
#####################################################

sub volumes
{
    $template->param(FORMNO => 'VOLUME');

    # Ensure profiles file exists
    if (-e $lbpconfigdir . "/" . $volumeconfigfile) {
        LOGDEB("Volume Profiles config already exists");
    } else {
        my $volfile = qx(/usr/bin/php $lbphtmldir/bin/vol_prof_ini.php);
        LOGDEB("New Volume Profiles config has been created");
    }

    $rowsvolplayer = '';
    my $jsonobjvol = LoxBerry::JSON->new();
    my $vcfg_local = $jsonobj->open(filename => $lbpconfigdir . "/" . $volumeconfigfile);

    my $last_id = (keys @$vcfg_local);
    my $config  = $cfg->{sonoszonen};

    for (my $id = 1; $id <= $last_id; $id++) {
        $countplayers = 0;

        $rowsvolplayer .= "<table class='tables' style='width:100%' id='tblvol_prof$id' name='tblvol_prof$id' border='0'>\n";
        $rowsvolplayer .= "<th align='left' style='height: 25px; width:100px'>&nbsp;Profile #$id</th>\n";
        $rowsvolplayer .= "<th align='middle' colspan='8'><div style='width: 180px; align: left'>\n";
        $rowsvolplayer .= "<input class='textfield' type='text' style='align: middle; width: 100%' id='profile$id' name='profile$id' value='' placeholder='Volume Profile Name'/>\n";
        $rowsvolplayer .= "<td valign='left'>";
        $rowsvolplayer .= "<img title='Load current values from Sonos devices' value='$id' id='btnload$id' name='btnload$id' class='ico-load' src='/plugins/$lbpplugindir/images/musik-note.png' border='0' width='30' height='30'>\n";
        if ($last_id > 1) {
            $rowsvolplayer .= "<img title='Delete current Profile' onclick='' value='$id' id='btndel$id' name='btndel$id' class='ico-delete' src='/plugins/$lbpplugindir/images/recycle-bin.png' border='0' width='30' height='30'></td>\n";
        }
        $rowsvolplayer .= "</th><tr><th style='background-color: #6dac20;' align='left'>&nbsp;Rooms</th><div class='form-group col-7'>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>V</th>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>T</th>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>B</th>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>L</th>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>SR</th>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>SW</th>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>SWL</th>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>MA</th>\n";
        $rowsvolplayer .= "<th class='form-control' style='background-color: #6dac20; align: center'>ME</th>\n";
        $rowsvolplayer .= "</div></tr>";

        foreach my $key (sort keys %$config) {
            $countplayers++;
            my $zid = $countplayers . "_" . $id;

            my $error_volume      = $SL{'T2S.ERROR_VOLUME_PLAYER'};
            my $error_treble_bass = $SL{'VOLUME_PROFILES.ERROR_TREBLE_BASS_PLAYER'};
            my $error_sbass       = $SL{'VOLUME_PROFILES.ERROR_SUB_LEVEL_PLAYER'};

            $rowsvolplayer .= "<tr><div class='container'>";

            my $statusfile = $lbpdatadir . '/PlayerStatus/s4lox_on_' . $key . '.txt';
            if (-e $statusfile) {
                $rowsvolplayer .= "<td style='height: 15px; width: 160px;'><input type='text' id='zone_$zid' name='zone_$zid' readonly='true' value='$key' style='width: 100%; background-color:#6dac20; color:white'></td>\n";
            } else {
                $rowsvolplayer .= "<td style='height: 15px; width: 160px;'><input type='text' id='zone_$zid' name='zone_$zid' readonly='true' value='$key' style='width: 100%; background-color: #e6e6e6;'></td>\n";
            }

            $rowsvolplayer .= "<td style='width: 45px; height: 15px;'><input type='text' class='form-validation' id='vol_$zid' name='vol_$zid' size='100' data-validation-rule='special:number-min-max-value:0:100' data-validation-error-msg='$error_volume' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Volume}'></td>\n";
            $rowsvolplayer .= "<td style='width: 45px; height: 15px;'><input type='text' class='form-validation' id='treble_$zid' name='treble_$zid' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='$error_treble_bass' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Treble}'></td>\n";
            $rowsvolplayer .= "<td style='width: 45px; height: 15px;'><input type='text' class='form-validation' id='bass_$zid' name='bass_$zid' size='100' data-validation-rule='special:number-min-max-value:-10:10' data-validation-error-msg='$error_treble_bass' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Bass}'></td>\n";

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

            $rowsvolplayer .= "<td style='width: 55px; height: 15px'><input type='text' class='form-validation' id='sbass_$zid' name='sbass_$zid' size='100' data-validation-rule='special:number-min-max-value:-15:15' data-validation-error-msg='$error_sbass' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Subwoofer_level}'></td>\n";
            $rowsvolplayer .= "<td style='width: 60px; height: 15px'><div class='$id' id='$id'><input type='checkbox' id='master_$zid' name='master_$zid' class='$id' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Master}'></div></td>\n";
            $rowsvolplayer .= "<td style='width: 60px; height: 15px'><input type='checkbox' id='member_$zid' name='member_$zid' class='member_$id' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Member}'></td>\n";
            $rowsvolplayer .= "</div></tr>";
        }

        $rowsvolplayer .= "<br>";
        $rowsvolplayer .= "</table>";
    }

    $rowsvolplayer .= "<input type='hidden' id='last_id' name='last_id' value='$last_id'>\n";
    $rowsvolplayer .= "<input type='hidden' id='new_id' name='new_id' value='$last_id'>\n";
    $rowsvolplayer .= "<input type='hidden' id='delprofil' name='delprofil' value=0>\n";
    $rowsvolplayer .= "<input type='hidden' id='countplayers' name='countplayers' value='$countplayers'>\n";
    $template->param("ROWSVOLPLAYER", $rowsvolplayer);

    LOGOK "Sound Profiles have been loaded successfully.";
}

#####################################################
# Save Volume Profiles
#####################################################

sub save_volume
{
    my $countplayers = param('countplayers');
    my $last_id      = param('last_id');
    my $new_id       = param('new_id');

    my $surround;
    my $subwoofer;
    my $Subwoofer_level;
    my $loudness;

    my $jsonobjvol = LoxBerry::JSON->new();
    my $vcfg_local = $jsonobjvol->open(filename => $lbpconfigdir . "/" . $volumeconfigfile);

    # Delete selected profile
    if (param("delprofil") > 0) {
        my $c = param("delprofil") - 1;
        splice @{ $vcfg_local }, $c, 1;
        $jsonobjvol->write();
        LOGOK "Sound Profile has been deleted successfully.";
        volumes();
        $navbar{3}{active} = 1;
        $template->param("VOLUME", "1");
        form();
    }

    my ($master, $member, $hasMaster, $hasMember, $isGroup);

    for (my $i = 1; $i <= $new_id; $i++) {

        $vcfg_local->[$i - 1]->{Name} = lc(param("profile$i"));
        $hasMaster = "false";
        $hasMember = "false";

        for (my $k = 1; $k <= $countplayers; $k++) {
            my $zid  = $k . "_" . $i;
            my $zone = param("zone_$zid");

            # Surround
            if ($cfg->{sonoszonen}->{$zone}->[10] eq "NOSUR") {
                $surround = "na";
            } else {
                $surround = is_enabled(param("surround_$zid")) ? "true" : "false";
            }

            # Subwoofer
            if ($cfg->{sonoszonen}->{$zone}->[8] eq "NOSUB") {
                $subwoofer       = "na";
                $Subwoofer_level = "";
            } else {
                $Subwoofer_level = param("sbass_$zid");
                $subwoofer = is_enabled(param("subwoofer_$zid")) ? "true" : "false";
            }

            # Loudness
            $loudness = is_enabled(param("loudness_$zid")) ? "true" : "false";

            my $Volume  = param("vol_$zid");
            my $Treble  = param("treble_$zid");
            my $Bass    = param("bass_$zid");

            if (param("master_$zid") eq "true") {
                $master    = "true";
                $hasMaster = "true";
            } else {
                $master = "false";
            }

            if (param("member_$zid") eq "true") {
                $member    = "true";
                $hasMember = "true";
            } else {
                $member = "false";
            }

            my @profiles = ({
                "Volume"          => $Volume,
                "Treble"          => $Treble,
                "Bass"            => $Bass,
                "Loudness"        => $loudness,
                "Surround"        => $surround,
                "Subwoofer"       => $subwoofer,
                "Subwoofer_level" => $Subwoofer_level,
                "Master"          => $master,
                "Member"          => $member
            });

            $vcfg_local->[$i - 1]->{Player}->{$zone} = \@profiles;
        }

        # Determine grouping status for the profile
        if ($hasMaster eq "true" and $hasMember eq "true") {
            $isGroup = "Group";
        } elsif ($hasMaster eq "true" and $hasMember eq "false") {
            $isGroup = "Single";
        } elsif ($hasMaster eq "false" and $hasMember eq "true") {
            $isGroup = "Error";   # members without master -> invalid
        } else {
            $isGroup = "NoGroup";
        }

        $vcfg_local->[$i - 1]->{Group} = $isGroup;
    }

    $jsonobjvol->write();
    LOGOK "Sound Profile has been saved";
    volumes();
    $navbar{3}{active} = 1;
    $template->param("VOLUME", "1");
    form();
}

######################################################################
# Helpers for CalDAV validation
######################################################################

sub _mask_url
{
    my ($u) = @_;
    return '' unless defined $u;
    $u =~ s/(pass=)([^&]+)/$1***MASKED***/ig;
    $u =~ s{(https?://)([^:/\s]+):([^@/]+)\@}{$1$2:***MASKED***@}ig;
    return $u;
}

sub _validate_url
{
    my ($url, $mode) = @_;
    $mode ||= 'ics';

    return (0, "No URL entered") unless defined $url && $url ne '';

    # Resolve actual target for ICS: take calURL=... if present
    my $target_url = $url;
    if ($mode eq 'ics') {
        if ($url =~ /(?:^|[?&])calURL=([^&]+)/i) {
            my $enc = $1;
            my $dec = uri_unescape($enc);          # e.g. https%3A// -> https://
            $dec =~ s/^webcal:\/\//https:\/\//i;   # normalize webcal://
            $target_url = $dec if $dec =~ m{^https?://}i;
        }
    }

    # HTTP client
    my $ua = LWP::UserAgent->new(
        timeout      => 12,
        max_size     => 512 * 1024,   # up to ~512 KB
        max_redirect => 5,
        env_proxy    => 1,
        agent        => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
                      . "(KHTML, like Gecko) Chrome/124.0 Safari/537.36"
    );

    my $req = HTTP::Request->new(GET => $target_url);
    if ($mode eq 'ics') {
        $req->header('Accept' => 'text/calendar, text/plain, application/octet-stream');
    } else {
        $req->header('Accept' => 'application/json, text/plain');
    }
    $req->header('Connection' => 'close');

    my $res = $ua->request($req);
    return (0, sprintf("HTTP error %s at %s", $res->status_line, _mask_url($target_url)))
        unless $res->is_success;

    my $ct_header = $res->header('Content-Type') // '';
    my $ct        = lc $ct_header;

    my $bytes = $res->content // '';
    my $body  = $bytes;

    # Remove UTF-8 BOM
    $body =~ s/^\xEF\xBB\xBF//;

    # Detect UTF-16/32 BOMs and decode if needed
    if ($body =~ /^\xFF\xFE\x00\x00/) {              # UTF-32 LE
        $body = Encode::decode('UTF-32LE', $body);
    } elsif ($body =~ /^\x00\x00\xFE\xFF/) {         # UTF-32 BE
        $body = Encode::decode('UTF-32BE', $body);
    } elsif ($body =~ /^\xFF\xFE/) {                 # UTF-16 LE
        $body = Encode::decode('UTF-16LE', $body);
    } elsif ($body =~ /^\xFE\xFF/) {                 # UTF-16 BE
        $body = Encode::decode('UTF-16BE', $body);
    }

    # Trim leading whitespace/newlines that might precede VCALENDAR
    $body =~ s/^\s+//;

    if ($mode eq 'ics') {

        my $has_vcal = ($body =~ /BEGIN:VCALENDAR/i) ? 1 : 0;

        # If server clearly returned JSON
        if ($ct =~ /json/) {
            return (0, sprintf("Got JSON instead of ICS (Content-Type: %s)", $ct_header));
        }

        # If HTML and no VCALENDAR -> likely error/login page
        if ($ct =~ /html/ && !$has_vcal) {
            my $snip = substr($body, 0, 200); $snip =~ s/\s+/ /g;
            return (0, sprintf("Got HTML instead of ICS (Content-Type: %s): %s", $ct_header, $snip));
        }

        # Accept ICS if VCALENDAR marker is present even with wrong Content-Type
        unless ($has_vcal) {
            my $snip = substr($body, 0, 200); $snip =~ s/\s+/ /g;
            return (0, sprintf(
                "No iCalendar (BEGIN:VCALENDAR missing). Content-Type: %s. Snippet: %s",
                $ct_header, $snip
            ));
        }

        my $events = () = ($body =~ /BEGIN:VEVENT/ig);
        return (0, "No events found") if $events < 1;
        return (1, "OK", $events);
    }
    elsif ($mode eq 'json') {

        # Wrong type for JSON?
        if ($ct =~ /calendar|ics/) {
            return (0, sprintf("Got ICS instead of JSON (Content-Type: %s)", $ct_header));
        }

        my $data;
        eval { $data = decode_json($body) };
        if ($@ || !defined $data) {
            my $snip = substr($body, 0, 200); $snip =~ s/\s+/ /g;
            return (0, sprintf("Invalid JSON. Snippet: %s", $snip));
        }

        # Count appointment-like objects (ignore 'now')
        my $count = 0;
        if (ref $data eq 'HASH') {
            for my $k (keys %$data) {
                next if lc($k) eq 'now';
                $count++ if ref $data->{$k} eq 'HASH';
            }
        }
        return (0, "No appointments found in JSON") if $count < 1;

        return (1, "OK", $count);
    }

    return (0, "Unknown mode");
}

#####################################################
# Save config file (AJAX)
#####################################################

sub saveconfig
{
    my $config_file    = $lbpconfigdir . "/" . $configfile;
    my $volconfig_file = $lbpconfigdir . "/" . $volumeconfigfile;

    chmod 0700, $lbhomedir . "/webfrontend/html/XL";

    my $new_config_file    = $lbhomedir . "/webfrontend/html/XL/" . $configfile;
    my $new_volconfig_file = $lbhomedir . "/webfrontend/html/XL/" . $volumeconfigfile;

    copy $config_file,    $new_config_file;
    copy $volconfig_file, $new_volconfig_file;

    LOGOK "Plugin config has been saved";
    return "true";
}

#####################################################
# Restore config file (AJAX)
#####################################################

sub restoreconfig
{
    my $config_file    = $lbpconfigdir . "/" . $configfile;
    my $volconfig_file = $lbpconfigdir . "/" . $volumeconfigfile;

    my $new_config_file    = $lbhomedir . "/webfrontend/html/XL/" . $configfile;
    my $new_volconfig_file = $lbhomedir . "/webfrontend/html/XL/" . $volumeconfigfile;

    copy $new_config_file,    $config_file;
    copy $new_volconfig_file, $volconfig_file;

    unlink($new_config_file);
    unlink($new_volconfig_file);

    chmod 0500, $lbhomedir . "/webfrontend/html/XL";

    LOGOK "Plugin config has been restored";
    return "true";
}

#######################################################################
# Control Sonos systemd units (enable/disable + start/stop)
# based on Loxone data transfer toggle
#######################################################################

sub services
{
    my @enable_units = (
        'sonos_event_listener.service',
        'sonos_check_on_state.timer',
        'sonos_watchdog.timer',
    );

    sub sysd {
        my (@args) = @_;
        my $rc = system('sudo', '-n', '/bin/systemctl', @args);
        return ($rc >> 8);
    }

    if ($R::sendlox eq "true") {

        LOGINF "MQTT data for Loxone is enabled – enabling and starting systemd units";

        for my $u (@enable_units) {
            my $rc = sysd('enable', '--now', $u);
            if ($rc != 0) {
                LOGERR "Could not enable/start $u (rc=$rc)";
            } else {
                LOGOK "$u has been enabled and started";
            }
        }

    } else {

        LOGINF "MQTT data for Loxone is disabled – disabling and stopping systemd units";

        my @disable_units = (
            'sonos_watchdog.timer',
            'sonos_check_on_state.timer',
            'sonos_event_listener.service',
        );

        for my $u (@disable_units) {
            my $rc = sysd('disable', '--now', $u);
            if ($rc != 0) {
                LOGERR "Could not disable/stop $u (rc=$rc)";
            } else {
                LOGOK "$u has been disabled and stopped";
            }
        }
    }

    return;
}

####################################################################
# Prepare event listener health data to be displayed in UI
####################################################################

sub get_sonos_health
{
    my $healthfile = "/dev/shm/$lbpplugindir/health.json";

    my %fallback = (
        service        => 'sonos_event_listener',
        hostname       => 'unknown',
        pid            => undef,
        timestamp      => 0,
        iso_time       => '',
        formatted_time => '',
        players_online => 0,
        players_total  => 0,
        status         => 'unknown',
        status_class   => 'status-unknown',
        age_sec        => 0,
    );

    return \%fallback if !-e $healthfile;

    my $json_text;
    if (open(my $fh, '<', $healthfile)) {
        local $/ = undef;
        $json_text = <$fh>;
        close($fh);
    } else {
        return \%fallback;
    }

    my $decoded;
    eval {
        $decoded = decode_json($json_text);
        1;
    } or do {
        return \%fallback;
    };

    my $root = $decoded;
    return \%fallback if !$root || ref $root ne 'HASH';

    my $ts  = $root->{timestamp} // 0;
    my $now = time();
    my $age = $ts ? ($now - $ts) : 999999;

    my $formatted_time = $root->{ts_formatted} // '';

    my ($status, $status_class);
    if ($age <= 60) {
        $status       = $SL{'TEMPLATE.TXT_PLAYER_GREEN'};
        $status_class = 'ok';
    } elsif ($age <= 300) {
        $status       = $SL{'TEMPLATE.TXT_PLAYER_YELLOW'};
        $status_class = 'warn';
    } else {
        $status       = $SL{'TEMPLATE.TXT_PLAYER_RED'};
        $status_class = 'error';
    }

    my $players_online = $root->{online_players} // 0;
    my $players_total  = $root->{total_players}  // 0;

    my %out = (
        service        => $root->{source}      // 'sonos_event_listener',
        hostname       => $root->{hostname}    // 'unknown',
        pid            => $root->{pid},
        timestamp      => $ts,
        iso_time       => $root->{iso_time}    // '',
        formatted_time => $formatted_time,
        players_online => $players_online,
        players_total  => $players_total,
        status         => $status,
        status_class   => $status_class,
        age_sec        => int($age),
    );

    return \%out;
}

#####################################################
# Get engine keys (AJAX)
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
# Execute PHP script to generate XML template
#####################################################

sub prep_XML
{
    my $udp_temp = qx(/usr/bin/php $lbphtmldir/system/$udp_file);
    LOGOK "XML template file generation has been called";
    return();
}

#####################################################
# Error handler
#####################################################

sub error
{
    $template->param("SETTINGS", "1");
    $template->param('ERR_MESSAGE', $error_message);
    LOGERR($error_message);
    form();
}

##########################################################################
# Init template
##########################################################################

sub inittemplate
{
    # Check if main template is readable
    stat($lbptemplatedir . "/" . $maintemplatefilename);
    if (!-r _) {
        $error_message = "Error: Main template not readable";
        LOGCRIT "The $maintemplatefilename file could not be loaded. Aborting plugin loading.";
        LOGCRIT $error_message;
        error();
    }

    $template = HTML::Template->new(
        filename           => $lbptemplatedir . "/" . $maintemplatefilename,
        global_vars        => 1,
        loop_context_vars  => 1,
        die_on_bad_params  => 0,
        associate          => $jsonobj,
        %htmltemplate_options
    );

    %SL = LoxBerry::System::readlanguage($template, $languagefile);
}

##########################################################################
# Print template
##########################################################################

sub printtemplate
{
    LoxBerry::Web::lbheader("$SL{'BASIS.MAIN_TITLE'}: v$sversion", $helplink, $helptemplatefilename);
    print LoxBerry::Log::get_notifications_html($lbpplugindir);
    print $template->output();
    LoxBerry::Web::lbfooter();
    exit;
}

##########################################################################
# END routine - called on every exit (including exceptions)
##########################################################################

sub END
{
    our @reason;
    our $IS_AJAX_REQUEST;
    our $AJAX_ACTION;

    return if !$log;

    # For AJAX requests: log only errors/exceptions, suppress LOGEND success noise
    if ($IS_AJAX_REQUEST) {
        if (@reason) {
            LOGCRIT "Unhandled exception in AJAX request" . ($AJAX_ACTION ? " ($AJAX_ACTION)" : "") . ":";
            LOGERR @reason;
        } elsif ($error_message) {
            LOGERR "AJAX error" . ($AJAX_ACTION ? " ($AJAX_ACTION)" : "") . ": " . $error_message;
        }
        return;
    }

    # Normal page render: keep original behavior
    if (@reason) {
        LOGCRIT "Unhandled exception caught:";
        LOGERR @reason;
        LOGEND "Finished with an exception";
    } elsif ($error_message) {
        LOGEND "Finished with error: " . $error_message;
    } else {
        LOGEND "Finished successful";
    }
}
