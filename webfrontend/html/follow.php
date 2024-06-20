<?php

/**
/* Function : follow --> follow master host by motion/presence detection
/*
/* @param:  $roomname
/* @return: 
**/

function follow()    {
	
	global $sonoszone, $config, $client, $follow, $host, $hostroom, $backup, $save_status_file;
	
	$follow = "true";
	# check if not both parameters been called
	if (isset($_GET['play']) and isset($_GET['function']))   {
		LOGWARN("follow.php: Please enter even 'play' or 'function' for '".$client."' in URL, not both");
		exit;
	}
	$backup = checkBackup();
	$hostroom = getHost();
	$client = getClient();
	$statehost = checkHostState($hostroom);
	$stateclient = checkClientState();
	connectClient($statehost);
}


/**
/* Function : getHost --> collect host data
/*
/* @param:  
/* @return: $room
**/

function getHost()    {
	
	global $sonoszone, $config, $host, $client, $backup, $hostroom;
	
	# +++ get host from URL
	if (isset($_GET['host']))   {
		$hostroom 	= $_GET['host'];
		# check if host is Online
		$state = checkOnline($hostroom);
		if ($state == "true")  {
			LOGINF("follow.php: Host '".$hostroom."' has been entered in URL and is Online");
			$host		= $sonoszone[$hostroom][1];
		} else {
			# Switch to function/play
			if (is_enabled($backup))    {
				LOGINF("follow.php: Host '".$hostroom."' has been entered in URL, but seems to be Offline! We switch to backup function from config");
				$client = getClient();
				$stateclient = checkClientState();
				playclient($client);
				exit;
			} else {
				# No backup play function
				LOGWARN("follow.php: Host '".$hostroom."' has been grabbed from URL, but seems to be Offline! We abort here...(No Backup function entered in URL)");
			exit;
			}
		}
	# +++ get host from config
	} elseif (isset($config['VARIOUS']['follow_host']) 
			and $config['VARIOUS']['follow_host'] != "false"
			and $config['VARIOUS']['follow_host'] != "")   {
		$hostroom 	= $config['VARIOUS']['follow_host'];
		# check if host is Online
		$state = checkOnline($hostroom);
		if ($state == "true")  {
			LOGDEB("follow.php: Host '".$hostroom."' has been grabbed from config and is Online");
			$host		= $sonoszone[$hostroom][1];
		} else {
			if (is_enabled($backup))    {
				# Switch to function/play
				LOGINF("follow.php: Host '".$hostroom."' has been grabbed from config, but seems to be Offline! We switch to backup function from config");
				$client = getClient();
				$stateclient = checkClientState();
				playclient($client);
				exit;
			} else {
				# No backup play function
				LOGWARN("follow.php: Host '".$hostroom."' has been grabbed from config, but seems to be Offline! We abort here...(No Backup function entered in URL)");
			exit;
			}
		}
	} else {
		LOGWARN("follow.php: No Host has been maintained in config, nore a host has been entered in URL. Please maintain config 'Options' or add '...&action=follow&host=ROOMNAME'");
		exit;
	}
	return $hostroom;
}



/**
/* Function : getClient --> collect client data
/*
/* @param:  
/* @return: $room
**/

function getClient()    {
	
	global $client, $host;
	
	# +++ get zone from URL
	if (isset($_GET['zone']))   {
		$client = $_GET['zone'];
		# check if client is Online
		$state = checkOnline($client);
		if ($state == "true")  {
			LOGINF("follow.php: Client '".$client."' is Online");
		} else {
			LOGWARN("follow.php: Client '".$client."' seems to be Offline!");
			exit;
		}
	} else {
		LOGERR("follow.php: No client (zone) has been entered");
		exit;
	}
	return $client;
}



/**
/* Function : checkHostState --> check host status
/*
/* @param:  $room
/* @return: (int)state of host
**/

