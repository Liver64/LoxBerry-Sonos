<?php
/**
 * Mini TTS Router für Testausgaben aus der Zonen-Tabelle (Sonos4lox)
 *
 * Ablauf:
 *  - nimmt via POST "room", "text" (+ optionale T2S-Parameter) entgegen
 *  - prüft, ob der Raum in s4lox_config.json existiert
 *  - liest die Standard-Lautstärke aus sonoszonen[room][4] (Sonos Vol), falls kein volume-POST
 *  - löscht ggf. vorhandene TTS-Cache-Dateien (mp3/wav) für diesen Text
 *  - ruft die normale Sonos4lox-T2S-Logik über index.php?action=say&nocache=1 auf
 *
 * Rückgabe (JSON): { success: bool, message: string, deleted: array }
 */

require_once "loxberry_system.php";

header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => false,
    'message' => 'Unknown error',
    'deleted' => [],
];

// ---------------------------------------------------------------------
// Plugin-HTML-Verzeichnis ermitteln, um helper.php einbinden zu können
// ---------------------------------------------------------------------
global $lbphtmldir;

if (defined('LBPHTMLDIR')) {
    $pluginHtmlDir = LBPHTMLDIR;
} elseif (!empty($lbphtmldir)) {
    $pluginHtmlDir = $lbphtmldir;
} else {
    // Fallback: eine Ebene über /bin
    $pluginHtmlDir = dirname(__DIR__);
}

// helper.php ist optional (delete_all_cache)
$helperFile = $pluginHtmlDir . "/helper.php";
if (file_exists($helperFile)) {
    require_once $helperFile;
}

// ---------------------------------------------------------------------
// Hilfsfunktion: Cache-Dateien für einen Text löschen
// ---------------------------------------------------------------------
function clear_tts_cache_for_text(string $text, array $config): array
{
    $deleted = [];
    $hash    = md5($text);

    $candidates = [];

    // ttspath/<hash>.mp3 / .wav
    if (!empty($config['SYSTEM']['ttspath'])) {
        $ttspath = rtrim($config['SYSTEM']['ttspath'], '/');
        $candidates[] = $ttspath . '/' . $hash . '.mp3';
        $candidates[] = $ttspath . '/' . $hash . '.wav';
    }

    // mp3path/<hash>.mp3 / .wav (falls verwendet)
    if (!empty($config['SYSTEM']['mp3path'])) {
        $mp3path = rtrim($config['SYSTEM']['mp3path'], '/');
        $candidates[] = $mp3path . '/' . $hash . '.mp3';
        $candidates[] = $mp3path . '/' . $hash . '.wav';
    }

    // cifsinterface/<hash>.mp3 und cifsinterface/mp3/<hash>.mp3
    if (!empty($config['SYSTEM']['cifsinterface'])) {
        $cifs = rtrim($config['SYSTEM']['cifsinterface'], '/');
        $candidates[] = $cifs . '/' . $hash . '.mp3';
        $candidates[] = $cifs . '/' . $hash . '.wav';
        $candidates[] = $cifs . '/mp3/' . $hash . '.mp3';
        $candidates[] = $cifs . '/mp3/' . $hash . '.wav';
    }

    // Duplikate raus
    $candidates = array_values(array_unique($candidates));

    foreach ($candidates as $file) {
        if ($file && file_exists($file)) {
            if (@unlink($file)) {
                $deleted[] = $file;
            }
        }
    }

    return $deleted;
}

// ---------------------------------------------------------------------
// Nur POST zulassen
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = "Invalid request method. Use POST.";
    echo json_encode($response);
    exit;
}

$room   = trim($_POST['room'] ?? '');
$text   = trim($_POST['text'] ?? '');
$volume = isset($_POST['volume']) ? (int)$_POST['volume'] : null;

// NEU: optionale T2S-Parameter aus POST
$t2sengine = isset($_POST['t2sengine']) ? trim($_POST['t2sengine']) : '';
$language  = isset($_POST['language'])  ? trim($_POST['language'])  : '';
$voice     = isset($_POST['voice'])     ? trim($_POST['voice'])     : '';
$apikey    = isset($_POST['apikey'])    ? trim($_POST['apikey'])    : '';
$secretkey = isset($_POST['secretkey']) ? trim($_POST['secretkey']) : '';

