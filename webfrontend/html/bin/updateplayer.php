 <?php
	#header('Content-Type: application/json; charset=utf-8'); 
	
	require_once "loxberry_system.php";
	require_once "loxberry_web.php";
	require_once "loxberry_log.php";
	require_once LBPHTMLDIR."/Helper.php";
	require_once LBPHTMLDIR."/system/sonosAccess.php";
	
	$myConfigFolder = LBPCONFIGDIR;								// get config folder
	$myBinFolder = LBPBINDIR;									// get bin folder
	$myConfigFile = "s4lox_config.json";						// configuration file
	$off_file = LBPLOGDIR."/s4lox_off.tmp";						// path/file for Script turned off

	# check if script/Sonos Plugin is off
	if (file_exists($off_file)) {
		exit;
	}
	
	global $config, $result, $tmp_error, $sonos;
	
	echo "<PRE>";

	// open config file
	if (!file_exists(LBPCONFIGDIR.'/'.$myConfigFile)) {
		echo "<ERROR> The file s4lox_config.json could not be opened, please try again! We skip here!".PHP_EOL;
		exit(1);
	} else {
		$config = json_decode(file_get_contents(LBPCONFIGDIR . "/" . $myConfigFile), TRUE);
		if ($config === false)  {
			echo "<ERROR> The file s4lox_config.json could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file 's4lox_config.json' manually!".PHP_EOL;
			exit(1);
		}
		echo "<OK> Player config has been loaded.".PHP_EOL;
	}
	
	$sonoszonen = $config['sonoszonen'];
	#print_R($sonoszonen);
	
	#if (!copy(LBPCONFIGDIR.'/'.$myConfigFile, LBPCONFIGDIR.'/s4lox_config_backup.json')) {
		#echo "<ERROR> failed to copy $myConfigFile...".PHP_EOL;
	#} else {
		#echo '<OK> s4lox_config.json has been copied to s4lox_config_backup.json'.PHP_EOL;
	#}

	$port = 1400;
	$timeout = 3;	
	$res = "1";
		

	$sub = CheckSubSur("SW");		// check for SUB and get room
	$sur = CheckSubSur("LR");		// check for Surround and get room
	#print_r($sub);
	
	foreach ($sonoszonen as $zone => $player) {
		$ip = $sonoszonen[$zone][0];

		$handle = @stream_socket_client("$ip:$port", $errno, $errstr, $timeout);
		if($handle) {
			if (!isset($sonoszonen[$zone][6]))   {
				array_push($sonoszonen[$zone], '');
			}
			$mig = false;
			if (!isset($sonoszonen[$zone][7]))   {
				$info = json_decode(file_get_contents('http://' . $ip . ':1400/info'), true);
				# Preparing variables to update config
				$model = $info['device']['model'];
				$groupId = $info['groupId'];
				$modelDisplayName = $info['device']['modelDisplayName'];
				$householdId = $info['householdId'];
				$deviceId = $info['device']['serialNumber'];
				array_push($sonoszonen[$zone], $model, $groupId, $householdId, $deviceId);
				#$line = implode(',',$sonoszonen[$zone]);
				echo "<INFO> Update Zone ".$zone." by: ".$zone."[]=".$line."".PHP_EOL;
				$res = "0";
			} else {
				$info = json_decode(file_get_contents('http://' . $ip . ':1400/info'), true);
				$capabilities = $info['device']['capabilities'];
				$model = $info['device']['model'];
				$isSoundbar = isSoundbar($model) == true;
				$soundbarString = $isSoundbar ? "is Soundbar" : "no Soundbar";
				if (array_key_exists(11, $sonoszonen[$zone]))  {
					if ($sonoszonen[$zone][11] == "SB")  {
						$mig = true;
						echo "<INFO> Identified Zone ".$zone." as Soundbar to be migrated.".PHP_EOL;
						$sbvol = $sonoszonen[$zone][12];
						echo "<INFO> TV Monitor Volume '".$sbvol."' for Zone ".$zone." has been saved.".PHP_EOL;
						unset($sonoszonen[$zone][11]);
						unset($sonoszonen[$zone][12]);
					}
				}
				array_values($sonoszonen);

				if (!isset($sonoszonen[$zone][11]))  {
					$audioclip = in_array("AUDIO_CLIP", $capabilities);
					$sonoszonen[$zone][11] = "$audioclip";
					echo "<INFO> Updated identified Zone ".$zone." as ".($audioclip ? "" : "not ")."AUDIO_CLIP capable".PHP_EOL;
					$res = "0";
				}
				if (!isset($sonoszonen[$zone][12]))  {
					$voice = in_array("VOICE", $capabilities);
					$sonoszonen[$zone][12] = "$voice";
					echo "<INFO> Updated identified Zone ".$zone." as ".($voice ? "" : "not ")."VOICE capable".PHP_EOL;
					$res = "0";
				}
				if (!isset($sonoszonen[$zone][13]))  {
					if($isSoundbar) {
						array_push($sonoszonen[$zone], "SB");
						echo "<INFO> Updated identified ".$zone." as ".$soundbarString.PHP_EOL;
						if (!isset($sonoszonen[$zone][14]))  {
							if ($mig == true)  {
								array_push($sonoszonen[$zone], $sbvol); // TV vol migrated
								echo "<INFO> Updated identified Soundbar Zone ".$zone." with previous value ".$sbvol." for TV Vol".PHP_EOL;
							} else {
								array_push($sonoszonen[$zone], "15"); // TV vol SB default
								echo "<INFO> Updated identified Soundbar Zone ".$zone." with default 15 for TV Vol".PHP_EOL;
							}
						}
					} else {
						array_push($sonoszonen[$zone], "NOSB");
					}
					$res = "0";
				}
				# add SUB for Zone if assigned
				if ($sub != "false")  {
					if (array_key_exists($zone, $sub))   {
						if ($sonoszonen[$zone][8] != "SUB")   {
							$sonoszonen[$zone][8] = "SUB";
							echo "<INFO> Updated identified Zone ".$zone." as SUB capable".PHP_EOL;
							$res = "0";
						}
					} else {
						$sonoszonen[$zone][8] ="NOSUB";
					}						
				} else {
					$sonoszonen[$zone][8] ="NOSUB";
				}
				# add SURROUND for Zone if assigned
				if ($sur != "false")  {
					if (array_key_exists($zone, $sur))   {
						if ($sonoszonen[$zone][10] != "SUR")   {
							$sonoszonen[$zone][10] = "SUR";
							echo "<INFO> Updated identified Zone ".$zone." as SURROUND capable".PHP_EOL;
							$res = "0";
						}
					} else {
						$sonoszonen[$zone][10] ="NOSUR";
					}						
				} else {
					$sonoszonen[$zone][10] ="NOSUR";
				}
			}
		} else {
			if (!isset($sonoszonen[$zone][6]))   {
				if (!isset($sonoszonen[$zone][7]))   {
					$res = "2";
					#$line = implode(',',$sonoszonen[$zone]);
					#notify(LBPPLUGINDIR, "Sonos", "Update for Player '".$zone."' is required, but failed due to Offline Status. Please turn Player '".$zone."' on and restart your Loxberry to execute Daemon again/update Setup!", "error");
					echo "<WARNING> Check/update Player '".$zone."' failed! Please turn On all Players and restart your Loxberry.".PHP_EOL;
				}
			} else {
				$res = "3";
				#$line = implode(',',$sonoszonen[$zone]);
				echo "<OK> Player '".$zone."' seems to be offline, but main config is OK. Please Power On and reboot Loxberry".PHP_EOL;
			}
		}
		
	}
	# Migrate API keys
	if (array_key_exists('API-key',$config['TTS']))   {
		$config['TTS']['apikey'] = $config['TTS']['API-key'];
		$config['TTS']['secretkey'] = $config['TTS']['secret-key'];
		unset($config['TTS']['API-key']);
		unset($config['TTS']['secret-key']);
		echo "<OK> 'API-key' and 'secret-key' has been migrated to 'apikey' and 'secretkey'.".PHP_EOL;
	}
	
	# Migrate TV Monitor
		foreach ($sonoszonen as $zone => $player) {
			$ip = $sonoszonen[$zone][0];
			if (($sonoszonen[$zone][13] == "SB") and !isset($sonoszonen[$zone][14]['tvmonspeech']))   {
					$sonoszonen[$zone][14] = array('tvmonspeech' => $config['VARIOUS']['tvmonspeech'],
													'usesb' => "true",
													'tvvol' => $sonoszonen[$zone][14],
													'tvmonsurr' => $config['VARIOUS']['tvmonsurr'],
													'fromtime' => $config['VARIOUS']['fromtime'],
													'tvmonnight' => $config['VARIOUS']['tvmonnight'], 
													'tvmonnightsub' => "false",
													'tvmonnightsublevel' => "0"
													);
				echo "<OK> Settings for TV Monitor has been migrated. Please doublecheck settings for Zone '".$zone."'".PHP_EOL;
			}
		}
		if (isset($config['VARIOUS']['tvmonnight']))    {
			unset($config['VARIOUS']['tvmonnight']);
		}
		if (isset($config['VARIOUS']['tvmonspeech']))    {
			unset($config['VARIOUS']['tvmonspeech']);
		}
		if (isset($config['VARIOUS']['tvmonsurr']))    {
			unset($config['VARIOUS']['tvmonsurr']);
		}
		if (isset($config['VARIOUS']['fromtime']))    {
			unset($config['VARIOUS']['fromtime']);
		}

	#unset($config['sonoszonen']);
	$newsonoszonen['sonoszonen'] = $sonoszonen;
	$final = array_merge($config, $newsonoszonen);
	file_put_contents($myConfigFolder.'/'.$myConfigFile, json_encode($final, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));

	switch ($res) {
		case "0":	
			echo '<OK> Player update took place.'.PHP_EOL;
		break;
		case "1":	
			echo '<OK> Player config is up-to-date.'.PHP_EOL;
		break;
		case "2":	
			echo '<OK> Player config require update, but min. 1 Player seems to be Offline.'.PHP_EOL;
		break;
		case "3":	
			echo '<OK> Min. 1 Player seems to be Offline, but main config is up-to-date. Please turn on Player(s) and restart Loxberry'.PHP_EOL;
		break;
	}
	echo "<INFO> End of player update.";
	#print_r($sonoszonen);


?>