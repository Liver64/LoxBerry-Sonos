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
	
	
	$hostroom = getHost();
	$statehost = checkHostState($hostroom);
	$backup = checkBackup();
	$client = getClient();
	#$statehost = checkHostState($hostroom);
	$stateclient = checkClientState();
	if (!file_exists("/run/shm/".$save_status_file."_".$client.".json"))   {
		connectClient($statehost);
	}
	#file_put_contents("/run/shm/".$save_status_file."_".$client.".json", "1");
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
			$host = $sonoszone[$hostroom][1];
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
	#if ($follow == "false" and $stateclient == 1)   {
	if ($stateclient == 1)   {
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
		#$actual = saveClientZone($client);
		#file_put_contents("/run/shm/".$save_status_file."_".$client.".json",json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));
		file_put_contents("/run/shm/".$save_status_file."_".$client.".json", "1");
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
	
	getClient();
	if (file_exists("/run/shm/".$save_status_file."_".$client.".json"))   {
		if (!isset($_GET['zone']))   {
			LOGWARN("follow.php: No client (zone) has been entered");
			exit;
		}
		@unlink("/run/shm/".$save_status_file."_".$client.".json");
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
				
		sleep($waitleave);
		$sonos = new SonosAccess($sonoszone[$client][0]);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		#@unlink("/run/shm/".$save_status_file."_".$client.".json");
		LOGOK("follow.php: Client '".$client."' has been stopped streaming");
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
?>