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

// Falls kein Wetterhinweis oder Warnung vorliegt abbrechen
if (substr($stadtgemeinde,0 , 12) == 'er und Klima') {
	#$text = 'Es liegen derzeit schön keine Wetter Hinweise oder Warnungen für ihre Stadt bzw. Gemeinde vor';
	#return $text;
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

$url = urlencode($text);
#echo $url;
LOGGING('Wetter Warnung Ansage: '.$url,6);
return $url;

curl_setopt($curl, CURLOPT_URL, $url);
$return = curl_exec($curl);

curl_close($curl);
}

?>