<?php

/**
* Funktion : 	AddAmazon --> lädt Amazon Playlisten oder Alben
*
* @param: empty
* @return: Amazon Playlisten oder Alben in Queue
**/

// Playlist:
// x-sonosapi-hls-static:catalog/tracks/B00PM22KH2/%3fplaylistAsin%3dB071W6HN35%26playlistType%3dprimePlaylist?sid=201&flags=0&sn=8
// Album
// x-sonosapi-hls-static:catalog/tracks/B003Z6EX90/%3falbumAsin%3dB003Z69GS8?sid=201&flags=0&sn=8
// Track
// x-sonosapi-hls-static:catalog/tracks/B01F5KG8L4/%3fplaylistAsin%3dB077HNFYYG%26playlistType%3dprimePlaylist?sid=201&flags=0&sn=8

function AddAmazon() {
	
	global $sonoszone, $master, $sonos, $lookup, $volume;

	// playlist-ID = B07G4L7CLZ
	// album-ID = B003Z69GS8
	// track-ID = B09RF3D4GT
	
	$reg = 'SA_RINCON51463_X_#Svc51463-0-Token';
	$rand = mt_rand(1000000, 1999999);
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
	$sonos->ClearQueue();	
	
	// Amazon Track
	if (isset($_GET['trackuri'])) { 
		$pl = $_GET['trackuri'];
		$EnqueuedURI = 'x-sonos-http:catalog%2ftracks%2f'.$pl.'%2f%3fplaylistAsin%3d'.$pl.'H%26playlistType%3dhawkfirePlaylist.flac?sid=201&amp;flags=0&amp;sn=2';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="10030000catalog%2ftracks%2f'.$pl.'%2f%3fplaylistAsin%3d%26playlistType%3dprimePlaylist" parentID="-1" restricted="true">';
	}
	// Amazon Playlist
	elseif (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		$EnqueuedURI = 'x-rincon-cpcontainer:'.$rand.'ccatalog%2fplaylists%2f'.$pl.'%2f%23prime_playlist?sid=201&amp;sn=8';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="'.$rand.'ccatalog%2fplaylists%2f'.$pl.'%2f%23prime_playlist" parentID="-1" restricted="true">';
	}
	// Amazon Album
	elseif (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
		$EnqueuedURI = 'x-rincon-cpcontainer:'.$rand.'ccatalog%2falbums%2f'.$pl.'%2f%23album_desc?sid=201&amp;sn=8';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="'.$rand.'ccatalog%2falbums%2f'.$pl.'8%2f%23album_desc" parentID="-1" restricted="true">';
	} else {
		LOGGING("MusicService.php: The entered URI isn't correct. Please use 'trackuri' or 'playlisturi' or 'albumuri'! Check your URL/entry", 3);
		return false;
	}
	$EnqueuedURIMetaData .= '<dc:title>/dc:title><upnp:class>object.container.album.musicAlbum</upnp:class>';
	$EnqueuedURIMetaData .= '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">'.$reg.'</desc></item></DIDL-Lite>';
	try {
		$sonos->AddToQueue($EnqueuedURI, htmlspecialchars_decode($EnqueuedURIMetaData));
	} catch (Exception $e) {
		LOGGING("MusicService.php: The entered Amazon-ID ".$pl." seems to be not valid! Please check!", 3);
		exit;
	}
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("MusicService.php: Requested Amazon Music plays now.", 7);
	$sonos->Play();
}


/**
* Funktion : 	AddApple --> lädt Apple Music Track(s), Playlisten oder Alben
*
* @param: empty
* @return: Apple Track(s), Playlisten oder Alben
**/

// Playlist:
// https://itunes.apple.com/de/playlist/angesagt-dance/pl.6bf4415b83ce4f3789614ac4c3675740
// Album Link
// https://itunes.apple.com/de/album/eiskalt/1288630360
// Track (getpositioninfo[TrackURI)
// x-sonos-http:song:1280409269.mp4?sid=204&flags=8224&sn=10

