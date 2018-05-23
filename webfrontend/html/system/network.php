<?php
/**
* Funktion : ermittelt automatisch die IP Adressen der sich im Netzwerk befindlichen Sonos Komponenten
* @param: 	$ip = Multicast Adresse
*			$port = Port
*
* @return: Array mit allen gefunden Zonen, IP-Adressen, Rincon-ID's und Sonos Modell
**/

global $sonosnet;

echo '<PRE>';
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "logging.php";
require_once __DIR__ . "/vendor/autoload.php";

use duncan3dc\Sonos\Network;

ini_set("log_errors", 7);
ini_set("error_log", LBPLOGDIR."/sonos.log");

// define variables
$home = $lbhomedir;
$folder = $lbpplugindir;

LOGGING("Initial network scan (MULTICAST) for supported Sonos devices in your network will be executed.",5);
$sonos3dc = new Network;
$zonen = array();
$speakers = $sonos3dc->getSpeakers();
// prepare detailed array
foreach ($speakers as $speaker) {
	$name = substr($speaker->name, 22, 100);
	$ip = $speaker->ip;
	$room = $speaker->room;
	$uuid = $speaker->getUuid();
	$search = array('Ä','ä','Ö','ö','Ü','ü','ß');
	$replace = array('Ae','ae','Oe','oe','Ue','ue','ss');
	$room = strtolower(str_replace($search,$replace,$room));
	LOGGING("Sonos Model '".$name."' called '".$room."' using IP '".$ip."' and Rincon-ID '".$uuid."' has been identified." ,6);
	$zonen =	[(string)$room,
				(string)$ip,
				(string)$uuid,
				(string)$name,
				'',
				'', 						
				''
				];
	$raum = array_shift($zonen);
	$sonosfinal[$raum] = $zonen;
	
}
ksort($sonosfinal);
// parse configuration file, returning $sonosnet
parse_cfg_file(); 
// prepare logging
if (!empty($sonosnet)) {
	foreach ($sonosnet as $key => $val)  {
		LOGGING("Sonos Model '".$val[2]."' called '".$key."' using IP '".$val[0]."' and Rincon-ID '".$val[1]."' are currently used in your Sonos Plugin." ,5);
	}
}
$new = count($sonosfinal) - count($sonosnet);
if ($new == '0')  {
	LOGGING("No new Zones has been found", 6);
} else {
	LOGGING($new." new Zone(s) has been found which are currently not in your configuration", 6);
}
// compare scan results vs. parsed configuration file
if(empty($sonosnet)) {
	$finalzones = $sonosfinal;
} else {
// computes the difference of arrays with additional index check
	$finalzones = @array_diff_assoc($sonosfinal, $sonosnet);
}
// prepare logging
foreach ($finalzones as $key => $val)  {
	LOGGING("Sonos Model '".$val[2]."' called '".$key."' using IP '".$val[0]."' and Rincon-ID '".$val[1]."' will be added to your Plugin configuration." ,5);
}
// save array as JSON file
array2json($finalzones);
$fh = fopen($home.'/config/plugins/'.$folder.'/tmp_player.json', 'w');
LOGGING("The initial setup has been completed.",7);
fwrite($fh, json_encode($finalzones));
fclose($fh);
LOGGING("File 'tmp_player.json' has been saved and system setup passed over to LoxBerry Configuration.",6);
exit;



/**
* Funktion : 	parse_cfg_file --> parsed die player.cfg in eine Array
* 				Subwoofer, Bridge und Dock werden nicht berücksichtigt
*
* @return: $array --> gespeicherte Sonos Zonen
**/
function parse_cfg_file() {
	global $sonosnet, $home, $folder;
	// Laden der Zonen Konfiguration aus player.cfg
	$tmp = parse_ini_file($home.'/config/plugins/'.$folder.'/player.cfg', true);
	$player = ($tmp['SONOSZONEN']);
	foreach ($player as $zonen => $key) {
		$sonosnet[$zonen] = explode(',', $key[0]);
	}
	LOGGING("Existing configuration file 'player.cfg' has been loaded successfully.",7);
	return $sonosnet;
	}


/**
* Funktion : 	array2json --> konvertiert array in JSON Format
* http://www.bin-co.com/php/scripts/array2json/
* 
* @return: JSON string
**/

function array2json($arr) { 
    if(function_exists('json_encode')) return json_encode($arr); // Lastest versions of PHP already has this functionality.
    $parts = array(); 
    $is_list = false; 

    // Find out if the given array is a numerical array 
    $keys = array_keys($arr); 
    $max_length = count($arr)-1; 
    if(($keys[0] == 0) and ($keys[$max_length] == $max_length)) {// See if the first key is 0 and last key is length - 1 
        $is_list = true; 
        for($i=0; $i<count($keys); $i++) { // See if each key correspondes to its position 
            if($i != $keys[$i]) { // A key fails at position check. 
                $is_list = false; // It is an associative array. 
                break; 
            } 
        } 
    } 

    foreach($arr as $key=>$value) { 
        if(is_array($value)) { // Custom handling for arrays 
            if($is_list) $parts[] = array2json($value); /* :RECURSION: */ 
            else $parts[] = '"' . $key . '":' . array2json($value); /* :RECURSION: */ 
        } else { 
            $str = ''; 
            if(!$is_list) $str = '"' . $key . '":'; 

            // Custom handling for multiple data types 
            if(is_numeric($value)) $str .= $value; // Numbers 
            elseif($value === false) $str .= 'false'; // The booleans 
            elseif($value === true) $str .= 'true'; 
            else $str .= '"' . addslashes($value) . '"'; // All other things 
            $parts[] = $str; 
        } 
    } 
    $json = implode(',',$parts); 
     
    if($is_list) return '[' . $json . ']';// Return numerical JSON 
    return '{' . $json . '}'; // Return associative JSON 
} 


 
 



?>