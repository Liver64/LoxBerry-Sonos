#!/usr/bin/php
<?php
declare(strict_types=1);

/**
 * Sonos Event Listener (raum-zentriert, vollständig)
 * Version: EVENT_HANDLER_LOXONE_EMPTY_TEXT_CLEAR_V13_2026_07_18
 * - MQTT CLI fallback reads persisted LoxBerry MQTT credentials directly
 * - powered-off rooms are subscribed automatically after they become reachable
 * - legacy radio is latched while PLAYING; empty follow-up metadata cannot erase the station
 * - legacy artist/int is cleared reliably for PAUSED and STOPPED
 * - modern s4lox track metadata follows the same PLAYING/PAUSED/STOPPED lifecycle
 * - modern s4lox nexttrack and track titles are cleared for PAUSED/STOPPED and cannot be restored by stale metadata
 * - source classification combines CurrentURI/TrackURI with GetZoneInfo HTAudioIn
 * - TV is active only when x-sonos-htastream is present and HTAudioIn > 21
 * - active TV clears retained radio fields and nexttrack_title in both topic lifecycles
 * - direct s4lox HTTP text clears use an explicit encoded blank value instead of a missing URL value
 * - liest Räume/Player aus /opt/loxberry/config/plugins/sonos4lox/s4lox_config.json
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
 *     - source_text ("Radio"|"Playlist/Stream"|"TV"|"LineIn"|"Nothing")
 *     - source      (int) 0=Nothing,1=Radio,2=Playlist/Stream,3=TV,4=LineIn
 *     - tit         (Titel)
 *     - int         (Interpret)
 *     - titint      ("Artist - Title")
 *     - radio       (Sendername, wenn Radio)
 *     - sid         ("TV"|"Radio"|"Music")
 *     - cover       (AlbumArtUri)
 *     - tvstate     (1 only for x-sonos-htastream + HTAudioIn > 21, otherwise 0)
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
 *
 * VOLUME FIX:
 * - Gruppen-Koordinatoren verwenden GroupVolume (die "echte" Gruppenlautstärke)
 * - Members verwenden ihre individuelle Volume
 */

require_once "/opt/loxberry/libs/phplib/loxberry_system.php";
require_once "/opt/loxberry/libs/phplib/loxberry_io.php";
require_once $lbphtmldir . "/src/Core/Mqtt/SonosMqttClient.php";
require_once $lbphtmldir . "/src/Core/Sonos/sonosAccess.php";

date_default_timezone_set('Europe/Berlin');

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}

const S4L_EVENTHANDLER_CONTEXT = 'src/Core/Event/EventHandler.php';

// --------------------------------- Pfade ---------------------------------
const S4L_CFG     = "/opt/loxberry/config/plugins/sonos4lox/s4lox_config.json";
$ramLogDir        = '/run/shm/sonos4lox';
$LogFile          = 'sonos_events.log';
$loglevel 	  	  = LBSystem::pluginloglevel();
$presenceEnabled  = null;

// --------------------------------- UDP memory send globals ---------------------------------
#$mem_sendall_sec  = 300;
#$mem_sendall      = 0;
#$udpsocket        = null;

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
#$lastHealthPublish    = 0;
$NO_NOTIFY_WARN_AFTER = 600;     // 10 Minuten
$HEALTH_INTERVAL      = 60;      // 1 Minute
$lastHealthPublish    = time() - $HEALTH_INTERVAL;
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

    // 1) Logfile rotation – always check, regardless of debug level.
    if (!empty($LOGFILE) && is_file($LOGFILE) && filesize($LOGFILE) >= $LOG_MAX_BYTES) {
        @unlink($LOGFILE);
    }

    // 2) Keep this daemon log local to sonos_events.log and journalctl.
    //    Do not route all event traffic through the general Sonos4Lox LBLog.

    $msg = trim($msg);
    if (strpos($msg, S4L_EVENTHANDLER_CONTEXT . ':') !== 0) {
        $msg = S4L_EVENTHANDLER_CONTEXT . ': ' . $msg;
    }

    // 3) Write line.
    $line = sprintf("[%s] %-5s %s
", date('Y-m-d H:i:s'), strtoupper($lvl), $msg);

    if (!empty($LOGFILE)) {
        @file_put_contents($LOGFILE, $line, FILE_APPEND | LOCK_EX);
    }

    // Always also write to STDOUT for journalctl.
    echo $line;
}

// --------------------------------- Runtime safety ---------------------------------
if (!function_exists('s4lox_eventhandler_register_runtime_safety')) {
    /**
     * Register fatal/exception logging for the systemd listener context.
     * This keeps future PHP fatals visible in sonos_events.log and journalctl.
     */
    function s4lox_eventhandler_register_runtime_safety(): void
    {
        set_exception_handler(function (Throwable $e): void {
            logln(
                'error',
                'Uncaught exception: ' . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine()
            );
            exit(255);
        });

        register_shutdown_function(function (): void {
            $err = error_get_last();
            if (!is_array($err)) {
                return;
            }

            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array((int)($err['type'] ?? 0), $fatalTypes, true)) {
                return;
            }

            logln(
                'error',
                'Fatal shutdown: ' . (string)($err['message'] ?? 'unknown fatal error')
                . ' in ' . (string)($err['file'] ?? 'unknown') . ':' . (string)($err['line'] ?? '0')
            );
        });
    }
}

s4lox_eventhandler_register_runtime_safety();

// --------------------------------- Helpers ---------------------------------

require_once $lbphtmldir . "/Helper.php";

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
            $meta['events'] = default_event_urls($base);
			logln('warn', "Event URL refresh: still no device_description.xml for {$meta['ip']} ($room) – using default event URLs");
			continue;
        }

        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($desc);
        if (!$sx) {
            logln('warn', "Event URL refresh: XML parse failed for {$meta['ip']} ($room) – keeping room without events");
            $meta['events'] = default_event_urls($base);
			logln('warn', "Event URL refresh: XML parse failed for {$meta['ip']} ($room) – using default event URLs");
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
            $meta['events'] = default_event_urls($base);
			logln('warn', "Event URL refresh: {$meta['ip']} ($room): no event URLs found – using default event URLs");
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

function default_event_urls(string $base): array
{
    $b = rtrim($base, '/');
    return [
        'AVTransport'           => $b . '/MediaRenderer/AVTransport/Event',
        'RenderingControl'      => $b . '/MediaRenderer/RenderingControl/Event',
        'GroupRenderingControl' => $b . '/MediaRenderer/GroupRenderingControl/Event',
        'ZoneGroupTopology'     => $b . '/ZoneGroupTopology/Event',
    ];
}

function udp_key_name(string $room, string $name): string
{
    $room = strtolower(trim($room));
    $room = preg_replace('/[^a-z0-9_]+/i', '_', $room);
    $room = trim((string)$room, '_');

    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9_]+/i', '_', $name);
    $name = trim((string)$name, '_');

    return $room . '_' . $name;
}

function udp_plain_name(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9_]+/i', '_', $name);
    return trim((string)$name, '_');
}

function udp_value($value, int $maxLen = 120): string
{
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    } elseif ($value === null) {
        $value = '';
    }

    $value = trim((string)$value);
    $value = preg_replace('/[\r\n\t]+/', ' ', $value);

    if (strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }

    return $value;
}

