<?php

/**
 * Submodule: Playlist
 * Version: PLAYLIST_HARDENING_V01_2026_06_18
 * Language: EN
 *
 * Purpose:
 * - Playlist related legacy runtime functions.
 * - Harden input handling, temporary file handling and logging.
 * - Keep public function names unchanged for URL/runtime compatibility.
 * - Remove the obsolete dynamic playlist helper. The nextpush playlist stepping logic
 *   now lives in src/Actions/OneClickActions.php.
 */

/**
 * Makes externally supplied values safe for log output.
 */
function s4lox_playlist_log_value($value)
{
    return str_replace(array("\r", "\n"), ' ', trim((string)$value));
}

/**
 * Checks whether a Sonos zone exists and has a usable IP address.
 */
function s4lox_playlist_zone_exists($zone)
{
    global $sonoszone;

    return isset($sonoszone[$zone][0]) && trim((string)$sonoszone[$zone][0]) !== '';
}

/**
 * Returns a safe integer volume in the Sonos range 0..100.
 */
function s4lox_playlist_normalize_volume($value, $fallback)
{
    if ($value === null || $value === '') {
        return max(0, min(100, (int)$fallback));
    }

    return max(0, min(100, (int)$value));
}

/**
 * Deletes a file without suppressing errors.
 */
function s4lox_playlist_delete_file($file, $label)
{
    if (!is_string($file) || $file === '' || !file_exists($file)) {
        return true;
    }

    if (!unlink($file)) {
        LOGWARN("Playlist.php: " . $label . " could not be deleted: " . $file);
        return false;
    }

    LOGINF("Playlist.php: " . $label . " has been deleted.");
    return true;
}

/**
 * Writes JSON atomically enough for the current runtime usage.
 */
function s4lox_playlist_write_json($file, $value, $label)
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        LOGWARN("Playlist.php: " . $label . " could not be encoded as JSON.");
        return false;
    }

    if (file_put_contents($file, $json, LOCK_EX) === false) {
        LOGWARN("Playlist.php: " . $label . " could not be written: " . $file);
        return false;
    }

    return true;
}

/**
 * playmode_detection()
 * Applies a numeric Sonos play mode and returns the readable mode string.
 *
 * @param string $zone Sonos zone name
 * @param mixed  $mode Numeric Sonos play mode
 * @return mixed Readable play mode string or the original value if unsupported
 */
function playmode_detection($zone, $mode)
{
    $map = array(
        0 => 'NORMAL',
        1 => 'REPEAT_ALL',
        2 => 'REPEAT_ONE',
        3 => 'SHUFFLE_NOREPEAT',
        4 => 'SHUFFLE',
        5 => 'SHUFFLE_REPEAT_ONE',
    );

    if (!s4lox_playlist_zone_exists($zone)) {
        LOGWARN("Playlist.php: Playmode detection skipped because zone '" . s4lox_playlist_log_value($zone) . "' is unknown.");
        return $mode;
    }

    $numericMode = (int)$mode;

    if (!array_key_exists($numericMode, $map)) {
        LOGWARN("Playlist.php: Unsupported numeric playmode '" . s4lox_playlist_log_value($mode) . "'.");
        return $mode;
    }

    global $sonoszone;

    $sonos = new SonosAccess($sonoszone[$zone][0]);
    $sonos->SetPlayMode((string)$numericMode);

    return $map[$numericMode];
}

/**
 * SetPlaymodes()
 * Applies a readable Sonos play mode and returns the numeric mode.
 *
 * @param string $zone Sonos zone name
 * @param mixed  $mode Readable Sonos play mode
 * @return mixed Numeric play mode or the original value if unsupported
 */
function SetPlaymodes($zone, $mode)
{
    $map = array(
        'NORMAL'             => 0,
        'REPEAT_ALL'         => 1,
        'REPEAT_ONE'         => 2,
        'SHUFFLE_NOREPEAT'   => 3,
        'SHUFFLE'            => 4,
        'SHUFFLE_REPEAT_ONE' => 5,
    );

    if (!s4lox_playlist_zone_exists($zone)) {
        LOGWARN("Playlist.php: SetPlaymodes skipped because zone '" . s4lox_playlist_log_value($zone) . "' is unknown.");
        return $mode;
    }

    $modeKey = strtoupper(trim((string)$mode));

    if (!array_key_exists($modeKey, $map)) {
        LOGWARN("Playlist.php: Unsupported playmode '" . s4lox_playlist_log_value($mode) . "'.");
        return $mode;
    }

    global $sonoszone;

    $numericMode = $map[$modeKey];
    $sonos = new SonosAccess($sonoszone[$zone][0]);
    $sonos->SetPlayMode((string)$numericMode);

    return $numericMode;
}

