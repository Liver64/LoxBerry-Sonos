<?php
function t2s($messageid)
// text-to-speech: Erstellt mit dem OS X Command "say" eine AIFF Datei und speichert diese in einem Verzeichnis 
// @Parameter = $messageid von sonos2.php
{
	#echo "bin in der MAC_OSX.php angekommen\n";
	global $words, $config, $messageid, $fileolang, $fileo;
	
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

		$mpath = $config['SYSTEM']['messageStorePath'];
		$lamePath = $config['TTS']['lamePath'];
		$words = urldecode($words);
		if($engine = '3001') {
			if (isset($_GET['voice'])) {
				$tmp_voice = $_GET['voice'];
					$valid_voice = array_multi_search($tmp_voice, $voices);
					if (!empty($valid_voice)) {
						$voice = $valid_voice[0]['voice'];
						shell_exec("say -v $voice $words -o $messageStorePath$fileolang.aiff; ".$lamePath."lame $mpath$fileolang.aiff 2>&1");
					} else {
						trigger_error('Der eingegebene OSX Name wird nicht unterstuetzt. Bitte korrigieren (siehe Wiki Tabelle)!', E_USER_ERROR);	
					}
			} else {
				shell_exec("say $words -o $messageStorePath$fileolang.aiff; ".$lamePath."lame $mpath$fileolang.aiff 2>&1");
			}
		}
	$messageid = $fileolang;
	return ($messageid);
				  	
}

?>

