<?php
function t2s($messageid)
// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht mit Pico2Wave
// http://lame.sf.net
// @param = $messageid von sonos2.php

{
	global $words, $config, $messageid, $fileolang, $fileo;
		
		$ttsengine = $config['TTS']['t2s_engine'];
		$words = urldecode($words);
		
		if($ttsengine = '4001') {
			if($config['TTS']['messageLang'] = "de") { $ttslanguage = "de-DE"; }
			elseif($config['TTS']['messageLang'] = "gb") { $ttslanguage = "en-GB"; }
			elseif($config['TTS']['messageLang'] = "us") { $ttslanguage = "en-US"; }
			elseif($config['TTS']['messageLang'] = "fr") { $ttslanguage = "fr-FR"; }
			elseif($config['TTS']['messageLang'] = "it") { $ttslanguage = "it-IT"; }
		} else {
			trigger_error("Die angegebene Sprache für Pico2wave ist unbekannt! Die Sprache wird automatisch auf DE gesetzt!", E_USER_WARNING);
			$ttslanguage = "de-DE";
		}
		
		$mpath = $config['SYSTEM']['messageStorePath'];
		$file = $mpath . $fileolang . ".wav";
					
		# Prüfung ob die Voice Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# Übermitteln des Strings an Pico und lame zu MP3
			try {
				exec('/usr/bin/pico2wave -l=' . $ttslanguage . ' -w=' . $file . ' "'.$words.'"');
				exec('/usr/bin/lame '.$mpath . $fileolang . ".wav".' '.$mpath . $fileolang . ".mp3");
				unlink($mpath . $fileolang . ".wav");
			} catch(Exception $e) {
				trigger_error("Die T2S konnte nicht erstellt werden! Bitte erneut versuchen.", E_USER_WARNING);
			}
		}
	# Ersetze die messageid durch die von TTS gespeicherte Datei
	$messageid = $fileolang;
	return ($messageid);
}

?>