function AddApple() {
	
	global $sonoszone, $master, $lookup, $sonos, $volume;

	// playlist geht = 914196c8783d46a5ba46f38eda448a43
	// album geht = 1623854804
	// track geht = 1308233284
	
	$reg = 'SA_RINCON52231_X_#Svc52231-0-Token';
	$rand = mt_rand(10000000, 19999999);
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
	$sonos->ClearQueue();	
		
	// Apple Track
	if (isset($_GET['trackuri'])) { 
		$pl = $_GET['trackuri'];
		$EnqueuedURI = 'x-sonos-http:song%3a'.$pl.'.mp4?sid=204&amp;flags=8224&amp;sn=21';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="'.$rand.'song%3a'.$pl.'" parentID="-1" restricted="true">';
	}
	// Apple Playlist
	elseif (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		$EnqueuedURI = 'x-rincon-cpcontainer:1006206cplaylist%3apl.'.$pl.'?sid=204&amp;flags=8300&amp;sn=21';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="'.$rand.'calbum%3a'.$pl.'" parentID="-1" restricted="true">';
	}
	// Apple Album
	elseif (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
		$EnqueuedURI = 'x-rincon-cpcontainer:1004206calbum%3a'.$pl.'?sid=204&amp;flags=8300&amp;sn=21';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="'.$rand.'calbum%3a'.$pl.'" parentID="-1" restricted="true">';
	} else {
		LOGGING("MusicService.php: The entered URI isn't correct. Please use 'trackuri' or 'playlisturi' or 'albumuri'! Check your URL/entry", 3);
		return false;
	}
	$EnqueuedURIMetaData .= '<dc:title>/dc:title><upnp:class>object.container.album.musicAlbum</upnp:class>';
	$EnqueuedURIMetaData .= '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">'.$reg.'</desc></item></DIDL-Lite>';
	try {
		$sonos->AddToQueue($EnqueuedURI, htmlspecialchars_decode($EnqueuedURIMetaData));
	} catch (Exception $e) {
		LOGGING("MusicService.php: The entered Apple-ID ".$pl." seems to be not valid! Please check!", 3);
		exit;
	}
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("MusicService.php: Requested Apple Music plays now.", 7);
	$sonos->Play();
}




/**
* Funktion : 	AddNapster --> lädt Napster Music Playlisten oder Alben
*
* @param: empty
* @return: Napster Playlisten oder Alben in Queue
**/

// Playlist:
// https://app.napster.com/playlist/pp.207466845
// Album Link
// https://app.napster.com/artist/art.4212/album/alb.285904254
// Track
// 

	// playlist geht = 179779424
	// album geht = 287008560, 665083375



function AddNapster() {
	
	global $sonoszone, $master, $lookup, $sonos, $volume;

	$mail = '';
	$reg = 'SA_RINCON51975_'.$mail;
	$rand = mt_rand(1000000, 1999999);

	$sonos = new SonosAccess($sonoszone[$master][0]);
	$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
	$sonos->ClearQueue();	
		
	// Napster Track
	if (isset($_GET['trackuri'])) { 
		LOGGING("Napster track is currently not supported!", 4);
		exit;
	}
	// Napster Playlist
	elseif (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		$EnqueuedURI = 'x-rincon-cpcontainer:100e004cexplore%3aplaylist%3a%3app.'.$pl.'?sid=203&amp;flags=8428&amp;sn=27';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="'.$rand.'cexplore%3aplaylist%3a%3app.'.$pl.'" parentID="-1" restricted="true">';
	}
	// Napster Album
	elseif (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
		$EnqueuedURI = 'x-rincon-cpcontainer:100420ecexplore%3aalbum%3a%3aAlb.'.$pl.'?sid=203&amp;flags=8428&amp;sn=27';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="'.$rand.'ecexplore%3aalbum%3a%3aAlb.'.$pl.'" parentID="-1" restricted="true">';
	} else {
		LOGGING("MusicService.php: The entered URI isn't correct. Please use 'trackuri' or 'playlisturi' or 'albumuri'! Check your URL/entry", 3);
		return false;
	}
	$EnqueuedURIMetaData .= '<dc:title>/dc:title><upnp:class>object.container.album.musicAlbum</upnp:class>';
	$EnqueuedURIMetaData .= '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">'.$reg.'</desc></item></DIDL-Lite>';
	try {
		$sonos->AddToQueue($EnqueuedURI, htmlspecialchars_decode($EnqueuedURIMetaData));
	} catch (Exception $e) {
		LOGGING("MusicService.php: The entered Napster-ID ".$pl." seems to be not valid! Please check!", 3);
		exit;
	}
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("MusicService.php: Requested Napster Music plays now.", 7);
	$sonos->Play();
}



