<?php
	
	require_once "loxberry_system.php";
	require_once "loxberry_log.php";
	
	$myConfigFolder = "$lbpconfigdir";								// get config folder
	$myBinFolder = "$lbpbindir";									// get bin folder
	$myConfigFile = "player.cfg";									// get config file
	
	global $config, $result, $tmp_error;
	
	#echo "<PRE>";
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
	$timeout = 1;	
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
				#echo "<br>";;
				fwrite($h, $zone."[]=".$line."\n");
				fclose($h);
			} else {
				$line = implode(',',$sonoszonen[$zone]);
				echo "<OK> No update for Zone ".$zone." neccessary.".PHP_EOL;
				$res = "1";
				#echo "<br>";;
				fwrite($h, $zone."[]=".$line."\n");
				fclose($h);
			}
		}
	}
	if (!copy($myConfigFolder.'/player_template.cfg', $myConfigFolder.'/player.cfg')) {
		echo "<ERROR> failed to copy player_template.cfg...".PHP_EOL;
		#echo "<br>";;
	}
	if (!copy($myBinFolder.'/player_template.cfg', $myConfigFolder.'/player_template.cfg')) {
		echo "<ERROR> failed to copy player_template.cfg...".PHP_EOL;
		#echo "<br>";;
	}
	if ($res == "1")  {
		echo '<OK> Player config is up-to-date.'.PHP_EOL;
		echo "<INFO> End of player update.";
	} else {
		echo '<OK> Player update took place.'.PHP_EOL;
		echo "<INFO> End of player update.";
	}
	
	#echo "<br>";;


?>


