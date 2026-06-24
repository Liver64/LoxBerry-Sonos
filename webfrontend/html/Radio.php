<?php
/**
 * Sonos4Lox Radio functions
 * Version: RADIO_REMOVE_RANDOM_RADIO_V04_2026_06_18
 * Language: EN
 *
 * Purpose:
 * - Harden plugin radio handling without changing public URL compatibility.
 * - Keep nextradio endless/cyclic: after the last configured station it starts again at the first station.
 * - Reset/stale-state handling remains outside of this file, e.g. in OneClickActions/Zapzone state logic.
 * - The obsolete randomradio/random_radio legacy action has been removed from this file.
 */

/**
 * Function: nextradio --> iterate through Plugin Radio Favorites (endless/cyclic)
 *
 * @param: empty
 * @return: void
 **/
function nextradio()
{
    global $sonos, $config, $profile_selected, $master, $debug, $min_vol, $volume,
           $tmp_tts, $sonoszone, $tmp_error, $stst, $profile_details;

    $stations = s4lox_radio_get_configured_stations();
    if (count($stations) === 0) {
        LOGERR('Radio.php: There are no valid radio stations configured. Please update the plugin radio configuration before using nextradio or zapzone.');
        exit;
    }

    if (file_exists($tmp_tts)) {
        LOGINF('Radio.php: T2S is currently running, nextradio has been skipped. Please try again later.');
        exit;
    }

    VolumeProfiles();

    if (isset($_GET['member']) && isset($_GET['profile']) && defined('GROUPMASTER')) {
        $master = GROUPMASTER;
    } elseif (isset($_GET['profile']) && defined('GROUPMASTER')) {
        $master = GROUPMASTER;
    } else {
        $master = MASTER;
    }

    if (isset($_GET['member']) && trim((string)$_GET['member']) !== '') {
        // The group member setup is idempotent and is normally already prepared by Sonos.php.
        SyncGroupForPlaybackToMember();
    }

    $announcementAlreadyDone = false;

    // Announce radio error information once a day if an error marker exists.
    if (file_exists($tmp_error)) {
        $errorMessages = s4lox_radio_read_error_messages($tmp_error);
        foreach ($errorMessages as $message) {
            LOGWARN('Radio.php: ' . s4lox_radio_log_value($message));
        }

        check_date_once();
        if ($stst === 'true') {
            select_error_lang();
            global $errortext;
            say_radio_station((string)$errortext);
            $announcementAlreadyDone = true;
            LOGINF('Radio.php: Information about a broken radio URL has been announced once.');
        }
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);
    $mediaInfo = $sonos->GetMediaInfo();
    $currentStationName = '';

    if (is_array($mediaInfo) && !empty($mediaInfo['title'])) {
        $currentStationName = (string)$mediaInfo['title'];
    }

    $stationNames = array();
    foreach ($stations as $station) {
        $stationNames[] = $station['name'];
    }

    // Keep nextradio endless/cyclic:
    // - known current station: next configured station
    // - last station: first configured station
    // - unknown current station: first configured station, not index 1 by PHP false-to-zero conversion
    $currentIndex = array_search($currentStationName, $stationNames, true);
    if ($currentIndex === false || $currentIndex >= (count($stations) - 1)) {
        $nextIndex = 0;
    } else {
        $nextIndex = $currentIndex + 1;
    }

    $nextStation = $stations[$nextIndex];
    $nextName = $nextStation['name'];
    $nextUrl = 'x-rincon-mp3radio://' . trim($nextStation['url']);
    $nextMeta = trim($nextStation['meta']);

    $announcementVolume = null;
    if (s4lox_radio_announcement_enabled() && !$announcementAlreadyDone) {
        $announcementVolume = say_radio_station('', $nextName);
    }

    $coord = getRoomCoordinator($master);
    $sonos = new SonosAccess($coord[0]);
    $sonos->SetMute(false);

    if (isset($_GET['profile']) || isset($_GET['Profile'])) {
        if (isset($profile_details[0]['Player'][$master][0]['Volume'])) {
            $volume = $profile_details[0]['Player'][$master][0]['Volume'];
        }
    } elseif ($announcementVolume !== null) {
        $volume = $announcementVolume;
    } elseif (isset($sonoszone[$master][4])) {
        $volume = $sonoszone[$master][4];
    }

    $volume = s4lox_radio_sanitize_volume($volume, isset($sonoszone[$master][4]) ? $sonoszone[$master][4] : 20);

    $sonos->SetRadio($nextUrl, $nextName, $nextMeta);
    $sonos->SetVolume($volume);
    $sonos->Play();

    LOGOK("Radio.php: Radio station '" . s4lox_radio_log_value($nextName) . "' has been loaded successfully by nextradio.");
}


