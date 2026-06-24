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



function tt2t()
{
	// https://developers.google.com/maps/documentation/distance-matrix/intro#DistanceMatrixRequests
	
	global $config, $debug, $traffic, $home, $myIP;
	
	$TL = LOAD_T2S_TEXT();
	    	
	$valid_traffic_models = array("pessimistic","best_guess","optimistic");
	if (empty($_GET['to'])) {
		S4L_Logger::write('You do not have a destination address maintained in syntax. Please enter address!',3, __FILE__);
		exit;
    } else {
		$arrival = $_GET['to'];
		S4L_Logger::write('Valid destination address has been found!',5, __FILE__);
	}
	$key 		= trim((string)($config['LOCATION']['googlekey'] ?? ''));
	$street		= (string)($config['LOCATION']['googlestreet'] ?? '');
	$town 		= (string)($config['LOCATION']['googletown'] ?? '');
	if ($key === '' || trim($street) === '' || trim($town) === '') {
		S4L_Logger::write('Google API key or start address is missing in config.', 3, __FILE__);
		exit;
	}
	if (isset($_GET['traffic'])) {
		$traffic = '1';
	} else {
		$traffic = '0';
	}
	$start = $street. ', '.$town;
	if (!isset($_GET['model'])) {
		$traffic_model 	= "best_guess";
	} else {
		$traffic_model 	= $_GET['model'];
		if (in_array($traffic_model, $valid_traffic_models)) {
			$traffic_model 	= $_GET['model'];
			S4L_Logger::write('Valid traffic model has been entered!',5, __FILE__);
		} else {
			S4L_Logger::write('The traffic model you have entered is invalid. Please correct!',3, __FILE__);
			exit;
		}
	}
	$lang		= "de"; // https://developers.google.com/maps/faq#languagesupport
	$mode 		= "driving"; // walking, bicycling, transit
	$units		= "metric"; // imperial
	$departure_time = time();
	$start      = urlencode($start);
    $arrival    = urlencode($arrival);
	
	if (isset($_GET['deptime']))  {
		$deptime = strtotime($_GET['deptime']);
		if ($deptime === false)  {
			S4L_Logger::write('Something went wrong with your time entry, please correct! Only 24h or US/UK formats are allowed (eg. 14:30 or 2:30pm)',3, __FILE__);
			exit(1);
		}
		if ($deptime < time())  {
			S4L_Logger::write('The departure time from your syntax need to be a future time! Current time you have entered is in the past, please correct',3, __FILE__);
			exit(1);
		} else {
			$time = $deptime;
		}
	} else {
		$time = time();
	}
	$request    = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . $start . "&destinations=" . $arrival . "&departure_time=" . $time . "&traffic_model=" . $traffic_model . "&mode=" . $mode . "&units=" . $units . "&key=" . $key . "&language=" . $lang;
    $jdata      = s4lox_addon_fetch_url($request, 10);
	#print_R($jdata);
	$data       = s4lox_addon_decode_json($jdata);
	if (empty($data) || !is_array($data)) {
		S4L_Logger::write('Data from Google Maps could not be obtainend! Please check your syntax',3, __FILE__);
		exit;
	} else {
		S4L_Logger::write('Data from Google Maps has been successful obtainend.',6, __FILE__);
	}
	if (!empty($data['error_message']))  {
		S4L_Logger::write('Google Maps API error: ' . $data['error_message'],3, __FILE__);
		exit(1);
	}
	$status     = $data["status"] ?? '';
    $row_status = $data["rows"][0]["elements"][0]["status"] ?? '';
    if ($status == "OK" && $row_status == "OK") {
        $distance = $data["rows"][0]["elements"][0]["distance"]["value"];
        $distance = round(($distance / 1000), 0);
        $duration = $data["rows"][0]["elements"][0]["duration"]["value"];
        $dhours   = floor($duration / 3600);
        $dminutes = floor($duration % 3600 / 60);
        $dseconds = $duration % 60;
        if ($dseconds >= 30) {
            $dminutes = $dminutes + 1;
        }
		$duration_in_traffic = $data["rows"][0]["elements"][0]["duration_in_traffic"]["value"] ?? $duration;
        $dthours             = floor($duration_in_traffic / 3600);
        $dtminutes           = floor($duration_in_traffic % 3600 / 60);
        $dtseconds           = $duration_in_traffic % 60;
		$start     = urldecode($start);
        $arrival   = urldecode($arrival);
        if ($dtseconds >= 30) {
            $dtminutes = $dtminutes + 1;
        }
		if ($traffic == '0') {
            $hours   = $dhours;
            $minutes = $dminutes;    
			$textpart1 = $TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT1']." ".$distance." ".$TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT2']." ".$arrival." ".$TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT3']." ". date("H", $time) ." ".$TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT4']." ".date("i", $time)." ".$TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT5']." ";
        } else {
            $hours   = $dthours;
            $minutes = $dtminutes;
			$textpart1 = $TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT1']." ".$distance." ".$TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT2']." ".$arrival." ".$TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT3']." ". date("H", $time) ." ".$TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT4']." ".date("i", $time)." ".$TL['DESTINATION-TO-SPEECH']['TEXT_ANNOUNCEMENT6']." ";
        }
		$textpart2 = '';
		if ($hours == 0 && $minutes == 1) {
            $textpart2 = $TL['DESTINATION-TO-SPEECH']['ONE_MINUTE'];
        } else if ($hours == 0 && $minutes > 1) {
            $textpart2 = $minutes . $TL['DESTINATION-TO-SPEECH']['MORE_THEN_ONE_MINUTE'];
        } else if ($hours == 1 && $minutes == 1) {
            $textpart2 = $TL['DESTINATION-TO-SPEECH']['ONE_HOUR_AND_MINUTES'];
        } else if ($hours == 1 && $minutes >= 1) {
            $textpart2 = $TL['DESTINATION-TO-SPEECH']['ONE_HOUR_AND']." ".$minutes." ".$TL['DESTINATION-TO-SPEECH']['MORE_THEN_ONE_MINUTE'];
        } else if ($hours > 1 && $minutes > 1) {
            $textpart2 = $hours . " ".$TL['DESTINATION-TO-SPEECH']['HOUR_AND_MINUTES']." ". $minutes." ".$TL['DESTINATION-TO-SPEECH']['MORE_THEN_ONE_MINUTE'];
        }
        $text = $textpart1 . $textpart2;
    } else {
		S4L_Logger::write('The entered URL is not complete or invalid. Please check URL! Google status=' . $status . ', row status=' . $row_status,3, __FILE__);
        exit;
    }
    $words = $text;
	#echo $request;
	
		$ttd = "Text = " . $text . "\r\n";
		$ttd .= "starting address = " . $start . "\r\n";
		$ttd .= "destination address = " . $arrival . "\r\n";
		$ttd .= "planned departuretime = " . date("H:i", $time) . "\r\n";
		$ttd .= "Traffic Model = " . $traffic_model . "\r\n";
		$ttd .= "Mode = " . $mode . "\r\n";
		$ttd .= "Dictance = " . $distance . "km / Zeit = " . $hours . " Stunden " . $minutes . " Minuten";

	#echo $text;
	S4L_Logger::write('Destination announcement: '.($ttd),7, __FILE__);
	S4L_Logger::write('Message been generated and pushed to T2S creation',5, __FILE__);
	return $words;
}


?>