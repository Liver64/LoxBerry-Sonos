#!/usr/bin/perl

use strict;
use warnings;
use utf8;

use Getopt::Long;
use JSON::PP ();
use LWP::UserAgent;
use Time::HiRes qw(time sleep);
use Time::Local qw(timelocal);
use POSIX qw(strftime);
use File::Basename qw(dirname);
use File::Path qw(make_path);
use Encode qw(decode encode);
use URI::Escape qw(uri_escape_utf8 uri_unescape);

use lib "/opt/loxberry/libs/perllib";
use LoxBerry::System ();
use LoxBerry::Web ();

binmode STDOUT, ":encoding(UTF-8)";
binmode STDERR, ":encoding(UTF-8)";

############################################################
# regression_test.pl
#
# Regression / smoke test runner for Sonos4Lox URL tests.
#
# Notes:
# - JSON field timeout_sec is optional and now means delay after this test in seconds.
# - If timeout_sec is missing or empty, the default delay before the next test is 1 second.
# - HTTP client timeouts are calculated internally based on category/action/risk.
# - Client timeouts are logged as TIMEOUT and not as misleading HTTP 500.
# - Tests can be selected by risk using --mode=risk:<name>.
# - Failed tests are summarized by risk and error class.
# - Related sonos.log blocks for failed tests are logged automatically at the end.
# - The runtime host/IP and web server port are detected from LoxBerry.
#   The host part from url_tests.json is not used as a fixed target.
# - Preferred JSON URL format is now query-only, e.g. "action=...&zone=...".
#
# Expected JSON fields:
# [
#   {
#     "test_number": 1,
#     "status": "active",
#     "name": "Load Playlist only",
#     "method": "GET",
#     "url": "action=...&zone=...",
#     "expect_http": 200,
#     "timeout_sec": 1,
#     "risk": "safe",
#     "category": "status"
#   }
# ]
#
# Examples:
#   perl regression_test.pl --mode=safe
#   perl regression_test.pl --mode=all
#   perl regression_test.pl --mode=category:status
#   perl regression_test.pl --mode=risk:low
#   perl regression_test.pl --mode=risk:middle
#   perl regression_test.pl --mode=risk:critical
#   perl regression_test.pl --mode=risk:low,middle
#   perl regression_test.pl --mode=all --verbose
#   perl regression_test.pl --mode=all --sonos-tail-lines=25
#   perl regression_test.pl --mode=all --no-sonos-blocks
#
############################################################

############################################################
# Defaults (changeable)
############################################################

# Version: REGRESSION_TEST_V14_2026_06_07_DELAY_TIMEOUT_FIELD
# V14:
# - JSON timeout_sec no longer controls the HTTP client timeout.
# - JSON timeout_sec is now the pause after this test before the next test starts.
# - Missing/empty timeout_sec uses the default pause of 1 second.
# - HTTP client timeouts stay internally calculated from action/category/risk.
#
# V13:
# - Supports the JSON field "source" for playlist/radio/favorite tests.
# - Literal SOURCE placeholders in the URL are replaced at runtime.
# - The replacement is URL-encoded, so names with spaces or special characters are safe.
#
# V12:
# - url_tests.json may store only the query string, e.g. action=...&zone=...
# - The current LoxBerry host, port and Sonos4Lox endpoint are added at runtime.
# - Old absolute or relative /plugins/sonos4lox/index.php URLs remain supported.
my $SCRIPT_VERSION       = "REGRESSION_TEST_V14_2026_06_07_DELAY_TIMEOUT_FIELD";
my $DEFAULT_BASE_DIR     = "/opt/loxberry/config/plugins/sonos4lox";
my $DEFAULT_JSON_FILE    = "$DEFAULT_BASE_DIR/url_tests.json";
my $DEFAULT_TEST_LOG     = "/opt/loxberry/log/plugins/sonos4lox/regression_test.log";
my $SONOS_LOG_FILE       = "/opt/loxberry/log/plugins/sonos4lox/sonos.log";
my $DEFAULT_TEST_ENDPOINT_PATH = "/plugins/sonos4lox/index.php/";
my $SOURCE_PLACEHOLDER = "SOURCE";

my $DEFAULT_MODE       = "safe";
my $DEFAULT_HTTP_CODE       = 200;
my $DEFAULT_HTTP_TIMEOUT    = 3;
my $DEFAULT_METHOD          = "GET";

# JSON field timeout_sec means: pause after this test before the next test starts.
# If timeout_sec is missing/empty in url_tests.json, this default is used.
my $DEFAULT_DELAY_BETWEEN_TESTS_MS = 1000;

# Extra grace pause after a client timeout. The PHP/Sonos process may still be running.
my $DEFAULT_DELAY_AFTER_TIMEOUT_MS = 1500;

# Response sample length for failed tests.
my $DEFAULT_RESPONSE_SAMPLE_LEN = 4000;

# Optional Sonos log tail for failed tests.
my $DEFAULT_SONOS_TAIL_LINES = 0;

# Automatically append matching sonos.log blocks for failed tests at the end.
my $DEFAULT_SONOS_BLOCKS_ON_FAILURE = 1;

############################################################
# Command line options
############################################################

my $mode                    = $DEFAULT_MODE;
my $config_file             = $DEFAULT_JSON_FILE;
my $test_log_file           = $DEFAULT_TEST_LOG;
my $verbose                 = 0;
my $keep_sonos_log          = 0;
my $include_inactive        = 0;
my $help                    = 0;
my $delay_between_tests_ms  = $DEFAULT_DELAY_BETWEEN_TESTS_MS;
my $delay_after_timeout_ms  = $DEFAULT_DELAY_AFTER_TIMEOUT_MS;
my $response_sample_len     = $DEFAULT_RESPONSE_SAMPLE_LEN;
my $sonos_tail_lines        = $DEFAULT_SONOS_TAIL_LINES;
my $sonos_blocks_on_failure = $DEFAULT_SONOS_BLOCKS_ON_FAILURE;
my $selected_zone           = "";
my $selected_members        = "";
my $selected_test_number    = "";

GetOptions(
    "mode=s"                   => \$mode,
    "config=s"                 => \$config_file,
    "log=s"                    => \$test_log_file,
    "verbose"                  => \$verbose,
    "keep-sonos-log"           => \$keep_sonos_log,
    "include-inactive"         => \$include_inactive,
    "delay-ms=i"               => \$delay_between_tests_ms,
    "timeout-grace-ms=i"       => \$delay_after_timeout_ms,
    "response-sample-len=i"    => \$response_sample_len,
    "sonos-tail-lines=i"       => \$sonos_tail_lines,
    "sonos-blocks!"            => \$sonos_blocks_on_failure,
    "sonos-blocks-on-failure!" => \$sonos_blocks_on_failure,
    "zone=s"                   => \$selected_zone,
    "member=s"                 => \$selected_members,
    "test-number=s"            => \$selected_test_number,
    "help"                     => \$help,
) or usage_and_exit(1);

usage_and_exit(0) if $help;

$selected_zone    = rt_trim($selected_zone);
$selected_members = normalize_member_override($selected_members, $selected_zone);

############################################################
# Startup
############################################################

