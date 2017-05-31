<?php
function t2s($messageid)
// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, �bermittelt sie an ResponsiveVoice und 
// speichert das zur�ckkommende file lokal ab
// @Parameter = $messageid von sonos2.php

{
	global $words, $config, $messageid, $fileolang, $fileo;

		$ttsengine = $config['TTS']['t2s_engine'];
		
		if($ttsengine = '6001') {
			$ttslanguage = $config['TTS']['messageLang'];
		}
						
		#####################################################################################################################
		# zu testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualit�t zu verbessern
		# search = array('�','�','�','�','�','�','�','�','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# words = str_replace($search,$replace,$words);
		#####################################################################################################################	

		# Sprache in Gro�buchsaben
		$upper = strtoupper($ttslanguage);
										  		
		# Speicherort der MP3 Datei
		$mpath = $config['SYSTEM']['messageStorePath'];
		$file = $mpath . $fileolang . ".mp3";
					
		# Pr�fung ob die MP3 Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# �bermitteln des strings an ResponsiveVoice
			$mp3 = file_get_contents('https://code.responsivevoice.org/getvoice.php?t='.$words.'&tl='.$upper.'');
			#http://responsivevoice.org/responsivevoice/getvoice.php?t=' + multipartText[i]+ '&tl=' + profile.collectionvoice.lang || profile.systemvoice.lang || 'en-US';
			file_put_contents($file, $mp3);
		}
	# Ersetze die messageid durch die von TTS gespeicherte Datei
	$messageid = $fileolang;
	return ($messageid);
				  	
}

?>

