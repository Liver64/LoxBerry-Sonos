<?php
/**
 * Sonos4Lox - Core Discovery (Optimized for Auto-Discovery)
 * Version: CORE_DISCOVERY_LOGGER_CLEANUP_V03_2026_07_19
 * - added additional code to discover sub 
 *
 * Goals:
 * - Fast SSDP discovery (fixed window)
 * - De-duplicate SSDP replies (VLAN relays / multicast proxy duplicates)
 * - Parallel fetch of http://<ip>:1400/info via curl_multi
 * - Delta-check by RINCON vs existing s4lox_config.json (only NEW devices processed deeply)
 * - Optional cache in /dev/shm to speed up UI auto-discovery
 * - CLI support for Perl qx():
 *     /usr/bin/php src/Core/Discovery/Discovery.php --ttl=60 --force=1
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
 
require_once __DIR__ . "/../Sonos/sonosAccess.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";

$pluginHtmlDir = defined('LBPHTMLDIR') ? LBPHTMLDIR : (isset($lbphtmldir) ? $lbphtmldir : dirname(__DIR__, 3));

require_once $pluginHtmlDir . "/src/Support/Logger.php";
require_once $pluginHtmlDir . "/src/Support/ErrorHandler.php";
require_once $pluginHtmlDir . "/Helper.php";

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

/**
 * Write Discovery log messages through the central Sonos4Lox logger.
 * Keeps the existing message text while removing the old direct LOG* calls.
 */
function s4l_discovery_log($message, $level)
{
	S4L_Logger::write($message, $level, __FILE__);
}
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
		if ($arg === '--unicast-only=1' || $arg === '--unicast-only') {
			$_GET['unicast_only'] = '1';
		}
	}
}
global $sonosfinal, $sonosnet, $devices;

// ---- Cache settings (for auto-discovery speed) ----
$cache_dir  = "/dev/shm/sonos4lox";
$cache_file = $cache_dir . "/discovery_cache.json";
$force        = isset($_GET['force']) && (string)$_GET['force'] === "1";
$unicast_only = isset($_GET['unicast_only']) && (string)$_GET['unicast_only'] === "1";
$ttl          = isset($_GET['ttl']) ? (int)$_GET['ttl'] : 60;

