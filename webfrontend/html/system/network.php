<?php
/**
 * Sonos Discovery (Optimized for Auto-Discovery)
 *
 * Goals:
 * - Fast SSDP discovery (fixed window)
 * - De-duplicate SSDP replies (VLAN relays / multicast proxy duplicates)
 * - Parallel fetch of http://<ip>:1400/info via curl_multi
 * - Delta-check by RINCON vs existing s4lox_config.json (only NEW devices processed deeply)
 * - Optional cache in /dev/shm to speed up UI auto-discovery
 * - CLI support for Perl qx():
 *     /usr/bin/php network.php --ttl=60 --force=1
 *
 * VLAN support:
 * - Multicast TTL is configurable via --mcast-ttl=X (default 1, increase for routed VLANs)
 * - Static/unicast IP fallback: add "vlan_static_ips": ["x.x.x.x", ...] to s4lox_config.json
 *   This is the most reliable method when no multicast relay is available between VLANs.
 *
 * Required network ports for VLAN setups:
 *   UDP 1900   both directions   SSDP multicast discovery
 *   TCP 1400   LoxBerry -> Sonos Sonos HTTP API (/info, SOAP control)
 *   TCP 1443   LoxBerry -> Sonos Sonos HTTPS API
 *   TCP 3400   LoxBerry -> Sonos Sonos control
 *   TCP 3401   LoxBerry -> Sonos Sonos events
 *   TCP 3500   LoxBerry -> Sonos Sonos events
 *   Multicast group 239.255.255.250 must be relayed between VLANs
 *   (via igmpproxy, smcroute, PIM, or avahi-daemon / mdns-repeater)
 *   NOTE: Ports 137-139/445 (Samba/NetBIOS) are NOT needed for Sonos.
 *
 * Output:
 * - JSON object of NEW zones only (room => array)
 * - EXACT "[]" when nothing new (for Perl compatibility)
 *
 * Links:
 * https://www.reddit.com/r/sonos/comments/1ggv8dk/sonos_network_troubleshooting_an_unofficial/
 * https://www.reddit.com/r/sonos/comments/t0emv0/the_definitive_sonos_vlan_segregation_post/
 */
