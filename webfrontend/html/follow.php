<?php

/**
/* Function : follow --> follow master host by motion detector
/*
/* @param:  $roomname
/* @return: 
**/

function follow($client)    {
	
	global $sonoszone, $config, $client, $host, $hostroom, $save_status_file;
	
	#+++++++++++++++++++++++++++++++++++
	# checking host status
	#+++++++++++++++++++++++++++++++++++
	
	# get Host Info for preparation
	$sonos   	 = new SonosAccess($sonoszone[$hostroom][0]); //Sonos IP Adresse
	$stategrouph = getZoneStatus($hostroom);
	
	# check if Host is member of a group
	if ($stategrouph == "member")   {
		$coord 		  = getCoordinator($hostroom);
		$sonos   	  = new SonosAccess($sonoszone[$coord][0]);
		$statehost    = $sonos->GetTransportInfo();
		# check if Group master is streaming
		if ($statehost == "1")  {
			$host = $sonoszone[$coord][1];
			LOGINF("follow.php: Host '".$hostroom."' is member of a already streaming group, we identified '".$coord."' as new host");
			$hostroom 	 = $coord;
			$tvmode  	 = $sonos->GetZoneInfo();
			$posinfo 	 = $sonos->GetPositionInfo();
			# check if new Host is in TV Mode
			if ($tvmode['HTAudioIn'] > 21 or (substr($posinfo["TrackURI"], 0, 17) == "x-sonos-htastream"))  {
				LOGINF("follow.php: Source of new Host '".$hostroom."' is TV, we skip...");
				exit;
			}
		} else {
			LOGINF("follow.php: Host '".$hostroom."' isn't streaming , we skip...");
			exit;
		}
	# Host is Single or Master
	} else {
		$sonos    	 = new SonosAccess($sonoszone[$hostroom][0]); //Sonos IP Adresse
		$tvmode  	 = $sonos->GetZoneInfo();
		$posinfo 	 = $sonos->GetPositionInfo();
		$statehost	 = $sonos->GetTransportInfo();
		# Host is in TV Mode
		if ($tvmode['HTAudioIn'] > 21 or (substr($posinfo["TrackURI"], 0, 17) == "x-sonos-htastream"))  {
			LOGINF("follow.php: Source of Host '".$hostroom."' is TV, we skip...");
			exit;
		}
		# Host is streaming
		if ($statehost > 1)   {
			LOGINF("follow.php: Host '".$hostroom."' isn't streaming , we skip...");
			exit;
		}
	}
	
	#+++++++++++++++++++++++++++++++++++
	# checking client status
	#+++++++++++++++++++++++++++++++++++
	
	# get Client Infos for preparation
	$sonos   	  = new SonosAccess($sonoszone[$client][0]);
	$stateclient  = $sonos->GetTransportInfo();
	$stategroupc  = getZoneStatus($client);
	
	# check if Client is member of a group
	if ($stategroupc == "member")   {
		$coord 		  = getCoordinator($client);
		$sonos   	  = new SonosAccess($sonoszone[$coord][0]);
		$stateclient  = $sonos->GetTransportInfo();
		# check if Group master is streaming
		if ($stateclient == "1")  {
			LOGINF("follow.php: Client '".$client."' is member of a streaming group , we skip...");
			$sonos = new SonosAccess($sonoszone[$client][0]);
			exit;
		} else {
			LOGINF("follow.php: Client '".$client."' is member of a group");
			$sonos = new SonosAccess($sonoszone[$client][0]);
		}
	}
	# Client is streaming
	if ($stateclient == 1)   {
		LOGINF("follow.php: Client '".$client."' is already streaming , we skip...");
		exit;
	} else {
		# Save Zone Status to ramdisk
		$actual = saveClientZone($client);
		file_put_contents("/run/shm/".$save_status_file."_".$client.".json",json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));
		# add client to host
		$sonos->SetAVTransportURI("x-rincon:" . trim($host));
		$sonos->SetMute(false);	
		LOGOK("follow.php: Client '".$client."' has been assigned to '".$hostroom."'");
	}
}



/**
/* Function : leave --> stop following Host
/*
/* @param:  $roomname
/* @return: 
**/

function leave($client, $waitleave)    {
	
	global $sonoszone, $sonos, $config, $actual, $client, $save_status_file;
	
	if (file_exists("/run/shm/".$save_status_file."_".$client.".json"))   {
		sleep($waitleave);
		$actual = json_decode(file_get_contents("/run/shm/".$save_status_file."_".$client.".json"), true);
		restoreClientZone($client);
		unlink("/run/shm/".$save_status_file."_".$client.".json");
	} else {
		LOGINF("follow.php: Client '".$client."' is not connected to Host , we skip...");
		exit;
	}
	
}
	

