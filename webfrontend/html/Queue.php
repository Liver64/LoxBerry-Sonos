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
		$sonos = new SonosAccess($config['sonoszonen'][$master][0]);
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
				@unlink($zname);
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
			$sonos = new SonosAccess($sonoszone[$zone][0]);
			$state = $sonos->GetTransportInfo();			// only playing zones
			$posinfo = $sonos->GetPositionInfo();	
			if ($state == '1' and $sonoszone[$zone][1] != $sonoszone[$master][1] and substr($posinfo["TrackURI"], 0, 18) != "x-sonos-htastream:")   {				// except masterzone
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
		$sonos = new SonosAccess($config['sonoszonen'][$master][0]);
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
	global $sonos, $volume, $browse, $sonoszone, $re, $master, $favtmp;
	
	# if playlist has been loaded iterate through tracks
	if (file_exists($favtmp))  {
		LOGINF("queue.php: Playlist file has been loaded, we start iterating through Playlist.");
		$countqueue = count($sonos->GetCurrentPlaylist());
		$currtrack = $sonos->GetPositioninfo();
		if ($currtrack['Track'] != $countqueue)    {
			NextTrack();
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
		AddMemberTo();
		LOGINF ("queue.php: Member has been added");
	}
	$sonos->Stop();
	$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
	@$sonos->SetGroupMute(false);
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
	
	$tes = $sonos->GetFavorites();
	echo "Only Tracks and Radio Stations are supported, no Albums/playlists, except for fucntion 'playfavorite@favorite=TITLE'";
	echo "Fuzzy Logic search is possible by Title/Playlist or Radio Station";
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
				NextTrack();
				LOGINF ("queue.php: Favorite Tracks are running");
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
					#removemember();
					LOGINF ("queue.php: Member has been removed");
				}
				@unlink($radiofav);
				@unlink($radiofavtmp);
				@unlink($queuetmp);
				LOGINF ("queue.php: Files has been deleted");
				LOGOK ("queue.php: ** Loop ended, we start from beginning **");
			}
		}
		return;
	} 
	#echo "Count: ".count($sonos->GetCurrentPlaylist());
	try {
		if (count($sonos->GetCurrentPlaylist()) > 0 )  {
			NextTrack();
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
		@$sonos->SetGroupMute(false);
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
				# Set variable true
				$shift = true;
			}
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
			NextTrack();
			return true;
		} else {
			@unlink($queuetracktmp);
			if(isset($_GET['member'])) {
				#removemember();
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
	@$sonos->SetGroupMute(false);
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
			@$sonos->Play();
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
			$proof = @metadata($value[0]);
		}
		@$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your favorite has been prepared!");
		# check addionally if Radio Station has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				@$sonos->Play();
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Radio Favorite has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGWARN ("queue.php: PlayRadio: Radio Favorite has been removed from array (Loading failed)");
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
				#removemember();
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
		@$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your playlist has been prepared!");
		# check addionally if Playlist has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				@$sonos->Play();
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Sonos Playlist has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGWARN ("queue.php: Sonos Playlist has been removed from array (Loading failed)");
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
				#removemember();
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
		@$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your TuneIn Radio Station has been prepared!");
		# check addionally if Radio Station has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				@$sonos->Play();
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: TuneIn Favorite has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGWARN ("queue.php: TuneIn Favorite has been removed from array (Loading failed)");
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
				#removemember();
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
			@metadata($value[0]);
		}
		@$sonos->SetGroupMute(false);
		$sonos->SetVolume($volume);
		LOGINF("queue.php: Settings to play your playlist has been prepared!");
		# check addionally if Playlist has been successful loaded
		$mediainfo = $sonos->GetMediaInfo();
		if ($mediainfo['CurrentURI'] != "")  {
			try {
				@$sonos->Play();
				# remove 1st element of array
				array_shift($value);
				LOGINF ("queue.php: Favorite Playlist has been removed from array.");
			} catch (Exception $e) {
				# remove 1st element of array
				array_shift($value);
				LOGWARN ("queue.php: Favorite Playlist has been removed from array (Loading failed)");
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
				#removemember();
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




?>