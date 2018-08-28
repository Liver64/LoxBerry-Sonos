<?php
function w2s() 
// weather-to-speech: Erstellt basierend auf Weather4Lox eine Wettervorhersage zur Generierung einer
// TTS Nachricht
// @Parameter = $text von sonos2.php
 	{
		global $config, $debug, $town, $home, $psubfolder, $myIP;
		
		$TL = LOAD_T2S_TEXT();
		#print_r($TL);
		#exit;
				
		// Einlesen der Daten vom Weather4Lox Plugin
		if (!file_exists("$home/data/plugins/weather4lox/current.dat")) {
			LOGGING('Data from Weather4Lox could be obtainend.',3);
			LOGGING('The file current.dat could not been opened. Please check Weather4Lox Plugin!',3);
			exit;
		} else {
			$current = file_get_contents("$home/data/plugins/weather4lox/current.dat");
			$current = explode('|',$current);
		}
		if (!file_exists("$home/data/plugins/weather4lox/dailyforecast.dat")) {
			LOGGING('Data from Weather4Lox could be obtainend.',3);
			LOGGING('The file dailyforecast.dat could not been opened. Please check Weather4Lox Plugin!',3);
			exit;
		} else {
			$dailyforecast = file_get_contents("$home/data/plugins/weather4lox/dailyforecast.dat");
			$dailyforecast = explode('|',$dailyforecast);
		}
		if (!file_exists("$home/data/plugins/weather4lox/hourlyforecast.dat")) {
			LOGGING('Data from Weather4Lox could be obtainend.',3);
			LOGGING('The file hourlyforecast.dat could not been opened. Please check Weather4Lox Plugin!',3);
			exit;
		} else {
			$hourlyforecast = file_get_contents("$home/data/plugins/weather4lox/hourlyforecast.dat");
			$hourlyforecast = explode('|',$hourlyforecast);
		}
		LOGGING('Data from Weather4Lox has been successful obtainend.',7);
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
			$search = array("W","S","N","O");
			$replace = array($TL['WEATHER-TO-SPEECH']['DIRECTION_WEST'],$TL['WEATHER-TO-SPEECH']['DIRECTION_SOUTH'],$TL['WEATHER-TO-SPEECH']['DIRECTION_NORTH'],$TL['WEATHER-TO-SPEECH']['DIRECTION_EAST']);
			$wind_dir = str_replace($search,$replace,$wind_dir);
		}
		# Erstellen der Windtexte basierend auf der Windgeschwindigkeit
		## Quelle der Daten: http://www.brennstoffzellen-heiztechnik.de/windenergie-daten-infos/windtabelle-windrichtungen.html
		switch ($windtxt) 
		{
			case $windspeed >=1 && $windspeed <=5:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_1_TO_5'];
				break;
			case $windspeed >5 && $windspeed <=11:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_5_TO_11'];
				break;
			case $windspeed >11 && $windspeed <=19:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_11_TO_19'];
				break;
			case $windspeed >19 && $windspeed <=28:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_19_TO_28'];
				break;
			case $windspeed >28 && $windspeed <=38:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_28_TO_38'];
				break;
			case $windspeed >38 && $windspeed <=49:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_38_TO_49'];
				break;
			case $windspeed >49 && $windspeed <=61:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_49_TO_61'];
				break;
			case $windspeed >61 && $windspeed <=74:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_61_TO_74'];
				break;
			case $windspeed >74 && $windspeed <=88:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_74_TO_88'];
				break;
			case $windspeed >88 && $windspeed <=102:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_88_TO_102'];
				break;
			case $windspeed >102:
				$WindText= $TL['WEATHER-TO-SPEECH']['WINDSPEED_KM/H_GREATER_THEN_102'];
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
					$WindAnsage = "";
					break;
				case $windspeed >=$windschwelle:
					$WindAnsage = ". ".$TL['WEATHER-TO-SPEECH']['WIND_ANNOUNCEMENT_1']." ".$WindText." ".$TL['WEATHER-TO-SPEECH']['WIND_ANNOUNCEMENT_2']." ".$wind_dir." ".$TL['WEATHER-TO-SPEECH']['WIND_ANNOUNCEMENT_3']." ".$windspeed." ".$TL['WEATHER-TO-SPEECH']['WIND_ANNOUNCEMENT_4'];
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
				$RegenAnsage=$TL['WEATHER-TO-SPEECH']['RAIN_ANNOUNCEMENT_1']." ".$regenwahrscheinlichkeit0." ".$TL['WEATHER-TO-SPEECH']['RAIN_ANNOUNCEMENT_2']." ";
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
			# Wettervorhersage für die Zeit zwischen 06:00 und 10:00h
			case $Stunden >=6 && $Stunden <10:
				$text=($TL['WEATHER-TO-SPEECH']['WEATHERTEXT_1_HOUR_FROM_6AM_TO_10AM']." ". ($wetter). ", ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_2_HOUR_FROM_6AM_TO_10AM']." ".round($high0)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_3_HOUR_FROM_6AM_TO_10AM']." ". round($temp_c)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_4_HOUR_FROM_6AM_TO_10AM']." ". $RegenAnsage.". ".$WindAnsage.". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_5_HOUR_FROM_6AM_TO_10AM']);
				break;
			# Wettervorhersage für die Zeit zwischen 10:00 und 17:00h
			case $Stunden >=10 && $Stunden <17:
				$text=($TL['WEATHER-TO-SPEECH']['WEATHERTEXT_1_HOUR_FROM_10AM_TO_5PM']." ". ($wetter_hc)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_2_HOUR_FROM_10AM_TO_5PM']." ". round($temp_c)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_3_HOUR_FROM_10AM_TO_5PM']." ".$RegenAnsage.". ".$WindAnsage.". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_4_HOUR_FROM_10AM_TO_5PM']);
				break;
			# Wettervorhersage für die Zeit zwischen 17:00 und 22:00h
			case $Stunden >=17 && $Stunden <22:
				$text=$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_1_HOUR_FROM_5PM_TO_10PM']." ". ($wetter). ". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_2_HOUR_FROM_5PM_TO_10PM']." ". round($temp_c)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_3_HOUR_FROM_5PM_TO_10PM']." ". round($low0). " ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_4_HOUR_FROM_5PM_TO_10PM'].". ". $RegenAnsage.". ".$WindAnsage.". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_5_HOUR_FROM_5PM_TO_10PM'].". ";
				break;
			# Wettervorhersage für den morgigen Tag nach 22:00h
			case $Stunden >=22:
				$text=$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_1_HOUR_AFTER_10PM']." ".($conditions1). ", ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_2_HOUR_AFTER_10PM']." ". round($high1) ." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_3_HOUR_AFTER_10PM']." ". round($low1)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_4_HOUR_AFTER_10PM']." ".$regenwahrscheinlichkeit1." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_5_HOUR_AFTER_10PM'].".";
				break;
			default:
				$text="";
				break;
		}
		$textcode = ($text);
		LOGGING('Weather announcement: '.($text),5);
		LOGGING('Message been generated and pushed to T2S creation',7);
		return $textcode;
	}
?>