/**
 * NextTrack()
 * Skips to the next track and starts playback if Sonos is not already playing.
 */
function NextTrack()
{
    global $sonos;

    if (!is_object($sonos)) {
        LOGWARN("Playlist.php: NextTrack skipped because SonosAccess is not available.");
        return false;
    }

    $sonos->Next();
    sleep(1);
    LOGINF("Playlist.php: Function 'next' has been executed.");

    $transportInfo = $sonos->GetTransportInfo();
    $transportState = '';

    if (is_array($transportInfo)) {
        $transportState = (string)($transportInfo['CurrentTransportState'] ?? '');
    } else {
        $transportState = (string)$transportInfo;
    }

    if ($transportState !== 'PLAYING' && $transportState !== '1') {
        $sonos->Play();
    }

    return true;
}

/**
 * playlist()
 * Loads a Sonos playlist into a zone/group.
 */
function playlist()
{
    global $debug, $sonos, $master, $profile_details, $memberarray, $samearray, $sonoszone, $config, $volume, $masterzone, $sonospltmp, $profile_selected;

    if (!defined('GROUPMASTER')) {
        define('GROUPMASTER', $master);
    }

    CreateMember();

    if (!s4lox_playlist_zone_exists(GROUPMASTER)) {
        LOGERR("Playlist.php: Group master '" . s4lox_playlist_log_value(GROUPMASTER) . "' is not a known Sonos zone.");
        exit;
    }

    if (file_exists($sonospltmp) && !isset($_GET['load'])) {
        $sonos = new SonosAccess($sonoszone[GROUPMASTER][0]);
        $currentPlaylist = $sonos->GetCurrentPlaylist();
        $countqueue = is_array($currentPlaylist) ? count($currentPlaylist) : 0;
        $currtrack = $sonos->GetPositioninfo();
        $trackNumber = is_array($currtrack) ? (int)($currtrack['Track'] ?? 0) : 0;

        if ($trackNumber > 0 && $trackNumber < $countqueue) {
            NextTrack();
            LOGINF("Playlist.php: Next track has been called.");
            return true;
        }

        s4lox_playlist_delete_file($sonospltmp, 'Playlist temp file');

        $action = (string)($_GET['action'] ?? '');
        if (isset($_GET['member']) && !isset($_GET['profile']) && !isset($_GET['Profile']) && $action !== 'Profile') {
            removemember();
            LOGINF("Playlist.php: Member has been removed.");
        }

        LOGOK("Playlist.php: Playlist loop ended, starting from the beginning.");
    }

    $sonos = new SonosAccess($sonoszone[GROUPMASTER][0]);

    $enteredPlaylist = trim((string)($_GET['playlist'] ?? ''));
    if ($enteredPlaylist === '') {
        LOGERR("Playlist.php: Missing playlist parameter. Correct syntax is: &action=playlist&playlist=<PLAYLIST>");
        exit;
    }

    $enteredPlaylistLog = s4lox_playlist_log_value($enteredPlaylist);
    $playlistSearch = mb_strtolower($enteredPlaylist);

    $sonoslists = $sonos->GetSONOSPlaylists();
    if (!is_array($sonoslists) || count($sonoslists) < 1) {
        LOGERR("Playlist.php: No Sonos playlists are available.");
        exit;
    }

    $found = array();
    foreach ($sonoslists as $key => $item) {
        if (!is_array($item) || !isset($item['title'])) {
            continue;
        }

        $titleLower = mb_strtolower((string)$item['title']);
        $sonoslists[$key]['titlelow'] = $titleLower;

        if ($playlistSearch === $titleLower) {
            $found[] = $sonoslists[$key];
        }
    }

    if (count($found) > 1) {
        LOGERR("Playlist.php: Entered playlist '" . $enteredPlaylistLog . "' has more than one match. Please specify it more precisely.");
        exit;
    }

    if (count($found) === 0) {
        LOGERR("Playlist.php: Entered playlist '" . $enteredPlaylistLog . "' could not be found.");
        exit;
    }

    $selectedPlaylist = $found[0];
    $selectedTitle = s4lox_playlist_log_value($selectedPlaylist['title'] ?? $enteredPlaylist);

    if (empty($selectedPlaylist['file'])) {
        LOGERR("Playlist.php: Playlist '" . $selectedTitle . "' has no queue file URI.");
        exit;
    }

    LOGINF("Playlist.php: Playlist '" . $selectedTitle . "' has been found.");

    $sonos->SetQueue('x-rincon-queue:' . trim($sonoszone[GROUPMASTER][1]) . '#0');
    $sonos->ClearQueue();

    $plfile = urldecode((string)$selectedPlaylist['file']);
    $sonos->AddToQueue($plfile);
    s4lox_playlist_write_json($sonospltmp, $plfile, 'Playlist temp file');

    if (!isset($_GET['load']) && !isset($_GET['rampto'])) {
        $sonos->SetMute(false);
        $sonos->Stop();

        if (isset($_GET['profile']) || isset($_GET['Profile'])) {
            if (isset($profile_details[0]['Player'][GROUPMASTER][0]['Volume'])) {
                $volume = s4lox_playlist_normalize_volume($profile_details[0]['Player'][GROUPMASTER][0]['Volume'], $volume);
            }
        } else {
            volume_group();
        }

        $sonos = new SonosAccess($sonoszone[GROUPMASTER][0]);
        $sonos->SetVolume(s4lox_playlist_normalize_volume($volume, 25));
        $sonos->Play();
    } else {
        $sonos->SetQueue('x-rincon-queue:' . trim($sonoszone[GROUPMASTER][1]) . '#0');
    }

    RampTo();
    LOGOK("Playlist.php: Playlist '" . $selectedTitle . "' has been loaded successfully.");

    return true;
}

