<?php

/**
 * Sonos4Lox follow/leave helper.
 * Version: FOLLOW_HARDENING_V01_2026_06_18
 *
 * This file intentionally stays in the legacy html plugin area.
 * Logging therefore uses the native LoxBerry logging functions.
 */

/**
 * Return a safe one-line value for log messages.
 */
function s4lox_follow_log_value($value)
{
    return str_replace(array("\r", "\n"), ' ', trim((string)$value));
}

/**
 * Read and trim a query parameter.
 */
function s4lox_follow_query_param($name, $default = '')
{
    if (!isset($_GET[$name])) {
        return $default;
    }

    return trim((string)$_GET[$name]);
}

/**
 * Validate that a room exists in the Sonos zone map and has an IP and RINCON id.
 */
function s4lox_follow_zone_exists($room)
{
    global $sonoszone;

    $room = trim((string)$room);

    return (
        $room !== '' &&
        isset($sonoszone[$room]) &&
        is_array($sonoszone[$room]) &&
        isset($sonoszone[$room][0]) &&
        trim((string)$sonoszone[$room][0]) !== '' &&
        isset($sonoszone[$room][1]) &&
        trim((string)$sonoszone[$room][1]) !== ''
    );
}

/**
 * Build the follow status file path in RAM disk.
 */
function s4lox_follow_status_file($clientRoom)
{
    global $save_status_file;

    $base = trim((string)$save_status_file);
    if ($base === '') {
        $base = 's4lox_follow_status';
    }

    $safeClient = preg_replace('/[^A-Za-z0-9_.-]/', '_', trim((string)$clientRoom));
    if ($safeClient === '') {
        $safeClient = 'unknown';
    }

    return '/run/shm/' . $base . '_' . $safeClient . '.json';
}

/**
 * Parse a volume value and clamp it to the valid Sonos range.
 */
