#!/usr/bin/php
<?php
declare(strict_types=1);

/**
 * Sonos Event Listener (raum-zentriert, vollständig)
 * - liest Räume/Player aus REPLACELBHOMEDIR/config/plugins/sonos4lox/s4lox_config.json
 * - SUBSCRIBE: AVTransport, RenderingControl, ZoneGroupTopology
 * - NOTIFY -> MQTT je Raum:
 *     state   (retain, QoS1)
 *     volume  (retain, QoS1)
 *     mute    (QoS1)
 *     track       (CurrentTrackMetaData inkl. albumArtUri) (QoS1)
 *     nexttrack   (NextTrackMetaData/NextAVTransportURIMetaData inkl. albumArtUri; Fallback: URI) (QoS1)
 *     group       (Gruppen- & Koordinator-Infos aus ZGT) (QoS1)
 *     eq          (Bass/Treble/Loudness) (QoS1)
 *     playmode    (Shuffle/Repeat/Crossfade) (QoS1)
 * - Payload IMMER: type, room, ip, rincon, model, service, ts
 *
 * Zusätzliche Felder für Loxone-Kompatibilität:
 *   state:
 *     - state_code (int)  1=PLAYING, 2=PAUSED, 3=STOPPED, 4=TRANSITIONING, 0=unknown
 *   volume:
 *     - vol (Alias zu volume)
 *   mute:
 *     - mute_int (0/1)
 *   group:
 *     - role_name ("single"|"master"|"member")
 *     - role_code (1/2/3)
 *   track:
 *     - source_text ("Radio"|"TV"|"Track"|"LineIn"|"Nothing")
 *     - source      (int) 0=Nothing,1=Radio,2=Track,3=TV,4=LineIn
 *     - tit         (Titel)
 *     - int         (Interpret)
 *     - titint      ("Artist - Title")
 *     - radio       (Sendername, wenn Radio)
 *     - sid         ("TV"|"Radio"|"Music")
 *     - cover       (AlbumArtUri)
 *     - tvstate     (z.Zt. 0 – Platzhalter)
 *   eq:
 *     - bass        (int)
 *     - treble      (int)
 *     - loudness    (0/1)
 *     - loudness_int(0/1)
 *   playmode:
 *     - mode_raw    (z.B. "NORMAL","REPEAT_ALL","SHUFFLE_NOREPEAT",…)
 *     - shuffle     (0/1)
 *     - repeat      (0/1)
 *     - repeat_one  (0/1)
 *     - crossfade   (0/1)
 *
 * MQTT:
 * - interner MQTT-Client "SonosMqttClient" (TCP + MQTT v3.1.1, QoS 0/1)
 * - nutzt mqtt_connectiondetails() aus LoxBerry (Host, Port, User, Passwort)
 * - Reconnect mit Backoff
 * - verbose Logging
 */

require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_io.php";
require_once __DIR__ . '/../system/SonosMqttClient.php';
require_once "$lbphtmldir/system/sonosAccess.php";

date_default_timezone_set('Europe/Berlin');
pcntl_async_signals(true);

// --------------------------------- Pfade ---------------------------------
const S4L_CFG     = "REPLACELBHOMEDIR/config/plugins/sonos4lox/s4lox_config.json";
$ramLogDir        = '/run/shm/sonos4lox';
$LogFile          = 'sonos_events.log';
$loglevel 	  	  = LBSystem::pluginloglevel();
$presenceEnabled  = null;

// --------------------------------- Basis-Config ---------------------------------
$LISTEN_HOST   = '0.0.0.0';
$LISTEN_PORT   = 5005;
$CALLBACK_HOST = null;         // null => auto
$TIMEOUT_SEC   = 300;
$RENEW_MARGIN  = 60;
$TOPIC_PREFIX  = 's4lox/sonos';
$MQTT_QOS      = 1;            // global QoS (1 wie gewünscht)

// --------------------------------- Health-Monitor ---------------------------------
$startTs              = time();
$lastNotifyTs         = time();  // wird bei jedem NOTIFY aktualisiert
$lastHealthPublish    = 0;
$NO_NOTIFY_WARN_AFTER = 600;     // 10 Minuten
$HEALTH_INTERVAL      = 60;      // 1 Minute
$healthRoomsFlags 	  = []; 	 // room => ['Online' => 0/1]
// Zeitstempel der letzten Events pro Service
$lastAvtEventTs 	  = 0;  // AVTransport
$lastRcEventTs  	  = 0;  // RenderingControl
$lastZgtEventTs 	  = 0;  // ZoneGroupTopology

// --------------------------------- Logging-Konfiguration ---------------------------------
$LOG_MAX_BYTES = 150 * 1024; // 150 KB

// Versuch: RAM-basiertes Logging
$ramLogDir = '/run/shm/sonos4lox';
if (!is_dir($ramLogDir)) {
    @mkdir($ramLogDir, 0775, true);
}

if (is_dir($ramLogDir) && is_writable($ramLogDir)) {
    $LOGFILE = $ramLogDir . '/' . $LogFile;
} else {
    // Fallback auf "normales" Plugin-Logverzeichnis
    $LOGFILE = LBPLOGDIR . '/' . $LogFile;
}

// Optional: Symlink ins normale LoxBerry-Log, damit der Logviewer ihn findet
$diskLog = LBPLOGDIR . '/' . $LogFile;
if ($LOGFILE !== $diskLog && @is_writable(dirname($diskLog))) {
    if (is_file($diskLog) && !is_link($diskLog)) {
        @unlink($diskLog);
    }
    if (!is_link($diskLog)) {
        @symlink($LOGFILE, $diskLog);
    }
}
// falls File zu groß direkt löschen
if (is_file($LOGFILE) && filesize($LOGFILE) > $LOG_MAX_BYTES) {
    @unlink($LOGFILE);
}

// --------------------------------- Logging ---------------------------------
function logln(string $lvl, string $msg): void {
    global $LOGFILE, $LOG_MAX_BYTES, $loglevel, $sonosLogEnabled;

    // 1) Logfile rotation – immer prüfen, unabhängig von Debug-Level
    if (!empty($LOGFILE) && is_file($LOGFILE) && filesize($LOGFILE) >= $LOG_MAX_BYTES) {
        @unlink($LOGFILE);
    }

    // 2) Logging nur, wenn Debuglevel 7 ODER presence==true
    #if ($loglevel != 7 && $sonosLogEnabled) {
    #    return;
    #}

    // 3) Zeile schreiben
    $line = sprintf("[%s] %-5s %s\n", date('Y-m-d H:i:s'), strtoupper($lvl), $msg);

    if (!empty($LOGFILE)) {
        @file_put_contents($LOGFILE, $line, FILE_APPEND);
    }

    // immer auch nach STDOUT für journalctl
    echo $line;
}

// --------------------------------- Helpers ---------------------------------

require_once "REPLACELBHOMEDIR/webfrontend/html/plugins/sonos4lox/Helper.php";

/**
 * Decide if we should publish a nexttrack MQTT event.
 * - suppress if title/artist/album are all empty
 * - suppress if nexttrack is effectively identical to current track
 */
function should_publish_nexttrack($track, $nexttrack) {
    if (empty($nexttrack) || !is_array($nexttrack)) {
        return false;
    }

    // Normalize helper
    $norm = function($v) {
        return trim((string)($v ?? ''));
    };

    $nt_title  = $norm($nexttrack['title']  ?? '');
    $nt_artist = $norm($nexttrack['artist'] ?? '');
    $nt_album  = $norm($nexttrack['album']  ?? '');

    // 1) Completely empty? → nothing to publish
    if ($nt_title === '' && $nt_artist === '' && $nt_album === '') {
        return false;
    }

    // 2) Compare with current track
    $t_title   = $norm($track['title']       ?? '');
    $t_artist  = $norm($track['artist']      ?? '');
    $t_album   = $norm($track['album']       ?? '');
    $t_arturi  = $norm($track['albumArtUri'] ?? '');
    $nt_arturi = $norm($nexttrack['albumArtUri'] ?? '');

    $same_title  = ($t_title  === $nt_title);
    $same_artist = ($t_artist === $nt_artist);
    $same_album  = ($t_album  === $nt_album);
    $same_art    = ($t_arturi === $nt_arturi);

    if ($same_title && $same_artist && $same_album && $same_art) {
        // typical radio case
        return false;
    }

    return true;
}

