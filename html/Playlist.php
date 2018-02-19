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
	} else {
		trigger_error("No playlist with the specified name found.", E_USER_NOTICE);
	}
	
	# Sonos Playlist ermitteln und mit übergebene vergleichen	
	$sonoslists=$sonos->GetSONOSPlaylists();
	$pleinzeln = 0;
	$gefunden = 0;
	while ($pleinzeln < count($sonoslists) ) {
		if($playlist == $sonoslists[$pleinzeln]["title"]) {
			$plfile = urldecode($sonoslists[$pleinzeln]["file"]);
			$sonos->ClearQueue();
			#$sonos->SetMute(false);
			$sonos->AddToQueue($plfile); //Datei hinzufügen
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
				$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
				$sonos->SetMute(false);
				$sonos->SetVolume($config['sonoszonen'][$master][4]);
				$sonos->Play();
			} else {
				if($sonos->GetVolume() <= $config['TTS']['volrampto'])	{
					$sonos->RampToVolume($config['TTS']['rampto'], $volume);
					$sonos->Play();
				} else {
					$sonos->Play();
				}
			}
			$gefunden = 1;
		}
		$pleinzeln++;
			if (($pleinzeln == count($sonoslists) ) && ($gefunden != 1)) {
				$sonos->Stop();
				trigger_error("No playlist with the specified name found.", E_USER_NOTICE);
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
	#include_once("text2speech.php");

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
		trigger_error("No permission to write to curr_Zone.txt", E_USER_ERROR);
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
		trigger_error("Could not open file curr_Zone.txt", E_USER_ERROR);
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
        $aufruf=0;
	}
	$counter=fopen("count.txt","r+"); $output=fgets($counter,100);
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
		trigger_error("The temporary Playlist (PL) could not be saved because the list contains min. 1 Song (URL) which is not longer valid! Please check or remove the list!", E_USER_ERROR);
	}
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
}


/**
* Funktion : 	random_playlist --> lädt per Zufallsgenerator eine Playliste und spielt sie ab.
*
* @param: empty
* @return: Playliste
**/

function random_playlist() {
	global $sonos, $sonoszone, $master, $volume, $config;
	
	if (isset($_GET['member'])) {
		trigger_error("This function could not be used with groups!", E_USER_ERROR);
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
		if($sonos->GetVolume() <= $config['TTS']['volrampto']) {
			$sonos->RampToVolume($config['TTS']['rampto'], $volume);
		}	
	}
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
		$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
		$sonos->Next();
	} else {
		checkifmaster($master);
		$sonos = new PHPSonos($sonoszone[$master][0]); //Sonos IP Adresse
		$sonos->SetTrack("1");
	}
	$sonos->Play();
}

?>