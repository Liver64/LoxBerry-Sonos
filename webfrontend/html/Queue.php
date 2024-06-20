<?php

/**
* Function: zap --> checks each zone in network and if playing add current zone as member - NEW zapzone
*
* @param: empty
* @return: 
**/

function zap()
{
	global $sonos, $config, $tmp_tts, $maxzap, $volume, $sonoszone, $master, $zapname, $lbphtmldir, $lbhomedir, $lbpplugindir;
	
	#$zapname = "/run/shm/s4lox_zap_zone.json";								// queue.php: file containig running zones
	
	
	# check if TTS is currently running
	if (file_exists($tmp_tts))  {
		LOGGING("queue.php: Currently a T2S is running, we skip zapzone for now. Please try again later.", 4);
		exit;
	}
	# become single zone 1st
	$sonos = new SonosAccess($config['sonoszonen'][$master][0]);
	$check_stat = getZoneStatus($master);
	if ($check_stat != (string)"single")  {
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGGING("queue.php: Zone ".$master." has been ungrouped.",5);
	}
	# set cronjob default in case nothing has been selected
	if ($config['VARIOUS']['cron'] == "")   {
		system ("ln -s $lbphtmldir/bin/cronjob.sh $lbhomedir/system/cron/cron.01min/$lbpplugindir");
		@unlink ("$lbhomedir/system/cron/cron.03min/$lbpplugindir");
		@unlink ("$lbhomedir/system/cron/cron.05min/$lbpplugindir");
		@unlink ("$lbhomedir/system/cron/cron.10min/$lbpplugindir");
		@unlink ("$lbhomedir/system/cron/cron.30min/$lbpplugindir");
		LOGGING("queue.php: Please configure zapzone settings in Sonos Plugin under 'Details/Reset after:'",4);
	}
	# set subfunction default in case nothing has been selected
	if ($config['VARIOUS']['selfunction'] === "")   {
		$subfunction = "nextradio";
		LOGGING("queue.php: Please configure zapzone settings in Sonos Plugin under 'Sub-Funktion für ZAPZONE:'",4);
	} else {
		$subfunction = $config['VARIOUS']['selfunction'];
	}
	#LOGGING("queue.php: selected Subfunction: ".$config['VARIOUS']['selfunction'], 7);
	#LOGGING("queue.php: Cronjob: ".$config['VARIOUS']['cron']." Min.", 7);
	
	# START ZAPZONE
	if (is_file($zapname) === false)  {
		LOGGING("queue.php: Start ZAPZONE", 7);
		$runarray = array();
		foreach ($sonoszone as $zone => $player) {
			$sonos = new SonosAccess($sonoszone[$zone][0]);
			$state = $sonos->GetTransportInfo();			// select only playing zones
			$posinfo = $sonos->GetPositionInfo();	
			#LOGGING("GetPositionInfo for ".$zone.": ".$posinfo['TrackURI'], 7);
			if ($state == '1' and $sonoszone[$zone][1] != $sonoszone[$master][1] and substr($posinfo["TrackURI"], 0, 18) != "x-sonos-htastream:")   {				// except masterzone
				$u = getZoneStatus($zone);
				if ($u <> "member")    {
					array_push($runarray, $zone, $sonoszone[$zone][0], $sonoszone[$zone][1]); 		// add IP-address to array
				}
			}
		}
		$countzones = count($runarray);
		if ($countzones == 0)  {
			$empty = Array();
			LOGGING("queue.php: Currently no zone is running or last Zone has been reached, we switch to Sub-Function",7);
			file_put_contents($zapname, json_encode($empty));
			PlayZapzoneNext();
			LOGGING("queue.php: Zapzone Sub-Function '".$subfunction."' has been called ",7);
		} else {
			# join zone to currently running zone
			$sonos = new SonosAccess($config['sonoszonen'][$master][0]);
			$sonos->SetAVTransportURI("x-rincon:" . $runarray[2]);
			LOGGING("queue.php: Zone ".$master." has been added as member to Zone ".$runarray[0],7);
			sleep(1);
			array_shift($runarray);										// remove 1st Zone Name and re-index array
			array_shift($runarray);										// remove 1st Zone Rincon-ID and re-index array
			array_shift($runarray);										// remove 1st Zone IP and re-index array
			DeleteTmpFavFiles();
			$result = file_put_contents($zapname, json_encode($runarray));		// save file
			if ($result === false)    {
				LOGGING("queue.php: Writing file '".$zapname."' failed. Pls. check your Loxberry settings!", 7);
			}
		}
	} else {
		LOGGING("queue.php: Continue ZAPNAME", 7);
		$file = json_decode(file_get_contents($zapname), true); 
		$countzapfile = count($file);
		if ($countzapfile == 0)  {
			LOGGING("queue.php: Currently no zone is running or last Zone has been reached, we switch to Sub-Function",7);
			PlayZapzoneNext();
			LOGGING("queue.php: Sub-Function '".$subfunction."' has been called ",7);
			exit;
		} else {
			# add master to zone and remove zone from array
			$sonos = new SonosAccess($config['sonoszonen'][$master][0]);
			$sonos->SetAVTransportURI("x-rincon:" . $file[2]);
			LOGGING("queue.php: Zone ".$master." has been added as member to Zone ".$file[0],7);
			sleep(1);
			array_shift($file);
			array_shift($file);
			array_shift($file);
			# save new array again
			$result = file_put_contents($zapname, json_encode($file));
			if ($result === false)    {
				LOGGING("queue.php: Writing file '".$zapname."' failed. Pls. check your Loxberry settings!", 7);
			}
		}
	}
}