/**
 * Function: say_radio_station --> announce radio station before playing it.
 *
 * @param string $errortext    Optional error/info text, e.g. from error.json.
 * @param string $stationTitle Optional next station name, e.g. "hr3".
 * @return int                 Volume to be used after the announcement.
 **/
function say_radio_station($errortext = '', $stationTitle = '')
{
    global $master, $sonoszone, $config, $min_vol, $volume,
           $sonos, $coord, $errorvoice, $errorlang;

    if (isset($_GET['batch'])) {
        LOGWARN("Radio.php: The parameter 'batch' cannot be used for radio station announcements.");
        exit;
    }

    $coord = getRoomCoordinator($master);
    LOGDEB('Radio.php: Room coordinator has been identified.');
    $sonos = new SonosAccess($coord[0]);

    $tmpVolume = $sonos->GetVolume();
    $tempRadio = $sonos->GetMediaInfo();

    $TL = load_t2s_text();
    if (!empty($TL) && !empty($TL['SONOS-TO-SPEECH']['ANNOUNCE_RADIO'])) {
        $playStat = $TL['SONOS-TO-SPEECH']['ANNOUNCE_RADIO'];
    } else {
        $playStat = 'Radio';
    }

    if ($errortext !== '') {
        $textstring = (string)$errortext;
    } elseif ($stationTitle !== '') {
        $textstring = $playStat . ' ' . (string)$stationTitle;
    } else {
        $title = '';
        if (is_array($tempRadio) && isset($tempRadio['title'])) {
            $title = (string)$tempRadio['title'];
        }

        if ($title === '') {
            $textstring = $playStat;
        } elseif (strncmp($title, $playStat, strlen($playStat)) === 0) {
            $textstring = $title;
        } else {
            $textstring = $playStat . ' ' . $title;
        }
    }

    if (trim($textstring) === '') {
        LOGWARN('Radio.php: Empty announcement text, skipping TTS.');
        return s4lox_radio_sanitize_volume($tmpVolume, isset($sonoszone[$master][4]) ? $sonoszone[$master][4] : 20);
    }

    $override = array();

    if ($errortext !== '') {
        $override['ignore_get'] = true;

        if (!empty($errorvoice)) {
            $override['voice'] = $errorvoice;
        }

        if (!empty($errorlang)) {
            $override['language'] = $errorlang;
        }
    }

    if (isset($_GET['volume'])) {
        $volume = s4lox_radio_sanitize_volume($_GET['volume'], isset($sonoszone[$master][4]) ? $sonoszone[$master][4] : 20);
    } elseif (isset($_GET['keepvolume'])) {
        if ((int)$tmpVolume >= (int)$min_vol) {
            $volume = $tmpVolume;
        } else {
            $volume = isset($sonoszone[$master][4]) ? $sonoszone[$master][4] : $tmpVolume;
        }
    } else {
        $volume = isset($sonoszone[$master][4]) ? $sonoszone[$master][4] : $tmpVolume;
    }

    $volume = s4lox_radio_sanitize_volume($volume, isset($sonoszone[$master][4]) ? $sonoszone[$master][4] : 20);

    // Radio announcements are followed immediately by a stream switch.
    // Give Play_T2S.php a conservative minimum observation window so Sonos does not
    // report a short intermediate STOPPED state and clear the queue before the
    // announcement was actually audible.
    $override['minimum_wait_seconds'] = s4lox_radio_announcement_minimum_wait($textstring);

    t2s_basic_say($textstring, $override);
    LOGDEB('Radio.php: Radio station announcement has been announced.');

    return $volume;
}


/**
 * Function: select_error_lang --> select language for radio error announcement.
 *
 * @param: empty
 * @return: void
 **/
function select_error_lang()
{
    global $config, $pathlanguagefile, $errortext, $errorvoice, $errorlang;

    $file = 'error.json';
    $url = $pathlanguagefile . $file;
    $validLanguages = File_Get_Array_From_JSON($url, $zip = false);

    if (!is_array($validLanguages)) {
        $validLanguages = array();
    }

    $language = '';
    if (!empty($config['TTS']['messageLang'])) {
        $language = substr((string)$config['TTS']['messageLang'], 0, 5);
    }

    $isvalid = array_multi_search($language, $validLanguages, $sKey = 'language');
    if (!empty($isvalid)) {
        $errortext = isset($isvalid[0]['value']) ? $isvalid[0]['value'] : 'the function nextradio is not working, please check the Sonos plugin error log.';
        $errorvoice = isset($isvalid[0]['voice']) ? $isvalid[0]['voice'] : 'en-US-Wavenet-A';
        $errorlang = isset($isvalid[0]['language']) ? $isvalid[0]['language'] : 'en-US';
    } else {
        $errortext = 'the function nextradio is not working, please check the Sonos plugin error log.';
        $errorvoice = 'en-US-Wavenet-A';
        $errorlang = 'en-US';
        LOGINF('Radio.php: Translation for the configured default language is not available. English has been selected.');
    }
}


