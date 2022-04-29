<?php

/**
* Funktion : 	AddGoogle --> lädt Google Playlisten oder Alben
*
* @param: empty
* @return: Google Playlisten oder Alben added to PL
**/

// Playlist:
// 
// Album
// https://play.google.com/store/music/album/WIRTZ_Die_f%C3%BCnfte_Dimension?id=Bhnzl7fvl5wpg67snkkhjquml7y
// Track
// x-sonosapi-hls-static:catalog/tracks/B0727YDWXG/?sid=201&flags=0&sn=8
// https://play.google.com/store/music/album?id=B5ajs37gooyyku2qrkqc3ructey&tid=song-Tyxfeuv5grxtfxjtwecrxf3vhom

function AddGoogle() {
	global $sonoszone, $master, $sonos, $volume;
	
	LOGGING("google.php: Google is currently not supported!", 3);
	exit;
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$rincon = $sonoszone[$master][1];
	$curr_track_tmp = $sonos->GetPositionInfo();
	// check if Radio/Line IN/TV is playing, then switch to PL
	empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
	
	// Google Track(s) -> NOT WORKING
	if (isset($_GET['trackuri'])) {
		$uri = $_GET['trackuri'];
		if (empty($uri)) {
			LOGGING("google.php: Please enter Google Track-URI!", 3);
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
				$service->SetGoogleTrack($trackuri, $message_pos);
			}
		} catch (Exception $e) {
			LOGGING("google.php: The entered Google-Track-URI: ".$trackuri." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('google.php: The entered Google-Track has been loaded',6);
		$sonos->SetTrack($message_pos);
	}
	// Google Playlist -> NOT REALLY WORKING
	if (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			#$service->SetGooglePlaylist($pl, $reg);
			$service->SetGooglePlaylist($pl);
		} catch (Exception $e) {
			LOGGING("google.php: The entered Google-Playlist-URI: ".$pl." is not valid or is a User Playlist! Please check!", 3);
			exit;
		}
		LOGGING('google.php: The entered Google-Playlist has been loaded',6);
	}
	// Google Album -> NOT WORKING
	if (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetGoogleAlbum($pl, $reg);
		} catch (Exception $e) {
			LOGGING("google.php: The entered Google-Album-URI: ".$pl." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('google.php: The entered Google-Album has been loaded',6);
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("google.php: Requested Google Music plays now.", 7);
	$sonos->Play();
}



?>
