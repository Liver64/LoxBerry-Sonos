<?php

/*
 * Sonos4Lox addon TTS helper
 * Version: ADDON_TTS_SUPPORT_ADDON_RELOCATION_V04_2026_06_12
 * Notes: moved to src/Support/AddOn with centralized S4L_Logger based logging and defensive input/fetch handling.
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


function c2s()

// clock-to-speech: Erstellt basierend auf der aktuellen Uhrzeit eine TTS Nachricht, �bermittelt sie an VoiceRRS und 
// speichert das zur�ckkommende file lokal ab
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
			# Gru� von 04:00 bis 10:00h
			case $Stunden >=4 && $Stunden <10:
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
			# Gru� nach 22:00h
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
		# erg�nzender Satz f�r die Zeit zwischen 6:00 und 8:00h (z.B. an Schultagen)
		case $Stunden >=6 && $Stunden <8:
			$Nachsatz=" ";
		break;
		# erg�nzender Satz f�r die Zeit nach 8:00h
		case $Stunden >=8:
			$Nachsatz="";
		break;
		default:
			$Nachsatz="";
		break;
	}
	
	$ttext = $greet." ".$TL['CLOCK-TO-SPEECH']['TEXT_BEFORE_HOUR_ANNOUNCEMENT']." ".$Stunden." ".$TL['CLOCK-TO-SPEECH']['TEXT_BEFORE_MINUTE_ANNOUNCEMENT']." ".$Minuten. " ".$TL['CLOCK-TO-SPEECH']['TEXT_AFTER_MINUTE_ANNOUNCEMENT']." ".$Nachsatz;
	$text = ($ttext);
	
	S4L_Logger::write('Time Announcement: '.$ttext,7, __FILE__);
	S4L_Logger::write('Message been generated and pushed to T2S creation',6, __FILE__);
	return ($text);
}	
?>
