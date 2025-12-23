<?php
declare(strict_types=1);

/**
 * Sonos4Lox - ms_inbound.php
 * Generate ONLY Loxone Virtual Input Template (MQTT -> UDP)
 *
 * Creates/overwrites:
 *   $lbphtmldir/system/VI_MQTT_UDP_Sonos.xml
 *
 * IMPORTANT:
 * Your MQTT gateway expands JSON to flat topics:
 *   - underscores become "##" (e.g. state##_code)
 *   - each JSON key becomes its own MQTT topic (topic/key=value)
 *
 * Therefore, this template subscribes to those expanded topics directly
 * (NO JSON extraction patterns).
 */

require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "bin/loxberry_loxonetemplatebuilder.php";

register_shutdown_function('shutdown');

global $config;

// ------------------------ Logging ------------------------
$params = [
    "name"     => "Sonos PHP",
    "filename" => "$lbplogdir/sonos.log",
    "append"   => 1,
    "addtime"  => 1,
];
$log = LBLog::newLog($params);
LOGSTART("Sonos PHP");

// ------------------------ Config load ------------------------
$configfile = $lbpconfigdir . "/s4lox_config.json";

if (!is_file($configfile)) {
    LOGCRIT("system/ms_inbound.php: Config not found: $configfile");
    exit(1);
}

$config = json_decode((string)file_get_contents($configfile), true);
if (!is_array($config)) {
    LOGCRIT("system/ms_inbound.php: Config JSON invalid: $configfile");
    exit(1);
}

// ------------------------ Guards ------------------------
if (($config['LOXONE']['LoxDaten'] ?? false) != true) {
    LOGWARN("system/ms_inbound.php: Communication to Loxone is switched off (LOXONE.LoxDaten=false).");
    exit(1);
}

if (empty($config['sonoszonen']) || !is_array($config['sonoszonen']) || count($config['sonoszonen']) < 1) {
    LOGERR("system/ms_inbound.php: No Sonos players configured (sonoszonen empty).");
    exit(1);
}

// ------------------------ Read UDP port from mqttgateway.json ------------------------
$mqttGwFile = "/opt/loxberry/config/system/mqttgateway.json";
if (!is_file($mqttGwFile)) {
    LOGERR("system/ms_inbound.php: mqttgateway.json not found: $mqttGwFile (cannot determine UDP port).");
    exit(1);
}

$mqttGw = json_decode((string)file_get_contents($mqttGwFile), true);
if (!is_array($mqttGw)) {
    LOGERR("system/ms_inbound.php: mqttgateway.json invalid JSON: $mqttGwFile");
    exit(1);
}

$udpPortRaw = $mqttGw['Main']['udpport'] ?? '';
$udpPort = (int)$udpPortRaw;

if ($udpPort <= 0 || $udpPort > 65535) {
    LOGERR("system/ms_inbound.php: Invalid UDP port in mqttgateway.json (Main->udpport). Value=" . (is_scalar($udpPortRaw) ? (string)$udpPortRaw : 'non-scalar'));
    exit(1);
}

// ------------------------ Output file ------------------------
$xmlOutFile = $lbphtmldir . "/system/VI_MQTT_UDP_Sonos.xml";
if (is_file($xmlOutFile)) {
    @unlink($xmlOutFile);
}

// ------------------------ Topic prefix ------------------------
$NEW_PREFIX = 's4lox/sonos';

// ------------------------ Build XML (VirtualInUdp) ------------------------
$VIudp = new VirtualInUdp([
    "Title"   => "Sonos4lox (MQTT UDP)",
    "Address" => "",
    "Port"    => (string)$udpPort
]);

/**
 * Add one UDP cmd line.
 * IMPORTANT:
 * - Build check strings so Loxone sees \i and \v literally
 */
