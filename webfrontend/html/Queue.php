<?php

/**
 * Version: QUEUE_ZAPZONE_FALLBACK_LOOP_FIX_V03_2026_06_19
* Function: zap --> checks each zone in network and if playing add current zone as member - NEW zapzone
*
* @param: empty
* @return: 
**/

/*
 * Queue helper functions relocated from Helper.php.
 * Keep the global function names for legacy URL compatibility.
 */





/**
* Function : array_multi_search --> search threw a multidimensionales array for a specific value
* Optional you can search more detailed on a specific key'
* https://sklueh.de/2012/11/mit-php-ein-mehrdimensionales-array-durchsuchen/
*
* @return: array with result
**/

 function array_multi_search($mSearch, $aArray, $sKey = "")
{
    $aResult = array();
    foreach( (array) $aArray as $aValues) {
        if($sKey === "" && in_array($mSearch, $aValues)) $aResult[] = $aValues;
        else 
        if(isset($aValues[$sKey]) && $aValues[$sKey] == $mSearch) $aResult[] = $aValues;
    }
    return $aResult;
}


/**
 * Return a log-safe one-line value.
 */
function s4lox_queue_log_value($value)
{
    return str_replace(["\r", "\n"], ' ', trim((string)$value));
}

/**
 * Return a bounded volume value.
 */
function s4lox_queue_volume($value, $fallback = 0)
{
    if ($value === null || $value === '') {
        return max(0, min(100, (int)$fallback));
    }

    return max(0, min(100, (int)$value));
}

/**
 * Delete one file or symlink safely.
 */
function s4lox_queue_delete_file($file, $label = 'temp file')
{
    $file = (string)$file;

    if ($file === '' || (!file_exists($file) && !is_link($file))) {
        return true;
    }

    if (!is_file($file) && !is_link($file)) {
        LOGWARN("Queue.php: Refusing to delete non-file " . $label . " '" . basename($file) . "'.");
        return false;
    }

    if (!unlink($file)) {
        LOGWARN("Queue.php: Could not delete " . $label . " '" . basename($file) . "'.");
        return false;
    }

    LOGDEB("Queue.php: Deleted " . $label . " '" . basename($file) . "'.");
    return true;
}

/**
 * Delete all files matching a glob pattern safely.
 */
function s4lox_queue_delete_files_by_pattern($pattern, $label)
{
    $files = glob((string)$pattern);

    if (!is_array($files)) {
        LOGWARN("Queue.php: Could not evaluate temp file pattern for " . $label . ".");
        return false;
    }

    foreach ($files as $file) {
        s4lox_queue_delete_file($file, $label);
    }

    return true;
}

/**
 * Write JSON with locking and error handling.
 */
function s4lox_queue_write_json($file, $data, $label = 'JSON file')
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($json === false) {
        LOGWARN("Queue.php: Could not encode " . $label . " as JSON.");
        return false;
    }

    if (file_put_contents((string)$file, $json, LOCK_EX) === false) {
        LOGWARN("Queue.php: Could not write " . $label . " '" . basename((string)$file) . "'.");
        return false;
    }

    return true;
}

/**
 * Write a small marker file with locking and error handling.
 */
function s4lox_queue_write_marker($file, $value, $label = 'marker file')
{
    if (file_put_contents((string)$file, (string)$value, LOCK_EX) === false) {
        LOGWARN("Queue.php: Could not write " . $label . " '" . basename((string)$file) . "'.");
        return false;
    }

    return true;
}

/**
 * Read a JSON file and return an array, or null on error.
 */
function s4lox_queue_read_json_array($file, $label = 'JSON file')
{
    if (!is_file((string)$file)) {
        LOGWARN("Queue.php: " . $label . " does not exist.");
        return null;
    }

    $content = file_get_contents((string)$file);
    if ($content === false) {
        LOGWARN("Queue.php: Could not read " . $label . " '" . basename((string)$file) . "'.");
        return null;
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        LOGWARN("Queue.php: " . $label . " contains invalid JSON.");
        return null;
    }

    return $data;
}

/**
 * Return true when Sonos media info reports a non-empty URI.
 */
function s4lox_queue_has_media_uri($mediaInfo)
{
    return is_array($mediaInfo) && trim((string)($mediaInfo['CurrentURI'] ?? '')) !== '';
}

/**
 * Return true when a zone exists in the current Sonos zone map.
 */
function s4lox_queue_zone_exists($zone)
{
    global $sonoszone;

    return isset($sonoszone[$zone][0]) && trim((string)$sonoszone[$zone][0]) !== '';
}


/**
 * Build a persistent zapzone fallback state.
 *
 * Once all detected playing zones have been visited, zapzone must continue
 * the configured fallback/subfunction on subsequent one-click calls until
 * the normal zap reset timeout expires. This marker prevents the next call
 * from scanning and joining the same playing zone again immediately.
 */
function s4lox_queue_zap_fallback_state($subfunction)
{
    $subfunction = trim((string)$subfunction);
    if ($subfunction === '') {
        $subfunction = 'nextradio';
    }

    return [
        'mode' => 'fallback',
        'subfunction' => $subfunction,
        'started_at' => time(),
    ];
}

/**
 * Return true if the given zapzone state represents active fallback mode.
 */
function s4lox_queue_is_zap_fallback_state($state)
{
    return is_array($state) && (($state['mode'] ?? '') === 'fallback');
}

/**
 * Persist zapzone fallback mode without using an empty state file.
 */
function s4lox_queue_mark_zap_fallback($zapname, $subfunction)
{
    return s4lox_queue_write_json(
        $zapname,
        s4lox_queue_zap_fallback_state($subfunction),
        'zapzone fallback state file'
    );
}







/**
/* Funktion : DeleteTmpFavFilesh --> deletes the Favorite ONE-click Temp files
/*
/* @param: empty                             
/* @return: 
**/

function DeleteTmpFavFiles()
{
    s4lox_queue_delete_files_by_pattern('/run/shm/s4lox_fav*.json', 'favorite temp file');
    s4lox_queue_delete_files_by_pattern('/run/shm/s4lox_pl*.json', 'playlist temp file');
    LOGDEB("Queue.php: All radio, track and playlist temp files have been deleted.");
}


/**
/* Funktion : RampTo --> control volume by diff rampto parameters
/*
/* @param: empty                             
/* @return: 
**/

