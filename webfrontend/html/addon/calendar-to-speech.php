<?php


	
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
		LOGGING('Sonos: calendar-to-speech.php: The required Caldav-4-Lox Plugin is already not installed. Please install Plugin!',3);
		exit;
	}
	if(substr($home,0,4) !== "/opt") {
		LOGGING('Sonos: calendar-to-speech.php: The system you are using is not a loxberry. This application runs only on LoxBerry!',3);
		exit;
	}
	$url = $config['VARIOUS']['CALDav2'];
	$checklength = strlen($url).'<br>';
	$checkdebug = @substr($url,$checklength - 5,$checklength);
	if ($checkdebug == "debug") {
		LOGGING('Sonos: calendar-to-speech.php: Please remove &debug from your syntax in Sonos4lox Calendar URL!',3);
		exit;
	}
	$callurlcal = trim($config['VARIOUS']['CALDav2'].'&debug');
	print_r($calendar = json_decode(file_get_contents("$callurlcal"), TRUE));
	if (empty($calendar))  {
		LOGGING('Sonos: calendar-to-speech.php: No actual appointments according to your "fwday" parameter received or something went wrong!',4);
		#LOGGING('Sonos: calendar-to-speech.php: in addition please check/maintain events to be announced in your calendar URL!',4);
		exit(1);
	}
	$findMich1 = "&events=";
	$findMich2 = "&debug";
	$e = strpos($callurlcal, $findMich1) + 8;
	$j = strripos($callurlcal, $findMich2);
	$k = substr($callurlcal,$e,$j-$e);
	($u = explode('|', $k));
	
	if (@isset($calendar[$u[0]]['fwDay']) AND @$calendar[[0]]['fwDay'] <> -1) {
		$utc = new DateTimeZone('UTC'); 
		$curtimezone = date_timezone_get(date_create());
		$zeit = date_create_from_format('d.m.Y G:i:s', $calendar[$u[0]]['hStart'], $utc);
		$zeit = date_timezone_set($zeit, $curtimezone);
		$Startzeit = $calendar[$u[0]]['Start'];
		$zeit = new Datetime("@$Startzeit");
		#echo "<br>Termin gefunden: " . date_format($zeit, 'G:i').'<br>';
		@$speak .= "Der nächste Termin im Kalender ist ";
		if ($calendar[$u[0]]['fwDay'] == 0)
			$speak .= "heute um " . date_format($zeit, 'G:i') . " Uhr und lautet: " . $calendar[$u[0]]['Summary'] . ". ";
		elseif ($calendar[$u[0]]['fwDay'] == 1)
			$speak .= "morgen um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar[$u[0]]['Summary'] . ". ";
		elseif ($calendar[$u[0]]['fwDay'] == 2)
			$speak .= "übermorgen um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar[$u[0]]['Summary'] . ". ";
		else {
			$tage = array("Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag");
			$monate = array("Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember");
			$speak .= $calendar[$u[0]]['Summary'] . " am " . $tage[$calendar[$u[0]]['wkDay']] . " den ". date_format($zeit, 'd'). " " .$monate[date_format($zeit, 'm') - 1] ." um ". date_format($zeit, 'G:i');
		}
	echo ($speak);
	#echo '<br><br>';
	LOGGING('Sonos: calendar-to-speech.php: Calendar Announcement: '.$speak,7);
	LOGGING('Sonos: calendar-to-speech.php: Message been generated and pushed to T2S creation',5);
	return $speak;
	}
}

?>