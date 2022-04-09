<?php

/**
* Function: zap --> checks each zone in network and if playing add current zone as member - NEW zapzone
*
* @param: empty
* @return: 
**/

function zap()
{
	global $sonos, $config, $tmp_tts, $sonoszonen, $maxzap, $volume, $sonoszone, $master, $fname, $zname;
	
	#$fname = "/run/shm/s4lox_zap_zone.json";								// queue.php: file containig running zones
	#$zname = "/run/shm/s4lox_zap_zone_time";								// queue.php: temp file for nextradio
	
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
	# START ZAPZONE
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
		} else {
			nextradio();
			LOGGING("queue.php: Function nextradio has been called ",7);
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
	#$sonos->SetVolume($volume);
}



/**
* Function : PlayFavorite --> load and play specified Sonos Favorite (Radio/Track/Playlist)
* 
* 
* @param: empty
* @return: play Favorite
**/

function PlayFavorite() 
{
	global $sonos, $volume, $sonoszone, $re, $master, $favtmp;
	
	# if playlist has been loaded iterate through tracks
	if (file_exists($favtmp))  {
		LOGINF("queue.php: Playlist file has been loaded, we start iterating through Playlist.");
		$countqueue = count($sonos->GetCurrentPlaylist());
		$currtrack = $sonos->GetPositioninfo();
		if ($currtrack['Track'] != $countqueue)    {
			$sonos->Next();
			LOGINF ("queue.php: Function 'next' has been executed");
			return true;
		} else {
			@unlink($favtmp);
			if(isset($_GET['member'])) {
				removemember();
				LOGINF ("queue.php: Member has been removed");
			}
			LOGINF ("queue.php: Last track has been played and Playlist file has been deleted");
			LOGOK ("queue.php: ** Loop ended, we start from beginning **");
		}
	}
	
	# initial load of favorite
	if(isset($_GET['favorite'])) {
		$favorite = mb_strtolower($_GET['favorite']);	
	} else {
		LOGERR("queue.php: You have maybe a typo or you missed: favorite=TITLE! Correct syntax is: &action=playfavorite&favorite=TITLE");
		exit;
	}
	
	$tes = AddDetailsToMetadata();
	foreach ($tes as $val => $item)  {
		$tes[$val]['title'] = mb_strtolower($tes[$val]['title']);
	}
	$fav = $favorite;
	$re = array();
	foreach ($tes as $key)    {
		$favoritecheck = contains($key['title'], $fav);
		if ($favoritecheck === true)   {
			$favorite = $key['title'];
			array_push($re, array_multi_search($favorite, $tes, "title"));
		}
	}
	$favorite = urldecode($favorite);
	if (count($re) > 1)  {
		LOGERR ("queue.php: Your entered favorite '".$fav."' has more then 1 hit! Please specify more detailed.");
		exit;
	}
	$sonos->BecomeCoordinatorOfStandaloneGroup();
	if(isset($_GET['member'])) {
		addmember();
		LOGINF ("queue.php: Member has been added");
	}
	#print_r($re);
	$sonos->Stop();
	$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
	$sonos->SetGroupMute(false);
	$sonos->SetPlayMode('NORMAL');
	$sonos->SetVolume($volume);
	$sonos->ClearQueue();
	LOGINF("queue.php: Settings to play your favorite has been prepared!");
	try {
		@metadata($re[0][0]);
		$sonos->Play();
		if (count($sonos->GetCurrentPlaylist()) > 1)  {
			file_put_contents($favtmp,"running");
			LOGINF("queue.php: Playlist Favorite has been identified. File has been saved!");
		}
		LOGOK("queue.php: Your favorite '".$favorite."' has been successful loaded and is playing!");
	} catch (Exception $e) {
		LOGERR("queue.php: Your entered favorite '".($favorite)."' seems not to be valid!");
		exit;
	}
	
}


/**
* Function : GetFavorites --> prepare list of Sonos favorites
* 
* 
* @param: empty
* @return: list of all favorites
**/

function GetFavorites() 
{
	global $sonos;
	
	$tes = AddDetailsToMetadata();
	#echo "Only Tracks and Radio Stations are supported, no Albums/playlists, except for fucntion 'playfavorite@favorite=TITLE'";
	echo "Fuzzy Logic search is possible by Title/Playlist/Album or Radio Station";
	echo "<br>";
	echo "<br>";
	print_r($tes);
	LOGOK("queue.php: Your list of Sonos favorites has been successful loaded. Playlists are excluded");
}


/**
* Function : PlayAllFavorites --> load and play Sonos favorites (only Tracks/Radio)
* 
* 
* @param: empty
* @return: list of all favorites except Album/Playlists
**/

