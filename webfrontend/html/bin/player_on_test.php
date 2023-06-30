<?php

require_once "loxberry_system.php";
$configfile	= "s4lox_config.json";							// configuration file


if (isset($_POST['room'])) {
	$datain = $_POST['room'];
} else {
	echo "No incoming value from ajax post. We have to abort :-(')".PHP_EOL;
	exit;
}
#echo "<PRE>";
check_player_state();
	
function check_player_state()    {
	
	global $configfile, $lbpconfigdir, $datain;
	
	# load Player Configuration
	if (file_exists($lbpconfigdir . "/" . $configfile))    {
		$config = json_decode(file_get_contents($lbpconfigdir . "/" . $configfile), TRUE);
	} else {
		echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')".PHP_EOL;
		exit;
	}
	$sonoszonen = ($config['sonoszonen']);
	
	if (array_key_exists($datain, $sonoszonen)) {
		$result = true;
	} else {
		$result = false;
	}
	echo $result;
	#print_r($sonoszonen);
}


?>