/**
 * SavePlaylist()
 * Saves a temporary Sonos playlist for TTS restore handling.
 */
function SavePlaylist()
{
    global $sonos, $id;

    try {
        $sonos->SaveQueue('temp_t2s');
    } catch (Exception $e) {
        LOGWARN("Playlist.php: The temporary playlist could not be saved because the queue contains at least one invalid song URL. Please check or remove the queue entry.");
        exit;
    }

    LOGOK("Playlist.php: Temporary playlist has been saved.");
}

/**
 * DelPlaylist()
 * Deletes the previously saved temporary TTS playlist.
 */
function DelPlaylist()
{
    global $sonos;

    $playlists = $sonos->GetSonosPlaylists();
    if (!is_array($playlists)) {
        LOGWARN("Playlist.php: Temporary playlist could not be deleted because the playlist list is invalid.");
        return false;
    }

    $t2splaylist = recursive_array_search('temp_t2s', $playlists);
    if (!empty($t2splaylist) && isset($playlists[$t2splaylist]['id'])) {
        $sonos->DeleteSonosPlaylist($playlists[$t2splaylist]['id']);
    }

    LOGOK("Playlist.php: Temporary playlist has been deleted.");
    return true;
}

/**
 * random_playlist()
 * Loads and starts a random Sonos playlist.
 */
