<?php

/**
* Submodul: Playlist
*
**/

/**
/* Funktion : playlist --> lädt eine Sonos Playliste in eine Zone/Gruppe
/*
/* @param: Playliste                             
/* @return: nichts
**/

function playlist() {
	
	global $debug, $sonos, $master, $profile_details, $memberarray, $samearray, $sonoszone, $config, $volume, $masterzone, $sonospltmp, $profile_selected;
	
	if (!defined('GROUPMASTER')) {
		define("GROUPMASTER",$master);
	}
	CreateMember();
	if (file_exists($sonospltmp) and (!isset($_GET['load'])))  {
		# load previously saved Sonos Playlist
		$sonos = new SonosAccess($sonoszone[GROUPMASTER][0]);
		$value = json_decode(file_get_contents($sonospltmp), TRUE);
		$countqueue = count($sonos->GetCurrentPlaylist());
		$currtrack = $sonos->GetPositioninfo();
		if ($currtrack['Track'] < $countqueue)    {
			NextTrack();
			LOGINF ("playlist.php: Next track has been called.");
			return true;
		} else {
			@unlink($sonospltmp);
			if(isset($_GET['member']) and !isset($_GET['profile']) and !$_GET['action'] == "Profile")   {
				removemember();
				LOGINF ("playlist.php: Member has been removed");
			}
			LOGINF ("playlist.php: File has been deleted");
			LOGOK ("playlist.php: ** Loop ended, we start from beginning **");
		}
	} 
		
	$sonos = new SonosAccess($sonoszone[GROUPMASTER][0]);
	if(isset($_GET['playlist']))   {
		$epl = $_GET['playlist'];
		$playlist = mb_strtolower($_GET['playlist']);	
	} else {
		LOGERR("playlist.php: You have maybe a typo! Correct syntax is: &action=playlist&playlist=<PLAYLIST>");
		exit;
	}
	$sonoslists = $sonos->GetSONOSPlaylists();
	foreach ($sonoslists as $val => $item)  {
		$sonoslists[$val]['titlelow'] = mb_strtolower($sonoslists[$val]['title']);
	}
	$found = array();
	foreach ($sonoslists as $key)    {
		if ($playlist === $key['titlelow'])   {
			$playlist = $key['titlelow'];
			array_push($found, array_multi_search($playlist, $sonoslists, "titlelow"));
		}
	}
	$playlist = urldecode($playlist);
	if (count($found) > 1)  {
		LOGERR ("playlist.php: Your entered Playlist '".$_GET['playlist']."' has more then 1 hit! Please specify more detailed.");
		exit;
	} elseif (count($found) == 0)  {
		LOGERR ("playlist.php: Your entered Playlist '".$_GET['playlist']."' could not be found.");
		exit;
	} else {
		LOGGING("playlist.php: Playlist '".$found[0][0]["title"]."' has been found.", 5);
	}
	$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[GROUPMASTER][1]) . "#0");
	$sonos->ClearQueue();	
	$countpl = count($found);
	if ($countpl > 0)   {
		$plfile = urldecode($found[0][0]["file"]);
		$sonos->AddToQueue($plfile);
		$currpl = file_put_contents($sonospltmp, json_encode($plfile));
		if(!isset($_GET['load']) and !isset($_GET['rampto'])) {
			$sonos->SetMute(false);
			$sonos->Stop();
			if (isset($_GET['profile']) or isset($_GET['Profile']))    {
				$volume = $profile_details[0]['Player'][GROUPMASTER][0]['Volume'];
			} else {
				volume_group();
			}
			$sonos = new SonosAccess($sonoszone[GROUPMASTER][0]);
			$sonos->SetVolume($volume);
			$sonos->Play();
		} else {
			$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[GROUPMASTER][1]) . "#0");
		}
		RampTo();
		LOGGING("playlist.php: Playlist '".$found[0][0]["title"]."' has been loaded successful",6);
	} else {
		LOGGING("playlist.php: Playlist '".$found[0][0]["title"]."' could not be loaded. Please check your input.",3);
		if(isset($_GET['member']) and !isset($_GET['profile']) and !$_GET['action'] == "Profile")   {
			removemember();
			LOGINF ("playlist.php: Member has been removed");
		}
		exit;
	}
}



/** 
* Sub Function for T2S: SavePlaylist --> save temporally Playlist
*
* @param: empty
* @return: playlist "temp_t2s" saved
**/

function SavePlaylist() {
	global $sonos, $id;
	try {
		$sonos->SaveQueue("temp_t2s");
	} catch (Exception $e) {
		LOGGING("playlist.php: The temporary Playlist (PL) could not be saved because the list contains min. 1 Song (URL) which is not longer valid! Please check or remove the list!", 3);
		exit;
	}
	LOGGING("playlist.php: Temporally playlist has been saved.", 6);
}


/**
* Sub Function for T2S: DelPlaylist --> deletes previously saved temporally Playlist
*
* @param: empty
* @return: playlist "temp_t2s" deleted
**/

function DelPlaylist() {
	global $sonos;
	
	$playlists = $sonos->GetSonosPlaylists();
	$t2splaylist = recursive_array_search("temp_t2s",$playlists);
	if(!empty($t2splaylist)) {
		$sonos->DeleteSonosPlaylist($playlists[$t2splaylist]['id']);
	}
	LOGGING("playlist.php: Temporally playlist has been deleted.", 6);
}


