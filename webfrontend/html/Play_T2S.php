<?php

/**
* Submodul: Play_T2S
* Version: PLAY_T2S_AUDIOCLIP_CURL_RETRY_TUNING_V02_2026_06_20
*
**/

require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
if (file_exists(__DIR__ . '/src/Support/Logger.php')) {
	require_once __DIR__ . '/src/Support/Logger.php';
}
if (file_exists(__DIR__ . '/src/Support/PresenceGuard.php')) {
	require_once __DIR__ . '/src/Support/PresenceGuard.php';
}

$lbhostname = lbhostname();
$lbwebport  = lbwebserverport();
$myLBip     = LBSystem::get_localip();
if (!defined('T2S_BATCHFILE')) {
    // Batch file in RAM (/dev/shm) per plugin
    define('T2S_BATCHFILE', "/dev/shm/".LBPPLUGINDIR."/t2s_batch.txt");
}
if (!function_exists('check_zone_time')) {
    function check_zone_time(string $zone): bool {
        return defined('SONOSZONE') && isset(SONOSZONE[$zone]);
    }
}


if (!function_exists('s4lox_play_t2s_log')) {
    function s4lox_play_t2s_log($message = "", $loglevel = 7, $raw = 0): void {
        $message = (string)$message;
        if (strpos($message, 'Play_T2S.php:') !== 0) {
            $message = 'Play_T2S.php: ' . $message;
        }

        $level = (int)$loglevel;

        if ($level <= 3 && function_exists('LOGERR')) {
            LOGERR($message);
        } elseif ($level === 4 && function_exists('LOGWARN')) {
            LOGWARN($message);
        } elseif ($level === 5 && function_exists('LOGINF')) {
            LOGINF($message);
        } elseif ($level === 6 && function_exists('LOGOK')) {
            LOGOK($message);
        } elseif ($level >= 7 && function_exists('LOGDEB')) {
            LOGDEB($message);
        } elseif (function_exists('LOGINF')) {
            LOGINF($message);
        } else {
            error_log($message);
        }
    }
}




if (!defined('S4L_PLAY_T2S_CURL_CONNECT_TIMEOUT')) {
    define('S4L_PLAY_T2S_CURL_CONNECT_TIMEOUT', 6);
}
if (!defined('S4L_PLAY_T2S_CURL_REQUEST_TIMEOUT')) {
    define('S4L_PLAY_T2S_CURL_REQUEST_TIMEOUT', 20);
}
if (!defined('S4L_PLAY_T2S_CURL_MULTI_TOTAL_TIMEOUT')) {
    define('S4L_PLAY_T2S_CURL_MULTI_TOTAL_TIMEOUT', 30);
}
if (!defined('S4L_PLAY_T2S_CURL_MULTI_RETRY_COUNT')) {
    define('S4L_PLAY_T2S_CURL_MULTI_RETRY_COUNT', 1);
}
if (!defined('S4L_PLAY_T2S_CURL_MULTI_RETRY_DELAY_US')) {
    define('S4L_PLAY_T2S_CURL_MULTI_RETRY_DELAY_US', 350000);
}

if (!function_exists('s4lox_play_t2s_transport_is_playing')) {
    function s4lox_play_t2s_transport_is_playing($state): bool {
        if (is_array($state)) {
            $cur = strtoupper((string)($state['CurrentTransportState'] ?? $state['currenttransportstate'] ?? ''));
            return $cur === 'PLAYING' || $cur === 'TRANSITIONING';
        }

        if ($state === 1 || $state === '1') {
            return true;
        }

        $stateString = strtoupper(trim((string)$state));
        return $stateString === 'PLAYING' || $stateString === 'TRANSITIONING';
    }
}

