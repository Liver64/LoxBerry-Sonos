#!/usr/bin/perl -w
# =============================================================================
# Sonos4Lox / Sonos UI - testing.cgi
# Version: TESTING_CGI_V26_2026_06_22_SONOS_THEME_WALLPAPER_PATHFIX
# =============================================================================
# Dedicated Testing page for Sonos4Lox.
# The page is available only in debug loglevel (7).
# =============================================================================

use LoxBerry::System;
use LoxBerry::Web;
use LoxBerry::Log;
use LoxBerry::IO;
use LoxBerry::JSON;

use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use CGI ();
use JSON::PP ();
use HTML::Template;
use POSIX qw(strftime);
use File::Path qw(make_path);
use File::Copy qw(copy);
use Encode qw(encode is_utf8);
use URI::Escape qw(uri_unescape);

use strict;
use warnings;
use utf8;
no strict "refs";

##########################################################################
# Generic exception handler
##########################################################################
our @reason;
$SIG{__DIE__} = sub {
    return if $^S;     # inside eval -> ignore (handled)
    @reason = @_;
};

##########################################################################
# Variables / constants
##########################################################################
my $helptemplatefilename = "help/help.html";
my $languagefile         = "sonos.ini";
my $maintemplatefilename = "sonos.html";
my $pluginlogfile        = "sonos.log";
my $configfile           = "s4lox_config.json";
my $generaljson          = "general.json";
my $helplink             = "http://www.loxwiki.eu/display/LOXBERRY/Sonos4Loxone";

# Testing data files are intentionally stored outside the web frontend.
# url_tests.json is now maintained directly from the Testing page and belongs
# to the plugin config directory so it is part of the normal plugin configuration.
# Example: /opt/loxberry/config/plugins/sonos4lox/url_tests.json
my $testing_script_version = "TESTING_CGI_V26_2026_06_22_SONOS_THEME_WALLPAPER_PATHFIX";
my $testing_json_file      = $lbpconfigdir . "/url_tests.json";
my $testing_log_file       = $lbplogdir . "/regression_test.log";
my $testing_launcher_log_file = $lbplogdir . "/regression_test_launcher.log";
my $testing_status_dir       = "/run/shm/sonos4lox";
my $testing_status_file      = $testing_status_dir . "/regression_test_status.json";

# Placeholder handling for playlist/radio/favorite regression tests.
# The URL keeps SOURCE as placeholder, while the real playlist/radio/favorite
# value is stored in the JSON field "source" and injected by regression_test.pl.
my $TESTING_SOURCE_PLACEHOLDER = "SOURCE";
my @TESTING_SOURCE_ACTIONS = qw(sonosplaylist pluginradio playfavorite);
my %TESTING_SOURCE_ACTION = map { $_ => 1 } @TESTING_SOURCE_ACTIONS;
my %TESTING_SOURCE_PARAM_CANDIDATES = (
    sonosplaylist => [qw(playlist)],
    pluginradio   => [qw(radio station radiostation source name)],
    playfavorite  => [qw(favorite fav title name source)],
);

our $template;
our %SL;
our $cgi;
our $log;
our $error_message = '';

our $jsonobj = LoxBerry::JSON->new();
our $cfg     = $jsonobj->open(filename => $lbpconfigdir . "/" . $configfile, writeonclose => 0);

our $jsonobjg   = LoxBerry::JSON->new();
our $generalcfg = $jsonobjg->open(filename => $lbsconfigdir . "/" . $generaljson, writeonclose => 0);

$log = LoxBerry::Log->new(
    name     => 'Sonos UI Testing',
    filename => $lbplogdir . "/" . $pluginlogfile,
    append   => 1,
    addtime  => 1
);

my $lblang    = lblanguage();
my $sversion  = LoxBerry::System::pluginversion();
my $lbversion = LoxBerry::System::lbversion();
my $lbv = substr($lbversion, 0, 1);

our $theme = '';
if (ref($generalcfg) eq 'HASH' && ref($generalcfg->{Base}) eq 'HASH') {
    $theme = $generalcfg->{Base}->{Theme} // '';
}
our $sonos_theme_info = s4l_get_sonos_theme_info();

$cgi = CGI->new;
$cgi->import_names('R');
our $q = $cgi->Vars;

our $htmlhead = s4l_build_htmlhead();
##########################################################################
# Debug-only gate
##########################################################################
if ($log->loglevel() ne "7") {
    print "Status: 403 Forbidden\n";
    print "Content-Type: text/plain; charset=utf-8\n\n";
    print "Testing is only available when plugin loglevel is set to 7 (Debug).\n";
    exit;
}

##########################################################################
# Sonos theme selector helpers
##########################################################################
sub s4l_normalize_theme_name
{
    my ($value) = @_;
    $value = '' if !defined $value;
    $value = lc($value);
    $value =~ s/^\s+|\s+$//g;
    $value =~ s/_/-/g;
    $value =~ s/\.css$//;
    $value =~ s/^theme-//;

    # SONOS_THEME_SELECTOR_V05_2026_06_21:
    # Keep native LoxBerry theme names such as "glass" instead of collapsing
    # them to "system". The saved Sonos plugin-theme value is still validated
    # later against the explicit allow-list, but the effective system theme
    # must remain detectable so the white Sonos logo is used for theme-glass.
    return 'system' if $value eq '' || $value eq 'default' || $value eq 'auto';
    return 'classic-mac' if $value eq 'mac-classic';
    return $value;
}

sub s4l_system_theme_css_exists
{
    my ($theme_name) = @_;
    $theme_name = s4l_normalize_theme_name($theme_name);
    return 0 if $theme_name eq 'system';
    my $css_file = "$lbhomedir/webfrontend/html/system/css/theme-$theme_name.css";
    return -e $css_file ? 1 : 0;
}

sub s4l_get_sonos_theme_info
{
    my $saved_theme = 'system';
    if (ref($cfg) eq 'HASH' && ref($cfg->{UI}) eq 'HASH') {
        $saved_theme = s4l_normalize_theme_name($cfg->{UI}->{sonostheme});
    }

    my %plugin_theme_allowed = (
        'classic-mac'  => ($lbv >= 4 && !s4l_system_theme_css_exists('classic-mac')) ? 1 : 0,
        'liquid-glass' => ($lbv >= 4 && !s4l_system_theme_css_exists('liquid-glass')) ? 1 : 0,
    );

    my $show_selector = ($lbv >= 4 && ($plugin_theme_allowed{'classic-mac'} || $plugin_theme_allowed{'liquid-glass'})) ? 1 : 0;
    my $effective_theme = ($show_selector && $plugin_theme_allowed{$saved_theme}) ? $saved_theme : 'system';

    return {
        saved          => $saved_theme,
        effective      => $effective_theme,
        show_selector  => $show_selector,
        allowed        => \%plugin_theme_allowed,
    };
}

