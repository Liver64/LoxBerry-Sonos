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
#echo '<PRE>';	

function saveZonesStatus() {
	
	global $sonoszone, $member, $config, $master, $sonos, $player, $actual, $time_start;

	// save each Zone Status
	foreach ($sonoszone as $player => $value) {
		$sonos = new SonosAccess($sonoszone[$player][0]); 
		$actual[$player]['Mute'] = $sonos->GetMute($player);
		$actual[$player]['Volume'] = $sonos->GetVolume($player);
		$actual[$player]['MediaInfo'] = $sonos->GetMediaInfo($player);
		$actual[$player]['PositionInfo'] = $sonos->GetPositionInfo($player);
		$actual[$player]['TransportInfo'] = $sonos->GetTransportInfo($player);
		$actual[$player]['TransportSettings'] = $sonos->GetTransportSettings($player);
		$actual[$player]['Coordinator'] = $master;
		$actual[$player]['Grouping'] = getGroup($player);
		$zonestatus = getZoneStatus($player);
		$actual[$player]['ZoneStatus'] = $zonestatus;
		$posinfo = $actual[$player]['PositionInfo'];
		$media = $actual[$player]['MediaInfo'];
		if ($zonestatus != "member")    {
			if (substr($posinfo["TrackURI"], 0, 18) == "x-sonos-htastream:")  {
				$actual[$player]['Type'] = "TV";
			} elseif (substr($actual[$player]['MediaInfo']['UpnpClass'] ,0 ,36) == "object.item.audioItem.audioBroadcast")  {
				$actual[$player]['Type'] = "Radio";
			} elseif (substr($posinfo["TrackURI"], 0, 15) == "x-rincon-stream")   {
				$actual[$player]['Type'] = "LineIn";
			} elseif (empty($posinfo["CurrentURIMetaData"]))   {
				$actual[$player]['Type'] = "Nothing";
			} else {
				$actual[$player]['Type'] = "Track";
			}
		}
	}
	#print_r($actual);
	LOGGING("save_t2s.php: All Zone settings has been saved successful",5);
	return $actual;
}
?>