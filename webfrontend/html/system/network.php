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
 * Output:
 * - JSON object of NEW zones only (room => array)
 * - EXACT "[]" when nothing new (for Perl compatibility)
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

if (!is_dir($cache_dir)) {
	@mkdir($cache_dir, 0775, true);
}

/* >>> ADD THIS BLOCK HERE <<< */
// ttl=0 => disable cache and remove any existing cache file
if ($ttl === 0 && file_exists($cache_file)) {
	@unlink($cache_file);
	LOGDEB("system/network.php: ttl=0 -> cache file removed ($cache_file)");
}
/* >>> END ADD <<< */

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

// ---- Load existing config early (delta-check) ----
$sonosnet = parse_cfg_file_safe($myConfigFolder, $myConfigFile);
$existing = build_existing_maps($sonosnet);

LOGDEB("Start scanning for Sonos Players using MULTICAST SSDP: {$ssdp_ip}:{$ssdp_port}");

// ---- 1) SSDP multicast scan (fast fixed window) ----
$devices = ssdp_discover_ips_multicast($ssdp_ip, $ssdp_port, $search, 2500);

// Fallback broadcast only if multicast yielded nothing
if (empty($devices)) {
	LOGWARN("system/network.php: No Sonos devices detected via MULTICAST. Trying BROADCAST fallback.");
	$devices = ssdp_discover_ips_broadcast($ssdp_port, $search, 2500);
}

// Hard-dedupe
$devices = array_values(array_unique(array_filter($devices)));

if (empty($devices)) {
	LOGWARN("system/network.php: System has not detected any Sonos devices by scanning SSDP (multicast/broadcast).");
	$out = "[]";
	cache_write($cache_file, $out);
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}

LOGINF("system/network.php: SSDP detected " . count($devices) . " unique IP(s).");

// ---- 2) Fetch /info in parallel for ALL discovered IPs ----
$info_by_ip = fetch_sonos_info_parallel($devices, 3500, 1200);

// Retry: Some players answer /info late (WiFi/VLAN/Busy). Try serial fetch for missing IPs.
foreach ($devices as $ip) {
    if (isset($info_by_ip[$ip]) && is_array($info_by_ip[$ip])) {
        continue;
    }

    LOGDEB("system/network.php: /info missing for {$ip} after parallel fetch – retrying serial...");
    $url = "http://{$ip}:1400/info";
    $body = http_get_quick($url, 5000, 1500); // 5s timeout, 1.5s connect
    if (!empty($body)) {
        $json = json_decode((string)$body, true);
        if (is_array($json)) {
            $info_by_ip[$ip] = $json;
            LOGDEB("system/network.php: /info retry succeeded for {$ip}.");
        }
    }
}

// ---- 3) Delta-check by RINCON (only NEW candidates processed deeply) ----
$new_candidate_ips = [];
foreach ($devices as $ip) {
	$info = $info_by_ip[$ip] ?? null;
	if (!is_array($info) || empty($info['device']['id'])) {
		LOGWARN("system/network.php: /info missing/invalid for {$ip} – skipping candidate.");
		continue;
	}
	$rincon = (string)$info['device']['id'];
	if (isset($existing['rincons'][$rincon])) {
		continue; // already known
	}
	$new_candidate_ips[] = $ip;
}

$new_candidate_ips = array_values(array_unique(array_filter($new_candidate_ips)));

if (empty($new_candidate_ips)) {
	LOGINF("system/network.php: No new Sonos Player has been detected (delta check by RINCON).");
	$out = "[]";
	cache_write($cache_file, $out);
	header('Content-Type: application/json; charset=utf-8');
	echo $out;
	exit;
}

LOGINF("system/network.php: New candidate IP(s) after delta check: " . implode(", ", $new_candidate_ips));

