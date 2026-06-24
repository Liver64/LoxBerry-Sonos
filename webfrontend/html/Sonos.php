<?php

##############################################################################################################################
#
# veröffentlicht in: https://github.com/Liver64/LoxBerry-Sonos/releases
# Refactor cleanup: ACTION_ROUTER_PARAMETER_WARNING_V01_2026_06_19
# Warn about unknown URL parameters before runtime preparation/volume handling.
# Refactor cleanup: ACTION_ROUTER_EARLY_ACTION_GUARD_V01_2026_06_19
# Reject missing or unsupported URL actions before runtime preparation/volume handling.
# Refactor cleanup: LEGACY_SONOS_PREPARATION_CLEANUP_V02_2026_06_09
# Structured the Sonos.php runtime preparation block and added matching preparation smoke-test JSON in the patch bundle.
# Previous cleanup: LEGACY_REQUEST_PREPARATION_V02_2026_06_09
# Moved request preparation into src/Support/RequestPreparation.php; V02 preserves legacy globals before online-zone checks.
# Previous cleanup: LEGACY_MESSAGE_ALIAS_CLEANUP_V01_2026_06_09
# Removed final legacy sendmessage/sendgroupmessage switch cases; deprecated URL aliases are routed through src/Actions/TtsActions.php.
# Previous cleanup: LEGACY_ERROR_HANDLER_CLEANUP_V02_2026_06_09
# Removed HTTP client address logging and moved syntax guidance fully into src/Support/ErrorHandler.php.
# Previous cleanup: LEGACY_ERROR_HANDLER_CLEANUP_V01_2026_06_09
# Moved application error logging into src/Support/ErrorHandler.php.
# Previous cleanup: LEGACY_SHUTDOWN_CLEANUP_V01_2026_06_09
# Removed obsolete getsonosinfo() and moved shutdown handling into src/Support/ShutdownHandler.php.
# Previous cleanup: LEGACY_VOLUME_CONTEXT_V01_2026_06_09
# Moved per-request master volume resolution and group-member volume helper into src/Support/VolumeContext.php.
# Previous cleanup: LEGACY_PRESENCE_ACTIONS_V01_2026_06_08
# Removed legacy presence()/presence_detection() helpers; handled by src/Actions/PresenceActions.php and src/Support/PresenceGuard.php.
# Previous cleanup: LEGACY_FOLLOW_ACTIONS_V01_2026_06_08
# Removed migrated follow/leave switch cases handled by src/Routing/ActionRouter.php.
# Previous cleanup: LEGACY_EXTPROVIDER_ACTIONS_V01_2026_06_08
# Removed migrated external provider switch cases handled by src/Routing/ActionRouter.php.
# Previous cleanup: LEGACY_ONE_CLICK_ACTIONS_V01_2026_06_08.
# Removed obsolete no-op or migrated legacy blocks from this file.
#
# http://<IP>:1400/xml/device_description.xml
# http://<IP>:1400/support/review
#
# https://www.reddit.com/r/sonos/comments/1ggv8dk/sonos_network_troubleshooting_an_unofficial/
# https://www.reddit.com/r/sonos/comments/t0emv0/the_definitive_sonos_vlan_segregation_post/
# 
##############################################################################################################################


// Runtime limits
ini_set('max_execution_time', 60); // Maximum script runtime for long Sonos/TTS operations.

// Legacy plugin includes.
// The order is intentionally kept stable because older helper files still define global functions and rely on globals.
include_once(__DIR__ . "/src/Core/Sonos/sonosAccess.php");
include_once("Grouping.php");
include_once("Helper.php");
include_once("Playlist.php");
include_once("Alarm.php");
include_once("Metadata.php");
include_once("Queue.php");
include_once("Play_T2S.php");
include_once("Radio.php");
include_once("Restore_T2S.php");
include_once("Save_T2S.php");
include_once("Speaker.php");
include_once("follow.php");
include_once(__DIR__ . "/src/Support/MpegAudio.php");
include_once(__DIR__ . "/src/Support/MpegAudioFrameHeader.php");
include_once __DIR__ . '/src/Support/LegacyLogging.php';
include_once(__DIR__ . '/src/Support/Crypto/openssl_file.class.php');