function checkHostState($hostroom)    {
	
	global $sonoszone, $config, $backup, $client, $host, $hostroom, $save_status_file;
	
	#+++++++++++++++++++++++++++++++++++
	# checking host status
	#+++++++++++++++++++++++++++++++++++

	# get Host Info for preparation
	try {			
		$sonos = new SonosAccess($sonoszone[$hostroom][0]); //Sonos IP Adresse
		LOGDEB("follow.php: Host '".$hostroom."' is Online!");
	} catch (Exception $e) {
		LOGWARN("follow.php: Host '".$hostroom."' seems to be Offline!");
		return "false";
	}
	
	$stategrouph = getZoneStatus($hostroom);

	# check if Host is member of a group
	if ($stategrouph == "member")   {
		$coord 		  = getCoordinator($hostroom);
		$sonos   	  = new SonosAccess($sonoszone[$coord][0]);
		$statehost    = $sonos->GetTransportInfo();
		# check if Group master is streaming
		if ($statehost == "1")  {
			$host = $sonoszone[$coord][1];
			LOGDEB("follow.php: Host '".$hostroom."' is member of a already streaming group, we identified '".$coord."' as new host");
			$hostroom 	 = $coord;
			$tvmode  	 = $sonos->GetZoneInfo();
			$posinfo 	 = $sonos->GetPositionInfo();
			# check if new Host is in TV Mode
			if ($tvmode['HTAudioIn'] > 21 or (substr($posinfo["TrackURI"], 0, 17) == "x-sonos-htastream"))  {
				if (is_enabled($backup))    {
					# Switch to function/play
					#$client = getClient();
					$stateclient = checkClientState();
					playclient($client);
					LOGINF("follow.php: Source of new Host '".$hostroom."' is TV, we switched to backup function");
					exit;
				} else {
					# No backup play function
					LOGWARN("follow.php: Source of new Host '".$hostroom."' is TV, we abort here...(No Backup function entered in URL)");
					exit;
				}
			}
		} else {
			if (is_enabled($backup))    {
				# Switch to function/play
				#$client = getClient();
				$stateclient = checkClientState();
				playclient($client);
				LOGDEB("follow.php: Host '".$hostroom."' isn't streaming, we switch to backup function");
				exit;
			} else {
				# No backup play function
				LOGWARN("follow.php: Host '".$hostroom."' isn't streaming, we abort here...(No Backup function entered in URL)");
				exit;
			}
		}
	# Host is Single or Master
	} else {
		$sonos    	 = new SonosAccess($sonoszone[$hostroom][0]); //Sonos IP Adresse
		$tvmode  	 = $sonos->GetZoneInfo();
		$posinfo 	 = $sonos->GetPositionInfo();
		$statehost	 = $sonos->GetTransportInfo();
		# Host is in TV Mode
		if ($tvmode['HTAudioIn'] > 21 or (substr($posinfo["TrackURI"], 0, 17) == "x-sonos-htastream"))  {			
			if (is_enabled($backup))    {
				# Switch to function/play
				#$client = getClient();
				$stateclient = checkClientState();
				playclient($client);
				LOGDEB("follow.php: Source of Host '".$hostroom."' is TV, we switched to backup function");
				exit;
			} else {
				# No backup play function
				LOGWARN("follow.php: Source of Host '".$hostroom."' is TV, we abort here...(No Backup function entered in URL)");
				exit;
			}
		}
		# Host is streaming
		if ($statehost > 1)   {
			if (is_enabled($backup))    {
				# Switch to function/play
				#$client = getClient();
				$stateclient = checkClientState();
				playclient($client);
				LOGDEB("follow.php: Host '".$hostroom."' isn't streaming, we switched to backup function");
				exit;
			} else {
				# No backup play function
				LOGWARN("follow.php: Host '".$hostroom."' isn't streaming, we abort here...(No Backup function entered in URL)");
				exit;
			}
		}
	}
	return $statehost;
}



/**
/* Function : checkClientState --> check Client status
/*
/* @param:  
/* @return: State of client
**/

function checkClientState()    {
	
	global $sonoszone, $config, $follow, $client, $host, $hostroom, $save_status_file;
	
	#+++++++++++++++++++++++++++++++++++
	# checking client status
	#+++++++++++++++++++++++++++++++++++
	
	# get Client Infos for preparation
	$sonos   	  = new SonosAccess($sonoszone[$client][0]);
	$stateclient  = $sonos->GetTransportInfo();
	$stategroupc  = getZoneStatus($client);
	#print_r($stateclient);
	# check if Client is member of a group
	if ($stategroupc == "member")   {
		$coord 		  = getCoordinator($client);
		$sonos   	  = new SonosAccess($sonoszone[$coord][0]);
		$stateclient  = $sonos->GetTransportInfo();
		# check if Group master is streaming
		if ($stateclient == "1")  {
			LOGDEB("follow.php: Client '".$client."' is member of a streaming group");
			$sonos = new SonosAccess($sonoszone[$client][0]);
			exit;
		} else {
			LOGDEB("follow.php: Client '".$client."' is member of a group");
			$sonos = new SonosAccess($sonoszone[$client][0]);
		}
	}
	if ($follow == "false" and $stateclient == 1)   {
		LOGINF("follow.php: Client '".$client."' is already streaming, we abort here...");
		exit;
	}
	return $stateclient;
}



/**
/* Function : connectClient() --> connects client to Host
/*
/* @param:  
/* @return: 
**/

