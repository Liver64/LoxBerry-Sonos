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
	
	#print_r($actual);
	#if (!$_GET['member']) {
	$restore = $actual;
	switch ($restore) {
		// Zone was playing in Single Mode
		case $actual[$master]['ZoneStatus'] == 'single':
			#$prevStatus = "single";
			restore_details($master);
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
			if (($actual[$master]['TransportInfo'] == 1)) {
				$sonos->Play();	
				RestoreShuffle($master);
			}
			LOGGING("restore_t2s.php: Single Zone ".$master." has been restored.", 6);
		break;
		
		// Zone was Member of a group
		case $actual[$master]['ZoneStatus'] == 'member':
			#$prevStatus = "member";
			$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]); 
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
			LOGGING("restore_t2s.php: Zone ".$master." has been added back to group.", 6);
		break;
		
		// Zone was Master of a group
		case $actual[$master]['ZoneStatus'] == 'master':
			if ($actual[$master]['Type'] == "LineIn") {
				#echo "LineIn";
				# Zone was Master of a group
				$sonos = new SonosAccess($sonoszone[$master][0]);
				$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]);
				
				# Restore Zone Members
				$tmp_group = $actual[$master]['Grouping'];
				$tmp_group1st = array_shift($tmp_group);
				foreach ($tmp_group as $groupmem) {
					$sonos = new SonosAccess($sonoszone[$groupmem][0]);
					$sonos->SetAVTransportURI($actual[$groupmem]['PositionInfo']["TrackURI"]);
					$sonos->SetVolume($actual[$groupmem]['Volume']);
					$sonos->SetMute($actual[$groupmem]['Mute']);
				}
				# Start restore Master settings
				$sonos = new SonosAccess($sonoszone[$master][0]);
				$sonos->SetVolume($actual[$master]['Volume']);
				$sonos->SetMute($actual[$master]['Mute']);
			} else {
				#$prevStatus = "master";
				$oldGroup = $actual[$master]['Grouping'];
				foreach ($oldGroup as $newMaster) {
					// loop threw former Members in order to get the New Coordinator
					$sonos = new SonosAccess($sonoszone[$newMaster][0]);
					$check = $sonos->GetPositionInfo();
					$checkMaster = $check['TrackURI'];
					if (empty($checkMaster)) {
						// if TrackURI is empty add Zone to New Coordinator
						$sonos_old = new SonosAccess($sonoszone[$master][0]);
						$sonos_old->SetVolume($actual[$master]['Volume']);
						$sonos_old->SetMute($actual[$master]['Mute']);
						$sonos_old->SetAVTransportURI("x-rincon:" . $sonoszone[$newMaster][1]);
					}
				}
				$rinconOfNewMaster = $sonoszone[$newMaster][1];
				$sonos = new SonosAccess($sonoszone[$master][0]);
				#@$sonos->ClearQueue();
				try {
					$sonos->SetAVTransportURI("x-rincon:" . $rinconOfNewMaster);
					$sonos->SetVolume($actual[$master]['Volume']);
					$sonos->SetMute($actual[$master]['Mute']);
					# delegate back to exMaster
					$sonos = new SonosAccess($sonoszone[$newMaster][0]);
					$sonos->DelegateGroupCoordinationTo($sonoszone[$master][1], 1);
					$sonos = new SonosAccess($sonoszone[$master][0]);
					#RestoreShuffle($master);
					#$sonos->Play();
					LOGGING("restore_t2s.php: Zone ".$master." has been added back to group.", 6);
				} catch (Exception $e) {
					LOGGING("restore_t2s.php: Assignment to new GroupCoordinator " . $newMaster . " failed.",5);	
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
		$sonos = new SonosAccess($sonoszone[$zone][0]);
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
		$sonos = new SonosAccess($sonoszone[$player][0]);
		switch($restore) {
			
		case 'single';  
			#echo 'Die Zone '.$player.' ist single<br>';
			// Zone was playing as Single Zone
			if (empty($actual[$player]['Grouping'])) {
				restore_details($player);
			}
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			if($actual[$player]['TransportInfo'] != 1) {
				$sonos->Stop();
				RestoreShuffle($player);
			} else {
				$sonos->Play();
				RestoreShuffle($player);
			}
			LOGGING("restore_t2s.php: Single Zone ".$player." has been restored.", 6);
		break;
			
		case 'member';
			#echo 'Die Zone '.$player.' ist member<br>';
			# Zone was Member of a group
			$sonos = new SonosAccess($sonoszone[$player][0]);
			$tmp_checkmember = $actual[$player]['PositionInfo']["TrackURI"];
			$sonos->SetAVTransportURI($tmp_checkmember);
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			LOGGING("restore_t2s.php: Member Zone ".$player." has been added back to group.", 6);
		break;
			
			
		case 'master';
			#echo "MASTER";
			#echo 'Die Zone '.$player.' ist master<br>';
			# Zone was Master of a group
			$sonos = new SonosAccess($sonoszone[$player][0]);
			restore_details($player);
			
			# Restore Zone Members
			#echo "TEST MEMBER<br>";
			$tmp_group = $actual[$player]['Grouping'];
			$tmp_group1st = array_shift($tmp_group);
			foreach ($tmp_group as $groupmem) {
				$sonos = new SonosAccess($sonoszone[$groupmem][0]);
				$sonos->SetAVTransportURI($actual[$groupmem]['PositionInfo']["TrackURI"]);
				$sonos->SetVolume($actual[$groupmem]['Volume']);
				$sonos->SetMute($actual[$groupmem]['Mute']);
			}
			# Start restore Master settings
			#echo "TEST MASTER<br>";
			$sonos = new SonosAccess($sonoszone[$player][0]);
			$sonos->SetVolume($actual[$player]['Volume']);
			$sonos->SetMute($actual[$player]['Mute']);
			if($actual[$player]['TransportInfo'] != 1) {
				$sonos->Stop();
				RestoreShuffle($player);
			} else {
				$sonos->Play();
				RestoreShuffle($player);
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
* Function : PlayList --> load previous saved Temp Playlist back into Queue 
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
* Function : restore_details() --> restore the details of each zone
*
* @param: 
* @return: 
**/

function restore_details($zone) {
	global $sonoszone, $sonos, $master, $actual, $j, $browselist, $senderName;

	#print_r($actual);
	# Playlist/Track
	if ($actual[$zone]['Type'] == "Track")   {
		#echo "TRACK for ".$zone."<br>";		
		if ($actual[$zone]['PositionInfo']['Track'] != "0")    {
			$sonos->SetTrack($actual[$zone]['PositionInfo']['Track']);
			$sonos->Seek("REL_TIME", $actual[$zone]['PositionInfo']['RelTime']);
			//RestoreShuffle($zone);
			LOGGING("restore_t2s.php: Source 'Track' has been set for '".$zone."'", 7);
		}
	} 
	# TV
	elseif ($actual[$zone]['Type'] == "TV") {
		#echo "TV for ".$zone."<br>";	
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
		LOGGING("restore_t2s.php: Source 'TV' has been set for '".$zone."'", 7);
	} 
	# LineIn
	elseif ($actual[$zone]['Type'] == "LineIn") {
		#echo "LineIn for ".$zone."<br>";	
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
		LOGGING("restore_t2s.php: Source 'LineIn' has been set for '".$zone."'", 7);
	} 
	# Radio Station
	elseif ($actual[$zone]['Type'] == "Radio") {
		#echo "Radio for ".$zone."<br>";	
		@$sonos->SetAVTransportURI($actual[$zone]['MediaInfo']["CurrentURI"], htmlspecialchars_decode($actual[$zone]['MediaInfo']["CurrentURIMetaData"])); 
		LOGGING("restore_t2s.php: Source 'Radio' has been set for '".$zone."'", 7);
	}
	# Queue empty
	elseif (empty($actual[$zone]['Type'])) {
		#echo "No Queue for ".$zone."<br>";	
		LOGGING("restore_t2s.php: '".$zone."' had no Queue", 7);
	} else {
		#echo "Something went wrong :-(<br>";	
		LOGGING("restore_t2s.php: Something went wrong :-(", 4);
	}
	return;
}


/**
* Function : RestoreShuffle() --> Restore previous playmode settings
*
* @param: string playmode, string player
* @return: static
**/

function RestoreShuffle($player) {
	
	global $sonoszone, $actual;
	
	$sonos = new SonosAccess($sonoszone[$player][0]);
	$mode = $actual[$player]['TransportSettings'];
	$pl = $sonos->GetCurrentPlaylist();
	if (count($pl) > 1 and ($actual[$player]['TransportSettings'] != 0))   {
		$modereal = playmode_detection($player, $mode);
		LOGGING("restore_t2s.php: Previous playmode '".$modereal."' for '".$player."' has been restored.", 6);		
	}
	
}

	
?>