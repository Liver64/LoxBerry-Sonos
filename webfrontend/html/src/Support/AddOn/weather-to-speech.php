<?php

/*
 * Sonos4Lox addon TTS helper
 * Version: ADDON_TTS_WEATHER4LOX_DUALFORMAT_V05_2026_09_07
 * Notes: supports Weather4Lox v4 JSON output with automatic fallback to legacy DAT files.
 */

require_once dirname(__DIR__) . '/Logger.php';



if (!function_exists('s4lox_addon_fetch_url')) {
    function s4lox_addon_fetch_url($url, $timeout = 8)
    {
        if (!is_string($url) || trim($url) === '') {
            return false;
        }
        $context = stream_context_create(array(
            'http' => array('timeout' => $timeout, 'ignore_errors' => true),
            'https' => array('timeout' => $timeout, 'ignore_errors' => true),
        ));
        return @file_get_contents($url, false, $context);
    }
}

if (!function_exists('s4lox_addon_decode_json')) {
    function s4lox_addon_decode_json($json)
    {
        if (!is_string($json) || trim($json) === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $data;
    }
}


if (!function_exists('s4lox_weather4lox_read_json_file')) {
    function s4lox_weather4lox_read_json_file($path)
    {
        if (!is_string($path) || $path === '' || !is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        return s4lox_addon_decode_json($raw);
    }
}

if (!function_exists('s4lox_weather4lox_get')) {
    function s4lox_weather4lox_get($data, array $path, $default = null)
    {
        $value = $data;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value;
    }
}


function w2s()
// weather-to-speech: Builds a Weather4Lox based weather forecast for TTS generation.
// Parameter: $text from Sonos TTS processing
 	{
		global $config, $debug, $town, $home, $psubfolder, $myIP;
		
		$TL = load_t2s_text();
		#print_r($TL);
		#exit;
				
		// ---------------------------------------------------------------------
		// Weather4Lox data input
		// New Weather4Lox (v4): JSON in /opt/loxberry/log/plugins/weather4lox
		// Legacy Weather4Lox:    pipe-delimited DAT in /opt/loxberry/data/plugins/weather4lox
		// JSON is preferred. If JSON is missing/incomplete, legacy DAT is used.
		// ---------------------------------------------------------------------
		$jsonDir   = "$home/log/plugins/weather4lox";
		$legacyDir = "$home/data/plugins/weather4lox";

		$currentJsonFile = $jsonDir . '/current.json';
		$dailyJsonFile   = $jsonDir . '/dailyforecast.json';
		$hourlyJsonFile  = $jsonDir . '/hourlyforecast.json';

		$weatherDataLoaded = false;
		$jsonFilesPresent = is_file($currentJsonFile) && is_file($dailyJsonFile) && is_file($hourlyJsonFile);

		if ($jsonFilesPresent) {
			$currentEnvelope = s4lox_weather4lox_read_json_file($currentJsonFile);
			$dailyEnvelope   = s4lox_weather4lox_read_json_file($dailyJsonFile);
			$hourlyEnvelope  = s4lox_weather4lox_read_json_file($hourlyJsonFile);

			$currentJson = is_array($currentEnvelope) ? ($currentEnvelope['current'] ?? null) : null;
			$dailyJson   = is_array($dailyEnvelope) ? ($dailyEnvelope['dailyforecast'] ?? null) : null;
			$hourlyJson  = is_array($hourlyEnvelope) ? ($hourlyEnvelope['hourlyforecast'] ?? null) : null;

			$todayJson    = (is_array($dailyJson) && isset($dailyJson[0]) && is_array($dailyJson[0])) ? $dailyJson[0] : null;
			$tomorrowJson = (is_array($dailyJson) && isset($dailyJson[1]) && is_array($dailyJson[1])) ? $dailyJson[1] : null;
			$nextHourJson = (is_array($hourlyJson) && isset($hourlyJson[0]) && is_array($hourlyJson[0])) ? $hourlyJson[0] : null;

			if (is_array($currentJson) && is_array($todayJson) && is_array($tomorrowJson) && is_array($nextHourJson)) {
				// Map new JSON schema to the existing internal variables. The TTS
				// wording below intentionally remains unchanged.
				$temp_c  = s4lox_weather4lox_get($currentJson, array('temperature', 'air'));
				$high0   = s4lox_weather4lox_get($todayJson, array('temperature', 'max', 'air'));
				$high1   = s4lox_weather4lox_get($tomorrowJson, array('temperature', 'max', 'air'));
				$low0    = s4lox_weather4lox_get($todayJson, array('temperature', 'min', 'air'));
				$low1    = s4lox_weather4lox_get($tomorrowJson, array('temperature', 'min', 'air'));
				$wind    = s4lox_weather4lox_get($todayJson, array('wind', 'max', 'speed'), 0);
				$wetter_hc = s4lox_weather4lox_get($currentJson, array('weatherCode', 'description'));
				$windspeed = s4lox_weather4lox_get($nextHourJson, array('wind', 'speed'), 0);
				$windtxt = $windspeed;
				$wind_dir = s4lox_weather4lox_get($nextHourJson, array('wind', 'dirLabel'), '');
				$wetter = s4lox_weather4lox_get($currentJson, array('weatherCode', 'description'));
				$conditions0 = s4lox_weather4lox_get($todayJson, array('weatherCode', 'description'));
				$conditions1 = s4lox_weather4lox_get($tomorrowJson, array('weatherCode', 'description'));
				$forecast0 = $conditions0;
				$forecast1 = $conditions1;
				$regenwahrscheinlichkeit0 = s4lox_weather4lox_get($todayJson, array('precipitation', 'probability'));
				$regenwahrscheinlichkeit1 = s4lox_weather4lox_get($tomorrowJson, array('precipitation', 'probability'));

				$requiredJsonValues = array(
					$temp_c, $high0, $high1, $low0, $low1,
					$wetter_hc, $wetter, $conditions0, $conditions1,
					$regenwahrscheinlichkeit0, $regenwahrscheinlichkeit1
				);

				$jsonComplete = true;
				foreach ($requiredJsonValues as $requiredValue) {
					if ($requiredValue === null || $requiredValue === '') {
						$jsonComplete = false;
						break;
					}
				}

				if ($jsonComplete) {
					$weatherDataLoaded = true;
					S4L_Logger::write('Weather4Lox JSON data format detected. Data has been successfully retrieved.', 7, __FILE__);
				} else {
					S4L_Logger::write('Weather4Lox JSON files are present but required weather values are incomplete. Trying legacy DAT format.', 4, __FILE__);
				}
			} else {
				S4L_Logger::write('Weather4Lox JSON files could not be decoded or do not contain the expected current/dailyforecast/hourlyforecast data. Trying legacy DAT format.', 4, __FILE__);
			}
		}

		if (!$weatherDataLoaded) {
			$currentFile = $legacyDir . '/current.dat';
			$dailyFile   = $legacyDir . '/dailyforecast.dat';
			$hourlyFile  = $legacyDir . '/hourlyforecast.dat';

			if (!file_exists($currentFile)) {
				S4L_Logger::write('Data from Weather4Lox could not be obtained. Neither usable JSON data nor legacy current.dat is available.', 4, __FILE__);
				S4L_Logger::write("Checked Weather4Lox JSON path '$jsonDir' and legacy path '$legacyDir'.", 4, __FILE__);
				exit;
			}
			$currentRaw = @file_get_contents($currentFile);
			if ($currentRaw === false) {
				S4L_Logger::write('The file current.dat could not be read. Please check Weather4Lox Plugin!', 4, __FILE__);
				exit;
			}
			$current = explode('|', $currentRaw);

			if (!file_exists($dailyFile)) {
				S4L_Logger::write('The file dailyforecast.dat could not be opened. Please check Weather4Lox Plugin!', 4, __FILE__);
				exit;
			}
			$dailyRaw = @file_get_contents($dailyFile);
			if ($dailyRaw === false) {
				S4L_Logger::write('The file dailyforecast.dat could not be read. Please check Weather4Lox Plugin!', 4, __FILE__);
				exit;
			}
			$dailyforecast = explode('|', $dailyRaw);

			if (!file_exists($hourlyFile)) {
				S4L_Logger::write('The file hourlyforecast.dat could not be opened. Please check Weather4Lox Plugin!', 4, __FILE__);
				exit;
			}
			$hourlyRaw = @file_get_contents($hourlyFile);
			if ($hourlyRaw === false) {
				S4L_Logger::write('The file hourlyforecast.dat could not be read. Please check Weather4Lox Plugin!', 4, __FILE__);
				exit;
			}
			$hourlyforecast = explode('|', $hourlyRaw);

			if (count($current) < 30 || count($dailyforecast) < 67 || count($hourlyforecast) < 18) {
				S4L_Logger::write('Weather4Lox legacy DAT data is incomplete. Please check Weather4Lox Plugin output files.', 4, __FILE__);
				exit;
			}

			S4L_Logger::write('Weather4Lox legacy DAT data format detected. Data has been successfully retrieved.', 7, __FILE__);

			// Legacy mapping - unchanged from the previous implementation.
			$temp_c = $current[11];
			$high0 = $dailyforecast[11];
			$high1 = $dailyforecast[50];
			$low0 = $dailyforecast[12];
			$low1 = $dailyforecast[51];
			$wind = $dailyforecast[16];
			$wetter_hc = $current[29];
			$windspeed = $hourlyforecast[17];
			$windtxt = $windspeed;
			$wind_dir = $hourlyforecast[15];
			$wetter = $current[29];
			$conditions0 = $dailyforecast[27];
			$conditions1 = $dailyforecast[66];
			$forecast0 = $dailyforecast[27];
			$forecast1 = $dailyforecast[66];
			$regenwahrscheinlichkeit0 = $dailyforecast[13];
			$regenwahrscheinlichkeit1 = $dailyforecast[52];
		}

		$Stunden = intval(strftime("%H"));
		$Minuten = intval(strftime("%M"));
		$regenschwelle = '10';
		$windschwelle = '10';

		# Pr�fen ob Wetterk�rzel vorhanden, wenn ja durch W�rter ersetzen
		if(ctype_upper($wind_dir)) 
		{
			# Ersetzen der Windrichtungsk�rzel f�r Windrichtung
			$search = array("W","S","N","O");
			$replace = array($TL['WEATHER-TO-SPEECH']['DIRECTION_WEST'],$TL['WEATHER-TO-SPEECH']['DIRECTION_SOUTH'],$TL['WEATHER-TO-SPEECH']['DIRECTION_NORTH'],$TL['WEATHER-TO-SPEECH']['DIRECTION_EAST']);
			$wind_dir = str_replace($search,$replace,$wind_dir);
		}
		
		if (isset($_GET['greet']))  {
			$TL = LOAD_T2S_TEXT();
			switch ($Stunden) {
				# Gru� von 01:00 bis 10:00h
				case $Stunden >=1 && $Stunden <10:
					$greet = $TL['GREETINGS']['MORNING_'.mt_rand (1, 5)];
				break;
				# Gru� von 10:00 bis 17:00h
				case $Stunden >=10 && $Stunden <17:
					$greet = $TL['GREETINGS']['DAY_'.mt_rand (1, 5)];
				break;
				# Gru� von 17:00 bis 22:00h
				case $Stunden >=17 && $Stunden <22:
					$greet = $TL['GREETINGS']['EVENING_'.mt_rand (1, 5)];
				break;
				# Gru� nach 22:00h bis Mitternacht
				case $Stunden >=22 or $Stunden <24:
					$greet = $TL['GREETINGS']['NIGHT_'.mt_rand (1, 5)];
				break;
				default:
					$greet = "";
				break;
			}
		} else {
			$greet = "";
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
		# Windinformationen werden nur ausgeben wenn Windgeschwindigkeit gr��er dem Schwellwert ist
			switch ($windspeed) 
			{
				case $windspeed <$windschwelle:
					$WindAnsage = "";
					break;
				case $windspeed >=$windschwelle:
					$WindAnsage = ". ".$TL['WEATHER-TO-SPEECH']['WIND_ANNOUNCEMENT_1']." ".$WindText." ".$TL['WEATHER-TO-SPEECH']['WIND_ANNOUNCEMENT_2']." ".$wind_dir." ".$TL['WEATHER-TO-SPEECH']['WIND_ANNOUNCEMENT_3']." ".round($windspeed)." ".$TL['WEATHER-TO-SPEECH']['WIND_ANNOUNCEMENT_4'];
					break;
				default:
					$WindAnsage="";
					break;
			
			break;
			}
		
		# Rain announcement only if the probability is above the configured threshold.
		if ((float)$regenwahrscheinlichkeit0 >= (float)$regenschwelle) {
			$RegenAnsage=". ".$TL['WEATHER-TO-SPEECH']['RAIN_ANNOUNCEMENT_1']." ".$regenwahrscheinlichkeit0." ".$TL['WEATHER-TO-SPEECH']['RAIN_ANNOUNCEMENT_2']." ";
		} else {
			$RegenAnsage="";
		}
		
		# Aufbereitung der TTS Ansage
		# 
		# Aufpassen das bei Text�nderungen die Werte nicht �berschrieben werden
		###############################################################################################
		switch ($Stunden) {
			# Wettervorhersage f�r die Zeit zwischen 01:00 und 10:00h
			case $Stunden >=1 && $Stunden <10:
				$text=($greet." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_1_HOUR_FROM_6AM_TO_10AM']." ". ($wetter). ", ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_2_HOUR_FROM_6AM_TO_10AM']." ".formatTemperatureForTTS($high0)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_3_HOUR_FROM_6AM_TO_10AM']." ". formatTemperatureForTTS($temp_c)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_4_HOUR_FROM_6AM_TO_10AM']." ". $RegenAnsage." ".$WindAnsage.". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_5_HOUR_FROM_6AM_TO_10AM']);
				break;
			# Wettervorhersage f�r die Zeit zwischen 10:00 und 17:00h
			case $Stunden >=10 && $Stunden <17:
				$text=($greet." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_1_HOUR_FROM_10AM_TO_5PM']." ". ($wetter_hc).". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_2_HOUR_FROM_10AM_TO_5PM']." ". formatTemperatureForTTS($temp_c)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_3_HOUR_FROM_10AM_TO_5PM']."".$RegenAnsage." ".$WindAnsage.". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_4_HOUR_FROM_10AM_TO_5PM']);
				break;
			# Wettervorhersage f�r die Zeit zwischen 17:00 und 22:00h
			case $Stunden >=17 && $Stunden <22:
				$text=$greet." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_1_HOUR_FROM_5PM_TO_10PM']." ". ($wetter). ". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_2_HOUR_FROM_5PM_TO_10PM']." ". formatTemperatureForTTS($temp_c)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_3_HOUR_FROM_5PM_TO_10PM']." ". formatTemperatureForTTS($low0). " ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_4_HOUR_FROM_5PM_TO_10PM']."". $RegenAnsage." ".$WindAnsage.". ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_5_HOUR_FROM_5PM_TO_10PM'].". ";
				break;
			# Wettervorhersage f�r den morgigen Tag nach 22:00h bis Mitternacht
			case $Stunden >=22 or $Stunden <=24:
				$text=$greet." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_1_HOUR_AFTER_10PM']." ".($conditions1). ", ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_2_HOUR_AFTER_10PM']." ". formatTemperatureForTTS($high1) ." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_3_HOUR_AFTER_10PM']." ". formatTemperatureForTTS($low1)." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_4_HOUR_AFTER_10PM']." ".$regenwahrscheinlichkeit1." ".$TL['WEATHER-TO-SPEECH']['WEATHERTEXT_5_HOUR_AFTER_10PM'].".";
				break;
			default:
				$text="";
				S4L_Logger::write('Request could not be processed, please try again!',5, __FILE__);
				break;
		}
		$textcode = ($text);
		#echo $textcode;
		S4L_Logger::write('Weather announcement: '.($text),5, __FILE__);
		S4L_Logger::write('Message been generated and pushed to T2S creation',7, __FILE__);
		return $textcode;
	}
	
// Temperatur korrekt f�r TTS vorbereiten
function formatTemperatureForTTS($temp) {
    $tempRounded = round($temp);
    if ($tempRounded < 0) {
        return "minus " . abs($tempRounded);
    } else {
        return $tempRounded;
    }
}

?>
