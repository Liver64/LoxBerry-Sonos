#!/usr/bin/env php
<?php

/**
* Submodul: TV Monitoring
*
**/

require_once "loxberry_system.php";
require_once "loxberry_log.php";
include($lbphtmldir."/system/sonosAccess.php");
include($lbphtmldir."/Grouping.php");
include($lbphtmldir."/Speaker.php");
include($lbphtmldir."/Helper.php");

$configfile			= "s4lox_config.json";							// configuration file
$TV_safe_file		= "s4lox_TV_save";								// saved Values of all SB's
$status_file		= "s4lox_TV_on";								// TV is running
$mask 				= 's4lox_TV*.*';								// mask for delition
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";			// Folder and file name for Player Status
$Stunden 			= intval(strftime("%H"));

$time_start = microtime(true);

echo "<PRE>";

/*******

Tests: 	

*******/

	# Preparation
	
	# load Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
		# check if no TV Volume turned on
		if ($config['VARIOUS']['tvmon'] == false)   {
			echo "TV Monitor off".PHP_EOL;
			exit(1);
		} else {
			echo "TV Monitor on".PHP_EOL;
		}
	} else {
		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
		exit;
	}
	# extract Players
	$sonoszone = ($config['sonoszonen']);
	
	if ($Stunden >= $config['VARIOUS']['starttime'] && $Stunden < $config['VARIOUS']['endtime'])   { 	
	
		# Extract predefined soundbars only (marked with SB and Volume > 0)
		$soundbars = array_filter($sonoszone, fn($innerArr) => isset($innerArr[11]) && $innerArr[12] > 0);

		# ... and then check for their Online Status
		$zonesonline = array();	
		foreach($soundbars as $zonen => $ip) {
			$handle = is_file($folfilePlOn."".$zonen.".txt");
			if($handle == true) {
				$zonesonline[$zonen] = $ip;
			}
		}
		$soundbars = $zonesonline;
		#print_r($soundbars);
		
		# ERROR handling if no Soundbar is Online
		if (empty($soundbars))    {
			echo "No Soundbar has been detected or the 'TV Vol' is missing or Soundbar is offline, we skip here...".PHP_EOL;
			startLog();
			LOGWARN("bin/tv_monitor.php: No Soundbar has been detected or the 'TV Vol' is missing or Soundbar is offline, we skip here...");
			exit(1);
		}
				
		# End Preparation
		
		# Start script
		
		foreach($soundbars as $key => $value)   {
			$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
			$tvmodi = $sonos->GetZoneInfo();
			$posinfo = $sonos->GetPositionInfo();
			$master = $key;
			if ($tvmodi['HTAudioIn'] == 0)  {				// Zeitbegrenzung einbauen
				echo "Soundbar TV Mode has been turned off".PHP_EOL;
				# TV has been turned off
				if (file_exists("/run/shm/".$TV_safe_file."_".$key.".json"))   {
					$actual = json_decode(file_get_contents("/run/shm/".$TV_safe_file."_".$key.".json"), true);
					startLog();
					restoreSingleZone();
					LOGDEB("bin/tv_monitor.php: Soundbar TV Mode has been turned off and previous settings has been restored.");
				}
				DelFiles();
				# TV is On and input at SPDIF
			} elseif ($tvmodi['HTAudioIn'] > 21 or (substr($posinfo["TrackURI"], 0, 17) == "x-sonos-htastream"))  {
				if (!file_exists("/run/shm/".$status_file."_".$key.".tmp"))   {
					startLog();
					$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
					$sonos->SetVolume($soundbars[$key][12]);
					echo "Volume has been set to: ".$soundbars[$key][12]."".PHP_EOL;
					echo "Soundbar ON and in TV Mode".PHP_EOL;
					file_put_contents("/run/shm/".$status_file."_".$key.".tmp", "On");
					LOGDEB("bin/tv_monitor.php: Soundbar is ON and in TV Mode.");
				} else {
					echo "Soundbar ON and in TV Mode, Volume has been set previously".PHP_EOL;
				}
				# Music is loaded/playing
			} else {
				echo "Music is loaded/streaming".PHP_EOL;
				$actual = PrepSaveZonesStati();
				file_put_contents("/run/shm/".$TV_safe_file."_".$key.".json",json_encode($actual, JSON_PRETTY_PRINT) );
			}
		}
		# identify results of TV Modus
		
		/*******
		values depend on what input is running (tested with BEAM Gen 2 and Samsung TV Frame)
		
		Single Stream:
		TV 				= 33554434
		Stream/Radio 	= 21
		off 			= 0
		
		Grouping:
		Member 			= 21
		Master 			= 21
		*******/
		
		$time_end = microtime(true);
		$process_time = $time_end - $time_start;
		echo "Processing request tooks about ".round($process_time, 2)." seconds.".PHP_EOL;
	} else {
		# action outside hours
		DelFiles();
	}

