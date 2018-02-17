<?php
function w2s() 
// weather-to-speech: Erstellt basierend auf Wunderground eine Wettervorhersage zur Generierung einer
// TTS Nachricht, übermittelt sie an VoiceRRS und speichert das zurückkommende file lokal ab
// @Parameter = $text von sonos2.php
 	{
		global $config, $debug, $town;
		
		$home = posix_getpwuid(posix_getuid());
		$home = $home['dir'];

		$psubfolder = __FILE__;
		$psubfolder = preg_replace('/(.*)\/(.*)\/(.*)$/',"$2", $psubfolder);

		// Einlesen der Daten vom Wunderground Plugin
		if (!file_exists("$home/data/plugins/wu4lox/current.dat")) {
			trigger_error("Die Datei current.dat konnte nicht eingelesen werden. Bitte das Wunderground Plugin prüfen!!", E_USER_NOTICE);
		} else {
			$current = file_get_contents("$home/data/plugins/wu4lox/current.dat");
			$current = explode('|',$current);
		}
		if (!file_exists("$home/data/plugins/wu4lox/dailyforecast.dat")) {
			trigger_error("Die Datei dailyforecast.dat konnte nicht eingelesen werden. Bitte das Wunderground Plugin prüfen!!", E_USER_NOTICE);
		} else {
			$dailyforecast = file_get_contents("$home/data/plugins/wu4lox/dailyforecast.dat");
			$dailyforecast = explode('|',$dailyforecast);
		}
		if (!file_exists("$home/data/plugins/wu4lox/hourlyforecast.dat")) {
			trigger_error("Die Datei hourlyforecast.dat konnte nicht eingelesen werden. Bitte das Wunderground Plugin prüfen!!", E_USER_NOTICE);
		} else {
			$hourlyforecast = file_get_contents("$home/data/plugins/wu4lox/hourlyforecast.dat");
			$hourlyforecast = explode('|',$hourlyforecast);
		}
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
				$WindText= "en roligere tog";
				break;
			case $windspeed >5 && $windspeed <=11:
				$WindText= "en lett bris";
				break;
			case $windspeed >11 && $windspeed <=19:
				$WindText= "en svak bris";
				break;
			case $windspeed >19 && $windspeed <=28:
				$WindText= "en moderat vind";
				break;
			case $windspeed >28 && $windspeed <=38:
				$WindText= "en frisk vind";
				break;
			case $windspeed >38 && $windspeed <=49:
				$WindText= "en sterk vind";
				break;
			case $windspeed >49 && $windspeed <=61:
				$WindText= "en sterk vind";
				break;
			case $windspeed >61 && $windspeed <=74:
				$WindText= "en stormvind";
				break;
			case $windspeed >74 && $windspeed <=88:
				$WindText= "en storm";
				break;
			case $windspeed >88 && $windspeed <=102:
				$WindText= "en kraftig storm";
				break;
			case $windspeed >102:
				$WindText= "en storm-force storm";
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
					$WindAnsage=". det blåser ".$WindText. " fra retning ". ($wind_dir). " med hastigheter på opptil ".$windspeed." km / t";
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
				$RegenAnsage="Sannsynligheten for regn er " .$regenwahrscheinlichkeit0." Prosent.";
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
				$wtext="God morgen kjære familie. La meg gi deg en kort værmelding for dagen. I morgen, været". $wetter. ", Den maksimale temperatur er forventet ". round($high0)." Grad, er den gjeldende temperatur ". round($temp_c)." Grad. ". $RegenAnsage.". ".$WindAnsage.". Jeg ønsker deg en flott dag.";
				break;
			# Wettervorhersage für die Zeit zwischen 11:00 und 17:00h
			case $Stunden >=8 && $Stunden <12:
				$wtext="Hei. Ved middagstid i dag, eller i ettermiddag, været ". $wetter_hc. ". er den aktuelle utetemperaturen ". round($temp_c)." Grad. " .$RegenAnsage.". ".$WindAnsage.". Jeg ønsker deg en fin ettermiddag.";
				break;
			# Wettervorhersage für die Zeit zwischen 17:00 und 22:00h
			case $Stunden >=12 && $Stunden <22:
				$wtext="God kveld. Her igjen en kort oppdatering. På kveldene vil det ". $wetter. ". Den gjeldende utetemperatur ". round($temp_c)." er nivå, den forventede minimum i kveld ". round($low0). " Grad. ". $RegenAnsage.". ".$WindAnsage.". Ha en fin kveld.";
				break;
			# Wettervorhersage für den morgigen Tag nach 22:00h
			case $Stunden >=22:
				$wtext="Hei kjære kone. Den Weiterstadt været forventes i morgen ".utf8_decode($conditions1). ", er den maksimale temperaturen ". round($high1) ." Grad, er den laveste temperaturen " . round($low1). " Grad, og sjansen for regn er ".$regenwahrscheinlichkeit1." Prosent. God kveld dere og sove godt.";
				break;
			default:
				$wtext="";
				break;
		}
		$wtext = urlencode($wtext);
		if ($debug == 1) {
			echo 'Text zur Uebergabe an T2S:<br><br>';
			print_r ($wtext); 
		}
		return $wtext;
	}
?>
