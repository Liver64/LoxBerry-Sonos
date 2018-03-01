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
		
	$sonos = new PHPSonos($sonoszone[$master][0]);
	$rincon = $sonoszone[$master][1];
	$curr_track_tmp = $sonos->GetPositionInfo();
	// check if Radio/Line IN/TV is playing, then switch to PL
	empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
	
	// Napster Track(s) - NOT IMPLEMENTED
	if (isset($_GET['trackuri'])) {  
		trigger_error("Napster Track-URI is currently not supported!", E_USER_ERROR);	
		$uri = $_GET['trackuri'];
		if (empty($uri)) {
			trigger_error("Please enter Napster Track-URI!", E_USER_ERROR);
		}
		$track_array = explode(',',$uri);
		if (count($track_array) > 2) {
			trigger_error("Please enter just one Napster Track-URI!", E_USER_ERROR);
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
			trigger_error("The entered Napster-Track-ID's: ".$uri." are not valid! Please check!", E_USER_ERROR);
		}
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
			trigger_error("The entered Napster-Playlist-ID: ".$pl." is not valid! Please check!", E_USER_ERROR);
		}
	}
	// Napster Album
	if (isset($_GET['albumuri'])) {
		$pl = $_GET['albumuri'];
			$sonos->ClearQueue();
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetNapsterAlbum($pl, $mail);
		} catch (Exception $e) {
			trigger_error("The entered Napster-Album-ID ".$pl." is not valid! Please check!", E_USER_ERROR);
		}
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	$sonos->Play();
}



?>