function PlayAllFavorites() 
{
	global $sonos, $volume, $value, $sonoszone, $master, $services, $radiofav, $radiolist, $queuetmp, $radiofavtmp;
	 
	if (file_exists($radiofav))  {
		
		try {
			if (!file_exists($radiofavtmp))  {
				# as long as we tracks iterate through
				$sonos->Next();
				LOGINF ("queue.php: Favorite Tracks are running");
				LOGINF ("queue.php: Function 'next' has been executed");
			} else {
				# create Failure in case Radio Playlist is loaded to catch exception
				$sonos->Rewind();
				LOGDEB ("queue.php: Fake Function has been executed in order to create temp error");
			}
		} catch (Exception $e) {
			# clear current queue
			$sonos->ClearQueue();
			LOGINF ("queue.php: Current Queue has been deleted");
			# load previously saved radio Stations
			$value = json_decode(file_get_contents($radiofav), TRUE);
			LOGOK ("queue.php: Your Radio Favorites has been loaded");
			# add Radio Station
			if (count($value) >= 1)  {
				metadata($value[0]);
				LOGOK ("queue.php: Radio Favorite '".$value[0]['title']."' has been added and is playing");
			}
			$sonos->SetVolume($volume);
			# check addionally if Radio Station has been loaded
			$mediainfo = $sonos->GetMediaInfo();
			if ($mediainfo['CurrentURI'] != "")  {
				try {
					$sonos->Play();
					# remove 1st element of array
					array_shift($value);
					LOGINF ("queue.php: Current playing Radio Favorite has been removed from array.");
				} catch (Exception $e) {
					# remove 1st element of array
					array_shift($value);
					LOGINF ("queue.php: Radio Favorite has been removed from array  (Loading failed)");
				}
			}
			# check array if NULL
			if (count($value) > 0)  {
				# save new array
				LOGOK ("queue.php: New Radio Favorite array has been saved");
				$radiofavarray = file_put_contents($radiofav, json_encode($value));
				$radiofavtmp = file_put_contents($radiofavtmp, json_encode("Radio"));
			} else {
				# if last element loaded delete files
				if(isset($_GET['member'])) {
					removemember();
					LOGINF ("queue.php: Member has been removed");
				}
				@unlink($radiofav);
				@unlink($radiofavtmp);
				@unlink($queuetmp);
				LOGINF ("queue.php: Files has been deleted");
				LOGOK ("queue.php: ** Loop ended, we start from beginning **");
			}
		}
		#return($value);	
		return;
	} 
	#echo "Count: ".count($sonos->GetCurrentPlaylist());
	try {
		if (count($sonos->GetCurrentPlaylist()) > 0 )  {
			$sonos->Next();
			LOGINF ("queue.php: Function 'next' has been executed");
		} else {
			# create Failure in case Radio Playlist is already loaded in order to catch exception
			$sonos->Rewind();
			LOGINF ("queue.php: Error produced in order to catch exception!");
		}
	} catch (Exception $e) {
		$single = "Track";
		$radio = "Radio";	
		$pl = "Playlist";
		$tes = AddDetailsToMetadata();
		#print_r($tes);
		$track 		= array_multi_search($single, $tes);
		$radio 		= array_multi_search($radio, $tes);
		$playlist 	= array_multi_search($pl, $tes);
		if ((count($playlist) > 0))    {
			LOGINF ("queue.php: Playlist Favorites/Album are currently not supported!");
		}
		LOGOK ("queue.php: Your Favorites has been identified");
		# Prepare Play
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		$sonos->Stop();
		$sonos->ClearQueue();
		$sonos->SetGroupMute(false);
		$sonos->SetPlayMode('NORMAL');
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your favorite has been prepared!");
		$shift = false;
		# Select 1st favorite 
		if ((count($track) > 0))    {
			$value = $track[0];
			# Load 1st favorite 
			$proof = metadata($value);
			if ($proof === true)   {
				LOGOK ("queue.php: First Favorite Track has been loaded");
				# Set variable true
				$shift = true;
			} else {
				LOGOK ("queue.php: First Favorite Track could not be loaded");
				# Set variable false
				#$shift = false;
			}
			#var_dump($shift);
			# remove loaded favorite from array
			array_shift($track);
			if (count($sonos->GetCurrentPlaylist()) > 0 )  {
				$sonos->Play();
				LOGDEB ("queue.php: First Favorite Track is playing");
				LOGINF ("queue.php: Currently playing favorite has been removed from array");
			} else {
				$value = $track[0];
				LOGDEB ("queue.php: Just one track has been identified.");
			}
			$base_array = $track;
			if (count($base_array) > 0)   {
				# ...then add rest of favorites
				LOGINF("queue.php: More then one track has been identified, prepare load of remaining!");
				foreach ($base_array as $key => $value)   {
					# Load all favorites
					metadata($value);
				}
			}		
		LOGOK ("queue.php: All Favorite tracks has been loaded");
		}
		if (count($radio) > 0)   {
			file_put_contents($radiofav, json_encode($radio));
			LOGINF ("queue.php: File including all Radio Stations has been saved.");
		}
		# only if Radiostations are in the Favorites
		if ($shift === false and count($radio) > 0)    {
			$value = $radio[0];
			metadata($value);
			LOGOK ("queue.php: First Favorite Radio has been loaded");
			$sonos->Play();
			array_shift($radio);
			LOGDEB ("queue.php: First Favorite Radio is playing");
			LOGINF ("queue.php: Currently playing favorite has been removed from array");
			file_put_contents($radiofav, json_encode($radio));
			LOGINF ("queue.php: File including all Radio Stations has been saved.");
		}		
	}
}