// Raum muss gesetzt sein
if ($room === '') {
    $response['message'] = "No room has been provided.";
    echo json_encode($response);
    exit;
}

// Fallback-Text, falls aus dem JS nichts (oder nur Spaces) kommt
if ($text === '') {
    $text = "Test Sprachausgabe für Raum " . $room;
}

// ------------------------------------------------------------
// Konfiguration von Sonos4lox einlesen (s4lox_config.json)
// ------------------------------------------------------------

$configfile = "s4lox_config.json";
$configpath = $lbpconfigdir . "/" . $configfile;

if (!file_exists($configpath)) {
    $response['message'] = "Configuration file '$configfile' could not be loaded.";
    echo json_encode($response);
    exit;
}

$config = json_decode(file_get_contents($configpath), true);
if (!is_array($config)) {
    $response['message'] = "Configuration file '$configfile' is not valid JSON.";
    echo json_encode($response);
    exit;
}

if (!isset($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
    $response['message'] = "Configuration does not contain 'sonoszonen' section.";
    echo json_encode($response);
    exit;
}

$sonoszonen = $config['sonoszonen'];

if (!array_key_exists($room, $sonoszonen)) {
    $response['message'] = "Room '$room' not found in configuration.";
    echo json_encode($response);
    exit;
}

$zoneData = $sonoszonen[$room];

// ------------------------------------------------------------
// Lautstärke: POST > Config[4] > Fallback 35
// ------------------------------------------------------------

if ($volume === null) {
    $volume = 35;
    if (isset($zoneData[4]) && is_numeric($zoneData[4])) {
        $volume = (int)$zoneData[4];
    }
}

if ($volume < 0)   { $volume = 0; }
if ($volume > 100) { $volume = 100; }

// ------------------------------------------------------------
// Cache-Dateien für diesen Text löschen
// ------------------------------------------------------------

$response['deleted'] = clear_tts_cache_for_text($text, $config);

// ------------------------------------------------------------
// Optional: Buffer/Caches leeren, wenn Helper vorhanden
// ------------------------------------------------------------
if (function_exists('delete_all_cache')) {
    delete_all_cache();
}

// ------------------------------------------------------------
// internen TTS-Aufruf bauen: index.php?action=say
//  + on-the-fly T2S-Parameter, wenn gesetzt
// ------------------------------------------------------------

$lbIp      = LBSystem::get_localip();
$webport   = lbwebserverport();
$protocol  = ($webport == 443 ? 'https' : 'http');
$hostPart  = ($webport == 80 || $webport == 443) ? $lbIp : $lbIp . ':' . $webport;

$params = [
    'zone'     => $room,
    'action'   => 'say',
    'text'     => $text,
    'volume'   => $volume,
    'playgong' => 'yes',
    'nocache'  => 1,
];

// Nur anhängen, wenn wirklich gesetzt
if ($t2sengine !== '') { $params['t2sengine'] = $t2sengine; }
if ($language  !== '') { $params['language']  = $language;  }
if ($voice     !== '') { $params['voice']     = $voice;     }
if ($apikey    !== '') { $params['apikey']    = $apikey;    }
if ($secretkey !== '') { $params['secretkey'] = $secretkey; }

$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

// kompletter URL zum eigenen Plugin
$ttsUrl = $protocol . "://" . $hostPart . "/plugins/sonos4lox/index.php?" . $query;

// ------------------------------------------------------------
// Call an die bestehende Sonos4lox-TTS-Logik
// ------------------------------------------------------------

$context = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 20,
    ],
]);

$result = @file_get_contents($ttsUrl, false, $context);

if ($result === false) {
    $response['success'] = false;
    $response['message'] = "Call to internal TTS (index.php?action=say&nocache=1) failed. Please check Sonos4lox logs.";
} else {
    $response['success'] = true;
    $response['message'] = "T2S test call for room '$room' started successfully (cache bypassed, on-the-fly T2S params).";
}

echo json_encode($response);
exit;