/**
 * Function: check_date_once --> check for execution once a day.
 * The daily cronjob deletes the marker file.
 *
 * @param: empty
 * @return: string true or false
 **/
function check_date_once()
{
    global $check_date, $stst, $tmp_error;

    if (file_exists($check_date) && file_exists($tmp_error)) {
        $stst = 'false';
        return $stst;
    }

    $now = date('d.m.Y');
    $result = @file_put_contents($check_date, $now, LOCK_EX);
    if ($result === false) {
        $stst = 'false';
        LOGWARN('Radio.php: Could not write the daily radio error announcement marker.');
        return $stst;
    }

    $stst = 'true';
    return $stst;
}


/**
 * Function: PluginRadio --> load a configured plugin radio favorite into a zone/group.
 *
 * @param: radio URL parameter
 * @return: void
 **/
function PluginRadio()
{
    global $sonos, $sonoszone, $profile_details, $master, $config, $volume;

    $enteredRadioRaw = isset($_GET['radio']) ? trim((string)$_GET['radio']) : '';
    if ($enteredRadioRaw === '') {
        LOGWARN('Radio.php: No radio station has been entered. Please use ...action=pluginradio&radio=<STATION>.');
        exit(1);
    }

    if (isset($_GET['member']) && isset($_GET['profile']) && defined('GROUPMASTER')) {
        $master = GROUPMASTER;
    } elseif (isset($_GET['profile']) && defined('GROUPMASTER')) {
        $master = GROUPMASTER;
    } else {
        $master = MASTER;
    }

    if (isset($_GET['member']) && trim((string)$_GET['member']) !== '') {
        // The group member setup is idempotent and is normally already prepared by Sonos.php.
        SyncGroupForPlaybackToMember();
    }

    $stations = s4lox_radio_get_configured_stations();
    if (count($stations) === 0) {
        LOGERR('Radio.php: No valid plugin radio stations are configured.');
        exit;
    }

    $enteredRadio = s4lox_radio_lower($enteredRadioRaw);
    $matches = array();

    foreach ($stations as $station) {
        $stationLower = s4lox_radio_lower($station['name']);
        if (function_exists('contains')) {
            $matched = contains($stationLower, $enteredRadio);
        } else {
            $matched = ($enteredRadio !== '' && strpos($stationLower, $enteredRadio) !== false);
        }

        if ($matched === true) {
            $matches[] = $station;
        }
    }

    if (count($matches) > 1) {
        LOGERR("Radio.php: The entered favorite/keyword '" . s4lox_radio_log_value($enteredRadioRaw) . "' has more than one hit. Please specify it more precisely.");
        exit;
    }

    if (count($matches) < 1) {
        LOGERR("Radio.php: The entered favorite/keyword '" . s4lox_radio_log_value($enteredRadioRaw) . "' could not be found. Please specify it more precisely.");
        exit;
    }

    $station = $matches[0];
    $stationName = $station['name'];
    $stationUrl = $station['url'];
    $stationMeta = $station['meta'];

    $announcementVolume = null;
    try {
        if (s4lox_radio_announcement_enabled()) {
            $announcementVolume = say_radio_station('', $stationName);
            $sonos = new SonosAccess($sonoszone[$master][0]);
            $sonos->ClearQueue();
        }
    } catch (Exception $e) {
        LOGWARN('Radio.php: Radio station announcement failed: ' . s4lox_radio_log_value($e->getMessage()));
    }

    try {
        $sonos = new SonosAccess($sonoszone[$master][0]);

        if (isset($_GET['profile']) || isset($_GET['Profile'])) {
            if (isset($profile_details[0]['Player'][$master][0]['Volume'])) {
                $volume = $profile_details[0]['Player'][$master][0]['Volume'];
            }
        } elseif (isset($_GET['member'])) {
            volume_group();
            $sonos = new SonosAccess($sonoszone[$master][0]);
        } elseif ($announcementVolume !== null) {
            $volume = $announcementVolume;
        } elseif (isset($_GET['volume'])) {
            $volume = $_GET['volume'];
        }

        $volume = s4lox_radio_sanitize_volume($volume, isset($sonoszone[$master][4]) ? $sonoszone[$master][4] : 20);

        $uri = 'x-rincon-mp3radio://' . trim($stationUrl);
        $sonos->SetRadio($uri, $stationName, $stationMeta);
        $sonos->SetGroupMute(false);
        $sonos->SetVolume($volume);

        if (!isset($_GET['load']) && !isset($_GET['rampto'])) {
            $sonos->SetMute(false);
            $sonos->Stop();
            LOGOK('Radio.php: Volume ' . $volume . ' has been set.');
            $sonos->Play();
            LOGOK("Radio.php: Plugin radio station '" . s4lox_radio_log_value($stationName) . "' has been loaded successfully and is playing.");
        } else {
            LOGOK("Radio.php: Plugin radio station '" . s4lox_radio_log_value($stationName) . "' has been loaded successfully.");
        }

        RampTo();
    } catch (Exception $e) {
        LOGERR("Radio.php: Something unexpected went wrong while loading radio '" . s4lox_radio_log_value($enteredRadioRaw) . "': " . s4lox_radio_log_value($e->getMessage()));
        return;
    }
}