/**
/* Function : startLog --> start logging
/*
/* @param:  none
/* @return: none
**/

function startLog()    {
	
	global $lbplogdir;

	$params = [	"name" => "Sonos PHP",
				"filename" => "$lbplogdir/sonos.log",
				"append" => 1,
				"addtime" => 1,
				];
	$level = LBSystem::pluginloglevel();
	$log = LBLog::newLog($params);
	#LOGSTART("Sonos PHP");
	return;
}


/**
/* Function : DelFiles --> delete tmp files
/*
/* @param:  none
/* @return: none
**/

function DelFiles()    {
	
	global $mask;
	
	@array_map('unlink', glob($mask));
}



/**
/* Function : PrepSaveZonesStati --> start Preparation for to save zone status
/*
/* @param:  none
/* @return: array saved details 
**/

function PrepSaveZonesStati() {
	
	global $sonoszone, $soundbars, $sonos, $player, $actual, $time_start, $log, $folfilePlOn;
	
	# identify if Soundbars are grouped
	foreach($soundbars as $zonen => $ip) {
		$sonos = new SonosAccess($soundbars[$zonen][0]); //Sonos IP Adresse
		$relzones = getGroup($zonen);
	}
	#print_r($relzones);

	# Filter Player by grouped Players 
	if (!empty($relzones))    {
		$filtered = array_filter(
			$sonoszone,
			fn ($key) => in_array($key, $relzones),
			ARRAY_FILTER_USE_KEY
		);
		$sonoszone = $filtered;
	} else {
		$sonoszone = $soundbars;
	}
	
	# check filtered Zones Online and create new temp array
	$zonesonline = array();
	foreach($sonoszone as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			$sonoszone[$zonen] = $ip;
			array_push($zonesonline, $zonen);
		}
	}
	
	# ERROR Handling: If no Player Online we exit
	if (empty($zonesonline))    {
		echo "No Players are online, we skip here...".PHP_EOL;
		startLog();
		LOGDEB("bin/tv_monitor.php: No Players are online, we skip here...");
		exit(1);
	}
	$actual = saveZonesStati();
	return($actual);
}


/**
/* Function : saveZonesStati --> saving of all needed info to restore later
/*
/* @param:  none
/* @return: none
**/

function saveZonesStati() {
	
	global $sonoszone, $sonos, $player, $actual, $time_start, $log, $folfilePlOn;

	// save each Zone Status
	foreach ($sonoszone as $player => $value) {
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
		$actual[$player]['CONNECT'] = GetVolumeModeConnect($player);
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
	#print_r($actual);
	return $actual;
}


/**
* Function : restoreSingleZone --> restores previous Zone settings before a single message has been played 
*
* @param:  empty
* @return: previous settings
**/		

function restoreSingleZone() {
	global $sonoszone, $sonos, $master, $actual, $time_start, $log, $mode, $tts_stat;
	
	$sonos = new SonosAccess($sonoszone[$master][0]); 
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
			LOGDEB("bin/tv_monitor.php: Single Zone ".$master." has been restored.");
		break;
		
		// Zone was Member of a group
		case $actual[$master]['ZoneStatus'] == 'member':
			#$prevStatus = "member";
			try {
				$sonos->SetAVTransportURI($actual[$master]['PositionInfo']["TrackURI"]); 
				$sonos->SetVolume($actual[$master]['Volume']);
				$sonos->SetMute($actual[$master]['Mute']);
				LOGDEB("bin/tv_monitor.php: Zone ".$master." has been added back to group.");
			} catch (Exception $e) {
				LOGWARN("bin/tv_monitor.php: Re-Assignment to previous Zone failed.");	
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
					LOGWARN("bin/tv_monitor.php: Restore to previous status (Master of Line-in failed.");	
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
						LOGWARN("bin/tv_monitor.php: Restore to previous status (Member of Line-in) failed.");	
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
						LOGWARN("bin/tv_monitor.php: Restore to previous Group Coordinator status failed.");	
					}
					if (empty($checkMaster)) {
						try {
							// if TrackURI is empty add Zone to New Coordinator
							$sonos_old = new SonosAccess($sonoszone[$master][0]);
							$sonos_old->SetVolume($actual[$master]['Volume']);
							$sonos_old->SetMute($actual[$master]['Mute']);
							$sonos_old->SetAVTransportURI("x-rincon:" . $sonoszone[$newMaster][1]);
						} catch (Exception $e) {
							LOGWARN("bin/tv_monitor.php: Restore to previous Group Coordinator status failed.");	
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
					LOGDEB("bin/tv_monitor.php: Zone '".$master."' has been added back to group.");
				} catch (Exception $e) {
					LOGWARN("bin/tv_monitor.php: Assignment to Zone '" . $newMaster . "' failed.");	
				}	

			}
		break;
	}
	return;
}