function http_get(string $url, int $timeout=4): ?string {
    $ctx = stream_context_create(['http' => [
        'timeout'       => $timeout,
        'ignore_errors' => true,
        'header'        => "Connection: close\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return ($body === false) ? null : $body;
}

function http_req(string $method, string $url, array $headers): array {
    $ctx = stream_context_create(['http' => [
        'method'        => $method,
        'header'        => implode("\r\n", $headers) . "\r\n",
        'ignore_errors' => true,
        'timeout'       => 4,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return [($http_response_header ?? []), ($body === false ? '' : $body)];
}

/**
 * Refresh missing Sonos event URLs for rooms where $rooms[$room]['events'] is empty.
 * Keeps rooms, just tries to (re)load device_description.xml and fill events.
 */
function refresh_event_urls_for_missing_rooms(array &$rooms): void
{
    foreach ($rooms as $room => &$meta) {

        // only refresh if events missing/empty
        if (!empty($meta['events']) && is_array($meta['events'])) {
            continue;
        }

        $base = $meta['base'] ?? ("http://" . ($meta['ip'] ?? '') . ":1400");
        if (empty($base)) {
            continue;
        }

        $desc = null;
        for ($try = 1; $try <= 3; $try++) {
            $desc = http_get(rtrim($base, '/') . "/xml/device_description.xml", 6);
            if ($desc) {
                break;
            }
            logln('warn', "Event URL refresh: device_description.xml fetch failed for {$meta['ip']} ($room) try {$try}/3");
            usleep(250000);
        }

        if (!$desc) {
            logln('warn', "Event URL refresh: still no device_description.xml for {$meta['ip']} ($room) – keeping room without events");
            $meta['events'] = [];
            continue;
        }

        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($desc);
        if (!$sx) {
            logln('warn', "Event URL refresh: XML parse failed for {$meta['ip']} ($room) – keeping room without events");
            $meta['events'] = [];
            continue;
        }

        $sx->registerXPathNamespace('d', 'urn:schemas-upnp-org:device-1-0');
        $svcs = $sx->xpath('//d:serviceList/d:service') ?: [];

        $evs  = [];
        foreach ($svcs as $svc) {
            $sid = (string)$svc->serviceId;
            $ev  = (string)$svc->eventSubURL;
            if (!$ev) continue;

            $full = (strpos($ev, 'http') === 0)
                ? $ev
                : rtrim($base, '/') . '/' . ltrim($ev, '/');

            if (stripos($sid, 'AVTransport') !== false) {
                $evs['AVTransport'] = $full;
            } elseif (stripos($sid, 'GroupRenderingControl') !== false) {
                $evs['GroupRenderingControl'] = $full;
            } elseif (stripos($sid, 'RenderingControl') !== false) {
                $evs['RenderingControl'] = $full;
            } elseif (stripos($sid, 'ZoneGroupTopology') !== false) {
                $evs['ZoneGroupTopology'] = $full;
            }
        }

        $meta['events'] = $evs;

        if (!empty($evs)) {
            logln('ok', "Event URL refresh: {$meta['ip']} ($room): " . implode(', ', array_keys($evs)));
        } else {
            logln('warn', "Event URL refresh: {$meta['ip']} ($room): no event URLs found – keeping room without events");
        }
    }
    unset($meta);
}

function header_value(array $headers, string $name): ?string {
    foreach ($headers as $h) {
        if (stripos($h, $name . ':') === 0) return trim(substr($h, strlen($name) + 1));
    }
    return null;
}

// -------------------------------------------------------------
// Loxone target resolver (cached general.json by mtime)
// -------------------------------------------------------------
function get_miniserver_target_from_general_cached(string $msId): ?array
{
    static $cache = [
        'mtime'   => 0,
        'general' => null,
    ];

    $path  = 'REPLACELBHOMEDIR/config/system/general.json';
    $mtime = @filemtime($path) ?: 0;

    // Reload only if changed (or first time)
    if (!$cache['general'] || $mtime !== $cache['mtime']) {

        if (!is_file($path)) {
            logln('warn', "general.json not found at $path");
            $cache['general'] = null;
            $cache['mtime']   = $mtime;
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            logln('warn', "general.json unreadable/empty at $path");
            $cache['general'] = null;
            $cache['mtime']   = $mtime;
            return null;
        }

        $j = json_decode($raw, true);
        if (!is_array($j)) {
            logln('warn', "general.json invalid JSON at $path");
            $cache['general'] = null;
            $cache['mtime']   = $mtime;
            return null;
        }

        $cache['general'] = $j;
        $cache['mtime']   = $mtime;

        logln('info', "general.json reloaded (mtime=$mtime)");
    }

    $general = $cache['general'];
    $minis   = $general['Miniserver'] ?? null;
    if (!is_array($minis)) {
        logln('warn', "general.json: Miniserver block missing");
        return null;
    }

    // Prefer configured id, else fallback to first
    $ms     = $minis[$msId] ?? null;
    $usedId = $msId;

    if (!is_array($ms)) {
        $firstKey = array_key_first($minis);
        if ($firstKey !== null && is_array($minis[$firstKey] ?? null)) {
            $usedId = (string)$firstKey;
            $ms     = $minis[$firstKey];
            logln('warn', "Miniserver id '$msId' not found, falling back to first entry '$usedId'");
        } else {
            logln('warn', "general.json: no usable Miniserver entries");
            return null;
        }
    }

    $user = (string)($ms['Admin_raw'] ?? $ms['Admin'] ?? '');
    $pass = (string)($ms['Pass_raw']  ?? $ms['Pass']  ?? '');
    $ip   = (string)($ms['Ipaddress'] ?? '');

    $portHttp    = (string)($ms['Port'] ?? '80');
    $portHttps   = (string)($ms['Porthttps'] ?? '');
    $preferHttps = (string)($ms['Preferhttps'] ?? '0');

    if ($user === '' || $pass === '' || $ip === '') {
        logln('warn', "general.json: Miniserver '$usedId' missing user/pass/ip");
        return null;
    }

    $scheme = ($preferHttps === '1' || strtolower((string)($ms['Transport'] ?? 'http')) === 'https')
        ? 'https'
        : 'http';

    $port = ($scheme === 'https')
        ? ($portHttps !== '' ? $portHttps : '443')
        : ($portHttp  !== '' ? $portHttp  : '80');

    return [
        'baseUrl' => $scheme . '://' . $ip . ':' . $port,
        'user'    => $user,
        'pass'    => $pass,
        'msId'    => $usedId,
        'name'    => (string)($ms['Name'] ?? ''),
    ];
}

// -------------------------------------------------------------
// Loxone HTTP publish helpers
// -------------------------------------------------------------
function loxone_http_set_io(array $loxTarget, string $input, string $value, int $timeout = 3): bool
{
    // Loxone API: /dev/sps/io/<input>/<value>
    $base = rtrim((string)$loxTarget['baseUrl'], '/');
    $url  = $base . '/dev/sps/io/' . rawurlencode($input) . '/' . rawurlencode($value);

    $auth = base64_encode((string)$loxTarget['user'] . ':' . (string)$loxTarget['pass']);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeout,
            'ignore_errors' => true,
            'header'        => "Authorization: Basic {$auth}\r\nConnection: close\r\n",
        ],
        // If you use https with self-signed certs and it fails, you could add ssl options here.
    ]);

    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) {
        return false;
    }

    // Optional: you can inspect $http_response_header for status, but keep it simple for now.
    return true;
}

function loxone_apply_placeholders(string $tpl, array $payload): string
{
    // add a few common aliases/normalizations
    $p = $payload;
    if (!isset($p['uri']) && isset($p['av_uri'])) $p['uri'] = $p['av_uri'];

    return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($m) use ($p) {
        $k = $m[1];
        return isset($p[$k]) && $p[$k] !== null ? (string)$p[$k] : '';
    }, $tpl);
}

function loxone_publish_for_event(string $type, array $payload, ?array $loxTarget, array $map): void
{
    static $lastSent = []; // input => last value (dedup)

    if (!$loxTarget) return;
    if (empty($map[$type]) || !is_array($map[$type])) return;

    foreach ($map[$type] as $rule) {
        $inTpl  = (string)($rule['input'] ?? '');
        $valTpl = (string)($rule['value'] ?? '');
        if ($inTpl === '') continue;

        $input = loxone_apply_placeholders($inTpl, $payload);
        $value = loxone_apply_placeholders($valTpl, $payload);

        // Dedup: don't spam unchanged values
        $key = $input;
        if (isset($lastSent[$key]) && $lastSent[$key] === $value) {
            continue;
        }
        $lastSent[$key] = $value;

        $ok = loxone_http_set_io($loxTarget, $input, $value, 3);
        if (!$ok) {
            logln('warn', "Loxone HTTP set failed: input='$input'");
        }
    }
}

// AlbumArt-URL absolut machen (falls relativ/leer)
function absolutize_art(?string $art, string $playerBase): ?string {
    $art = trim((string)$art);
    if ($art === '') return null;
    if (preg_match('~^https?://~i', $art)) return $art;
    // Sonos liefert oft Pfade wie /getaa?u=... oder /img/...
    return rtrim($playerBase, '/') . '/' . ltrim($art, '/');
}

// "HH:MM:SS" nach Sekunden umwandeln
function hms_to_seconds(?string $str): ?int {
    $str = trim((string)$str);
    if ($str === '') {
        return null;
    }
    if (!preg_match('/^(\d+):([0-5]?\d):([0-5]?\d)$/', $str, $m)) {
        return null;
    }
    $h = (int)$m[1];
    $m2 = (int)$m[2];
    $s = (int)$m[3];
    return $h * 3600 + $m2 * 60 + $s;
}

// -------------------------------------------------------------
// Source-Erkennung (TV / Radio / LineIn / Track / Nothing)
// -------------------------------------------------------------

// Einfacher Classifier basierend auf GetPositionInfo()-Array
function classify_source_from_posinfo(array $posinfo): string {
    $uri       = (string)($posinfo['TrackURI']          ?? $posinfo['URI'] ?? '');
    $upnpClass = (string)($posinfo['UpnpClass']         ?? '');
    $meta      = (string)($posinfo['CurrentURIMetaData']?? '');
    $protocol  = (string)($posinfo['ProtocolInfo']      ?? '');

    $uri_l       = strtolower($uri);
    $upnpClass_l = strtolower($upnpClass);
    $protocol_l  = strtolower($protocol);

    // TV (Sonos HT-Stream)
    if (strpos($uri_l, 'x-sonos-htastream:') === 0) {
        return 'TV';
    }

    // Line-In (Analog / Digital-In)
    if (strpos($uri_l, 'x-rincon-stream') === 0) {
        return 'LineIn';
    }

    // Radio / Stream (TuneIn, Sonos Radio, etc.)
    if (
        strpos($uri_l, 'x-rincon-mp3radio') === 0 ||
        strpos($protocol_l, 'x-rincon-mp3radio') === 0 ||
        strpos($upnpClass_l, 'object.item.audioitem.audiobroadcast') === 0
    ) {
        return 'Radio';
    }

    // Nichts / Idle
    if ($uri === '' && $meta === '') {
        return 'Nothing';
    }

    // Default: normale Track-/Playlist-Wiedergabe
    return 'Track';
}

// Cache für SonosAccess-Objekte pro Raum
$__sonos_clients = [];

/**
 * Liefert 'source' (TV/Radio/LineIn/Track/Nothing) für einen Raum.
 * Holt sich intern über SonosAccess->GetPositionInfo() die Daten.
 */
function get_source_for_room(string $room, array $rooms): ?string {
    global $__sonos_clients;

    if (empty($rooms[$room]['ip'])) {
        return null;
    }
    $ip = $rooms[$room]['ip'];

    try {
        if (!isset($__sonos_clients[$room])) {
            $__sonos_clients[$room] = new SonosAccess($ip);
        }
        $sonos    = $__sonos_clients[$room];
        $posinfo  = $sonos->GetPositionInfo();
        if (!is_array($posinfo)) {
            return null;
        }
        return classify_source_from_posinfo($posinfo);
    } catch (Exception $e) {
        logln('warn', "get_source_for_room($room): " . $e->getMessage());
        return null;
    }
}

function get_radio_station_for_room(string $room, array $rooms): ?string
{
    global $__sonos_clients;

    if (empty($rooms[$room]['ip'])) return null;

    try {
        if (!isset($__sonos_clients[$room])) {
            $__sonos_clients[$room] = new SonosAccess($rooms[$room]['ip']);
        }
        $sonos = $__sonos_clients[$room];

        // WICHTIG: GetMediaInfo liefert CurrentURIMetaData inkl. dc:title = Sender
        $mi = $sonos->GetMediaInfo();
        if (!is_array($mi)) return null;

        // Viele SonosAccess-Versionen parsen bereits "title" aus CurrentURIMetaData
        $t = trim((string)($mi['title'] ?? ''));
        if ($t !== '') return $t;

        // Fallback: raw DIDL aus CurrentURIMetaData parsen
        $raw = (string)($mi['CurrentURIMetaData'] ?? '');
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $raw = preg_replace('/&(?![a-zA-Z0-9#]+;)/', '&amp;', $raw);

        libxml_use_internal_errors(true);
        $d = @simplexml_load_string($raw);
        if ($d) {
            $d->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
            $st = trim((string)($d->xpath('//dc:title')[0] ?? ''));
            if ($st !== '') return $st;
        }
        libxml_clear_errors();

    } catch (Exception $e) {
        logln('warn', "get_radio_station_for_room($room): " . $e->getMessage());
    }

    return null;
}


// ---------- Parser: LastChange für AVT/RC ----------
function parse_lastchange(string $xml, string $playerBase): array {
    $events = [];

    // 1) Nackte '&' in '&amp;' wandeln (URLs, Querystrings etc.)
    $xmlSanitized = preg_replace(
        '/&(?![a-zA-Z0-9#]+;)/',
        '&amp;',
        $xml
    );

    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($xmlSanitized);
    if (!$sx) {
        $err = libxml_get_last_error();
        if ($err) {
            logln(
                'dbg',
                "LastChange XML parse failed at line {$err->line}, col {$err->column}: " . trim($err->message)
            );
        } else {
            logln('dbg', "LastChange XML parse failed (unknown error)");
        }
        logln('dbg', "LastChange XML RAW START: " . substr($xml, 0, 200));
        libxml_clear_errors();

        // Fallback: Wenigstens den TransportState via Regex extrahieren,
        // damit /state immer noch gepflegt wird
        if (preg_match('~TransportState[^>]*val="([^"]+)"~', $xml, $m)) {
            $events[] = [
                'type'  => 'state',
                'state' => $m[1],
            ];
        }

        return $events;
    }

    // -------------------------------------------------
    // TransportStatus und PlaybackStorageMedium (für state-Extras)
    // -------------------------------------------------
    $transportStatus = null;
    foreach ($sx->xpath('//*[local-name()="TransportStatus"]') as $n) {
        $val = (string)$n['val'];
        if ($val === '') {
            $val = trim((string)$n);
        }
        if ($val !== '') {
            $transportStatus = $val;
            break;
        }
    }

    $playbackStorage = null;
    foreach ($sx->xpath('//*[local-name()="PlaybackStorageMedium"]') as $n) {
        $val = (string)$n['val'];
        if ($val === '') {
            $val = trim((string)$n);
        }
        if ($val !== '') {
            $playbackStorage = $val;
            break;
        }
    }

    // --- TransportState (PLAYING / PAUSED_PLAYBACK / STOPPED / TRANSITIONING) ---
    foreach ($sx->xpath('//*[local-name()="TransportState"]') as $n) {
        $state = (string)$n['val'];
        if ($state === '') {
            $state = trim((string)$n);
        }
        if ($state !== '') {
            $ev = [
                'type'  => 'state',
                'state' => $state,
            ];
            if ($transportStatus !== null) {
                $ev['transport_status'] = $transportStatus;
            }
            if ($playbackStorage !== null) {
                $ev['playback_storage'] = $playbackStorage;
            }
            $events[] = $ev;
        }
    }

    // -------------------------------------------------
    // Hilfsfunktion: DIDL -> (title, artist, album, albumArtUri, upnp:class)
    // -------------------------------------------------
    $extractFromDidl = function (string $didlXml) use ($playerBase): array {
        $out = [
            'title'            => '',
            'artist'           => '',
            'album'            => '',
            'albumArtUri'      => null,
            'streamContent'    => '',   // Sonos: aktueller Inhalt (Song/Info) bei Radio
            'radioStationName' => '',   // Sonos: Sendername (wenn vorhanden)
            '__upnp_class'     => '',
        ];

        $d = @simplexml_load_string($didlXml);
        if ($d) {
            $d->registerXPathNamespace('dc',   'http://purl.org/dc/elements/1.1/');
            $d->registerXPathNamespace('upnp', 'urn:schemas-upnp-org:metadata-1-0/upnp/');
            // Sonos / Rincon namespace (Radio/Stream Metadata)
            $d->registerXPathNamespace('r', 'urn:schemas-rinconnetworks-com:metadata-1-0/');

            $title  = (string)($d->xpath('//dc:title')[0]        ?? '');
            $artist = (string)($d->xpath('//dc:creator')[0]      ?? '');
            $album  = (string)($d->xpath('//upnp:album')[0]      ?? '');
            $art    = (string)($d->xpath('//upnp:albumArtURI')[0]?? '');
            $class  = (string)($d->xpath('//upnp:class')[0]      ?? '');

            // Sonos Radio Felder
            $streamContent    = (string)($d->xpath('//r:streamContent')[0] ?? '');
            $radioStationName = (string)($d->xpath('//r:radioStationName')[0] ?? '');

            $out['title']            = $title;
            $out['artist']           = $artist;
            $out['album']            = $album;
            $out['albumArtUri']      = absolutize_art($art, $playerBase);
            $out['streamContent']    = $streamContent;
            $out['radioStationName'] = $radioStationName;
            $out['__upnp_class']     = $class;
        }

        return $out;
    };

    // Helper: Metadaten lesen (Attribut val oder Elementtext)
    $decodeMetaNode = function (\SimpleXMLElement $n) use ($extractFromDidl): ?array {
        // 1) Attribut val
        $raw = (string)$n['val'];

        // 2) Fallback: Elementinhalt
        if ($raw === '') {
            $raw = (string)$n;
        }

        // DIDL ist in LastChange als XML-String ge-escaped → hier gezielt decodieren
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_XML1, 'UTF-8');
        // und nackte '&' im DIDL selbst fixen
        $raw = preg_replace('/&(?![a-zA-Z0-9#]+;)/', '&amp;', $raw);

        $raw = trim($raw);
        if ($raw === '' || stripos($raw, '<DIDL-Lite') === false) {
            return null;
        }

        return $extractFromDidl($raw);
    };

    // -------------------------------------------------
    // CurrentTrackMetaData → track
    // -------------------------------------------------
    foreach ($sx->xpath('//*[local-name()="CurrentTrackMetaData"]') as $n) {
        $meta = $decodeMetaNode($n);
        if ($meta !== null) {
            unset($meta['__upnp_class']);
            $events[] = ['type' => 'track'] + $meta;
        }
    }

    // -------------------------------------------------
    // NextTrack: wir wollen GENAU EIN Event pro LastChange.
    // Priorität:
    //   1) NextTrackMetaData
    //   2) NextAVTransportURIMetaData
    //   3) EnqueuedTransportURIMetaData (nur item.*, keine container.*)
    //   4) NextTrackURI / NextAVTransportURI (nur URI)
    // -------------------------------------------------
    $nextTrackEvent = null;

    // 1) NextTrackMetaData
    foreach ($sx->xpath('//*[local-name()="NextTrackMetaData"]') as $n) {
        $meta = $decodeMetaNode($n);
        if ($meta !== null) {
            unset($meta['__upnp_class']);
            $nextTrackEvent = ['type' => 'nexttrack'] + $meta;
            break;
        }
    }

    // 2) NextAVTransportURIMetaData (nur wenn noch nichts da)
    if ($nextTrackEvent === null) {
        foreach ($sx->xpath('//*[local-name()="NextAVTransportURIMetaData"]') as $n) {
            $meta = $decodeMetaNode($n);
            if ($meta !== null) {
                unset($meta['__upnp_class']);
                $nextTrackEvent = ['type' => 'nexttrack'] + $meta;
                break;
            }
        }
    }

    // 3) EnqueuedTransportURIMetaData (Playlist / Queue etc.) → nur items, keine container.*
    if ($nextTrackEvent === null) {
        foreach ($sx->xpath('//*[local-name()="EnqueuedTransportURIMetaData"]') as $n) {
            $meta = $decodeMetaNode($n);
            if ($meta !== null) {
                $cls = strtolower($meta['__upnp_class'] ?? '');
                unset($meta['__upnp_class']);

                // Container (Playlist, Bibliothek etc.) ignorieren – das ist genau dein "Hot Country / Spotify"-Fall
                if ($cls !== '' && strpos($cls, 'object.container') === 0) {
                    logln('dbg', "parse_lastchange: skip EnqueuedTransportURIMetaData with container class '$cls' for nexttrack");
                    continue;
                }

                logln(
                    'dbg',
                    "parse_lastchange: EnqueuedTransportURIMetaData → nexttrack: "
                    . ($meta['title'] ?? '') . " — " . ($meta['artist'] ?? '')
                );

                $nextTrackEvent = ['type' => 'nexttrack'] + $meta;
                break;
            }
        }
    }

    // 4) Fallback: NextTrackURI / NextAVTransportURI → nur URI
    if ($nextTrackEvent === null) {
        foreach ($sx->xpath('//*[local-name()="NextTrackURI"] | //*[local-name()="NextAVTransportURI"]') as $n) {
            $uri = trim((string)$n);
            if ($uri !== '') {
                $nextTrackEvent = [
                    'type' => 'nexttrack',
                    'uri'  => $uri,
                ];
                break;
            }
        }
    }

    if ($nextTrackEvent !== null) {
        $events[] = $nextTrackEvent;
    }

    // -------------------------------------------------
    // Volume (Master + LF/RF) – Balance wird oft über LF/RF umgesetzt
    // -------------------------------------------------
    $volByChan = []; // e.g. ['Master'=>20,'LF'=>18,'RF'=>22]

    foreach ($sx->xpath('//*[local-name()="Volume"]') as $n) {
        $ch  = (string)($n['channel'] ?? $n['Channel'] ?? '');
        $ch  = $ch !== '' ? $ch : 'Master';

        $val = (string)($n['val'] ?? '');
        if ($val === '') {
            $val = (string)$n;
        }
        $val = trim($val);

        if ($val !== '' && is_numeric($val)) {
            $volByChan[$ch] = (int)$val;
        }
    }

    // Master publishen wie bisher
    if (isset($volByChan['Master'])) {
        $events[] = [
            'type'   => 'volume',
            'volume' => (int)$volByChan['Master'],
        ];
    }

    foreach ($sx->xpath('//*[local-name()="Mute"][@channel="Master"]') as $n) {
        $val = (string)$n['val'];
        if ($val === '') {
            $val = (string)$n;
        }
        if ($val !== '') {
            $events[] = [
                'type' => 'mute',
                'mute' => ($val === '1'),
            ];
        }
    }

    // -------------------------------------------------
    // EQ (Bass/Treble/Loudness, Balance, etc.)
    // Sonos liefert Bass/Treble/Balance teils OHNE channel="Master".
    // Daher: zuerst Master versuchen, sonst ohne channel, sonst "irgendeins".
    // -------------------------------------------------
    $eq = [];

    // helper: first matching node value (prefer Master channel, then no channel, then any)
    $first_val = function(array $xp) use ($sx): ?string {
        foreach ($xp as $expr) {
            $nodes = $sx->xpath($expr) ?: [];
            foreach ($nodes as $n) {
                // Prefer attribute val, else element text
                $val = (string)($n['val'] ?? '');
                if ($val === '') $val = trim((string)$n);
                // allow "0" and negative values
                if ($val !== '' && is_numeric($val)) {
                    return $val;
                }
            }
        }
        return null;
    };

    // Bass
    $val = $first_val([
        '//*[local-name()="Bass"][@channel="Master"]',
        '//*[local-name()="Bass"][not(@channel)]',
        '//*[local-name()="Bass"]',
    ]);
    if ($val !== null) $eq['bass'] = (int)$val;

    // Treble
    $val = $first_val([
        '//*[local-name()="Treble"][@channel="Master"]',
        '//*[local-name()="Treble"][not(@channel)]',
        '//*[local-name()="Treble"]',
    ]);
    if ($val !== null) $eq['treble'] = (int)$val;

    // Loudness (meist channel="Master", aber sicher ist sicher)
    $val = $first_val([
        '//*[local-name()="Loudness"][@channel="Master"]',
        '//*[local-name()="Loudness"][not(@channel)]',
        '//*[local-name()="Loudness"]',
    ]);
    if ($val !== null) $eq['loudness'] = ((int)$val === 1) ? 1 : 0;

    // Balance (oft ohne channel)
    $val = $first_val([
        '//*[local-name()="Balance"][@channel="Master"]',
        '//*[local-name()="Balance"][not(@channel)]',
        '//*[local-name()="Balance"]',
    ]);
    if ($val !== null) $eq['balance'] = (int)$val;

    // SubGain / NightMode / DialogLevel (unverändert, aber numeric-safe)
    $val = $first_val(['//*[local-name()="SubGain"]']);
    if ($val !== null) $eq['subgain'] = (int)$val;

    $val = $first_val(['//*[local-name()="NightMode"]']);
    if ($val !== null) $eq['nightmode'] = ((int)$val === 1) ? 1 : 0;

    $val = $first_val(['//*[local-name()="DialogLevel"]']);
    if ($val !== null) $eq['dialoglevel'] = (int)$val;

    if (!isset($eq['balance']) && isset($volByChan['LF'], $volByChan['RF'])) {
        // einfache, robuste Ableitung: Differenz (rechts - links)
        $eq['balance'] = (int)$volByChan['RF'] - (int)$volByChan['LF'];

        // optional: clamp, falls du keine Ausreißer willst
        if ($eq['balance'] < -100) $eq['balance'] = -100;
        if ($eq['balance'] >  100) $eq['balance'] =  100;
    }

    if (!empty($eq)) {
        $events[] = ['type' => 'eq'] + $eq;
    }

    // -------------------------------------------------
    // Playmode (CurrentPlayMode / CurrentCrossfadeMode)
    // -------------------------------------------------
    $pm = [];

    foreach ($sx->xpath('//*[local-name()="CurrentPlayMode"]') as $n) {
        $val = (string)$n['val'];
        if ($val === '') {
            $val = trim((string)$n);
        }
        if ($val !== '') {
            $pm['mode_raw'] = $val;
        }
    }

    foreach ($sx->xpath('//*[local-name()="CurrentCrossfadeMode"]') as $n) {
        $val = (string)$n['val'];
        if ($val === '') {
            $val = trim((string)$n);
        }
        if ($val !== '') {
            $pm['crossfade'] = ($val === '1') ? 1 : 0;
        }
    }

    // --- Playmode fixed numeric code (based on mode_raw) ---
    if (!empty($pm['mode_raw'])) {
        $rawU = strtoupper((string)$pm['mode_raw']);

        // normalize a few common variants
        if ($rawU === 'SHUFFLE') $rawU = 'SHUFFLE_NOREPEAT';
        if ($rawU === 'REPEAT')  $rawU = 'REPEAT_ALL';

        $modeCodeMap = [
            'NORMAL'             => 0,
            'REPEAT_ALL'         => 1,
            'REPEAT_ONE'         => 2,
            'SHUFFLE_NOREPEAT'   => 3,
            'SHUFFLE_REPEAT_ALL' => 4,
            'SHUFFLE_REPEAT_ONE' => 5,
        ];

        $pm['code'] = $modeCodeMap[$rawU] ?? 99;
    } else {
        $pm['code'] = 99;
    }

    if (!empty($pm)) {
        $events[] = ['type' => 'playmode'] + $pm;
    }

    // -------------------------------------------------
    // Position/Progress
    // -------------------------------------------------
    $position = [];

    foreach ($sx->xpath('//*[local-name()="CurrentTrackDuration"]') as $n) {
        $val = (string)$n['val'];
        if ($val === '') {
            $val = trim((string)$n);
        }
        $sec = hms_to_seconds($val);
        if ($sec !== null) {
            $position['duration_sec'] = $sec;
            break;
        }
    }

    foreach ($sx->xpath('//*[local-name()="RelTime"] | //*[local-name()="RelativeTimePosition"]') as $n) {
        $val = (string)$n['val'];
        if ($val === '') {
            $val = trim((string)$n);
        }
        $sec = hms_to_seconds($val);
        if ($sec !== null) {
            $position['position_sec'] = $sec;
            break;
        }
    }

    foreach ($sx->xpath('//*[local-name()="CurrentTrack"]') as $n) {
        $val = trim((string)$n['val'] !== '' ? (string)$n['val'] : (string)$n);
        if ($val !== '' && ctype_digit($val)) {
            $position['track_no'] = (int)$val;
            break;
        }
    }

    foreach ($sx->xpath('//*[local-name()="NumberOfTracks"]') as $n) {
        $val = trim((string)$n['val'] !== '' ? (string)$n['val'] : (string)$n);
        if ($val !== '' && ctype_digit($val)) {
            $position['track_count'] = (int)$val;
            break;
        }
    }

    foreach ($sx->xpath('//*[local-name()="AVTransportURI"]') as $n) {
        $val = trim((string)$n);
        if ($val !== '') {
            $position['av_uri'] = $val;
            break;
        }
    }

    if (!empty($position['duration_sec']) && isset($position['position_sec'])) {
        $dur = max(1, (int)$position['duration_sec']);
        $pos = max(0, (int)$position['position_sec']);
        $pct = (int)round($pos * 100 / $dur);
        if ($pct < 0) $pct = 0;
        if ($pct > 100) $pct = 100;
        $position['progress_pct'] = $pct;
    }

    if (!empty($position)) {
        $events[] = ['type' => 'position'] + $position;
    }

    return $events;
}


