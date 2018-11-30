<?php

function GetTodayBauernregel() {
	$month = date('m');
	$bauernregel = file_get_contents("http://www.bauernregeln.net/i" . $month . ".js");
	$bauernregel = mb_convert_encoding($bauernregel, 'UTF-8', mb_detect_encoding($bauernregel, 'UTF-8, ISO-8859-1', true));
	$bauernregel = str_replace("St. ", "Sankt ", $bauernregel);
	$search = "zit[" . date('j') . "]";
	$posstart = strpos($bauernregel, $search);
	if ($posstart !== FALSE)  {
		$posstart = $posstart + 10;
		$posend = strpos($bauernregel, "\"", $posstart+1);
		$regel = "Die Bauernregel des Tages lautet: " . substr($bauernregel, $posstart, $posend-$posstart);
		echo "<br>Bauernregel: $regel";
		echo '<br>';
		LOGGING('Bauernregel been generated and pushed to T2S creation',7);
		return ($regel);
	}
}


function GetWitz()  {
	$such_start = "<!-- google_ad_section_start -->";
	$such_ende = "<!-- google_ad_section_end -->";
	$witz_html = file_get_contents("http://lustich.de/witze/zufallswitz/");
	$witz_start = stripos($witz_html, $such_start);
	$witz_ende = stripos($witz_html, $such_ende);
	$witz = html_entity_decode(strip_tags(substr($witz_html, $witz_start+strlen($such_start), $witz_ende-$witz_start-strlen($such_ende))));
	$witz = str_replace("\"", "", $witz);
	echo "<br>WITZ: $witz";
	echo '<br>';
	LOGGING('Witz been generated and pushed to T2S creation',7);
	return ($witz);
}
	
?>
