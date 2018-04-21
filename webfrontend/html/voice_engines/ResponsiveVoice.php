<?php
function t2s($messageid, $MessageStorepath, $textstring, $filename)

// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an ResponsiveVoice und 
// speichert das zurückkommende file lokal ab

{
	global $config, $messageid, $pathlanguagefile;
	
	$file = "respvoice.json";
	$url = $pathlanguagefile."".$file;
	$textstring = urlencode($textstring);
	$valid_languages = File_Get_Array_From_JSON($url, $zip=false);
	
		if (isset($_GET['lang'])) {
			$language = $_GET['lang'];
			$isvalid = array_multi_search($language, $valid_languages, $sKey = "value");
			if (!empty($isvalid)) {
				$language = $_GET['lang'];
				LOGGING('T2S language has been successful entered',5);
			} else {
				LOGGING("The entered ResponsiveVoice language key is not supported. Please correct (see Wiki)!",3);
				exit;
			}
		} else {
			$language = $config['TTS']['messageLang'];
		}
						
		#####################################################################################################################
		# zu testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# words = str_replace($search,$replace,$textstring);
		#####################################################################################################################	

		# Speicherort der MP3 Datei
		$file = $MessageStorepath . $filename . ".mp3";
				
		# Prüfung ob die MP3 Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# Übermitteln des strings an ResponsiveVoice
			$mp3 = file_get_contents('https://code.responsivevoice.org/getvoice.php?t='.$textstring.'&tl='.$language.'');
			#http://responsivevoice.org/responsivevoice/getvoice.php?t=' + multipartText[i]+ '&tl=' + profile.collectionvoice.lang || profile.systemvoice.lang || 'en-US';
			file_put_contents($file, $mp3);
			LOGGING('The text has been passed to ResponsiveVoice for MP3 creation',5);
		} else {
			LOGGING('Requested T2s has been grabbed from cache',6);
		}
	# Ersetze die messageid durch die von TTS gespeicherte Datei
	$messageid = $filename;
	return $filename;
				  	
}

?>

