<?php
	/**
	 * Sonos4Lox - Player update helper
	 * Version: UPDATEPLAYER_CORE_RUNTIME_RELOCATION_V01_2026_06_12
	 *
	 * Relocation package for the existing player update helper.
	 * The file is now located in src/Core/Runtime.
	 *
	 * Changes:
	 * - Adds safe JSON config loading and atomic config writing with backup.
	 * - Adds timeout and validation for Sonos /info requests.
	 * - Prevents undefined variable/index warnings in migration paths.
	 * - Escapes chmod shell arguments and checks directories before chmod.
	 * - Closes socket handles after reachability checks.
	 * - Keeps PiperVoiceIndex rebuild, but shields config updates from Piper errors.
	 */

	#header('Content-Type: application/json; charset=utf-8');

	require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
	require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_web.php";
	require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";
	require_once LBPHTMLDIR."/Helper.php";
	require_once LBPHTMLDIR."/src/Core/Sonos/sonosAccess.php";
	require_once LBPHTMLDIR."/src/Support/Logger.php";

	if (!function_exists('s4lox_updateplayer_log')) {
		function s4lox_updateplayer_log($level, $message) {
			
			$levelMap = array(
				'ERROR' => S4L_Logger::LEVEL_ERROR,
				'FAIL' => S4L_Logger::LEVEL_ERROR,
				'WARNING' => S4L_Logger::LEVEL_WARNING,
				'WARN' => S4L_Logger::LEVEL_WARNING,
				'OK' => S4L_Logger::LEVEL_OK,
				'INFO' => S4L_Logger::LEVEL_INFO,
				'DEBUG' => S4L_Logger::LEVEL_DEBUG,
				'DEB' => S4L_Logger::LEVEL_DEBUG
			);
			$numericLevel = isset($levelMap[strtoupper((string)$level)]) ? $levelMap[strtoupper((string)$level)] : S4L_Logger::LEVEL_INFO;
			S4L_Logger::write($message, $numericLevel, __FILE__);
			echo '<'.$level.'> src/Core/Runtime/Updateplayer.php: '.$message.PHP_EOL;
		}
	}

	if (!function_exists('s4lox_updateplayer_load_config')) {
		function s4lox_updateplayer_load_config($configPath) {
			if (!file_exists($configPath) || !is_readable($configPath)) {
				s4lox_updateplayer_log('ERROR', "The file s4lox_config.json could not be opened, please try again. We skip here.");
				exit(1);
			}

			$rawConfig = file_get_contents($configPath);
			if ($rawConfig === false || trim($rawConfig) === '') {
				s4lox_updateplayer_log('ERROR', "The file s4lox_config.json could not be read or is empty. Please check/save your plugin config.");
				exit(1);
			}

			$config = json_decode($rawConfig, true);
			if (!is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
				s4lox_updateplayer_log('ERROR', "The file s4lox_config.json could not be parsed: ".json_last_error_msg().". Please check/save your plugin config or check the file manually.");
				exit(1);
			}

			return $config;
		}
	}

	if (!function_exists('s4lox_updateplayer_fix_config_permissions')) {
	function s4lox_updateplayer_fix_config_permissions($path) {
		if (!is_string($path) || $path === '' || !file_exists($path)) {
			return;
		}

		@chown($path, 'loxberry');
		@chgrp($path, 'loxberry');
		@chmod($path, 0664);
	}
}

	if (!function_exists('s4lox_updateplayer_write_config_atomic')) {
		function s4lox_updateplayer_write_config_atomic($configPath, $config) {
			$encodedConfig = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT);
			if ($encodedConfig === false) {
				s4lox_updateplayer_log('ERROR', 'Config could not be encoded as JSON: '.json_last_error_msg());
				return false;
			}

			$backupPath = $configPath.'.bak';
			if (file_exists($configPath) && !copy($configPath, $backupPath)) {
				s4lox_updateplayer_log('WARNING', "Could not create config backup '$backupPath'. Continuing with atomic write.");
			} else {
				s4lox_updateplayer_fix_config_permissions($backupPath);
			}

			$tmpPath = $configPath.'.tmp.'.getmypid();
			if (file_put_contents($tmpPath, $encodedConfig, LOCK_EX) === false) {
				s4lox_updateplayer_log('ERROR', "Could not write temporary config file '$tmpPath'.");
				return false;
			}

			/*
			 * Updateplayer may run as root from the daemon.
			 * The temporary file would then be root:root after rename().
			 * Set permissions before and after rename so the web UI user
			 * 'loxberry' can still save s4lox_config.json afterwards.
			 */
			s4lox_updateplayer_fix_config_permissions($tmpPath);

			if (!rename($tmpPath, $configPath)) {
				@unlink($tmpPath);
				s4lox_updateplayer_log('ERROR', "Could not replace config file '$configPath' with temporary config file.");
				return false;
			}

			s4lox_updateplayer_fix_config_permissions($configPath);
			s4lox_updateplayer_fix_config_permissions($backupPath);

			return true;
		}
	}

	if (!function_exists('s4lox_updateplayer_fetch_player_info')) {
		function s4lox_updateplayer_fetch_player_info($ip, $timeout) {
			$url = 'http://'.$ip.':1400/info';
			$context = stream_context_create(array(
				'http' => array(
					'timeout' => $timeout,
					'ignore_errors' => true
				)
			));

			$rawInfo = @file_get_contents($url, false, $context);
			if ($rawInfo === false || trim($rawInfo) === '') {
				s4lox_updateplayer_log('WARNING', "Could not read Sonos info from '$url'. Player is skipped for detail update.");
				return false;
			}

			$info = json_decode($rawInfo, true);
			if (!is_array($info) || json_last_error() !== JSON_ERROR_NONE) {
				s4lox_updateplayer_log('WARNING', "Invalid Sonos info JSON from '$url': ".json_last_error_msg().". Player is skipped for detail update.");
				return false;
			}

			if (!isset($info['device']) || !is_array($info['device'])) {
				s4lox_updateplayer_log('WARNING', "Sonos info from '$url' does not contain a valid device section. Player is skipped for detail update.");
				return false;
			}

			return $info;
		}
	}

	if (!function_exists('s4lox_updateplayer_chmod_recursive')) {
		function s4lox_updateplayer_chmod_recursive($path) {
			if (!is_string($path) || $path === '') {
				s4lox_updateplayer_log('WARNING', 'Skipped chmod because the configured path is empty.');
				return;
			}

			if (!is_dir($path)) {
				s4lox_updateplayer_log('WARNING', "Skipped chmod because '$path' is not an existing directory.");
				return;
			}

			system('chmod -R 0755 '.escapeshellarg($path), $returnCode);
			if ($returnCode !== 0) {
				s4lox_updateplayer_log('WARNING', "chmod returned code '$returnCode' for '$path'.");
			}
		}
	}

	if (!function_exists('s4lox_updateplayer_bool_to_legacy_string')) {
		function s4lox_updateplayer_bool_to_legacy_string($value) {
			return $value ? '1' : '';
		}
	}

	if (!function_exists('s4lox_updateplayer_rebuild_piper_index')) {
		function s4lox_updateplayer_rebuild_piper_index() {
			$piperIndexFile = LBPHTMLDIR.'/src/Support/PiperVoiceIndex.php';
			if (!file_exists($piperIndexFile)) {
				s4lox_updateplayer_log('WARNING', "Piper voice index file '$piperIndexFile' was not found. Piper index rebuild skipped.");
				return;
			}

			require_once $piperIndexFile;
			if (!class_exists('S4L_PiperVoiceIndex')) {
				s4lox_updateplayer_log('WARNING', 'Class S4L_PiperVoiceIndex was not found. Piper index rebuild skipped.');
				return;
			}

			try {
				S4L_PiperVoiceIndex::rebuildIndex(
					LBPHTMLDIR.'/VoiceEngines/piper-voices/',
					LBPHTMLDIR.'/VoiceEngines/langfiles/piper.json',
					LBPHTMLDIR.'/VoiceEngines/langfiles/piper_voices.json'
				);
				s4lox_updateplayer_log('OK', 'Piper voice index has been rebuilt.');
			} catch (Throwable $e) {
				s4lox_updateplayer_log('WARNING', 'Piper voice index rebuild failed: '.$e->getMessage());
			}
		}
	}

	$myConfigFolder = LBPCONFIGDIR;								// get config folder
	$myBinFolder = LBPBINDIR;									// get bin folder
	$myConfigFile = 's4lox_config.json';					// configuration file
	$configPath = $myConfigFolder.'/'.$myConfigFile;
	$off_file = LBPLOGDIR.'/s4lox_off.tmp';				// path/file for Script turned off

	# check if script/Sonos Plugin is off
	if (file_exists($off_file)) {
		exit;
	}

	global $config, $result, $tmp_error, $sonos;

	echo '<PRE>';

	$config = s4lox_updateplayer_load_config($configPath);
	s4lox_updateplayer_log('OK', 'Player config has been loaded.');

	if (!isset($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
		s4lox_updateplayer_log('ERROR', "Config section 'sonoszonen' is missing or invalid. Player update skipped.");
		exit(1);
	}

	$sonoszonen = $config['sonoszonen'];
	#print_R($sonoszonen);

	$port = 1400;
	$timeout = 3;
	$res = '1';

	$sub = CheckSubSur('SW');		// check for SUB and get room
	$sur = CheckSubSur('LR');		// check for Surround and get room
	#print_r($sub);

	$ttspath = isset($config['SYSTEM']['ttspath']) ? $config['SYSTEM']['ttspath'] : '';
	$mp3path = isset($config['SYSTEM']['mp3path']) ? $config['SYSTEM']['mp3path'] : '';
	s4lox_updateplayer_chmod_recursive($ttspath);
	s4lox_updateplayer_chmod_recursive($mp3path);
	s4lox_updateplayer_log('INFO', 'Access rights for configured TTS/MP3 folders have been checked and updated where possible.');

	foreach ($sonoszonen as $zone => $player) {
		$ip = isset($sonoszonen[$zone][0]) ? $sonoszonen[$zone][0] : '';
		if ($ip === '') {
			s4lox_updateplayer_log('WARNING', "Zone '$zone' has no IP address configured. Player is skipped.");
			continue;
		}

		$handle = @stream_socket_client($ip.':'.$port, $errno, $errstr, $timeout);
		if ($handle) {
			fclose($handle);

			if (!isset($sonoszonen[$zone][6])) {
				array_push($sonoszonen[$zone], '');
			}

			$mig = false;
			$swgen = null;
			$info = s4lox_updateplayer_fetch_player_info($ip, $timeout);
			if ($info === false) {
				s4lox_updateplayer_log('WARNING', "Zone '$zone' is reachable on port $port, but /info could not be parsed. Existing config values are kept.");
				continue;
			}

			$device = $info['device'];
			$model = isset($device['model']) ? $device['model'] : '';
			$capabilities = isset($device['capabilities']) && is_array($device['capabilities']) ? $device['capabilities'] : array();
			$swgen = isset($device['swGen']) ? $device['swGen'] : null;

			if (!isset($sonoszonen[$zone][7])) {
				$groupId = isset($info['groupId']) ? $info['groupId'] : '';
				$householdId = isset($info['householdId']) ? $info['householdId'] : '';
				$deviceId = isset($device['serialNumber']) ? $device['serialNumber'] : '';
				array_push($sonoszonen[$zone], $model, $groupId, $householdId, $deviceId);
				$line = implode(',', $sonoszonen[$zone]);
				s4lox_updateplayer_log('INFO', "Updated Zone '".$zone."' by: ".$zone."[]=".$line);
				$res = '0';
			} else {
				$isSoundbar = isSoundbar($model) == true;
				$soundbarString = $isSoundbar ? 'is Soundbar' : 'no Soundbar';
				if (array_key_exists(11, $sonoszonen[$zone])) {
					if ($sonoszonen[$zone][11] == 'SB') {
						$mig = true;
						s4lox_updateplayer_log('INFO', "Identified Zone '$zone' as Soundbar to be migrated.");
						$sbvol = isset($sonoszonen[$zone][12]) ? $sonoszonen[$zone][12] : '15';
						s4lox_updateplayer_log('INFO', "TV Monitor Volume '$sbvol' for Zone '$zone' has been saved.");
						unset($sonoszonen[$zone][11]);
						unset($sonoszonen[$zone][12]);
					}
				}

				if (!isset($sonoszonen[$zone][11])) {
					$audioclip = in_array('AUDIO_CLIP', $capabilities);
					$sonoszonen[$zone][11] = s4lox_updateplayer_bool_to_legacy_string($audioclip);
					s4lox_updateplayer_log('INFO', "Updated identified Zone '$zone' as ".($audioclip ? '' : 'not ').'AUDIO_CLIP capable');
					$res = '0';
				}
				if (!isset($sonoszonen[$zone][12])) {
					$voice = in_array('VOICE', $capabilities);
					$sonoszonen[$zone][12] = s4lox_updateplayer_bool_to_legacy_string($voice);
					s4lox_updateplayer_log('INFO', "Updated identified Zone '$zone' as ".($voice ? '' : 'not ').'VOICE capable');
					$res = '0';
				}
				if (!isset($sonoszonen[$zone][13])) {
					if ($isSoundbar) {
						array_push($sonoszonen[$zone], 'SB');
						s4lox_updateplayer_log('INFO', "Updated identified '$zone' as $soundbarString");
						if (!isset($sonoszonen[$zone][14])) {
							if ($mig == true) {
								array_push($sonoszonen[$zone], $sbvol); // TV vol migrated
								s4lox_updateplayer_log('INFO', "Updated identified Soundbar Zone '$zone' with previous value '$sbvol' for TV Vol");
							} else {
								array_push($sonoszonen[$zone], '15'); // TV vol SB default
								s4lox_updateplayer_log('INFO', "Updated identified Soundbar Zone '$zone' with default 15 for TV Vol");
							}
						}
					} else {
						array_push($sonoszonen[$zone], 'NOSB');
					}
					$res = '0';
				}
				# add SUB for Zone if assigned
				if ($sub != 'false') {
					if (array_key_exists($zone, $sub)) {
						if (!isset($sonoszonen[$zone][8]) || $sonoszonen[$zone][8] != 'SUB') {
							$sonoszonen[$zone][8] = 'SUB';
							s4lox_updateplayer_log('INFO', "Updated identified Zone '$zone' as SUB capable");
							$res = '0';
						}
					} else {
						$sonoszonen[$zone][8] = 'NOSUB';
					}
				} else {
					$sonoszonen[$zone][8] = 'NOSUB';
				}
				# add SURROUND for Zone if assigned
				if ($sur != 'false') {
					if (array_key_exists($zone, $sur)) {
						if (!isset($sonoszonen[$zone][10]) || $sonoszonen[$zone][10] != 'SUR') {
							$sonoszonen[$zone][10] = 'SUR';
							s4lox_updateplayer_log('INFO', "Updated identified Zone '$zone' as SURROUND capable");
							$res = '0';
						}
					} else {
						$sonoszonen[$zone][10] = 'NOSUR';
					}
				} else {
					$sonoszonen[$zone][10] = 'NOSUR';
				}
			}

			# Update Software Version of Player
			if ($swgen !== null && (!isset($sonoszonen[$zone][9]) || ($sonoszonen[$zone][9] <> '2' && $sonoszonen[$zone][9] <> '1'))) {
				$sonoszonen[$zone][9] = (string)$swgen;
				s4lox_updateplayer_log('INFO', "Updated identified Zone '$zone' as S$swgen Version");
			}
		} else {
			if (!isset($sonoszonen[$zone][6])) {
				if (!isset($sonoszonen[$zone][7])) {
					$res = '2';
					#notify(LBPPLUGINDIR, "Sonos", "Update for Player '$zone' is required, but failed due to Offline Status. Please turn Player '$zone' on and restart your Loxberry to execute Daemon again/update Setup!", "error");
					s4lox_updateplayer_log('WARNING', "Check/update Player '$zone' failed. Please turn On all Players and restart your Loxberry.");
				}
			} else {
				$res = '3';
				s4lox_updateplayer_log('OK', "Player '$zone' seems to be offline, but main config is OK. Please Power On and reboot Loxberry.");
			}
		}
	}

	# Migrate API keys
	if (isset($config['TTS']) && is_array($config['TTS']) && array_key_exists('API-key', $config['TTS'])) {
		$config['TTS']['apikey'] = $config['TTS']['API-key'];
		$config['TTS']['secretkey'] = isset($config['TTS']['secret-key']) ? $config['TTS']['secret-key'] : '';
		unset($config['TTS']['API-key']);
		unset($config['TTS']['secret-key']);
		s4lox_updateplayer_log('OK', "'API-key' and 'secret-key' have been migrated to 'apikey' and 'secretkey'.");
	}

	# update cifs Interface path
	if (!isset($config['SYSTEM']) || !is_array($config['SYSTEM'])) {
		$config['SYSTEM'] = array();
	}
	unset($config['SYSTEM']['cifsinterface']);
	$myip = LBSystem::get_localip();
	$pluginDirName = isset($lbpplugindir) && $lbpplugindir !== '' ? $lbpplugindir : basename(LBPPLUGINDIR);
	$config['SYSTEM']['cifsinterface'] = 'x-file-cifs://'.$myip.'/plugindata/'.$pluginDirName.'/interfacedownload';

	# Update T2S Presence
	if (!isset($config['TTS']) || !is_array($config['TTS'])) {
		$config['TTS'] = array();
	}
	if (!isset($config['TTS']['presence'])) {
		$config['TTS']['presence'] = 'true';
	}

	# Migrate TV Monitor
	if (!isset($config['VARIOUS']) || !is_array($config['VARIOUS'])) {
		$config['VARIOUS'] = array();
	}

	foreach ($sonoszonen as $zone => $player) {
		$tvmonEnabled = isset($config['VARIOUS']['tvmon']) ? $config['VARIOUS']['tvmon'] : 'false';
		$zoneType = isset($sonoszonen[$zone][13]) ? $sonoszonen[$zone][13] : '';
		$tvMonitorSettings = isset($sonoszonen[$zone][14]) ? $sonoszonen[$zone][14] : null;
		$needsTvMonitorMigration = !is_array($tvMonitorSettings) || !isset($tvMonitorSettings['tvmonspeech']);

		if ($tvmonEnabled == 'true' && $zoneType == 'SB' && $needsTvMonitorMigration) {
			$sonoszonen[$zone][14] = array(
				'tvmonspeech' => isset($config['VARIOUS']['tvmonspeech']) ? $config['VARIOUS']['tvmonspeech'] : 'false',
				'usesb' => 'true',
				'tvvol' => $tvMonitorSettings,
				'tvmonsurr' => isset($config['VARIOUS']['tvmonsurr']) ? $config['VARIOUS']['tvmonsurr'] : 'false',
				'fromtime' => isset($config['VARIOUS']['fromtime']) ? $config['VARIOUS']['fromtime'] : '00:00',
				'tvmonnight' => isset($config['VARIOUS']['tvmonnight']) ? $config['VARIOUS']['tvmonnight'] : 'false',
				'tvmonnightsub' => 'false',
				'tvmonnightsublevel' => '0'
			);
			s4lox_updateplayer_log('OK', "Settings for TV Monitor have been migrated. Please double-check settings for Zone '$zone'.");
		}

		if (isset($sonoszonen[$zone][14]) && is_array($sonoszonen[$zone][14]) && isset($sonoszonen[$zone][14]['fromtime']) && strlen((string)$sonoszonen[$zone][14]['fromtime']) == 2) {
			$sonoszonen[$zone][14]['fromtime'] = $sonoszonen[$zone][14]['fromtime'].':00';
		}
	}

	if (isset($config['VARIOUS']['tvmonnight'])) {
		unset($config['VARIOUS']['tvmonnight']);
	}
	if (isset($config['VARIOUS']['tvmonspeech'])) {
		unset($config['VARIOUS']['tvmonspeech']);
	}
	if (isset($config['VARIOUS']['tvmonsurr'])) {
		unset($config['VARIOUS']['tvmonsurr']);
	}
	if (isset($config['VARIOUS']['fromtime'])) {
		unset($config['VARIOUS']['fromtime']);
	}

	if (isset($config['VARIOUS']['starttime']) && strlen((string)$config['VARIOUS']['starttime']) == 2) {
		$config['VARIOUS']['starttime'] = $config['VARIOUS']['starttime'].':00';
	}
	if (isset($config['VARIOUS']['endtime']) && strlen((string)$config['VARIOUS']['endtime']) == 2) {
		$config['VARIOUS']['endtime'] = $config['VARIOUS']['endtime'].':00';
	}

	$newsonoszonen['sonoszonen'] = $sonoszonen;
	$final = array_merge($config, $newsonoszonen);
	if (!s4lox_updateplayer_write_config_atomic($configPath, $final)) {
		s4lox_updateplayer_log('ERROR', 'Player config could not be written. Player update aborted.');
		exit(1);
	}

	switch ($res) {
		case '0':
			s4lox_updateplayer_log('OK', 'Player update took place.');
		break;
		case '1':
			s4lox_updateplayer_log('OK', 'Player config is up-to-date.');
		break;
		case '2':
			s4lox_updateplayer_log('OK', 'Player config requires update, but at least one Player seems to be Offline.');
		break;
		case '3':
			s4lox_updateplayer_log('OK', 'At least one Player seems to be Offline, but main config is up-to-date. Please turn on Player(s) and restart Loxberry.');
		break;
	}
	s4lox_updateplayer_log('INFO', 'End of player update.');

	s4lox_updateplayer_rebuild_piper_index();

	#print_r($sonoszonen);

?>
