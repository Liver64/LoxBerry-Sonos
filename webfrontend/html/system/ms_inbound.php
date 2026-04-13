<?php
declare(strict_types=1);

/**
 * Sonos4Lox - ms_inbound.php
 * Generate Loxone Virtual Input Templates
 *
 * Creates/overwrites:
 *   $lbphtmldir/system/VI_MQTT_UDP_Sonos.xml
 *   $lbphtmldir/system/VI_UDP_Sonos.xml
 *
 * IMPORTANT:
 * 1) MQTT gateway template:
 *    Your MQTT gateway expands JSON to flat topics:
 *      - underscores become "##" (e.g. state##_code)
 *      - each JSON key becomes its own MQTT topic (topic/key=value)
 *    Therefore this template subscribes to those expanded topics directly
 *    (NO JSON extraction patterns).
 *
 * 2) Direct UDP template:
 *    Your direct Sonos UDP format in Loxone UDP Monitor is:
 *      s4lox: <key>@<value>
 *
 *    Examples:
 *      s4lox: kueche_volume@3
 *      s4lox: kueche_mute@0
 *      s4lox: kueche_playmode_code@99
 *
 *    Therefore VI_UDP_Sonos.xml must use checks like:
 *      s4lox: kueche_volume@\v
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

// ------------------------ Read direct UDP port from plugin config ------------------------
$udpDirectRaw  = trim((string)($config['LOXONE']['UDP'] ?? ''));
$udpDirectPort = 0;
$hasDirectUdp  = ($udpDirectRaw !== '');

if ($hasDirectUdp) {
    $udpDirectPort = (int)$udpDirectRaw;
    validate_port($udpDirectPort, $udpDirectRaw, "LOXONE->UDP");
} else {
    LOGINF("system/ms_inbound.php: No direct UDP port configured in LOXONE->UDP - skipping direct UDP template.");
}

// ------------------------ Read MQTT gateway UDP port ------------------------
$mqttGwFile = "REPLACELBHOMEDIR/config/system/mqttgateway.json";
if (!is_file($mqttGwFile)) {
    LOGERR("system/ms_inbound.php: mqttgateway.json not found: $mqttGwFile (cannot determine MQTT UDP port).");
    exit(1);
}

$mqttGw = json_decode((string)file_get_contents($mqttGwFile), true);
if (!is_array($mqttGw)) {
    LOGERR("system/ms_inbound.php: mqttgateway.json invalid JSON: $mqttGwFile");
    exit(1);
}

$udpMqttRaw = $mqttGw['Main']['udpport'] ?? '';
$udpMqttPort = (int)$udpMqttRaw;
validate_port($udpMqttPort, $udpMqttRaw, "mqttgateway.json Main->udpport");

// ------------------------ Output files ------------------------
$xmlOutFileMqtt = $lbphtmldir . "/system/VI_MQTT_UDP_Sonos.xml";
$xmlOutFileUdp  = $lbphtmldir . "/system/VI_UDP_Sonos.xml";

if (is_file($xmlOutFileMqtt)) {
    @unlink($xmlOutFileMqtt);
}
if (is_file($xmlOutFileUdp)) {
    @unlink($xmlOutFileUdp);
}

// ------------------------ Topic prefix ------------------------
$NEW_PREFIX = 's4lox/sonos';

// ------------------------ Build MQTT UDP XML ------------------------
$VImqtt = new VirtualInUdp([
    "Title"   => "Sonos4Lox (MQTT UDP)",
    "Address" => "",
    "Port"    => (string)$udpMqttPort
]);

build_mqtt_template_commands($VImqtt, $NEW_PREFIX, array_keys($config['sonoszonen']));
write_xml_file($xmlOutFileMqtt, $VImqtt->output(), "VI_MQTT_UDP_Sonos.xml");

// ------------------------ Build direct UDP XML ------------------------
if ($hasDirectUdp) {
    $VIudp = new VirtualInUdp([
        "Title"   => "Sonos4Lox (UDP)",
        "Address" => "",
        "Port"    => (string)$udpDirectPort
    ]);

    build_direct_udp_template_commands($VIudp, array_keys($config['sonoszonen']));
    write_xml_file($xmlOutFileUdp, $VIudp->output(), "VI_UDP_Sonos.xml");

    LOGOK(
        "system/ms_inbound.php: Templates created successfully " .
        "(MQTT UDP port: $udpMqttPort, direct UDP port: $udpDirectPort)"
    );
} else {
    LOGOK(
        "system/ms_inbound.php: MQTT UDP template created successfully " .
        "(MQTT UDP port: $udpMqttPort, direct UDP template skipped)"
    );
}

exit(0);

/**
 * Add one MQTT gateway based UDP cmd line.
 *
 * Expected pattern:
 *   MQTT:\i<topic>=\i\v
 */
