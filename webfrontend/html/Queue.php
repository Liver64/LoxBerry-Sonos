<?php

/**
* Function : playFav --> load and play specified Sonos Favorite
* 
* 
* @param: empty
* @return: play Favorite
**/

function playFav() 
{
	global $sonos, $volume, $sonoszone, $re, $master, $metadata;
	
	if(isset($_GET['favorite'])) {
		$favorite = $_GET['favorite'];	
	} else {
		LOGERR("queue.php: You have maybe a typo or you missed: favorite=EXACT NAME! Correct syntax is: &action=favorite&favorite=EXACT NAME");
	exit;
	}
	
	$single = "Single";
	$radio = "Radio";
	$tes = $sonos->BrowseFav("FV:2","c");
	$re1 = array_multi_search($single, $tes);
	$re2 = array_multi_search($radio, $tes);
	$re = array_merge($re1, $re2);
	foreach ($tes as $key)    {
		$favoritecheck = starts_with($key['title'], $favorite);
		if ($favoritecheck === true)   {
			$favorite = $key['title'];
		}
	}
	$favorite = urldecode($favorite);
	$re = array_multi_search($favorite, $tes);
	$artist = @substr($re[0]['artist'], 4, 60);
	$sonos->Stop();
	$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
	$sonos->SetGroupMute(false);
	$sonos->SetPlayMode('NORMAL');
	$sonos->SetVolume($volume);
	$sonos->ClearQueue();
	# Bsp NOT WORKING :-(  $metadata = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><s:Body><u:ReplaceAllTracks xmlns:u="urn:schemas-sonos-com:service:Queue:1"><QueueID>0</QueueID><UpdateID>0</UpdateID><ContainerURI></ContainerURI><EnqueuedURIsAndMetaData><URI uri="x-sonos-spotify:spotify:track:1M7zI4Qh9iotN0zx88urTh?sid=9&flags=8224&sn=5"></URI><DIDL-Lite xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:upnp="urn:schemas-upnp-org:metadata-1-0/upnp/" xmlns:r="urn:schemas-rinconnetworks-com:metadata-1-0/" xmlns="urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/"><dc:title>Feel Right</dc:title><upnp:albumArtURI>http://192.168.50.42:1400https://i.scdn.co/image/4a15e08f300699ebc45b2fc6abcdec5eab363022</upnp:albumArtURI><r:description>Von A Little Nothing</r:description></DIDL-Lite></EnqueuedURIsAndMetaData></u:ReplaceAllTracks></s:Body></s:Envelope>';
	try {
		@$sonos->SetRadio($re[0]['res'], $re[0]['title'].' - '.$artist, $id="R:0/0/0", $parentID="R:0/0", $metadata);
		$sonos->Play();
		LOGOK("queue.php: Your favorite '".$favorite."' has been successful loaded and is playing!");
	} catch (Exception $e) {
		LOGERR("queue.php: Your entered favorite '".($favorite)."' seems not to be valid! Type Album are not supported, please use playlist functions therefore!");
		LOGERR("queue.php: If no Album entered please check your writing, maybe there is a typo or lowercase/uppercase issue!");
		exit;
	}
	
}

/**
* Function : getFav --> prepare list of Sonos favorite
* 
* 
* @param: empty
* @return: list of all favorites
**/

function getFav() 
{
	global $sonos;
	
	$single = "Single";
	$radio = "Radio";	
	$tes = $sonos->BrowseFav("FV:2","c");
	$re1 = array_multi_search($single, $tes);
	$re2 = array_multi_search($radio, $tes);
	$re = array_merge($re1, $re2);
	echo "Only MP3 files and Radio Stations are supported. No Albums etc. Fuzzy Logic search is possible by Title or Radio Station";
	echo "<br>";
	echo "<br>";
	print_r($re);
	LOGOK("queue.php: Your list of Sonos favorites has been successful loaded. Type Album are excluded");
}



/**
* Function : addFavList --> laod and play Sonos favorites
* 
* 
* @param: empty
* @return: list of all favorites except Album and Radio Stations
**/