/**
/* Function : saveClientZone --> save all neccessary info to restore later
/*
/* @param:  $client
/* @return: $array
**/

function saveClientZone($client) {
	
	global $sonoszone;

	// save each Zone Status
	foreach ($sonoszone as $player => $value) {
		if ($player == $client)    {
			@$sonos = new SonosAccess($sonoszone[$player][0]); 
			$actual[$player]['Mute'] = $sonos->GetMute($player);
			$actual[$player]['Volume'] = $sonos->GetVolume($player);
			$actual[$player]['MediaInfo'] = $sonos->GetMediaInfo($player);
			$actual[$player]['PositionInfo'] = $sonos->GetPositionInfo($player);
			$actual[$player]['TransportInfo'] = $sonos->GetTransportInfo($player);
			$actual[$player]['TransportSettings'] = $sonos->GetTransportSettings($player);
			$actual[$player]['Group-ID'] = $sonos->GetZoneGroupAttributes($player);
			$actual[$player]['Grouping'] = getGroup($player);
			$actual[$player]['ZoneStatus'] = getZoneStatus($player);
			#$actual[$player]['CONNECT'] = GetVolumeModeConnect($player);
			$posinfo = $sonos->GetPositionInfo($player);
			$media = $sonos->GetMediaInfo($player);
			$zonestatus = getZoneStatus($player);
			if ($zonestatus != "member")    {
				if (substr($posinfo["TrackURI"], 0, 18) == "x-sonos-htastream:")  {
					$actual[$player]['Type'] = "TV";
				} elseif (substr($actual[$player]['MediaInfo']["UpnpClass"] ,0 ,36) == "object.item.audioItem.audioBroadcast")  {
					$actual[$player]['Type'] = "Radio";
				} elseif (substr($posinfo["TrackURI"], 0, 15) == "x-rincon-stream")   {
					$actual[$player]['Type'] = "LineIn";
				} elseif (empty($posinfo["CurrentURIMetaData"]))   {
					$actual[$player]['Type'] = "";
				} else {
					$actual[$player]['Type'] = "Track";
				}
			}
		}
	}
	#print_r($actual);
	return $actual;
}



/**
* Function : restoreClientZone --> restores previous Zone settings before a single message has been played 
*
* @param:  empty
* @return: previous settings
**/		

function restoreClientZone($client) {
	
	global $sonoszone, $sonos, $actual;
	
	$master = $client;
	$sonos = new SonosAccess($sonoszone[$master][0]); 
	$restore = $actual;
	#print_r($restore);
	switch ($restore) {
		// Zone was playing in Single Mode
		case $actual[$master]['ZoneStatus'] == 'single':
			#$prevStatus = "single";
			restore_details_client($master);
			$sonos->SetVolume($actual[$master]['Volume']);
			$sonos->SetMute($actual[$master]['Mute']);
			if (($actual[$master]['TransportInfo'] == 1)) {
				$sonos->Play();	
				RestoreShuffleClient($master);
			}
			LOGDEB("follow.php: Single Zone ".$master." has been restored.");
		break;
		
		// Zone was Member of a group
		case $actual[$master]['ZoneStatus'] == 'member':
			#$prevStatus = "member";
			try {
				$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]); 
				$sonos->SetVolume($actual[$master]['Volume']);
				$sonos->SetMute($actual[$master]['Mute']);
				LOGDEB("follow.php: Zone ".$master." has been added back to group.");
			} catch (Exception $e) {
				LOGWARN("follow.php: Re-Assignment to previous Zone failed.");	
			}
		break;
		
		// Zone was Master of a group
		case $actual[$master]['ZoneStatus'] == 'master':
			if ($actual[$master]['Type'] == "LineIn") {
				#echo "LineIn";
				# Zone was Master of a group
				try {
					$sonos = new SonosAccess($sonoszone[$master][0]);
					$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]);
				} catch (Exception $e) {
					LOGWARN("follow.php: Restore to previous status (Master of Line-in failed.");	
				}
				# Restore Zone Members
				$tmp_group = $actual[$master]['Grouping'];
				$tmp_group1st = array_shift($tmp_group);
				foreach ($tmp_group as $groupmem) {
					try {
						$sonos = new SonosAccess($sonoszone[$groupmem][0]);
						$sonos->SetAVTransportURI($actual[$groupmem]['PositionInfo']["TrackURI"]);
						$sonos->SetVolume($actual[$groupmem]['Volume']);
						$sonos->SetMute($actual[$groupmem]['Mute']);
					} catch (Exception $e) {
						LOGWARN("follow.php: Restore to previous status (Member of Line-in) failed.");	
					}
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
					try {
						$sonos = new SonosAccess($sonoszone[$newMaster][0]);
						$check = $sonos->GetPositionInfo();
						$checkMaster = $check['TrackURI'];
					} catch (Exception $e) {
						LOGWARN("follow.php: Restore to previous Group Coordinator status failed.");	
					}
					if (empty($checkMaster)) {
						try {
							// if TrackURI is empty add Zone to New Coordinator
							$sonos_old = new SonosAccess($sonoszone[$master][0]);
							$sonos_old->SetVolume($actual[$master]['Volume']);
							$sonos_old->SetMute($actual[$master]['Mute']);
							$sonos_old->SetAVTransportURI("x-rincon:" . $sonoszone[$newMaster][1]);
						} catch (Exception $e) {
							LOGWARN("follow.php: Restore to previous Group Coordinator status failed.");	
						}
					}
				}
				$rinconOfNewMaster = $sonoszone[$newMaster][1];
				$sonos = new SonosAccess($sonoszone[$master][0]);
				try {
					$sonos->SetAVTransportURI("x-rincon:" . $rinconOfNewMaster);
					$sonos->SetVolume($actual[$master]['Volume']);
					$sonos->SetMute($actual[$master]['Mute']);
					# delegate back to exMaster
					#$sonos = new SonosAccess($sonoszone[$newMaster][0]);
					#$sonos->DelegateGroupCoordinationTo($sonoszone[$master][1], 1);
					#$sonos = new SonosAccess($sonoszone[$master][0]);
					LOGDEB("follow.php: Zone '".$master."' has been added back to group.");
				} catch (Exception $e) {
					LOGWARN("follow.php: Assignment to Zone '" . $newMaster . "' failed.");	
				}	

			}
		break;
	}
	return;
}


