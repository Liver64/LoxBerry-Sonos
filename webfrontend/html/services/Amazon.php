<?php

/**
* Funktion : 	AddAmazon --> lädt Amazon Playlisten oder Alben
*
* @param: empty
* @return: Amazon Playlisten oder Alben in Queue
**/

// GetPositionInfo[TrackURI]

// Playlist:
// x-sonosapi-hls-static:catalog/tracks/B00PM22KH2/%3fplaylistAsin%3dB071W6HN35%26playlistType%3dprimePlaylist?sid=201&flags=0&sn=8
// Album
// x-sonosapi-hls-static:catalog/tracks/B003Z6EX90/%3falbumAsin%3dB003Z69GS8?sid=201&flags=0&sn=8
// Track
// x-sonosapi-hls-static:catalog/tracks/B01F5KG8L4/%3fplaylistAsin%3dB077HNFYYG%26playlistType%3dprimePlaylist?sid=201&flags=0&sn=8

function AddAmazon() {
	global $sonoszone, $master, $sonos, $volume;

	// playlist geht = B071JFKCXV, B071W6HN35
	// album geht = B003Z69GS8, B00BLWYTE4
	// track = B01F5KG8L4,B077HNFYYG 
	
	
	
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$rincon = $sonoszone[$master][1];
	$curr_track_tmp = $sonos->GetPositionInfo();
	// check if Radio/Line IN/TV is playing, then switch to PL
	empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
	
	// Amazon Track
	if (isset($_GET['trackuri'])) { 
		LOGGING("amazon.php: Amazon Track-URI is currently not supported!",3);	
		exit;
		$uri = $_GET['trackuri'];
		if (empty($uri)) {
			LOGGING("amazon.php: Please enter Amazon Track-URI!", 3);
			exit;
		}
		$track_array = explode(',',$uri);
		if (count($track_array) > 2) {
			LOGGING("amazon.php: Please enter just one Amazon Track-URI!", 3);
			exit;
		}
		$curr_track = $curr_track_tmp['Track'];
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
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
				#$service->SetAmazonTrack($trackuri, $rincon);
				$service->SetAmazonTrack($track_array[0], $track_array[1]);
			}
		} catch (Exception $e) {
			LOGGING("amazon.php: The entered Amazon-Track-ID's: ".$uri." are not valid! Please check!", 3);
			exit;
		}
		LOGGING('amazon.php: The entered Amazon-Track has been loaded',6);
		$sonos->SetTrack($message_pos);
	}
	// Amazon Playlist
	if (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetAmazonPlaylist($pl);
		} catch (Exception $e) {
			LOGGING("amazon.php: The entered Amazon-Playlist-ID: ".$pl." is not valid! Please check!",3);
			exit;
		}
		LOGGING('amazon.php: The entered Amazon-Playlist has been loaded',6);
	}
	// Amazon Album
	if (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetAmazonAlbum($pl);
		} catch (Exception $e) {
			LOGGING("amazon.php: The entered Amazon-Album-ID ".$pl." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('amazon.php: The entered Amazon-Album has been loaded',6);
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("amazon.php: Requested Amazon Music plays now.", 7);
	$sonos->Play();
}



?>
