<?php

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

    $this_song   = $TL['SONOS-TO-SPEECH']['CURRENT_SONG'];               // z.B. "Es läuft gerade"
    $by          = $TL['SONOS-TO-SPEECH']['CURRENT_SONG_ARTIST_BY'];     // z.B. "von"
    $this_radio  = $TL['SONOS-TO-SPEECH']['CURRENT_RADIO_STATION'];      // z.B. "Sie hören"
    // ********************************************************************

    // Prüft, ob überhaupt etwas gespielt wird
    $gettransportinfo = $sonos->GetTransportInfo();
    if ($gettransportinfo <> 1) {
        LOGWARN("sonos-to-speech.php: Nothing is playing on Zone '$master', aborting.");
		exit;
    }

    // Prüfen, ob Zone gruppiert ist – aber NICHT mehr abbrechen
    $grouped = getGroup($master);
    if ($grouped != array()) {
        LOGINF("sonos-to-speech.php: Player $master is grouped, but we continue (snapshot/restore will handle state).");
    }

    // Master-Zone aus GET übernehmen (wie bisher)
    if (isset($_GET['zone']) && isset($sonoszone[$_GET['zone']])) {
        $master = $_GET['zone'];
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);

    // Aktuelle Track-/Radio-Infos holen
    $temp       = $sonos->GetPositionInfo();
    $temp_radio = $sonos->GetMediaInfo();

    $ann_radio  = $config['VARIOUS']['announceradio_always'];

    // ===== Debug: Rohdaten ins Log =====
    #LOGGING('sonos-to-speech.php: PositionInfo: ' . print_r($temp, true), 7);
    #LOGGING('sonos-to-speech.php: MediaInfo: '    . print_r($temp_radio, true), 7);

    // ---------------------------------------------------------
    // Robuste Track-Erkennung – kompatibel zum alten Verhalten
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
    // - Live-Radio: oft "0:00:00" oder "" ? wie früher: KEIN Track
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

        LOGDEB('sonos-to-speech.php: Raw track meta ? artist="' . $artist . '", title="' . $titel . '"');

        // Fallback 1: Wenn Artist/Titel leer, aus streamContent "Artist - Titel" extrahieren
        if ($artist === '' && $titel === '' && !empty($temp['streamContent'])) {
            $sc = $temp['streamContent'];
            LOGDEB('sonos-to-speech.php: streamContent="' . $sc . '"');

            $find1st  = strpos($sc, " - ");
            $findlast = strrpos($sc, " - ");
            if ($find1st !== false && $find1st === $findlast) {
                $artist = trim(substr($sc, 0, $find1st));
                $titel  = trim(substr($sc, $find1st + 3, 70));
                LOGDEB('sonos-to-speech.php: Fallback from streamContent ? Artist="' . $artist . '", Title="' . $titel . '"');
            } else {
                LOGDEB('sonos-to-speech.php: streamContent not in "Artist - Title" form.');
            }
        }

        // Fallback 2: Wenn immer noch nichts da ist, versuche wenigstens den Titel aus MediaInfo
        if ($artist === '' && $titel === '' && !empty($temp_radio['title'])) {
            $titel = trim($temp_radio['title']);
            LOGDEB('sonos-to-speech.php: Fallback from MediaInfo ? Title="' . $titel . '"');
        }

        // Endgültiger Text für Track/Playlist
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
            LOGDEB('sonos-to-speech.php: No usable metadata for track – falling back to generic text.');
        }

    } else {
        // ---------------------------------------------------------
        // RADIO / STREAM – exakt wie dein alter Radio-Zweig
        // ---------------------------------------------------------
        $sc = $temp['streamContent'] ?? '';
        LOGDEB('sonos-to-speech.php: Radio branch, streamContent="' . $sc . '"');

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
        LOGDEB('sonos-to-speech.php: Computed announcement text is empty – aborting.');
        return;
    }

    LOGDEB('sonos-to-speech.php: Song Announcement: ' . $text);
    LOGOK('sonos-to-speech.php: Message been generated and pushed to T2S creation');

    return $text;
}

?>
