<?php

/**
* Submodul: Info
*
**/

/**
/* Funktion : info --> zeigt visuelle Informationen bzlg. Titel/Sender an
/*
/* @param: 	empty
/* @return: 
**/	

function info()  {
	global $sonos;
	
    $PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$radio = $sonos->RadiotimeGetNowPlaying();
	$title = $PositionInfo["title"];
	$album = $PositionInfo["album"];
	$artist = $PositionInfo["artist"];
	$albumartist = $PositionInfo["albumArtist"];
	$reltime = $PositionInfo["RelTime"];
	$bild = $PositionInfo["albumArtURI"];
	$streamContent = $PositionInfo["streamContent"];
	if($sonos->GetTransportInfo() == 1 )  {
		# Play
		$status = 'Play';
	} else {
		# Pause
		$status = 'Pause';
	}  
	if($PositionInfo["albumArtURI"] == '')  {
		# Kein Cover - Dann Radio Cover
		$bild = $radio["logo"];
	}
	if($PositionInfo["albumArtURI"] == '')  {
		# Kein Title - Dann Radio Title
		$title = $GetMediaInfo["title"];
	}   
	if($PositionInfo["album"] == '')  {
		# Kein Album - Dann Radio Stream Info
		$album = $PositionInfo["streamContent"];
	}  
	echo'
		cover: <tab>' . $bild . '<br>   
		title: <tab>' . $title . '<br>
		album: <tab>' . $album . '<br>
		artist: <tab>' . $artist . '<br>
		time: <tab>' . $reltime . '<br>
		status: <tab>' . $status . '<br>
		';
}
      

/**
/* Funktion : cover --> zeigt visuelle Informationen bzgl. Cover an
/*
/* @param: 	empty
/* @return: 
**/	

function cover() {
	global $sonos;

	$PositionInfo = $sonos->GetPositionInfo();
	$radio = $sonos->RadiotimeGetNowPlaying();
	$bild = $PositionInfo["albumArtURI"];
	if($PositionInfo["albumArtURI"] == '')  {
		# Kein Cover - Dann Radio Cover
		$bild = $radio["logo"];
	}
	echo' ' . $bild . ' ';
}
		

/**
/* Funktion : titel --> zeigt Titel Informationen an
/* 
/* @param: 	empty
/* @return: 
**/	
		
function title()  {
	global $sonos;
	
	$PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$radio = $sonos->RadiotimeGetNowPlaying();
	$title = $PositionInfo["title"];
	if($PositionInfo["albumArtURI"] == '')  {
		# Kein Title - Dann Radio Title
		$title = $GetMediaInfo["title"];
	}
	echo' ' . $title . ' ';
}



/**
/* Funktion : artist --> zeigt Artist Informationen an
/*
/* @param: 	empty
/* @return: 
**/	
		
function artist()  {
	global $sonos;
	
	$PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$title = $PositionInfo["title"];
	$album = $PositionInfo["album"];
	$artist = $PositionInfo["artist"];
	$albumartist = $PositionInfo["albumArtist"];
	$reltime = $PositionInfo["RelTime"];
	$bild = $PositionInfo["albumArtURI"];
	echo' ' . $artist . ' ';      
}
		
/**
/* Funktion : album --> zeigt Album Informationen an
/*
/* @param: 	empty
/* @return: 
**/	
		 
function album()  {
	global $sonos;
	
	$PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$radio = $sonos->RadiotimeGetNowPlaying();
	$album = $PositionInfo["album"];
	if($PositionInfo["album"] == '')  {
		# Kein Album - Dann Radio Stream Info
		$album = $PositionInfo["streamContent"];
	}
	echo'' . $album . '';
}


/**
/* Funktion : titelinfo --> zeigt Informationen bzgl. Tiel/Interpret etc. an
/*
/* @param: 	empty
/* @return: 
**/	
		
