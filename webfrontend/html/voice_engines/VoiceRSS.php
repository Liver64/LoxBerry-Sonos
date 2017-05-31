<?php
function t2s($messageid)
// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an VoiceRRS und 
// speichert das zurückkommende file lokal ab
// @Parameter = $messageid von sonos2.php

{
	global $words, $config, $messageid, $fileolang, $fileo;
	// List of all available VoiceRSS voices (Date: 12.11.2016)
	$valid_languages= array('ca-ESs','da-DK','de-DE','en-AU','en-CA','en-GB','en-IN','en-US','es-ES','es-MX',
							'fi-FI','fr-CA','fr-FR','it-IT','ja-JP','ko-KR','nb-NO','nl-NL','pl-PL','pt-BR',
							'pt-PT','ru-RU','sv-SE','zh-CN','zh-HK','zh-TW'
							);

		$ttsengine = $config['TTS']['t2s_engine'];
		$ttskey = $config['TTS']['API-key'];
		$ttsaudiocodec = $config['TTS']['audiocodec'];
		$words = utf8_encode($words);
		
		if($ttsengine = '1001') {
			if (isset($_GET['lang'])) {
				$language = $_GET['lang'];
				// lang = de-DE
				if (in_array($language, $valid_languages)) {
					$language = $_GET['lang'];
					$lang_end = substr($language, -2);
					$lang_start = substr($language, 0, 2);
					$language = $lang_start.'-'.strtolower($lang_end);
				} else {
					trigger_error('The entered VoiceRS language key is not supported. Please correct (see Wiki)!', E_USER_ERROR);
					exit;
				}
			} else {
				$language = $config['TTS']['messageLang'].'-'.$config['TTS']['messageLang'];
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
		$upper = strtoupper($language);
								  
		# Generieren des strings der an VoiceRSS geschickt wird
		$inlay = "key=$ttskey&src=$words&hl=$language&f=$ttsaudiocodec";	
									
		# Speicherort der MP3 Datei
		$mpath = $config['SYSTEM']['messageStorePath'];
		$file = $mpath . $fileolang . ".mp3";
					
		# Prüfung ob die MP3 Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# Übermitteln des strings an VoiceRSS.org
			ini_set('user_agent', 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36');
			$mp3 = file_get_contents('http://api.voicerss.org/?' . $inlay);
			file_put_contents($file, $mp3);
		}
	# Ersetze die messageid durch die von TTS gespeicherte Datei
	$messageid = $fileolang;
	return ($messageid);
				  	
}

?>

