<?php
function t2s($messageid, $MessageStorepath, $textstring, $filename)

// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an Google.com und 
// speichert das zurückkommende file lokal ab
// @Parameter = $messageid von sonos2.php
{
	global $config, $messageid;
	// List of all available google voices (Date: 28.04.2017)
	$valid_languages =array('af-ZA','id-ID','ms-MY','ca-ES','cs-CZ','da-DK','de-DE','en-AU','en-CA','en-GB','en-IN',
							'en-IE','en-NZ','en-PH','en-ZA','en-US','es-AR','es-BO','es-CL','es-CO','es-CR','es-EC',
							'es-SV','es-ES','es-US','es-GT','es-HN','es-MX','es-NI','es-PA','es-PY','es-PE','es-PR',
							'es-DO','es-UY','es-VE','eu-ES','fil-PH','fr-CA','fr-FR','gl-ES','hr-HR','zu-ZA','is-IS',
							'it-IT','lt-LT','hu-HU','nl-NL','nb-NO','pl-PL','pt-BR','pt-PT','ro-RO','sk-SK','sl-SI',
							'fi-FI','sv-SE','vi-VN','tr-TR','el-GR','bg-BG','ru-RU','sr-RS','uk-UA','he-IL','ar-IL',
							'ar-JO','ar-AE','ar-BH','ar-DZ','ar-SA','ar-IQ','ar-KW','ar-MA','ar-TN','ar-OM','ar-PS',
							'ar-QA','ar-LB','ar-EG','fa-IR','hi-IN','th-TH','ko-KR','cmn-Hant-TW','yue-Hant-HK',
							'ja-JP','cmn-Hans-HK','cmn-Hans-CN'
						   );			
		#-- Übernahme der Variablen aus config.php --
		$engine = $config['TTS']['t2s_engine'];
		if($engine = '7001') {
			if (isset($_GET['lang'])) {
				$language = $_GET['lang'];
				if (in_array($language, $valid_languages)) {
					$language = $_GET['lang'];	
				} else {
					trigger_error('The entered Google language key is not supported. Please correct (see Wiki)!', E_USER_ERROR);
					exit;
				}
			} else {
				$language = $config['TTS']['messageLang'].'-'.strtoupper($config['TTS']['messageLang']);
			}	
		}
		
		#####################################################################################################################
		# Zum Testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# $search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# $replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# $textstring = str_replace($search,$replace,$textstring);
		#####################################################################################################################
		
		if (strlen($textstring) > 100) {
            trigger_error("The T2S contains more than 100 characters and therefor could not be generated. Please reduce characters in your message!", E_USER_NOTICE);
        }
								  
		# Speicherort der MP3 Datei
		$mpath = $config['SYSTEM']['messageStorePath'];
		$file = $MessageStorepath . $filename . ".mp3";
		$textstring = utf8_encode($textstring);
		
		#Generieren des strings der an Google geschickt wird.
		$inlay = "ie=UTF-8&total=1&idx=0&textlen=100&client=tw-ob&q=$textstring&tl=$language";	
					
		# Prüfung ob die MP3 Datei bereits vorhanden ist
		if (!file_exists($file)) 
		{
			# Übermitteln des strings an Google.com
			$mp3 = file_get_contents("http://translate.google.com/translate_tts?".$inlay);
			file_put_contents($file, $mp3);
		}
	$messageid = $filename;
	return ($messageid);
}


?> 