// ---- 4) Exclude satellites / RF of stereo pair / surround (expensive SOAP) - only for NEW candidates ----
$devicecheck = [];
foreach ($new_candidate_ips as $newzoneip) {
	try {
		$sonos = new SonosAccess($newzoneip);
		$zone_details = $sonos->GetZoneGroupAttributes();
		if (!empty($zone_details['CurrentZonePlayerUUIDsInGroup'])) {
			$devicecheck[] = $newzoneip;
		} else {
			LOGDEB("system/network.php: IP-address '{$newzoneip}' seems to be a part of a Stereopair/Surround setup and has not been added.");
		}
	} catch (Exception $e) {
		// be permissive - keep device if SOAP fails
		LOGWARN("system/network.php: GetZoneGroupAttributes failed for '{$newzoneip}' -> keeping as candidate. Error: " . $e->getMessage());
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

// ---- 5) Build final zones for NEW devices only (using cached /info data) ----
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
 * SSDP multicast discovery: returns array of IPs
 */
function ssdp_discover_ips_multicast($ip, $port, $st, $window_ms = 900) {
	$sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	if ($sock === false) {
		LOGERR("system/network.php: socket_create failed for multicast SSDP.");
		return [];
	}

	@socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
	@socket_set_nonblock($sock);

	$data  = "M-SEARCH * HTTP/1.1\r\n";
	$data .= "HOST: {$ip}:{$port}\r\n";
	$data .= "MAN: \"ssdp:discover\"\r\n";
	$data .= "MX: 2\r\n";
	$data .= "ST: {$st}\r\n";
	$data .= "\r\n";

	@socket_sendto($sock, $data, strlen($data), 0, $ip, $port);

	$deadline = microtime(true) + ($window_ms / 1000);
	$ips = [];
	$usn_seen = [];

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

		$hdr = parse_ssdp_headers($buf);
		if (empty($hdr['st']) || stripos($hdr['st'], $st) === false) continue;
		if (empty($hdr['location'])) continue;

		$usn = $hdr['usn'] ?? '';
		if ($usn !== '' && isset($usn_seen[$usn])) continue;
		if ($usn !== '') $usn_seen[$usn] = true;

		$url = @parse_url($hdr['location']);
		if (!is_array($url) || empty($url['host'])) continue;

		$ips[] = (string)$url['host'];
	}

	@socket_close($sock);
	return array_values(array_unique($ips));
}

/**
 * SSDP broadcast fallback: returns array of IPs
 */
function ssdp_discover_ips_broadcast($port, $st, $window_ms = 900) {
	$broadcastip = '255.255.255.255';

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

	@socket_sendto($sock, $data, strlen($data), 0, $broadcastip, $port);

	$deadline = microtime(true) + ($window_ms / 1000);
	$ips = [];
	$usn_seen = [];

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

		$hdr = parse_ssdp_headers($buf);
		if (empty($hdr['st']) || stripos($hdr['st'], $st) === false) continue;
		if (empty($hdr['location'])) continue;

		$usn = $hdr['usn'] ?? '';
		if ($usn !== '' && isset($usn_seen[$usn])) continue;
		if ($usn !== '') $usn_seen[$usn] = true;

		$url = @parse_url($hdr['location']);
		if (!is_array($url) || empty($url['host'])) continue;

		$ips[] = (string)$url['host'];
	}

	@socket_close($sock);
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

		if ($code === 200 && !empty($body)) {
			$json = json_decode((string)$body, true);
			if (is_array($json)) $result[$ip] = $json;
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

		// SUR mapping (BUGFIX: use $sur)
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
				$icon = http_get_quick("http://{$zoneip}:1400/img/icon-{$model}.png", 800, 500);
				if (!empty($icon)) {
					@file_put_contents($img, $icon);
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


/* Funktion :  GetSubSur --> check for Subwoofer/Surround available
/*
/* @param: IP of one Zone
/* @return: array room names
**/
function GetSub($devices, $val) {

	global $sonos;

	if ($val != "SW" and $val != "LR") {
		return "invalid entries";
	} elseif ($val == "SW") {
		$key = "SUB";
	} elseif ($val == "LR") {
		$key = "SUR";
	}

	require_once(LBPHTMLDIR . '/system/bin/XmlToArray.php');

	$sonos = new SonosAccess($devices[0]);
	$xml = $sonos->GetZoneStates();
	$array = XmlToArray::convert($xml);

	$interim = $array['ZoneGroupState']['ZoneGroups']['ZoneGroup'] ?? [];

	$subsur = array();
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
