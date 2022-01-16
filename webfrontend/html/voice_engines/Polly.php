<?php
function t2s($textstring, $filename)

// polly: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an Ivona.com und 
// speichert das zurückkommende file lokal ab
{
	set_include_path(__DIR__ . '/polly_tts');
	
	global $config, $messageid, $voice, $accesskey, $secretkey, $MessageStorepath, $pathlanguagefile;
		require_once 'polly_tts/polly.php';
		
		$voicefile = "polly_voices.json";
		$urlvoice = $pathlanguagefile."".$voicefile;
		$valid_voices = File_Get_Array_From_JSON($urlvoice, $zip=false);
		if (isset($_GET['voice'])) {
			$tmp_voice = $_GET['voice'];
				$valid_voice = array_multi_search($tmp_voice, $valid_voices, $sKey = "name");
				if (!empty($valid_voice)) {
					$language = $valid_voice[0]['language'];
					$voice = $valid_voice[0]['name'];
					LOGGING('voice_engines\polly.php: T2S language/voice has been successful entered',5);
				} else {
					LOGGING("voice_engines\polly.php: The entered Polly voice is not supported. Please correct (see Wiki)!",3);
					exit;
				}
		} else {
			$language = $config['TTS']['messageLang'];
			$voice = $config['TTS']['voice'];
		}
				
		#####################################################################################################################
		# Zum Testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# $search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# $replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# $textstring = str_replace($search,$replace,$textstring);
		#####################################################################################################################
		
		# Speicherort der MP3 Datei
		$file = $config['SYSTEM']['ttspath'] ."/". $filename . ".mp3";
		
		LOGGING("voice_engines\polly.php: AWS Polly has been successful selected", 7);	
			
		#-- Aufruf der POLLY Class zum generieren der t2s --
		$a = new POLLY_TTS();
		$a->save_mp3($textstring, $config['SYSTEM']['ttspath']."/".$filename.".mp3", $language, $voice);
		LOGGING('voice_engines\polly.php: The text has been passed to Polly engine for MP3 creation',5);
		return $filename;
}


?> 