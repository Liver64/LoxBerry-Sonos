<?php

/**
* Function: muellkalender --> list all waste events of a CALDav based calendar
*
* @param: text
* @return: 
**/
function muellkalender() {
	global $config, $home, $myIP;
	
	#********************** NEW get text variables*********** ***********
	$TL = LOAD_T2S_TEXT();
	
	if (!file_exists("$home/webfrontend/html/plugins/caldav4lox/caldav.php")) {
		LOGGING('The required Caldav-4-Lox Plugin is already not installed. Please install Plugin!',3);
		exit;
	}
	if(substr($home,0,4) !== "/opt") {
		LOGGING('The system you are using is not a loxberry. This application runs only on LoxBerry!',3);
		exit;
	}
	// URL from Config
	$url = $config['VARIOUS']['CALDavMuell'];
	$checkdebug = strpos($url,"&debug");
	if ($checkdebug != false) {
		LOGGING('Please remove &debug from your syntax entry in Sonos4lox configuration!',3);
		exit;
	}
	$callurl = trim($config['VARIOUS']['CALDavMuell'].'&debug');
	$Stunden = intval(strftime("%H"));
	$muellarten = array();
	
	// call the waste calendar
	$dienst = json_decode(file_get_contents("$callurl"), TRUE);
	$utc = new DateTimeZone('UTC'); 
	$today = date('m/d/Y');
	print_r ($dienst);
	$checklength = strlen($url).'<br>';
	$events = strpos($url, "events");
	if ($events + 7 == $checklength){
		LOGGING('Please remove &events= from your syntax entry in Sonos4lox configuration or enter add the events you are looking for!',3);
		exit;
	}
	if ($events === false) {
		// prepare output without using events
		$enddate = date('m/d/Y',strtotime($dienst['']['hStart']));
		$days = (strtotime($enddate) - strtotime($today)) / (60*60*24);
		#if($dienst['']['fwDay'] < 0) {
		#	exit;
		if(($dienst['']['fwDay'] === 0) AND ($Stunden >=4 && $Stunden <12)){
			$welcomemorning = welcomemorning();
			$speak = $welcomemorning." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$dienst['']['Summary']." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif(($dienst['']['fwDay'] === 1) AND ($Stunden >=18)){
			$welcomeevening = welcomeevening();
			$speak = $welcomeevening." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$dienst['']['Summary']." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
		}
	} else {
		// prepare output using events
		$muell = substr($url, strrpos($url, "events") + 7);
		$muellarten = explode('|', $muell);
		$muellheute = array();
		$muellmorgen = array();
		foreach ($muellarten as $abfall => $val) {
			#echo $val.'<br>';
			$starttime = substr($dienst[$val]['hStart'],11,20);
			$endtime = substr($dienst[$val]['hEnd'],11,20);
			// ensure only full day appointments were picked
			if ($endtime = $starttime) {
				$enddate = date('m/d/Y',strtotime($dienst[$val]['hEnd']));
				$days = (strtotime($enddate) - strtotime($today)) / (60*60*24).'<br>';
				if($dienst[$val]['fwDay'] === 0)  {
					array_push($muellheute, $val);
				} elseif($dienst[$val]['fwDay'] === 1)  {
					array_push($muellmorgen, $val);
				}
			} else {
				break;
			}
		}
		#print_r($muellheute);
		#print_r($muellmorgen);
		// prepare speech
		if ((count($muellheute)) === 1 AND ($Stunden >=0 && $Stunden <11)) {
			$welcomemorning = welcomemorning();
			$speak = $welcomemorning." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$muellheute[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif ((count($muellheute)) === 2 AND ($Stunden >=0 && $Stunden <11)) {
			$welcomemorning = welcomemorning();
			$speak = $welcomemorning." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$muellheute[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE']." ".$muellheute[1]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif ((count($muellmorgen)) === 1 AND ($Stunden >=11 && $Stunden <24)) {
			$welcomeevening = welcomeevening();
			$speak = $welcomeevening." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$muellmorgen[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
		} elseif ((count($muellmorgen)) === 2 AND ($Stunden >=11 && $Stunden <24)) {
			$welcomeevening = welcomeevening();
			$speak = $welcomeevening." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$muellmorgen[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE']." ".$muellmorgen[1]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
		} elseif ((empty($muellheute)) or (empty($muellmorgen)))  {
			$text = $TL['WASTE-CALENDAR-TO-SPEECH']['NO_WASTE_FOUND_ONLY_LOGGING'];
			#echo $text;
			LOGGING('Waste calendar: '.$text,6);
			exit;
		}
	}
	if (empty($speak)) {
		exit;	
	}
	#echo urlencode($speak);
	#echo '<br><br>';
	LOGGING('Waste calendar Announcement: '.$speak,7);
	LOGGING('Message been generated and pushed to T2S creation',5);
	return $speak;
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
		LOGGING('The required Caldav-4-Lox Plugin is already not installed. Please install Plugin!',3);
		exit;
	}
	if(substr($home,0,4) !== "/opt") {
		LOGGING('The system you are using is not a loxberry. This application runs only on LoxBerry!',3);
		exit;
	}
	$url = $config['VARIOUS']['CALDav2'];
	$checklength = strlen($url).'<br>';
	$checkdebug = @substr($url,$checklength - 5,$checklength);
	if ($checkdebug == "debug") {
		LOGGING('Please remove &debug from your syntax entry in Sonos4lox configuration!',3);
		exit;
	}
	$callurl = trim($config['VARIOUS']['CALDav2'].'&debug');
	$calendar = json_decode(file_get_contents("$callurl"), TRUE);
	#print_r($calendar);
	if (isset($calendar['']['fwDay']) AND $calendar['']['fwDay'] <> -1) {
		$utc = new DateTimeZone('UTC'); 
		$curtimezone = date_timezone_get(date_create());
		$zeit = date_create_from_format('d.m.Y G:i:s', $calendar['']['hStart'], $utc);
		$zeit = date_timezone_set($zeit, $curtimezone);
		$Startzeit = $calendar['']['Start'];
		$zeit = new Datetime("@$Startzeit");
		#echo "<br>Termin gefunden: " . date_format($zeit, 'G:i').'<br>';
		@$speak .= "Der nächste Termin im Kalender ist ";
		if ($calendar['']['fwDay'] == 0)
			$speak .= "heute um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar['']['Summary'] . ". ";
		elseif ($calendar['']['fwDay'] == 1)
			$speak .= "morgen um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar['']['Summary'] . ". ";
		elseif ($calendar['']['fwDay'] == 2)
			$speak .= "übermorgen um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar['']['Summary'] . ". ";
		else {
			$tage = array("Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag");
			$monate = array("Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember");
			$speak .= $calendar['']['Summary'] . " am " . $tage[$calendar['']['wkDay']] . " den ". date_format($zeit, 'd'). " " .$monate[date_format($zeit, 'm') - 1] ." um ". date_format($zeit, 'G:i');
		}
	#echo ($speak);
	#echo '<br><br>';
	LOGGING('Calendar Announcement: '.$speak,7);
	LOGGING('Message been generated and pushed to T2S creation',5);
	return $speak;
	}
}

/**
* Function: welcomemorning() --> list of greetings for morning messages
*
* @param: text
* @return: 
**/
function welcomemorning() {
	global $TL;
	
	$welcomearraymorning = array(
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING1'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING2'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING3'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING4'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING5'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING6'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING7'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING8'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_MORNING9']
		);
	$welcomemorning = $welcomearraymorning[array_rand($welcomearraymorning)];
	return $welcomemorning;
	}
	
	
/**
* Function: welcomeevening() --> list of greetings for evening messages
*
* @param: text
* @return: 
**/
function welcomeevening() {
	global $TL;
	
	$welcomearrayevening = array(
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING1'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING2'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING3'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING4'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING5'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING6'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING7'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING8'],
		$TL['WASTE-CALENDAR-TO-SPEECH']['RANDOM_WELCOME_EVENING9'],
		);
	$welcomeevening = $welcomearrayevening[array_rand($welcomearrayevening)];
	return $welcomeevening;
	}
	



?>