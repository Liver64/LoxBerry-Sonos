<?php
function t2s($messageid)
// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht mit Pico2Wave
// http://lame.sf.net
// @param = $messageid von sonos2.php

{
	global $words, $config, $messageid, $fileolang, $fileo;
		
		$valid_languages =array('de-DE','en-GB','fr-FR','it-IT');
		$ttsengine = $config['TTS']['t2s_engine'];
		$words = urldecode($words);
		
		if($ttsengine = '4001') {
			if (isset($_GET['lang'])) {
				$language = $_GET['lang'];
				if (in_array($language, $valid_languages)) {
					$ttslanguage = $_GET['lang'];	
				} else {
					trigger_error('The entered Pico language key is not supported. Please correct (see Wiki)!', E_USER_ERROR);	
					exit;
				}
			} else {
				$ttslanguage = $config['TTS']['messageLang'].'-'.strtoupper($config['TTS']['messageLang']);
			}	
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
				trigger_error("The T2S could not be created! Please try again.", E_USER_WARNING);
			}
		}
	# Ersetze die messageid durch die von TTS gespeicherte Datei
	$messageid = $fileolang;
	return ($messageid);
}

?>