ensure_parent_dir($test_log_file);

# Important: delete the actually configured test log, not only the default one.
unlink($test_log_file);

open(my $LOG, ">>:encoding(UTF-8)", $test_log_file)
    or die "Cannot open test log '$test_log_file': $!\n";

log_msg("INFO", "------------------------------------------------------------");
log_msg("INFO", "URL regression test started.");
log_msg("INFO", "Version: $SCRIPT_VERSION");
log_msg("INFO", "Mode: $mode");
log_msg("INFO", "Config file: $config_file");
log_msg("INFO", "Test log: $test_log_file");
log_msg("INFO", "Default HTTP timeout: $DEFAULT_HTTP_TIMEOUT sec");
log_msg("INFO", "Default delay if JSON timeout_sec is empty: $delay_between_tests_ms ms");
log_msg("INFO", "Delay after timeout: $delay_after_timeout_ms ms");
log_msg("INFO", "Response sample length: $response_sample_len chars");
log_msg("INFO", "Sonos log tail lines on failure: $sonos_tail_lines");
my $runtime_base_url     = build_runtime_base_url();
my $runtime_endpoint_url = build_runtime_endpoint_url($runtime_base_url);

log_msg("INFO", "Sonos log block extraction on failure: " . ($sonos_blocks_on_failure ? "enabled" : "disabled"));
log_msg("INFO", "Runtime base URL: $runtime_base_url");
log_msg("INFO", "Runtime endpoint URL: $runtime_endpoint_url");
log_msg("INFO", "Zone override: " . ($selected_zone ne "" ? $selected_zone : "not set"));
log_msg("INFO", "Member override: " . ($selected_members ne "" ? $selected_members : "not set"));
log_msg("INFO", "Selected test number: " . ($selected_test_number ne "" ? $selected_test_number : "not set"));

if (!$keep_sonos_log) {
    reset_sonos_log();
} else {
    log_msg("INFO", "Keeping existing sonos.log because --keep-sonos-log was used.");
}

my $tests = load_tests($config_file);

my $ua = LWP::UserAgent->new;
$ua->agent("Sonos4Lox-URL-Regression-Test/1.7-V14");
$ua->env_proxy;

############################################################
# Counters
############################################################

my $count_total      = 0;
my $count_selected   = 0;
my $count_ok         = 0;
my $count_failed     = 0;
my $count_timeout    = 0;
my $count_skipped    = 0;
my $count_inactive   = 0;

# Risk/statistics counters.
my %selected_by_risk;
my %ok_by_risk;
my %failed_by_risk;
my %timeout_by_risk;

# Error classification counters.
my %failed_by_result_type;
my %failed_by_error_class;
my %failed_by_risk_and_error_class;

# Store failed tests so we can append related sonos.log blocks at the end.
my @failed_results;

my $suite_start = time();

############################################################
# Execute tests
############################################################

foreach my $test (@{$tests}) {

    $count_total++;

    my $test_number        = safe_value($test->{test_number}, $count_total);
    my $name               = safe_value($test->{name}, "Unnamed test");
    my $status             = lc(rt_trim(safe_value($test->{status}, "")));
    my $risk               = normalize_risk(safe_value($test->{risk}, ""));
    my $category           = lc(rt_trim(safe_value($test->{category}, "")));
    my $method             = uc(rt_trim(safe_value($test->{method}, $DEFAULT_METHOD)));
    my $url                = rt_trim(safe_value($test->{url}, ""));
    my $source             = rt_trim(safe_value($test->{source}, ""));
    my $expect_http          = safe_number($test->{expect_http}, $DEFAULT_HTTP_CODE);
    my $configured_timeout   = $DEFAULT_HTTP_TIMEOUT;
    my $delay_after_test_ms  = delay_ms_from_json_timeout_sec($test->{timeout_sec}, $delay_between_tests_ms);

    if (!$include_inactive && $status ne "active") {
        $count_inactive++;
        next;
    }

    if ($selected_test_number ne "" && "$test_number" ne "$selected_test_number") {
        $count_skipped++;
        next;
    }

    if (!test_matches_mode($mode, $risk, $category)) {
        $count_skipped++;
        next;
    }

    if ($url eq "") {
        $count_skipped++;
        log_msg("WARNING", "SKIPPED test #$test_number '$name' - URL is empty.");
        next;
    }

    if (url_contains_source_placeholder($url) && $source eq "") {
        $count_skipped++;
        log_msg("WARNING", "SKIPPED test #$test_number '$name' - URL contains $SOURCE_PLACEHOLDER but JSON field source is empty.");
        next;
    }

    if ($method ne "GET") {
        $count_skipped++;
        log_msg("WARNING", "SKIPPED test #$test_number '$name' - unsupported method '$method'. Only GET is implemented.");
        next;
    }

    $count_selected++;
    $selected_by_risk{$risk}++;

    $url = apply_source_placeholder($url, $source);
    $url = apply_runtime_base_url($url, $runtime_base_url);
    $url = apply_runtime_url_parameters(
        url    => $url,
        zone   => $selected_zone,
        member => $selected_members,
    );
    $url = normalize_url($url);

    if ($verbose) {
        log_msg("INFO", "Test #$test_number '$name' runtime URL: $url");
    }

    my $effective_timeout = calculate_effective_timeout(
        configured_timeout => $configured_timeout,
        category           => $category,
        risk               => $risk,
        name               => $name,
        url                => $url,
    );

    if ($verbose && $effective_timeout != $configured_timeout) {
        log_msg(
            "INFO",
            "Test #$test_number '$name': internal HTTP timeout ${configured_timeout}s adjusted to effective timeout ${effective_timeout}s."
        );
    }

    my $result = run_get_test(
        ua                  => $ua,
        test_number         => $test_number,
        name                => $name,
        url                 => $url,
        expect_http         => $expect_http,
        configured_timeout  => $configured_timeout,
        effective_timeout   => $effective_timeout,
        risk                => $risk,
        category            => $category,
        response_sample_len => $response_sample_len,
    );

    if ($result->{ok}) {
        $count_ok++;
        $ok_by_risk{$risk}++;

        if ($verbose) {
            log_msg(
                "OK",
                "OK test #$test_number '$name' - HTTP $result->{http_code}, duration $result->{duration_ms} ms."
            );
        }
    } else {
        $count_failed++;
        $failed_by_risk{$risk}++;
        $failed_by_result_type{$result->{result_type}}++;
        $failed_by_error_class{$result->{error_class}}++;
        $failed_by_risk_and_error_class{$risk}{$result->{error_class}}++;

        if ($result->{result_type} eq "TIMEOUT") {
            $count_timeout++;
            $timeout_by_risk{$risk}++;
        }

        push @failed_results, $result;

        log_failed_test($result);

        if ($sonos_tail_lines > 0) {
            log_sonos_tail($sonos_tail_lines);
        }
    }

    my $delay_before_next_ms = $delay_after_test_ms;

    if ($result->{result_type} eq "TIMEOUT") {
        # Keep the special timeout grace as a minimum because PHP/Sonos may still be processing.
        $delay_before_next_ms = max_number($delay_after_test_ms, $delay_after_timeout_ms);
    }

    if ($verbose) {
        log_msg("INFO", "Test #$test_number '$name': waiting ${delay_before_next_ms} ms before next test.");
    }

    polite_sleep_ms($delay_before_next_ms);
}

