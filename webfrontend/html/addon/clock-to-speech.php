<?php
function c2s($ttext)

// clock-to-speech: Erstellt basierend auf der aktuellen Uhrzeit eine TTS Nachricht, übermittelt sie an VoiceRRS und 
// speichert das zurückkommende file lokal ab
// @Parameter = $ttext von sonos2.php
{
	global $debug;
	
	$Stunden = intval(strftime("%H"));
	$Minuten = intval(strftime("%M"));
	switch ($Stunden) 
	{
		# Uhrzeitansage für die Zeit zwischen 06:00 und 11:00h
		case $Stunden >=6 && $Stunden <11:
			$Vorspann="Guten Morgen.";
			break;
		# Uhrzeitansage für die Zeit zwischen 11:00 und 17:00h
		case $Stunden >=11 && $Stunden <17:
			$Vorspann="Guten Tag. Ich bin es noch einmal";
			break;
		# Uhrzeitansage für die Zeit zwischen 17:00 und 22:00h
		case $Stunden >=17 && $Stunden <22:
			$Vorspann="Guten Abend.";
			break;
		# Uhrzeitansage für die Zeit nach 22:00h
		case $Stunden >=22 :
			$Vorspann="Gute Nacht.";
			break;
		default:
			$Vorspann="Gute Nacht.";
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
	
	$ttext = $Vorspann." Es ist jetzt ".$Stunden." Uhr und ".$Minuten. " Minuten." .$Nachsatz;
	$ttext = utf8_encode($ttext);
	if ($debug == 1) 
		{
			echo ($ttext); 
			echo '<br />';
		}
	return ($ttext);
}	
?>
