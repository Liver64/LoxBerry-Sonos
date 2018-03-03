<?php

/**
* Submodul: Restore_T2S
*
**/

/**
* Function : restoreSingleZone --> restores previous Zone settings before a single message has been played 
*
* @param:  empty
* @return: previous settings
**/		

function restoreSingleZone() {
	global $sonoszone, $sonos, $master, $actual, $time_start, $mode;
	
	$restore = $actual;
	switch ($restore) {
		// Zone was playing in Single Mode
		case $actual[$master]['ZoneStatus'] == 'single':
			$prevStatus = "single";
			restore_details($master);
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
			if (!empty($actual[$master]['PositionInfo']["duration"]))  {
				RestoreShuffle($actual, $master);
			}
			if (($actual[$master]['TransportInfo'] == 1)) {
				$sonos->Play();	
			}
			LOGGING("Single mode for zone ".$master." has been restored.", 6);
		break;
		
		// Zone was Member of a group
		case $actual[$master]['ZoneStatus'] == 'member':
			$prevStatus = "member";
			$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]); 
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
			LOGGING("Zone ".$master." has been added back to group.", 6);
		break;
		
		// Zone was Master of a group
		case $actual[$master]['ZoneStatus'] == 'master':
		
		if (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
			#echo "LineIn";
			# Zone was Master of a group
			$sonos = new PHPSonos($sonoszone[$master][0]);
			$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]);
			
			# Restore Zone Members
			$tmp_group = $actual[$master]['Grouping'];
			$tmp_group1st = array_shift($tmp_group);
			foreach ($tmp_group as $groupmem) {
				$sonos = new PHPSonos($sonoszone[$groupmem][0]);
				$sonos->SetAVTransportURI($actual[$groupmem]['PositionInfo']["TrackURI"]);
				$sonos->SetVolume($actual[$groupmem]['Volume']);
				$sonos->SetMute($actual[$groupmem]['Mute']);
			}
			# Start restore Master settings
			$sonos = new PHPSonos($sonoszone[$master][0]);
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
		} else {
			#echo "Normal";
			$prevStatus = "master";
			$oldGroup = $actual[$master]['Grouping'];
			$exMaster = array_shift($oldGroup); // deletes previous master from array
			foreach ($oldGroup as $newMaster) {
				// loop threw former Members in order to get the New Coordinator
				$sonos = new PHPSonos($sonoszone[$newMaster][0]);
				$check = $sonos->GetPositionInfo();
				$checkMaster = $check['TrackURI'];
				if (empty($checkMaster)) {
					// if TrackURI is empty add Zone to New Coordinator
					$sonos_old = new PHPSonos($sonoszone[$master][0]);
					$sonos_old->SetVolume($actual[$master]['Volume']);
					$sonos_old->SetMute($actual[$master]['Mute']);
					$sonos_old->SetAVTransportURI("x-rincon:" . $sonoszone[$newMaster][1]);
				}
			}
			# add previous master back to group and restore settings
			$sonos = new PHPSonos($sonoszone[$exMaster][0]);
			$sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$newMaster][1]);
			$sonos->SetVolume($actual[$exMaster]['Volume']);
			$sonos->SetMute($actual[$exMaster]['Mute']);
		try {
			$sonos = new PHPSonos($sonoszone[$newMaster][0]);
			$sonos->DelegateGroupCoordinationTo($sonoszone[$master][1], 1);
			LOGGING("Zone ".$master." has been added back to group.", 6);
		} catch (Exception $e) {
			LOGGING("Assignment to new GroupCoordinator " . $master . " failed.",5);	
		}
		}
	break;
	}
}


/**
* Function : restoreGroupZone --> restores previous Group settings before a group message has been played 
*
* @param:  empty
* @return: previous settings
**/		

