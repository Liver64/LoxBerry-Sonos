<?php

/**
* Funktion : 	AddTrack --> lädt lokales Track
*
* @param: empty
* @return: Track in Queue
**/

// Examples:
# //DESKTOP/G/01 Culcha Candela - Hamma.mp3
# //DESKTOP/E/05 Ich Und Ich - Vom Selben Stern.mp3
# //LOXBERRY/loxberry/data/plugins/sonos4lox/tts/mp3/07 - Eminem - The Monster (feat. Rihanna).mp3
# //LOXBERRY/sonos_tts/mp3/07 - Eminem - The Monster (feat. Rihanna).mp3
# \\SYN-DS415\music\Sonstige\DE_TOP100_01-2014\15 - Family Of The Year - Hero.mp3

function AddTrack() {
	global $sonoszone, $master, $sonos, $volume, $mute;

	$sonos = new PHPSonos($sonoszone[$master][0]);
	$rincon = $sonoszone[$master][1];
	$curr_track_tmp = $sonos->GetPositionInfo();
	// check if Radio/Line IN/TV is playing, then switch to PL
	empty($curr_track_tmp['duration']) ? $sonos->SetQueue("x-rincon-queue:".$sonoszone[$master][1]."#0") : '';
	
	// local Track
	if (isset($_GET['file'])) {  
		$uri = $_GET['file'];
		if (empty($uri)) {
			LOGGING("local_track.php: Please enter file name!", 3);
			exit;
		}
		// check if audio format is supported
		$length = strripos($uri,'.');
		$format = trim(substr($uri, $length +1 , $length + 5));
		$check_audio = AudioFormat(strtoupper($format));
		$check_audio === false ? LOGGING("local_track.php: The entered audio format: '.".$format."' is not supported by Sonos. Please correct!", 3) : '';
		// check diff. usage of syntax (WIN or LINUX)
		$tmp = substr($uri, 0 ,2);
		if ($tmp == "\\\\") {
			$parts = explode("\\", $uri);
		} else {
			$parts = explode("/", $uri);
		}
		$parts = array_map("rawurlencode", $parts);
		$parts[2] = strtoupper($parts[2]);
		$file = implode("/", $parts);
		$track = "x-file-cifs:" . $file;
		$curr_track = $curr_track_tmp['Track'];
		if ($curr_track >= 1 && $curr_track <= 998 && !empty($curr_track_tmp['duration'])) {
			$message_pos = $curr_track + 1;
		} else {
			// No Playlist or Radio/TV is playing 
			$sonos->ClearQueue();
			$message_pos = 1;
		}
		try {
			$service = New SonosMusicService($sonoszone[$master][0]);
			$service->SetLocalTrack($track, $file);
		} catch (Exception $e) {
			LOGGING("local_track.php: The entered file: ".$uri." is not valid or could not be accessed! Please check!", 3);
			exit;
		}
		LOGGING('local_track.php: The entered Local-Track has been loaded',6);
		$sonos->SetTrack($message_pos);
	}
	$sonos->SetVolume($volume);
	$sonos->SetMute(false);
	try {
		LOGGING("local_track.php: Requested local track plays now.", 7);
		$sonos->Play();
	} catch (Exception $e) {
		LOGGING("local_track.php: The audio file could not be played! Please check folder permission or if file exists", 3);	
		exit;
	}
}



/**
* Funktion : 	AudioFormat --> prüft ob eingegebenes Audio Format von Sonos unterstützt wird
*
* @param: $model --> Audio Format
* @return: $format --> true or false
**/

function AudioFormat($audio) {
    $format = [
		"MP3"  =>  "MP§ Audio Format",
        "WMA"  =>  "Windows Media Audio",
        "AAC"  =>  "Advanced Audio Coding",
        "OGG"  =>  "Ogg Vorbis Compressed Audio File",
        "FLAC" =>  "Free Lossless Audio Codec",
        "ALAC" =>  "Apple Lossless Audio Codec",
		"AIFF" =>  "Audio Interchange File Format",
		"WAV"  =>  "Waveform Audio File Format",
        ];
    return in_array($audio, array_keys($format));
}
?>

