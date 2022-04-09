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
	global $sonoszone, $sonos, $master, $actual, $time_start, $mode, $tts_stat;
	
	#if (!$_GET['member']) {
	$restore = $actual;
	switch ($restore) {
		// Zone was playing in Single Mode
		case $actual[$master]['ZoneStatus'] == 'single':
			$prevStatus = "single";
			restore_details($master);
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
			if (($actual[$master]['TransportInfo'] == 1)) {
				$sonos->Play();	
			}
			LOGGING("restore_t2s.php: Single Zone ".$master." has been restored.", 6);
		break;
		
		// Zone was Member of a group
		case $actual[$master]['ZoneStatus'] == 'member':
			$prevStatus = "member";
			$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]); 
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
			LOGGING("restore_t2s.php: Zone ".$master." has been added back to group.", 6);
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
			LOGGING("restore_t2s.php: Zone ".$master." has been added back to group.", 6);
		} catch (Exception $e) {
			LOGGING("restore_t2s.php: Assignment to new GroupCoordinator " . $master . " failed.",5);	
		}
		}
	break;
	}
	# setze 0 für virtuellen Texteingang (T2S Ende)
	$tts_stat = 0;
	send_tts_source($tts_stat);
	return;
	#}
}


/**
* Function : restoreGroupZone --> restores previous Group settings before a group message has been played 
*
* @param:  empty
* @return: previous settings
**/		

function restoreGroupZone() {
	global $sonoszone, $logpath, $master, $sonos, $config, $member, $player, $actual, $coord, $time_start, $tts_stat;
	
	#print_r($actual);
	foreach ($member as $zone) {
		$sonos = new PHPSonos($sonoszone[$zone][0]);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
	}
	// add Master to array
	array_push($member, $master);
	// Restore former settings for each Zone
	#print_r($actual);
	foreach($member as $zone => $player) {
		#echo $player.'<br>';
		#echo $zone.'<br>';
		$restore = $actual[$player]['ZoneStatus'];
		$sonos = new PHPSonos($sonoszone[$player][0]);
		switch($restore) {
			
		case 'single';  
			#echo 'Die Zone '.$player.' ist single<br>';
			// Zone was playing as Single Zone
			if (empty($actual[$player]['Grouping'])) {
			# Playlist
			if ((substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) !== "x-sonos-htastream:") &&
				(empty($actual[$master]['MediaInfo']["CurrentURIMetaData"]))) {	
				if ($actual[$player]['PositionInfo']['Track'] != "0")    {				
					RestoreShuffle($player);
				}
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
				elseif (!empty($actual[$master]['MediaInfo']["CurrentURIMetaData"])) {
					#@$radioname = $actual[$player]['MediaInfo']["title"];
					#$sonos->SetRadio($actual[$player]['PositionInfo']["TrackURI"], $radioname);
					$sonos->SetAVTransportURI($actual[$player]['MediaInfo']["CurrentURI"], ($actual[$player]['MediaInfo']["CurrentURIMetaData"])); 
				}
			}
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			if($actual[$player]['TransportInfo'] != 1) {
				$sonos->Stop();
			} else {
				$sonos->Play();
			}
			LOGGING("restore_t2s.php: Single Zone ".$player." has been restored.", 6);
		break;
			
		case 'member';
			#echo 'Die Zone '.$player.' ist member<br>';
			# Zone was Member of a group
			$sonos = new PHPSonos($sonoszone[$player][0]);
			$tmp_checkmember = $actual[$player]['PositionInfo']["TrackURI"];
			$sonos->SetAVTransportURI($tmp_checkmember);
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			LOGGING("restore_t2s.php: Member Zone ".$player." has been added back to group.", 6);
		break;
			
			
		case 'master';
			#echo 'Die Zone '.$player.' ist master<br>';
			# Zone was Master of a group
			$sonos = new PHPSonos($sonoszone[$player][0]);
			# Playlist
			if ((substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) !== "x-sonos-htastream:") &&
				(empty($actual[$player]['MediaInfo']["CurrentURIMetaData"]))) {	
				#(empty($actual[$master]['MediaInfo']["CurrentURIMetaData"]))) {					
					if ($actual[$master]['PositionInfo']['Track'] != "0")    {
						#RestoreShuffle($actual, $player);
						RestoreShuffle($master);
					}
				} 
				# TV Playbar
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")   {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# LineIn
				elseif (substr($actual[$player]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream")   {
					$sonos->SetAVTransportURI($actual[$player]['PositionInfo']["TrackURI"]); 
				} 
				# Radio Station
				elseif (!empty($actual[$master]['MediaInfo']["CurrentURIMetaData"]))    {
					#@$radionam = @$actual[$player]['MediaInfo']["title"];
					#$sonos->SetRadio($actual[$player]['PositionInfo']['TrackURI'],"$radionam");
					$sonos->SetAVTransportURI($actual[$player]['MediaInfo']["CurrentURI"], ($actual[$player]['MediaInfo']["CurrentURIMetaData"])); 
			}
			# Restore Zone Members
			#echo "TEST MEMBER<br>";
			$tmp_group = $actual[$player]['Grouping'];
			$tmp_group1st = array_shift($tmp_group);
			foreach ($tmp_group as $groupmem) {
				$sonos = new PHPSonos($sonoszone[$groupmem][0]);
				$sonos->SetAVTransportURI($actual[$groupmem]['PositionInfo']["TrackURI"]);
				$sonos->SetVolume($actual[$groupmem]['Volume']);
				$sonos->SetMute($actual[$groupmem]['Mute']);
				#LOGGING("restore_t2s.php: Member Zone ".$player." has been added back to group.", 6);
			}
			# Start restore Master settings
			#echo "TEST MASTER<br>";
			$sonos = new PHPSonos($sonoszone[$player][0]);
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			if($actual[$player]['TransportInfo'] != 1) {
				$sonos->Stop();
			} else {
				$sonos->Play();
			}
			LOGGING("restore_t2s.php: Master Zone ".$player." has been added back to group.", 6);
		break;			
		}
	}
	# setze 0 für virtuellen Texteingang (T2S Ende)
	$tts_stat = 0;
	send_tts_source($tts_stat);
	return;
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
		LOGGING("restore_t2s.php: There is no T2S batch file to be played!", 4);
        exit();
	}
	$t2s_batch = file("t2s_batch.txt");
	@unlink($filename);
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
	
	# Playlist/Track
	if ((substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 17) !== "x-sonos-htastream") && (empty($actual[$master]['MediaInfo']["CurrentURIMetaData"])))   {			
		if ($actual[$zone]['PositionInfo']['Track'] != "0")    {
			$sonos->SetTrack($actual[$zone]['PositionInfo']['Track']);
			$sonos->Seek($actual[$zone]['PositionInfo']['RelTime'],"NONE");
			if (empty($actual[$zone]['MediaInfo']["CurrentURIMetaData"]))  {
				RestoreShuffle($zone);
			}
		}
	} 
	# TV
	elseif (substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:") {
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
	} 
	# LineIn
	elseif (substr($actual[$zone]['PositionInfo']["TrackURI"], 0, 15) == "x-rincon-stream") {
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
	} 
	# Radio Station
	elseif (!empty($actual[$master]['MediaInfo']["CurrentURIMetaData"])) {
		$sonos->SetAVTransportURI($actual[$zone]['MediaInfo']["CurrentURI"], ($actual[$zone]['MediaInfo']["CurrentURIMetaData"])); 
	} else {
		LOGGING("restore_t2s.php: Something went wrong :-(", 4);
	}
	return;
}