/**
* Function : restore_details() --> restore the details of each zone
*
* @param: 
* @return: 
**/

function restore_details($zone) {
	global $sonoszone, $sonos, $master, $actual, $j, $browselist, $senderName, $log;

	# Playlist/Track
	$sonos = new SonosAccess($sonoszone[$zone][0]); 
	if ($actual[$zone]['Type'] == "Track")   {	
		#echo "TRACK for ".$zone."<br>";		
		if ($actual[$zone]['PositionInfo']['Track'] != "0")    {
			$sonos->SetQueue("x-rincon-queue:".$sonoszone[$zone][1]."#0");
			$sonos->SetTrack($actual[$zone]['PositionInfo']['Track']);
			$sonos->Seek("REL_TIME", $actual[$zone]['PositionInfo']['RelTime']);
			//RestoreShuffle($zone);
			LOGDEB("bin/tv_monitor.php: Source 'Track' has been set for '".$zone."'");
		}
	} 
	# TV
	elseif ($actual[$zone]['Type'] == "TV") {
		#echo "TV for ".$zone."<br>";	
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
		LOGDEB("bin/tv_monitor.php: Source 'TV' has been set for '".$zone."'");
	} 
	# LineIn
	elseif ($actual[$zone]['Type'] == "LineIn") {
		#echo "LineIn for ".$zone."<br>";	
		$sonos->SetAVTransportURI($actual[$zone]['PositionInfo']["TrackURI"]); 
		LOGDEB("bin/tv_monitor.php: Source 'LineIn' has been set for '".$zone."'");
	} 
	# Radio Station
	elseif ($actual[$zone]['Type'] == "Radio") {
		#echo "Radio for ".$zone."<br>";	
		$sonos->SetAVTransportURI($actual[$zone]['MediaInfo']["CurrentURI"], htmlspecialchars_decode($actual[$zone]['MediaInfo']["CurrentURIMetaData"])); 
		LOGDEB("bin/tv_monitor.php: Source 'Radio' has been set for '".$zone."'");
	}
	# Queue empty
	elseif (empty($actual[$zone]['Type'])) {
		#echo "No Queue for ".$zone."<br>";	
		LOGDEB("bin/tv_monitor.php: '".$zone."' had no Queue");
	} else {
		#echo "Something went wrong :-(<br>";	
		LOGDEB("bin/tv_monitor.php: Something went wrong :-(");
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
	
	global $sonoszone, $actual, $log;
	
	$sonos = new SonosAccess($sonoszone[$player][0]);
	$mode = $actual[$player]['TransportSettings'];
	$pl = $sonos->GetCurrentPlaylist();
	if (count($pl) > 1 and ($actual[$player]['TransportSettings'] != 0))   {
		$modereal = playmode_detection($player, $mode);
		LOGDEB("bin/tv_monitor.php: Previous playmode '".$modereal."' for '".$player."' has been restored.");		
	}
	
}

#LOGEND("Sonos PHP");
	



?>