if (!function_exists('s4lox_play_t2s_call_without_php_warning')) {
    function s4lox_play_t2s_call_without_php_warning(callable $callback, &$warning = null) {
        $warning = null;
        set_error_handler(function ($severity, $message) use (&$warning) {
            $warning = (string)$message;
            return true;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}

if (!function_exists('s4lox_play_t2s_safe_mkdir')) {
    function s4lox_play_t2s_safe_mkdir(string $dir, int $mode, string $label): bool {
        if ($dir === '' || $dir === '.') {
            return true;
        }

        if (is_dir($dir)) {
            return true;
        }

        $warning = null;
        $result = s4lox_play_t2s_call_without_php_warning(
            function () use ($dir, $mode) {
                return mkdir($dir, $mode, true);
            },
            $warning
        );

        if ($result !== true && !is_dir($dir)) {
            $suffix = $warning !== null ? ' (' . $warning . ')' : '';
            s4lox_play_t2s_log("Play_T2S.php: Could not create " . $label . " '" . $dir . "'." . $suffix, 4);
            return false;
        }

        return true;
    }
}

if (!function_exists('s4lox_play_t2s_safe_unlink')) {
    function s4lox_play_t2s_safe_unlink(string $file, string $label): bool {
        if (!file_exists($file) && !is_link($file)) {
            return true;
        }

        if (!is_file($file) && !is_link($file)) {
            s4lox_play_t2s_log("Play_T2S.php: Refusing to delete " . $label . " because it is not a regular file or symlink: '" . $file . "'.", 4);
            return false;
        }

        $warning = null;
        $result = s4lox_play_t2s_call_without_php_warning(
            function () use ($file) {
                return unlink($file);
            },
            $warning
        );

        if ($result !== true) {
            $suffix = $warning !== null ? ' (' . $warning . ')' : '';
            s4lox_play_t2s_log("Play_T2S.php: Could not delete " . $label . " '" . $file . "'." . $suffix, 4);
            return false;
        }

        return true;
    }
}

if (!function_exists('s4lox_play_t2s_safe_copy')) {
    function s4lox_play_t2s_safe_copy(string $src, string $dst, string $label): bool {
        if (!is_readable($src)) {
            s4lox_play_t2s_log("Play_T2S.php: Could not copy " . $label . " because source file is missing or not readable: '" . $src . "'.", 4);
            return false;
        }

        if (!s4lox_play_t2s_safe_mkdir(dirname($dst), 0775, $label . ' target directory')) {
            return false;
        }

        $warning = null;
        $result = s4lox_play_t2s_call_without_php_warning(
            function () use ($src, $dst) {
                return copy($src, $dst);
            },
            $warning
        );

        if ($result !== true) {
            $suffix = $warning !== null ? ' (' . $warning . ')' : '';
            s4lox_play_t2s_log("Play_T2S.php: Could not copy " . $label . " from '" . $src . "' to '" . $dst . "'." . $suffix, 4);
            return false;
        }

        return true;
    }
}

if (!function_exists('s4lox_play_t2s_safe_file_get_contents')) {
    function s4lox_play_t2s_safe_file_get_contents(string $path, string $label, bool $logFailure = true) {
        $context = null;
        if (preg_match('~^https?://~i', $path)) {
            $context = stream_context_create([
                'http' => ['timeout' => 3],
                'https' => ['timeout' => 3],
            ]);
        }

        $warning = null;
        $result = s4lox_play_t2s_call_without_php_warning(
            function () use ($path, $context) {
                return $context === null ? file_get_contents($path) : file_get_contents($path, false, $context);
            },
            $warning
        );

        if ($result === false && $logFailure) {
            $suffix = $warning !== null ? ' (' . $warning . ')' : '';
            s4lox_play_t2s_log("Play_T2S.php: Could not read " . $label . " from '" . $path . "'." . $suffix, 4);
        }

        return $result;
    }
}

if (!function_exists('s4lox_play_t2s_safe_get_file_content')) {
    function s4lox_play_t2s_safe_get_file_content(string $url, string $label) {
        if (!function_exists('get_file_content')) {
            s4lox_play_t2s_log("Play_T2S.php: Could not call " . $label . " because get_file_content() is not available.", 4);
            return false;
        }

        $warning = null;
        $result = s4lox_play_t2s_call_without_php_warning(
            function () use ($url) {
                return get_file_content($url);
            },
            $warning
        );

        if ($result === false && $warning !== null) {
            s4lox_play_t2s_log("Play_T2S.php: Could not read " . $label . ". " . $warning, 4);
        }

        return $result;
    }
}

if (!function_exists('s4lox_play_t2s_is_standard_playgong_request')) {
    function s4lox_play_t2s_is_standard_playgong_request($value): bool {
        $value = strtolower(trim((string)$value));
        return $value === '' || $value === 'yes' || $value === 'true' || $value === '1' || $value === 'on';
    }
}

if (!function_exists('s4lox_play_t2s_is_invalid_disabled_playgong_request')) {
    function s4lox_play_t2s_is_invalid_disabled_playgong_request($value): bool {
        $value = strtolower(trim((string)$value));
        return $value === 'no' || $value === 'false' || $value === '0' || $value === 'off';
    }
}


if (!function_exists('s4lox_play_t2s_log_safe_value')) {
    function s4lox_play_t2s_log_safe_value($value, int $maxLength = 120): string {
        $value = str_replace(["
", "
", "	"], ' ', (string)$value);
        if (strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength) . '...';
        }
        return $value;
    }
}

if (!function_exists('s4lox_play_t2s_validate_messageid_parameter')) {
    function s4lox_play_t2s_validate_messageid_parameter(): bool {
        if (!array_key_exists('messageid', $_GET)) {
            return true;
        }

        $originalMessageId = trim((string)$_GET['messageid']);
        $messageid = $originalMessageId;
        $messageidForLog = s4lox_play_t2s_log_safe_value($messageid);

        if ($messageid === '') {
            s4lox_play_t2s_log("Play_T2S.php: Invalid messageid parameter. The messageid must not be empty. Please provide the stored message name without path. A trailing '.mp3' suffix is accepted and removed automatically.", 3);
            return false;
        }

        if (preg_match('/\.mp3\z/i', $messageid)) {
            $messageid = substr($messageid, 0, -4);
            if ($messageid !== '') {
                s4lox_play_t2s_log("Play_T2S.php: Messageid parameter '" . s4lox_play_t2s_log_safe_value($originalMessageId) . "' has been normalized to '" . s4lox_play_t2s_log_safe_value($messageid) . "'.", 6);
            }
        }

        if ($messageid === '') {
            s4lox_play_t2s_log("Play_T2S.php: Invalid messageid parameter '" . $messageidForLog . "'. Please provide the stored message name without path. A trailing '.mp3' suffix is accepted and removed automatically.", 3);
            return false;
        }

        if (!preg_match('/\A[A-Za-z0-9_-]+\z/', $messageid)) {
            s4lox_play_t2s_log("Play_T2S.php: Invalid messageid parameter '" . $messageidForLog . "'. Allowed characters are letters, digits, underscore and hyphen. A trailing '.mp3' suffix is accepted, but slashes, spaces, path components and dots inside the name are not allowed.", 3);
            return false;
        }

        $_GET['messageid'] = $messageid;
        $GLOBALS['messageid'] = $messageid;
        return true;
    }
}

if (!function_exists('s4lox_include_addon')) {
    function s4lox_include_addon(string $filename): bool {
        $path = __DIR__ . '/src/Support/AddOn/' . $filename;

        if (!is_readable($path)) {
            s4lox_play_t2s_log("AddOn file 'src/Support/AddOn/" . $filename . "' is missing or not readable.", 4);
            return false;
        }

        include_once $path;
        return true;
    }


/*
 * TTS helper functions relocated from Helper.php.
 * Keep the global function names for legacy URL compatibility.
 */

/**
 * Function : select_t2s_engine --> includes the configured t2s engine file
 *
 * @param int|null $engineCode  Optional explicit engine code
 *                              (falls null, wird Config-Wert verwendet)
 * @return int  Effektiv verwendeter Engine-Code
 */
function select_t2s_engine(int $engineCode = null): array
{
    global $config;

    if ($engineCode === null || $engineCode === 0) {
        $engineCode = (int)($config['TTS']['t2s_engine'] ?? 0);
    }

    // Engine Registry
    $engines = [
        1001 => ['name' => 'VoiceRSS',        'file' => 'VoiceRSS.php'],
        6001 => ['name' => 'ResponsiveVoice', 'file' => 'ResponsiveVoice.php'],
        9012 => ['name' => 'Piper',           'file' => 'Piper.php'],
        4001 => ['name' => 'Polly',           'file' => 'Polly.php'],
        9001 => ['name' => 'MS_Azure',        'file' => 'MS_Azure.php'],
        9011 => ['name' => 'ElevenLabs',      'file' => 'ElevenLabs.php'],
        8001 => ['name' => 'GoogleCloud',     'file' => 'GoogleCloud.php']
    ];

    if (!isset($engines[$engineCode])) {

        if (function_exists('LOGERR')) {
            LOGERR("Play_T2S.php: select_t2s_engine(): Unknown TTS engine code '$engineCode'.");
        }

        return [
            'code' => $engineCode,
            'name' => 'Unknown'
        ];
    }

    $engine = $engines[$engineCode];

    $engineFile = "VoiceEngines/" . $engine['file'];

    if (!file_exists($engineFile)) {

        if (function_exists('LOGERR')) {
            LOGERR("Play_T2S.php: select_t2s_engine(): Engine file missing: $engineFile");
        }

    } else {
        include_once($engineFile);
    }

    return [
        'code' => $engineCode,
        'name' => $engine['name']
    ];
}

/**
* Function : mp3_files --> check if playgong mp3 file is valid in ../tts/mp3/
*
* @param: 
* @return: array 
**/

function mp3_files($playgongfile) {
	global $config;
	
	$scanned_directory = array_diff(scandir($config['SYSTEM']['mp3path'], SCANDIR_SORT_DESCENDING), array('..', '.'));
	$file_only = array();
	foreach ($scanned_directory as $file) {
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		if ($extension == 'mp3') {
			array_push($file_only, $file);
		}
	}
	#print_r($file_only);
	return (in_array($playgongfile, $file_only));
}
}


/*
 * TTS text helper relocated from Helper.php.
 * Keep the global function name for legacy AddOn compatibility.
 */






/**
* Function : load_t2s_text --> check if translation file exit and load into array
*
* @param: 
* @return: array 
**/

function load_t2s_text(){
	global $config, $t2s_langfile, $t2s_text_stand, $templatepath;
	
	$templatepath.'/lang/'.$t2s_langfile;
	if (file_exists($templatepath.'/lang/'.$t2s_langfile)) {
		$TL = parse_ini_file($templatepath.'/lang/'.$t2s_langfile, true);
	} else {
		s4lox_play_t2s_log("Play_T2S.php: For selected T2S language no translation file still exist! Please go to LoxBerry Plugin translation and create a file for selected language ".substr($config['TTS']['messageLang'],0,2),4);
		$TL = "";
		#exit;
	}
	return $TL;
}



/**
* New Function for T2S: say --> replacement/enhancement for sendmessage/sendgroupmessage
*
* @param: empty
* @return: nothing
**/

function say() {
	
	global $sonos, $tts_stat, $sonoszone, $profile, $master, $lbpconfigdir, $config, $vol_config, $group, $textstring, $result;
	
	if (!s4lox_play_t2s_validate_messageid_parameter()) {
		exit();
	}

	S4L_PresenceGuard::assertTtsAllowed();
	#print_r($sonoszone);
	
	if (!isset($sonoszone[$master])) {
        s4lox_play_t2s_log("Play_T2S.php: Zone '$master' is currently not available. Maybe '$master' ist offline or Time restrictions are valid", 5);
        exit();
    }
	
	if ((empty($config['TTS']['t2s_engine'])) or (empty($config['TTS']['messageLang'])))  {
		s4lox_play_t2s_log("Play_T2S.php: There is no T2S engine/language selected in Plugin config. Please select before using T2S functionality.", 3);
		exit();
	}
	if(isset($_GET['profile']) and isset($_GET['member']))  {
		$tocall = "Error!! Both parameters where entered";
		s4lox_play_t2s_log("Play_T2S.php: Parameter 'member' and 'profile' could not be used in conjunction! Please correct your syntax/URL", 3);
		exit();
	}
	check_S1_player();
	if(isset($_GET['ic']))    {
		$ic = true;
		$textstring = "Hallo Oliver";
		require_once("bin/interface.php");
		$result = send_data_curl();
		s4lox_play_t2s_log("Play_T2S.php: T2S Interface has been called");
	}
	$profile = false;
	if(!isset($_GET['member']) && !isset($_GET['profile'])) {
		if ((!isset($_GET['text'])) && (!isset($_GET['messageid'])) && (!isset($errortext)) && (!isset($_GET['sonos'])) &&
			(!isset($_GET['text'])) && (!isset($_GET['weather'])) && (!isset($_GET['abfall'])) && (!isset($_GET['pollen'])) && (!isset($_GET['warning'])) &&
			(!isset($_GET['distance'])) && (!isset($_GET['clock'])) && 
			(!isset($_GET['calendar'])) && (($_GET['action'] ?? '') !== "playbatch")) {
			$tocall = "Error!! Data/Input is missing";
			s4lox_play_t2s_log("Play_T2S.php: Wrong Syntax, please correct! Even 'say&text=' or 'say&messageid=' in combination with &clip are necessary to play an anouncement. (check Wiki)", 3);	
			exit;
		}
		if (isset($_GET['playbatch'])) {
			$tocall = "Playbatch T2S";
			s4lox_play_t2s_log("Play_T2S.php: 'Playbatch T2S' has been identified", 6);
			#$tts_stat = 1;
			#send_tts_source($tts_stat);
			sendmessage();
		} else {
			if(isset($_GET['clip']))  {
				$tocall = "Single Clip";
				s4lox_play_t2s_log("Play_T2S.php: 'Single Clip' has been identified", 6);
				#$tts_stat = 1;
				#send_tts_source($tts_stat);				
				sendAudioSingleClip();
			} else {
				$tocall = "Single T2S";
				s4lox_play_t2s_log("Play_T2S.php: 'Single T2S' has been identified", 6);
				#$tts_stat = 1;
				#send_tts_source($tts_stat);
				sendmessage();			
			}
		}
	} else {
		if(isset($_GET['clip']) and !isset($_GET['profile']) and isset($_GET['member']))  {
			$tocall = "Multi Clip Member";
			s4lox_play_t2s_log("Play_T2S.php: 'Multi Clip Member' has been identified", 6);
			$profile = true;
			#$tts_stat = 1;
			#send_tts_source($tts_stat);
			sendAudioMultiClip();
		} elseif(isset($_GET['clip']) and isset($_GET['profile']) and !isset($_GET['member']))  {
			$group = checkGroupProfile();
			if ($group == true)   {
				$tocall = "Multi Clip Profile";
				s4lox_play_t2s_log("Play_T2S.php: 'Multi Clip Profile' has been identified", 6);
				#$tts_stat = 1;
				#send_tts_source($tts_stat);
				sendAudioMultiClip();
			} else {
				$tocall = "Single Clip Profile";
				s4lox_play_t2s_log("Play_T2S.php: 'Single Clip Profile' has been identified", 6);
				#$tts_stat = 1;
				#send_tts_source($tts_stat);				
				sendAudioSingleClip();
			}
		} elseif(!isset($_GET['clip']) and isset($_GET['member']) and !isset($_GET['profile'])) {
			$tocall = "Group T2S";
			s4lox_play_t2s_log("Play_T2S.php: 'Group T2S' has been identified", 6);	
			$profile = true;
			#$tts_stat = 1;
			#send_tts_source($tts_stat);
			sendgroupmessage();
		} elseif(!isset($_GET['clip']) and !isset($_GET['member']) and isset($_GET['profile'])) {
			$group = checkGroupProfile();
			if ($group == true)   {
				$tocall = "Group T2S Profile";
				s4lox_play_t2s_log("Play_T2S.php: 'Group T2S Profile' has been identified", 6);
				$profile = true;
				#$tts_stat = 1;
				#send_tts_source($tts_stat);				
				sendgroupmessage();
			} else {
				$tocall = "Single T2S Profile";
				s4lox_play_t2s_log("Play_T2S.php: 'Single T2S Profile' has been identified", 6);
				createArrayFromGroupProfile();				
				#$tts_stat = 1;
				#send_tts_source($tts_stat);
				sendmessage();
			}
		}
	}
	echo "Profile '$tocall' has been identified";
}


/**
* Function : playAudioClip --> playing T2S or file 
*
* @param: T2S or messageid (Number)
* @return: 
**/

function playAudioClip() {

	global $config, $prio, $profile_details, $memberon, $master, $lbpconfigdir, $vol_config, $roomcord, $sonos, $errortext, $time_start, $zones, $source, $messageid, $playstat, $filename;

	if (isset($_GET['profile'])) {
		get_profile_details();
		if ($profile_details[0]['Group'] == "Group")   {
			$source = "multi";
		} else {
			$source = "Single";
		}
	}
	if (isset($_GET['member']) and !isset($_GET['profile'])) {
		$source = "multi";
	} 
	if (!isset($_GET['member'])and !isset($_GET['profile'])) {
		$source = "single";
	}
	// check if filename is there or T2S generated and saved
	check_file();
	// call prio to set for clip
	handle_prio();
	// call playgong check upfront
	handle_playgong($zones, $source);
	// call messageid/T2S announcement
	handle_message($zones, $source);
}

if (!function_exists('t2s_basic_say')) {
    /**
     * t2s_basic_say
     *
     * Lightweight wrapper for:
     *   - building TTS parameters from config/GET/overrides
     *   - generating the MP3 file via t2s()
     *   - playing it through a temporary Sonos queue and waiting until it ends
     *
     * No Sonos zone snapshot/restore is performed here.
     * No dependency on $actual / play_tts().
     *
     * @param string $textstring  Text to announce.
     * @param array  $override    Optional overrides, similar to create_t2s_param():
     *                             - 't2sengine' : int
     *                             - 'language'  : string
     *                             - 'voice'     : string
     *                             - 'apikey'    : string
     *                             - 'secretkey' : string
     *                             - 'region'    : string
     *                             - 'ignore_get': bool
     *                             - 'minimum_wait_seconds': float
     * @param string $log_context  Accepted for compatibility only; not used for logging.
     */
    function t2s_basic_say(
        string $textstring,
        array $override = [],
        string $log_context = 'Play_T2S.php:'
    ): array {
        global $config, $sonos, $master, $sonoszone, $volume;

        // Make sure there is something to say.
        $textstring = trim($textstring);
        if ($textstring === '') {
            LOGWARN("Play_T2S.php: Empty text received – skipping TTS.");
            return [];
        }

        $minimum_wait_seconds = 0.0;
        if (isset($override['minimum_wait_seconds']) && is_numeric($override['minimum_wait_seconds'])) {
            $minimum_wait_seconds = (float)$override['minimum_wait_seconds'];
            if ($minimum_wait_seconds < 0.0) {
                $minimum_wait_seconds = 0.0;
            } elseif ($minimum_wait_seconds > 10.0) {
                $minimum_wait_seconds = 10.0;
            }
        }

        // Build a deterministic filename from the text.
        $filename = md5($textstring);

        // Build the central T2S parameter array.
        $t2s_param = create_t2s_param(
            $textstring,
            $filename,
            $override,
            #'Play_T2S.php: t2s_basic_say()'
        );

        // Load the selected engine file so t2s() is available.
        select_t2s_engine();

        if (!function_exists('t2s')) {
            LOGERR("Play_T2S.php: t2s() is not defined after select_t2s_engine().");
            return $t2s_param;
        }

        // Generate the MP3 file.
        LOGDEB("Play_T2S.php: Generating TTS file '$filename.mp3'.");
        t2s($t2s_param);

        // MP3 path as used by create_tts/create_t2s_param.
        $ttspath  = rtrim($config['SYSTEM']['ttspath'] ?? '/tmp', '/');
        $mp3_file = $ttspath . "/" . $filename . ".mp3";

        if (!is_file($mp3_file) || filesize($mp3_file) === 0) {
            LOGERR("Play_T2S.php: TTS MP3 file '$mp3_file' does not exist or is empty.");
            return $t2s_param;
        }

        // ===========================
        // Lightweight Sonos queue playback
        // ===========================
        if (!isset($sonoszone[$master])) {
            LOGERR("Play_T2S.php: Master zone '$master' not found in \$sonoszone.");
            return $t2s_param;
        }

        // Coordinator IP and RINCON of the master zone.
        $coord_ip   = $sonoszone[$master][0];
        $coord_rinc = $sonoszone[$master][1];

        try {
            $sonos = new SonosAccess($coord_ip);
        } catch (Exception $e) {
            LOGERR("Play_T2S.php: Could not create SonosAccess for '$coord_ip': " . $e->getMessage());
            return $t2s_param;
        }

        try {
            // Clear the queue and add only the TTS announcement.
            $sonos->ClearQueue();

            // MP3 aus CIFS-Share hinzufügen (für Sonos erreichbar!)
            // Beispiel: \\LOXBERRY\sonos4lox\tts\<filename>.mp3
            $cifs_mp3 = rtrim($config['SYSTEM']['cifsinterface'], '/') . "/" . $t2s_param['filename'] . ".mp3";
            LOGDEB("Play_T2S.php: Adding TTS file to queue: " . $cifs_mp3);
            $sonos->AddToQueue($cifs_mp3);

            // Set the zone queue.
            $sonos->SetQueue("x-rincon-queue:" . trim($coord_rinc) . "#0");
            $sonos->SetPlayMode('0');
            LOGDEB("Play_T2S.php: Playmode has been set to NORMAL");

            // The announcement is the first and only queue item.
            $message_pos = 1;
            $sonos->SetTrack($message_pos);
            LOGDEB("Play_T2S.php: Message has been set to position '$message_pos' in current queue");

            // Unmute and set volume if it is not controlled by member/profile handling.
            $sonos->SetMute(false);
            if (!isset($_GET['member']) && !isset($_GET['profile'])) {
                $sonos->SetVolume((int)$volume);
            }
            LOGDEB("Play_T2S.php: Mute for relevant player(s) has been turned off");

            // Start playback.
            try {
                $sonos->Play();
                LOGOK("Play_T2S.php: TTS playback started successfully.");
            } catch (Exception $e) {
                LOGERR("Play_T2S.php: Failed to start TTS playback: " . $e->getMessage());
                return $t2s_param;
            }

            // ===========================
            // Wait until the announcement has finished.
            // ===========================
            // Estimate the maximum wait time from text length.
            $approxSeconds = max(2, min(30, (int)ceil(strlen($textstring) / 12) + 1));
            $max_wait      = max($approxSeconds, (int)ceil($minimum_wait_seconds) + 1);
            $start         = microtime(true);
            $seen_playing  = false;

            if ($minimum_wait_seconds > 0.0) {
                LOGDEB("Play_T2S.php: Waiting up to {$max_wait}s for TTS playback to finish; minimum observation window is {$minimum_wait_seconds}s.");
            } else {
                LOGDEB("Play_T2S.php: Waiting up to {$max_wait}s for TTS playback to finish.");
            }

            while (true) {
                usleep(200000); // Check every 200ms.

                // Timeout guard.
                $elapsed = microtime(true) - $start;
                if ($elapsed > $max_wait) {
                    LOGWARN("Play_T2S.php: Wait timeout ({$max_wait}s) reached while TTS was playing – continuing.");
                    break;
                }

                try {
                    $state = $sonos->GetTransportInfo();
                } catch (Exception $e) {
                    LOGERR("Play_T2S.php: GetTransportInfo() failed during wait loop: " . $e->getMessage());
                    break;
                }

                $playing = s4lox_play_t2s_transport_is_playing($state);

                if ($playing) {
                    $seen_playing = true;
                    continue;
                }

                // Radio announcements are often followed immediately by SetRadio().
                // Some Sonos players briefly report STOPPED while switching from a
                // radio stream to queue playback. Do not clear the queue during this
                // minimum observation window, otherwise the generated MP3 can be
                // removed before it becomes audible.
                if ($minimum_wait_seconds > 0.0 && $elapsed < $minimum_wait_seconds) {
                    LOGDEB("Play_T2S.php: Transport state is not PLAYING yet; keeping TTS queue active during the minimum observation window.");
                    continue;
                }

                if (!$seen_playing && $minimum_wait_seconds > 0.0) {
                    LOGWARN("Play_T2S.php: TTS playback was not observed as PLAYING within the minimum observation window; continuing carefully.");
                }

                LOGDEB("Play_T2S.php: Transport state no longer PLAYING – TTS finished or stopped.");
                $sonos->ClearQueue();
                break;
            }

            LOGOK("Play_T2S.php: TTS playback finished (or timeout reached).");

        } catch (Exception $e) {
            LOGERR("Play_T2S.php: Error while preparing/playing TTS queue: " . $e->getMessage());
        }

        return $t2s_param;
    }
}



/**
* Function : handle_prio() --> get prio for clip from URL 
*
* @param: 
* @return: 
**/

function handle_prio() {
	
	global $prio, $time_start;

	$high_enabled = false;

	if (array_key_exists('high', $_GET)) {
		$high_value = $_GET['high'];

		if (is_array($high_value)) {
			$high_value = reset($high_value);
		}

		$high_value = strtolower(trim((string)$high_value));
		$high_enabled = ($high_value === '' || !in_array($high_value, array('0', 'false', 'off', 'no'), true));
	}
	
	if($high_enabled) {
		$prio = "HIGH";
		LOGDEB("Play_T2S.php: Audioclip: Priority for Notification has been set to HIGH");
	} else {
		$prio = "LOW";
		LOGDEB("Play_T2S.php: Audioclip: Standard Priority LOW for Notification will be used " );
	}
	return $prio;
}


/**
* Function : check_file() --> check if filename is available 
*
* @param: 
* @return: 
**/

function check_file() {
     
    global $sonos, $config, $filename, $messageid, $time_start;
    
    // Für playbatch gibt es keine einzelne TTS-Datei – Batch wird später über Queue abgearbeitet
    if (isset($_GET['playbatch'])) {
        LOGDEB("Play_T2S.php: Audioclip: check_file() skipped for playbatch – batch entries will be used directly.");
        return;
    }

    // pre check for MP3 Stream
    if (isset($_GET['messageid']))  {
        $messageid     = $_GET['messageid'];
        $filenamecheck = $config['SYSTEM']['ttspath']."/mp3/".$messageid.".mp3";
    } else {
        $filenamecheck = $config['SYSTEM']['ttspath']."/".$filename.".mp3";
    }

    // Schutz: Wenn kein sinnvoller Dateiname vorhanden ist, nicht stumpf auf '.mp3' warten
    if (!isset($_GET['messageid']) && (empty($filename) || $filename === '')) {
        LOGDEB("Play_T2S.php: Audioclip: check_file() called without filename and no messageid – skipping wait loop.");
        return;
    }

    // check if T2S file has been successfully created, if not wait until finished (max ~20s)
    $wait_loops = 0;
    $max_loops  = 100; // 100 * 200ms = 20 Sekunden

	while ($wait_loops < $max_loops):

		clearstatcache(false, $filenamecheck);

		// Datei existiert und hat > 0 Byte -> fertig
		if (file_exists($filenamecheck) && filesize($filenamecheck) > 0) {
			LOGINF("Play_T2S.php: Audioclip: Notification file '$filenamecheck' is ready after ~" . ($wait_loops * 0.2) . " seconds.");
			break;
		}

		LOGDEB("Play_T2S.php: Audioclip: Notification creation not yet finished, we have to wait...");
		usleep(200000); // 200ms
		$wait_loops++;

	endwhile;

	// Nach der Schleife: final prüfen
	clearstatcache(false, $filenamecheck);
	if (!file_exists($filenamecheck) || filesize($filenamecheck) == 0) {
		LOGERR("Play_T2S.php: Audioclip: Giving up waiting for notification file '$filenamecheck' after ~" . ($wait_loops * 0.2) . " seconds.");
		// Optional: hier könntest du noch $filename auf 't2s_not_available' umbiegen
		// oder die Notification ganz abbrechen
	}
	return;
}

	
	
function create_tts($text ='') {
	
    global $sonos, $config, $dist, $filename, $MessageStorepath, $errortext, $zones, $messageid,
           $textstring, $home, $time_start, $tmp_batch, $MP3path, $filenameplay, $textstring,
           $volume, $tts_stat;

    // setze 1 für virtuellen Texteingang (T2S Start)
    #$tts_stat = 1;
    #send_tts_source($tts_stat);

    // ----------------------------------------------------------
    // Optional Greeting (GREETINGS.* aus T2S-Textdatei)
    // ----------------------------------------------------------
    if (isset($_GET['greet']))  {
        $Stunden = intval(strftime("%H"));
        $TL = LOAD_T2S_TEXT();
        switch ($Stunden) {
            // Gruß von 04:00 bis 10:00h
            case $Stunden >=4 && $Stunden <10:
                $greet = $TL['GREETINGS']['MORNING_'.mt_rand (1, 5)];
            break;
            // Gruß von 10:00 bis 17:00h
            case $Stunden >=10 && $Stunden <17:
                $greet = $TL['GREETINGS']['DAY_'.mt_rand (1, 5)];
            break;
            // Gruß von 17:00 bis 22:00h
            case $Stunden >=17 && $Stunden <22:
                $greet = $TL['GREETINGS']['EVENING_'.mt_rand (1, 5)];
            break;
            // Gruß nach 22:00h
            case $Stunden >=22:
                $greet = $TL['GREETINGS']['NIGHT_'.mt_rand (1, 5)];
            break;
            default:
                $greet = "";
            break;
        }
    } else {
        $greet = "";
    }

    // ----------------------------------------------------------
    // Direktaufruf über messageid → kein TTS erzeugen
    // ----------------------------------------------------------
    if (isset($_GET['messageid'])) {
        $messageid = $_GET['messageid'];
        if (file_exists($config['SYSTEM']['mp3path']."/".$messageid.".mp3") === true)  {
            s4lox_play_t2s_log("Play_T2S.php: Messageid '".$messageid."' has been entered", 7);
        } else {
            s4lox_play_t2s_log("Play_T2S.php: The corrosponding messageid file '".$messageid.".mp3' does not exist or could not be played. Please check your directory or syntax!", 3);
            exit;
        }
        return;
    }

    $rampsleep = $config['TTS']['rampto'];

    // Basis-Textquelle bestimmen
    if (isset($_GET['text']))   {
        $text = $_GET['text'];
    } elseif ($text <> '') {
        $text;
    } else {
        $text = '';
    }

    // ----------------------------------------------------------
    // Addon-Handler (weather, clock, pollen, ...)
    // ----------------------------------------------------------
    if(isset($_GET['weather'])) {
        if (!s4lox_include_addon('weather-to-speech.php')) {
            exit;
        }
        $textstring = substr(w2s(), 0, 500);
        s4lox_play_t2s_log("Play_T2S.php: weather-to-speech addon has been called", 7);

    } elseif (isset($_GET['clock'])) {
        if (!s4lox_include_addon('clock-to-speech.php')) {
            exit;
        }
        $textstring = c2s();
        s4lox_play_t2s_log("Play_T2S.php: clock-to-speech addon has been called", 7);

    } elseif (isset($_GET['pollen'])) {
        if (!s4lox_include_addon('pollen-to-speach.php')) {
            exit;
        }
        $textstring = substr(p2s(), 0, 500);
        s4lox_play_t2s_log("Play_T2S.php: pollen-to-speech addon has been called", 7);

    } elseif (isset($_GET['warning'])) {
        if (!s4lox_include_addon('weather-warning-to-speech.php')) {
            exit;
        }
        $textstring = substr(ww2s(), 0, 500);
        s4lox_play_t2s_log("Play_T2S.php: weather-warning-to-speech addon has been called", 7);

    } elseif (isset($_GET['distance'])) {
        if (!s4lox_include_addon('time-to-destination-speech.php')) {
            exit;
        }
        $textstring = substr(tt2t(), 0, 500);
        s4lox_play_t2s_log("Play_T2S.php: time-to-destination-speech addon has been called", 7);

    } elseif (isset($_GET['abfall'])) {
        if (!s4lox_include_addon('waste-calendar-to-speech.php')) {
            exit;
        }
        $textstring = substr(muellkalender(), 0, 500);
        s4lox_play_t2s_log("Play_T2S.php: waste-calendar-to-speech addon has been called", 7);

    } elseif (isset($_GET['calendar'])) {
        if (!s4lox_include_addon('calendar-to-speech.php')) {
            exit;
        }
        $textstring = substr(calendar(), 0, 500);
        s4lox_play_t2s_log("Play_T2S.php: calendar-to-speech addon has been called", 7);

    } elseif (isset($_GET['sonos'])) {
        if (!s4lox_include_addon('sonos-to-speech.php')) {
            exit;
        }
        $textstring = s2s();
        $rampsleep = false;
        s4lox_play_t2s_log("Play_T2S.php: sonos-to-speech addon has been called", 7);

    } elseif ((!isset($_GET['text'])) && isset($_GET['playbatch'])) {
        // Batch-Playback: es sollen nur vorhandene MP3s aus der Batchdatei gespielt werden
        s4lox_play_t2s_log("Play_T2S.php: create_tts(): Skipping TTS generation for playbatch – using batch file only.", 7);
        return;

    } elseif ($text <> '') {
        if (empty($greet))  {
            $textstring = $text;
            s4lox_play_t2s_log("Play_T2S.php: Textstring has been entered", 7);
        } else {
            $textstring = $greet.". ".$text;
            s4lox_play_t2s_log("Play_T2S.php: Greeting + Textstring has been entered", 7);
        }
    }

    // ----------------------------------------------------------
    // Kein Text → Ende
    // ----------------------------------------------------------
    if (empty($textstring)) {
        s4lox_play_t2s_log("Play_T2S.php: No T2S text available after input processing. Aborting.", 3);
        return;
    }

    // encrypt MP3 file as MD5 Hash
    $filename  = md5($textstring);
    $ttspath   = rtrim($config['SYSTEM']['ttspath'], '/');
    $mp3_file  = $ttspath."/".$filename.".mp3";
    $wav_file  = $ttspath."/".$filename.".wav";
    $nocache   = !empty($_GET['nocache']);

    // ----------------------------------------------------------
    // TTS-Parameter zentral bauen (inkl. GET-Overrides)
    // ----------------------------------------------------------
    $t2s_param = create_t2s_param(
        $textstring,
        $filename,
        [], // keine Overrides
        'Play_T2S.php: create_tts()'
    );

    $primary_engine_code = (int)($t2s_param['t2sengine'] ?? ($config['TTS']['t2s_engine'] ?? 0));

    // Engine-Datei einbinden
    $primary_engine_code_tmp = select_t2s_engine($primary_engine_code);
	$primary_engine_code = $primary_engine_code_tmp['code'];
	$primary_engine_name = $primary_engine_code_tmp['name'];
	
    // ----------------------------------------------------------
    // Cache-Check
    // ----------------------------------------------------------
    if (file_exists($mp3_file) && !$nocache) {

        s4lox_play_t2s_log("Play_T2S.php: MP3 grabbed from cache: '$textstring' ", 6);

    } elseif (file_exists($wav_file) && !$nocache) {

        s4lox_play_t2s_log("Play_T2S.php: WAV grabbed from cache: '$textstring' ", 6);

    } else {

        // ======================================================
        // 1. Primäre TTS-Engine ausführen
        // ======================================================
        s4lox_play_t2s_log("Play_T2S.php: Primary TTS engine '$primary_engine_name' will be used for '$textstring'.", 6);

        if (function_exists('t2s')) {
            // Für ALLE Engines (inkl. Piper) gilt: t2s($t2s_param)
            t2s($t2s_param);
        } else {
            LOGERR("Play_T2S.php: t2s() is not defined for engine '$primary_engine_name'. Did you include the correct VoiceEngines file?");
        }

        // ======================================================
        // 2. Prüfung Primär-Engine → Fallback auf Piper
        // ======================================================
        clearstatcache(false, $mp3_file);

        if (!file_exists($mp3_file) || filesize($mp3_file) < 1) {

            if (file_exists($mp3_file)) {
                $heute      = date("Y-m-d");
                $time       = date("His");
                $failedname = $ttspath."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3";
                rename($mp3_file, $failedname);
                LOGERR("Play_T2S.php: Primary TTS engine failed, bad file has been renamed to: ".$failedname);
            } else {
                LOGERR("Play_T2S.php: Primary TTS engine failed, no MP3 file has been created at all.");
            }

            // --------------------------------------------------
            // 3. Piper-Fallback (Offline) – Code 9012
            // --------------------------------------------------
            $fallback_filename = $filename . "_piper";
            $fallback_mp3      = $ttspath."/".$fallback_filename.".mp3";
            $piperBinary       = '/usr/bin/piper';

            // Piper nur versuchen, wenn das Binary auch wirklich existiert
            if (is_executable($piperBinary)) {
                s4lox_play_t2s_log("Play_T2S.php: Trying local Piper fallback engine (code 9012)...", 5);
                if (file_exists($fallback_mp3)) {
                    s4lox_play_t2s_safe_unlink($fallback_mp3, 'Piper fallback MP3');
                }
                include_once("VoiceEngines/Piper.php");

                if (function_exists('t2s_piper')) {
                    t2s_piper($textstring, $fallback_filename);
                } else {
                    // Fallback auf die Legacy-Signatur t2s($text,$filename) aus deinem Piper.php
                    t2s($textstring, $fallback_filename);
                }

                clearstatcache(false, $fallback_mp3);

                if (file_exists($fallback_mp3) && filesize($fallback_mp3) > 0) {
                    LOGOK("Play_T2S.php: Piper fallback succeeded, using offline file '".$fallback_filename.".mp3'.");
                    $filename = $fallback_filename;
                } else {
                    LOGERR("Play_T2S.php: Piper fallback failed (no valid MP3). Using fallback file 't2s_not_available.mp3' if available.");
                    $filename = "t2s_not_available";
                    $src      = $config['SYSTEM']['mp3path']."/t2s_not_available.mp3";
                    $dst      = $ttspath."/t2s_not_available.mp3";
                    if (file_exists($src)) {
                        if (s4lox_play_t2s_safe_copy($src, $dst, "fallback file 't2s_not_available.mp3'")) {
                            LOGINF("Play_T2S.php: Fallback file 't2s_not_available.mp3' has been copied to TTS path.");
                        }
                    } else {
                        LOGERR("Play_T2S.php: Fallback file 't2s_not_available.mp3' not found. No audio will be played.");
                    }
                }
            } else {
                LOGWARN("Play_T2S.php: Piper fallback skipped – binary '$piperBinary' not found or not executable. Using fallback file 't2s_not_available.mp3' if available.");
                $filename = "t2s_not_available";
                $src      = $config['SYSTEM']['mp3path']."/t2s_not_available.mp3";
                $dst      = $ttspath."/t2s_not_available.mp3";
                if (file_exists($src)) {
                    if (s4lox_play_t2s_safe_copy($src, $dst, "fallback file 't2s_not_available.mp3'")) {
                        LOGINF("Play_T2S.php: Fallback file 't2s_not_available.mp3' has been copied to TTS path.");
                    }
                } else {
                    LOGERR("Play_T2S.php: Fallback file 't2s_not_available.mp3' not found. No audio will be played.");
                }
            }
        }

        echo $textstring;
        echo "<br>";
    }

    return $filename;
}


/**
* Function : play_tts --> play T2S or MP3 File
*
* @param: 	MessageID, Parameter zur Unterscheidung ob Gruppen oder Einzeldurchsage
* @return: empty
**/		

function play_tts($filename) {
	global $volume, $config, $dist, $messageid, $sonos, $text, $errortext, $lbphtmldir, $messageid, $sleeptimegong, $sonoszone, $sonoszonen, $master, $coord, $actual, $textstring, $zones, $time_start, $t2s_batch, $filename, $textstring, $home, $MP3path, $logpath, $try_play, $MessageStorepath, $filename, $tts_stat;
		
		if (defined('T2SMASTER'))   {
			$master = T2SMASTER;
		}
		
		$coord = getRoomCoordinator($master);
		$sonos = new SonosAccess($coord[0]);
		if (isset($_GET['messageid'])) {
			// Set path if messageid
			s4lox_play_t2s_log("Play_T2S.php: Path for messageid's been adopted", 7);
			$messageid = $_GET['messageid'];
		} else {
			// Set path if T2S
			s4lox_play_t2s_log("Play_T2S.php: Path for T2S been adopted", 7);	
		}
		#print_r($actual);
		// if BEAM etc. is in Modus TV switch to Playlist 1st
		if (substr($actual[$master]['PositionInfo']["TrackURI"], 0, 18) == "x-sonos-htastream:")  {  
			$sonos->SetQueue("x-rincon-queue:".$coord[1]."#0");
			s4lox_play_t2s_log("Play_T2S.php: TV was playing", 7);		
		}
		// Playlist is playing
		$save_plist = count($sonos->GetCurrentPlaylist());
		
		// if Playlist has more then 998 entries
		if ($save_plist > 998) {
			// save temporally playlist
			SavePlaylist();
			$sonos->ClearQueue();
			s4lox_play_t2s_log("Play_T2S.php: Queue has been cleared", 7);		
			$message_pos = 1;
			s4lox_play_t2s_log("Play_T2S.php: Playlist has more then 998 songs", 6);		
		}
		// if Playlist has more then 1 or less then 999 entries
		if ($save_plist >= 1 && $save_plist <= 998) {
			$message_pos = count($sonos->GetCurrentPlaylist()) + 1;
		} else {
			// No Playlist is playing
			$message_pos = 1;
		}
			
		// Playgong/jingle to be played upfront
		if(isset($_GET['playgong'])) {
			$playgongValue = trim((string)($_GET['playgong'] ?? ''));
			if (s4lox_play_t2s_is_invalid_disabled_playgong_request($playgongValue))	{
				s4lox_play_t2s_log("Play_T2S.php: 'playgong=no' could not be used in syntax, only 'playgong=yes', 'playgong' or 'playgong=file' are allowed", 3);
				exit;
			}
			if(empty($config['MP3']['file_gong'])) {
				s4lox_play_t2s_log("Play_T2S.php: Standard file for jingle is missing in Plugin config. Please maintain before usage.", 3);
				exit;	
			}
			if (!s4lox_play_t2s_is_standard_playgong_request($playgongValue)) {
				$file = $playgongValue;
				$file = $file.'.mp3';
				$valid = mp3_files($file);
				if ($valid === true) {
					$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($file);
					$sonos->AddToQueue($jinglepath);
					s4lox_play_t2s_log("Play_T2S.php: Individual jingle '".trim($file)."' added to Queue", 7);	
				} else {
					s4lox_play_t2s_log("Play_T2S.php: Entered jingle '".$file."' for playgong is not valid or nothing has been entered. Please correct your syntax", 3);
					exit;
				}
			} else {
				$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($config['MP3']['file_gong']);
				$sonos->AddToQueue($jinglepath);
				s4lox_play_t2s_log("Play_T2S.php: Standard jingle '".trim($config['MP3']['file_gong'])."' added to Queue", 7);	
			}
		}

		// if batch has been created add all T2S
		$filenamebatch = "/dev/shm/".LBPPLUGINDIR."/t2s_batch.txt";

		if (file_exists($filenamebatch) && isset($_GET['playbatch'])) {

			$t2s_batch = file($filenamebatch, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

			foreach ($t2s_batch as $t2s_value) {
				// Jede Zeile ist ein x-file-cifs://... Pfad ohne ".mp3"
				$sonos->AddToQueue($t2s_value . ".mp3");
			}

			s4lox_play_t2s_log("Play_T2S.php: Messages from batch file '".$filenamebatch."' have been added to Queue", 7);

		} else {
			// if no batch has been created add single T2S
			$t2s_file = file_exists($config['SYSTEM']['ttspath']."/".$filename.".mp3");
			$meid_file = file_exists($config['SYSTEM']['mp3path']."/".$messageid.".mp3");
			if (($t2s_file  === true) or ($meid_file  === true))  {
				if ($t2s_file  === true)  {
					// check if T2S has been saved/coded correctly
					if (filesize($config['SYSTEM']['ttspath']."/".$filename.".mp3") < 1)  {
						$heute = date("Y-m-d"); 
						$time = date("His"); 
						if (is_enabled($config['SYSTEM']['checkt2s']))  {
							rename($config['SYSTEM']['ttspath']."/".$filename.".mp3", $config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");
							LOGERR("Play_T2S.php: Something went wrong :-( the message has not been saved. The bad file has been renamed to: ".$config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");	
							LOGERR("Play_T2S.php: Please check...");
							LOGERR("Play_T2S.php: ...your internet connection");	
							LOGERR("Play_T2S.php: ...your storage device");	
							LOGERR("Play_T2S.php: ...your T2S Engine settings");	
							LOGERR("Play_T2S.php: Please try your requested URL in a browser or change temporally the T2S provider.");	
							LOGINF("Play_T2S.php: If no success at all please add a thread in Loxone Forum");	
							LOGOK("Play_T2S.php: Exception message has been announced!");	
							$filename = "t2s_not_available";
							s4lox_play_t2s_safe_copy($config['SYSTEM']['mp3path']."/t2s_not_available.mp3", $config['SYSTEM']['ttspath']."/t2s_not_available.mp3", "TTS error fallback file 't2s_not_available.mp3'");
						} else {
							rename($config['SYSTEM']['ttspath']."/".$filename.".mp3", $config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");
							LOGERR("Play_T2S.php: Something went wrong :-( the message has not been saved. The bad file has been renamed to: ".$config['SYSTEM']['ttspath']."/".$filename."_FAILED_T2S_".$heute."_".$time.".mp3");	
							LOGERR("Play_T2S.php: Please check...");
							LOGERR("Play_T2S.php: ...your internet connection");	
							LOGERR("Play_T2S.php: ...your storage device");	
							LOGERR("Play_T2S.php: ...your T2S Engine settings");	
							LOGERR("Play_T2S.php: Please try your requested URL in a browser or change temporally the T2S provider.");	
							LOGINF("Play_T2S.php: If no success at all please add a thread in Loxone Forum");	
							exit;
						}						
					}
					$sonos->AddToQueue($config['SYSTEM']['cifsinterface']."/".$filename.".mp3");
					s4lox_play_t2s_log("Play_T2S.php: T2S '".trim($filename).".mp3' has been added to Queue", 7);
				} else {
					$sonos->AddToQueue($config['SYSTEM']['cifsinterface']."/mp3/".$messageid.".mp3");
					s4lox_play_t2s_log("Play_T2S.php: MP3 File '".trim($messageid).".mp3' has been added to Queue", 7);
					$filename = $messageid;
				}
			} else {
				s4lox_play_t2s_log("Play_T2S.php: The file '".trim($filename).".mp3' does not exist or could not be played. Please check your directory or your T2S settings!", 3);
				exit;
			}
		}
		$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
		$sonos->SetPlayMode('0');
		s4lox_play_t2s_log("Play_T2S.php: Playmode has been set to NORMAL", 7);		
		$sonos->SetTrack($message_pos);
		s4lox_play_t2s_log("Play_T2S.php: Message has been set to Position '".$message_pos."' in current Queue", 7);		
		$sonos->SetMute(false);
		if(!isset($_GET['member']) && !isset($_GET['profile'])) {
			$sonos->SetVolume($volume);
		}
		s4lox_play_t2s_log("Play_T2S.php: Mute for relevant Player(s) has been turned off", 7);		
		try {
			$try_play = $sonos->Play();
			s4lox_play_t2s_log("Play_T2S.php: T2S has been passed to Sonos Application", 5);	
			s4lox_play_t2s_log("Play_T2S.php: In case the announcement wasn't played please check any Messages appearing in the Sonos App during processing the request.", 5);	
		} catch (Exception $e) {
			s4lox_play_t2s_log("Play_T2S.php: The requested T2S message ".trim($messageid).".mp3 could not be played!", 3);
			$notification = array(
				"PACKAGE"  => LBPPLUGINDIR,
				"NAME"     => "Sonos",
				"MESSAGE"  => "The requested T2S message could not be played!",
				"SEVERITY" => 3,
				"fullerror"=> "the received error: ".$try_play,
				"LOGFILE"  => LBPLOGDIR . "/sonos.log"
			);
			notify_ext($notification);
		}
		$abort = false;
		$sleeptimegong = "3";
		sleep($sleeptimegong); // wait according to config
		$transportWaitStart = microtime(true);
		while (true) {
			try {
				$transportState = $sonos->GetTransportInfo();
			} catch (Exception $e) {
				s4lox_play_t2s_log("Play_T2S.php: GetTransportInfo() failed after queue TTS playback: " . $e->getMessage(), 4);
				break;
			}

			if (!s4lox_play_t2s_transport_is_playing($transportState)) {
				break;
			}

			if ((microtime(true) - $transportWaitStart) > 30) {
				s4lox_play_t2s_log("Play_T2S.php: Wait timeout reached while queue TTS was still reported as playing – continuing cleanup.", 4);
				break;
			}

			usleep(200000); // check every 200ms
		}
		// If batch T2S has been be played
		if (!empty($t2s_batch))  {
			$i = $message_pos;
			foreach ($t2s_batch as $t2s => $value) {
				$mess_pos = $message_pos;
				$sonos->RemoveFromQueue($mess_pos);
				$i++;
			} 
			unlink ($filenamebatch);
			s4lox_play_t2s_log("Play_T2S.php: T2S batch files has been removed from Queue", 7);	
		} else {
			// If single T2S has been be played
			$sonos->RemoveFromQueue($message_pos);
			s4lox_play_t2s_log("Play_T2S.php: T2S has been removed from Queue", 7);	
			if(isset($_GET['playgong'])) {		
				$sonos->RemoveFromQueue($message_pos);
				s4lox_play_t2s_log("Play_T2S.php: Jingle has been removed from Queue", 7);	
			}	
		}	
		
		// if Playlist has more than 998 entries
		if ($save_plist > 998) {
			$sonos->ClearQueue();
			s4lox_play_t2s_log("Play_T2S.php: Queue has been cleared", 7);		
			LoadPlaylist("temp_t2s");
			s4lox_play_t2s_log("Play_T2S.php: Temporary saved playlist 'temp_t2s' has been loaded back into Queue", 7);		
			DelPlaylist();
			s4lox_play_t2s_log("Play_T2S.php: Temporary playlist 'temp_t2s' has been finally deleted", 7);		
		}
		
		$time_end = microtime(true);
		$t2s_time = $time_end - $time_start;
		s4lox_play_t2s_log("Play_T2S.php: The requested T2S took ".round($t2s_time, 2)." seconds to be played.", 5);	
		s4lox_play_t2s_log("Play_T2S.php: T2S play process has been successful finished", 6);
		return $actual;
}


/**
* Function : sendmessage --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendmessage($errortext = "") {
	global $text, $dist, $master, $messageid, $errortext, $logging, $textstring, $voice, $config, $actual, $zones, $volume, $source, $sonos, $coord, $time_start, $filename, $sonoszone, $sonoszonen, $tmp_batch, $mode, $MP3path, $tts_stat;
			
	S4L_PresenceGuard::assertTtsAllowed();
	if(isset($_GET['member'])) {
		sendgroupmessage();
		s4lox_play_t2s_log("Play_T2S.php: Member has been entered for a single Zone function, we switch to 'sendgroupmessage'. Please correct your syntax!", 4);
	}	

	$time_start = microtime(true);

	// ----------------------------------------------------------------------
	// AUTO-MODE: Prefer AudioClip for single T2S if possible
	//     → NICHT bei batch und NICHT bei playbatch
	// ----------------------------------------------------------------------
	if (!isset($_GET['batch']) && !isset($_GET['sonos']) && !isset($_GET['playbatch'])) {
		// Determine effective master (T2SMASTER overrides $master)
		$autoMaster = $master;
		if (defined('T2SMASTER')) {
			$autoMaster = T2SMASTER;
		}

		if (zone_supports_audioclip($autoMaster)) {
			s4lox_play_t2s_log(
				"Play_T2S.php: Audioclip: Master '" . $autoMaster .
				"' supports Audio Clip – switching to AudioClip single mode (AUTO).",
				6
			);
			// Re-use existing AudioClip path (creates TTS and sends AudioClip)
			sendAudioSingleClip($errortext);
			return;
		}
	}

	// ----------------------------------------------------------------------
	// Ende AUTO-MODE Single
	// ----------------------------------------------------------------------

	// if batch has been choosed save filenames to a txt file and exit
	if(isset($_GET['batch'])) {
		if((isset($_GET['volume'])) or (isset($_GET['rampto'])) or (isset($_GET['playmode'])) or (isset($_GET['playgong']))) {
			s4lox_play_t2s_log("Play_T2S.php: The parameter volume, rampto, playmode or playgong are not allowed to be used in conjunction with batch. Please remove from syntax!", 4);
			exit;
		}
		if (isset($_GET['messageid'])) {
			$messageid = $_GET['messageid'];
		} else {
			create_tts();
		}
		// creates file to store T2S filenames
		if (!s4lox_play_t2s_safe_mkdir(dirname(T2S_BATCHFILE), 0775, 'T2S batch directory')) {
			s4lox_play_t2s_log("Play_T2S.php: There is no T2S batch directory to be written!", 3);
			exit();
		}
		$filenamebatch = T2S_BATCHFILE;
		$file = fopen($filenamebatch, "a+");

		if($file == false ) {
			s4lox_play_t2s_log("Play_T2S.php: There is no T2S batch file to be written!", 3);
			exit();
		}
		if (strlen($filename) == '32') {
			fwrite($file, $config['SYSTEM']['cifsinterface']."/".$filename."\r\n");
			s4lox_play_t2s_log("Play_T2S.php: T2S '".$filename.".mp3' has been added to batch", 7);
			s4lox_play_t2s_log("Play_T2S.php: Please ensure to call later '...action=playbatch', otherwise the messages could be played uncontrolled", 5);					
		} else {
			fwrite($file, $config['SYSTEM']['cifsinterface']."/".$MP3path."/".$messageid."\r\n");
			s4lox_play_t2s_log("Play_T2S.php: Messageid '".$messageid."' has been added to batch", 7);
			s4lox_play_t2s_log("Play_T2S.php: Please ensure to call later '...action=playbatch', otherwise the messages could be played uncontrolled", 5);										
		}
		fclose($file);
		exit;
	}
	if (defined('T2SMASTER'))   {
		$master = T2SMASTER;
	}
	$sonos = new SonosAccess($sonoszone[$master][0]); 
	$return = getZoneStatus($master); // get current Zone Status (Single, Member or Master)
	$save = saveZonesStatus(); // saves all Zones Status
	if($return == 'member') {
		if(isset($_GET['sonos'])) { // check if Zone is Group Member, then abort
			s4lox_play_t2s_log("Play_T2S.php: The specified zone is part of a group! There are no information available.", 4);
			exit;
		}
	}
	create_tts($errortext);
	$sonos = new SonosAccess($sonoszone[$master][0]);
	// stop 1st before Song Name been played
	$test = $sonos->GetPositionInfo();
	if ($return == 'member') {
		$sonos->BecomeCoordinatorOfStandaloneGroup();  // in case Member then remove Zone from Group
		s4lox_play_t2s_log("Play_T2S.php: Zone '$master' has been removed from group", 6);		
	}
			
	if (substr($test['TrackURI'], 0, 18) == "x-sonos-htastream:") {
		$sonos->SetQueue("x-rincon-queue:". $sonoszone[$master][1] ."#0");
		s4lox_play_t2s_log("Play_T2S.php: Streaming/TV end successful", 7);		
	}
	if (!isset($_GET['sonos']))  {
		$sonos->Stop();
		usleep(200000);
	}
	$sonos = new SonosAccess($sonoszone[$master][0]); 
	$sonos->SetMute(false);
	play_tts($messageid);
	restoreSingleZone();
	$time_end = microtime(true);
	$t2s_time = $time_end - $time_start;
	proccessing_time();
}
	
/**
* Function : sendAudioSingleClip --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendAudioSingleClip($errortext = "") {
	
	global $config, $duration, $volume, $master, $filename, $messageid, $sonoszone, $sonos, $zones, $playstat, $time_start, $roomcord;
	
	if(isset($_GET['member'])) {
		$zones = $sonoszone[$master];
	} elseif (isset($_GET['profile'])) {
		$tmp = createArrayFromGroupProfile();
		$zones = $sonoszone[$tmp[0]];
		$master = $tmp[0];
	} else {
		$zones = $sonoszone[$master];
	}

	// determine if Player supports AUDIO_CLIP function
	if (isset($zones[11]) && is_enabled($zones[11]) && $zones[9] <> "1") {
		LOGDEB("Play_T2S.php: Audioclip: Player '". $master ."' does support Audio Clip.");
	} else {
		LOGERR("Play_T2S.php: Audioclip: Player '". $master ."' does not support Audio Clip! Please remove player from URL (zone=". $master ."&action= ....) or from Sound Profile");
		exit;
	}
	create_tts($errortext);
	playAudioClip();
	
	proccessing_time();	
}
	
	
/**
* Function : sendAudioMultiClip --> translate a text into speech for a single zone
*
* @param: Text or messageid (Number)
* @return: 
**/

function sendAudioMultiClip($errortext = "") {
	
	global $config, $volume, $master, $filename, $messageid, $sonoszone, $sonos, $time_start, $zones, $playstat, $roomcord, $profile_details, $zones_all;
	
	LOGDEB("Play_T2S.php: Audioclip: Notification for Player has been called.");
	
	$zones     = array();
	$tmp_zones = array();

	// === NEU: Fastpath für Group T2S via Sound Profile (ohne clip-Parameter) ===
	if (!isset($_GET['clip']) && isset($_GET['profile']) && !isset($_GET['paused']) && !isset($_GET['member'])) {

		$zones = getProfileZonesForAudioclip();
		if (empty($zones)) {
			LOGWARN("Play_T2S.php: Audioclip: No players resolved from Sound Profile '".$_GET['profile']."'. Falling back to legacy group path.");
			return; // sendgroupmessage() fällt dann in den klassischen Pfad zurück
		}
		$r = implode(',', $zones);
		s4lox_play_t2s_log("Play_T2S.php: Audioclip: Players ".$r." for audioclip retrieved from Profile (AUTO).", 7);

	// === Bestehende Logik: member=... ===
	} elseif (isset($_GET['member']) and !isset($_GET['paused'])) {

		$zones_all = $_GET['member'];
		$zones = array_merge($zones, audioclip_handle_members($zones_all));
		$zones = array_keys($zones);
		$r = implode(',', $zones);
		s4lox_play_t2s_log("Play_T2S.php: Audioclip: Players ".$r." for audioclip retrieved from URL", 7);

	// === Bestehende Logik: profile=... + clip (alte Multi-Clip-Profile) ===
	} elseif (isset($_GET['profile']) and !isset($_GET['paused']))   {

		$zones = createArrayFromGroupProfile();	
		$r = implode(',', $zones);
		s4lox_play_t2s_log("Play_T2S.php: Audioclip: Players ".$r." for audioclip retrieved from Profile", 7);

	// === Bestehende Logik: paused=1 ===
	} 
	if (isset($_GET['paused']))    {
		$zones = IdentPausedPlayers();
		$zones = array_keys($zones);
		$r = implode(',', $zones);
		s4lox_play_t2s_log("Play_T2S.php: Audioclip: Players ".$r." for audioclip retrieved from currently not streaming player", 7);
	}

	// Fähigkeiten prüfen + S1 rausfiltern (wie gehabt)
	foreach ($zones as $key)   {
		if(isset($sonoszone[$key][11]) && is_enabled($sonoszone[$key][11]) && $sonoszone[$key][9] <> "1") {
			LOGDEB("Play_T2S.php: Audioclip: Player '". $key ."' does support Audio Clip");
			array_push($tmp_zones, $key);
		} else {
			LOGWARN("Play_T2S.php: Audioclip: Player '". $key ."' does not support Audio Clip. The Player has been removed by plugin!");
		}
	}
	$zones = $tmp_zones;

	create_tts($errortext);
	playAudioClip();
	
	proccessing_time();
}


/**
* Function : doorbell --> playing file as doorbell
*
* @param: CHIME or messageid (Number)
* @return: 
**/

function doorbell() {

	global $config, $master, $sonos, $sonoszone, $zone_volumes, $time_start, $masterzone;

	if(isset($_GET['playgong'])) {
		LOGERR("Play_T2S.php: Audioclip: playgong could not be used in combination with function 'doorbell'");
		exit;
	}

	$time_start = microtime(true);
	$prio = "HIGH";
	$zonesdoor = array();

	if (isset($_GET['member']) and !isset($_GET['paused'])) {
		$zones_all = $_GET['member'];
		$zonesdoor = array_merge($zonesdoor, audioclip_handle_members($zones_all));
		$zones = array_keys($zonesdoor);
		$r = implode(',', $zones);
		s4lox_play_t2s_log("Play_T2S.php: Audioclip: Players for doorbell ".$r." retrieved from URL", 7);
	} elseif (isset($_GET['profile']) and !isset($_GET['paused']))   {
		$zones = createArrayFromGroupProfile();	
		$r = implode(',', $zones);
		s4lox_play_t2s_log("Play_T2S.php: Audioclip: Players for doorbell ".$r." retrieved from Profile", 7);
	} else {
		$zones[0] = MASTER;
	}
	if (isset($_GET['paused']))    {
		$zones = IdentPausedPlayers();
		$zones = array_keys($zones);
		$r = implode(',', $zones);
		s4lox_play_t2s_log("Play_T2S.php: Audioclip: Players for doorbell ".$r." retrieved from currently not streaming player", 7);
	}
	
	$tmp_zones = array();
	foreach ($zones as $key)   {
		// determine if Player is fully supported/partial supported  for AUDIO_CLIP
		if(isset($sonoszone[$key][11]) && is_enabled($sonoszone[$key][11]) && $sonoszone[$key][9] <> "1")    {
			array_push($tmp_zones, $key);
			LOGDEB("Play_T2S.php: Audioclip: Player '$key' does support Audio Clip (Doorbell)");
		} else {
			LOGWARN("Play_T2S.php: Audioclip: Player '". $key ."' does not support Audio Clip. The Player has been removed by plugin!");
		}
	}
	$zones = $tmp_zones;
	
	if (isset($_GET['file'])) {
		$file = $_GET['file'];
		$file = $file.'.mp3';
		$valid = mp3_files($file);
		if ($valid === true) {
			$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($file);
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: Doorbell '".trim($file)."' with Priority HIGH has been announced", 7);	
			audioclip_multi_post_request($zones, "CUSTOM", $prio, $jinglepath);
		} else {
			if ($_GET['file'] == "chime")   {
				s4lox_play_t2s_log("Play_T2S.php: Audioclip: Sonos build-in Doorbell CHIME with Priority HIGH has been announced", 7);	
				audioclip_multi_post_request($zones, "CHIME", $prio);
			} else {
				s4lox_play_t2s_log("Play_T2S.php: Audioclip: Entered file '".$file."' for doorbell is not valid or nothing has been entered. Please correct your syntax", 3);
				exit;
			}
		}
		sleep(3);
		if (isset($zone_volumes) && is_array($zone_volumes)) {
			foreach ($zone_volumes as $key => $value)   {
				if (!isset($sonoszone[$key][0])) {
					continue;
				}
				$sonos = new SonosAccess($sonoszone[$key][0]);
				$sonos->SetVolume($value);
			}
		} else {
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: Doorbell volume restore skipped because no previous zone volumes were captured.", 7);
		}
	} else {
		s4lox_play_t2s_log("Play_T2S.php: Audioclip: File for Doorbell is missing! Use even ...action=doorbell&file=chime or ...action=doorbell&file=<MP3 File from tts/mp3 Folder>", 3);
		exit;		
	}
	proccessing_time();
}



/**
* Function : handle_playgong --> Playgong/jingle to be played upfront
*
* @param: Zone or array of zones
* @return: 
**/
			
function handle_playgong($zones, $source) {	

	global $sonos, $config, $prio, $time_start;
		
	if(isset($_GET['playgong'])) {
			
		$playgongValue = trim((string)($_GET['playgong'] ?? ''));
		if (s4lox_play_t2s_is_invalid_disabled_playgong_request($playgongValue))	{
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: 'playgong=no' could not be used in syntax, only 'playgong=yes', 'playgong' or 'playgong=<file>' are allowed", 3);
			exit;
		}
		if(empty($config['MP3']['file_gong'])) {
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: Standard file for jingle is missing in Plugin config. Please maintain before usage.", 3);
			exit;	
		}
		if (!s4lox_play_t2s_is_standard_playgong_request($playgongValue)) {
			$file = $playgongValue;
			$file = $file.'.mp3';
			$valid = mp3_files($file);
			if ($valid === true) {
				// Replace whitespaces from filename
				$name = str_replace(" ", '%20', $file);
				$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($file);
				// check upfront if file is accessable
				if (s4lox_play_t2s_safe_file_get_contents($config['SYSTEM']['httpinterface']."/mp3/".$name, 'individual playgong file', false) === false)    {
					s4lox_play_t2s_log("Play_T2S.php: Audioclip: The provided playgong file could not be played due to unsupported characters or whitespaces in filename!! Please change filename accordingly", 3);	
					exit;
				}
				$duration = round(\falahati\PHPMP3\MpegAudio::fromFile($config['SYSTEM']['httpinterface']."/mp3/".$name)->getTotalDuration());
				if ($source === "multi")   {
					audioclip_multi_post_request($zones, "CUSTOM", $prio, $jinglepath);
				} else {
					audioclip_post_request($zones[0], $zones[1], "CUSTOM", $prio, $jinglepath);
				}
				s4lox_play_t2s_log("Play_T2S.php: Audioclip: Individual jingle '".trim($file)."' has been played as Playgong", 7);	
			} else {
				s4lox_play_t2s_log("Play_T2S.php: Audioclip: Entered jingle '".$file."' for playgong is not valid or nothing has been entered. Please correct your syntax", 3);
				exit;
			}
		} else {
			$jinglepath = $config['SYSTEM']['cifsinterface']."/mp3/".trim($config['MP3']['file_gong']);
			$name = str_replace(" ", '%20', $config['MP3']['file_gong']);
			if (s4lox_play_t2s_safe_file_get_contents($config['SYSTEM']['httpinterface']."/mp3/".$name, 'standard playgong file', false) === false)    {
				s4lox_play_t2s_log("Play_T2S.php: Audioclip: The standard playgong file could not be played due to unsupported characters or whitespaces in filename!! Please change filename accordingly", 3);	
				exit;
			}
			$duration = round(\falahati\PHPMP3\MpegAudio::fromFile($config['SYSTEM']['httpinterface']."/mp3/".$name)->getTotalDuration());
			if ($source === "multi")   {
				audioclip_multi_post_request($zones, "CUSTOM", $prio, $jinglepath);
			} else {
				audioclip_post_request($zones[0], $zones[1], "CUSTOM", $prio, $jinglepath);
			}
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: Standard file '".trim($config['MP3']['file_gong'])."' has been played as Playgong", 7);	
		}
		sleep($duration);
	}
	return;
}


/**
* Function : handle_message --> message to be played
*
* @param: Zone or array of zones
* @return: 
**/
			
function handle_message($zones, $source)
{
    global $config, $prio, $filename, $duration, $tmp_tts;

    if (isset($_GET['messageid'])) {
        $messageid = $_GET['messageid'];

        $mp3 = "REPLACELBHOMEDIR/data/plugins/sonos4lox/tts/mp3/{$messageid}.mp3";
        $duration = get_mp3_duration($mp3);
		#wait_for_global_audio_lock($duration);

        if ($source === "multi") {
            audioclip_multi_post_request(
                $zones,
                "CUSTOM",
                $prio,
                $config['SYSTEM']['cifsinterface']."/mp3/".$messageid.".mp3"
            );
        } else {
            audioclip_post_request(
                $zones[0],
                $zones[1],
                "CUSTOM",
                $prio,
                $config['SYSTEM']['cifsinterface']."/mp3/".$messageid.".mp3"
            );
        }
		usleep($duration);
		$tts_stat = 0;
		send_tts_source($tts_stat);
        s4lox_play_t2s_log("Play_T2S.php: Audioclip messageid played", 7);

    } else {
        $mp3 = "REPLACELBHOMEDIR/data/plugins/sonos4lox/tts/{$filename}.mp3";
        $duration = get_mp3_duration($mp3);
		#wait_for_global_audio_lock($duration);

		# Status senden
		$tts_stat = 1;
		send_tts_source($tts_stat);

		if ($source === "multi") {
            audioclip_multi_post_request(
                $zones,
                "CUSTOM",
                $prio,
                $config['SYSTEM']['cifsinterface']."/".$filename.".mp3"
            );
        } else {
            audioclip_post_request(
                $zones[0],
                $zones[1],
                "CUSTOM",
                $prio,
                $config['SYSTEM']['cifsinterface']."/".$filename.".mp3"
            );
        }
		usleep($duration);
		$tts_stat = 0;
		send_tts_source($tts_stat);
        LOGDEB("Play_T2S.php: TTS '{$filename}' played");
		
    }
	
}


/**
 * Helper : audioclip_can_handle_group
 *
 * Prüft, ob die aktuelle Anfrage (member=..., profile=..., paused=...)
 * komplett über AudioClip gefahren werden kann.
 *
 * Bedingungen:
 *  - alle Zielplayer existieren in $sonoszone / SONOSZONE
 *  - alle unterstützen AudioClip (via zone_supports_audioclip)
 *
 * @return array [bool $canClip, array $zones]
 */
function audioclip_can_handle_group()
{
    global $sonoszone, $master;

    // Batch- und Playbatch-Aufrufe NICHT per AudioClip abwickeln
    if (isset($_GET['batch']) || isset($_GET['playbatch'])) {
        LOGDEB("Play_T2S.php: Audioclip: batch/playbatch detected – falling back to classic T2S queue path.");
        return array(false, array());
    }

    $zones = array();

    // --- 1) member=... aus URL --------------------------------------------
    if (isset($_GET['member']) && !isset($_GET['paused'])) {

        $memberParam = trim($_GET['member'] ?? '');

        if ($memberParam === 'all') {
            // 'all' → alle bekannten Zonen (wie in audioclip_handle_members)
            foreach (SONOSZONE as $z => $zoneData) {
                $zones[] = $z;
            }
        } else {
            $members = explode(',', $memberParam);
            foreach ($members as $z) {
                $z = trim($z);
                if ($z !== '') {
                    $zones[] = $z;
                }
            }
        }

        // Master immer ergänzen, falls nicht explizit enthalten
        if (!in_array($master, $zones)) {
            $zones[] = $master;
        }

    // --- 2) profile=... → Zonen aus Sound-Profil --------------------------
    } elseif (isset($_GET['profile']) && !isset($_GET['paused'])) {

        // NEU: Zonen aus Sound Profil lesen (ohne SOAP/Volume-Seiteneffekte)
        $zones = getProfileZonesForAudioclip();

    // --- 3) paused=1 → alle pausierten Player -----------------------------
    } elseif (isset($_GET['paused'])) {

        if (function_exists('IdentPausedPlayers')) {
            $paused = IdentPausedPlayers();
            if (!empty($paused) && is_array($paused)) {
                $zones = array_keys($paused);
            }
        }

    } else {
        // weder member noch profile noch paused → kein AudioClip-Autopath
        return array(false, array());
    }

    // Duplikate entfernen
    $zones = array_values(array_unique($zones));

    if (empty($zones)) {
        return array(false, array());
    }

    // Fähigkeiten prüfen: jeder Player muss AudioClip können
    foreach ($zones as $z) {
		if (!isset($sonoszone[$z])) {
			s4lox_play_t2s_log("Play_T2S.php: Zone '$z' is currently not available. Maybe '$z' ist offline or Time restrictions are valid", 4);
			continue; // ← nicht return false, sondern überspringen
		}
		if (!zone_supports_audioclip($z)) {
			LOGINF("Play_T2S.php: Audioclip: Zone '$z' kein AudioClip → classic T2S path.");
			return array(false, $zones);
		}
	}
	// Duplikate/exkludierte Zonen sauber rausnehmen
	$zones = array_values(array_filter($zones, fn($z) => isset($sonoszone[$z])));
	if (empty($zones)) return array(false, array());
	return array(true, $zones);
	}


function sendgroupmessage() {	
        
	global $coord, $sonos, $text, $folfilePlOn, $sonoszone, $sonoszonen, $errortext, $member, $master, $zone, $messageid, $logging, $textstring, $voice, $config, $mute, $volume, $membermaster, $getgroup, $checkgroup, $time_start, $mode, $modeback, $actual, $errortext;
            
	S4L_PresenceGuard::assertTtsAllowed();
	$time_start = microtime(true);
            
	if(isset($_GET['batch'])) {
		s4lox_play_t2s_log("Play_T2S.php: The parameter batch is not allowed to be used in groups. Please use single message to prepare your batch!", 4);
		exit;
	}

	// Volume-Handling vorziehen (wird auch für AudioClip genutzt)
	if(isset($_GET['volume']) or isset($_GET['groupvolume']))  { 
		isset($_GET['volume']) ? $groupvolume = $_GET['volume'] : $groupvolume = $_GET['groupvolume'];
		if ((!is_numeric($groupvolume)) or ($groupvolume < 0) or ($groupvolume > 200)) {
			s4lox_play_t2s_log("Play_T2S.php: The entered volume of ".$groupvolume." must be even numeric or between 0 and 200! Please correct", 4);	
		} else {
			$volume = $groupvolume;
		}
	}

	if(isset($_GET['sonos'])) {
		s4lox_play_t2s_log("Play_T2S.php: The parameter 'sonos' couldn't be used for group T2S!", 4);
		exit;
	}

	if($sonoszone[$master][9] == "1") {
		LOGERR("Play_T2S.php: Player '". $master ."' is an Generation S1 player and can't be Master of a group! Please remove player from URL (zone=". $master ."&action= ....) or from Sound Profile marked as Master!");
		exit;
	}

	/**
	 * === AUDIOCLIP AUTO-MODE (gruppiert per AudioClip, wenn alle können) ===
	 */
	list($canClip, $zonesClip) = audioclip_can_handle_group();

	if ($canClip) {
		if (!empty($zonesClip)) {
			$targets = implode(',', $zonesClip);
			LOGINF("Play_T2S.php: Audioclip: All target players (".$targets.") support Audio Clip – switching to AudioClip group mode (AUTO).");
		} else {
			LOGINF("Play_T2S.php: Audioclip: All target players support Audio Clip – switching to AudioClip group mode (AUTO).");
		}

		sendAudioMultiClip($errortext);
		return;
	}

	/**
	 * === Klassischer Gruppen-T2S Pfad (Fallback) ============================
	 */

	// TTS erzeugen (oder messageid prüfen)
	create_tts($errortext);

	// Snapshot IMMER vor dem Umbauen der Gruppen
	$save = saveZonesStatus(); // saves all Zones Status

	// Sound-Profile können Master/Members überschreiben
	if (isset($_GET['profile'])) {
		// legt u.a. T2SMASTER und MEMBER fest, aber NOCH KEINE Volumes setzen
		$member = createArrayFromGroupProfile(false);
	}

	// T2SMASTER (aus Profil) übernimmt die Kontrolle über $master
	if (defined('T2SMASTER')) {
		$master = T2SMASTER;
	}

	// Ab hier ist $master der finale Szenario-Master
	$masterrincon = $sonoszone[$master][1];

	if (isset($member) && !defined('MEMBER')) {
		define("MEMBER", $member);
	}

	// ----------------------------------------------------------------------
	// Master IMMER erst entgruppieren → garantiert Single-Zone
	// ----------------------------------------------------------------------
	try {
		$sonos = new SonosAccess($sonoszone[$master][0]);
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGINF("Play_T2S.php: Player '".$master."' has been removed from existing Group (standalone for Group T2S).");
	} catch (Exception $e) {
		LOGWARN("Play_T2S.php: Could not prepare master '".$master."' as standalone group. Reason: ".$e->getMessage());
	}

	// ----------------------------------------------------------------------
	// Gruppierung aufbauen
	// ----------------------------------------------------------------------
	if (!isset($_GET['profile'])) {
		// Klassischer Weg (member=...)
		CreateMember();
	} else {
		// Profil-basierte Gruppierung: nur Zonen mit PlayerStatus-File
		foreach ($member as $zone) {
			$file = $folfilePlOn . $zone . ".txt";
			if (is_file($file)) {
				if (!isset($sonoszone[$zone])) { // ← NEU
					s4lox_play_t2s_log("Play_T2S.php: Zone '$master' is currently not available. Maybe '$master' ist offline or Time restrictions are valid", 5);
					continue;
				}

				// Master selbst nicht erneut einbinden
				if ($zone != $master) {
					try {
						// Zone ggf. erst aus bestehender Gruppe lösen
						$zmState = getZoneStatus($zone);
						$zSonos  = new SonosAccess($sonoszone[$zone][0]);

						if ($zmState == "master" || $zmState == "member") {
							$zSonos->BecomeCoordinatorOfStandaloneGroup();
							LOGINF("Play_T2S.php: Player '".$zone."' has been removed from existing Group before grouping to master '".$master."'.");
						}

						// Jetzt erst an neuen Master anhängen
						$zSonos->SetAVTransportURI("x-rincon:" . $masterrincon);
						s4lox_play_t2s_log("Play_T2S.php: Member '$zone' is now connected to Master Zone '$master'", 6);
						$zSonos->SetMute(false);

					} catch (Exception $e) {
						LOGWARN("Play_T2S.php: Member '$zone' could not be added to Master $master. Reason: ".$e->getMessage());
					}
				}
			} else {
				LOGDEB("Play_T2S.php: Player status file '$file' NOT found for zone '$zone' – skipping grouping.");
			}
		}

		// >>> HIER: Profil-Volumes NACH dem Gruppieren anwenden <<<
		if (!empty($member) && is_array($member)) {
			VolumeProfile($member);
		}
}


	

	// ----------------------------------------------------------------------
	// Queue vorbereiten und T2S abspielen
	// ----------------------------------------------------------------------
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$sonos->SetPlayMode('0'); 
	$sonos->SetQueue("x-rincon-queue:". $masterrincon ."#0");
	if (!isset($_GET['sonos']))  {
		$sonos->Stop();
		$sonos->SetVolume($volume);
	}

	// ggf. Gruppen-Volume setzen
	if (isset($groupvolume))  {
		$sonos->SetVolume($groupvolume);
	}
	volume_group();

	// T2S spielen
	play_tts($messageid);

	// Ursprungszustände wiederherstellen
	s4lox_play_t2s_log("Play_T2S.php: *** Restore previous settings will be called ***", 6);	
	restoreGroupZone();		
	s4lox_play_t2s_log("Play_T2S.php: *** Text-to-speech successful processed ***", 6);	
	proccessing_time();
}


/**
* New Function for T2S: t2s_playbatch --> allows T2S to be played in batch mode
*
* @param: empty
* @return: T2S
**/
function t2s_playbatch() {
    global $time_start;

    $filenamebatch = "/dev/shm/".LBPPLUGINDIR."/t2s_batch.txt";

    if (!file_exists($filenamebatch)) {
        s4lox_play_t2s_log("Play_T2S.php: There is no T2S batch file to be played! (".$filenamebatch.")", 4);
        exit();
    }

    // Kennzeichnen: das ist ein reiner Batch-Playback-Aufruf
    $_GET['playbatch'] = 1;

    say();
}

if (!function_exists('create_t2s_param')) {
    /**
     * create_t2s_param
     *
     * Zentraler Builder für das TTS-Parameter-Array, wie bisher in Play_T2S.php.
     *
     * Priorität (pro Feld):
     *   1. $override[...] (explizit vom Aufrufer)
     *   2. $_GET[...] (wenn ignore_get = false)
     *   3. $config['TTS'][...] inkl. messageLang Fallback-Logik
     *   4. Defaults (z.B. Sprache de-DE)
     *
     * @param string $textstring   Text, der gesprochen werden soll
     * @param string $filename     Zieldateiname (ohne Extension)
     * @param array  $override     Optionale Overrides:
     *                              - 't2sengine' : int
     *                              - 'language'  : string
     *                              - 'voice'     : string
     *                              - 'apikey'    : string
     *                              - 'secretkey' : string
     *                              - 'region'    : string
     *                              - 'ignore_get': bool (default false → GET darf überschreiben)
     * @param string $log_context  Kontext-String für s4lox_play_t2s_log(z.B. "Play_T2S.php: create_tts()")
     *
     * @return array $t2s_param
     */
    function create_t2s_param(
        string $textstring,
        string $filename,
        array $override = [],
        string $log_context = 'Play_T2S.php'
    ): array {
        global $config;

        $ignore_get = !empty($override['ignore_get']);

        // messageLang für Fallbacks (z.B. "de-DE-ElkeNeural")
        $messageLang = $config['TTS']['messageLang'] ?? '';

        // ----------------------------------------------------------
        // 1) Engine-Code (numerisch, wie bisher)
        // ----------------------------------------------------------
        if (array_key_exists('t2sengine', $override)) {
            $primary_engine_code = (int)$override['t2sengine'];
        } elseif (!$ignore_get && isset($_GET['t2sengine']) && $_GET['t2sengine'] !== '') {
            $primary_engine_code = (int)$_GET['t2sengine'];
        } else {
            $primary_engine_code = (int)($config['TTS']['t2s_engine'] ?? 0);
        }

        // ----------------------------------------------------------
        // 2) Sprache
        //    override.language > GET.language > Config.language > aus messageLang > "de-DE"
        // ----------------------------------------------------------
        if (isset($override['language']) && $override['language'] !== '') {
            $language = trim($override['language']);
        } elseif (!$ignore_get && isset($_GET['language']) && $_GET['language'] !== '') {
            $language = trim($_GET['language']);
        } else {
            $language = $config['TTS']['language'] ?? '';
            $language = trim($language);

            if ($language === '' && $messageLang !== '' && preg_match('/^([a-z]{2,3}-[A-Z]{2})/', $messageLang, $m)) {
                $language = $m[1]; // z.B. "de-DE" aus "de-DE-ElkeNeural"
            }
            if ($language === '') {
                $language = 'de-DE'; // Default
            }
        }

        // ----------------------------------------------------------
        // 3) Voice
        //    override.voice > GET.voice > Config.voice > messageLang > "<lang>-KatjaNeural"
        // ----------------------------------------------------------
        if (isset($override['voice']) && $override['voice'] !== '') {
            $voice = trim($override['voice']);
        } elseif (!$ignore_get && isset($_GET['voice']) && $_GET['voice'] !== '') {
            $voice = trim($_GET['voice']);
        } else {
            $voice = $config['TTS']['voice'] ?? '';
            $voice = trim($voice);

            if ($voice === '' && $messageLang !== '') {
                $voice = $messageLang; // z.B. "de-DE-ElkeNeural"
            }
            if ($voice === '') {
                $voice = $language . '-KatjaNeural';
            }
        }

        // ----------------------------------------------------------
        // 4) API-Key
        //    override.apikey > GET.apikey > per-Engine > global
        // ----------------------------------------------------------
        if (isset($override['apikey']) && $override['apikey'] !== '') {
            $apikey = trim($override['apikey']);
        } elseif (!$ignore_get && isset($_GET['apikey']) && $_GET['apikey'] !== '') {
            $apikey = trim($_GET['apikey']);
        } else {
            $apikey = $config['TTS']['apikey'] ?? '';
            if (!empty($config['TTS']['apikeys'][$primary_engine_code])) {
                $apikey = $config['TTS']['apikeys'][$primary_engine_code];
            }
            $apikey = trim($apikey);
        }

        // ----------------------------------------------------------
        // 5) SecretKey
        //    override.secretkey > GET.secretkey > per-Engine > global
        // ----------------------------------------------------------
        if (isset($override['secretkey']) && $override['secretkey'] !== '') {
            $secretkey = trim($override['secretkey']);
        } elseif (!$ignore_get && isset($_GET['secretkey']) && $_GET['secretkey'] !== '') {
            $secretkey = trim($_GET['secretkey']);
        } else {
            $secretkey = $config['TTS']['secretkey'] ?? '';
            if (!empty($config['TTS']['secretkeys'][$primary_engine_code])) {
                $secretkey = $config['TTS']['secretkeys'][$primary_engine_code];
            }
            $secretkey = trim($secretkey);
        }

        // ----------------------------------------------------------
        // 6) Region
        //    override.region > GET.region > Config.region > regionms > ""
        // ----------------------------------------------------------
        if (isset($override['region']) && $override['region'] !== '') {
            $region = trim($override['region']);
        } elseif (!$ignore_get && isset($_GET['region']) && $_GET['region'] !== '') {
            $region = trim($_GET['region']);
        } else {
            $region = $config['TTS']['region'] ?? '';
            if ($region === '' && !empty($config['TTS']['regionms'])) {
                $region = $config['TTS']['regionms'];
            }
            $region = trim($region);
        }

        // ----------------------------------------------------------
        // Debug log
        // Keep the primary prefix stable so log-based tests can verify
        // that the old "T2S Helper" context is gone.
        // ----------------------------------------------------------
        s4lox_play_t2s_log(
            "Play_T2S.php: Effective TTS params: engine=" . $primary_engine_code .
            ", lang=" . $language . ", voice=" . $voice,
            7
        );

        // ------------------
        // Rückgabe-Array 
        // ------------------
        $t2s_param = [
            'filename'   => $filename,
            'text'       => $textstring,
            't2sengine'  => $primary_engine_code,
            'voice'      => $voice,
            'language'   => $language,
            'apikey'     => $apikey,
            'secretkey'  => $secretkey,
            'region'     => $region,
        ];

        return $t2s_param;
    }
}


/**
* Function : send_tts_source --> sendet eine 1 zu Beginn von T2S und eine 0 am Ende
*
* @param: 0 oder 1
* @return: leer
**/

function send_tts_source($tts_stat)  {
	
	global $config, $tmp_tts, $sonoszone, $time_start, $sonoszonen, $master, $ms, $lbphtmldir;
	
	require_once $lbphtmldir . "/src/Core/Communication/io-modul.php";
	require_once $lbphtmldir . "/src/Core/Mqtt/phpMQTT.php";
	require_once "$lbphtmldir/src/Core/CommunicationMS.php";

	$tmp_tts = "/run/shm/s4lox_tmp_tts";
	#var_dump($tts_stat);
	if ($tts_stat == 1)  {
		if(!touch($tmp_tts)) {
			s4lox_play_t2s_log("Play_T2S.php: No permission to write file", 3);
			return;
		}
		$handle = fopen ($tmp_tts, 'w');
		fwrite ($handle, $tts_stat);
		fclose ($handle); 
	} 
	
	// check if Data transmission is switched off
	if(!is_enabled($config['LOXONE']['LoxDaten'])) {
		return;
	}
	
	if (empty($config['LOXONE']['UDP'])) {
		// Get the MQTT Gateway connection details from LoxBerry
		$creds     = mqtt_connectiondetails();
		// MQTT requires a unique client id
		$client_id = uniqid(gethostname()."_client");
		$mqtt      = new Bluerhinos\phpMQTT($creds['brokerhost'],  $creds['brokerport'], $client_id);
		$mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass']);
		$mqttstat = "1";
	} else {
		$mqttstat = "0";
	}
	
	// check if MS is fully configured
	if (!isset($ms[$config['LOXONE']['Loxone']])) {
		LOGERR ("Play_T2S.php: Your selected Miniserver from Sonos4lox Plugin config seems not to be fully configured. Please check your LoxBerry Miniserver config!") ;
		return;
	}
	
	// obtain selected Miniserver from Plugin config
	$my_ms      = $ms[$config['LOXONE']['Loxone']];
	$lox_ip     = $my_ms['IPAddress'];
	$lox_port   = $my_ms['Port'];
	$loxuser    = $my_ms['Admin'];
	$loxpassword= $my_ms['Pass'];
	$loxip      = $lox_ip.':'.$lox_port;
		
	$t2s_zones = array();
	if (isset($_GET['member']))   {
		$mem       = $_GET['member'];
		$t2s_zones = explode(",", $mem);
		array_push($t2s_zones, $master);
	} else {
		array_push($t2s_zones, $master);
	}
	foreach ($t2s_zones as $value)    {
		try {
			$data['t2s_'.$value] = $tts_stat;
			if ($mqttstat == "1")   {
				$err  = $mqtt->publish('Sonos4lox/t2s/'.$value, $data['t2s_'.$value], 0, 1);
				$err1 = $mqtt->publish('s4lox/t2s/'.$value, $data['t2s_'.$value], 0, 1);
			} else {			
				$handle = s4lox_play_t2s_safe_get_file_content("http://$loxuser:$loxpassword@$loxip/dev/sps/io/t2s_$value/$tts_stat", 'Loxone T2S status update');
			}
			// UDP senden, wenn Port konfiguriert
			if (!empty($config['LOXONE']['UDP'])) {
				sendUDP($tts_stat, 't2s_'.$value);
			}
		} catch (Exception $e) {
			LOGWARN("Play_T2S.php: Sending T2S notification for Zone '".$value."' failed, we skip here...");	
			return;
		}
	}
	if ($mqttstat == "1")   {
		$mqtt->close();
	}

	return;
}


function guidv4($data = null) {
	// Generate 16 bytes (128 bits) of random data or use the data passed into the function.
	$data = $data ?? random_bytes(16);
	assert(strlen($data) == 16);

	// Set version to 0100
	$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
	// Set bits 6-7 to 10
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80);

	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}


function audioclip_handle_members($member) {
	
	global $sonoszone, $sonoszonen, $time_start, $memberon, $profile_details, $master, $zones_all;

	$memberon = array();
	$members  = explode(',', $member);

	if (isset($_GET['profile']))   {
		checkGroupProfile();
		exit;
	}
	foreach (SONOSZONE as $zone => $zoneData) {
		if ($zone == $master)   {
			$memberon[$master] = $zoneData;
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: Player '".$master."' has been added", 5);
		}
		if ($member === 'all' || ($members && in_array($zone, $members))) {
			$zoneon = checkZoneOnline($zone);
			if ($zoneon === true and $master != $zone)  {
				$memberon[$zone] = $zoneData;
				s4lox_play_t2s_log("Play_T2S.php: Audioclip: Member '".$zone."' has been added", 5);
			}
		} 
	}
	$memberCount = max(0, count($memberon) - 1);
	s4lox_play_t2s_log("Play_T2S.php: Audioclip: ".$memberCount." Member has been identified (plus Master)", 7);
	return $memberon;
}

/**
 * Resolve the requested AudioClip volume for one target zone.
 *
 * Rules:
 * 1) Sound profile volume wins if present.
 * 2) Explicit URL volume/groupvolume is used for every target zone.
 * 3) keepvolume keeps the current volume per target zone.
 * 4) Without explicit volume, every target zone uses its own configured T2S volume.
 */
function audioclip_resolve_zone_volume($zone, $fallbackVolume = null) {

	global $sonoszone, $profile_zone_volumes, $min_vol;

	if (isset($profile_zone_volumes[$zone]) && is_numeric($profile_zone_volumes[$zone])) {
		return (int)$profile_zone_volumes[$zone];
	}

	if (isset($_GET['volume']) && $_GET['volume'] !== '' && is_numeric($_GET['volume'])) {
		return (int)$_GET['volume'];
	}

	if (isset($_GET['groupvolume']) && $_GET['groupvolume'] !== '' && is_numeric($_GET['groupvolume'])) {
		return (int)$_GET['groupvolume'];
	}

	if (isset($_GET['keepvolume']) && isset($sonoszone[$zone][0])) {
		try {
			$sonos = new SonosAccess($sonoszone[$zone][0]);
			$currentVolume = (int)$sonos->GetVolume();
			$minimumVolume = isset($min_vol) ? (int)$min_vol : 0;

			if ($currentVolume >= $minimumVolume) {
				return $currentVolume;
			}
			LOGINF("Play_T2S.php: Audioclip: Current volume for zone '".$zone."' is below threshold; using configured T2S volume.");
		} catch (Exception $e) {
			LOGWARN("Play_T2S.php: Audioclip: Could not read current volume for zone '".$zone."'; using configured T2S volume.");
		}
	}

	if (isset($sonoszone[$zone][3]) && is_numeric($sonoszone[$zone][3])) {
		return (int)$sonoszone[$zone][3];
	}

	if ($fallbackVolume !== null && is_numeric($fallbackVolume)) {
		return (int)$fallbackVolume;
	}

	return 20;
}

function s4lox_play_t2s_create_audioclip_curl_handle($url, $jsonData, array $headers) {
	$worker = curl_init();
	curl_setopt_array($worker, [
		CURLOPT_URL              => $url,
		CURLOPT_CONNECTTIMEOUT   => S4L_PLAY_T2S_CURL_CONNECT_TIMEOUT,
		CURLOPT_TIMEOUT          => S4L_PLAY_T2S_CURL_REQUEST_TIMEOUT,
		CURLOPT_NOSIGNAL         => true,
		CURLOPT_HEADER           => 0,
		CURLOPT_FOLLOWLOCATION   => 1,
		CURLOPT_POST             => 1,
		CURLOPT_POSTFIELDS       => $jsonData,
		CURLOPT_HTTPHEADER       => $headers,
		CURLOPT_SSL_VERIFYHOST   => false,
		CURLOPT_SSL_VERIFYPEER   => false,
		CURLOPT_SSL_VERIFYSTATUS => false,
		CURLOPT_RETURNTRANSFER   => 1,
		CURLOPT_USERAGENT        => "PHP",
		CURLOPT_SSL_ENABLE_ALPN  => false,
		CURLOPT_SSL_ENABLE_NPN   => false,
		CURLOPT_SSL_FALSESTART   => true,
		CURLOPT_TCP_NODELAY      => true,
		CURLOPT_IPRESOLVE        => CURL_IPRESOLVE_V4,
		CURLOPT_TCP_FASTOPEN     => true,
	]);
	return $worker;
}

function s4lox_play_t2s_audioclip_post_once($zoneName, $url, $jsonData, array $headers) {
	$ch = s4lox_play_t2s_create_audioclip_curl_handle($url, $jsonData, $headers);
	$result = curl_exec($ch);
	$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$error = curl_error($ch);
	curl_close($ch);

	return [
		'ok'       => ($result !== false && $httpCode === 200),
		'zone'     => $zoneName,
		'url'      => $url,
		'httpCode' => $httpCode,
		'error'    => $error,
		'content'  => $result,
	];
}

function audioclip_multi_post_request($zones, $clipType="CUSTOM", $priority="LOW", $tts="") {

	global $volume, $guid, $memberon, $time_start, $profile_zone_volumes;
	
	if (empty($zones)) return;

	$headers = [
		'Content-Type: application/json',
		'X-Sonos-Api-Key: '.$guid,
	];

	$mh = curl_multi_init();
	$handles = [];

	foreach ($zones as $zone) {
		
		$url = audioclip_zone_url($zone);
		if (!$url) {
			continue;
		}

		// ------------------------------------------------------
		// Effective volume handling:
		//  1) sound profile volume, if available
		//  2) explicit URL parameter &volume / &groupvolume
		//  3) &keepvolume reads the current volume per target zone
		//  4) without explicit volume: T2S volume per target zone
		// ------------------------------------------------------
		$baseVolume = audioclip_resolve_zone_volume($zone, $volume ?? null);
		$volForJson = audioclip_zone_max_volume($zone, $baseVolume);
		$jsonData = audiclip_json_data($volForJson, $clipType, $priority, $tts);

		$worker = s4lox_play_t2s_create_audioclip_curl_handle($url, $jsonData, $headers);
		$handleKey = (int)$worker;
		$handles[$handleKey] = [
			'zone' => (string)$zone,
			'url'  => $url,
			'json' => $jsonData,
		];
		curl_multi_add_handle($mh, $worker);
	}

	$curlMultiStarted = microtime(true);
	for (;;) {
		$still_running = null;
		do {
			$err = curl_multi_exec($mh, $still_running);
		} while ($err === CURLM_CALL_MULTI_PERFORM);
		if ($err !== CURLM_OK) {
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: cURL multi execution returned error code " . $err . ".", 4);
			break;
		}
		if ($still_running < 1) {
			break;
		}
		if ((microtime(true) - $curlMultiStarted) > S4L_PLAY_T2S_CURL_MULTI_TOTAL_TIMEOUT) {
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: cURL multi request timeout reached after " . S4L_PLAY_T2S_CURL_MULTI_TOTAL_TIMEOUT . " seconds.", 4);
			break;
		}
		$selectResult = curl_multi_select($mh, 1);
		if ($selectResult === -1) {
			usleep(100000);
		}
	}

	$results = [];
	$failedRequests = [];
	while (false !== ($info = curl_multi_info_read($mh))) {
		$handle = $info["handle"];
		$handleKey = (int)$handle;
		$meta = $handles[$handleKey] ?? [
			'zone' => 'unknown',
			'url'  => curl_getinfo($handle, CURLINFO_EFFECTIVE_URL),
			'json' => '',
		];
		$httpCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
		$content = curl_multi_getcontent($handle);

		if ($info["result"] !== CURLE_OK || $httpCode !== 200) {
			$errorText = curl_error($handle);
			if ($errorText === '') {
				$errorText = 'HTTP ' . $httpCode;
			}
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: cURL multi request failed for player '" . $meta['zone'] . "': " . $errorText . ". A single retry will be attempted.", 4);
			$failedRequests[] = $meta;
		}

		$results[$meta['url']] = $content;
		curl_multi_remove_handle($mh, $handle);
		curl_close($handle);
		unset($handles[$handleKey]);
	}
	curl_multi_close($mh);

	foreach ($failedRequests as $failedRequest) {
		for ($attempt = 1; $attempt <= S4L_PLAY_T2S_CURL_MULTI_RETRY_COUNT; $attempt++) {
			usleep(S4L_PLAY_T2S_CURL_MULTI_RETRY_DELAY_US);
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: Retry " . $attempt . " for player '" . $failedRequest['zone'] . "'.", 6);
			$retry = s4lox_play_t2s_audioclip_post_once($failedRequest['zone'], $failedRequest['url'], $failedRequest['json'], $headers);
			$results[$failedRequest['url']] = $retry['content'];
			if ($retry['ok']) {
				s4lox_play_t2s_log("Play_T2S.php: Audioclip: Retry " . $attempt . " for player '" . $failedRequest['zone'] . "' was successful.", 7);
				break;
			}
			$errorText = $retry['error'] !== '' ? $retry['error'] : ('HTTP ' . $retry['httpCode']);
			s4lox_play_t2s_log("Play_T2S.php: Audioclip: Retry " . $attempt . " for player '" . $failedRequest['zone'] . "' failed: " . $errorText . ".", 4);
		}
	}

	return $results;
}

/**
 * Helper: Check if a single zone supports AudioClip (AUTO mode)
 * Uses sonoszone[*][11] and excludes S1 (index 9 == "1")
 */
function zone_supports_audioclip($zone) {
	global $sonoszone;

	if (empty($zone) || !isset($sonoszone[$zone])) {
		return false;
	}

	$z = $sonoszone[$zone];

	// Index 11: capability flag (true/"1"/"true")
	$clipCapable = isset($z[11]) ? $z[11] : null;
	if (!is_enabled($clipCapable)) {
		return false;
	}

	// Index 9: 1 = S1 / not supported for AudioClip
	if (isset($z[9]) && $z[9] == "1") {
		return false;
	}

	return true;
}

/**
 * Helper: Check if all given zones support AudioClip
 */
function all_zones_support_audioclip(array $zones) {
	if (empty($zones)) {
		return false;
	}

	foreach ($zones as $zone) {
		if (!zone_supports_audioclip($zone)) {
			return false;
		}
	}

	return true;
}

/**
 * Determine TTS target zones for group announcements (AUTO mode)
 * WITHOUT triggering audioclip_handle_members() or any logging.
 */
function get_tts_target_zones_for_group() {

	global $master;

	// Case 1: member=… provided
	if (isset($_GET['member'])) {
		$zones_all = trim($_GET['member']);

		// Master immer hinzufügen
		$zones = explode(',', $zones_all);
		$zones[] = $master;

		// doppelte entfernen und neu indizieren
		$zones = array_values(array_unique($zones));

		return $zones;
	}

	// Case 2: profile=… provided → Profile liefert komplette Liste
	if (isset($_GET['profile'])) {
		$zones = createArrayFromGroupProfile();
		return $zones;
	}

	// Case 3: paused=1 → Zonen aus IdentPausedPlayers()
	if (isset($_GET['paused'])) {
		$paused = IdentPausedPlayers();   // liefert ASSOC array
		$zones  = array_keys($paused);    // wandeln in einfache Liste

		return $zones;
	}

	// Fallback → nur Master
	return [$master];
}



function audiclip_json_data($volume, $clipType="CUSTOM", $priority="LOW", $tts="") {
	
	global $time_start;
	
	// $volume wird vom Aufrufer (z.B. audioclip_multi_post_request)
	// bereits passend bestimmt (Profil / URL / Fallback).
	
	if ($clipType == "CUSTOM") {
		$jsonData = array(
			'name'     => "AudioClip",
			'appId'    => 'de.loxberry.sonos',
			'clipType' => "CUSTOM",
			'streamUrl'=> $tts,
			'priority' => $priority,
			'volume'   => $volume
		);
	}
	if ($clipType == "CHIME") {
		$jsonData = array(
			'name'     => "AudioClip",
			'appId'    => 'de.loxberry.sonos',
			'clipType' => "CHIME",
			'priority' => $priority,
			'volume'   => $volume
		);
	}

	$jsonDataEncoded = json_encode($jsonData);
	return $jsonDataEncoded;
}


function audioclip_zone_url($zone) {
	
	global $sonoszone, $time_start;

	$zoneData = $sonoszone[$zone] ?? null;
	if ($zoneData) return audioclip_url($zoneData[0], $zoneData[1]);

	return false;
}

function audioclip_zone_max_volume($zone, $requestedVolume) {
	global $sonoszone, $time_start;

	$zoneData = $sonoszone[$zone] ?? null;

	// Zone bekannt → Max-Volume vorhanden
	if ($zoneData) {
		$maxVolume   = isset($zoneData[5]) ? (int)$zoneData[5] : 200;
		$effective   = min((int)$requestedVolume, $maxVolume);

		// Detail-Log pro Zone
		LOGDEB(
			"Play_T2S.php: Audioclip: Effective volume for '".$zone.
			"' is ".$effective." (requested=".$requestedVolume.
			", max=".$maxVolume.")"
		);

		return $effective;
	}

	// Fallback, falls Zone nicht in sonoszone[] gefunden wird
	LOGDEB(
		"Play_T2S.php: Audioclip: No max volume known for zone '".$zone.
		"' – using requested volume ".$requestedVolume
	);

	return (int)$requestedVolume;
}


function audioclip_url($ip, $rincon) {
	global $time_start;
	
	return 'https://'.$ip.':1443/api/v1/players/'.$rincon.'/audioClip';
}

  
function audioclip_guid_selection() {
	global $guid, $time_start;
	
	return $guid = array(
		"guid1" => "622493a2-4877-496c-9bba-abcb502908a5",
		"guid2" => "123e4567-e89b-12d3-a456-426655440000",
	);
}
	

/**
* Funktion : audioclip_post_request --> POST to https url of player
*
* @param: 	$text, $greet
* @return: JSON
**/	
  
function audioclip_post_request($ip, $rincon, $clipType="CUSTOM", $priority="LOW", $tts="") {
	
	global $myLBip, $sonos, $volume, $lbhostname, $lbwebport, $filename, $streamUrl, $config, $guid, $time_start;
	
	// API Url
	$url = audioclip_url($ip, $rincon);
	
	// Get Volume (Backup)
	if (isset($_GET['volume']))    {
		$volume = $_GET['volume'];
	}
						
	// Initiate cURL.
	$ch = curl_init($url);

	if ($clipType == "CUSTOM")    {
		$jsonData = array(
			'name'              => "AudioClip",
			'appId'             => 'de.loxberry.sonos',
			'clipType'          => "CUSTOM",
			'httpAuthorization' => null,
			'clipLEDBehavior'   => 'NONE',
			'streamUrl'         => $tts,
			'priority'          => $priority,
			'volume'            => $volume
		);
	}
	if ($clipType == "CHIME")    {
		$jsonData = array(
			'name'              => "AudioClip",
			'appId'             => 'de.loxberry.sonos',
			'clipType'          => "CHIME",
			'httpAuthorization' => null,
			'clipLEDBehavior'   => 'NONE',
			'priority'          => $priority,
			'volume'            => $volume
		);
	}
		 
	// Encode the array into JSON.
	$jsonDataEncoded = json_encode($jsonData);

	// Tell cURL that we want to send a POST request.
	curl_setopt($ch, CURLOPT_POST, 1);
	 
	// Attach our encoded JSON string to the POST fields.
	curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
	 
	// Set the content type to application/json
	$headers = [
		'Content-Type: application/json',
		"Accept: application/json",
		'X-Sonos-Api-Key: '.$guid,
	];
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 

	// Accept peer SSL (HTTPS) certificate
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	
	// Request response from Call
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, S4L_PLAY_T2S_CURL_CONNECT_TIMEOUT);
	curl_setopt($ch, CURLOPT_TIMEOUT, S4L_PLAY_T2S_CURL_REQUEST_TIMEOUT);
	curl_setopt($ch, CURLOPT_NOSIGNAL, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
	// Execute the request
	$result = curl_exec($ch);
	
	// Request info/details from Call
	$info = curl_getinfo($ch);
	
	// was the request successful?
	if($result === false or $info['http_code'] != "200") {
		$result = json_decode($result, true);
		if (isset($result['_objectType'])) {
			$msg = "unknown";
			if (isset($result['wwwAuthenticate'])) {
				$split = explode(",", $result['wwwAuthenticate']);
				if (isset($split[2])) {
					$msg = $split[2];
				}
			}
			s4lox_play_t2s_log("Play_T2S.php: cURL AudioClip error: ".$result['errorCode']." ".$msg, 3);
			exit;
		} else {
			s4lox_play_t2s_log("Play_T2S.php: cURL AudioClip error: ".curl_error($ch), 3);
			exit;
		}
	}
	// close cURL
	curl_close($ch);
	return $result;
}


/**
* Function : checkGroupProfile --> check if selected Profile is a Group
*
* @param: 
* @return: true or false
**/

function checkGroupProfile()   {
	
	global $lbpconfigdir, $vol_config, $group, $profile_details;

	get_profile_details();
	if ($profile_details[0]['Group'] == "Group")   {
		$group = true;
	} else {
		$group = false;
	}
	return $group;
}


/**
* Function : createArrayFromGroupProfile --> create Array From Group Profile
*
* @param bool $applyVolume  Wenn true, werden die Profil-Volumes sofort gesetzt
* @return array
**/
function createArrayFromGroupProfile($applyVolume = true)   {

	global $lbpconfigdir, $profile_details, $vol_config, $zone, $master, $zone_volumes, $masterzone, $sonoszone, $memberincl;

	get_profile_details();

	switch($profile_details[0]['Group'])    {
	case 'Group';
		foreach (SONOSZONE as $player => $value)   {
			if (is_enabled($profile_details[0]['Player'][$player][0]['Master']))    {
				$master = $player;
			}
		}
		$memberincl = array();
		foreach (SONOSZONE as $zone => $ip) {
			if ($profile_details[0]['Player'][$zone][0]['Master'] == "true")   {
				$memberincl[0] = $zone;
				// check wether master Zone is Master of existing group
				$state = getZoneStatus($zone);
				if ($state == "master")   {
					$sonos = new SonosAccess(SONOSZONE[$zone][0]);
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGINF("Play_T2S.php: Player '".$zone."' has been removed from existing Group");
				}
				$masterzone = $zone;
			}
		}
		foreach (SONOSZONE as $zone => $ip) {
			// add member to Master
			if ($profile_details[0]['Player'][$zone][0]['Member'] == "true")   {
				array_push($memberincl, $zone);
			}
		}

		// VOLUMES HIER NUR NOCH WENN EXPLIZIT GEWÜNSCHT
		if ($applyVolume) {
			VolumeProfile($memberincl);
		}

		// in case of Group T2S remove master (1st element) from member group
		if (!isset($_GET['clip']) && !isset($_GET['action']) == "doorbell")   {
			array_shift($memberincl);
		}
		if (!defined('MEMBER')) {
			define("MEMBER", $memberincl);
		}
		if (!defined('T2SMASTER')) {
			define("T2SMASTER", $master);
		}
		LOGOK("Play_T2S.php: Array of Speakers from Sound Profile '".$_GET['profile']."' has been created");
		return $memberincl;
	break;
	
	case 'Single';
		foreach (SONOSZONE as $player => $value)   {
			// in case only master marked
			if (is_enabled($profile_details[0]['Player'][$player][0]['Master']))    {
				$memberincl[0] = $player;
			}
		}

		if ($applyVolume) {
			VolumeProfile($memberincl);
		}

		if (!defined('T2SMASTER')) {
			define("T2SMASTER", $memberincl[0]);
		}
		return $memberincl;
	break;
	
	case 'NoGroup';
		$memberincl[0] = MASTER;

		if ($applyVolume) {
			VolumeProfile($memberincl);
		}

		return $memberincl;
	break;
	}
}



/**
* Funktion : VolumeProfile --> set Volume for each player
*
* @param: array of player                        
* @return: 
**/

function VolumeProfile($memberincl)   {
	
	global $sonoszone, $profile_details, $profile_selected, $profile, $config, $masterzone, $memberincl, $zone_volumes;

	$zone_volumes = array();
	foreach ($memberincl as $key)  {
		try {
			$sonos = new SonosAccess($sonoszone[$key][0]);
			$zone_volumes[$key] = $sonos->GetVolume();
			// Set Volume	
			if ($profile_details[0]['Player'][$key][0]['Volume'] != "")	{
				$sonos->SetVolume($profile_details[0]['Player'][$key][0]['Volume']);
				$volume = $profile_details[0]['Player'][$key][0]['Volume'];
				LOGINF("Play_T2S.php: Volume for '".$key."' has been set to: ".$profile_details[0]['Player'][$key][0]['Volume']);
			} else {
				LOGWARN("Play_T2S.php: No Volume entered in Profile, so we could not set Volume");
			}
		} catch (Exception $e) {
			LOGERR("Play_T2S.php: Player '".$key."' does not respond. Please check your settings");
			continue;
		}
	}	
	return;
}
	

function get_profile_details()   {

	global $lbpconfigdir, $profile_details, $vol_config, $masterzone;

	$volprofil = $_GET['profile'];
	$volconfig = json_decode(file_get_contents($lbpconfigdir . "/" . $vol_config.".json"), TRUE);
	$profile_details = array_multi_search(strtolower($volprofil), $volconfig, $sKey = "");
	if (!$profile_details)   {
		LOGERR("Play_T2S.php: Entered Sound Profile '".$_GET['profile']."' in URL could not be found. Please check your entry!");
		exit(1);
	} else {
		LOGINF("Play_T2S.php: Sound Profile '".$_GET['profile']."' has been selected!");
	}
	return $profile_details;
}

/**
 * Helper: Resolve target zones from a Sound Profile for AudioClip AUTO mode
 *
 * - liest das JSON wie get_profile_details()
 * - liefert eine einfache Zoneliste (Master + Member) zurück
 * - baut zusätzlich $profile_zone_volumes[zone] = Profil-Lautstärke
 *   (wird NUR für AudioClip benutzt, kein SOAP-SetVolume!)
 */
function getProfileZonesForAudioclip()
{
    global $lbpconfigdir, $vol_config, $profile_zone_volumes;

    $profile_zone_volumes = array();

    if (!isset($_GET['profile'])) {
        return array();
    }

    $volprofil = $_GET['profile'];
    $volconfig = json_decode(file_get_contents($lbpconfigdir . "/" . $vol_config . ".json"), TRUE);
    $profile_details = array_multi_search(strtolower($volprofil), $volconfig, $sKey = "");

    if (!$profile_details) {
        LOGERR("Play_T2S.php: Entered Sound Profile '".$_GET['profile']."' in URL could not be found. Please check your entry!");
        return array();
    }

    $zones      = array();
    $masterZone = null;

    // Master + Member aus dem Profil sammeln und Profil-Lautstärken merken
    foreach (SONOSZONE as $player => $value) {

        // Master?
        if (isset($profile_details[0]['Player'][$player][0]['Master']) &&
            is_enabled($profile_details[0]['Player'][$player][0]['Master'])) {

            $masterZone = $player;
            $zones[]    = $player;

            if (isset($profile_details[0]['Player'][$player][0]['Volume']) &&
                $profile_details[0]['Player'][$player][0]['Volume'] !== "") {

                $profile_zone_volumes[$player] = (int)$profile_details[0]['Player'][$player][0]['Volume'];
            }

        // Member?
        } elseif (isset($profile_details[0]['Player'][$player][0]['Member']) &&
                  is_enabled($profile_details[0]['Player'][$player][0]['Member'])) {

            $zones[] = $player;

            if (isset($profile_details[0]['Player'][$player][0]['Volume']) &&
                $profile_details[0]['Player'][$player][0]['Volume'] !== "") {

                $profile_zone_volumes[$player] = (int)$profile_details[0]['Player'][$player][0]['Volume'];
            }
        }
    }

    // Fallback: Wenn im Profil kein Master markiert ist, nimm MASTER (falls definiert)
    if (!$masterZone && defined('MASTER')) {
        $zones[] = MASTER;
    }

    // Duplikate entfernen
    $zones = array_values(array_unique($zones));

    return $zones;
}

function get_mp3_duration(string $mp3_file): int
{
    require_once __DIR__ . "/src/Support/MP3/getid3/getid3.php";

    $add_time = 1; // safety buffer in seconds
    $getID3 = new getID3();
    $info = $getID3->analyze($mp3_file);

    $seconds = (float)($info['playtime_seconds'] ?? 0);
	// turn to microseconds
    return (int)round(($seconds + $add_time) * 1000000);
}


function wait_for_global_audio_lock(int $duration_ms): void
{
    $lockfile = "/run/shm/sonos4lox/sonos4lox_audio_global.lock";
    $now = (int)round(microtime(true) * 1000);

    // ensure directory exists
    s4lox_play_t2s_safe_mkdir(dirname($lockfile), 0777, 'global audio lock directory');

    // --- Phase 1: wait for previous playback ---
    if (file_exists($lockfile)) {
        $lockContent = s4lox_play_t2s_safe_file_get_contents($lockfile, 'global audio lock file', false);
        $until = $lockContent === false ? 0 : (int)trim($lockContent);
        if ($until > $now) {
            usleep(($until - $now) * 1000);
        }
    }

    // --- Phase 2: set new lock ---
    $new_until = (int)round(microtime(true) * 1000) + $duration_ms;
    if (file_put_contents($lockfile, $new_until, LOCK_EX) === false) {
        s4lox_play_t2s_log("Play_T2S.php: Could not write global audio lock file '" . $lockfile . "'.", 4);
    }
}



function proccessing_time()
{
    global $duration;

    $elapsed = microtime(true) - $GLOBALS['time_start'];
    $t2s_time = isset($duration) ? $elapsed - ($duration / 1000000) : $elapsed;
    s4lox_play_t2s_log("Play_T2S.php: The requested T2S/AudioClip took ".round($t2s_time, 2)." seconds to be processed completely.", 5);
}
