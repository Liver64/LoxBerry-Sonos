<?php
/**
* Submodul: logging
*
**/

/**
* Function : logging --> provide interface to LoxBerry logfile
*
* @param: 	empty
* @return: 	log entry
**/

function LOGGING($message = "", $loglevel = 7, $raw = 0)
{
	global $pcfg, $L, $config, $lbplogdir, $logfile, $plugindata;

	if ($plugindata['PLUGINDB_LOGLEVEL'] >= intval($loglevel) || $loglevel == 8)  {
		($raw == 1)?$message="<br>".$message:$message=htmlentities($message);
		switch ($loglevel) 	{
		    case 0:
		        #LOGEMERGE("$message");
		        break;
		    case 1:
		        LOGALERT("$message");
		        break;
		    case 2:
		        LOGCRIT("$message");
		        break;
			case 3:
		        LOGERR("$message");
		        break;
			case 4:
				LOGWARN("$message");
		        break;
			case 5:
				LOGOK("$message");
		        break;
			case 6:
				LOGINF("$message");
		        break;
			case 7:
				LOGDEB("$message");
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
	
	if (!is_file(LBPLOGDIR."/sonos.log"))   {
		fopen(LBPLOGDIR."/sonos.log", "w");
	} else {
		$logsize = filesize(LBPLOGDIR."/sonos.log");
		if ( $logsize > 5242880 )  {
			LOGGING($L["ERRORS.ERROR_LOGFILE_TOO_BIG"]." (".$logsize." Bytes)",4);
			LOGGING("Set Logfile notification: ".LBPPLUGINDIR." ".$L['BASIS.MAIN_TITLE']." => ".$L['ERRORS.ERROR_LOGFILE_TOO_BIG'],7);
			notify (LBPPLUGINDIR, $L['BASIS.MAIN_TITLE'], $L['ERRORS.ERROR_LOGFILE_TOO_BIG']);
			system("echo '' > ".LBPLOGDIR."/sonos.log");
		}
		return;
	}
}
?>
