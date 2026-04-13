<?php

require_once "loxberry_system.php";

#echo "<PRE>";
$piperfile = "piper.json";

if (file_exists($lbphtmldir . "/voice_engines/langfiles/" . $piperfile))    {
	$piper = json_decode(file_get_contents($lbphtmldir . "/voice_engines/langfiles/". $piperfile), TRUE);
}
$result = remove_duplicates_piper_array($piper,'value');
file_put_contents($lbphtmldir . "/voice_engines/langfiles/". $piperfile, json_encode($result, JSON_PRETTY_PRINT));
return;


function remove_duplicates_piper_array($array,$key)
    {
		$temp_array = [];
		foreach ($array as &$v) {
			if (!isset($temp_array[$v[$key]]))
			$temp_array[$v[$key]] =& $v;
		}
		$array = array_values($temp_array);
		return $array;
    }

?>