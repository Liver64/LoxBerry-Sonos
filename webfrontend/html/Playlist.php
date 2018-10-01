<?php

/**
* Submodul: Playlist
*
**/

/**
/* Funktion : playlist --> lädt eine Playliste in eine Zone/Gruppe
/*
/* @param: Playliste                             
/* @return: nichts
**/

function playlist() {
	Global $debug, $sonos, $master, $sonoszone, $config, $volume;
	if(isset($_GET['playlist'])) {
		$sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$master][1]) . "#0"); 
		$playlist = $_GET['playlist'];
		LOGGING("Playlist has been found.", 7);
	} else {
		LOGGING("No playlist with the specified name found.", 3);
		exit;
	}
	
	# Sonos Playlist ermitteln und mit übergebene vergleichen	
	$sonoslists=$sonos->GetSONOSPlaylists();
	$pleinzeln = 0;
	$gefunden = 0;
	while ($pleinzeln < count($sonoslists) ) {
		if($playlist == $sonoslists[$pleinzeln]["title"]) {
			$plfile = urldecode($sonoslists[$pleinzeln]["file"]);
			$sonos->ClearQueue();
			LOGGING("Queue has been cleared.", 7);
			#$sonos->SetMute(false);
			$sonos->AddToQueue($plfile); //Datei hinzufügen
			LOGGING("Playlist has been added to Queue.", 7);
			$sonos->SetQueue("x-rincon-queue:". trim($sonoszone[$master][1]) ."#0"); 
			if ((isset($_GET['member'])) and isset($_GET['standardvolume'])) {
				$member = $_GET['member'];
				$member = explode(',', $member);
				foreach ($member as $zone) {
					$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos IP Adresse
					$sonos->SetMute(false);
					$volume = $config['sonoszonen'][$zone][4];
					$sonos->SetVolume($config['sonoszonen'][$zone][4]);
				}
				LOGGING("Standardvolume for members has been set.", 7);
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->SetMute(false);
				$sonos->SetVolume($config['sonoszonen'][$master][4]);
				LOGGING("Standardvolume for master has been set.", 7);
				$sonos->Play();
			} else {
				if(empty($config['TTS']['volrampto'])) {
					$config['TTS']['volrampto'] = "25";
					LOGGING("Rampto Volume in config has not been set. Default of 25% Volume has been taken, please update Plugin Config (T2S Optionen).", 4);
				}
				if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					$sonos->Play();
					LOGGING("Rampto Volume from Syntax has been set.", 7);
				} else {
					$sonos->Play();
					LOGGING("Volume from Syntax has been set.", 7);
				}
			}
			LOGGING("Playlist is playing.", 7);
			$gefunden = 1;
		}
		$pleinzeln++;
			if (($pleinzeln == count($sonoslists) ) && ($gefunden != 1)) {
				$sonos->Stop();
				LOGGING("No playlist with the specified name found.", 3);
				exit;
			}
		}			
}

/**
* Function: zapzone --> checks each zone in network and if playing add current zone as member
*
* @param: empty
* @return: 
**/

function zapzone() {
	global $config, $sonos, $sonoszone, $master, $playzones, $count;
	
	$sonos = new PHPSonos($sonoszone[$master][0]);
	if (substr($sonos->GetPositionInfo()["TrackURI"], 0, 15) == "x-rincon:RINCON") {
		$sonos->BecomeCoordinatorOfStandaloneGroup();
	}
	play_zones();
	$playingzones = $_SESSION["playingzone"];
	#print_r($playingzones);
	$max_loop = count($playingzones);
	$count = countzones();
	// if no zone is playing switch to nextradio
	if (empty($playingzones) or $count > count($playingzones)) {
		nextradio();
		sleep($config['VARIOUS']['maxzap']);
		if(file_exists("count.txt"))  {
			unlink("count.txt");
		}
		exit;
	}
	$currentZone = currentZone();
	// finally loop by call through array
    foreach ($playingzones as $key => $value) {
		if($key == $currentZone) {
            $nextZoneUrl 	= next($playingzones);
            $nextZoneKey    = key($playingzones);
            //if last element catched, move to first element
            if(!$nextZoneUrl)  {
                $nextZoneUrl 	= reset($playingzones);
                $nextZoneKey    = key($playingzones);
			}
			break;
        } else {
			next($playingzones);
		}
	}
	if (empty($nextZoneKey)) {
		$nextZoneKey = $key;
	}
	#echo '<br>Zone: ['.$nextZoneKey.']';
	saveCurrentZone($nextZoneKey);
	if ($config['VARIOUS']['announceradio'] == 1) {
		say_zone($nextZoneKey);
	} else {
		if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
			$volume = $_GET['volume'];
			LOGGING("Volume from syntax been used", 7);		
		} else 	{
			// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
			$volume = $config['sonoszonen'][$master][3];
			LOGGING("Standard Volume from config been used", 7);		
		}
	}
	unset ($playingzones[$nextZoneKey]);
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	$sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$nextZoneKey][1]);
	$sonos->SetMute(false);
	}


/**
* Sub-Function for zapzone: saveCurrentZone --> saves current playing zone to file
*
* @param: Zone
* @return: 
**/

function saveCurrentZone($nextZoneKey) {
    if(!touch('curr_Zone.txt')) {
		LOGGING("No permission to write to curr_Zone.txt", 3);
		exit;
    }
	$handle = fopen ('curr_Zone.txt', 'w');
    fwrite ($handle, $nextZoneKey);
    fclose ($handle);                
} 


/**
* Sub-Function for zapzone: currentZone --> open file and read last playing zone
*
* @param: 
* @return: last playing zone 
**/      

