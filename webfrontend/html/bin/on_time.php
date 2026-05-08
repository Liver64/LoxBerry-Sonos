#!/usr/bin/env php
<?php

/**
 * Sonos4Lox Time Restriction Enforcement Script
 *
 * This script is intended to be executed frequently, for example every 10 seconds by cron.
 *
 * Purpose:
 * - Reads the configured Sonos zones from s4lox_config.json.
 * - Checks whether time restrictions are configured for any zone.
 * - Determines which zones are currently outside their allowed playback time range.
 * - Checks whether those zones are online and currently streaming.
 * - If a restricted zone is streaming:
 *   - Group member: remove it from the group.
 *   - Group coordinator/master: remove it from the group.
 *   - Single player: stop playback.
 *
 * Logging / Notification behavior:
 * - No LoxBerry log entry is created for normal checks.
 * - A LoxBerry log entry is only written when an actual action was performed.
 * - Notifications are only sent when an actual action was performed.
 * - Logging and notification are throttled to once per hour to avoid spam,
 *   because this script can run very frequently.
 */

require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "loxberry_log.php";
require_once "$lbphtmldir/system/sonosAccess.php";
require_once "$lbphtmldir/Helper.php";
require_once "$lbphtmldir/Grouping.php";

/**
 * Basic configuration
 */
$configfile             = "s4lox_config.json";                              // Sonos4Lox configuration file
$off_file               = "$lbplogdir/s4lox_off.tmp";                       // If this file exists, the script exits immediately
$folfilePlOn            = "$lbpdatadir/PlayerStatus/s4lox_on_";             // Player online status file prefix

/**
 * Action logging configuration
 */
$action_logfile         = "$lbplogdir/sonos.log";                           				// LoxBerry Sonos log file
$action_log_statefile   = "/run/shm/$lbpplugindir/s4lox_timecheck_last_action_log.json";  	// Stores the timestamp of the last action log/notify
$action_messages        = array();                                          				    // Stores actions performed during this run

echo "<PRE>";

global $sonoszonen, $folfilePlOn;

/**
 * Abort if the Sonos4Lox plugin/script is disabled.
 */
if (file_exists($off_file)) {
	exit;
}

/**
 * Start runtime measurement.
 * The shutdown() function is always called at the end of the script.
 */
$time_start = microtime(true);
register_shutdown_function('shutdown');

/**
 * Load Sonos4Lox player configuration.
 */
if (file_exists($lbpconfigdir . "/" . $configfile)) {
	$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
} else {
	echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
	exit;
}

/**
 * Read configured Sonos zones.
 */
$sonoszonen = ($config['sonoszonen']);

/**
 * Check whether at least one zone has time restrictions configured.
 *
 * Zone data indexes:
 * - [15] = allowed start time
 * - [16] = allowed end time
 */
$i = 0;
foreach ($sonoszonen as $zone => $data) {
	if ($data[15] != "" and $data[16] != "") {
		$i++;
	}
}

/**
 * Abort if no time restrictions are configured.
 */
if ($i === 0) {
	echo "No time restrictions are entered. We abort here.".PHP_EOL;
	exit;
}

/**
 * Determine currently active/allowed zones.
 *
 * The sonoszonen_on() helper function returns the zones that are currently
 * allowed based on the configured time restrictions.
 */
$sonoszone = sonoszonen_on();

/**
 * Abort if no active/allowed players were determined.
 */
if (count($sonoszone) === 0) {
	echo "No active Player determined. We abort here.".PHP_EOL;
	exit;
}

/**
 * Determine zones that are outside the allowed playback time range.
 *
 * This compares all configured zones against the currently active/allowed zones.
 * The result should contain zones that must not continue streaming right now.
 */
$diff_array = @array_diff_assoc($sonoszonen, $sonoszone);

/**
 * Check whether the restricted zones are currently online.
 *
 * Only online zones are processed further. Offline zones are skipped silently.
 */
$deltazones = array();
foreach ($diff_array as $zonen => $ip) {
	$handle = is_file($folfilePlOn."".$zonen.".txt");
	if ($handle === true) {
		$deltazones[$zonen] = $ip;
	}
}

/**
 * Process all online zones that are outside their allowed playback time range.
 */
