<?php
/**
 * VLAN Hint Simulation Test
 *
 * Testet die Kern-Logik aus network.php isoliert, ohne LoxBerry-Abhängigkeiten.
 * Simuliert drei Szenarien:
 *
 *   Szenario 1: SSDP scheitert, keine Static-IPs konfiguriert
 *               => __vlan_hint__, reason: ssdp_failed_no_static_ips
 *
 *   Szenario 2: SSDP scheitert, Static-IPs konfiguriert, aber TCP 1400 nicht erreichbar
 *               => __vlan_hint__, reason: unicast_failed, tried_ips: [...]
 *
 *   Szenario 3: SSDP scheitert, Static-IPs konfiguriert, TCP 1400 antwortet mit Mock-JSON
 *               => normales Player-JSON (kein Hint)
 *
 * Usage:
 *   php test_network_vlan.php
 *   php test_network_vlan.php --scenario=1
 *   php test_network_vlan.php --scenario=2
 *   php test_network_vlan.php --scenario=3
 */

// ---- Logging-Stubs (ersetzt LoxBerry-Logging) ----
function LOGDEB($m)  { echo "[DEB] $m\n"; }
function LOGINF($m)  { echo "[INF] $m\n"; }
function LOGWARN($m) { echo "[WRN] $m\n"; }
function LOGOK($m)   { echo "[OK ] $m\n"; }
function LOGERR($m)  { echo "[ERR] $m\n"; }

// ---- CLI-Argumente ----
$scenario = 1; // Default
foreach ($argv as $arg) {
    if (preg_match('/^--scenario=(\d)$/', $arg, $m)) {
        $scenario = (int)$m[1];
    }
}

echo "\n";
echo "=======================================================\n";
echo "  VLAN Hint Simulation – Szenario {$scenario}\n";
echo "=======================================================\n\n";

// ---- Mock-Konfiguration je Szenario ----
// Simuliert was get_static_sonos_ips() aus der Config liest
function get_mock_static_ips($scenario) {
    switch ($scenario) {
        case 1: return [];                          // keine Static-IPs
        case 2: return ['192.168.10.50', '192.168.10.51']; // IPs konfiguriert aber nicht erreichbar
        case 3: return ['192.168.10.52'];           // IP konfiguriert und antwortet
    }
    return [];
}

// Simuliert was ssdp_discover_ips_multicast() zurückgibt
// Im Test: immer leer (SSDP scheitert in VLAN)
function mock_ssdp_multicast($scenario) {
    LOGDEB("system/network.php: SSDP M-SEARCH multicast sent (2500ms window).");
    LOGDEB("system/network.php: SSDP multicast window closed. Total responses: 0, unique IPs: 0");
    return [];
}

// Simuliert was ssdp_discover_ips_broadcast() zurückgibt
// Im Test: immer leer
function mock_ssdp_broadcast($scenario) {
    LOGDEB("system/network.php: SSDP M-SEARCH broadcast sent (2500ms window).");
    LOGDEB("system/network.php: SSDP broadcast window closed. Total responses: 0, unique IPs: 0");
    return [];
}

// Simuliert test_sonos_ips_unicast()
// Szenario 2: keine Antwort
// Szenario 3: gibt Mock-JSON zurück als wäre TCP 1400 erreichbar
function mock_unicast_probe(array $ips, $scenario) {
    LOGDEB("system/network.php: Unicast probe – testing " . count($ips) . " IP(s) via http://<ip>:1400/info ...");
    if ($scenario === 3) {
        // Szenario 3: erste IP antwortet mit Mock-Daten
        foreach ($ips as $ip) {
            LOGDEB("system/network.php: Unicast probe OK: {$ip} -> 'Wohnzimmer' (S36)");
        }
        return $ips; // alle IPs "erreichbar"
    }
    // Szenario 2: keine IP antwortet
    foreach ($ips as $ip) {
        LOGDEB("system/network.php: Unicast probe FAILED for {$ip} – no response on TCP 1400.");
    }
    return [];
}

// Mock für fetch_sonos_info_parallel (Szenario 3)
function mock_fetch_info(array $ips) {
    $result = [];
    foreach ($ips as $ip) {
        $result[$ip] = [
            'device' => [
                'id'               => 'RINCON_' . strtoupper(str_replace('.', '', $ip)) . '01400',
                'name'             => 'Wohnzimmer',
                'model'            => 'S36',
                'modelDisplayName' => 'Sonos Era 300',
                'capabilities'     => ['AUDIO_CLIP', 'VOICE'],
                'swGen'            => '2',
            ],
            'householdId' => 'Sonos_AABBCCDDEEFF.GGHHIIJJKKLL',
        ];
    }
    return $result;
}

// ============================================================
// HAUPTLOGIK (aus network.php, ohne LoxBerry-Infrastruktur)
// ============================================================