require_once "sonosAccess.php";
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "logging.php";
require_once "error.php";
require_once $lbphtmldir . "/Helper.php";
register_shutdown_function('shutdown');
$home          = $lbhomedir;
$myConfigFolder = (string)$lbpconfigdir;
$myConfigFile   = "s4lox_config.json";
error_reporting(E_ALL);
ini_set("display_errors", "off");
define('ERROR_LOG_FILE', "$lbplogdir/sonos.log");
// calling custom error handler
set_error_handler("handleError");
// Logger
$params = [
	"name"     => "Sonos",
	"filename" => "$lbplogdir/sonos.log",
	"append"   => 1,
	"addtime"  => 1,
];
$log = LBLog::newLog($params);
// ---- CLI support: allow --ttl=60 --force=1 when called via /usr/bin/php ----
if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
	foreach ($argv as $arg) {
		if (preg_match('/^--ttl=(\d+)$/', $arg, $m)) {
			$_GET['ttl'] = $m[1];
		}
		if (preg_match('/^--mcast-ttl=(\d+)$/', $arg, $m)) {
			$_GET['mcast_ttl'] = $m[1];
		}
		if ($arg === '--force=1' || $arg === '--force') {
			$_GET['force'] = '1';
		}
	}
}
global $sonosfinal, $sonosnet, $devices;
// ---- Cache settings (for auto-discovery speed) ----
$cache_dir  = "/dev/shm/sonos4lox";
$cache_file = $cache_dir . "/discovery_cache.json";
$force = isset($_GET['force']) && (string)$_GET['force'] === "1";
$ttl   = isset($_GET['ttl']) ? (int)$_GET['ttl'] : 60;
if ($ttl < 0) $ttl = 0;
if ($ttl > 600) $ttl = 600;
// Multicast TTL for SSDP.
// Important for routed/VLAN environments.
// Default is 1 (same subnet only). Increase if Sonos players are in a different VLAN/subnet.
// Requires a multicast-aware router or proxy (igmpproxy, smcroute, PIM) between VLANs.
// This is NOT the cache TTL above.
$mcast_ttl = isset($_GET['mcast_ttl']) ? (int)$_GET['mcast_ttl'] : 1;
if ($mcast_ttl < 1) $mcast_ttl = 1;
if ($mcast_ttl > 8) $mcast_ttl = 8;
LOGDEB("system/network.php: SSDP multicast TTL configured as {$mcast_ttl}.");
if ($mcast_ttl === 1) {
	LOGDEB("system/network.php: Multicast TTL=1 (default) – SSDP packets will not cross routers/VLANs. Use --mcast-ttl=X (X>1) if Sonos players are in a separate VLAN.");
}
if (!is_dir($cache_dir)) {
	@mkdir($cache_dir, 0775, true);
}
// ttl=0 => disable cache and remove any existing cache file
if ($ttl === 0 && file_exists($cache_file)) {
	@unlink($cache_file);
	LOGDEB("system/network.php: ttl=0 -> cache file removed ($cache_file)");
}
// Serve cache quickly if valid (and not forced)
if (!$force && $ttl > 0 && file_exists($cache_file)) {
	$raw = @file_get_contents($cache_file);
	$cache = json_decode((string)$raw, true);
	if (is_array($cache) && isset($cache['ts']) && isset($cache['json'])) {
		$age = time() - (int)$cache['ts'];
		if ($age >= 0 && $age <= $ttl) {
			LOGDEB("system/network.php: Discovery cache hit (age {$age}s, ttl {$ttl}s).");
			header('Content-Type: application/json; charset=utf-8');
			echo (string)$cache['json'];
			exit;
		}
	}
}
// ---- SSDP params ----
$ssdp_ip   = '239.255.255.250';
$ssdp_port = 1900;
$search    = "urn:schemas-upnp-org:device:ZonePlayer:1";
// ---- Load existing config early (delta-check + static IPs) ----
$sonosnet = parse_cfg_file_safe($myConfigFolder, $myConfigFile);
$existing = build_existing_maps($sonosnet);
// ---- Read optional static IPs for VLAN unicast fallback ----
$static_ips = get_static_sonos_ips($myConfigFolder, $myConfigFile);
if (!empty($static_ips)) {
	LOGINF("system/network.php: VLAN unicast fallback configured with " . count($static_ips) . " static IP(s): " . implode(", ", $static_ips));
}
LOGDEB("Start scanning for Sonos Players using MULTICAST SSDP: {$ssdp_ip}:{$ssdp_port}");
// ---- 1) SSDP multicast scan (fast fixed window) ----
$devices = ssdp_discover_ips_multicast($ssdp_ip, $ssdp_port, $search, 2500, $mcast_ttl);
// ---- 2) Broadcast fallback if multicast yielded nothing ----
if (empty($devices)) {
	LOGWARN("system/network.php: No Sonos devices detected via MULTICAST.");
	if ($mcast_ttl === 1) {
		LOGWARN("system/network.php: VLAN hint: If Sonos players are in a different VLAN/subnet, multicast (TTL=1) does not cross routers. Options:");
		LOGWARN("system/network.php:   (A) Set up a multicast relay (igmpproxy, smcroute, avahi-daemon) between VLANs.");
		LOGWARN("system/network.php:   (B) Increase multicast TTL: call network.php with --mcast-ttl=4 (requires router support).");
		LOGWARN("system/network.php:   (C) Use unicast fallback: add 'vlan_static_ips' array to s4lox_config.json.");
	} else {
		LOGWARN("system/network.php: Multicast TTL={$mcast_ttl} was used – ensure your router/switch relays multicast group 239.255.255.250 between VLANs.");
	}
	LOGWARN("system/network.php: Trying BROADCAST fallback (255.255.255.255).");
	$devices = ssdp_discover_ips_broadcast($ssdp_port, $search, 2500);
}
// ---- 3) VLAN Hint / Unicast fallback if broadcast also yielded nothing ----
// Bedingungen für den VLAN-Hint:
//   A) Kein Ergebnis via Multicast + Broadcast (SSDP komplett gescheitert)
//   B) Keine vlan_static_ips konfiguriert => User muss IP manuell eingeben
//      => Rückgabe: {"__vlan_hint__": 1, "reason": "ssdp_failed_no_static_ips"}
// Bedingungen für den Unicast-Fallback:
//   A) Kein Ergebnis via Multicast + Broadcast
//   B) vlan_static_ips vorhanden => direkt per TCP 1400 prüfen
//      Falls auch Unicast scheitert:
//      => Rückgabe: {"__vlan_hint__": 1, "reason": "unicast_failed", "tried_ips": [...]}
if (empty($devices)) {
	LOGWARN("system/network.php: No Sonos devices detected via BROADCAST either.");
	LOGWARN("system/network.php: Broadcast does not cross routers/VLANs by design – likely a VLAN environment.");
	if (!empty($static_ips)) {
		// Unicast-Fallback: konfigurierte IPs direkt via TCP 1400 prüfen
		LOGINF("system/network.php: Trying UNICAST fallback with " . count($static_ips) . " configured static IP(s): " . implode(", ", $static_ips));
		$unicast_devices = test_sonos_ips_unicast($static_ips);
		if (!empty($unicast_devices)) {
			LOGINF("system/network.php: UNICAST fallback succeeded. Reachable Sonos IP(s): " . implode(", ", $unicast_devices));
			$devices = $unicast_devices;
		} else {
			// Unicast auch gescheitert – VLAN-Hint mit Reason unicast_failed zurückgeben.
			// Der Perl-Caller zeigt ein Formular zur IP-Eingabe (ggf. andere IP probieren).
			LOGWARN("system/network.php: UNICAST fallback also failed for all configured IPs.");
			LOGWARN("system/network.php: Check that TCP port 1400 is open from LoxBerry to Sonos VLAN.");
			LOGWARN("system/network.php: Returning VLAN hint (reason: unicast_failed) to Perl caller.");
			$out = json_encode([
				'__vlan_hint__' => 1,
				'reason'        => 'unicast_failed',
				'mcast_ttl'     => $mcast_ttl,
				'tried_ips'     => array_values($static_ips),
			]);
			cache_write($cache_file, '[]'); // Cache leer lassen – kein valides Ergebnis
			header('Content-Type: application/json; charset=utf-8');
			echo $out;
			exit;
		}
	} else {
		// Keine Static-IPs konfiguriert.
		// Hochwahrscheinlich VLAN-Problem: VLAN-Hint an Perl zurückgeben.
		// Der Perl-Caller zeigt dem User ein Formular zur manuellen IP-Eingabe.
		LOGWARN("system/network.php: No vlan_static_ips configured – cannot attempt unicast fallback.");
		LOGWARN("system/network.php: --- VLAN TROUBLESHOOTING (for log) ---");
		LOGWARN("system/network.php: Required firewall ports (LoxBerry <-> Sonos VLAN):");
		LOGWARN("system/network.php:   UDP 1900  (both)            SSDP multicast discovery");
		LOGWARN("system/network.php:   TCP 1400  (LoxBerry->Sonos) Sonos HTTP API (/info, SOAP)");
		LOGWARN("system/network.php:   TCP 1443  (LoxBerry->Sonos) Sonos HTTPS API");
		LOGWARN("system/network.php:   TCP 3400  (LoxBerry->Sonos) Sonos control");
		LOGWARN("system/network.php:   TCP 3401  (LoxBerry->Sonos) Sonos events");
		LOGWARN("system/network.php:   TCP 3500  (LoxBerry->Sonos) Sonos events");
		LOGWARN("system/network.php:   NOTE: Ports 137-139/445 (Samba/NetBIOS) are NOT required for Sonos.");
		LOGWARN("system/network.php: Returning VLAN hint (reason: ssdp_failed_no_static_ips) to Perl caller.");
		$out = json_encode([
			'__vlan_hint__' => 1,
			'reason'        => 'ssdp_failed_no_static_ips',
			'mcast_ttl'     => $mcast_ttl,
		]);
		cache_write($cache_file, '[]'); // Cache leer lassen
		header('Content-Type: application/json; charset=utf-8');
		echo $out;
		exit;
	}
}
// Hard-dedupe
$devices = array_values(array_unique(array_filter($devices)));
if (empty($devices)) {
	LOGWARN("system/network.php: System has not detected any Sonos devices by scanning (multicast / broadcast / unicast).");
	$out = "[]";
	cache_write($cache_file, '[]');
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}
LOGINF("system/network.php: Discovery found " . count($devices) . " unique IP(s): " . implode(", ", $devices));
// ---- 4) Fetch /info in parallel for ALL discovered IPs ----
LOGDEB("system/network.php: Fetching /info (parallel) for " . count($devices) . " IP(s)...");
$info_by_ip = fetch_sonos_info_parallel($devices, 3500, 1200);
LOGDEB("system/network.php: Parallel /info fetch completed. Got responses from " . count($info_by_ip) . " of " . count($devices) . " IP(s).");
// Retry: Some players answer /info late (WiFi/VLAN/Busy). Try serial fetch for missing IPs.
foreach ($devices as $ip) {
	if (isset($info_by_ip[$ip]) && is_array($info_by_ip[$ip])) {
		continue;
	}
	LOGDEB("system/network.php: /info missing for {$ip} after parallel fetch – retrying serial (5s timeout)...");
	$url = "http://{$ip}:1400/info";
	$body = http_get_quick($url, 5000, 1500); // 5s timeout, 1.5s connect
	if (!empty($body)) {
		$json = json_decode((string)$body, true);
		if (is_array($json)) {
			$info_by_ip[$ip] = $json;
			LOGDEB("system/network.php: /info serial retry succeeded for {$ip}.");
		} else {
			LOGWARN("system/network.php: /info serial retry for {$ip} returned non-JSON body – skipping.");
		}
	} else {
		LOGWARN("system/network.php: /info serial retry for {$ip} failed (TCP 1400 unreachable or timeout). Check firewall rules.");
	}
}
// ---- 5) Delta-check by RINCON (only NEW candidates processed deeply) ----
$new_candidate_ips = [];
foreach ($devices as $ip) {
	$info = $info_by_ip[$ip] ?? null;
	if (!is_array($info) || empty($info['device']['id'])) {
		LOGWARN("system/network.php: /info missing or invalid for {$ip} – cannot determine RINCON, skipping candidate.");
		LOGWARN("system/network.php:   -> Possible cause: TCP port 1400 blocked between LoxBerry and Sonos player at {$ip}.");
		continue;
	}
	$rincon = (string)$info['device']['id'];
	$roomraw = (string)($info['device']['name'] ?? '(unknown)');
	if (isset($existing['rincons'][$rincon])) {
		LOGDEB("system/network.php: IP {$ip} ('{$roomraw}', RINCON {$rincon}) already known – skipping.");
		continue; // already known
	}
	LOGDEB("system/network.php: IP {$ip} ('{$roomraw}', RINCON {$rincon}) is NEW – adding to candidate list.");
	$new_candidate_ips[] = $ip;
}
$new_candidate_ips = array_values(array_unique(array_filter($new_candidate_ips)));
if (empty($new_candidate_ips)) {
	LOGINF("system/network.php: No new Sonos Player has been detected (delta check by RINCON – all devices already known).");
	$out = "[]";
	cache_write($cache_file, $out);
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}
LOGINF("system/network.php: New candidate IP(s) after delta check: " . implode(", ", $new_candidate_ips));
// ---- 6) Exclude satellites / RF of stereo pair / surround (expensive SOAP) - only for NEW candidates ----
$devicecheck = [];
foreach ($new_candidate_ips as $newzoneip) {
	try {
		LOGDEB("system/network.php: Checking SOAP GetZoneGroupAttributes for {$newzoneip}...");
		$sonos = new SonosAccess($newzoneip);
		$zone_details = $sonos->GetZoneGroupAttributes();
		if (!empty($zone_details['CurrentZonePlayerUUIDsInGroup'])) {
			$devicecheck[] = $newzoneip;
			LOGDEB("system/network.php: {$newzoneip} is a primary zone player – keeping.");
		} else {
			LOGDEB("system/network.php: IP-address '{$newzoneip}' seems to be a part of a Stereopair/Surround setup and has not been added.");
		}
	} catch (Exception $e) {
		// be permissive - keep device if SOAP fails
		LOGWARN("system/network.php: GetZoneGroupAttributes failed for '{$newzoneip}' -> keeping as candidate. Error: " . $e->getMessage());
		LOGWARN("system/network.php:   -> Possible cause: TCP port 1400 blocked or player busy. Check firewall.");
		$devicecheck[] = $newzoneip;
	}
}
$devicecheck = array_values(array_unique(array_filter($devicecheck)));
if (empty($devicecheck)) {
	LOGINF("system/network.php: All new candidates were excluded as satellites – nothing to add.");
	$out = "[]";
	cache_write($cache_file, $out);
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}
// ---- 7) Build final zones for NEW devices only (using cached /info data) ----
$sonosfinal = getSonosDevicesOptimized($devicecheck, $info_by_ip, $myConfigFolder, $myConfigFile);
if (empty($sonosfinal)) {
	LOGINF("system/network.php: No new Sonos Player has been detected after processing.");
	$out = "[]";
	cache_write($cache_file, $out);
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}
LOGOK("system/network.php: New Players has been detected and converted to JSON (" . count($sonosfinal) . ").");
$post_json = json_encode($sonosfinal);
cache_write($cache_file, $post_json);
header('Content-Type: application/json; charset=utf-8');
echo $post_json;
exit;
// ============================================================
// HELPER FUNCTIONS
// ============================================================
/**
 * Build maps for fast delta checks
 */
