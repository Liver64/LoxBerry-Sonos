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
	
	global $config, $sonoszone, $master, $lbversion, $plugindata, $level, $ms, $heute, $lbpdatadir, $debuggingfile, $lbplogdir;
	
	$debugconfig = $config;
	
	$sw = file_get_contents("http://".$sonoszone[$master][0] .":1400/xml/device_description.xml");
	$swv = new SimpleXMLElement($sw);
	
	$debugconfig['GENERAL']['Loxberry Version'] = $lbversion;
	$debugconfig['GENERAL']['Loxberry IPv4'] = LBSystem::get_localip();
	$debugconfig['GENERAL']['Plugin Version'] = $plugindata['PLUGINDB_VERSION'];
	$debugconfig['GENERAL']['Sonos Version'] = (string)$swv->device->displayVersion[0];
	$debugconfig['GENERAL']['Plugin Loglevel'] = $level;
	$debugconfig['GENERAL']['Installed Plugins'] = array();
	
	unset($debugconfig['LOCATION']);
	unset($debugconfig['MP3']['volumeup']);
	unset($debugconfig['MP3']['volumedown']);
	unset($debugconfig['MP3']['cachesize']);
	unset($debugconfig['MP3']['MP3store']);
	#unset($debugconfig['SYSTEM']['cifsinterface']);
	#unset($debugconfig['SYSTEM']['httpinterface']);
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
	
	$pluginarray = LBSystem::get_plugins();
	foreach ($pluginarray as $key)    {
		array_push($debugconfig['GENERAL']['Installed Plugins'], $key['PLUGINDB_TITLE']);
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
	if ($debugconfig['TTS']['API-key'] != "")    {
		$debugconfig['TTS']['API-key'] = "valid";
	}
	if ($debugconfig['TTS']['secret-key'] != "")    {
		$debugconfig['TTS']['secret-key'] = "valid";
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
		$debugconfig['TTS']['secret-key'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "8001")  {
		$debugconfig['TTS']['t2s_engine'] = "Google Cloud";
		$debugconfig['TTS']['secret-key'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "4001")  {
		$debugconfig['TTS']['t2s_engine'] = "AWS Polly";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "5001")  {
		$debugconfig['TTS']['t2s_engine'] = "Pico TTS";
		$debugconfig['TTS']['API-key'] = "";
		$debugconfig['TTS']['secret-key'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "7001")  {
		$debugconfig['TTS']['t2s_engine'] = "Google";
		$debugconfig['TTS']['API-key'] = "";
		$debugconfig['TTS']['secret-key'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "6001")  {
		$debugconfig['TTS']['t2s_engine'] = "Responsive Voice";
		$debugconfig['TTS']['API-key'] = "";
		$debugconfig['TTS']['secret-key'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "3001")  {
		$debugconfig['TTS']['t2s_engine'] = "OSX";
		$debugconfig['TTS']['API-key'] = "";
		$debugconfig['TTS']['secret-key'] = "";
	} elseif ($debugconfig['TTS']['t2s_engine'] == "1001")  {
		$debugconfig['TTS']['t2s_engine'] = "Voice RSS";
		$debugconfig['TTS']['secret-key'] = "";
	} else {
		$debugconfig['TTS']['t2s_engine'] = "No TTS Provider selected";
	}
	#print_r($debugconfig);
	file_put_contents($debuggingfile, json_encode($debugconfig, JSON_PRETTY_PRINT));
	copy($lbplogdir."/s4lox_debug_".$heute.".log", $lbpdatadir."/s4lox_debug_".$heute.".log");
	copy($lbplogdir."/SOAP-Log-".$heute.".log", $lbpdatadir."/SOAP_debug_".$heute.".log");
	echo "Please check debug Log file(s) in '$lbpdatadir' for further analysis! Your personal config data has been anonymized for Support reasons.";
	
}


?>