<?php

/**
* Function: muellkalender --> list all waste events of a CALDav based calendar
*
* @param: text
* @return: 
**/
function muellkalender() {
	
	global $config;
	
	$home = posix_getpwuid(posix_getuid());
	$home = $home['dir'];
	if (!file_exists("$home/webfrontend/html/plugins/caldav4lox/caldav.php")) {
		trigger_error("The required Caldav-4-Lox Plugin is already not installed. Please install Plugin!", E_USER_ERROR);
		exit;
	}
	$myIP = $_SERVER["SERVER_ADDR"];
	if(substr($home,0,4) !== "/opt") {
		trigger_error("The system you are using is not a loxberry. This application runs only on LoxBerry!", E_USER_ERROR);
		exit;
	}
	// URL from Config
	$url = $config['VARIOUS']['CALDavMuell'];
	$checkdebug = strpos($url,"&debug");
	if ($checkdebug != false) {
		trigger_error("Please remove &debug from your syntax entry in Sonos4lox configuration!", E_USER_ERROR);
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
		trigger_error("Please remove &events= from your syntax entry in Sonos4lox configuration or enter add the events you are looking for!", E_USER_ERROR);
		exit;
	}
	if ($events === false) {
		// prepare output without using events
		$enddate = date('m/d/Y',strtotime($dienst['']['hStart']));
		$days = (strtotime($enddate) - strtotime($today)) / (60*60*24);
		#if($dienst['']['fwDay'] < 0) {
		#	exit;
		if(($dienst['']['fwDay'] === 0) AND ($Stunden >=4 && $Stunden <12)){
			$welcomemorning = welcomemorning($text);
			$speak = $welcomemorning." Hier noch einmal eine allerletzte Erinnerung. Gleich wird " . $dienst['']['Summary'] . " abgeholt. Falls die Muelltonne gestern nicht schon rausgestellt wurde wird es jetzt aber allerhöchste Zeit!";
		} elseif(($dienst['']['fwDay'] === 1) AND ($Stunden >=18)){
			$welcomeevening = welcomeevening($text);
			$speak = $welcomeevening." Ich bin es noch einmal. Morgen früh wird " . $dienst['']['Summary'] . " abgeholt. Falls die Muelltonne noch nicht vorm Haus steht bitte noch unbedingt daran denken sie raus zu stellen!. ";
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
		if ((count($muellheute)) === 1 AND ($Stunden >=0 && $Stunden <18)) {
			$welcomemorning = welcomemorning($text);
			$speak = $welcomemorning." Hier noch einmal eine allerletzte Erinnerung. Gleich wird " . $muellheute[0] . " abgeholt. Falls die Muelltonne gestern nicht schon rausgestellt wurde wird es jetzt aber allerhöchste Zeit!";
		} elseif ((count($muellheute)) === 2 AND ($Stunden >=0 && $Stunden <18)) {
			$welcomemorning = welcomemorning($text);
			$speak = $welcomemorning." Hier noch einmal eine allerletzte Erinnerung. Gleich wird " . $muellheute[0] . " und " .$muellheute[1]. " abgeholt. Falls die Muelltonne gestern nicht schon rausgestellt wurde wird es jetzt aber allerhöchste Zeit!";
		} elseif ((count($muellmorgen)) === 1 AND ($Stunden >=18 && $Stunden <24)) {
			$welcomeevening = welcomeevening($text);
			$speak = $welcomeevening." Ich bin es noch einmal. Morgen früh wird " . $muellmorgen[0] . " abgeholt. Falls die Muelltonne noch nicht vorm Haus steht bitte noch unbedingt daran denken sie raus zu stellen!. ";
		} elseif ((count($muellmorgen)) === 2 AND ($Stunden >=18 && $Stunden <24)) {
			$welcomeevening = welcomeevening($text);
			$speak = $welcomeevening." Ich bin es noch einmal. Morgen früh wird " . $muellmorgen[0] . " und " .$muellmorgen[1]. " abgeholt. Falls die Muelltonne noch nicht vorm Haus steht bitte noch unbedingt daran denken sie raus zu stellen!. ";
		} elseif ((empty($muellheute)) or (empty($muellmorgen)))  {
			echo 'Kein Abfalltermin für heute oder morgen im Kalender.';
			exit;
		}
	}
	if (empty($speak)) {
		exit;	
	}
	echo $speak;
	echo '<br><br>';
	return $speak;
}

	
/**
* Function: calendar --> list all events of a dedicate calendar
*
* @param: text
* @return: 
**/	
function calendar() {
	
	global $config;
	
	$home = posix_getpwuid(posix_getuid());
	$home = $home['dir'];
	if (!file_exists("$home/webfrontend/html/plugins/caldav4lox/caldav.php")) {
		trigger_error("The required Caldav-4-Lox Plugin is already not installed. Please install Plugin!", E_USER_ERROR);
		exit;
	}
	$myIP = $_SERVER["SERVER_ADDR"];
	if(substr($home,0,4) !== "/opt") {
		trigger_error("The system you are using is not a loxberry. This application runs only on LoxBerry!", E_USER_ERROR);
		exit;
	}
	$url = $config['VARIOUS']['CALDav2'];
	$checklength = strlen($url).'<br>';
	$checkdebug = @substr($url,$checklength - 5,$checklength);
	if ($checkdebug == "debug") {
		trigger_error("Please remove &debug from your syntax entry in Sonos4lox configuration!", E_USER_ERROR);
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
	#echo $speak;
	#echo '<br><br>';
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
	$welcomearraymorning = array(
		"Einen schönen guten Morgen!",
		"Guten Morgen!",
		"Hallo liebe Familie!",
		"Guten Morgen alle zusammen!",
		"Grüßt euch!",
		"Servus!",
		"Morgen!",
		"Herzlich willkommen in der Küche!",
		"Moin moin!",
		"Seit gegrüßt!");
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
	$welcomearrayevening = array(
		"Einen schönen, guten Abend!",
		"Guten Abend!",
		"Hallo!",
		"Hallo noch mal ihr zwei!",
		"Abend!",
		"Hallo, wie war euer Tag?");
	$welcomeevening = $welcomearrayevening[array_rand($welcomearrayevening)];
	return $welcomeevening;
	}
	



?>