if ($unicast_only) {
	$force = true;
	$ttl   = 0;
}
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
s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP multicast TTL configured as {$mcast_ttl}.", S4L_Logger::LEVEL_DEBUG);
if ($mcast_ttl === 1) {
	s4l_discovery_log("src/Core/Discovery/Discovery.php: Multicast TTL=1 (default) – SSDP packets will not cross routers/VLANs. Use --mcast-ttl=X (X>1) if Sonos players are in a separate VLAN.", S4L_Logger::LEVEL_DEBUG);
}
if (!is_dir($cache_dir)) {
	@mkdir($cache_dir, 0775, true);
}
// ttl=0 => disable cache and remove any existing cache file
if ($ttl === 0 && file_exists($cache_file)) {
	@unlink($cache_file);
	s4l_discovery_log("src/Core/Discovery/Discovery.php: ttl=0 -> cache file removed ($cache_file)", S4L_Logger::LEVEL_DEBUG);
}
// Serve cache quickly if valid (and not forced)
if (!$force && $ttl > 0 && file_exists($cache_file)) {
	$raw = @file_get_contents($cache_file);
	$cache = json_decode((string)$raw, true);
	if (is_array($cache) && isset($cache['ts']) && isset($cache['json'])) {
		$age = time() - (int)$cache['ts'];
		if ($age >= 0 && $age <= $ttl) {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Discovery cache hit (age {$age}s, ttl {$ttl}s).", S4L_Logger::LEVEL_DEBUG);
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
	s4l_discovery_log("src/Core/Discovery/Discovery.php: VLAN unicast fallback configured with " . count($static_ips) . " static IP(s): " . implode(", ", $static_ips), S4L_Logger::LEVEL_INFO);
}
if ($unicast_only) {
	s4l_discovery_log("src/Core/Discovery/Discovery.php: UNICAST-ONLY mode enabled – skipping SSDP multicast and broadcast.", S4L_Logger::LEVEL_INFO);

	if (empty($static_ips)) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: UNICAST-ONLY requested, but no vlan_static_ips configured.", S4L_Logger::LEVEL_WARNING);

		$out = json_encode([
			'__vlan_hint__' => 1,
			'reason'        => 'ssdp_failed_no_static_ips',
			'mcast_ttl'     => $mcast_ttl,
		]);

		@unlink($cache_file);
		header('Content-Type: application/json; charset=utf-8');
		echo $out;
		exit;
	}

	s4l_discovery_log("src/Core/Discovery/Discovery.php: Trying UNICAST fallback with " . count($static_ips) . " configured static IP(s): " . implode(", ", $static_ips), S4L_Logger::LEVEL_INFO);

	$devices = test_sonos_ips_unicast($static_ips);

	if (empty($devices)) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: UNICAST-ONLY failed for all configured IPs.", S4L_Logger::LEVEL_WARNING);
		s4l_discovery_log("src/Core/Discovery/Discovery.php: Check that TCP port 1400 is open from LoxBerry to Sonos VLAN.", S4L_Logger::LEVEL_WARNING);

		$out = json_encode([
			'__vlan_hint__' => 1,
			'reason'        => 'unicast_failed',
			'mcast_ttl'     => $mcast_ttl,
			'tried_ips'     => array_values($static_ips),
		]);

		@unlink($cache_file);
		header('Content-Type: application/json; charset=utf-8');
		echo $out;
		exit;
	}

	s4l_discovery_log("src/Core/Discovery/Discovery.php: UNICAST-ONLY succeeded. Reachable Sonos IP(s): " . implode(", ", $devices), S4L_Logger::LEVEL_INFO);
} else {
	s4l_discovery_log("src/Core/Discovery/Discovery.php: Start scanning for Sonos Players using MULTICAST SSDP: {$ssdp_ip}:{$ssdp_port}", S4L_Logger::LEVEL_DEBUG);

	// ---- 1) SSDP multicast scan ----
	$devices = ssdp_discover_ips_multicast($ssdp_ip, $ssdp_port, $search, 2500, $mcast_ttl);

	// ---- 2) Broadcast fallback if multicast yielded nothing ----
	if (empty($devices)) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: No Sonos devices detected via MULTICAST.", S4L_Logger::LEVEL_WARNING);

		if ($mcast_ttl === 1) {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: VLAN hint: If Sonos players are in a different VLAN/subnet, multicast (TTL=1) does not cross routers. Options:", S4L_Logger::LEVEL_WARNING);
			s4l_discovery_log("src/Core/Discovery/Discovery.php:   (A) Set up a multicast relay (igmpproxy, smcroute, avahi-daemon) between VLANs.", S4L_Logger::LEVEL_WARNING);
			s4l_discovery_log("src/Core/Discovery/Discovery.php:   (B) Increase multicast TTL: call Discovery.php with --mcast-ttl=4 (requires router support).", S4L_Logger::LEVEL_WARNING);
			s4l_discovery_log("src/Core/Discovery/Discovery.php:   (C) Use unicast fallback: add 'vlan_static_ips' array to s4lox_config.json.", S4L_Logger::LEVEL_WARNING);
		} else {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Multicast TTL={$mcast_ttl} was used – ensure your router/switch relays multicast group 239.255.255.250 between VLANs.", S4L_Logger::LEVEL_WARNING);
		}

		s4l_discovery_log("src/Core/Discovery/Discovery.php: Trying BROADCAST fallback (255.255.255.255).", S4L_Logger::LEVEL_WARNING);
		$devices = ssdp_discover_ips_broadcast($ssdp_port, $search, 2500);
	}

	// ---- 3) VLAN Hint / Unicast fallback if broadcast also yielded nothing ----
	if (empty($devices)) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: No Sonos devices detected via BROADCAST either.", S4L_Logger::LEVEL_WARNING);
		s4l_discovery_log("src/Core/Discovery/Discovery.php: Broadcast does not cross routers/VLANs by design – likely a VLAN environment.", S4L_Logger::LEVEL_WARNING);

		if (!empty($static_ips)) {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Trying UNICAST fallback with " . count($static_ips) . " configured static IP(s): " . implode(", ", $static_ips), S4L_Logger::LEVEL_INFO);

			$unicast_devices = test_sonos_ips_unicast($static_ips);

			if (!empty($unicast_devices)) {
				s4l_discovery_log("src/Core/Discovery/Discovery.php: UNICAST fallback succeeded. Reachable Sonos IP(s): " . implode(", ", $unicast_devices), S4L_Logger::LEVEL_INFO);
				$devices = $unicast_devices;
			} else {
				s4l_discovery_log("src/Core/Discovery/Discovery.php: UNICAST fallback also failed for all configured IPs.", S4L_Logger::LEVEL_WARNING);
				s4l_discovery_log("src/Core/Discovery/Discovery.php: Check that TCP port 1400 is open from LoxBerry to Sonos VLAN.", S4L_Logger::LEVEL_WARNING);
				s4l_discovery_log("src/Core/Discovery/Discovery.php: Returning VLAN hint (reason: unicast_failed) to Perl caller.", S4L_Logger::LEVEL_WARNING);

				$out = json_encode([
					'__vlan_hint__' => 1,
					'reason'        => 'unicast_failed',
					'mcast_ttl'     => $mcast_ttl,
					'tried_ips'     => array_values($static_ips),
				]);

				@unlink($cache_file);
				header('Content-Type: application/json; charset=utf-8');
				echo $out;
				exit;
			}
		} else {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: No vlan_static_ips configured – cannot attempt unicast fallback.", S4L_Logger::LEVEL_WARNING);
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Returning VLAN hint (reason: ssdp_failed_no_static_ips) to Perl caller.", S4L_Logger::LEVEL_WARNING);

			$out = json_encode([
				'__vlan_hint__' => 1,
				'reason'        => 'ssdp_failed_no_static_ips',
				'mcast_ttl'     => $mcast_ttl,
			]);

			@unlink($cache_file);
			header('Content-Type: application/json; charset=utf-8');
			echo $out;
			exit;
		}
	}
}
// Hard-dedupe
$devices = array_values(array_unique(array_filter($devices)));
if (empty($devices)) {
	s4l_discovery_log("src/Core/Discovery/Discovery.php: System has not detected any Sonos devices by scanning (multicast / broadcast / unicast).", S4L_Logger::LEVEL_WARNING);
	$out = "[]";
	cache_write($cache_file, '[]');
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}
s4l_discovery_log("src/Core/Discovery/Discovery.php: Discovery found " . count($devices) . " unique IP(s): " . implode(", ", $devices), S4L_Logger::LEVEL_INFO);
// ---- 4) Fetch /info in parallel for ALL discovered IPs ----
s4l_discovery_log("src/Core/Discovery/Discovery.php: Fetching /info (parallel) for " . count($devices) . " IP(s)...", S4L_Logger::LEVEL_DEBUG);
$info_by_ip = fetch_sonos_info_parallel($devices, 3500, 1200);
s4l_discovery_log("src/Core/Discovery/Discovery.php: Parallel /info fetch completed. Got responses from " . count($info_by_ip) . " of " . count($devices) . " IP(s).", S4L_Logger::LEVEL_DEBUG);
// Retry: Some players answer /info late (WiFi/VLAN/Busy). Try serial fetch for missing IPs.
foreach ($devices as $ip) {
	if (isset($info_by_ip[$ip]) && is_array($info_by_ip[$ip])) {
		continue;
	}
	s4l_discovery_log("src/Core/Discovery/Discovery.php: /info missing for {$ip} after parallel fetch – retrying serial (5s timeout)...", S4L_Logger::LEVEL_DEBUG);
	$url = "http://{$ip}:1400/info";
	$body = http_get_quick($url, 5000, 1500); // 5s timeout, 1.5s connect
	if (!empty($body)) {
		$json = json_decode((string)$body, true);
		if (is_array($json)) {
			$info_by_ip[$ip] = $json;
			s4l_discovery_log("src/Core/Discovery/Discovery.php: /info serial retry succeeded for {$ip}.", S4L_Logger::LEVEL_DEBUG);
		} else {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: /info serial retry for {$ip} returned non-JSON body – skipping.", S4L_Logger::LEVEL_WARNING);
		}
	} else {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: /info serial retry for {$ip} failed (TCP 1400 unreachable or timeout). Check firewall rules.", S4L_Logger::LEVEL_WARNING);
	}
}
// ---- 5) Delta-check by RINCON (only NEW candidates processed deeply) ----
$new_candidate_ips = [];
foreach ($devices as $ip) {
	$info = $info_by_ip[$ip] ?? null;
	if (!is_array($info) || empty($info['device']['id'])) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: /info missing or invalid for {$ip} – cannot determine RINCON, skipping candidate.", S4L_Logger::LEVEL_WARNING);
		s4l_discovery_log("src/Core/Discovery/Discovery.php:   -> Possible cause: TCP port 1400 blocked between LoxBerry and Sonos player at {$ip}.", S4L_Logger::LEVEL_WARNING);
		continue;
	}
	$rincon = (string)$info['device']['id'];
	$roomraw = (string)($info['device']['name'] ?? '(unknown)');
	if (isset($existing['rincons'][$rincon])) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: IP {$ip} ('{$roomraw}', RINCON {$rincon}) already known – skipping.", S4L_Logger::LEVEL_DEBUG);
		continue; // already known
	}
	s4l_discovery_log("src/Core/Discovery/Discovery.php: IP {$ip} ('{$roomraw}', RINCON {$rincon}) is NEW – adding to candidate list.", S4L_Logger::LEVEL_DEBUG);
	$new_candidate_ips[] = $ip;
}
$new_candidate_ips = array_values(array_unique(array_filter($new_candidate_ips)));
if (empty($new_candidate_ips)) {
	s4l_discovery_log("src/Core/Discovery/Discovery.php: No new Sonos Player has been detected (delta check by RINCON – all devices already known).", S4L_Logger::LEVEL_INFO);
	$out = "[]";
	cache_write($cache_file, $out);
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}
s4l_discovery_log("src/Core/Discovery/Discovery.php: New candidate IP(s) after delta check: " . implode(", ", $new_candidate_ips), S4L_Logger::LEVEL_INFO);
// ---- 6) Exclude satellites / RF of stereo pair / surround (expensive SOAP) - only for NEW candidates ----
$devicecheck = [];
foreach ($new_candidate_ips as $newzoneip) {
	try {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: Checking SOAP GetZoneGroupAttributes for {$newzoneip}...", S4L_Logger::LEVEL_DEBUG);
		$sonos = new SonosAccess($newzoneip);
		$zone_details = $sonos->GetZoneGroupAttributes();
		if (!empty($zone_details['CurrentZonePlayerUUIDsInGroup'])) {
			$devicecheck[] = $newzoneip;
			s4l_discovery_log("src/Core/Discovery/Discovery.php: {$newzoneip} is a primary zone player – keeping.", S4L_Logger::LEVEL_DEBUG);
		} else {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: IP-address '{$newzoneip}' seems to be a part of a Stereopair/Surround setup and has not been added.", S4L_Logger::LEVEL_DEBUG);
		}
	} catch (Exception $e) {
		// be permissive - keep device if SOAP fails
		s4l_discovery_log("src/Core/Discovery/Discovery.php: GetZoneGroupAttributes failed for '{$newzoneip}' -> keeping as candidate. Error: " . $e->getMessage(), S4L_Logger::LEVEL_WARNING);
		s4l_discovery_log("src/Core/Discovery/Discovery.php:   -> Possible cause: TCP port 1400 blocked or player busy. Check firewall.", S4L_Logger::LEVEL_WARNING);
		$devicecheck[] = $newzoneip;
	}
}
$devicecheck = array_values(array_unique(array_filter($devicecheck)));
if (empty($devicecheck)) {
	s4l_discovery_log("src/Core/Discovery/Discovery.php: All new candidates were excluded as satellites – nothing to add.", S4L_Logger::LEVEL_INFO);
	$out = "[]";
	cache_write($cache_file, $out);
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}
// ---- 7) Build final zones for NEW devices only (using cached /info data) ----
$sonosfinal = getSonosDevicesOptimized($devicecheck, $info_by_ip, $myConfigFolder, $myConfigFile);
if (empty($sonosfinal)) {
	s4l_discovery_log("src/Core/Discovery/Discovery.php: No new Sonos Player has been detected after processing.", S4L_Logger::LEVEL_INFO);
	$out = "[]";
	cache_write($cache_file, $out);
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}
s4l_discovery_log("src/Core/Discovery/Discovery.php: New Players has been detected and converted to JSON (" . count($sonosfinal) . ").", S4L_Logger::LEVEL_OK);
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
		s4l_discovery_log("src/Core/Discovery/Discovery.php: The file '{$path}' does not exist – treating as empty config.", S4L_Logger::LEVEL_WARNING);
		return [];
	}
	$raw = @file_get_contents($path);
	$cfg = json_decode((string)$raw, true);
	if (!is_array($cfg)) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: The file '{$path}' could not be parsed – treating as empty config.", S4L_Logger::LEVEL_WARNING);
		return [];
	}
	if (array_key_exists('sonoszonen', $cfg) && is_array($cfg['sonoszonen'])) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: Existing configuration file 's4lox_config.json' has been loaded successfully.", S4L_Logger::LEVEL_OK);
		return $cfg['sonoszonen'];
	}
	s4l_discovery_log("src/Core/Discovery/Discovery.php: Config exists but has no 'sonoszonen' – treating as empty.", S4L_Logger::LEVEL_INFO);
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
			s4l_discovery_log("src/Core/Discovery/Discovery.php: vlan_static_ips contains invalid entry '{$ip}' – ignoring.", S4L_Logger::LEVEL_WARNING);
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
	s4l_discovery_log("src/Core/Discovery/Discovery.php: Unicast probe – testing " . count($ips) . " IP(s) via http://<ip>:1400/info ...", S4L_Logger::LEVEL_DEBUG);
	// Re-use parallel fetch with generous timeout for first contact
	$info_by_ip = fetch_sonos_info_parallel($ips, 4000, 2000);
	$reachable = [];
	foreach ($ips as $ip) {
		if (isset($info_by_ip[$ip]) && is_array($info_by_ip[$ip])) {
			$model = $info_by_ip[$ip]['device']['model'] ?? '?';
			$name  = $info_by_ip[$ip]['device']['name']  ?? '?';
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Unicast probe OK: {$ip} -> '{$name}' ({$model})", S4L_Logger::LEVEL_DEBUG);
			$reachable[] = $ip;
		} else {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Unicast probe FAILED for {$ip} – no response on TCP 1400.", S4L_Logger::LEVEL_DEBUG);
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
		s4l_discovery_log("src/Core/Discovery/Discovery.php: socket_create failed for multicast SSDP.", S4L_Logger::LEVEL_ERROR);
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
		s4l_discovery_log("src/Core/Discovery/Discovery.php: IP_MULTICAST_TTL set to {$mcast_ttl}.", S4L_Logger::LEVEL_DEBUG);
	} else {
		// Linux fallback: IP_MULTICAST_TTL = 33
		@socket_set_option($sock, IPPROTO_IP, 33, (int)$mcast_ttl);
		s4l_discovery_log("src/Core/Discovery/Discovery.php: IP_MULTICAST_TTL constant missing, fallback option 33 set to {$mcast_ttl}.", S4L_Logger::LEVEL_DEBUG);
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
		s4l_discovery_log("src/Core/Discovery/Discovery.php: socket_sendto failed for multicast SSDP – socket send error.", S4L_Logger::LEVEL_WARNING);
	} else {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP M-SEARCH multicast sent ({$sent} bytes) to {$ip}:{$port}, window {$window_ms}ms.", S4L_Logger::LEVEL_DEBUG);
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
			s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP response from {$from} ignored (ST mismatch: '" . ($hdr['st'] ?? '') . "').", S4L_Logger::LEVEL_DEBUG);
			continue;
		}
		if (empty($hdr['location'])) {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP response from {$from} ignored (no LOCATION header).", S4L_Logger::LEVEL_DEBUG);
			continue;
		}
		$usn = $hdr['usn'] ?? '';
		if ($usn !== '' && isset($usn_seen[$usn])) {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP response from {$from} ignored (duplicate USN: {$usn}).", S4L_Logger::LEVEL_DEBUG);
			continue;
		}
		if ($usn !== '') $usn_seen[$usn] = true;
		$url = @parse_url($hdr['location']);
		if (!is_array($url) || empty($url['host'])) {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP response from {$from} ignored (could not parse LOCATION: " . ($hdr['location'] ?? '') . ").", S4L_Logger::LEVEL_DEBUG);
			continue;
		}
		$found_ip = (string)$url['host'];
		s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP multicast response: {$from} -> LOCATION host: {$found_ip}", S4L_Logger::LEVEL_DEBUG);
		$ips[] = $found_ip;
	}
	@socket_close($sock);
	s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP multicast window closed. Total responses received: {$responses}, unique IPs found: " . count(array_unique($ips)), S4L_Logger::LEVEL_DEBUG);
	return array_values(array_unique($ips));
}
/**
 * SSDP broadcast fallback: returns array of IPs
 */
