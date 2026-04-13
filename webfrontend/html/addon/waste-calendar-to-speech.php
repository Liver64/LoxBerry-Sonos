<?php

/**
* Function: muellkalender --> list all waste events of a CALDav based calendar
*
* @param: text
* @return: 
**/
function muellkalender() {
	global $config, $home, $myIP;

	$TL = LOAD_T2S_TEXT();

	if (!file_exists("$home/webfrontend/html/plugins/caldav4lox/caldav.php")) {
		LOGGING('waste-calendar-to-speech.php: The required Caldav-4-Lox Plugin is already not installed. Please install Plugin!', 3);
		exit;
	}

	if (substr($home, 0, 4) !== "/opt") {
		LOGGING('waste-calendar-to-speech.php: The system you are using is not a loxberry. This application runs only on LoxBerry!', 3);
		exit;
	}

	// URL from Config
	if ($url === '') {
		lLOGGING("waste-calendar-to-speech.php: Config for waste calendar is empty – aborting.");
		exit(0); // or exit(1) if you want it to be treated as an error
	}

	$checkdebug = strpos($url, "&debug");
	if ($checkdebug !== false) {
		LOGGING('waste-calendar-to-speech.php: Please remove &debug from your syntax entry in Sonos4lox configuration!', 3);
		exit;
	}

	$callurl = trim($config['VARIOUS']['CALDavMuell'] . '&debug');

	// Leerzeichen durch ein Plus ersetzen
	$callurl = str_replace(' ', '+', $callurl);

	$Stunden = (int) strftime("%H");
	$muellarten = array();

	// call the waste calendar
	$dienst = json_decode(file_get_contents($callurl), true);
	if (empty($dienst) || !is_array($dienst)) {
		LOGGING('waste-calendar-to-speech.php: Waste calendar: No or invalid JSON received from CalDav URL!', 4);
		exit;
	}

	$today = date('m/d/Y');

	$events = strpos($url, "events");
	// (Optional) stricter check for empty "&events=" at end (fix old strlen + '<br>' bug)
	if ($events !== false) {
		// If URL contains "...&events=" but nothing after it
		if (preg_match('/[?&]events=$/', $url)) {
			LOGGING('waste-calendar-to-speech.php: Please remove &events= from your syntax entry in Sonos4lox configuration or add the events you are looking for!', 3);
			exit;
		}
	}

	// Greeting
	if (isset($_GET['greet'])) {
		switch (true) {
			case ($Stunden >= 4 && $Stunden < 10):
				$greet = $TL['GREETINGS']['MORNING_' . mt_rand(1, 5)];
				break;
			case ($Stunden >= 10 && $Stunden < 17):
				$greet = $TL['GREETINGS']['DAY_' . mt_rand(1, 5)];
				break;
			case ($Stunden >= 17 && $Stunden < 22):
				$greet = $TL['GREETINGS']['EVENING_' . mt_rand(1, 5)];
				break;
			case ($Stunden >= 22 || $Stunden < 4):
				$greet = $TL['GREETINGS']['NIGHT_' . mt_rand(1, 5)];
				break;
			default:
				$greet = "";
				break;
		}
	} else {
		$greet = "";
	}

	// ============================
	// A) Output without using events
	// ============================
	if ($events === false) {

		// NOTE: this assumes the caldav4lox JSON uses the empty-key entry [''] for "next event"
		$fwDay   = $dienst['']['fwDay']   ?? null;
		$summary = $dienst['']['Summary'] ?? null;

		if ($fwDay === null || $summary === null) {
			LOGGING('waste-calendar-to-speech.php: Waste calendar: Missing expected keys (fwDay/Summary) in JSON response!', 4);
			exit;
		}

		if ($fwDay === 0 && ($Stunden >= 4 && $Stunden < 17)) {
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START'] . " " . $summary . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];

		} elseif ($fwDay === 1 && ($Stunden >= 4 && $Stunden < 17)) {
			// NEW: also announce tomorrow in the morning/day if today has no waste
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $summary . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];

		} elseif ($fwDay === 1 && ($Stunden >= 17 && $Stunden < 22)) {
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $summary . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];

		} elseif ($fwDay === 1 && ($Stunden >= 22 || $Stunden < 4)) {
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $summary . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];
		}

	} else {

		// ============================
		// B) Output using events
		// ============================
		$muell = substr($url, strrpos($url, "events") + 7); // after "events="
		$muellarten = explode('|', $muell);

		$muellheute = array();
		$muellmorgen = array();

		foreach ($muellarten as $abfall => $val) {

			if (!isset($dienst[$val])) {
				// event not found in returned JSON, skip
				continue;
			}

			$starttime = substr($dienst[$val]['hStart'], 11, 8);
			$endtime   = substr($dienst[$val]['hEnd'], 11, 8);

			// ensure only full day appointments were picked
			if ($endtime == $starttime) {   // FIX: comparison, not assignment
				if ($dienst[$val]['fwDay'] === 0) {
					$muellheute[] = $val;
				} elseif ($dienst[$val]['fwDay'] === 1) {
					$muellmorgen[] = $val;
				}
			}
		}

		// prepare speech
		if (count($muellheute) === 1 && ($Stunden >= 4 && $Stunden < 17)) {
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START'] . " " . $muellheute[0] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];

		} elseif (count($muellheute) === 2 && ($Stunden >= 4 && $Stunden < 17)) {
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_START'] . " " . $muellheute[0] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE'] . " " . $muellheute[1] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['TODAY_MORNING_END'];

		} elseif (empty($muellheute) && count($muellmorgen) === 1 && ($Stunden >= 4 && $Stunden < 17)) {
			// NEW: morning/day -> announce tomorrow if today is empty
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $muellmorgen[0] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];

		} elseif (empty($muellheute) && count($muellmorgen) === 2 && ($Stunden >= 4 && $Stunden < 17)) {
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $muellmorgen[0] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE'] . " " . $muellmorgen[1] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];

		} elseif (count($muellmorgen) === 1 && ($Stunden >= 17 && $Stunden < 22)) {
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $muellmorgen[0] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];

		} elseif (count($muellmorgen) === 2 && ($Stunden >= 17 && $Stunden < 22)) {
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $muellmorgen[0] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE'] . " " . $muellmorgen[1] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];

		} elseif (count($muellmorgen) === 1 && ($Stunden >= 22 || $Stunden < 4)) { // FIX
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $muellmorgen[0] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];

		} elseif (count($muellmorgen) === 2 && ($Stunden >= 22 || $Stunden < 4)) { // FIX
			$speak = $greet . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_START'] . " " . $muellmorgen[0] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['IN_CASE_2TIMES_WASTE'] . " " . $muellmorgen[1] . " " . $TL['WASTE-CALENDAR-TO-SPEECH']['EVENING_BEFORE_END'];

		} elseif (empty($muellheute) && empty($muellmorgen)) {  // FIX: only if BOTH are empty
			$text = $TL['WASTE-CALENDAR-TO-SPEECH']['NO_WASTE_FOUND_ONLY_LOGGING'];
			LOGGING('waste-calendar-to-speech.php: Waste calendar: ' . $text, 6);
			exit;
		}
	}

	if (empty($speak)) {
		exit;
	}

	LOGGING('waste-calendar-to-speech.php: Waste calendar Announcement: ' . $speak, 7);
	LOGGING('waste-calendar-to-speech.php: Message been generated and pushed to T2S creation', 5);
	return $speak;
}
?>