/**
* Function : FuncZapzone --> load and play dependend on saved ZAPZONE function
* 
* 
* @param: empty
* @return: play Favorite
**/

function PlayZapzoneNext() 
{
	global $sonos, $config, $volume, $zapname, $queuetracktmp, $browse, $sonoszone, $re, $master, $favtmp;
	
	$value  = substr($config['VARIOUS']['selfunction'], 0, 4);
	
	$empty = array();
	if ($config['VARIOUS']['selfunction'] == "nextradio")   {
		nextradio();
		file_put_contents($zapname, json_encode($empty));
		return "nextradio";
		
	} elseif ($config['VARIOUS']['selfunction'] == "trackfavorites")   {
		PlayTrackFavorites();
		file_put_contents($zapname, json_encode($empty));
		return "trackfavorites";
		
	} elseif ($config['VARIOUS']['selfunction'] == "playlistfavorites")   {
		PlayPlaylistFavorites();
		file_put_contents($zapname, json_encode($empty));
		return "playlistfavorites";
		
	} elseif ($config['VARIOUS']['selfunction'] == "radiofavorites")   {
		PlayRadioFavorites();
		file_put_contents($zapname, json_encode($empty));
		return "radiofavorites";
		
	} elseif ($config['VARIOUS']['selfunction'] == "tuneinfavorites")   {
		PlayTuneInPlaylist();
		file_put_contents($zapname, json_encode($empty));
		return "tuneinfavorites";
		
	# Radio Station from Radio Favorites
	} elseif ($value == "http")   {
		$index = array_values($config['RADIO']['radio']);
		foreach ($index as $key => $value)   {
			$splitted = explode(",", $value);
			if ($splitted[1] == $config['VARIOUS']['selfunction'])  {
				$sonos->SetRadio('x-rincon-mp3radio://'.trim($splitted[1]), trim($splitted[0]), trim($splitted[2]));
				return $splitted[0];
			}
		}
		return "false";
		
	} else {
		nextradio();
		file_put_contents($zapname, json_encode($empty));
		LOGGING("queue.php: Exception raised... Please configure zapzone or follow settings in Sonos Plugin under Options",4);
		return "nextradio";
	}
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
	global $sonos, $volume, $lookup, $browse, $sonoszone, $re, $master, $favtmp;
	
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
	$re = array();
	foreach ($tes as $key)    {
		if ($favorite === $key['title'])   {
			$favorite = $key['title'];
			array_push($re, array_multi_search($favorite, $tes, "title"));
		}
	}
	$favorite = urldecode($favorite);
	if (count($re) > 1)  {
		LOGERR ("queue.php: Your entered favorite '".$favorite."' has more then 1 hit! Please specify more detailed.");
		exit;
	}
	$check_stat = getZoneStatus($master);
	if ($check_stat != (string)"single")  {
		$sonos->BecomeCoordinatorOfStandaloneGroup();
		LOGGING("queue.php: Zone ".$master." has been ungrouped.",5);
	}
	if(isset($_GET['member'])) {
		AddMemberTo();
		LOGINF ("queue.php: Member has been added");
	}
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$sonos->Stop();
	$sonos->SetQueue("x-rincon-queue:".trim($sonoszone[$master][1])."#0");
	@$sonos->SetGroupMute(false);
	$sonos->SetPlayMode('0'); // NORMAL
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	} 
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
	global $sonos, $volume, $value, $lookup, $sonoszone, $master, $services, $radiofav, $radiolist, $queuetmp, $radiofavtmp;
	
