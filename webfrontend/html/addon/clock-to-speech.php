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
	switch ($Stunden) 
	{
		# Uhrzeitansage für die Zeit zwischen 06:00 und 11:00h
		case $Stunden >=6 && $Stunden <11:
			$Vorspann=$TL['CLOCK-TO-SPEECH']['GREETING_6AM_to_11AM'];
			break;
		# Uhrzeitansage für die Zeit zwischen 11:00 und 17:00h
		case $Stunden >=11 && $Stunden <17:
			$Vorspann=$TL['CLOCK-TO-SPEECH']['GREETING_11AM_to_5PM'];
			break;
		# Uhrzeitansage für die Zeit zwischen 17:00 und 22:00h
		case $Stunden >=17 && $Stunden <22:
			$Vorspann=$TL['CLOCK-TO-SPEECH']['GREETING_5PM_to_10PM'];
			break;
		# Uhrzeitansage für die Zeit nach 22:00h
		case $Stunden >=22 :
			$Vorspann=$TL['CLOCK-TO-SPEECH']['GREETING_AFTER_10PM'];
			break;
		default:
			$Vorspann=$TL['CLOCK-TO-SPEECH']['GREETING_DEFAULT'];
			break;
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
	
	$ttext = $Vorspann." ".$TL['CLOCK-TO-SPEECH']['TEXT_BEFORE_HOUR_ANNOUNCEMENT']." ".$Stunden." ".$TL['CLOCK-TO-SPEECH']['TEXT_BEFORE_MINUTE_ANNOUNCEMENT']." ".$Minuten. " ".$TL['CLOCK-TO-SPEECH']['TEXT_AFTER_MINUTE_ANNOUNCEMENT']." ".$Nachsatz;
	$text = ($ttext);
	
	LOGGING('Time Announcement: '.$ttext,7);
	LOGGING('Message been generated and pushed to T2S creation',6);
	return ($text);
}	
?>
