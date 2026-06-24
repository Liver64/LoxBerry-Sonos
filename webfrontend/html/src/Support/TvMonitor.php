<?php
/**
 * Sonos4Lox TV Monitor support class
 * Version: TV_MONITOR_ENDTIME_RESTORE_FIX_V21_2026_06_17
 *
 * Changes in V21:
 * - After the configured monitor end time, restore must not run just because the
 *   time window has ended. The monitor now waits until the TV/HDMI input is
 *   clearly off before restoring saved soundbar settings.
 * - HTAudioIn values greater than zero, including 21, are treated as still active
 *   or not safely off in the outside-window restore path. This prevents unwanted
 *   restore while the TV is still on.
 *
 * Changes in V19/V20:
 * - Remove the restore-pending marker workflow. Outside the monitor time window,
 *   restore is executed only when TV/HDMI input is already off. If TV is still on,
 *   the monitor waits silently and does not create s4lox_restore_pending_*.json.
 * - Prevent stale delayed restores after the next morning/start time. A TV session
 *   marker from a previous monitor window is cleaned up without restoring or playing.
 * - Do not create/update s4lox_TV_save_<room>.json while HTAudioIn/output is below 21.
 * - TV-on handling always runs for HTAudioIn > 21, independent of queue/stream state.
 *   A pre-TV source snapshot is only written when active restorable playback exists.
 * - Only active Stream/Radio input (21) with TransportInfo == 1 may update the
 *   pre-TV restore snapshot.
 *
 * Changes in V17/V18:
 * - Outside the monitor time window, restore soundbar settings only.
 * - Do not restore source/stream/group context after monitor end time because SetAVTransportURI()
 *   can auto-start playback on some Sonos sources even when Play() is not called.
 * - Keep V16 behavior: preserve existing TV monitor save files while idle and only update them
 *   when a restorable music/radio/queue state exists.
 *
 * Changes in V16:
 * - Keep the current cron/include cleanup baseline.
 * - Keep existing TV monitor save files when the current player state is idle/empty.
 * - Create/update TV monitor save files only when a restorable music/radio/queue state exists.
 * - Skip source/playback restore for empty/non-restorable save files, but still restore soundbar settings.
 *
 * Changes in V15:
 * - Keep the current cron/include cleanup baseline.
 * - Do not create TV monitor restore files when the player state is idle/empty.
 * - Removed in V16: deleting existing save files when no restorable playback state exists.
 *
 * Previous V12 changes:
 * - Add a no-play restore mode to restoreFromJson(). In this mode the source may be loaded
 *   and the seek position may be restored, but playback is explicitly left paused.
 * - Keep the soundbar settings restore from s4lox_TV_on_<room>.json unchanged.
 *
 * V17 deliberately does not use the source/context restore path after monitor end time.
 *
 * Cron-only TV monitor implementation. The old bin/tv_monitor.php wrapper is obsolete.
 */

require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';
include_once $lbphtmldir . '/src/Core/Sonos/sonosAccess.php';
include_once $lbphtmldir . '/Grouping.php';
include_once $lbphtmldir . '/Speaker.php';
include_once $lbphtmldir . '/Helper.php';
include_once $lbphtmldir . '/Info.php';

class S4L_TvMonitor
{
    const FILE_CONTEXT = 'src/Support/TvMonitor.php';

