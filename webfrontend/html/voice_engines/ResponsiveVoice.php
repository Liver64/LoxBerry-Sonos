<?php
function t2s($messageid)
// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an ResponsiveVoice und 
// speichert das zurückkommende file lokal ab
// @Parameter = $messageid von sonos2.php

{
	global $words, $config, $messageid, $fileolang, $fileo;

		$ttsengine = $config['TTS']['t2s_engine'];
		
		// List of all available Responsive Voice languages (Date: 22.05.2017)
		$voices[] = array('voice' => 'af-ZA','language' => 'Afrikaans (South Africa)');
		$voices[] = array('voice' => 'sq-SQ','language' => 'Albanian');
		$voices[] = array('voice' => 'ar-DZ','language' => 'Arabic (Algeria)');
		$voices[] = array('voice' => 'hy-HY','language' => 'Armenian');
		$voices[] = array('voice' => 'bs-BS','language' => 'Bosnian');
		$voices[] = array('voice' => 'ca-ES','language' => 'Catalan (Spain)');
		$voices[] = array('voice' => 'hr-HR','language' => 'Croatian (Croatia)');
		$voices[] = array('voice' => 'cs-CZ','language' => 'Czech (Czech Republic)');
		$voices[] = array('voice' => 'da-DK','language' => 'Danish');
		$voices[] = array('voice' => 'nl-NL','language' => 'Dutch');
		$voices[] = array('voice' => 'en-AU','language' => 'English (Australian)');
		$voices[] = array('voice' => 'en-GB','language' => 'English (British)');
		$voices[] = array('voice' => 'eo-EO','language' => 'Esperanto');
		$voices[] = array('voice' => 'fi-FI','language' => 'Finnish (Finland)');
		$voices[] = array('voice' => 'fr-FR','language' => 'French');
		$voices[] = array('voice' => 'de-DE','language' => 'German');
		$voices[] = array('voice' => 'el-GR','language' => 'Greek (Greece)');
		$voices[] = array('voice' => 'hi-IN','language' => 'Hindi (India)');
		$voices[] = array('voice' => 'hu-HU','language' => 'Hungarian (Hungary)');
		$voices[] = array('voice' => 'is-IS','language' => 'Icelandic');
		$voices[] = array('voice' => 'id-ID','language' => 'Indonesian (Indonesia)');
		$voices[] = array('voice' => 'it-IT','language' => 'Italian');
		$voices[] = array('voice' => 'ja-JP','language' => 'Japanese');
		$voices[] = array('voice' => 'ko-KR','language' => 'Korean (South Korea)');
		$voices[] = array('voice' => 'lv-LV','language' => 'Latvian');
		$voices[] = array('voice' => 'mk-MK','language' => 'Macedonian');
		$voices[] = array('voice' => 'nb-NO','language' => 'Norwegian');
		$voices[] = array('voice' => 'pl-PL','language' => 'Polish');
		$voices[] = array('voice' => 'pt-BR','language' => 'Portuguese (Brazilian)');
		$voices[] = array('voice' => 'pt-PT','language' => 'Portuguese (European)');
		$voices[] = array('voice' => 'ro-RO','language' => 'Romanian');
		$voices[] = array('voice' => 'ru-RU','language' => 'Russian');
		$voices[] = array('voice' => 'sr-RS','language' => 'Serbian (Serbia)');
		$voices[] = array('voice' => 'sk-SK','language' => 'Slovak (Slovakia)');
		$voices[] = array('voice' => 'es-ES','language' => 'Spanish (Castilian)');
		$voices[] = array('voice' => 'es-US','language' => 'Spanish (Latin American)');
		$voices[] = array('voice' => 'sw-SW','language' => 'Swahili');
		$voices[] = array('voice' => 'sv-SE','language' => 'Swedish');
		$voices[] = array('voice' => 'ta-TA','language' => 'Tamil');
		$voices[] = array('voice' => 'th-TH','language' => 'Thai (Thailand)');
		$voices[] = array('voice' => 'tr-TR','language' => 'Turkish');
		$voices[] = array('voice' => 'vi-VN','language' => 'Vietnamese (Vietnam)');
		$voices[] = array('voice' => 'cy-GB','language' => 'Welsh');
	
		
		if($ttsengine = '6001') {
			if (isset($_GET['lang'])) {
				$tmp_voice = $_GET['lang'];
				$valid_voice = array_multi_search($tmp_voice, $voices);
				if (!empty($valid_voice)) {
					$language = $valid_voice[0]['voice'];
				} else {
					trigger_error('The entered Responsivevoice language key is not supported. Please correct (see Wiki)!', E_USER_ERROR);	
				}
			} else {
				$language = $config['TTS']['messageLang'].'-'.strtoupper($config['TTS']['messageLang']);
			}
		}
						
		#####################################################################################################################
		# zu testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# words = str_replace($search,$replace,$words);
		#####################################################################################################################	

		# Sprache in Großbuchsaben
		#$upper = strtoupper($language);
										  		
		# Speicherort der MP3 Datei
		$mpath = $config['SYSTEM']['messageStorePath'];
		$file = $mpath . $fileolang . ".mp3";
					
		# Prüfung ob die MP3 Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# Übermitteln des strings an ResponsiveVoice
			$mp3 = file_get_contents('https://code.responsivevoice.org/getvoice.php?t='.$words.'&tl='.$language.'');
			#http://responsivevoice.org/responsivevoice/getvoice.php?t=' + multipartText[i]+ '&tl=' + profile.collectionvoice.lang || profile.systemvoice.lang || 'en-US';
			file_put_contents($file, $mp3);
		}
	# Ersetze die messageid durch die von TTS gespeicherte Datei
	$messageid = $fileolang;
	return ($messageid);
				  	
}

?>