/**
* Function : PlayTrackFavorites --> load and play Sonos track favorites
* 
* 
* @param: empty
* @return: 
**/

function PlayTrackFavorites() 
{
	global $sonos, $volume, $value, $sonoszone, $master, $queuetracktmp;
	
	if (file_exists($queuetracktmp))  {
		$countqueue = count($sonos->GetCurrentPlaylist());
		$currtrack = $sonos->GetPositioninfo();
		if ($currtrack['Track'] < $countqueue)    {
			$sonos->Next();
			LOGINF ("queue.php: Function 'next' has been executed");
			return true;
		} else {
			@unlink($queuetracktmp);
			if(isset($_GET['member'])) {
				removemember();
				LOGINF ("queue.php: Member has been removed");
			}
			LOGINF ("queue.php: File has been deleted");
			LOGOK ("queue.php: ** Loop ended, we start from beginning **");
		}
	}
	LOGDEB ("queue.php: ** Loop Favorite Tracks started from scratch**");
	$single = "Track";
	$tes = AddDetailsToMetadata();
	#print_r($tes);
	$track 		= array_multi_search($single, $tes);
	LOGOK ("queue.php: Your Track Favorites has been identified");
	# Prepare Play
	$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
	$sonos->Stop();
	$sonos->ClearQueue();
	$sonos->SetGroupMute(false);
	$sonos->SetPlayMode('NORMAL');
	$sonos->SetVolume($volume);
	LOGINF("queue.php: Settings to play your favorite has been prepared!");
	$shift = false;
	# Select 1st favorite 
	if ((count($track) > 0))    {
		$value = $track[0];
		# Load 1st favorite 
		$proof = metadata($value);
		if ($proof === false)   {
			array_shift($value);
			LOGINF ("queue.php: Favorite Track could not be loaded and has been removed");
			LOGOK ("queue.php: Next Favorite Track will be loaded");
			$sonos->ClearQueue();
			metadata($value);
		}
		LOGOK ("queue.php: First Track Favorite has been loaded");
		# remove loaded favorite from array
		array_shift($track);
		# Set variable for ClearQueue
		$shift = true;
		if (count($sonos->GetCurrentPlaylist()) > 0 )  {
			$sonos->Play();
			LOGDEB ("queue.php: First Favorite Track is playing");
			LOGINF ("queue.php: Currently playing Track favorite has been removed from array");
		} else {
			$value = $track[0];
			LOGDEB ("queue.php: Just one track has been identified.");
		}
		$base_array = $track;
		if (count($base_array) > 0)   {
			LOGINF("queue.php: More then one track has been identified, prepare load of remaining!");
			# ...then add rest of favorites
			foreach ($base_array as $key => $value)   {
				# Load all favorites
				$proof = metadata($value);
			}
		}
	}
	file_put_contents($queuetracktmp, json_encode("cleared"));	
}



/**
* Function : PlayRadioFavorites --> load and play Sonos Radio favorites
* 
* 
* @param: empty
* @return: 
**/

function PlayRadioFavorites() 
{
	global $sonos, $volume, $value, $sonoszone, $master, $queueradiotmp;

	if (file_exists($queueradiotmp))  {
		# load previously saved radio Stations
		$value = json_decode(file_get_contents($queueradiotmp), TRUE);
		LOGOK ("queue.php: Your Radio Favorites has been loaded");
		# add Radio Station
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		if (count($value) >= 1)  {
			$proof = metadata($value[0]);
		}
		if ($proof === false)   {
			array_shift($value);
			LOGINF ("queue.php: Favorite Radio could not be loaded and has been removed");
			LOGOK ("queue.php: Next Favorite Radio will be loaded");
			$sonos->ClearQueue();
			$proof = metadata($value[0]);
		}
		$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your favorite has been prepared!");
		# check addionally if Radio Station has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				$sonos->Play();
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Current playing Radio Favorite has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: PlayRadio: Radio Favorite has been removed from array (Loading failed)");
			}
		}
		
		# check array if NULL
		if (count($value) > 0)  {
			# save new array
			$radiofavarray = file_put_contents($queueradiotmp, json_encode($value));
			LOGOK ("queue.php: New Radio Favorite array has been saved");
		} else {
			# if last element loaded delete files
			if(isset($_GET['member'])) {
				removemember();
				LOGINF ("queue.php: Member has been removed");
			}
			@unlink($queueradiotmp);
			LOGINF ("queue.php: Files has been deleted");
			LOGOK ("queue.php: ** Loop ended, we start from beginning **");
		}
	} else {
		LOGWARN ("queue.php: Radio Stations File could not be loaded!");
		exit;
	}
}