sub s4l_effective_theme_lc
{
    my $system_theme = s4l_normalize_theme_name($theme);
    if (ref($sonos_theme_info) eq 'HASH' && ($sonos_theme_info->{effective} // 'system') ne 'system') {
        return $sonos_theme_info->{effective};
    }
    return $system_theme;
}


sub s4l_wallpaper_dir
{
    return $lbpdatadir . "/themes";
}

sub s4l_custom_wallpaper_files
{
    my $dir = s4l_wallpaper_dir();
    return map { "$dir/liquid-glass-background.$_" } qw(png jpg jpeg webp);
}

sub s4l_find_custom_wallpaper
{
    for my $file (s4l_custom_wallpaper_files()) {
        return $file if -r $file;
    }
    return '';
}

sub s4l_wallpaper_cache_buster
{
    my $file = s4l_find_custom_wallpaper();
    if (!$file) {
        $file = $lbphtmldir . "/LayoutUI/themes/theme-liquid-glass-background.png";
    }
    my @stat = stat($file);
    return $stat[9] || time();
}


sub s4l_get_liquid_glass_wallpaper_brightness
{
    my $value = 32;
    if (ref($cfg) eq 'HASH' && ref($cfg->{UI}) eq 'HASH' && defined $cfg->{UI}->{liquid_glass_wallpaper_brightness}) {
        $value = $cfg->{UI}->{liquid_glass_wallpaper_brightness};
    }

    $value = 32 if $value !~ /^\d+(?:\.\d+)?$/;
    $value = int($value + 0.5);
    $value = 0 if $value < 0;
    $value = 100 if $value > 100;
    return $value;
}

sub s4l_liquid_glass_wallpaper_dim_values
{
    my $brightness = s4l_get_liquid_glass_wallpaper_brightness();
    my $primary = (100 - $brightness) / 100;
    my $secondary = $primary - 0.10;
    $secondary = 0 if $secondary < 0;
    return (sprintf('%.2f', $primary), sprintf('%.2f', $secondary));
}

sub s4l_liquid_glass_wallpaper_style_tag
{
    return '' if $lbv < 4;

    my $url = '/plugins/' . $lbpplugindir . '/LayoutUI/themes/theme-wallpaper.cgi?v=' . s4l_wallpaper_cache_buster();
    my ($dim_primary, $dim_secondary) = s4l_liquid_glass_wallpaper_dim_values();

    return '<style id="s4lox-liquid-glass-wallpaper-style">' . "\n"
        . 'body.theme-liquid-glass {' . "\n"
        . '--s4lox-liquid-glass-wallpaper-dim-primary: ' . $dim_primary . ';' . "\n"
        . '--s4lox-liquid-glass-wallpaper-dim-secondary: ' . $dim_secondary . ';' . "\n"
        . '}' . "\n"
        . 'body.theme-liquid-glass::before {' . "\n"
        . 'content: "";' . "\n"
        . 'position: fixed;' . "\n"
        . 'inset: 0;' . "\n"
        . 'z-index: -2;' . "\n"
        . 'pointer-events: none;' . "\n"
        . 'background:' . "\n"
        . 'linear-gradient(135deg, rgba(3, 8, 16, var(--s4lox-liquid-glass-wallpaper-dim-primary, 0.68)), rgba(10, 24, 38, var(--s4lox-liquid-glass-wallpaper-dim-secondary, 0.58))),' . "\n"
        . 'radial-gradient(900px 500px at 15% 10%, rgba(96, 130, 165, 0.10), transparent 52%),' . "\n"
        . 'radial-gradient(900px 500px at 85% 90%, rgba(73, 102, 133, 0.08), transparent 54%),' . "\n"
        . 'radial-gradient(700px 420px at 65% 15%, rgba(109, 172, 32, 0.035), transparent 58%),' . "\n"
        . 'url("' . $url . '") center center / cover fixed no-repeat !important;' . "\n"
        . '}' . "\n"
        . '</style>' . "\n";
}

sub s4l_build_htmlhead
{
    my $head = '';

    if ($lbv < 4) {
        return '<link rel="stylesheet" href="/plugins/'.$lbpplugindir.'/LayoutUI/sonos_lbv3.css?v='.$sversion.'"/>';
    }

    my $effective_theme = (ref($sonos_theme_info) eq 'HASH') ? ($sonos_theme_info->{effective} // 'system') : 'system';
    if ($effective_theme eq 'classic-mac' || $effective_theme eq 'liquid-glass') {
        $head .= '<link rel="stylesheet" href="/plugins/'.$lbpplugindir.'/LayoutUI/themes/theme-'.$effective_theme.'.css?v='.$sversion.'"/>' . "\n";
        $head .= '<script>(function(){var themeClass="theme-'.$effective_theme.'";function applySonosTheme(){var body=document.body;if(!body){window.setTimeout(applySonosTheme,0);return;}Array.prototype.slice.call(body.classList).forEach(function(cls){if(/^theme-/.test(cls)){body.classList.remove(cls);}});body.classList.add(themeClass);}applySonosTheme();})();</script>' . "\n";
    }

    my $theme_lc_for_wallpaper = s4l_effective_theme_lc();
    if ($theme_lc_for_wallpaper eq 'liquid-glass') {
        $head .= s4l_liquid_glass_wallpaper_style_tag();
    }

    $head .= '<link rel="stylesheet" href="/plugins/'.$lbpplugindir.'/LayoutUI/sonos_lbv4.css?v='.$sversion.'"/>';
    return $head;
}

##########################################################################
# Testing actions
##########################################################################
# The former external-file workflow was removed. url_tests.json is edited
# directly on this page and saved to the plugin config directory.

##########################################################################
# Plain log viewer for the Testing page links
##########################################################################
if (defined $q->{action} && $q->{action} eq 'view_testing_log') {
    my $type = defined $q->{type} ? $q->{type} : 'test';

    my $file = ($type eq 'sonos')
        ? $lbplogdir . "/" . $pluginlogfile
        : $testing_log_file;

    my $filename = ($type eq 'sonos') ? $pluginlogfile : 'regression_test.log';

    print "Content-Type: text/plain; charset=utf-8\n";
    print "Content-Disposition: inline; filename=\"$filename\"\n";
    print "Cache-Control: no-store, no-cache, must-revalidate\n\n";

    if (!-r $file) {
        print "Log file not found or not readable: $file\n";
        exit;
    }

    if (open(my $fh, '<:encoding(UTF-8)', $file)) {
        while (my $line = <$fh>) {
            print $line;
        }
        close($fh);
    } else {
        print "Could not open log file: $file ($!)\n";
    }
    exit;
}

##########################################################################
# JSON status endpoint for background regression tests
##########################################################################
if (defined $q->{action} && ($q->{action} eq 'testing_status' || $q->{action} eq 'status')) {
    print_testing_status_json();
    exit;
}

##########################################################################
# Init template / language
##########################################################################
inittemplate();
LOGSTART "Plugin GUI Testing";

if ($log->loglevel() eq "7") {
    $LoxBerry::System::DEBUG  = 1;
    $LoxBerry::Web::DEBUG     = 1;
    $LoxBerry::Log::DEBUG     = 1;
    $LoxBerry::IO::DEBUG      = 1;
}

$template->param("LBHOSTNAME", lbhostname());
$template->param("LBLANG", $lblang);
$template->param("SELFURL", $ENV{REQUEST_URI});
$template->param("PLUGINDIR" => $lbpplugindir);
$template->param("LOGFILE", $lbplogdir . "/" . $pluginlogfile);
$template->param("TESTING_SCRIPT_VERSION" => $testing_script_version);
$template->param("TESTING.JSON_HEADER_SOURCE" => html_escape($SL{'TESTING.JSON_HEADER_SOURCE'} || 'PL/Radio'));
# Backward compatibility for older sonos.html templates that still use JSON_HEADER_CATEGORY as the visible last column.
$template->param("TESTING.JSON_HEADER_CATEGORY" => html_escape($SL{'TESTING.JSON_HEADER_SOURCE'} || 'PL/Radio'));

if (!-r $lbpconfigdir . "/" . $configfile) {
    $error_message = 'Plugin config file does not exist.';
    LOGCRIT $error_message;
    notify($lbpplugindir, "Sonos UI Testing", $error_message, 1);
    error();
}

##########################################################################
# Navbar
##########################################################################
our %navbar;
$navbar{1}{Name} = $SL{'BASIS.MENU_SETTINGS'} || 'Settings';
$navbar{1}{URL}  = './index.cgi';
$navbar{2}{Name} = $SL{'BASIS.MENU_OPTIONS'} || 'Options';
$navbar{2}{URL}  = './index.cgi?do=details';
$navbar{3}{Name} = $SL{'BASIS.MENU_VOLUME'} || 'Volume';
$navbar{3}{URL}  = './index.cgi?do=volume';
$navbar{99}{Name} = $SL{'BASIS.MENU_LOGFILES'} || 'Logfiles';
$navbar{99}{URL}  = './index.cgi?do=logfiles';

my $mqttcred = LoxBerry::IO::mqtt_connectiondetails();
if ($mqttcred && ref($cfg) eq 'HASH' && ref($cfg->{LOXONE}) eq 'HASH' && ($cfg->{LOXONE}->{LoxDaten} // '') eq 'true') {
    $navbar{5}{Name} = $SL{'BASIS.MENU_MQTT'} || 'MQTT';
    if ($lbv < 3) {
        $navbar{5}{URL} = '/admin/plugins/mqttgateway/index.cgi';
    } else {
        $navbar{5}{URL} = '/admin/system/mqtt.cgi';
    }
    $navbar{5}{target} = '_blank';
}

$navbar{4}{Name}   = $SL{'BASIS.MENU_TESTING'} || 'Testing';
$navbar{4}{URL}    = './testing.cgi';
$navbar{4}{active} = 1;

##########################################################################
# Render Testing page
##########################################################################
$template->param("TESTING", "1");
testing();
printtemplate();

##########################################################################
# Testing page logic
##########################################################################
##########################################################################
# Testing file actions
##########################################################################
sub testing
{
    $template->param(FORMNO => 'TESTING');

    if ($lbv >= 4) {
        my $theme_lc = s4l_effective_theme_lc();
        $template->param("LBV4", 1);
        if ($theme_lc =~ /glass/ && $theme_lc ne "classic-mac") {
            $template->param("THEMEGLASS", 1);
        }
        if ($theme_lc eq "classic-mac") {
            $template->param("THEMEMAC", 1);
        }
    }

    my $testing_action    = scalar($cgi->param('testing_action'))    // '';
    my $selected_scenario = scalar($cgi->param('testing_scenario'))  // '';
    my $selected_zone     = scalar($cgi->param('testing_zone'))      // '';
    my @selected_members  = $cgi->param('testing_member');

    my $is_save_action    = ($testing_action eq 'save_json' || defined scalar($cgi->param('save_testing_json')));
    my $is_execute_action = ((defined $R::execute_testing && $R::execute_testing) || $testing_action eq 'execute');

    # Keep the description visible on initial page load and after saving the JSON editor.
    # Only hide it while a test execution result is displayed, so the execution output
    # remains the visual focus after a real regression run.
    if (!$is_execute_action) {
        $template->param("TESTING_SHOW_DESCRIPTION" => 1);
    }
    $template->param("TESTING_DESCRIPTION_TEXT" => html_escape(build_testing_description_text()));
		$template->param(
		"TESTING.JSON_EDIT_TESTS_LABEL" =>
		html_escape($SL{'TESTING.JSON_EDIT_TESTS_LABEL'} || 'Maintain Tests')
	);

    if ($is_save_action) {
        my $save_result_ref = save_testing_json_from_form($testing_json_file);
        $template->param("TESTING_JSON_SAVE_RESULT" => 1);

		if ($save_result_ref->{ok}) {
			$template->param("TESTING_JSON_SAVE_OK" => 1);
		} else {
			$template->param("TESTING_JSON_SAVE_FAILED" => 1);
			$template->param("TESTING_JSON_SAVE_TEXT" => html_escape(($SL{'TESTING.JSON_SAVE_FAILED'} || 'Could not save test definitions:') . ' ' . ($save_result_ref->{error} || 'unknown error')));
		}
        # Keep the execution selector clean after a save operation.
        $selected_scenario = '';
        $selected_zone     = '';
        @selected_members  = ();
    }

    my $testing_started_notice = scalar($cgi->param('testing_started')) // '';
    if ($testing_started_notice eq '1') {
        $template->param("TESTING_HAS_RESULT" => 1);
        $template->param("TESTING_RESULT_OK" => 1);
        $template->param("TESTING_RESULT_TEXT" => $SL{'TESTING.RESULT_STARTED_BACKGROUND'} || 'Test execution was started in the background. The browser page is released immediately to avoid a gateway timeout. Open the test log to follow the progress.');
    }

    my @online_rooms = get_online_player_rooms();
    my %online_rooms = map { $_ => 1 } @online_rooms;

    if ($selected_zone ne '' && !$online_rooms{$selected_zone}) {
        $selected_zone = '';
    }

    @selected_members = grep { defined $_ && $_ ne '' && $online_rooms{$_} } @selected_members;
    @selected_members = unique_values(@selected_members);

    # The selected Zone must never be passed as Member as well.
    # This is also enforced in the browser by JavaScript, but the CGI keeps
    # the server-side state clean when JavaScript is disabled or stale.
    if ($selected_zone ne '') {
        @selected_members = grep { lc($_) ne lc($selected_zone) } @selected_members;
    }

    my ($scenario_options_html, $scenario_map_ref) = build_testing_scenario_options($selected_scenario);
    my $json_tests_ref = read_testing_json_tests();

    $template->param("TESTING_SCENARIO_OPTIONS" => $scenario_options_html);
    $template->param("TESTING_ZONE_DROPDOWN"    => render_testing_player_dropdown(
        id          => 'testing_zone_details',
        input_name  => 'testing_zone',
        rooms       => \@online_rooms,
        selected    => ($selected_zone ne '' ? [$selected_zone] : []),
        single      => 1,
        placeholder => ($SL{'TESTING.SELECT_PLAYER'} || 'Please select player'),
    ));
    $template->param("TESTING_MEMBER_DROPDOWN"  => render_testing_player_dropdown(
        id          => 'testing_member_details',
        input_name  => 'testing_member',
        rooms       => \@online_rooms,
        selected    => \@selected_members,
        single      => 0,
        placeholder => ($SL{'TESTING.SELECT_PLAYER'} || 'Please select player'),
    ));

    $template->param("TESTING_TESTLOG_URL"      => "./testing.cgi?action=view_testing_log&type=test");
    $template->param("TESTING_SONOSLOG_URL"     => "./testing.cgi?action=view_testing_log&type=sonos");
    $template->param("TESTING_JSON_ROWS"        => build_testing_json_template_rows($json_tests_ref));
    $template->param("TESTING_JSON_COUNT"       => scalar(@{$json_tests_ref}));

    if ((defined $R::execute_testing && $R::execute_testing) || $testing_action eq 'execute') {
        LOGTITLE "Execute testing scenario";

        if ($selected_scenario eq '' || !exists $scenario_map_ref->{$selected_scenario}) {
            $template->param("TESTING_HAS_RESULT"   => 1);
            $template->param("TESTING_RESULT_FAILED" => 1);
            $template->param("TESTING_RESULT_TEXT"   => $SL{'TESTING.ERROR_SCENARIO_REQUIRED'} || 'Please select a test scenario.');
            return;
        }

        if ($selected_zone eq '') {
            $template->param("TESTING_HAS_RESULT"   => 1);
            $template->param("TESTING_RESULT_FAILED" => 1);
            $template->param("TESTING_RESULT_TEXT"   => $SL{'TESTING.ERROR_ZONE_REQUIRED'} || 'Please select one online zone.');
            return;
        }

        my $result_ref = start_testing_scenario_background(
            scenario => $scenario_map_ref->{$selected_scenario},
            zone     => $selected_zone,
            members  => \@selected_members,
        );

        if ($result_ref->{ok}) {
            print "Status: 303 See Other\r\n";
            print "Location: ./testing.cgi?testing_started=1\r\n";
            print "Cache-Control: no-store, no-cache, must-revalidate\r\n\r\n";
            exit;
        }

        $template->param("TESTING_HAS_RESULT" => 1);
        $template->param("TESTING_RESULT_FAILED" => 1);
        $template->param("TESTING_RESULT_TEXT" => ($SL{'TESTING.RESULT_FAILED'} || 'Test execution could not be started.') . " Exit code: " . $result_ref->{exit_code});
        $template->param("TESTING_RESULT_COMMAND" => html_escape($result_ref->{command}));
        $template->param("TESTING_RESULT_OUTPUT"  => html_escape($result_ref->{output}));
    }
}

sub get_online_player_rooms
{
    my @rooms;
    return @rooms if ref($cfg) ne 'HASH' || ref($cfg->{sonoszonen}) ne 'HASH';

    foreach my $room (sort keys %{$cfg->{sonoszonen}}) {
        my $zone = $cfg->{sonoszonen}->{$room};
        next unless ref($zone) eq 'ARRAY';

        # Online check requested by user.
        my $statusfile = $lbpdatadir . '/PlayerStatus/s4lox_on_' . $room . '.txt';
        next if !-e $statusfile;

        push @rooms, $room;
    }

    return @rooms;
}

sub build_testing_description_text
{
    my @lines;

    # General hints first
    push @lines, $SL{'TESTING.DESCRIPTION_HINT_ZONE'}
        if defined $SL{'TESTING.DESCRIPTION_HINT_ZONE'} && $SL{'TESTING.DESCRIPTION_HINT_ZONE'} ne '';

    push @lines, $SL{'TESTING.DESCRIPTION_HINT_MEMBER'}
        if defined $SL{'TESTING.DESCRIPTION_HINT_MEMBER'} && $SL{'TESTING.DESCRIPTION_HINT_MEMBER'} ne '';

    push @lines, $SL{'TESTING.DESCRIPTION_HINT_JSON'}
        if defined $SL{'TESTING.DESCRIPTION_HINT_JSON'} && $SL{'TESTING.DESCRIPTION_HINT_JSON'} ne '';

    push @lines, $SL{'TESTING.DESCRIPTION_HINT_WARNING'}
        if defined $SL{'TESTING.DESCRIPTION_HINT_WARNING'} && $SL{'TESTING.DESCRIPTION_HINT_WARNING'} ne '';

    push @lines, '' if @lines;

    # Available dropdown scenarios
    push @lines, $SL{'TESTING.DESCRIPTION_INTRO'}
        if defined $SL{'TESTING.DESCRIPTION_INTRO'} && $SL{'TESTING.DESCRIPTION_INTRO'} ne '';

    my @scenarios = get_testing_scenarios();

    foreach my $scenario (@scenarios) {
        next if ref($scenario) ne 'HASH';
        next if !defined $scenario->{id} || $scenario->{id} eq '';

        my $desc_key = 'TESTING.DESCRIPTION_SCENARIO_' . uc($scenario->{id});
        my $label    = $scenario->{label} || $scenario->{id};
        my $desc     = $SL{$desc_key} || '';

        if ($desc ne '') {
            push @lines, $label . ': ' . $desc;
        } else {
            push @lines, $label;
        }
    }

    return join("\n", @lines);
}

sub build_testing_scenario_options
{
    my ($selected_id) = @_;
    my @scenarios = get_testing_scenarios();
    my %map;
    my $html = "";

    $html .= "<option value=''>" . html_escape($SL{'TESTING.SELECT_SCENARIO'} || 'Please select test scenario') . "</option>\n";

    foreach my $scenario (@scenarios) {
        next if !$scenario->{id};
        $map{$scenario->{id}} = $scenario;
        my $selected = (defined $selected_id && $selected_id eq $scenario->{id}) ? " selected='selected'" : "";
        $html .= "<option value='" . html_escape($scenario->{id}) . "'$selected>" . html_escape($scenario->{label}) . "</option>\n";
    }

    return ($html, \%map);
}

sub get_testing_scenarios
{
    # Keep the Testing UI intentionally limited to the fixed regression modes.
    # url_tests.json remains the source for the individual test definitions,
    # but the UI should not add every JSON test/category/risk as a separate dropdown entry.
    my @scenarios = (
        { id => 'mode_safe',           label => ($SL{'TESTING.SCENARIO_SAFE'}            || 'Safe Tests (--mode=safe)'),                                      args => ['--mode=safe'] },
        { id => 'mode_all',            label => ($SL{'TESTING.SCENARIO_ALL'}             || 'Alle aktiven Tests (--mode=all)'),                              args => ['--mode=all'] },
        { id => 'category_status',     label => ($SL{'TESTING.SCENARIO_CATEGORY_STATUS'} || 'Kategorie Status (--mode=category:status)'),                    args => ['--mode=category:status'] },
        { id => 'risk_low',            label => ($SL{'TESTING.SCENARIO_RISK_LOW'}        || 'Risiko low (--mode=risk:low)'),                                 args => ['--mode=risk:low'] },
        { id => 'risk_middle',         label => ($SL{'TESTING.SCENARIO_RISK_MIDDLE'}     || 'Risiko middle (--mode=risk:middle)'),                           args => ['--mode=risk:middle'] },
        { id => 'risk_critical',       label => ($SL{'TESTING.SCENARIO_RISK_CRITICAL'}   || 'Risiko critical (--mode=risk:critical)'),                       args => ['--mode=risk:critical'] },
        { id => 'risk_low_middle',     label => ($SL{'TESTING.SCENARIO_RISK_LOW_MIDDLE'} || 'Risiko low + middle (--mode=risk:low,middle)'),                  args => ['--mode=risk:low,middle'] },
        { id => 'all_verbose',         label => ($SL{'TESTING.SCENARIO_ALL_VERBOSE'}     || 'Alle Tests mit Verbose-Logging (--mode=all --verbose)'),        args => ['--mode=all', '--verbose'] },
        { id => 'all_tail_25',         label => ($SL{'TESTING.SCENARIO_ALL_TAIL'}        || 'Alle Tests mit Sonos Tail (--mode=all --sonos-tail-lines=25)'), args => ['--mode=all', '--sonos-tail-lines=25'] },
        { id => 'all_no_sonos_blocks', label => ($SL{'TESTING.SCENARIO_ALL_NO_BLOCKS'}   || 'Alle Tests ohne Sonos Blockauswertung (--mode=all --no-sonos-blocks)'), args => ['--mode=all', '--no-sonos-blocks'] },
    );

    return @scenarios;
}

sub read_testing_json_tests
{
    my $json_file = $testing_json_file;
    my @tests;

    if (!-r $json_file) {
        LOGWARN "Testing JSON not readable: $json_file";
        return \@tests;
    }

    eval {
        open(my $fh, '<:encoding(UTF-8)', $json_file) or die $!;
        local $/;
        my $json_text = <$fh>;
        close($fh);

        my $data = JSON::PP->new->decode($json_text);
        if (ref($data) eq 'ARRAY') {
            foreach my $test (@{$data}) {
                next if ref($test) ne 'HASH';
                my $url    = normalize_testing_url_for_json(trim_string($test->{url} // ''));
                my $source = trim_string($test->{source} // '');

                ($url, $source) = normalize_testing_source_url_and_value($url, $source);

                push @tests, {
                    test_number => int($test->{test_number} || 0),
                    status      => trim_string($test->{status}      // 'active'),
                    name        => trim_string($test->{name}        // ''),
                    method      => trim_string($test->{method}      // 'GET'),
                    url         => $url,
                    source      => $source,
                    expect_http => int($test->{expect_http} || 200),
                    timeout_sec => trim_string($test->{timeout_sec} // ''),
                    risk        => trim_string($test->{risk}        // 'middle'),
                    category    => trim_string($test->{category}    // ''),
                };
            }
        }
        1;
    } or do {
        my $err = $@ || 'unknown error';
        LOGWARN "Could not read testing JSON tests: $err";
    };

    @tests = sort { ($a->{test_number} || 0) <=> ($b->{test_number} || 0) } @tests;
    return \@tests;
}

sub build_testing_json_template_rows
{
    my ($tests_ref) = @_;
    $tests_ref ||= [];

    my @rows;
    my $row_index = 0;
    foreach my $test (@{$tests_ref}) {
        next if ref($test) ne 'HASH';
        $row_index++;
        my $action = get_testing_url_action($test->{url});
        my $source_visible = is_testing_source_action($action) ? 1 : 0;
        my $source_value = $source_visible ? trim_string($test->{source} // '') : '';

        push @rows, {
            ROW_INDEX          => $row_index,
            TEST_NUMBER        => $row_index,
            STATUS_OPTIONS     => build_testing_select_options($test->{status}, qw(active inactive)),
            NAME               => html_escape($test->{name}),
            RISK_OPTIONS       => build_testing_select_options($test->{risk}, qw(low middle high critical safe info)),
            URL                => html_escape($test->{url}),
            SOURCE             => html_escape($source_value),
            SOURCE_VISIBLE     => $source_visible,
            SOURCE_STYLE       => ($source_visible ? '' : 'display:none;'),
            SOURCE_DISABLED    => ($source_visible ? '' : 'disabled="disabled"'),
            SOURCE_ROW_CLASS   => ($source_visible ? 'testing-json-source-visible' : 'testing-json-source-hidden'),
            TIMEOUT_SEC        => html_escape($test->{timeout_sec}),
            HIDDEN_CATEGORY    => html_escape($test->{category}),

            # Backward compatibility for older sonos.html templates that still bind the visible last column to CATEGORY.
            CATEGORY           => html_escape($source_value),
        };
    }

    return \@rows;
}

sub build_testing_select_options
{
    my ($selected, @values) = @_;
    $selected = trim_string($selected // '');

    my %known = map { $_ => 1 } @values;
    if ($selected ne '' && !$known{$selected}) {
        unshift @values, $selected;
    }

    my $html = '';
    foreach my $value (@values) {
        my $sel = ($selected eq $value) ? " selected='selected'" : '';
        $html .= "<option value='" . html_escape($value) . "'$sel>" . html_escape($value) . "</option>\n";
    }
    return $html;
}

sub normalize_testing_url_for_json
{
    my ($url) = @_;
    $url = trim_string($url // '');

    return '' if $url eq '';

    # Store only the query part in url_tests.json.
    # regression_test.pl adds the current LoxBerry host, port and
    # /plugins/sonos4lox/index.php/ endpoint at runtime.
    $url =~ s{^https?://[^/]+}{}i;
    $url =~ s{^//[^/]+}{}i;
    $url =~ s{^[A-Za-z0-9._-]+(?::\d+)?(?=/plugins/)}{}i;

    $url =~ s{^/?plugins/sonos4lox/index\.php/?\??}{}i;
    $url =~ s{^\?}{};
    $url =~ s{^&}{};

    return trim_string($url);
}

sub normalize_testing_source_url_and_value
{
    my ($url, $source) = @_;

    $url    = normalize_testing_url_for_json($url);
    $source = trim_string($source // '');

    my $action = get_testing_url_action($url);
    return ($url, '') if !is_testing_source_action($action);

    # If the URL already contains SOURCE, only keep the separate value.
    return ($url, $source) if testing_url_contains_source_placeholder($url);

    # Migration helper for old entries that still contain a concrete playlist/radio/favorite value.
    # The concrete value is moved to the JSON field "source" and the URL gets SOURCE.
    my @candidate_params = @{ $TESTING_SOURCE_PARAM_CANDIDATES{$action} || [] };

    foreach my $param (@candidate_params) {
        my $value = get_testing_url_query_parameter($url, $param);
        next if !defined $value;
        next if trim_string($value) eq '';

        $source = trim_string($value) if $source eq '';
        $url = set_testing_url_query_parameter_raw($url, $param, $TESTING_SOURCE_PLACEHOLDER);
        last;
    }

    return ($url, $source);
}

sub get_testing_url_action
{
    my ($url) = @_;
    my $action = get_testing_url_query_parameter($url, 'action');
    $action = lc(trim_string($action // ''));
    return $action;
}

sub is_testing_source_action
{
    my ($action) = @_;
    $action = lc(trim_string($action // ''));
    return $TESTING_SOURCE_ACTION{$action} ? 1 : 0;
}

sub testing_url_contains_source_placeholder
{
    my ($url) = @_;
    $url = trim_string($url // '');
    return ($url =~ /(?:^|[=&?])\Q$TESTING_SOURCE_PLACEHOLDER\E(?:[&#]|$)/) ? 1 : 0;
}

sub get_testing_url_query_parameter
{
    my ($url, $param) = @_;

    $url   = trim_string($url // '');
    $param = trim_string($param // '');

    return undef if $url eq '' || $param eq '';

    if ($url =~ /(?:^|[?&])\Q$param\E=([^&#]*)/i) {
        my $value = $1;
        $value =~ tr/+/ /;
        $value = uri_unescape($value);
        return trim_string($value);
    }

    return undef;
}

sub set_testing_url_query_parameter_raw
{
    my ($url, $param, $value) = @_;

    $url   = trim_string($url // '');
    $param = trim_string($param // '');
    $value = trim_string($value // '');

    return $url if $url eq '' || $param eq '';

    if ($url =~ /(^|[?&])\Q$param\E=[^&#]*/i) {
        $url =~ s/(^|[?&])\Q$param\E=[^&#]*/$1$param=$value/i;
        return $url;
    }

    my ($base, $fragment) = split(/#/, $url, 2);
    my $separator = ($base =~ /\?/) ? '&' : '&';
    $base .= $separator . $param . '=' . $value;
    $base =~ s/^&//;

    return defined $fragment ? $base . '#' . $fragment : $base;
}

sub category_from_testing_url_action
{
    my ($action) = @_;
    $action = lc(trim_string($action // ''));

    return 'Playlist' if $action eq 'sonosplaylist';
    return 'Radio'    if $action eq 'pluginradio';
    return 'Playlist' if $action eq 'playfavorite';
    return 'TTS'      if $action eq 'say';
    return 'Control'  if $action =~ /^(play|pause|stop|toggle|volumeup|volumedown|setbass|settreble|setloudness|crossfade)$/;
    return 'Function' if $action =~ /^(zapzone|nextpush|nextradio|addmember|removemember)$/;

    return 'General';
}

sub save_testing_json_from_form
{
    my ($file) = @_;

    my $count = int(scalar($cgi->param('testing_json_count')) || 0);
    my $existing_tests_ref = read_testing_json_tests();
    my @tests;
    my $test_number = 1;

    for (my $i = 1; $i <= $count; $i++) {
        my $status      = trim_string(scalar($cgi->param("testing_status_$i"))      // '');
        my $name        = trim_string(scalar($cgi->param("testing_name_$i"))        // '');
        my $risk        = trim_string(scalar($cgi->param("testing_risk_$i"))        // '');
        my $url         = normalize_testing_url_for_json(trim_string(scalar($cgi->param("testing_url_$i"))         // ''));
        my $timeout_sec = trim_string(scalar($cgi->param("testing_timeout_$i"))     // '');

        my $posted_last_column = trim_string(scalar($cgi->param("testing_category_$i")) // '');
        my $has_source_field   = defined scalar($cgi->param("testing_source_$i"));
        my $posted_source      = $has_source_field ? trim_string(scalar($cgi->param("testing_source_$i")) // '') : '';

        my $existing_category = '';
        if (ref($existing_tests_ref) eq 'ARRAY' && ref($existing_tests_ref->[$i - 1]) eq 'HASH') {
            $existing_category = trim_string($existing_tests_ref->[$i - 1]->{category} // '');
        }

        my $action = get_testing_url_action($url);
        my $is_source_action = is_testing_source_action($action);

        my $source = '';
        my $category = '';

        if ($has_source_field) {
            # New template: testing_source_* is the visible PL/Radio field and testing_category_* is hidden.
            $source   = $posted_source;
            $category = $posted_last_column ne '' ? $posted_last_column : $existing_category;
        } else {
            # Backward compatibility: older templates still post the last visible column as testing_category_*.
            # For SOURCE actions that visible field is treated as PL/Radio.
            if ($is_source_action) {
                $source   = $posted_last_column;
                $category = $existing_category;
            } else {
                $source   = '';
                $category = $posted_last_column ne '' ? $posted_last_column : $existing_category;
            }
        }

        ($url, $source) = normalize_testing_source_url_and_value($url, $source);

        # Deleted rows no longer submit their fields. Completely empty rows are ignored.
        next if $status eq '' && $name eq '' && $risk eq '' && $url eq '' && $timeout_sec eq '' && $category eq '' && $source eq '';

        $status = lc($status);
        $status = 'active' if $status ne 'active' && $status ne 'inactive';

        $risk = lc($risk);
        $risk =~ s/\s+/_/g;
        $risk = 'middle' if $risk eq '';

        $name = 'Unnamed test' if $name eq '';
        $category = category_from_testing_url_action($action) if $category eq '';
        $category = 'General' if $category eq '';
        $source = '' if !$is_source_action;

        $timeout_sec =~ s/[^0-9]//g;
        if ($timeout_sec ne '') {
            $timeout_sec = 1 if int($timeout_sec) < 1;
            $timeout_sec = 300 if int($timeout_sec) > 300;
        }

        push @tests, {
            test_number => $test_number++,
            status      => $status,
            name        => $name,
            method      => 'GET',
            url         => $url,
            source      => $source,
            expect_http => 200,
            timeout_sec => ($timeout_sec eq '' ? '' : int($timeout_sec)),
            risk        => $risk,
            category    => $category,
        };
    }

    eval {
        write_testing_json_file_atomic($file, \@tests);
        1;
    } or do {
        my $err = $@ || 'unknown error';
        chomp $err;
        LOGERR "Could not save url_tests.json: $err";
        return { ok => 0, error => $err };
    };

    LOGOK "url_tests.json saved successfully: $file (" . scalar(@tests) . " tests)";
    return { ok => 1, count => scalar(@tests) };
}

sub write_testing_json_file_atomic
{
    my ($file, $tests_ref) = @_;

    my $dir = $lbpconfigdir;
    if (!-d $dir) {
        make_path($dir) or die "Could not create directory $dir: $!";
    }

    my $backup = $file . '.bak';
    my $tmp    = $file . '.tmp';

    if (-e $file) {
        copy($file, $backup) or die "Could not create backup $backup: $!";
    }

    my $json_text = encode_testing_tests_fixed_order($tests_ref);

    open(my $fh, '>:encoding(UTF-8)', $tmp) or die "Could not write $tmp: $!";
    print {$fh} $json_text;
    close($fh) or die "Could not close $tmp: $!";

    rename($tmp, $file) or die "Could not replace $file with $tmp: $!";
}

sub encode_testing_tests_fixed_order
{
    my ($tests_ref) = @_;
    $tests_ref ||= [];

    my $encoder = JSON::PP->new->allow_nonref;
    my @blocks;

    foreach my $test (@{$tests_ref}) {
        next if ref($test) ne 'HASH';
        my @lines = (
            "    \"test_number\": " . int($test->{test_number} || 0),
            "    \"status\": "      . $encoder->encode($test->{status}      // 'active'),
            "    \"name\": "        . $encoder->encode($test->{name}        // ''),
            "    \"method\": "      . $encoder->encode($test->{method}      // 'GET'),
            "    \"url\": "         . $encoder->encode($test->{url}         // ''),
            "    \"source\": "      . $encoder->encode($test->{source}      // ''),
            "    \"expect_http\": " . int($test->{expect_http} || 200),
        );

        my $timeout_sec = trim_string($test->{timeout_sec} // '');
        $timeout_sec =~ s/[^0-9]//g;

        # Optional: timeout_sec now means delay after this test in seconds.
        # If empty/missing, regression_test.pl uses the default delay of 1 second.
        if ($timeout_sec ne '') {
            $timeout_sec = 1 if int($timeout_sec) < 1;
            $timeout_sec = 300 if int($timeout_sec) > 300;
            push @lines, "    \"timeout_sec\": " . int($timeout_sec);
        }

        push @lines, (
            "    \"risk\": "        . $encoder->encode($test->{risk}        // 'middle'),
            "    \"category\": "    . $encoder->encode($test->{category}    // 'General'),
        );

        push @blocks, "  {\n" . join(",\n", @lines) . "\n  }";
    }

    return "[\n" . join(",\n", @blocks) . "\n]\n";
}

sub make_testing_id
{
    my ($value) = @_;
    $value = lc(trim_string($value // ''));
    $value =~ s/[^a-z0-9]+/_/g;
    $value =~ s/^_+|_+$//g;
    return $value || 'value';
}

sub render_testing_player_dropdown
{
    my (%args) = @_;
    my $id           = $args{id};
    my $input_name   = $args{input_name};
    my $rooms_ref    = $args{rooms}    || [];
    my $selected_ref = $args{selected} || [];
    my $single       = $args{single} ? 1 : 0;
    my $placeholder  = $args{placeholder} || 'Please select player';

    my %selected = map { $_ => 1 } @{$selected_ref};
    my @selected_labels = grep { $selected{$_} } @{$rooms_ref};
    my $summary = @selected_labels ? join(', ', @selected_labels) : $placeholder;

    my $html = "<details id='" . html_escape($id) . "' class='testing_player_details' data-single='$single' data-placeholder='" . html_escape($placeholder) . "'>\n";
    $html .= "<summary>" . html_escape($summary) . "</summary>\n";
    $html .= "<div class='testing_player_details_content'>\n";

    if (!@{$rooms_ref}) {
        $html .= "<div class='testing_no_players'>" . html_escape($SL{'TESTING.NO_ONLINE_PLAYERS'} || 'No online players available') . "</div>\n";
    } else {
        foreach my $room (@{$rooms_ref}) {
            my $checked = $selected{$room} ? " checked='checked'" : "";
            $html .= "<label class='testing_player_check_label'>";
            $html .= "<input type='checkbox' class='testing_player_checkbox' name='" . html_escape($input_name) . "' value='" . html_escape($room) . "'$checked data-role='none'> ";
            $html .= html_escape($room);
            $html .= "</label>\n";
        }
    }

    $html .= "</div>\n";
    $html .= "</details>\n";
    return $html;
}

sub start_testing_scenario_background
{
    my (%args) = @_;
    my $scenario = $args{scenario};
    my $zone     = trim_string($args{zone} // '');
    my @members  = @{ $args{members} || [] };

    # Safety net: do not pass the selected Zone as Member.
    if ($zone ne '') {
        @members = grep { defined $_ && $_ ne '' && lc($_) ne lc($zone) } @members;
        @members = unique_values(@members);
    }

    my $tests_dir = $lbphtmldir . '/src/Support/Testing';
    my $script    = $tests_dir . '/regression_test.pl';

    if (!-r $script) {
        return {
            ok        => 0,
            exit_code => 127,
            command   => $script,
            output    => "Test script not readable: $script",
        };
    }

    my @cmd = ($^X, $script, "--config=$testing_json_file", "--log=$testing_log_file", @{ $scenario->{args} || [] });
    push @cmd, "--zone=$zone" if $zone ne '';

    my $member_string = join(',', @members);
    push @cmd, "--member=$member_string" if $member_string ne '';

    my $command = join(' ', map { shell_display_quote($_) } @cmd);
    my $token = time() . '-' . $$ . '-' . int(rand(100000));
    my $status_ref = {
        state      => 'running',
        result     => '',
        token      => $token,
        pid        => 0,
        started_at => strftime('%Y-%m-%d %H:%M:%S', localtime),
        updated_at => strftime('%Y-%m-%d %H:%M:%S', localtime),
        command    => $command,
        test_log   => $testing_log_file,
        sonos_log  => $lbplogdir . '/' . $pluginlogfile,
        message    => 'Regression test is running in the background.',
    };

    my $pid = start_background_command($tests_dir, $status_ref, @cmd);

    if (!$pid) {
        return {
            ok        => 0,
            exit_code => 127,
            command   => $command,
            output    => "Could not start background test command.",
        };
    }

    $status_ref->{pid} = $pid;
    write_testing_status_file($status_ref);

    LOGINF "Testing command started in background: $command (pid $pid)";

    return {
        ok        => 1,
        exit_code => 0,
        command   => $command,
        output    => "Background test process started. PID: $pid\nTest log: $testing_log_file\nSonos log: " . $lbplogdir . "/" . $pluginlogfile . "\nThe test continues even if this browser page is closed or refreshed.",
    };
}

sub start_background_command
{
    my ($workdir, $status_ref, @cmd) = @_;

    my $pid = fork();
    if (!defined $pid) {
        LOGERR "Could not fork background test command: $!";
        return 0;
    }

    if ($pid) {
        return $pid;
    }

    # Child process: detach from the CGI request so long regression runs do not
    # keep the web request open and do not trigger a reverse proxy timeout.
    chdir $workdir or do {
        _write_launcher_log("Could not change to test directory '$workdir': $!");
        exit 127;
    };

    eval { POSIX::setsid(); };

    open(STDIN, '<', '/dev/null');
    open(STDOUT, '>>:encoding(UTF-8)', $testing_launcher_log_file);
    open(STDERR, '>&', STDOUT);

    print "\n" . strftime('%d-%m-%Y %H:%M:%S', localtime) . " Background regression test started.\n";
    print "Command: " . join(' ', map { shell_display_quote($_) } @cmd) . "\n";

    if (ref($status_ref) eq 'HASH') {
        $status_ref->{pid} = $$;
        $status_ref->{updated_at} = strftime('%Y-%m-%d %H:%M:%S', localtime);
        write_testing_status_file($status_ref);
    }

    my $rc = system @cmd;
    my $exit_code = ($rc == -1) ? 127 : (($rc & 127) ? 128 + ($rc & 127) : ($rc >> 8));

    my $finished_ref = build_testing_finished_status($status_ref, $exit_code);
    write_testing_status_file($finished_ref);

    print strftime('%d-%m-%Y %H:%M:%S', localtime) . " Background regression test finished with exit code $exit_code.\n";
    exit $exit_code;
}

sub print_testing_status_json
{
    my $status_ref = read_testing_status_file();
    if (!$status_ref) {
        $status_ref = {
            state      => 'idle',
            result     => '',
            token      => '',
            message    => 'No regression test is running.',
            updated_at => strftime('%Y-%m-%d %H:%M:%S', localtime),
        };
    }

    print "Content-Type: application/json; charset=utf-8\n";
    print "Cache-Control: no-store, no-cache, must-revalidate\n\n";
    print JSON::PP->new->utf8->canonical->encode($status_ref);
}

sub read_testing_status_file
{
    return undef if !-r $testing_status_file;
	
	my $fh;
	my $content = '';
	
    if (!open($fh, '<:encoding(UTF-8)', $testing_status_file)) {
        return undef;
    }
    local $/;
    $content = <$fh> // '';
    close($fh);

    my $status_ref = eval { JSON::PP::decode_json($content) };
    return undef if $@ || ref($status_ref) ne 'HASH';
    return $status_ref;
}

sub write_testing_status_file
{
    my ($status_ref) = @_;
    return if ref($status_ref) ne 'HASH';

    eval { make_path($testing_status_dir) if !-d $testing_status_dir; };
    return if !-d $testing_status_dir;

    $status_ref->{updated_at} = strftime('%Y-%m-%d %H:%M:%S', localtime);

    my $json = eval { JSON::PP->new->utf8->canonical->pretty->encode($status_ref) };
    return if $@ || !defined $json;

    my $tmp = $testing_status_file . '.' . $$ . '.tmp';
    if (open(my $fh, '>:encoding(UTF-8)', $tmp)) {
        print {$fh} $json;
        close($fh);
        rename($tmp, $testing_status_file);
    }
}

sub build_testing_finished_status
{
    my ($status_ref, $exit_code) = @_;
    $status_ref = {} if ref($status_ref) ne 'HASH';

    my $summary_ref = parse_testing_log_summary($testing_log_file);
    my $failed  = defined $summary_ref->{failed}  ? int($summary_ref->{failed})  : undef;
    my $timeout = defined $summary_ref->{timeout} ? int($summary_ref->{timeout}) : undef;

    my $result = 'failed';
    if ($exit_code == 0 && defined $failed && defined $timeout && $failed == 0 && $timeout == 0) {
        $result = 'ok';
    } elsif ($exit_code == 0 && !defined $failed && !defined $timeout) {
        $result = 'ok';
    }

    my $message = ($result eq 'ok')
        ? 'Regression test finished successfully.'
        : 'Regression test finished with errors. Please open the regression test log for details.';

    return {
        %{$status_ref},
        state       => 'finished',
        result      => $result,
        exit_code   => $exit_code,
        finished_at => strftime('%Y-%m-%d %H:%M:%S', localtime),
        message     => $message,
        ok          => $summary_ref->{ok},
        failed      => $summary_ref->{failed},
        timeout     => $summary_ref->{timeout},
        skipped     => $summary_ref->{skipped},
        selected    => $summary_ref->{selected},
        total       => $summary_ref->{total},
        duration_ms => $summary_ref->{duration_ms},
    };
}

sub parse_testing_log_summary
{
    my ($file) = @_;
    my %summary;
    return \%summary if !-r $file;

    my $content = '';
    if (open(my $fh, '<:encoding(UTF-8)', $file)) {
        local $/;
        $content = <$fh> // '';
        close($fh);
    }

    while ($content =~ /Total tests in JSON:\s*(\d+)/g) { $summary{total} = int($1); }
    while ($content =~ /Selected tests:\s*(\d+)/g)    { $summary{selected} = int($1); }
    while ($content =~ /OK:\s*(\d+)/g)                { $summary{ok} = int($1); }
    while ($content =~ /FAILED:\s*(\d+)/g)            { $summary{failed} = int($1); }
    while ($content =~ /TIMEOUT:\s*(\d+)/g)           { $summary{timeout} = int($1); }
    while ($content =~ /Inactive skipped:\s*(\d+)/g)  { $summary{skipped} = int($1); }
    while ($content =~ /Duration:\s*(\d+)\s*ms/g)     { $summary{duration_ms} = int($1); }

    return \%summary;
}

sub _write_launcher_log
{
    my ($message) = @_;
    if (open(my $fh, '>>:encoding(UTF-8)', $testing_launcher_log_file)) {
        print {$fh} strftime('%d-%m-%Y %H:%M:%S', localtime) . " $message\n";
        close($fh);
    }
}

sub run_command_capture
{
    my ($workdir, @cmd) = @_;
    my $output = '';
    my $pid = open(my $fh, '-|');

    if (!defined $pid) {
        return (127, "Could not fork test command: $!");
    }

    if ($pid == 0) {
        chdir $workdir or do {
            print "Could not change to test directory '$workdir': $!\n";
            exit 127;
        };
        open(STDERR, '>&', STDOUT);
        exec @cmd;
        print "Could not execute test command: $!\n";
        exit 127;
    }

    while (my $line = <$fh>) {
        # Keep page output bounded but always drain the pipe to avoid blocking the child.
        $output .= $line if length($output) < 20000;
    }

    close($fh);
    my $status = $?;
    my $exit_code = ($status == -1) ? 127 : ($status >> 8);
    return ($exit_code, $output);
}

##########################################################################
# Helpers
##########################################################################
sub unique_values
{
    my @values = @_;
    my %seen;
    my @unique;
    foreach my $value (@values) {
        next if !defined $value || $value eq '';
        next if $seen{$value}++;
        push @unique, $value;
    }
    return @unique;
}

sub shell_display_quote
{
    my ($value) = @_;
    $value = '' if !defined $value;
    return $value if $value =~ /^[A-Za-z0-9_\.\/\:\=\-\,]+$/;
    $value =~ s/'/'"'"'/g;
    return "'$value'";
}

sub trim_string
{
    my ($value) = @_;
    $value = '' if !defined $value;
    $value =~ s/^\s+//;
    $value =~ s/\s+$//;
    return $value;
}

sub html_escape
{
    my ($value) = @_;
    $value = '' if !defined $value;
    $value =~ s/&/&amp;/g;
    $value =~ s/</&lt;/g;
    $value =~ s/>/&gt;/g;
    $value =~ s/"/&quot;/g;
    $value =~ s/'/&#39;/g;
    return $value;
}

##########################################################################
# Template / output / error
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
        filename          => $lbptemplatedir . "/" . $maintemplatefilename,
        global_vars       => 1,
        loop_context_vars => 1,
        die_on_bad_params => 0,
        associate         => $jsonobj,
        %htmltemplate_options
    );

    %SL = LoxBerry::System::readlanguage($template, $languagefile);
}

sub printtemplate
{
    if ($lbv < 4) {
        LoxBerry::Web::lbheader(($SL{'BASIS.MAIN_TITLE'} || 'Sonos4Lox') . ": v$sversion", $helplink, $helptemplatefilename);
    } else {
        LoxBerry::Web::lbheader(($SL{'BASIS.MAIN_TITLE'} || 'Sonos4Lox') . ": v$sversion", $helplink, $helptemplatefilename, 1);
    }

    print LoxBerry::Log::get_notifications_html($lbpplugindir);
    print $template->output();
    LoxBerry::Web::lbfooter();
    exit;
}

sub error
{
    if (!$template) {
        print "Content-Type: text/plain; charset=utf-8\n\n";
        print $error_message || 'Unknown error';
        exit;
    }

    $template->param("TESTING", "1");
    $template->param("TESTING_HAS_RESULT"   => 1);
    $template->param("TESTING_RESULT_FAILED" => 1);
    $template->param("TESTING_RESULT_TEXT"   => $error_message || 'Unknown error');
    printtemplate();
}

##########################################################################
# END routine
##########################################################################
sub END
{
    our @reason;
    return if !$log;

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
