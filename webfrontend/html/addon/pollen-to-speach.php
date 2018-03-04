<?php

function p2s() {
// pollenflug: Erstellt basierend auf Daten des deutschen Wetterdienstes eine Ansage bzgl. der Pollenbelastung
// TTS Nachricht, übermittelt sie an die T2S Engine und speichert das zurückkommende file lokal ab
// @Parameter = $text von sonos2.php

global $config, $debug, $town;

$town = $config['LOCATION']['town'];
if (empty($town)) {
	LOGGING('There is no entry in config maintained, please update your Sonos config!',3);
	exit;
}
#$town = "München";

$search = array('ä','ü','ö','Ä','Ü','Ö','ß');
$replace = array('ae','ue','oe','Ae','Ue','Oe','ss');
$town = str_replace($search,$replace,$town);

$polmuc = file_get_contents( "http://www.wetterdienst.de/Deutschlandwetter/".$town."/Pollenflug/" );

// Vorhersage für heute isolieren
$counter = 0;
do {

    $polarr[$counter] = substr($polmuc, strrpos($polmuc, "vorhersage_schrift1") + 19);
    $polmuc = substr($polmuc, 0, strrpos($polmuc, "vorhersage_schrift1"));
    $counter++;

} while (strlen($polmuc) !== 0);

$polmuc = $polarr[1];

// Belastung vorhanden?
if (strpos($polmuc, "esche_0.") == true 
and strpos($polmuc, "hasel_0." ) == true
and strpos($polmuc, "erle_0.") == true
and strpos($polmuc, "birke_0.") == true
and strpos($polmuc, "graeser_0.") == true
and strpos($polmuc, "roggen_0.") == true
and strpos($polmuc, "beifuss_0.") == true
and strpos($polmuc, "ambrosia_0.") == true) {
	$text = 'Heute ist keine Belastung durch Pollen zu erwarten.';
	return $text;
}

// Text für geringe Belastung
$gb_text = "";
if (strpos($polmuc, "esche_1.") == true) {
$gb_text = $gb_text . "Esche ";
}
if (strpos($polmuc, "hasel_1.") == true) {
$gb_text = $gb_text . "Haselnuss ";
}
if (strpos($polmuc, "erle_1.") == true) {
$gb_text = $gb_text . "Erle ";
}
if (strpos($polmuc, "birke_1.") == true) {
$gb_text = $gb_text . "Birke ";
}
if (strpos($polmuc, "graeser_1.") == true) {
$gb_text = $gb_text . "Graeser ";
}
if (strpos($polmuc, "roggen_1.") == true) {
$gb_text = $gb_text . "Roggen ";
}
if (strpos($polmuc, "beifuss_1.") == true) {
$gb_text = $gb_text . "Beifuss ";
}
if (strpos($polmuc, "ambrosia_1.") == true) {
$gb_text = $gb_text . "Ambrosia ";
}
if ($gb_text !== "") {
$gb_text = "Es ist eine geringe Belastung durch " . str_replace(" ", ", ", trim($gb_text) . " zu erwarten.");
}

// Text für mittlere Belastung
$mb_text = "";
if (strpos($polmuc, "esche_2.") == true
or strpos($polmuc, "esche_3.") == true) {
$mb_text = $mb_text . "Esche ";
}
if (strpos($polmuc, "hasel_2.") == true
or strpos($polmuc, "hasel_3.") == true) {
$mb_text = $mb_text . "Haselnuss ";
}
if (strpos($polmuc, "erle_2.") == true
or strpos($polmuc, "erle_3.") == true) {
$mb_text = $mb_text . "Erle ";
}
if (strpos($polmuc, "birke_2.") == true
or strpos($polmuc, "birke_3.") == true) {
$mb_text = $mb_text . "Birke ";
}
if (strpos($polmuc, "graeser_2.") == true
or strpos($polmuc, "graeser_3.") == true) {
$mb_text = $mb_text . "Graeser ";
}
if (strpos($polmuc, "roggen_2.") == true
or strpos($polmuc, "roggen_3.") == true) {
$mb_text = $mb_text . "Roggen ";
}
if (strpos($polmuc, "beifuss_2.") == true
or strpos($polmuc, "beifuss_3.") == true) {
$mb_text = $mb_text . "Beifuss ";
}
if (strpos($polmuc, "ambrosia_2.") == true
or strpos($polmuc, "ambrosia_3.") == true) {
$mb_text = $mb_text . "Ambrosia ";
}
if ($mb_text !== "") {
$mb_text = "Es ist eine mittlere Belastung durch " . str_replace(" ", ", ", trim($mb_text) . " zu erwarten.");
}

// Text für hohe Belastung
$hb_text = "";
if (strpos($polmuc, "esche_4.") == true
or strpos($polmuc, "esche_5.") == true
or strpos($polmuc, "esche_6.") == true) {
$hb_text = $hb_text . "Esche ";
}
if (strpos($polmuc, "hasel_4.") == true
or strpos($polmuc, "hasel_5.") == true
or strpos($polmuc, "hasel_6.") == true) {
$hb_text = $hb_text . "Haselnuss ";
}
if (strpos($polmuc, "erle_4.") == true
or strpos($polmuc, "erle_.5") == true
or strpos($polmuc, "erle_.6") == true) {
$hb_text = $hb_text . "Erle ";
}
if (strpos($polmuc, "birke_4.") == true
or strpos($polmuc, "birke_5.") == true
or strpos($polmuc, "birke_6.") == true) {
$hb_text = $hb_text . "Birke ";
}
if (strpos($polmuc, "graeser_4.") == true
or strpos($polmuc, "graeser_5.") == true
or strpos($polmuc, "graeser_6.") == true) {
$hb_text = $hb_text . "Graeser ";
}
if (strpos($polmuc, "roggen_4.") == true
or strpos($polmuc, "roggen_5.") == true
or strpos($polmuc, "roggen_6.") == true) {
$hb_text = $hb_text . "Roggen ";
}
if (strpos($polmuc, "beifuss_4.") == true
or strpos($polmuc, "beifuss_5.") == true
or strpos($polmuc, "beifuss_6.") == true) {
$hb_text = $hb_text . "Beifuss ";
}
if (strpos($polmuc, "ambrosia_4.") == true
or strpos($polmuc, "ambrosia_5.") == true
or strpos($polmuc, "ambrosia_6.") == true) {
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
$text = "Hier der heutige Hinweis zum Pollen Wetter. " . trim($text1);
$text = trim($text);

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

// Text ansagen
$text = preg_replace("/[^a-z0-9!. ]/i", "", $text);
$url = $text;
#echo $url;
LOGGING('Pollen level announcement: '.($url),5);
LOGGING('Message been generated and pushed to T2S creation',7);
return $url;

curl_setopt($curl, CURLOPT_URL, $url);
curl_exec($curl);
curl_close($curl);
}

?>