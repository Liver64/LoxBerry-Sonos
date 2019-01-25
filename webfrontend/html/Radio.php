<?php

/**
* Submodul: Radio
*
**/

/**
/* Funktion : radio --> lädt einen Radiosender in eine Zone/Gruppe
/*
/* @param: Sender                             
/* @return: nichts
**/

function radio(){
	Global $sonos, $volume, $config, $sonoszone, $master;
			
	if(isset($_GET['radio'])) {
        $playlist = $_GET['radio'];		
	} elseif (isset($_GET['playlist'])) {
		$playlist = $_GET['playlist'];		
	} else {
		LOGGING("No radio stations found.", 4);
    }
	$coord = $master;
	$roomcord = getRoomCoordinator($coord);
	$sonosroom = new PHPSonos($roomcord[0]); //Sonos IP Adresse
	$sonosroom->SetQueue("x-rincon-queue:".$roomcord[1]."#0");
	$sonosroom->SetMute(false);
	$sonosroom->Stop();
    # Sonos Radio Playlist ermitteln und mit übergebene vergleichen   
    $radiolists = $sonos->Browse("R:0/0","c");
	$radioplaylist = urldecode($playlist);
	$rleinzeln = 0;
    while ($rleinzeln < count($radiolists)) {
	if ($radioplaylist == $radiolists[$rleinzeln]["title"]) {
		$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]),$radiolists[$rleinzeln]["title"]);
		#$sonos->SetRadio(urldecode($radiolists[$rleinzeln]["res"]));
		if (isset($_GET['member'])) {
			$member = $_GET['member'];
			$member = explode(',', $member);
			if (isset($_GET['standardvolume'])) {
				foreach ($member as $zone) {
					$sonos = new PHPSonos($sonoszone[$zone][0]); //Sonos IP Adresse
					$volume = $config['sonoszonen'][$zone][4];
					$sonos->SetVolume($config['sonoszonen'][$zone][4]);
				}
			}
			$sonos = new PHPSonos($roomcord[0]); //Sonos IP Adresse
			$sonosroom->SetVolume($config['sonoszonen'][$master][4]);
		} else {
			if(empty($config['TTS']['volrampto'])) {
				check_rampto();
			} else {
				$sonos->SetVolume($volume);
			}
		}
		if(!isset($_GET['load'])) {
			$sonos->Play();
		}
    }
	$rleinzeln++;
	}   
	LOGGING("Radio Station '".$playlist."' has been loaded successful",6);
}

/**
* Function: nextradio --> iterate through Radio Favorites (endless)
*
* @param: empty
* @return: 
**/
function nextradio() {
	global $sonos, $config, $master, $debug, $volume, $tmp_tts;
	
	if (file_exists($tmp_tts))  {
		LOGGING("Currently a T2S is running, we skip nextradio for now. Please try again later.",3);
		exit;
	}
	
	$sonos = new PHPSonos($config['sonoszonen'][$master][0]);
	$radioanzahl_check = count($config['RADIO']);
	if($radioanzahl_check == 0)  {
		LOGGING("There are no Radio Stations maintained in the config. Pls update before using function NEXTRADIO or ZAPZONE!", 3);
		exit;
	}
	$playstatus = $sonos->GetTransportInfo();
	if(isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100) {
		$so_volume = $_GET['volume'];
		LOGGING("Volume from syntax been used", 7);		
	} else 	{
		// übernimmt Standard Lautstärke der angegebenen Zone aus der config
		$so_volume = $config['sonoszonen'][$master][4];
		LOGGING("Standard Volume from config been used", 7);		
	}
	$radioname = $sonos->GetMediaInfo();
	if (!empty($radioname["title"])) {
		$senderuri = $radioname["title"];
	} else {
		$senderuri = "";
	}
	$radio = $config['RADIO']['radio'];
	ksort($radio);
	$radioanzahl = count($config['RADIO']['radio']);
	$radio_name = array();
	$radio_adresse = array();
	foreach ($radio as $key) {
		$radiosplit = explode(',',$key);
		array_push($radio_name, $radiosplit[0]);
		array_push($radio_adresse, $radiosplit[1]);
	}
	$senderaktuell = array_search($senderuri, $radio_name);
	# Wenn nextradio aufgerufen wird ohne eine vorherigen Radiosender
	if( $senderaktuell == "" && $senderuri == "" || substr($senderuri, 0, 12) == "x-file-cifs:" ) {
		$senderaktuell = -1;
	}
	if ($senderaktuell == ($radioanzahl) ) {
		$sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[0], $radio_name[0]);
	}
    if ($senderaktuell < ($radioanzahl) ) {
		@$sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[$senderaktuell + 1], $radio_name[$senderaktuell + 1]);
	}
    if ($senderaktuell == $radioanzahl - 1) {
	    $sonos->SetRadio('x-rincon-mp3radio://'.$radio_adresse[0], $radio_name[0]);
	}
	$info_r = "\r\n Senderuri vorher: " . $senderuri . "\r\n";
	$info_r .= "Sender aktuell: " . $senderaktuell . "\r\n";
	$info_r .= "Radioanzahl: " .$radioanzahl;
	LOGGING('Next Radio Info: '.($info_r),7);
    if ($config['VARIOUS']['announceradio'] == 1) {
		say_radio_station();
	}
    if($playstatus == 1) {
		$sonos->SetVolume($so_volume);
		$sonos->Play();
	} else {
		check_rampto();
		$sonos->Play();
	}
	LOGGING("Radio Station '".$radioname["title"]."' has been loaded successful by nextradio",6);
	#print_r($radio_name);
}


/**
* Funktion : 	random_radio --> lädt per Zufallsgenerator einen Radiosender und spielt ihn ab.
*
* @param: empty
* @return: Radio Sender
**/

function random_radio() {
	global $sonos, $sonoszone, $master, $volume, $config;
	
	if (isset($_GET['member'])) {
		LOGGING("This function could not be used with groups!", 3);
		exit;
	}
	$sonoslists = $sonos->Browse("R:0/0","c");
	print_r($sonoslists);
	if(!isset($_GET['except'])) {
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	} else {
		$except = $_GET['except'];
		$exception = explode(',',$except);
		for($i = 0; $i < count($exception); $i++) {
			$exception[$i] = str_replace(' ', '', $exception[$i]);
		}
		foreach ($exception as $key => $val) {
			unset($sonoslists[$val]);
		}
		$sonoslists = array_values($sonoslists);
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	}
	$sonos->ClearQueue();
	$sonos->SetMute(false);
	$sonos->SetRadio(urldecode($sonoslists[$random]["res"]),$sonoslists[$random]["title"]);
	if (!isset($_GET['volume'])) {
		check_rampto();
	} else {
		$radiovolume = $sonos->GetVolume();
		$sonos->SetVolume($radiovolume);
	}
	$sonos->Play();
	LOGGING("Radio Station '".$sonoslists[$random]["title"]."' has been loaded successful by randomradio",6);
}

?>