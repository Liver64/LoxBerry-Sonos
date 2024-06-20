<?php

/**
/* Create initial setup/config file for Volume Profiles
/*
/* @param: 	
/* @return: array()
**/	

#header('Content-Type: application/json');

	require_once("loxberry_system.php");
	require_once("loxberry_log.php");
	require_once($lbphtmldir."/system/sonosAccess.php");
	require_once($lbphtmldir."/Info.php");

	if (isset($_POST['new_id']) ? $ajax = "true" : $ajax = "false")    

	$configfile			= "s4lox_config.json";	
	$vol_config			= "s4lox_vol_profiles";
	$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";	

	$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);

	$zonesoff = array();
	$sonoszonen = $config['sonoszonen'];
	# check Zones Online Status
	foreach($sonoszonen as $zonen => $ip) {
		$handle = is_file($folfilePlOn."".$zonen.".txt");
		if($handle === true) {
			$sonoszone[$zonen] = $ip;
		} else {
			$zonesoff[$zonen] = $ip;
		}
	}
	$vol_prof = array();
	$vol_prof['Name'] = "current values";		// set default name for 1st execution
	
	foreach ($sonoszone as $player => $value) {
		$sonos = new SonosAccess($sonoszone[$player][0]);
		#$data['IP'] = $value[0];					// get IP
		#$data['Rincon'] = $value[1];				// get Rincon
		$data['Volume'] = $sonos->GetVolume();		// get Volume
		$data['Bass'] = $sonos->GetBass();			// get Bass
		$data['Treble'] = $sonos->GetTreble();		// get Treble
		if (is_enabled($sonos->GetLoudness()))   {
			$data['Loudness'] = "true";				// set true
		} else {
			$data['Loudness'] = "false";			// set false
		}
		try {
			$dialog = Getdialoglevel();
		} catch (Exception $e) {
			
		}
		if ($sonoszone[$player][8] == "NOSUB")  {
			$data['Subwoofer'] = "na";								// If no Subwoofer connected set false
			$data['Subwoofer_level'] = "";							// If no Subwoofer connected empty
		} else {
			if (is_enabled((string)$dialog['SubEnable']))   {
				$data['Subwoofer'] = "true";						// set Subwoofer on
			} else {
				$data['Subwoofer'] = "false";						// set Subwoofer off
			}
			$data['Subwoofer_level'] = $dialog['SubGain'];			// set Subwoofer level
		}
		if ($sonoszone[$player][10] == "NOSUR")   {
			$data['Surround'] = "na";								// If no Surround set na
		} else {
			if (is_enabled((string)$dialog['SurroundEnable']))   {
				$data['Surround'] = "true";							// set Surround on
			} else {
				$data['Surround'] = "false";						// set Surround off
			}
		}
		$vol_prof['Player'][$player] = array($data);
	}
	
	# add Offline Zones
	foreach ($zonesoff as $player => $value) {
		#$data['IP'] = $value[0];
		#$data['Rincon'] = $value[1];
		$data['Volume'] = "";	
		$data['Bass'] = "";
		$data['Treble'] = "";
		$data['Loudness'] = "false";
		$data['Subwoofer'] = "na";
		$data['Subwoofer_level'] = "";	
		$data['Surround'] = "na";
		$vol_prof['Player'][$player] = array($data);	
	}
	$final[] = $vol_prof;

	if ($ajax == "true")   {
		echo json_encode($final);
	} else {
		file_put_contents($lbpconfigdir . "/" . $vol_config.".json",json_encode($final, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT));
		echo "<PRE>";
		print_r($final);
	}
?>