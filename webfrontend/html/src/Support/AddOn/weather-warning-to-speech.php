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



function ww2s() {
// unwetter: Erstellt basierend auf Daten des deutschen Wetterdienstes eine Wetterwarnung
// TTS Nachricht, �bermittelt sie an die T2S Engine und speichert das zur�ckkommende file lokal ab
// @Parameter = $text von sonos2.php

global $config, $debug, $town, $region, $tmpsonos;

$town = $config['LOCATION']['town'];
$region = $config['LOCATION']['region'];
$town = htmlentities($town);

if (empty($town) or empty($region)) {
	S4L_Logger::write('Town or region is missing in config. Please maintain the location settings first.',3, __FILE__);
	exit;
}

$stadtgemeinde = s4lox_addon_fetch_url("https://www.dwd.de/DE/wetter/warnungen_gemeinden/warntabellen/warntab_".$region."_node.html", 10);
if ($stadtgemeinde === false || $stadtgemeinde === '') {
	S4L_Logger::write('Weather warning data could not be retrieved from Deutscher Wetterdienst.',3, __FILE__);
	exit;
}

// Verarbeitung des zur�ckerhaltenen Strings
$stadtgemeinde = preg_replace("/<[^>]+>/", "", $stadtgemeinde);
$townPos = strpos($stadtgemeinde, $town);
if ($townPos === false) {
	S4L_Logger::write('Configured town was not found in Deutscher Wetterdienst warning table.',5, __FILE__);
	return false;
}
$stadtgemeinde = substr($stadtgemeinde, $townPos + 18);

if (strpos($stadtgemeinde, "Gemeinde") !== false) {
    $stadtgemeinde = substr($stadtgemeinde, 0, strpos($stadtgemeinde, "Gemeinde"));
} elseif (strpos($stadtgemeinde, "Stadt") !== false) {
    $stadtgemeinde = substr($stadtgemeinde, 0, strpos($stadtgemeinde, "Stadt"));
}

$stadtgemeinde = preg_replace("#\(.*?\)#m", "", $stadtgemeinde);
$stadtgemeinde = substr($stadtgemeinde, strpos($stadtgemeinde, "Beschreibung") + 12);
$stadtgemeinde = str_replace('km/h', 'Kilometer pro Stunde', $stadtgemeinde);
$stadtgemeinde = str_replace('&deg;C', 'Grad', $stadtgemeinde);

if (empty($stadtgemeinde)) {
	S4L_Logger::write('No usable weather warning data could be retrieved from Deutscher Wetterdienst.',3, __FILE__);
	exit;
} else {
	S4L_Logger::write('Weather warning data has been successfully retrieved from Deutscher Wetterdienst.',6, __FILE__);
}	
#print_r(substr($stadtgemeinde,0 , 12));

// Falls kein Wetterhinweis oder Warnung vorliegt abbrechen
if (substr($stadtgemeinde,0 , 12) == 'er und Klima') {
	S4L_Logger::write('There are currently no weather warnings for the configured town.',5, __FILE__);
	exit;
}


// Nach Warnungen zerlegen
$counter = 0;
do {

    $uwarr[$counter] = substr($stadtgemeinde, strrpos($stadtgemeinde, "Uhr") + 3);
    $stadtgemeinde = substr($stadtgemeinde, 0, strrpos($stadtgemeinde, "Amtliche WARNUNG"));
    $counter++;

} while (strlen($stadtgemeinde) !== 0);

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
S4L_Logger::write('Weather warning announcement: '.($url),7, __FILE__);
S4L_Logger::write('Message been generated and pushed to T2S creation',5, __FILE__);
return $url;
}



function check_warning() {
// unwetter: Erstellt basierend auf Daten des deutschen Wetterdienstes eine Wetterwarnung
// TTS Nachricht, �bermittelt sie an die T2S Engine und speichert das zur�ckkommende file lokal ab
// @Parameter = $text von sonos2.php

global $config, $debug, $town, $region, $tmpsonos;

$town = $config['LOCATION']['town'];
$region = $config['LOCATION']['region'];
$town = htmlentities($town);

if (empty($town) or empty($region)) {
	S4L_Logger::write('Weather warning check has been executed but town or region is missing.',7, __FILE__);
	exit;
}

$stadtgemeinde = s4lox_addon_fetch_url("https://www.dwd.de/DE/wetter/warnungen_gemeinden/warntabellen/warntab_".$region."_node.html", 10);
if ($stadtgemeinde === false || $stadtgemeinde === '') {
	S4L_Logger::write('Weather warning data could not be retrieved from Deutscher Wetterdienst.',3, __FILE__);
	return false;
}

// Verarbeitung des zur�ckerhaltenen Strings
$stadtgemeinde = preg_replace("/<[^>]+>/", "", $stadtgemeinde);
$townPos = strpos($stadtgemeinde, $town);
if ($townPos === false) {
	S4L_Logger::write('Configured town was not found in Deutscher Wetterdienst warning table.',5, __FILE__);
	return false;
}
$stadtgemeinde = substr($stadtgemeinde, $townPos + 18);

if (strpos($stadtgemeinde, "Gemeinde") !== false) {
    $stadtgemeinde = substr($stadtgemeinde, 0, strpos($stadtgemeinde, "Gemeinde"));
} elseif (strpos($stadtgemeinde, "Stadt") !== false) {
    $stadtgemeinde = substr($stadtgemeinde, 0, strpos($stadtgemeinde, "Stadt"));
}

$stadtgemeinde = preg_replace("#\(.*?\)#m", "", $stadtgemeinde);
$stadtgemeinde = substr($stadtgemeinde, strpos($stadtgemeinde, "Beschreibung") + 12);
$stadtgemeinde = str_replace('km/h', 'Kilometer pro Stunde', $stadtgemeinde);
$stadtgemeinde = str_replace('&deg;C', 'Grad', $stadtgemeinde);

if (empty($stadtgemeinde)) {
	S4L_Logger::write('No usable weather warning data could be retrieved from Deutscher Wetterdienst.',3, __FILE__);
	return false;
} else {
	S4L_Logger::write('Weather warning data has been successfully retrieved from Deutscher Wetterdienst.',6, __FILE__);
}	
#print_r(substr($stadtgemeinde,0 , 12));

// Falls kein Wetterhinweis oder Warnung vorliegt abbrechen
if (substr($stadtgemeinde,0 , 12) == 'er und Klima') {
	S4L_Logger::write('There are currently no weather warnings for the configured town.',5, __FILE__);
	return false;
}
}
?>