############################################################
# Summary
############################################################

my $suite_duration_ms = int((time() - $suite_start) * 1000);

log_msg("INFO", "------------------------------------------------------------");
log_msg("INFO", "URL regression test finished.");
log_msg("INFO", "Total tests in JSON: $count_total");
log_msg("INFO", "Selected tests: $count_selected");
log_msg("INFO", "OK: $count_ok");
log_msg("INFO", "FAILED: $count_failed");
log_msg("INFO", "TIMEOUT: $count_timeout");
log_msg("INFO", "Skipped by mode or unsupported: $count_skipped");
log_msg("INFO", "Inactive skipped: $count_inactive");
log_msg("INFO", "Duration: $suite_duration_ms ms");

log_summary_by_risk();
log_summary_by_error_class();

if ($sonos_blocks_on_failure && @failed_results) {
    log_related_sonos_blocks_for_failed_tests(\@failed_results);
}

log_msg("INFO", "------------------------------------------------------------");

close($LOG);

exit($count_failed > 0 ? 2 : 0);

############################################################
# Functions
############################################################

sub usage_and_exit {
    my ($exit_code) = @_;

    print <<"USAGE";
Usage:
  perl regression_test.pl --mode=safe
  perl regression_test.pl --mode=all
  perl regression_test.pl --mode=category:status
  perl regression_test.pl --mode=risk:low
  perl regression_test.pl --mode=risk:middle
  perl regression_test.pl --mode=risk:critical
  perl regression_test.pl --mode=risk:low,middle

Options:
  --mode=safe                  Run only safe/low/read-only/status tests
  --mode=all                   Run all active tests
  --mode=category:<name>       Run active tests of one category, e.g. category:status
  --mode=risk:<name>           Run active tests of one risk level, e.g. risk:critical
  --mode=risk:<a,b,c>          Run active tests of multiple risk levels, e.g. risk:low,middle

  --config=<file>              JSON config file
                                Default: $DEFAULT_JSON_FILE

  --log=<file>                 Test log file
                                Default: $DEFAULT_TEST_LOG

  --verbose                    Log successful tests too
  --keep-sonos-log             Do not delete sonos.log before test start
  --include-inactive           Also run tests where status is not active

  --delay-ms=<ms>              Default pause after a test when JSON timeout_sec is missing/empty
                                Default: $DEFAULT_DELAY_BETWEEN_TESTS_MS

  --timeout-grace-ms=<ms>      Pause after a client timeout
                                Default: $DEFAULT_DELAY_AFTER_TIMEOUT_MS

  --response-sample-len=<n>    Max response sample chars on failure
                                Default: $DEFAULT_RESPONSE_SAMPLE_LEN

  --sonos-tail-lines=<n>       Add last n lines from sonos.log to failed test output
                                Default: $DEFAULT_SONOS_TAIL_LINES

  --sonos-blocks               Add matching sonos.log blocks for failed tests at the end
                                Default: enabled

  --no-sonos-blocks            Disable matching sonos.log block extraction

  --zone=<room>                Override/add URL parameter &zone=<room> for executed tests
  --member=<room1,room2>       Override/add URL parameter &member=<room1,room2> for executed tests
  --test-number=<n>            Run only the test with this test_number from url_tests.json

  --help                       Show this help

SOURCE placeholder:
  If a JSON URL contains SOURCE, the value from the JSON field source is
  injected at runtime and URL-encoded. Example:
    "url": "zone=RAUM&action=sonosplaylist&playlist=SOURCE&load"
    "source": "Herbst Chillout"

Exit codes:
  0 = all selected tests OK
  1 = command line / usage error
  2 = at least one test failed

Risk values:
  safe
  low
  middle / medium
  critical
  readonly / read-only / read_only

Notes:
  JSON timeout_sec is optional and now means delay after this test in seconds.
  If timeout_sec is missing or empty, the default pause is 1 second.
  HTTP client timeouts are still calculated internally and are not controlled by timeout_sec.
  The script automatically raises the effective HTTP timeout for slow Sonos actions
  like member handling, zapzone, nextpush and TTS.
  Related sonos.log blocks are appended automatically for failed tests.
USAGE

    exit($exit_code);
}

sub ensure_parent_dir {
    my ($file) = @_;
    my $dir = dirname($file);

    if (!-d $dir) {
        make_path($dir) or die "Cannot create directory '$dir': $!\n";
    }
}

sub reset_sonos_log {
    if (-e $SONOS_LOG_FILE) {
        if (unlink $SONOS_LOG_FILE) {
            log_msg("INFO", "Deleted sonos.log: $SONOS_LOG_FILE");
        } else {
            log_msg("WARNING", "Could not delete sonos.log '$SONOS_LOG_FILE': $!");
        }
    } else {
        log_msg("INFO", "sonos.log does not exist, nothing to delete: $SONOS_LOG_FILE");
    }
}

sub load_tests {
    my ($file) = @_;

    if (!-f $file) {
        log_msg("ERROR", "JSON config file not found: $file");
        close($LOG);
        exit(1);
    }

    open(my $fh, "<:raw", $file)
        or do {
            log_msg("ERROR", "Cannot open JSON config file '$file': $!");
            close($LOG);
            exit(1);
        };

    local $/;
    my $json_bytes = <$fh>;
    close($fh);

    my $json_text;

    eval {
        $json_text = decode("UTF-8", $json_bytes, Encode::FB_CROAK);
        1;
    } or do {
        log_msg("WARNING", "JSON file is not valid UTF-8. Trying Windows-1252 fallback: $file");
        eval {
            $json_text = decode("Windows-1252", $json_bytes, Encode::FB_CROAK);
            1;
        } or do {
            my $err = $@ || "unknown encoding error";
            log_msg("ERROR", "Cannot decode JSON file as UTF-8 or Windows-1252: $err");
            close($LOG);
            exit(1);
        };
    };

    # Remove UTF-8 BOM if present.
    $json_text =~ s/^\x{FEFF}//;

    # Repair invalid JSON escape sequences produced by broken exports, e.g. \xFC -> ü.
    # JSON officially allows \u00FC, but not \xFC.
    if ($json_text =~ /\\x(?:\{[0-9A-Fa-f]+\}|[0-9A-Fa-f]{2})/) {
        log_msg("WARNING", "JSON contains invalid \\xNN escape sequences. Trying to repair them before parsing.");

        $json_text =~ s/\\x\{([0-9A-Fa-f]+)\}/chr(hex($1))/eg;
        $json_text =~ s/\\x([0-9A-Fa-f]{2})/decode("Windows-1252", pack("C", hex($1)))/eg;
    }

    my $data;
    eval {
        $data = JSON::PP->new->utf8(0)->decode($json_text);
        1;
    } or do {
        my $err = $@ || "unknown JSON error";
        log_msg("ERROR", "Invalid JSON in '$file': $err");
        close($LOG);
        exit(1);
    };

    if (ref($data) ne "ARRAY") {
        log_msg("ERROR", "JSON root must be an array.");
        close($LOG);
        exit(1);
    }

    log_msg("INFO", "Loaded " . scalar(@{$data}) . " tests from JSON.");
    return $data;
}