function restoreGroupZone() {
	global $sonoszone, $logpath, $master, $sonos, $config, $member, $player, $actual, $coord, $time_start;
	
	foreach ($member as $zone) {
		$sonos = new PHPSonos($sonoszone[$zone][0]);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
	}
	// add Master to array
	array_push($member, $master);
	// Restore former settings for each Zone
	foreach($member as $zone => $player) {
		#echo $player.'<br>';
		$restore = $actual[$player]['ZoneStatus'];
		$sonos = new PHPSonos($sonoszone[$player][0]);
		switch($restore) {
		case 'single';  
			#echo 'Die Zone '.$player.' ist single<br>';
			// Zone was playing as Single Zone
			if (empty($actual[$player]['Grouping'])) {
			# Playlist
			if ((substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) !== "x-sonos-htastream:") &&
				($actual[$player]['PositionInfo']["TrackDuration"] != '')) {			
				RestoreShuffle($actual, $player);
				} 
				# TV Playbar
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# LineIn
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# Radio Station
				elseif (($actual[$player]['PositionInfo']["TrackDuration"] == '') or ($actual[$player]['MediaInfo']["title"] <> '')) {
					@$radioname = $actual[$player]['MediaInfo']["title"];
					$sonos->SetRadio($actual[$player]['PositionInfo']["TrackURI"], $radioname);
				}
			}
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			if($actual[$player]['TransportInfo'] != 1) {
				$sonos->Stop();
			} else {
				$sonos->Play();
			}
			LOGGING("Single mode for zone ".$player." has been restored.", 6);
		break;
			
		case 'member';
			#echo 'Die Zone '.$player.' ist member<br>';
			# Zone was Member of a group
			$sonos = new PHPSonos($sonoszone[$player][0]);
			$tmp_checkmember = $actual[$player]['PositionInfo']["TrackURI"];
			$sonos->SetAVTransportURI($tmp_checkmember);
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			LOGGING("Zone ".$player."  has been added back to group.", 6);
		break;
			
			
		case 'master';
			#echo 'Die Zone '.$player.' ist master<br>';
			# Zone was Master of a group
			$sonos = new PHPSonos($sonoszone[$player][0]);
			# Playlist
			if ((substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) !== "x-sonos-htastream:") &&
				($actual[$player]['PositionInfo']["TrackDuration"] != '')) {			
				RestoreShuffle($actual, $player);
				} 
				# TV Playbar
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# LineIn
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# Radio Station
				elseif (($actual[$player]['PositionInfo']["TrackDuration"] == '') && ($actual[$player]['MediaInfo']["title"] <> '')){
					@$radionam = @$actual[$player]['MediaInfo']["title"];
					$sonos->SetRadio($actual[$player]['PositionInfo']['TrackURI'],"$radionam");
				}
			# Restore Zone Members
			$tmp_group = $actual[$player]['Grouping'];
			$tmp_group1st = array_shift($tmp_group);
			foreach ($tmp_group as $groupmem) {
				$sonos = new PHPSonos($sonoszone[$groupmem][0]);
				$sonos->SetAVTransportURI($actual[$groupmem]['PositionInfo']["TrackURI"]);
				$sonos->SetVolume($actual[$groupmem]['Volume']);
				$sonos->SetMute($actual[$groupmem]['Mute']);
			}
			# Start restore Master settings
			$sonos = new PHPSonos($sonoszone[$player][0]);
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			if($actual[$player]['TransportInfo'] != 1) {
				$sonos->Stop();
			} else {
				$sonos->Play();
			}
			LOGGING("Zone ".$player."  has been added back to group.", 6);
		break;			
		}
	}
}	

/**
* Function : PlayList --> load previous saved Playlist back into Queue 
*
* @param: PlayList                             
* @return: empty
**/

function LoadPlayList($playlist) {
	Global $sonos, $master, $sonoszone, $coord;
	
	
	$sonoslists=$sonos->GetSONOSPlaylists();
	$pleinzeln = 0;
	while ($pleinzeln < count($sonoslists) ) {
		if($playlist == $sonoslists[$pleinzeln]["title"]) {
			$plfile = urldecode($sonoslists[$pleinzeln]["file"]);
			$sonos->AddToQueue($plfile);
			$sonos->SetQueue("x-rincon-queue:".$coord[1]."#0"); 
			$gefunden = 1;
		}
		$pleinzeln++;
	}			
}


/**
* Function : read_txt_file_to_array --> Subfunction of batch T2S to read a txt file into an array
*
* @param: file
* @return: array
**/

function read_txt_file_to_array() {
	global $t2s_batch, $filename;
	
	$filename = "t2s_batch.txt";
    if (!file_exists($filename)) {
		LOGGING("There is no T2S batch file to be played!", 4);
        exit();
	}
	$t2s_batch = file("t2s_batch.txt");
	unlink($filename);
	#print_r($t2s_batch);
	return $t2s_batch;
}

/**
* Function : restore_details() --> restore the details of master zone
*
* @param: 
* @return: 
**/

function restore_details($zone) {
	global $sonoszone, $sonos, $master, $actual, $j, $browselist, $senderName;
	
	# Playlist
	if ((substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 18) !== "x-sonos-htastream:") && ($actual[$zone]['PositionInfo']["TrackDuration"] != '')) {			
		$sonos->SetTrack($actual[$zone]['PositionInfo']['Track']);
		$sonos->Seek($actual[$zone]['PositionInfo']['RelTime'],"NONE");
		
		#$mode = $actual[$zone]['TransportSettings'];
		#playmode_detection($zone, $mode);
	} 
	# TV Playbar
	elseif (substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
	} 
	# LineIn
	elseif (substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
	} 
	# Radio Station
	elseif (($actual[$zone]['PositionInfo']["TrackDuration"] == '') or ($actual[$zone]['MediaInfo']["title"] <> '')) {
		@$radioname1 = $actual[$zone]['MediaInfo']["title"];
		$sonos->SetRadio(urldecode($actual[$zone]['PositionInfo']["TrackURI"]), "$radioname1");
	}
}


/**
* Function : RestoreShuffle() --> Restore previous playmode settings
*
* @param: array $actual array of saved status, string $player Masterplayer
* @return: static
**/

function RestoreShuffle($actual, $player) {
	global $sonoszonen;
	
	$sonos = new PHPSonos($sonoszonen[$player][0]);
	$mode = $actual[$player]['TransportSettings'];
	playmode_detection($player, $mode);
	$pl = $sonos->GetCurrentPlaylist();
	$titel = (string)$actual[$player]['PositionInfo']['title'];
	// falls irgendein SHUFFLE ein
	if ($mode['shuffle'] == 1)  { 
		$trackNoSearch = recursive_array_search($titel, $pl);
		$track = (string)$pl[$trackNoSearch]['listid'];
		$sonos->SetTrack($track);
		$sonos->Seek($actual[$player]['PositionInfo']['RelTime'],"NONE");	
	// falls SHUFFLE aus
	} else {
		$sonos->SetTrack($actual[$player]['PositionInfo']['Track']);
		$sonos->Seek($actual[$player]['PositionInfo']['RelTime'],"NONE");	
	}
	LOGGING("Previous playmode has been restored.", 6);
}
	
?>