function RampTo()
{
    global $sonos, $master, $sonoszone;

    if (!isset($_GET['rampto'])) {
        return;
    }

    $rampto = strtolower(trim((string)($_GET['rampto'] ?? '')));
    $allowedRampTypes = [
        'sleep' => 'SLEEP_TIMER_RAMP_TYPE',
        'alarm' => 'ALARM_RAMP_TYPE',
        'auto'  => 'AUTOPLAY_RAMP_TYPE',
    ];

    if (isset($_GET['member'])) {
        LOGWARN("Queue.php: The rampto parameter does not work for members. Member volume has been set to a fixed volume.");
    }

    if (!s4lox_queue_zone_exists($master)) {
        LOGERR("Queue.php: Zone '" . s4lox_queue_log_value($master) . "' is unknown. RampTo has been aborted.");
        return;
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);

    if (!isset($_GET['volume'])) {
        LOGWARN("Queue.php: The rampto parameter '" . s4lox_queue_log_value($rampto) . "' requires a volume parameter.");
        $fallbackVolume = s4lox_queue_volume($sonoszone[$master][4] ?? 0);
        $sonos->SetVolume($fallbackVolume);
        LOGWARN("Queue.php: Standard Sonos volume for zone '" . s4lox_queue_log_value($master) . "' has been used as fallback: " . $fallbackVolume . ".");

        if (!isset($_GET['load'])) {
            try {
                $sonos->Play();
            } catch (Exception $e) {
                LOGERR("Queue.php: Play could not be executed after RampTo fallback: " . $e->getMessage());
            }
        } else {
            LOGINF("Queue.php: Parameter 'load' has been used. Play must be executed separately.");
        }
        return;
    }

    $volume = s4lox_queue_volume($_GET['volume']);
    $zero = isset($_GET['zero']);

    $sonos->SetMute(false);

    if (!isset($allowedRampTypes[$rampto])) {
        LOGWARN("Queue.php: The entered rampto parameter '" . s4lox_queue_log_value($rampto) . "' is not supported.");
        $sonos->SetVolume($volume);
    } else {
        if ($zero) {
            $sonos->SetVolume(0);
        } else {
            $sonos->SetVolume($volume);
        }

        $sonos->RampToVolume($allowedRampTypes[$rampto], $volume);
        LOGDEB("Queue.php: RampTo parameter '" . s4lox_queue_log_value($rampto) . "' for zone '" . s4lox_queue_log_value($master) . "' has been executed with volume " . $volume . ".");
    }

    if (!isset($_GET['load'])) {
        try {
            $sonos->Play();
        } catch (Exception $e) {
            LOGERR("Queue.php: Play could not be executed after RampTo: " . $e->getMessage());
        }
    } else {
        LOGINF("Queue.php: Parameter 'load' has been used. Play must be executed separately.");
    }
}


function zap()
{
    global $sonos, $config, $tmp_tts, $volume, $sonoszone, $master, $zapname, $lbphtmldir, $lbhomedir, $lbpplugindir;

    $zapname = "/run/shm/s4lox_zap_" . $master . ".json";

    if (file_exists($tmp_tts)) {
        LOGWARN("Queue.php: TTS is currently running. Zapzone has been skipped.");
        exit;
    }

    if (!s4lox_queue_zone_exists($master)) {
        LOGERR("Queue.php: Master zone '" . s4lox_queue_log_value($master) . "' is unknown. Zapzone has been aborted.");
        exit;
    }

    $volume = s4lox_queue_volume($volume);
    $sonos->SetVolume($volume);

    if (($config['VARIOUS']['cron'] ?? '') === '') {
        $cronSource = $lbphtmldir . '/src/Core/systemd/cronjob.sh';
        $cronTarget = $lbhomedir . '/system/cron/cron.01min/' . $lbpplugindir;

        if (is_link($cronTarget) || is_file($cronTarget)) {
            s4lox_queue_delete_file($cronTarget, 'zapzone cron symlink');
        }

        if (is_file($cronSource) && !symlink($cronSource, $cronTarget)) {
            LOGWARN("Queue.php: Could not create zapzone cron symlink.");
        }

        s4lox_queue_delete_file($lbhomedir . '/system/cron/cron.03min/' . $lbpplugindir, 'zapzone cron file');
        s4lox_queue_delete_file($lbhomedir . '/system/cron/cron.05min/' . $lbpplugindir, 'zapzone cron file');
        s4lox_queue_delete_file($lbhomedir . '/system/cron/cron.10min/' . $lbpplugindir, 'zapzone cron file');
        s4lox_queue_delete_file($lbhomedir . '/system/cron/cron.30min/' . $lbpplugindir, 'zapzone cron file');
        LOGWARN("Queue.php: Please configure zapzone settings.");
    }

    $subfunction = (($config['VARIOUS']['selfunction'] ?? '') === '') ? 'nextradio' : (string)$config['VARIOUS']['selfunction'];
    $value = substr($subfunction, 0, 4);

    if ($value === 'http') {
        LOGDEB("Queue.php: HTTP subfunction is configured. PlayZapzoneNext() will be called.");
        PlayZapzoneNext();
        exit;
    }

    $state = is_file($zapname) ? s4lox_queue_read_json_array($zapname, 'zapzone state file') : null;

    if (s4lox_queue_is_zap_fallback_state($state)) {
        $fallbackName = (string)($state['subfunction'] ?? $subfunction);
        LOGDEB("Queue.php: Zapzone fallback mode is active. Continuing configured subfunction '" . s4lox_queue_log_value($fallbackName) . "'.");
        DeleteTmpFavFiles();
        PlayZapzoneNext();
        exit;
    }

    if ($state === null || !isset($state['zones'], $state['index'], $state['total']) || !is_array($state['zones'])) {
        LOGDEB("Queue.php: Starting zapzone by scanning playing zones.");

        $playingZones = [];
        foreach ((array)$sonoszone as $zone => $player) {
            if ($zone === $master || !isset($sonoszone[$zone][0])) {
                continue;
            }

            $coordinator = getCoordinator($zone);
            if ($coordinator !== $zone) {
                LOGDEB("Queue.php: Skipping '" . s4lox_queue_log_value($zone) . "' because it is member of group coordinator '" . s4lox_queue_log_value($coordinator) . "'.");
                continue;
            }

            try {
                $s = new SonosAccess($sonoszone[$zone][0]);
                $transportState = $s->GetTransportInfo();
                usleep(200000);
                $posinfo = $s->GetPositionInfo();
                $trackUri = is_array($posinfo) ? (string)($posinfo['TrackURI'] ?? '') : '';
                $isTV = substr($trackUri, 0, 18) === 'x-sonos-htastream:';

                if ($transportState == '1' && !$isTV) {
                    $playingZones[] = $zone;
                }
            } catch (Exception $e) {
                LOGWARN("Queue.php: Could not inspect zone '" . s4lox_queue_log_value($zone) . "' during zapzone scan: " . $e->getMessage());
            }
        }

        if (empty($playingZones)) {
            LOGDEB("Queue.php: No zones are playing. Calling configured subfunction '" . s4lox_queue_log_value($subfunction) . "'.");
            DeleteTmpFavFiles();
            PlayZapzoneNext();
            exit;
        }

        $state = [
            'zones' => $playingZones,
            'index' => 0,
            'total' => count($playingZones),
        ];
        LOGDEB("Queue.php: Found " . $state['total'] . " playing zone(s): " . implode(', ', array_map('s4lox_queue_log_value', $playingZones)) . ".");
    }

    $state['index'] = max(0, (int)$state['index']);
    $state['total'] = count($state['zones']);

    if ($state['index'] >= $state['total']) {
        LOGDEB("Queue.php: All zapzone zones have been visited. Calling configured subfunction '" . s4lox_queue_log_value($subfunction) . "'.");
        s4lox_queue_delete_file($zapname, 'zapzone state file');
        DeleteTmpFavFiles();
        PlayZapzoneNext();
        exit;
    }

    $targetZone = $state['zones'][$state['index']] ?? '';
    $joinedTarget = false;

    if (!s4lox_queue_zone_exists($targetZone)) {
        LOGWARN("Queue.php: Zapzone target zone '" . s4lox_queue_log_value($targetZone) . "' is unknown and will be skipped.");
        array_splice($state['zones'], $state['index'], 1);
        $state['total'] = count($state['zones']);
    } else {
        try {
            $sonos = new SonosAccess($sonoszone[$master][0]);
            $sonos->SetAVTransportURI('x-rincon:' . $sonoszone[$targetZone][1]);
            LOGOK("Queue.php: Zone '" . s4lox_queue_log_value($master) . "' joined zone '" . s4lox_queue_log_value($targetZone) . "' (" . ($state['index'] + 1) . "/" . $state['total'] . ").");
            $state['index']++;
            $joinedTarget = true;
        } catch (Exception $e) {
            LOGWARN("Queue.php: Join to '" . s4lox_queue_log_value($targetZone) . "' failed, removing it from the zapzone list: " . $e->getMessage());
            array_splice($state['zones'], $state['index'], 1);
            $state['total'] = count($state['zones']);
        }
    }

    if ($joinedTarget) {
        /*
         * A successful zapzone join must finish the current request.
         * Even if this was the last available playing zone, the configured
         * fallback/subfunction must only run on the next zapzone request after
         * the state file reports that all targets were already visited.
         */
        sleep(1);
        s4lox_queue_write_json($zapname, $state, 'zapzone state file');
        DeleteTmpFavFiles();
        return true;
    }

    if ($state['index'] >= $state['total']) {
        LOGDEB("Queue.php: All zapzone zones have been skipped. Calling configured subfunction '" . s4lox_queue_log_value($subfunction) . "'.");
        s4lox_queue_delete_file($zapname, 'zapzone state file');
        DeleteTmpFavFiles();
        PlayZapzoneNext();
        exit;
    }

    sleep(1);
    s4lox_queue_write_json($zapname, $state, 'zapzone state file');
    DeleteTmpFavFiles();
    return true;
}


