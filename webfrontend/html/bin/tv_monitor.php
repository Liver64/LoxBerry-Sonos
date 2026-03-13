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

#startlog();

$configfile			= "s4lox_config.json";								// configuration file
$TV_safe_file		= "s4lox_TV_save";									// saved Values of all SB's
$status_file		= "s4lox_TV_on";									// TV has been turned on
#$status_file_run	= "s4lox_TV_on_run";								// TV is running
$restore_file		= "s4lox_restore";									// Settings restore file
$mask 				= 's4lox_TV*.*';									// mask for deletion
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";				// Folder and file name for Player Status
$statusNight 		= "s4lox_TV_night_on";								// Folder and file name for Night Modus

$Stunden 			= date("H:i");
$time_start 		= microtime(true);
#var_dump($Stunden);

global $soundbars, $grouping, $sonoszone;

echo "<PRE>";

	# Preparation
	
	# load Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
		$sonosgrpzone = [];
		foreach ($config['sonoszonen'] as $room => $data) {
			$sonosgrpzone[$room][0] = $data[0];  // IP
			$sonosgrpzone[$room][1] = $data[1];  // UUID
		}
		// Debug
		#echo "Sonoszone mapping:\n";
		#print_r($sonosgrpzone);
		
		# check if no TV Volume turned on
		if (is_disabled($config['VARIOUS']['tvmon']))   {
			echo "TV Monitor off".PHP_EOL;
			DelFiles($mask);
			exit(1);
		} else {
			echo "TV Monitor on".PHP_EOL;
			echo "<br>";
			$soundbars = identSB($config['sonoszonen'], $folfilePlOn);
			$GLOBALS['soundbars'];
		}
	} else {
		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
		exit;
	}
	# extract all Players and identify those were Online
	$sonoszonen = $config['sonoszonen'];
	$sonoszone = sonoszonen_on();
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
				if (file_exists("/run/shm/".$lbpplugindir."/".$restore_file."_".$key.".json"))   {
					unlink("/run/shm/".$lbpplugindir."/".$restore_file."_".$key.".json");
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
						if (file_exists("/run/shm/".$lbpplugindir."/".$TV_safe_file."_".$key.".json"))   {
							$actual = json_decode(file_get_contents("/run/shm/".$lbpplugindir."/".$TV_safe_file."_".$key.".json"), true);
							startlog();
							# Restore previous Zone settings
							restoreFromJson($actual);
							@array_map('unlink', glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$key.'*.*'));
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
						if ((bool)is_enabled($soundbars[$key][14]['tvsubnight']) === true ? $sub = "On" : $sub = "Off");
						$sublevel = $soundbars[$key][14]['tvmonnightsublevel'];
						# TV has been turned on
						if (!file_exists("/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json"))   {
							echo "TV Mode for Soundbar '".$key."' has been turned On".PHP_EOL;
							try {
								$sonos->BecomeCoordinatorOfStandaloneGroup();
								sleep(1);
								echo "Player '".$key."' been seperated".PHP_EOL;
							} catch (Exception $e) {
								echo "Player '".$key."' already been seperated".PHP_EOL;
							}								
							startlog();
							$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
							# Set Volume
							if ($soundbars[$key][14]['tvvol'] < 5)    {
								$sonos->SetVolume($soundbars[$key][4]);
								$vol = $soundbars[$key][4];
							} else {
								$sonos->SetVolume($soundbars[$key][14]['tvvol']);
								$vol = $soundbars[$key][14]['tvvol'];
							}
							if (!empty($soundbars[$key][14]['tvtreble']))    {
								$sonos->SetTreble($soundbars[$key][14]['tvtreble']);
								$treble = $soundbars[$key][14]['tvtreble'];
								echo "Treble for '".$key."' has been set to: ".$treble.PHP_EOL;
								LOGDEB("bin/tv_monitor.php: Treble for '".$key."' has been set to: ".$treble);
							}
							if (!empty($soundbars[$key][14]['tvbass']))    {
								$sonos->SetBass($soundbars[$key][14]['tvbass']);
								$bass = $soundbars[$key][14]['tvbass'];
								echo "Bass for '".$key."' has been set to: ".$bass.PHP_EOL;
								LOGDEB("bin/tv_monitor.php: Bass for '".$key."' has been set to: ".$bass);
							}
							if (!empty($soundbars[$key][14]['tvgrpstop']))    {
								processTvGroupStop($soundbars, $sonosgrpzone);
							}
							$sonos = new SonosAccess($soundbars[$key][0]); //Sonos IP Adresse
							$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
							try {
								$dialog['Volume'] = $vol;
								$dialog['Treble'] = $treble;
								$dialog['Bass'] = $bass;
								#var_dump($dialog);
								LOGDEB("bin/tv_monitor.php: Volume for '".$key."' has been set to: ".$vol);
								echo "Volume for '".$key."' has been set to: ".$vol.PHP_EOL;
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
								file_put_contents("/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json", json_encode($dialog, JSON_PRETTY_PRINT));
							} catch (Exception $e) {
								echo "Speech/Surround/Night Mode/Subwoofer could'nt been turned On for: ".$key."".PHP_EOL;
								LOGWARN("bin/tv_monitor.php: Speech/Surround/Night Mode/Subwoofer could'nt been turned On for: ".$key);
								@LOGEND($logname);	
							}
							#echo "Volume for '".$key."' has been set to: ".$vol.PHP_EOL;
							#echo "Treble for '".$key."' has been set to: ".$treble.PHP_EOL;
							#echo "Bass for '".$key."' has been set to: ".$bass.PHP_EOL;
							LOGDEB("bin/tv_monitor.php: Soundbar ".$key." is On and in TV Mode.");
						} else {
							#******************************************************
							# Soundbar is already running
							#******************************************************
							echo "TV Mode for Soundbar '".$key."' is already running.".PHP_EOL;
							if ($soundbars[$key][14]['fromtime'] != "false")    {
								# set Nightmode and Subgain
								if ((string)$Stunden >= (string)$soundbars[$key][14]['fromtime'])   { 
									if (!file_exists("/run/shm/".$lbpplugindir."/".$statusNight."_".$key.".json"))   {
										# Turn Night Mode On/Off
										startlog();										
										$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonnight']), 'NightMode');
										echo "NightMode for Soundbar ".$key." has been turned to ".$night."".PHP_EOL;
										LOGDEB("bin/tv_monitor.php: NightMode for Soundbar ".$key." has been turned to ".$night);
										# Set Sub Level
										@$sonos->SetDialogLevel($soundbars[$key][14]['tvmonnightsublevel'], 'SubGain');
										echo "Subwoofer Level for Soundbar ".$key." has been set to: ".$sublevel."".PHP_EOL;
										LOGDEB("bin/tv_monitor.php: Subwoofer Level for Soundbar ".$key." has been set to: ".$sublevel);
										@$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvsubnight']), 'SubEnable');
										echo "Subwoofer for Soundbar ".$key." has been turned to ".$night." for night".PHP_EOL;
										LOGDEB("bin/tv_monitor.php: Subwoofer for Soundbar ".$key." has been turned to ".$night." for night");
										file_put_contents("/run/shm/".$lbpplugindir."/".$statusNight."_".$key.".json",json_encode("1", JSON_PRETTY_PRINT));
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
						@file_put_contents("/run/shm/".$lbpplugindir."/".$TV_safe_file."_".$key.".json",json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));
						if ($state == 1)   {	
							echo "...and streaming".PHP_EOL;
						} else {
							echo "...but paused or stopped".PHP_EOL;
						}
					}
					echo "Current incoming value for ".$key." at HDMI/SPDIF: ".$tvmodi['HTAudioIn'].PHP_EOL;
				} catch (Exception $e) {
					echo "Soundbar '".$key."' has not responded , maybe Soundbar is offline, we skip here...".PHP_EOL;
					#$logname = startlog();
					#LOGINF("bin/tv_monitor.php: Soundbar '".$key."' has not responded , maybe Soundbar is offline, we skip here...");
				}
			#********************************************
			# If Soundbar is turned Off in Plugin
			#********************************************
			} else {
				#DelFiles($mask);
				@array_map('unlink', glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$key.'*.*'));				
				echo "TV Monitor for Soundbar '".$key."' is turned off in Plugin Config".PHP_EOL;
			}
		}
	#********************************************************
	# restore previous soundbar settings 
	#********************************************************
	# turn nightmode off
	} elseif ((string)$Stunden == "04:00" or (string)$Stunden == "07:00")    {
		$soundbars = identSB($sonoszone, $folfilePlOn);
		foreach($soundbars as $subkey => $value)   {
			if (!file_exists("/run/shm/".$lbpplugindir."/".$restore_file."_".$subkey.".json"))   {
				RestorePrevSBsettings($soundbars);
				DelFiles($mask);
				file_put_contents("/run/shm/".$lbpplugindir."/".$restore_file."_".$subkey.".json",json_encode("1", JSON_PRETTY_PRINT));
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
	
	global $mask,$lbpplugindir;
	
	array_map('unlink', glob("/run/shm/".$lbpplugindir."/".$mask));
}



/**
/* Function : RestorePrevSBsettings --> Restore previous settings before TV Monitor starts
/*
/* @param:  none
/* @return: none
**/

function RestorePrevSBsettings($soundbars)    {
	
	global $status_file, $logname, $lbpplugindir;
	
	startlog();
	foreach($soundbars as $key => $value)   {
		$restorelevel = json_decode(file_get_contents("/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json"), true);
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
	
	global $sonoszone, $soundbars, $sonos, $player, $actual, $time_start, $log, $folfilePlOn;
	
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
		$actual[$player]['Bass'] = $sonos->GetBass($player);
		$actual[$player]['Treble'] = $sonos->GetTreble($player);
		$actual[$player]['MediaInfo'] = $sonos->GetMediaInfo($player);
		$actual[$player]['PositionInfo'] = $sonos->GetPositionInfo($player);
		$actual[$player]['TransportInfo'] = $sonos->GetTransportInfo($player);
		$actual[$player]['TransportSettings'] = $sonos->GetTransportSettings($player);
		$actual[$player]['Group-ID'] = $sonos->GetZoneGroupAttributes($player);
		$actual[$player]['Grouping'] = getGroup($player);
		$actual[$player]['ZoneStatus'] = getZoneStatus($player);
		#$actual[$player]['CONNECT'] = GetVolumeModeConnect($player);
		$posinfo = $actual[$player]['PositionInfo'];
		$media = $actual[$player]['MediaInfo'];
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

function restoreFromJson($actual)
{
    global $sonoszone;

    LOGDEB("bin/tv_monitor.php: Starting full Sonos restore from JSON.");
    if (empty($actual)) {
        LOGWARN("bin/tv_monitor.php: Restore aborted - JSON empty.");
        return;
    }

    /*
        MASTER ERMITTELN
    */
    $firstZone = array_key_first($actual);
    if (!empty($actual[$firstZone]['Grouping'])) {
        $master = $actual[$firstZone]['Grouping'][0];
    } else {
        $master = $firstZone;
    }
    LOGDEB("bin/tv_monitor.php: Master zone detected: ".$master);

    /*
        GRUPPE WIEDERHERSTELLEN
    */

    $group = $actual[$master]['Grouping'] ?? [];
    if (count($group) > 1) {
        foreach ($group as $member) {
            if ($member == $master) {
                continue;
            }
            try {
                $sonos = new SonosAccess($sonoszone[$member][0]);
                $sonos->SetAVTransportURI("x-rincon:" . $sonoszone[$master][1]);
                LOGDEB("bin/tv_monitor.php: '".$member."' joined '".$master."'");
            } catch (Exception $e) {
                LOGWARN("bin/tv_monitor.php: Group join failed for ".$member);
            }
        }
    }

    /*
        QUELLE WIEDERHERSTELLEN
    */

    try {

        $sonos = new SonosAccess($sonoszone[$master][0]);
        $uri  = $actual[$master]['MediaInfo']['CurrentURI'] ?? "";
        $meta = $actual[$master]['MediaInfo']['CurrentURIMetaData'] ?? "";
        if (!empty($meta)) {
            $meta = htmlspecialchars_decode($meta);
        }
        if (!empty($uri)) {
            $sonos->SetAVTransportURI($uri, $meta);
            LOGDEB("bin/tv_monitor.php: Source restored on '".$master."'");
        }
    } catch (Exception $e) {
        LOGWARN("bin/tv_monitor.php: Source restore failed on ".$master);
    }

    /*
        AUDIO SETTINGS
    */

    foreach ($actual as $zone => $data) {
        try {
            $sonos = new SonosAccess($sonoszone[$zone][0]);
            if (isset($data['Volume']))
                $sonos->SetVolume($data['Volume']);
            if (isset($data['Bass']))
                $sonos->SetBass($data['Bass']);
            if (isset($data['Treble']))
                $sonos->SetTreble($data['Treble']);
            if (isset($data['Mute']))
                $sonos->SetMute($data['Mute']);
            LOGDEB("bin/tv_monitor.php: Audio settings restored for '".$zone."'");
        } catch (Exception $e) {
            LOGWARN("bin/tv_monitor.php: Audio restore failed for ".$zone);
        }
    }
	
	$posinfo = $actual[$master]['PositionInfo'] ?? [];
	if (!empty($posinfo['RelTime']) && $posinfo['RelTime'] != "0:00:00") {
		try {
			$reltime = $posinfo['RelTime'];
			$sonos = new SonosAccess($sonoszone[$master][0]);
			$sonos->Seek("REL_TIME", $reltime);
			LOGDEB("bin/tv_monitor.php: Seek restored to ".$reltime." on '".$master."'");
		} catch (Exception $e) {
			LOGWARN("bin/tv_monitor.php: Seek restore failed on ".$master);
		}
    }

    /*
        PLAY STATUS
    */

    if (!empty($actual[$master]['TransportInfo'])) {
        try {
            $sonos = new SonosAccess($sonoszone[$master][0]);
            if ($actual[$master]['TransportInfo'] == 1) {
                $sonos->Play();
                LOGDEB("bin/tv_monitor.php: Playback restarted on '".$master."'");
            } else {
                $sonos->Pause();
                LOGDEB("bin/tv_monitor.php: Playback paused on '".$master."'");
            }
        } catch (Exception $e) {
            LOGWARN("bin/tv_monitor.php: Playback restore failed on ".$master);
        }
    }
    LOGDEB("bin/tv_monitor.php: Restore finished.");
}


/**
* Function : processTvGroupStop --> stop grouped players 
*
* @param: array @soundbars, array @sonoszone
* @return: static
**/

function processTvGroupStop(array $soundbars, array $sonoszone) {

    foreach ($soundbars as $sbRoom => $sbData) {

        $tvgrpstop = $sbData[14]['tvgrpstop'] ?? [];
        if (empty($tvgrpstop)) {
            continue; // keine Räume zum Stoppen
        }
        echo "Processing Soundbar: $sbRoom\n";
        foreach ($tvgrpstop as $stopRoom) {
            // Prüfen, ob IP/UUID existiert
            if (empty($sonoszone[$stopRoom][0])) {
                echo "ERROR: No Sonos IP/UUID for room '$stopRoom'\n";
				LOGERR("bin/tv_monitor.php: ERROR: No Sonos IP/UUID for room '$stopRoom'");
                continue;
            }
            echo "Processing stop room: $stopRoom\n";
            try {
                $sonos = new SonosAccess($sonoszone[$stopRoom][0]);
                // Status abfragen
                $status = getZoneStatus($stopRoom);
                #echo "Room '$stopRoom' status: $status\n";
                // Aktion je nach Status
                if ($status === 'member' || $status === 'master') {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGDEB("bin/tv_monitor.php: '$stopRoom' is leaving group");
					echo "'$stopRoom' is leaving group\n";
                    sleep(1);
					if ($sonos->GetTransportInfo() === 1) {
						$sonos->Pause();
						echo "Pausing room '$stopRoom'\n";
						LOGDEB("bin/tv_monitor.php: Pausing room '$stopRoom'");
					}
                } elseif ($status === 'single') {
					if ($sonos->GetTransportInfo() === 1) {
						$sonos->Pause();
						echo "Pausing single room '$stopRoom'\n";
						LOGDEB("bin/tv_monitor.php: Pausing single room '$stopRoom'");
					}
                } else {
                    echo "No action for room '$stopRoom'\n";
                }
            } catch (Exception $e) {
				LOGERR("bin/tv_monitor.php: ERROR processing room '$stopRoom': " . $e->getMessage());
                echo "ERROR processing room '$stopRoom': " . $e->getMessage() . "\n";
            }
        }
    }
}

/**
/* Funktion : startlog --> startet logging
/*
/* @param: Name of Log, filename of Log                        
/* @return: 
**/

function startlog()
{
    require_once "loxberry_log.php";
    global $lbplogdir, $lbpplugindir, $log;

    $params = [
        "name"     => "TV Monitor",
        "package"  => $lbpplugindir,                // WICHTIG: package setzen
        "filename" => $lbplogdir . "/tv_monitor.log", // WICHTIG: echten Pfad nutzen
        "append"   => 1,
        "addtime"  => 1,
        "loglevel" => 7,
    ];

    $log = LBLog::newLog($params);

    // Falls newLog fehlschlägt, nicht weiter loggen
    if (empty($log)) {
        echo "ERROR: Could not initialize LoxBerry log.\n";
        return;
    }

    $GLOBALS['TVMON_LOG_STARTED'] = true;
    $log->LOGSTART("TV Monitor");   // Methode benutzen, nicht globales LOGSTART()
}

function shutdown()
{
    require_once "loxberry_log.php";
    global $log;

    if (!empty($GLOBALS['TVMON_LOG_STARTED']) && !empty($log)) {
        $log->LOGEND("TV Monitor"); // Methode benutzen, nicht globales LOGEND()
    }
}

?>