$mcast_ttl  = 2;
$static_ips = get_mock_static_ips($scenario);
$devices    = [];

if (!empty($static_ips)) {
    LOGINF("system/network.php: VLAN unicast fallback configured with " . count($static_ips) . " static IP(s): " . implode(", ", $static_ips));
}

// 1) SSDP Multicast
LOGDEB("Start scanning for Sonos Players using MULTICAST SSDP: 239.255.255.250:1900");
$devices = mock_ssdp_multicast($scenario);

// 2) Broadcast-Fallback
if (empty($devices)) {
    LOGWARN("system/network.php: No Sonos devices detected via MULTICAST.");
    LOGWARN("system/network.php: Trying BROADCAST fallback (255.255.255.255).");
    $devices = mock_ssdp_broadcast($scenario);
}

// 3) VLAN-Hint / Unicast-Fallback
if (empty($devices)) {
    LOGWARN("system/network.php: No Sonos devices detected via BROADCAST either.");
    LOGWARN("system/network.php: Broadcast does not cross routers/VLANs by design – likely a VLAN environment.");

    if (!empty($static_ips)) {
        LOGINF("system/network.php: Trying UNICAST fallback with " . count($static_ips) . " configured static IP(s): " . implode(", ", $static_ips));
        $unicast_devices = mock_unicast_probe($static_ips, $scenario);

        if (!empty($unicast_devices)) {
            LOGINF("system/network.php: UNICAST fallback succeeded. Reachable Sonos IP(s): " . implode(", ", $unicast_devices));
            $devices = $unicast_devices;
        } else {
            LOGWARN("system/network.php: UNICAST fallback also failed for all configured IPs.");
            LOGWARN("system/network.php: Check that TCP port 1400 is open from LoxBerry to Sonos VLAN.");
            LOGWARN("system/network.php: Returning VLAN hint (reason: unicast_failed) to Perl caller.");
            $out = json_encode([
                '__vlan_hint__' => 1,
                'reason'        => 'unicast_failed',
                'mcast_ttl'     => $mcast_ttl,
                'tried_ips'     => array_values($static_ips),
            ]);
            echo "\n---- PHP OUTPUT (was Perl via qx() empfängt) ----\n";
            echo $out . "\n";
            echo "--------------------------------------------------\n";
            exit;
        }
    } else {
        LOGWARN("system/network.php: No vlan_static_ips configured – cannot attempt unicast fallback.");
        LOGWARN("system/network.php: Returning VLAN hint (reason: ssdp_failed_no_static_ips) to Perl caller.");
        $out = json_encode([
            '__vlan_hint__' => 1,
            'reason'        => 'ssdp_failed_no_static_ips',
            'mcast_ttl'     => $mcast_ttl,
        ]);
        echo "\n---- PHP OUTPUT (was Perl via qx() empfängt) ----\n";
        echo $out . "\n";
        echo "--------------------------------------------------\n";
        exit;
    }
}

// 4) Normaler Flow – Szenario 3: Player gefunden via Unicast
$devices = array_values(array_unique(array_filter($devices)));
LOGINF("system/network.php: Discovery found " . count($devices) . " unique IP(s): " . implode(", ", $devices));

LOGDEB("system/network.php: Fetching /info (parallel) for " . count($devices) . " IP(s)...");
$info_by_ip = mock_fetch_info($devices);
LOGDEB("system/network.php: Parallel /info fetch completed. Got responses from " . count($info_by_ip) . " of " . count($devices) . " IP(s).");

// Delta-Check (simuliert leer – also alles ist NEU)
$new_candidate_ips = $devices;
LOGINF("system/network.php: New candidate IP(s) after delta check: " . implode(", ", $new_candidate_ips));

// Mini getSonosDevices (nur für Test)
$sonosplayerfinal = [];
foreach ($new_candidate_ips as $ip) {
    $info   = $info_by_ip[$ip];
    $room   = strtolower($info['device']['name']);
    $zonen  = [
        $ip,
        $info['device']['id'],
        strtoupper($info['device']['modelDisplayName']),
        "", "", "", "",
        $info['device']['model'],
        "NOSUB",
        $info['householdId'],
        "NOSUR",
        true,  // AUDIO_CLIP
        true,  // VOICE
        "SB", "", "", ""
    ];
    $sonosplayerfinal[$room] = $zonen;
    LOGOK("system/network.php: New Sonos Player: '" . $zonen[2] . "' called: '{$room}' using IP: '{$ip}' and Rincon-ID: '" . $zonen[1] . "'");
}

ksort($sonosplayerfinal);
$out = json_encode($sonosplayerfinal);

echo "\n---- PHP OUTPUT (was Perl via qx() empfängt) ----\n";
echo $out . "\n";
echo "--------------------------------------------------\n";