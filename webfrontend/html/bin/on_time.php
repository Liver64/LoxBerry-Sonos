<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "$lbphtmldir/system/sonosAccess.php";
require_once "$lbphtmldir/Helper.php";
require_once "$lbphtmldir/Grouping.php";

$configfile			= "s4lox_config.json";							// configuration file
$off_file 			= "$lbplogdir/s4lox_off.tmp";					// path/file for Script turned off
$folfilePlOn 		= "$lbpdatadir/PlayerStatus/s4lox_on_";			// Folder and file name for Player Status

echo "<PRE>";

global $sonoszonen, $folfilePlOn; 
	
# check if script/Sonos Plugin is off
if (file_exists($off_file)) {
	exit;
}

$time_start = microtime(true);
register_shutdown_function('shutdown');

# load Player Configuration
if (file_exists($lbpconfigdir . "/" . $configfile))    {
	$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
} else {
	echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
	exit;
}
$sonoszonen = ($config['sonoszonen']);
#print_r($sonoszonen);

# check if time restrictions maintained
$i = 0;
foreach ($sonoszonen as $zone => $data)    {
	if ($data[15] != "" and $data[16] != "")    {
		$i++;
	}
}
#echo "Count Time entries: ".$i;
if ($i === 0)   {	
	echo "No time restrictions are entered. We abort here.".PHP_EOL;
	exit;
}
# get active zones
$sonoszone = sonoszonen_on();

# skip if no active zones
if (count($sonoszone) === 0)   {
	echo "No active Player determined. We abort here.".PHP_EOL;
	exit;
}
# get zones to be excluded from streaming
$diff_array = @array_diff_assoc($sonoszonen, $sonoszone);

# check again if delta zones are Online
$deltazones = array();
foreach($diff_array as $zonen => $ip) {
	$handle = is_file($folfilePlOn."".$zonen.".txt");
	if($handle === true) {
		$deltazones[$zonen] = $ip;
	}
}		

foreach ($deltazones as $zone => $data)    {
	try {
		$sonos = new SonosAccess($diff_array[$zone][0]);
		$gti = $sonos->GetTransportInfo();
		# check if Zone is actual streaming
		if ($gti == "1")   {
			try {
				$group = $sonos->GetZoneGroupAttributes();
				$tmp_name = $group["CurrentZoneGroupName"];
				$group = explode(',', $group["CurrentZonePlayerUUIDsInGroup"]);
				if(empty($tmp_name)) {
					# Member
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					echo "Player '".$zone."' has been removed from Group (was Member)".PHP_EOL;
				} 
				elseif(!empty($tmp_name) && count($group) > 1) {
					# Master
					#array_shift($group);
					#$sonos->DelegateGroupCoordinationTo($group[0], 1);
					#sleep(5);
					$sonos->BecomeCoordinatorOfStandaloneGroup();
					echo "Player '".$zone."' has been removed from Group (was Master)".PHP_EOL;
				}
				elseif(!empty($tmp_name) && count($group) < 2) {
					# Single
					$sonos->Stop();
					echo "Player '".$zone."' has been stopped streaming (was Single)".PHP_EOL;
				}
				else {
					# unknown
					echo "unknown Status of Player '".$zone."'. Please check".PHP_EOL;
				}
			} catch (Exception $e) {
				echo "unexpected error for Player '".$zone."' occured".PHP_EOL;
			}	
		}				
	} catch (Exception $e) {
		#echo $zone." seems to be Offline, nothing to do".PHP_EOL;
	}	
}


function shutdown()   {
	
	global $time_start;
	
	$time_end = microtime(true);
	$process_time = $time_end - $time_start;
	echo "Timecheck tooks ".round($process_time, 2)." seconds to be processed".PHP_EOL;
}
?>