// ZGT: ZoneGroupState -> Gruppen je Raum
function parse_topology(string $zoneGroupState): array {
    libxml_use_internal_errors(true);
    $x = @simplexml_load_string($zoneGroupState);
    if (!$x) return [];
    $groups = []; // groupId => ['coordinator'=>rincon,'members'=>[rincon=>['ip'=>..,'room'=>..]]]
    foreach ($x->xpath('//*[local-name()="ZoneGroup"]') as $zg) {
        $gid   = (string)$zg['ID'];
        $coord = (string)$zg['Coordinator'];
        $groups[$gid] = ['coordinator' => $coord, 'members' => []];
        foreach ($zg->xpath('.//*[local-name()="ZoneGroupMember"]') as $mem) {
            $rid = (string)$mem['UUID'];
            $rnm = (string)$mem['ZoneName'];
            $ip  = (string)$mem['Location'];
            if ($ip && strpos($ip, 'http') === 0) {
                $p  = parse_url($ip);
                $ip = $p['host'] ?? $ip;
            }
            $groups[$gid]['members'][$rid] = ['room' => $rnm, 'ip' => $ip];
        }
    }
    return $groups;
}

/**
 * Kompletten Satz an Sonos SUBSCRIPTIONS neu aufbauen.
 * - Alle bestehenden SIDs werden UNSUBSCRIBE'd
 * - Danach wie beim Start für alle Räume AVTransport/RenderingControl/ZGT neu SUBSCRIBE
 */
