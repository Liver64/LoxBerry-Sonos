<?php
/**
 * Sonos4Lox Presence Guard
 * Version: V02.0
 * Language: EN
 *
 * Purpose:
 * - Encapsulate the former Sonos.php presence() and presence_detection() helpers.
 * - Keep legacy behaviour unchanged while removing these helpers from Sonos.php.
 * - Provide one central guard for TTS playback functions.
 */

class S4L_PresenceGuard
{
    public static function setPresenceEnabled($enabled)
    {
        global $lbpconfigdir, $configfile;

        $cfg = self::openConfig();
        if ($cfg === null) {
            self::logWarning('Presence config could not be opened. Presence state has not been changed.');
            return false;
        }

        $cfg->TTS->presence = $enabled ? 'true' : 'false';
        $cfg->write();

        if ($enabled) {
            self::logOk('Presence detection has been turned ON');
        } else {
            self::logOk('Presence detection has been turned OFF');
        }

        return true;
    }

    public static function assertTtsAllowed()
    {
        $presence = self::readPresenceState();

        if ($presence === 'false') {
            self::logInfo('Presence detection is OFF, no TTS has been announced.');
            exit;
        }

        return true;
    }

    private static function readPresenceState()
    {
        global $config;

        $cfg = self::openConfig();
        if ($cfg !== null && isset($cfg->TTS) && isset($cfg->TTS->presence)) {
            return (string)$cfg->TTS->presence;
        }

        if (isset($config['TTS']) && is_array($config['TTS']) && isset($config['TTS']['presence'])) {
            return (string)$config['TTS']['presence'];
        }

        return 'true';
    }

    private static function openConfig()
    {
        global $lbpconfigdir, $configfile;

        if (!class_exists('LBJSON')) {
            @require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_json.php';
        }

        if (!class_exists('LBJSON')) {
            return null;
        }

        if (empty($lbpconfigdir) || empty($configfile)) {
            return null;
        }

        return new LBJSON($lbpconfigdir . '/' . $configfile);
    }

    private static function logOk($message)
    {
        self::log($message, 5);
    }

    private static function logInfo($message)
    {
        self::log($message, 6);
    }

    private static function logWarning($message)
    {
        self::log($message, 4);
    }

    private static function log($message, $level)
    {
        if (!class_exists('S4L_Logger')) {
            $loggerFile = __DIR__ . '/Logger.php';
            if (is_readable($loggerFile)) {
                require_once $loggerFile;
            }
        }

        if (class_exists('S4L_Logger')) {
            S4L_Logger::write($message, $level, __FILE__);
            return;
        }

        $message = 'src/Support/PresenceGuard.php: ' . $message;
        $function = self::nativeLogFunction($level);
        if ($function !== '' && function_exists($function)) {
            $function($message);
            return;
        }

        error_log($message);
    }

    private static function nativeLogFunction($level)
    {
        switch ((int)$level) {
            case 3:
                return 'LOGERR';
            case 4:
                return 'LOGWARN';
            case 5:
                return 'LOGOK';
            case 6:
                return 'LOGINF';
            case 7:
                return 'LOGDEB';
            default:
                return 'LOGDEB';
        }
    }
}
