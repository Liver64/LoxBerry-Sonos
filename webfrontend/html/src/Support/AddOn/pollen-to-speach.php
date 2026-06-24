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



function p2s() {
// pollenflug: Erstellt basierend auf Daten des deutschen Wetterdienstes eine Ansage bzgl. der Pollenbelastung
// TTS Nachricht, �bermittelt sie an die T2S Engine und speichert das zur�ckkommende file lokal ab
// @Parameter = $text von sonos2.php

global $config, $debug, $town;

$town = trim((string)($config['LOCATION']['town'] ?? ''));
if (empty($town)) {
	S4L_Logger::write('There is no town entry maintained in config. Please update your Sonos config.',3, __FILE__);
	exit;
}
#$town = "M�nchen";

$search = array('�','�','�','�','�','�','�');
$replace = array('ae','ue','oe','Ae','Ue','Oe','ss');
$town = str_replace($search,$replace,$town);

$polmuc = s4lox_addon_fetch_url("http://www.wetterdienst.de/Deutschlandwetter/".$town."/Pollenflug/", 10);
if ($polmuc === false || $polmuc === '') {
	S4L_Logger::write('Pollen data could not be retrieved from wetterdienst.de.', 4, __FILE__);
	exit;
}

if (isset($_GET['greet']))  {
		$Stunden = intval(strftime("%H"));
		$TL = LOAD_T2S_TEXT();
		switch ($Stunden) {
			# Gru� von 04:00 bis 10:00h
			case $Stunden >=4 && $Stunden <10:
				$greet = $TL['GREETINGS']['MORNING_'.mt_rand (1, 5)];
			break;
			# Gru� von 10:00 bis 17:00h
			case $Stunden >=10 && $Stunden <17:
				$greet = $TL['GREETINGS']['DAY_'.mt_rand (1, 5)];
			break;
			# Gru� von 17:00 bis 22:00h
			case $Stunden >=17 && $Stunden <22:
				$greet = $TL['GREETINGS']['EVENING_'.mt_rand (1, 5)];
			break;
			# Gru� nach 22:00h
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


// Vorhersage fuer heute isolieren
$polarr = array();
$counter = 0;
while (($markerPos = strrpos($polmuc, "vorhersage_schrift1")) !== false) {
    $polarr[$counter] = substr($polmuc, $markerPos + 19);
    $polmuc = substr($polmuc, 0, $markerPos);
    $counter++;
}

if (!isset($polarr[1])) {
	S4L_Logger::write('Pollen data could not be parsed. Expected forecast marker was not found.', 4, __FILE__);
	exit;
}

$polmuc = $polarr[1];

// Belastung vorhanden?
if (strpos($polmuc, "esche_0.") !== false 
and strpos($polmuc, "hasel_0." ) !== false
and strpos($polmuc, "erle_0.") !== false
and strpos($polmuc, "birke_0.") !== false
and strpos($polmuc, "graeser_0.") !== false
and strpos($polmuc, "roggen_0.") !== false
and strpos($polmuc, "beifuss_0.") !== false
and strpos($polmuc, "ambrosia_0.") !== false) {
	$text = 'Heute ist keine Belastung durch Pollen zu erwarten.';
	return $text;
}

// Text f�r geringe Belastung
$gb_text = "";
if (strpos($polmuc, "esche_1.") !== false) {
$gb_text = $gb_text . "Esche ";
}
if (strpos($polmuc, "hasel_1.") !== false) {
$gb_text = $gb_text . "Haselnuss ";
}
if (strpos($polmuc, "erle_1.") !== false) {
$gb_text = $gb_text . "Erle ";
}
if (strpos($polmuc, "birke_1.") !== false) {
$gb_text = $gb_text . "Birke ";
}
if (strpos($polmuc, "graeser_1.") !== false) {
$gb_text = $gb_text . "Graeser ";
}
if (strpos($polmuc, "roggen_1.") !== false) {
$gb_text = $gb_text . "Roggen ";
}
if (strpos($polmuc, "beifuss_1.") !== false) {
$gb_text = $gb_text . "Beifuss ";
}
if (strpos($polmuc, "ambrosia_1.") !== false) {
$gb_text = $gb_text . "Ambrosia ";
}
if ($gb_text !== "") {
$gb_text = "Es ist eine geringe Belastung durch " . str_replace(" ", ", ", trim($gb_text) . " zu erwarten.");
}

// Text f�r mittlere Belastung
$mb_text = "";
if (strpos($polmuc, "esche_2.") !== false
or strpos($polmuc, "esche_3.") !== false) {
$mb_text = $mb_text . "Esche ";
}
if (strpos($polmuc, "hasel_2.") !== false
or strpos($polmuc, "hasel_3.") !== false) {
$mb_text = $mb_text . "Haselnuss ";
}
if (strpos($polmuc, "erle_2.") !== false
or strpos($polmuc, "erle_3.") !== false) {
$mb_text = $mb_text . "Erle ";
}
if (strpos($polmuc, "birke_2.") !== false
or strpos($polmuc, "birke_3.") !== false) {
$mb_text = $mb_text . "Birke ";
}
if (strpos($polmuc, "graeser_2.") !== false
or strpos($polmuc, "graeser_3.") !== false) {
$mb_text = $mb_text . "Graeser ";
}
if (strpos($polmuc, "roggen_2.") !== false
or strpos($polmuc, "roggen_3.") !== false) {
$mb_text = $mb_text . "Roggen ";
}
if (strpos($polmuc, "beifuss_2.") !== false
or strpos($polmuc, "beifuss_3.") !== false) {
$mb_text = $mb_text . "Beifuss ";
}
if (strpos($polmuc, "ambrosia_2.") !== false
or strpos($polmuc, "ambrosia_3.") !== false) {
$mb_text = $mb_text . "Ambrosia ";
}
if ($mb_text !== "") {
$mb_text = "Es ist eine mittlere Belastung durch " . str_replace(" ", ", ", trim($mb_text) . " zu erwarten.");
}

// Text f�r hohe Belastung
$hb_text = "";
if (strpos($polmuc, "esche_4.") !== false
or strpos($polmuc, "esche_5.") !== false
or strpos($polmuc, "esche_6.") !== false) {
$hb_text = $hb_text . "Esche ";
}
if (strpos($polmuc, "hasel_4.") !== false
or strpos($polmuc, "hasel_5.") !== false
or strpos($polmuc, "hasel_6.") !== false) {
$hb_text = $hb_text . "Haselnuss ";
}
if (strpos($polmuc, "erle_4.") !== false
or strpos($polmuc, "erle_.5") !== false
or strpos($polmuc, "erle_.6") !== false) {
$hb_text = $hb_text . "Erle ";
}
if (strpos($polmuc, "birke_4.") !== false
or strpos($polmuc, "birke_5.") !== false
or strpos($polmuc, "birke_6.") !== false) {
$hb_text = $hb_text . "Birke ";
}
if (strpos($polmuc, "graeser_4.") !== false
or strpos($polmuc, "graeser_5.") !== false
or strpos($polmuc, "graeser_6.") !== false) {
$hb_text = $hb_text . "Graeser ";
}
if (strpos($polmuc, "roggen_4.") !== false
or strpos($polmuc, "roggen_5.") !== false
or strpos($polmuc, "roggen_6.") !== false) {
$hb_text = $hb_text . "Roggen ";
}
if (strpos($polmuc, "beifuss_4.") !== false
or strpos($polmuc, "beifuss_5.") !== false
or strpos($polmuc, "beifuss_6.") !== false) {
$hb_text = $hb_text . "Beifuss ";
}
if (strpos($polmuc, "ambrosia_4.") !== false
or strpos($polmuc, "ambrosia_5.") !== false
or strpos($polmuc, "ambrosia_6.") !== false) {
$hb_text = $hb_text . "Ambrosia ";
}
if ($hb_text !== "") {
$hb_text = "Es ist eine hohe Belastung durch " . str_replace(" ", ", ", trim($hb_text) . " zu erwarten.");
}

// Ansagetext zusammen stellen
$text1 = "";
if ($gb_text !== "") {$text1 = trim($gb_text) . " " ;}
if ($mb_text !== "") {$text1 = $text1 . trim($mb_text) . " ";}
if ($hb_text !== "") {$text1 = $text1 . trim($hb_text) . " ";}
$text = $greet." Hier der heutige Hinweis zum Pollen Wetter. " . trim($text1);
$text = trim($text);


// Text ansagen
$text = preg_replace("/[^a-z0-9!. ]/i", "", $text);
$url = $text;
#echo $url;
S4L_Logger::write('Pollen level announcement: '.($url),5, __FILE__);
S4L_Logger::write('Message been generated and pushed to T2S creation',7, __FILE__);
return $url;
}

?>