function titelinfo()  {
	global $sonos;
	
	if($debug == 1) {
		#echo debug();
	}
	$PositionInfo = $sonos->GetPositionInfo();
	$GetMediaInfo = $sonos->GetMediaInfo();
	$title = $PositionInfo["title"];
	$album = $PositionInfo["album"];
	$artist = $PositionInfo["artist"];
	$albumartist = $PositionInfo["albumArtist"];
	$reltime = $PositionInfo["RelTime"];
	$bild = $PositionInfo["albumArtURI"];
		echo'
			<table>
				<tr>
					<td><img src="' . $bild . '" width="200" height="200" border="0"></td>
					<td>
					Titel: ' . $title . '<br><br>
					Album: ' . $album . '<br><br>
					Artist: ' . $artist . '</td>
				</tr>
				<tr>
				<td>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=previous" target="_blank">Back</a>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=play" target="_blank">Cancel</a>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=pause" target="_blank">Pause</a>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=stop" target="_blank">Stop</a>
					<a href="'.$_SERVER['SCRIPT_NAME'].'?zone='.$master.'&action=next" target="_blank">Next</a>
					</table>
				';
}


/**
/* Funktion : debugInfo --> erstellt notwendige Informationen zum debugging
/*
/* @param: 	empty
/* @return: 
**/	

