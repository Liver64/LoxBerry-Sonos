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
include($lbphtmldir."/Info.php");

ini_set('max_execution_time', 30); 	
register_shutdown_function('shutdown');

$configfile			= "s4lox_config.json";								// configuration file
$TV_safe_file		= "s4lox_TV_save";									// saved Values of all SB's
$status_file		= "s4lox_TV_on";									// TV has been turned on
#$status_file_run	= "s4lox_TV_on_run";								// TV is running
$restore_file		= "s4lox_restore";									// Settings restore file
$mask 				= 's4lox_TV*.*';									// mask for deletion
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";				// Folder and file name for Player Status
$statusNight 		= "s4lox_TV_night_on";								// Folder and file name for Night Modus

$Stunden 			= date("H");
$time_start 		= microtime(true);
#var_dump($Stunden);

echo "<PRE>";

	# Preparation
	
	# load Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
		# check if no TV Volume turned on
		if (is_disabled($config['VARIOUS']['tvmon']))   {
			echo "TV Monitor off".PHP_EOL;
			DelFiles($mask);
			exit(1);
		} else {
			echo "TV Monitor on".PHP_EOL;
			echo "<br>";
		}
	} else {
		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
		exit;
	}
	#print_r($config);

	# extract all Players and identify those were Online
	$sonoszone = array();
	foreach($config['sonoszonen'] as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			$sonoszone[$zonen] = $ip;
		}
	}
	#print_r($sonoszone);

	if ((string)$Stunden >= (string)$config['VARIOUS']['starttime'] && (string)$Stunden < $config['VARIOUS']['endtime'])   { 	
		$soundbars = identSB($sonoszone, $folfilePlOn);
		#$soundbars = array_merge_recursive($soundbars, $config['SOUNDBARS']);
		#print_r($soundbars);
		
		# Start script
		
		foreach($soundbars as $key => $value)   {
			#********************************************
			# If Soundbar has been configured On
			#********************************************
			
			if ((bool)is_enabled($soundbars[$key][14]['usesb']))   {
				if (file_exists("/run/shm/".$restore_file."_".$key.".json"))   {
					unlink("/run/shm/".$restore_file."_".$key.".json");
				}
				try {
					$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
					$tvmodi = $sonos->GetZoneInfo();
					$posinfo = $sonos->GetPositionInfo();
					$state = $sonos->GetTransportInfo();
					$master = $key;
					$dialog = Getdialoglevel();
					#print_r($dialog);
					#**********************************************
					# Soundbar if off
					#**********************************************
					if ($tvmodi['HTAudioIn'] == 0)  {				// Zeitbegrenzung einbauen
						echo "Soundbar TV Mode for ".$key." has been turned off".PHP_EOL;
						# TV has been turned off
						if (file_exists("/run/shm/".$TV_safe_file."_".$key.".json"))   {
							$actual = json_decode(file_get_contents("/run/shm/".$TV_safe_file."_".$key.".json"), true);
							$logname = startlog("TV Monitor", "tv_monitor");
							# Restore previous Zone settings
							restoreSingleZone($sonoszone, $master);
							@array_map('unlink', glob('/run/shm/s4lox_TV*'.$key.'*.*'));
							LOGDEB("bin/tv_monitor.php: Soundbar TV Mode for ".$key." has been turned off and previous settings has been restored.");
						}
						#***********************************************
						# Soundbar has been turned On 1st time 
						#***********************************************
					} elseif ($tvmodi['HTAudioIn'] > 21 or (substr($posinfo["TrackURI"], 0, 17) == "x-sonos-htastream"))  {
						# Save Soundbar settings
						if ((bool)is_enabled($soundbars[$key][14]['tvmonspeech']) === true ? $dia = "On" : $dia = "Off");
						if ((bool)is_enabled($soundbars[$key][14]['tvmonsurr']) === true ? $sur = "On" : $sur = "Off");
						if ((bool)is_enabled($soundbars[$key][14]['tvmonnight']) === true ? $night = "On" : $night = "Off");
						if ((bool)is_enabled($soundbars[$key][14]['tvmonnightsub']) === true ? $sub = "On" : $sub = "Off");
						$sublevel = $soundbars[$key][14]['tvmonnightsublevel'];
						# TV has been turned on
						if (!file_exists("/run/shm/".$status_file."_".$key.".json"))   {
							echo "TV Mode for Soundbar '".$key."' has been turned On".PHP_EOL;
							$logname = startlog("TV Monitor", "tv_monitor");
							$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
							# Set Volume
							if ($soundbars[$key][14]['tvvol'] < 5)    {
								$sonos->SetVolume($soundbars[$key][4]);
								$vol = $soundbars[$key][4];
							} else {
								$sonos->SetVolume($soundbars[$key][14]['tvvol']);
								$vol = $soundbars[$key][14]['tvvol'];
							}
							try {
								$dialog['Volume'] = $vol;
								# Turn Speech/Surround/Dialog Mode On and Mute Off
								$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonspeech']), 'DialogLevel');
								echo "DialogLevel for Soundbar ".$key." has been turned ".$dia."".PHP_EOL;
								LOGDEB("bin/tv_monitor.php: DialogLevel for Soundbar ".$key." has been turned ".$dia."");
								$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonsurr']), 'SurroundEnable');
								echo "SurroundEnable for Soundbar ".$key." has been turned ".$sur."".PHP_EOL;
								LOGDEB("bin/tv_monitor.php: SurroundEnable for Soundbar ".$key." has been turned ".$sur);
								$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonnightsub']), 'SubEnable');
								echo "Subwoofer for Soundbar ".$key." has been turned ".$sub."".PHP_EOL;
								LOGDEB("bin/tv_monitor.php: Subwoofer for Soundbar ".$key." has been turned ".$sub);
								@$sonos->SetMute(false);
								# Save Original settings
								file_put_contents("/run/shm/".$status_file."_".$key.".json", json_encode($dialog, JSON_PRETTY_PRINT));
							} catch (Exception $e) {
								echo "Speech/Surround/Night Mode/Subwoofer could'nt been turned On for: ".$key."".PHP_EOL;
								LOGWARN("bin/tv_monitor.php: Speech/Surround/Night Mode/Subwoofer could'nt been turned On for: ".$key);
								@LOGEND($logname);	
							}
							echo "Volume for '".$key."' has been set to: ".$vol.PHP_EOL;
							LOGDEB("bin/tv_monitor.php: Soundbar ".$key." is On and in TV Mode.");
						} else {
							#******************************************************
							# Soundbar is already running
							#******************************************************
							echo "TV Mode for Soundbar '".$key."' is already running.".PHP_EOL;
							# set Nightmode and Subgain
							if ($soundbars[$key][14]['fromtime'] != "false")    {
								if ((string)$Stunden >= (string)$soundbars[$key][14]['fromtime'])   { 
									if (!file_exists("/run/shm/".$statusNight."_".$key.".json"))   {
										# Turn Night Mode On/Off
										$logname = startlog("TV Monitor", "tv_monitor");										
										$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonnight']), 'NightMode');
										echo "NightMode for Soundbar ".$key." has been turned to ".$night."".PHP_EOL;
										LOGDEB("bin/tv_monitor.php: NightMode for Soundbar ".$key." has been turned to ".$night);
										# Set Sub Level
										@$sonos->SetDialogLevel($soundbars[$key][14]['tvmonnightsublevel'], 'SubGain');
										echo "Subwoofer Level for Soundbar ".$key." has been set to: ".$sublevel."".PHP_EOL;
										LOGDEB("bin/tv_monitor.php: Subwoofer Level for Soundbar ".$key." has been set to: ".$sublevel);
										file_put_contents("/run/shm/".$statusNight."_".$key.".json",json_encode("1", JSON_PRETTY_PRINT));
									}
								}
							echo "Soundbar ".$key." is On and in TV Mode, all settings has been set previously".PHP_EOL;
							}
						}
						#******************************************************
						# Music is loaded/playing
						#******************************************************
					} else {
						echo "Music on ".$key." is loaded...".PHP_EOL;
						$actual = PrepSaveZonesStati();
						file_put_contents("/run/shm/".$TV_safe_file."_".$key.".json",json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));
						if ($state == 1)   {	
							echo "...and streaming".PHP_EOL;
						} else {
							echo "...but paused or stopped".PHP_EOL;
						}
					}
					echo "Current incoming value for ".$key." at HDMI/SPDIF: ".$tvmodi['HTAudioIn'].PHP_EOL;
				} catch (Exception $e) {
					echo "Soundbar '".$key."' has not responded , maybe Soundbar is offline, we skip here...".PHP_EOL;
					$logname = startlog("TV Monitor", "tv_monitor");
					LOGINF("bin/tv_monitor.php: Soundbar '".$key."' has not responded , maybe Soundbar is offline, we skip here...");
				}
			#********************************************
			# If Soundbar is turned Off in Plugin
			#********************************************
			} else {
				#DelFiles($mask);
				@array_map('unlink', glob('/run/shm/s4lox_TV*'.$key.'*.*'));				
				echo "TV Monitor for Soundbar '".$key."' is turned off in Plugin Config".PHP_EOL;
			}
		}
	#********************************************************
	# restore previous soundbar settings 
	#********************************************************
	# turn nightmode off
	} elseif ((string)$Stunden == "4" or (string)$Stunden == "7")    {
		$soundbars = identSB($sonoszone, $folfilePlOn);
		foreach($soundbars as $subkey => $value)   {
			if (!file_exists("/run/shm/".$restore_file."_".$subkey.".json"))   {
				RestorePrevSBsettings($soundbars);
				DelFiles($mask);
				file_put_contents("/run/shm/".$restore_file."_".$subkey.".json",json_encode("1", JSON_PRETTY_PRINT));
			}
		}
	} else {
		echo "TV Monitor is not active (outside hours)".PHP_EOL;
	}
	#print_r($config);
	$time_end = microtime(true);
	$process_time = $time_end - $time_start;
	echo "Processing request tooks about ".round($process_time, 2)." seconds.".PHP_EOL;	

	# TV Modus values
		
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
		
		

