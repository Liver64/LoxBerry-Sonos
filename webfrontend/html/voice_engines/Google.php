<?php
function t2s($messageid)

// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an Google.com und 
// speichert das zurückkommende file lokal ab
// @Parameter = $messageid von sonos2.php
{
	global $messageid, $words, $config, $filename, $fileolang, $voice, $accesskey, $secretkey, $fileo;
					
		#-- Übernahme der Variablen aus config.php --
		$engine = $config['TTS']['t2s_engine'];
		$mpath = $config['SYSTEM']['messageStorePath'];
		if($engine = '7001') {
			$language = $config['TTS']['messageLang'].'-'.$config['TTS']['messageLang'];;
		}
		
		#####################################################################################################################
		# Zum Testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# $search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# $replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# $words = str_replace($search,$replace,$words);
		#####################################################################################################################
		
		if (strlen($words) > 100) {
            trigger_error("Die T2S Message hat mehr als 100 Zeichen! Bitte den Text kürzen", E_USER_NOTICE);
        }
								  
		# Speicherort der MP3 Datei
		$mpath = $config['SYSTEM']['messageStorePath'];
		$file = $mpath . $fileolang . ".mp3";
		$words = utf8_encode($words);
		
		#Generieren des strings der an Google geschickt wird.
		$inlay = "ie=UTF-8&total=1&idx=0&textlen=100&client=tw-ob&q=$words&tl=$language";	
					
		# Prüfung ob die MP3 Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# Übermitteln des strings an Google.com
			$mp3 = file_get_contents("http://translate.google.com/translate_tts?".$inlay);
			file_put_contents($file, $mp3);
		}
	$messageid = $fileolang;
	return ($messageid);
}


?> 