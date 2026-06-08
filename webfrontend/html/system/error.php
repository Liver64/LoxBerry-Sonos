<?php
// http://php.net/manual/de/function.set-error-handler.php#118630

/**
* Used for logging all php notices,warings and etc in a file when error reporting
* is set and display_errors is off
* @uses used in prod env for logging all type of error of php code in a file for further debugging
* and code performance
* @author Aditya Mehrotra<aditycse@gmail.com>

* Custom error handler
* @param integer $code
* @param string $description
* @param string $file
* @param interger $line
* @param mixed $context
* @return boolean
*/


function handleError($code, $description, $file = null, $line = null, $context = null) {

    /*
     * Respect current error_reporting().
     * Example: error_reporting(E_ALL & ~E_NOTICE) must really suppress notices.
     */
    if ((error_reporting() & $code) === 0) {
        return false;
    }

    $displayErrors = strtolower((string) ini_get("display_errors"));
    if ($displayErrors === "on" || $displayErrors === "1") {
        return false;
    }

    list($error, $log) = mapErrorCode($code);

    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    /*
     * Compact PHP error information.
     * IMPORTANT: Do NOT log $context.
     */
    $logData = array(
        'level'       => $log,
        'code'        => $code,
        'error'       => $error,
        'description' => $description,
        'file'        => $file,
        'line'        => $line
    );

    if ($requestUri !== '') {
        $logData['request'] = $requestUri;
    }

    if ($remoteAddr !== '') {
        $logData['remote'] = $remoteAddr;
    }

    $message  = date("H:i:s") . " <ERROR> PHP error detected:\n";
    $message .= print_r($logData, true);

    return fileLog($message);
}

/**
* This method is used to write data in file
* @param mixed $logData
* @param string $fileName
* @return boolean
*/

function fileLog($logData, $fileName = null) {

    if ($fileName === null || $fileName === '') {
        if (defined('ERROR_LOG_FILE')) {
            $fileName = ERROR_LOG_FILE;
        } else {
            return false;
        }
    }

    if (is_array($logData)) {
        $logData = print_r($logData, true);
    }

    $fh = fopen($fileName, 'a');
    if (!$fh) {
        return false;
    }

    $status = fwrite($fh, $logData);
    fclose($fh);

    return ($status !== false);
}

/**
* Map an error code into an Error word, and log location.
*
* @param int $code Error code to map
* @return array Array of error word, and log location.
*/
function mapErrorCode($code) {
	global $data;
	
    $error = $log = null;
    switch ($code) {
        case E_PARSE:
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            $error = 'Fatal Error';
            $log = LOG_ERR;
            break;
        case E_WARNING:
        case E_USER_WARNING:
			$error = 'Warning';
            $log = LOG_WARNING;
            break;
        case E_COMPILE_WARNING:
        case E_RECOVERABLE_ERROR:
            $error = 'Warning';
            $log = LOG_WARNING;
            break;
        case E_NOTICE:
			$error = 'Notice';
            $log = LOG_NOTICE;
            break;
        case E_USER_NOTICE:
            $error = 'Notice';
            $log = LOG_NOTICE;
            break;
        case E_STRICT:
            $error = 'Strict';
            $log = LOG_NOTICE;
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $error = 'Deprecated';
            $log = LOG_NOTICE;
            break;
        default :
            break;
    }
    return array($error, $log);
}





?>