function add_cmd_mqtt(VirtualInUdp $vi, string $title, string $topic, bool $analog = true): void
{
    $check = 'MQTT:\i' . $topic . '=\i\v';

    $vi->VirtualInUdpCmd([
        "Title"  => $title,
        "Check"  => $check,
        "Analog" => $analog ? "true" : "false",
    ]);
}

/**
 * Add one direct UDP cmd line.
 *
 * Expected direct Sonos UDP pattern:
 *   s4lox: <key>@<value>
 *
 * Example:
 *   s4lox: kueche_volume@3
 */
function add_cmd_udp(VirtualInUdp $vi, string $title, string $key, bool $analog = true): void
{
    $check = '\is4lox: ' . $key . '@\i\v';

    $vi->VirtualInUdpCmd([
        "Title"  => $title,
        "Check"  => $check,
        "Analog" => $analog ? "true" : "false",
    ]);
}

/**
 * Build MQTT gateway template commands.
 */
function build_mqtt_template_commands(VirtualInUdp $vi, string $prefix, array $rooms): void
{
    // ============================================================
    // HEALTH
    // ============================================================
    $healthBase = $prefix . '/_health';

    add_cmd_mqtt($vi, 'Health online_players', $healthBase . '/online##_players', true);
    add_cmd_mqtt($vi, 'Health total_players',  $healthBase . '/total##_players', true);

    foreach ($rooms as $room) {
        $roomName = (string)$room;
        add_cmd_mqtt($vi, 'Health ' . $roomName . ' Online', $healthBase . '/' . $roomName . '##_Online', true);
    }

    // ============================================================
    // PER ROOM TOPICS
    // ============================================================
    foreach ($rooms as $room) {
        $roomName = (string)$room;

        // ---------- EQ ----------
        $eqBase = $prefix . '/' . $roomName . '/eq';
        add_cmd_mqtt($vi, 'EQ bass '     . $roomName, $eqBase . '/bass', true);
        add_cmd_mqtt($vi, 'EQ treble '   . $roomName, $eqBase . '/treble', true);
        add_cmd_mqtt($vi, 'EQ loudness ' . $roomName, $eqBase . '/loudness', true);
        add_cmd_mqtt($vi, 'EQ balance '  . $roomName, $eqBase . '/balance', true);
        add_cmd_mqtt($vi, 'EQ subgain '  . $roomName, $eqBase . '/subgain', true);

        // ---------- STATE ----------
        $stateBase = $prefix . '/' . $roomName . '/state';
        add_cmd_mqtt($vi, 'State_code ' . $roomName, $stateBase . '/state##_code', true);

        // ---------- GROUP ----------
        $groupBase = $prefix . '/' . $roomName . '/group';
        add_cmd_mqtt($vi, 'Group_code ' . $roomName, $groupBase . '/role##_code', true);

        // ---------- TRACK / SOURCE ----------
        $trackBase = $prefix . '/' . $roomName . '/track';
        add_cmd_mqtt($vi, 'Source_code ' . $roomName, $trackBase . '/source', true);

        // ---------- VOLUME ----------
        $volBase = $prefix . '/' . $roomName . '/volume';
        add_cmd_mqtt($vi, 'Volume ' . $roomName, $volBase . '/volume', true);

        // ---------- MUTE ----------
        $muteBase = $prefix . '/' . $roomName . '/mute';
        add_cmd_mqtt($vi, 'Mute ' . $roomName, $muteBase . '/mute', true);

        // ---------- PLAYMODE ----------
        $pmBase = $prefix . '/' . $roomName . '/playmode';
        add_cmd_mqtt($vi, 'Playmode_code ' . $roomName, $pmBase . '/code', true);

        // ---------- POSITION ----------
        $posBase = $prefix . '/' . $roomName . '/position';
        add_cmd_mqtt($vi, 'Pos track_no '    . $roomName, $posBase . '/track##_no', true);
        add_cmd_mqtt($vi, 'Pos track_count ' . $roomName, $posBase . '/track##_count', true);
    }
}