function s4lox_follow_normalize_volume($value)
{
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
 * Parse a delay value and limit it to a safe runtime window.
 */
function s4lox_follow_normalize_delay($value)
{
    $delay = (int)$value;

    if ($delay < 0) {
        return 0;
    }

    if ($delay > 300) {
        LOGWARN("follow.php: Delay value '" . s4lox_follow_log_value($value) . "' exceeds 300 seconds and has been limited to 300 seconds.");
        return 300;
    }

    return $delay;
}

/**
 * Write the follow status marker with locking.
 */
function s4lox_follow_write_status_file($clientRoom)
{
    $statusFile = s4lox_follow_status_file($clientRoom);
    $result = file_put_contents($statusFile, '1', LOCK_EX);

    if ($result === false) {
        LOGWARN("follow.php: Could not write follow status file for client '" . s4lox_follow_log_value($clientRoom) . "'.");
        return false;
    }

    return true;
}

/**
 * Remove the follow status marker.
 */
function s4lox_follow_remove_status_file($clientRoom)
{
    $statusFile = s4lox_follow_status_file($clientRoom);

    if (!file_exists($statusFile)) {
        return true;
    }

    if (!unlink($statusFile)) {
        LOGWARN("follow.php: Could not remove follow status file for client '" . s4lox_follow_log_value($clientRoom) . "'.");
        return false;
    }

    return true;
}

/**
 * Execute the configured backup action or abort with a clear log message.
 */
function s4lox_follow_backup_or_abort($message)
{
    global $backup, $client;

    if (is_enabled($backup)) {
        LOGINF("follow.php: " . $message . " We switch to the backup function.");

        if (trim((string)$client) === '') {
            $client = getClient();
        }

        checkClientState();
        playclient($client);
        exit;
    }

    LOGWARN("follow.php: " . $message . " We abort because no backup function was requested.");
    exit;
}

/**
 * Follow the configured or supplied host by adding the client room to the host group.
 */
function follow()
{
    global $client, $follow, $hostroom, $backup;

    $follow = 'true';

    if (isset($_GET['play']) && isset($_GET['function'])) {
        LOGWARN("follow.php: Please enter either 'play' or 'function' in the URL, not both.");
        exit;
    }

    $backup = checkBackup();
    $client = getClient();
    $hostroom = getHost();

    if ($hostroom === $client) {
        LOGWARN("follow.php: Host and client must be different rooms. Room '" . s4lox_follow_log_value($client) . "' was used for both.");
        exit;
    }

    $statehost = checkHostState($hostroom);
    checkClientState();

    if (!file_exists(s4lox_follow_status_file($client))) {
        connectClient($statehost);
    } else {
        LOGDEB("follow.php: Client '" . s4lox_follow_log_value($client) . "' is already in follow mode.");
    }
}

/**
 * Collect and validate the host room.
 */
function getHost()
{
    global $sonoszone, $config, $host, $hostroom;

    if (isset($_GET['host'])) {
        $hostroom = s4lox_follow_query_param('host');
        $source = 'URL';
    } elseif (
        isset($config['VARIOUS']['follow_host']) &&
        $config['VARIOUS']['follow_host'] !== 'false' &&
        trim((string)$config['VARIOUS']['follow_host']) !== ''
    ) {
        $hostroom = trim((string)$config['VARIOUS']['follow_host']);
        $source = 'config';
    } else {
        LOGWARN("follow.php: No host has been configured and no host was supplied in the URL. Please maintain Options or add '&action=follow&host=ROOMNAME'.");
        exit;
    }

    if (!s4lox_follow_zone_exists($hostroom)) {
        s4lox_follow_backup_or_abort("Host '" . s4lox_follow_log_value($hostroom) . "' from " . $source . " is not a known Sonos zone.");
    }

    $state = checkOnline($hostroom);
    if ($state === 'true') {
        if ($source === 'URL') {
            LOGINF("follow.php: Host '" . s4lox_follow_log_value($hostroom) . "' was supplied in the URL and is online.");
        } else {
            LOGDEB("follow.php: Host '" . s4lox_follow_log_value($hostroom) . "' was loaded from config and is online.");
        }
        $host = $sonoszone[$hostroom][1];
        return $hostroom;
    }

    s4lox_follow_backup_or_abort("Host '" . s4lox_follow_log_value($hostroom) . "' from " . $source . " seems to be offline.");
}

/**
 * Collect and validate the client room from the URL zone parameter.
 */
function getClient()
{
    global $client;

    if (!isset($_GET['zone'])) {
        LOGERR("follow.php: No client zone was supplied.");
        exit;
    }

    $client = s4lox_follow_query_param('zone');

    if (!s4lox_follow_zone_exists($client)) {
        LOGWARN("follow.php: Client '" . s4lox_follow_log_value($client) . "' is not a known Sonos zone.");
        exit;
    }

    $state = checkOnline($client);
    if ($state === 'true') {
        LOGINF("follow.php: Client '" . s4lox_follow_log_value($client) . "' is online.");
        return $client;
    }

    LOGWARN("follow.php: Client '" . s4lox_follow_log_value($client) . "' seems to be offline.");
    exit;
}

/**
 * Check whether the host can be followed and return its transport state.
 */
function checkHostState($room)
{
    global $sonoszone, $host, $client;

    $room = trim((string)$room);

    if (!s4lox_follow_zone_exists($room)) {
        s4lox_follow_backup_or_abort("Host '" . s4lox_follow_log_value($room) . "' is not a known Sonos zone.");
    }

    try {
        $sonos = new SonosAccess($sonoszone[$room][0]);
        LOGDEB("follow.php: Host '" . s4lox_follow_log_value($room) . "' is reachable.");
    } catch (Exception $e) {
        s4lox_follow_backup_or_abort("Host '" . s4lox_follow_log_value($room) . "' seems to be offline.");
    }

    $stategrouph = getZoneStatus($room);

    if ($stategrouph === 'member') {
        $coord = getCoordinator($room);

        if (!s4lox_follow_zone_exists($coord)) {
            s4lox_follow_backup_or_abort("Coordinator '" . s4lox_follow_log_value($coord) . "' for host '" . s4lox_follow_log_value($room) . "' is not a known Sonos zone.");
        }

        $sonos = new SonosAccess($sonoszone[$coord][0]);
        $statehost = $sonos->GetTransportInfo();

        if ($statehost == '1') {
            $host = $sonoszone[$coord][1];
            $GLOBALS['hostroom'] = $coord;
            LOGDEB("follow.php: Host '" . s4lox_follow_log_value($room) . "' is member of a streaming group. Coordinator '" . s4lox_follow_log_value($coord) . "' is used as new host.");

            $tvmode = $sonos->GetZoneInfo();
            $posinfo = $sonos->GetPositionInfo();
            $trackUri = isset($posinfo['TrackURI']) ? (string)$posinfo['TrackURI'] : '';
            $htAudioIn = isset($tvmode['HTAudioIn']) ? (int)$tvmode['HTAudioIn'] : 0;

            if ($htAudioIn > 21 || substr($trackUri, 0, 17) === 'x-sonos-htastream') {
                s4lox_follow_backup_or_abort("Source of new host '" . s4lox_follow_log_value($coord) . "' is TV.");
            }

            return $statehost;
        }

        s4lox_follow_backup_or_abort("Host '" . s4lox_follow_log_value($room) . "' is member of a group, but the coordinator is not streaming.");
    }

    $sonos = new SonosAccess($sonoszone[$room][0]);
    $tvmode = $sonos->GetZoneInfo();
    $posinfo = $sonos->GetPositionInfo();
    $statehost = $sonos->GetTransportInfo();
    $trackUri = isset($posinfo['TrackURI']) ? (string)$posinfo['TrackURI'] : '';
    $htAudioIn = isset($tvmode['HTAudioIn']) ? (int)$tvmode['HTAudioIn'] : 0;

    if ($htAudioIn > 21 || substr($trackUri, 0, 17) === 'x-sonos-htastream') {
        s4lox_follow_backup_or_abort("Source of host '" . s4lox_follow_log_value($room) . "' is TV.");
    }

    if ($statehost > 1) {
        s4lox_follow_backup_or_abort("Host '" . s4lox_follow_log_value($room) . "' is not streaming.");
    }

    $host = $sonoszone[$room][1];
    $GLOBALS['hostroom'] = $room;

    return $statehost;
}

/**
 * Check whether the client can be added to the host.
 */
function checkClientState()
{
    global $sonoszone, $client;

    if (!s4lox_follow_zone_exists($client)) {
        LOGWARN("follow.php: Client '" . s4lox_follow_log_value($client) . "' is not a known Sonos zone.");
        exit;
    }

    $sonos = new SonosAccess($sonoszone[$client][0]);
    $stateclient = $sonos->GetTransportInfo();
    $stategroupc = getZoneStatus($client);

    if ($stategroupc === 'member') {
        $coord = getCoordinator($client);

        if (s4lox_follow_zone_exists($coord)) {
            $sonos = new SonosAccess($sonoszone[$coord][0]);
            $stateclient = $sonos->GetTransportInfo();
        }

        if ($stateclient == '1') {
            LOGDEB("follow.php: Client '" . s4lox_follow_log_value($client) . "' is member of a streaming group.");
            exit;
        }

        LOGDEB("follow.php: Client '" . s4lox_follow_log_value($client) . "' is member of a group.");
    }

    if ($stateclient == '1') {
        LOGINF("follow.php: Client '" . s4lox_follow_log_value($client) . "' is already streaming. We abort here.");
        exit;
    }

    return $stateclient;
}

/**
 * Add the client to the host group.
 */
function connectClient($statehost)
{
    global $sonoszone, $client, $host, $hostroom;

    if ($statehost != '1') {
        LOGWARN("follow.php: Host '" . s4lox_follow_log_value($hostroom) . "' is not in PLAYING state. Client was not assigned.");
        return;
    }

    if (!s4lox_follow_zone_exists($client)) {
        LOGWARN("follow.php: Client '" . s4lox_follow_log_value($client) . "' is not a known Sonos zone.");
        exit;
    }

    if (!s4lox_follow_write_status_file($client)) {
        exit;
    }

    $sonos = new SonosAccess($sonoszone[$client][0]);
    $sonos->SetAVTransportURI('x-rincon:' . trim((string)$host));
    $sonos->SetMute(false);

    if (isset($_GET['volume']) && s4lox_follow_query_param('volume') !== '') {
        $volume = s4lox_follow_normalize_volume(s4lox_follow_query_param('volume'));
        $sonos->SetVolume($volume);
        LOGDEB("follow.php: Client '" . s4lox_follow_log_value($client) . "' volume has been set to " . $volume . '.');
    }

    LOGOK("follow.php: Client '" . s4lox_follow_log_value($client) . "' has been assigned to '" . s4lox_follow_log_value($hostroom) . "'.");
}

/**
 * Stop following the host and make the client standalone again.
 */
function leave()
{
    global $sonoszone, $config, $client;

    $client = getClient();
    $statusFile = s4lox_follow_status_file($client);

    if (!file_exists($statusFile)) {
        LOGDEB("follow.php: Client '" . s4lox_follow_log_value($client) . "' is not in follow mode. Nothing to leave.");
        return;
    }

    if (!s4lox_follow_remove_status_file($client)) {
        exit;
    }

    if (isset($_GET['delay']) && s4lox_follow_query_param('delay') !== '' && s4lox_follow_query_param('delay') !== '0') {
        $waitleave = s4lox_follow_normalize_delay(s4lox_follow_query_param('delay'));
        LOGINF("follow.php: " . $waitleave . " seconds delay for client '" . s4lox_follow_log_value($client) . "' was supplied in the URL.");
    } elseif (
        isset($config['VARIOUS']['follow_wait']) &&
        $config['VARIOUS']['follow_wait'] !== 'false' &&
        trim((string)$config['VARIOUS']['follow_wait']) !== '' &&
        trim((string)$config['VARIOUS']['follow_wait']) !== '0'
    ) {
        $waitleave = s4lox_follow_normalize_delay($config['VARIOUS']['follow_wait']);
        LOGINF("follow.php: " . $waitleave . " seconds delay for client '" . s4lox_follow_log_value($client) . "' was loaded from config.");
    } else {
        LOGWARN("follow.php: No delay for the leave action was configured or supplied. Please maintain Options or add '&action=leave&delay=SECONDS'.");
        exit;
    }

    if ($waitleave > 0) {
        sleep($waitleave);
    }

    $sonos = new SonosAccess($sonoszone[$client][0]);
    $sonos->BecomeCoordinatorOfStandaloneGroup();

    LOGOK("follow.php: Client '" . s4lox_follow_log_value($client) . "' has stopped following the host.");
}

/**
 * Check whether a backup action was requested.
 */
function checkBackup()
{
    if (isset($_GET['play']) || isset($_GET['function'])) {
        if (isset($_GET['play'])) {
            LOGDEB("follow.php: Backup function '&play' was supplied in the URL.");
        }
        if (isset($_GET['function'])) {
            LOGDEB("follow.php: Backup function '&function' was supplied in the URL.");
        }
        return 'true';
    }

    return 'false';
}

/**
 * Start playback on the client if the host cannot be followed and a backup was requested.
 */
function playclient($client)
{
    global $sonoszone, $config;

    if (!s4lox_follow_zone_exists($client)) {
        LOGWARN("follow.php: Backup client '" . s4lox_follow_log_value($client) . "' is not a known Sonos zone.");
        exit;
    }

    $sonos = new SonosAccess($sonoszone[$client][0]);

    if (isset($_GET['play'])) {
        $getclient = $sonos->GetMediaInfo();
        $getpos = $sonos->GetPositionInfo();

        if (empty($getclient['UpnpClass']) && empty($getpos['UpnpClass'])) {
            LOGWARN("follow.php: Client '" . s4lox_follow_log_value($client) . "' has no queue to play. Please load a playlist or radio station before calling the follow function.");
            return;
        }

        $sonos->SetMute(false);
        $sonos->Play();
        LOGOK("follow.php: Client '" . s4lox_follow_log_value($client) . "' starts playing the current queue.");
        return 'play current queue';
    }

    if (isset($_GET['function'])) {
        $source = '';

        if (isset($config['VARIOUS']['selfunction']) && trim((string)$config['VARIOUS']['selfunction']) !== '') {
            $source = trim((string)$config['VARIOUS']['selfunction']);
            $rad = PlayZapzoneNext();

            if ($rad !== 'false') {
                LOGOK("follow.php: '" . s4lox_follow_log_value($rad) . "' from config has been called by client '" . s4lox_follow_log_value($client) . "'.");
            } else {
                LOGOK("follow.php: '" . s4lox_follow_log_value($source) . "' from config has been called by client '" . s4lox_follow_log_value($client) . "'.");
            }
        } else {
            LOGWARN("follow.php: Backup function '&function' was requested, but no selfunction is configured.");
            return '';
        }

        $sonos->SetMute(false);
        try {
            $sonos->Play();
        } catch (Exception $e) {
            LOGWARN("follow.php: Backup playback could not be started for client '" . s4lox_follow_log_value($client) . "'.");
        }

        return $source;
    }

    return '';
}