/**
* Function : PlaySonosPlaylist --> load and play Sonos Playlists
* 
* 
* @param: empty
* @return: 
**/

function PlaySonosPlaylist() 
{
	global $sonos, $volume, $value, $sonoszone, $master, $pltmp;

	if (file_exists($pltmp))  {
		# load previously saved Sonos Playlist
		$value = json_decode(file_get_contents($pltmp), TRUE);
		#print_r($value);
		LOGOK ("queue.php: Your Sonos Playlists has been loaded");
		# add Playlist
		$sonos->ClearQueue();
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		if (count($value) >= 1)  {
			$proof = metadata($value[0]);
		}
		if ($proof === false)   {
			array_shift($value);
			LOGINF ("queue.php: Sonos Playlist could not be loaded and has been removed");
			LOGOK ("queue.php: Next Sonos Playlist will be loaded");
			$sonos->ClearQueue();
			metadata($value[0]);
		}
		$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your playlist has been prepared!");
		# check addionally if Playlist has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				$sonos->Play();
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Current playing Sonos Playlist has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Sonos Playlist has been removed from array (Loading failed)");
			}
		}
		# check array if NULL
		if (count($value) > 0)  {
			# save new array
			$radiofavarray = file_put_contents($pltmp, json_encode($value));
			LOGOK ("queue.php: New Sonos Playlists array has been saved");
		} else {
			# if last element loaded delete files
			if(isset($_GET['member'])) {
				removemember();
				LOGINF ("queue.php: Member has been removed");
			}
			@unlink($pltmp);
			LOGINF ("queue.php: File has been deleted");
			LOGOK ("queue.php: ** Loop ended, we start from beginning **");
		}
	} else {
		LOGWARN ("queue.php: Sonos Playlists File could not be loaded!");
		exit;
	}
}

/**
* Function : PlayTuneInPlaylist --> load and play TuneIn Radio Favorites
* 
* 
* @param: empty
* @return: 
**/

function PlayTuneInPlaylist() 
{
	global $sonos, $volume, $value, $sonoszone, $master, $tuneinradiotmp;
	
	if (file_exists($tuneinradiotmp))  {
		# load previously saved radio Stations
		$value = json_decode(file_get_contents($tuneinradiotmp), TRUE);
		LOGOK ("queue.php: Your TuneIn Radio Favorites has been loaded");
		# add Radio Station
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		if (count($value) >= 1)  {
			$proof = metadata($value[0]);
		}
		if ($proof === false)   {
			array_shift($value);
			LOGINF ("queue.php: Favorite TuneIn Station could not be loaded and has been removed");
			LOGOK ("queue.php: Next Favorite TuneIn Station will be loaded");
			$sonos->ClearQueue();
			metadata($value[0]);
		}
		$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your TuneIn Radio Station has been prepared!");
		# check addionally if Radio Station has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				$sonos->Play();
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Current playing TuneIn Station has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: TuneIn Station has been removed from array (Loading failed)");
			}
		}
		# check array if NULL
		if (count($value) > 0)  {
			# save new array
			$radiofavarray = file_put_contents($tuneinradiotmp, json_encode($value));
			LOGOK ("queue.php: New TuneIn Favorite array has been saved");
		} else {
			# if last element loaded delete files
			if(isset($_GET['member'])) {
				removemember();
				LOGINF ("queue.php: Member has been removed");
			}
			@unlink($tuneinradiotmp);
			LOGINF ("queue.php: Files has been deleted");
			LOGOK ("queue.php: ** Loop ended, we start from beginning **");
		}
	} else {
		LOGWARN ("queue.php: TuneIn Stations File could not be loaded!");
		exit;
	}
}



/**
* Function : PlayPlaylistFavorite --> load and play Sonos Favorites Playlists
* 
* 
* @param: empty
* @return: 
**/