/**
* Funktion : 	AddSpotify --> lädt Spotify Track(s), Playlisten oder Alben
*
* @param: empty
* @return: Spotify Track(s), Playlisten oder Alben added to PL
**/

// Playlist:
// spotify:user:spotify:playlist:37i9dQZF1DX36edUJpD76c
// Album
// https://open.spotify.com/album/4ADIqpPBchOMHfHYRL9HU1
// Track ...getpositioninfo -> [TrackURI]
// x-sonos-spotify:spotify:track:6If6tXpbwYs5zBop1AqfwG?sid=9&flags=8224&sn=5

function AddSpotify($user = '') {
	global $sonoszone, $master, $sonos, $lookup, $volume;

	// playlist geht = 37i9dQZF1DX1i11qSEWNoS, 18J8kfJh79lgmZZGOcV7dZ
	// album geht = 0PNXB6AmSfM9oS0YwNkCYH, 7GcrxecamwbZqW0Vf0TEo7
	// track geht = 4hKWUzhQWsOkgT6LnDEASe, 7vGuf3Y35N4wmASOKLUVVU
	
	$reg = 'SA_RINCON2311_X_#Svc2311-0-Token';   // Region EU
	#$reg = 'SA_RINCON3079_X_#Svc3079-0-Token';  // Region US
	$rand = mt_rand(1000000, 1999999);
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
	$sonos->ClearQueue();	
		
	// Spotify Track
	if (isset($_GET['trackuri'])) { 
		$pl = $_GET['trackuri'];
		$EnqueuedURI = 'x-sonos-spotify:spotify%3atrack%3a'.$pl.'?sid=9&amp;sn=5';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="16054235spotify%3atrack%3a'.$pl.'" parentID="-1" restricted="true">';
	}
	// Spotify Playlist
	elseif (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		#$EnqueuedURI = 'x-rincon-cpcontainer:'.$rand.'cspotify%3auser%3a'.$user.'%3aplaylist%3a'.$pl.'?sid=9&amp;flags=8300&amp;sn=5';
		$EnqueuedURI = 'x-rincon-cpcontainer:1006206cspotify%3aplaylist%3a'.$pl.'?sid=9&amp;flags=8300&amp;sn=18';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		#$EnqueuedURIMetaData .= '<item id="1006206cspotify%3a'.$user.'%3aspotify%3aplaylist%3a'.$pl.'" parentID="-1" restricted="true">';
		$EnqueuedURIMetaData .= '<item id="1006206cspotify%3aplaylist%3a'.$pl.'" parentID="-1" restricted="true">';
	}
	// Spotify Album
	elseif (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
		$EnqueuedURI = 'x-rincon-cpcontainer:'.$rand.'cspotify%3aalbum%3a'.$pl.'?sid=9&amp;sn=5';
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="1004206cspotify%3aalbum%3a'.$pl.'" parentID="-1" restricted="true">';
	} else {
		LOGGING("MusicService.php: The entered URI isn't correct. Please use 'trackuri' or 'playlisturi' or 'albumuri'! Check your URL/entry", 3);
		return false;
	}
	$EnqueuedURIMetaData .= '<dc:title>/dc:title><upnp:class>object.container.album.musicAlbum</upnp:class>';
	$EnqueuedURIMetaData .= '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">'.$reg.'</desc></item></DIDL-Lite>';
	try {
		$sonos->AddToQueue($EnqueuedURI, htmlspecialchars_decode($EnqueuedURIMetaData));
	} catch (Exception $e) {
		LOGGING("MusicService.php: The entered Spotify-ID ".$pl." seems to be not valid! Please check!", 3);
		exit;
	}
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("MusicService.php: Requested Spotify Music plays now.", 7);
	$sonos->Play();
}


