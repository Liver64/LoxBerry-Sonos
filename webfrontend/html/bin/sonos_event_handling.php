#!/usr/bin/php
<?php
declare(strict_types=1);

/**
 * Sonos Event Listener (raum-zentriert, vollständig)
 * - liest Räume/Player aus /opt/loxberry/config/plugins/sonos4lox/s4lox_config.json
 * - SUBSCRIBE: AVTransport, RenderingControl, ZoneGroupTopology
 * - NOTIFY -> MQTT je Raum:
 *     state   (retain, QoS1)
 *     volume  (retain, QoS1)
 *     mute    (QoS1)
 *     track       (CurrentTrackMetaData inkl. albumArtUri) (QoS1)
 *     nexttrack   (NextTrackMetaData/NextAVTransportURIMetaData inkl. albumArtUri; Fallback: URI) (QoS1)
 *     group       (Gruppen- & Koordinator-Infos aus ZGT) (QoS1)
 * - Payload IMMER: type, room, ip, rincon, model, service, ts
 *
 * MQTT:
 * - interner MQTT-Client "SonosMqttClient" (TCP + MQTT v3.1.1, QoS 0/1)
 * - nutzt mqtt_connectiondetails() aus LoxBerry (Host, Port, User, Passwort)
 * - Reconnect mit Backoff
 * - verbose Logging
 */

require_once "/opt/loxberry/libs/phplib/loxberry_system.php";
require_once "/opt/loxberry/libs/phplib/loxberry_io.php";
require_once __DIR__ . '/../system/SonosMqttClient.php';

date_default_timezone_set('Europe/Berlin');
pcntl_async_signals(true);

// --------------------------------- Pfade ---------------------------------
const S4L_CFG 	= "/opt/loxberry/config/plugins/sonos4lox/s4lox_config.json";
$ramLogDir 		= '/run/shm/sonos4lox';
$LogFile 		= 'sonos_events.log';

// --------------------------------- Basis-Config ---------------------------------
$LISTEN_HOST   = '0.0.0.0';
$LISTEN_PORT   = 5005;
$CALLBACK_HOST = null;         // null => auto
$TIMEOUT_SEC   = 300;
$RENEW_MARGIN  = 60;
$TOPIC_PREFIX  = 's4lox/sonos';
$MQTT_QOS      = 1;            // global QoS (1 wie gewünscht)

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
// --------------------------------- Logging ---------------------------------
function logln(string $lvl, string $msg): void {
    global $LOGFILE, $LOG_MAX_BYTES;

    $line = sprintf("[%s] %-5s %s\n", date('Y-m-d H:i:s'), strtoupper($lvl), $msg);

    if (!empty($LOGFILE)) {
        // Simple Logrotation: Wenn größer als Limit → löschen
        if (is_file($LOGFILE) && filesize($LOGFILE) > $LOG_MAX_BYTES) {
            @unlink($LOGFILE);
        }

        @file_put_contents($LOGFILE, $line, FILE_APPEND);
    }

    // Zusätzlich immer auf STDOUT, damit systemd/journalctl es auch sieht
    echo $line;
}

// --------------------------------- Helpers ---------------------------------

/**
 * Decide if we should publish a nexttrack MQTT event.
 * - suppress if title/artist/album are all empty
 * - suppress if nexttrack is effectively identical to current track
 */
