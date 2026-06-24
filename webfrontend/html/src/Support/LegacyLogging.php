<?php


header('Content-Type: text/html; charset=utf-8');

/**
 * Sonos4Lox legacy logging compatibility layer.
 * Version: LEGACY_LOGGING_RELOCATION_V02_2026_06_19
 *
 * Relocated from system/logging.php.
 *
 * This file intentionally keeps the legacy global logging compatibility functions
 * for legacy files that have not yet been migrated to src/Support/Logger.php.
 */

/**
* Function : logging --> provide interface to LoxBerry logfile
*
* @param: 	empty
* @return: 	log entry
**/

function LOGGING($message = "", $loglevel = 7, $raw = 0)
{
        global $pcfg, $L, $config, $lbplogdir, $logfile, $plugindata, $level, $log;

        if ($level >= intval($loglevel) || $loglevel == 8)  {

                ($raw == 1) ? $message = "<br>" . $message : $message = htmlentities($message);

                /*
                 * Prefer the active LoxBerry LBLog object.
                 * This keeps log lines classified correctly for the LoxBerry Log Viewer
                 * instead of writing DEBUG/INFO/WARN lines as unclassified plain text.
                 */
                if (is_object($log)) {
                        switch ($loglevel) {
                                case 1:
                                        if (method_exists($log, 'LOGALERT')) {
                                                $log->LOGALERT("$message");
                                                break;
                                        }
                                        LOGALERT("$message");
                                        break;

                                case 2:
                                        if (method_exists($log, 'LOGCRIT')) {
                                                $log->LOGCRIT("$message");
                                                break;
                                        }
                                        LOGCRIT("$message");
                                        break;

                                case 3:
                                        if (method_exists($log, 'LOGERR')) {
                                                $log->LOGERR("$message");
                                                break;
                                        }
                                        LOGERR("$message");
                                        break;

                                case 4:
                                        if (method_exists($log, 'LOGWARN')) {
                                                $log->LOGWARN("$message");
                                                break;
                                        }
                                        LOGWARN("$message");
                                        break;

                                case 5:
                                        if (method_exists($log, 'LOGOK')) {
                                                $log->LOGOK("$message");
                                                break;
                                        }
                                        LOGOK("$message");
                                        break;

                                case 6:
                                        if (method_exists($log, 'LOGINF')) {
                                                $log->LOGINF("$message");
                                                break;
                                        }
                                        LOGINF("$message");
                                        break;

                                case 7:
                                        if (method_exists($log, 'LOGDEB')) {
                                                $log->LOGDEB("$message");
                                                break;
                                        }
                                        LOGDEB("$message");
                                        break;

                                case 8:
                                        if (method_exists($log, 'LOGDEB')) {
                                                $log->LOGDEB("$message");
                                                break;
                                        }
                                        LOGDEB("$message");
                                        break;

                                case 0:
                                default:
                                        break;
                        }
                } else {
                        /*
                         * Fallback for legacy contexts without an active LBLog object.
                         */
                        switch ($loglevel)      {
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
                                        break;
                                case 8:
                                        LOGDEB("$message");
                                        break;
                                default:
                                        break;
                        }
                }

                if ($loglevel < 4) {
                        if (isset($message) && $message != "" ) {
                                notify(LBPPLUGINDIR, $L['BASIS.MAIN_TITLE'], $message);
                        }
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
			LOGWARN($L["ERRORS.ERROR_LOGFILE_TOO_BIG"]." (".$logsize." Bytes)");
			LOGDEB("Set Logfile notification: ".LBPPLUGINDIR." ".$L['BASIS.MAIN_TITLE']." => ".$L['ERRORS.ERROR_LOGFILE_TOO_BIG']);
			notify (LBPPLUGINDIR, $L['BASIS.MAIN_TITLE'], $L['ERRORS.ERROR_LOGFILE_TOO_BIG']);
			system("echo '' > ".LBPLOGDIR."/sonos.log");
		}
		return;
	}
}
?>
