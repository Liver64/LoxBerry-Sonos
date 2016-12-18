<?php
function t2s($messageid)

// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an Ivona.com und 
// speichert das zurückkommende file lokal ab
// @Parameter = $messageid von sonos2.php
{
	set_include_path(__DIR__ . '/ivona_tts');
	
	global $messageid, $words, $config, $filename, $fileolang, $voice, $accesskey, $secretkey, $fileo;
		#include 'ivona_tts/ivona_tts.php'; -- ALTE VERSION --
		include 'ivona_tts/ivona.php';
				
		#-- Übernahme der Variablen aus config.php --
		$engine = $config['TTS']['t2s_engine'];
		$mpath = $config['SYSTEM']['messageStorePath'];
		if($engine = '2001') {
			$language = $config['TTS']['messageLang'].'-'.strtoupper($config['TTS']['messageLang']);
		}
		
		#####################################################################################################################
		# Zum Testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# $search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# $replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# $words = str_replace($search,$replace,$words);
		#####################################################################################################################
		
		#-- Aufruf der IVONA Class zum generieren der t2s -- ALTE VERSION --
		#$a = new IVONA_TTS(); 
		#$a->save_mp3($words, $mpath."".$fileolang.".mp3", $language, $voice);
		
		#-- Aufruf der IVONA Class zum generieren der t2s --
		$a = new IvonaClient();
		$a->getSave($words, array('Language' => $language, 'VoiceName'=> $voice, 'VoiceRate'=>'default', 'CacheDir'=>$mpath, 'FileName'=>$fileolang));
		$messageid = $fileolang;
	
	return ($messageid);
}


?> 