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




	
/**
* Function: calendar --> list all events of a dedicate calendar
*
* @param: text
* @return: 
**/	
function calendar() {
	
	global $config, $home, $myIP;
	
	$TL = LOAD_T2S_TEXT();
	if (!file_exists("$home/webfrontend/html/plugins/caldav4lox/caldav.php")) {
		S4L_Logger::write('The required Caldav-4-Lox Plugin is already not installed. Please install Plugin!',4, __FILE__);
		exit;
	}
	if(substr($home,0,4) !== "/opt") {
		S4L_Logger::write('The system you are using is not a loxberry. This application runs only on LoxBerry!',3, __FILE__);
		exit;
	}
	$url = trim((string)($config['VARIOUS']['CALDav2'] ?? ''));
	if ($url === '') {
		S4L_Logger::write('Calendar URL is empty in config.',4, __FILE__);
		exit;
	}
	if (strpos($url, '&debug') !== false) {
		S4L_Logger::write('Please remove &debug from your syntax in Sonos4lox Calendar URL!',4, __FILE__);
		exit;
	}
	$callurlcal = trim($url . '&debug');
	$calendarRaw = s4lox_addon_fetch_url($callurlcal, 10);
	$calendar = s4lox_addon_decode_json($calendarRaw);
	if (empty($calendar) || !is_array($calendar))  {
		S4L_Logger::write('No actual appointments according to your "fwday" parameter received or something went wrong!',4, __FILE__);
		#S4L_Logger::write('in addition please check/maintain events to be announced in your calendar URL!',4, __FILE__);
		exit(1);
	}
	$findMich1 = "&events=";
	$findMich2 = "&debug";
	$e = strpos($callurlcal, $findMich1) + 8;
	$j = strripos($callurlcal, $findMich2);
	$k = substr($callurlcal,$e,$j-$e);
	($u = explode('|', $k));
	
	$calendarKey = isset($u[0]) ? trim($u[0]) : '';
	if ($calendarKey !== '' && isset($calendar[$calendarKey]['fwDay']) && $calendar[$calendarKey]['fwDay'] != -1) {
		$utc = new DateTimeZone('UTC'); 
		$curtimezone = date_timezone_get(date_create());
		$zeit = date_create_from_format('d.m.Y G:i:s', $calendar[$calendarKey]['hStart'], $utc);
		$zeit = date_timezone_set($zeit, $curtimezone);
		$Startzeit = $calendar[$calendarKey]['Start'];
		$zeit = new Datetime("@$Startzeit");
		#echo "<br>Termin gefunden: " . date_format($zeit, 'G:i').'<br>';
		@$speak .= "Der nächste Termin im Kalender ist ";
		if ($calendar[$calendarKey]['fwDay'] == 0)
			$speak .= "heute um " . date_format($zeit, 'G:i') . " Uhr und lautet: " . $calendar[$calendarKey]['Summary'] . ". ";
		elseif ($calendar[$calendarKey]['fwDay'] == 1)
			$speak .= "morgen um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar[$calendarKey]['Summary'] . ". ";
		elseif ($calendar[$calendarKey]['fwDay'] == 2)
			$speak .= "übermorgen um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar[$calendarKey]['Summary'] . ". ";
		else {
			$tage = array("Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag");
			$monate = array("Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember");
			$speak .= $calendar[$calendarKey]['Summary'] . " am " . $tage[$calendar[$calendarKey]['wkDay']] . " den ". date_format($zeit, 'd'). " " .$monate[date_format($zeit, 'm') - 1] ." um ". date_format($zeit, 'G:i');
		}
	echo ($speak);
	#echo '<br><br>';
	S4L_Logger::write('Calendar Announcement: '.$speak,7, __FILE__);
	S4L_Logger::write('Message been generated and pushed to T2S creation',5, __FILE__);
	return $speak;
	}
}

?>