function rebuild_all_subscriptions(
    array &$subs,
    array $rooms,
    string $CALLBACK_URL,
    int $TIMEOUT_SEC,
    int $RENEW_MARGIN
): void {
    logln('info', 'Rebuilding all Sonos SUBSCRIPTIONS (full resubscribe) …');

    // 1) Alte Subscriptions abräumen
    foreach ($subs as $sid => $s) {
        if (empty($s['eventUrl'])) {
            continue;
        }
        $host = parse_url($s['eventUrl'], PHP_URL_HOST);
        $port = parse_url($s['eventUrl'], PHP_URL_PORT);
        http_req('UNSUBSCRIBE', $s['eventUrl'], [
            "HOST: $host:$port",
            "SID: " . $sid,
            "Connection: close",
        ]);
    }
    $subs = [];
	
	// try to repopulate missing event URLs before resubscribe
	refresh_event_urls_for_missing_rooms($rooms);

    // 2) Neu SUBSCRIBE für alle Räume und Services
	foreach ($rooms as $room => $meta) {
		foreach (['AVTransport', 'RenderingControl', 'GroupRenderingControl', 'ZoneGroupTopology'] as $svc) {
            if (empty($meta['events'][$svc])) {
                continue;
            }
            $evUrl = $meta['events'][$svc];

            $host = parse_url($evUrl, PHP_URL_HOST);
            $port = parse_url($evUrl, PHP_URL_PORT);

            [$resH, ] = http_req('SUBSCRIBE', $evUrl, [
                "HOST: $host:$port",
                "CALLBACK: <{$CALLBACK_URL}>",
                "NT: upnp:event",
                "TIMEOUT: Second-{$TIMEOUT_SEC}",
                "Connection: close",
            ]);

            $sid  = header_value($resH, 'SID') ?: header_value($resH, 'Sid');
            $tout = header_value($resH, 'TIMEOUT') ?: header_value($resH, 'Timeout');

            if ($sid) {
                $ttl = (preg_match('~Second-(\d+)~i', (string)$tout, $m) ? (int)$m[1] : $TIMEOUT_SEC);
                $renewAt = time() + max(60, $ttl) - $RENEW_MARGIN;
                $subs[$sid] = [
                    'service'  => $svc,
                    'eventUrl' => $evUrl,
                    'room'     => $room,
                    'renewAt'  => $renewAt,
                ];
                logln('ok', "SUBSCRIBE $svc @ {$meta['ip']} ($room) -> SID=$sid (rebuild)");
            } else {
                logln('warn', "SUBSCRIBE $svc @ {$meta['ip']} ($room) FAILED during rebuild");
            }

            usleep(100000);
        }
    }
}


// ------- Publish Helper: raum-zentriert & vollständige Payload -------
// global cache to deduplicate group events: room|group_id => signature
$lastGroupState 	= [];
$lastEqByRoom 		= [];
// group cache: room => ['group_id'=>..., 'members'=>[...], 'is_coordinator'=>0/1, 'ts'=>...]
$groupByRoom 		= [];



