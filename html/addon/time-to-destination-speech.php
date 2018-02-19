 <?php

function tt2t()
{
	// https://developers.google.com/maps/documentation/distance-matrix/intro#DistanceMatrixRequests
	
	global $config, $debug, $traffic;
    	
	$valid_traffic_models = array("pessimistic","best_guess","optimistic");
	if (empty($_GET['to'])) {
        trigger_error('No destination address maintained in syntax. Please enter address!', E_USER_ERROR);
    } else {
		$arrival = $_GET['to'];
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
		} else {
			trigger_error('The traffic model entered is invalid. Please correct!', E_USER_ERROR);
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
			$textpart1 = "Die Fahrzeit für die Strecke von " . $distance . " km nach " . $arrival . " beträgt bei geplanter Abfahrtszeit von ". date("H", $time) ." Uhr ". date("i", $time) ." ohne Berücksichtigung des Verkehrs ca. ";
        } else {
            $hours   = $dthours;
            $minutes = $dtminutes;
			$textpart1 = "Die Fahrzeit für die Strecke von " . $distance . " km nach " . $arrival . " beträgt bei geplanter Abfahrtszeit von ". date("H", $time) ." Uhr ". date("i", $time) ." unter Berücksichtigung des Verkehrs ca. ";
        }
		if ($hours == 0 && $minutes == 1) {
            $textpart2 = "eine Minute";
        } else if ($hours == 0 && $minutes > 1) {
            $textpart2 = $minutes . " Minuten";
        } else if ($hours == 1 && $minutes == 1) {
            $textpart2 = "eine Stunde und eine Minute";
        } else if ($hours == 1 && $minutes >= 1) {
            $textpart2 = "eine Stunde und " . $minutes . " Minuten";
        } else if ($hours > 1 && $minutes > 1) {
            $textpart2 = $hours . " Stunden und " . $minutes . " Minuten";
        }
        $text = $textpart1 . $textpart2;
    } else {
        trigger_error('The URL is not complete or invalid. Please check URL!', E_USER_ERROR);
    }
    $words = urlencode($text);
	#echo $request;
	
	if( $debug == 1) {
		echo "<b>-----------------------------------------------------------------------</b><br>";
		echo "Text = " . $text . "<br>";
		echo "Abfahrtsort = " . $start . "<br>";
		echo "Ankunftsort = " . $arrival . "<br>";
		echo "geplante Abfahrtszeit = " . date("H:i", $time) . "<br>";
		echo "Traffic Model = " . $traffic_model . "<br>";
		echo "Mode = " . $mode . "<br>";
		echo "Entfernung = " . $distance . "km / Zeit = " . $hours . " Stunden " . $minutes . " Minuten<br>";
		echo "<b>-----------------------------------------------------------------------</b><br>";
	}
	
	return $words;
}


?>