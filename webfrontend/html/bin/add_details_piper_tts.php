#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
#echo "<PRE>";

$piperfile = "Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx.json";

if (file_exists($lbphtmldir . "/voice_engines/piper-voices/" . $piperfile))    {
	$piper = json_decode(file_get_contents($lbphtmldir . "/voice_engines/piper-voices/". $piperfile), TRUE);
} 
if (array_key_exists('language', $piper) && array_key_exists('dataset', $piper))    {
	echo "Key exists";
	exit;
} else {
	# add details to Thorsten Hessisch
	$array = array();
	$array['language'] = array();
	$array['language']['code'] = 'de_DE';
	$array['language']['family'] = 'de';
	$array['language']['region'] = 'DE';
	$array['language']['name_native'] = 'Deutsch';
	$array['language']['name_english'] = 'German';
	$array['language']['country_english'] = 'Germany';
	$array['dataset'] = 'thorsten_hessisch';
	$piper = array_merge($piper, $array); 
}
#print_r($piper);
file_put_contents($lbphtmldir . "/voice_engines/piper-voices/". $piperfile, json_encode($piper, JSON_PRETTY_PRINT));

?>