function publish_room_event(
    string $room,
    string $type,
    array $data,
    array $rooms,
    SonosMqttClient $mqttClient,
    string $prefix,
    string $service,
    int $qos
): void {

    global $lastEqByRoom, $groupByRoom;

    // CENTRAL: always lowercase room everywhere downstream
    $room = strtolower($room);

    // Default: publish only for this room
    $targetRooms = [$room];

    // For track/nexttrack: if we know the group and THIS room is coordinator, publish for all members
    if (($type === 'track' || $type === 'nexttrack') && !empty($groupByRoom[$room])) {
        $g = $groupByRoom[$room];

        $members = $g['members'] ?? [];
        $isCoord = !empty($g['is_coordinator']);

        if ($isCoord && is_array($members) && count($members) > 1) {
            // normalize members to lowercase and ensure coordinator room included
            $members = array_map('strtolower', $members);
            $members[] = $room;
            $targetRooms = array_values(array_unique(array_filter($members)));
        }
    }

    // Typ ins Payload schreiben
    $data['type'] = $type;

    // --- State-Normalisierung + state_code (für Loxone) ---
    if ($type === 'state' && isset($data['state'])) {
        $raw = (string)$data['state'];
        switch ($raw) {
            case 'PAUSED_PLAYBACK':
                $data['state'] = 'PAUSED';
                break;
            case 'PLAYING':
            case 'STOPPED':
            case 'TRANSITIONING':
                $data['state'] = $raw;
                break;
            default:
                $data['state'] = $raw;
                break;
        }

        // numerischer Code wie GetTransportInfo()
        $map = [
            'PLAYING'       => 1,
            'PAUSED'        => 2,
            'STOPPED'       => 3,
            'TRANSITIONING' => 4,
        ];
        $data['state_code'] = $map[$data['state']] ?? 0;
    }

    // --- Volume-Alias ---
    if ($type === 'volume' && isset($data['volume'])) {
        $data['vol'] = (int)$data['volume'];
    }

    // --- Mute-Alias ---
    if ($type === 'mute' && array_key_exists('mute', $data)) {
        $data['mute_int'] = !empty($data['mute']) ? 1 : 0;
    }

    // --- EQ: loudness_int + Snapshot für _health ---
    if ($type === 'eq') {
        if (isset($data['bass'])) {
            $data['bass'] = (int)$data['bass'];
        }
        if (isset($data['treble'])) {
            $data['treble'] = (int)$data['treble'];
        }
        if (isset($data['loudness'])) {
            $data['loudness']     = (int)$data['loudness'] ? 1 : 0;
            $data['loudness_int'] = $data['loudness'];
        }

        // EQ-Snapshot je Raum: MERGE (fehlende Werte beibehalten)
		$prev = $lastEqByRoom[$room] ?? [];

		$merged = $prev;

		// ints
		foreach (['bass','treble','balance','subgain','dialoglevel'] as $k) {
			if (array_key_exists($k, $data)) {
				$merged[$k] = (int)$data[$k];
			} elseif (!array_key_exists($k, $merged)) {
				$merged[$k] = 0;
			}
		}

		// bool-ish ints
		foreach (['loudness','loudness_int','nightmode'] as $k) {
			if (array_key_exists($k, $data)) {
				$merged[$k] = (int)$data[$k] ? 1 : 0;
			} elseif (!array_key_exists($k, $merged)) {
				$merged[$k] = 0;
			}
		}

		$merged['ts'] = time();
		$lastEqByRoom[$room] = $merged;
    }

    // --- Playmode: Shuffle/Repeat/Crossfade ---
    if ($type === 'playmode') {
        $raw = strtoupper((string)($data['mode_raw'] ?? $data['playmode'] ?? ''));

        $shuffle    = 0;
        $repeat     = 0;
        $repeat_one = 0;

        if ($raw !== '') {
            if (strpos($raw, 'SHUFFLE') !== false) {
                $shuffle = 1;
            }

            if (strpos($raw, 'REPEAT') !== false) {
                $repeat = 1;
            }

            if ($raw === 'REPEAT_ONE' || strpos($raw, 'REPEAT_ONE') !== false) {
                $repeat_one = 1;
                $repeat     = 1;
            }
        }

        if (!isset($data['mode_raw'])) {
            $data['mode_raw'] = $raw;
        }

        if (isset($data['crossfade'])) {
            $data['crossfade'] = (int)$data['crossfade'] ? 1 : 0;
        }

        $data['shuffle']    = $shuffle;
        $data['repeat']     = $repeat;
        $data['repeat_one'] = $repeat_one;
    }

    // --- Source-Erkennung + alte Sonos4lox-Kompatibilität (track) ---
    if ($type === 'track') {
        // Source erkennen (TV / Radio / Track / LineIn / Nothing)
        $src = get_source_for_room($room, $rooms);
        if ($src !== null) {
            $data['source_text'] = $src;
            $map = [
                'Nothing' => 0,
                'Radio'   => 1,
                'Track'   => 2,
                'TV'      => 3,
                'LineIn'  => 4,
            ];
            $data['source'] = $map[$src] ?? 0; // wie früher source_<room>
        }

        $title  = (string)($data['title']  ?? '');
        $artist = (string)($data['artist'] ?? '');
        $album  = (string)($data['album']  ?? '');

        // tit / int / titint
        if (($data['source_text'] ?? '') === 'TV') {
            $data['tit']    = 'TV';
            $data['int']    = 'TV';
            $data['titint'] = 'TV';
        } else {
            $data['tit'] = $title;
            $data['int'] = $artist;
            if ($artist !== '' && $title !== '') {
                $data['titint'] = $artist . ' - ' . $title;
            } else {
                $data['titint'] = $title;
            }
        }

		// Radio-Sender + Song sauber trennen
		if (($data['source_text'] ?? '') === 'Radio') {

			// 1) Sender IMMER aus CurrentURIMetaData (GetMediaInfo)
			$station = get_radio_station_for_room($room, $rooms);
			$station = trim((string)($station ?? ''));

			// 2) Song/NowPlaying: bevorzugt streamContent, sonst Artist-Title, sonst title
			$song = trim((string)($data['streamContent'] ?? ''));
			if ($song === '') {
				$t = trim((string)($data['title'] ?? ''));
				$a = trim((string)($data['artist'] ?? ''));
				if ($a !== '' && $t !== '') $song = $a . ' - ' . $t;
				else $song = $t;
			}

			// 3) MQTT Felder
			$data['radio'] = $station;              // Sendername
			$data['streamContent'] = $song;    		// NowPlaying

		} else {

			$data['radio'] = '';
		}


        // Cover / SID / TV-State
        $data['cover'] = $data['albumArtUri'] ?? null;

        if (($data['source_text'] ?? '') === 'TV') {
            $data['sid'] = 'TV';
        } elseif (($data['source_text'] ?? '') === 'Radio') {
            $data['sid'] = 'Radio';
        } else {
            $data['sid'] = 'Music';
        }

        // Platzhalter (0) – könnte später mit echtem HTAudioIn ersetzt werden
        $data['tvstate'] = 0;
    }

    // --- Group-Rolle (single/master/member) ---
    if ($type === 'group') {
        $members  = $data['members'] ?? [];
        $isCoord  = !empty($data['is_coordinator']);
        $roleName = 'single';
        $roleCode = 1;

        if (count($members) > 1) {
            if ($isCoord) {
                $roleName = 'master';
                $roleCode = 2;
            } else {
                $roleName = 'member';
                $roleCode = 3;
            }
        }

        $data['role_name'] = $roleName;
        $data['role_code'] = $roleCode; // wie früher grp_<room>
		// Update group cache for replication of track/nexttrack
		$memRooms = [];
		if (!empty($data['members']) && is_array($data['members'])) {
			foreach ($data['members'] as $mm) {
				$rn = strtolower((string)($mm['room'] ?? ''));
				if ($rn !== '') $memRooms[] = $rn;
			}
		}
		$memRooms[] = $room;
		$memRooms = array_values(array_unique($memRooms));

		$groupByRoom[$room] = [
			'group_id'        => (string)($data['group_id'] ?? ''),
			'members'         => $memRooms,
			'is_coordinator'  => !empty($data['is_coordinator']) ? 1 : 0,
			'ts'              => time(),
		];
	}

    // Geräte-Metadaten
    $meta = $rooms[$room] ?? ['ip' => '', 'rincon' => '', 'model' => ''];

    // Vollständiges Payload aufbauen
    $payload = $data + [
        'room'    => $room,
        'ip'      => $meta['ip'],
        'rincon'  => $meta['rincon'],
        'model'   => $meta['model'],
        'service' => $service,
        'ts'      => time(),
    ];
	
    $topic  = rtrim($prefix, '/') . "/{$room}/{$type}";
    // track/nexttrack auch retainen, damit Loxone sie zuverlässig abholen kann
    $retain = in_array($type, ['state', 'volume', 'track', 'nexttrack'], true);

    // -------- Group-Events entdünnen (ohne Logspam) --------
    if ($type === 'group') {
        static $lastGroupHash = [];

        // Timestamp für den Vergleich ignorieren
        $cmp = $payload;
        unset($cmp['ts']);

        $hash = md5(json_encode($cmp, JSON_UNESCAPED_UNICODE));

        // Wenn sich nichts geändert hat → Event stillschweigend verwerfen
        if (isset($lastGroupHash[$room]) && $lastGroupHash[$room] === $hash) {
            return;
        }

        $lastGroupHash[$room] = $hash;
    }

    // --- publish for one or multiple target rooms (group replication for track/nexttrack) ---
    $published = 0;

    foreach ($targetRooms as $tr) {
        $tr = strtolower((string)$tr);
        if ($tr === '') continue;

        $metaT = $rooms[$tr] ?? $meta;

        $payloadT = $payload;
        $payloadT['room']   = $tr;
        $payloadT['ip']     = $metaT['ip']     ?? ($payload['ip'] ?? '');
        $payloadT['rincon'] = $metaT['rincon'] ?? ($payload['rincon'] ?? '');
        $payloadT['model']  = $metaT['model']  ?? ($payload['model'] ?? '');

        $topicT = rtrim($prefix, '/') . "/{$tr}/{$type}";

        $jsonT = json_encode($payloadT, JSON_UNESCAPED_UNICODE);
        if ($jsonT === false) {
            logln('warn', "JSON encode failed for $tr/$type");
            continue;
        }

        $ok = $mqttClient->publish($topicT, $jsonT, $retain, $qos);
        if (!$ok) {
            logln('warn', "MQTT publish failed: $topicT");
        } else {
            $published++;
        }

        // ----------------------------------------------------
        // Legacy-Kompatibilität: alte Sonos4lox-Topics weiter bedienen
        // (nur das, was du ohnehin publizierst; für track replizieren wir das genauso)
        // ----------------------------------------------------
        $legacyPrefix = 'Sonos4lox';

        try {
            if ($type === 'volume' && isset($payloadT['volume'])) {
                $mqttClient->publish($legacyPrefix . '/vol/' . $tr, (string)$payloadT['volume'], true, 0);
            }

            if ($type === 'state' && isset($payloadT['state_code'])) {
                $mqttClient->publish($legacyPrefix . '/stat/' . $tr, (string)$payloadT['state_code'], true, 0);
            }

            if ($type === 'group' && isset($payloadT['role_code'])) {
                $mqttClient->publish($legacyPrefix . '/grp/' . $tr, (string)$payloadT['role_code'], true, 0);
            }

            if ($type === 'mute' && array_key_exists('mute_int', $payloadT)) {
                $mqttClient->publish($legacyPrefix . '/mute/' . $tr, (string)$payloadT['mute_int'], true, 0);
            }

            if ($type === 'track') {
                $mqttClient->publish($legacyPrefix . '/titint/' . $tr, (string)($payloadT['titint'] ?? ''), true, 0);
                $mqttClient->publish($legacyPrefix . '/tit/'    . $tr, (string)($payloadT['tit']    ?? ''), true, 0);
                $mqttClient->publish($legacyPrefix . '/int/'    . $tr, (string)($payloadT['int']    ?? ''), true, 0);
                $mqttClient->publish($legacyPrefix . '/radio/'  . $tr, (string)($payloadT['radio']  ?? ''), true, 0);

                if (isset($payloadT['source'])) {
                    $mqttClient->publish($legacyPrefix . '/source/' . $tr, (string)$payloadT['source'], true, 0);
                }

                $mqttClient->publish($legacyPrefix . '/sid/'   . $tr, (string)($payloadT['sid']   ?? ''), true, 0);
                $mqttClient->publish($legacyPrefix . '/cover/' . $tr, (string)($payloadT['cover'] ?? ''), true, 0);

                if (isset($payloadT['tvstate'])) {
                    $mqttClient->publish($legacyPrefix . '/tvstate/' . $tr, (string)$payloadT['tvstate'], true, 0);
                }
            }

        } catch (Throwable $e) {
            logln('warn', "Legacy MQTT publish failed for $tr/$type: " . $e->getMessage());
        }

        // ----------------------------------------------------
        // Loxone HTTP push (optional) - pro Zielraum
        // ----------------------------------------------------
        global $loxTarget, $LOXONE_HTTP_PUBLISH;
        loxone_publish_for_event($type, $payloadT, $loxTarget, $LOXONE_HTTP_PUBLISH);
    }

    // Optional: einmaliger Debug-Hinweis bei Replikation (ohne Spam)
    if (($type === 'track' || $type === 'nexttrack') && count($targetRooms) > 1) {
        logln('dbg', "$room $type replicated to members: " . implode(',', $targetRooms));
    }


    // ----------------------------------------------------
    // Legacy-Kompatibilität: alte Sonos4lox-Topics weiter bedienen
    // ----------------------------------------------------
    $legacyPrefix = 'Sonos4lox';

    try {
        // 1) Volume -> Sonos4lox/vol/<room>
        if ($type === 'volume' && isset($payload['volume'])) {
            $mqttClient->publish(
                $legacyPrefix . '/vol/' . $room,
                (string)$payload['volume'],
                true,   // retain wie früher
                0       // QoS 0 wie im alten push_loxone.php
            );
        }

        // 2) State -> Sonos4lox/stat/<room> (GetTransportInfo-Code)
        if ($type === 'state' && isset($payload['state_code'])) {
            $mqttClient->publish(
                $legacyPrefix . '/stat/' . $room,
                (string)$payload['state_code'],
                true,
                0
            );
        }

        // 3) Group-Role -> Sonos4lox/grp/<room> (1=single,2=master,3=member)
        if ($type === 'group' && isset($payload['role_code'])) {
            $mqttClient->publish(
                $legacyPrefix . '/grp/' . $room,
                (string)$payload['role_code'],
                true,
                0
            );
        }

        // 4) Mute -> Sonos4lox/mute/<room> (0/1)
        if ($type === 'mute' && array_key_exists('mute_int', $payload)) {
            $mqttClient->publish(
                $legacyPrefix . '/mute/' . $room,
                (string)$payload['mute_int'],
                true,
                0
            );
        }

        // 5) Track-Infos -> Sonos4lox/tit*, /radio, /source, /sid, /cover, /tvstate
        if ($type === 'track') {
            $mqttClient->publish(
                $legacyPrefix . '/titint/' . $room,
                (string)($payload['titint'] ?? ''),
                true,
                0
            );
            $mqttClient->publish(
                $legacyPrefix . '/tit/' . $room,
                (string)($payload['tit'] ?? ''),
                true,
                0
            );
            $mqttClient->publish(
                $legacyPrefix . '/int/' . $room,
                (string)($payload['int'] ?? ''),
                true,
                0
            );
            $mqttClient->publish(
                $legacyPrefix . '/radio/' . $room,
                (string)($payload['radio'] ?? ''),
                true,
                0
            );

            if (isset($payload['source'])) {
                $mqttClient->publish(
                    $legacyPrefix . '/source/' . $room,
                    (string)$payload['source'],   // 0..4 wie früher
                    true,
                    0
                );
            }

            $mqttClient->publish(
                $legacyPrefix . '/sid/' . $room,
                (string)($payload['sid'] ?? ''),
                true,
                0
            );
            $mqttClient->publish(
                $legacyPrefix . '/cover/' . $room,
                (string)($payload['cover'] ?? ''),
                true,
                0
            );

            if (isset($payload['tvstate'])) {
                $mqttClient->publish(
                    $legacyPrefix . '/tvstate/' . $room,
                    (string)$payload['tvstate'],
                    true,
                    0
                );
            }
        }

    } catch (Throwable $e) {
        logln('warn', "Legacy MQTT publish failed for $room/$type: " . $e->getMessage());
    }
	
	// ----------------------------------------------------
    // Loxone HTTP push (optional)
    // ----------------------------------------------------
    global $loxTarget, $LOXONE_HTTP_PUBLISH;
    loxone_publish_for_event($type, $payload, $loxTarget, $LOXONE_HTTP_PUBLISH);


    // Log kompakt
    if     ($type === 'track')     logln('evnt', "$room track: " . ($payload['title'] ?? '') . " — " . ($payload['artist'] ?? '') . " [" . ($payload['album'] ?? '') . "]");
    elseif ($type === 'nexttrack') logln('evnt', "$room next: " . ($payload['title'] ?? ($payload['uri'] ?? '')));
    elseif ($type === 'state')     logln('evnt', "$room state: {$payload['state']} ({$payload['state_code']})");
    elseif ($type === 'volume')    logln('evnt', "$room volume: {$payload['volume']}");
    elseif ($type === 'mute')      logln('evnt', "$room mute: " . (!empty($payload['mute']) ? 'on' : 'off'));
    elseif ($type === 'group')     logln('evnt', "$room group: gid={$payload['group_id']} coord=" . (!empty($payload['is_coordinator']) ? 'yes' : 'no') . " role={$payload['role_name']}");
	    elseif ($type === 'position') {
        $pos = $payload['position_sec'] ?? null;
        $dur = $payload['duration_sec'] ?? null;
        $pct = $payload['progress_pct'] ?? null;
        logln(
            'evnt',
            sprintf(
                "%s position: %s/%s s (%s%%)",
                $room,
                $pos !== null ? $pos : '-',
                $dur !== null ? $dur : '-',
                $pct !== null ? $pct : '-'
            )
        );
    }
    elseif ($type === 'eq')        logln('evnt', "$room eq: bass=" . ($payload['bass'] ?? '-') . " treble=" . ($payload['treble'] ?? '-') . " loudness=" . ($payload['loudness'] ?? '-'));
    elseif ($type === 'playmode')  logln('evnt', "$room playmode: {$payload['mode_raw']} shuffle={$payload['shuffle']} repeat={$payload['repeat']} crossfade=" . ($payload['crossfade'] ?? '-'));
}