/**
* Function : FuncZapzone --> load and play dependend on saved ZAPZONE function
* 
* 
* @param: empty
* @return: play Favorite
**/

function PlayZapzoneNext()
{
    global $sonos, $config, $volume, $zapname, $sonoszone, $master;

    $selfunction = trim((string)($config['VARIOUS']['selfunction'] ?? ''));
    $value = substr($selfunction, 0, 4);
    $fallbackSubfunction = ($selfunction === '') ? 'nextradio' : $selfunction;

    if (!s4lox_queue_zone_exists($master)) {
        LOGERR("Queue.php: Master zone '" . s4lox_queue_log_value($master) . "' is unknown. Zapzone subfunction has been aborted.");
        return false;
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);
    $sonos->BecomeCoordinatorOfStandaloneGroup();
    LOGDEB("Queue.php: Zone '" . s4lox_queue_log_value($master) . "' has been made a standalone zone.");

    if ($selfunction === 'nextradio' || $selfunction === '') {
        nextradio();
        s4lox_queue_mark_zap_fallback($zapname, $fallbackSubfunction);
        return 'nextradio';
    }

    if ($selfunction === 'trackfavorites') {
        PlayTrackFavorites();
        s4lox_queue_mark_zap_fallback($zapname, $fallbackSubfunction);
        return 'trackfavorites';
    }

    if ($selfunction === 'playlistfavorites') {
        PlayPlaylistFavorites();
        s4lox_queue_mark_zap_fallback($zapname, $fallbackSubfunction);
        return 'playlistfavorites';
    }

    if ($selfunction === 'radiofavorites') {
        PlayRadioFavorites();
        s4lox_queue_mark_zap_fallback($zapname, $fallbackSubfunction);
        return 'radiofavorites';
    }

    if ($selfunction === 'tuneinfavorites') {
        PlayTuneInPlaylist();
        s4lox_queue_mark_zap_fallback($zapname, $fallbackSubfunction);
        return 'tuneinfavorites';
    }

    if ($value === 'http') {
        $radioEntries = array_values((array)($config['RADIO']['radio'] ?? []));
        $savedzap = is_file($zapname) ? s4lox_queue_read_json_array($zapname, 'zapzone state file') : [];

        $alreadyStreaming = is_array($savedzap) && count($savedzap) !== 0 && (
            (($savedzap[0] ?? '') === $selfunction) ||
            (s4lox_queue_is_zap_fallback_state($savedzap) && (($savedzap['subfunction'] ?? '') === $selfunction))
        );

        if ($alreadyStreaming) {
            LOGDEB("Queue.php: Configured plugin radio favorite is already streaming. Nothing to do.");
            exit;
        }

        foreach ($radioEntries as $entry) {
            $parts = explode(',', (string)$entry, 3);
            if (count($parts) < 2) {
                LOGWARN("Queue.php: Invalid plugin radio entry skipped during zapzone subfunction.");
                continue;
            }

            $stationName = trim($parts[0]);
            $stationUrl = trim($parts[1]);
            $stationMeta = trim($parts[2] ?? '');

            if ($stationUrl !== $selfunction) {
                continue;
            }

            $sonos = new SonosAccess($sonoszone[$master][0]);
            $sonos->SetRadio('x-rincon-mp3radio://' . $stationUrl, $stationName, $stationMeta);
            $sonos->SetVolume(s4lox_queue_volume($volume));
            $sonos->Play();
            s4lox_queue_mark_zap_fallback($zapname, $fallbackSubfunction);
            LOGDEB("Queue.php: Subfunction plugin radio favorite has been called.");
            return 'pluginradio';
        }

        LOGWARN("Queue.php: Configured plugin radio favorite URL was not found in the radio configuration.");
        return false;
    }

    nextradio();
    s4lox_queue_mark_zap_fallback($zapname, $fallbackSubfunction);
    LOGWARN("Queue.php: Unknown zapzone subfunction. Please configure zapzone or follow settings in the Sonos plugin options.");
    return 'nextradio';
}

/**
* Function : PlayFavorite --> load and play specified Sonos Favorite (Radio/Track/Playlist)
* 
* 
* @param: empty
* @return: play Favorite
**/