foreach ($deltazones as $zone => $data) {
	try {
		/**
		 * Create Sonos access object using the zone IP address.
		 */
		$sonos = new SonosAccess($diff_array[$zone][0]);

		/**
		 * Get current transport state.
		 *
		 * In this plugin context, "1" means that the zone is currently streaming.
		 */
		$gti = $sonos->GetTransportInfo();

		/**
		 * Only take action if the zone is currently streaming.
		 */
		if ($gti == "1") {
			try {
				/**
				 * Read current Sonos group information.
				 */
				$group = $sonos->GetZoneGroupAttributes();
				$tmp_name = $group["CurrentZoneGroupName"];
				$group = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]);

				if (empty($tmp_name)) {
					/**
					 * Case 1:
					 * The player is treated as a group member.
					 *
					 * Action:
					 * Remove it from the group by making it a standalone coordinator.
					 */
					$sonos->BecomeCoordinatorOfStandaloneGroup();

					$msg = "Player '".$zone."' has been removed from Group because it is outside the allowed time range (was Member)";
					$action_messages[] = $msg;
					echo $msg.PHP_EOL;

				} elseif (!empty($tmp_name) && count($group) > 1) {
					/**
					 * Case 2:
					 * The player is treated as group coordinator/master and the group has more than one member.
					 *
					 * Action:
					 * Remove it from the group by making it a standalone coordinator.
					 *
					 * Alternative delegation logic is kept as reference but currently disabled.
					 */
					// array_shift($group);
					// $sonos->DelegateGroupCoordinationTo($group[0], 1);
					// sleep(5);

					$sonos->BecomeCoordinatorOfStandaloneGroup();

					$msg = "Player '".$zone."' has been removed from Group because it is outside the allowed time range (was Master)";
					$action_messages[] = $msg;
					echo $msg.PHP_EOL;

				} elseif (!empty($tmp_name) && count($group) < 2) {
					/**
					 * Case 3:
					 * The player is a single standalone player.
					 *
					 * Action:
					 * Stop playback.
					 */
					$sonos->Stop();

					$msg = "Player '".$zone."' has been stopped streaming because it is outside the allowed time range (was Single)";
					$action_messages[] = $msg;
					echo $msg.PHP_EOL;

				} else {
					/**
					 * Fallback:
					 * The group state could not be clearly classified.
					 */
					echo "Unknown status of Player '".$zone."'. Please check".PHP_EOL;
				}

			} catch (Exception $e) {
				/**
				 * Catch errors while reading or changing group/playback state.
				 */
				echo "Unexpected error for Player '".$zone."' occurred".PHP_EOL;
			}
		}

	} catch (Exception $e) {
		/**
		 * Zone is probably offline or not reachable.
		 * This is intentionally not logged to avoid log spam.
		 */
		// echo $zone." seems to be Offline, nothing to do".PHP_EOL;
	}
}

/**
 * Write LoxBerry log and send notification only if actions were performed.
 * Logging and notification are throttled to once per hour.
 */
s4lox_log_actions_hourly($action_messages);


/**
 * Writes performed actions to the LoxBerry log and sends a notification.
 *
 * This function only does something if:
 * - At least one action was performed during this run.
 * - The last action log/notify is older than 3600 seconds.
 *
 * @param array $messages List of action messages collected during this script run.
 * @return void
 */
function s4lox_log_actions_hourly($messages)
{
	global $action_logfile, $action_log_statefile;

	if (!is_array($messages) || count($messages) === 0) {
		return;
	}

	$now = time();
	$last_log = 0;

	/**
	 * Read the timestamp of the last action log/notification.
	 */
	if (is_file($action_log_statefile)) {
		$state = json_decode(file_get_contents($action_log_statefile), true);

		if (is_array($state) && isset($state['last_log'])) {
			$last_log = intval($state['last_log']);
		}
	}

	/**
	 * Log and notify only once per hour.
	 */
	if (($now - $last_log) < 3600) {
		return;
	}

	/**
	 * Initialize LoxBerry logging only when an actual action must be logged.
	 * This prevents log entries during normal 10-second cron runs.
	 */
	$params = [
		"name"     => "Sonos time restriction check",
		"filename" => $action_logfile,
		"append"   => 1,
		"stderr"   => 0,
		"addtime"  => 1,
	];

	$log = LBLog::newLog($params);

	LOGSTART("Sonos time restriction check");

	/**
	 * Write every action as a separate log entry.
	 * This ensures that every line receives its own LoxBerry timestamp.
	 */
	foreach ($messages as $message) {
		LOGWARN($message);
	}

	LOGEND("Sonos time restriction check finished");

	/**
	 * Send one combined notification.
	 */
	$notify_text = "Sonos time restriction action performed:".PHP_EOL;
	$notify_text .= implode(PHP_EOL, $messages);

	s4lox_notify($notify_text, "warning");

	/**
	 * Store the timestamp of this log/notify action.
	 */
	file_put_contents(
		$action_log_statefile,
		json_encode(
			[
				"last_log" => $now,
				"iso_time" => date("c"),
			],
			JSON_PRETTY_PRINT
		),
		LOCK_EX
	);
}


/**
 * Sends a LoxBerry notification if the notify() function is available.
 *
 * @param string $message Notification message.
 * @param string $type    Notification type, for example "info", "warning" or "error".
 * @return void
 */
function s4lox_notify($message, $type = "info")
{
	if (!function_exists("notify")) {
		return;
	}

	if ($type === "info" || $type === "") {
		notify(LBPPLUGINDIR, "Sonos", $message);
	} else {
		notify(LBPPLUGINDIR, "Sonos", $message, $type);
	}
}


/**
 * Shutdown handler.
 *
 * Prints the script runtime to STDOUT/HTML output.
 * This is not written to the LoxBerry log file.
 *
 * @return void
 */
function shutdown()
{
	global $time_start;

	$time_end = microtime(true);
	$process_time = $time_end - $time_start;

	echo "Timecheck took ".round($process_time, 2)." seconds to process".PHP_EOL;
}

?>