function build_existing_maps($sonosnet) {
	$maps = [
		'rincons' => [],
		'ips'     => [],
		'rooms'   => [],
	];
	if (!is_array($sonosnet)) return $maps;
	foreach ($sonosnet as $room => $arr) {
		$maps['rooms'][(string)$room] = true;
		if (is_array($arr)) {
			// Expected structure in config:
			// [0]=ip, [1]=rincon, ...
			if (!empty($arr[0])) $maps['ips'][(string)$arr[0]] = true;
			if (!empty($arr[1])) $maps['rincons'][(string)$arr[1]] = true;
		}
	}
	return $maps;
}
/**
 * Safe config reader (returns [] instead of exit)
 */
function parse_cfg_file_safe($folder, $file) {
	$path = rtrim((string)$folder, "/") . "/" . (string)$file;
	if (!file_exists($path)) {
		LOGWARN("system/network.php: The file '{$path}' does not exist – treating as empty config.");
		return [];
	}
	$raw = @file_get_contents($path);
	$cfg = json_decode((string)$raw, true);
	if (!is_array($cfg)) {
		LOGWARN("system/network.php: The file '{$path}' could not be parsed – treating as empty config.");
		return [];
	}
	if (array_key_exists('sonoszonen', $cfg) && is_array($cfg['sonoszonen'])) {
		LOGOK("system/network.php: Existing configuration file 's4lox_config.json' has been loaded successfully.");
		return $cfg['sonoszonen'];
	}
	LOGINF("system/network.php: Config exists but has no 'sonoszonen' – treating as empty.");
	return [];
}
/**
 * Read optional static Sonos IPs for VLAN unicast fallback.
 *
 * Add to s4lox_config.json at the root level (NOT inside sonoszonen):
 *   "vlan_static_ips": ["192.168.10.50", "192.168.10.51"]
 *
 * These IPs are tested directly via HTTP /info when SSDP (multicast + broadcast) fails.
 * This is the most reliable method in VLAN environments without a multicast relay.
 *
 * @return string[] Array of IP addresses, empty if not configured
 */
