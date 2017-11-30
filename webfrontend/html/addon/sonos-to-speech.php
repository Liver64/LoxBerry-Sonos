<?php

 /** 
* aktueller Sonos Titel und Interpret per TTS ansagen
* @param $text
*/ 

function s2s($text)
{ 		
	global $debug, $sonos, $sonoszone, $master;
	
	$thissong = "Der laufende Song lautet "; 
 	$by = " von "; 
	
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
			$text = $thissong . $titel . $by . $artist ; 
		} elseif(empty($temp["duration"])) {
			# Generiert Ansage des laufenden Senders
			$sender = $temp_radio['title'];
			$text = 'Es läuft '.$sender;
		}
		$text = utf8_encode($text);
		if ($debug == 1) 
		{
			echo ($text); 
			echo '<br />';
		}
		return ($text);
	} 
}
?>