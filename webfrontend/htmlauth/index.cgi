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
#
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
use Data::Dumper;
# Template system needs symbol-table access (legacy LoxBerry plugin templates)
use strict;
use warnings;
use utf8;
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
my $azureregion           = "westeurope";
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
# --- Manual Sonos IP / Unicast scan state ---
# The manual IP input field is shown only after a failed Auto Discovery scan.
# VLAN hint is used to render the manual IP fallback block.
our $vlan_hint        = 0;
our $vlan_hint_reason = '';
our $vlan_hint_ips    = [];

# Failed manual IP validation result for template output
our $vlan_unicast_failed      = 0;
our $vlan_unicast_failed_text = '';

# JS popup hint after an unsuccessful MULTICAST/BROADCAST scan
our $show_unicast_scan_hint = 0;

our $FORCE_UNICAST_SCAN = 0;

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
if (!defined $cfg->{"MP3"}->{cachesize}) {
    $cfg->{MP3}->{cachesize} = "100";
    $defaultSave = "true";
}
if ($cfg->{TTS}->{volrampto} eq '') {
    $cfg->{TTS}->{volrampto} = "25";
    $defaultSave = "true";
}
if ($cfg->{TTS}->{rampto} eq '') {
    $cfg->{TTS}->{rampto} = "auto";
    $defaultSave = "true";
}
if (!defined $cfg->{TTS}->{correction}) {
    $cfg->{TTS}->{correction} = "8";
    $defaultSave = "true";
}
if (!defined $cfg->{TTS}->{regionms}) {
    $cfg->{TTS}->{regionms} = $azureregion;
    $defaultSave = "true";
}
if (!defined $cfg->{TTS}->{phonemute}) {
    $cfg->{TTS}->{phonemute} = "8";
    $defaultSave = "true";
}
if (!defined $cfg->{TTS}->{waiting}) {
    $cfg->{TTS}->{waiting} = "10";
    $defaultSave = "true";
}
if (!defined $cfg->{VARIOUS}->{phonestop}) {
    $cfg->{VARIOUS}->{phonestop} = "0";
    $defaultSave = "true";
}
if (!defined $cfg->{VARIOUS}->{cron}) {
    $cfg->{VARIOUS}->{cron} = "1";
    $defaultSave = "true";
}
if (!defined $cfg->{SYSTEM}->{checkonline} || $cfg->{SYSTEM}->{checkonline} ne "true") {
	$cfg->{SYSTEM}->{checkonline} = "true";
	$defaultSave = "true";
}
if (!defined $cfg->{VARIOUS}->{volmax}) {
    $cfg->{VARIOUS}->{volmax} = "0";
    $defaultSave = "true";
}
if (!defined $cfg->{TTS}->{t2son}) {
    $cfg->{TTS}->{t2son} = "true";
    $defaultSave = "true";
}
if (!defined $cfg->{TTS}->{t2s_engine}) {
    $cfg->{TTS}->{t2s_engine} = "9012";
	$cfg->{TTS}->{voice} = "thorsten";
	$cfg->{TTS}->{messageLang} = "de_DE";
    $defaultSave = "true";
}
if (!defined $cfg->{VARIOUS}->{starttime}) {
    $cfg->{VARIOUS}->{starttime} = "10";
    $defaultSave = "true";
}
if (!defined $cfg->{VARIOUS}->{endtime}) {
    $cfg->{VARIOUS}->{endtime} = "22";
    $defaultSave = "true";
}
if (defined $cfg->{TTS}->{'API-key'}) {
    $cfg->{TTS}->{apikey} = $cfg->{TTS}->{'API-key'};
    delete $cfg->{TTS}->{'API-key'};
}
if (!defined $cfg->{TTS}->{apikeys}) {
    $cfg->{TTS}->{apikeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{apikey};
}
if (defined $cfg->{TTS}->{'secret-key'}) {
    $cfg->{TTS}->{secretkey} = $cfg->{TTS}->{'secret-key'};
    delete $cfg->{TTS}->{'secret-key'};
}
if (!defined $cfg->{TTS}->{secretkeys}) {
    $cfg->{TTS}->{secretkeys}->{$cfg->{TTS}->{t2s_engine}} = $cfg->{TTS}->{secretkey};
}
if (!defined $cfg->{VARIOUS}->{follow_host}) {
    $cfg->{VARIOUS}->{follow_host} = "false";
    $defaultSave = "true";
}
if (!defined $cfg->{VARIOUS}->{follow_wait}) {
    $cfg->{VARIOUS}->{follow_wait} = "false";
    $defaultSave = "true";
}
if (!defined $cfg->{TTS}->{presence}) {
    $cfg->{TTS}->{presence} = "true";
    $defaultSave = "true";
}
if (!defined $cfg->{TTS}->{hostip}) {
    $cfg->{TTS}->{hostip} = "host";
    $defaultSave = "true";
}
if ($cfg->{SYSTEM}->{checkt2s} eq "false") {
	$cfg->{SYSTEM}->{checkt2s} = "true";
	$defaultSave = "true";
}
if (exists $cfg->{LOXONE}->{LoxDatenMQTT}) {
    delete $cfg->{LOXONE}->{LoxDatenMQTT};
    $defaultSave = "true";
}
if (is_enabled($defaultSave)) {
    $jsonobj->write();
}
##########################################################################
# Read settings / environment
##########################################################################
my $lblang = lblanguage();
%SL = LoxBerry::System::readlanguage($template, $languagefile);
my $sversion  = LoxBerry::System::pluginversion();
my $lbversion = LoxBerry::System::lbversion();
$lbv = substr($lbversion, 0, 1);
$cgi = CGI->new;
$cgi->import_names('R');
$mqttcred = LoxBerry::IO::mqtt_connectiondetails();
our $htmlhead = '<link rel="stylesheet" href="/plugins/'.$lbpplugindir.'/web/sonos.css"/>';
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
## Early exit for AJAX actions (no template, no LOGSTART/LOGEND wrapper)
if ($q->{action} && $q->{action} ne 'save_vlan_ip') {   # <-- nur hier ne 'save_vlan_ip' ergänzen
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
    if ($AJAX_ACTION eq "get_sonos_health_json") {
        my $health = get_sonos_health();
        print JSON::encode_json({
            timestamp      => 0 + ($health->{timestamp}       // 0),
            status_class   =>      ($health->{status_class}   // 'error'),
            status         =>      ($health->{status}         // ''),
            formatted_time =>      ($health->{formatted_time} // ''),
            age_sec        => 0 + ($health->{age_sec}         // 0),
            players_online => 0 + ($health->{players_online}  // 0),
            players_total  => 0 + ($health->{players_total}   // 0),
        });
        exit;
    }
    if ($AJAX_ACTION eq "restart_listener") {
		my $healthfile = "/dev/shm/$lbpplugindir/health.json";
		unlink($healthfile) if -e $healthfile;
		my $rc = system('sudo', '-n', '/bin/systemctl', 'restart', 'sonos_event_listener.service');
		$rc = ($rc >> 8);
		if ($rc != 0) {
			$error_message = "AJAX restart_listener failed (rc=$rc)";
			print JSON::encode_json({ success => JSON::false, error => $error_message });
			exit;
		}
		print JSON::encode_json({ success => JSON::true });
		exit;
	}
	if ($AJAX_ACTION eq "getsonosversions") {
		my $plugindb_file     = "REPLACELBHOMEDIR/data/system/plugindatabase.json";
		my $release_cfg_url   = "https://raw.githubusercontent.com/Liver64/LoxBerry-Sonos/master/webfrontend/html/release/release.cfg";
		my $releases_atom_url = "https://github.com/Liver64/LoxBerry-Sonos/releases.atom";
		my $installed     = '';
		my $latest_stable = '';
		$error_message 	  = '';
		my $error 		  = '';
		my $db_raw = LoxBerry::System::read_file($plugindb_file) // '';
		if ($db_raw) {
			my $db = eval { decode_json($db_raw) };
			if (ref($db) eq 'HASH' && ref($db->{plugins}) eq 'HASH') {
				foreach my $plugin_id (keys %{ $db->{plugins} }) {
					my $plugin = $db->{plugins}{$plugin_id};
					next unless ref($plugin) eq 'HASH';
					my $folder = $plugin->{folder} // '';
					my $name   = $plugin->{name}   // '';
					if ($folder eq 'sonos4lox' || lc($name) eq 'sonos') {
						$installed = _normalize_version($plugin->{version} // '');
						last;
					}
				}
			}
		}
		my $cfg_cmd = qq{curl -k -sfL --max-time 15 "$release_cfg_url" 2>/dev/null};
		my $cfg_raw = `$cfg_cmd`;
		my $cfg_rc  = $? >> 8;
		if ($cfg_raw) {
			my ($cfg_ver) = $cfg_raw =~ /^VERSION=(.+)$/m;
			$latest_stable = _normalize_version($cfg_ver // '');
		} else {
			LOGWARN "Could not fetch release.cfg from $release_cfg_url (rc=$cfg_rc)";
		}
		my $atom_cmd = qq{curl -k -sfL --max-time 20 "$releases_atom_url" 2>/dev/null};
		my $atom_raw = `$atom_cmd`;
		my $atom_rc  = $? >> 8;
		my @releases;
		my %seen;
		if ($atom_raw) {
			while (
				$atom_raw =~ m{
					<entry\b[^>]*>.*?
					<title[^>]*>(.*?)</title>.*?
					<link[^>]+href="([^"]+/releases/tag/([^"/]+))"[^>]*/?>.*?
					</entry>
				}sgx
			) {
				my $title    = $1 // '';
				my $html_url = $2 // '';
				my $raw_tag  = $3 // '';
				my $version = _normalize_version($raw_tag);
				next unless $version;
				next if $seen{$version}++;
				$title =~ s/&amp;/&/g;
				$title =~ s/&lt;/</g;
				$title =~ s/&gt;/>/g;
				$title =~ s/&quot;/"/g;
				$title =~ s/&#39;/'/g;
				push @releases, {
					version      => $version,
					raw_tag      => $raw_tag,
					name         => $title,
					html_url     => $html_url,
					published_at => '',
					prerelease   => 0,
				};
			}
		} else {
			LOGWARN "Could not fetch Sonos releases atom feed from $releases_atom_url (rc=$atom_rc)";
		}
		if ($atom_raw && !@releases) {
			LOGWARN "Sonos releases atom feed was fetched, but no release entries could be parsed";
		}
		if ($latest_stable && !$seen{$latest_stable}) {
			$seen{$latest_stable} = 1;
			push @releases, {
				version      => $latest_stable,
				raw_tag      => "v$latest_stable",
				name         => $latest_stable,
				html_url     => "https://github.com/Liver64/LoxBerry-Sonos/releases/tag/v$latest_stable",
				published_at => '',
				prerelease   => 0,
			};
			#LOGINF "Latest stable version $latest_stable was added from release.cfg because it was missing in releases.atom";
		}
		if ($installed && !$seen{$installed}) {
			$seen{$installed} = 1;
			push @releases, {
				version      => $installed,
				raw_tag      => "v$installed",
				name         => $installed,
				html_url     => '',
				published_at => '',
				prerelease   => 0,
			};
		}
		if (!@releases) {
			$error = "No release information available";
		}
		@releases = sort { _sonos_version_cmp($a->{version}, $b->{version}) } @releases;
		foreach my $rel (@releases) {
			my $rel_version = _normalize_version($rel->{version});
			$rel->{selected} = (
				$installed
				&& $rel_version eq _normalize_version($installed)
			) ? 1 : 0;
			$rel->{is_newer} = _sonos_version_is_newer($rel_version, $installed) ? 1 : 0;
		}
		print JSON::encode_json({
			installed     => $installed,
			latest_stable => $latest_stable,
			releases      => \@releases,
			error         => $error,
		});
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
$template->param("PLUGINDIR" => $lbpplugindir);
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
if ($mqttcred and $cfg->{LOXONE}->{LoxDaten} eq "true")  {
    $navbar{4}{Name} = "$SL{'BASIS.MENU_MQTT'}";
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
# VLAN IP form submit: speichert eingegebene IPs und löst Re-Scan aus
if (
    (defined $R::save_vlan_ip && $R::save_vlan_ip)
    || (defined $q->{save_vlan_ip} && $q->{save_vlan_ip})
    || (defined $q->{action} && $q->{action} eq 'save_vlan_ip')
) {
    save_vlan_ip_handler();
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
if (-r "$lbhomedir/webfrontend/html/XL/$configfile" && -r "$lbpconfigdir/$configfile") {
    if (compare("$lbpconfigdir/$configfile", "$lbhomedir/webfrontend/html/XL/$configfile") == 1) {
        $template->param("RESTORE_POSSIBLE", 1);
    }
}
elsif (-r "$lbhomedir/webfrontend/html/XL/$configfile") {
    $template->param("RESTORE_POSSIBLE", 1);
}
if (-r "$lbhomedir/webfrontend/html/XL/$volumeconfigfile" && -r "$lbpconfigdir/$volumeconfigfile") {
    if (compare("$lbpconfigdir/$volumeconfigfile", "$lbhomedir/webfrontend/html/XL/$volumeconfigfile") == 1) {
        $template->param("RESTORE_POSSIBLE", 1);
    }
}
elsif (-r "$lbhomedir/webfrontend/html/XL/$volumeconfigfile") {
    $template->param("RESTORE_POSSIBLE", 1);
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

    # Reset manual/unicast fallback state for this explicit scan request
    $vlan_hint                = 0;
    $vlan_hint_reason         = '';
    $vlan_hint_ips            = [];
    $vlan_unicast_failed      = 0;
    $vlan_unicast_failed_text = '';
    $show_unicast_scan_hint   = 0;
    $FORCE_UNICAST_SCAN       = 0;

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
    if ($cfg->{SYSTEM}->{path} eq "") {
        $cfg->{SYSTEM}->{path} = "$lbpdatadir";
        LOGINF("Default path has been added to config");
    }
	my $storage = LoxBerry::Storage::get_storage_html(
					formid => 'STORAGEPATH',
					currentpath => $jsonobj->param("SYSTEM.path"),
					custom_folder => 1,
					type_all => 1,
					readwriteonly => 1,
					data_mini => 1,
					label => "$SL{'T2S.LABEL_SOUNDCARD'}");
	$template->param("STORAGEPATH", $storage);
    $template->param("SELFURL", $SL{REQUEST_URI});
    $template->param("T2S_ENGINE" => $cfg->{TTS}->{t2s_engine});
    $template->param("APIKEY"     => $cfg->{TTS}->{apikeys}->{$cfg->{TTS}->{t2s_engine}});
    $template->param("SECKEY"     => $cfg->{TTS}->{secretkeys}->{$cfg->{TTS}->{t2s_engine}});
    $template->param("VOICE"      => $cfg->{TTS}->{voice});
    $template->param("CODE"       => $cfg->{TTS}->{messageLang});
    $template->param("DATADIR"    => $cfg->{SYSTEM}->{path});
    $template->param("LOX_ON"     => $cfg->{LOXONE}->{LoxDaten});
    my $testvoice = $cfg->{TTS}->{voice};
    my $testlang  = $cfg->{TTS}->{messageLang};
    if ($testvoice ne "" || $testlang ne "") {
        $template->param("TESTVOICE", 1);
    }
    if (ref($cfg->{RADIO}) ne 'HASH') {
        $cfg->{RADIO} = {};
    }
    if (ref($cfg->{RADIO}->{radio}) ne 'HASH') {
        $cfg->{RADIO}->{radio} = {};
    }
    our $countradios = 0;
    our $rowsradios  = '';
    my $radiofavorites = $cfg->{RADIO}->{radio};
    foreach my $key (keys %{$radiofavorites}) {
        $countradios++;
        my @fields = split(/,/, $cfg->{RADIO}->{radio}->{$countradios});
        $rowsradios .= "<tr>";
		$rowsradios .= "<td style='height: 25px; width: 43px; text-align:center;'>"
		  . "<input type='checkbox' name='chkradios$countradios' id='chkradios$countradios' style='display:none' />"
		  . "<a href='#' class='jsDelRadio' data-idx='$countradios' data-name='$fields[0]' title='Delete'>"
		  . "<img class='ico_delete' src='/plugins/$lbpplugindir/images/recycle-bin.png' border='0' width='24' height='24'>"
		  . "</a>"
		  . "</td>\n";
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

    # ---------------------------------------------------------------
    # Manual Unicast fallback block
    # Important:
    # Do NOT call scan() here.
    # Auto Discovery must only run after clicking "Scan Player"
    # via do=scanning, or after submitting manual IPs.
    # ---------------------------------------------------------------

    # Build failed IP text from:
    # 1) failed manual validation in save_vlan_ip_handler()
    # 2) optional unicast_failed hint returned by network.php
    my $failed_ip_text = $vlan_unicast_failed_text // '';

    if ($vlan_hint_reason eq 'unicast_failed') {
        my @scan_failed_ips = ();

        if (ref($vlan_hint_ips) eq 'ARRAY') {
            @scan_failed_ips = @{$vlan_hint_ips};
        } elsif (defined $vlan_hint_ips && $vlan_hint_ips ne '') {
            @scan_failed_ips = ($vlan_hint_ips);
        }

        if (@scan_failed_ips) {
            my $scan_failed_text = join(', ', @scan_failed_ips);
            $failed_ip_text = ($failed_ip_text ne '')
                ? $failed_ip_text . ', ' . $scan_failed_text
                : $scan_failed_text;
        }
    }

    $template->param(
		VLAN_HINT                => $vlan_hint ? 1 : 0,
		VLAN_HINT_UNICAST_FAILED => ($failed_ip_text ne '') ? 1 : 0,
		VLAN_UNICAST_FAILED      => $failed_ip_text,
		SHOW_UNICAST_SCAN_HINT   => $show_unicast_scan_hint ? 1 : 0,
	);
	
    # ---------------------------------------------------------------------
    # Load Sonos players / zones
    # ---------------------------------------------------------------------
    $rowssonosplayer = '';
    our $rowssoundbar = '';
    our $currtime;
    my $error_volume      = $SL{'T2S.ERROR_VOLUME_PLAYER'};
    my $config = $cfg->{sonoszonen};
    my $audioclip_ok_count = 0;
    $currtime = strftime("%H:%M", localtime());
    $countplayers   = 0;
    $countsoundbars = 0;
	my @all_rooms = sort keys %$config;
	my $has_any_soundbar = 0;
	my $tvmon_master_style;
	foreach my $cfg_key (keys %{$config}) {
		next unless ref $config->{$cfg_key} eq 'ARRAY';
		next unless defined $config->{$cfg_key}->[13];
		if ($config->{$cfg_key}->[13] eq 'SB') {
			$has_any_soundbar = 1;
			last;
		}
	}
	foreach my $key (sort keys %{$config}) {
		my $zone = $config->{$key};
		next unless ref($zone) eq 'ARRAY';
		$countplayers++;
		my $room       = $key;
		my $filename   = $lbphtmldir . '/images/icon-' . ($zone->[7] // '') . '.png';
		my $statusfile = $lbpdatadir . '/PlayerStatus/s4lox_on_' . $room . '.txt';
		$rowssonosplayer  .= "<tr>";
		$rowssonosplayer  .= "<td style='height: 25px; width: 20px; text-align:center;'>"
						  . "<input type='checkbox' name='chkplayers$countplayers' id='chkplayers$countplayers' style='display:none' />"
						  . "<a href='#' class='jsDelZone' data-idx='$countplayers' data-room='$room' title='Delete'>"
						  . "<img class='ico_delete' src='/plugins/$lbpplugindir/images/recycle-bin.png' border='0' width='20' height='20'>"
						  . "</a>"
						  . "</td>\n";
		if (-e $statusfile) {
			$rowssonosplayer .= "<td style='height: 28px; width: 16%;'><input type='text' class='pd-price' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$room' style='width:100%; background-color:#6dac20; color:white'></td>\n";
		} else {
			$rowssonosplayer .= "<td style='height: 28px; width: 16%;'><input type='text' id='zone$countplayers' name='zone$countplayers' size='40' readonly='true' value='$room' style='width: 100%; background-color: #e6e6e6;'></td>\n";
		}
		$rowssonosplayer .= "<td style='height: 25px; width: 6px;'><input type='checkbox' class='chk-checked' name='mainchk$countplayers' id='mainchk$countplayers' value='" . ($zone->[6] // '') . "' align='center'></td>\n";
		if (($zone->[9] // '') eq "1") {
			$rowssonosplayer .= "<td style='height: 28px; width: 15%;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='" . ($zone->[2] // '') . "' style='width: 100%; background-color: red; color:white'></td>\n";
			$template->param("SWGEN", "1");
		} else {
			$rowssonosplayer .= "<td style='height: 28px; width: 15%;'><input type='text' id='model$countplayers' name='model$countplayers' size='30' readonly='true' value='" . ($zone->[2] // '') . "' style='width: 100%; background-color: #e6e6e6;'></td>\n";
		}
		if (-e $filename) {
			$rowssonosplayer .= "<td style='height: 28px; width: 2%;'><img src='/plugins/$lbpplugindir/images/icon-" . ($zone->[7] // '') . ".png' border='0' width='50' height='50' align='middle'/></td>\n";
		} else {
			$rowssonosplayer .= "<td style='height: 28px; width: 2%;'><img src='/plugins/$lbpplugindir/images/sonos_logo_sm.png' border='0' width='50' height='50' align='middle'/></td>\n";
		}
		$rowssonosplayer .= "<td style='height: 28px; width: 17%;'><input type='text' id='ip$countplayers' name='ip$countplayers' size='30' value='" . ($zone->[0] // '') . "' style='width: 100%; background-color: #e6e6e6;'></td>\n";
		my $audioclip_ok = ($zone->[11]) ? 1 : 0;
		if ($audioclip_ok) {
			$rowssonosplayer .= "<td style='height: 30px; width: 10px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/green.png' border='0' width='26' height='28' align='center'/></div></td>\n";
			$audioclip_ok_count++;
		} else {
			$rowssonosplayer .= "<td style='height: 30px; width: 10px; align: 'middle'><div style='text-align: center;'><img src='/plugins/$lbpplugindir/images/red.png' border='0' width='26' height='28' align='center'/></div></td>\n";
		}
		$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='t2svol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='t2svol$countplayers' value='" . ($zone->[3] // '') . "'></td>\n";
		$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='sonosvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='sonosvol$countplayers' value='" . ($zone->[4] // '') . "'></td>\n";
		$rowssonosplayer .= "<td style='width: 10%; height: 28px;'><input type='text' id='maxvol$countplayers' size='100' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' name='maxvol$countplayers' value='" . ($zone->[5] // '') . "'></td>\n";
		if (($zone->[13] // '') eq "SB") {
			$countsoundbars++;
			$rowssonosplayer .= "<input type='hidden' id='sb$countplayers' name='sb$countplayers' value='" . ($zone->[13] // '') . "'>\n";
		} else {
			$rowssonosplayer .= "<input type='hidden' id='sb$countplayers' name='sb$countplayers' value='NOSB'>\n";
		}
		if (($zone->[15] // '') ne "" || ($zone->[16] // '') ne "") {
			if ($currtime ge ($zone->[15] // '') && $currtime lt ($zone->[16] // '')) {
				$rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-start-time$countplayers' type='time' name='pl-start-time$countplayers' value='" . ($zone->[15] // '') . "'></td>\n";
				$rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-end-time$countplayers' type='time' name='pl-end-time$countplayers' value='" . ($zone->[16] // '') . "'></td></tr>\n";
			} else {
				$rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-start-time$countplayers' type='time' name='pl-start-time$countplayers' value='" . ($zone->[15] // '') . "' style='width:100%; background-color:orange; color:black'></td>\n";
				$rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-end-time$countplayers' type='time' name='pl-end-time$countplayers' value='" . ($zone->[16] // '') . "'style='width:100%; background-color:orange; color:black'></td></tr>\n";
			}
		} else {
			$rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-start-time$countplayers' type='time' name='pl-start-time$countplayers' value='" . ($zone->[15] // '') . "'></td>\n";
			$rowssonosplayer .= "<td style='width: 9%; height: 28px;'><input id='pl-end-time$countplayers' type='time' name='pl-end-time$countplayers' value='" . ($zone->[16] // '') . "'></td></tr>\n";
		}
		$rowssonosplayer .= "<input type='hidden' id='room$countplayers' name='room$countplayers' value='$room'>\n";
		$rowssonosplayer .= "<input type='hidden' id='models$countplayers' name='models$countplayers' value='" . ($zone->[7] // '') . "'>\n";
		$rowssonosplayer .= "<input type='hidden' id='sub$countplayers' name='sub$countplayers' value='" . ($zone->[8] // '') . "'>\n";
		$rowssonosplayer .= "<input type='hidden' id='householdId$countplayers' name='householdId$countplayers' value='" . ($zone->[9] // '') . "'>\n";
		$rowssonosplayer .= "<input type='hidden' id='sur$countplayers' name='sur$countplayers' value='" . ($zone->[10] // '') . "'>\n";
		$rowssonosplayer .= "<input type='hidden' id='audioclip$countplayers' name='audioclip$countplayers' value='" . ($zone->[11] // '') . "'>\n";
		$rowssonosplayer .= "<input type='hidden' id='voice$countplayers' name='voice$countplayers' value='" . ($zone->[12] // '') . "'>\n";
		$rowssonosplayer .= "<input type='hidden' id='rincon$countplayers' name='rincon$countplayers' data-validation-rule='special:number-min-max-value:1:100' data-validation-error-msg='$error_volume' value='" . ($zone->[1] // '') . "'>\n";
		if (($zone->[13] // '') eq "SB") {
			my $sbcfg = (ref($zone->[14]) eq 'HASH') ? $zone->[14] : {};
			my $tvmonnightsubn_val      = $sbcfg->{tvsubnight}         // 'false';
			my $tvsublevel_val          = $sbcfg->{tvsublevel}         // 0;
			my $tvsurrlevel_val         = $sbcfg->{tvsurrlevel}        // 0;
			my $tvmonnightsublevel_val  = $sbcfg->{tvmonnightsublevel} // 0;
			my $fromtime_val            = $sbcfg->{fromtime}           // '';
			my $has_valid_fromtime      = ($fromtime_val =~ /^(?:[01]\d|2[0-3]):[0-5]\d$/) ? 1 : 0;
			my $night_style             = $has_valid_fromtime ? "" : "display:none;";
			my $usesb_val               = $sbcfg->{usesb}              // 'false';
			my $tvmonspeech_val         = $sbcfg->{tvmonspeech}        // 'false';
			my $tvmonsurr_val           = $sbcfg->{tvmonsurr}          // 'false';
			my $tvmonnightsub_val       = $sbcfg->{tvmonnightsub}      // 'false';
			my $tvmonnight_val          = $sbcfg->{tvmonnight}         // 'false';
			my $surrlevel_style         = ($tvmonsurr_val eq 'true') ? "" : "display:none;";
			my $tvmon_master_style      = "";
			$rowssoundbar .= "<table class='tables sb_table_compact sb_single_table' border='0' id='tblsb_$room' name='tblsb_$room'>\n";
			$rowssoundbar .= "<tbody>\n";
			$rowssoundbar .= "<tr class='tvmon_switch_row tvmon_master' style='$tvmon_master_style'>\n";
			$rowssoundbar .= "<td id='soundbar_topcell_$room' colspan='13' class='sb_topcell'>\n";
			$rowssoundbar .= "<div class='sb_topline_inner'>\n";
			$rowssoundbar .= "<div class='sb_topline_wrap'>\n";
			$rowssoundbar .= "<label for='sbzone_$room' class='sb_topline_label' style='font-weight:bold;'>Soundbar:</label>\n";
			if (-e $statusfile) {
				$rowssoundbar .= "<div class='sb_topline_input_wrap'><input type='text' id='sbzone_$room' name='sbzone_$room' style='background-color: #6dac20; color:white' readonly='true' value='$room' class='sb_topline_input'></div>\n";
			} else {
				$rowssoundbar .= "<div class='sb_topline_input_wrap'><input type='text' id='sbzone_$room' name='sbzone_$room' style='background-color: #e6e6e6;' readonly='true' value='$room' class='sb_topline_input'></div>\n";
			}
			$rowssoundbar .= "<div class='sb_topline_switch_wrap'>\n";
			$rowssoundbar .= render_lb_flipswitch(
				id       => "usesb_$room",
				name     => "usesb_$room",
				value    => $usesb_val,
				onchange => "toggleSoundbar('$room')",
				style    => "margin:0;"
			);
			$rowssoundbar .= "</div>\n</div>\n</div>\n</td>\n</tr>\n";
			$rowssoundbar .= "<tr class='tvmon_header' id='soundbar_header_$room' style='background-color:#6db33f;'>\n";
			$rowssoundbar .= "<th class='sb_col_switch'>$SL{'SOUNDBARS.LABEL_SPEECH'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_switch'>$SL{'SOUNDBARS.LABEL_SURROUND'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_level sb_tvsurrlevel_col_$room' style='$surrlevel_style'>SurLev</th>\n";
			$rowssoundbar .= "<th class='sb_col_switch'>$SL{'SOUNDBARS.LABEL_SUB'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_level sb_tvsublevel_col_$room'>$SL{'SOUNDBARS.LABEL_SUB_GAIN'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_num' id='tvmtvol' name='tvmtvol'>$SL{'SOUNDBARS.LABEL_TVVOL'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_num' id='tvmttreble' name='tvmttreble'>$SL{'SOUNDBARS.LABEL_TVTREBLE'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_num' id='tvmtbass' name='tvmtbass'>$SL{'SOUNDBARS.LABEL_TVBASS'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_group'>$SL{'SOUNDBARS.LABEL_GRPZONE'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_time'>$SL{'SOUNDBARS.LABEL_FROM_TIME'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_switch sb_night_col_$room' style='$night_style'>$SL{'SOUNDBARS.LABEL_NIGHT'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_switch sb_night_col_$room' style='$night_style'>$SL{'SOUNDBARS.LABEL_SUB'}</th>\n";
			$rowssoundbar .= "<th class='sb_col_level sb_night_col_$room sb_nightsublevel_col_$room' style='$night_style'>$SL{'SOUNDBARS.LABEL_SUB_GAIN'}</th>\n";
			$rowssoundbar .= "</tr>\n";
			$rowssoundbar .= "<tr class='tvmon_body' id='soundbar_row_$room'>\n";
			$rowssoundbar .= "<td class='sb_col_switch'>\n<fieldset style='margin:0; padding:0; border:none; text-align:center;'>\n<div class='sb_switch_wrap'>\n";
			$rowssoundbar .= render_lb_flipswitch(id => "tvmonspeech_$room", name => "tvmonspeech_$room", value => $tvmonspeech_val);
			$rowssoundbar .= "</div>\n</fieldset></td>\n";
			$rowssoundbar .= "<td class='sb_col_switch'>\n<fieldset style='margin:0; padding:0; border:none; text-align:center;'>\n<div class='sb_switch_wrap'>\n";
			$rowssoundbar .= render_lb_flipswitch(id => "tvmonsurr_$room", name => "tvmonsurr_$room", value => $tvmonsurr_val, onchange => "toggleSoundbarSurrLevel('$room')");
			$rowssoundbar .= "</div>\n</fieldset></td>\n";
			$rowssoundbar .= "<td class='sb_col_level sb_tvsurrlevel_col_$room' style='$surrlevel_style'>\n<div class='sb_select_wrap sb_select_wrap_mid_left'><fieldset style='margin:0; padding:0; border:none; width:100%;'>\n";
			$rowssoundbar .= "<select id='tvsurrlevel_$room' name='tvsurrlevel_$room' data-mini='true' data-native-menu='true' style='width:100%'>\n";
			for my $i (-15 .. 15) { my $sel = ($i == $tvsurrlevel_val) ? " selected='selected'" : ""; $rowssoundbar .= "<option value='$i'$sel>$i</option>\n"; }
			$rowssoundbar .= "</select></fieldset></div>\n</td>\n";
			$rowssoundbar .= "<td class='sb_col_switch'>\n<fieldset style='margin:0; padding:0; border:none; text-align:center;'>\n<div class='sb_switch_wrap'>\n";
			$rowssoundbar .= render_lb_flipswitch(id => "tvmonnightsub_$room", name => "tvmonnightsub_$room", value => $tvmonnightsub_val);
			$rowssoundbar .= "</div>\n</fieldset></td>\n";
			$rowssoundbar .= "<td class='sb_col_level sb_tvsublevel_col_$room'>\n<div class='sb_select_wrap sb_select_wrap_mid_left'><fieldset style='margin:0; padding:0; border:none; width:100%;'>\n";
			$rowssoundbar .= "<select id='tvsublevel_$room' name='tvsublevel_$room' data-mini='true' data-native-menu='true' style='width:100%'>\n";
			for my $i (-15 .. 15) { my $sel = ($i == $tvsublevel_val) ? " selected='selected'" : ""; $rowssoundbar .= "<option value='$i'$sel>$i</option>\n"; }
			$rowssoundbar .= "</select></fieldset></div>\n</td>\n";
			$rowssoundbar .= "<td class='sb_col_num'>\n<div class='sb_input_wrap'><input class='tvvol' type='text' id='tvvol_$room' size='100' data-validation-error-msg='$error_volume' name='tvvol_$room' value='" . ($sbcfg->{tvvol} // '') . "'></div>\n</td>\n";
			$rowssoundbar .= "<td class='sb_col_num'>\n<div class='sb_input_wrap'><input class='tvtreble' type='text' id='tvtreble_$room' size='100' name='tvtreble_$room' value='" . ($sbcfg->{tvtreble} // '') . "'></div>\n</td>\n";
			$rowssoundbar .= "<td class='sb_col_num'>\n<div class='sb_input_wrap'><input class='tvbass' type='text' id='tvbass_$room' size='100' name='tvbass_$room' value='" . ($sbcfg->{tvbass} // '') . "'></div>\n</td>\n";
			my $tip_room = $room; $tip_room =~ s/[^A-Za-z0-9_\-]/_/g;
			my $tip_id = "tvgrpstop_tip_" . $tip_room;
			$rowssoundbar .= "<td class='sb_col_group sb_select_wrap sb_player_select_wrap'>\n";
			$rowssoundbar .= "<div style='position:relative; display:inline-block; width:100%; cursor:pointer;' onmouseenter='showGreenTooltip(\"#$tip_id\")' onmouseleave='hideTooltip(\"#$tip_id\")' onmousedown='hideTooltip(\"#$tip_id\")' onclick='hideTooltip(\"#$tip_id\")'>\n";
			$rowssoundbar .= "<div data-role='collapsible' data-collapsed='true' data-mini='true' style='cursor:pointer;' onmousedown='hideTooltip(\"#$tip_id\")' onclick='hideTooltip(\"#$tip_id\")'>\n";
			$rowssoundbar .= "<h4 style='cursor:pointer;' onmousedown='hideTooltip(\"#$tip_id\")' onclick='hideTooltip(\"#$tip_id\")'>$SL{'SOUNDBARS.LABEL_SELECT'}</h4>\n";
			my @saved_players = @{ (ref($sbcfg->{tvgrpstop}) eq 'ARRAY') ? $sbcfg->{tvgrpstop} : [] };
			foreach my $other_room (@all_rooms) {
				next if $other_room eq $room;
				my $checked = grep { $_ eq $other_room } @saved_players ? " checked='checked'" : "";
				$rowssoundbar .= "<label style='cursor:pointer;'><input type='checkbox' name='tvgrpstop_$room' value='$other_room'$checked style='cursor:pointer;'>$other_room</label>\n";
			}
			$rowssoundbar .= "</div>\n<div id='$tip_id' style='display:none; position:absolute; left:50%; bottom:42px; transform:translateX(-50%); padding:8px 12px; border-radius:6px; z-index:9999; text-align:left;'>$SL{'SOUNDBARS.TOOLTIP_PLAYER'} '$room' $SL{'SOUNDBARS.TOOLTIP_PLAYER1'}<div style='position:absolute; left:50%; transform:translateX(-50%); bottom:-8px; width:0; height:0; border-left:8px solid transparent; border-right:8px solid transparent; border-top:8px solid #6db33f;'></div></div>\n";
			$rowssoundbar .= "</div>\n</td>\n";
			$rowssoundbar .= "<td class='sb_col_time'>\n<div class='sb_time_wrap'><input id='fromtime_$room' type='time' name='fromtime_$room' value='$fromtime_val' oninput=\"toggleNightFieldsByTime('$room')\" onchange=\"toggleNightFieldsByTime('$room')\"></div>\n</td>\n";
			$rowssoundbar .= "<td class='sb_col_switch sb_night_col_$room' style='$night_style'>\n<fieldset style='margin:0; padding:0; border:none; text-align:center;'>\n<div class='sb_switch_wrap'>\n";
			$rowssoundbar .= render_lb_flipswitch(id => "tvmonnight_$room", name => "tvmonnight_$room", value => $tvmonnight_val);
			$rowssoundbar .= "</div>\n</fieldset></td>\n";
			$rowssoundbar .= "<td class='sb_col_switch sb_night_col_$room' style='$night_style'>\n<fieldset style='margin:0; padding:0; border:none; text-align:center;'>\n<div class='sb_switch_wrap'>\n";
			$rowssoundbar .= render_lb_flipswitch(id => "tvmonnightsubn_$room", name => "tvmonnightsubn_$room", value => $tvmonnightsubn_val);
			$rowssoundbar .= "</div>\n</fieldset></td>\n";
			$rowssoundbar .= "<td class='sb_col_level sb_night_col_$room sb_nightsublevel_col_$room' style='$night_style'>\n<div class='sb_select_wrap sb_select_wrap_mid_right'><fieldset style='margin:0; padding:0; border:none; width:100%;'>\n";
			$rowssoundbar .= "<select id='tvmonnightsublevel_$room' name='tvmonnightsublevel_$room' data-mini='true' data-native-menu='true' style='width:100%'>\n";
			for my $i (-15 .. 15) { my $sel = ($i == $tvmonnightsublevel_val) ? " selected='selected'" : ""; $rowssoundbar .= "<option value='$i'$sel>$i</option>\n"; }
			$rowssoundbar .= "</select></fieldset></div>\n</td>\n</tr>\n</tbody>\n</table>\n";
		}
	}
	#$countplayers = 0;
    if ($countplayers < 1) {
        $rowssonosplayer .= "<tr><td colspan=10>" . $SL{'ZONES.SONOS_EMPTY_ZONES'} . "</td></tr>\n";
    }
    $rowssonosplayer .= "<input type='hidden' id='countplayers' name='countplayers' value='$countplayers'>\n";
	$rowssonosplayer .= "<tr style='background-color: #6db33f;'><td colspan='12' style='text-align: center; height: 41px;'><a onClick='discover()' id='btnplayerscan' data-role='button' data-inline='true' data-mini='true' href='#'>$SL{'T2S.BUTTON_SCAN'}</a></td></tr>";
    
	$template->param("ROWSSONOSPLAYER", $rowssonosplayer);
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
        $rowssoundbar .= "<tr class='tvmon_extra tvmon_master' style='$tvmon_master_style'><td colspan=8>" . $SL{'ZONES.SONOS_EMPTY_SOUNDBARS'} . "</td></tr>\n";
    }
    $rowssoundbar .= "<input type='hidden' id='countsoundbars' name='countsoundbars' value='$countsoundbars'>\n";
    
	$template->param("ROWSOUNDBARS", $rowssoundbar);
    LOGDEB "Sonos soundbars have been discovered.";
	
    my $mshtml = LoxBerry::Web::mslist_select_html(
        FORMID    => 'ms',
        SELECTED  => $jsonobj->{LOXONE}->{Loxone},
        DATA_MINI => 1,
        LABEL     => "",
    );
    $template->param('MS', $mshtml);
    LOGDEB "List of available Miniserver(s) has been loaded.";
    my $dir = $lbpdatadir . '/' . $ttsfolder . '/' . $mp3folder . '/';
    my $mp3_list = '';
    opendir(my $dh, $dir) or die $!;
    my @dots = grep { /\.mp3$/ && -f "$dir/$_" } readdir($dh);
    closedir($dh);
    my @sorted_dots = sort { $a <=> $b } @dots;
    foreach my $file (@sorted_dots) {
        $mp3_list .= "<option value='$file'>$file</option>\n";
    }
    $template->param("MP3_LIST", $mp3_list);
    LOGDEB "List of MP3 files has been loaded.";
    if ($mqttcred) {
        $template->param("MQTT" => "true");
        LOGDEB "MQTT Gateway is installed and valid credentials were received.";
    } else {
        $template->param("MQTT" => "false");
        $cfg->{LOXONE}->{LoxDatenMQTT} = "false";
        LOGDEB "MQTT Gateway is not installed or wrong credentials were received.";
    }
    my $sonos_health = get_sonos_health();
    $template->param(
        SONOS_HEALTH_PID            => $sonos_health->{pid},
        SONOS_HEALTH_STATUS         => $sonos_health->{status},
        SONOS_HEALTH_STATUS_CLASS   => $sonos_health->{status_class},
		SONOS_HEALTH_TIMESTAMP 		=> $sonos_health->{timestamp},
        SONOS_HEALTH_FORMATTED_TIME => $sonos_health->{formatted_time},
        SONOS_HEALTH_ONLINE         => $sonos_health->{players_online},
        SONOS_HEALTH_TOTAL          => $sonos_health->{players_total},
        SONOS_HEALTH_IS_ERROR       => (
            defined $sonos_health->{status_class}
            && $sonos_health->{status_class} eq 'error'
        ) ? 1 : 0,
    );
    my $rowshostplayer = '';
    my $counthost = 0;
    foreach my $key (keys %{$config}) {
        $counthost++;
        $rowshostplayer .= "<option value=" . $key . " >" . $key . "</option>";
    }
    $template->param("ROWSHOSTPLAYER", $rowshostplayer);
    LOGOK "Sonos Plugin UI has been successfully loaded.";
    if (is_enabled($cfg->{VARIOUS}->{donate})) {
        $template->param("DONATE", 'checked="checked"');
    } else {
        $template->param("DONATE", '');
    }
    printtemplate();
    exit;
}

#####################################################
# Scan Sonos players / zones
#####################################################
#####################################################
# Scan Sonos players / zones
# Important:
# - Discovered players are added to $cfg->{sonoszonen} in memory only.
# - They are NOT written to s4lox_config.json here.
# - The user must press the normal Save button to persist them.
#####################################################
sub scan
{
    LOGINF "Auto-Discovery: Scan for Sonos Zones has been executed.";

    my $ttl       = 0;
    my $mcast_ttl = 2;
    my $extra_args = " --force=1";

    if ($FORCE_UNICAST_SCAN) {
        $ttl = 0;
        $extra_args = " --force=1 --unicast-only=1";
        LOGINF "Auto-Discovery: Manual VLAN IP scan requested – using UNICAST-ONLY mode.";
    }

    my $cmd = "/usr/bin/php $lbphtmldir/system/$scanzonesfile --ttl=$ttl --mcast-ttl=$mcast_ttl$extra_args";
    my $response = qx($cmd);
    $response =~ s/^\s+|\s+$//g;

	if ($response eq "") {
		LOGWARN "Auto-Discovery: Empty response from network.php. Manual Sonos IP input will be shown.";

		$vlan_hint        = 1;
		$vlan_hint_reason = 'empty_scan_result';
		$vlan_hint_ips    = [];

		# Show JS warning only after a failed MULTICAST/BROADCAST scan,
		# not after a manual UNICAST scan.
		$show_unicast_scan_hint = 1 if !$FORCE_UNICAST_SCAN;

		return($countplayers);
	}

    # No players found: empty array or empty object
	if ($response =~ /^\[\s*\]$/ || $response =~ /^\{\s*\}$/) {
		LOGINF "Auto-Discovery: No new players found. Manual Sonos IP input will be shown.";

		$vlan_hint        = 1;
		$vlan_hint_reason = 'no_new_players';
		$vlan_hint_ips    = [];

		# Show JS warning only after a failed MULTICAST/BROADCAST scan,
		# not after a manual UNICAST scan.
		$show_unicast_scan_hint = 1 if !$FORCE_UNICAST_SCAN;

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

    # ---- VLAN Hint Detection ----
    # network.php returns __vlan_hint__ when SSDP failed completely
    # or configured unicast IPs also failed.
	if (exists $newzones->{'__vlan_hint__'}) {
		$vlan_hint        = 1;
		$vlan_hint_reason = $newzones->{'reason'}    // 'ssdp_failed_no_static_ips';
		$vlan_hint_ips    = $newzones->{'tried_ips'} // [];

		LOGWARN "Auto-Discovery: VLAN hint received from network.php (reason: $vlan_hint_reason).";
		LOGWARN "Auto-Discovery: SSDP discovery failed completely or unicast fallback failed.";
		LOGWARN "Auto-Discovery: Manual Sonos IP input will be shown.";

		# Show JS warning only after a failed MULTICAST/BROADCAST scan,
		# not after a manual UNICAST scan.
		$show_unicast_scan_hint = 1 if !$FORCE_UNICAST_SCAN;

		return($countplayers);
	}

    # ---- Normal flow: stage discovered players in memory only ----
    $cfg->{sonoszonen} = {} if ref($cfg->{sonoszonen}) ne 'HASH';

    my $added = 0;

    foreach my $room (keys %{$newzones}) {
        next if exists $cfg->{sonoszonen}->{$room};

        # Add player only to the current runtime config object.
        # Do NOT write s4lox_config.json here.
        # The player will be saved only when the user presses Save.
        $cfg->{sonoszonen}->{$room} = $newzones->{$room};

        $added++;
        LOGOK "Auto-Discovery: Player '$room' staged in UI. It will be saved only after pressing Save.";
    }

    if ($added > 0) {
        $vlan_hint        = 0;
        $vlan_hint_reason = '';
        $vlan_hint_ips    = [];

        LOGOK "Auto-Discovery: $added new player(s) staged in UI. Configuration was not written yet.";
	} else {
		$vlan_hint        = 1;
		$vlan_hint_reason = 'no_new_players';
		$vlan_hint_ips    = [];

		# Show JS warning only after a MULTICAST/BROADCAST scan without new players,
		# not after a manual UNICAST scan.
		$show_unicast_scan_hint = 1 if !$FORCE_UNICAST_SCAN;

		LOGINF "Auto-Discovery: Scan returned players, but no new player was added to the UI.";
		LOGINF "Auto-Discovery: Manual Sonos IP input will be shown.";
	}

    return($countplayers);
}


sub _is_valid_ipv4
{
    my ($ip) = @_;
    return 0 unless defined $ip;

    $ip =~ s/^\s+|\s+$//g;

    return 0 unless $ip =~ /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/;

    for my $octet ($1, $2, $3, $4) {
        return 0 if $octet < 0 || $octet > 255;
    }

    return 1;
}

sub validate_sonos_unicast_ip
{
    my ($ip) = @_;

    $ip =~ s/^\s+|\s+$//g if defined $ip;

    return (0, "Invalid IPv4 format") unless _is_valid_ipv4($ip);

    my $url = "http://$ip:1400/info";

    my $ua = LWP::UserAgent->new(
        timeout      => 4,
        max_redirect => 0,
        env_proxy    => 0,
        agent        => "Sonos4Lox-Discovery/1.0",
    );

    my $res = $ua->get(
        $url,
        'Accept'     => 'application/json',
        'Connection' => 'close',
    );

    if (!$res->is_success) {
        return (0, "HTTP request failed: " . $res->status_line);
    }

    my $json;
    eval {
        $json = decode_json($res->decoded_content(charset => 'none'));
        1;
    } or do {
        return (0, "Invalid JSON response from $url");
    };

    if (
        ref($json) ne 'HASH'
        || ref($json->{device}) ne 'HASH'
        || !defined $json->{device}->{id}
        || !defined $json->{device}->{name}
    ) {
        return (0, "Response is not valid Sonos /info JSON");
    }

    my $rincon = $json->{device}->{id}   // '';
    my $room   = $json->{device}->{name} // '';
    my $model  = $json->{device}->{modelDisplayName} // $json->{device}->{model} // '';

    return (1, "OK: '$room' ($model), RINCON: $rincon");
}


#####################################################
# Save VLAN static IPs to config
# Speichert manuell eingegebene Sonos-IPs als
# vlan_static_ips in der s4lox_config.json.
# network.php verwendet diese beim nächsten Scan
# als Unicast-Fallback wenn SSDP fehlschlägt.
#####################################################
sub save_vlan_static_ips
{
    my @new_ips = @_;

    my @existing = ();
    if (ref($cfg->{'vlan_static_ips'}) eq 'ARRAY') {
        @existing = @{$cfg->{'vlan_static_ips'}};
    }

    my %seen = map { $_ => 1 } @existing;

    my @added;
    my @valid;
    my @failed;

    for my $ip (@new_ips) {
        $ip =~ s/^\s+|\s+$//g;
        next if $ip eq '';

        my ($ok, $msg) = validate_sonos_unicast_ip($ip);

        if (!$ok) {
            push @failed, "$ip ($msg)";
            LOGWARN "save_vlan_static_ips: IP '$ip' failed validation and will NOT be saved: $msg";
            next;
        }

        push @valid, $ip;
        LOGOK "save_vlan_static_ips: IP '$ip' validated successfully: $msg";

        if ($seen{$ip}) {
            LOGINF "save_vlan_static_ips: IP '$ip' is already stored in vlan_static_ips.";
            next;
        }

        push @existing, $ip;
        push @added, $ip;
        $seen{$ip} = 1;
    }

    if (@added) {
        $cfg->{'vlan_static_ips'} = \@existing;
        $jsonobj->write();

        LOGOK "save_vlan_static_ips: Saved validated VLAN static IP(s): " . join(", ", @added);
    } else {
        LOGWARN "save_vlan_static_ips: No new validated IPs were saved.";
    }

    if (@failed) {
        LOGWARN "save_vlan_static_ips: Failed IP(s): " . join(", ", @failed);
    }

    return {
        added  => \@added,
        valid  => \@valid,
        failed => \@failed,
    };
}


#####################################################
# VLAN IP form submit handler
# Verarbeitet den POST von action=save_vlan_ip:
#   1. IPs validieren und in Config speichern
#   2. Discovery-Cache leeren
#   3. Bei gültigen IPs Unicast-Scan ausführen
#   4. Danach form() rendern
#####################################################
sub save_vlan_ip_handler
{
    my $raw_ips = $cgi->param('vlan_ips') // $q->{vlan_ips} // $R::vlan_ips // '';

    LOGINF "save_vlan_ip_handler: Called.";
    LOGINF "save_vlan_ip_handler: Raw submitted IP string: '$raw_ips'";
    LOGDEB "save_vlan_ip_handler: Available CGI params: " . join(', ', $cgi->param);
	
	# This handler processes manual UNICAST input.
    # Do not show the MULTICAST/BROADCAST warning popup here.
    $show_unicast_scan_hint = 0;

    # The manual IP block should stay visible after this action unless
    # a valid scan actually adds new players successfully.
    $vlan_hint        = 1;
    $vlan_hint_reason = 'manual_unicast_input';
    $vlan_hint_ips    = [];

    # Comma, semicolon or whitespace separated IPs
    my @ips = grep { $_ ne '' } split /[\s,;]+/, $raw_ips;

    if (!@ips) {
        LOGWARN "save_vlan_ip_handler: No IPs submitted – returning to form.";

        $vlan_unicast_failed      = 0;
        $vlan_unicast_failed_text = '';

        $navbar{1}{active} = 1;
        $template->param("SETTINGS", "1");
        form();
        return;
    }

    my $result = save_vlan_static_ips(@ips);

    my $added_count  = scalar @{ $result->{added}  // [] };
    my $valid_count  = scalar @{ $result->{valid}  // [] };
    my $failed_count = scalar @{ $result->{failed} // [] };

    LOGINF "save_vlan_ip_handler: Validation result: $valid_count valid, $failed_count failed, $added_count newly saved.";

    if ($failed_count > 0) {
        $vlan_unicast_failed      = 1;
        $vlan_unicast_failed_text = join(', ', @{ $result->{failed} // [] });

        LOGWARN "save_vlan_ip_handler: Failed submitted IP(s): $vlan_unicast_failed_text";
    } else {
        $vlan_unicast_failed      = 0;
        $vlan_unicast_failed_text = '';
    }

    if ($valid_count < 1) {
        LOGWARN "save_vlan_ip_handler: No valid reachable Sonos IP submitted. Nothing will be saved.";

        $navbar{1}{active} = 1;
        $template->param("SETTINGS", "1");
        form();
        return;
    }

    LOGINF "save_vlan_ip_handler: Clearing discovery cache and running UNICAST scan...";

    # Clear cache so network.php does not return an old empty result
    unlink($cache_file) if defined $cache_file && -e $cache_file;

    # Run network.php in unicast-only mode using vlan_static_ips from config
    $FORCE_UNICAST_SCAN = 1;
    scan();
    $FORCE_UNICAST_SCAN = 0;

    # If at least one submitted IP failed validation, keep the manual block visible
    # even if another valid IP was successfully scanned.
    if ($failed_count > 0) {
        $vlan_hint = 1;
    }

    $navbar{1}{active} = 1;
    $template->param("SETTINGS", "1");
    form();
    return;
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
    if ($R::waitleave ne "") {
        $cfg->{VARIOUS}->{follow_wait} = "$R::waitleave";
    }
    $cfg->{VARIOUS}->{cron} = "$R::cron";
    $cfg->{VARIOUS}->{selfunction} = "$R::func_list";
    $cfg->{SYSTEM}->{checkt2s} = "true";
    $cfg->{SYSTEM}->{hw_update} = "$R::hw_update";
    $cfg->{SYSTEM}->{hw_update_day} = "$R::hw_update_day";
    $cfg->{SYSTEM}->{hw_update_time} = "$R::hw_update_time";
    $cfg->{SYSTEM}->{hw_update_power} = "$R::hw_update_power";
    $jsonobj->write();
    if ($R::cron eq "1") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
		unlink("$lbhomedir/system/cron/cron.15min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (each minute) created";
    }
    if ($R::cron eq "3") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
		unlink("$lbhomedir/system/cron/cron.15min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (every 3 minutes) created";
    }
    if ($R::cron eq "5") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.05min/$lbpplugindir");
		unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
		unlink("$lbhomedir/system/cron/cron.15min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (every 5 minutes) created";
    }
    if ($R::cron eq "10") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.10min/$lbpplugindir");
		unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.15min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (every 10 minutes) created";
    }
	if ($R::cron eq "15") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.15min/$lbpplugindir");
		unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
		unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
        LOGOK "Cron job (every 15 minutes) created";
    }
    if ($R::cron eq "30") {
        system("ln -s $lbphtmldir/bin/cron/cronjob.sh $lbhomedir/system/cron/cron.30min/$lbpplugindir");
		unlink("$lbhomedir/system/cron/cron.01min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
        unlink("$lbhomedir/system/cron/cron.15min/$lbpplugindir");
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
    my $countplayers    	= param('countplayers');
    my $countsoundbars 		= param('countsoundbars');
    my $countradios     	= param('countradios');
    my $LoxDaten        	= param('sendlox');
    my $selminiserver   	= param('ms');
    my $sel_ms = substr($selminiserver, -1, 1);
    my $gcfg = Config::Simple->new("$lbsconfigdir/general.cfg");
    my $miniservers = $gcfg->param("BASE.MINISERVERS");
    my $MiniServer  = $gcfg->param("MINISERVER$selminiserver.IPADDRESS");
    my $MSWebPort   = $gcfg->param("MINISERVER$selminiserver.PORT");
    my $MSUser      = $gcfg->param("MINISERVER$selminiserver.ADMIN");
    my $MSPass      = $gcfg->param("MINISERVER$selminiserver.PASS");
    if ($LoxDaten eq "true") {
        LOGDEB "Communication to Miniserver is switched ON";
    } else {
        LOGDEB "Communication to Miniserver is switched OFF";
    }
	my $old_sendlox = exists $cfg->{LOXONE}->{LoxDaten} ? ($cfg->{LOXONE}->{LoxDaten} // '') : '';
	my $old_loxone  = exists $cfg->{LOXONE}->{Loxone}   ? ($cfg->{LOXONE}->{Loxone}   // '') : '';
	my $old_udp     = exists $cfg->{LOXONE}->{UDP}      ? ($cfg->{LOXONE}->{UDP}      // '') : '';
	$old_sendlox =~ s/^\s+|\s+$//g;
	$old_loxone  =~ s/^\s+|\s+$//g;
	$old_udp     =~ s/^\s+|\s+$//g;
	my $udp_changed             = 0;
	my $listener_config_changed = 0;
	if (($old_sendlox // '') ne (($R::sendlox // ''))) {
		$listener_config_changed = 1;
	}
	if (($old_loxone // '') ne (($sel_ms // ''))) {
		$listener_config_changed = 1;
	}
	$cfg->{LOXONE}->{Loxone}   = "$sel_ms";
	$cfg->{LOXONE}->{LoxDaten} = "$R::sendlox";
	if (!defined $cfg->{LOXONE}->{LoxDaten} || lc($cfg->{LOXONE}->{LoxDaten}) ne 'true') {
		delete $cfg->{LOXONE}->{UDP};
		my $new_udp = '';
		$udp_changed = ($old_udp ne $new_udp) ? 1 : 0;
		$listener_config_changed = 1 if $udp_changed;
	} else {
		my $new_udp = defined $R::UDP ? "$R::UDP" : '';
		$new_udp =~ s/^\s+|\s+$//g;
		if ($new_udp eq '') {
			delete $cfg->{LOXONE}->{UDP};
		} else {
			$cfg->{LOXONE}->{UDP} = $new_udp;
		}
		$udp_changed = ($old_udp ne $new_udp) ? 1 : 0;
		$listener_config_changed = 1 if $udp_changed;
	}
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
	my %newradio;
	my $newi = 0;
	for (my $i = 1; $i <= $countradios; $i++) {
		next if param("chkradios$i");
		my $rname = param("radioname$i") // '';
		my $rurl  = param("radiourl$i")  // '';
		my $curl  = param("coverurl$i")  // '';
		$rname =~ s/^\s+|\s+$//g;
		$rurl  =~ s/^\s+|\s+$//g;
		$curl  =~ s/^\s+|\s+$//g;
		next if $rname eq '' && $rurl eq '' && $curl eq '';
		$newi++;
		$newradio{$newi} = $rname . "," . $rurl . "," . $curl;
	}
	$cfg->{RADIO}->{radio} = \%newradio;
	LOGDEB "Radio Stations have been saved (count=$newi).";
    if ($countplayers < 1) {
        $error_message = $SL{'ZONES.ERROR_NO_SCAN'};
        error();
    }
    my $del = "false";
    for (my $i = 1; $i <= $countplayers; $i++) {
        if (param("chkplayers$i")) {
			my $room1 = param("zone$i") // '';

			# Get the zone IP before deleting the zone from config.
			# Prefer submitted form value, fallback to existing config.
			my $deleted_ip = param("ip$i") // '';

			if (
				$deleted_ip eq ''
				&& ref($cfg->{sonoszonen}) eq 'HASH'
				&& exists $cfg->{sonoszonen}->{$room1}
				&& ref($cfg->{sonoszonen}->{$room1}) eq 'ARRAY'
			) {
				$deleted_ip = $cfg->{sonoszonen}->{$room1}->[0] // '';
			}

			$deleted_ip =~ s/^\s+|\s+$//g;

			delete $cfg->{sonoszonen}->{$room1};
			LOGOK "Sonos Zone '$room1' has been deleted from main config";

			# Also remove the deleted player's IP from vlan_static_ips.
			if ($deleted_ip ne '' && ref($cfg->{vlan_static_ips}) eq 'ARRAY') {
				my @old_vlan_ips = @{ $cfg->{vlan_static_ips} };

				my @new_vlan_ips = grep {
					my $ip = defined $_ ? $_ : '';
					$ip =~ s/^\s+|\s+$//g;
					$ip ne $deleted_ip;
				} @old_vlan_ips;

				if (scalar(@new_vlan_ips) != scalar(@old_vlan_ips)) {
					$cfg->{vlan_static_ips} = \@new_vlan_ips;
					LOGOK "Sonos Zone '$room1': IP '$deleted_ip' has been removed from vlan_static_ips.";
				} else {
					LOGINF "Sonos Zone '$room1': IP '$deleted_ip' was not found in vlan_static_ips.";
				}
			} elsif ($deleted_ip eq '') {
				LOGWARN "Sonos Zone '$room1': No IP address found, vlan_static_ips was not changed.";
			} else {
				LOGDEB "Sonos Zone '$room1': vlan_static_ips does not exist or is not an array.";
			}

			if (-r $lbpconfigdir . "/" . $volumeconfigfile) {
				for (my $e = 1; $e <= $size; $e++) {
					delete $vcfg->[$e - 1]->{Player}->{$room1};
					$del = "true";
				}
				LOGOK "Sonos Zone '$room1' has been deleted from Volume Profiles";
			}
			unlink($cache_file) if defined $cache_file && -e $cache_file;
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
					my $room = param("zone$i");
					my $tvmonspeech = param("tvmonspeech_$room");
					my $usesb       = param("usesb_$room");
					my $tvvol = param("tvvol_$room");
					$tvvol = "" if !defined $tvvol || $tvvol eq "false";
					my $tvtreble = param("tvtreble_$room");
					$tvtreble = "" if !defined $tvtreble || $tvtreble eq "false";
					my $tvbass = param("tvbass_$room");
					$tvbass = "" if !defined $tvbass || $tvbass eq "false";
					my $tvmonsurr = param("tvmonsurr_$room");
					$tvmonsurr = "false" if !defined $tvmonsurr || $tvmonsurr eq "";
					my $tvsurrlevel = param("tvsurrlevel_$room");
					$tvsurrlevel = 0 if !defined $tvsurrlevel || $tvsurrlevel eq "";
					my $fromtime       = param("fromtime_$room");
					my $tvmonnight     = param("tvmonnight_$room");
					my $tvmonnightsub  = param("tvmonnightsub_$room");
					$tvmonnightsub     = "false" if !defined $tvmonnightsub || $tvmonnightsub eq "";
					my $tvsubnight     = param("tvmonnightsubn_$room");
					$tvsubnight        = "false" if !defined $tvsubnight || $tvsubnight eq "";
					my $tvmonnightsublevel = param("tvmonnightsublevel_$room");
					$tvmonnightsublevel = 0 if !defined $tvmonnightsublevel || $tvmonnightsublevel eq "";
					my $tvsublevel = param("tvsublevel_$room");
					$tvsublevel = 0 if !defined $tvsublevel || $tvsublevel eq "";
					$tvsurrlevel        = 0 if $tvmonsurr eq "false";
					$tvsublevel         = 0 if $tvmonnightsub eq "false";
					$tvmonnightsublevel = 0 if $tvsubnight eq "false";
					my $starttime = param("pl-start-time$i");
					my $endtime   = param("pl-end-time$i");
					my @tvgrpstop = param("tvgrpstop_$room");
					@tvgrpstop = () unless @tvgrpstop;
					my @sbs = (
						{
							"tvmonspeech"        => $tvmonspeech,
							"usesb"              => $usesb,
							"tvvol"              => $tvvol,
							"tvtreble"           => $tvtreble,
							"tvbass"             => $tvbass,
							"tvmonsurr"          => $tvmonsurr,
							"tvsurrlevel"        => $tvsurrlevel,
							"tvsublevel"         => $tvsublevel,
							"fromtime"           => $fromtime,
							"tvmonnight"         => $tvmonnight,
							"tvmonnightsub"      => $tvmonnightsub,
							"tvsubnight"         => $tvsubnight,
							"tvmonnightsublevel" => $tvmonnightsublevel,
							"tvgrpstop"          => \@tvgrpstop
						},
						$starttime,
						$endtime
					);
					push @player, @sbs;
				} else {
					my @sbs = ("false", param("pl-start-time$i"), param("pl-end-time$i"));
					push @player, @sbs;
				}
			} else {
				my @sbs = ("false", param("pl-start-time$i"), param("pl-end-time$i"));
				push @player, @sbs;
			}
            my $zone_name = param("zone$i") // '';
            $cfg->{sonoszonen}->{$zone_name} = \@player;

            # If this player was discovered but not saved before,
            # add it to the Volume Profiles now.
            ensure_zone_in_volume_profiles(
                $zone_name,
                param("sur$i"),
                param("sub$i")
            );
        }
    }
    if (-r $lbpconfigdir . "/" . $volumeconfigfile && $del eq "true") {
        $jsonparser->write();
    }
    $jsonobj->write();
    LOGDEB "Sonos Zones have been saved.";
	if ($listener_config_changed) {
		my $healthfile = "/dev/shm/$lbpplugindir/health.json";
		unlink($healthfile) if -e $healthfile;
	}
    services();
    if ($R::sendlox eq "true") {
        prep_XML();
    }
    my $tv = qx(/usr/bin/php $lbphtmldir/bin/tv_monitor_conf.php);
    LOGOK "Main settings have been saved successfully";
    return;
}
#####################################################
# Save scanned zone into Volume Profiles
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
# Ensure saved zone exists in Volume Profiles
# Used when a newly discovered player is finally saved by the user.
# Existing player profile values are never overwritten.
#####################################################
sub ensure_zone_in_volume_profiles
{
    my ($room, $sur, $sub) = @_;

    return if !defined $room || $room eq '';
    return if !-r ($lbpconfigdir . "/" . $volumeconfigfile);
    return if ref($vcfg) ne 'ARRAY';

    my ($surround, $subwoofer, $Subwoofer_level);

    if (defined $sur && $sur eq "NOSUR") {
        $surround = "na";
    } else {
        $surround = "true";
    }

    if (defined $sub && $sub eq "NOSUB") {
        $subwoofer       = "na";
        $Subwoofer_level = "";
    } else {
        $subwoofer       = "true";
        $Subwoofer_level = "";
    }

    my $changed = 0;

    for (my $e = 1; $e <= $size; $e++) {
        next if ref($vcfg->[$e - 1]) ne 'HASH';

        if (ref($vcfg->[$e - 1]->{Player}) ne 'HASH') {
            $vcfg->[$e - 1]->{Player} = {};
        }

        # Do not overwrite existing profile data.
        next if exists $vcfg->[$e - 1]->{Player}->{$room};

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
        $changed = 1;
    }

    if ($changed) {
        $jsonparser->write();
        LOGOK "Sonos Zone '$room' has been added to Volume Profiles.";
    } else {
        LOGDEB "Sonos Zone '$room' already exists in Volume Profiles. Nothing changed.";
    }

    return;
}
#####################################################
# Volume page renderer
#####################################################
sub volumes {
    $template->param(FORMNO => 'VOLUME');
    if (-e $lbpconfigdir . "/" . $volumeconfigfile) {
        LOGDEB("Volume Profiles config already exists");
    } else {
        my $volfile = qx(/usr/bin/php $lbphtmldir/bin/vol_prof_ini.php);
        LOGDEB("New Volume Profiles config has been created");
    }
    my $rowsvolplayer = '';
    my $jsonobjvol    = LoxBerry::JSON->new();
    my $vcfg_local    = $jsonobj->open(filename => $lbpconfigdir . "/" . $volumeconfigfile);
    my $last_id = (keys @$vcfg_local);
    my $config  = $cfg->{sonoszonen};
    my $build_flipswitch = sub {
        my ($name, $value, $disabled) = @_;
        $value    = defined $value ? $value : 'false';
        $disabled = $disabled ? 1 : 0;
        my $checked_attr  = ($value eq 'true') ? " checked='checked'" : "";
        my $disabled_attr = $disabled ? " disabled='disabled'" : "";
        my $wrap_class    = $disabled ? "lb-flipswitch is-disabled" : "lb-flipswitch";
        return ""
            . "<div class='$wrap_class' data-input='$name' data-value='$value'>"
            . "<input type='hidden' name='$name' id='${name}_hidden' value='$value'>"
            . "<input type='checkbox' id='$name' class='lb-flipswitch-checkbox no-jqm-flipswitch' data-role='none'$checked_attr$disabled_attr>"
            . "<label class='lb-flipswitch-label' for='$name'>"
            . "<span class='lb-flipswitch-inner'></span>"
            . "<span class='lb-flipswitch-switch'></span>"
            . "</label>"
            . "</div>";
    };
    for (my $id = 1; $id <= $last_id; $id++) {
        $countplayers = 0;
        $rowsvolplayer .= "<table class='tables' style='width:100%' id='tblvol_prof$id' name='tblvol_prof$id' border='0'>\n";
        $rowsvolplayer .= "<th align='left' style='height: 25px; width:100px'>&nbsp;Profile #$id</th>\n";
        $rowsvolplayer .= "<th align='middle' colspan='8'><div style='width: 180px; align: left'>\n";
        $rowsvolplayer .= "<input class='textfield' type='text' style='align: middle; width: 100%' id='profile$id' name='profile$id' value='' placeholder='Volume Profile Name'/>\n";
        $rowsvolplayer .= "<td valign='left'>";
		$rowsvolplayer .= "<span style='position:relative; display:inline-block; margin-right:8px;'>"
						. "<img value='$id' id='btnload$id' name='btnload$id' class='ico-load' "
						. "style='cursor:pointer;' "
						. "onmouseenter='showGreenTooltip(\"#btnload_tip_$id\")' "
						. "onmouseleave='hideTooltip(\"#btnload_tip_$id\")' "
						. "src='/plugins/$lbpplugindir/images/musik-note.png' border='0' width='30' height='30'>"
						. "<div id='btnload_tip_$id' "
						. "style='display:none; position:absolute; left:50%; bottom:38px; transform:translateX(-50%); "
						. "padding:8px 12px; border-radius:6px; z-index:9999; text-align:left;'>"
						. "Load current values from Sonos devices"
						. "<div style='position:absolute; left:50%; transform:translateX(-50%); bottom:-8px; width:0; height:0; "
						. "border-left:8px solid transparent; border-right:8px solid transparent; border-top:8px solid #6db33f;'></div>"
						. "</div>"
						. "</span>\n";
		if ($last_id > 1) {
			$rowsvolplayer .= "<span style='position:relative; display:inline-block;'>"
							. "<img onclick='' value='$id' id='btndel$id' name='btndel$id' class='ico-delete' "
							. "style='cursor:pointer;' "
							. "onmouseenter='showGreenTooltip(\"#btndel_tip_$id\")' "
							. "onmouseleave='hideTooltip(\"#btndel_tip_$id\")' "
							. "src='/plugins/$lbpplugindir/images/recycle-bin.png' border='0' width='30' height='30'>"
							. "<div id='btndel_tip_$id' "
							. "style='display:none; position:absolute; left:50%; bottom:38px; transform:translateX(-50%); "
							. "padding:8px 12px; border-radius:6px; z-index:9999; text-align:left;'>"
							. "Delete current Profile"
							. "<div style='position:absolute; left:50%; transform:translateX(-50%); bottom:-8px; width:0; height:0; "
							. "border-left:8px solid transparent; border-right:8px solid transparent; border-top:8px solid #6db33f;'></div>"
							. "</div>"
							. "</span></td>\n";
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
            my $surround_cap = $config->{$key}->[10];
            my $sub_cap      = $config->{$key}->[8];
            my $surround_disabled = ($surround_cap && $surround_cap eq 'NOSUR') ? 1 : 0;
            my $sub_disabled      = ($sub_cap && $sub_cap eq 'NOSUB') ? 1 : 0;
            my $loudness_value  = $vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Loudness}  // 'false';
            my $surround_value  = $vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Surround}  // 'false';
            my $subwoofer_value = $vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Subwoofer} // 'false';
            if ($surround_value eq 'na')  { $surround_value  = 'false'; $surround_disabled = 1; }
            if ($subwoofer_value eq 'na') { $subwoofer_value = 'false'; $sub_disabled      = 1; }
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
            $rowsvolplayer .= "<td style='height: 10px; width: 70px; text-align:center; vertical-align:middle;'>";
            $rowsvolplayer .= $build_flipswitch->("loudness_$zid", $loudness_value, 0);
            $rowsvolplayer .= "</td>\n";
            $rowsvolplayer .= "<td style='height: 10px; width: 70px; text-align:center; vertical-align:middle;'>";
            $rowsvolplayer .= $build_flipswitch->("surround_$zid", $surround_value, $surround_disabled);
            $rowsvolplayer .= "</td>\n";
            $rowsvolplayer .= "<td style='height: 10px; width: 70px; text-align:center; vertical-align:middle;'>";
            $rowsvolplayer .= $build_flipswitch->("subwoofer_$zid", $subwoofer_value, $sub_disabled);
            $rowsvolplayer .= "</td>\n";
            if ($sub_disabled) {
                $rowsvolplayer .= "<td style='width: 55px; height: 15px'><input type='text' class='form-validation' id='sbass_$zid' name='sbass_$zid' size='100' disabled='disabled' style='background: rgba(192,192,192, 0.2)' data-validation-rule='special:number-min-max-value:-15:15' data-validation-error-msg='$error_sbass' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Subwoofer_level}'></td>\n";
            } else {
                $rowsvolplayer .= "<td style='width: 55px; height: 15px'><input type='text' class='form-validation' id='sbass_$zid' name='sbass_$zid' size='100' data-validation-rule='special:number-min-max-value:-15:15' data-validation-error-msg='$error_sbass' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Subwoofer_level}'></td>\n";
            }
            $rowsvolplayer .= "<td style='width: 60px; height: 15px'><div class='$id' id='$id'><input type='checkbox' id='master_$zid' name='master_$zid' class='$id' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Master}'></div></td>\n";
            $rowsvolplayer .= "<td style='width: 60px; height: 15px'><input type='checkbox' id='member_$zid' name='member_$zid' class='member_$id' value='$vcfg_local->[$id-1]->{Player}->{$key}->[0]->{Member}'></td>\n";
            $rowsvolplayer .= "</div></tr>";
        }
        $rowsvolplayer .= "<br></table>";
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
    my ($surround, $subwoofer, $Subwoofer_level, $loudness);
    my $jsonobjvol = LoxBerry::JSON->new();
    my $vcfg_local = $jsonobjvol->open(filename => $lbpconfigdir . "/" . $volumeconfigfile);
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
            if ($cfg->{sonoszonen}->{$zone}->[10] eq "NOSUR") {
                $surround = "na";
            } else {
                $surround = is_enabled(param("surround_$zid")) ? "true" : "false";
            }
            if ($cfg->{sonoszonen}->{$zone}->[8] eq "NOSUB") {
                $subwoofer       = "na";
                $Subwoofer_level = "";
            } else {
                $Subwoofer_level = param("sbass_$zid");
                $subwoofer = is_enabled(param("subwoofer_$zid")) ? "true" : "false";
            }
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
        if ($hasMaster eq "true" and $hasMember eq "true") {
            $isGroup = "Group";
        } elsif ($hasMaster eq "true" and $hasMember eq "false") {
            $isGroup = "Single";
        } elsif ($hasMaster eq "false" and $hasMember eq "true") {
            $isGroup = "Error";
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
    my $target_url = $url;
    if ($mode eq 'ics') {
        if ($url =~ /(?:^|[?&])calURL=([^&]+)/i) {
            my $enc = $1;
            my $dec = uri_unescape($enc);
            $dec =~ s/^webcal:\/\//https:\/\//i;
            $target_url = $dec if $dec =~ m{^https?://}i;
        }
    }
    my $ua = LWP::UserAgent->new(
        timeout      => 12,
        max_size     => 512 * 1024,
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
    $body =~ s/^\xEF\xBB\xBF//;
    if ($body =~ /^\xFF\xFE\x00\x00/) {
        $body = Encode::decode('UTF-32LE', $body);
    } elsif ($body =~ /^\x00\x00\xFE\xFF/) {
        $body = Encode::decode('UTF-32BE', $body);
    } elsif ($body =~ /^\xFF\xFE/) {
        $body = Encode::decode('UTF-16LE', $body);
    } elsif ($body =~ /^\xFE\xFF/) {
        $body = Encode::decode('UTF-16BE', $body);
    }
    $body =~ s/^\s+//;
    if ($mode eq 'ics') {
        my $has_vcal = ($body =~ /BEGIN:VCALENDAR/i) ? 1 : 0;
        if ($ct =~ /json/) {
            return (0, sprintf("Got JSON instead of ICS (Content-Type: %s)", $ct_header));
        }
        if ($ct =~ /html/ && !$has_vcal) {
            my $snip = substr($body, 0, 200); $snip =~ s/\s+/ /g;
            return (0, sprintf("Got HTML instead of ICS (Content-Type: %s): %s", $ct_header, $snip));
        }
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
        if ($ct =~ /calendar|ics/) {
            return (0, sprintf("Got ICS instead of JSON (Content-Type: %s)", $ct_header));
        }
        my $data;
        eval { $data = decode_json($body) };
        if ($@ || !defined $data) {
            my $snip = substr($body, 0, 200); $snip =~ s/\s+/ /g;
            return (0, sprintf("Invalid JSON. Snippet: %s", $snip));
        }
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
# Restore config file (AJAX) called from Cronjob
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
# ------------------------------------------------------------------------------
# Helpers
# ------------------------------------------------------------------------------
sub _normalize_version {
	my ($v) = @_;
	$v = lc($v // '');
	$v =~ s/^\s+//;
	$v =~ s/\s+$//;
	$v =~ s/^v//i;
	return $v;
}
sub _sonos_version_cmp {
	my ($left, $right) = @_;
	my $a = _normalize_version($left);
	my $b = _normalize_version($right);
	my @A = split /\./, $a;
	my @B = split /\./, $b;
	my $max = @A > @B ? scalar(@A) : scalar(@B);
	for (my $i = 0; $i < $max; $i++) {
		my $x = (defined $A[$i] && $A[$i] =~ /^\d+$/) ? $A[$i] : 0;
		my $y = (defined $B[$i] && $B[$i] =~ /^\d+$/) ? $B[$i] : 0;
		my $cmp = $y <=> $x;
		return $cmp if $cmp;
	}
	return $b cmp $a;

	sub _sonos_version_is_newer {
		my ($candidate, $installed) = @_;
		$candidate = _normalize_version($candidate);
		$installed = _normalize_version($installed);
		return 0 if !$candidate || !$installed;
		return _sonos_version_cmp($candidate, $installed) < 0 ? 1 : 0;
	}
}
#######################################################################
# Control Sonos systemd units
#######################################################################
sub services
{
    my @enable_units = (
        'sonos_event_listener.service',
        'sonos_check_on_state.timer',
        'sonos_watchdog.timer',
    );
    my @disable_units = (
        'sonos_watchdog.timer',
        'sonos_check_on_state.timer',
        'sonos_event_listener.service',
    );
    my $sysd = sub {
        my (@args) = @_;
        my $rc = system('sudo', '-n', '/bin/systemctl', @args);
        return ($rc >> 8);
    };
    my $cfgfile      = "$lbpconfigdir/s4lox_config.json";
    my $lox_enabled  = 0;
    my $udp_port     = 0;
    my $want_mqtt    = 0;
    my $want_udp     = 0;
    my $want_listener = 0;
    if (-r $cfgfile) {
        my $json_text;
        if (open(my $fh, '<', $cfgfile)) {
            local $/ = undef;
            $json_text = <$fh>;
            close($fh);
            my $decoded;
            eval {
                $decoded = decode_json($json_text);
                1;
            } or do {
                LOGERR "Could not decode $cfgfile while checking listener state";
            };
            if ($decoded && ref $decoded eq 'HASH') {
                $lox_enabled = (
                    defined $decoded->{LOXONE}->{LoxDaten}
                    && $decoded->{LOXONE}->{LoxDaten} eq 'true'
                ) ? 1 : 0;
                $udp_port = int($decoded->{LOXONE}->{UDP} // 0);
            }
        } else {
            LOGERR "Could not open $cfgfile while checking listener state";
        }
    } else {
        LOGINF "Config file $cfgfile not readable - assuming listener disabled";
    }
    if ($lox_enabled) {
        $want_mqtt = ($R::sendlox && $R::sendlox eq "true") ? 1 : 0;
        $want_udp  = ($udp_port > 0) ? 1 : 0;
    }
    $want_listener = ($want_mqtt || $want_udp) ? 1 : 0;
    if ($want_listener) {
        my @modes;
        push @modes, 'MQTT' if $want_mqtt;
        push @modes, "UDP:$udp_port" if $want_udp;
        my $mode_text = join(' + ', @modes);
        LOGINF "Sonos event output is enabled ($mode_text) – enabling and starting systemd units";
        for my $u (@enable_units) {
            my $rc = $sysd->('enable', '--now', $u);
            if ($rc != 0) { LOGERR "Could not enable/start $u (rc=$rc)"; }
            else          { LOGOK  "$u has been enabled and started"; }
        }
    } else {
        if (!$lox_enabled) {
            LOGINF "Sonos event output is disabled – disabling and stopping systemd units";
        } else {
            LOGINF "Sonos event output is disabled (MQTT off, UDP off) – disabling and stopping systemd units";
        }
        for my $u (@disable_units) {
            my $rc = $sysd->('disable', '--now', $u);
            if ($rc != 0) { LOGERR "Could not disable/stop $u (rc=$rc)"; }
            else          { LOGOK  "$u has been disabled and stopped"; }
        }
    }
    return;
}
####################################################################
# Event listener health
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
    eval { $decoded = decode_json($json_text); 1; } or do { return \%fallback; };
    my $root = $decoded;
    return \%fallback if !$root || ref $root ne 'HASH';
    my $ts  = $root->{timestamp} // 0;
    my $now = time();
    my $age = $ts ? ($now - $ts) : 999999;
    my $formatted_time = $root->{ts_formatted} // '';
    my ($status, $status_class);
    if ($age <= 60)       { $status = $SL{'TEMPLATE.TXT_PLAYER_GREEN'};  $status_class = 'ok'; }
    elsif ($age <= 300)   { $status = $SL{'TEMPLATE.TXT_PLAYER_YELLOW'}; $status_class = 'warn'; }
    else                  { $status = $SL{'TEMPLATE.TXT_PLAYER_RED'};    $status_class = 'error'; }
    return {
        service        => $root->{source}      // 'sonos_event_listener',
        hostname       => $root->{hostname}    // 'unknown',
        pid            => $root->{pid},
        timestamp      => $ts,
        iso_time       => $root->{iso_time}    // '',
        formatted_time => $formatted_time,
        players_online => $root->{online_players} // 0,
        players_total  => $root->{total_players}  // 0,
        status         => $status,
        status_class   => $status_class,
        age_sec        => int($age),
    };
}
sub render_lb_flipswitch {
    my (%p) = @_;
    my $id      = $p{id}      // return '';
    my $name    = $p{name}    // $id;
    my $value   = defined $p{value} ? $p{value} : 'false';
    my $class   = $p{class}   // '';
    my $style   = $p{style}   // '';
    my $onchange= $p{onchange} // '';
    my $html = "";
    $html .= "<div class='lb-flipswitch $class' data-input='$id' data-value='$value' style='$style'>\n";
    $html .= "  <input type='hidden' name='$name' id='${id}_hidden' value='false'>\n";
    $html .= "  <input type='checkbox' id='$id' class='lb-flipswitch-checkbox no-jqm-flipswitch' data-role='none'";
    $html .= " onchange=\"$onchange\"" if $onchange;
    $html .= ">\n";
    $html .= "  <label class='lb-flipswitch-label' for='$id'>\n";
    $html .= "      <span class='lb-flipswitch-inner'></span>\n";
    $html .= "      <span class='lb-flipswitch-switch'></span>\n";
    $html .= "  </label>\n";
    $html .= "</div>\n";
    return $html;
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
# END routine
##########################################################################
sub END
{
    our @reason;
    our $IS_AJAX_REQUEST;
    our $AJAX_ACTION;
    return if !$log;
    if ($IS_AJAX_REQUEST) {
        if (@reason) {
            LOGCRIT "Unhandled exception in AJAX request" . ($AJAX_ACTION ? " ($AJAX_ACTION)" : "") . ":";
            LOGERR @reason;
        } elsif ($error_message) {
            LOGERR "AJAX error" . ($AJAX_ACTION ? " ($AJAX_ACTION)" : "") . ": " . $error_message;
        }
        return;
    }
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