sub url_contains_source_placeholder {
    my ($url) = @_;

    $url = rt_trim($url // "");
    return 0 if $url eq "";

    return ($url =~ /(?:^|[=&?])\Q$SOURCE_PLACEHOLDER\E(?:[&#]|$)/) ? 1 : 0;
}

sub apply_source_placeholder {
    my ($url, $source) = @_;

    $url    = rt_trim($url // "");
    $source = rt_trim($source // "");

    return $url if $url eq "";
    return $url if !url_contains_source_placeholder($url);

    my $encoded_source = uri_escape_utf8($source);
    $url =~ s/\Q$SOURCE_PLACEHOLDER\E/$encoded_source/g;

    return $url;
}

sub build_runtime_base_url {
    my $host = "";

    eval {
        $host = LoxBerry::System::lbhostname();
        1;
    } or do {
        $host = "";
    };

    $host = rt_trim($host);

    if ($host eq "") {
        eval {
            $host = LoxBerry::System::get_localip();
            1;
        } or do {
            $host = "";
        };

        $host = rt_trim($host);
    }

    $host = "127.0.0.1" if $host eq "";

    # Be tolerant if a function or local setting returns more than the plain host.
    $host =~ s{^https?://}{}i;
    $host =~ s{/.*$}{};
    $host =~ s{\s+.*$}{};

    my $port = "";

    eval {
        $port = LoxBerry::Web::lbwebserverport();
        1;
    } or do {
        $port = "";
    };

    $port = rt_trim($port);
    $port = "" if $port !~ /^\d+$/;

    my $scheme = ($port ne "" && int($port) == 443) ? "https" : "http";
    my $base_url = "$scheme://$host";

    if ($port ne "" && int($port) != 80 && int($port) != 443) {
        $base_url .= ":$port";
    }

    return $base_url;
}

sub build_runtime_endpoint_url {
    my ($base_url) = @_;

    $base_url = rt_trim($base_url // "");
    return "" if $base_url eq "";

    my $path = $DEFAULT_TEST_ENDPOINT_PATH;
    $path = "/" . $path if $path !~ m{^/};

    return $base_url . $path;
}

sub apply_runtime_base_url {
    my ($url, $base_url) = @_;

    $url      = rt_trim($url // "");
    $base_url = rt_trim($base_url // "");

    return $url if $url eq "" || $base_url eq "";

    my $endpoint_url = build_runtime_endpoint_url($base_url);

    # Preferred JSON format since V12:
    # Store only the query string, e.g. action=...&zone=...
    # The runtime endpoint is added here.
    if ($url =~ /^\?(.+)$/) {
        return $endpoint_url . "?" . $1;
    }

    if ($url =~ /^&(.+)$/) {
        return $endpoint_url . "?" . $1;
    }

    if (is_query_only_url($url)) {
        return $endpoint_url . "?" . $url;
    }

    # JSON may still contain old absolute URLs like:
    # http://loxberry-dev/plugins/sonos4lox/index.php/?...
    # Keep path and query, but always replace scheme/host/port by the current LoxBerry runtime base URL.
    if ($url =~ m{^https?://[^/]+(/.*)$}i) {
        return $base_url . $1;
    }

    # Protocol-relative URL fallback.
    if ($url =~ m{^//[^/]+(/.*)$}) {
        return $base_url . $1;
    }

    # Host without scheme fallback, e.g. loxberry-dev/plugins/sonos4lox/index.php?...
    if ($url =~ m{^[A-Za-z0-9._-]+(?::\d+)?(/plugins/.*)$}i) {
        return $base_url . $1;
    }

    # Relative plugin URL beginning with /.
    if ($url =~ m{^/}) {
        return $base_url . $url;
    }

    # Tolerate relative plugin URLs without leading slash.
    if ($url =~ m{^(plugins/.*)$}i) {
        return $base_url . "/" . $1;
    }

    # Unknown format: return unchanged so the later request/logging shows the original problem.
    return $url;
}

sub is_query_only_url {
    my ($url) = @_;

    $url = rt_trim($url // "");
    return 0 if $url eq "";

    # Do not treat paths or full URLs as query-only.
    return 0 if $url =~ m{^https?://}i;
    return 0 if $url =~ m{^//}i;
    return 0 if $url =~ m{^/};
    return 0 if $url =~ m{^plugins/}i;

    # Query-only means: starts with a typical key=value pair.
    return ($url =~ /^[A-Za-z0-9_.-]+=/) ? 1 : 0;
}

sub apply_runtime_url_parameters {
    my (%args) = @_;

    my $url    = rt_trim($args{url}  // "");
    my $zone   = rt_trim($args{zone} // "");

    # Important:
    # Member must only be used for test scenarios that already contain
    # a member parameter in url_tests.json. Do not add member to all tests.
    my $has_member_parameter = url_has_query_parameter($url, "member");

    my $member = normalize_member_override($args{member} // "", $zone);

    # Zone is the selected master/player and is always overridden/added
    # if the UI or CLI supplied a value.
    $url = set_url_query_parameter($url, "zone", $zone) if $zone ne "";

    # Member is only overridden if the original test URL already had
    # a member parameter. Tests without member stay without member.
    if ($member ne "" && $has_member_parameter) {
        $url = set_url_query_parameter($url, "member", $member);
    }

    return $url;
}

sub normalize_member_override {
    my ($member_string, $zone) = @_;

    $member_string = rt_trim($member_string // "");
    $zone          = rt_trim($zone // "");

    return "" if $member_string eq "";

    my %seen;
    my @members;

    foreach my $member (split(/,/, $member_string)) {
        $member = rt_trim($member);
        next if $member eq "";

        # A Zone must not be part of the Member list. Compare case-insensitive
        # but keep the original spelling for the URL value.
        next if $zone ne "" && lc($member) eq lc($zone);

        my $key = lc($member);
        next if $seen{$key}++;

        push @members, $member;
    }

    return join(',', @members);
}

sub url_has_query_parameter {
    my ($url, $key) = @_;

    return 0 if !defined $url || $url eq "" || !defined $key || $key eq "";

    return ($url =~ /(?:\?|&)\Q$key\E(?:=|&|#|$)/i) ? 1 : 0;
}

sub set_url_query_parameter {
    my ($url, $key, $value) = @_;

    return $url if !defined $url || $url eq "" || !defined $key || $key eq "";

    my $encoded_value = uri_escape_utf8($value // "");

    if ($url =~ /([?&])\Q$key\E=[^&#]*/i) {
        $url =~ s/([?&])\Q$key\E=[^&#]*/$1$key=$encoded_value/i;
        return $url;
    }

    my ($base, $fragment) = split(/#/, $url, 2);
    my $separator = ($base =~ /\?/) ? "&" : "?";
    $base .= $separator . $key . "=" . $encoded_value;

    return defined $fragment ? $base . "#" . $fragment : $base;
}

sub test_matches_mode {
    my ($mode_value, $risk, $category) = @_;

    $mode_value = lc(rt_trim($mode_value // ""));
    $risk       = normalize_risk($risk);
    $category   = lc(rt_trim($category // ""));

    if ($mode_value eq "all") {
        return 1;
    }

    if ($mode_value eq "safe") {
        return is_safe_risk($risk, $category);
    }

    if ($mode_value =~ /^category:(.+)$/) {
        my $wanted_category = lc(rt_trim($1));
        return $category eq $wanted_category;
    }

    if ($mode_value =~ /^risk:(.+)$/) {
        my $wanted_risk = $1;
        return risk_matches($risk, $wanted_risk);
    }

    log_msg("ERROR", "Unsupported mode '$mode_value'. Use safe, all, category:<name>, or risk:<name>.");
    close($LOG);
    exit(1);
}

sub risk_matches {
    my ($risk, $wanted_risk_string) = @_;

    $risk = normalize_risk($risk);
    $wanted_risk_string = "" if !defined $wanted_risk_string;

    my @wanted_risks = split(/[\,\s]+/, $wanted_risk_string);

    foreach my $wanted (@wanted_risks) {
        $wanted = normalize_risk($wanted);
        return 1 if $wanted ne "" && $risk eq $wanted;
    }

    return 0;
}

sub normalize_risk {
    my ($risk) = @_;

    $risk = lc(rt_trim($risk // ""));
    $risk =~ s/\s+/_/g;

    return "undefined" if $risk eq "";

    if ($risk eq "medium" || $risk eq "mid") {
        return "middle";
    }

    if ($risk eq "read-only" || $risk eq "read_only" || $risk eq "read only") {
        return "readonly";
    }

    return $risk;
}

sub is_safe_risk {
    my ($risk, $category) = @_;

    $risk     = normalize_risk($risk);
    $category = lc(rt_trim($category // ""));

    return 1 if $risk eq "safe";
    return 1 if $risk eq "low";
    return 1 if $risk eq "readonly";
    return 1 if $risk eq "status";
    return 1 if $risk eq "info";

    return 1 if $category eq "status";
    return 1 if $category eq "info";
    return 1 if $category eq "read";
    return 1 if $category eq "readonly";
    return 1 if $category eq "read-only";
    return 1 if $category eq "read_only";

    return 0;
}

sub calculate_effective_timeout {
    my (%args) = @_;

    my $configured_timeout = safe_number($args{configured_timeout}, $DEFAULT_HTTP_TIMEOUT);
    my $category           = lc(rt_trim($args{category} // ""));
    my $risk               = normalize_risk($args{risk} // "");
    my $name               = lc(rt_trim($args{name} // ""));
    my $url                = rt_trim($args{url} // "");
    my $action             = get_url_param($url, "action");

    my $effective_timeout = $configured_timeout;

    # Category based minimums.
    my %category_min_timeout = (
        "status"     => 3,
        "info"       => 3,
        "read"       => 3,
        "readonly"   => 3,
        "read-only"  => 3,
        "read_only"  => 3,

        "control"    => 5,
        "member"     => 10,
        "function"   => 10,

        "tts"        => 25,
        "tts file"   => 35,
        "tts_file"   => 35,
    );

    if (exists $category_min_timeout{$category}) {
        $effective_timeout = max_number($effective_timeout, $category_min_timeout{$category});
    }

    # Risk based minimums. Middle/critical actions are often slower.
    if ($risk eq "middle") {
        $effective_timeout = max_number($effective_timeout, 8);
    } elsif ($risk eq "critical") {
        $effective_timeout = max_number($effective_timeout, 20);
    }

    # Action based minimums. These are more precise than category.
    my %action_min_timeout = (
        "addmember"         => 10,
        "removemember"      => 10,

        "zapzone"           => 12,
        "nextpush"          => 12,
        "nextradio"         => 10,

        "say"               => 30,

        "playfavorite"      => 15,
        "trackfavorites"    => 15,
        "radiofavorites"    => 15,
        "playlistfavorites" => 15,

        "sonosplaylist"     => 10,
        "radioplaylist"     => 8,

        "crossfade"         => 5,
        "pause"             => 5,
        "toggle"            => 5,
        "play"              => 5,
        "stop"              => 5,
    );

    if ($action ne "" && exists $action_min_timeout{$action}) {
        $effective_timeout = max_number($effective_timeout, $action_min_timeout{$action});
    }

    # Name based fallback for cases where category/action are not ideal.
    if ($name =~ /tts|text|messageid|gong/) {
        $effective_timeout = max_number($effective_timeout, 30);
    } elsif ($name =~ /member|group/) {
        $effective_timeout = max_number($effective_timeout, 10);
    } elsif ($name =~ /one-click|one click|nextpush|zapzone/) {
        $effective_timeout = max_number($effective_timeout, 12);
    }

    return $effective_timeout;
}

sub run_get_test {
    my (%args) = @_;

    my $ua                  = $args{ua};
    my $test_number         = $args{test_number};
    my $name                = $args{name};
    my $url                 = $args{url};
    my $expect_http         = $args{expect_http};
    my $configured_timeout  = $args{configured_timeout};
    my $effective_timeout   = $args{effective_timeout};
    my $risk                = $args{risk};
    my $category            = $args{category};
    my $response_sample_len = $args{response_sample_len};

    $ua->timeout($effective_timeout);

    my $start = time();
    my $response;

    eval {
        $response = $ua->get($url);
        1;
    } or do {
        my $err = $@ || "unknown request error";
        my $end = time();
        my $duration_ms = int(($end - $start) * 1000);

        return {
            ok                 => 0,
            result_type        => "ERROR",
            error_class        => "REQUEST_EXCEPTION",
            test_number        => $test_number,
            name               => $name,
            url                => $url,
            risk               => $risk,
            category           => $category,
            http_code          => 0,
            raw_http_code      => 0,
            duration_ms        => $duration_ms,
            configured_timeout => $configured_timeout,
            effective_timeout  => $effective_timeout,
            reason             => "Request exception: $err",
            body_sample        => "",
            php_errors         => [],
            start_epoch        => $start,
            end_epoch          => $end,
        };
    };

    my $end           = time();
    my $duration_ms   = int(($end - $start) * 1000);
    my $raw_http_code = $response ? $response->code : 0;
    my $status_line   = $response ? $response->status_line : "No response";
    my $body          = $response ? $response->decoded_content(charset => "none") : "";

    if (is_lwp_timeout_response($response, $body)) {
        return {
            ok                 => 0,
            result_type        => "TIMEOUT",
            error_class        => "CLIENT_TIMEOUT",
            test_number        => $test_number,
            name               => $name,
            url                => $url,
            risk               => $risk,
            category           => $category,
            http_code          => 0,
            raw_http_code      => $raw_http_code,
            duration_ms        => $duration_ms,
            configured_timeout => $configured_timeout,
            effective_timeout  => $effective_timeout,
            reason             => "Client timeout after ${effective_timeout}s. Server/PHP may still have continued processing.",
            body_sample        => shorten(clean_text($body), $response_sample_len),
            php_errors         => [],
            start_epoch        => $start,
            end_epoch          => $end,
        };
    }

    my @php_errors = detect_php_errors($body);

    my @problems;

    if ($raw_http_code != $expect_http) {
        push @problems, "HTTP code $raw_http_code does not match expected HTTP code $expect_http";
    }

    if (@php_errors) {
        push @problems, "PHP error pattern detected in response";
    }

    if (!$response || !$response->is_success) {
        push @problems, "HTTP request not successful: $status_line";
    }

    my $ok = @problems ? 0 : 1;

    return {
        ok                 => $ok,
        result_type        => ($ok ? "OK" : "FAILED"),
        error_class        => classify_error(
                                ok          => $ok,
                                http_code   => $raw_http_code,
                                expect_http => $expect_http,
                                response    => $response,
                                php_errors  => \@php_errors,
                              ),
        test_number        => $test_number,
        name               => $name,
        url                => $url,
        risk               => $risk,
        category           => $category,
        http_code          => $raw_http_code,
        raw_http_code      => $raw_http_code,
        duration_ms        => $duration_ms,
        configured_timeout => $configured_timeout,
        effective_timeout  => $effective_timeout,
        reason             => join("; ", @problems),
        body_sample        => shorten(clean_text($body), $response_sample_len),
        php_errors         => \@php_errors,
        start_epoch        => $start,
        end_epoch          => $end,
    };
}

sub classify_error {
    my (%args) = @_;

    return "NONE" if $args{ok};

    my $http_code   = safe_number($args{http_code}, 0);
    my $expect_http = safe_number($args{expect_http}, $DEFAULT_HTTP_CODE);
    my $response    = $args{response};
    my $php_errors  = $args{php_errors};

    if ($php_errors && @{$php_errors}) {
        return "PHP_ERROR";
    }

    if ($http_code == 0) {
        return "NO_RESPONSE";
    }

    if ($http_code != $expect_http) {
        return "HTTP_CODE_MISMATCH";
    }

    if (!$response || !$response->is_success) {
        return "HTTP_NOT_SUCCESSFUL";
    }

    return "UNKNOWN_FAILURE";
}

sub is_lwp_timeout_response {
    my ($response, $body) = @_;

    return 0 if !$response;

    my $status_line = $response->status_line // "";
    $body //= "";

    # LWP commonly returns a synthetic HTTP 500 with "read timeout".
    return 1 if $status_line =~ /\b(read|connect|write)\s+timeout\b/i;
    return 1 if $body =~ /^\s*(read|connect|write)\s+timeout\s+at\s+/i;

    return 0;
}

sub detect_php_errors {
    my ($body) = @_;
    $body //= "";

    my @patterns = (
        qr/Fatal error/i,
        qr/Parse error/i,
        qr/Warning:/i,
        qr/Notice:/i,
        qr/Deprecated:/i,
        qr/Stack trace:/i,
        qr/syntax error/i,
        qr/Undefined variable/i,
        qr/Undefined array key/i,
        qr/Undefined index/i,
        qr/Call to undefined function/i,
        qr/Call to a member function/i,
        qr/Uncaught Error/i,
        qr/Uncaught Exception/i,
        qr/Allowed memory size/i,
        qr/Maximum execution time/i,
    );

    my @found;

    foreach my $pattern (@patterns) {
        if ($body =~ /($pattern.{0,350})/is) {
            my $match = clean_text($1);
            push @found, shorten($match, 420);
        }
    }

    return @found;
}

sub log_failed_test {
    my ($result) = @_;

    my $headline = $result->{result_type} eq "TIMEOUT" ? "TIMEOUT" : "FAILED";

    log_msg("ERROR", "$headline test #$result->{test_number} '$result->{name}'");
    log_msg("ERROR", "  Result: $result->{result_type}");
    log_msg("ERROR", "  Error class: $result->{error_class}");
    log_msg("ERROR", "  Category: " . safe_value($result->{category}, ""));
    log_msg("ERROR", "  Risk: " . safe_value($result->{risk}, ""));
    log_msg("ERROR", "  URL: $result->{url}");

    if ($result->{result_type} eq "TIMEOUT") {
        log_msg("ERROR", "  HTTP code: n/a (client timeout, raw LWP code: $result->{raw_http_code})");
    } else {
        log_msg("ERROR", "  HTTP code: $result->{http_code}");
    }

    log_msg("ERROR", "  Duration: $result->{duration_ms} ms");
    log_msg("ERROR", "  Internal HTTP timeout: $result->{configured_timeout} sec");
    log_msg("ERROR", "  Effective HTTP timeout: $result->{effective_timeout} sec");
    log_msg("ERROR", "  Reason: $result->{reason}");

    if ($result->{php_errors} && @{$result->{php_errors}}) {
        log_msg("ERROR", "  PHP errors detected:");
        foreach my $err (@{$result->{php_errors}}) {
            log_msg("ERROR", "    - $err");
        }
    }

    if (defined($result->{body_sample}) && $result->{body_sample} ne "") {
        log_msg("ERROR", "  Response sample:");
        log_multiline("ERROR", $result->{body_sample}, "  ");
    }
}

sub log_summary_by_risk {
    log_msg("INFO", "------------------------------------------------------------");
    log_msg("INFO", "Selected tests by risk:");

    if (%selected_by_risk) {
        foreach my $risk_key (sort_risk_keys(keys %selected_by_risk)) {
            my $selected = $selected_by_risk{$risk_key} || 0;
            my $ok       = $ok_by_risk{$risk_key} || 0;
            my $failed   = $failed_by_risk{$risk_key} || 0;
            my $timeout  = $timeout_by_risk{$risk_key} || 0;

            log_msg(
                "INFO",
                sprintf(
                    "  %-10s selected=%3d, ok=%3d, failed=%3d, timeout=%3d",
                    $risk_key,
                    $selected,
                    $ok,
                    $failed,
                    $timeout
                )
            );
        }
    } else {
        log_msg("INFO", "  none");
    }
}

sub log_summary_by_error_class {
    log_msg("INFO", "------------------------------------------------------------");
    log_msg("INFO", "Failed tests by result type:");

    if (%failed_by_result_type) {
        foreach my $type_key (sort keys %failed_by_result_type) {
            log_msg("INFO", "  $type_key: $failed_by_result_type{$type_key}");
        }
    } else {
        log_msg("INFO", "  none");
    }

    log_msg("INFO", "Failed tests by error class:");

    if (%failed_by_error_class) {
        foreach my $class_key (sort keys %failed_by_error_class) {
            log_msg("INFO", "  $class_key: $failed_by_error_class{$class_key}");
        }
    } else {
        log_msg("INFO", "  none");
    }

    log_msg("INFO", "Failed tests by risk and error class:");

    if (%failed_by_risk_and_error_class) {
        foreach my $risk_key (sort_risk_keys(keys %failed_by_risk_and_error_class)) {
            foreach my $class_key (sort keys %{$failed_by_risk_and_error_class{$risk_key}}) {
                log_msg(
                    "INFO",
                    "  $risk_key / $class_key: $failed_by_risk_and_error_class{$risk_key}{$class_key}"
                );
            }
        }
    } else {
        log_msg("INFO", "  none");
    }
}

sub log_related_sonos_blocks_for_failed_tests {
    my ($failed_results) = @_;

    return if !$failed_results || ref($failed_results) ne "ARRAY" || !@{$failed_results};

    log_msg("INFO", "------------------------------------------------------------");
    log_msg("INFO", "Related sonos.log blocks for failed tests:");
    log_msg("INFO", "sonos.log: $SONOS_LOG_FILE");

    if (!-f $SONOS_LOG_FILE) {
        log_msg("WARNING", "sonos.log not found. Related block extraction skipped.");
        return;
    }

    my @blocks = read_sonos_log_blocks($SONOS_LOG_FILE);

    if (!@blocks) {
        log_msg("WARNING", "No readable blocks found in sonos.log. Related block extraction skipped.");
        return;
    }

    foreach my $result (@{$failed_results}) {
        log_msg("INFO", "------------------------------------------------------------");
        log_msg("INFO", "Failed test #$result->{test_number} '$result->{name}'");
        log_msg("INFO", "Result: $result->{result_type}, Error class: $result->{error_class}, Risk: $result->{risk}, Category: $result->{category}");
        log_msg("INFO", "URL: $result->{url}");

        my ($block, $match_reason, $delta_sec) = find_best_sonos_block_for_failed_result($result, \@blocks);

        if (!$block) {
            log_msg("WARNING", "No matching sonos.log block found for failed test #$result->{test_number}.");
            next;
        }

        if (defined $delta_sec) {
            log_msg("INFO", "Matching sonos.log block found: $match_reason, time delta: ${delta_sec}s");
        } else {
            log_msg("INFO", "Matching sonos.log block found: $match_reason");
        }

        log_msg("INFO", "sonos.log block begin:");
        log_multiline("INFO", $block->{text}, "  sonos.log: ");
        log_msg("INFO", "sonos.log block end.");
    }
}

sub read_sonos_log_blocks {
    my ($file) = @_;

    my $text = read_text_file_lax($file);
    return () if !defined($text) || $text eq "";

    my @raw_blocks = split(/(?=^={20,}\s*$)/m, $text);
    my @blocks;
    my $idx = 0;

    foreach my $raw (@raw_blocks) {
        $raw = rt_trim($raw);
        next if $raw eq "";

        my @called_syntaxes;
        while ($raw =~ /sonos\.php:\s*called syntax:\s*(.+)$/mig) {
            my $called = rt_trim($1);
            push @called_syntaxes, $called if $called ne "";
        }

        my @canonical_called_urls = map { canonicalize_url_for_sonos_match($_) } @called_syntaxes;

        push @blocks, {
            index                 => $idx,
            text                  => $raw,
            start_epoch           => parse_sonos_block_start_epoch($raw),
            called_syntaxes       => \@called_syntaxes,
            canonical_called_urls => \@canonical_called_urls,
        };

        $idx++;
    }

    return @blocks;
}

sub find_best_sonos_block_for_failed_result {
    my ($result, $blocks) = @_;

    return (undef, undef, undef) if !$result || !$blocks || ref($blocks) ne "ARRAY";

    my $test_url_canonical = canonicalize_url_for_sonos_match($result->{url});
    my @candidates;

    foreach my $block (@{$blocks}) {
        my $called_urls = $block->{canonical_called_urls} || [];

        foreach my $called_url (@{$called_urls}) {
            my ($score, $reason) = compare_sonos_log_url_match($test_url_canonical, $called_url);

            next if !defined $score;

            my $delta = undef;

            if (defined($result->{start_epoch}) && defined($block->{start_epoch})) {
                $delta = abs(int($block->{start_epoch} - $result->{start_epoch}));
            }

            push @candidates, {
                block  => $block,
                score  => $score,
                reason => $reason,
                delta  => defined($delta) ? $delta : 999999,
            };
        }
    }

    return (undef, undef, undef) if !@candidates;

    @candidates = sort {
        $a->{score} <=> $b->{score}
        ||
        $a->{delta} <=> $b->{delta}
        ||
        $a->{block}->{index} <=> $b->{block}->{index}
    } @candidates;

    my $best = $candidates[0];

    return (
        $best->{block},
        $best->{reason},
        $best->{delta} == 999999 ? undef : $best->{delta},
    );
}

sub compare_sonos_log_url_match {
    my ($test_url, $called_url) = @_;

    $test_url   = rt_trim($test_url // "");
    $called_url = rt_trim($called_url // "");

    return (undef, undef) if $test_url eq "" || $called_url eq "";

    if ($test_url eq $called_url) {
        return (0, "exact normalized URL match");
    }

    if (index($called_url, $test_url) >= 0 || index($test_url, $called_url) >= 0) {
        return (1, "partial normalized URL match");
    }

    my $param_score = url_param_match_score($test_url, $called_url);

    if (defined $param_score) {
        return ($param_score, "query parameter fallback match");
    }

    return (undef, undef);
}

sub url_param_match_score {
    my ($test_url, $called_url) = @_;

    my %test_params   = extract_query_params_for_match($test_url);
    my %called_params = extract_query_params_for_match($called_url);

    return undef if !%test_params || !%called_params;

    if (exists $test_params{action}) {
        return undef if !exists $called_params{action};
        return undef if $test_params{action} ne $called_params{action};
    } else {
        return undef;
    }

    if (exists $test_params{zone}) {
        return undef if !exists $called_params{zone};
        return undef if $test_params{zone} ne $called_params{zone};
    }

    my $total   = 0;
    my $matched = 0;

    foreach my $key (keys %test_params) {
        next if $key eq "";

        $total++;

        if (exists $called_params{$key} && $called_params{$key} eq $test_params{$key}) {
            $matched++;
        }
    }

    return undef if $total == 0;
    return undef if $matched < 2;

    my $ratio = $matched / $total;

    return undef if $ratio < 0.60;

    return 3;
}

sub extract_query_params_for_match {
    my ($url) = @_;

    my %params;
    $url = rt_trim($url // "");

    return %params if $url eq "";

    my $query = "";

    if ($url =~ /\?(.*)$/) {
        $query = $1;
    } else {
        return %params;
    }

    $query =~ s/#.*$//;

    foreach my $pair (split(/&/, $query)) {
        next if $pair eq "";

        my ($key, $value) = split(/=/, $pair, 2);

        $key   = lc(rt_trim($key // ""));
        $value = rt_trim($value // "");

        next if $key eq "";

        $params{$key} = $value;
    }

    return %params;
}

sub canonicalize_url_for_sonos_match {
    my ($value) = @_;

    $value = rt_trim($value // "");
    return "" if $value eq "";

    $value = decode_html_entities_minimal($value);

    if ($value =~ m{https?://[^/]+(/.*)}i) {
        $value = $1;
    } elsif ($value =~ m{^[^/\s]+(/plugins/.*)}i) {
        $value = $1;
    } elsif ($value =~ m{(/plugins/.*)}i) {
        $value = $1;
    }

    $value =~ s/#.*$//;
    $value = percent_decode_utf8_lax($value);
    $value = rt_trim($value);
    $value =~ s/\s+/ /g;

    return $value;
}

sub decode_html_entities_minimal {
    my ($text) = @_;

    $text //= "";

    $text =~ s/&amp;/&/gi;
    $text =~ s/&quot;/"/gi;
    $text =~ s/&apos;/'/gi;
    $text =~ s/&#39;/'/gi;
    $text =~ s/&lt;/</gi;
    $text =~ s/&gt;/>/gi;
    $text =~ s/&#x([0-9A-Fa-f]+);/chr(hex($1))/eg;
    $text =~ s/&#(\d+);/chr($1)/eg;

    return $text;
}

sub percent_decode_utf8_lax {
    my ($text) = @_;

    $text //= "";

    my $unescaped = uri_unescape($text);

    if (!utf8::is_utf8($unescaped)) {
        my $decoded = $unescaped;

        eval {
            $decoded = decode("UTF-8", $unescaped, Encode::FB_CROAK);
            1;
        } or do {
            $decoded = $unescaped;
        };

        return $decoded;
    }

    return $unescaped;
}

sub parse_sonos_block_start_epoch {
    my ($block) = @_;

    return undef if !defined $block;

    if ($block =~ /<LOGSTART>(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2}):(\d{2})\s+TASK STARTED/) {
        my ($day, $mon, $year, $hour, $min, $sec) = ($1, $2, $3, $4, $5, $6);

        my $epoch;
        eval {
            $epoch = timelocal($sec, $min, $hour, $day, $mon - 1, $year);
            1;
        } or do {
            return undef;
        };

        return $epoch;
    }

    return undef;
}

sub read_text_file_lax {
    my ($file) = @_;

    return undef if !defined($file) || !-f $file;

    open(my $fh, "<:raw", $file)
        or return undef;

    local $/;
    my $bytes = <$fh>;
    close($fh);

    my $text;

    eval {
        $text = decode("UTF-8", $bytes, Encode::FB_CROAK);
        1;
    } or do {
        eval {
            $text = decode("Windows-1252", $bytes, Encode::FB_DEFAULT);
            1;
        } or do {
            $text = $bytes;
        };
    };

    return $text;
}

sub sort_risk_keys {
    my @keys = @_;

    my %rank = (
        "safe"      => 1,
        "readonly"  => 2,
        "low"       => 3,
        "middle"    => 4,
        "critical"  => 5,
        "undefined" => 99,
    );

    return sort {
        ($rank{$a} || 50) <=> ($rank{$b} || 50)
        ||
        $a cmp $b
    } @keys;
}

sub normalize_url {
    my ($url) = @_;
    $url = rt_trim($url);

    # Replace literal spaces because some test URLs may contain playlist names or text with spaces.
    # Existing encoded values like %20 stay untouched.
    $url =~ s/ /%20/g;

    # Percent-encode remaining non-ASCII or unsafe characters.
    # Existing percent-encoded values like %20 or %C3%BC stay untouched because '%' is allowed here.
    #
    # Important:
    # The dollar sign must be escaped as \$ in the regexp.
    # Otherwise Perl may interpret "$&" as the special match variable $&.
    $url =~ s{([^A-Za-z0-9\-\._~:/\?#\[\]\@!\$\&'\(\)\*\+,;=%])}{uri_escape_utf8($1)}eg;

    return $url;
}

sub get_url_param {
    my ($url, $param) = @_;

    return "" if !defined $url || !defined $param || $url eq "" || $param eq "";

    if ($url =~ /(?:\?|&)\Q$param\E=([^&#]*)/i) {
        my $value = $1;
        $value =~ tr/+/ /;
        $value =~ s/%([0-9A-Fa-f]{2})/chr(hex($1))/eg;
        return lc(rt_trim($value));
    }

    return "";
}

sub log_sonos_tail {
    my ($lines) = @_;

    return if !$lines || $lines < 1;

    my @tail = tail_file_lines($SONOS_LOG_FILE, $lines);

    if (!@tail) {
        log_msg("INFO", "  sonos.log tail: no data available.");
        return;
    }

    log_msg("INFO", "  Last $lines line(s) from sonos.log:");
    foreach my $line (@tail) {
        chomp($line);
        log_msg("INFO", "  sonos.log: $line");
    }
}

sub tail_file_lines {
    my ($file, $lines) = @_;

    return () if !defined $file || !-f $file;
    return () if !defined $lines || $lines < 1;

    my $text = read_text_file_lax($file);
    return () if !defined($text) || $text eq "";

    my @all = split(/\n/, $text);

    if (@all <= $lines) {
        return @all;
    }

    return @all[-$lines .. -1];
}

sub delay_ms_from_json_timeout_sec {
    my ($value, $default_ms) = @_;

    $default_ms = 1000 if !defined($default_ms) || $default_ms < 1;
    $value = rt_trim($value // "");

    # Missing/empty timeout_sec means the configured default pause before the next test.
    return int($default_ms) if $value eq "";

    # Keep the JSON field simple and robust: seconds, integer, 1..300.
    $value =~ s/[^0-9]//g;
    return int($default_ms) if $value eq "";

    my $seconds = int($value);
    $seconds = 1 if $seconds < 1;
    $seconds = 300 if $seconds > 300;

    return $seconds * 1000;
}

sub polite_sleep_ms {
    my ($ms) = @_;

    return if !defined $ms;
    return if $ms <= 0;

    sleep($ms / 1000);
}

sub log_msg {
    my ($level, $message) = @_;

    my $ts = strftime("%d-%m-%Y %H:%M:%S", localtime);
    my $line = "$ts <$level> $message";

    print $LOG $line . "\n" if $LOG;
    print $line . "\n";
}

sub log_multiline {
    my ($level, $text, $prefix) = @_;

    $text //= "";
    $prefix //= "";

    my @lines = split(/\n/, $text);

    foreach my $line (@lines) {
        log_msg($level, $prefix . $line);
    }
}

sub rt_trim {
    my ($value) = @_;
    $value = "" if !defined $value;
    $value =~ s/^\s+//;
    $value =~ s/\s+$//;
    return $value;
}

sub safe_value {
    my ($value, $default) = @_;
    return defined($value) ? $value : $default;
}

sub safe_number {
    my ($value, $default) = @_;

    if (!defined $value || $value eq "") {
        return $default;
    }

    if ($value =~ /^\d+$/) {
        return int($value);
    }

    return $default;
}

sub clean_text {
    my ($text) = @_;
    $text //= "";

    $text =~ s/\r\n/\n/g;
    $text =~ s/\r/\n/g;
    $text =~ s/\t/ /g;
    $text =~ s/[ ]{2,}/ /g;
    $text =~ s/\n{4,}/\n\n/g;
    $text =~ s/^\s+//;
    $text =~ s/\s+$//;

    return $text;
}

sub shorten {
    my ($text, $max_len) = @_;
    $text //= "";

    if (!defined $max_len || $max_len < 1) {
        return $text;
    }

    if (length($text) > $max_len) {
        return substr($text, 0, $max_len) . "...";
    }

    return $text;
}

sub max_number {
    my ($a, $b) = @_;

    $a = 0 if !defined $a;
    $b = 0 if !defined $b;

    return $a >= $b ? $a : $b;
}
