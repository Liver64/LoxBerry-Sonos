<?php
/**
 * Sonos4Lox Battery Monitor
 * Version: BATTERY_FINALIZATION_V01_2026_06_10
 * Language: EN
 *
 * Purpose:
 * - Check Sonos battery powered devices and log low battery warnings.
 * - Can be called directly from cron via src/Support/BatteryMonitor.php.
 * - Can also be called from the refactored DeviceActions battery action.
 *
 * Notes:
 * - No legacy bin/battery.php wrapper is required anymore.
 * - Log messages start with the relative file context.
 */

if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}
require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';

class S4L_BatteryMonitor
{
    private static $logStarted = false;

    public static function run(): int
    {
        global $lbpconfigdir, $lbplogdir, $lbpdatadir;

        $offFile = rtrim((string)$lbplogdir, '/') . '/s4lox_off.tmp';
        if (file_exists($offFile)) {
            return 0;
        }

        $hour = (int)strftime('%H');
        if ($hour < 8 || $hour >= 22) {
            return 0;
        }

        ini_set('max_execution_time', '30');
        register_shutdown_function([__CLASS__, 'shutdown']);

        $configFile = rtrim((string)$lbpconfigdir, '/') . '/s4lox_config.json';
        if (!file_exists($configFile)) {
            self::startLog();
            self::logError('Configuration file could not be loaded. Battery check aborted.');
            return 1;
        }

        $config = json_decode((string)file_get_contents($configFile), true);
        if (!is_array($config) || empty($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
            self::startLog();
            self::logError('Configuration file could not be parsed or does not contain sonoszonen. Battery check aborted.');
            return 1;
        }

        $onlinePrefix = rtrim((string)$lbpdatadir, '/') . '/PlayerStatus/s4lox_on_';
        foreach ($config['sonoszonen'] as $zone => $player) {
            if (!is_file($onlinePrefix . $zone . '.txt')) {
                continue;
            }

            $ip = is_array($player) && isset($player[0]) ? trim((string)$player[0]) : '';
            if ($ip === '') {
                continue;
            }

            $battery = self::readBatteryStatus($ip);
            if ($battery === null) {
                continue;
            }

            if ($battery['power_source'] === 'BATTERY' && $battery['level'] <= 20) {
                self::startLog();
                self::logWarning('Battery level of "' . $zone . '" is about ' . $battery['level'] . '%. Please charge your device.');
            }
        }

        return 0;
    }

    private static function readBatteryStatus(string $ip): ?array
    {
        $url = 'http://' . $ip . ':1400/status/batterystatus';
        $xml = @simplexml_load_file($url);
        if ($xml === false || !isset($xml->LocalBatteryStatus->Data)) {
            return null;
        }

        $level = isset($xml->LocalBatteryStatus->Data[1]) ? (int)$xml->LocalBatteryStatus->Data[1] : null;
        $powerSource = isset($xml->LocalBatteryStatus->Data[3]) ? (string)$xml->LocalBatteryStatus->Data[3] : '';

        if ($level === null || $powerSource === '') {
            return null;
        }

        return [
            'level' => $level,
            'power_source' => $powerSource,
        ];
    }

    private static function startLog(): void
    {
        global $lbplogdir;

        if (self::$logStarted) {
            return;
        }

        LBLog::newLog([
            'name' => 'Cronjobs',
            'filename' => rtrim((string)$lbplogdir, '/') . '/sonos.log',
            'append' => 1,
            'stderr' => 1,
            'addtime' => 1,
        ]);

        if (function_exists('LOGSTART')) {
            LOGSTART('Check Battery state');
        }

        self::$logStarted = true;
    }

    private static function logWarning(string $message): void
    {
        $message = 'src/Support/BatteryMonitor.php: ' . $message;
        if (function_exists('LOGWARN')) {
            LOGWARN($message);
            return;
        }
        error_log($message);
    }

    private static function logError(string $message): void
    {
        $message = 'src/Support/BatteryMonitor.php: ' . $message;
        if (function_exists('LOGERR')) {
            LOGERR($message);
            return;
        }
        error_log($message);
    }

    public static function shutdown(): void
    {
        if (self::$logStarted && function_exists('LOGEND')) {
            LOGEND('Battery check finished');
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(S4L_BatteryMonitor::run());
}