function PlayFavorite()
{
    global $sonos, $volume, $profile_details, $sonoszone, $re, $master, $favtmp;

    if (isset($_GET['member']) && isset($_GET['profile'])) {
        $master = GROUPMASTER;
    } elseif (isset($_GET['profile'])) {
        $master = GROUPMASTER;
    } else {
        $master = MASTER;
    }

    if (!s4lox_queue_zone_exists($master)) {
        LOGERR("Queue.php: Master zone '" . s4lox_queue_log_value($master) . "' is unknown. Favorite playback has been aborted.");
        exit;
    }

    if (isset($_GET['member']) && trim((string)$_GET['member']) !== '') {
        // CreateMember is normally already called by sonos.php. This is idempotent.
        SyncGroupForPlaybackToMember();
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);

    // If a playlist favorite is already loaded, iterate through its tracks.
    if (file_exists($favtmp)) {
        LOGINF("Queue.php: Playlist favorite marker exists. Continuing playlist iteration.");
        $currentPlaylist = $sonos->GetCurrentPlaylist();
        $countqueue = is_array($currentPlaylist) ? count($currentPlaylist) : 0;
        $currtrack = $sonos->GetPositioninfo();
        $trackNumber = is_array($currtrack) ? (int)($currtrack['Track'] ?? 0) : 0;

        if ($trackNumber !== $countqueue) {
            NextTrack();
            return true;
        }

        s4lox_queue_delete_file($favtmp, 'favorite playlist marker');
        if (isset($_GET['member'])) {
            removemember();
            LOGINF("Queue.php: Member has been removed.");
        }
        LOGINF("Queue.php: Last track has been played and playlist marker has been deleted.");
        LOGOK("Queue.php: Playlist favorite loop ended. Starting from the beginning.");
    }

    $favoriteRaw = trim((string)($_GET['favorite'] ?? ''));
    if ($favoriteRaw === '') {
        LOGERR("Queue.php: Missing favorite parameter. Correct syntax is: &action=playfavorite&favorite=TITLE.");
        exit;
    }

    $favorite = mb_strtolower($favoriteRaw);
    $favoriteLog = s4lox_queue_log_value(urldecode($favoriteRaw));

    $favorites = AddDetailsToMetadata();
    if (!is_array($favorites) || count($favorites) < 1) {
        LOGWARN("Queue.php: No Sonos favorites are available.");
        exit;
    }

    foreach ($favorites as $idx => $item) {
        $favorites[$idx]['title'] = mb_strtolower((string)($favorites[$idx]['title'] ?? ''));
    }

    $re = [];
    foreach ($favorites as $item) {
        if ($favorite === ($item['title'] ?? '')) {
            $re[] = array_multi_search($favorite, $favorites, 'title');
        }
    }

    if (count($re) > 1) {
        LOGERR("Queue.php: Favorite '" . $favoriteLog . "' has more than one hit. Please specify it more precisely.");
        exit;
    }

    if (count($re) < 1 || empty($re[0][0]) || !is_array($re[0][0])) {
        LOGWARN("Queue.php: Favorite '" . $favoriteLog . "' was not found.");
        exit;
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);
    $sonos->Stop();
    $sonos->SetQueue('x-rincon-queue:' . trim($sonoszone[$master][1]) . '#0');
    $sonos->SetGroupMute(false);
    $sonos->SetPlayMode('0'); // NORMAL

    if (isset($_GET['profile']) || isset($_GET['Profile'])) {
        $volume = s4lox_queue_volume($profile_details[0]['Player'][$master][0]['Volume'] ?? $volume);
    } elseif (isset($_GET['member'])) {
        volume_group();
        $sonos = new SonosAccess($sonoszone[$master][0]);
    } else {
        $volume = s4lox_queue_volume($volume);
    }

    $sonos->ClearQueue();
    LOGINF("Queue.php: Settings to play the favorite have been prepared.");

    try {
        $proof = metadata($re[0][0]);
        if ($proof === false) {
            LOGWARN("Queue.php: Metadata preparation for favorite '" . $favoriteLog . "' failed.");
            exit;
        }

        if (isset($_GET['rampto']) && !file_exists($favtmp)) {
            RampTo();
        } else {
            $sonos->SetVolume($volume);
        }

        $sonos->Play();
        $currentPlaylist = $sonos->GetCurrentPlaylist();
        if (is_array($currentPlaylist) && count($currentPlaylist) > 1) {
            s4lox_queue_write_marker($favtmp, 'running', 'favorite playlist marker');
            LOGINF("Queue.php: Playlist favorite has been identified. Marker file has been saved.");
        }
        LOGOK("Queue.php: Favorite '" . $favoriteLog . "' has been loaded and is playing.");
    } catch (Exception $e) {
        LOGERR("Queue.php: Favorite '" . $favoriteLog . "' seems not to be valid: " . $e->getMessage());
        exit;
    }
}


/**
* Function : GetFavorites --> prepare list of Sonos favorites
* 
* 
* @param: empty
* @return: list of all favorites
**/

function GetFavorites() 
{
	global $sonos;
	
	$tes = $sonos->GetFavorites();
	echo "Only Tracks and Radio Stations are supported, no Albums/playlists, except for fucntion 'playfavorite@favorite=TITLE'";
	echo "Fuzzy Logic search is possible by Title/Playlist or Radio Station";
	echo "<br>";
	echo "<br>";
	print_r($tes);
	LOGOK("Queue.php: Your list of Sonos favorites has been successful loaded. Playlists are excluded");
}


/**
* Function : PlayAllFavorites --> load and play Sonos favorites (only Tracks/Radio)
* 
* 
* @param: empty
* @return: list of all favorites except Album/Playlists
**/