function connectClient($statehost)   {
	
	global $sonoszone, $config, $client, $host, $hostroom, $save_status_file;
	
	if ($statehost == 1)   {
		$sonos = new SonosAccess($sonoszone[$client][0]);
		# Save Zone Status to ramdisk
		$actual = saveClientZone($client);
		file_put_contents("/run/shm/".$save_status_file."_".$client.".json",json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));
		# add client to host
		$sonos->SetAVTransportURI("x-rincon:" . trim($host));
		$sonos->SetMute(false);	
		if (isset($_GET['volume']))   {
			$sonos->SetVolume($_GET['volume']);
		}
		LOGOK("follow.php: Client '".$client."' has been assigned to '".$hostroom."'");
	}
}


/**
/* Function : leave --> stop following Host
/*
/* @param:  
/* @return: 
**/

function leave()    {
	
	global $sonoszone, $sonos, $config, $actual, $client, $save_status_file;
	
	# get zone
	if (isset($_GET['zone']))   {
		$client = $_GET['zone'];
		$state = checkOnline($client);
		if ($state == "true")  {
			LOGINF("follow.php: Client '".$client."' is Online");
		} else {
			LOGWARN("follow.php: Client '".$client."' seems to be Offline!");
			exit;
		}
	} else {
		LOGWARN("follow.php: No client (zone) has been entered");
		exit;
	}
			
	# get wait time
	if (isset($_GET['delay']))   {
		$waitleave = $_GET['delay'];
		LOGINF("follow.php: ".$waitleave." seconds delay for '".$client."' has been entered");
	} elseif (isset($config['VARIOUS']['follow_wait']) 
			and $config['VARIOUS']['follow_wait'] != "false"
			and $config['VARIOUS']['follow_wait'] != "")   {
		$waitleave 	= $config['VARIOUS']['follow_wait'];
		LOGINF("follow.php: ".$waitleave." seconds delay for '".$client."' grabbed from config");
	} else {
		LOGWARN("follow.php: No delay to leave 'follow' function has been maintained in config, nore delay has been entered in URL. Please maintain config <Options> or add '...&action=leave&delay=SECONDS'");
		exit;
	}
			
	# restore previous settings prior function has been called
	if (file_exists("/run/shm/".$save_status_file."_".$client.".json"))   {
		sleep($waitleave);
		$actual = json_decode(file_get_contents("/run/shm/".$save_status_file."_".$client.".json"), true);
		restoreClientZone($client);
		unlink("/run/shm/".$save_status_file."_".$client.".json");
		LOGDEB("follow.php: Save file for client '".$client."' has been deleted");
	} else {
		if ($follow = "true")  {
			sleep($waitleave);
			# pause/stop current Queue 
			try {			
				$sonos->Pause();
			} catch (Exception $e) {
				$sonos->Stop();
			}
		}
		LOGOK("follow.php: Client '".$client."' has been paused streaming");
		exit;
	}
}



/**
/* Function : checkBackup --> check if Backup function been called
/*
/* @param: 
/* @return: true or false
**/
  
function checkBackup()    {
	
	global $sonoszone, $sonos, $config;
	
	if (isset($_GET['play']) or isset($_GET['function']))   {
		$backup = "true";
		if (isset($_GET['play']))   {
			LOGDEB("follow.php: Backup function '&play' from URL");
		}
		if (isset($_GET['function']))   {
			LOGDEB("follow.php: Backup function '&function' from URL");
		}
	} else {
		$backup = "false";
	}
	return $backup;
}




/**
/* Function : playclient --> start playing Queue
/*
/* @param:  $roomname
/* @return: 
**/
  
function playclient($client)    {
	
	global $sonoszone, $sonos, $config;
	
	$sonos = new SonosAccess($sonoszone[$client][0]);
	
	# if play has been entered in URL
	if (isset($_GET['play']))   {
		#$sonos   	  = new SonosAccess($sonoszone[$client][0]);
		$getclient    = $sonos->GetMediaInfo();
		$getpos    	  = $sonos->GetPositionInfo();
		# if Queue is empty
		if (empty($getclient['UpnpClass']) and empty($getpos['UpnpClass']))    {
			LOGWARN("follow.php: Client '".$client."' has no Queue to be played! Please load Playlist/Radio prior to call follow function");
		} else {
			$sonos->SetMute(false);	
			$sonos->Play();	
			LOGOK("follow.php: Client '".$client."' starts playing current Queue");
			return "play current Queue";
		}
		return;
	# if function has been entered in URL
	} elseif (isset($_GET['function']))   {
		#$sonos   	  = new SonosAccess($sonoszone[$client][0]);
		# check if Subfunction is configured
		if (isset($config['VARIOUS']['selfunction']))   {
			$source = $config['VARIOUS']['selfunction'];
			$rad = PlayZapzoneNext();
			if ($rad != "false")  {
				LOGOK("follow.php: '".$rad."' from Config has been called by Client '".$client."'");
			} else {
				LOGOK("follow.php: '".$source."' frooom Config has been called by Client '".$client."'");
			}
		}
		$sonos->SetMute(false);	
		@$sonos->Play();	
		return $source;
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