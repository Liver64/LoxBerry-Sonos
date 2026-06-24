<?php
/**
 * Sonos4Lox Music Service helpers
 * Version: MUSIC_SERVICE_HARDENING_V01_2026_06_19
 *
 * Purpose:
 * - Keep the public URL behavior for external music provider actions.
 * - Harden URL parameter handling, logging and local track handling.
 * - Avoid the legacy logging helper in this non-src runtime file.
 */

function s4lox_musicservice_log_safe($value)
{
    return str_replace(array("\r", "\n"), ' ', (string)$value);
}

function s4lox_musicservice_xml($value)
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function s4lox_musicservice_limit_volume($value, $fallback = 25)
{
    if ($value === null || $value === '') {
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

function s4lox_musicservice_requested_uri($provider)
{
    $candidates = array(
        'trackuri'    => 'track',
        'playlisturi' => 'playlist',
        'albumuri'    => 'album'
    );

    foreach ($candidates as $param => $type) {
        if (!array_key_exists($param, $_GET)) {
            continue;
        }

        $value = trim((string)$_GET[$param]);
        $value = str_replace(array("\r", "\n"), '', $value);

        if ($value === '') {
            LOGWARN("MusicService.php: URL parameter '" . $param . "' for " . $provider . " is empty. Please provide a valid " . $type . " id.");
            return false;
        }

        return array(
            'param' => $param,
            'type'  => $type,
            'value' => $value
        );
    }

    LOGWARN("MusicService.php: Missing music URI parameter for " . $provider . ". Please use 'trackuri', 'playlisturi' or 'albumuri'.");
    return false;
}

function s4lox_musicservice_prepare_sonos()
{
    global $sonoszone, $master, $sonos;

    if (!isset($sonoszone[$master]) || empty($sonoszone[$master][0]) || empty($sonoszone[$master][1])) {
        LOGERR("MusicService.php: Master zone '" . s4lox_musicservice_log_safe($master) . "' is unknown or incomplete. Music service request aborted.");
        return false;
    }

    try {
        $sonos = new SonosAccess($sonoszone[$master][0]);
        $sonos->SetQueue("x-rincon-queue:" . $sonoszone[$master][1] . "#0");
        $sonos->ClearQueue();
        return $sonos;
    } catch (Exception $e) {
        LOGERR("MusicService.php: Could not prepare Sonos queue for master zone '" . s4lox_musicservice_log_safe($master) . "': " . s4lox_musicservice_log_safe($e->getMessage()));
        return false;
    }
}

function s4lox_musicservice_effective_volume()
{
    global $lookup, $master, $volume;

    $effectiveVolume = s4lox_musicservice_limit_volume($volume, 25);

    if (isset($_GET['profile']) || isset($_GET['Profile'])) {
        if (isset($lookup[0]['Player'][$master][0]['Volume'])) {
            $effectiveVolume = s4lox_musicservice_limit_volume($lookup[0]['Player'][$master][0]['Volume'], $effectiveVolume);
        } else {
            LOGWARN("MusicService.php: Profile volume for master zone '" . s4lox_musicservice_log_safe($master) . "' is missing. Keeping current effective volume " . $effectiveVolume . ".");
        }
    }

    return $effectiveVolume;
}

function s4lox_musicservice_enqueue_and_play($provider, $enqueuedUri, $metadata, $sourceId)
{
    $sonos = s4lox_musicservice_prepare_sonos();

    if ($sonos === false) {
        return false;
    }

    try {
        $sonos->AddToQueue($enqueuedUri, htmlspecialchars_decode($metadata));
    } catch (Exception $e) {
        LOGWARN("MusicService.php: The entered " . $provider . " id '" . s4lox_musicservice_log_safe($sourceId) . "' seems to be invalid or could not be added to the queue. Please check the URL parameter.");
        return false;
    }

    try {
        $sonos->SetVolume(s4lox_musicservice_effective_volume());
        $sonos->SetMute(false);
        $sonos->Play();
    } catch (Exception $e) {
        LOGERR("MusicService.php: Requested " . $provider . " music could not be started: " . s4lox_musicservice_log_safe($e->getMessage()));
        return false;
    }

    LOGOK("MusicService.php: Requested " . $provider . " music is playing now.");
    return true;
}

function s4lox_musicservice_didl_start($itemId)
{
    return '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">'
        . '<item id="' . s4lox_musicservice_xml($itemId) . '" parentID="-1" restricted="true">';
}

function s4lox_musicservice_didl_finish($reg)
{
    return '<dc:title></dc:title><upnp:class>object.container.album.musicAlbum</upnp:class>'
        . '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">'
        . s4lox_musicservice_xml($reg)
        . '</desc></item></DIDL-Lite>';
}

/**
 * AddAmazon()
 * Loads Amazon Music tracks, playlists or albums into the Sonos queue.
 */
function AddAmazon()
{
    $requested = s4lox_musicservice_requested_uri('Amazon Music');
    if ($requested === false) {
        return false;
    }

    $reg = 'SA_RINCON51463_X_#Svc51463-0-Token';
    $rand = mt_rand(1000000, 1999999);
    $pl = $requested['value'];

    if ($requested['type'] === 'track') {
        $enqueuedUri = 'x-sonos-http:catalog%2ftracks%2f' . $pl . '%2f%3fplaylistAsin%3d' . $pl . 'H%26playlistType%3dhawkfirePlaylist.flac?sid=201&amp;flags=0&amp;sn=2';
        $itemId = '10030000catalog%2ftracks%2f' . $pl . '%2f%3fplaylistAsin%3d%26playlistType%3dprimePlaylist';
    } elseif ($requested['type'] === 'playlist') {
        $enqueuedUri = 'x-rincon-cpcontainer:' . $rand . 'ccatalog%2fplaylists%2f' . $pl . '%2f%23prime_playlist?sid=201&amp;sn=8';
        $itemId = $rand . 'ccatalog%2fplaylists%2f' . $pl . '%2f%23prime_playlist';
    } else {
        $enqueuedUri = 'x-rincon-cpcontainer:' . $rand . 'ccatalog%2falbums%2f' . $pl . '%2f%23album_desc?sid=201&amp;sn=8';
        $itemId = $rand . 'ccatalog%2falbums%2f' . $pl . '8%2f%23album_desc';
    }

    $metadata = s4lox_musicservice_didl_start($itemId) . s4lox_musicservice_didl_finish($reg);
    return s4lox_musicservice_enqueue_and_play('Amazon Music', $enqueuedUri, $metadata, $pl);
}

/**
 * AddApple()
 * Loads Apple Music tracks, playlists or albums into the Sonos queue.
 */
function AddApple()
{
    $requested = s4lox_musicservice_requested_uri('Apple Music');
    if ($requested === false) {
        return false;
    }

    $reg = 'SA_RINCON52231_X_#Svc52231-0-Token';
    $rand = mt_rand(10000000, 19999999);
    $pl = $requested['value'];

    if ($requested['type'] === 'track') {
        $enqueuedUri = 'x-sonos-http:song%3a' . $pl . '.mp4?sid=204&amp;flags=8224&amp;sn=21';
        $itemId = $rand . 'song%3a' . $pl;
    } elseif ($requested['type'] === 'playlist') {
        $enqueuedUri = 'x-rincon-cpcontainer:1006206cplaylist%3apl.' . $pl . '?sid=204&amp;flags=8300&amp;sn=21';
        $itemId = $rand . 'calbum%3a' . $pl;
    } else {
        $enqueuedUri = 'x-rincon-cpcontainer:1004206calbum%3a' . $pl . '?sid=204&amp;flags=8300&amp;sn=21';
        $itemId = $rand . 'calbum%3a' . $pl;
    }

    $metadata = s4lox_musicservice_didl_start($itemId) . s4lox_musicservice_didl_finish($reg);
    return s4lox_musicservice_enqueue_and_play('Apple Music', $enqueuedUri, $metadata, $pl);
}

/**
 * AddNapster()
 * Loads Napster playlists or albums into the Sonos queue.
 */
function AddNapster()
{
    if (isset($_GET['trackuri'])) {
        LOGWARN("MusicService.php: Napster track playback is currently not supported. Please use 'playlisturi' or 'albumuri'.");
        return false;
    }

    $requested = s4lox_musicservice_requested_uri('Napster');
    if ($requested === false) {
        return false;
    }

    $mail = '';
    $reg = 'SA_RINCON51975_' . $mail;
    $rand = mt_rand(1000000, 1999999);
    $pl = $requested['value'];

    if ($requested['type'] === 'playlist') {
        $enqueuedUri = 'x-rincon-cpcontainer:100e004cexplore%3aplaylist%3a%3app.' . $pl . '?sid=203&amp;flags=8428&amp;sn=27';
        $itemId = $rand . 'cexplore%3aplaylist%3a%3app.' . $pl;
    } elseif ($requested['type'] === 'album') {
        $enqueuedUri = 'x-rincon-cpcontainer:100420ecexplore%3aalbum%3a%3aAlb.' . $pl . '?sid=203&amp;flags=8428&amp;sn=27';
        $itemId = $rand . 'ecexplore%3aalbum%3a%3aAlb.' . $pl;
    } else {
        LOGWARN("MusicService.php: Napster track playback is currently not supported. Please use 'playlisturi' or 'albumuri'.");
        return false;
    }

    $metadata = s4lox_musicservice_didl_start($itemId) . s4lox_musicservice_didl_finish($reg);
    return s4lox_musicservice_enqueue_and_play('Napster', $enqueuedUri, $metadata, $pl);
}

/**
 * AddSpotify()
 * Loads Spotify tracks, playlists or albums into the Sonos queue.
 */
function AddSpotify($user = '')
{
    $requested = s4lox_musicservice_requested_uri('Spotify');
    if ($requested === false) {
        return false;
    }

    $reg = 'SA_RINCON2311_X_#Svc2311-0-Token';
    $rand = mt_rand(1000000, 1999999);
    $pl = $requested['value'];

    if ($requested['type'] === 'track') {
        $enqueuedUri = 'x-sonos-spotify:spotify%3atrack%3a' . $pl . '?sid=9&amp;sn=5';
        $itemId = '16054235spotify%3atrack%3a' . $pl;
    } elseif ($requested['type'] === 'playlist') {
        $enqueuedUri = 'x-rincon-cpcontainer:1006206cspotify%3aplaylist%3a' . $pl . '?sid=9&amp;flags=8300&amp;sn=18';
        $itemId = '1006206cspotify%3aplaylist%3a' . $pl;
    } else {
        $enqueuedUri = 'x-rincon-cpcontainer:' . $rand . 'cspotify%3aalbum%3a' . $pl . '?sid=9&amp;sn=5';
        $itemId = '1004206cspotify%3aalbum%3a' . $pl;
    }

    $metadata = s4lox_musicservice_didl_start($itemId) . s4lox_musicservice_didl_finish($reg);
    return s4lox_musicservice_enqueue_and_play('Spotify', $enqueuedUri, $metadata, $pl);
}

/**
 * AddTrack()
 * Loads a local SMB/CIFS track into the Sonos queue.
 */
function AddTrack()
{
    global $sonoszone, $master, $sonos;

    $uri = trim((string)($_GET['file'] ?? ''));
    $uri = str_replace(array("\r", "\n"), '', $uri);

    if ($uri === '') {
        LOGWARN("MusicService.php: Missing or empty URL parameter 'file'. Please provide a valid SMB/UNC path to a local audio file.");
        return false;
    }

    $format = strtoupper((string)pathinfo($uri, PATHINFO_EXTENSION));

    if ($format === '') {
        LOGWARN("MusicService.php: Local track path does not contain a file extension. Please provide a supported audio file.");
        return false;
    }

    if (!AudioFormat($format)) {
        LOGWARN("MusicService.php: The entered audio format '." . s4lox_musicservice_log_safe($format) . "' is not supported by Sonos. Please correct the file path.");
        return false;
    }

    if (substr($uri, 0, 2) === "\\\\") {
        $parts = explode("\\", $uri);
    } else {
        $parts = explode("/", $uri);
    }

    if (count($parts) < 3 || trim((string)$parts[2]) === '') {
        LOGWARN("MusicService.php: Local track path is incomplete. Please use a valid SMB/UNC path like //SERVER/Share/file.mp3.");
        return false;
    }

    $parts = array_map('rawurlencode', $parts);
    $parts[2] = strtoupper($parts[2]);
    $file = implode('/', $parts);
    $track = 'x-file-cifs:' . $file;

    if (!isset($sonoszone[$master]) || empty($sonoszone[$master][0]) || empty($sonoszone[$master][1])) {
        LOGERR("MusicService.php: Master zone '" . s4lox_musicservice_log_safe($master) . "' is unknown or incomplete. Local track request aborted.");
        return false;
    }

    try {
        $sonos = new SonosAccess($sonoszone[$master][0]);
        $sonos->ClearQueue();
        $currentTrack = $sonos->GetPositionInfo();

        if (empty($currentTrack['duration'])) {
            $sonos->SetQueue('x-rincon-queue:' . $sonoszone[$master][1] . '#0');
        }

        $metadata = s4lox_musicservice_didl_start($file)
            . '<dc:title></dc:title><upnp:class>object.container.album.musicAlbum</upnp:class>'
            . '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">RINCON_AssociatedZPUDN</desc></item></DIDL-Lite>';

        $sonos->AddToQueue($track, htmlspecialchars_decode($metadata));
        LOGINF("MusicService.php: The entered local track has been loaded.");
        $sonos->SetVolume(s4lox_musicservice_effective_volume());
        $sonos->SetMute(false);
        $sonos->Play();
        LOGOK("MusicService.php: Requested local track is playing now.");
        return true;
    } catch (Exception $e) {
        LOGWARN("MusicService.php: The requested local track could not be played. Please check folder permissions and whether the file exists.");
        return false;
    }
}

/**
 * AudioFormat()
 * Checks if the entered audio format is supported by Sonos.
 */
function AudioFormat($audio)
{
    $format = array(
        'MP3'  => 'MP3 Audio Format',
        'WMA'  => 'Windows Media Audio',
        'AAC'  => 'Advanced Audio Coding',
        'OGG'  => 'Ogg Vorbis Compressed Audio File',
        'FLAC' => 'Free Lossless Audio Codec',
        'ALAC' => 'Apple Lossless Audio Codec',
        'AIFF' => 'Audio Interchange File Format',
        'WAV'  => 'Waveform Audio File Format'
    );

    return isset($format[strtoupper((string)$audio)]);
}