function udp_publish_for_event(string $type, array $payload, string $msnr, int $udpport, string $prefix): void
{
    if ($msnr === '' || $udpport < 1 || $udpport > 65535) {
        return;
    }

    $params = [];

    if ($type === 'health') {
        if (isset($payload['online_players'])) {
            $params['online_players'] = (string)(int)$payload['online_players'];
        }
        if (isset($payload['offline_players'])) {
            $params['offline_players'] = (string)(int)$payload['offline_players'];
        }
        if (isset($payload['total_players'])) {
            $params['total_players'] = (string)(int)$payload['total_players'];
        }

        foreach ($payload as $k => $v) {
            if (preg_match('/_online$/i', (string)$k)) {
                $params[udp_plain_name((string)$k)] = (string)(int)$v;
            }
        }
    } else {
        $room = strtolower((string)($payload['room'] ?? ''));
        if ($room === '') {
            return;
        }

        switch ($type) {
            case 'state':
                if (isset($payload['state_code'])) {
                    $params[udp_key_name($room, 'state_code')] = (string)(int)$payload['state_code'];
                }
                break;

            case 'volume':
                if (isset($payload['volume'])) {
                    $params[udp_key_name($room, 'volume')] = (string)(int)$payload['volume'];
                }
                break;

            case 'mute':
                if (isset($payload['mute_int'])) {
                    $params[udp_key_name($room, 'mute')] = (string)(int)$payload['mute_int'];
                }
                break;

            case 'group':
                if (isset($payload['role_code'])) {
                    $params[udp_key_name($room, 'group_role')] = (string)(int)$payload['role_code'];
                }
                break;

            case 'track':
                if (isset($payload['source'])) {
                    $params[udp_key_name($room, 'current_source')] = (string)(int)$payload['source'];
                }
                if (isset($payload['tvstate'])) {
                    $params[udp_key_name($room, 'tvstate')] = (string)(int)$payload['tvstate'];
                }
                break;

            case 'nexttrack':
                // intentionally no UDP output: no numeric values needed
                break;

            case 'eq':
                if (isset($payload['bass'])) {
                    $params[udp_key_name($room, 'bass')] = (string)(int)$payload['bass'];
                }
                if (isset($payload['treble'])) {
                    $params[udp_key_name($room, 'treble')] = (string)(int)$payload['treble'];
                }
                if (isset($payload['loudness_int'])) {
                    $params[udp_key_name($room, 'loudness')] = (string)(int)$payload['loudness_int'];
                }
                if (isset($payload['balance'])) {
                    $params[udp_key_name($room, 'balance')] = (string)(int)$payload['balance'];
                }
                if (isset($payload['subgain'])) {
                    $params[udp_key_name($room, 'subgain')] = (string)(int)$payload['subgain'];
                }
                if (isset($payload['nightmode'])) {
                    $params[udp_key_name($room, 'nightmode')] = (string)(int)$payload['nightmode'];
                }
                if (isset($payload['dialoglevel'])) {
                    $params[udp_key_name($room, 'dialoglevel')] = (string)(int)$payload['dialoglevel'];
                }
                break;

            case 'playmode':
                if (isset($payload['shuffle'])) {
                    $params[udp_key_name($room, 'shuffle')] = (string)(int)$payload['shuffle'];
                }
                if (isset($payload['repeat'])) {
                    $params[udp_key_name($room, 'repeat')] = (string)(int)$payload['repeat'];
                }
                if (isset($payload['repeat_one'])) {
                    $params[udp_key_name($room, 'repeat_one')] = (string)(int)$payload['repeat_one'];
                }
                if (isset($payload['crossfade'])) {
                    $params[udp_key_name($room, 'crossfade')] = (string)(int)$payload['crossfade'];
                }
                if (isset($payload['code'])) {
                    $params[udp_key_name($room, 'playmode_code')] = (string)(int)$payload['code'];
                }
                break;

            case 'position':
                if (isset($payload['position_sec'])) {
                    $params[udp_key_name($room, 'position_sec')] = (string)(int)$payload['position_sec'];
                }
                if (isset($payload['duration_sec'])) {
                    $params[udp_key_name($room, 'duration_sec')] = (string)(int)$payload['duration_sec'];
                }
                if (isset($payload['progress_pct'])) {
                    $params[udp_key_name($room, 'progress_pct')] = (string)(int)$payload['progress_pct'];
                }
                if (isset($payload['track_no'])) {
                    $params[udp_key_name($room, 'track_no')] = (string)(int)$payload['track_no'];
                }
                if (isset($payload['track_count'])) {
                    $params[udp_key_name($room, 'track_count')] = (string)(int)$payload['track_count'];
                }
                break;
        }
    }

    if (empty($params)) {
        return;
    }

    foreach ($params as $param => $value) {
        udp_send_single_value($msnr, $udpport, $prefix, $param, $value);
    }
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

    $path  = '/opt/loxberry/config/system/general.json';
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

        // Loxone interprets /dev/sps/io/<input>/ without a value as a read.
        // Only explicitly marked text-clear rules therefore transmit one blank
        // character (%20) when their logical value is empty. This remains
        // deliberately opt-in so album, cover, numeric and ordinary metadata
        // publishing keep their previous behavior unchanged.
        $isExplicitTextClear = ($value === '' && !empty($rule['blank_on_empty']));
        $wireValue = $isExplicitTextClear ? ' ' : $value;

        // Dedup by the value that is actually sent to the Miniserver.
        $key = $input;
        if (isset($lastSent[$key]) && $lastSent[$key] === $wireValue) {
            continue;
        }
        $lastSent[$key] = $wireValue;

        $ok = loxone_http_set_io($loxTarget, $input, $wireValue, 3);
        if (!$ok) {
            logln('warn', "Loxone HTTP set failed: input='$input'");
        } elseif ($isExplicitTextClear) {
            logln('dbg', "Loxone HTTP text clear sent: input='$input'");
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
// Source-Erkennung (TV / Radio / Playlist/Stream / LineIn / Nothing)
// -------------------------------------------------------------

/**
 * Return the first non-empty Sonos value for one of the supplied keys.
 * SonosAccess versions use slightly different names/casing.
 */
function sonos_source_value(array $data, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            $value = trim((string)$data[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

/**
 * Normalize URI/metadata text for deterministic source matching.
 */
function sonos_source_normalize(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return strtolower(trim($value));
}

/**
 * Test whether any supplied URI starts with one of the known Sonos schemes.
 */
function sonos_source_has_prefix(array $uris, array $prefixes): bool
{
    foreach ($uris as $uri) {
        $uri = sonos_source_normalize((string)$uri);
        if ($uri === '') {
            continue;
        }
        foreach ($prefixes as $prefix) {
            if (strpos($uri, strtolower($prefix)) === 0) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Classify the active Sonos source using both transport-level information
 * (GetMediaInfo/CurrentURI) and item-level information
 * (GetPositionInfo/TrackURI + UPnP class).
 *
 * Priority is intentional:
 *   1. TV
 *   2. Radio
 *   3. Line-In
 *   4. Playlist/Stream
 *   5. Nothing
 */
function classify_source_from_sonos(array $posinfo, array $mediainfo, ?int $htAudioIn = null): string
{
    $transportUri = sonos_source_value($mediainfo, [
        'CurrentURI', 'AVTransportURI', 'URI', 'current_uri', 'av_transport_uri'
    ]);
    $trackUri = sonos_source_value($posinfo, [
        'TrackURI', 'CurrentTrackURI', 'URI', 'track_uri', 'current_track_uri'
    ]);

    $mediaClass = sonos_source_normalize(sonos_source_value($mediainfo, [
        'UpnpClass', 'UPnPClass', 'upnpClass', 'upnp_class'
    ]));
    $trackClass = sonos_source_normalize(sonos_source_value($posinfo, [
        'UpnpClass', 'UPnPClass', 'upnpClass', 'upnp_class'
    ]));
    $protocol = sonos_source_normalize(sonos_source_value($posinfo, [
        'ProtocolInfo', 'protocolInfo', 'protocol_info'
    ]));

    $mediaMeta = sonos_source_normalize(sonos_source_value($mediainfo, [
        'CurrentURIMetaData', 'AVTransportURIMetaData', 'current_uri_metadata'
    ]));
    $trackMeta = sonos_source_normalize(sonos_source_value($posinfo, [
        'CurrentURIMetaData', 'TrackMetaData', 'current_uri_metadata', 'track_metadata'
    ]));

    $uris = [$transportUri, $trackUri];
    $hasHtStream = sonos_source_has_prefix($uris, ['x-sonos-htastream:']) ||
        strpos(implode(' ', [$mediaMeta, $trackMeta]), 'x-sonos-htastream:') !== false;

    // A running TV requires both indicators. Sonos can leave x-sonos-htastream
    // selected after the TV has gone off, therefore the URI alone is not enough.
    if ($hasHtStream && $htAudioIn !== null && $htAudioIn > 21) {
        return 'TV';
    }

    // Ignore a stale HT stream for all following source checks. A second real
    // URI or music metadata can still identify Playlist/Stream playback.
    $nonHtUris = [];
    foreach ($uris as $uri) {
        $normalized = sonos_source_normalize((string)$uri);
        if ($normalized !== '' && strpos($normalized, 'x-sonos-htastream:') !== 0) {
            $nonHtUris[] = $uri;
        }
    }

    // Radio. CurrentURI is the authoritative discriminator for Sonos Radio,
    // TuneIn and other radio services. Item-level audioBroadcast remains a
    // fallback for direct MP3/AAC radio streams.
    if (
        sonos_source_has_prefix($nonHtUris, [
            'x-sonosapi-radio:',
            'x-sonosapi-stream:',
            'x-rincon-mp3radio:',
            'x-rincon-webstream:',
        ]) ||
        strpos($mediaClass, 'object.item.audioitem.audiobroadcast') === 0 ||
        strpos($trackClass, 'object.item.audioitem.audiobroadcast') === 0 ||
        strpos($protocol, 'x-rincon-mp3radio') !== false
    ) {
        return 'Radio';
    }

    // Physical/digital line input keeps its existing compatibility code 4.
    if (sonos_source_has_prefix($nonHtUris, ['x-rincon-stream:'])) {
        return 'LineIn';
    }

    // Queue, Sonos playlist, container, saved queue, music-service track,
    // file and ordinary HTTP/HLS playback all belong to Playlist/Stream (code 2).
    if (
        sonos_source_has_prefix($nonHtUris, [
            'x-rincon-queue:',
            'x-rincon-playlist:',
            'x-rincon-cpcontainer:',
            'file:///jffs/settings/savedqueues.rsq',
            'x-sonosapi-hls-static:',
            'x-sonos-http:',
            'x-file-cifs:',
            'http://',
            'https://',
        ]) ||
        strpos($trackClass, 'object.item.audioitem.musictrack') === 0 ||
        strpos($mediaClass, 'object.container') === 0 ||
        strpos($mediaClass, 'object.item.audioitem.musictrack') === 0
    ) {
        return 'Playlist/Stream';
    }

    // A lone stale x-sonos-htastream with no recognized playable item means
    // TV off, not streaming. This covers HTAudioIn=0 and the 1..21 transition range.
    if ($hasHtStream && empty($nonHtUris)) {
        return 'Nothing';
    }

    // A non-HT URI or ordinary item metadata indicates streaming.
    if (!empty($nonHtUris) || $mediaClass !== '' || $trackClass !== '' || $protocol !== '') {
        return 'Playlist/Stream';
    }

    return 'Nothing';
}

// Backward-compatible wrapper for any external/local call that still passes
// only GetPositionInfo data.
function classify_source_from_posinfo(array $posinfo): string
{
    return classify_source_from_sonos($posinfo, [], null);
}

// Cache für SonosAccess-Objekte pro Raum
$__sonos_clients = [];

/**
 * Liefert 'source' (TV/Radio/LineIn/Track/Nothing) für einen Raum.
 * Holt sich intern über SonosAccess->GetPositionInfo() die Daten.
 */
function get_source_state_for_room(string $room, array $rooms): ?array
{
    global $__sonos_clients;

    if (empty($rooms[$room]['ip'])) {
        return null;
    }
    $ip = $rooms[$room]['ip'];

    try {
        if (!isset($__sonos_clients[$room])) {
            $__sonos_clients[$room] = new SonosAccess($ip);
        }
        $sonos = $__sonos_clients[$room];

        $posinfo = [];
        $mediainfo = [];
        $zoneinfo = [];

        try {
            $pos = $sonos->GetPositionInfo();
            if (is_array($pos)) {
                $posinfo = $pos;
            }
        } catch (Throwable $e) {
            logln('warn', "get_source_state_for_room($room): GetPositionInfo failed: " . $e->getMessage());
        }

        try {
            $media = $sonos->GetMediaInfo();
            if (is_array($media)) {
                $mediainfo = $media;
            }
        } catch (Throwable $e) {
            logln('warn', "get_source_state_for_room($room): GetMediaInfo failed: " . $e->getMessage());
        }

        try {
            $zone = $sonos->GetZoneInfo();
            if (is_array($zone)) {
                $zoneinfo = $zone;
            }
        } catch (Throwable $e) {
            logln('warn', "get_source_state_for_room($room): GetZoneInfo failed: " . $e->getMessage());
        }

        if (empty($posinfo) && empty($mediainfo) && empty($zoneinfo)) {
            return null;
        }

        $htAudioIn = null;
        foreach (['HTAudioIn', 'HTAUDIOIN', 'htAudioIn', 'htaudioin'] as $key) {
            if (array_key_exists($key, $zoneinfo) && is_numeric($zoneinfo[$key])) {
                $htAudioIn = (int)$zoneinfo[$key];
                break;
            }
        }

        $source = classify_source_from_sonos($posinfo, $mediainfo, $htAudioIn);
        $tvstate = ($source === 'TV' && $htAudioIn !== null && $htAudioIn > 21) ? 1 : 0;

        logln('dbg', "$room source=$source HTAudioIn=" . ($htAudioIn === null ? 'unknown' : (string)$htAudioIn) . " tvstate=$tvstate");

        return [
            'source' => $source,
            'tvstate' => $tvstate,
            'htaudioin' => $htAudioIn,
        ];
    } catch (Throwable $e) {
        logln('warn', "get_source_state_for_room($room): " . $e->getMessage());
        return null;
    }
}

function get_source_for_room(string $room, array $rooms): ?string
{
    $state = get_source_state_for_room($room, $rooms);
    return is_array($state) ? (string)($state['source'] ?? '') : null;
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
    array &$rooms,
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


/**
 * Retry only rooms that currently have no active subscription.
 * A single device-description probe prevents four long SUBSCRIBE timeouts while
 * a physically powered-off Sonos player is unavailable.
 */
function retry_missing_room_subscriptions(
    array &$subs,
    array &$rooms,
    string $CALLBACK_URL,
    int $TIMEOUT_SEC,
    int $RENEW_MARGIN
): void {
    $services = ['AVTransport', 'RenderingControl', 'GroupRenderingControl', 'ZoneGroupTopology'];
    $subscribed = [];

    foreach ($subs as $s) {
        $room = (string)($s['room'] ?? '');
        $svc  = (string)($s['service'] ?? '');
        if ($room !== '' && $svc !== '') {
            $subscribed[$room][$svc] = true;
        }
    }

    foreach ($rooms as $room => &$meta) {
        $missing = [];
        foreach ($services as $svc) {
            if (empty($subscribed[$room][$svc])) {
                $missing[] = $svc;
            }
        }

        if (empty($missing)) {
            continue;
        }

        $base = rtrim((string)($meta['base'] ?? ''), '/');
        if ($base === '') {
            continue;
        }

        // One cheap reachability/provisioning probe per missing room.
        $desc = http_get($base . '/xml/device_description.xml', 3);
        if ($desc === null) {
            continue;
        }

        // Refresh event URLs when the player has returned after being powered off.
        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($desc);
        if ($sx) {
            $sx->registerXPathNamespace('d', 'urn:schemas-upnp-org:device-1-0');
            $evs = [];
            foreach (($sx->xpath('//d:serviceList/d:service') ?: []) as $svcNode) {
                $serviceId = (string)$svcNode->serviceId;
                $eventPath = (string)$svcNode->eventSubURL;
                if ($eventPath === '') {
                    continue;
                }

                $full = (strpos($eventPath, 'http') === 0)
                    ? $eventPath
                    : $base . '/' . ltrim($eventPath, '/');

                if (stripos($serviceId, 'AVTransport') !== false) {
                    $evs['AVTransport'] = $full;
                } elseif (stripos($serviceId, 'GroupRenderingControl') !== false) {
                    $evs['GroupRenderingControl'] = $full;
                } elseif (stripos($serviceId, 'RenderingControl') !== false) {
                    $evs['RenderingControl'] = $full;
                } elseif (stripos($serviceId, 'ZoneGroupTopology') !== false) {
                    $evs['ZoneGroupTopology'] = $full;
                }
            }
            if (!empty($evs)) {
                $meta['events'] = $evs;
            }
        }

        $added = 0;
        foreach ($missing as $svc) {
            $evUrl = (string)($meta['events'][$svc] ?? '');
            if ($evUrl === '') {
                continue;
            }

            $host = parse_url($evUrl, PHP_URL_HOST);
            $port = parse_url($evUrl, PHP_URL_PORT) ?: 1400;
            [$resH, ] = http_req('SUBSCRIBE', $evUrl, [
                "HOST: $host:$port",
                "CALLBACK: <{$CALLBACK_URL}>",
                'NT: upnp:event',
                "TIMEOUT: Second-{$TIMEOUT_SEC}",
                'Connection: close',
            ]);

            $sid  = header_value($resH, 'SID') ?: header_value($resH, 'Sid');
            $tout = header_value($resH, 'TIMEOUT') ?: header_value($resH, 'Timeout');
            if (!$sid) {
                continue;
            }

            $ttl = (preg_match('~Second-(\d+)~i', (string)$tout, $m) ? (int)$m[1] : $TIMEOUT_SEC);
            $subs[$sid] = [
                'service'  => $svc,
                'eventUrl' => $evUrl,
                'room'     => $room,
                'renewAt'  => time() + max(60, $ttl) - $RENEW_MARGIN,
            ];
            $subscribed[$room][$svc] = true;
            $added++;
            logln('ok', "SUBSCRIBE $svc @ {$meta['ip']} ($room) -> SID=$sid (late recovery)");
            usleep(100000);
        }

        if ($added > 0) {
            $remaining = 0;
            foreach ($services as $svc) {
                if (empty($subscribed[$room][$svc])) {
                    $remaining++;
                }
            }
            if ($remaining === 0) {
                logln('ok', "Previously unavailable Sonos room '$room' is now fully subscribed");
            } else {
                logln('info', "Sonos room '$room' recovered partially; {$remaining} subscription(s) still missing");
            }
        }
    }
    unset($meta);
}


// ------- Publish Helper: raum-zentriert & vollständige Payload -------
// global cache to deduplicate group events: room|group_id => signature
$lastGroupState 	= [];
$lastEqByRoom 		= [];
// Last normalized transport state per room. Used to suppress stale metadata
// that Sonos may append to the same LastChange after PAUSED/STOPPED.
$lastTransportStateByRoom = [];
// Last non-empty radio station per room. While state=PLAYING this value is
// latched so a delayed/partial Sonos metadata event cannot erase it again.
$lastRadioStationByRoom = [];
// Last retained modern s4lox track payload per room. Used to publish immediate
// metadata clears on PAUSED/STOPPED and a stable radio station on PLAYING.
$lastTrackPayloadByRoom = [];
// Last retained modern s4lox nexttrack payload per room. Used to clear the
// flattened nexttrack_title value immediately on PAUSED/STOPPED or active TV.
$lastNextTrackPayloadByRoom = [];
// Last authoritative source per room. This guards delayed nexttrack events:
// while TV is active, stale Sonos metadata must not restore nexttrack_title.
$lastSourceByRoom = [];
// group cache: room => ['group_id'=>..., 'members'=>[...], 'is_coordinator'=>0/1, 'ts'=>...]
$groupByRoom 		= [];


/**
 * Clear only the retained modern nexttrack title for one room.
 *
 * This helper intentionally leaves artist/album/cover/URI untouched. It is used
 * for PAUSED/STOPPED and for an actively detected TV source, where Sonos can
 * otherwise leave a stale queue title in the retained nexttrack JSON.
 */
function clear_modern_nexttrack_title_for_room(
    string $room,
    array $meta,
    ?SonosMqttClient $mqttClient,
    string $prefix,
    int $qos,
    ?array $loxTarget,
    array $loxoneMap,
    string $reason
): void {
    global $lastNextTrackPayloadByRoom;

    if (!$mqttClient) {
        return;
    }

    $room = strtolower(trim($room));
    if ($room === '') {
        return;
    }

    $topic = rtrim($prefix, '/') . '/' . $room . '/nexttrack';
    $payload = $lastNextTrackPayloadByRoom[$room] ?? [
        'type'    => 'nexttrack',
        'room'    => $room,
        'ip'      => $meta['ip'] ?? '',
        'rincon'  => $meta['rincon'] ?? '',
        'model'   => $meta['model'] ?? '',
        'service' => 'AVTransport',
    ];

    $alreadyEmpty = array_key_exists('title', $payload)
        && trim((string)$payload['title']) === '';

    $payload['type'] = 'nexttrack';
    $payload['room'] = $room;
    $payload['ip'] = $meta['ip'] ?? ($payload['ip'] ?? '');
    $payload['rincon'] = $meta['rincon'] ?? ($payload['rincon'] ?? '');
    $payload['model'] = $meta['model'] ?? ($payload['model'] ?? '');
    $payload['service'] = $payload['service'] ?? 'AVTransport';
    $payload['title'] = '';
    $payload['ts'] = time();

    // On first use after a daemon restart we must publish even without a cache,
    // because an old retained nexttrack title may still exist in MQTT.
    if (!$alreadyEmpty || !isset($lastNextTrackPayloadByRoom[$room])) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            logln('warn', "JSON encode failed for $room/nexttrack clear ($reason)");
        } elseif ($mqttClient->publish($topic, $json, true, $qos)) {
            $lastNextTrackPayloadByRoom[$room] = $payload;
            logln('dbg', "$room modern s4lox nexttrack title cleared ($reason)");
        } else {
            logln('warn', "MQTT publish failed: $topic (nexttrack clear: $reason)");
        }
    } else {
        // Keep metadata fresh in memory without generating identical MQTT traffic.
        $lastNextTrackPayloadByRoom[$room] = $payload;
    }

    // The HTTP publisher deduplicates identical values itself.
    loxone_publish_for_event('nexttrack_state_clear', $payload, $loxTarget, $loxoneMap);
}



function publish_room_event(
    string $room,
    string $type,
    array $data,
    array $rooms,
    ?SonosMqttClient $mqttClient,
    string $prefix,
    string $service,
    int $qos
): void {

    global $lastEqByRoom, $groupByRoom, $lastTransportStateByRoom, $lastRadioStationByRoom, $lastTrackPayloadByRoom, $lastNextTrackPayloadByRoom, $lastSourceByRoom,
           $useUdp, $loxMsId, $LoxoneUDPPort, $LoxoneUDPPrefix, $loxTarget, $LOXONE_HTTP_PUBLISH;

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
        $lastTransportStateByRoom[$room] = (int)$data['state_code'];
    }

    // --- Volume: einfach direkt publishen (GroupVolume wird separat ignoriert) ---
    if ($type === 'volume') {
        $vol = (int)($data['volume'] ?? 0);
        $data['volume'] = $vol;
        $data['vol']    = $vol;  // Alias
        // volume_src falls vorhanden entfernen (nicht mehr nötig)
        unset($data['volume_src']);
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
        // Source und echten TV-Audiozustand gemeinsam ermitteln.
        $sourceState = get_source_state_for_room($room, $rooms);
        $src = is_array($sourceState) ? (string)($sourceState['source'] ?? '') : null;
        $tvstate = is_array($sourceState) ? (int)($sourceState['tvstate'] ?? 0) : 0;
        if ($src !== null && $src !== '') {
            $data['source_text'] = $src;
            $map = [
                'Nothing'         => 0,
                'Radio'           => 1,
                'Playlist/Stream' => 2,
                'Track'           => 2, // compatibility fallback
                'TV'              => 3,
                'LineIn'          => 4,
            ];
            $data['source'] = $map[$src] ?? 0; // wie früher source_<room>
            $lastSourceByRoom[$room] = $src;
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

			// 1) Sender bevorzugt direkt aus dem Event. Falls Sonos dort keinen
			//    Sender liefert, CurrentURIMetaData aktiv per GetMediaInfo lesen.
			$station = trim((string)($data['radioStationName'] ?? ''));
			if ($station === '') {
				$station = trim((string)(get_radio_station_for_room($room, $rooms) ?? ''));
			}

			// Sonos sends partial follow-up metadata shortly after PLAYING. If that
			// packet has no station name, retain the last valid station while state=1.
			$cachedState = (int)($lastTransportStateByRoom[$room] ?? 0);
			if ($station !== '') {
				$lastRadioStationByRoom[$room] = $station;
			} elseif ($cachedState === 1 && !empty($lastRadioStationByRoom[$room])) {
				$station = (string)$lastRadioStationByRoom[$room];
			}

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
			$data['streamContent'] = $song;          // NowPlaying

		} else {
			$data['radio'] = '';
		}

        // A TV source must never inherit a previously latched radio station.
        // Clear the original Sonos radio field as well as the compatibility alias.
        if (($data['source_text'] ?? '') === 'TV') {
            $data['radio'] = '';
            $data['radioStationName'] = '';
            unset($lastRadioStationByRoom[$room]);
        }

        // Sonos kann im gleichen LastChange nach PAUSED/STOPPED noch die alten
        // CurrentTrackMetaData mitsenden. Diese dürfen die zuvor geleerten
        // Legacy-Anzeigen nicht erneut befüllen.
        $cachedState = (int)($lastTransportStateByRoom[$room] ?? 0);
        if ($cachedState === 2 || $cachedState === 3) {
            // Clear both the compatibility aliases and the original JSON fields.
            // Otherwise a delayed track event could restore flattened values such
            // as s4lox_*_track_title after the state-driven retained clear.
            $data['title']  = '';
            $data['artist'] = '';
            $data['tit']    = '';
            $data['int']    = '';
            $data['titint'] = '';
            $data['radio']  = '';
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

        // True only for an active Sonos home-theatre stream with HTAudioIn > 21.
        $data['tvstate'] = $tvstate;
    }

    // Sonos may append stale next-track metadata after PAUSED/STOPPED.
    // Keep the retained modern nexttrack topic cleared until playback resumes.
    if ($type === 'nexttrack') {
        $cachedState = (int)($lastTransportStateByRoom[$room] ?? 0);
        $cachedSource = (string)($lastSourceByRoom[$room] ?? '');
        if ($cachedState === 2 || $cachedState === 3 || $cachedSource === 'TV') {
            $data['title'] = '';
        }
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

        if ($type === 'track' && !empty($payloadT['source_text'])) {
            $lastSourceByRoom[$tr] = (string)$payloadT['source_text'];
            if ($lastSourceByRoom[$tr] === 'TV') {
                unset($lastRadioStationByRoom[$tr]);
            }
        }

        $topicT = rtrim($prefix, '/') . "/{$tr}/{$type}";

		if (!$mqttClient) {
			logln('warn', "mqttClient is null");
			continue;
		}

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

		// Keep the last modern track snapshot per target room. The state handler
		// uses this to clear/latch the retained s4lox track topic immediately.
		if ($ok && $type === 'track') {
			$lastTrackPayloadByRoom[$tr] = $payloadT;
		}
		if ($ok && $type === 'nexttrack') {
			$lastNextTrackPayloadByRoom[$tr] = $payloadT;
		}

        // Active TV has no meaningful queued "next title". Clear an older
        // retained queue title immediately, even if Sonos sends no nexttrack event.
        if ($type === 'track' && (($payloadT['source_text'] ?? '') === 'TV')) {
            // The modern MQTT JSON already contains radio="". Direct Loxone HTTP
            // needs an explicit text-clear command as well, even if no separate
            // transport-state event accompanies the TV source change.
            loxone_publish_for_event(
                'track_radio_latch',
                ['room' => $tr, 'radio' => ''],
                $loxTarget,
                $LOXONE_HTTP_PUBLISH
            );

            clear_modern_nexttrack_title_for_room(
                $tr,
                $metaT,
                $mqttClient,
                $prefix,
                $qos,
                $loxTarget,
                $LOXONE_HTTP_PUBLISH,
                'TV track'
            );
        }

		if ($useUdp) {
			udp_publish_for_event(
				$type,
				$payloadT,
				$loxMsId,
				$LoxoneUDPPort,
				$LoxoneUDPPrefix
			);
		}

        // ----------------------------------------------------
        // Legacy-Kompatibilität: alte Sonos4lox-Topics weiter bedienen
        // (nur das, was du ohnehin publizierst; für track replizieren wir das genauso)
        // ----------------------------------------------------
        if ($mqttClient) {
            $legacyPrefix = 'Sonos4lox';

            try {
                if ($type === 'volume' && isset($payloadT['volume'])) {
                    $mqttClient->publish($legacyPrefix . '/vol/' . $tr, (string)$payloadT['volume'], true, 0);
                }

                if ($type === 'state' && isset($payloadT['state_code'])) {
                    $stateCode = (int)$payloadT['state_code'];
                    $mqttClient->publish($legacyPrefix . '/stat/' . $tr, (string)$stateCode, true, 0);

                    // Send the numeric s4lox state input first. The final generic
                    // publisher call is deduplicated, so this does not create spam.
                    loxone_publish_for_event('state', $payloadT, $loxTarget, $LOXONE_HTTP_PUBLISH);

                    // Resolve source + real TV audio state together. PLAYING gets
                    // short retries because Sonos may expose URI/HTAudioIn slightly late.
                    $sourceStateNow = null;
                    $sourceNow = null;
                    $tvstateNow = 0;
                    $stationNow = '';
                    $sourceAttempts = ($stateCode === 1) ? 3 : 1;
                    for ($attempt = 1; $attempt <= $sourceAttempts; $attempt++) {
                        $sourceStateNow = get_source_state_for_room($tr, $rooms);
                        if (is_array($sourceStateNow)) {
                            $sourceNow = (string)($sourceStateNow['source'] ?? '');
                            $tvstateNow = (int)($sourceStateNow['tvstate'] ?? 0);
                        }
                        if ($stateCode === 1 && $sourceNow === 'Radio') {
                            $stationNow = trim((string)(get_radio_station_for_room($tr, $rooms) ?? ''));
                            if ($stationNow !== '') {
                                break;
                            }
                        } elseif ($sourceNow !== null && $sourceNow !== '' && $sourceNow !== 'Nothing') {
                            break;
                        }
                        if ($attempt < $sourceAttempts) {
                            usleep(150000);
                        }
                    }

                    $sourceCodeMap = [
                        'Nothing'         => 0,
                        'Radio'           => 1,
                        'Playlist/Stream' => 2,
                        'Track'           => 2,
                        'TV'              => 3,
                        'LineIn'          => 4,
                    ];
                    $sourceCodeNow = ($sourceNow !== null && $sourceNow !== '')
                        ? (int)($sourceCodeMap[$sourceNow] ?? 0)
                        : null;

                    if ($sourceNow !== null && $sourceNow !== '') {
                        $lastSourceByRoom[$tr] = $sourceNow;
                    }

                    // A detected TV source is authoritative for the display lifecycle:
                    // remove any station latched by the previously playing radio and
                    // clear an old retained queue title immediately.
                    if ($sourceNow === 'TV') {
                        unset($lastRadioStationByRoom[$tr]);
                        $mqttClient->publish($legacyPrefix . '/radio/' . $tr, '', true, 0);

                        // Keep the direct Loxone input aligned with both MQTT topic
                        // families even when no separate track event follows.
                        loxone_publish_for_event(
                            'track_radio_latch',
                            ['room' => $tr, 'radio' => ''],
                            $loxTarget,
                            $LOXONE_HTTP_PUBLISH
                        );

                        clear_modern_nexttrack_title_for_room(
                            $tr,
                            $metaT,
                            $mqttClient,
                            $prefix,
                            $qos,
                            $loxTarget,
                            $LOXONE_HTTP_PUBLISH,
                            'TV state'
                        );
                    }

                    // Keep legacy numeric source/TV state synchronized even when no
                    // separate track metadata event follows the transport state event.
                    if ($sourceCodeNow !== null) {
                        $mqttClient->publish($legacyPrefix . '/source/' . $tr, (string)$sourceCodeNow, true, 0);
                    }
                    $mqttClient->publish($legacyPrefix . '/tvstate/' . $tr, (string)$tvstateNow, true, 0);

                    // ------------------------------------------------------------
                    // Modern retained s4lox/sonos/<room>/track lifecycle
                    // ------------------------------------------------------------
                    $modernTrackTopic = rtrim($prefix, '/') . '/' . $tr . '/track';

                    if ($stateCode === 2 || $stateCode === 3) {
                        $modernTrack = $lastTrackPayloadByRoom[$tr] ?? [
                            'type'    => 'track',
                            'room'    => $tr,
                            'ip'      => $metaT['ip'] ?? '',
                            'rincon'  => $metaT['rincon'] ?? '',
                            'model'   => $metaT['model'] ?? '',
                            'service' => 'AVTransport',
                        ];

                        // Same display rules as the legacy topics. Clear both the
                        // compatibility aliases and the underlying metadata fields,
                        // so JSON consumers cannot keep stale title/artist content.
                        foreach (['title','artist','tit','int','titint','radio'] as $field) {
                            $modernTrack[$field] = '';
                        }
                        if ($sourceCodeNow !== null) {
                            $modernTrack['source_text'] = $sourceNow;
                            $modernTrack['source'] = $sourceCodeNow;
                        }
                        $modernTrack['tvstate'] = $tvstateNow;
                        if ($sourceNow === 'TV') {
                            $modernTrack['sid'] = 'TV';
                        } elseif ($sourceNow === 'Radio') {
                            $modernTrack['sid'] = 'Radio';
                        } else {
                            $modernTrack['sid'] = 'Music';
                        }
                        $modernTrack['type'] = 'track';
                        $modernTrack['room'] = $tr;
                        $modernTrack['ip'] = $metaT['ip'] ?? ($modernTrack['ip'] ?? '');
                        $modernTrack['rincon'] = $metaT['rincon'] ?? ($modernTrack['rincon'] ?? '');
                        $modernTrack['model'] = $metaT['model'] ?? ($modernTrack['model'] ?? '');
                        $modernTrack['service'] = $modernTrack['service'] ?? 'AVTransport';
                        $modernTrack['ts'] = time();

                        $modernJson = json_encode($modernTrack, JSON_UNESCAPED_UNICODE);
                        if ($modernJson !== false) {
                            if ($mqttClient->publish($modernTrackTopic, $modernJson, true, $qos)) {
                                $lastTrackPayloadByRoom[$tr] = $modernTrack;
                                logln('dbg', "$tr modern s4lox metadata cleared for state=$stateCode");
                            } else {
                                logln('warn', "MQTT publish failed: $modernTrackTopic (state clear)");
                            }
                        } else {
                            logln('warn', "JSON encode failed for $tr/track state clear");
                        }

                        // Apply the identical lifecycle to Loxone HTTP inputs:
                        // s4lox_<room>_current_title/current_artist/current_titint/current_radio.
                        loxone_publish_for_event('track_state_clear', $modernTrack, $loxTarget, $LOXONE_HTTP_PUBLISH);

                        // Clear the retained modern nexttrack title as well. MQTT Gateway
                        // flattening therefore clears s4lox_*_nexttrack_title immediately.
                        clear_modern_nexttrack_title_for_room(
                            $tr,
                            $metaT,
                            $mqttClient,
                            $prefix,
                            $qos,
                            $loxTarget,
                            $LOXONE_HTTP_PUBLISH,
                            'state=' . $stateCode
                        );

                        // Legacy PAUSED/STOPPED: stale display values immediately clear.
                        $mqttClient->publish($legacyPrefix . '/tit/'    . $tr, '', true, 0);
                        $mqttClient->publish($legacyPrefix . '/int/'    . $tr, '', true, 0);
                        $mqttClient->publish($legacyPrefix . '/titint/' . $tr, '', true, 0);
                        $mqttClient->publish($legacyPrefix . '/radio/'  . $tr, '', true, 0);
                        unset($lastRadioStationByRoom[$tr]);
                        logln('dbg', "$tr legacy metadata cleared for state=$stateCode");
                    } elseif ($stateCode === 1) {
                        // Refresh modern source/tvstate immediately. This is essential
                        // for TV because HTAudioIn can change without a fresh track packet.
                        if ($sourceCodeNow !== null) {
                            $modernTrack = $lastTrackPayloadByRoom[$tr] ?? [
                                'type'    => 'track',
                                'room'    => $tr,
                                'ip'      => $metaT['ip'] ?? '',
                                'rincon'  => $metaT['rincon'] ?? '',
                                'model'   => $metaT['model'] ?? '',
                                'service' => 'AVTransport',
                                'title'   => '',
                                'artist'  => '',
                                'album'   => '',
                                'tit'     => '',
                                'int'     => '',
                                'titint'  => '',
                                'radio'   => '',
                            ];
                            $modernTrack['type'] = 'track';
                            $modernTrack['room'] = $tr;
                            $modernTrack['ip'] = $metaT['ip'] ?? ($modernTrack['ip'] ?? '');
                            $modernTrack['rincon'] = $metaT['rincon'] ?? ($modernTrack['rincon'] ?? '');
                            $modernTrack['model'] = $metaT['model'] ?? ($modernTrack['model'] ?? '');
                            $modernTrack['service'] = $modernTrack['service'] ?? 'AVTransport';
                            $modernTrack['source_text'] = $sourceNow;
                            $modernTrack['source'] = $sourceCodeNow;
                            $modernTrack['tvstate'] = $tvstateNow;
                            if ($sourceNow === 'TV') {
                                $modernTrack['sid'] = 'TV';
                                $modernTrack['radio'] = '';
                                $modernTrack['radioStationName'] = '';
                            } elseif ($sourceNow === 'Radio') {
                                $modernTrack['sid'] = 'Radio';
                            } else {
                                $modernTrack['sid'] = 'Music';
                                $modernTrack['radio'] = '';
                            }
                            $modernTrack['ts'] = time();

                            $sourceJson = json_encode($modernTrack, JSON_UNESCAPED_UNICODE);
                            if ($sourceJson !== false && $mqttClient->publish($modernTrackTopic, $sourceJson, true, $qos)) {
                                $lastTrackPayloadByRoom[$tr] = $modernTrack;
                                logln('dbg', "$tr modern source=$sourceNow tvstate=$tvstateNow latched for PLAYING");
                            }
                        }

                        if ($sourceNow === 'TV') {
                            // TV clearing was already published above. Never fall
                            // through to the PLAYING radio latch with an old station.
                            unset($lastRadioStationByRoom[$tr]);
                        } elseif ($stationNow !== '') {
                            $lastRadioStationByRoom[$tr] = $stationNow;

                            // Publish/refresh the retained modern track snapshot so
                            // the flattened s4lox_* radio value remains available
                            // for the complete PLAYING period.
                            $modernTrack = $lastTrackPayloadByRoom[$tr] ?? [
                                'type'    => 'track',
                                'room'    => $tr,
                                'ip'      => $metaT['ip'] ?? '',
                                'rincon'  => $metaT['rincon'] ?? '',
                                'model'   => $metaT['model'] ?? '',
                                'service' => 'AVTransport',
                                'title'   => '',
                                'artist'  => '',
                                'album'   => '',
                                'tit'     => '',
                                'int'     => '',
                                'titint'  => '',
                            ];
                            $modernTrack['type'] = 'track';
                            $modernTrack['room'] = $tr;
                            $modernTrack['ip'] = $metaT['ip'] ?? ($modernTrack['ip'] ?? '');
                            $modernTrack['rincon'] = $metaT['rincon'] ?? ($modernTrack['rincon'] ?? '');
                            $modernTrack['model'] = $metaT['model'] ?? ($modernTrack['model'] ?? '');
                            $modernTrack['service'] = $modernTrack['service'] ?? 'AVTransport';
                            $modernTrack['source_text'] = 'Radio';
                            $modernTrack['source'] = 1;
                            $modernTrack['radio'] = $stationNow;
                            $modernTrack['sid'] = 'Radio';
                            $modernTrack['ts'] = time();

                            $modernJson = json_encode($modernTrack, JSON_UNESCAPED_UNICODE);
                            if ($modernJson !== false) {
                                if ($mqttClient->publish($modernTrackTopic, $modernJson, true, $qos)) {
                                    $lastTrackPayloadByRoom[$tr] = $modernTrack;
                                    logln('dbg', "$tr modern s4lox radio latched for PLAYING: $stationNow");
                                } else {
                                    logln('warn', "MQTT publish failed: $modernTrackTopic (radio latch)");
                                }
                            } else {
                                logln('warn', "JSON encode failed for $tr/track radio latch");
                            }

                            // Keep s4lox_<room>_current_radio set for the whole PLAYING period.
                            loxone_publish_for_event('track_radio_latch', $modernTrack, $loxTarget, $LOXONE_HTTP_PUBLISH);

                            $mqttClient->publish($legacyPrefix . '/radio/' . $tr, $stationNow, true, 0);
                            logln('dbg', "$tr legacy radio latched for PLAYING: $stationNow");
                        } elseif (!empty($lastRadioStationByRoom[$tr])) {
                            // No empty update while PLAYING: keep both retained topic families.
                            $keptStation = (string)$lastRadioStationByRoom[$tr];
                            $mqttClient->publish($legacyPrefix . '/radio/' . $tr, $keptStation, true, 0);

                            if (!empty($lastTrackPayloadByRoom[$tr])) {
                                $modernTrack = $lastTrackPayloadByRoom[$tr];
                                $modernTrack['radio'] = $keptStation;
                                $modernTrack['ts'] = time();
                                $modernJson = json_encode($modernTrack, JSON_UNESCAPED_UNICODE);
                                if ($modernJson !== false && $mqttClient->publish($modernTrackTopic, $modernJson, true, $qos)) {
                                    $lastTrackPayloadByRoom[$tr] = $modernTrack;
                                }
                                loxone_publish_for_event('track_radio_latch', $modernTrack, $loxTarget, $LOXONE_HTTP_PUBLISH);
                            }
                            logln('dbg', "$tr radio kept for PLAYING: $keptStation");
                        } else {
                            // No station is known yet. Publish no empty retained value.
                            logln('dbg', "$tr radio not yet available for PLAYING; retained values unchanged");
                        }
                    }
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

                    $trackState = (int)($lastTransportStateByRoom[$tr] ?? $lastTransportStateByRoom[$room] ?? 0);
                    $trackRadio = trim((string)($payloadT['radio'] ?? ''));
                    $trackIsTv = (($payloadT['source_text'] ?? '') === 'TV')
                        || ((int)($payloadT['source'] ?? 0) === 3)
                        || ((int)($payloadT['tvstate'] ?? 0) === 1);

                    if ($trackIsTv) {
                        unset($lastRadioStationByRoom[$tr]);
                        $mqttClient->publish($legacyPrefix . '/radio/' . $tr, '', true, 0);
                    } elseif ($trackRadio !== '') {
                        $lastRadioStationByRoom[$tr] = $trackRadio;
                        $mqttClient->publish($legacyPrefix . '/radio/' . $tr, $trackRadio, true, 0);
                    } elseif ($trackState === 1 && !empty($lastRadioStationByRoom[$tr])) {
                        // PLAYING latch: a partial track event must not delete the station.
                        $mqttClient->publish($legacyPrefix . '/radio/' . $tr, (string)$lastRadioStationByRoom[$tr], true, 0);
                        logln('dbg', "$tr ignored empty legacy radio update while PLAYING");
                    } elseif ($trackState !== 1) {
                        $mqttClient->publish($legacyPrefix . '/radio/' . $tr, '', true, 0);
                    }

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


    // Legacy- und Loxone-Ausgaben erfolgen bereits im targetRooms-Loop oben.
    // Kein zweiter Publish hier: verhindert doppelte Telegramme und Reihenfolgeeffekte.


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
    ?SonosMqttClient $mqttClient,
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
        // GroupVolume wird NICHT als normales volume-Event published
        // (jeder Player soll seine individuelle Volume behalten)
        foreach ($sx->xpath('//*[local-name()="GroupVolume"]') as $gv) {
            // GroupVolume ignorieren - wird nicht als Volume-Event published
            logln('dbg', "$room: GroupVolume=" . (int)$gv . " (ignored)");
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

function udp_send_single_value(string $msnr, int $udpport, string $prefix, string $param, $value): void
{
    udp_send_mem($msnr, $udpport, $prefix, [
        $param => (string)$value
    ]);
}

// --------------------------------- Start: MQTT-Client & Callback ---------------------------------

$CALLBACK_HOST = $CALLBACK_HOST ?: LBSystem::get_localip();
$CALLBACK_PATH = '/sonos/cb';

$CALLBACK_URL  = "http://{$CALLBACK_HOST}:{$LISTEN_PORT}{$CALLBACK_PATH}";
logln('info', "Callback URL (for SUBSCRIBE): $CALLBACK_URL");

// --------------------------------- Räume/Player laden ---------------------------------
if (!file_exists(S4L_CFG)) { logln('error', "Config missing: " . S4L_CFG); exit(1); }
$cfg = json_decode(file_get_contents(S4L_CFG), true);
if (!is_array($cfg)) { logln('error', "Invalid JSON in " . S4L_CFG); exit(1); }

// --- Loxone Miniserver Target einmalig auflösen (optional) ---
$loxCfg       = $cfg['LOXONE'] ?? [];
$LoxDaten     = strtolower((string)($loxCfg['LoxDaten'] ?? 'false')) === 'true';
$loxMsId      = (string)($loxCfg['Loxone'] ?? '1');

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

    // State-driven display lifecycle. These narrow maps intentionally touch
    // only the four values that mirror Sonos4lox/tit,int,titint,radio.
    'track_state_clear' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_title',  'value' => '{title}',  'blank_on_empty' => true],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_artist', 'value' => '{artist}', 'blank_on_empty' => true],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_titint', 'value' => '{titint}', 'blank_on_empty' => true],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_radio',  'value' => '{radio}',  'blank_on_empty' => true],
    ],
    'track_radio_latch' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_current_radio',  'value' => '{radio}', 'blank_on_empty' => true],
    ],

    // publish next track meta
    'nexttrack' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_title',  'value' => '{title}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_artist', 'value' => '{artist}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_album',  'value' => '{album}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_cover',  'value' => '{albumArtUri}'],
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_uri',    'value' => '{uri}'],
    ],
    'nexttrack_state_clear' => [
        ['input' => $LOXONE_HTTP_PREFIX . '_{room}_next_title',  'value' => '{title}', 'blank_on_empty' => true],
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

// --------------------------------- Optional UDP output ---------------------------------
$useMqtt = true;
$LoxoneUDPPort   = (int)($cfg['LOXONE']['UDP'] ?? 0);
$useUdp          = ($LoxoneUDPPort > 0);
$LoxoneUDPPrefix = 's4lox';

if ($useUdp) {
    require_once $lbphtmldir . "/src/Core/Communication/io-modul.php";

    $mem_sendall_sec = 300;
    $mem_sendall     = 0;
    $udpsocket       = null;
}

// MQTT-Verbindungsdaten aus LoxBerry holen
$mqttClient  = null;
$healthTopic = rtrim($TOPIC_PREFIX, '/') . '/_health';

if (!function_exists('s4lox_eventhandler_mqtt_value')) {
    /**
     * Read a MQTT config value independent from LoxBerry key casing.
     */
    function s4lox_eventhandler_mqtt_value(array $mqttconf, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $mqttconf) && $mqttconf[$key] !== null && $mqttconf[$key] !== '') {
                return $mqttconf[$key];
            }
        }
        return $default;
    }
}

if (!function_exists('s4lox_eventhandler_normalize_mqtt_config')) {
    /**
     * Normalize the different key casing used by LoxBerry generations.
     */
    function s4lox_eventhandler_normalize_mqtt_config($mqttconf, string $source, array $errors = []): array
    {
        if (is_object($mqttconf)) {
            $mqttconf = (array)$mqttconf;
        }

        if (!is_array($mqttconf) || empty($mqttconf)) {
            return [
                'enabled' => false,
                'reason'  => "No usable MQTT configuration from {$source}.",
                'source'  => $source,
                'errors'  => $errors,
            ];
        }

        $host = trim((string)s4lox_eventhandler_mqtt_value($mqttconf, ['brokerhost', 'Brokerhost', 'BrokerHost'], ''));
        $port = (int)s4lox_eventhandler_mqtt_value($mqttconf, ['brokerport', 'Brokerport', 'BrokerPort'], 1883);
        $user = (string)s4lox_eventhandler_mqtt_value($mqttconf, ['brokeruser', 'Brokeruser', 'BrokerUser'], '');
        $pass = (string)s4lox_eventhandler_mqtt_value($mqttconf, ['brokerpass', 'Brokerpass', 'BrokerPass'], '');

        if ($host === '' || $port <= 0 || $port > 65535) {
            return [
                'enabled' => false,
                'reason'  => "MQTT broker host or port is missing/invalid in {$source}.",
                'source'  => $source,
                'errors'  => $errors,
            ];
        }

        return [
            'enabled' => true,
            'host'    => $host,
            'port'    => $port,
            'user'    => $user,
            'pass'    => $pass,
            'source'  => $source,
            'errors'  => $errors,
        ];
    }
}

if (!function_exists('s4lox_eventhandler_read_mqtt_json')) {
    /**
     * Read MQTT settings directly from a LoxBerry JSON file.
     * This is required because mqtt_connectiondetails() can depend on web-request
     * globals that are not available in a systemd/CLI listener context.
     */
    function s4lox_eventhandler_read_mqtt_json(string $path): ?array
    {
        if (!is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return null;
        }

        foreach (['Mqtt', 'MQTT', 'mqtt'] as $section) {
            if (isset($json[$section]) && is_array($json[$section])) {
                return $json[$section];
            }
        }

        return $json;
    }
}

if (!function_exists('s4lox_eventhandler_load_mqtt_config')) {
    /**
     * Load MQTT details safely in both web and systemd/CLI contexts.
     * Primary source is mqtt_connectiondetails(). If that emits warnings or loses
     * credentials, read the persisted LoxBerry MQTT section directly.
     */
    function s4lox_eventhandler_load_mqtt_config(): array
    {
        $errors = [];
        $mqttconf = null;

        set_error_handler(
            function (int $errno, string $errstr, string $errfile = '', int $errline = 0) use (&$errors): bool {
                $errors[] = basename($errfile) . ':' . $errline . ' ' . $errstr;
                return true;
            }
        );

        try {
            $mqttconf = function_exists('mqtt_connectiondetails') ? mqtt_connectiondetails() : null;
        } catch (Throwable $e) {
            $errors[] = get_class($e) . ': ' . $e->getMessage();
        } finally {
            restore_error_handler();
        }

        $runtime = s4lox_eventhandler_normalize_mqtt_config($mqttconf, 'mqtt_connectiondetails()', $errors);
        $runtimeHasCredentials = !empty($runtime['user']) || !empty($runtime['pass']);

        // Direct fallback paths, newest/current LoxBerry first.
        if (!empty($errors) || empty($runtime['enabled']) || !$runtimeHasCredentials) {
            $jsonPaths = [
                '/opt/loxberry/config/system/general.json',
                '/opt/loxberry/config/system/mqttgateway.json',
                '/opt/loxberry/config/plugins/mqttgateway/mqtt.json',
            ];

            foreach ($jsonPaths as $path) {
                $direct = s4lox_eventhandler_read_mqtt_json($path);
                if ($direct === null) {
                    continue;
                }

                $candidate = s4lox_eventhandler_normalize_mqtt_config($direct, $path, $errors);
                if (!empty($candidate['enabled'])) {
                    return $candidate;
                }
            }
        }

        // Anonymous MQTT remains valid when mqtt_connectiondetails() completed cleanly.
        if (!empty($runtime['enabled']) && (empty($errors) || $runtimeHasCredentials)) {
            return $runtime;
        }

        return [
            'enabled' => false,
            'reason'  => 'MQTT credentials could not be resolved safely in the systemd/CLI context.',
            'source'  => $runtime['source'] ?? 'unknown',
            'errors'  => $errors,
        ];
    }
}

$mqttRuntimeConfig = s4lox_eventhandler_load_mqtt_config();
if (!empty($mqttRuntimeConfig['errors'])) {
    logln('warn', 'MQTT config lookup reported CLI warning(s): ' . implode(' | ', array_slice($mqttRuntimeConfig['errors'], 0, 3)));
}

if (!empty($mqttRuntimeConfig['enabled'])) {
    $mqttHost = (string)$mqttRuntimeConfig['host'];
    $mqttPort = (int)$mqttRuntimeConfig['port'];
    $mqttUser = (string)$mqttRuntimeConfig['user'];
    $mqttPass = (string)$mqttRuntimeConfig['pass'];

    $mqttClientId = 'sonos_events_' . gethostname() . '_' . uniqid();
    $mqttClient   = new SonosMqttClient($mqttHost, $mqttPort, $mqttClientId, $mqttUser !== '' ? $mqttUser : null, $mqttPass !== '' ? $mqttPass : null);
    $mqttSource = (string)($mqttRuntimeConfig['source'] ?? 'unknown');
    logln('info', "MQTT publishing enabled for broker {$mqttHost}:{$mqttPort} (user=" . ($mqttUser !== '' ? 'configured' : 'none') . ", source={$mqttSource}).");
} else {
    logln('warn', 'MQTT publishing disabled: ' . (string)($mqttRuntimeConfig['reason'] ?? 'unknown reason'));
    $mqttClient = null;
}

// IMPORTANT:
// mqtt_connectiondetails() may overwrite global $cfg internally.
// Reload our plugin config here to restore the original behavior.
$cfg = json_decode(file_get_contents(S4L_CFG), true);
if (!is_array($cfg)) {
    logln('error', "Invalid JSON in " . S4L_CFG);
    exit(1);
}

// Re-read UDP config from the restored plugin config
$LoxoneUDPPort   = (int)($cfg['LOXONE']['UDP'] ?? 0);
$useUdp          = ($LoxoneUDPPort > 0);
$LoxoneUDPPrefix = (string)(($cfg['LOXONE']['LoxoneUDPPrefix'] ?? 's4lox'));

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
        $meta['events'] = default_event_urls($meta['base']);
		logln('warn', "No device_description from {$meta['ip']} ($room) – using default event URLs");
		continue;
    }

    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($desc);
    if (!$sx) {
        logln('warn', "XML error for {$meta['ip']} ($room) – keeping room, will retry later");
        $meta['events'] = default_event_urls($meta['base']);
		logln('warn', "No device_description from {$meta['ip']} ($room) – using default event URLs");
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
		$meta['events'] = default_event_urls($meta['base']);
		logln('warn', "Device {$meta['ip']} ($room): using default event URLs");
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
$MISSING_SUB_RETRY_INTERVAL = 30;
$lastMissingSubRetry = time();

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

			// ---- Content-Length robust: null = unbekannt (chunked / close-delimited) ----
			$contentLen = null;
			foreach ($lines as $line) {
				if (stripos($line, 'Content-Length:') === 0) {
					$contentLen = (int)trim(substr($line, 15));
					break;
				}
			}

			$bodyStart = $headerEndPos + $headerEndLen;

			// Wenn KEIN Content-Length vorhanden ist:
			// -> warten bis der Client die Verbindung schließt.
			// Dann wird oben im Branch ($chunk === '' || $chunk === false) der komplette Buffer verarbeitet.
			if ($contentLen === null) {
				continue;
			}

			// Wenn Content-Length da ist: warten bis Body komplett
			if (strlen($raw) < $bodyStart + $contentLen) {
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

			// Nach jedem NOTIFY Verbindung schließen, Sonos öffnet neu
			@fclose($sock);
			unset($clients[$id], $bufs[$id]);
        }
    }

    // MQTT Housekeeping (KeepAlive/Reconnect)
    if ($mqttClient) {
        try {
            $mqttClient->loop();
        } catch (Throwable $e) {
            // Keep the client object: SonosMqttClient owns the reconnect/backoff state.
            logln('warn', 'MQTT loop failed: ' . $e->getMessage() . ' - keeping client for reconnect.');
        }
    }

    $now = time();

    // Rooms that were physically off during listener startup are subscribed later.
    if ($now - $lastMissingSubRetry >= $MISSING_SUB_RETRY_INTERVAL) {
        $lastMissingSubRetry = $now;
        retry_missing_room_subscriptions(
            $subs,
            $rooms,
            $CALLBACK_URL,
            $TIMEOUT_SEC,
            $RENEW_MARGIN
        );
    }

    // --------------------------------- Health-Publish ---------------------------------
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
            try {
                update_sonos_health($allRooms, $onlineRooms, $lastEvents);
            } catch (Throwable $e) {
                logln('warn', 'update_sonos_health() failed: ' . $e->getMessage());
            }
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
            if ($mqttClient) {
                try {
                    $ok = $mqttClient->publish($healthTopic, $healthJson, true, $MQTT_QOS);
                    if (!$ok) {
                        logln('warn', "MQTT publish failed: $healthTopic");
                    }
                } catch (Throwable $e) {
                    logln('warn', 'MQTT health publish failed: ' . $e->getMessage() . ' - keeping client for reconnect.');
                }
            }

			if ($useUdp) {
				udp_publish_for_event(
					'health',
					$healthPayload,
					$loxMsId,
					$LoxoneUDPPort,
					$LoxoneUDPPrefix
				);
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
                    if ($mqttClient) {
                        try {
                            $ok = $mqttClient->publish($eqTopic, $eqJson, true, $MQTT_QOS);
                            if (!$ok) {
                                logln('warn', "MQTT publish failed: $eqTopic");
                            }
                        } catch (Throwable $e) {
                            logln('warn', 'MQTT eq publish failed: ' . $e->getMessage() . ' - keeping client for reconnect.');
                        }
                    }

					if ($useUdp) {
						udp_publish_for_event(
							'eq',
							$eqPayload,
							$loxMsId,
							$LoxoneUDPPort,
							$LoxoneUDPPrefix
						);
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