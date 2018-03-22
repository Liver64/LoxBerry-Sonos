 <?php

function tt2t()
{
	// https://developers.google.com/maps/documentation/distance-matrix/intro#DistanceMatrixRequests
	
	global $config, $debug, $traffic, $home, $myIP;
	
	$TL = LOAD_T2S_TEXT();
	    	
	$valid_traffic_models = array("pessimistic","best_guess","optimistic");
	if (empty($_GET['to'])) {
		LOGGING('You do not have a destination address maintained in syntax. Please enter address!',3);
		exit;
    } else {
		$arrival = $_GET['to'];
		LOGGING('Valid destination address has been found!',5);
	}
	$key 		= trim($config['LOCATION']['googlekey']);
	$street		= $config['LOCATION']['googlestreet'];
	$town 		= $config['LOCATION']['googletown'];
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
			LOGGING('Valid traffic model has been entered!',5);
		} else {
			LOGGING('The traffic model you have entered is invalid. Please correct!',3);
			exit;
		}
	}
	$lang		= "de"; // https://developers.google.com/maps/faq#languagesupport
	$mode 		= "driving"; // walking, bicycling, transit
	$units		= "metric"; // imperial
	$departure_time = time();
    $start      = urlencode($start);
    $arrival    = urlencode($arrival);
	$time 		= time(); # + 900; // +15 Minuten Abfahrtzeit
	$request    = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . $start . "&destinations=" . $arrival . "&departure_time=" . $time . "&traffic_model=" . $traffic_model . "&mode=" . $mode . "&units=" . $units . "&key=" . $key . "&language=" . $lang;
    $jdata      = file_get_contents($request);
	#print_R($jdata);
	$data       = json_decode($jdata, true);
	if (empty($data)) {
		LOGGING('Data from Google Maps could not be obtainend! Please check your syntax',3);
		exit;
	} else {
		LOGGING('Data from Google Maps has been successful obtainend.',6);
	}	
	$status     = $data["status"];
    $row_status = $data["rows"][0]["elements"][0]["status"];
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
		$duration_in_traffic = $data["rows"][0]["elements"][0]["duration_in_traffic"]["value"];
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
		LOGGING('The entered URL is not complete or invalid. Please check URL!',3);
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
	LOGGING('Destination announcement: '.($ttd),7);
	LOGGING('Message been generated and pushed to T2S creation',5);
	return $words;
}


?>