    /**
     * Configure Sonos TV autoplay settings for all detected soundbars.
     *
     * This replaces the old standalone bin/tv_monitor_conf.php logic, which is now obsolete.
     * It is intentionally conservative and keeps the original behavior:
     * - load s4lox_config.json
     * - detect soundbars via identSB()
     * - if TV monitor is enabled, set AutoplayRoomUUID and disable linked zones
     */
    public static function configureAutoplay($configFile = 's4lox_config.json')
    {
        global $lbpconfigdir, $lbpdatadir;

        $configPath = $lbpconfigdir . '/' . $configFile;
        $folfilePlOn = $lbpdatadir . '/PlayerStatus/s4lox_on_';

        self::startlog();

        if (!file_exists($configPath)) {
            echo self::FILE_CONTEXT . ": configuration file not found: " . $configPath . PHP_EOL;
            return false;
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!is_array($config)) {
            echo self::FILE_CONTEXT . ": configuration file could not be decoded: " . $configPath . PHP_EOL;
            return false;
        }

        if (empty($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
            echo self::FILE_CONTEXT . ": no Sonos zones found in configuration." . PHP_EOL;
            return false;
        }

        $soundbars = identSB($config['sonoszonen'], $folfilePlOn);
        if (empty($soundbars) || !is_array($soundbars)) {
            echo self::FILE_CONTEXT . ": no soundbars detected for TV monitor configuration." . PHP_EOL;
            return true;
        }

        $tvMonitorEnabled = !empty($config['VARIOUS']['tvmon']);
        foreach ($soundbars as $room => $soundbar) {
            if (empty($soundbar[0]) || empty($soundbar[1])) {
                echo self::FILE_CONTEXT . ": skipped soundbar '" . $room . "' because IP or UUID is missing." . PHP_EOL;
                continue;
            }

            if (!$tvMonitorEnabled) {
                echo self::FILE_CONTEXT . ": TV monitor is disabled, skipped autoplay configuration for '" . $room . "'." . PHP_EOL;
                continue;
            }

            try {
                $sonos = new SonosAccess($soundbar[0]);
                $sonos->SetAutoplayRoomUUID($soundbar[1], 'TV');
                $sonos->SetAutoplayLinkedZones('false', 'TV');
                echo self::FILE_CONTEXT . ": TV autoplay configuration updated for soundbar '" . $room . "'." . PHP_EOL;
            } catch (Exception $e) {
                echo self::FILE_CONTEXT . ": TV autoplay configuration failed for soundbar '" . $room . "': " . $e->getMessage() . PHP_EOL;
            }
        }

        return true;
    }

    /**
     * Run the TV monitor cron task.
     */
    public static function run()
    {
        ini_set('max_execution_time', 30);
        register_shutdown_function(array(__CLASS__, 'shutdown'));

        global $lbpdatadir;

        $configfile			= "s4lox_config.json";								// configuration file
        $TV_safe_file		= "s4lox_TV_save";									// saved Values of all SB's
        $status_file		= "s4lox_TV_on";									// TV has been turned on
        $restore_file		= "s4lox_restore";									// Settings restore file
        $mask 				= 's4lox_TV*.*';									// mask for deletion
        $folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";				// Folder and file name for Player Status
        $statusNight 		= "s4lox_TV_night_on";								// Folder and file name for night mode

        $Stunden 			= date("H:i");
        // Debugging only
        #$Stunden 			= "07:00";
        $time_start 		= microtime(true);

        global $soundbars, $grouping, $sonoszone;

        // Keep legacy globals available for older helper functions used by the TV monitor.
        foreach (array(
            'configfile',
            'TV_safe_file',
            'status_file',
            'restore_file',
            'mask',
            'folfilePlOn',
            'statusNight',
            'Stunden',
            'time_start',
        ) as $legacyName) {
            $GLOBALS[$legacyName] = ${$legacyName};
        }


        echo "<PRE>";

        // Preparation & Load Configuration
        $config = self::loadConfiguration($configfile);
        $GLOBALS['config'] = $config;

        $soundbars = identSB($config['sonoszonen'], $folfilePlOn);
        $GLOBALS['soundbars'] = $soundbars;

        // V19: remove legacy restore-pending markers from older test versions.
        // They must never trigger a delayed restore after the next monitor start time.
        self::cleanupLegacyPendingRestoreMarkers($restore_file);

        // extract all players and identify those which are online
        $sonoszonen = $config['sonoszonen'];
        $GLOBALS['sonoszonen'] = $sonoszonen;

        $sonoszone = sonoszonen_on();
        $GLOBALS['sonoszone'] = $sonoszone;
        #print_r($config);

        // ********************************************************
        // Check whether the current time is inside the configured monitor window
        // ********************************************************
        if (self::isWithinTimeWindow($Stunden, $config['VARIOUS']['starttime'], $config['VARIOUS']['endtime'])) {
        	// Start script - Process each soundbar
        	foreach($soundbars as $key => $value) {
        		self::processSoundbar($key, $soundbars, $sonoszonen, $TV_safe_file, $status_file, $statusNight, $Stunden, $restore_file);
        	}
        // ********************************************************
        // restore previous soundbar settings 
        // ********************************************************
        } else {
        	self::restoreSoundbarSettings($soundbars, $restore_file);
        }

        #print_r($config);
        $time_end = microtime(true);
        $process_time = $time_end - $time_start;
        echo "Processing request tooks about ".round($process_time, 2)." seconds.".PHP_EOL;	

        # TV mode values

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
    }

    private static function loadConfiguration($configfile) {

    	global $lbpconfigdir, $folfilePlOn, $mask;

    	if (!file_exists($lbpconfigdir . "/" . $configfile)) {
    		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
    		exit;
    	}

    	$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
    	$GLOBALS['CONFIG'] = $config;

    	// check if no TV Volume turned on
    	if (is_disabled($config['VARIOUS']['tvmon'])) {
    		echo "TV Monitor off".PHP_EOL;
    		self::DelFiles($mask);
    		exit(1);
    	} else {
    		// No-op runs must stay silent in the LoxBerry log. Actual changes start logging explicitly.
    	}
    	return $config;
    }

    /**
     * Handles a soundbar after TV input has been turned off
     */
    private static function processSoundbarTVOff($key, $TV_safe_file) {

    	global $lbpplugindir, $restore_file, $status_file, $soundbars, $config;

    	echo "Soundbar TV Mode for ".$key." has been turned off".PHP_EOL;

    	$savefile     = "/run/shm/".$lbpplugindir."/".$TV_safe_file."_".$key.".json";
    	$restoremark  = "/run/shm/".$lbpplugindir."/".$restore_file."_".$key.".json";
    	$statusfile   = "/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json";

    	// Safety guard:
    	// A full restore may restart streams, rebuild groups and call Play().
    	// This is allowed only while the configured TV monitor window is active.
    	// Outside the window, TV-off cleanup must restore soundbar settings only.
    	$monitorActiveNow = true;
    	$currentWindowStartTs = null;
    	if (isset($config['VARIOUS']['starttime']) && isset($config['VARIOUS']['endtime'])) {
    		$monitorActiveNow = self::isWithinTimeWindow(date("H:i"), $config['VARIOUS']['starttime'], $config['VARIOUS']['endtime']);
    		$currentWindowStartTs = self::currentWindowStartTimestamp($config['VARIOUS']['starttime'], $config['VARIOUS']['endtime']);
    	}

    	if ($monitorActiveNow && $currentWindowStartTs !== null && file_exists($statusfile)) {
    		$statusMtime = @filemtime($statusfile);
    		if ($statusMtime !== false && $statusMtime < $currentWindowStartTs) {
    			// A TV marker from a previous monitor window must not trigger a delayed
    			// morning restore. Once the new active window has started, the old TV
    			// session is abandoned silently except for this one cleanup log line.
    			self::startlog();
    			@array_map('unlink', glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$key.'*.*'));
    			LOGDEB("src/Support/TvMonitor.php: Stale TV monitor session for '".$key."' was discarded after the new monitor start time. No restore and no playback was executed.");
    			return;
    		}
    	}

    	if (!$monitorActiveNow) {
    		self::startlog();
    		LOGDEB("src/Support/TvMonitor.php: TV Mode for Soundbar '".$key."' ended outside monitor time window; restoring settings only and skipping source/playback restore.");

    		$ip = isset($soundbars[$key][0]) ? $soundbars[$key][0] : '';
    		if ($ip !== '') {
    			$restored = self::restoreSoundbarSettingsFromJson($key, $ip, $lbpplugindir, $status_file);
    			if ($restored) {
    				@array_map('unlink', glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$key.'*.*'));
    				file_put_contents($restoremark, json_encode("1", JSON_PRETTY_PRINT));
    				LOGDEB("src/Support/TvMonitor.php: Settings-only restore completed for '".$key."'. No stream, source, group, seek position or playback state was restored.");
    			} else {
    				LOGWARN("src/Support/TvMonitor.php: Settings-only restore failed for '".$key."' after TV-off outside monitor time window.");
    			}
    		} else {
    			LOGWARN("src/Support/TvMonitor.php: Settings-only restore skipped for '".$key."' because no soundbar IP was available.");
    		}

    		return;
    	}