// Refactor bootstrap and shutdown handling.
require_once __DIR__ . '/src/Bootstrap.php';
S4L_ShutdownHandler::register();

// Runtime clock.
date_default_timezone_set(date("e"));
$time_start = microtime(true);
$GLOBALS['time_start'] = $time_start;

// Basic runtime identifiers.
$home = $lbhomedir;
$hostname = gethostname();
$myIP = LBSystem::get_localip();
$syntax = $_SERVER['REQUEST_URI'];
$psubfolder = $lbpplugindir;
$lbversion = LBSystem::lbversion();
$lbport = lbwebserverport();
$requestZone = isset($_GET['zone']) ? $_GET['zone'] : '';

// LoxBerry paths and plugin folders.
$path = LBSCONFIGDIR;
$myFolder = "$lbpconfigdir";
$pathlanguagefile = "$lbphtmldir/VoiceEngines/langfiles/";
$logpath = "$lbplogdir/$psubfolder";
$templatepath = "$lbptemplatedir";
$sambaini = $lbhomedir . '/system/samba/smb.conf';
$folfilePlOn = "$lbpdatadir/PlayerStatus/s4lox_on_";
$debuggingfile = "$lbpdatadir/s4lox_debug_config.json";
$file = $lbphtmldir . "/bin/check_player_dup.txt";

// Static defaults and config file names.
$t2s_text_stand = "t2s-text_en.ini";
$searchfor = '[plugindata]';
$MP3path = "mp3";
$sleeptimegong = "3";
$sleepaddmember = "2";
$maxzap = '60';
$sPassword = 'loxberry';
$configfile = "s4lox_config.json";
$save_status_file = "s4lox_follow";
$vol_config = "s4lox_vol_profiles";
$guid = "622493a2-4877-496c-9bba-abcb502908a5"; // GUID for Sonos AudioClip notifications.

// Temporary files in RAM or plugin data.
// Some obsolete temp files are still defined for legacy compatibility and can be removed later when no helper uses them anymore.
$lastVol = "/run/shm/s4lox_PhoneMute.log";
$tmp_tts = "/run/shm/s4lox_tmp_tts";
$tmp_phone = "/run/shm/s4lox_tmp_phonemute.tmp";
$off_file = $lbplogdir . "/s4lox_off.tmp";
$alarm_off_file = $lbpdatadir . "/s4lox_alarm_off.json";
$profile_selected = "/run/shm/s4lox_Profil_selected.tmp";
$memberarray = "/run/shm/s4lox_member.json";
$tmp_error = "/run/shm/s4lox_errorMP3Stream.json";
$check_date = "/run/shm/s4lox_date";
$maxvolfile = "/run/shm/s4lox_max_volume.json";
$zapname = "/run/shm/s4lox_zap_zone.json";
$pltmp = "/run/shm/s4lox_pl_play_tmp_" . $requestZone . ".json";
$filenst = "/run/shm/s4lox_t2s_stat.tmp";

// Zone-dependent temporary files for one-click, playlist and favorite functions.
if ($requestZone !== '') {
	$radiofav = "/run/shm/s4lox_fav_all_radio_" . $requestZone . ".json";
	$queuetmp = "/run/shm/s4lox_fav_queue_tmp_" . $requestZone . ".json";
	$favtmp = "/run/shm/s4lox_fav_fav_tmp_" . $requestZone . ".json";
	$radiofavtmp = "/run/shm/s4lox_fav_all_radio_tmp_" . $requestZone . ".json";
	$queuetracktmp = "/run/shm/s4lox_fav_track_tmp_" . $requestZone . ".json";
	$queueradiotmp = "/run/shm/s4lox_fav_radio_tmp_" . $requestZone . ".json";
	$queuepltmp = "/run/shm/s4lox_fav_pl_tmp_" . $requestZone . ".json";
	$tuneinradiotmp = "/run/shm/s4lox_fav_tunein_radio_" . $requestZone . ".json";
	$sonospltmp = "/run/shm/s4lox_pl_sonos_tmp_" . $requestZone . ".json";
	$debugfile = $lbpdatadir . "/s4lox_debug_meta_fav.json";
}

