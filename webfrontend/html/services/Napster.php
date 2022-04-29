<?php

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

$mail = '';

function AddNapster() {
	global $sonoszone, $master, $sonos, $volume, $mail;

	// playlist geht = 207466845, 249764303
	// album geht = 287008560, 285904254
		
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$rincon = $sonoszone[$master][1];
	$curr_track_tmp = $sonos->GetPositionInfo();
	// check if Radio/Line IN/TV is playing, then switch to PL
	empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
	
	// Napster Track(s) - NOT IMPLEMENTED
	if (isset($_GET['trackuri'])) {  
		LOGGING("napster.php: Napster Track-URI is currently not supported!", 3);	
		exit;
		$uri = $_GET['trackuri'];
		if (empty($uri)) {
			LOGGING("napster.php: Please enter Napster Track-URI!", 3);
			exit;
		}
		$track_array = explode(',',$uri);
		if (count($track_array) > 2) {
			LOGGING("napster.php: Please enter just one Napster Track-URI!", 3);
			exit;
		}
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
				$service->SetNapsterTrack($trackuri);
			}
		} catch (Exception $e) {
			LOGGING("napster.php: The entered Napster-Track-ID's: ".$uri." are not valid! Please check!", 3);
			exit;
		}
		LOGGING('napster.php: The entered Napster-Track has been loaded',6);
		$sonos->SetTrack($message_pos);
	}
	// Napster Playlist
	if (isset($_GET['playlisturi'])) {
		$pl = $_GET['playlisturi'];
		$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetNapsterPlaylist($pl, $mail);
		} catch (Exception $e) {
			LOGGING("napster.php: The entered Napster-Playlist-ID: ".$pl." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('napster.php: The entered Napster-Playlist has been loaded',6);
	}
	// Napster Album
	if (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
			$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetNapsterAlbum($pl, $mail);
		} catch (Exception $e) {
			LOGGING("napster.php: The entered Napster-Album-ID ".$pl." is not valid! Please check!", 3);
			exit;
		}
		LOGGING('napster.php: The entered Napster-Album has been loaded',6);
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	LOGGING("napster.php: Requested Napster Music plays now.", 7);
	$sonos->Play();
}



?>