    	if (file_exists($savefile)) {
    		$actual = json_decode(file_get_contents($savefile), true);
    		self::startlog();

    		// Restore previous Zone settings only if the saved state contains
    		// an actual music/radio/stream/queue source. Older monitor versions
    		// may have created empty save files while the player was idle. In that
    		// case only the soundbar TV settings must be restored.
    		if (self::hasRestorableSavedState($actual)) {
    			// This full restore is intentionally only reachable inside active monitor time.
    			self::restoreFromJson($actual);
    			LOGDEB("src/Support/TvMonitor.php: Soundbar TV Mode for ".$key." has been turned off and previous source/settings have been restored.");
    		} else {
    			$ip = isset($soundbars[$key][0]) ? $soundbars[$key][0] : '';
    			if ($ip !== '') {
    				self::restoreSoundbarSettingsFromJson($key, $ip, $lbpplugindir, $status_file);
    				LOGDEB("src/Support/TvMonitor.php: Soundbar TV Mode for ".$key." has been turned off; saved source state was empty, restored soundbar settings only.");
    			} else {
    				LOGWARN("src/Support/TvMonitor.php: Soundbar TV Mode for ".$key." has been turned off but settings-only restore was skipped because no soundbar IP was available.");
    			}
    		}

    		@array_map('unlink', glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$key.'*.*'));
    	}

    }

    /**
     * Handles the first TV-on detection for a soundbar
     */
    private static function processSoundbarTVFirstOn($key, $soundbars, $sonoszonen, $status_file) {

    	global $lbpplugindir,$sonos,$state;

    	echo "TV Mode for Soundbar '".$key."' has been turned On".PHP_EOL;

    	$sonos = new SonosAccess($soundbars[$key][0]); // Sonos IP address

    	try {
    		$sonos->BecomeCoordinatorOfStandaloneGroup();
    		sleep(1);
    		echo "Player '".$key."' been seperated".PHP_EOL;
    	} catch (Exception $e) {
    		echo "Player '".$key."' already been seperated".PHP_EOL;
    	}

    	self::startlog();
    	$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");

    	try {
    		$dialog = Getdialoglevel();
    		$dialog['Volume'] = $sonos->GetVolume();
    		$dialog['Treble'] = $sonos->GetTreble();
    		$dialog['Bass'] = $sonos->GetBass();
    		#print_r($dialog);

    		// Save Original settings
    		file_put_contents("/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json", json_encode($dialog, JSON_PRETTY_PRINT));
    	} catch (Exception $e) {
    		echo "DialogLevel could not be obtained, nore file has been saved".PHP_EOL;
    		LOGWARN("src/Support/TvMonitor.php: DialogLevel could not be obtained, nore file has been saved");
    		@LOGEND($logname);	
    	}

    	$sonos->SetVolume($soundbars[$key][14]['tvvol']);
    	LOGDEB("src/Support/TvMonitor.php: Volume for '".$key."' has been set to: ".$soundbars[$key][14]['tvvol']);
    	echo "Volume for '".$key."' has been set to: ".$soundbars[$key][14]['tvvol'].PHP_EOL;

    	$treble = null;
    	$bass = null;
    	$tvsublevel = null;
    	$tvsurrlevel = null;

    	if (isset($soundbars[$key][14]['tvtreble']) && $soundbars[$key][14]['tvtreble'] !== "") {
    		$sonos->SetTreble((int)$soundbars[$key][14]['tvtreble']);
    		$treble = (int)$soundbars[$key][14]['tvtreble'];
    		echo "Treble for '".$key."' has been set to: ".$treble.PHP_EOL;
    		LOGDEB("src/Support/TvMonitor.php: Treble for '".$key."' has been set to: ".$treble);
    	}

    	if (isset($soundbars[$key][14]['tvbass']) && $soundbars[$key][14]['tvbass'] !== "") {
    		$sonos->SetBass((int)$soundbars[$key][14]['tvbass']);
    		$bass = (int)$soundbars[$key][14]['tvbass'];
    		echo "Bass for '".$key."' has been set to: ".$bass.PHP_EOL;
    		LOGDEB("src/Support/TvMonitor.php: Bass for '".$key."' has been set to: ".$bass);
    	}

    	if (!empty($soundbars[$key][14]['tvgrpstop'])) {
    		self::processTvGroupStop($soundbars, $sonoszonen);
    	}

    	$sonos = new SonosAccess($soundbars[$key][0]); // Sonos IP address
    	$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");

    	try {
    		// Turn Speech/Surround/Dialog Mode On and Mute Off
    		$dia = is_enabled($soundbars[$key][14]['tvmonspeech']) ? "On" : "Off";
    		$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonspeech']), 'DialogLevel');
    		echo "Speech Mode for Soundbar ".$key." has been turned ".$dia."".PHP_EOL;
    		LOGDEB("src/Support/TvMonitor.php: Speech Mode for Soundbar ".$key." has been turned ".$dia."");

    		$sur = is_enabled($soundbars[$key][14]['tvmonsurr']) ? "On" : "Off";
    		$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonsurr']), 'SurroundEnable');
    		echo "Surround for Soundbar ".$key." has been turned ".$sur."".PHP_EOL;
    		LOGDEB("src/Support/TvMonitor.php: Surround for Soundbar ".$key." has been turned ".$sur);

    		if ($sur == "On")   {
    			if (isset($soundbars[$key][14]['tvsurrlevel']) && $soundbars[$key][14]['tvsurrlevel'] !== "") {
    				$sonos->SetDialogLevel((int)$soundbars[$key][14]['tvsurrlevel'], 'SurroundLevel');
    				$tvsurrlevel = (int)$soundbars[$key][14]['tvsurrlevel'];
    				echo "Surround Level for '".$key."' has been set to: ".$tvsurrlevel.PHP_EOL;
    				LOGDEB("src/Support/TvMonitor.php: Surround Level for '".$key."' has been set to: ".$tvsurrlevel);
    			}
    		}

    		$sub = is_enabled($soundbars[$key][14]['tvmonnightsub']) ? "On" : "Off";
    		$sonos->SetDialogLevel(is_enabled($soundbars[$key][14]['tvmonnightsub']), 'SubEnable');
    		echo "Subwoofer for Soundbar ".$key." has been turned ".$sub."".PHP_EOL;
    		LOGDEB("src/Support/TvMonitor.php: Subwoofer for Soundbar ".$key." has been turned ".$sub);

    		if ($sub == "On")   {
    			if (isset($soundbars[$key][14]['tvsublevel']) && $soundbars[$key][14]['tvsublevel'] !== "") {
    				@$sonos->SetDialogLevel((int)$soundbars[$key][14]['tvsublevel'], 'SubGain');
    				$tvsublevel = (int)$soundbars[$key][14]['tvsublevel'];
    				echo "Subwoofer Level for '".$key."' has been set to: ".$tvsublevel.PHP_EOL;
    				LOGDEB("src/Support/TvMonitor.php: Subwoofer Level for '".$key."' has been set to: ".$tvsublevel);
    			}
    		}

    		@$sonos->SetMute(false);

    	} catch (Exception $e) {
    		echo "Speech/Surround/Night Mode/Subwoofer could'nt been turned On for: ".$key."".PHP_EOL;
    		LOGWARN("src/Support/TvMonitor.php: Speech/Surround/Night Mode/Subwoofer could'nt been turned On for: ".$key);
    		@LOGEND($logname);	
    	}
    	LOGDEB("src/Support/TvMonitor.php: Soundbar ".$key." is On and in TV Mode.");
    }

    /**
     * Handles an active TV session, including night mode settings
     */
    private static function processSoundbarTVRunning($key, $soundbars, $Stunden, $statusNight) {

    	global $lbpplugindir,$state;

    	echo "TV Mode for Soundbar '".$key."' is already running.".PHP_EOL;

    	$settings = isset($soundbars[$key][14]) && is_array($soundbars[$key][14]) ? $soundbars[$key][14] : array();
    	$nightMarker = "/run/shm/".$lbpplugindir."/".$statusNight."_".$key.".json";

    	// Night mode must only run when an explicit fromtime is configured.
    	// Empty values or false disable the night branch completely.
    	$fromTime = isset($settings['fromtime']) ? trim((string)$settings['fromtime']) : '';
    	if (!self::hasValidNightStartTime($fromTime)) {
    		// Remove stale marker files created by older versions when no night schedule is configured.
    		if (file_exists($nightMarker)) {
    			@unlink($nightMarker);
    		}
    		echo "Soundbar ".$key." is On and in TV Mode, night mode is not configured.".PHP_EOL;
    		return;
    	}

    	// If no night option is enabled, do not create a night marker and do not call Sonos.
    	if (!self::hasNightActionEnabled($settings)) {
    		if (file_exists($nightMarker)) {
    			@unlink($nightMarker);
    		}
    		echo "Soundbar ".$key." is On and in TV Mode, no night action is enabled.".PHP_EOL;
    		return;
    	}

    	// Set night mode and night subwoofer values only once per TV session.
    	if (self::isAtOrAfterNightStart($Stunden, $fromTime)) {
    		if (!file_exists($nightMarker)) {
    			$sonos = new SonosAccess($soundbars[$key][0]);

    			self::startlog();

    			if (array_key_exists('tvmonnight', $settings)) {
    				$night = is_enabled($settings['tvmonnight']) ? "On" : "Off";
    				$sonos->SetDialogLevel(is_enabled($settings['tvmonnight']), 'NightMode');
    				echo "Night Mode for Soundbar ".$key." has been turned to ".$night."".PHP_EOL;
    				LOGDEB("src/Support/TvMonitor.php: NightMode for Soundbar ".$key." has been turned to ".$night);
    			}

    			if (array_key_exists('tvsubnight', $settings)) {
    				$subnight = is_enabled($settings['tvsubnight']) ? "On" : "Off";
    				$sonos->SetDialogLevel(is_enabled($settings['tvsubnight']), 'SubEnable');
    				echo "Subwoofer for Soundbar ".$key." has been turned to ".$subnight."".PHP_EOL;
    				LOGDEB("src/Support/TvMonitor.php: Subwoofer for Soundbar ".$key." has been turned to ".$subnight);

    				if ($subnight == "On" && isset($settings['tvmonnightsublevel']) && $settings['tvmonnightsublevel'] !== "") {
    					$sublevel = (int)$settings['tvmonnightsublevel'];
    					$sonos->SetDialogLevel($sublevel, 'SubGain');
    					echo "Subwoofer Level for Soundbar ".$key." has been set to: ".$sublevel." for night".PHP_EOL;
    					LOGDEB("src/Support/TvMonitor.php: Night Subwoofer Level for Soundbar ".$key." has been set to: ".$sublevel." for night");
    				}
    			}

    			file_put_contents($nightMarker, json_encode("1", JSON_PRETTY_PRINT));
    		}
    	}

    	echo "Soundbar ".$key." is On and in TV Mode, all settings have been set previously".PHP_EOL;
    }

    /**
     * Handles music/radio/queue mode before TV starts
     */
    private static function processSoundbarMusic($key, $state, $TV_safe_file) {

    	global $lbpplugindir,$state;

    	echo "Music on ".$key." is loaded...".PHP_EOL;
    	$actual = self::PrepSaveZonesStati();
    	@file_put_contents("/run/shm/".$lbpplugindir."/".$TV_safe_file."_".$key.".json",json_encode($actual, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));

    	if ($state == 1) {	
    		echo "...and streaming".PHP_EOL;
    	} else {
    		echo "...but paused or stopped".PHP_EOL;
    	}
    }

    private static function hasRestorablePlaybackState($posinfo, $mediainfo)
    {
    	$posinfo = is_array($posinfo) ? $posinfo : array();
    	$mediainfo = is_array($mediainfo) ? $mediainfo : array();

    	$nrTracks = isset($mediainfo['NrTracks']) ? (int)$mediainfo['NrTracks'] : 0;
    	if ($nrTracks > 0) {
    		return true;
    	}

    	$fields = array(
    		isset($posinfo['TrackURI']) ? $posinfo['TrackURI'] : '',
    		isset($posinfo['TrackMetaData']) ? $posinfo['TrackMetaData'] : '',
    		isset($posinfo['CurrentURIMetaData']) ? $posinfo['CurrentURIMetaData'] : '',
    		isset($mediainfo['CurrentURI']) ? $mediainfo['CurrentURI'] : '',
    		isset($mediainfo['CurrentURIMetaData']) ? $mediainfo['CurrentURIMetaData'] : ''
    	);

    	foreach ($fields as $field) {
    		if (trim((string)$field) !== '') {
    			return true;
    		}
    	}

    	return false;
    }


    /**
     * Decide whether the currently visible state may be stored as the pre-TV
     * source snapshot. This is intentionally stricter than hasRestorablePlaybackState().
     *
     * TV-on handling itself must never depend on this result. The TV monitor must
     * still apply TV settings when HTAudioIn indicates TV input, even if the queue
     * is empty. This helper only controls whether s4lox_TV_save_<room>.json may
     * be written/updated.
     */
    private static function shouldSavePreTvSource($htAudioIn, $transportState, $posinfo, $mediainfo)
    {
    	$htAudioIn = (int)$htAudioIn;
    	$transportState = (int)$transportState;
    	$posinfo = is_array($posinfo) ? $posinfo : array();
    	$trackUri = isset($posinfo['TrackURI']) ? trim((string)$posinfo['TrackURI']) : '';

    	// Only active playback may update the pre-TV snapshot.
    	// Stopped/paused queue leftovers must not overwrite the real previous state.
    	if ($transportState !== 1) {
    		return false;
    	}

    	// Never store the TV input itself as a restore source.
    	if (substr($trackUri, 0, 17) === 'x-sonos-htastream') {
    		return false;
    	}

    	return self::hasRestorablePlaybackState($posinfo, $mediainfo);
    }

    private static function hasRestorableSavedState($actual)
    {
    	if (!is_array($actual) || empty($actual)) {
    		return false;
    	}

    	foreach ($actual as $zone => $data) {
    		if (!is_array($data)) {
    			continue;
    		}

    		$posinfo = (isset($data['PositionInfo']) && is_array($data['PositionInfo'])) ? $data['PositionInfo'] : array();
    		$mediainfo = (isset($data['MediaInfo']) && is_array($data['MediaInfo'])) ? $data['MediaInfo'] : array();

    		if (self::hasRestorablePlaybackState($posinfo, $mediainfo)) {
    			return true;
    		}
    	}

    	return false;
    }


    /**
     * Handles one soundbar during the active monitor time window
     */
    private static function processSoundbar($key, $soundbars, $sonoszonen, $TV_safe_file, $status_file, $statusNight, $Stunden, $restore_file) {

    	global $lbpplugindir,$state,$status_file;

    	// ********************************************
    	// If Soundbar has been configured On
    	// ********************************************
    	if ((bool)is_enabled($soundbars[$key][14]['usesb'])) {
    		if (file_exists("/run/shm/".$lbpplugindir."/".$restore_file."_".$key.".json")) {
    			unlink("/run/shm/".$lbpplugindir."/".$restore_file."_".$key.".json");
    		}
    		if (file_exists("/run/shm/".$lbpplugindir."/".$restore_file."_pending_".$key.".json")) {
    			unlink("/run/shm/".$lbpplugindir."/".$restore_file."_pending_".$key.".json");
    		}

    		try {
    			$sonos = new SonosAccess($soundbars[$key][0]); // Sonos IP address
    			$tvmodi = $sonos->GetZoneInfo();
    			$posinfo = $sonos->GetPositionInfo();
    			$state = $sonos->GetTransportInfo();
    			$master = $key;

    			// **********************************************
    			// Soundbar is off
    			// **********************************************
    			$htAudioIn = isset($tvmodi['HTAudioIn']) ? (int)$tvmodi['HTAudioIn'] : 0;
    			$trackUri  = isset($posinfo["TrackURI"]) ? (string)$posinfo["TrackURI"] : '';
    			$tvInputActive = self::isTvInputActive($htAudioIn, $trackUri);
    			$statusMarker = "/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json";

    			if ($htAudioIn == 0 || (!$tvInputActive && file_exists($statusMarker))) {
    				// If a TV monitor session is active/known and the input value drops below
    				// the TV threshold, this is TV-off handling. It must not fall through
    				// into the music branch because the queue metadata may still be present
    				// and would overwrite the original pre-TV save file.
    				self::processSoundbarTVOff($key, $TV_safe_file);

    			// ***********************************************
    			// Soundbar has been turned On 1st time 
    			// ***********************************************
    			} elseif ($tvInputActive) {

    				// TV has been turned on.
    				// Important: TV-on handling must run even if no music/radio/queue
    				// source is currently restorable. The restore-source save is optional
    				// and must not block the first TV monitor setup.
    				if (!file_exists($statusMarker)) {
    					$mediainfo = array();
    					try {
    						$mediainfo = $sonos->GetMediaInfo();
    					} catch (Exception $e) {
    						$mediainfo = array();
    					}

    					if (self::shouldSavePreTvSource($htAudioIn, $state, $posinfo, $mediainfo)) {
    						self::processSoundbarMusic($key, $state, $TV_safe_file);
    					} else {
    						echo "TV input detected for ".$key." (HTAudioIn=".$htAudioIn.") without active restorable queue/radio/stream; pre-TV save file is not updated.".PHP_EOL;
    					}

    					self::processSoundbarTVFirstOn($key, $soundbars, $sonoszonen, $status_file);

    				// ******************************************************
    				// Soundbar is already running
    				// ******************************************************
    				} else {
    					self::processSoundbarTVRunning($key, $soundbars, $Stunden, $statusNight);
    				}

    			// ******************************************************
    			// Music, stream, radio or queue is really playing
    			// ******************************************************
    			} else {
    				$mediainfo = array();
    				try {
    					$mediainfo = $sonos->GetMediaInfo();
    				} catch (Exception $e) {
    					$mediainfo = array();
    				}

    				if ($htAudioIn < 21) {
    					// Output/HTAudioIn below 21 means no active stream/radio input.
    					// Sonos may still expose queue metadata after Stop/Pause/TV, but this
    					// must not overwrite the real pre-TV restore snapshot.
    					echo "Output/HTAudioIn for ".$key." is below Stream/Radio threshold (".$htAudioIn."); existing TV monitor save file is kept unchanged.".PHP_EOL;
    				} elseif ($htAudioIn !== 21) {
    					// Be conservative: only the known Stream/Radio value 21 may update
    					// s4lox_TV_save_<room>.json. TV input is handled above.
    					echo "Output/HTAudioIn for ".$key." is not Stream/Radio (".$htAudioIn."); existing TV monitor save file is kept unchanged.".PHP_EOL;
    				} elseif ((int)$state !== 1) {
    					// Do not overwrite a valid pre-TV save file with a stopped/paused queue
    					// snapshot. Sonos often keeps TrackMetaData/Queue data even after TV
    					// playback or after Stop, but that is not the original status we want
    					// to restore later.
    					echo "No active stream/radio/queue playback for ".$key." (transport state=".$state."); existing TV monitor save file is kept unchanged.".PHP_EOL;
    				} elseif (self::hasRestorablePlaybackState($posinfo, $mediainfo)) {
    					self::processSoundbarMusic($key, $state, $TV_safe_file);
    				} else {
    					// Important: keep an existing valid save file from an earlier stream/radio/queue run.
    					// Do not delete it just because the current poll sees an idle/empty player state.
    					echo "No restorable music, radio, stream or queue state for ".$key."; existing TV monitor save file is kept unchanged.".PHP_EOL;
    				}
    			}

    			echo "Current incoming source for ".$key." at HDMI/SPDIF: " . self::getAudioSourceName($tvmodi['HTAudioIn']) . " (".$tvmodi['HTAudioIn'].")". PHP_EOL;	

    		} catch (Exception $e) {
    			echo "Soundbar '".$key."' has not responded , maybe Soundbar is offline, we skip here...".PHP_EOL;
    		}	

    	// ********************************************
    	// If Soundbar is turned Off in Plugin
    	// ********************************************
    	} else {
    		@array_map('unlink', glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$key.'*.*'));				
    		echo "TV Monitor for Soundbar '".$key."' is turned off in Plugin Config".PHP_EOL;
    	}
    }


    /**
     * Restores saved soundbar settings outside active hours.
     *
     * End-time behavior:
     * Once the configured monitor end time has been reached, the monitor no longer
     * changes active TV playback. The restore is delayed until HDMI/TV input is no
     * longer active. At that point only soundbar settings are restored.
     *
     * Important:
     * This outside-window path must never restore the previous source, stream, group,
     * seek position, or play/pause state. Full playback restore is only allowed inside
     * the active monitor window when TV is turned off normally.
     *
     * - per soundbar individually
     * - only once per soundbar (controlled by $restore_file)
     */
    private static function restoreSoundbarSettings($soundbars, $restore_file) {

    	global $lbpplugindir, $status_file, $TV_safe_file;

    	// Important:
    	// This outside-window function is called by cron every few seconds.
    	// Therefore it must stay silent unless a real state transition happens:
    	// - first delayed restore detection while TV is still active
    	// - actual settings-only restore after TV input is off
    	foreach ($soundbars as $subkey => $value) {

    		// Only handle enabled soundbars
    		if (!isset($soundbars[$subkey][14]['usesb']) || !is_enabled($soundbars[$subkey][14]['usesb'])) {
    			continue;
    		}

    		// Validate IP
    		if (!isset($soundbars[$subkey][0]) || empty($soundbars[$subkey][0])) {
    			continue;
    		}

    		$ip          	= $soundbars[$subkey][0];
    		$restoremark	= "/run/shm/".$lbpplugindir."/".$restore_file."_".$subkey.".json";
    		$statusfile 	= "/run/shm/".$lbpplugindir."/".$status_file."_".$subkey.".json";

    		// Restore only once per soundbar/session
    		if (file_exists($restoremark)) {
    			continue;
    		}

    		// No saved TV monitor settings available. This is a normal no-op outside the time window.
    		if (!file_exists($statusfile)) {
    			continue;
    		}

    		try {
    			$sonos = new SonosAccess($ip);
    			$tvmodi = $sonos->GetZoneInfo();
    			$posinfo = $sonos->GetPositionInfo();
    			$htAudioIn = isset($tvmodi['HTAudioIn']) ? (int)$tvmodi['HTAudioIn'] : null;
    			$trackUri = isset($posinfo['TrackURI']) ? (string)$posinfo['TrackURI'] : '';

    			// After the configured monitor end time, do not restore just because
    			// the time window has ended. Restore is allowed only after the
    			// TV/HDMI input is clearly off. Any positive HTAudioIn value,
    			// including 21, is treated as still active or not safely off.
    			if (!self::isTvInputClearlyOffForRestore($tvmodi)) {
    				continue;
    			}

    			$settingsRestored = self::restoreSoundbarSettingsFromJson($subkey, $ip, $lbpplugindir, $status_file);

    			if ($settingsRestored) {
				// Outside the monitor time window we must not restore source/stream/group
				// context at all. Even without an explicit Play() call, some Sonos
				// sources may start playback after SetAVTransportURI(). Therefore only
				// soundbar settings from s4lox_TV_on_<room>.json are restored here.
				LOGDEB("src/Support/TvMonitor.php: Source/stream restore skipped for '".$subkey."' after monitor end time. Soundbar settings only were restored to prevent playback from starting.");

				// Delete only TV temp files for this soundbar after the restore.
    				$tempfiles = glob('/run/shm/'.$lbpplugindir.'/s4lox_TV*'.$subkey.'*.*');
    				if (is_array($tempfiles)) {
    					foreach ($tempfiles as $tempfile) {
    						@unlink($tempfile);
    					}
    				}

    				file_put_contents($restoremark, json_encode("1", JSON_PRETTY_PRINT));
    				LOGDEB("src/Support/TvMonitor.php: TV Monitor restore completed for '".$subkey."' after monitor end time and TV input off. Soundbar settings were restored only; source/stream context and playback were intentionally not restored.");

    			} else {
    				self::startlog();
    				LOGWARN("src/Support/TvMonitor.php: Settings-only restore failed for '".$subkey."' outside monitor time window.");
    			}

    		} catch (Exception $e) {
    			self::startlog();
    			LOGWARN("src/Support/TvMonitor.php: Restore check for Soundbar '".$subkey."' failed: ".$e->getMessage());
    		}
    	}
    }

    /**
     * Restore all saved soundbar settings from JSON file
     * for exactly one soundbar ($subkey)
     */
    private static function restoreSoundbarSettingsFromJson($subkey, $ip, $lbpplugindir, $status_file)
    {
    	#global ,$sonos;
    	self::startlog();
    	$jsonfile = "/run/shm/" . $lbpplugindir . "/" . $status_file . "_" . $subkey . ".json";
    	#print_r($jsonfile);
    	if (!file_exists($jsonfile)) {
    		LOGERR("src/Support/TvMonitor.php: No restore file found for '".$subkey."': ".$jsonfile);
    		return false;
    	}

    	$json = file_get_contents($jsonfile);
    	if ($json === false || trim($json) === '') {
    		LOGERR("src/Support/TvMonitor.php: Could not read restore file or file is empty for '".$subkey."': ".$jsonfile);
    		return false;
    	}

    	$saved = json_decode($json, true);

    	if (!is_array($saved)) {
    		LOGERR("src/Support/TvMonitor.php: Invalid JSON in '".$jsonfile."'");
    		return false;
    	}

    	LOGDEB("src/Support/TvMonitor.php: Restoring saved settings for '".$subkey."' from '".$jsonfile."'");

    	// helper: convert JSON boolean-like values safely to 0/1
    	$toBoolInt = function ($value) {
    		if (is_bool($value)) {
    			return $value ? 1 : 0;
    		}

    		$value = strtolower(trim((string)$value));
    		return in_array($value, array("1", "true", "yes", "on"), true) ? 1 : 0;
    	};

    	// helper: convert numeric values safely to integer
    	$toInt = function ($value, $default = 0) {
    		return is_numeric($value) ? (int)$value : (int)$default;
    	};

    	$sonos = new SonosAccess($ip); // Sonos IP address
    	// Restore soundbar dialog/sub/night settings
    	if (array_key_exists("NightMode", $saved)) {
    		$sonos->SetDialogLevel($toBoolInt($saved["NightMode"]), "NightMode");
    		LOGDEB("src/Support/TvMonitor.php: Night Mode restored to '".self::boolToOnOff($saved["NightMode"])."' for '".$subkey."'");
    	}

    	if (array_key_exists("SurroundEnable", $saved)) {
    		$sonos->SetDialogLevel($toBoolInt($saved["SurroundEnable"]), "SurroundEnable");
    		LOGDEB("src/Support/TvMonitor.php: Surround Mode restored to '".self::boolToOnOff($saved["SurroundEnable"])."' for '".$subkey."'");
    	}

    	if (
    		array_key_exists("SurroundLevel", $saved) &&
    		array_key_exists("SurroundEnable", $saved) &&
    		$toBoolInt($saved["SurroundEnable"]) === 1
    	) {
    		$sonos->SetDialogLevel($saved["SurroundLevel"], "SurroundLevel");
    		LOGDEB("src/Support/TvMonitor.php: Surround Level restored to '".$saved["SurroundLevel"]."' for '".$subkey."'");
    	} elseif (array_key_exists("SurroundLevel", $saved)) {
    		LOGDEB("src/Support/TvMonitor.php: Surround Level restore skipped because Surround is Off for '".$subkey."'");
    	}

    	if (array_key_exists("DialogLevel", $saved)) {
    		$sonos->SetDialogLevel($toBoolInt($saved["DialogLevel"]), "DialogLevel");
    		LOGDEB("src/Support/TvMonitor.php: Speech Mode restored to '".self::boolToOnOff($saved["DialogLevel"])."' for '".$subkey."'");
    	}

    	if (array_key_exists("SubEnable", $saved)) {
    		$sonos->SetDialogLevel($toBoolInt($saved["SubEnable"]), "SubEnable");
    		LOGDEB("src/Support/TvMonitor.php: Subwoofer Mode restored to '".self::boolToOnOff($saved["SubEnable"])."' for '".$subkey."'");
    	}

    	if (
    		array_key_exists("SubGain", $saved) &&
    		array_key_exists("SubEnable", $saved) &&
    		$toBoolInt($saved["SubEnable"]) === 1
    	) {
    		$sonos->SetDialogLevel($saved["SubGain"], "SubGain");
    		LOGDEB("src/Support/TvMonitor.php: Subwoofer Level restored to '".$saved["SubGain"]."' for '".$subkey."'");
    	} elseif (array_key_exists("SubGain", $saved)) {
    		LOGDEB("src/Support/TvMonitor.php: Subwoofer Level restore skipped because Subwoofer is Off for '".$subkey."'");
    	}

    	// Restore audio settings
    	if (array_key_exists("Volume", $saved)) {
    		$sonos->SetVolume($saved["Volume"]);
    		LOGDEB("src/Support/TvMonitor.php: Volume restored to '".$saved["Volume"]."' for '".$subkey."'");
    	}

    	if (array_key_exists("Treble", $saved)) {
    		$sonos->SetTreble($saved["Treble"]);
    		LOGDEB("src/Support/TvMonitor.php: Treble restored to '".$saved["Treble"]."' for '".$subkey."'");
    	}

    	if (array_key_exists("Bass", $saved)) {
    		$sonos->SetBass($saved["Bass"]);
    		LOGDEB("src/Support/TvMonitor.php: Bass restored to '".$saved["Bass"]."' for '".$subkey."'");
    	}

    	LOGDEB("src/Support/TvMonitor.php: Restore finished successfully for '".$subkey."'");
    	return true;
    }

    // turns bool into Off/On

    private static function hasValidNightStartTime($fromTime)
    {
        $fromTime = trim((string)$fromTime);
        if ($fromTime === '' || strtolower($fromTime) === 'false') {
            return false;
        }
        return (bool)preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $fromTime);
    }

    private static function hasNightActionEnabled(array $settings)
    {
        if (isset($settings['tvmonnight']) && is_enabled($settings['tvmonnight'])) {
            return true;
        }
        if (isset($settings['tvsubnight']) && is_enabled($settings['tvsubnight'])) {
            return true;
        }
        if (isset($settings['tvmonnightsublevel']) && $settings['tvmonnightsublevel'] !== '') {
            return true;
        }
        return false;
    }

    private static function isAtOrAfterNightStart($now, $fromTime)
    {
        if (!self::hasValidNightStartTime($fromTime)) {
            return false;
        }

        $normalize = function ($value) {
            $parts = explode(':', (string)$value);
            $hour = isset($parts[0]) ? (int)$parts[0] : 0;
            $minute = isset($parts[1]) ? (int)$parts[1] : 0;
            return ($hour * 60) + $minute;
        };

        return $normalize($now) >= $normalize($fromTime);
    }

    private static function boolToOnOff($value)
    {
    	if (is_bool($value)) {
    		return $value ? "On" : "Off";
    	}

    	$value = strtolower(trim((string)$value));
    	return in_array($value, array("1", "true", "yes", "on"), true) ? "On" : "Off";
    }

    private static function isTvInputActive($htAudioIn, $trackUri)
    {
    	$htAudioIn = (int)$htAudioIn;
    	$trackUri = (string)$trackUri;

    	return ($htAudioIn > 21 || substr($trackUri, 0, 17) == "x-sonos-htastream");
    }

    /**
     * Return true only when the TV/HDMI input is clearly off.
     *
     * This check is intentionally stricter than isTvInputActive(). After the
     * monitor end time, a restore must not happen while the soundbar still
     * reports any positive HTAudioIn value. Some Sonos states can report 21
     * even though the TV session is still effectively active, so only 0 is
     * accepted as a safe off state for delayed restore.
     */
    private static function isTvInputClearlyOffForRestore($tvmodi)
    {
    	if (!is_array($tvmodi) || !array_key_exists('HTAudioIn', $tvmodi)) {
    		return false;
    	}

    	return ((int)$tvmodi['HTAudioIn'] === 0);
    }

    private static function getAudioSourceName($value)
    {
        $value = (int)$value;

        // TV / HDMI (bit 0x02000000 is set)
        if ($value > 21) {
            return 'TV on';
        }

        // Stream / Radio (known value)
        if ($value === 21) {
            return 'Stream/Radio';
        }

        // TV off
        if ($value === 0) {
            return 'TV off';
        }

        return 'Unknown (' . $value . ')';
    }


    /**
    /* Function : DelFiles --> delete tmp files
    /*
    /* @param:  none
    /* @return: none
    **/

    private static function DelFiles($mask)    {

    	global $mask,$lbpplugindir;

    	array_map('unlink', glob("/run/shm/".$lbpplugindir."/".$mask));
    }



    /**
    /* Function : RestorePrevSBsettings --> Restore previous settings before TV Monitor starts
    /*
    /* @param:  none
    /* @return: none
    **/

    private static function RestorePrevSBsettings($soundbars)    {

    	global $status_file, $logname, $lbpplugindir;

    	self::startlog();
    	foreach($soundbars as $key => $value)   {
    		$restorelevel = json_decode(file_get_contents("/run/shm/".$lbpplugindir."/".$status_file."_".$key.".json"), true);
    		$sonos = new SonosAccess($soundbars[$key][0]); // Sonos IP address
    		$sonos->SetAVTransportURI("x-sonos-htastream:".$soundbars[$key][1].":spdif");
    		$sonos->SetDialogLevel(is_enabled(json_encode($restorelevel['NightMode'])), 'NightMode');
    		$sonos->SetDialogLevel($restorelevel['SubGain'], 'SubGain');
    		$sonos->SetDialogLevel($restorelevel['SurroundLevel'], 'SurroundLevel');
    		echo "Previous Soundbar settings for '".$key."' has been restored.".PHP_EOL;
    		LOGDEB("src/Support/TvMonitor.php: Previous Soundbar settings for '".$key."' has been restored");	
    	}
    }


    /**
    /* Function : PrepSaveZonesStati --> start Preparation for save zones
    /*
    /* @param:  none
    /* @return: array saved details 
    **/

    private static function PrepSaveZonesStati() {

    	global $sonoszone, $soundbars, $sonos, $player, $actual, $time_start, $log, $folfilePlOn;

    	# identify if Soundbars are grouped
    	foreach($soundbars as $zonen => $ip) {
    		$sonos = new SonosAccess($soundbars[$zonen][0]); // Sonos IP address
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
    	$actual = self::saveZonesStati($sonoszone);
    	#print_r($actual);
    	return $actual;
    }


    /**
    /* Function : saveZonesStati --> saving of all needed info to restore later
    /*
    /* @param:  none
    /* @return: none
    **/

    private static function saveZonesStati($sonoszone) {

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
    		$posinfo = $actual[$player]['PositionInfo'];
    		$media = $actual[$player]['MediaInfo'];
    		$zonestatus = $actual[$player]['ZoneStatus'];
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
    	return $actual;
    }


    /**
    * Function : restoreFromJson --> restores previous Zone settings
    *
    * @param:  array
    * @return: previous settings
    **/		

    private static function restoreFromJson($actual, $restorePlayback = true)
    {
        global $sonoszone;

        LOGDEB("src/Support/TvMonitor.php: Starting full Sonos restore from JSON.");
        if (empty($actual)) {
            LOGWARN("src/Support/TvMonitor.php: Restore aborted - JSON empty.");
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
        LOGDEB("src/Support/TvMonitor.php: Master zone detected: ".$master);
        /*
            RESTORE GROUP
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
                    LOGDEB("src/Support/TvMonitor.php: '".$member."' joined '".$master."'");
                } catch (Exception $e) {
                    LOGWARN("src/Support/TvMonitor.php: Group join failed for ".$member);
                }
            }
        }
        /*
            RESTORE SOURCE
        */
        $sourceRestored = false;
        $uri  = $actual[$master]['MediaInfo']['CurrentURI'] ?? "";
        $meta = $actual[$master]['MediaInfo']['CurrentURIMetaData'] ?? "";

        // Restore only when a valid URI is available
        #if (!empty($uri) && $uri != "" && !strpos($uri, 'x-sonos-htastream')) {
    	if (!empty($uri) && $uri != "" && strpos($uri, 'x-sonos-htastream') === false) {
            try {
                $sonos = new SonosAccess($sonoszone[$master][0]);
                if (!empty($meta)) {
                    $meta = htmlspecialchars_decode($meta);
                }
                $sonos->SetAVTransportURI($uri, $meta);
                $sourceRestored = true;
                LOGDEB("src/Support/TvMonitor.php: Source restored on '".$master."' (URI: ".$uri.")");
            } catch (Exception $e) {
                LOGWARN("src/Support/TvMonitor.php: Source restore failed on ".$master);
            }
        } else {
            LOGDEB("src/Support/TvMonitor.php: No valid source to restore on '".$master."' - skipping (URI was: ".($uri ?: "empty").")");
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
                LOGDEB("src/Support/TvMonitor.php: Settings restored for '".$zone."'");
            } catch (Exception $e) {
                LOGWARN("src/Support/TvMonitor.php: Restore settings failed for ".$zone);
            }
        }

        /*
            RESTORE POSITION (seekable sources only)
        */
        if ($sourceRestored) {
            // URI patterns that are not seekable
            $nonSeekablePatterns = [
                'x-sonosapi-stream',    // Radio-Streams (SWR3, etc.)
                'x-sonosapi-radio',     // TuneIn Radio
                'x-sonosapi-hls',       // HTTP Live Streaming
                'x-rincon-stream',      // Line-In
                'x-sonos-htastream'     // TV/HDMI (should already be filtered above)
            ];

            // Check whether the URI is seekable
            $isSeekable = true;
            foreach ($nonSeekablePatterns as $pattern) {
                if (strpos($uri, $pattern) !== false) {
                    $isSeekable = false;
                    LOGDEB("src/Support/TvMonitor.php: Source is a live stream - seek not applicable");
                    break;
                }
            }

            // Restore position only for seekable sources
            if ($isSeekable) {
                $posinfo = $actual[$master]['PositionInfo'] ?? [];
                $reltime = $posinfo['RelTime'] ?? "";

                if (!empty($reltime) && $reltime != "0:00:00" && $reltime != "NOT_IMPLEMENTED") {
                    try {
                        $sonos = new SonosAccess($sonoszone[$master][0]);
                        $sonos->Seek("REL_TIME", $reltime);
                        LOGDEB("src/Support/TvMonitor.php: Seek restored to ".$reltime." on '".$master."'");
                    } catch (Exception $e) {
                        LOGDEB("src/Support/TvMonitor.php: Seek not supported for this source type");
                    }
                }
            }
        }

        /*
            PLAY STATUS

            Normal monitor window:
            - restore the previous play/pause state.

            Outside monitor window:
            - restore source/context only, but never call Play().
            - explicitly pause the master after loading the source because some Sonos
              sources may resume automatically after SetAVTransportURI().
        */
        if ($sourceRestored && !empty($actual[$master]['TransportInfo'])) {
            try {
                $sonos = new SonosAccess($sonoszone[$master][0]);
                if ($restorePlayback) {
                    if ($actual[$master]['TransportInfo'] == 1) {
                        $sonos->Play();
                        LOGDEB("src/Support/TvMonitor.php: Playback restarted on '".$master."'");
                    } else {
                        $sonos->Pause();
                        LOGDEB("src/Support/TvMonitor.php: Playback paused on '".$master."'");
                    }
                } else {
                    try {
                        $sonos->Pause();
                        LOGDEB("src/Support/TvMonitor.php: Playback restore suppressed on '".$master."' because monitor end time was reached; source is loaded but left paused.");
                    } catch (Exception $pauseException) {
                        LOGWARN("src/Support/TvMonitor.php: Playback restore was suppressed on '".$master."', but Pause() failed: ".$pauseException->getMessage());
                        try {
                            $sonos->Stop();
                            LOGDEB("src/Support/TvMonitor.php: Stop fallback executed on '".$master."' to keep restored source from playing.");
                        } catch (Exception $stopException) {
                            LOGWARN("src/Support/TvMonitor.php: Stop fallback also failed on '".$master."': ".$stopException->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                LOGWARN("src/Support/TvMonitor.php: Playback restore failed on ".$master);
            }
        } elseif (!$sourceRestored) {
            LOGDEB("src/Support/TvMonitor.php: Skipping playback restore - no source was loaded");
        }

        LOGDEB("src/Support/TvMonitor.php: Restore finished.");
    }


    /**
    * Function : processTvGroupStop --> stop grouped players 
    *
    * @param: array @soundbars, array @sonoszone
    * @return: static
    **/

    private static function processTvGroupStop(array $soundbars, array $sonoszone) {

        foreach ($soundbars as $sbRoom => $sbData) {

            $tvgrpstop = $sbData[14]['tvgrpstop'] ?? [];
            if (empty($tvgrpstop)) {
                continue; // no rooms to stop
            }
            echo "Processing Soundbar: $sbRoom\n";
            foreach ($tvgrpstop as $stopRoom) {
    			// Check whether the room is online
    			if (!file_exists(LBPDATADIR."/PlayerStatus/s4lox_on_".$stopRoom.".txt"))   {
    				LOGDEB("src/Support/TvMonitor.php: Room '$stopRoom' seems to be not reachable");
    				continue;
    			}
                // Check whether IP/UUID exists
                if (empty($sonoszone[$stopRoom][0])) {
                    echo "ERROR: No Sonos IP/UUID for room '$stopRoom'\n";
    				LOGERR("src/Support/TvMonitor.php: ERROR: No Sonos IP/UUID for room '$stopRoom'");
                    continue;
                }
                echo "Processing stop room: $stopRoom\n";
                try {
                    $sonos = new SonosAccess($sonoszone[$stopRoom][0]);
                    // Status abfragen
                    $status = getZoneStatus($stopRoom);
                    // Aktion je nach Status
                    if ($status === 'member' || $status === 'master') {
    					$sonos->BecomeCoordinatorOfStandaloneGroup();
    					LOGDEB("src/Support/TvMonitor.php: '$stopRoom' is leaving group");
    					echo "'$stopRoom' is leaving group\n";
                        sleep(1);
    					if ($sonos->GetTransportInfo() == 1) {
    						$sonos->Pause();
    						echo "Pausing room '$stopRoom'\n";
    						LOGDEB("src/Support/TvMonitor.php: Pausing room '$stopRoom'");
    					}
                    } elseif ($status === 'single') {
    					if ($sonos->GetTransportInfo() == 1) {
    						$sonos->Pause();
    						echo "Pausing single room '$stopRoom'\n";
    						LOGDEB("src/Support/TvMonitor.php: Pausing single room '$stopRoom'");
    					}
                    } else {
                        echo "No action for room '$stopRoom'\n";
                    }
                } catch (Exception $e) {
    				LOGERR("src/Support/TvMonitor.php: ERROR processing room '$stopRoom': " . $e->getMessage());
                    echo "ERROR processing room '$stopRoom': " . $e->getMessage() . "\n";
                }
            }
        }
    }


    /**
     * Remove restore-pending markers created by older test versions.
     * These markers are intentionally obsolete in V19 because they can trigger
     * an unwanted delayed restore after the next monitor start time.
     */
    private static function cleanupLegacyPendingRestoreMarkers($restore_file)
    {
        global $lbpplugindir;

        $pattern = '/run/shm/' . $lbpplugindir . '/' . $restore_file . '_pending_*.json';
        $files = glob($pattern);
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Return the timestamp of the current active monitor window start.
     * Used to detect and discard TV session markers from a previous window
     * after the next morning/start time has already been reached.
     */
    private static function currentWindowStartTimestamp($start, $end)
    {
        $now = time();
        $today = date('Y-m-d', $now);
        $startMinutes = self::timeToMinutes($start);
        $endMinutes = self::timeToMinutes($end);
        $nowMinutes = ((int)date('H', $now) * 60) + (int)date('i', $now);

        if ($startMinutes === null || $endMinutes === null) {
            return null;
        }

        if ($startMinutes === $endMinutes) {
            return strtotime($today . ' 00:00:00');
        }

        $startTime = sprintf('%02d:%02d:00', intdiv($startMinutes, 60), $startMinutes % 60);

        if ($startMinutes < $endMinutes) {
            return strtotime($today . ' ' . $startTime);
        }

        // Window crosses midnight. If we are after midnight but before end,
        // the current window started yesterday.
        if ($nowMinutes < $endMinutes) {
            return strtotime(date('Y-m-d', $now - 86400) . ' ' . $startTime);
        }

        return strtotime($today . ' ' . $startTime);
    }

    /**
    /* Function : isWithinTimeWindow --> checks whether start/end time are correct
    /*
    /* @param: $now, $start, $end                        
    /* @return: 
    **/

    private static function isWithinTimeWindow($now, $start, $end)
    {
    	$nowMinutes   = self::timeToMinutes($now);
    	$startMinutes = self::timeToMinutes($start);
    	$endMinutes   = self::timeToMinutes($end);

    	if ($nowMinutes === null || $startMinutes === null || $endMinutes === null) {
    		return false;
    	}

    	if ($startMinutes === $endMinutes) {
    		return true; // 24h active
    	}

    	if ($startMinutes < $endMinutes) {
    		return ($nowMinutes >= $startMinutes && $nowMinutes < $endMinutes);
    	}

    	// Time window crosses midnight.
    	return ($nowMinutes >= $startMinutes || $nowMinutes < $endMinutes);
    }

    private static function timeToMinutes($value)
    {
    	$value = trim((string)$value);

    	if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
    		return null;
    	}

    	$hour = (int)$matches[1];
    	$minute = (int)$matches[2];

    	if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
    		return null;
    	}

    	return ($hour * 60) + $minute;
    }

    /**
    /* Function : startlog --> starts logging
    /*
    /* @param: Name of Log, filename of Log                        
    /* @return: 
    **/

    private static function startlog()
    {
        if (!empty($GLOBALS['TVMON_LOG_STARTED'])) {
            return;
        }

        require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";
        global $lbplogdir, $lbpplugindir, $log;

        $params = [
            "name"     => "TV Monitor",
            "package"  => $lbpplugindir,
            "filename" => $lbplogdir . "/tv_monitor.log",
            "append"   => 1,
            "addtime"  => 1,
            "loglevel" => 7,
        ];

        $log = LBLog::newLog($params);

        if (empty($log)) {
            echo "ERROR: Could not initialize LoxBerry log.\n";
            return;
        }

        $GLOBALS['TVMON_LOG_STARTED'] = true;
        $log->LOGSTART("TV Monitor");
    }

    public static function shutdown()
    {
        require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";
        global $log;

        if (!empty($GLOBALS['TVMON_LOG_STARTED']) && !empty($log)) {
            $log->LOGEND("TV Monitor"); // Methode benutzen, nicht globales LOGEND()
        }
    }
}

if (PHP_SAPI === 'cli' && realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '') === __FILE__) {
    $mode = isset($argv[1]) ? strtolower(trim($argv[1])) : 'run';

    if ($mode === 'configure' || $mode === '--configure') {
        echo "<PRE>";
        S4L_TvMonitor::configureAutoplay();
        exit;
    }

    S4L_TvMonitor::run();
}
?>
