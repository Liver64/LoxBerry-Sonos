<?php
function w2s() 
// weather-to-speech: Erstellt basierend auf Wunderground eine Wettervorhersage zur Generierung einer
// TTS Nachricht, übermittelt sie an VoiceRRS und speichert das zurückkommende file lokal ab
// @Parameter = $text von sonos2.php
 	{
		global $config, $debug, $town, $home, $psubfolder;
		
		// Einlesen der Daten vom Wunderground Plugin
		if (!file_exists("$home/data/plugins/wu4lox/current.dat")) {
			LOGGING('Data from Wunderground could be obtainend.',3);
			LOGGING('The file current.dat could not been opened. Please check Wunderground Plugin!',3);
			exit;
		} else {
			$current = file_get_contents("$home/data/plugins/wu4lox/current.dat");
			$current = explode('|',$current);
		}
		if (!file_exists("$home/data/plugins/wu4lox/dailyforecast.dat")) {
			LOGGING('Data from Wunderground could be obtainend.',3);
			LOGGING('The file dailyforecast.dat could not been opened. Please check Wunderground Plugin!',3);
			exit;
		} else {
			$dailyforecast = file_get_contents("$home/data/plugins/wu4lox/dailyforecast.dat");
			$dailyforecast = explode('|',$dailyforecast);
		}
		if (!file_exists("$home/data/plugins/wu4lox/hourlyforecast.dat")) {
			LOGGING('Data from Wunderground could be obtainend.',3);
			LOGGING('The file hourlyforecast.dat could not been opened. Please check Wunderground Plugin!',3);
			exit;
		} else {
			$hourlyforecast = file_get_contents("$home/data/plugins/wu4lox/hourlyforecast.dat");
			$hourlyforecast = explode('|',$hourlyforecast);
		}
		LOGGING('Data from Wunderground has been successful obtainend.',7);
		#print_r($current);
		#print_r($dailyforecast);
		#print_r($hourlyforecast);
		
		$Stunden = intval(strftime("%H"));
		$Minuten = intval(strftime("%M"));
		$regenschwelle = '10';
		$windschwelle = '10';
			
		#-- Aufbereiten der Wetterdaten ---------------------------------------------------------------------
		$temp_c = $current[11]; 
		$high0 = $dailyforecast[11]; // Höchsttemperatur heute
		$high1 = $dailyforecast[38]; // Höchsttemperatur morgen
		$low0 = $dailyforecast[12]; // Tiefsttemperatur heute
		$low1 = $dailyforecast[39]; // Tiefsttemperatur morgen
		$wind = $dailyforecast[16]; // max. Windgeschwindigkeit heute
		$wetter_hc = $current[29]; // Wetterkonditionen
		$windspeed = $hourlyforecast[17]; // maximale Windgeschwindigkeit nächste Stunde
		$windtxt = $windspeed;
		$wind_dir = $hourlyforecast[15]; // Windrichtung für die nächste Stunde
		$wetter = $current[29]; // Wetterkonditionen aktuell
		$conditions0 = $dailyforecast[27]; // allgemeine Wetterdaten heute
		$conditions1 = $dailyforecast[54]; // allgemeine Wetterdaten morgen
		$forecast0 = $dailyforecast[27]; // Wetterlage heute
		$forecast1 = $dailyforecast[54]; // Wetterlage morgen
		$regenwahrscheinlichkeit0 = $dailyforecast[13]; // Regenwahrscheinlichkeit heute
		$regenwahrscheinlichkeit1 = $dailyforecast[40]; // Regenwahrscheinlichkeit morgen
		# Prüfen ob Wetterkürzel vorhanden, wenn ja durch Wörter ersetzen
		if(ctype_upper($wind_dir)) 
		{
			# Ersetzen der Windrichtungskürzel für Windrichtung
			$search = array('W','S','N','O');
			$replace = array('west','sued','nord','ost');
			$wind_dir = str_replace($search,$replace,$wind_dir);
		}
		# Erstellen der Windtexte basierend auf der Windgeschwindigkeit
		## Quelle der Daten: http://www.brennstoffzellen-heiztechnik.de/windenergie-daten-infos/windtabelle-windrichtungen.html
		switch ($windtxt) 
		{
			case $windspeed >=1 && $windspeed <=5:
				$WindText= "ein leiser Zug";
				break;
			case $windspeed >5 && $windspeed <=11:
				$WindText= "eine leichte Briese";
				break;
			case $windspeed >11 && $windspeed <=19:
				$WindText= "eine schwache Briese";
				break;
			case $windspeed >19 && $windspeed <=28:
				$WindText= "ein mäßiger Wind";
				break;
			case $windspeed >28 && $windspeed <=38:
				$WindText= "ein frischer Wind";
				break;
			case $windspeed >38 && $windspeed <=49:
				$WindText= "ein starker Wind";
				break;
			case $windspeed >49 && $windspeed <=61:
				$WindText= "ein steifer Wind";
				break;
			case $windspeed >61 && $windspeed <=74:
				$WindText= "ein stürmischer Wind";
				break;
			case $windspeed >74 && $windspeed <=88:
				$WindText= "ein Sturm";
				break;
			case $windspeed >88 && $windspeed <=102:
				$WindText= "ein schwerer Sturm";
				break;
			case $windspeed >102:
				$WindText= "ein orkanartiger Sturm";
				break;
			default:
				$WindText= "";
				break;
			break;
		}
		# Windinformationen werden nur ausgeben wenn Windgeschwindigkeit größer dem Schwellwert ist
			switch ($windspeed) 
			{
				case $windspeed <$windschwelle:
					$WindAnsage="";
					break;
				case $windspeed >=$windschwelle:
					$WindAnsage=". Es weht ".$WindText. " aus Richtung ". utf8_decode($wind_dir). " mit Geschwindigkeiten bis zu ".$windspeed." km/h";
					break;
				default:
					$WindAnsage="";
					break;
			
			break;
			}
		
		# wird nur bei Regen ausgeben wenn Wert größer dem Schwellwert größer dem Schwellwert ist
		switch ($regenwahrscheinlichkeit0) {
			case $regenwahrscheinlichkeit0 =0 || $regenwahrscheinlichkeit0 <$regenschwelle:
				$RegenAnsage="";
				break;
			case $regenwahrscheinlichkeit0 >=$regenschwelle:
				$RegenAnsage="Die Regenwahrscheinlichkeit beträgt " .$regenwahrscheinlichkeit0." Prozent.";
				break;
			default:
				$RegenAnsage="";
				break;
		}
		
		# Aufbereitung der TTS Ansage
		# 
		# Aufpassen das bei Textänderungen die Werte nicht überschrieben werden
		###############################################################################################
		switch ($Stunden) {
			# Wettervorhersage für die Zeit zwischen 06:00 und 11:00h
			case $Stunden >=6 && $Stunden <8:
				$text="Guten morgen. Ich möchte euch eine kurze Wettervorhersage für den heutigen Taach geben. Vormittags wird das Wetter ". utf8_decode($wetter). ", die Höchsttemperatur beträgt voraussichtlich ". round($high0)." Grad, die aktuelle Temperatur beträgt ". round($temp_c)." Grad. ". $RegenAnsage.". ".$WindAnsage.". Ich wünsche euch einen wundervollen Tag.";
				break;
			# Wettervorhersage für die Zeit zwischen 11:00 und 17:00h
			case $Stunden >=8 && $Stunden <17:
				$text="Hallo zusammen. Heute Mittag, beziehungsweise heute Nachmittag, wird das Wetter ". utf8_decode($wetter_hc). ". Die momentane Außentemperatur beträgt ". round($temp_c)." Grad. " .$RegenAnsage.". ".$WindAnsage.". Ich wünsche euch noch einen schönen Nachmitag.";
				break;
			# Wettervorhersage für die Zeit zwischen 17:00 und 22:00h
			case $Stunden >=17 && $Stunden <22:
				$text="Guten Abend. Hier noch mal eine kurze Aktualisierung. In den Abendstunden wird es ". utf8_decode($wetter). ". Die aktuelle Außentemperatur ist ". round($temp_c)." Grad, die zu erwartende Tiefsttemperatur heute abend beträgt ". round($low0). " Grad. ". $RegenAnsage.". ".$WindAnsage.". Einen schönen Abend noch.";
				break;
			# Wettervorhersage für den morgigen Tag nach 22:00h
			case $Stunden >=22:
				$text="Guten Abend. Das Wetter morgen wird voraussichtlich ".utf8_decode($conditions1). ", die Höchsttemperatur beträgt ". round($high1) ." Grad, die Tiefsttemperatur beträgt " . round($low1). " Grad und die Regenwahrscheinlichkeit liegt bei ".$regenwahrscheinlichkeit1." Prozent. Gute Nacht und schlaft gut.";
				break;
			default:
				$text="";
				break;
		}
		$textcode = utf8_encode($text);
		LOGGING('Weather announcement: '.utf8_encode($text),6);
		LOGGING('Message been generated and pushed to T2S creation',7);
		return $textcode;
	}
?>
