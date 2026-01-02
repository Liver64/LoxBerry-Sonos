#!/usr/bin/php
<?php
declare(strict_types=1);

/**
 * sonos_mqtt_debug.php
 *
 * Kleines CLI-Tool zum Testen der MQTT-Anbindung für Sonos4Lox.
 *
 * Features:
 *  - Verbindungscheck zum MQTT-Broker
 *  - Senden von Test-Events im Sonos-Event-Format:
 *      - state   (AVTransport)
 *      - volume  (RenderingControl oder GroupRenderingControl)
 *      - mute    (RenderingControl oder GroupRenderingControl)
 *      - track   (AVTransport)
 *
 * Alle Payloads sind kompatibel zum Sonos Event Listener:
 *   { "type": "...", "room": "...", "ip": "...", "rincon": "...", "model": "...", "service": "...", "ts": ... }
 */

require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_io.php";
require_once __DIR__ . '/../system/SonosMqttClient.php';

date_default_timezone_set('Europe/Berlin');

const S4L_CFG      = "REPLACELBHOMEDIR/config/plugins/sonos4lox/s4lox_config.json";
const TOPIC_PREFIX = "lox/sonos";

// -------------------------------------------------------------
// Simple logger (konform zum Event Listener)
// -------------------------------------------------------------
$LOGFILE = LBPLOGDIR . "/sonos_mqtt_debug.log";

function logln(string $lvl, string $msg): void {
    global $LOGFILE;
    $line = sprintf("[%s] %-5s %s\n", date('Y-m-d H:i:s'), strtoupper($lvl), $msg);
    file_put_contents($LOGFILE, $line, FILE_APPEND);
    echo $line;
}

// -------------------------------------------------------------
// Helper: Räume aus s4lox_config.json lesen
// -------------------------------------------------------------
function load_rooms(): array
{
    if (!file_exists(S4L_CFG)) {
        logln('warn', "Config file not found: " . S4L_CFG . " – room metadata will be empty.");
        return [];
    }

    $cfg = json_decode(file_get_contents(S4L_CFG), true);
    if (!is_array($cfg)) {
        logln('warn', "Invalid JSON in " . S4L_CFG . " – room metadata will be empty.");
        return [];
    }

    $zones = $cfg['sonoszonen'] ?? [];
    $rooms = [];

    foreach ($zones as $room => $arr) {
        if (!is_array($arr) || empty($arr[0])) {
            continue;
        }
        $rooms[$room] = [
            'ip'     => (string)$arr[0],
            'rincon' => isset($arr[1]) ? (string)$arr[1] : '',
            'model'  => isset($arr[2]) ? (string)$arr[2] : '',
        ];
    }

    return $rooms;
}

// -------------------------------------------------------------
// Helper: Payload im Sonos-Format bauen
// -------------------------------------------------------------
function build_payload(
    string $type,
    string $room,
    array $data,
    array $roomsMeta,
    string $service
): string {
    $meta = $roomsMeta[$room] ?? ['ip' => '', 'rincon' => '', 'model' => ''];

    $payload = $data + [
        'type'    => $type,
        'room'    => $room,
        'ip'      => $meta['ip'],
        'rincon'  => $meta['rincon'],
        'model'   => $meta['model'],
        'service' => $service,
        'ts'      => time(),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException("JSON encode failed for payload (type=$type, room=$room)");
    }
    return $json;
}

// -------------------------------------------------------------
// Helper: publish wrapper
// -------------------------------------------------------------
function publish_debug(
    SonosMqttClient $mqtt,
    string $room,
    string $type,
    array $data,
    array $roomsMeta,
    string $service,
    bool $retain = false,
    int $qos = 1
): void {
    $topic   = rtrim(TOPIC_PREFIX, '/') . "/{$room}/{$type}";
    $payload = build_payload($type, $room, $data, $roomsMeta, $service);

    logln('info', "Publishing debug event to $topic");
    logln('dbg',  "Payload:\n" . $payload);

    $ok = $mqtt->publish($topic, $payload, $retain, $qos);
    if ($ok) {
        logln('ok',   "Publish OK ($topic)");
    } else {
        logln('warn', "Publish FAILED ($topic)");
    }
}