function ssdp_discover_ips_broadcast($port, $st, $window_ms = 900) {
	$broadcastip = '255.255.255.255';
	s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP BROADCAST M-SEARCH to {$broadcastip}:{$port}, window {$window_ms}ms.", S4L_Logger::LEVEL_DEBUG);
	$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($sock === false) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: socket_create failed for broadcast SSDP.", S4L_Logger::LEVEL_ERROR);
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
		s4l_discovery_log("src/Core/Discovery/Discovery.php: socket_sendto failed for broadcast SSDP.", S4L_Logger::LEVEL_WARNING);
	} else {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP M-SEARCH broadcast sent ({$sent} bytes).", S4L_Logger::LEVEL_DEBUG);
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
		s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP broadcast response: {$from} -> LOCATION host: {$found_ip}", S4L_Logger::LEVEL_DEBUG);
		$ips[] = $found_ip;
	}
	@socket_close($sock);
	s4l_discovery_log("src/Core/Discovery/Discovery.php: SSDP broadcast window closed. Total responses: {$responses}, unique IPs: " . count(array_unique($ips)), S4L_Logger::LEVEL_DEBUG);
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
				s4l_discovery_log("src/Core/Discovery/Discovery.php: /info for {$ip} returned HTTP 200 but non-JSON body.", S4L_Logger::LEVEL_WARNING);
			}
		} elseif ($code !== 0) {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: /info for {$ip} returned HTTP {$code}" . ($curl_err ? " ({$curl_err})" : "") . ".", S4L_Logger::LEVEL_DEBUG);
		} else {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: /info for {$ip} – no response" . ($curl_err ? " ({$curl_err})" : "") . ".", S4L_Logger::LEVEL_DEBUG);
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
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Missing /info data for '{$zoneip}' – skipping.", S4L_Logger::LEVEL_WARNING);
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
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Player '{$room}' has been identified as Generation S1 and TTS/MP3 is only possible as Group Member!", S4L_Logger::LEVEL_WARNING);
			notify(LBPPLUGINDIR, "Sonos", "Player '{$room}' has been identified as Generation S1 and TTS/MP3 is only possible as Group Member!", "warning");
		}
		// SUB mapping
		$subwoofer = "NOSUB";
		if ($sub !== "false" && is_array($sub) && array_key_exists($room, $sub)) {
			$subwoofer = "SUB";
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Player '{$room}' has been identified with SUBWOOFER connected.", S4L_Logger::LEVEL_INFO);
		}
		// SUR mapping
		$surround = "NOSUR";
		if ($sur !== "false" && is_array($sur) && array_key_exists($room, $sur)) {
			$surround = "SUR";
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Player '{$room}' has been identified as SURROUND System.", S4L_Logger::LEVEL_INFO);
		}
		// Capabilities
		$audioclip = in_array("AUDIO_CLIP", $capabilities, true);
		$voice     = in_array("VOICE", $capabilities, true);
		s4l_discovery_log("src/Core/Discovery/Discovery.php: Player '{$room}' (" . strtoupper($device) . ") is" . ($audioclip ? " " : " not ") . "AUDIO_CLIP capable", S4L_Logger::LEVEL_INFO);
		s4l_discovery_log("src/Core/Discovery/Discovery.php: Player '{$room}' (" . strtoupper($device) . ") is" . ($voice ? " " : " not ") . "VOICE capable", S4L_Logger::LEVEL_INFO);
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
			s4l_discovery_log("src/Core/Discovery/Discovery.php: Player '{$room}' (" . strtoupper($device) . ") has been identified as Soundbar.", S4L_Logger::LEVEL_INFO);
		}
		$sonosplayerfinal[$room] = $zonen;
		// Optional: icon download only if missing (small timeout; don't block discovery much)
		if (!empty($model)) {
			$img = $lbphtmldir . "/images/icon-{$model}.png";
			if (!file_exists($img)) {
				s4l_discovery_log("src/Core/Discovery/Discovery.php: Fetching player icon for model '{$model}' from {$zoneip}...", S4L_Logger::LEVEL_DEBUG);
				$icon = http_get_quick("http://{$zoneip}:1400/img/icon-{$model}.png", 800, 500);
				if (!empty($icon)) {
					@file_put_contents($img, $icon);
					s4l_discovery_log("src/Core/Discovery/Discovery.php: Icon for '{$model}' saved to {$img}.", S4L_Logger::LEVEL_DEBUG);
				} else {
					s4l_discovery_log("src/Core/Discovery/Discovery.php: Icon fetch failed for model '{$model}' – not critical.", S4L_Logger::LEVEL_DEBUG);
				}
			}
		}
	}
	if (empty($sonosplayerfinal)) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: Something went wrong... Device(s) has been found but could not be added to your system!", S4L_Logger::LEVEL_ERROR);
		return [];
	}
	try {
		ksort($sonosplayerfinal);
	} catch (Exception $e) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: Array of devices could not be sorted!", S4L_Logger::LEVEL_ERROR);
		return [];
	}
	// Logging like before (new devices only)
	foreach ($sonosplayerfinal as $found_zone => $arr) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: New Sonos Player: '" . $arr[2] . "' called: '" . $found_zone . "' using IP: '" . $arr[0] . "' and Rincon-ID: '" . $arr[1] . "' will be added to your Plugin.", S4L_Logger::LEVEL_OK);
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
	global $sonos, $pluginHtmlDir;
	if ($val !== "SW" && $val !== "LR") {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: GetSub called with invalid parameter '{$val}' (must be SW or LR).", S4L_Logger::LEVEL_ERROR);
		return "invalid entries";
	}
	$key = ($val === "SW") ? "SUB" : "SUR";
	if (empty($pluginHtmlDir)) {
		$pluginHtmlDir = dirname(__DIR__, 3);
	}
	require_once $pluginHtmlDir . '/src/Support/Xml/XmlToArray.php';
	$xml = null;
	// --- IMPROVED: iterate all devices until one responds ---
	// Previously only $devices[0] was tried, causing silent failure
	// if the first player was offline, a satellite, or blocked by firewall.
	foreach ($devices as $candidate_ip) {
		try {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: GetSub({$val}): Trying GetZoneStates on {$candidate_ip}...", S4L_Logger::LEVEL_DEBUG);
			$sonos = new SonosAccess($candidate_ip);
			$xml   = $sonos->GetZoneStates();
			if (!empty($xml)) {
				s4l_discovery_log("src/Core/Discovery/Discovery.php: GetSub({$val}): GetZoneStates succeeded on {$candidate_ip}.", S4L_Logger::LEVEL_DEBUG);
				break; // use first successful response
			}
		} catch (Exception $e) {
			s4l_discovery_log("src/Core/Discovery/Discovery.php: GetSub({$val}): GetZoneStates failed for {$candidate_ip}: " . $e->getMessage(), S4L_Logger::LEVEL_WARNING);
			$xml = null;
			// continue to next device
		}
	}
	if (empty($xml)) {
		s4l_discovery_log("src/Core/Discovery/Discovery.php: GetSub({$val}): GetZoneStates failed on all " . count($devices) . " device(s). {$key} detection skipped.", S4L_Logger::LEVEL_WARNING);
		return "false";
	}
	$array   = XmlToArray::convert($xml);
	$interim = $array['ZoneGroupState']['ZoneGroups']['ZoneGroup'] ?? [];
	
	$subsur  = [];
	foreach ($interim as $groupKey => $group) {
		if (!isset($group['ZoneGroupMember'])) {
			continue;
		}
		$members = $group['ZoneGroupMember'];
		if (isset($members['attributes'])) {
			$members = [$members];
		}
		foreach ($members as $member) {
			$zoneName = strtolower($member['attributes']['ZoneName'] ?? '');
			$stack = [$member];
			while (!empty($stack)) {
				$node = array_pop($stack);
				if (!is_array($node)) {
					continue;
				}
				if (isset($node['attributes']['HTSatChanMapSet'])) {
					$map = $node['attributes']['HTSatChanMapSet'];
					foreach (explode(';', $map) as $entry) {
						if (substr($entry, -2) === $val) {
							$subsur[$zoneName] = $groupKey;
							break 2;
						}
					}
				}
				foreach ($node as $child) {
					if (!is_array($child)) {
						continue;
					}
					if (isset($child['attributes'])) {
						$stack[] = $child;
					} else {
						foreach ($child as $c) {
							if (is_array($c)) {
								$stack[] = $c;
							}
						}
					}
				}
			}
		}
	}
	if (empty($subsur)) {
		return "false";
	}
	return $subsur;​
}
?>