if (count($sonos->GetFavorites()) < 1)    {
				LOGGING("sonos.php: No Sonos Favorites are maintained.", 4);
				exit;
			}
			# 1st click/execution
			if (!file_exists($queuetmp))  {
				$check_stat = getZoneStatus($master);
				if ($check_stat != (string)"single")  {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
				}
				if(isset($_GET['member'])) {
					AddMemberTo();
					$sonos = new SonosAccess($sonoszone[$master][0]);
					LOGINF ("sonos.php: Member has been added");
				}
				DeleteTmpFavFiles();
				@$sonos->ClearQueue();
				LOGGING("Queue has been deleted", 7);
				file_put_contents($queuetmp, json_encode("cleared"));
			}
	
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
			if (isset($_GET['profile']) or isset($_GET['Profile']))    {
				$volume = $lookup[0]['Player'][$master][0]['Volume'];
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
		$sonos->SetPlayMode('0'); // NORMAL
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $lookup[0]['Player'][$master][0]['Volume'];
		} 
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
	global $sonos, $volume, $lookup, $value, $sonoszone, $master, $queuetracktmp;
	
	$browse = AddDetailsToMetadata();
	$browseTracks = count($browse);
		
	if ($browseTracks < 1)    {
		LOGGING("sonos.php: No Sonos Favorites are maintained.", 4);
		exit;
	}
	$filter = "Track";
	$tracks = array_multi_search($filter, $browse);
	if (count($tracks) < 1)    {
		LOGGING("sonos.php: No Sonos Track Favorites are maintained.", 4);
		exit;
	}
	# 1st click/execution
	if (!file_exists($queuetracktmp))  {
		$check_stat = getZoneStatus($master);
		if ($check_stat != (string)"single")  {
			$sonos->BecomeCoordinatorOfStandaloneGroup();
			LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
		}
		if(isset($_GET['member'])) {
			AddMemberTo();
			$sonos = new SonosAccess($sonoszone[$master][0]);
			LOGINF ("sonos.php: Requested Member has been added");
		}
		DeleteTmpFavFiles();
		@$sonos->ClearQueue();
		LOGGING("sonos.php: Queue has been deleted", 7);
	}
	
	
	
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
				#LOGINF ("queue.php: Member has been removed");
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
	#@$sonos->SetGroupMute(false);
	$sonos->SetPlayMode('0'); // NORMAL
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $lookup[0]['Player'][$master][0]['Volume'];
	} 
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
	LOGOK("queue.php: All Track Favorites has been loaded");
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
	global $sonos, $volume, $lookup, $value, $lookup, $sonoszone, $master, $queueradiotmp;
	
	
	$browse = AddDetailsToMetadata();
			$browseRadio = count($browse);
		
			if ($browseRadio < 1)    {
				LOGGING("sonos.php: No Sonos Favorites are maintained.", 4);
				exit;
			}
			$filter = "Radio";
			$radios = array_multi_search($filter, $browse);
			if (count($radios) < 1)    {
				LOGGING("sonos.php: No Sonos Radio Station Favorites are maintained.", 4);
				exit;
			}
			# 1st click/execution
			if (!file_exists($queueradiotmp))  {
				$check_stat = getZoneStatus($master);
				if ($check_stat != (string)"single")  {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
				}
				if(isset($_GET['member'])) {
					AddMemberTo();
					$sonos = new SonosAccess($sonoszone[$master][0]);
					LOGINF ("sonos.php: Member has been added");
				}
				LOGOK ("sonos.php: Your Radio Favorites has been identified");
				DeleteTmpFavFiles();
				@$sonos->ClearQueue();
				LOGGING("sonos.php: Queue has been deleted", 7);
				file_put_contents($queueradiotmp, json_encode($radios));
				LOGINF ("sonos.php: File including all Radio Stations has been saved.");
			} 
			
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
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $lookup[0]['Player'][$master][0]['Volume'];
		} 
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
	global $sonos, $volume, $lookup, $value, $sonoszone, $master, $pltmp;
	
	$browse = $sonos->BrowseContentDirectory("SQ:","BrowseDirectChildren");
			$browseRadio = count($browse);
			
			if ($browseRadio < 1)    {
				LOGGING("sonos.php: No Sonos Playlists are maintained.", 4);
				exit;
			}
			# add Service and sid
			foreach ($browse as $key => $value)  {
				$browse[$key]['Service'] = "Sonos Playlist";
				$browse[$key]['sid'] = "998";
			}
			
			# 1st click/execution
			if (!file_exists($pltmp))  {
				$check_stat = getZoneStatus($master);
				if ($check_stat != (string)"single")  {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
				}
				if(isset($_GET['member'])) {
					AddMemberTo();
					$sonos = new SonosAccess($sonoszone[$master][0]);
					LOGINF ("sonos.php: Member has been added");
				}
				LOGOK ("sonos.php: Your Radio Favorites has been identified");
				DeleteTmpFavFiles();
				@$sonos->ClearQueue();
				LOGGING("sonos.php: Queue has been deleted", 7);
				file_put_contents($pltmp, json_encode($browse));
				LOGINF ("sonos.php: File including all Playlists has been saved.");
			} 
			
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
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $lookup[0]['Player'][$master][0]['Volume'];
		} 
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
	global $sonos, $volume, $lookup, $value, $sonoszone, $master, $tuneinradiotmp;
	
	$browse = $sonos->BrowseContentDirectory("R:0/0","BrowseDirectChildren");
			$browseRadio = count($browse);
		
			if ($browseRadio < 1)    {
				LOGGING("sonos.php: No TuneIn Radio Favorites in 'My Radiostations' are maintained.", 4);
				exit;
			}
			# add Service and sid
			foreach ($browse as $key => $value)  {
				$browse[$key]['Service'] = "TuneIn";
				$browse[$key]['sid'] = "254";
			}
			# 1st click/execution
			if (!file_exists($tuneinradiotmp))  {
				$check_stat = getZoneStatus($master);
				if ($check_stat != (string)"single")  {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
				}
				if(isset($_GET['member'])) {
					AddMemberTo();
					$sonos = new SonosAccess($sonoszone[$master][0]);
					LOGINF ("sonos.php: Member has been added");
				}
				LOGOK ("sonos.php: Your TuneIn Favorite Radio Station has been identified");
				DeleteTmpFavFiles();
				@$sonos->ClearQueue();
				LOGGING("sonos.php: Queue has been deleted", 7);
				file_put_contents($tuneinradiotmp, json_encode($browse));
				LOGINF ("sonos.php: File including all TuneIn Favorite Radio Stations has been saved.");
			} 
	
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
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $lookup[0]['Player'][$master][0]['Volume'];
		} 
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
	global $sonos, $volume, $lookup, $value, $sonoszone, $master, $queuepltmp;
	
	$browse = AddDetailsToMetadata();
			$browseRadio = count($browse);
			#print_r($browse);
		
			if ($browseRadio < 1)    {
				LOGGING("sonos.php: No Sonos Favorites are maintained.", 4);
				exit;
			}
			$filter = "Playlist";
			$radios = array_multi_search($filter, $browse);
			if (count($radios) < 1)    {
				LOGGING("sonos.php: No Sonos Playlist Favorites are maintained.", 4);
				exit;
			}
			#print_r($radios);
			# 1st click/execution
			if (!file_exists($queuepltmp))  {
				$check_stat = getZoneStatus($master);
				if ($check_stat != (string)"single")  {
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
				}
				if(isset($_GET['member'])) {
					AddMemberTo();
					$sonos = new SonosAccess($sonoszone[$master][0]);
					LOGINF ("sonos.php: Member has been added");
				}
				LOGOK ("sonos.php: Your Radio Favorites has been identified");
				DeleteTmpFavFiles();
				@$sonos->ClearQueue();
				LOGGING("sonos.php: Queue has been deleted", 7);
				file_put_contents($queuepltmp, json_encode($radios));
				LOGINF ("sonos.php: File including all Playlists has been saved.");
			} 
			#print_r($radios);
	
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
		if (isset($_GET['profile']) or isset($_GET['Profile']))    {
			$volume = $lookup[0]['Player'][$master][0]['Volume'];
		} 
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