function get_static_sonos_ips($folder, $file) {
	$path = rtrim((string)$folder, "/") . "/" . (string)$file;
	if (!file_exists($path)) return [];
	$raw = @file_get_contents($path);
	$cfg = json_decode((string)$raw, true);
	if (!is_array($cfg)) return [];
	if (empty($cfg['vlan_static_ips']) || !is_array($cfg['vlan_static_ips'])) return [];
	// Sanitize: keep only valid-looking IPs
	$ips = [];
	foreach ($cfg['vlan_static_ips'] as $ip) {
		$ip = trim((string)$ip);
		if (filter_var($ip, FILTER_VALIDATE_IP)) {
			$ips[] = $ip;
		} else {
			LOGWARN("system/network.php: vlan_static_ips contains invalid entry '{$ip}' – ignoring.");
		}
	}
	return $ips;
}
/**
 * Test a list of IPs directly via HTTP /info (unicast, no SSDP).
 * Returns only IPs that respond successfully with valid Sonos JSON.
 * Used as VLAN fallback when SSDP multicast/broadcast fails.
 *
 * @param  string[] $ips  List of IP addresses to probe
 * @return string[]       Subset of IPs that responded as Sonos devices
 */
function test_sonos_ips_unicast(array $ips) {
	if (empty($ips)) return [];
	LOGDEB("system/network.php: Unicast probe – testing " . count($ips) . " IP(s) via http://<ip>:1400/info ...");
	// Re-use parallel fetch with generous timeout for first contact
	$info_by_ip = fetch_sonos_info_parallel($ips, 4000, 2000);
	$reachable = [];
	foreach ($ips as $ip) {
		if (isset($info_by_ip[$ip]) && is_array($info_by_ip[$ip])) {
			$model = $info_by_ip[$ip]['device']['model'] ?? '?';
			$name  = $info_by_ip[$ip]['device']['name']  ?? '?';
			LOGDEB("system/network.php: Unicast probe OK: {$ip} -> '{$name}' ({$model})");
			$reachable[] = $ip;
		} else {
			LOGDEB("system/network.php: Unicast probe FAILED for {$ip} – no response on TCP 1400.");
		}
	}
	return $reachable;
}
/**
 * SSDP multicast discovery: returns array of IPs
 */
