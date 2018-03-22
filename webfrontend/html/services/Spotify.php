<?php

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

function AddSpotify() {
	global $sonoszone, $master, $sonos, $volume;

	// playlist geht = 37i9dQZF1DX3h1vasAdBTc, 37i9dQZEVXcCOxDenytp7k
	// album geht = 0PNXB6AmSfM9oS0YwNkCYH, 7GcrxecamwbZqW0Vf0TEo7
	// track geht = 4hKWUzhQWsOkgT6LnDEASe, 7vGuf3Y35N4wmASOKLUVVU, 56zQMVWGBBTVSItzddscyj, 6If6tXpbwYs5zBop1AqfwG
	
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$rincon = $sonoszone[$master][1];
	$curr_track_tmp = $sonos->GetPositionInfo();
	// check if Radio/Line IN/TV is playing, then switch to PL
	empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
	
	if (isset($_GET['user'])) {
		$user = $_GET['user'];
	} else {
		$user = "spotify";
	}
	// Spotify Track(s)
	if (isset($_GET['trackuri'])) {
		$uri = $_GET['trackuri'];
		if (empty($uri)) {
			LOGGING("Please enter Spotify Track-URI!", 3);
			exit;
		}
		$track_array = explode(',',$uri);
		$curr_track = $curr_track_tmp['Track'];
		empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
		if ($curr_track >= 1 && $curr_track <= 998 && !empty($curr_track_tmp['duration'])) {
			$message_pos = $curr_track + 1;
		} else {
			// No Playlist or Radio/TV is playing 
			$sonos->ClearQueue();
			$message_pos = 1;
		}
		try {
			foreach ($track_array as $trackuri) {
				$service = New SonosMusicService($sonoszone[$master][0]);
				$service->SetSpotifyTrack($trackuri, $message_pos);
			}
		} catch (Exception $e) {
			LOGGING("The entered Spotify-Track-URI: ".$trackuri." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('The entered Spotify-Track has been loaded',6);
		$sonos->SetTrack($message_pos);
	}
	// Spotify Playlist
	if (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetSpotifyPlaylist($pl, $user);
		} catch (Exception $e) {
			LOGGING("The entered Spotify-Playlist-URI: ".$pl." is not valid! Please check!", 3);
			exit;
		}LOGGING('The entered Spotify-Playlist has been loaded',6);
	}
	// Spotify Album
	if (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetSpotifyAlbum($pl);
		} catch (Exception $e) {
			LOGGING("The entered Spotify-Album-URI: ".$pl." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('The entered Spotify-Album has been loaded',6);
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("Requested Spotify Music plays now.", 7);
	$sonos->Play();
}



?>