/**
 * Build a normalized list of configured plugin radio stations.
 *
 * @return array
 */
function s4lox_radio_get_configured_stations()
{
    global $config;

    if (empty($config['RADIO']['radio']) || !is_array($config['RADIO']['radio'])) {
        return array();
    }

    $radio = $config['RADIO']['radio'];
    ksort($radio);

    $stations = array();
    foreach ($radio as $entry) {
        if (!is_string($entry) || trim($entry) === '') {
            LOGWARN('Radio.php: Invalid empty radio configuration entry has been skipped.');
            continue;
        }

        $split = explode(',', $entry, 3);
        $name = isset($split[0]) ? trim($split[0]) : '';
        $url = isset($split[1]) ? trim($split[1]) : '';
        $meta = isset($split[2]) ? trim($split[2]) : '';

        if ($name === '' || $url === '') {
            LOGWARN("Radio.php: Invalid radio configuration entry '" . s4lox_radio_log_value($entry) . "' has been skipped.");
            continue;
        }

        $stations[] = array(
            'name' => $name,
            'url' => $url,
            'meta' => $meta,
        );
    }

    return $stations;
}


/**
 * Read messages from a JSON error file safely.
 *
 * @param string $file
 * @return array
 */
function s4lox_radio_read_error_messages($file)
{
    $raw = @file_get_contents($file);
    if ($raw === false || trim((string)$raw) === '') {
        return array('Radio error file exists but is empty or could not be read.');
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        return array('Radio error file exists but could not be decoded.');
    }

    $messages = array();
    foreach ($decoded as $value) {
        if (is_array($value)) {
            $messages[] = json_encode($value);
        } else {
            $messages[] = (string)$value;
        }
    }

    if (count($messages) === 0) {
        $messages[] = 'Radio error file exists but contains no message.';
    }

    return $messages;
}


/**
 * Check whether radio announcements are enabled.
 *
 * @return bool
 */
function s4lox_radio_announcement_enabled()
{
    global $config;

    if (empty($config['VARIOUS']['announceradio'])) {
        return false;
    }

    if (function_exists('is_enabled')) {
        return is_enabled($config['VARIOUS']['announceradio']);
    }

    $value = strtolower(trim((string)$config['VARIOUS']['announceradio']));
    return in_array($value, array('1', 'true', 'yes', 'on', 'enabled'), true);
}


/**
 * Calculate a conservative minimum wait time for radio station announcements.
 *
 * Sonos may briefly report STOPPED while switching from a radio stream to queue
 * playback. During nextradio/pluginradio this can otherwise remove the generated
 * MP3 from the queue before it becomes audible.
 *
 * @param string $text
 * @return float
 */
function s4lox_radio_announcement_minimum_wait($text)
{
    $length = strlen(trim((string)$text));
    if ($length <= 0) {
        return 0.0;
    }

    // Short station names need a fixed guard window; longer announcements get a
    // small text-length based extension. Keep it bounded to avoid blocking radio
    // changes for too long.
    $seconds = max(2.0, min(8.0, (float)(ceil($length / 14) + 1)));
    return $seconds;
}


/**
 * Sanitize a volume value into Sonos range 0..100.
 *
 * @param mixed $value
 * @param mixed $fallback
 * @return int
 */
function s4lox_radio_sanitize_volume($value, $fallback = 20)
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        $value = $fallback;
    }

    $volume = (int)$value;
    if ($volume < 0) {
        return 0;
    }
    if ($volume > 100) {
        return 100;
    }

    return $volume;
}


/**
 * Lowercase helper with mbstring fallback.
 *
 * @param string $value
 * @return string
 */
function s4lox_radio_lower($value)
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower((string)$value, 'UTF-8');
    }

    return strtolower((string)$value);
}


/**
 * Remove line breaks/control characters from values written to logs.
 *
 * @param mixed $value
 * @return string
 */
function s4lox_radio_log_value($value)
{
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value);
    }

    $value = (string)$value;
    $value = str_replace(array("\r", "\n", "\t"), ' ', $value);
    return trim($value);
}

?>
