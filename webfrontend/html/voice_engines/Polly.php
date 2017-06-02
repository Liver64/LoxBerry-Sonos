<?php
function t2s($messageid)

// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an Ivona.com und 
// speichert das zurückkommende file lokal ab
// @Parameter = $messageid von sonos2.php
{
	set_include_path(__DIR__ . '/polly_tts');
	
	global $messageid, $words, $config, $filename, $fileolang, $voice, $accesskey, $secretkey, $fileo;
		include 'polly_tts/polly.php';
		// List of all available Polly (Ivonca?) voices (Date: 22.05.2017)
		$voices[] = array('voice' => 'Mads','lang' => 'da-DK','gender' => 'Male');
		$voices[] = array('voice' => 'Naja','lang' => 'da-DK','gender' => 'Female');
		$voices[] = array('voice' => 'Lotte','lang' => 'nl-NL','gender' => 'Female');
		$voices[] = array('voice' => 'Ruben','lang' => 'nl-NL','gender' => 'Male');
		$voices[] = array('voice' => 'Nicole','lang' => 'en-AU','gender' => 'Female');
		$voices[] = array('voice' => 'Russell','lang' => 'en-AU','gender' => 'Male');
		$voices[] = array('voice' => 'Amy','lang' => 'en-GB','gender' => 'Female');
		$voices[] = array('voice' => 'Brian','lang' => 'en-GB','gender' => 'Male');
		$voices[] = array('voice' => 'Emma','lang' => 'en-GB','gender' => 'Female');
		$voices[] = array('voice' => 'Raveena','lang' => 'en-IN','gender' => 'Female');
		$voices[] = array('voice' => 'Ivy','lang' => 'en-US','gender' => 'Male');
		$voices[] = array('voice' => 'Joanna','lang' => 'en-US','gender' => 'Female');
		$voices[] = array('voice' => 'Joey','lang' => 'en-US','gender' => 'Male');
		$voices[] = array('voice' => 'Justin','lang' => 'en-US','gender' => 'Male');
		$voices[] = array('voice' => 'Kendra','lang' => 'en-US','gender' => 'Female');
		$voices[] = array('voice' => 'Kimberly','lang' => 'en-US','gender' => 'Female');
		$voices[] = array('voice' => 'Salli','lang' => 'en-US','gender' => 'Female');
		$voices[] = array('voice' => 'Geraint','lang' => 'en-GB-WLS','gender' => 'Male');
		$voices[] = array('voice' => 'Celine','lang' => 'fr-FR','gender' => 'Female');
		$voices[] = array('voice' => 'Mathieu','lang' => 'fr-FR','gender' => 'Male');
		$voices[] = array('voice' => 'Chantal','lang' => 'fr-CA','gender' => 'Female');
		$voices[] = array('voice' => 'Hans','lang' => 'de-DE','gender' => 'Male');
		$voices[] = array('voice' => 'Marlene','lang' => 'de-DE','gender' => 'Female');
		$voices[] = array('voice' => 'Vicki','lang' => 'de-DE','gender' => 'Female');
		$voices[] = array('voice' => 'Dora','lang' => 'is-IS','gender' => 'Female');
		$voices[] = array('voice' => 'Karl','lang' => 'is-IS','gender' => 'Male');
		$voices[] = array('voice' => 'Carla','lang' => 'it-IT','gender' => 'Female');
		$voices[] = array('voice' => 'Giorgio','lang' => 'it-IT','gender' => 'Male');
		$voices[] = array('voice' => 'Mizuki','lang' => 'ja-JP','gender' => 'Female');
		$voices[] = array('voice' => 'Liv','lang' => 'nb-NO','gender' => 'Female');
		$voices[] = array('voice' => 'Jacek','lang' => 'pl-PL','gender' => 'Male');
		$voices[] = array('voice' => 'Jan','lang' => 'pl-PL','gender' => 'Male');
		$voices[] = array('voice' => 'Ewa','lang' => 'pl-PL','gender' => 'Male');
		$voices[] = array('voice' => 'Maja','lang' => 'pl-PL','gender' => 'Female');
		$voices[] = array('voice' => 'Ricardo','lang' => 'pt-BR','gender' => 'Male');
		$voices[] = array('voice' => 'Vitoria','lang' => 'pt-BR','gender' => 'Female');
		$voices[] = array('voice' => 'Cristiano','lang' => 'pt-PT','gender' => 'Male');
		$voices[] = array('voice' => 'Ines','lang' => 'pt-PT','gender' => 'Female');
		$voices[] = array('voice' => 'Carmen','lang' => 'ro-RO','gender' => 'Female');
		$voices[] = array('voice' => 'Maxim','lang' => 'ru-RU','gender' => 'Male');
		$voices[] = array('voice' => 'Tatyana','lang' => 'ru-RU','gender' => 'Female');
		$voices[] = array('voice' => 'Conchita','lang' => 'es-ES','gender' => 'Female');
		$voices[] = array('voice' => 'Enrique','lang' => 'es-ES','gender' => 'Male');
		$voices[] = array('voice' => 'Miguel','lang' => 'es-US','gender' => 'Male');
		$voices[] = array('voice' => 'Penelope','lang' => 'es-US','gender' => 'Female');
		$voices[] = array('voice' => 'Astrid','lang' => 'sv-SE','gender' => 'Female');
		$voices[] = array('voice' => 'Filiz','lang' => 'tr-TR','gender' => 'Female');
		$voices[] = array('voice' => 'Gwyneth','lang' => 'cy-GB','gender' => 'Female');
						
		#-- Übernahme der Variablen aus config.php --
		$engine = $config['TTS']['t2s_engine'];
		$mpath = $config['SYSTEM']['messageStorePath'];
		if($engine = '4001') {
			if (isset($_GET['voice'])) {
				$tmp_voice = $_GET['voice'];
					$valid_voice = array_multi_search($tmp_voice, $voices);
					if (!empty($valid_voice)) {
						$language = $valid_voice[0]['lang'];
						$voice = $valid_voice[0]['voice'];
					} else {
						trigger_error('The entered Polly voice is not supported. Please correct (see Wiki)!', E_USER_ERROR);	
					}
			} else {
				$language = $config['TTS']['messageLang'].'-'.strtoupper($config['TTS']['messageLang']);
				$voice = $config['TTS']['voice'];
			}
		}
				
		#####################################################################################################################
		# Zum Testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# $search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# $replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# $words = str_replace($search,$replace,$words);
		#####################################################################################################################
		
		#-- Aufruf der POLLY Class zum generieren der t2s --
		$a = new POLLY_TTS();
		$a->save_mp3($words, $mpath."/".$fileolang.".mp3", $language, $voice);
		$messageid = $fileolang;
	
	return ($messageid);
}


?> 