// --------------------------------- HTTP Request Processing ---------------------------------
function process_sonos_http_request(
    $sock,
    string $raw,
    array &$subs,
    array $rooms,
    SonosMqttClient $mqttClient,
    string $TOPIC_PREFIX,
    int $MQTT_QOS
): void {
    global $lastNotifyTs;

    // 200 OK antworten
    @fwrite($sock, "HTTP/1.1 200 OK\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");

    if ($raw === '') {
        return;
    }

    // Wir haben einen NOTIFY gesehen
    $lastNotifyTs = time();

    // SID und Service/Raum ermitteln
    $sid = '';
    if (preg_match('~\r\nSID:\s*(uuid:[^\s]+)~i', $raw, $m)) {
        $sid = $m[1];
    }
    $info = $subs[$sid] ?? null;
    $room = $info['room']    ?? 'Unknown';
    $svc  = $info['service'] ?? 'Unknown';

    // Fallback, falls wir über SID keinen Raum finden – versuche es über die Peer-IP
    if ($room === 'Unknown' && is_resource($sock)) {
        $peer   = @stream_socket_get_name($sock, true); // "IP:Port"
        $peerIp = null;
        if ($peer && strpos($peer, ':') !== false) {
            $peerIp = explode(':', $peer, 2)[0];
        }
        if ($peerIp) {
            foreach ($rooms as $rName => $meta) {
                if (!empty($meta['ip']) && $meta['ip'] === $peerIp) {
                    $room = $rName;
                    break;
                }
            }
            if ($room !== 'Unknown') {
                logln('dbg', "Room resolved via peer IP: $room (peer=$peer, SID=$sid)");
            } else {
                logln('dbg', "Could not resolve room via peer IP ($peer), SID=$sid");
            }
        }
    }

    // Header/Body robust trennen (\r\n\r\n oder \n\n)
    $parts = preg_split("/\r\n\r\n|\n\n/s", $raw, 2);
    $body  = $parts[1] ?? '';

    $firstLine = strtok($raw, "\r\n");
    logln('dbg', "HTTP request: $firstLine bodylen=" . strlen($body));

    if (strlen($body) <= 600) {
        $prettyBody = str_replace(["\r", "\n"], ['', '\n'], substr($body, 0, 500));
        logln('dbg', "HTTP BODY (short) START: " . $prettyBody);
    }

    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($body);

    if ($sx !== false) {
        $sx->registerXPathNamespace('e', 'urn:schemas-upnp-org:event-1-0');

        // --- LastChange (AVTransport / RenderingControl / GroupRenderingControl) ---
        $hadLastChange = false;
        foreach ($sx->xpath('//*[local-name()="LastChange"]') as $lc) {
            // Wichtig: kein html_entity_decode auf dem kompletten LastChange!
            $inner = (string)$lc;

            logln('dbg', "LastChange inner XML START: " . substr($inner, 0, 200));

            foreach (parse_lastchange($inner, $rooms[$room]['base'] ?? '') as $ev) {
                $hadLastChange = true;

                publish_room_event(
                    $room,
                    $ev['type'],
                    $ev,
                    $rooms,
                    $mqttClient,
                    $TOPIC_PREFIX,
                    $svc,
                    $MQTT_QOS
                );
            }
        }

        // Wenn wir einen LastChange hatten → passenden Service-Timestamp setzen
        if ($hadLastChange) {
            global $lastAvtEventTs, $lastRcEventTs;
            if ($svc === 'AVTransport') {
                $lastAvtEventTs = time();
            } elseif ($svc === 'RenderingControl' || $svc === 'GroupRenderingControl') {
                $lastRcEventTs = time();
            }
        }


        foreach ($sx->xpath('//*[local-name()="ZoneGroupState"]') as $zgs) {
            global $lastZgtEventTs;
            $lastZgtEventTs = time();

            $state  = html_entity_decode((string)$zgs, ENT_QUOTES | ENT_XML1, 'UTF-8');
            logln('dbg', "ZoneGroupState inner XML START: " . substr($state, 0, 200));
            $groups = parse_topology($state);

            // --- Health-Flags aus Topologie ableiten ---
            global $zones, $healthRoomsFlags;

            // Map: RINCON (UUID) -> Raumname aus der *Config* (sonoszonen)
            $rin2room = [];
            foreach ($zones as $roomName => $cfgArr) {
                if (!empty($cfgArr[1])) {           // [1] = RINCON aus s4lox_config.json
                    $rin2room[$cfgArr[1]] = $roomName;
                }
            }

            // welche konfigurierten Räume sind laut aktueller Topologie präsent?
            $presentRooms = []; // room => true
            foreach ($groups as $gid => $g) {
                foreach ($g['members'] as $rin => $mi) {
                    if (isset($rin2room[$rin])) {
                        // immer auf den CONFIG-Namen (z.B. "badezimmer") mappen
                        $presentRooms[$rin2room[$rin]] = true;
                    }
                }
            }

            // Flags für alle konfigurierten Räume setzen (1 = in Topologie, 0 = fehlt)
            foreach (array_keys($zones) as $roomName) {
                $healthRoomsFlags[$roomName] = [
                    'Online' => isset($presentRooms[$roomName]) ? 1 : 0,
                ];
            }

            // ab hier bleibt dein bestehender Gruppen-Code unverändert
            foreach ($groups as $gid => $g) {
                $coordRincon = $g['coordinator'];
                $coordRoom   = $rin2room[$coordRincon] ?? null;

                // Für jeden bekannten Raum einen 'group'-Event, wenn Mitglied
                foreach ($rooms as $r => $mta) {
                    $isMember = false;
                    foreach ($g['members'] as $rin => $mi) {
                        if (!empty($mta['rincon']) && $mta['rincon'] === $rin) {
                            $isMember = true;
                            break;
                        }
                    }
                    if (!$isMember) {
                        continue;
                    }

                    $membersRooms = [];
                    foreach ($g['members'] as $rin => $mi) {
                        $membersRooms[] = [
                            'room'   => $rin2room[$rin] ?? $mi['room'] ?? '',
                            'rincon' => $rin,
                            'ip'     => $mi['ip'] ?? '',
                        ];
                    }

                    $data = [
                        'group_id'         => $gid,
                        'is_coordinator'   => (!empty($mta['rincon']) && $mta['rincon'] === $coordRincon),
                        'coordinator_room' => $coordRoom,
                        'members'          => $membersRooms,
                    ];
                    publish_room_event(
                        $r,
                        'group',
                        $data,
                        $rooms,
                        $mqttClient,
                        $TOPIC_PREFIX,
                        'ZoneGroupTopology',
                        $MQTT_QOS
                    );
                }
            }
        }


        // --- GroupRenderingControl: GroupVolume / GroupMute ---
        foreach ($sx->xpath('//*[local-name()="GroupVolume"]') as $gv) {
            $vol = (int)$gv;
            publish_room_event(
                $room,
                'volume',
                ['volume' => $vol],
                $rooms,
                $mqttClient,
                $TOPIC_PREFIX,
                $svc,
                $MQTT_QOS
            );
        }

		foreach ($sx->xpath('//*[local-name()="GroupMute"]') as $gm) {
            $mute = ((string)$gm === '1');
            publish_room_event(
                $room,
                'mute',
                ['mute' => $mute],
                $rooms,
                $mqttClient,
                $TOPIC_PREFIX,
                $svc,          // <--- Service-Name ergänzen
                $MQTT_QOS
            );
        }

    } else {
        $errs = libxml_get_errors();
        if (!empty($errs)) {
            $e = $errs[0];
            logln(
                'warn',
                "XML parse error line {$e->line}, col {$e->column}: " . trim($e->message)
            );
        } else {
            logln('warn', "XML parse error: unknown (bodylen=" . strlen($body) . ")");
        }
        libxml_clear_errors();

        logln('dbg', "RAW HTTP HEADER: $firstLine");
        logln('dbg', "RAW BODY START: " . substr($body, 0, 200));
    }
}

