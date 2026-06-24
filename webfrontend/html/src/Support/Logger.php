<?php
/**
 * Sonos4Lox Logger Wrapper
 * Version: V06_2026_06_19
 * Language: EN
 *
 * Purpose:
 * - Provide one stable logging interface for the refactored code.
 * - Use the LoxBerry PHP SDK logging stack whenever available.
 * - Prefer the active LoxBerry LBLog object / native LoxBerry LOG* functions
 *   before falling back to PHP error_log().
 * - This keeps LOGDEB/LOGWARN/LOGERR lines classified correctly in the
 *   LoxBerry Log Viewer.
 * - Prefix every refactored log message with the relative source file path.
 * - Avoid custom file writers in the refactored code.
 *
 * Log message rule:
 * - Every message written by this wrapper starts with a relative path/file context.
 * - Example: src/Actions/PlaybackActions.php: Play has been executed.
 *
 * LoxBerry SDK:
 * - loxberry_log.php is expected to be loaded by Sonos.php before the router runs.
 * - If it is not loaded yet, this wrapper tries to load it without an absolute path.
 */

class S4L_Logger
{
    const LEVEL_ERROR   = 3;
    const LEVEL_WARNING = 4;
    const LEVEL_OK      = 5;
    const LEVEL_INFO    = 6;
    const LEVEL_DEBUG   = 7;

    public static function debug($message)
    {
        self::write($message, self::LEVEL_DEBUG);
    }

    public static function info($message)
    {
        self::write($message, self::LEVEL_INFO);
    }

    public static function ok($message)
    {
        self::write($message, self::LEVEL_OK);
    }

    public static function warning($message)
    {
        self::write($message, self::LEVEL_WARNING);
    }

    public static function error($message)
    {
        self::write($message, self::LEVEL_ERROR);
    }

    public static function write($message, $level = self::LEVEL_DEBUG, $sourceFile = null)
    {
        $message = self::formatMessage($message, $sourceFile);
        $level = (int)$level;

        self::ensureLoxBerryLogLoaded();

        /*
         * Prefer the active LBLog object and native LoxBerry LOG* functions.
         * If neither is available, fall back to PHP error_log().
         */
        if (self::callLoxBerryObject($message, $level)) {
            return;
        }

        if (self::callLoxBerryFunction($message, $level)) {
            return;
        }

        error_log($message);
    }

    private static function formatMessage($message, $sourceFile = null)
    {
        $message = trim((string)$message);
        $source = self::relativeSourceFile($sourceFile);

        if ($source === '') {
            $source = self::detectCallerFile();
        }

        if ($source === '') {
            return $message;
        }

        if (strpos($message, $source . ':') === 0) {
            return $message;
        }

        return $source . ': ' . $message;
    }

    private static function detectCallerFile()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($trace as $frame) {
            if (empty($frame['file'])) {
                continue;
            }

            $file = str_replace('\\', '/', $frame['file']);

            if (basename($file) === 'Logger.php') {
                continue;
            }

            return self::relativeSourceFile($file);
        }

        return '';
    }

    private static function relativeSourceFile($file)
    {
        if ($file === null || $file === '') {
            return '';
        }

        $file = str_replace('\\', '/', (string)$file);
        $htmlRoot = str_replace('\\', '/', dirname(__DIR__, 2));

        if (strpos($file, $htmlRoot . '/') === 0) {
            return substr($file, strlen($htmlRoot) + 1);
        }

        $marker = '/webfrontend/html/';
        $pos = strpos($file, $marker);
        if ($pos !== false) {
            return substr($file, $pos + strlen($marker));
        }

        $srcMarker = '/src/';
        $pos = strpos($file, $srcMarker);
        if ($pos !== false) {
            return 'src/' . substr($file, $pos + strlen($srcMarker));
        }

        return basename($file);
    }

    private static function ensureLoxBerryLogLoaded()
    {
        if (class_exists('LBLog') || function_exists('LOGINF')) {
            return;
        }

        @require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';
    }

    private static function callLoxBerryFunction($message, $level)
    {
        $function = self::functionForLevel($level);

        if ($function !== '' && function_exists($function)) {
            $function($message);
            return true;
        }

        return false;
    }

    private static function callLoxBerryObject($message, $level)
    {
        global $log;

        if (!is_object($log)) {
            return false;
        }

        $method = self::methodForLevel($level);

        if ($method !== '' && method_exists($log, $method)) {
            $log->{$method}($message);
            return true;
        }

        return false;
    }

    private static function functionForLevel($level)
    {
        switch ((int)$level) {
            case self::LEVEL_ERROR:
                return 'LOGERR';
            case self::LEVEL_WARNING:
                return 'LOGWARN';
            case self::LEVEL_OK:
                return 'LOGOK';
            case self::LEVEL_INFO:
                return 'LOGINF';
            case self::LEVEL_DEBUG:
                return 'LOGDEB';
            default:
                return 'LOGDEB';
        }
    }

    private static function methodForLevel($level)
    {
        switch ((int)$level) {
            case self::LEVEL_ERROR:
                return 'LOGERR';
            case self::LEVEL_WARNING:
                return 'LOGWARN';
            case self::LEVEL_OK:
                return 'LOGOK';
            case self::LEVEL_INFO:
                return 'LOGINF';
            case self::LEVEL_DEBUG:
                return 'LOGDEB';
            default:
                return 'LOGDEB';
        }
    }
}