/**
 * Build direct UDP template commands.
 *
 * Direct UDP naming is flattened:
 *   s4lox: <room>_<field>@<value>
 *
 * Observed examples:
 *   s4lox: kueche_volume@3
 *   s4lox: kueche_mute@0
 *   s4lox: kueche_playmode_code@99
 */
function build_direct_udp_template_commands(VirtualInUdp $vi, array $rooms): void
{
    // ============================================================
    // OPTIONAL GLOBAL HEALTH
    // ============================================================
    add_cmd_udp($vi, 'Health online_players', 'online_players', true);
    add_cmd_udp($vi, 'Health total_players', 'total_players', true);

    foreach ($rooms as $room) {
        $roomName = (string)$room;
        $roomKey  = normalize_room_key($roomName);

        add_cmd_udp($vi, 'Health ' . $roomName . ' Online', $roomKey . '_Online', true);

        // ---------- EQ ----------
        add_cmd_udp($vi, 'EQ bass '     . $roomName, $roomKey . '_bass', true);
        add_cmd_udp($vi, 'EQ treble '   . $roomName, $roomKey . '_treble', true);
        add_cmd_udp($vi, 'EQ loudness ' . $roomName, $roomKey . '_loudness', true);
        add_cmd_udp($vi, 'EQ balance '  . $roomName, $roomKey . '_balance', true);
        add_cmd_udp($vi, 'EQ subgain '  . $roomName, $roomKey . '_subgain', true);

        // ---------- STATE ----------
        add_cmd_udp($vi, 'State_code ' . $roomName, $roomKey . '_state_code', true);

        // ---------- GROUP ----------
        add_cmd_udp($vi, 'Group_code ' . $roomName, $roomKey . '_role_code', true);

        // ---------- TRACK / SOURCE ----------
        add_cmd_udp($vi, 'Source_code ' . $roomName, $roomKey . '_source', true);

        // ---------- VOLUME ----------
        add_cmd_udp($vi, 'Volume ' . $roomName, $roomKey . '_volume', true);

        // ---------- MUTE ----------
        add_cmd_udp($vi, 'Mute ' . $roomName, $roomKey . '_mute', true);

        // ---------- PLAYMODE ----------
        add_cmd_udp($vi, 'Playmode_code ' . $roomName, $roomKey . '_playmode_code', true);

        // ---------- POSITION ----------
        add_cmd_udp($vi, 'Pos track_no '    . $roomName, $roomKey . '_track_no', true);
        add_cmd_udp($vi, 'Pos track_count ' . $roomName, $roomKey . '_track_count', true);

        // ---------- OPTIONAL PLAYMODE FLAGS ----------
        add_cmd_udp($vi, 'Shuffle '    . $roomName, $roomKey . '_shuffle', true);
        add_cmd_udp($vi, 'Repeat '     . $roomName, $roomKey . '_repeat', true);
        add_cmd_udp($vi, 'Repeat one ' . $roomName, $roomKey . '_repeat_one', true);
    }
}

/**
 * Normalize room names for direct UDP flat keys.
 *
 * Example:
 *   "Küche" -> "kueche"
 *   "Living Room" -> "living_room"
 */
function normalize_room_key(string $room): string
{
    $map = [
        'ä' => 'ae', 'Ä' => 'Ae',
        'ö' => 'oe', 'Ö' => 'Oe',
        'ü' => 'ue', 'Ü' => 'Ue',
        'ß' => 'ss',
    ];

    $room = strtr($room, $map);
    $room = strtolower($room);
    $room = preg_replace('/[^a-z0-9]+/', '_', $room);
    $room = trim((string)$room, '_');

    return $room;
}

/**
 * Validate a UDP port and abort on invalid values.
 */
function validate_port(int $port, $rawValue, string $label): void
{
    if ($port <= 0 || $port > 65535) {
        $value = is_scalar($rawValue) ? (string)$rawValue : 'non-scalar';
        LOGERR("system/ms_inbound.php: Invalid UDP port in $label. Value=$value");
        exit(1);
    }
}

/**
 * Write XML output with UTF-8 BOM.
 */
function write_xml_file(string $filename, string $xml, string $label): void
{
    $xml = chr(239) . chr(187) . chr(191) . $xml;

    $ok = @file_put_contents($filename, $xml);
    if ($ok === false) {
        LOGERR("system/ms_inbound.php: Failed writing template $label to '$filename'");
        exit(1);
    }

    LOGOK("system/ms_inbound.php: $label created at '$filename'");
}

function shutdown(): void
{
    // no-op
}