/**
/* Function : DelFiles --> delete tmp files
/*
/* @param:  none
/* @return: none
**/

function DelFiles($mask)    {
	
	global $mask;
	
	array_map('unlink', glob("/run/shm/".$mask));
}



/**
/* Function : RestorePrevSBsettings --> Restore previous settings before TV Monitor starts
/*
/* @param:  none
/* @return: none
**/

function RestorePrevSBsettings($soundbars)    {
	
	global $status_file;
	
	startlog("TV Monitor", "tv_monitor");
	foreach($soundbars as $key => $value)   {
		$restorelevel = json_decode(file_get_contents("/run/shm/".$status_file."_".$key.".json"), true);
		$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
		$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
		$sonos->SetDialogLevel(is_enabled(json_encode($restorelevel['NightMode'])), 'NightMode');
		$sonos->SetDialogLevel($restorelevel['SubGain'], 'SubGain');
		echo "Previous Soundbar settings for '".$key."' has been restored.".PHP_EOL;
		LOGDEB("bin/tv_monitor.php: Previous Soundbar settings for '".$key."' has been restored");	
	}
}


/**
/* Function : PrepSaveZonesStati --> start Preparation for save zones
/*
/* @param:  none
/* @return: array saved details 
**/

function PrepSaveZonesStati() {
	
	global $sonoszone, $sonoszonen, $soundbars, $sonos, $player, $actual, $time_start, $log, $folfilePlOn;
	
	# identify if Soundbars are grouped
	foreach($soundbars as $zonen => $ip) {
		$sonos = new SonosAccess($soundbars[$zonen][0]); //Sonos IP Adresse
		$relzones = getGroup($zonen);
	}

	# Filter Player by grouped Players 
	if (!empty($relzones))    {
		$filtered = array();
			foreach($sonoszone as $zone => $ip) {
				$exist = in_array($zone, $relzones);
				if ($exist == true)  {
					$filtered[$zone] = $ip;
				}
			}
		$sonoszone = $filtered;
	} else {
		$sonoszone = $soundbars;
	}
	$actual = saveZonesStati($sonoszone);
	#print_r($actual);
	return $actual;
}