// -------------------------------------------------------------
// CLI Usage / Help
// -------------------------------------------------------------
function usage(int $exitCode = 0): void
{
    echo "Usage: php sonos_mqtt_debug.php <command> [args...]\n\n";
    echo "Commands:\n";
    echo "  help, -h, --help\n";
    echo "      Show this help text and available options.\n\n";

    echo "  conn\n";
    echo "      Test MQTT connection using LoxBerry settings.\n\n";

    echo "  state <room> <STATE>\n";
    echo "      Send AVTransport state event.\n";
    echo "      Example states: PLAYING, PAUSED_PLAYBACK, STOPPED, TRANSITIONING\n";
    echo "      Example: php sonos_mqtt_debug.php state badezimmer PLAYING\n\n";

    echo "  volume <room> <value> [--group]\n";
    echo "      Send RenderingControl (default) or GroupRenderingControl volume event.\n";
    echo "      Examples:\n";
    echo "        php sonos_mqtt_debug.php volume badezimmer 15\n";
    echo "        php sonos_mqtt_debug.php volume badezimmer 20 --group\n\n";

    echo "  mute <room> <on|off|1|0> [--group]\n";
    echo "      Send RenderingControl (default) or GroupRenderingControl mute event.\n";
    echo "      Examples:\n";
    echo "        php sonos_mqtt_debug.php mute badezimmer on\n";
    echo "        php sonos_mqtt_debug.php mute badezimmer off --group\n\n";

    echo "  track <room>\n";
    echo "      Send a sample AVTransport track event.\n";
    echo "      Example: php sonos_mqtt_debug.php track badezimmer\n\n";

    echo "Examples (quick overview):\n";
    echo "  php sonos_mqtt_debug.php conn\n";
    echo "  php sonos_mqtt_debug.php state badezimmer PLAYING\n";
    echo "  php sonos_mqtt_debug.php volume badezimmer 18\n";
    echo "  php sonos_mqtt_debug.php volume badezimmer 20 --group\n";
    echo "  php sonos_mqtt_debug.php mute badezimmer on\n";
    echo "  php sonos_mqtt_debug.php mute badezimmer off --group\n";
    echo "  php sonos_mqtt_debug.php track badezimmer\n\n";

    echo "Notes:\n";
    echo "  - MQTT connection details are read from LoxBerry (mqtt_connectiondetails()).\n";
    echo "  - Room metadata (ip, rincon, model) is read from s4lox_config.json when available.\n";

    exit($exitCode);
}

// -------------------------------------------------------------
// Main
// -------------------------------------------------------------
$argvCount = $_SERVER['argc'] ?? 0;
$argvVals  = $_SERVER['argv'] ?? [];

if ($argvCount < 2) {
    usage(0);
}

$cmd = strtolower($argvVals[1]);

// help-Parameter unterstützen
if ($cmd === 'help' || $cmd === '-h' || $cmd === '--help') {
    usage(0);
}

// MQTT connection details
$mqttconf = mqtt_connectiondetails();
$mqttHost = $mqttconf['brokerhost'] ?? 'localhost';
$mqttPort = (int)($mqttconf['brokerport'] ?? 1883);
$mqttUser = $mqttconf['brokeruser'] ?? '';
$mqttPass = $mqttconf['brokerpass'] ?? '';

$mqttClientId = 'SonosMqttDebug_' . gethostname() . '_' . uniqid();
$mqtt = new SonosMqttClient($mqttHost, $mqttPort, $mqttClientId, $mqttUser ?: null, $mqttPass ?: null);

// Räume laden (für ip/rincon/model)
$roomsMeta = load_rooms();

if ($cmd === 'conn') {
    logln('info', "Testing MQTT connection to {$mqttHost}:{$mqttPort} …");
    if ($mqtt->connect()) {
        logln('ok', "MQTT connection successful.");
    } else {
        logln('error', "MQTT connection failed.");
        exit(1);
    }
    exit(0);
}

if ($argvCount < 3) {
    usage(1);
}

switch ($cmd) {

    case 'state': {
        if ($argvCount < 4) usage(1);
        $room  = $argvVals[2];
        $state = strtoupper($argvVals[3]);

        $payloadData = ['state' => $state];

        if (!$mqtt->connect()) {
            logln('error', "Cannot connect to MQTT broker.");
            exit(1);
        }

        publish_debug(
            $mqtt,
            $room,
            'state',
            $payloadData,
            $roomsMeta,
            'AVTransport',
            true,   // retain
            1       // qos
        );
        break;
    }

    case 'volume': {
        if ($argvCount < 4) usage(1);
        $room  = $argvVals[2];
        $value = (int)$argvVals[3];
        $group = in_array('--group', $argvVals, true);

        $service = $group ? 'GroupRenderingControl' : 'RenderingControl';

        $payloadData = ['volume' => $value];

        if (!$mqtt->connect()) {
            logln('error', "Cannot connect to MQTT broker.");
            exit(1);
        }

        // Volume-Events sind im Listener retained
        publish_debug(
            $mqtt,
            $room,
            'volume',
            $payloadData,
            $roomsMeta,
            $service,
            true,
            1
        );
        break;
    }

    case 'mute': {
        if ($argvCount < 4) usage(1);
        $room  = $argvVals[2];
        $val   = strtolower($argvVals[3]);
        $group = in_array('--group', $argvVals, true);

        $service = $group ? 'GroupRenderingControl' : 'RenderingControl';

        $mute = in_array($val, ['1', 'on', 'true', 'yes'], true);

        $payloadData = ['mute' => $mute];

        if (!$mqtt->connect()) {
            logln('error', "Cannot connect to MQTT broker.");
            exit(1);
        }

        publish_debug(
            $mqtt,
            $room,
            'mute',
            $payloadData,
            $roomsMeta,
            $service,
            false,
            1
        );
        break;
    }

    case 'track': {
        if ($argvCount < 3) usage(1);
        $room = $argvVals[2];

        $payloadData = [
            'title'       => 'Debug Track',
            'artist'      => 'SonosMqttDebug',
            'album'       => 'Test Album',
            'albumArtUri' => 'https://example.com/sonos-debug-art.png',
        ];

        if (!$mqtt->connect()) {
            logln('error', "Cannot connect to MQTT broker.");
            exit(1);
        }

        publish_debug(
            $mqtt,
            $room,
            'track',
            $payloadData,
            $roomsMeta,
            'AVTransport',
            false,
            1
        );
        break;
    }

    default:
        usage(1);
}

exit(0);
