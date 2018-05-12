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

function LOGGING($message = "", $loglevel, $raw = 0)
{
	global $pcfg, $L, $config;
	
	$config_loglevel = $config['SYSTEM']['LOGLEVEL'];
	if (empty($config_loglevel)) {
		$config_loglevel = 7;
	}
	if (intval($config_loglevel) >= intval($loglevel) )
	{
		($raw == 1)?$message="<br>".$message:$message=htmlentities($message);
		switch ($loglevel)
		{
		    case 2:
		        error_log( "<CRITICAL> PHP-> ".$message );
		        break;
		    case 3:
		        error_log( "<ERROR> PHP-> ".$message );
		        break;
		    case 4:
		        error_log( "<WARNING> PHP-> ".$message );
		        break;
			case 5:
		        error_log( "<INFO> PHP-> ".$message );
		        break;
			case 6:
				error_log( "<OK> PHP-> ".$message );
		        break;
			case 7:
		    default:
		        error_log( "PHP-> ".$message );
		        break;
		}
		if ( $loglevel < 4 ) 
		{
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
	
	$logsize = @filesize(LBPLOGDIR."/sonos.log");
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
