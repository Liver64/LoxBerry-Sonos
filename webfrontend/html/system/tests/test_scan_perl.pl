#!/usr/bin/perl
use strict;
use warnings;
use JSON;

# ============================================================
# VLAN Hint – Perl-seitiger Simulationstest
#
# Simuliert den scan-Sub aus dem LoxBerry-Perl-Skript.
# Ruft test_network_vlan.php für alle 3 Szenarien auf und
# prüft ob die Hint-Erkennung korrekt funktioniert.
# ============================================================

# Pfad zum PHP-Testskript – automatisch relativ zum Perl-Skript ermittelt
use File::Basename;
use Cwd 'abs_path';
my $script_dir = dirname(abs_path($0));
my $php_test   = "$script_dir/test_network_vlan.php";

# Globale Variablen wie im echten Skript
our $vlan_hint        = 0;
our $vlan_hint_reason = '';
our $vlan_hint_ips    = [];
our $countplayers     = 3; # fiktiv – simuliert bereits konfigurierte Player

# ---- Simulierter scan-Sub ----
sub scan {
    my ($scenario) = @_;

    # Globale Flags zurücksetzen
    $vlan_hint        = 0;
    $vlan_hint_reason = '';
    $vlan_hint_ips    = [];

    # STDERR mitausgeben damit PHP-Fehler sichtbar sind
    my $cmd = "/usr/bin/php $php_test --scenario=$scenario 2>&1";
    my $raw = qx($cmd);

    # Debug: komplette PHP-Ausgabe zeigen
    print "  [Perl] --- PHP raw output START ---\n";
    print $raw;
    print "  [Perl] --- PHP raw output END ---\n";

    # Nur den JSON-Teil extrahieren (nach dem Trennstrich)
    my $response = '';
    if ($raw =~ /----[^\n]*PHP OUTPUT[^\n]*----\n(.*?)\n--/s) {
        $response = $1;
        $response =~ s/^\s+|\s+$//g;
    }

    print "  [Perl] Extracted JSON: $response\n";

    # Leere Antwort
    if ($response eq '') {
        print "  [Perl] ERROR: Empty response from network.php\n";
        return $countplayers;
    }

    # Leeres Array oder Objekt => keine neuen Player
    if ($response =~ /^\[\s*\]$/ || $response =~ /^\{\s*\}$/) {
        print "  [Perl] No new players found (empty response).\n";
        return $countplayers;
    }

    # JSON dekodieren
    my $newzones;
    eval { $newzones = decode_json($response); 1; } or do {
        print "  [Perl] ERROR: Invalid JSON: $@\n";
        return $countplayers;
    };

    if (ref($newzones) ne 'HASH') {
        print "  [Perl] ERROR: Expected HASH, got " . ref($newzones) . "\n";
        return $countplayers;
    }

    # ---- VLAN Hint Detection ----
    if (exists $newzones->{'__vlan_hint__'}) {
        $vlan_hint        = 1;
        $vlan_hint_reason = $newzones->{'reason'}    // 'unknown';
        $vlan_hint_ips    = $newzones->{'tried_ips'} // [];
        print "  [Perl] VLAN hint detected! reason=$vlan_hint_reason\n";
        if (@{$vlan_hint_ips}) {
            print "  [Perl] Tried IPs (unreachable): " . join(', ', @{$vlan_hint_ips}) . "\n";
        }
        print "  [Perl] -> Rendering VLAN IP input form for user.\n";
        return $countplayers; # $countplayers bleibt unverändert
    }

    # ---- Normaler Flow: neue Player ----
    my $added = 0;
    foreach my $room (keys %{$newzones}) {
        my $arr = $newzones->{$room};
        print "  [Perl] New player added: room='$room', IP=" . ($arr->[0]//'?') . ", model=" . ($arr->[2]//'?') . "\n";
        $added++;
    }
    print "  [Perl] Total new players added: $added\n";
    return $countplayers + $added;
}

# ---- HTML-Formular-Simulation ----
sub render_vlan_form {
    if (!$vlan_hint) { return; }

    my $tried = @{$vlan_hint_ips} ? join(', ', @{$vlan_hint_ips}) : '(keine)';
    my $reason_text = $vlan_hint_reason eq 'unicast_failed'
        ? "Konfigurierte IPs waren nicht erreichbar (TCP 1400 geblockt?): $tried"
        : "SSDP Multicast + Broadcast fehlgeschlagen, keine Static-IPs konfiguriert.";

    print "\n  [HTML] ======================================\n";
    print "  [HTML] VLAN-Formular würde jetzt gerendert:\n";
    print "  [HTML] Grund    : $vlan_hint_reason\n";
    print "  [HTML] Hinweis  : $reason_text\n";
    print "  [HTML] Formular : <input name='vlan_ips' placeholder='z.B. 192.168.10.50'>\n";
    print "  [HTML] Action   : POST -> save_vlan_ip -> save_vlan_static_ips() -> scan()\n";
    print "  [HTML] ======================================\n";
}

# ============================================================
# TEST-AUSFÜHRUNG: alle 3 Szenarien
# ============================================================

my @scenarios = (
    { id => 1, label => "SSDP scheitert, keine Static-IPs",
      expected_hint => 1, expected_reason => 'ssdp_failed_no_static_ips' },
    { id => 2, label => "SSDP scheitert, Static-IPs konfiguriert aber TCP 1400 geblockt",
      expected_hint => 1, expected_reason => 'unicast_failed' },
    { id => 3, label => "SSDP scheitert, Unicast (TCP 1400) erreichbar -> Player gefunden",
      expected_hint => 0, expected_reason => '' },
);

my $pass = 0;
my $fail = 0;

for my $s (@scenarios) {
    print "\n";
    print "=======================================================\n";
    print "  SZENARIO $s->{id}: $s->{label}\n";
    print "=======================================================\n";

    my $result = scan($s->{id});
    render_vlan_form();

    # Assertion
    my $hint_ok   = ($vlan_hint == $s->{expected_hint});
    my $reason_ok = ($vlan_hint_reason eq $s->{expected_reason});
    my $ok        = $hint_ok && $reason_ok;

    print "\n  [TEST] vlan_hint=$vlan_hint (erwartet=$s->{expected_hint}) -> " . ($hint_ok ? "OK" : "FAIL") . "\n";
    print   "  [TEST] reason='$vlan_hint_reason' (erwartet='$s->{expected_reason}') -> " . ($reason_ok ? "OK" : "FAIL") . "\n";
    print   "  [TEST] countplayers returned=$result\n";
    print   "  [TEST] => " . ($ok ? "PASS ✓" : "FAIL ✗") . "\n";

    if ($ok) { $pass++; } else { $fail++; }
}

print "\n";
print "=======================================================\n";
printf "  ERGEBNIS: %d/%d Tests bestanden\n", $pass, $pass + $fail;
print "=======================================================\n\n";