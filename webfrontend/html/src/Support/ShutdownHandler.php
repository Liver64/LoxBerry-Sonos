<?php
/**
 * Sonos4Lox Shutdown Handler
 * Version: SHUTDOWN_HANDLER_REMOVE_DEBUGINFO_V02_2026_06_18
 * Language: EN
 *
 * Purpose:
 * - Keep Sonos.php as a slim public entry point.
 * - Move request shutdown/fallback handling out of the legacy entry script.
 * - Preserve the existing T2S status fallback and request timing finalization.
 *
 * Notes:
 * - File names are intentionally not versioned.
 * - Versioning is documented only in this file header.
 * - Every log message starts with the relative file context.
 * - This src file uses S4L_Logger when available and avoids the legacy logging helper.
 */

class S4L_ShutdownHandler
{
    private static $registered = false;

    public static function register()
    {
        if (self::$registered) {
            return;
        }

        register_shutdown_function(array('S4L_ShutdownHandler', 'handle'));
        self::$registered = true;
    }

    public static function handle()
    {
        global $tts_stat, $duration, $tmp_tts;

        /*
         * Fallback: reset the virtual TTS status input if a request ended while
         * the old TTS status flag was still active.
         */
        if (isset($tts_stat) && (int)$tts_stat === 1) {
            $tts_stat = 0;
            if (function_exists('send_tts_source')) {
                send_tts_source($tts_stat);
            }
        }

        if (isset($_GET['debug'])) {
            $start = isset($GLOBALS['time_start']) ? (float)$GLOBALS['time_start'] : microtime(true);
            $elapsed = microtime(true) - $start;
            $processTime = isset($duration) ? $elapsed - ((float)$duration / 1000000) : $elapsed;

            if ($processTime < 0) {
                $processTime = $elapsed;
            }

            self::log('Processing request took about ' . round($processTime, 3) . ' seconds.', 6);
        }

        if (function_exists('LOGEND')) {
            LOGEND('PHP finished');
        }

        if (isset($tmp_tts) && $tmp_tts !== '') {
            self::removeTemporaryTtsFile($tmp_tts);
        }
    }

    private static function log($message, $level = 7)
    {
        $message = 'src/Support/ShutdownHandler.php: ' . $message;

        if (class_exists('S4L_Logger')) {
            S4L_Logger::write($message, $level);
            return;
        }

        error_log($message);
    }

    private static function removeTemporaryTtsFile($tmpTts)
    {
        $tmpTts = (string)$tmpTts;

        if ($tmpTts === '' || !file_exists($tmpTts)) {
            return;
        }

        if (!is_file($tmpTts)) {
            self::log('Temporary TTS cleanup skipped because path is not a file: ' . $tmpTts, 4);
            return;
        }

        if (!unlink($tmpTts)) {
            self::log('Temporary TTS file could not be removed: ' . $tmpTts, 4);
        }
    }
}