/**
* Funktion : 	AddTrack --> lädt lokales Track
*
* @param: empty
* @return: Track in Queue
**/

// Examples:
# 
# //HP-LAPTOP/Music/Test%20Sonos/Aaliyah%20-%2002%20-%20Loose%20Rap.mp3

function AddTrack() {
	
	global $sonoszone, $master, $sonos, $lookup, $volume, $mute;

	$sonos = new SonosAccess($sonoszone[$master][0]);
	$rincon = $sonoszone[$master][1];
	$sonos->ClearQueue();
	$curr_track_tmp = $sonos->GetPositionInfo();
	// check if Radio/Line IN/TV is playing, then switch to PL
	empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
	
	
	// local Track
	if (isset($_GET['file'])) {  
		$uri = $_GET['file'];
		if (empty($uri)) {
			LOGGING("local_track.php: Please enter file name!", 3);
			exit;
		}
		// check if audio format is supported
		$length = strripos($uri,'.');
		$format = trim(substr($uri, $length +1 , $length + 5));
		$check_audio = AudioFormat(strtoupper($format));
		$check_audio === false ? LOGGING("local_track.php: The entered audio format: '.".$format."' is not supported by Sonos. Please correct!", 3) : '';
		// check diff. usage of syntax (WIN or LINUX)
		$tmp = substr($uri, 0 ,2);
		if ($tmp == "\\\\") {
			$parts = explode("\\", $uri);
		} else {
			$parts = explode("/", $uri);
		}
		$parts = array_map("rawurlencode", $parts);
		$parts[2] = strtoupper($parts[2]);
		$file = implode("/", $parts);
		$track = "x-file-cifs:" . $file;
		$EnqueuedURI = $track;
		$EnqueuedURIMetaData = '<DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/">';
		$EnqueuedURIMetaData .= '<item id="'.$file.'" parentID="-1" restricted="true">';
		$EnqueuedURIMetaData .= '<dc:title>/dc:title><upnp:class>object.container.album.musicAlbum</upnp:class>';
		$EnqueuedURIMetaData .= '<desc id="cdudn" nameSpace="urn:schemas-rinconnetworks-com:metadata-1-0/">RINCON_AssociatedZPUDN</desc></item></DIDL-Lite>';
		try {
			$sonos->AddToQueue($EnqueuedURI, htmlspecialchars_decode($EnqueuedURIMetaData));
		} catch (Exception $e) {
			LOGGING("local_track.php: The entered file: ".$uri." is not valid or could not be accessed! Please check!", 3);
			exit;
		}
		LOGGING('local_track.php: The entered Local-Track has been loaded',6);
	}
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	try {
		LOGGING("local_track.php: Requested local track plays now.", 7);
		$sonos->Play();
	} catch (Exception $e) {
		LOGGING("local_track.php: The Requested local track could not be played! Please check folder permission or if file exists", 3);	
		exit;
	}
}



/**
* Funktion : 	AudioFormat --> prüft ob eingegebenes Audio Format von Sonos unterstützt wird
*
* @param: $model --> Audio Format
* @return: $format --> true or false
**/

function AudioFormat($audio) {
    $format = [
		"MP3"  =>  "MP§ Audio Format",
        "WMA"  =>  "Windows Media Audio",
        "AAC"  =>  "Advanced Audio Coding",
        "OGG"  =>  "Ogg Vorbis Compressed Audio File",
        "FLAC" =>  "Free Lossless Audio Codec",
        "ALAC" =>  "Apple Lossless Audio Codec",
		"AIFF" =>  "Audio Interchange File Format",
		"WAV"  =>  "Waveform Audio File Format",
        ];
    return in_array($audio, array_keys($format));
}



?>
