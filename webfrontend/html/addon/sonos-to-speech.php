<?php

 /** 
* aktueller Sonos Titel und Interpret per TTS ansagen
* @param $text
*/ 

function s2s()
{ 		
	global $debug, $sonos, $sonoszone, $master, $t2s_langfile, $templatepath;
	
	#********************** NEW get text variables*********** ***********
	$TL = LOAD_T2S_TEXT();
		
	$this_song = $TL['SONOS-TO-SPEECH']['CURRENT_SONG'] ; 
 	$by = $TL['SONOS-TO-SPEECH']['CURRENT_SONG_ARTIST_BY']; 
	$this_radio = $TL['SONOS-TO-SPEECH']['CURRENT_RADIO_STATION'];
	#********************************************************************
	
	# prüft ob gerade etwas gespielt wird, falls nicht dann keine Ansage
	$gettransportinfo = $sonos->GetTransportInfo();
	if($gettransportinfo <> 1) {
		exit;
	} else {
	# Prüft ob Playliste oder Radio läuft
		$master = $_GET['zone'];
		$sonos = new PHPSonos($sonoszone[$master][0]);
		$temp = $sonos->GetPositionInfo();
		$temp_radio = $sonos->GetMediaInfo();
		$sonos->Stop();
		if(!empty($temp["duration"])) {
			# Generiert Titelinfo wenn MP3 läuft
			$artist = substr($temp["artist"], 0, 30);
			$titel = substr($temp["title"], 0, 70);
			$text = $this_song . $titel . $by . $artist ; 
		} elseif(empty($temp["duration"])) {
			# Generiert Ansage des laufenden Senders
			$sender = $temp_radio['title'];
			$text = $this_radio." ".$sender;
		}
		$text = utf8_encode($text);
		LOGGING('Song Announcement: '.($text),7);
		LOGGING('Message been generated and pushed to T2S creation',6);
		return ($text);
	} 
}
?>