function ssdp_discover_ips_multicast($ip, $port, $st, $window_ms = 900, $mcast_ttl = 1) {
	$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($sock === false) {
		LOGERR("system/network.php: socket_create failed for multicast SSDP.");
		return [];
	}
	@socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
	// Bind to all local interfaces. This helps on systems with multiple interfaces/VLAN routes.
	@socket_bind($sock, '0.0.0.0', 0);
	// Increase multicast TTL for routed/VLAN environments.
	// Default is 1, which does not cross routers/VLANs.
	// Even with TTL > 1, a multicast-aware router/proxy is required between VLANs.
	if (defined('IP_MULTICAST_TTL')) {
		@socket_set_option($sock, IPPROTO_IP, IP_MULTICAST_TTL, (int)$mcast_ttl);
		LOGDEB("system/network.php: IP_MULTICAST_TTL set to {$mcast_ttl}.");
	} else {
		// Linux fallback: IP_MULTICAST_TTL = 33
		@socket_set_option($sock, IPPROTO_IP, 33, (int)$mcast_ttl);
		LOGDEB("system/network.php: IP_MULTICAST_TTL constant missing, fallback option 33 set to {$mcast_ttl}.");
	}
	@socket_set_nonblock($sock);
	$data  = "M-SEARCH * HTTP/1.1\r\n";
	$data .= "HOST: {$ip}:{$port}\r\n";
	$data .= "MAN: \"ssdp:discover\"\r\n";
	$data .= "MX: 2\r\n";
	$data .= "ST: {$st}\r\n";
	$data .= "\r\n";
	$sent = @socket_sendto($sock, $data, strlen($data), 0, $ip, $port);
	if ($sent === false) {
		LOGWARN("system/network.php: socket_sendto failed for multicast SSDP – socket send error.");
	} else {
		LOGDEB("system/network.php: SSDP M-SEARCH multicast sent ({$sent} bytes) to {$ip}:{$port}, window {$window_ms}ms.");
	}
	$deadline = microtime(true) + ($window_ms / 1000);
	$ips = [];
	$usn_seen = [];
	$responses = 0;
	while (microtime(true) < $deadline) {
		$read = [$sock];
		$write = [];
		$except = [];
		$sec = 0;
		$usec = 200000; // 200ms
		$ready = @socket_select($read, $write, $except, $sec, $usec);
		if ($ready === false || $ready === 0) continue;
		$buf = "";
		$from = null;
		$from_port = null;
		$len = @socket_recvfrom($sock, $buf, 4096, 0, $from, $from_port);
		if ($len === false || empty($buf)) continue;
		$responses++;
		$hdr = parse_ssdp_headers($buf);
		if (empty($hdr['st']) || stripos($hdr['st'], $st) === false) {
			LOGDEB("system/network.php: SSDP response from {$from} ignored (ST mismatch: '" . ($hdr['st'] ?? '') . "').");
			continue;
		}
		if (empty($hdr['location'])) {
			LOGDEB("system/network.php: SSDP response from {$from} ignored (no LOCATION header).");
			continue;
		}
		$usn = $hdr['usn'] ?? '';
		if ($usn !== '' && isset($usn_seen[$usn])) {
			LOGDEB("system/network.php: SSDP response from {$from} ignored (duplicate USN: {$usn}).");
			continue;
		}
		if ($usn !== '') $usn_seen[$usn] = true;
		$url = @parse_url($hdr['location']);
		if (!is_array($url) || empty($url['host'])) {
			LOGDEB("system/network.php: SSDP response from {$from} ignored (could not parse LOCATION: " . ($hdr['location'] ?? '') . ").");
			continue;
		}
		$found_ip = (string)$url['host'];
		LOGDEB("system/network.php: SSDP multicast response: {$from} -> LOCATION host: {$found_ip}");
		$ips[] = $found_ip;
	}
	@socket_close($sock);
	LOGDEB("system/network.php: SSDP multicast window closed. Total responses received: {$responses}, unique IPs found: " . count(array_unique($ips)));
	return array_values(array_unique($ips));
}
/**
 * SSDP broadcast fallback: returns array of IPs
 */
