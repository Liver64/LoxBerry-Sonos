<?php

function ww2s() {
// unwetter: Erstellt basierend auf Daten des deutschen Wetterdienstes eine Wetterwarnung
// TTS Nachricht, übermittelt sie an die T2S Engine und speichert das zurückkommende file lokal ab
// @Parameter = $text von sonos2.php

global $config, $debug, $town, $region, $tmpsonos;

$town = $config['LOCATION']['town'];
$region = $config['LOCATION']['region'];
$town = htmlentities($town);

if (empty($town) or empty($region)) {
	LOGGING('Es ist keine Stadt bzw. Gemeinde oder Bundesland in der Konfiguration gepflegt. Bitte erst eingeben!',3);
	exit;
}

$stadtgemeinde = file_get_contents("http://www.dwd.de/DE/wetter/warnungen_gemeinden/warntabellen/warntab_".$region."_node.html");

// Verarbeitung des zurückerhaltenen Strings
$stadtgemeinde = preg_replace("/<[^>]+>/", "", $stadtgemeinde);
$stadtgemeinde = substr($stadtgemeinde, strpos($stadtgemeinde, $town) + 18);

if (strpos($stadtgemeinde, "Gemeinde")) {
    $stadtgemeinde = substr($stadtgemeinde, 0, strpos($stadtgemeinde, "Gemeinde"));
} elseif (strpos($stadtgemeinde, "Stadt")) {
    $stadtgemeinde = substr($stadtgemeinde, 0, strpos($stadtgemeinde, "Stadt"));
}

$stadtgemeinde = preg_replace("#\(.*?\)#m", "", $stadtgemeinde);
$stadtgemeinde = substr($stadtgemeinde, strpos($stadtgemeinde, "Beschreibung") + 12);
$stadtgemeinde = str_replace('km/h', 'Kilometer pro Stunde', $stadtgemeinde);
$stadtgemeinde = str_replace('&deg;C', 'Grad', $stadtgemeinde);

if (empty($stadtgemeinde)) {
	LOGGING('Es konnten keine Daten vom Deutschen Wetterdienst bezogen werden',3);
	exit;
} else {
	LOGGING('Daten vom Deutschen Wetterdienst wurden erfolgreich bezogen.',6);
}	
#print_r(substr($stadtgemeinde,0 , 12));

// Falls kein Wetterhinweis oder Warnung vorliegt abbrechen
if (substr($stadtgemeinde,0 , 12) == 'er und Klima') {
	LOGGING('Es liegen derzeit keine Wetter Hinweise oder Warnungen für ihre Stadt bzw. Gemeinde vor.',5);
	exit;
}


// Nach Warnungen zerlegen
$counter = 0;
do {

    $uwarr[$counter] = substr($stadtgemeinde, strrpos($stadtgemeinde, "Uhr") + 3);
    $stadtgemeinde = substr($stadtgemeinde, 0, strrpos($stadtgemeinde, "Amtliche WARNUNG"));
    $counter++;

} while (strlen($stadtgemeinde) !== 0);

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

// Text zusammen schreiben
$text = "Achtung ! Wetter Hinweis bzw. Warnung! ";
for ($counter2 = 0; $counter2 < $counter; $counter2++) {
    $uwarr[$counter2] = utf8_decode($uwarr[$counter2]);
    $text = $text . $uwarr[$counter2] . " ";
}

$text = html_entity_decode($text);

// Text ansagen
$text = str_replace("Warnzeitraum", "Warn Zeitraum", $text);
$text = str_replace(" M ", " Metern ", $text);
$text = str_replace(" m ", " Metern ", $text);

$url = $text;
#echo $url;
LOGGING('Wetter Warnung Ansage: '.($url),7);
LOGGING('Message been generated and pushed to T2S creation',5);
#return $url;

curl_setopt($curl, CURLOPT_URL, $url);
$return = curl_exec($curl);

curl_close($curl);
return $url;
}



function check_warning() {
// unwetter: Erstellt basierend auf Daten des deutschen Wetterdienstes eine Wetterwarnung
// TTS Nachricht, übermittelt sie an die T2S Engine und speichert das zurückkommende file lokal ab
// @Parameter = $text von sonos2.php

global $config, $debug, $town, $region, $tmpsonos;

$town = $config['LOCATION']['town'];
$region = $config['LOCATION']['region'];
$town = htmlentities($town);

if (empty($town) or empty($region)) {
	LOGGING('Prüfung ob eine aktuelle Warnung vorliegt wurde ausgeführt',7);
	exit;
}

$stadtgemeinde = file_get_contents("http://www.dwd.de/DE/wetter/warnungen_gemeinden/warntabellen/warntab_".$region."_node.html");

// Verarbeitung des zurückerhaltenen Strings
$stadtgemeinde = preg_replace("/<[^>]+>/", "", $stadtgemeinde);
$stadtgemeinde = substr($stadtgemeinde, strpos($stadtgemeinde, $town) + 18);

if (strpos($stadtgemeinde, "Gemeinde")) {
    $stadtgemeinde = substr($stadtgemeinde, 0, strpos($stadtgemeinde, "Gemeinde"));
} elseif (strpos($stadtgemeinde, "Stadt")) {
    $stadtgemeinde = substr($stadtgemeinde, 0, strpos($stadtgemeinde, "Stadt"));
}

$stadtgemeinde = preg_replace("#\(.*?\)#m", "", $stadtgemeinde);
$stadtgemeinde = substr($stadtgemeinde, strpos($stadtgemeinde, "Beschreibung") + 12);
$stadtgemeinde = str_replace('km/h', 'Kilometer pro Stunde', $stadtgemeinde);
$stadtgemeinde = str_replace('&deg;C', 'Grad', $stadtgemeinde);

if (empty($stadtgemeinde)) {
	LOGGING('Es konnten keine Daten vom Deutschen Wetterdienst bezogen werden',3);
	return false;
} else {
	LOGGING('Daten vom Deutschen Wetterdienst wurden erfolgreich bezogen.',6);
}	
#print_r(substr($stadtgemeinde,0 , 12));

// Falls kein Wetterhinweis oder Warnung vorliegt abbrechen
if (substr($stadtgemeinde,0 , 12) == 'er und Klima') {
	LOGGING('Es liegen derzeit keine Wetter Hinweise oder Warnungen für ihre Stadt bzw. Gemeinde vor.',5);
	return false;
}
}
?>