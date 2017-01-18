<?php
function w2s($text) 
// weather-to-speech: Erstellt basierend auf Wunderground eine Wettervorhersage zur Generierung einer
// TTS Nachricht, übermittelt sie an VoiceRRS und speichert das zurückkommende file lokal ab
// @Parameter = $text von sonos2.php
 	{
		global $config, $debug;
		
		$Stunden = intval(strftime("%H"));
		$Minuten = intval(strftime("%M"));
		$key = $config['WUNDERGROUND']['wgkey'];
		$pws = $config['WUNDERGROUND']['pws'];
		$city = $config['WUNDERGROUND']['wgcity']; 
		$regenschwelle = intval($config['WUNDERGROUND']['wgregenschwelle']);
		$windschwelle = intval($config['WUNDERGROUND']['wgwindschwelle']);
		$sprache = "DL"; // DL = deutsch
		$land = "Germany"; // Land
		
		#wenn PWS gesetzt, dann diese verwenden
		if ($pws != '') {
			$station = $land."/".$city;
		} else {
			$station = "pws:".$pws;
		}
	
		# aktuelle Wetterdaten aufbereiten
		$json_string = file_get_contents("http://api.wunderground.com/api/".$key."/geolookup/conditions/lang:".$sprache."/q/".$station.".json");	
		$current_parsed_json = json_decode($json_string);
		// Vorhersage: Tag 0 = heute, 1 = morgen, 2 = übermorgen *
		$json_fc_string = file_get_contents("http://api.wunderground.com/api/".$key."/forecast/lang:".$sprache."/q/".$station.".json");	
		$parsed_fc_json = json_decode($json_fc_string);
		// Vorhersage: stündlich ist 0 = jetzt, 1 = + 1 Stunde, 2 = + 2 Stunden usw.
		$json_hc_string = file_get_contents("http://api.wunderground.com/api/".$key."/hourly10day/lang:".$sprache."/q/".$station.".json");	
		# Kopiervorlage für wunderground.com
		# http://api.wunderground.com/api/9ad952ba578239ff/hourly10day/lang:DL/q/Germany/Frankfurt.json
		$parsed_hc_json = json_decode($json_hc_string);
		## hinzugefügt zur Fehleranalyse (speichern einer aktuellen Wetterdatei im JSON Format)
		if($debug == 1) {
			$path = $config['SYSTEM']['messageStorePath']; // Pfad aus config.php
			$file = $path . "weather_raw_data.txt"; // Dateiname
			file_put_contents($file, $json_fc_string); // je nach Typ ändern: json_fc_string = Vorschau; $json_hc_string = 10 Tage Vorschau; $json_string = aktuelles Wetter
			## Ende Fehleranalyse
		}
		
		## Beginn abholen und aufbereiten der Wetterdaten
		##------------------------------------------------------------------------------------------------------------
		$prognose = $parsed_fc_json->{'forecast'}->{'simpleforecast'}->{'forecastday'};
		$temp_c = $current_parsed_json->{'current_observation'}->{'temp_c'}; 
		$high0 = $prognose[0]->high->celsius; // Höchsttemperatur heute
		$high1 = $prognose[1]->high->celsius; // Höchsttemperatur morgen
		$min_temp = $current_parsed_json->{'current_observation'}->{'dewpoint_c'}; 
		$low0 = $prognose[0]->low->celsius; // Tiefsttemperatur heute
		$low1 = $prognose[1]->low->celsius; // Tiefsttemperatur morgen
		$wind = $parsed_fc_json->{'forecast'}->{'simpleforecast'}->{'forecastday'};
		$wetter_hc = $wind[0]->conditions; // Wetterkonditionen
		$windspeed = $wind[0]->maxwind->kph; // maximale Windgeschwindigkeit nächste Stunde
		$windtxt = $windspeed;
		$wind_dir = $wind[0]->maxwind->dir; // Windrichtung für die nächste Stunde
		$wetter = $current_parsed_json->{'current_observation'}->{'weather'};
		$conditions0 = $prognose[0]->conditions; // allgemeine Wetterdaten heute
		$conditions1 = $prognose[1]->conditions; // allgemeine Wetterdaten morgen
		$forecast0 = $parsed_fc_json -> {'forecast'}-> {'txt_forecast'}-> forecastday[0] -> {'fcttext_metric'}; // Wetterlage heute
		$forecast1 = $parsed_fc_json -> {'forecast'}-> {'txt_forecast'}-> forecastday[1] -> {'fcttext_metric'}; // Wetterlage morgen
		$regenwahrscheinlichkeit0 = intval($prognose[0]->pop); // Regenwahrscheinlichkeit heute
		$regenwahrscheinlichkeit1 = intval($prognose[1]->pop);// Regenwahrscheinlichkeit morgen
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
		# Windinformationen werden nur ausgeben wenn Windgeschwindigkeit größer dem Wert aus der config.php ist
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
		
		# wird nur bei Regen ausgeben wenn Wert größer dem Schwellwert aus der config.php ist
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
				$text="Guten morgen. Ich möchte euch eine kurze Wettervorhersage für den heutigen Taach geben. Vormittags wird das Wetter ". utf8_decode($wetter). ", die Höchsttemperatur beträgt voraussichtlich ". round($high0)." Grad, die aktuelle Temperatur beträgt ". round($temp_c)." Grad. ". $RegenAnsage.". ".$WindAnsage.". Ich wünsche euch einen wundervollen Taach.";
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
				$text="Guten Abend. Das morgigie Wetter wird voraussichtlich ".utf8_decode($conditions1). ", die Höchsttemperatur beträgt ". round($high1) ." Grad, die Tiefsttemperatur beträgt " . round($low1). " Grad und die Regenwahrscheinlichkeit liegt bei ".$regenwahrscheinlichkeit1." Prozent. Gute Nacht und schlaft gut.";
				break;
			default:
				$text="";
				break;
		}
		$text = utf8_encode($text);
		if ($debug == 1) {
			echo 'Text zur Uebergabe an T2S:';
			echo '<br>';
			print_r ($text); 
			echo '<br>';
		}
		return $text;
	}
?>
