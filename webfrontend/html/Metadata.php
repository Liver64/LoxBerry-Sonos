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
	global $sonos, $volume, $sonoszone, $master, $services, $radiofav, $radiolist, $radiofavtmp, $file, $meta, $stype;
	
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
		
		case "284":		// YoutTube Music
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
* Funktion : 	isService --> prüft ob die gefundene SID unterstützt wird
*
* @param: 	$sid --> sid von URI
* @return:  $services --> Not available Service
**/

 function isService($sid) {
	 
	global $services;
	
    $services = [
            "38"=>"7digital",
			"321"=>"80s80s - REAL 80s Radio",
			"201"=>"Amazon Music",
			"198"=>"Anghami",
			"204"=>"Apple Music",
			"275"=>"ARTRADIO - RadioArt.com",
			"306"=>"Atmosphere by Kollekt.fm",
			"239"=>"Audible",
			"219"=>"Audiobooks.com",
			"157"=>"Bandcamp",
			"307"=>"Bookmate",
			"283"=>"Calm",
			"144"=>"Calm Radio",
			"256"=>"CBC Radio & Music",
			"191"=>"Classical Archives",
			"315"=>"Convoy Network",
			"213"=>"Custom Channels",
			"2"=>"Deezer",
			"234"=>"deliver.media",
			"285"=>"Epidemic Spaces",
			"182"=>"FamilyStream",
			"217"=>"FIT Radio Workout Music",
			"192"=>"focus@will",
			"167"=>"Gaana",
			"279"=>"Global Player",
			"36"=>"Hearts of Space",
			"45"=>"hotelradio.fm",
			"310"=>"iBroadcast",
			"271"=>"IDAGIO",
			"300"=>"JUKE",
			"305"=>"Libby by OverDrive",
			"221"=>"LivePhish+",
			"260"=>"Minidisco",
			"181"=>"Mixcloud",
			"171"=>"Mood Mix",
			"33"=>"Murfie",
			"262"=>"My Cloud Home",
			"268"=>"myTuner Radio",
			"203"=>"Napster",
			"277"=>"NRK Radio",
			"230"=>"NTS Radio",
			"222"=>"nugs.net",
			"324"=>"Piraten.FM",
			"212"=>"Plex",
			"233"=>"Pocket Casts",
			"265"=>"PowerApp",
			"31"=>"Qobuz",
			"294"=>"Radio Javan",
			"308"=>"Radio Paradise",
			"264"=>"radio.net",
			"154"=>"Radionomy",
			"162"=>"radioPup",
			"312"=>"Radioshop",
			"223"=>"RauteMusik.FM",
			"270"=>"Relisten",
			"150"=>"RUSC",
			"164"=>"Saavn",
			"303"=>"Sonos Radio",
			"160"=>"Soundcloud",
			"189"=>"SOUNDMACHINE",
			"218"=>"Soundsuit.fm",
			"295"=>"Soundtrack Player",
			"9"=>"Spotify",
			"163"=>"Spreaker",
			"184"=>"Stingray Music",
			"13"=>"Stitcher",
			"237"=>"storePlay",
			"226"=>"Storytel",
			"235"=>"Sveriges Radio",
			"211"=>"The Music Manager",
			"174"=>"TIDAL",
			"287"=>"toníque",
			"169"=>"Tribe of Noise",
			"193"=>"Tunify for Business",
			"254"=>"TuneIn",
			"333"=>"TuneIn (new)",
			"231"=>"Wolfgang's Music",
			"272"=>"Worldwide FM",
			"317"=>"Yogi Tunes",
			"284"=>"YouTube Music",
			"996"=>"Plugin Radio",
			"998"=>"Sonos Playlist",
			"999"=>"Local Music",
			"000"=>"unknown",
        ];
    return in_array($sid, array_keys($services));
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
	LOGINF ("metadata.php: : ".$stype." '".htmlspecialchars($value['title'])."' has been added1");
	return;
}

function SetAVToQueue($file, $meta, $value, $stype)
{
	global $sonos, $file, $meta, $stype;

	@$sonos->SetAVTransportURI($file, $meta);
	LOGINF ("metadata.php: : ".$stype." '".htmlspecialchars($value['title'])."' has been added2");
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