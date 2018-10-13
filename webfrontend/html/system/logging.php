<?php
/**
* Submodul: debug
*
**/

/**
* Function : debug --> provide interface to LoxBerry logfile
*
* @param: 	empty
* @return: 	log entry
**/

$params = [
		"name" => "Sonos",
		"filename" => "$lbplogdir/sonos.log",
		"append" => 1,
		];
$log_sonos = LBLog::newLog($params);	
LOGSTART "Sonos";

$plugindata = LBSystem::plugindata();
	
function LOGGING($message = "", $loglevel = 7, $raw = 0)
{
	global $log_sonos, $plugindata, $pcfg, $L, $config, $lbplogdir, $logfile;

	#echo $plugindata['PLUGINDB_LOGLEVEL'];	
	#$config_loglevel = $config['SYSTEM']['LOGLEVEL'];
	#if (empty($config_loglevel)) {
	#	$config_loglevel = 7;
	#}
	if ($plugindata['PLUGINDB_LOGLEVEL'] >= intval($loglevel) || $loglevel == 8)  {
		($raw == 1)?$message="<br>".$message:$message=htmlentities($message);
		switch ($loglevel) 	{
		    case 0:
		        #LOGEMERGE("$message");
		        break;
		    case 1:
		        $log_sonos->LOGALERT("$message");
		        break;
		    case 2:
		        $log_sonos->LOGCRIT("$message");
		        break;
			case 3:
		        $log_sonos->LOGERR("$message");
		        break;
			case 4:
				$log_sonos->LOGWARN("$message");
		        break;
			case 5:
				$log_sonos->LOGOK("$message");
		        break;
			case 6:
				$log_sonos->LOGINF("$message");
		        break;
			case 7:
				$log_sonos->LOGDEB("$message");
			default:
		        break;
		}
		if ($loglevel < 4) {
			if (isset($message) && $message != "" ) notify (LBPPLUGINDIR, $L['BASIS.MAIN_TITLE'], $message);
		}
	}
	return;
}


/**
* Function : check_size_logfile --> check size of LoxBerry logfile
*
* @param: 	empty
* @return: 	empty
**/
function check_size_logfile()  {
	global $L;
	
	$logsize = filesize(LBPLOGDIR."/sonos.log");
	if ( $logsize > 5242880 )
	{
		LOGGING($L["ERRORS.ERROR_LOGFILE_TOO_BIG"]." (".$logsize." Bytes)",4);
		LOGGING("Set Logfile notification: ".LBPPLUGINDIR." ".$L['BASIS.MAIN_TITLE']." => ".$L['ERRORS.ERROR_LOGFILE_TOO_BIG'],7);
		notify (LBPPLUGINDIR, $L['BASIS.MAIN_TITLE'], $L['ERRORS.ERROR_LOGFILE_TOO_BIG']);
		system("echo '' > ".LBPLOGDIR."/sonos.log");
	}
	return;
}
?>