function random_playlist()
{
    global $sonos, $sonoszone, $master, $min_vol, $volume, $config;

    if (isset($_GET['member']) && defined('GROUPMASTER')) {
        $master = GROUPMASTER;
    } else {
        $master = trim((string)($_GET['zone'] ?? $master));
    }

    if (!s4lox_playlist_zone_exists($master)) {
        LOGERR("Playlist.php: Random playlist aborted because zone '" . s4lox_playlist_log_value($master) . "' is unknown.");
        exit;
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);
    $sonoslists = $sonos->GetSONOSPlaylists();

    if (!is_array($sonoslists) || count($sonoslists) < 1) {
        LOGWARN("Playlist.php: No Sonos playlists are available for random selection.");
        exit;
    }

    if (isset($_GET['except'])) {
        $exception = explode(',', (string)$_GET['except']);
        foreach ($exception as $rawIndex) {
            $index = trim((string)$rawIndex);
            if ($index === '' || !preg_match('/^\d+$/', $index)) {
                LOGWARN("Playlist.php: Ignoring invalid random playlist exception index '" . s4lox_playlist_log_value($rawIndex) . "'.");
                continue;
            }

            if (isset($sonoslists[(int)$index])) {
                unset($sonoslists[(int)$index]);
            }
        }

        $sonoslists = array_values($sonoslists);
    }

    $countpl = count($sonoslists);
    if ($countpl < 1) {
        LOGWARN("Playlist.php: Random playlist aborted because all playlists were excluded.");
        exit;
    }

    $random = mt_rand(0, $countpl - 1);

    if (empty($sonoslists[$random]['file'])) {
        LOGWARN("Playlist.php: Random playlist aborted because the selected playlist has no queue file URI.");
        exit;
    }

    $plfileLog = (string)$sonoslists[$random]['file'];
    $plfile = urldecode($plfileLog);
    $title = isset($sonoslists[$random]['title']) ? $sonoslists[$random]['title'] : $plfileLog;
    $tmp_volume = $sonos->GetVolume();

    $sonos->ClearQueue();
    $sonos->SetMute(false);
    $sonos->AddToQueue($plfile);
    $sonos->SetQueue('x-rincon-queue:' . trim($sonoszone[$master][1]) . '#0');

    if (isset($_GET['volume'])) {
        $volume = s4lox_playlist_normalize_volume($_GET['volume'], $sonoszone[$master][4]);
    } elseif (isset($_GET['keepvolume'])) {
        if ((int)$tmp_volume >= (int)$min_vol) {
            $volume = s4lox_playlist_normalize_volume($tmp_volume, $sonoszone[$master][4]);
        } else {
            $volume = s4lox_playlist_normalize_volume($sonoszone[$master][4], 25);
        }
    } else {
        $volume = s4lox_playlist_normalize_volume($sonoszone[$master][4], 25);
    }

    LOGOK("Playlist.php: Random playlist '" . s4lox_playlist_log_value(urldecode($title)) . "' has been added to the queue.");
    $sonos->Play();
}

/**
 * say_zone()
 * Optional zapzone sub-function. Announces the next zone before it is added to the master.
 */
function say_zone($zone)
{
    global $master, $sonoszone, $config, $volume, $min_vol, $actual, $sonos, $coord, $messageid, $filename, $MessageStorepath, $nextZoneKey, $filenameplaysay;

    require_once('addon/sonos-to-speech.php');

    if (isset($_GET['batch'])) {
        LOGWARN("Playlist.php: The parameter 'batch' cannot be used together with zone announcement.");
        exit;
    }

    if (!s4lox_playlist_zone_exists($master)) {
        LOGWARN("Playlist.php: Zone announcement skipped because master zone '" . s4lox_playlist_log_value($master) . "' is unknown.");
        return $volume;
    }

    saveZonesStatus();
    sleep(1);

    $sonos = new SonosAccess($sonoszone[$master][0]);
    $TL = LOAD_T2S_TEXT();

    $playZone = $TL['SONOS-TO-SPEECH']['ANNOUNCE_ZONE'];
    $text = $playZone . ' ' . $zone;
    $textstring = $text;
    $rawtext = md5($text);
    $filename = $rawtext;

    select_t2s_engine();
    t2s($textstring, $filename);

    $coord = getRoomCoordinator($master);
    LOGDEB("Playlist.php: Room coordinator has been identified.");

    $sonos = new SonosAccess($coord[0]);
    $tmp_volume = $sonos->GetVolume();
    $sonos->SetMute(false);
    $volume = $volume + $config['TTS']['correction'];
    play_tts($filename);
    LOGOK("Playlist.php: Zone announcement has been played.");

    restoreSingleZone();

    if (isset($_GET['volume'])) {
        $volume = s4lox_playlist_normalize_volume($_GET['volume'], $sonoszone[$master][4]);
    } elseif (isset($_GET['keepvolume'])) {
        if ((int)$tmp_volume >= (int)$min_vol) {
            $volume = s4lox_playlist_normalize_volume($tmp_volume, $sonoszone[$master][4]);
        } else {
            $volume = s4lox_playlist_normalize_volume($sonoszone[$master][4], 25);
        }
    } else {
        $volume = s4lox_playlist_normalize_volume($sonoszone[$master][4], 25);
    }

    return $volume;
}

?>