/**
* Function : restore_details_client() --> restore the details of each zone
*
* @param:  Player
* @return: restore
**/

function restore_details_client($zone) {
	global $sonoszone, $sonos, $master, $actual, $j, $browselist, $senderName, $log;

	# Playlist/Track
	$sonos = new SonosAccess($sonoszone[$zone][0]); 
	if ($actual[$zone]['Type'] == "Track")   {	
		if ($actual[$zone]['PositionInfo']['Track'] != "0")    {
			$sonos->SetQueue("x-rincon-queue:".$sonoszone[$zone][1]."#0");
			$sonos->SetTrack($actual[$zone]['PositionInfo']['Track']);
			$sonos->Seek("REL_TIME", $actual[$zone]['PositionInfo']['RelTime']);
			LOGDEB("follow.php: Source 'Track' has been set for '".$zone."'");
		}
	} 
	# TV
	elseif ($actual[$zone]['Type'] == "TV") {
		#echo "TV for ".$zone."<br>";	
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
		LOGDEB("follow.php: Source 'TV' has been set for '".$zone."'");
	} 
	# LineIn
	elseif ($actual[$zone]['Type'] == "LineIn") {
		#echo "LineIn for ".$zone."<br>";	
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
		LOGDEB("follow.php: Source 'LineIn' has been set for '".$zone."'");
	} 
	# Radio Station
	elseif ($actual[$zone]['Type'] == "Radio") {
		#echo "Radio for ".$zone."<br>";	
		$sonos->SetAVTransportURI($actual[$zone]['MediaInfo']["CurrentURI"], htmlspecialchars_decode($actual[$zone]['MediaInfo']["CurrentURIMetaData"])); 
		LOGDEB("follow.php: Source 'Radio' has been set for '".$zone."'");
	}
	# Queue empty
	elseif (empty($actual[$zone]['Type'])) {
		#echo "No Queue for ".$zone."<br>";	
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$zone][1]."#0");
		LOGDEB("follow.php: '".$zone."' had no Queue");
	} else {
		#echo "Something went wrong :-(<br>";	
		LOGDEB("follow.php: Something went wrong :-(");
	}
	return;
}


/**
* Function : RestoreShuffleClient() --> Restore previous playmode settings
*
* @param: string playmode, string player
* @return: static
**/

function RestoreShuffleClient($player) {
	
	global $sonoszone, $actual, $log;
	
	$sonos = new SonosAccess($sonoszone[$player][0]);
	$mode = $actual[$player]['TransportSettings'];
	$pl = $sonos->GetCurrentPlaylist();
	if (count($pl) > 1 and ($actual[$player]['TransportSettings'] != 0))   {
		$modereal = playmode_detection($player, $mode);
		LOGDEB("follow.php: Previous playmode '".$modereal."' for '".$player."' has been restored.");		
	}
	
}


?>