function add_cmd(VirtualInUdp $vi, string $title, string $topic, bool $analog = true): void
{
    // Pattern: MQTT:\i<topic>=\i\v
    $check = 'MQTT:\i' . $topic . '=\i\v';

    $vi->VirtualInUdpCmd([
        "Title"  => $title,
        "Check"  => $check,
        "Analog" => $analog ? "true" : "false",
    ]);
}

// ============================================================
// HEALTH (expanded topics, not JSON parsing)
// ============================================================
$healthBase = $NEW_PREFIX . '/_health';

// Global health fields (from your dump)
add_cmd($VIudp, 'Health online_players',         $healthBase . '/online##_players', true);
add_cmd($VIudp, 'Health total_players',          $healthBase . '/total##_players', true);

// Per-room Online flags: <room>##_Online
foreach ($config['sonoszonen'] as $room => $dummy) {
    $roomName = (string)$room;
    add_cmd($VIudp, 'Health ' . $roomName . ' Online', $healthBase . '/' . $roomName . '##_Online', true);
}

// ============================================================
// PER ROOM TOPICS (expanded topics)
// ============================================================
foreach ($config['sonoszonen'] as $room => $dummy) {

    $roomName = (string)$room;

    // ---------- EQ ----------
    $eqBase = $NEW_PREFIX . '/' . $roomName . '/eq';
    add_cmd($VIudp, 'EQ bass '        . $roomName, $eqBase . '/bass', true);
    add_cmd($VIudp, 'EQ treble '      . $roomName, $eqBase . '/treble', true);
    add_cmd($VIudp, 'EQ loudness '    . $roomName, $eqBase . '/loudness', true);
    add_cmd($VIudp, 'EQ balance '     . $roomName, $eqBase . '/balance', true);
    add_cmd($VIudp, 'EQ subgain '     . $roomName, $eqBase . '/subgain', true);

    // ---------- STATE ----------
    $stateBase = $NEW_PREFIX . '/' . $roomName . '/state';
    add_cmd($VIudp, 'State_code '     . $roomName, $stateBase . '/state##_code', true);
	
	// ---------- GROUP ----------
    $stateBase = $NEW_PREFIX . '/' . $roomName . '/group';
    add_cmd($VIudp, 'Group_code '     . $roomName, $stateBase . '/role##_code', true);
	
	// ---------- TRACK (SOURCE) ----------
    $stateBase = $NEW_PREFIX . '/' . $roomName . '/track';
    add_cmd($VIudp, 'Source_code '     . $roomName, $stateBase . '/source', true);

    // ---------- VOLUME ----------
    $volBase = $NEW_PREFIX . '/' . $roomName . '/volume';
    add_cmd($VIudp, 'Volume '  . $roomName, $volBase . '/volume', true);

    // ---------- MUTE ----------
    $muteBase = $NEW_PREFIX . '/' . $roomName . '/mute';
    add_cmd($VIudp, 'Mute '           . $roomName, $muteBase . '/mute', true);

    // ---------- PLAYMODE ----------
    $pmBase = $NEW_PREFIX . '/' . $roomName . '/playmode';
	add_cmd($VIudp, 'Playmode_code '  . $roomName, $pmBase . '/code', true);

    // ---------- POSITION ----------
    $posBase = $NEW_PREFIX . '/' . $roomName . '/position';
    add_cmd($VIudp, 'Pos track_no '       . $roomName, $posBase . '/track##_no', true);
    add_cmd($VIudp, 'Pos track_count '    . $roomName, $posBase . '/track##_count', true);
}

// ------------------------ Write XML ------------------------
$xml = $VIudp->output();

// Add BOM (same as your existing scripts)
$xml = chr(239) . chr(187) . chr(191) . $xml;

$ok = @file_put_contents($xmlOutFile, $xml);
if ($ok === false) {
    LOGERR("system/ms_inbound.php: Failed writing template: $xmlOutFile");
    exit(1);
}

LOGOK("system/ms_inbound.php: VI_MQTT_UDP_Sonos.xml created at '$xmlOutFile' (UDP port: $udpPort)");
exit(0);

function shutdown(): void
{
    // no-op
}
