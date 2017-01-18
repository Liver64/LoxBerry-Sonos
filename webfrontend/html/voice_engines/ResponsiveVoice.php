<?php
function t2s($messageid)
// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an ResponsiveVoice und 
// speichert das zurückkommende file lokal ab
// @Parameter = $messageid von sonos2.php

{
	global $words, $config, $messageid, $fileolang, $fileo;

		$ttsengine = $config['TTS']['t2s_engine'];
		
		if($ttsengine = '6001') {
			$ttslanguage = $config['TTS']['messageLang'];
		}
						
		#####################################################################################################################
		# zu testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# words = str_replace($search,$replace,$words);
		#####################################################################################################################	

		# Sprache in Großbuchsaben
		$upper = strtoupper($ttslanguage);
										  		
		# Speicherort der MP3 Datei
		$mpath = $config['SYSTEM']['messageStorePath'];
		$file = $mpath . $fileolang . ".mp3";
					
		# Prüfung ob die MP3 Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# Übermitteln des strings an ResponsiveVoice
			$mp3 = file_get_contents('https://code.responsivevoice.org/getvoice.php?t='.$words.'&tl='.$upper.'');
			file_put_contents($file, $mp3);
		}
	# Ersetze die messageid durch die von TTS gespeicherte Datei
	$messageid = $fileolang;
	return ($messageid);
				  	
}

?>

