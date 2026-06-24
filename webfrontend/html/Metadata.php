<?php

/**
 * Submodule: Metadata
 * Version: METADATA_HARDENING_V01_2026_06_18
 * Function: Creates DIDL metadata for Sonos favorites and streaming services.
 *
 * This file intentionally keeps the legacy global function names because other
 * Sonos4Lox modules still call them directly.
 */

function metadata($value)
{
    global $sonos, $sid, $services, $file, $meta, $stype;

    if (!is_array($value)) {
        LOGWARN("Metadata.php: Favorite metadata is invalid or empty.");
        return false;
    }

    $sid = s4lox_metadata_clean_sid($value['sid'] ?? '000');

    if ($sid === '000') {
        LOGINF("Metadata.php: No valid 'sid' has been received.");
    }

    $type = s4lox_metadata_string($value, 'typ');

    switch ($sid) {
        case '201': // Amazon Music
            if ($type === 'Radio') {
                $stype = 'Amazon Radio Favorite';
            } elseif ($type === 'Track') {
                $stype = 'Amazon Track Favorite';
            } elseif ($type === 'Playlist') {
                $stype = 'Amazon Playlist Favorite';
            } else {
                AddFavToQueueError($value, 'Amazon Music', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '998': // Sonos Playlist
            $stype = 'Sonos Playlist';
            $value['UpnpClass'] = 'object.container.playlistContainer';
            $value['albumArtURI'] = '';
            $value['parentid'] = 'SQ:';
            $value['artist'] = 'Sonos Playlist';
            $value['token'] = 'RINCON_AssociatedZPUDN';
            return CreateDIDL($value, $stype);

        case '999': // Local Music
            $resorg = s4lox_metadata_string($value, 'resorg');

            if (substr($resorg, 0, 11) === 'x-file-cifs') {
                $stype = 'Local Music Track';
                $value['UpnpClass'] = $value['UpnpClass'] ?? 'object.item.audioItem.musicTrack';
            } elseif (substr($resorg, 0, 17) === 'x-rincon-playlist') {
                $stype = 'Local Music Album';
                $value['UpnpClass'] = $value['UpnpClass'] ?? 'object.container.album.musicAlbum';
            } else {
                LOGWARN("Metadata.php: Unsupported local music URI for favorite '" . s4lox_metadata_log($value['title'] ?? 'Unknown favorite') . "'.");
                CreateDebugFile($value, $resorg, '');
                return false;
            }

            $value['artist'] = $value['artist'] ?? 'Local Music';
            $value['albumArtURI'] = $value['albumArtURI'] ?? '';
            $value['token'] = 'RINCON_AssociatedZPUDN';
            return CreateDIDL($value, $stype);

        case '303': // Sonos Radio / TuneIn detection
            $haystack = '';

            try {
                if (is_object($sonos) && method_exists($sonos, 'GetMediaInfo')) {
                    $tempradio = $sonos->GetMediaInfo();
                    if (is_array($tempradio)) {
                        $haystack = (string)($tempradio['CurrentURI'] ?? '');
                    }
                }
            } catch (Throwable $e) {
                LOGWARN("Metadata.php: Could not read current media info for SID 303 detection: " . s4lox_metadata_log($e->getMessage()));
            }

            $stype = (mb_strpos($haystack, 'tunein') !== false) ? 'TuneIn Favorite' : 'Sonos Radio Favorite';
            return CreateDIDL($value, $stype);

        case '160': // SoundCloud
            if ($type === 'Track') {
                $stype = 'SoundCloud Track Favorite';
            } elseif ($type === 'Playlist') {
                $stype = 'SoundCloud Playlist Favorite';
            } else {
                AddFavToQueueError($value, 'SoundCloud', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '9': // Spotify
            if ($type === 'Playlist') {
                $stype = 'Spotify Playlist Favorite';
            } elseif ($type === 'Track') {
                $stype = 'Spotify Track Favorite';
            } else {
                AddFavToQueueError($value, 'Spotify', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '181': // Mixcloud
            if ($type === 'Track') {
                $stype = 'Mixcloud Track Favorite';
            } elseif ($type === 'Playlist') {
                $stype = 'Mixcloud Playlist Favorite';
            } else {
                AddFavToQueueError($value, 'Mixcloud', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '254': // TuneIn Radio
            $value['token'] = 'SA_RINCON65031_';
            $value['UpnpClass'] = 'object.item.audioItem.audioBroadcast';
            $value['artist'] = 'TuneIn Radio';
            $stype = isset($value['protocolInfo']) ? 'TuneIn Favorite' : 'TuneIn';
            return CreateDIDL($value, $stype);

        case '333': // TuneIn Radio (new)
            $value['token'] = 'SA_RINCON65031_';
            $value['UpnpClass'] = 'object.item.audioItem.audioBroadcast';
            $value['artist'] = 'TuneIn (New) Radio';
            $stype = isset($value['protocolInfo']) ? 'TuneIn (New) Favorite' : 'TuneIn (New)';
            return CreateDIDL($value, $stype);

        case '2': // Deezer
            if ($type === 'Track') {
                $stype = 'Deezer Track Favorite';
            } elseif ($type === 'Radio') {
                $stype = 'Deezer Radio Favorite';
            } elseif ($type === 'Playlist') {
                $stype = 'Deezer Playlist Favorite';
            } else {
                AddFavToQueueError($value, 'Deezer', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '204': // Apple Music
            if ($type === 'Radio') {
                $stype = 'Apple Radio Favorite';
            } elseif ($type === 'Track') {
                $stype = 'Apple Track Favorite';
            } elseif ($type === 'Playlist') {
                $stype = 'Apple Playlist Favorite';
            } else {
                AddFavToQueueError($value, 'Apple Music', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '203': // Napster
            if ($type === 'Radio') {
                $stype = 'Napster Radio Favorite';
            } elseif ($type === 'Track') {
                $stype = 'Napster Track Favorite';
            } elseif ($type === 'Playlist') {
                $stype = 'Napster Playlist Favorite';
            } else {
                AddFavToQueueError($value, 'Napster', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '284': // YouTube Music
            if ($type === 'Track') {
                $stype = 'YouTube Track Favorite';
            } elseif ($type === 'Playlist') {
                $stype = 'YouTube Playlist Favorite';
            } else {
                AddFavToQueueError($value, 'YouTube Music', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '174': // Tidal
            if ($type === 'Track') {
                $stype = 'Tidal Track Favorite';
            } elseif ($type === 'Playlist') {
                $stype = 'Tidal Playlist Favorite';
            } else {
                AddFavToQueueError($value, 'Tidal', $sid);
                return false;
            }
            return CreateDIDL($value, $stype);

        case '000': // Unknown streaming service
            $title = s4lox_metadata_log($value['title'] ?? 'Unknown favorite');
            LOGWARN("Metadata.php: Your Sonos favorite '" . $title . "' could not be added because the streaming service '000' is unknown.");
            LOGINF("Metadata.php: Please remove the favorite from Sonos Favorites or provide a metadata debug file for adding the missing streaming type.");
            CreateDebugFile($value, s4lox_metadata_string($value, 'resorg'), '');
            return false;

        default:
            if (isService($sid) === true) {
                $stype = getServiceName($sid);
                LOGDEB("Metadata.php: Trying to add streaming service SID '" . s4lox_metadata_log($sid) . "' to queue.");
                return CreateDIDL($value, $stype);
            }

            AddFavToQueueError($value, getServiceName($sid), $sid);
            return false;
    }
}

/**
 * Add service details and SID information to Sonos favorite metadata.
 *
 * @return array
 */
function AddDetailsToMetadata()
{
    global $sonos, $services;

    if (!is_object($sonos) || !method_exists($sonos, 'GetFavorites')) {
        LOGWARN("Metadata.php: Sonos favorites cannot be loaded because the Sonos object is not available.");
        return [];
    }

    try {
        $browse = $sonos->GetFavorites();
    } catch (Throwable $e) {
        LOGWARN("Metadata.php: Sonos favorites could not be loaded: " . s4lox_metadata_log($e->getMessage()));
        return [];
    }

    if (!is_array($browse)) {
        LOGWARN("Metadata.php: Sonos favorites response is not an array.");
        return [];
    }

    foreach ($browse as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $resorg = s4lox_metadata_string($value, 'resorg');
        $sid = s4lox_metadata_extract_sid($resorg);

        if ($sid === '') {
            if (substr($resorg, 0, 11) === 'x-file-cifs' || substr($resorg, 0, 17) === 'x-rincon-playlist') {
                $sid = '999';
            } elseif (substr($resorg, 0, 4) === 'file') {
                $sid = '998';
            } else {
                $sid = '000';
            }
        }

        $sid = s4lox_metadata_clean_sid($sid);
        $serviceName = getServiceName($sid);

        $browse[$key]['Service'] = $serviceName;
        $browse[$key]['sid'] = $sid;
    }

    return $browse;
}

function isService($sid)
{
    $services = loadServices();
    return array_key_exists((string)$sid, $services);
}

function loadServices()
{
    global $services, $sonos;

    if (is_array($services) && !empty($services)) {
        return $services;
    }

    $services = [];

    if (is_object($sonos) && method_exists($sonos, 'GetAvailableServicesMap')) {
        try {
            $serviceMap = $sonos->GetAvailableServicesMap();
            if (is_array($serviceMap)) {
                $services = $serviceMap;
            }
        } catch (Throwable $e) {
            LOGWARN("Metadata.php: Sonos service map could not be loaded: " . s4lox_metadata_log($e->getMessage()));
            $services = [];
        }
    } else {
        LOGWARN("Metadata.php: Sonos service map is not available.");
    }

    // Manual additions / local special cases.
    $services['996'] = $services['996'] ?? 'Plugin Radio';
    $services['998'] = $services['998'] ?? 'Sonos Playlist';
    $services['999'] = $services['999'] ?? 'Local Music';
    $services['000'] = $services['000'] ?? 'unknown';

    return $services;
}

function getServiceName($sid)
{
    $services = loadServices();
    $sid = s4lox_metadata_clean_sid($sid);
    return $services[$sid] ?? $services['000'];
}

function CreateDIDL($value, $stype)
{
    global $meta, $file;

    if (!is_array($value)) {
        LOGWARN("Metadata.php: DIDL metadata cannot be created from invalid favorite data.");
        return false;
    }

    $title = s4lox_metadata_string($value, 'title', 'Unknown favorite');
    $type = s4lox_metadata_string($value, 'typ');
    $file = s4lox_metadata_string($value, 'resorg');

    if ($file === '') {
        LOGWARN("Metadata.php: Favorite '" . s4lox_metadata_log($title) . "' has no resource URI.");
        CreateDebugFile($value, $file, '');
        return false;
    }

    $id = s4lox_metadata_string($value, 'id', '-1');
    $upnpClass = s4lox_metadata_string($value, 'UpnpClass', 'object.item.audioItem');
    $artist = s4lox_metadata_string($value, 'artist', $stype);
    $token = s4lox_metadata_string($value, 'token', 'RINCON_AssociatedZPUDN');
    $albumArtURI = s4lox_metadata_string($value, 'albumArtURI');

    $meta = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
    $meta .= '<item id="' . s4lox_metadata_xml($id) . '" parentID="-1" restricted="true">';
    $meta .= '<dc:title>' . s4lox_metadata_xml($title) . '</dc:title>';
    $meta .= '<upnp:class>' . s4lox_metadata_xml($upnpClass) . '</upnp:class>';

    if (in_array($type, ['Track', 'Playlist', 'container'], true)) {
        $meta .= '<upnp:albumArtURI>' . s4lox_metadata_xml($albumArtURI) . '</upnp:albumArtURI>';
    }

    $meta .= '<r:description>' . s4lox_metadata_xml($artist) . '</r:description>';
    $meta .= '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">' . s4lox_metadata_xml($token) . '</desc>';
    $meta .= '</item>';
    $meta .= '</DIDL-Lite>';

    try {
        if (in_array($type, ['Track', 'Playlist', 'container'], true)) {
            AddFavToQueue($file, $meta, $value, $stype);
        } else {
            SetAVToQueue($file, $meta, $value, $stype);
        }

        return true;
    } catch (Throwable $e) {
        AddFavToQueueCatch($file, $meta, $value, $stype, $e);
        return false;
    }
}

function AddFavToQueue($file, $meta, $value, $stype)
{
    global $sonos;

    if (!is_object($sonos) || !method_exists($sonos, 'AddToQueue')) {
        throw new RuntimeException('Sonos AddToQueue method is not available.');
    }

    $sonos->AddToQueue($file, $meta);
    LOGINF("Metadata.php: " . s4lox_metadata_log($stype) . " '" . s4lox_metadata_log($value['title'] ?? 'Unknown favorite') . "' has been added.");
}

function SetAVToQueue($file, $meta, $value, $stype)
{
    global $sonos;

    if (!is_object($sonos) || !method_exists($sonos, 'SetAVTransportURI')) {
        throw new RuntimeException('Sonos SetAVTransportURI method is not available.');
    }

    $sonos->SetAVTransportURI($file, $meta);
    LOGINF("Metadata.php: " . s4lox_metadata_log($stype) . " '" . s4lox_metadata_log($value['title'] ?? 'Unknown favorite') . "' has been added.");
}

function AddFavToQueueCatch($file, $meta, $value, $stype, $exception = null)
{
    $title = s4lox_metadata_log($value['title'] ?? 'Unknown favorite');
    LOGWARN("Metadata.php: Streaming type '" . s4lox_metadata_log($stype) . "' favorite '" . $title . "' could not be added.");

    if ($exception instanceof Throwable) {
        LOGWARN("Metadata.php: Sonos metadata operation failed: " . s4lox_metadata_log($exception->getMessage()));
    }

    LOGINF("Metadata.php: Please remove the favorite from Sonos Favorites or provide a metadata debug file for adding the missing streaming type.");
    CreateDebugFile($value, $file, $meta);
}

function AddFavToQueueError($value, $stype, $sid)
{
    $serviceName = $stype !== '' ? $stype : getServiceName($sid);
    $title = s4lox_metadata_log($value['title'] ?? 'Unknown favorite');

    LOGWARN("Metadata.php: Your Sonos favorite '" . $title . "' could not be added because streaming service '" . s4lox_metadata_log($serviceName) . "' is currently not supported.");
    LOGINF("Metadata.php: Please remove the favorite from Sonos Favorites or provide a metadata debug file for adding the missing streaming type.");
    CreateDebugFile($value, s4lox_metadata_string($value, 'resorg'), '');
}

function CreateDebugFile($value, $file, $meta)
{
    global $debugfile;

    $target = trim((string)($debugfile ?? ''));

    if ($target === '') {
        $target = '/tmp/s4lox_metadata_debug.json';
        LOGWARN("Metadata.php: Debug file path was not configured. Falling back to '" . $target . "'.");
    }

    $favorite = is_array($value) ? $value : [];
    $favorite['CurrentURI'] = (string)$file;
    $favorite['CurrentURIMetaData'] = (string)$meta;

    $json = json_encode($favorite, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        LOGWARN("Metadata.php: Debug metadata could not be encoded as JSON.");
        return;
    }

    if (file_put_contents($target, $json, LOCK_EX) === false) {
        LOGWARN("Metadata.php: Debug file '" . s4lox_metadata_log($target) . "' could not be written.");
        return;
    }

    LOGINF("Metadata.php: Debug file '" . s4lox_metadata_log($target) . "' has been created and saved for metadata troubleshooting.");
}

function s4lox_metadata_extract_sid($resorg)
{
    $resorg = (string)$resorg;

    if ($resorg === '') {
        return '';
    }

    $parts = parse_url($resorg);
    if (is_array($parts) && isset($parts['query'])) {
        parse_str($parts['query'], $query);
        if (isset($query['sid'])) {
            return s4lox_metadata_clean_sid($query['sid']);
        }
    }

    if (preg_match('/(?:^|[?&])sid=([^&]+)/', $resorg, $matches)) {
        return s4lox_metadata_clean_sid($matches[1]);
    }

    return '';
}

function s4lox_metadata_clean_sid($sid)
{
    $sid = trim((string)$sid);

    if ($sid === '') {
        return '000';
    }

    if (preg_match('/^\d+$/', $sid)) {
        return $sid;
    }

    return '000';
}

function s4lox_metadata_string($array, $key, $default = '')
{
    if (!is_array($array) || !array_key_exists($key, $array) || $array[$key] === null) {
        return (string)$default;
    }

    return (string)$array[$key];
}

function s4lox_metadata_xml($value)
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function s4lox_metadata_log($value)
{
    return str_replace(["\r", "\n"], ' ', (string)$value);
}