// Logging setup.
echo '<PRE>';

if (!isset($_GET['debug'])) {
	$params = [
		"name" => "Sonos PHP",
		"filename" => "$lbplogdir/sonos.log",
		"append" => 1,
		"addtime" => 1,
	];
	$level = LBSystem::pluginloglevel();
} else {
	$heute = date("dmY");
	$debugLogFiles = glob($lbplogdir . '/s4lox_debug_*');
	foreach ($debugLogFiles as $debugLogFile) {
		@unlink($debugLogFile);
	}
	$params = [
		"name" => "Sonos PHP",
		"filename" => "$lbplogdir/s4lox_debug_" . $heute . ".log",
		"append" => 1,
		"addtime" => 1,
		"loglevel" => 7,
	];
	$level = "7";
}

$log = LBLog::newLog($params);
$plugindata = LBSystem::plugindata();
$L = LBSystem::readlanguage("sonos.ini");
$ms = LBSystem::get_miniservers();

LOGSTART("PHP started");
LOGOK("sonos.php: called syntax: " . $myIP . urldecode($syntax));

// Validate the public URL action before runtime preparation.
// This prevents invalid commands from changing request state, volume context,
// temporary files or one-click markers before the router rejects them.
$requestedAction = isset($_GET['action']) ? $_GET['action'] : '';
if (!S4L_ActionRouter::isKnownAction($requestedAction)) {
	S4L_ActionRouter::logInvalidAction($requestedAction);
	exit;
}
S4L_ActionRouter::warnUnknownParameters($requestedAction, $_GET);

#-- Start Request Preparation ------------------------------------------------------------

$preparation = S4L_RequestPreparation::prepare(array(
	'config_path'      => $lbpconfigdir . "/" . $configfile,
	'off_file'         => $off_file,
	'tmp_tts'          => $tmp_tts,
	'tmp_phone'        => $tmp_phone,
	'profile_selected' => $profile_selected,
));

$config           = $preparation['config'];
$sonoszonen       = $preparation['sonoszonen'];
$sonoszone        = $preparation['sonoszone'];
$master           = $preparation['master'];
$volume           = $preparation['volume'];
$t2s_langfile     = $preparation['t2s_langfile'];
$MessageStorepath = $preparation['MessageStorepath'];
$min_vol          = $preparation['min_vol'];
$min_sec          = $preparation['min_sec'];

#-- End Request Preparation --------------------------------------------------------------

if(array_key_exists($_GET['zone'], $sonoszone)){ 

	global $json;
	
	$master = $_GET['zone'];
	#check_S1_player();
	$sonos = new SonosAccess($sonoszone[$master][0]); //Sonos IP Adresse

	// Sonos4Lox Refactor bootstrap.
	// Version: V28.0
	// Public URL syntax remains unchanged. Migrated actions are handled by the new router;
	// unknown or unsupported actions are reported by the ErrorHandler.
	$s4l_refactor_context = array(
		'master' => $master,
		'sonoszone' => $sonoszone,
		'sonoszonen' => $sonoszonen,
		'sonos' => $sonos,
		'config' => $config,
		'volume' => isset($volume) ? $volume : null,
		'profile_selected' => isset($profile_selected) ? $profile_selected : array(),
		'off_file' => isset($off_file) ? $off_file : null
	);
	if (S4L_ActionRouter::dispatchIfHandled($s4l_refactor_context)) {
		exit;
	}

	// All remaining public URL actions have been moved to the refactored router.
	// If the router did not handle the request, the action is invalid or unknown.
	S4L_ErrorHandler::logApplicationError(
		"Invalid or unknown Sonos4Lox command. Required parameter action is missing or invalid.",
		__FILE__,
		__LINE__
	);
	} else 	{
	LOGWARN("sonos.php: The zone " . $master . " is not available or offline. Please check and if necessary add the zone to the config.");
	
}
exit;

# Funktionen für Skripte ------------------------------------------------------





# Application error logging is handled by src/Support/ErrorHandler.php.

?>