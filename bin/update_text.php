#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";

$templatepath 			= "$lbptemplatedir";								// get templatedir
$lang_de_ini 			= "t2s-text_de.ini";
$lang_en_ini 			= "t2s-text_en.ini";
$lang_de_update_ini 	= "update-text_de.ini";
$lang_en_update_ini 	= "update-text_en.ini";

// Prüfen ggf. Parsen der Text updatedatei für DE
if (!file_exists($templatepath.'/lang/'.$lang_de_update_ini)) {
	echo "There is no update for language DE to be processed!\n";
	
} else {
	$upt_de = parse_ini_file($templatepath.'/lang/'.$lang_de_update_ini, TRUE);
	echo "Update file '".$lang_de_update_ini."' has been located and loaded\n";
}
// Prüfen ggf. Parsen der Text updatedatei für EN
if (!file_exists($templatepath.'/lang/'.$lang_en_update_ini)) {
	echo "There is no update for language EN to be processed!\n";
	// wenn kein Update dann Ausstieg
	exit(0);
} else {
	$upt_en = parse_ini_file($templatepath.'/lang/'.$lang_en_update_ini, TRUE);
	echo "Update file '".$lang_en_update_ini."' has been located and loaded\n";
}

// Parsen der Standard Textdatei für DE
if (!file_exists($templatepath.'/lang/'.$lang_de_ini )) {
	echo "<WARNING> The file '".$lang_de_ini."' could not be opened, we skip here!\n";
	exit(0);
} else {
	$std_de = parse_ini_file($templatepath.'/lang/'.$lang_de_ini, TRUE);
}
// Parsen der Standard Textdatei für EN
if (!file_exists($templatepath.'/lang/'.$lang_en_ini )) {
	echo "<WARNING> The file '".$lang_en_ini."' could not be opened, we skip here!\n";
	exit(0);
} else {
	$std_en = parse_ini_file($templatepath.'/lang/'.$lang_en_ini, TRUE);
}

// Language DE
$arrdiff_de = @array_diff_assoc($upt_de, $std_de);
if (!empty($arrdiff_de))  {
	# Schreibe file DE neu und lösche update file
	$merge_de = array_merge($arrdiff_de, $std_de);
	put_ini_file($merge_de, $templatepath.'/lang/'.$lang_de_ini, true);
	unlink($templatepath.'/lang/'.$lang_de_update_ini);
	echo "Update for '".$lang_de_ini."' file has been successfully processed\n";
} else {
	unlink($templatepath.'/lang/'.$lang_de_update_ini);
	echo "<OK> No update for '".$lang_de_ini."' necessary, everything is up-to-date\n";
}

// Language EN
$arrdiff_en = @array_diff_assoc($upt_en, $std_en);
if (!empty($arrdiff_en))  {
	# Schreibe file EN neu und lösche update file
	$merge_en = array_merge($arrdiff_en, $std_en);
	put_ini_file($merge_en, $templatepath.'/lang/'.$lang_en_ini, true);
	unlink($templatepath.'/lang/'.$lang_en_update_ini);
	echo "Update for '".$lang_en_ini."' file has been successfully processed\n";
} else {
	unlink($templatepath.'/lang/'.$lang_en_update_ini);
	echo "<OK> No update for '".$lang_en_ini."' necessary, everything is up-to-date\n";
}




/**
/* Funktion : put_ini_file --> schreibt array in ein INI file
/*
/* @param: 	data array, filename
/* @return: 
**/	

function put_ini_file($config, $file, $has_section = false, $write_to_file = true){
$fileContent = '';

if(!empty($config))  {
	foreach($config as $i=>$v)  {
		if($has_section)  {
			$fileContent .= "[".$i."]\n" . put_ini_file($v, $file, false, false);
		} else {
		if(is_array($v))  {
			foreach($v as $t=>$m){
				$fileContent .= $i."[".$t."]=".(is_numeric($m) ? $m : '"'.$m.'"') . "\n";
			}
		}
		else $fileContent .= $i . "=" . (is_numeric($v) ? $v : '"'.$v.'"') . "\n";
		}
	}
}
if($write_to_file && strlen($fileContent)) return file_put_contents($file, $fileContent, LOCK_EX);
else return $fileContent;
}

?>