function PlayAllFavorites() 
{
	
	global $sonos, $volume, $value, $profile_details, $sonoszone, $master, $services, $radiofav, $radiolist, $queuetmp, $radiofavtmp;
		
	if (count($sonos->GetFavorites()) < 1)    {
		LOGWARN("Queue.php: No Sonos Favorites are maintained.");
		exit;
	}
	$save = false;
	if (isset($_GET['member']) && trim($_GET['member']) !== '') {
        // CreateMember wurde i.d.R. schon in sonos.php aufgerufen – ist idempotent
        SyncGroupForPlaybackToMember();
    }
	# 1st click/execution
	if (!file_exists($queuetmp))  {
		#$check_stat = getZoneStatus($master);
		#if ($check_stat != (string)"single")  {
			#$sonos->BecomeCoordinatorOfStandaloneGroup();
			#LOGOK("Queue.php: Zone ".$master." has been ungrouped.");
		#}
		DeleteTmpFavFiles();
		$sonos->ClearQueue();
		LOGDEB("Queue.php: Queue has been deleted");
		$save = true;
		s4lox_queue_write_json($queuetmp, "cleared", 'queue marker');
	}
	if (file_exists($radiofav))  {
		try {
			if (!file_exists($radiofavtmp))  {
				# as long as we tracks iterate through
				NextTrack();
				LOGINF ("Queue.php: Favorite Tracks are running");
			} else {
				# create Failure in case Radio Playlist is loaded to catch exception
				$sonos->Rewind();
				LOGDEB ("Queue.php: Fake Function has been executed in order to create temp error");
			}
		} catch (Exception $e) {
			# clear current queue
			$sonos->ClearQueue();
			LOGINF ("Queue.php: Current Queue has been deleted");
			# load previously saved radio Stations
			$value = s4lox_queue_read_json_array($radiofav, 'radio favorites temp file');
			if ($value === null) {
				DeleteTmpFavFiles();
				return;
			}
			LOGOK ("Queue.php: Your Radio Favorites has been loaded");
			# add Radio Station
			if (count($value) >= 1)  {
				metadata($value[0]);
				LOGOK ("Queue.php: Radio Favorite '".$value[0]['title']."' has been added and is playing");
			}
			if (isset($_GET['profile']) or isset($_GET['Profile']))    {
				$volume = $profile_details[0]['Player'][$master][0]['Volume'];
			} elseif (isset($_GET['member'])) {
				volume_group();
				$sonos = new SonosAccess($sonoszone[$master][0]);
			}
			if(isset($_GET['rampto']) and $save == false)  {
				RampTo();
			} else {
				$sonos->SetVolume($volume);
			}
			# check addionally if Radio Station has been loaded
			$mediainfo = $sonos->GetMediaInfo();
			if ($mediainfo['CurrentURI'] != "")  {
				try {
					$sonos->Play();
					# remove 1st element of array
					array_shift($value);
					LOGINF ("Queue.php: Current playing Radio Favorite has been removed from array.");
				} catch (Exception $e) {
					# remove 1st element of array
					array_shift($value);
					LOGINF ("Queue.php: Radio Favorite has been removed from array  (Loading failed)");
				}
			}
			# check array if NULL
			if (count($value) > 0)  {
				# save new array
				LOGOK ("Queue.php: New Radio Favorite array has been saved");
				$radiofavarray = s4lox_queue_write_json($radiofav, $value, 'radio favorites temp file');
				$radiofavtmp = s4lox_queue_write_json($radiofavtmp, "Radio", 'radio favorites marker');
			} else {
				# if last element loaded delete files
				if(isset($_GET['member'])) {
					#removemember();
					LOGINF ("Queue.php: Member has been removed");
				}
				s4lox_queue_delete_file($radiofav, 'radio favorites temp file');
				s4lox_queue_delete_file($radiofavtmp, 'radio favorites marker');
				s4lox_queue_delete_file($queuetmp, 'queue marker');
				LOGINF ("Queue.php: Files has been deleted");
				LOGOK ("Queue.php: ** Loop ended, we start from beginning **");
			}
		}
		return;
	} 
	#echo "Count: ".count($sonos->GetCurrentPlaylist());
	try {
		if (count($sonos->GetCurrentPlaylist()) > 0 )  {
			NextTrack();
		} else {
			# create Failure in case Radio Playlist is already loaded in order to catch exception
			$sonos->Rewind();
			LOGINF ("Queue.php: Error produced in order to catch exception!");
		}
	} catch (Exception $e) {
		$single = "Track";
		$radio = "Radio";	
		$pl = "Playlist";
		$tes = AddDetailsToMetadata();
		$track 		= array_multi_search($single, $tes);
		$radio 		= array_multi_search($radio, $tes);
		$playlist 	= array_multi_search($pl, $tes);
		if ((count($playlist) > 0))    {
			LOGINF ("Queue.php: Playlist Favorites/Album are currently not supported!");
		}
		LOGOK ("Queue.php: Your Favorites has been identified");
		# Prepare Play
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		$sonos->Stop();
		$sonos->ClearQueue();
		$sonos->SetGroupMute(false);
		$sonos->SetPlayMode('0'); // NORMAL
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $profile_details[0]['Player'][$master][0]['Volume'];
		} 
		$sonos->SetVolume($volume);
		LOGINF("Queue.php: Settings to play your favorite has been prepared!");
		$shift = false;
		# Select 1st favorite 
		if ((count($track) > 0))    {
			$value = $track[0];
			# Load 1st favorite 
			$proof = metadata($value);
			if ($proof === true)   {
				LOGOK ("Queue.php: First Favorite Track has been loaded");
				# Set variable true
				$shift = true;
			} else {
				LOGOK ("Queue.php: First Favorite Track could not be loaded");
				# Set variable true
				$shift = true;
			}
			# remove loaded favorite from array
			array_shift($track);
			if (count($sonos->GetCurrentPlaylist()) > 0 )  {
				$sonos->Play();
				LOGDEB ("Queue.php: First Favorite Track is playing");
				LOGINF ("Queue.php: Currently playing favorite has been removed from array");
			} else {
				$value = $track[0];
				LOGDEB ("Queue.php: Just one track has been identified.");
			}
			$base_array = $track;
			if (count($base_array) > 0)   {
				# ...then add rest of favorites
				LOGINF("Queue.php: More then one track has been identified, prepare load of remaining!");
				foreach ($base_array as $key => $value)   {
					# Load all favorites
					metadata($value);
				}
			}		
		LOGOK ("Queue.php: All Favorite tracks has been loaded");
		}
		if (count($radio) > 0)   {
			s4lox_queue_write_json($radiofav, $radio, 'radio favorites temp file');
			LOGINF ("Queue.php: File including all Radio Stations has been saved.");
		}
		# only if Radiostations are in the Favorites
		if ($shift === false and count($radio) > 0)    {
			$value = $radio[0];
			metadata($value);
			LOGOK ("Queue.php: First Favorite Radio has been loaded");
			$sonos->Play();
			array_shift($radio);
			LOGDEB ("Queue.php: First Favorite Radio is playing");
			LOGINF ("Queue.php: Currently playing favorite has been removed from array");
			s4lox_queue_write_json($radiofav, $radio, 'radio favorites temp file');
			LOGINF ("Queue.php: File including all Radio Stations has been saved.");
		}		
	}
}


/**
* Function : PlayTrackFavorites --> load and play Sonos track favorites
* 
* 
* @param: empty
* @return: 
**/