function addFavList() 
{
	global $sonos, $volume, $sonoszone, $master;
	
	$single = "Single";
	$tes = $sonos->BrowseFav("FV:2","c");
	$re = array_multi_search($single, $tes);
	#print_r($re);
	$sonos->Stop();
	$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
	$sonos->ClearQueue();
	$sonos->SetGroupMute(false);
	$sonos->SetPlayMode('NORMAL');
	$sonos->SetVolume($volume);
	foreach($re as $file)  {
		try {
			$artist = @substr($re[0]['artist'], 4, 40);
			print_r($file['res']);
			echo "<br>";
			$data  =' ';
			#$sonos->AddFavoritesToQueue($file['res']);
			#$sonos->SetRadio($re[0]['res'], $re[0]['title'].' - '.$artist, $id="R:0/0/0", $parentID="R:0/0");
			#$sonos->SetRadio($file['res'], $file['title'], $id="R:0/0/0", $parentID="R:0/0");
			#continue;
			$sonos->AddToQueue($file['res']);
			$sonos->Play();
		} catch (Exception $e) {
			LOGERR("queue.php: Your favorite '".$file['res']."' seems not to be valid! Please check!");
			continue;
			#exit;
		}
	}
	LOGOK("queue.php: Your favorites list has been successful loaded and is currently playing!");
}




/**
* Function: zap --> checks each zone in network and if playing add current zone as member - NEW zapzone
*
* @param: empty
* @return: 
**/

function zap()
{
	global $sonos, $config, $tmp_tts, $sonoszonen, $maxzap, $volume, $sonoszone, $master;
	
	$fname = "/run/shm/zap_zone.json";			// file containig running zones
	$zname = "/run/shm/zap_zone_time";			// temp file for nextradio
	
	# get volume
	$statmast = $sonos->GetTransportInfo();	
	if ($statmast == "1")   {
		$volume = $sonos->GetVolume();
		LOGGING("queue.php: Volume for ".$master." has been updated from current Volume.",7);
	} else {
		$volume = $config['sonoszonen'][$master][4];
		#LOGGING("queue.php: Standard Volume for ".$master." has been adopted from config.",7);
	}
	# check if TTS is currently running
	if (file_exists($tmp_tts))  {
		LOGGING("queue.php: Currently a T2S is running, we skip zapzone for now. Please try again later.", 4);
		exit;
	}
	# become single zone 1st
	$check_stat = getZoneStatus($master);
	if ($check_stat == "member")  {
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGGING("queue.php: Zone ".$master." has been ungrouped.",5);
	}
	# start zapzone
	if (file_exists($fname) === true)  {
		$file = json_decode(file_get_contents($fname), true);
		$c = count($file);
		if ($c == 0)  {
			$sonos->BecomeCoordinatorOfStandaloneGroup();
			@unlink($fname);
			file_put_contents($zname, "1");
			LOGGING("queue.php: Function nextradio has been called ",7);
			nextradio();
			exit;
		}
		$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
		$sonos->SetAVTransportURI("x-rincon:" . $file[2]);
		LOGGING("queue.php: Zone ".$master." has been added as member to Zone ".$file[0],7);
		#sleep(1);
		array_shift($file);
		array_shift($file);
		array_shift($file);
		$jencode = json_encode($file);
		file_put_contents($fname, $jencode);
	} else {
		# as long zapzone is running switch to nextradio
		if (file_exists($zname) === true)    {
			nextradio();
			LOGGING("queue.php: Function nextradio has been called ",7);
			sleep($maxzap);
			if(file_exists($zname))  {
				unlink($zname);
				LOGGING("queue.php: Function zapzone has been reseted",6);
			}
			exit;
		}
		# prepare list of currently playing zones
		$runarray = array();
		foreach ($sonoszone as $zone => $player) {
			$sonos = new PHPSonos($sonoszone[$zone][0]);
			$state = $sonos->GetTransportInfo();													// only playing zones
			if ($state == '1' and $sonoszone[$zone][1] != $sonoszone[$master][1])   {				// except masterzone
				$u = getZoneStatus($zone);
				if ($u <> "member")    {
					array_push($runarray, $zone, $sonoszone[$zone][0], $sonoszone[$zone][1]); 		// add IP-address to array
				}
			}
		}
		#print_r($runarray);
		$co =  count($runarray);
		if ($co == 0)  {
			LOGGING("queue.php: Currently no zone is running",7);
			exit;
		}
		# join 1st zone of array
		$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
		$sonos->SetAVTransportURI("x-rincon:" . $runarray[2]);
		LOGGING("queue.php: Zone ".$master." has been added as member to Zone ".$runarray[0],7);
		#sleep(1);
		array_shift($runarray);										// remove 1st Zone Name and re-index array
		array_shift($runarray);										// remove 1st Zone Rincon-ID and re-index array
		array_shift($runarray);										// remove 1st Zone IP and re-index array
		$jencode = json_encode($runarray);							// save file
		file_put_contents($fname, $jencode);
	}
}




?>