function PlayPlaylistFavorites()
{
	global $sonos, $volume, $value, $sonoszone, $master, $queuepltmp;

	if (file_exists($queuepltmp))  {
		# load previously saved Sonos Playlist
		$value = json_decode(file_get_contents($queuepltmp), TRUE);
		#print_r($value);
		LOGOK ("queue.php: Your Favorite Playlists has been loaded");
		# add Playlist
		$sonos->ClearQueue();
		$sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0");
		if (count($value) >= 1)  {
			$proof = @metadata($value[0]);
		}
		if ($proof === false)   {
			array_shift($value);
			LOGINF ("queue.php: Favorite Playlist could not be loaded and has been removed");
			LOGOK ("queue.php: Next Favorite Playlist will be loaded");
			$sonos->ClearQueue();
			metadata($value[0]);
		}
		$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your playlist has been prepared!");
		# check addionally if Playlist has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				$sonos->Play();
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Current playing Playlist has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Playlist has been removed from array (Loading failed)");
			}
		}
		# check array if NULL
		if (count($value) > 0)  {
			# save new array
			$radiofavarray = file_put_contents($queuepltmp, json_encode($value));
			LOGOK ("queue.php: New Favorite Playlists array has been saved");
		} else {
			# if last element loaded delete files
			if(isset($_GET['member'])) {
				removemember();
				LOGINF ("queue.php: Member has been removed");
			}
			@unlink($queuepltmp);
			LOGINF ("queue.php: File has been deleted");
			LOGOK ("queue.php: ** Loop ended, we start from beginning **");
		}
	} else {
		LOGWARN ("queue.php: Favorite Playlists File could not be loaded!");
		exit;
	}
}



/**
* Function : metadata --> creates metadata for Sonos
* 
* 
* @param: array of BrowseFavoriten()
* @return: generate Metadata and load Tracks/RadioStations/Playlist
**/