function PlayTrackFavorites() 
{
	global $sonos, $volume, $profile_details, $value, $sonoszone, $master, $queuetracktmp;
	
	#CreateMember();
	#$sonos = new SonosAccess($sonoszone[$master][0]);
	
	$browse = AddDetailsToMetadata();
	$browseTracks = count($browse);
		
	if ($browseTracks < 1)    {
		LOGWARN("Queue.php: No Sonos Favorites are maintained.");
		exit;
	}
	$filter = "Track";
	$tracks = array_multi_search($filter, $browse);
	if (count($tracks) < 1)    {
		LOGWARN("Queue.php: No Sonos Track Favorites are maintained.");
		exit;
	}
	# 1st click/execution
	if (!file_exists($queuetracktmp))  {
		if (isset($_GET['member']) && isset($_GET['profile'])) {
			$master = GROUPMASTER;
		} elseif (isset($_GET['profile']))   {
			$master = GROUPMASTER;	
		} else {
			$master = MASTER;
		}
		if (isset($_GET['member']) && trim($_GET['member']) !== '') {
			// CreateMember wurde i.d.R. schon in sonos.php aufgerufen – ist idempotent
			SyncGroupForPlaybackToMember();
		}
		#CreateMember();
		$sonos = new SonosAccess($sonoszone[$master][0]);
		#$check_stat = getZoneStatus($master);
		#if ($check_stat != (string)"single")  {
		#	$sonos->BecomeCoordinatorOfStandaloneGroup();
		#	LOGOK("Queue.php: Zone ".$master." has been ungrouped.");
		#}
		#if(isset($_GET['member'])) {
		#	AddMemberTo();
		#	$sonos = new SonosAccess($sonoszone[$master][0]);
		#	LOGINF ("Queue.php: Requested Member has been added");
		#}
		DeleteTmpFavFiles();
		$sonos->ClearQueue();
		LOGDEB("Queue.php: Queue has been deleted");
	}
	
	
	
	if (file_exists($queuetracktmp))  {
		$countqueue = count($sonos->GetCurrentPlaylist());
		$currtrack = $sonos->GetPositioninfo();
		if ($currtrack['Track'] < $countqueue)    {
			NextTrack();
			return true;
		} else {
			s4lox_queue_delete_file($queuetracktmp, 'track favorites marker');
			if(isset($_GET['member'])) {
				#removemember();
				#LOGINF ("Queue.php: Member has been removed");
			}
			LOGINF ("Queue.php: File has been deleted");
			LOGOK ("Queue.php: ** Loop ended, we start from beginning **");
		}
	}
	LOGDEB ("Queue.php: ** Loop Favorite Tracks started from scratch**");
	$single = "Track";
	$tes = AddDetailsToMetadata();
	#print_r($tes);
	$track 		= array_multi_search($single, $tes);
	LOGOK ("Queue.php: Your Track Favorites has been identified");
	# Prepare Play
	$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
	$sonos->Stop();
	$sonos->ClearQueue();
	#$sonos->SetGroupMute(false);
	$sonos->SetPlayMode('0'); // NORMAL
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $profile_details[0]['Player'][$master][0]['Volume'];
	} elseif (isset($_GET['member'])) {
		volume_group();
		$sonos = new SonosAccess($sonoszone[$master][0]);
	}
	LOGINF("Queue.php: Settings to play your favorite has been prepared!");
	$shift = false;
	# Select 1st favorite 
	if ((count($track) > 0))    {
		$value = $track[0];
		# Load first favorite.
		$proof = metadata($value);
		if ($proof === false) {
			array_shift($track);
			LOGINF("Queue.php: Favorite track could not be loaded and has been removed from the track list.");
			if (count($track) < 1) {
				LOGWARN("Queue.php: No further track favorites are available after removing the failed item.");
				return false;
			}
			LOGOK("Queue.php: Next favorite track will be loaded.");
			$sonos->ClearQueue();
			$value = $track[0];
			$proof = metadata($value);
			if ($proof === false) {
				LOGWARN("Queue.php: Next favorite track could not be loaded either.");
				return false;
			}
		}
		LOGOK("Queue.php: First track favorite has been loaded.");
		# Remove loaded favorite from array.
		array_shift($track);
		# Set variable for ClearQueue
		$shift = true;
		if (count($sonos->GetCurrentPlaylist()) > 0 )  {
			$sonos->Play();
			sleep(2);
			if(isset($_GET['rampto']) and !file_exists($queuetracktmp)) {
				RampTo();
			} else {
				$sonos->SetVolume($volume);
			}
			LOGDEB ("Queue.php: First Favorite Track is playing");
			LOGINF ("Queue.php: Currently playing Track favorite has been removed from array");
		} else {
			$value = $track[0];
			LOGDEB ("Queue.php: Just one track has been identified.");
		}
		$base_array = $track;
		if (count($base_array) > 0)   {
			LOGINF("Queue.php: More then one track has been identified, prepare load of remaining!");
			# ...then add rest of favorites
			foreach ($base_array as $key => $value)   {
				# Load all favorites
				$proof = metadata($value);
			}
		}
	}
	LOGOK("Queue.php: All Track Favorites has been loaded");
	s4lox_queue_write_json($queuetracktmp, "cleared", 'track favorites marker');	
}



/**
* Function : PlayRadioFavorites --> load and play Sonos Radio favorites
* 
* 
* @param: empty
* @return: 
**/

function PlayRadioFavorites() 
{
	global $sonos, $volume, $profile_details, $value, $profile_details, $sonoszone, $master, $queueradiotmp;
	
	
	$browse = AddDetailsToMetadata();
	$browseRadio = count($browse);
	$save = false;
	if ($browseRadio < 1)    {
		LOGWARN("Queue.php: No Sonos Favorites are maintained.");
		exit;
	}
	$filter = "Radio";
	$radios = array_multi_search($filter, $browse);
	if (count($radios) < 1)    {
		LOGWARN("Queue.php: No Sonos Radio Station Favorites are maintained.");
		exit;
	}
	
	# 1st click/execution
	if (!file_exists($queueradiotmp))  {
		if (isset($_GET['member']) && isset($_GET['profile'])) {
			$master = GROUPMASTER;
		} elseif (isset($_GET['profile']))   {
			$master = GROUPMASTER;	
		} else {
			$master = MASTER;
		}
		if (isset($_GET['member']) && trim($_GET['member']) !== '') {
			// CreateMember wurde i.d.R. schon in sonos.php aufgerufen – ist idempotent
			SyncGroupForPlaybackToMember();
		}
		#$check_stat = getZoneStatus($master);
		#if ($check_stat != (string)"single")  {
			#$sonos->BecomeCoordinatorOfStandaloneGroup();
			#LOGOK("Queue.php: Zone ".$master." has been ungrouped.");
		#}
		#echo "olli";
		#CreateMember();
		$sonos = new SonosAccess($sonoszone[$master][0]);
		LOGOK ("Queue.php: Your Radio Favorites has been identified");
		DeleteTmpFavFiles();
		$sonos->ClearQueue();
		LOGDEB("Queue.php: Queue has been deleted");
		$save = true;
		s4lox_queue_write_json($queueradiotmp, $radios, 'radio favorites queue file');
		LOGINF ("Queue.php: File including all Radio Stations has been saved.");
	} 
	if (file_exists($queueradiotmp))  {
		# load previously saved radio Stations
		$value = s4lox_queue_read_json_array($queueradiotmp, 'radio favorites queue file');
		if ($value === null) {
			return;
		}
		LOGOK ("Queue.php: Your Radio Favorites has been loaded");
		# add Radio Station
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		if (count($value) >= 1)  {
			$proof = metadata($value[0]);
		}
		if ($proof === false) {
			array_shift($value);
			LOGINF("Queue.php: Favorite radio could not be loaded and has been removed.");
			$sonos->ClearQueue();
			if (count($value) >= 1) {
				LOGOK("Queue.php: Next favorite radio will be loaded.");
				$proof = metadata($value[0]);
			} else {
				LOGWARN("Queue.php: No further radio favorites are available after removing the failed item.");
			}
		}
		$sonos->SetGroupMute(false);
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $profile_details[0]['Player'][$master][0]['Volume'];
		} elseif (isset($_GET['member'])) {
			volume_group();
			$sonos = new SonosAccess($sonoszone[$master][0]);
		}
		LOGINF("Queue.php: Settings to play your favorite has been prepared!");
		# check addionally if Radio Station has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				$sonos->Play();
				if(isset($_GET['rampto']) and $save == true)  {
					RampTo();
				} else {
					$sonos->SetVolume($volume);
				}
				# remove 1st element of array
				array_shift($value);
				LOGINF ("Queue.php: Radio Favorite has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGWARN ("Queue.php: PlayRadio: Radio Favorite has been removed from array (Loading failed)");
			}
		}
		
		# check array if NULL
		if (count($value) > 0)  {
			# save new array
			$radiofavarray = s4lox_queue_write_json($queueradiotmp, $value, 'radio favorites queue file');
			LOGOK ("Queue.php: New Radio Favorite array has been saved");
		} else {
			# if last element loaded delete files
			if(isset($_GET['member'])) {
				#removemember();
				LOGINF ("Queue.php: Member has been removed");
			}
			s4lox_queue_delete_file($queueradiotmp, 'radio favorites queue file');
			LOGINF ("Queue.php: Files has been deleted");
			LOGOK ("Queue.php: ** Loop ended, we start from beginning **");
		}
	} else {
		LOGWARN ("Queue.php: Radio Stations File could not be loaded!");
		#exit;
	}
	#if ($save == true)    {
	#	file_put_contents($queueradiotmp, json_encode($radios));
	#}
}