/**
* Function : RestoreShuffle() --> Restore previous playmode settings
*
* @param: array $actual array of saved status, string $player Masterplayer
* @return: static
**/

function RestoreShuffle($player) {
	
	global $sonoszonen, $actual;
	
	$sonos = new PHPSonos($sonoszonen[$player][0]);
	$mode = $actual[$player]['TransportSettings'];
	playmode_detection($player, $mode);
	$pl = $sonos->GetCurrentPlaylist();
	$titel = (string)$actual[$player]['PositionInfo']['title'];
	// falls irgendein SHUFFLE ein
	if ($mode['shuffle'] == 1)  { 
		$trackNoSearch = recursive_array_search($titel, $pl);
		$track = (string)$pl[$trackNoSearch]['listid'];
		if ($actual[$player]['PositionInfo']['Track'] != "0")    {
			$sonos->SetTrack($track);
			$sonos->Seek($actual[$player]['PositionInfo']['RelTime'],"NONE");	
		}
	// falls SHUFFLE aus
	} else {
		# nur wenn die Queue NICHT leer war
		if ($actual[$player]['PositionInfo']['Track'] != "0")    {
			$sonos->SetTrack($actual[$player]['PositionInfo']['Track']);
			$sonos->Seek($actual[$player]['PositionInfo']['RelTime'],"NONE");	
		}
	}
	LOGGING("restore_t2s.php: Previous playmode has been restored.", 6);
}
	
?>