/**
* Funktion : 	random_playlist --> lädt per Zufallsgenerator eine Playliste und spielt sie ab.
*
* @param: exceptions from Syntax
* @return: Playliste
**/

function random_playlist() {
	
	global $sonos, $sonoszone, $master, $min_vol, $volume, $config;
	
	if (isset($_GET['member'])) {
		$master = GROUPMASTER;
	} else {
		$master = $_GET['zone'];
	}
	
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$sonoslists = $sonos->GetSONOSPlaylists();
	#print_r($sonoslists);
	if(!isset($_GET['except'])) {
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	} else {
		$except = $_GET['except'];
		$exception = explode(',',$except);
		for($i = 0; $i < count($exception); $i++) {
			$exception[$i] = str_replace(' ', '', $exception[$i]);
		}
		foreach ($exception as $key => $val) {
			unset($sonoslists[$val]);
		}
		$sonoslists = array_values($sonoslists);
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	}
	$plfile_log = ($sonoslists[$random]["file"]);
	$plfile = urldecode($sonoslists[$random]["file"]);
	$pl = array_multi_search($plfile_log, $sonoslists, "file");
	$tmp_volume = $sonos->GetVolume();
	$sonos->ClearQueue();
	$sonos->SetMute(false);
	$sonos->AddToQueue($plfile);
	$sonos->SetQueue("x-rincon-queue:". trim($sonoszone[$master][1]) ."#0"); 
	if(isset($_GET['volume'])) {
		$volume = $_GET['volume'];
	} elseif (isset($_GET['keepvolume'])) {
		if ($tmp_volume >= $min_vol)  {
			$volume = $tmp_volume;
		} else {
			$volume = $sonoszone[$master][4];
		}
	} else {
		$volume = $sonoszone[$master][4];
	}
	LOGGING("playlist.php: Random playlist '".urldecode($pl[0]['title'])."' has been added to Queue.", 6);
	$sonos->Play();
}


/**
* Funktion : 	next_dynamic --> Unterfunktion von 'nextpush', lädt PL oder Radio Favoriten, je nachdem was gerade läuft
*
* @param: empty
* @return: Playliste oder Radio
**/

function next_dynamic() {
	
	global $sonos, $sonoszone, $master;
	
	$titelgesammt = $sonos->GetPositionInfo();
	$titelaktuel = $titelgesammt["Track"];
	$playlistgesammt = count($sonos->GetCurrentPlaylist());
	$sonos->SetPlayMode('0'); // NORMAL
	$sonos->SetMute(false);
	if (($titelaktuel < $playlistgesammt) or (substr($titelgesammt["TrackURI"], 0, 9) == "x-rincon:")) {
		checkifmaster($master);
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->Next();
		LOGGING("playlist.php: Next Song in Playlist.", 7);
	} else {
		checkifmaster($master);
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->SetTrack("1");
		LOGGING("playlist.php: Playlist starts at Song Number 1.", 7);
	}
	$sonos->Play();
}


/**
* Optional Sub-Function for zapzone: say_zone --> announce Zone before adding zone to master
*
* @param: $zone
* @return: 
**/
function say_zone($zone) {
			
	global $master, $sonoszone, $config, $volume, $min_vol, $actual, $sonos, $coord, $messageid, $filename, $MessageStorepath, $nextZoneKey, $filenameplaysay;
	
	require_once("addon/sonos-to-speech.php");
	
	// if batch has been choosed abort
	if(isset($_GET['batch'])) {
		LOGGING("playlist.php: The parameter batch could not be used to announce zone!", 4);
		exit;
	}
	saveZonesStatus(); // saves all Zones Status
	#$sonos->Stop();
	sleep(1);
	$sonos = new SonosAccess($sonoszone[$master][0]);
	#********************** NEW get text variables **********************
	$TL = LOAD_T2S_TEXT();
		
	$play_zone = $TL['SONOS-TO-SPEECH']['ANNOUNCE_ZONE'] ; 
 	#********************************************************************
	# Generiert und kodiert Ansage der Zone
	$text = ($play_zone.' '.$zone);
	$textstring = $text;
	$rawtext = md5($text);
	$filename = "$rawtext";
	select_t2s_engine();
	t2s($textstring, $filename);
	// get Coordinator of (maybe) pair or single player
	$coord = getRoomCoordinator($master);
	LOGGING("playlist.php: Room Coordinator been identified", 7);		
	$sonos = new SonosAccess($coord[0]); 
	$tmp_volume = $sonos->GetVolume();
	$sonos->SetMute(false);
	$volume = $volume + $config['TTS']['correction'];
	play_tts($filename);
	LOGGING("playlist.php: Zone Announcement has been played", 6);	
	restoreSingleZone();
	if(isset($_GET['volume'])) {
		$volume = $_GET['volume'];
	} elseif (isset($_GET['keepvolume'])) {
		if ($tmp_volume >= $min_vol)  {
			$volume = $tmp_volume;
		} else {
			$volume = $sonoszone[$master][4];
		}
	} else {
		$volume = $sonoszone[$master][4];
	}
	#$sonos->Play();
	return $volume;
}





?>