/**
* Function : PlaySonosPlaylist --> load and play Sonos Playlists
* 
* 
* @param: empty
* @return: 
**/

function PlaySonosPlaylist() 
{
	global $sonos, $volume, $profile_details, $value, $sonoszone, $master, $pltmp;
	
	$browse = $sonos->BrowseContentDirectory("SQ:","BrowseDirectChildren");
			$browseRadio = count($browse);
			
			if ($browseRadio < 1)    {
				LOGWARN("Queue.php: No Sonos Playlists are maintained.");
				exit;
			}
			# add Service and sid
			foreach ($browse as $key => $value)  {
				$browse[$key]['Service'] = "Sonos Playlist";
				$browse[$key]['sid'] = "998";
			}
			
			# 1st click/execution
			if (!file_exists($pltmp))  {
				#$check_stat = getZoneStatus($master);
				#if ($check_stat != (string)"single")  {
					#$sonos->BecomeCoordinatorOfStandaloneGroup();
					#LOGOK("Queue.php: Zone ".$master." has been ungrouped.");
				#}
				#if(isset($_GET['member'])) {
				#	AddMemberTo();
				#	$sonos = new SonosAccess($sonoszone[$master][0]);
				#	LOGINF ("Queue.php: Member has been added");
				#}
				if (isset($_GET['member']) && isset($_GET['profile'])) {
					$master = GROUPMASTER;
				} elseif (isset($_GET['profile']))   {
					$master = GROUPMASTER;	
				} else {
					$master = MASTER;
				}
				if (isset($_GET['member']) && trim($_GET['member']) !== '') {
					// CreateMember wurde i.d.R. schon in sonos.php aufgerufen – ist idempotent
					SyncGroupForPlaybackToMember();
				}
				#CreateMember();
				$sonos = new SonosAccess($sonoszone[$master][0]);
				LOGOK ("Queue.php: Your Radio Favorites has been identified");
				DeleteTmpFavFiles();
				$sonos->ClearQueue();
				LOGDEB("Queue.php: Queue has been deleted");
				s4lox_queue_write_json($pltmp, $browse, 'Sonos playlist temp file');
				LOGINF ("Queue.php: File including all Playlists has been saved.");
			} 
			
	if (file_exists($pltmp))  {
		# load previously saved Sonos Playlist
		$value = s4lox_queue_read_json_array($pltmp, 'Sonos playlist temp file');
		if ($value === null) {
			exit;
		}
		#print_r($value);
		LOGOK ("Queue.php: Your Sonos Playlists has been loaded");
		# add Playlist
		$sonos->ClearQueue();
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		if (count($value) >= 1)  {
			$proof = metadata($value[0]);
		}
		if ($proof === false) {
			array_shift($value);
			LOGINF("Queue.php: Sonos playlist could not be loaded and has been removed.");
			$sonos->ClearQueue();
			if (count($value) >= 1) {
				LOGOK("Queue.php: Next Sonos playlist will be loaded.");
				$proof = metadata($value[0]);
			} else {
				LOGWARN("Queue.php: No further Sonos playlists are available after removing the failed item.");
			}
		}
		$sonos->SetGroupMute(false);
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $profile_details[0]['Player'][$master][0]['Volume'];
		} elseif (isset($_GET['member'])) {
			volume_group();
			$sonos = new SonosAccess($sonoszone[$master][0]);
		}
		#$sonos->SetVolume($volume);
		LOGINF("Queue.php: Settings to play your playlist has been prepared!");
		# check addionally if Playlist has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				$sonos->Play();
				if(isset($_GET['rampto']) and !file_exists($pltmp))  {
					RampTo();
				} else {
					$sonos->SetVolume($volume);
				}
				# remove 1st element of array
				array_shift($value);
				LOGINF ("Queue.php: Sonos Playlist has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGWARN ("Queue.php: Sonos Playlist has been removed from array (Loading failed)");
			}
		}
		# check array if NULL
		if (count($value) > 0)  {
			# save new array
			$radiofavarray = s4lox_queue_write_json($pltmp, $value, 'Sonos playlist temp file');
			LOGOK ("Queue.php: New Sonos Playlists array has been saved");
		} else {
			# if last element loaded delete files
			if(isset($_GET['member'])) {
				#removemember();
				LOGINF ("Queue.php: Member has been removed");
			}
			s4lox_queue_delete_file($pltmp, 'Sonos playlist temp file');
			LOGINF ("Queue.php: File has been deleted");
			LOGOK ("Queue.php: ** Loop ended, we start from beginning **");
		}
	} else {
		LOGWARN ("Queue.php: Sonos Playlists File could not be loaded!");
		exit;
	}
}

/**
* Function : PlayTuneInPlaylist --> load and play TuneIn Radio Favorites - OBSOLETE -
* 
* 
* @param: empty
* @return: 
**/