// --------------------------------- Start: MQTT-Client & Callback ---------------------------------

$CALLBACK_HOST = $CALLBACK_HOST ?: LBSystem::get_localip();
$CALLBACK_PATH = '/sonos/cb';

$CALLBACK_URL  = "http://{$CALLBACK_HOST}:{$LISTEN_PORT}{$CALLBACK_PATH}";
logln('info', "Callback URL (for SUBSCRIBE): $CALLBACK_URL");

// MQTT-Verbindungsdaten aus LoxBerry holen
$mqttconf = mqtt_connectiondetails();
$mqttHost = $mqttconf['brokerhost'] ?? 'localhost';
$mqttPort = (int)($mqttconf['brokerport'] ?? 1883);
$mqttUser = $mqttconf['brokeruser'] ?? '';
$mqttPass = $mqttconf['brokerpass'] ?? '';

$mqttClientId = 'sonos_events_' . gethostname() . '_' . uniqid();
$mqttClient   = new SonosMqttClient($mqttHost, $mqttPort, $mqttClientId, $mqttUser ?: null, $mqttPass ?: null);

// Health-Topic
$healthTopic = rtrim($TOPIC_PREFIX, '/') . '/_health';

// --------------------------------- Räume/Player laden ---------------------------------
if (!file_exists(S4L_CFG)) { logln('error', "Config missing: " . S4L_CFG); exit(1); }
$cfg = json_decode(file_get_contents(S4L_CFG), true);
if (!is_array($cfg)) { logln('error', "Invalid JSON in " . S4L_CFG); exit(1); }

// --- Loxone Miniserver Target einmalig auflösen (optional) ---
$loxCfg       = $cfg['LOXONE'] ?? [];
$LoxDaten     = strtolower((string)($loxCfg['LoxDaten'] ?? 'false')) === 'true';
$loxMsId      = (string)($loxCfg['Loxone'] ?? '1');
$LoxDatenMQTT = strtolower((string)($loxCfg['LoxDatenMQTT'] ?? 'false'));

// --- Loxone Miniserver Target einmalig auflösen (optional) ---
$loxTarget = null;
if ($LoxDaten) {
    $loxTarget = get_miniserver_target_from_general_cached($loxMsId);
    if (!$loxTarget) {
        logln('warn', "LoxDaten=true but could not resolve Miniserver target from general.json (LOXONE.Loxone='$loxMsId')");
    } else {
        logln('ok', "Loxone target resolved: msId={$loxTarget['msId']} baseUrl={$loxTarget['baseUrl']}");
    }
}
// -------------------------------------------------------------
// Loxone HTTP push mapping (easy to extend)
// - Input names are YOUR Loxone Virtual Input names
// - Prefix here is NOT MQTT. It's only for your Loxone input naming.
// -------------------------------------------------------------
$LOXONE_HTTP_PREFIX = 's4lox';   // <-- das meint "s4lox_" vor deinen Loxone Inputs

$LOXONE_HTTP_PUBLISH = [
    // publish current track meta
    'track' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_title',  'value' => '{title}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_artist', 'value' => '{artist}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_album',  'value' => '{album}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_titint', 'value' => '{titint}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_radio',  'value' => '{radio}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_cover',  'value' => '{cover}'],
    ],

    // publish next track meta
    'nexttrack' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_title',  'value' => '{title}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_artist', 'value' => '{artist}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_album',  'value' => '{album}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_cover',  'value' => '{albumArtUri}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_uri',    'value' => '{uri}'],
    ],

    // examples for numeric events (optional)
    'volume' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_volume', 'value' => '{volume}'],
    ],
    'mute' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_mute', 'value' => '{mute_int}'],
    ],
    'state' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_state_code', 'value' => '{state_code}'],
    ],
];


$LoxDatenMQTT = strtolower((string)($loxCfg['LoxDatenMQTT'] ?? 'false'));
if ($LoxDatenMQTT !== 'true') {
    // hier gerne noch ein einfaches echo, falls du willst
    exit(0);
}

$zones = $cfg['sonoszonen'] ?? [];
if (!$zones) { logln('error', "Block 'sonoszonen' missing/empty"); exit(1); }

// Raum-Metadaten (aus s4lox_config.json)
$rooms = []; // room => ['ip','rincon','model','base','events'=>[]]
foreach ($zones as $room => $arr) {
    if (!is_array($arr) || empty($arr[0])) continue;
    $rooms[$room] = [
        'ip'     => (string)$arr[0],
        'rincon' => isset($arr[1]) ? (string)$arr[1] : '',
        'model'  => isset($arr[2]) ? (string)$arr[2] : '',
        'base'   => "http://" . (string)$arr[0] . ":1400",
        'events' => [],
    ];
}

// Event-URLs pro Raum ermitteln
foreach ($rooms as $room => &$meta) {

    $desc = null;
    for ($try = 1; $try <= 3; $try++) {
        $desc = http_get($meta['base'] . "/xml/device_description.xml", 6);
        if ($desc) break;

        logln('warn', "device_description.xml fetch failed for {$meta['ip']} ($room) try {$try}/3");
        usleep(250000);
    }

    if (!$desc) {
        logln('warn', "No device_description from {$meta['ip']} ($room) – keeping room, will retry later");
        $meta['events'] = [];     // bleibt leer, wird später erneut versucht
        continue;
    }

    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($desc);
    if (!$sx) {
        logln('warn', "XML error for {$meta['ip']} ($room) – keeping room, will retry later");
        $meta['events'] = [];
        continue;
    }

    $sx->registerXPathNamespace('d', 'urn:schemas-upnp-org:device-1-0');
    $svcs = $sx->xpath('//d:serviceList/d:service') ?: [];

    $evs  = [];
    foreach ($svcs as $svc) {
        $sid = (string)$svc->serviceId;
        $ev  = (string)$svc->eventSubURL;
        if (!$ev) continue;

        $full = (strpos($ev, 'http') === 0)
            ? $ev
            : rtrim($meta['base'], '/') . '/' . ltrim($ev, '/');

        if (stripos($sid, 'AVTransport') !== false) {
            $evs['AVTransport'] = $full;
        } elseif (stripos($sid, 'GroupRenderingControl') !== false) {
            $evs['GroupRenderingControl'] = $full;
        } elseif (stripos($sid, 'RenderingControl') !== false) {
            $evs['RenderingControl'] = $full;
        } elseif (stripos($sid, 'ZoneGroupTopology') !== false) {
            $evs['ZoneGroupTopology'] = $full;
        }
    }

    $meta['events'] = $evs;

    if (!empty($evs)) {
        logln('ok', "Device {$meta['ip']} ($room): " . implode(', ', array_keys($evs)));
    } else {
        logln('warn', "Device {$meta['ip']} ($room): no event URLs found – will retry later");
    }
}
unset($meta);

$hasAnyEvents = false;
foreach ($rooms as $r => $m) {
    if (!empty($m['events'])) { $hasAnyEvents = true; break; }
}
if (!$hasAnyEvents) {
    logln('error', 'No usable Sonos devices (no event URLs) – exiting.');
    exit(1);
}

$subs = []; // SID -> ['service','eventUrl','room','renewAt']
foreach ($rooms as $room => $meta) {
    foreach (['AVTransport', 'RenderingControl', 'GroupRenderingControl', 'ZoneGroupTopology'] as $svc) {
        if (empty($meta['events'][$svc])) continue;
        $evUrl = $meta['events'][$svc];
        $host  = parse_url($evUrl, PHP_URL_HOST);
        $port  = parse_url($evUrl, PHP_URL_PORT);

        [$resH, ] = http_req('SUBSCRIBE', $evUrl, [
            "HOST: $host:$port",
            "CALLBACK: <{$CALLBACK_URL}>",
            "NT: upnp:event",
            "TIMEOUT: Second-{$TIMEOUT_SEC}",
            "Connection: close",
        ]);

        $sid  = header_value($resH, 'SID') ?: header_value($resH, 'Sid');
        $tout = header_value($resH, 'TIMEOUT') ?: header_value($resH, 'Timeout');

        if ($sid) {
            $ttl     = (preg_match('~Second-(\d+)~i', (string)$tout, $m) ? (int)$m[1] : $TIMEOUT_SEC);
            $renewAt = time() + max(60, $ttl) - $RENEW_MARGIN;
            $subs[$sid] = [
                'service'  => $svc,
                'eventUrl' => $evUrl,
                'room'     => $room,
                'renewAt'  => $renewAt,
            ];
            logln('ok', "SUBSCRIBE $svc @ {$meta['ip']} ($room) -> SID=$sid");
        } else {
            logln('warn', "SUBSCRIBE $svc @ {$meta['ip']} ($room) failed");
        }

        usleep(100000);
    }
}

// UNSUBSCRIBE bei Ctrl+C
pcntl_signal(SIGINT, function() use (&$subs){
    logln('info', 'SIGINT → UNSUBSCRIBE …');
    foreach ($subs as $sid => $s) {
        $host = parse_url($s['eventUrl'], PHP_URL_HOST);
        $port = parse_url($s['eventUrl'], PHP_URL_PORT);
        http_req('UNSUBSCRIBE', $s['eventUrl'], [
            "HOST: $host:$port",
            "SID: " . $sid,
            "Connection: close",
        ]);
    }
    logln('info', 'last will and testament');
    exit(0);
});

