<?php

/**
* Function : metadata --> creates metadata for Sonos
* 
* 
* @param: array of BrowseFavoriten()
* @return: loaded Tracks/RadioStations
**/

function metadata($value) 
{
	global $sonos, $sid, $volume, $sonoszone, $master, $services, $radiofav, $radiolist, $radiofavtmp, $file, $meta, $stype;
	
	#print_r($value);
	
	# BACKUP Scenario... check if sid exist, if not assign unknown
	if (array_key_exists('sid', $value) === true)   {
		$sid = $value['sid'];
	} else {
		$sid = "000";
		LOGINF ("metadata.php: No 'sid' has been received.");
	}

	switch ($sid) {
		case "201":		// Amazon music
			if ($value['typ'] == "Radio")   {
				$stype = "Amazon Radio Favorite";
			} elseif ($value['typ'] == "Track")   {
				$stype = "Amazon Track Favorite";
			} elseif ($value['typ'] == "Playlist")   {
				$stype = "Amazon Playlist Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		case "998":		// Sonos Playlist
			$stype = "Sonos Playlist";
			$value['UpnpClass'] = "object.container.playlistContainer";
			$value['albumArtURI'] = "";
			$value['parentid'] = "SQ:";
			$value['artist'] = "Sonos-Playlist";
			$value['token'] = "RINCON_AssociatedZPUDN";
			CreateDIDL($value, $stype);
		break;
		
		case "999":		// Local Music
			if (substr($value['resorg'], 0, 11) == "x-file-cifs")   {
				$stype = "Local Music Track";
				$getupnpclass = "object.item.audioItem.musicTrack";
			} elseif (substr($value['resorg'], 0, 17) == "x-rincon-playlist")   {
				$stype = "Local Music Album";
				$getupnpclass = "object.container.album.musicAlbum";
			}
			$value['token'] = "RINCON_AssociatedZPUDN";
			CreateDIDL($value, $stype);
		break;
		
		case "303":		// Sonos Radio
			$tempradio = $sonos->GetMediaInfo();
			$haystack = $tempradio["CurrentURI"];
			$needleTuneIn = "tunein";		// searc for TuneIn
			$containTuneIn = mb_strpos($haystack, $needleTuneIn) !== false;
			# determine whether it's TuneIn or Sonos Radio in case sid=303
			if ($containTuneIn === true)  {	
				$stype = "TuneIn Favorite";
			} else {
				$stype = "Sonos Radio Favorite";
			}
			$file = $value['resorg'];
			CreateDIDL($value, $stype);
		break;
		
		
		case "160":		// Soundcloud
			$stype = "Soundcloud Track Favorite";
			if ($value['typ'] == "Track")    {
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Soundcloud Playlist Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		
		case "9":		// Spotify 
			if ($value['typ'] == "Playlist")    {
				$stype = "Spotify Playlist Favorite";
			} elseif ($value['typ'] == "Track")   {
				$stype = "Spotify Track Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		
		case "181":		// Mixcloud
			if ($value['typ'] == "Track")    {
				$stype = "Mixcloud Track Favorite";
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Mixcloud Playlist Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		
		case "254":		// TuneIn Radio
			$value['token'] = "SA_RINCON65031_";
			if (!isset($value['protocolInfo']))    {
				$stype = "TuneIn";
				$value['UpnpClass'] = "object.item.audioItem.audioBroadcast";
				$value['artist'] = "TuneIn Radio";
			} else {
				$stype = "TuneIn Favorite";
				$value['UpnpClass'] = "object.item.audioItem.audioBroadcast";
				$value['artist'] = "TuneIn Radio";
			}
			CreateDIDL($value, $stype);
		break;
		
		case "333":		// TuneIn Radio (new)
			$value['token'] = "SA_RINCON65031_";
			if (!isset($value['protocolInfo']))    {
				$stype = "TuneIn (New)";
				$value['UpnpClass'] = "object.item.audioItem.audioBroadcast";
				$value['artist'] = "TuneIn (New) Radio";
			} else {
				$stype = "TuneIn (New) Favorite";
				$value['UpnpClass'] = "object.item.audioItem.audioBroadcast";
				$value['artist'] = "TuneIn (New) Radio";
			}
			CreateDIDL($value, $stype);
		break;
		
		
		case "2":		// Deezer
			if ($value['typ'] == "Track")    {
				$stype = "Deezer Track Favorite";
			} elseif ($value['typ'] == "Radio")   {
				$stype = "Deezer Radio Favorite";
			} elseif ($value['typ'] == "Playlist")   {
				$stype = "Deezer Playlist Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		
		case "204":		// Apple Music
			if ($value['typ'] == "Radio")    {
				$stype = "Apple Radio Favorite";
			} elseif ($value['typ'] == "Track")    {
				$stype = "Apple Track Favorite";
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Apple Playlist Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		
		case "203":		// Napster
			if ($value['typ'] == "Radio")    {
				$stype = "Napster Radio Favorite";
			} elseif ($value['typ'] == "Track")    {
				$stype = "Napster Track Favorite";
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Napster Playlist Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		case "284":		// YouTube Music
			if ($value['typ'] == "Track")    {
				$stype = "YouTube Track Favorite";
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "YouTube Playlist Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		case "174":		// Tidal
			if ($value['typ'] == "Track")    {
				$stype = "Tidal Track Favorite";
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Tidal Playlist Favorite";
			} else {
				AddFavToQueueError($value, $stype, $services, $sid);
				return false;
			}
			CreateDIDL($value, $stype);
		break;
		
		case "000":		// Streaming Service identified as unkown
			$stype = "Unknown Streaming Service";
			LOGWARN ("metadata.php: Your Sonos Favorite '".$value['title']."' could not be added because your Streaming Service '000' is unknown");
			LOGINF ("metadata.php: If you are interest to get missing type added AND your familiar using Application 'Wireshark' you can contact me or remove Favorite from your Sonos Favorites");
			CreateDebugFile();
			return false;
		break;
		
		default:
			if (isService($sid) === true)   {
				LOGDEB ("Trying to add Streaming Service to Queue");
				try {
					CreateDIDL($value, $stype);
					LOGOK ("metadata.php: Streaming Service has been added to Queue");
				} catch (Exception $e) {
					$stype = $service;
					AddFavToQueueError($value, $stype, $services, $sid);
					LOGWARN ("metadata.php: Trying to add Streaming Service failed");
					return false;
				}
			}
		break;
		return;
	}
}


/**
* Funktion : 	isService --> load actually SID from player
*
* @param: 	$sid --> sid from URI
* @return:  $array

	Array
	(
		[294] => Radio Javan
		[237] => storePlay
		[256] => CBC Radio & Music
		[317] => Yogi Tunes
		[309] => jazzed
		[511] => 90s90s Radio
		...
**/

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

    try {
        $services = $sonos->GetAvailableServicesMap();
    } catch (Exception $e) {
        $services = [];
    }

    // Manuelle Ergänzungen / lokale Sonderfälle
    $services['996'] = $services['996'] ?? 'Plugin Radio';
    $services['998'] = $services['998'] ?? 'Sonos Playlist';
    $services['999'] = $services['999'] ?? 'Local Music';
    $services['000'] = $services['000'] ?? 'unknown';

    return $services;
}


function getServiceName($sid)
{
    $services = loadServices();
    return $services[(string)$sid] ?? $services['000'];
}


function CreateDIDL($value, $stype)
{
	global $meta, $stype, $file;
	
	$meta = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
	$meta .= '<item id="'.$value['id'].'" ';
	$meta .= 'parentID="-1" restricted="true">';
	$meta .= '<dc:title>'.htmlspecialchars($value['title']).'</dc:title>';
	$meta .= '<upnp:class>'.$value['UpnpClass'].'</upnp:class>';
	if ($value['typ'] == "Track" or $value['typ'] == "Playlist" or $value['typ'] == "container")   {
		$meta .= '<upnp:albumArtURI>'.$value['albumArtURI'].'</upnp:albumArtURI>';
	}
	$meta .= '<r:description>'.htmlspecialchars($value['artist']).'</r:description>';
	$meta .= '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">'.$value['token'].'</desc>';
	$meta .= '</item>';
	$meta .= '</DIDL-Lite>';
	#echo htmlspecialchars($meta);
	#echo "<br>";
	
	$file = $value['resorg'];
	try {	
		if ($value['typ'] == "Track" or $value['typ'] == "Playlist" or $value['typ'] == "container")   {
			AddFavToQueue($file, htmlspecialchars_decode($meta), $value, $stype);
			return true;
		} else {
			SetAVToQueue($file, htmlspecialchars_decode($meta), $value, $stype);
			return true;
		}				
	} catch (Exception $e) {
		AddFavToQueueCatch($file, $meta, $value, $stype);
		return false;
	}
}


function AddFavToQueue($file, $meta, $value, $stype)
{
	global $sonos, $file, $meta, $stype;
	
	@$sonos->AddToQueue($file, $meta);
	LOGINF ("metadata.php: ".$stype." '".htmlspecialchars($value['title'])."' has been added");
	return;
}

function SetAVToQueue($file, $meta, $value, $stype)
{
	global $sonos, $file, $meta, $stype;

	@$sonos->SetAVTransportURI($file, $meta);
	LOGINF ("metadata.php: ".$stype." '".htmlspecialchars($value['title'])."' has been added");
	return;
}

function AddFavToQueueCatch($file, $meta, $value, $stype)
{
	global $sonos, $file, $meta, $stype, $sid;
	
	LOGWARN ("metadata.php: Streaming type: ".$stype." '".htmlspecialchars($value['title'])."' could not be added");
	LOGINF ("metadata.php: If you are interest to get missing type added AND your familiar using Application 'Wireshark' you can contact me or remove Favorite from your Sonos Favorites");
	CreateDebugFile();
	return;
}

function AddFavToQueueError($file, $meta, $value, $stype, $services, $sid)
{
	global $sonos, $file, $meta, $stype;
	
	LOGWARN ("metadata.php: Your Sonos Favorite '".htmlspecialchars($value['title'])."' could not be added because your Streaming Service '".$services[$sid]."' is currently not supported");
	LOGINF ("metadata.php: If you are interest to get missing type added AND your familiar using Application 'Wireshark' you can contact me or remove Favorite from your Sonos Favorites");
	CreateDebugFile();
	return;
}

function CreateDebugFile()
{
	global $sonos, $debugfile, $value, $meta, $file;

	$favorite 						= $value;
	$favorite['CurrentURI'] 		= $file;
	$favorite['CurrentURIMetaData'] = $meta;
	file_put_contents($debugfile, json_encode($favorite, JSON_PRETTY_PRINT));
	LOGINF ("metadata.php: Debug file '".$debugfile."' has been created and saved. Please pick up file and send to 'olewald64@gmail.com' so i can support you adding missing Streaming type/Streaming Service");
	return;
}


?>