function ssdp_discover_ips_broadcast($port, $st, $window_ms = 900) {
	$broadcastip = '255.255.255.255';
	LOGDEB("system/network.php: SSDP BROADCAST M-SEARCH to {$broadcastip}:{$port}, window {$window_ms}ms.");
	$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($sock === false) {
		LOGERR("system/network.php: socket_create failed for broadcast SSDP.");
		return [];
	}
	@socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
	@socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
	@socket_set_nonblock($sock);
	$data  = "M-SEARCH * HTTP/1.1\r\n";
	$data .= "HOST: {$broadcastip}:{$port}\r\n";
	$data .= "MAN: \"ssdp:discover\"\r\n";
	$data .= "MX: 2\r\n";
	$data .= "ST: {$st}\r\n";
	$data .= "\r\n";
	$sent = @socket_sendto($sock, $data, strlen($data), 0, $broadcastip, $port);
	if ($sent === false) {
		LOGWARN("system/network.php: socket_sendto failed for broadcast SSDP.");
	} else {
		LOGDEB("system/network.php: SSDP M-SEARCH broadcast sent ({$sent} bytes).");
	}
	$deadline = microtime(true) + ($window_ms / 1000);
	$ips = [];
	$usn_seen = [];
	$responses = 0;
	while (microtime(true) < $deadline) {
		$read = [$sock];
		$write = [];
		$except = [];
		$sec = 0;
		$usec = 200000;
		$ready = @socket_select($read, $write, $except, $sec, $usec);
		if ($ready === false || $ready === 0) continue;
		$buf = "";
		$from = null;
		$from_port = null;
		$len = @socket_recvfrom($sock, $buf, 4096, 0, $from, $from_port);
		if ($len === false || empty($buf)) continue;
		$responses++;
		$hdr = parse_ssdp_headers($buf);
		if (empty($hdr['st']) || stripos($hdr['st'], $st) === false) continue;
		if (empty($hdr['location'])) continue;
		$usn = $hdr['usn'] ?? '';
		if ($usn !== '' && isset($usn_seen[$usn])) continue;
		if ($usn !== '') $usn_seen[$usn] = true;
		$url = @parse_url($hdr['location']);
		if (!is_array($url) || empty($url['host'])) continue;
		$found_ip = (string)$url['host'];
		LOGDEB("system/network.php: SSDP broadcast response: {$from} -> LOCATION host: {$found_ip}");
		$ips[] = $found_ip;
	}
	@socket_close($sock);
	LOGDEB("system/network.php: SSDP broadcast window closed. Total responses: {$responses}, unique IPs: " . count(array_unique($ips)));
	return array_values(array_unique($ips));
}
/**
 * Parse SSDP headers from a datagram
 */
function parse_ssdp_headers($packet) {
	$lines = preg_split("/\r\n/", (string)$packet);
	$hdr = [];
	foreach ($lines as $line) {
		$pos = strpos($line, ":");
		if ($pos === false) continue;
		$key = strtolower(trim(substr($line, 0, $pos)));
		$val = trim(substr($line, $pos + 1));
		if ($key !== '') $hdr[$key] = $val;
	}
	return $hdr;
}
/**
 * Parallel fetch Sonos /info JSON for a list of IPs.
 */
