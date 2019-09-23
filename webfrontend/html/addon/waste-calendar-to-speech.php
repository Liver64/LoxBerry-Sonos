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
	$callurl = (trim($config['VARIOUS']['CALDavMuell'].'&debug'));

	# Leerzeichen durch ein Plus ersetzen
	$search = array(' ');
	$replace = array('+');
	$callurl = str_replace($search,$replace,$callurl);
	
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
	
	if (isset($_GET['greet']))  {
		#$Stunden = intval(strftime("%H"));
		$TL = LOAD_T2S_TEXT();
		switch ($Stunden) {
			# Gruß von 04:00 bis 10:00h
			case $Stunden >=4 && $Stunden <10:
				$greet = $TL['GREETINGS']['MORNING_'.mt_rand (1, 5)];
			break;
			# Gruß von 10:00 bis 17:00h
			case $Stunden >=10 && $Stunden <17:
				$greet = $TL['GREETINGS']['DAY_'.mt_rand (1, 5)];
			break;
			# Gruß von 17:00 bis 22:00h
			case $Stunden >=17 && $Stunden <22:
				$greet = $TL['GREETINGS']['EVENING_'.mt_rand (1, 5)];
			break;
			# Gruß nach 22:00h
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
	
	if ($events === false) {
		// prepare output without using events
		$enddate = date('m/d/Y',strtotime($dienst['']['hStart']));
		$days = (strtotime($enddate) - strtotime($today)) / (60*60*24);
		#if($dienst['']['fwDay'] < 0) {
		#	exit;
		if(($dienst['']['fwDay'] === 0) AND ($Stunden >=4 && $Stunden <10)){
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$dienst['']['Summary']." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif(($dienst['']['fwDay'] === 0) AND ($Stunden >=10 && $Stunden <17)){
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$dienst['']['Summary']." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif(($dienst['']['fwDay'] === 1) AND ($Stunden >=17 && $Stunden <22)){
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$dienst['']['Summary']." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
		} elseif(($dienst['']['fwDay'] === 1) AND ($Stunden >=22)){
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$dienst['']['Summary']." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
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
		if ((count($muellheute)) === 1 AND ($Stunden >=4 && $Stunden <10)) {
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$muellheute[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif ((count($muellheute)) === 2 AND ($Stunden >=4 && $Stunden <10)) {
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$muellheute[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE']." ".$muellheute[1]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif ((count($muellheute)) === 1 AND ($Stunden >=10 && $Stunden <17)) {
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$muellheute[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif ((count($muellheute)) === 2 AND ($Stunden >=10 && $Stunden <17)) {
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START']." ".$muellheute[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE']." ".$muellheute[1]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];
		} elseif ((count($muellmorgen)) === 1 AND ($Stunden >=17 && $Stunden <22)) {
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$muellmorgen[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
		} elseif ((count($muellmorgen)) === 2 AND ($Stunden >=17 && $Stunden <22)) {
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$muellmorgen[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE']." ".$muellmorgen[1]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
		} elseif ((count($muellmorgen)) === 1 AND ($Stunden >=22 && $Stunden <4)) {
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$muellmorgen[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
		} elseif ((count($muellmorgen)) === 2 AND ($Stunden >=22 && $Stunden <4)) {
			$speak = $greet." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START']." ".$muellmorgen[0]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE']." ".$muellmorgen[1]." ".$TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
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
		LOGGING('Please remove &debug from your syntax in Sonos4lox Calendar URL!',3);
		exit;
	}
	echo '<PRE><br>';
	#$calendar = ('{ "": { "hStart": "16.01.2019 16:30:00", "hEnd": "16.01.2019 19:00:00", "Start": 316888200, "End": 316897200, "Summary": "Kids abholen ", "Description": "", "fwDay": 3, "wkDay": 3 }, "hnow": "13.01.2019 16:15:03", "now": 316628103 }');
	$callurlcal = trim($config['VARIOUS']['CALDav2'].'&debug');
	$url = $config['VARIOUS']['CALDav2'];
	
	$parts = parse_url($callurlcal);
    if(isset($parts['query'])) {
        parse_str(urldecode($parts['query']), $parts['query']);
    }
    #return $parts;

	
	print_r($parts);
	$calendar = file_get_contents($callurlcal);
	$calendar = (string)utf8_encode($calendar);
	#print_r($calendar);
	$calendar = str_replace("{ \"","{ \"1",$calendar); 
	$calendar = json_decode($calendar, TRUE);
	print_r($calendar);
	
	if (empty($calendar))  {
		LOGGING('No actual appointments according to your "fwday" parameter received or something went wrong!',4);
		LOGGING('in addition please check/maintain events to be announced in your calendar URL!',4);
		exit(1);
	}
	#$findMich1 = "&events=";
	#$findMich2 = "&debug";
	#$e = strpos($callurlcal, $findMich1) + 8;
	#$j = strripos($callurlcal, $findMich2);
	#$k = substr($callurlcal,$e,$j-$e);
	#($u = explode('|', $k));
	#print_r($u);
	#echo $calendar[1]['Summary'];
	
	if (@isset($calendar[1]['fwDay']) AND @$calendar[1]['fwDay'] <> -1) {
		$utc = new DateTimeZone('UTC'); 
		$curtimezone = date_timezone_get(date_create());
		$zeit = date_create_from_format('d.m.Y G:i:s', $calendar[1]['hStart'], $utc);
		$zeit = date_timezone_set($zeit, $curtimezone);
		$Startzeit = $calendar[1]['Start'];
		$zeit = new Datetime("@$Startzeit");
		#echo "<br>Termin gefunden: " . date_format($zeit, 'G:i').'<br>';
		@$speak .= "Der nächste Termin im Kalender ist ";
		if ($calendar[1]['fwDay'] == 0)
			$speak .= "heute um " . date_format($zeit, 'G:i') . " Uhr und lautet: " . $calendar[1]['Summary'] . ". ";
		elseif ($calendar[1]['fwDay'] == 1)
			$speak .= "morgen um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar[1]['Summary'] . ". ";
		elseif ($calendar[1]['fwDay'] == 2)
			$speak .= "übermorgen um " . date_format($zeit, 'G:i') . " Uhr: " . $calendar[1]['Summary'] . ". ";
		else {
			$tage = array("Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag");
			$monate = array("Januar", "Februar", "März", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember");
			$speak .= $calendar[1]['Summary'] . "am " . $tage[$calendar[1]['wkDay']] . " den ". date_format($zeit, 'd'). ". " .$monate[date_format($zeit, 'm') - 1] ." um ". date_format($zeit, 'G:i');
		}
	echo ($speak);
	#echo '<br><br>';
	LOGGING('Calendar Announcement: '.$speak,7);
	LOGGING('Message been generated and pushed to T2S creation',5);
	return $speak;
	}
}

/** OBSOLETE
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
	$greet = $welcomearraymorning[array_rand($welcomearraymorning)];
	return $greet;
	}
	
	
/** OBSOLETE
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
	$greet = $welcomearrayevening[array_rand($welcomearrayevening)];
	return $greet;
	}
	



?>