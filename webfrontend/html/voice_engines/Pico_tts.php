<?php
function t2s($messageid, $MessageStorepath, $textstring, $filename)

// pico: Erstellt basierend auf Input eine TTS Nachricht mit Pico2Wave
// http://lame.sf.net


{
	global $config, $messageid, $pathlanguagefile, $MessageStorepath, $textstring, $filename;
		
		$textstring = urldecode($textstring);
		$file = "pico.json";
		$url = $pathlanguagefile."".$file;
		$valid_languages = File_Get_Array_From_JSON($url, $zip=false);
		
		if (isset($_GET['lang'])) {
			$language = $_GET['lang'];
			$isvalid = array_multi_search($language, $valid_languages, $sKey = "value");
			if (!empty($isvalid)) {
				$ttslanguage = $_GET['lang'];		
			} else {
				trigger_error('The entered Pico language key is not supported. Please correct (see Wiki)!', E_USER_ERROR);	
				exit;
			}
		} else {
			$ttslanguage = $config['TTS']['messageLang'];
		}	
		
		$file = $MessageStorepath . $filename . ".wav";
					
		# Prüfung ob die Voice Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# Übermitteln des Strings an Pico und lame zu MP3
			try {
				exec('/usr/bin/pico2wave -l=' . $ttslanguage . ' -w=' . $file . ' "'.$textstring.'"');
				#exit;
				exec('/usr/bin/lame '.$MessageStorepath . $filename . ".wav".' '.$MessageStorepath . $filename . ".mp3");
				unlink($MessageStorepath . $filename . ".wav");
			} catch(Exception $e) {
				trigger_error("The T2S could not be created! Please try again.", E_USER_WARNING);
			}
		}
	# Ersetze die messageid durch die von TTS gespeicherte Datei
	$messageid = $filename;
	return ($messageid);
}

?>