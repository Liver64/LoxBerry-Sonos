<?php

/**
* Submodul: Save_T2S
*
**/

/**
* Function : saveZonesStatus --> saves current details for each Zone
*
* @param: 	empty
* @return: 	array
**/
	
function saveZonesStatus() {
	global $sonoszone, $config, $sonos, $player, $actual, $time_start;
	
	// save each Zone Status
	foreach ($sonoszone as $player => $value) {
		$sonos = new PHPSonos($config['sonoszonen'][$player][0]); 
		$actual[$player]['Mute'] = $sonos->GetMute($player);
		$actual[$player]['Volume'] = $sonos->GetVolume($player);
		$actual[$player]['MediaInfo'] = $sonos->GetMediaInfo($player);
		$actual[$player]['PositionInfo'] = $sonos->GetPositionInfo($player);
		$actual[$player]['TransportInfo'] = $sonos->GetTransportInfo($player);
		$actual[$player]['TransportSettings'] = $sonos->GetTransportSettings($player);
		$actual[$player]['Group-ID'] = $sonos->GetZoneGroupAttributes($player);
		$actual[$player]['Grouping'] = getGroup($player);
		$actual[$player]['ZoneStatus'] = getZoneStatus($player);
		$actual[$player]['CONNECT'] = GetVolumeModeConnect($player);
	}
	#print_r($actual);
	return $actual;
}
?>