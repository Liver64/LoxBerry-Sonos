<?php
function t2s($textstring, $filename)

{
	global $config, $pathlanguagefile;
	
	$file = "google.json";
	$url = $pathlanguagefile."".$file;
	$valid_languages = File_Get_Array_From_JSON($url, $zip=false);
	
		if (isset($_GET['lang'])) {
			$language = $_GET['lang'];
			$isvalid = array_multi_search($language, $valid_languages, $sKey = "value");
			if (!empty($isvalid)) {
				$language = $_GET['lang'];	
				LOGGING('T2S language has been successful entered',5);
			} else {
				LOGGING('The entered Google language key is not supported. Please correct (see Wiki)!',3);
				exit;
			}
		} else {
			$language = $config['TTS']['messageLang'];
		}	
		
		if (strlen($textstring) > 100) {
            LOGGING("The Google T2S contains more than 100 characters and therefor could not be generated. Please reduce characters to max. 100!",3);
			exit;
        }
								  
		# Speicherort der MP3 Datei
		$file = $config['SYSTEM']['ttspath'] ."/". $filename . ".mp3";
		$textstring = urlencode($textstring);
		
		#Generieren des strings der an Google geschickt wird.
		$inlay = "ie=UTF-8&total=1&idx=0&textlen=100&client=tw-ob&q=$textstring&tl=$language";	
		
		LOGGING("Google has been successful selected", 7);	
		# �bermitteln des strings an Google.com
		$mp3 = file_get_contents("http://translate.google.com/translate_tts?".$inlay);
		
		
		
		
		
		file_put_contents($file, $mp3);
		LOGGING('The text has been passed to google engine for MP3 creation',5);
		return ($filename);
}


?> 