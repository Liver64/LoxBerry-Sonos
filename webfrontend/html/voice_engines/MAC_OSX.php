<?php
function t2s($textstring, $filename)

// text-to-speech: Erstellt mit dem OS X Command "say" eine AIFF Datei und speichert diese in einem Verzeichnis 
// @Parameter = $messageid von sonos2.php

# 08/03/2018 added $lamePath from sonos.cfg
{
	#echo "bin in der MAC_OSX.php angekommen\n";
	global $config, $messageid;
	
	$voices[] = array('voice' => 'Alex','lang' => 'en_US');
	$voices[] = array('voice' => 'Ioana','lang' => 'ro_RO');
	$voices[] = array('voice' => 'Moira','lang' => 'en_IE');
	$voices[] = array('voice' => 'Sara','lang' => 'da_DK');
	$voices[] = array('voice' => 'Ellen','lang' => 'nl_BE');
	$voices[] = array('voice' => 'Thomas','lang' => 'fr_FR');
	$voices[] = array('voice' => 'Zosia','lang' => 'pl_PL');
	$voices[] = array('voice' => 'Steffi','lang' => 'de_DE');
	$voices[] = array('voice' => 'Amelie','lang' => 'fr_CA');
	$voices[] = array('voice' => 'Veena','lang' => 'en_IN');
	$voices[] = array('voice' => 'Luciana','lang' => 'pt_BR');
	$voices[] = array('voice' => 'Mariska','lang' => 'hu_HU');
	$voices[] = array('voice' => 'Sinji','lang' => 'zh_HK');
	$voices[] = array('voice' => 'Markus','lang' => 'de_DE');
	$voices[] = array('voice' => 'Zuzana','lang' => 'cs_CZ');
	$voices[] = array('voice' => 'Kyoko','lang' => 'ja_JP');
	$voices[] = array('voice' => 'Satu','lang' => 'fi_FI');
	$voices[] = array('voice' => 'Yuna','lang' => 'ko_KR');

		LOGGING("Sonos: voice_engines\mac_osx.php: MAC_OSX has been successful selected", 7);	
		
		$lamePath = "/usr/local/bin/";
		$textstring = urldecode($textstring);
		if($engine = '3001') {
			if (isset($_GET['voice'])) {
				$tmp_voice = $_GET['voice'];
					$valid_voice = array_multi_search($tmp_voice, $voices);
					if (!empty($valid_voice)) {
						$voice = $valid_voice[0]['voice'];
						shell_exec("say -v $voice $textstring -o $config['SYSTEM']['ttspath'] ."/". $filename.aiff; ".$lamePath."lame $config['SYSTEM']['ttspath'] ."/". $filename.aiff 2>&1");
						LOGGING('Sonos: voice_engines\mac_osx.php: T2S language/voice has been successful entered',5);
					} else {
						LOGGING("Sonos: voice_engines\mac_osx.php: The entered OSX Voice is not supported. Please correct (see Wiki)!!",3);
						exit;
					}
			} else {
				shell_exec("say $textstring -o $messageStorePath$filename.aiff; ".$lamePath."lame $mpath$filename.aiff 2>&1");
				LOGGING('Sonos: voice_engines\mac_osx.php: The text has been passed to OSX engine for MP3 creation',5);
			}
		}
	return $filename;
				  	
}

?>

