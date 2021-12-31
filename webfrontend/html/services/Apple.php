<?php

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
	global $sonoszone, $master, $sonos, $volume;

	// playlist geht = efaf877db72a4c05b2654eb4371d6c24, 5030c32a73e9459d82fae6f7b046d3ec
	// album geht = 1288630360, 1266795371
	// track geht = 1308233284, 1280409269, 1308317897
	
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$rincon = $sonoszone[$master][1];
	$curr_track_tmp = $sonos->GetPositionInfo();
	// check if Radio/Line IN/TV is playing, then switch to PL
	empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
	
	// Apple Track(s)
	if (isset($_GET['trackuri'])) {  
		$uri = $_GET['trackuri'];
		if (empty($uri)) {
			LOGGING("Sonos: apple.php: Please enter Apple Track-URI!", 3);
			exit;
		}
		$track_array = explode(',',$uri);
		$curr_track = $curr_track_tmp['Track'];
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
				$service->SetAppleTrack($trackuri, $message_pos);
			}
		} catch (Exception $e) {
			LOGGING("Sonos: apple.php: The entered Apple-Track-ID's: ".$uri." are not valid! Please check!", 3);
			exit;
		}
		LOGGING('Sonos: apple.php: The entered Apple-Track has been loaded',6);
		$sonos->SetTrack($message_pos);
	}
	// Apple Playlist
	if (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetApplePlaylist($pl);
		} catch (Exception $e) {
			LOGGING("Sonos: apple.php: The entered Apple-Playlist-ID: ".$pl." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('Sonos: apple.php: The entered Apple-Playlist has been loaded',6);
	}
	// Apple Album
	if (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetAppleAlbum($pl);
		} catch (Exception $e) {
			LOGGING("Sonos: apple.php: The entered Apple-Album-ID ".$pl." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('Sonos: apple.php: The entered Apple-Album has been loaded',6);
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("Sonos: apple.php: Requested Apple Music plays now.", 7);
	$sonos->Play();
}



?>