function currentZone() {
	global $config, $master;

	$playingzones = $_SESSION["playingzone"];
	if(!touch('curr_Zone.txt')) {
		LOGGING("Could not open file curr_Zone.txt", 3);
		exit;
    }
	$currentZone = file('curr_Zone.txt');
	if(empty($currentZone)) {
		reset($playingzones);
        $currentZone[0] = key($playingzones);
        saveCurrentZone($currentZone[0]);
    }
	return $currentZone[0];
}


/**
* Sub-Function for zapzone: play_zones --> scans through Sonos Network and create array of currently playing zones
*
* @param: 
* @return: array of zones 
**/

function play_zones() {
	global $sonoszone, $master, $sonos, $playingzones;
	
	$playzone = $sonoszone;
	unset($playzone[$master]); 
	foreach ($playzone as $key => $val) {
		$sonos = new PHPSonos($playzone[$key][0]);
		// only zones which are not a group member
		$zonestatus = getZoneStatus($key);
		if ($zonestatus <> 'member') {
			// check if zone is currently playing and add to array
			if($sonos->GetTransportInfo() == 1) {
				$playingzones[$key] = $val[1];
			}
		}
	}
	$_SESSION["playingzone"] = $playingzones;
	#print_r($playingzones);
	return array($playingzones);
}


/**
* Sub-Function for zapzone: countzones --> increment counter by each click
*
* @param: 
* @return: amount if clicks
**/

function countzones() {
	if(!file_exists("count.txt")){
        fopen("count.txt", "a" );
        #$aufruf=0;
	}
	$counter=fopen("count.txt","r+"); 
	$output=fgets($counter,100);
	$output=$output+1;
	rewind($counter);
	fputs($counter,$output);
	return $output;
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
		LOGGING("The temporary Playlist (PL) could not be saved because the list contains min. 1 Song (URL) which is not longer valid! Please check or remove the list!", 3);
		exit;
	}
	LOGGING("Temporally playlist has been saved.", 6);
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
		$sonos->DelSonosPlaylist($playlists[$t2splaylist]['id']);
	}
	LOGGING("Temporally playlist has been deleted.", 6);
}


/**
* Funktion : 	random_playlist --> lädt per Zufallsgenerator eine Playliste und spielt sie ab.
*
* @param: exceptions from Syntax
* @return: Playliste
**/

function random_playlist() {
	global $sonos, $sonoszone, $master, $volume, $config;
	
	if (isset($_GET['member'])) {
		LOGGING("This function could not be used with groups!", 3);
		exit;
	}
	$sonoslists = $sonos->GetSONOSPlaylists();
	print_r($sonoslists);
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
	$plfile = urldecode($sonoslists[$random]["file"]);
	$sonos->ClearQueue();
	$sonos->SetMute(false);
	$sonos->AddToQueue($plfile);
	$sonos->SetQueue("x-rincon-queue:". trim($sonoszone[$master][1]) ."#0"); 
	if (!isset($_GET['volume'])) {
		if(empty($config['TTS']['volrampto'])) {
			$config['TTS']['volrampto'] = "25";
			LOGGING("Rampto Volume in config has not been set. Default of 25% Volume has been taken, please update Plugin Config (T2S Optionen).", 4);
		}
		if($sonos->GetVolume() <= $config['TTS']['volrampto']) {
			$sonos->RampToVolume($config['TTS']['rampto'], $volume);
		}	
	}
	LOGGING("Random playlist has been added to Queue.", 6);
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
	$sonos->SetPlayMode('NORMAL');
	$sonos->SetMute(false);
	if (($titelaktuel < $playlistgesammt) or (substr($titelgesammt["TrackURI"], 0, 9) == "x-rincon:")) {
		checkifmaster($master);
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$sonos->Next();
		LOGGING("Next Song in Playlist.", 7);
	} else {
		checkifmaster($master);
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$sonos->SetTrack("1");
		LOGGING("Playlist starts at Song Number 1.", 7);
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
			
	global $master, $sonoszone, $config, $volume, $sonos, $coord, $messageid, $filename, $MessageStorepath, $nextZoneKey;
	require_once("addon/sonos-to-speech.php");
	
	// if batch has been choosed abort
	if(isset($_GET['batch'])) {
		LOGGING("The parameter batch could not be used to announce zone!", 4);
		exit;
	}
	#$sonos->Stop();
	if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
		$volume = $_GET['volume'];
		LOGGING("Volume from syntax been used", 7);		
	} else 	{
		// übernimmt Standard Lautstärke der angegebenen Zone aus config.php
		$volume = $config['sonoszonen'][$master][3];
		LOGGING("Standard Volume from config been used", 7);		
	}
	#saveZonesStatus(); // saves all Zones Status
	$sonos = new PHPSonos($sonoszone[$master][0]);
	#********************** NEW get text variables **********************
	$TL = LOAD_T2S_TEXT();
		
	$play_zone = $TL['SONOS-TO-SPEECH']['ANNOUNCE_ZONE'] ; 
 	#********************************************************************
	# Generiert und kodiert Ansage der Zone
	$text = ($play_zone.' '.$zone);
	$textstring = ($text);
	$rawtext = md5($textstring);
	$filename = "$rawtext";
	$messageid = $filename;
	select_t2s_engine();
	t2s($messageid, $MessageStorepath, $textstring, $filename);
	// get Coordinator of (maybe) pair or single player
	$coord = getRoomCoordinator($master);
	LOGGING("Room Coordinator been identified", 7);		
	$sonos = new PHPSonos($coord[0]); 
	$sonos->SetMute(false);
	$sonos->SetVolume($volume);
	play_tts($messageid);
	LOGGING("Zone Announcement has been played", 6);	
	#restoreSingleZone();
}

?>