// --------------------------------- HTTP Listener ---------------------------------
$server = @stream_socket_server("tcp://{$LISTEN_HOST}:{$LISTEN_PORT}", $errno, $errstr);
if (!$server) { logln('error', "Cannot bind port $LISTEN_PORT: $errstr ($errno)"); exit(1); }
stream_set_blocking($server, false);
logln('ok', "HTTP listener bound on {$LISTEN_HOST}:{$LISTEN_PORT} (path {$CALLBACK_PATH})");

// --------------------------------- Main Loop ---------------------------------
$clients = [];
$bufs    = [];

while (true) {
    $read = [$server]; foreach ($clients as $c) $read[] = $c;
    $write = $except = [];
    $sel = @stream_select($read, $write, $except, 1);
    if ($sel === false) continue;

    foreach ($read as $sock) {
        if ($sock === $server) {
            // Neue eingehende Verbindung
            $c = @stream_socket_accept($server, 0);
            if ($c) {
                stream_set_blocking($c, false);
                $id = (int)$c;
                $clients[$id] = $c;
                $bufs[$id]    = '';
                logln('dbg', "HTTP: new connection (id=$id)");
            }
        } else {
            $id    = (int)$sock;
            $chunk = fread($sock, 8192);

            if ($chunk === '' || $chunk === false) {
                // Verbindung vom Player geschlossen -> evtl. letzte Daten noch verarbeiten
                $raw = $bufs[$id] ?? '';
                if ($raw !== '') {
                    process_sonos_http_request(
                        $sock,
                        $raw,
                        $subs,
                        $rooms,
                        $mqttClient,
                        $TOPIC_PREFIX,
                        $MQTT_QOS
                    );
                }
                @fclose($sock);
                unset($clients[$id], $bufs[$id]);
                continue;
            }

            // Daten anhängen
            $bufs[$id] = ($bufs[$id] ?? '') . $chunk;
            $raw       = $bufs[$id];

            // Headerende suchen (\r\n\r\n oder \n\n)
            $headerEndPos = strpos($raw, "\r\n\r\n");
            $headerEndLen = 4;
            if ($headerEndPos === false) {
                $headerEndPos = strpos($raw, "\n\n");
                $headerEndLen = 2;
            }

            if ($headerEndPos === false) {
                // Header noch unvollständig -> weiter lesen
                continue;
            }

            // Content-Length aus Header ziehen
            $headerPart = substr($raw, 0, $headerEndPos);
            $lines      = preg_split("/\r\n|\n/", $headerPart);
            $contentLen = 0;
            foreach ($lines as $line) {
                if (stripos($line, 'Content-Length:') === 0) {
                    $contentLen = (int)trim(substr($line, 15));
                    break;
                }
            }

            $bodyStart = $headerEndPos + $headerEndLen;
            if (strlen($raw) < $bodyStart + $contentLen) {
                // Body noch nicht komplett
                continue;
            }

            // Wir haben einen vollständigen NOTIFY-Request im Buffer
            $oneRequest = substr($raw, 0, $bodyStart + $contentLen);
            $bufs[$id]  = substr($raw, $bodyStart + $contentLen);

            process_sonos_http_request(
                $sock,
                $oneRequest,
                $subs,
                $rooms,
                $mqttClient,
                $TOPIC_PREFIX,
                $MQTT_QOS
            );

            // Einfach: nach jedem NOTIFY die Verbindung schließen, Sonos öffnet neu
            @fclose($sock);
            unset($clients[$id], $bufs[$id]);
        }
    }

    // MQTT Housekeeping (KeepAlive/Reconnect)
    $mqttClient->loop();

        // --------------------------------- Health-Publish ---------------------------------
    $now = time();
    if ($now - $lastHealthPublish >= $HEALTH_INTERVAL) {
        $lastHealthPublish = $now;

        global $zones, $healthRoomsFlags, $lastAvtEventTs, $lastRcEventTs, $lastZgtEventTs, $lastEqByRoom;

        // 1) Alle Räume aus der CONFIG
        $allRooms = array_keys($zones);

        // Flags 0/1 pro Raum aus letzter Topologie; Fallback: Subscriptions
        $roomsFlags = []; // room => ['Online' => 0/1]
        foreach ($allRooms as $roomName) {
            if (isset($healthRoomsFlags[$roomName])) {
                // Wert aus letzter ZoneGroupTopology
                $roomsFlags[$roomName] = $healthRoomsFlags[$roomName];
            } else {
                // Noch keine Topologie gesehen → Fallback: gibt es überhaupt eine Subscription?
                $hasSub = false;
                foreach ($subs as $sid => $s) {
                    if ($s['room'] === $roomName) {
                        $hasSub = true;
                        break;
                    }
                }
                $roomsFlags[$roomName] = [ 'Online' => $hasSub ? 1 : 0 ];
            }
        }

        // Online-/Offline-Listen bauen
        $onlineRooms  = [];
        $offlineRooms = [];
        foreach ($roomsFlags as $roomName => $flagArr) {
            if (!empty($flagArr['Online'])) {
                $onlineRooms[] = $roomName;
            } else {
                $offlineRooms[] = $roomName;
            }
        }

        $age    = $now - $lastNotifyTs;
        $uptime = $now - $startTs;

        // 2) Health-JSON im Plugin-Config-Verzeichnis via Helper schreiben
                $lastEvents = [
            'avtransport'       => $lastAvtEventTs,
            'renderingcontrol'  => $lastRcEventTs,
            'zonegrouptopology' => $lastZgtEventTs,
        ];

        // Health auch in die Plugin-Config schreiben – aber nur, wenn Helper-Funktion existiert
        if (function_exists('update_sonos_health')) {
            update_sonos_health($allRooms, $onlineRooms, $lastEvents);
        } else {
            logln('warn', 'update_sonos_health() not found – skipping config health update');
        }

        // 3) MQTT-Health-Payload NUR Health (ohne eq, ohne verschachteltes "health")
        // flache ROOM_Online-Felder für Loxone
        $roomOnlineFlat = [];
        foreach ($roomsFlags as $roomName => $flagArr) {
            $roomOnlineFlat[$roomName . '_Online'] = (int)($flagArr['Online'] ?? 0);
        }

        $healthPayload = [
            'source'              => 'sonos_event_listener',
            'type'                => 'health',
            'timestamp'           => $now,
            'iso_time'            => date('c', $now),
            'ts_formatted'        => date('[d.m.Y] H:i\h', $now),
			'pid'                 => function_exists('getmypid') ? getmypid() : null,
            'uptime_sec'          => $uptime,
            'last_notify_ts'      => $lastNotifyTs,
            'last_notify_age_sec' => $age,
            'online_players'      => count($onlineRooms),
            'offline_players'     => count($offlineRooms),
            'total_players'       => count($allRooms),
        ] + $roomOnlineFlat;

        $healthJson = json_encode($healthPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($healthJson !== false) {
            // Topic bleibt: s4lox/sonos/_health
            $ok = $mqttClient->publish($healthTopic, $healthJson, true, $MQTT_QOS);
            if (!$ok) {
                logln('warn', "MQTT publish failed: $healthTopic");
            }

            // Health-JSON zusätzlich im RAM-FS für die UI ablegen
            $healthFile = '/dev/shm/sonos4lox/health.json';
            @mkdir(dirname($healthFile), 0775, true);
            $bytes = @file_put_contents($healthFile, $healthJson);

            if ($bytes === false) {
                logln('warn', "Failed to write health.json to $healthFile");
            } else {
                logln('dbg', "Updated health.json ($bytes bytes) at $healthFile");
            }
        } else {
            logln('warn', "JSON encode failed for health payload");
        }

        // 4) EQ-Snapshot je Raum als eigenes Topic: s4lox/sonos/<room>/eq
		if (!empty($lastEqByRoom)) {
			foreach ($lastEqByRoom as $roomName => $eqData) {
				$eqTopic = rtrim($TOPIC_PREFIX, '/') . '/' . $roomName . '/eq';

				$eqPayload = [
					'source'    => 'sonos_event_listener',
					'type'      => 'eq',
					'room'      => $roomName,
					'timestamp' => $now,
					'iso_time'  => date('c', $now),
				] + $eqData;

				$eqJson = json_encode($eqPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if ($eqJson !== false) {
					$ok = $mqttClient->publish($eqTopic, $eqJson, true, $MQTT_QOS);
					if (!$ok) {
						logln('warn', "MQTT publish failed: $eqTopic");
					}
				} else {
					logln('warn', "JSON encode failed for eq payload (room $roomName)");
				}
			}
		}

        // 5) Warnen, wenn schon lange kein NOTIFY mehr
        if ($age > $NO_NOTIFY_WARN_AFTER) {
            logln(
                'warn',
                sprintf(
                    "No NOTIFY received since %d seconds (last at %s)",
                    $age,
                    date('Y-m-d H:i:s', $lastNotifyTs)
                )
            );

            // → einmal kompletten Resubscribe anstoßen
            rebuild_all_subscriptions(
                $subs,
                $rooms,
                $CALLBACK_URL,
                $TIMEOUT_SEC,
                $RENEW_MARGIN
            );
        }
    }

    // --------------------------------- SUBS erneuern ---------------------------------
    foreach ($subs as $sid => &$s) {
        if ($now >= $s['renewAt']) {
            $host = parse_url($s['eventUrl'], PHP_URL_HOST);
            $port = parse_url($s['eventUrl'], PHP_URL_PORT);

            [$resH, ] = http_req('SUBSCRIBE', $s['eventUrl'], [
                "HOST: $host:$port",
                "SID: " . $sid,
                "TIMEOUT: Second-{$TIMEOUT_SEC}",
                "Connection: close",
            ]);

            // HTTP-Status aus erster Headerzeile extrahieren
            $statusLine = $resH[0] ?? '';
            $isOk       = false;
            if ($statusLine !== '' && preg_match('~HTTP/\d\.\d\s+(\d+)~', $statusLine, $mm)) {
                $code = (int)$mm[1];
                if ($code >= 200 && $code < 300) {
                    $isOk = true;
                }
            }

            if (!$isOk) {
                logln(
                    'warn',
                    "RENEW {$s['service']} @ {$s['room']} FAILED (SID $sid, status='{$statusLine}') → rebuilding all subscriptions"
                );

                // Bei RENEW-Fehlern nicht weiter rumdoktern, sondern einmal hart alles neu aufbauen
                rebuild_all_subscriptions(
                    $subs,
                    $rooms,
                    $CALLBACK_URL,
                    $TIMEOUT_SEC,
                    $RENEW_MARGIN
                );
                // Da rebuild_all_subscriptions $subs komplett neu setzt, kann der foreach abgebrochen werden
                break;
            }

            // Nur bei erfolgreichem 2xx-RESPONSE TTL aktualisieren
            $tout = header_value($resH, 'TIMEOUT') ?: header_value($resH, 'Timeout');
            $ttl  = (preg_match('~Second-(\d+)~i', (string)$tout, $m) ? (int)$m[1] : $TIMEOUT_SEC);
            $s['renewAt'] = time() + max(60, $ttl) - $RENEW_MARGIN;
            logln('ok', "RENEW {$s['service']} @ {$s['room']} (SID $sid)");
            usleep(100000);
        }
    }
    unset($s);
}