function PlayTuneInPlaylist() 
{
	global $sonos, $volume, $profile_details, $value, $sonoszone, $master, $tuneinradiotmp;
	
	$browse = $sonos->BrowseContentDirectory("R:0/0","BrowseDirectChildren");
			$browseRadio = count($browse);
		
			if ($browseRadio < 1)    {
				LOGWARN("Queue.php: No TuneIn Radio Favorites in 'My Radiostations' are maintained.");
				exit;
			}
			# add Service and sid
			foreach ($browse as $key => $value)  {
				$browse[$key]['Service'] = "TuneIn";
				$browse[$key]['sid'] = "254";
			}
			# 1st click/execution
			if (!file_exists($tuneinradiotmp))  {
				$check_stat = getZoneStatus($master);
				if ($check_stat != (string)"single")  {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGOK("Queue.php: Zone ".$master." has been ungrouped.");
				}
				#if(isset($_GET['member'])) {
				#	AddMemberTo();
				#	$sonos = new SonosAccess($sonoszone[$master][0]);
				#	LOGINF ("Queue.php: Member has been added");
				#}
				LOGOK ("Queue.php: Your TuneIn Favorite Radio Station has been identified");
				DeleteTmpFavFiles();
				$sonos->ClearQueue();
				LOGDEB("Queue.php: Queue has been deleted");
				s4lox_queue_write_json($tuneinradiotmp, $browse, 'TuneIn favorites temp file');
				LOGINF ("Queue.php: File including all TuneIn Favorite Radio Stations has been saved.");
			} 
	
	if (file_exists($tuneinradiotmp))  {
		# load previously saved radio Stations
		$value = s4lox_queue_read_json_array($tuneinradiotmp, 'TuneIn favorites temp file');
		if ($value === null) {
			exit;
		}
		LOGOK ("Queue.php: Your TuneIn Radio Favorites has been loaded");
		# add Radio Station
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		if (count($value) >= 1)  {
			$proof = metadata($value[0]);
		}
		if ($proof === false) {
			array_shift($value);
			LOGINF("Queue.php: Favorite TuneIn station could not be loaded and has been removed.");
			$sonos->ClearQueue();
			if (count($value) >= 1) {
				LOGOK("Queue.php: Next favorite TuneIn station will be loaded.");
				$proof = metadata($value[0]);
			} else {
				LOGWARN("Queue.php: No further TuneIn favorites are available after removing the failed item.");
			}
		}
		$sonos->SetGroupMute(false);
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $profile_details[0]['Player'][$master][0]['Volume'];
		} 
		#$sonos->SetVolume($volume);
		LOGINF("Queue.php: Settings to play your TuneIn Radio Station has been prepared!");
		# check addionally if Radio Station has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				$sonos->Play();
				if(isset($_GET['rampto']) and !file_exists($tuneinradiotmp))  {
					RampTo();
				} else {
					$sonos->SetVolume($volume);
				}
				# remove 1st element of array
				array_shift($value);
				LOGINF ("Queue.php: TuneIn Favorite has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGWARN ("Queue.php: TuneIn Favorite has been removed from array (Loading failed)");
			}
		}
		# check array if NULL
		if (count($value) > 0)  {
			# save new array
			$radiofavarray = s4lox_queue_write_json($tuneinradiotmp, $value, 'TuneIn favorites temp file');
			LOGOK ("Queue.php: New TuneIn Favorite array has been saved");
		} else {
			# if last element loaded delete files
			if(isset($_GET['member'])) {
				#removemember();
				LOGINF ("Queue.php: Member has been removed");
			}
			s4lox_queue_delete_file($tuneinradiotmp, 'TuneIn favorites temp file');
			LOGINF ("Queue.php: Files has been deleted");
			LOGOK ("Queue.php: ** Loop ended, we start from beginning **");
		}
	} else {
		LOGWARN ("Queue.php: TuneIn Stations File could not be loaded!");
		exit;
	}
}



/**
* Function : PlayPlaylistFavorite --> load and play Sonos Favorites Playlists
* 
* 
* @param: empty
* @return: 
**/

function PlayPlaylistFavorites()
{
	global $sonos, $volume, $profile_details, $value, $sonoszone, $master, $queuepltmp;
	
	$browse = AddDetailsToMetadata();
			$browseRadio = count($browse);
			#print_r($browse);
		
			if ($browseRadio < 1)    {
				LOGWARN("Queue.php: No Sonos Favorites are maintained.");
				exit;
			}
			$filter = "Playlist";
			$radios = array_multi_search($filter, $browse);
			if (count($radios) < 1)    {
				LOGWARN("Queue.php: No Sonos Playlist Favorites are maintained.");
				exit;
			}
			#print_r($radios);
			# 1st click/execution
			if (!file_exists($queuepltmp))  {
				if (isset($_GET['member']) && isset($_GET['profile'])) {
					$master = GROUPMASTER;
				} elseif (isset($_GET['profile']))   {
					$master = GROUPMASTER;	
				} else {
					$master = MASTER;
				}
				if (isset($_GET['member']) && trim($_GET['member']) !== '') {
					// CreateMember wurde i.d.R. schon in sonos.php aufgerufen – ist idempotent
					SyncGroupForPlaybackToMember();
				}
				#$check_stat = getZoneStatus($master);
				#if ($check_stat != (string)"single")  {
				#	$sonos->BecomeCoordinatorOfStandaloneGroup();
				#	LOGOK("Queue.php: Zone ".$master." has been ungrouped.");
				#}
				#if(isset($_GET['member'])) {
				#	AddMemberTo();
				#	$sonos = new SonosAccess($sonoszone[$master][0]);
				#	LOGINF ("Queue.php: Member has been added");
				#}
				#CreateMember();
				$sonos = new SonosAccess($sonoszone[$master][0]);
				LOGOK ("Queue.php: Your Radio Favorites has been identified");
				DeleteTmpFavFiles();
				$sonos->ClearQueue();
				LOGDEB("Queue.php: Queue has been deleted");
				s4lox_queue_write_json($queuepltmp, $radios, 'favorite playlists temp file');
				LOGINF ("Queue.php: File including all Playlists has been saved.");
			} 
			#print_r($radios);
	
	if (file_exists($queuepltmp))  {
		# load previously saved Sonos Playlist
		$value = s4lox_queue_read_json_array($queuepltmp, 'favorite playlists temp file');
		if ($value === null) {
			exit;
		}
		#print_r($value);
		LOGOK ("Queue.php: Your Favorite Playlists has been loaded");
		# add Playlist
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->ClearQueue();
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		if (count($value) >= 1)  {
			$proof = metadata($value[0]);
		}
		if ($proof === false) {
			array_shift($value);
			LOGINF("Queue.php: Favorite playlist could not be loaded and has been removed.");
			$sonos->ClearQueue();
			if (count($value) >= 1) {
				LOGOK("Queue.php: Next favorite playlist will be loaded.");
				$proof = metadata($value[0]);
			} else {
				LOGWARN("Queue.php: No further favorite playlists are available after removing the failed item.");
			}
		}
		$sonos->SetGroupMute(false);
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $profile_details[0]['Player'][$master][0]['Volume'];
		} elseif (isset($_GET['member'])) {
			volume_group();
			$sonos = new SonosAccess($sonoszone[$master][0]);
		}
		#$sonos->SetVolume($volume);
		LOGINF("Queue.php: Settings to play your playlist has been prepared!");
		# check addionally if Playlist has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				$sonos->Play();
				if(isset($_GET['rampto']) and !file_exists($queuepltmp))  {
					RampTo();
				} else {
					$sonos->SetVolume($volume);
				}
				# remove 1st element of array
				array_shift($value);
				LOGINF ("Queue.php: Favorite Playlist has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGWARN ("Queue.php: Favorite Playlist has been removed from array (Loading failed)");
			}
		}
		# check array if NULL
		if (count($value) > 0)  {
			# save new array
			$radiofavarray = s4lox_queue_write_json($queuepltmp, $value, 'favorite playlists temp file');
			LOGOK ("Queue.php: New Favorite Playlists array has been saved");
		} else {
			# if last element loaded delete files
			if(isset($_GET['member'])) {
				#removemember();
				LOGINF ("Queue.php: Member has been removed");
			}
			s4lox_queue_delete_file($queuepltmp, 'favorite playlists temp file');
			LOGINF ("Queue.php: File has been deleted");
			LOGOK ("Queue.php: ** Loop ended, we start from beginning **");
		}
	} else {
		LOGWARN ("Queue.php: Favorite Playlists File could not be loaded!");
		exit;
	}
}




