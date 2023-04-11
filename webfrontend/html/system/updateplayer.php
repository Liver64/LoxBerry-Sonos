<?php
	
	require_once "loxberry_system.php";
	require_once "loxberry_log.php";
	require_once $lbphtmldir."/Helper.php";
	
	$myConfigFolder = "$lbpconfigdir";								// get config folder
	$myBinFolder = "$lbpbindir";									// get bin folder
	$myConfigFile = "player.cfg";									// get config file
	$off_file = $lbplogdir."/s4lox_off.tmp";					// path/file for Script turned off

	# check if script/Sonos Plugin is off
	if (file_exists($off_file)) {
		exit;
	}
	
	global $config, $result, $tmp_error;
	
	echo "<PRE>";
	#echo "<br>";;
	
	// Parse config file
	if (!file_exists($myConfigFolder.'/'.$myConfigFile)) {
		echo "<ERROR> The file player.cfg could not be opened, please try again! We skip here!".PHP_EOL;
		#echo "<br>";;
		exit(1);
	} else {
		$tmpplayer = parse_ini_file($myConfigFolder.'/'.$myConfigFile, true);
		if ($tmpplayer === false)  {
			echo "<ERROR> The file player.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file 'player.cfg' manually!".PHP_EOL;
			#echo "<br>";;
			exit(1);
		}
		echo "<OK> Player config has been loaded.".PHP_EOL;
		#echo "<br>";;
	}
		$player = ($tmpplayer['SONOSZONEN']);
		foreach ($player as $zonen => $key) {
			$sonoszonen[$zonen] = explode(',', $key[0]);
	} 

	#copy($myConfigFolder.'/'.$myConfigFile, $myConfigFolder.'/player_org_backup.cfg');
	if (!copy($myConfigFolder.'/'.$myConfigFile, $myConfigFolder.'/player_org_backup.cfg')) {
		echo "<ERROR> failed to copy $myConfigFile...".PHP_EOL;
		#echo "<br>";;
	} else {
		echo '<OK> player.cfg has been copied to player_org_backup.cfg'.PHP_EOL;
		#echo "<br>";;
	}
	$port = 1400;
	$timeout = 3;	
	$res = "0";
	
	foreach ($sonoszonen as $zone => $player) {
		$ip = $sonoszonen[$zone][0];

		$handle = @stream_socket_client("$ip:$port", $errno, $errstr, $timeout);
		if($handle) {
			$h = fopen($myConfigFolder.'/player_template.cfg', 'a');
			if (!isset($sonoszonen[$zone][6]))   {
				array_push($sonoszonen[$zone], '');
			}
			if (!isset($sonoszonen[$zone][7]))   {
				$info = json_decode(file_get_contents('http://' . $ip . ':1400/info'), true);
				# Preparing variables to update config
				$model = $info['device']['model'];
				$groupId = $info['groupId'];
				$modelDisplayName = $info['device']['modelDisplayName'];
				$householdId = $info['householdId'];
				$deviceId = $info['device']['serialNumber'];
				array_push($sonoszonen[$zone], $model, $groupId, $householdId, $deviceId);
				$line = implode(',',$sonoszonen[$zone]);
				echo "<INFO> Update Zone ".$zone." by: ".$zone."[]=".$line."".PHP_EOL;
				$res = "0";
				fwrite($h, $zone."[]=".$line."\n");
			} else {
				if (!isset($sonoszonen[$zone][11]))  {
					$info = json_decode(file_get_contents('http://' . $ip . ':1400/info'), true);
					# Preparing variables to update config
					$model = $info['device']['model'];
					if(isSoundbar($model) == true) {
						array_push($sonoszonen[$zone], "SB");	
						$line = implode(',',$sonoszonen[$zone]);
						echo "<INFO> Updated identified Zone ".$zone." as Soundbar - SB".PHP_EOL;
						$res = "0";
					}
				}
				$line = implode(',',$sonoszonen[$zone]);
				#echo "<OK> No update for Zone ".$zone." required.".PHP_EOL;
				$res = "1";
				fwrite($h, $zone."[]=".$line."\n");
				fclose($h);
			}
		} else {
			$h = fopen($myConfigFolder.'/player_template.cfg', 'a');
			if (!isset($sonoszonen[$zone][6]))   {
				if (!isset($sonoszonen[$zone][7]))   {
					$res = "2";
					$line = implode(',',$sonoszonen[$zone]);
					fwrite($h, $zone."[]=".$line."\n");
					notify(LBPPLUGINDIR, "Sonos", "Update for Player '".$zone."' is required, but failed due to Offline Status. Please turn Player '".$zone."' on and restart your Loxberry to execute Daemon again/update Setup!", "error");
					echo "<WARNING> Check/update Player '".$zone."' failed! Please turn On all Players and restart your Loxberry.".PHP_EOL;
				}
			} else {
				$res = "3";
				$line = implode(',',$sonoszonen[$zone]);
				fwrite($h, $zone."[]=".$line."\n");
				echo "<OK> Player '".$zone."' seems to be offline, but config is OK :-)".PHP_EOL;
			}
			fclose($h);
		}
		
	}
	if (!copy($myConfigFolder.'/player_template.cfg', $myConfigFolder.'/player.cfg')) {
		echo "<ERROR> failed to copy player_template.cfg...".PHP_EOL;
		#echo "<br>";;
	}
	if (!copy($lbphtmldir.'/bin/player_template.cfg', $myConfigFolder.'/player_template.cfg')) {
		echo "<ERROR> failed to copy player_template.cfg...".PHP_EOL;
		#echo "<br>";;
	}
	
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
			echo '<OK> Min. 1 Player seems to be Offline, but config is up-to-date.'.PHP_EOL;
		break;
	}
	echo "<INFO> End of player update.";



?>