function should_publish_nexttrack($track, $nexttrack)
{
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

function getLocalIpGuess(): string {
    $s = @stream_socket_client("udp://8.8.8.8:53", $errno, $err, 1);
    if ($s) {
        $name = stream_socket_get_name($s, true);
        fclose($s);
        if ($name && strpos($name, ':') !== false) return explode(':', $name)[0];
    }
    return '127.0.0.1';
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

function header_value(array $headers, string $name): ?string {
    foreach ($headers as $h) {
        if (stripos($h, $name . ':') === 0) return trim(substr($h, strlen($name) + 1));
    }
    return null;
}

// AlbumArt-URL absolut machen (falls relativ/leer)
function absolutize_art(?string $art, string $playerBase): ?string {
    $art = trim((string)$art);
    if ($art === '') return null;
    if (preg_match('~^https?://~i', $art)) return $art;
    // Sonos liefert oft Pfade wie /getaa?u=... oder /img/...
    return rtrim($playerBase, '/') . '/' . ltrim($art, '/');
}

// ---------- Parser: LastChange für AVT/RC ----------
function parse_lastchange(string $xml, string $playerBase): array {
    $events = [];

    // 1) Problemkind EnqueuedTransportURIMetaData komplett entfernen
    $xmlStripped = preg_replace(
        '~<EnqueuedTransportURIMetaData[^>]*>.*?</EnqueuedTransportURIMetaData>~si',
        '',
        $xml
    );
    if ($xmlStripped !== $xml) {
        logln('dbg', 'LastChange: stripped EnqueuedTransportURIMetaData before XML parse');
    }

    // 2) Nackte '&' in '&amp;' wandeln (URLs, Querystrings etc.)
    $xmlSanitized = preg_replace(
        '/&(?![a-zA-Z0-9#]+;)/',
        '&amp;',
        $xmlStripped
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

    // --- TransportState (PLAYING / PAUSED_PLAYBACK / STOPPED / TRANSITIONING) ---
    foreach ($sx->xpath('//*[local-name()="TransportState"]') as $n) {
        $state = (string)$n['val'];
        if ($state === '') {
            $state = trim((string)$n);
        }
        if ($state !== '') {
            $events[] = [
                'type'  => 'state',
                'state' => $state,
            ];
        }
    }

    // Hilfsfunktion: DIDL -> (title, artist, album, albumArtUri)
    $extractFromDidl = function (string $didlXml) use ($playerBase): array {
        $out = [
            'title'       => '',
            'artist'      => '',
            'album'       => '',
            'albumArtUri' => null,
        ];

        $d = @simplexml_load_string($didlXml);
        if ($d) {
            $d->registerXPathNamespace('dc',   'http://purl.org/dc/elements/1.1/');
            $d->registerXPathNamespace('upnp', 'urn:schemas-upnp-org:metadata-1-0/upnp/');

            $title  = (string)($d->xpath('//dc:title')[0]        ?? '');
            $artist = (string)($d->xpath('//dc:creator')[0]      ?? '');
            $album  = (string)($d->xpath('//upnp:album')[0]      ?? '');
            $art    = (string)($d->xpath('//upnp:albumArtURI')[0]?? '');

            $out['title']       = $title;
            $out['artist']      = $artist;
            $out['album']       = $album;
            $out['albumArtUri'] = absolutize_art($art, $playerBase);
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

    // --- CurrentTrackMetaData → track ---
    foreach ($sx->xpath('//*[local-name()="CurrentTrackMetaData"]') as $n) {
        $meta = $decodeMetaNode($n);
        if ($meta !== null) {
            $events[] = ['type' => 'track'] + $meta;
        }
    }

    // --- NextTrackMetaData → nexttrack ---
    foreach ($sx->xpath('//*[local-name()="NextTrackMetaData"]') as $n) {
        $meta = $decodeMetaNode($n);
        if ($meta !== null) {
            $events[] = ['type' => 'nexttrack'] + $meta;
        }
    }

    // --- Alternative: NextAVTransportURIMetaData (z.B. bei Streams) ---
    foreach ($sx->xpath('//*[local-name()="NextAVTransportURIMetaData"]') as $n) {
        $meta = $decodeMetaNode($n);
        if ($meta !== null) {
            $events[] = ['type' => 'nexttrack'] + $meta;
        }
    }

    // --- Fallback: NextTrackURI / NextAVTransportURI → nur URI ---
    foreach ($sx->xpath('//*[local-name()="NextTrackURI"] | //*[local-name()="NextAVTransportURI"]') as $n) {
        $uri = trim((string)$n);
        if ($uri !== '') {
            $events[] = [
                'type' => 'nexttrack',
                'uri'  => $uri,
            ];
        }
    }

    // --- Volume/Mute (Master-Level, nicht GroupVolume) ---
    foreach ($sx->xpath('//*[local-name()="Volume"][@channel="Master"]') as $n) {
        $val = (string)$n['val'];
        if ($val === '') {
            $val = (string)$n;
        }
        if ($val !== '') {
            $events[] = [
                'type'   => 'volume',
                'volume' => (int)$val,
            ];
        }
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

// ------- Publish Helper: raum-zentriert & vollständige Payload -------

// global cache to deduplicate group events: room|group_id => signature
$lastGroupState = [];

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

    // Typ ins Payload schreiben
    $data['type'] = $type;

    // --- State-Normalisierung (z.B. PAUSED_PLAYBACK -> PAUSED) ---
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
                // Unbekannt? Dann einfach roh durchreichen
                $data['state'] = $raw;
                break;
        }
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
    $retain = in_array($type, ['state', 'volume'], true);

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

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        logln('warn', "JSON encode failed for $room/$type");
        return;
    }

    $ok = $mqttClient->publish($topic, $json, $retain, $qos);
    if (!$ok) {
        logln('warn', "MQTT publish failed: $topic");
    }

    // Log kompakt
    if     ($type === 'track')     logln('evnt', "$room track: " . ($payload['title'] ?? '') . " — " . ($payload['artist'] ?? '') . " [" . ($payload['album'] ?? '') . "]");
    elseif ($type === 'nexttrack') logln('evnt', "$room next: " . ($payload['title'] ?? ($payload['uri'] ?? '')));
    elseif ($type === 'state')     logln('evnt', "$room state: {$payload['state']}");
    elseif ($type === 'volume')    logln('evnt', "$room volume: {$payload['volume']}");
    elseif ($type === 'mute')      logln('evnt', "$room mute: " . (!empty($payload['mute']) ? 'on' : 'off'));
    elseif ($type === 'group')     logln('evnt', "$room group: gid={$payload['group_id']} coord=" . (!empty($payload['is_coordinator']) ? 'yes' : 'no'));
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
    // 200 OK antworten
    @fwrite($sock, "HTTP/1.1 200 OK\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");

    if ($raw === '') {
        return;
    }

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
        foreach ($sx->xpath('//*[local-name()="LastChange"]') as $lc) {
            // Wichtig: kein html_entity_decode auf dem kompletten LastChange!
            $inner = (string)$lc;

            logln('dbg', "LastChange inner XML START: " . substr($inner, 0, 200));

            foreach (parse_lastchange($inner, $rooms[$room]['base'] ?? '') as $ev) {
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

        // --- ZoneGroupTopology: ZoneGroupState ---
        foreach ($sx->xpath('//*[local-name()="ZoneGroupState"]') as $zgs) {
            $state  = html_entity_decode((string)$zgs, ENT_QUOTES | ENT_XML1, 'UTF-8');
            logln('dbg', "ZoneGroupState inner XML START: " . substr($state, 0, 200));
            $groups = parse_topology($state);

            // reverse index: rincon -> room (aus $rooms)
            $rin2room = [];
            foreach ($rooms as $r => $mta) {
                if (!empty($mta['rincon'])) {
                    $rin2room[$mta['rincon']] = $r;
                }
            }

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
                $svc,
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
$mqttClient = new SonosMqttClient($mqttHost, $mqttPort, $mqttClientId, $mqttUser ?: null, $mqttPass ?: null);

// --------------------------------- Räume/Player laden ---------------------------------
if (!file_exists(S4L_CFG)) { logln('error', "Config missing: " . S4L_CFG); exit(1); }
$cfg = json_decode(file_get_contents(S4L_CFG), true);
if (!is_array($cfg)) { logln('error', "Invalid JSON in " . S4L_CFG); exit(1); }
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
    $desc = http_get($meta['base'] . "/xml/device_description.xml");
    if (!$desc) { logln('warn', "No device_description from {$meta['ip']} ($room)"); unset($rooms[$room]); continue; }
    libxml_use_internal_errors(true);
    $sx = @simplexml_load_string($desc);
    if (!$sx) { logln('warn', "XML error for {$meta['ip']} ($room)"); unset($rooms[$room]); continue; }
    $sx->registerXPathNamespace('d', 'urn:schemas-upnp-org:device-1-0');
    $svcs = $sx->xpath('//d:serviceList/d:service') ?: [];
    $evs  = [];
    foreach ($svcs as $svc) {
        $sid = (string)$svc->serviceId;
        $ev  = (string)$svc->eventSubURL;
        if (!$ev) continue;
        $full = (strpos($ev, 'http') === 0) ? $ev : rtrim($meta['base'], '/') . '/' . ltrim($ev, '/');
        if     (stripos($sid, 'AVTransport')        !== false) $evs['AVTransport']      = $full;
        elseif (stripos($sid, 'RenderingControl')   !== false) $evs['RenderingControl'] = $full;
        elseif (stripos($sid, 'ZoneGroupTopology')  !== false) $evs['ZoneGroupTopology']= $full;
    }
    $meta['events'] = $evs;
    logln('ok', "Device {$meta['ip']} ($room): " . implode(', ', array_keys($evs)));
}
unset($meta);

if (!$rooms) { logln('error', 'No usable Sonos devices – exiting.'); exit(1); }

// --------------------------------- SUBSCRIBE ---------------------------------
$subs = []; // SID -> ['service','eventUrl','room','renewAt']
foreach ($rooms as $room => $meta) {
    foreach (['AVTransport', 'RenderingControl', 'ZoneGroupTopology'] as $svc) {
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
            $ttl = (preg_match('~Second-(\d+)~i', (string)$tout, $m) ? (int)$m[1] : $TIMEOUT_SEC);
            $renewAt = time() + max(60, $ttl) - $RENEW_MARGIN;
            $subs[$sid] = ['service' => $svc, 'eventUrl' => $evUrl, 'room' => $room, 'renewAt' => $renewAt];
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

    // SUBS erneuern
    $now = time();
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
            $tout = header_value($resH, 'TIMEOUT') ?: header_value($resH, 'Timeout');
            $ttl  = (preg_match('~Second-(\d+)~i', (string)$tout, $m) ? (int)$m[1] : $TIMEOUT_SEC);
            $s['renewAt'] = time() + max(60, $ttl) - $RENEW_MARGIN;
            logln('ok', "RENEW {$s['service']} @ {$s['room']} (SID $sid)");
            usleep(100000);
        }
    }
    unset($s);
}
