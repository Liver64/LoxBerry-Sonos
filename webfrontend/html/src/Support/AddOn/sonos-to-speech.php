<?php

/*
 * Sonos4Lox addon TTS helper
 * Version: ADDON_TTS_SUPPORT_ADDON_RELOCATION_V04_2026_06_12
 * Notes: moved to src/Support/AddOn with centralized S4L_Logger based logging and defensive input/fetch handling.
 */

require_once dirname(__DIR__) . '/Logger.php';



if (!function_exists('s4lox_addon_fetch_url')) {
    function s4lox_addon_fetch_url($url, $timeout = 8)
    {
        if (!is_string($url) || trim($url) === '') {
            return false;
        }
        $context = stream_context_create(array(
            'http' => array('timeout' => $timeout, 'ignore_errors' => true),
            'https' => array('timeout' => $timeout, 'ignore_errors' => true),
        ));
        return @file_get_contents($url, false, $context);
    }
}

if (!function_exists('s4lox_addon_decode_json')) {
    function s4lox_addon_decode_json($json)
    {
        if (!is_string($json) || trim($json) === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $data;
    }
}



 /** 
* aktueller Sonos Titel und Interpret per TTS ansagen
* @param $text
*/ 

/**
* aktueller Sonos Titel und Interpret per TTS ansagen
* @param none
* @return string  erzeugter Ansagetext (wird von play_t2s.php als TTS-Text verwendet)
*/

/**
 * aktueller Sonos Titel und Interpret per TTS ansagen
 * @param void
 * @return string|void  Text, der gesprochen werden soll (wird von play_t2s weiterverarbeitet)
 */
function s2s()
{
    global $debug, $sonos, $sonoszone, $master, $t2s_langfile, $templatepath, $config;

    // ********************** T2S-Textbausteine laden **********************
    $TL = LOAD_T2S_TEXT();

    $this_song   = $TL['SONOS-TO-SPEECH']['CURRENT_SONG'];               // z.B. "Es l�uft gerade"
    $by          = $TL['SONOS-TO-SPEECH']['CURRENT_SONG_ARTIST_BY'];     // z.B. "von"
    $this_radio  = $TL['SONOS-TO-SPEECH']['CURRENT_RADIO_STATION'];      // z.B. "Sie h�ren"
    // ********************************************************************

    // Pr�ft, ob �berhaupt etwas gespielt wird
    $gettransportinfo = $sonos->GetTransportInfo();
    if ($gettransportinfo <> 1) {
        S4L_Logger::write("Nothing is playing on Zone '$master', aborting.", 4, __FILE__);
		exit;
    }

    // Pr�fen, ob Zone gruppiert ist � aber NICHT mehr abbrechen
    $grouped = getGroup($master);
    if ($grouped != array()) {
        S4L_Logger::write("Player $master is grouped, but we continue (snapshot/restore will handle state).", 6, __FILE__);
    }

    // Master-Zone aus GET �bernehmen (wie bisher)
    if (isset($_GET['zone']) && isset($sonoszone[$_GET['zone']])) {
        $master = $_GET['zone'];
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);

    // Aktuelle Track-/Radio-Infos holen
    $temp       = $sonos->GetPositionInfo();
    $temp_radio = $sonos->GetMediaInfo();

    $ann_radio  = $config['VARIOUS']['announceradio_always'] ?? '0';

    // ===== Debug: Rohdaten ins Log =====
    #S4L_Logger::write('PositionInfo: ' . print_r($temp, true), 7, __FILE__);
    #S4L_Logger::write('MediaInfo: '    . print_r($temp_radio, true), 7, __FILE__);

    // ---------------------------------------------------------
    // Robuste Track-Erkennung � kompatibel zum alten Verhalten
    // ---------------------------------------------------------
    $durationRaw = '';

    if (array_key_exists('duration', $temp)) {
        $durationRaw = (string)$temp['duration'];
    } elseif (array_key_exists('TrackDuration', $temp)) {
        $durationRaw = (string)$temp['TrackDuration'];
    }

    $duration = trim($durationRaw);

    // Spezieller Fix:
    // - Spotify Tracks: z.B. "0:02:35"  ? Track
    // - Live-Radio: oft "0:00:00" oder "" ? wie fr�her: KEIN Track
    if ($duration === '0:00:00') {
        $duration = '';
    }

    $isTrack = ($duration !== '');

    $text   = '';
    $artist = '';
    $titel  = '';

    if ($isTrack) {
        // -----------------------------
        // TRACK / PLAYLIST  (wie vorher, nur robuster)
        // -----------------------------
        $artist = trim((string)($temp['artist'] ?? ''));
        $titel  = trim((string)($temp['title']  ?? ''));

        S4L_Logger::write('Raw track meta ? artist="' . $artist . '", title="' . $titel . '"', 7, __FILE__);

        // Fallback 1: Wenn Artist/Titel leer, aus streamContent "Artist - Titel" extrahieren
        if ($artist === '' && $titel === '' && !empty($temp['streamContent'])) {
            $sc = $temp['streamContent'];
            S4L_Logger::write('streamContent="' . $sc . '"', 7, __FILE__);

            $find1st  = strpos($sc, " - ");
            $findlast = strrpos($sc, " - ");
            if ($find1st !== false && $find1st === $findlast) {
                $artist = trim(substr($sc, 0, $find1st));
                $titel  = trim(substr($sc, $find1st + 3, 70));
                S4L_Logger::write('Fallback from streamContent ? Artist="' . $artist . '", Title="' . $titel . '"', 7, __FILE__);
            } else {
                S4L_Logger::write('streamContent not in "Artist - Title" form.', 7, __FILE__);
            }
        }

        // Fallback 2: Wenn immer noch nichts da ist, versuche wenigstens den Titel aus MediaInfo
        if ($artist === '' && $titel === '' && !empty($temp_radio['title'])) {
            $titel = trim($temp_radio['title']);
            S4L_Logger::write('Fallback from MediaInfo ? Title="' . $titel . '"', 7, __FILE__);
        }

        // Endg�ltiger Text f�r Track/Playlist
        if ($titel !== '' || $artist !== '') {
            if ($titel !== '' && $artist !== '') {
                $text = $this_song . ' ' . $titel . ' ' . $by . ' ' . $artist;
            } elseif ($titel !== '') {
                $text = $this_song . ' ' . $titel;
            } else {
                $text = $this_song . ' ' . $by . ' ' . $artist;
            }
        } else {
            $text = $this_song;
            S4L_Logger::write('No usable metadata for track � falling back to generic text.', 7, __FILE__);
        }

    } else {
        // ---------------------------------------------------------
        // RADIO / STREAM � exakt wie dein alter Radio-Zweig
        // ---------------------------------------------------------
        $sc = $temp['streamContent'] ?? '';
        S4L_Logger::write('Radio branch, streamContent="' . $sc . '"', 7, __FILE__);

        $find1st  = strpos($sc, " - ");
        $findlast = strrpos($sc, " - ");

        if (($find1st === false) || ($find1st <> $findlast) || ($ann_radio == "1")) {
            // Sender-Ansage
            $sender = $temp_radio['title'] ?? '';
            if ($sender !== '') {
                $text = $this_radio . ' ' . $sender;
            } else {
                $text = $this_radio;
            }
        } else {
            // Titel/Artist aus StreamContent
            $artist = substr($sc, 0, $find1st);
            $titel  = substr($sc, $find1st + 3, 70);
            $artist = trim($artist);
            $titel  = trim($titel);
            $text   = $this_song . ' ' . $titel . ' ' . $by . ' ' . $artist;
        }
    }

    // Sicherheit: Wenn aus irgendeinem Grund leer ? abbrechen
    if (trim($text) === '') {
        S4L_Logger::write('Computed announcement text is empty � aborting.', 7, __FILE__);
        return;
    }

    S4L_Logger::write('Song Announcement: ' . $text, 7, __FILE__);
    S4L_Logger::write('Message been generated and pushed to T2S creation', 6, __FILE__);

    return $text;
}

?>