function metadata($value) 
{
	global $sonos, $volume, $sonoszone, $master, $services, $radiofav, $radiolist, $radiofavtmp, $file, $meta, $stype;
	
	#print_r($value);
	
	# BACKUP Scenario... check if sid exist, if not assign unknown
	if (array_key_exists('sid', $value) === true)   {
		$sid = $value['sid'];
	} else {
		$sid = "000";
		LOGINF ("No 'sid' has been received.");
	}

	switch ($sid) {
		case "201":		// Amazon music
			$file = htmlspecialchars(str_replace(array("/", "&"), array("%2f", "&amp;"), $value['resorg']));
			if ($value['typ'] == "Radio")    {
				$stype = "Amazon Radio Favorite";
				$parentid = str_replace(array("/"), array("%2f"), $value['parentid']);
				$id = str_replace(array("/"), array("%2f"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					SetAVToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Track")   {
				$stype = "Amazon Track Favorite";
				$artist = str_replace(array("&"), array("&amp;amp;"), $value['artist']);;
				$parentid = str_replace(array("/"), array("%2f"), $value['parentid']);
				$id = str_replace(array("/"), array("%2f"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($artist)."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['sid'] == "201" and $value['typ'] == "Playlist")   {
				$stype = "Amazon Playlist Favorite";
				$artist = str_replace(array("&"), array("&amp;amp;"), $value['artist']);;
				$parentid = str_replace(array("/"), array("%2f"), $value['parentid']);
				$id = str_replace(array("/"), array("%2f"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($artist)."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		
		
		case "303":		// Sonos Radio
			$stype = "Sonos Radio Favorite";
			$file = $value['resorg'];
			$id = str_replace(array(":"), array("%3a"), $value['id']);
			$parentid = str_replace(array("/"), array("%2f"), $value['parentid']);
			$albumArtURI = str_replace(array("&"), array("&amp;amp;"), $value['albumArtURI']);
			$meta ="&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$albumArtURI."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
			try {			
				SetAVToQueue($file, $meta, $value, $stype);
				return true;
			} catch (Exception $e) {
				AddFavToQueueCatch($file, $meta, $value, $stype);
				return false;
			}
		break;
		
		case "998":		// Sonos Playlist
			$stype = "Sonos Playlist";
			$file = $value['resorg'];
			$token = "RINCON_AssociatedZPUDN";
			$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;".urldecode($value['id'])."&quot; parentID=&quot;".urldecode($value['parentid'])."&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;object.container.playlistContainer&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;Sonos-Playlist&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$token."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
			try {	
				AddFavToQueue($file, $meta, $value, $stype);
				return true;
			} catch (Exception $e) {
				AddFavToQueueCatch($file, $meta, $value, $stype);
				return false;
			}
		break;
		
		case "999":		// Local Music
			if (substr($value['resorg'], 0, 11) == "x-file-cifs")   {
				$stype = "Local Music Track";
				$getupnpclass = "object.item.audioItem.musicTrack";
			} elseif (substr($value['resorg'], 0, 17) == "x-rincon-playlist")   {
				$stype = "Local Music Album";
				$getupnpclass = "object.container.album.musicAlbum";
			}
			$file = $value['resorg'];
			$token = "RINCON_AssociatedZPUDN";
			$parentid = $value['parentid'];
			$id = $value['id'];
			$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".($value['id'])." parentID=".$value['parentid']." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$getupnpclass."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".htmlspecialchars($value['albumArtURI'])."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$token."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
			try {	
				AddFavToQueue($file, $meta, $value, $stype);
				return true;
			} catch (Exception $e) {
				AddFavToQueueCatch($file, $meta, $value, $stype);
				return false; 
			}
		break;
		
		
		case "160":		// Soundcloud
			$stype = "Soundcloud Track Favorite";
			if ($value['typ'] == "Track")    {
				$file = htmlspecialchars(str_replace(array("/", "&"), array("%2f", "&amp;"), $value['resorg']));
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$title = htmlspecialchars($value['title']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				#echo htmlspecialchars($meta);
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Soundcloud Playlist Favorite";
				$tmpfile1 = substr($value['resorg'], 21, 500);
				$tmpfile2 = htmlspecialchars(str_replace(array(":", "&"), array("%3a", "&amp;"), $tmpfile1));
				$file = "x-rincon-cpcontainer:".$tmpfile2;
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		
		
		case "9":		// Spotify 
			if ($value['typ'] == "Playlist")    {
				$stype = "Spotify Playlist Favorite";
				$tmpfile1 = substr($value['resorg'], 21, 500);
				$tmpfile2 = htmlspecialchars(str_replace(array(":", "&"), array("%3a", "&amp;"), $tmpfile1));
				$file = "x-rincon-cpcontainer:".$tmpfile2;
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				#echo htmlspecialchars($meta);
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Track")   {
				$stype = "Spotify Track Favorite";
				$file = htmlspecialchars(str_replace(array("/", "&"), array("%2f", "&amp;"), $value['resorg']));
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		
		
		case "181":		// Mixcloud
			if ($value['typ'] == "Track")    {
				$stype = "Mixcloud Track Favorite";
				$file = htmlspecialchars(str_replace(array("/", "&"), array("%2f", "&amp;"), $value['resorg']));
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Mixcloud Playlist Favorite";
				$tmpfile1 = substr($value['resorg'], 21, 500);
				$tmpfile2 = htmlspecialchars(str_replace(array(":", "&"), array("%3a", "&amp;"), $tmpfile1));
				$file = "x-rincon-cpcontainer:".$tmpfile2;
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		
		
		case "254":		// TuneIn Radio
			if (!isset($value['protocolInfo']))    {
				$stype = "TuneIn";
				$file = htmlspecialchars(str_replace(array("/", "&"), array("%2f", "&amp;"), $value['resorg']));
				$UpnpClass = "object.item.audioItem.audioBroadcast";
				$token = "SA_RINCON65031_";
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=&quot;".($value['id'])."&quot; parentID=&quot;".($value['parentid'])."&quot; restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$UpnpClass."&lt;/upnp:class&gt;&lt;r:description&gt;TuneIn Radio&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$token."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					SetAVToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				$stype = "TuneIn Favorite";
				$file = htmlspecialchars($value['resorg']);
				$UpnpClass = "object.item.audioItem.audioBroadcast";
				$token = "SA_RINCON65031_";
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".($value['id'])." parentID=".($value['parentid'])." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$UpnpClass."&lt;/upnp:class&gt;&lt;r:description&gt;TuneIn Radio&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$token."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					SetAVToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			}
		break;
		
		
		case "2":		// Deezer
			if ($value['typ'] == "Track")    {
				$stype = "Deezer Track Favorite";
				$file = htmlspecialchars(str_replace(array(":", "&"), array("%3a", "&amp;"), $value['resorg']));
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$value['parentid']." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Radio")   {
				$stype = "Deezer Radio Favorite";
				$file = htmlspecialchars($value['resorg']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$value['id']." parentID=".$value['parentid']." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					SetAVToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Playlist")   {
				$stype = "Deezer Playlist Favorite";
				$file = htmlspecialchars(str_replace(array("&"), array("&amp;"), $value['resorg']));
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$value['parentid']." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		
		
		case "204":		// Apple Music
			if ($value['typ'] == "Radio")    {
				$stype = "Apple Radio Favorite";
				$file = htmlspecialchars($value['resorg']);
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					SetAVToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Track")    {
				$stype = "Apple Track Favorite";
				$file = htmlspecialchars(str_replace(array("/", "&"), array("%2f", "&amp;"), $value['resorg']));
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$artist = str_replace(array("&", "ë"), array("&amp;amp;", "."), $value['artist']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($artist)."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Apple Playlist Favorite";
				$tmpfile1 = substr($value['resorg'], 21, 500);
				$tmpfile2 = htmlspecialchars(str_replace(array(":"), array("%3a"), $tmpfile1));
				$file = "x-rincon-cpcontainer:".$tmpfile2;
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		
		
		case "203":		// Napster
			if ($value['typ'] == "Radio")    {
				$stype = "Napster Radio Favorite";
				$file = htmlspecialchars($value['resorg']);
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					SetAVToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Track")    {
				$stype = "Napster Track Favorite";
				$file = htmlspecialchars($value['resorg']);
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Napster Playlist Favorite";
				$tmpfile1 = substr($value['resorg'], 21, 500);
				$tmpfile2 = htmlspecialchars(str_replace(array(":", "&"), array("%3a", "&amp;"), $tmpfile1));
				$file = "x-rincon-cpcontainer:".$tmpfile2;
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		
		case "284":		// YoutTube Music
			if ($value['typ'] == "Track")    {
				$stype = "YouTube Track Favorite";
				$file = htmlspecialchars($value['resorg']);
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "YouTube Playlist Favorite";
				$tmpfile1 = substr($value['resorg'], 21, 500);
				$tmpfile2 = htmlspecialchars(str_replace(array(":", "&"), array("%3a", "&amp;"), $tmpfile1));
				$file = "x-rincon-cpcontainer:".$tmpfile2;
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		
		case "174":		// Tidal
			if ($value['typ'] == "Track")    {
				$stype = "Tidal Track Favorite";
				$file = htmlspecialchars($value['resorg']);
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} elseif ($value['typ'] == "Playlist")    {
				$stype = "Tidal Playlist Favorite";
				$tmpfile1 = substr($value['resorg'], 21, 500);
				$tmpfile2 = htmlspecialchars(str_replace(array(":", "&"), array("%3a", "&amp;"), $tmpfile1));
				$file = "x-rincon-cpcontainer:".$tmpfile2;
				$parentid = str_replace(array(":"), array("%3a"), $value['parentid']);
				$id = str_replace(array(":"), array("%3a"), $value['id']);
				$meta = "&lt;DIDL-Lite xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot; xmlns:upnp=&quot;urn:schemas-upnp-org:metadata-1-0/upnp/&quot; xmlns:r=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot; xmlns=&quot;urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/&quot;&gt;&lt;item id=".$id." parentID=".$parentid." restricted=&quot;true&quot;&gt;&lt;dc:title&gt;".htmlspecialchars($value['title'])."&lt;/dc:title&gt;&lt;upnp:class&gt;".$value['UpnpClass']."&lt;/upnp:class&gt;&lt;upnp:albumArtURI&gt;".$value['albumArtURI']."&lt;/upnp:albumArtURI&gt;&lt;r:description&gt;".htmlspecialchars($value['artist'])."&lt;/r:description&gt;&lt;desc id=&quot;cdudn&quot; nameSpace=&quot;urn:schemas-rinconnetworks-com:metadata-1-0/&quot;&gt;".$value['token']."&lt;/desc&gt;&lt;/item&gt;&lt;/DIDL-Lite&gt;";
				try {			
					AddFavToQueue($file, $meta, $value, $stype);
					return true;
				} catch (Exception $e) {
					AddFavToQueueCatch($file, $meta, $value, $stype);
					return false;
				}
			} else {
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
			
		case "000":		// Streaming Service identified as unkown
			$file = $value['resorg'];
			$meta = "";
			LOGWARN ("Your Sonos Favorite '".$value['title']."' could not be added because your Streaming Service '000' is unknown");
			LOGWARN ("If you are interest to get missing type added AND your familiar using Application 'Wireshark' you can contact me or remove Favorite from your Sonos Favorites");
			CreateDebugFile();
			return false;
		break;
		
		default:
			if (isService($sid) === true)   {
				$file = $value['resorg'];
				$meta = "";
				$stype = "unknown";
				AddFavToQueueError($file, $meta, $value, $stype, $services, $sid);
				return false;
			}
		break;
		return;
	}
}


/**
* Funktion : 	isService --> prüft ob die gefundene SID unterstützt wird
*
* @param: 	$sid --> sid von URI
* @return:  $services --> Not available Service
**/

# source: https://svrooij.io/sonos-api-docs/music-services.html#current-music-services and https://svrooij.io/sonos-api-docs/music-services.json

 function isService($sid) {
	 
	global $services;
	# available Sonos Streaming Services: https://support.sonos.com/s/article/3459?language=en_US
    $services = [
            "38"=>"7digital",
			"321"=>"80s80s - REAL 80s Radio",
			"201"=>"Amazon Music",
			"198"=>"Anghami",
			"204"=>"Apple Music",
			"275"=>"ARTRADIO - RadioArt.com",
			"306"=>"Atmosphere by Kollekt.fm",
			"239"=>"Audible",
			"219"=>"Audiobooks.com",
			"157"=>"Bandcamp",
			"307"=>"Bookmate",
			"283"=>"Calm",
			"144"=>"Calm Radio",
			"256"=>"CBC Radio & Music",
			"191"=>"Classical Archives",
			"315"=>"Convoy Network",
			"213"=>"Custom Channels",
			"2"=>"Deezer",
			"234"=>"deliver.media",
			"285"=>"Epidemic Spaces",
			"182"=>"FamilyStream",
			"217"=>"FIT Radio Workout Music",
			"192"=>"focus@will",
			"167"=>"Gaana",
			"279"=>"Global Player",
			"36"=>"Hearts of Space",
			"45"=>"hotelradio.fm",
			"310"=>"iBroadcast",
			"271"=>"IDAGIO",
			"300"=>"JUKE",
			"305"=>"Libby by OverDrive",
			"221"=>"LivePhish+",
			"260"=>"Minidisco",
			"181"=>"Mixcloud",
			"171"=>"Mood Mix",
			"33"=>"Murfie",
			"262"=>"My Cloud Home",
			"268"=>"myTuner Radio",
			"203"=>"Napster",
			"277"=>"NRK Radio",
			"230"=>"NTS Radio",
			"222"=>"nugs.net",
			"324"=>"Piraten.FM",
			"212"=>"Plex",
			"233"=>"Pocket Casts",
			"265"=>"PowerApp",
			"31"=>"Qobuz",
			"294"=>"Radio Javan",
			"308"=>"Radio Paradise",
			"264"=>"radio.net",
			"154"=>"Radionomy",
			"162"=>"radioPup",
			"312"=>"Radioshop",
			"223"=>"RauteMusik.FM",
			"270"=>"Relisten",
			"150"=>"RUSC",
			"164"=>"Saavn",
			"303"=>"Sonos Radio",
			"160"=>"Soundcloud",
			"189"=>"SOUNDMACHINE",
			"218"=>"Soundsuit.fm",
			"295"=>"Soundtrack Player",
			"9"=>"Spotify",
			"163"=>"Spreaker",
			"184"=>"Stingray Music",
			"13"=>"Stitcher",
			"237"=>"storePlay",
			"226"=>"Storytel",
			"235"=>"Sveriges Radio",
			"211"=>"The Music Manager",
			"174"=>"TIDAL",
			"287"=>"toníque",
			"169"=>"Tribe of Noise",
			"193"=>"Tunify for Business",
			"254"=>"TuneIn",
			"231"=>"Wolfgang's Music",
			"272"=>"Worldwide FM",
			"317"=>"Yogi Tunes",
			"284"=>"YouTube Music",
			"998"=>"Sonos Playlist",
			"999"=>"Local Music",
			"000"=>"unknown",
        ];
    return in_array($sid, array_keys($services));
}



 function AddFavToQueue($file, $meta, $value, $stype)
{
	global $sonos, $file, $meta, $stype;
	
	@$sonos->AddFavoritesToQueueMeta($file, $meta);
	LOGINF ("queue.php: ".$stype." '".htmlspecialchars($value['title'])."' has been added");
	return;
}

function SetAVToQueue($file, $meta, $value, $stype)
{
	global $sonos, $file, $meta, $stype;

	@$sonos-> SetAVTransportURI($file, $meta);
	LOGINF ("queue.php: Sonos Radio Favorite '".htmlspecialchars($value['title'])."' has been added");
	return;
}

function AddFavToQueueCatch($file, $meta, $value, $stype)
{
	global $sonos, $file, $meta, $stype, $sid;
	
	LOGWARN ("Streaming type: ".$stype." '".htmlspecialchars($value['title'])."' could not be added");
	LOGWARN ("If you are interest to get missing type added AND your familiar using Application 'Wireshark' you can contact me or remove Favorite from your Sonos Favorites");
	CreateDebugFile();
	return;
}

function AddFavToQueueError($file, $meta, $value, $stype, $services, $sid)
{
	global $sonos, $file, $meta, $stype;
	
	LOGWARN ("Your Sonos Favorite '".htmlspecialchars($value['title'])."' could not be added because your Streaming Service '".$services[$sid]."' is currently not supported");
	LOGWARN ("If you are interest to get missing type added AND your familiar using Application 'Wireshark' you can contact me or remove Favorite from your Sonos Favorites");
	CreateDebugFile();
	return;
}

function CreateDebugFile()
{
	global $sonos, $debugfile, $value, $meta, $file;

	$favorite 						= $value[0];
	$favorite['CurrentURI'] 		= $file;
	$favorite['CurrentURIMetaData'] = $meta;
	file_put_contents($debugfile, json_encode($favorite));
	LOGWARN ("Debug file '".$debugfile."' has been created and saved. Please pick up file and send to 'olewald64@gmail.com' so i can support you adding missing Streaming type/Streaming Service");
}

function GetUpnpClass($parentid)  {
	
	switch ($parentid) {
      case 'A:ALBUM':
        return 'object.item.audioItem.musicAlbum';
		#return 'object.container.album.musicAlbum';	// Wireshark return
	  break;
      case 'A:TRACKS':
        return 'object.item.audioItem.musicTrack';
		#return 'object.item.audioItem.musicTrack';		// Wireshark return
	  break;
      case 'A:ALBUMARTIST':
        return 'object.item.audioItem.musicArtist';
		#return 'object.container.person.musicArtist':		// Wireshark return
	  break;
      case 'A:GENRE':
        return 'object.container.genre.musicGenre';
		#return 'object.container.album.musicAlbum';		// Wireshark return
	  break;
      case 'A:COMPOSER':
        return 'object.container.person.composer';
	  break;
      default:
        return '';
	  break;
    }
}

?>