function debugInfo()     {
	
	global $config, $sonos, $actual, $sonoszone, $master, $lbversion, $plugindata, $level, $ms, $heute, $lbpdatadir, $debuggingfile, $lbplogdir;
	
	require_once(LBPHTMLDIR.'/system/bin/XmlToArray.php');
	
	$debugconfig = $config;
	#print_r($debugconfig);
	
	$sw = file_get_contents("http://".$sonoszone[$master][0] .":1400/xml/device_description.xml");
	$swv = new SimpleXMLElement($sw);
	
	$debugconfig['GENERAL']['Loxberry Version'] = $lbversion;
	$debugconfig['GENERAL']['Loxberry IPv4'] = LBSystem::get_localip();
	$debugconfig['GENERAL']['Plugin Version'] = $plugindata['PLUGINDB_VERSION'];
	$debugconfig['GENERAL']['Sonos Version'] = (string)$swv->device->displayVersion[0];
	#$debugconfig['GENERAL']['Sonos Player Generation'] = (string);
	$debugconfig['GENERAL']['Plugin Loglevel'] = $level;
	$debugconfig['GENERAL']['Installed Plugins'] = array();
	
	unset($debugconfig['LOCATION']);
	unset($debugconfig['MP3']['volumeup']);
	unset($debugconfig['MP3']['volumedown']);
	unset($debugconfig['MP3']['cachesize']);
	unset($debugconfig['MP3']['MP3store']);
	unset($debugconfig['SYSTEM']['cifsinterface']);
	unset($debugconfig['SYSTEM']['checkonline']);
	unset($debugconfig['SYSTEM']['checkt2s']);
	unset($debugconfig['TTS']['phonemute']);
	unset($debugconfig['TTS']['volrampto']);
	unset($debugconfig['TTS']['audiocodec']);
	unset($debugconfig['TTS']['sleeptimegong']);
	unset($debugconfig['TTS']['lamePath']);
	unset($debugconfig['TTS']['rampto']);
	unset($debugconfig['TTS']['correction']);
	unset($debugconfig['TTS']['regionms']);
	unset($debugconfig['VARIOUS']['phonestop']);
	unset($debugconfig['VARIOUS']['donate']);
	unset($debugconfig['VARIOUS']['CALDav2']);
	unset($debugconfig['VARIOUS']['CALDavMuell']);
	#unset($debugconfig['VARIOUS']['cron']);
	
	$folfilePlOn = "$lbpdatadir/PlayerStatus/s4lox_on_";				// Folder and file name for Player Status
	$soundbars = identSB($sonoszone, $folfilePlOn);
	
	$pluginarray = LBSystem::get_plugins();
	foreach ($pluginarray as $key)    {
		array_push($debugconfig['GENERAL']['Installed Plugins'], $key['PLUGINDB_TITLE']);
	}
	foreach ($debugconfig['sonoszonen'] as $zonepl => $val)    {
		$port = 1400;
		$timeout = 1;
		unset($debugconfig['sonoszonen'][$zonepl][0]); // IP
		unset($debugconfig['sonoszonen'][$zonepl][1]); // RinconID
		unset($debugconfig['sonoszonen'][$zonepl][2]); // Player Type
		unset($debugconfig['sonoszonen'][$zonepl][3]); // Vol
		unset($debugconfig['sonoszonen'][$zonepl][4]); // Vol
		unset($debugconfig['sonoszonen'][$zonepl][5]); // Vol
		unset($debugconfig['sonoszonen'][$zonepl][6]); // T2S
		unset($debugconfig['sonoszonen'][$zonepl][7]); // Model No.
		unset($debugconfig['sonoszonen'][$zonepl][8]); // SUB
		unset($debugconfig['sonoszonen'][$zonepl][9]); // Household
		unset($debugconfig['sonoszonen'][$zonepl][10]); // Mac
		unset($debugconfig['sonoszonen'][$zonepl][11]); // Audio
		unset($debugconfig['sonoszonen'][$zonepl][12]); // Voice
		unset($debugconfig['sonoszonen'][$zonepl][13]); // Soundbar
		$data['IP-ADDRESS'] = $val[0];
		$handle = @stream_socket_client("$val[0]:$port", $errno, $errstr, $timeout);
		if($handle) {
			$data['STATUS'] = "Online";
		} else {
			$data['STATUS'] = "Offline";
		}
		if ($debugconfig['sonoszonen'][$zonepl][6] == "On")  {
			$data['T2S SELECTED'] = "Yes";
		} else {
			$data['T2S SELECTED'] = "No";
		}
		$data['MODEL NO'] = $val[7];
		$data['PLAYER TYPE'] = $val[2];
		if ($val[8] == "SUB")  {
			$data['SUB'] = "Yes";
		} else {
			$data['SUB'] = "No";
		}
		if (is_enabled($val[11]))  {
			$data['AUDIO'] = "Yes";
		} else {
			$data['AUDIO'] = "No";
		}
		if (is_enabled($val[12]))  {
			$data['VOICE'] = "Yes";
		} else {
			$data['VOICE'] = "No";
		}
		if ($val[13] == "SB")  {
			$data['SOUNDBAR'] = "Yes";
		} else {
			$data['SOUNDBAR'] = "No";
		}
		
		$debugconfig['sonoszonen'][$zonepl] = $data;
	}
	if (count($ms) > 0)    {
		$debugconfig['LOXONE']['Miniserver'] = "available";
	} else {
		$debugconfig['LOXONE']['Miniserver'] = "Not available";
	}
	unset($debugconfig['LOXONE']['Loxone']);
	if (is_enabled($debugconfig['VARIOUS']['announceradio']))    {
		$debugconfig['VARIOUS']['announceradio'] = "enabled";
	} else {
		$debugconfig['VARIOUS']['announceradio'] = "disabled";
	}
	if (is_enabled($debugconfig['VARIOUS']['announceradio_always']))    {
		$debugconfig['VARIOUS']['announceradio_always'] = "enabled";
	} else {
		$debugconfig['VARIOUS']['announceradio_always'] = "disabled";
	}
	if (is_enabled($debugconfig['VARIOUS']['volmax']))    {
		$debugconfig['VARIOUS']['volmax'] = "enabled";
	} else {
		$debugconfig['VARIOUS']['volmax'] = "disabled";
	}
	if ($debugconfig['TTS']['apikey'] != "")    {
		unset($debugconfig['TTS']['apikeys']);
		$debugconfig['TTS']['apikey'] = "valid";
	}
	if ($debugconfig['TTS']['secretkey'] != "")    {
		unset($debugconfig['TTS']['secretkeys']);
		$debugconfig['TTS']['secretkey'] = "valid";
	}
	if ($debugconfig['LOXONE']['LoxDaten'] == "1")    {
		$debugconfig['LOXONE']['LoxDaten'] = "enabled";
		if ($debugconfig['LOXONE']['LoxDatenMQTT'] == "1")    {
			$debugconfig['LOXONE']['LoxDaten'] = "MQTT";
			$debugconfig['LOXONE']['LoxPort'] = "";
		} else {
			$debugconfig['LOXONE']['LoxDaten'] = "UDP";
		}
	} else {
		$debugconfig['LOXONE']['LoxDaten'] = "disabled";
		$debugconfig['LOXONE']['LoxPort'] = "";
	}
	unset($debugconfig['LOXONE']['LoxDatenMQTT']);
	# Anynomise last digits of IP-Address
	#foreach ($debugconfig['sonoszonen'] as $key => $value)   {
	#	$debugconfig['sonoszonen'][$key][0] = substr($value[0], 0, 10).".xxx";
	#}
	if ($debugconfig['TTS']['t2s_engine'] == "9001")   {
		$debugconfig['TTS']['t2s_engine'] = "MS Azure";
		$debugconfig['TTS']['secretkey'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "8001")  {
		$debugconfig['TTS']['t2s_engine'] = "Google Cloud";
		$debugconfig['TTS']['secretkey'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "4001")  {
		$debugconfig['TTS']['t2s_engine'] = "AWS Polly";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "5001")  {
		$debugconfig['TTS']['t2s_engine'] = "Pico TTS";
		$debugconfig['TTS']['apikey'] = "";
		$debugconfig['TTS']['secretkey'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "7001")  {
		$debugconfig['TTS']['t2s_engine'] = "Google";
		$debugconfig['TTS']['apikey'] = "";
		$debugconfig['TTS']['secretkey'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "6001")  {
		$debugconfig['TTS']['t2s_engine'] = "Responsive Voice";
		$debugconfig['TTS']['apikey'] = "";
		$debugconfig['TTS']['secretkey'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "3001")  {
		$debugconfig['TTS']['t2s_engine'] = "OSX";
		$debugconfig['TTS']['apikey'] = "";
		$debugconfig['TTS']['secretkey'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "1001")  {
		$debugconfig['TTS']['t2s_engine'] = "Voice RSS";
		$debugconfig['TTS']['secretkey'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "9011")  {
		$debugconfig['TTS']['t2s_engine'] = "ElevenLabs";
		$debugconfig['TTS']['secretkey'] = "";
	} else {
		$debugconfig['TTS']['t2s_engine'] = "No TTS Provider selected";
	}
	$i = 0;
	foreach($soundbars as $key => $value)   {
		$sonos = new SonosAccess($sonoszone[$key][0]);
		$debugconfig['SOUNDBARS']['TV_MONITOR_STATUS'] = $config['VARIOUS']['tvmon'];
		$debugconfig['SOUNDBARS']['START_TIME'] = $config['VARIOUS']['starttime'];
		$debugconfig['SOUNDBARS']['END_TIME'] = $config['VARIOUS']['endtime'];
		$Autoplay = $sonos->GetAutoplayRoomUUID();
		if (empty($Autoplay['RoomUUID']))    {
			$debugconfig['SOUNDBARS'][$key]['AUTOPLAY'] = "Off";
		} else {
			$debugconfig['SOUNDBARS'][$key]['AUTOPLAY'] = "On";
		}
		$Autoplaylinked = $sonos->GetAutoplayLinkedZones();
		if ($Autoplaylinked[IncludeLinkedZones] == "0")    {
			$debugconfig['SOUNDBARS'][$key]['AUTOPLAY_LINKED_ZONES'] = "Off";
		} else {
			$debugconfig['SOUNDBARS'][$key]['AUTOPLAY_LINKED_ZONES'] = "On";
		}
		#print_r($config['sonoszonen']);
		$debugconfig['SOUNDBARS'][$key]['DETAILS']['NIGHT_FROM_TIME'] = $config['sonoszonen'][$key][14]['fromtime'];
		$debugconfig['SOUNDBARS'][$key]['DETAILS']['SUB_LEVEL'] = $config['sonoszonen'][$key][14]['tvmonnightsublevel'];
		$debugconfig['SOUNDBARS'][$key]['DETAILS']['SUBWOOFER'] = $config['sonoszonen'][$key][14]['tvmonnightsub'];
		$debugconfig['SOUNDBARS'][$key]['DETAILS']['NIGHT_MODE'] = $config['sonoszonen'][$key][14]['tvmonnight'];
		$debugconfig['SOUNDBARS'][$key]['DETAILS']['SPEECH_MODE'] = $config['sonoszonen'][$key][14]['tvmonspeech'];
		$debugconfig['SOUNDBARS'][$key]['DETAILS']['SURROUND'] = $config['sonoszonen'][$key][14]['tvmonsurr'];
		$debugconfig['SOUNDBARS'][$key]['DETAILS']['TV_VOLUME'] = $config['sonoszonen'][$key][14]['tvvol'];
		$debugconfig['SOUNDBARS'][$key]['DETAILS']['ZONE_STATUS'] = $config['sonoszonen'][$key][14]['usesb'];
	}
	
	$actual = saveZonesStatus();
	$debugconfig['SNAPSHOT CURRENT'] = $actual;
	
	$xml = $sonos->GetZoneStates();
	# https://github.com/vyuldashev/xml-to-array/tree/master
	$array = XmlToArray::convert($xml);
	$interim = $array['ZoneGroupState']['ZoneGroups']['ZoneGroup'];
	$debugconfig['ZONE INFOS'] = $interim;
	$actlog = file_get_contents($lbplogdir."/s4lox_debug_".$heute.".log");
	$debugconfig['LOG'] = $actlog;
	#print_r($debugconfig);
	file_put_contents($debuggingfile, print_r($debugconfig, true));
	echo "A full snapshot of your system/command has been executed...<br><br>";
	echo "Please check debug file 's4lox_debug_config.json' in '$lbpdatadir' for further analysis! Your personal data has been anonymized.<br>";
	echo "For support reasons you can use the file to post into Forum.";
	
}

/**
/* Funktion : batteryinfo --> zeigt Informationen bzgl. Ladestatus ROAM oder MOVE an
/*
/* @param: 	empty
/* @return: 
**/	
		
function batteryinfo()  {
	
	global $sonoszone;
	
	$abbr = "batt";
	foreach ($sonoszone as $zone => $player) {
		$src = $sonoszone[$zone][7];
		$ip = $sonoszone[$zone][0];
		$stype = $sonoszone[$zone][2];
		
		# only check MOVE or ROAM devices
		if ($src == "S27" or $src == "S17")   {
			LOGDEB('info.php: Mobile Player "'.$src.' - '.$stype.'" called "'.$zone.'" has been found.');
			$port = 1400;
			$timeout = 3;
			$handle = @stream_socket_client("$ip:$port", $errno, $errstr, $timeout);
			# if Online check battery status
			if($handle) {
				# get battery status
				$url = "http://".$ip.":1400/status/batterystatus";
				$xml = simpleXML_load_file($url);
				$batlevel = $xml->LocalBatteryStatus->Data[1];
				#echo 'The battery level of "'.$zone.'" is about '.$batlevel.'%. Please charge your device!';
				sendInfoMS($abbr, $zone, $batlevel);
			}
		}
	}
}

/**
/* Funktion : Getdialoglevel --> zeigt Informationen bzgl. DialogLevel der Zone an
/*
/* @param: 	empty
/* @return: 
**/	
		
function Getdialoglevel()  {
	
	global $sonos;
	
	$dialog = array();
	#echo '<PRE>';
	$NightMode = $sonos->GetDialogLevel('NightMode');
	$dialog['NightMode'] = $NightMode;
	$SurroundEnable = $sonos->GetDialogLevel('SurroundEnable');
	$dialog['SurroundEnable'] = $SurroundEnable;
	$DialogLevel = $sonos->GetDialogLevel('DialogLevel');
	$dialog['DialogLevel'] = $DialogLevel;
	$SubGain = $sonos->GetDialogLevel('SubGain');
	$dialog['SubGain'] = $SubGain;
	$SubEnable = $sonos->GetDialogLevel('SubEnable');
	$dialog['SubEnable'] = $SubEnable;
	#print_r($dialog);
	return $dialog;
	
	#echo '</PRE>';
}




?>
