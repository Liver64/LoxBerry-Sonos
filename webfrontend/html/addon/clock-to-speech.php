<?php
function c2s()

// clock-to-speech: Erstellt basierend auf der aktuellen Uhrzeit eine TTS Nachricht, übermittelt sie an VoiceRRS und 
// speichert das zurückkommende file lokal ab
// @Parameter = $ttext von sonos2.php
{
	global $debug;
	
	#********************** NEW get text variables*********** ***********
	$TL = LOAD_T2S_TEXT();
			
	$Stunden = intval(strftime("%H"));
	$Minuten = intval(strftime("%M"));
	
	if (isset($_GET['greet']))  {
		#$Stunden = intval(strftime("%H"));
		$TL = LOAD_T2S_TEXT();
		switch ($Stunden) {
			# Gruß von 04:00 bis 10:00h
			case $Stunden >=4 && $Stunden <10:
				$greet = $TL['GREETINGS']['MORNING_'.mt_rand (1, 5)];
			break;
			# Gruß von 10:00 bis 17:00h
			case $Stunden >=10 && $Stunden <17:
				$greet = $TL['GREETINGS']['DAY_'.mt_rand (1, 5)];
			break;
			# Gruß von 17:00 bis 22:00h
			case $Stunden >=17 && $Stunden <22:
				$greet = $TL['GREETINGS']['EVENING_'.mt_rand (1, 5)];
			break;
			# Gruß nach 22:00h
			case $Stunden >=22:
				$greet = $TL['GREETINGS']['NIGHT_'.mt_rand (1, 5)];
			break;
			default:
				$greet = "";
			break;
		}
	} else {
		$greet = "";
	}
	
	switch ($Stunden) 
	{
		# ergänzender Satz für die Zeit zwischen 6:00 und 8:00h (z.B. an Schultagen)
		case $Stunden >=6 && $Stunden <8:
			$Nachsatz=" ";
		break;
		# ergänzender Satz für die Zeit nach 8:00h
		case $Stunden >=8:
			$Nachsatz="";
		break;
		default:
			$Nachsatz="";
		break;
	}
	
	$ttext = $greet." ".$TL['CLOCK-TO-SPEECH']['TEXT_BEFORE_HOUR_ANNOUNCEMENT']." ".$Stunden." ".$TL['CLOCK-TO-SPEECH']['TEXT_BEFORE_MINUTE_ANNOUNCEMENT']." ".$Minuten. " ".$TL['CLOCK-TO-SPEECH']['TEXT_AFTER_MINUTE_ANNOUNCEMENT']." ".$Nachsatz;
	$text = ($ttext);
	
	LOGGING('clock-to-speech.php: Time Announcement: '.$ttext,7);
	LOGGING('clock-to-speech.php: Message been generated and pushed to T2S creation',6);
	return ($text);
}	
?>
