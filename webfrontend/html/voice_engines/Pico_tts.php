<?php
function t2s($textstring, $filename)

// pico: Erstellt basierend auf Input eine TTS Nachricht mit Pico2Wave
// http://lame.sf.net


{
	global $config, $pathlanguagefile, $textstring, $filename;
		
		$file = "pico.json";
		$url = $pathlanguagefile."".$file;
		$valid_languages = File_Get_Array_From_JSON($url, $zip=false);
		
		if (isset($_GET['lang'])) {
			$language = $_GET['lang'];
			$isvalid = array_multi_search($language, $valid_languages, $sKey = "value");
			if (!empty($isvalid)) {
				$ttslanguage = $_GET['lang'];
				LOGGING('Sonos: voice_engines\pico.php: T2S language has been successful entered',5);				
			} else {
				LOGGING("Sonos: voice_engines\pico.php: The entered Pico language key is not supported. Please correct (see Wiki)!",3);
				exit;
			}
		} else {
			$ttslanguage = $config['TTS']['messageLang'];
		}	
		
		$file = $config['SYSTEM']['ttspath'] ."/". $filename . ".wav";
		
		LOGGING("Sonos: voice_engines\pico.php: Pico has been successful selected", 7);	
		#echo '/usr/bin/pico2wave -l=' . $ttslanguage . ' -w=' . $file . ' "'.$textstring.'"';
		# Übermitteln des Strings an Pico und lame zu MP3
		try {
			exec('/usr/bin/pico2wave -l ' . $ttslanguage . ' -w ' . $file . ' "'.$textstring.'"');
			exec('/usr/bin/lame '.$config['SYSTEM']['ttspath'] ."/". $filename . ".wav".' '.$config['SYSTEM']['ttspath'] ."/". $filename . ".mp3");
			unlink($config['SYSTEM']['ttspath'] ."/". $filename . ".wav");
		} catch(Exception $e) {
			LOGGING("Sonos: voice_engines\pico.php: The T2S could not be created! Please try again.",4);
		}
		LOGGING('Sonos: voice_engines\pico.php: The text has been passed to Pico engine for MP3 creation',5);
		return $filename;
}

?>