function fetch_sonos_info_parallel(array $ips, int $timeout_ms = 1500, int $connect_timeout_ms = 800) {
	$result = [];
	if (empty($ips)) return $result;
	$mh = curl_multi_init();
	$handles = [];
	foreach ($ips as $ip) {
		$url = "http://{$ip}:1400/info";
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT_MS => $connect_timeout_ms,
			CURLOPT_TIMEOUT_MS => $timeout_ms,
			CURLOPT_NOSIGNAL => true,
			CURLOPT_HTTPHEADER => ['Accept: application/json'],
		]);
		curl_multi_add_handle($mh, $ch);
		$handles[(string)$ip] = $ch;
	}
	$running = null;
	do {
		$mrc = curl_multi_exec($mh, $running);
		if ($mrc !== CURLM_OK) break;
		curl_multi_select($mh, 0.2);
	} while ($running > 0);
	foreach ($handles as $ip => $ch) {
		$body = curl_multi_getcontent($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_err = curl_error($ch);
		if ($code === 200 && !empty($body)) {
			$json = json_decode((string)$body, true);
			if (is_array($json)) {
				$result[$ip] = $json;
			} else {
				LOGWARN("system/network.php: /info for {$ip} returned HTTP 200 but non-JSON body.");
			}
		} elseif ($code !== 0) {
			LOGDEB("system/network.php: /info for {$ip} returned HTTP {$code}" . ($curl_err ? " ({$curl_err})" : "") . ".");
		} else {
			LOGDEB("system/network.php: /info for {$ip} – no response" . ($curl_err ? " ({$curl_err})" : "") . ".");
		}
		curl_multi_remove_handle($mh, $ch);
		curl_close($ch);
	}
	curl_multi_close($mh);
	return $result;
}
/**
 * Optimized getSonosDevices:
 * - Uses pre-fetched /info data (no serial file_get_contents)
 * - Keeps schema compatible with existing Perl usage
 */
function getSonosDevicesOptimized(array $devices, array $info_by_ip, $myConfigFolder, $myConfigFile) {
	global $lbphtmldir, $sonosplayerfinal;
	$sonosplayerfinal = [];
	// SUB/SUR detection via topology (one SOAP call each, best-effort)
	$sub = GetSub($devices, "SW");
	$sur = GetSub($devices, "LR");
	foreach ($devices as $zoneip) {
		$info = $info_by_ip[$zoneip] ?? null;
		if (!is_array($info) || empty($info['device'])) {
			LOGWARN("system/network.php: Missing /info data for '{$zoneip}' – skipping.");
			continue;
		}
		$model        = (string)($info['device']['model'] ?? '');
		$roomraw      = (string)($info['device']['name'] ?? '');
		$rinconid     = (string)($info['device']['id'] ?? '');
		$device       = (string)($info['device']['modelDisplayName'] ?? '');
		$capabilities = (array)($info['device']['capabilities'] ?? []);
		$swgen        = (string)($info['device']['swGen'] ?? '');
		$householdId  = (string)($info['householdId'] ?? '');
		// Normalize room name
		$search = ['Ä','ä','Ö','ö','Ü','ü','ß'];
		$replace = ['Ae','ae','Oe','oe','Ue','ue','ss'];
		$room = strtolower(str_replace($search, $replace, $roomraw));
		// S1 warning
		if ($swgen === "1") {
			LOGWARN("system/network.php: Player '{$room}' has been identified as Generation S1 and TTS/MP3 is only possible as Group Member!");
			notify(LBPPLUGINDIR, "Sonos", "Player '{$room}' has been identified as Generation S1 and TTS/MP3 is only possible as Group Member!", "warning");
		}
		// SUB mapping
		$subwoofer = "NOSUB";
		if ($sub !== "false" && is_array($sub) && array_key_exists($room, $sub)) {
			$subwoofer = "SUB";
			LOGINF("system/network.php: Player '{$room}' has been identified with SUBWOOFER connected.");
		}
		// SUR mapping
		$surround = "NOSUR";
		if ($sur !== "false" && is_array($sur) && array_key_exists($room, $sur)) {
			$surround = "SUR";
			LOGINF("system/network.php: Player '{$room}' has been identified as SURROUND System.");
		}
		// Capabilities
		$audioclip = in_array("AUDIO_CLIP", $capabilities, true);
		$voice     = in_array("VOICE", $capabilities, true);
		LOGINF("system/network.php: Player '{$room}' (" . strtoupper($device) . ") is" . ($audioclip ? " " : " not ") . "AUDIO_CLIP capable");
		LOGINF("system/network.php: Player '{$room}' (" . strtoupper($device) . ") is" . ($voice ? " " : " not ") . "VOICE capable");
		// Keep schema compatible with your Perl:
		// [0]=ip
		// [1]=rincon
		// [2]=modelDisplayName (uppercase)
		// [3]=t2svol (empty default)
		// [4]=sonosvol (empty default)
		// [5]=maxvol (empty default)
		// [6]=mainchk value (empty default)
		// [7]=model (for icons)
		// [8]=sub (SUB/NOSUB)
		// [9]=householdId
		// [10]=sur (SUR/NOSUR)
		// [11]=audioclip (bool)
		// [12]=voice (bool)
		// [13]=SB flag or empty
		// [14]=TV vol for soundbar (empty default)
		// [15]=playlimit start (empty default)
		// [16]=playlimit end (empty default)
		$zonen = [
			$zoneip,
			$rinconid,
			(string)strtoupper($device),
			"",
			"",
			"",
			"",
			$model,
			$subwoofer,
			$householdId,
			$surround,
			$audioclip,
			$voice,
			"",
			"",
			"",
			""
		];
		// Soundbar detection (if Helper.php provides isSoundbar)
		if (function_exists('isSoundbar') && isSoundbar($model) === true) {
			$zonen[13] = "SB";
			$zonen[14] = ""; // TV vol default empty
			LOGINF("system/network.php: Player '{$room}' (" . strtoupper($device) . ") has been identified as Soundbar.");
		}
		$sonosplayerfinal[$room] = $zonen;
		// Optional: icon download only if missing (small timeout; don't block discovery much)
		if (!empty($model)) {
			$img = $lbphtmldir . "/images/icon-{$model}.png";
			if (!file_exists($img)) {
				LOGDEB("system/network.php: Fetching player icon for model '{$model}' from {$zoneip}...");
				$icon = http_get_quick("http://{$zoneip}:1400/img/icon-{$model}.png", 800, 500);
				if (!empty($icon)) {
					@file_put_contents($img, $icon);
					LOGDEB("system/network.php: Icon for '{$model}' saved to {$img}.");
				} else {
					LOGDEB("system/network.php: Icon fetch failed for model '{$model}' – not critical.");
				}
			}
		}
	}
	if (empty($sonosplayerfinal)) {
		LOGERR("system/network.php: Something went wrong... Device(s) has been found but could not be added to your system!");
		return [];
	}
	try {
		ksort($sonosplayerfinal);
	} catch (Exception $e) {
		LOGERR("system/network.php: Array of devices could not be sorted!");
		return [];
	}
	// Logging like before (new devices only)
	foreach ($sonosplayerfinal as $found_zone => $arr) {
		LOGOK("system/network.php: New Sonos Player: '" . $arr[2] . "' called: '" . $found_zone . "' using IP: '" . $arr[0] . "' and Rincon-ID: '" . $arr[1] . "' will be added to your Plugin.");
	}
	return $sonosplayerfinal;
}
/**
 * Quick GET helper with short timeouts
 */