/**
/* Function : saveZonesStati --> saving of all needed info to restore later
/*
/* @param:  none
/* @return: none
**/

function saveZonesStati($sonoszone) {
	
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
	#print_r($actual);
	return $actual;
}


/**
* Function : restoreSingleZone --> restores previous Zone settings before a single message has been played 
*
* @param:  empty
* @return: previous settings
**/		

function restoreSingleZone($sonoszone, $master) {
	
	global $sonoszone, $sonos, $actual, $time_start, $log, $mode, $tts_stat;
	
	$sonos = new SonosAccess($sonoszone[$master][0]); 
	$restore = $actual;
	#print_r($restore);
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
* @param:  Player
* @return: restore
**/

function restore_details($zone) {
	global $sonoszone, $sonos, $master, $actual, $j, $browselist, $senderName, $log;

	# Playlist/Track
	$sonos = new SonosAccess($sonoszone[$zone][0]); 
	if ($actual[$zone]['Type'] == "Track")   {	
		if ($actual[$zone]['PositionInfo']['Track'] != "0")    {
			$sonos->SetQueue("x-rincon-queue:".$sonoszone[$zone][1]."#0");
			$sonos->SetTrack($actual[$zone]['PositionInfo']['Track']);
			$sonos->Seek("REL_TIME", $actual[$zone]['PositionInfo']['RelTime']);
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
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$zone][1]."#0");
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


function shutdown()
{
	global $logname;
	
	if ($logname == "TV Monitor")   {
		@LOGEND($logname);
	}
}

	



?>