function http_get_quick($url, $timeout_ms = 800, $connect_ms = 500) {
	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT_MS => $connect_ms,
		CURLOPT_TIMEOUT_MS => $timeout_ms,
		CURLOPT_NOSIGNAL => true,
	]);
	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($code === 200 && !empty($body)) return $body;
	return "";
}
/**
 * Cache writer
 */
function cache_write($file, $json) {
	$payload = json_encode([
		'ts'   => time(),
		'json' => (string)$json
	]);
	@file_put_contents($file, $payload);
}
function shutdown() {
	global $log;
	// nothing yet
}
/**
 * GetSub / GetSur: Detect Subwoofer or Surround players in the topology.
 *
 * Iterates through ALL discovered devices until one responds successfully
 * to GetZoneStates (robust against individual player timeouts or VLAN drops).
 *
 * @param string[] $devices  List of discovered Sonos IPs
 * @param string   $val      "SW" (subwoofer) or "LR" (surround left/right)
 * @return array|string      Map of room names to group key, or "false" if none found
 */
function GetSub($devices, $val) {
	global $sonos;
	if ($val !== "SW" && $val !== "LR") {
		LOGERR("system/network.php: GetSub called with invalid parameter '{$val}' (must be SW or LR).");
		return "invalid entries";
	}
	$key = ($val === "SW") ? "SUB" : "SUR";
	require_once(LBPHTMLDIR . '/system/bin/XmlToArray.php');
	$xml = null;
	// --- IMPROVED: iterate all devices until one responds ---
	// Previously only $devices[0] was tried, causing silent failure
	// if the first player was offline, a satellite, or blocked by firewall.
	foreach ($devices as $candidate_ip) {
		try {
			LOGDEB("system/network.php: GetSub({$val}): Trying GetZoneStates on {$candidate_ip}...");
			$sonos = new SonosAccess($candidate_ip);
			$xml   = $sonos->GetZoneStates();
			if (!empty($xml)) {
				LOGDEB("system/network.php: GetSub({$val}): GetZoneStates succeeded on {$candidate_ip}.");
				break; // use first successful response
			}
		} catch (Exception $e) {
			LOGWARN("system/network.php: GetSub({$val}): GetZoneStates failed for {$candidate_ip}: " . $e->getMessage());
			$xml = null;
			// continue to next device
		}
	}
	if (empty($xml)) {
		LOGWARN("system/network.php: GetSub({$val}): GetZoneStates failed on all " . count($devices) . " device(s). {$key} detection skipped.");
		return "false";
	}
	$array   = XmlToArray::convert($xml);
	$interim = $array['ZoneGroupState']['ZoneGroups']['ZoneGroup'] ?? [];
	$subsur  = [];
	foreach ($interim as $k => $value) {
		if (@$value['ZoneGroupMember']['attributes']['HTSatChanMapSet']) {
			$int = explode(";", $value['ZoneGroupMember']['attributes']['HTSatChanMapSet']);
			foreach ($int as $a) {
				$a = substr($a, -2);
				if ($a == $val) {
					$subsur[strtolower($value['ZoneGroupMember']['attributes']['ZoneName'])] = $k;
				}
			}
		}
	}
	if (empty($subsur)) {
		$subsur = "false";
	}
	return $subsur;
}
?>