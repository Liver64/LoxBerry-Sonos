<?php
/**
 * Sonos4Lox Alarm push support class.
 * Version: ALARM_PUSH_CONFIG_CLEANUP_V02_2026_06_15
 *
 * Changes:
 * - Replaced obsolete bin/binlog.php include with src/Support/BinLog.php.
 * - Updated remaining bin/push_alarm.php log context to src/Support/AlarmPush.php.
 * - Removed obsolete LOXONE.LoxDatenMQTT handling. Alarm push now follows the current config.
 * - Avoids Helper.php::checkZoneOnline() global-state warning in cron context.
 */

if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}
require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';
require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_io.php';
require_once $lbphtmldir . '/src/Core/Sonos/sonosAccess.php';
require_once $lbphtmldir . '/Helper.php';
require_once __DIR__ . '/LegacyLogging.php';
require_once $lbphtmldir . '/src/Core/Communication/io-modul.php';
require_once __DIR__ . '/BinLog.php';

class S4L_AlarmPush
{
    public static function run(): void
    {
        global $lbpconfigdir, $lbplogdir, $lbpdatadir;

        echo '<PRE>';

        $alarmOffFile = $lbplogdir . '/s4lox_alarm_off.tmp';
        $offFile = $lbplogdir . '/s4lox_off.tmp';

        if (file_exists($offFile) || file_exists($alarmOffFile)) {
            return;
        }

        $configPath = $lbpconfigdir . '/s4lox_config.json';
        if (!file_exists($configPath)) {
            S4L_BinLog::write('Push data', 'src/Support/AlarmPush.php: The configuration file could not be loaded. Skipping alarm push.');
            exit(1);
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!is_array($config) || !is_enabled($config['LOXONE']['LoxDaten'] ?? false)) {
            return;
        }

        $sonosZones = $config['sonoszonen'] ?? [];
        if (!is_array($sonosZones) || count($sonosZones) === 0) {
            return;
        }

        $onlineZones = self::filterOnlineZones($sonosZones, $lbpdatadir);
        if (count($onlineZones) === 0) {
            S4L_BinLog::write('Push data', 'src/Support/AlarmPush.php: No online Sonos zone found. Skipping alarm push.');
            return;
        }

        $randomZone = array_rand($onlineZones, 1);

        $sonos = new SonosAccess($sonosZones[$randomZone][0]);
        $alarms = $sonos->ListAlarms();
        if (!is_array($alarms) || count($alarms) < 1) {
            return;
        }

        $payload = self::buildPayload($alarms, $sonosZones);
        if (count($payload) === 0) {
            return;
        }

        udp_send_mem($config['LOXONE']['Loxone'] ?? '', $config['LOXONE']['LoxPort'] ?? '', 'Sonos4lox', $payload);
    }

    private static function filterOnlineZones(array $sonosZones, string $lbpdatadir): array
    {
        $onlineZones = [];
        $statusPrefix = rtrim($lbpdatadir, '/') . '/PlayerStatus/s4lox_on_';

        foreach ($sonosZones as $zone => $zoneConfig) {
            if (is_file($statusPrefix . $zone . '.txt')) {
                $onlineZones[$zone] = $zoneConfig;
            }
        }

        return $onlineZones;
    }

    private static function buildPayload(array $alarms, array $sonosZones): array
    {
        $payload = [];
        foreach ($alarms as $alarm) {
            if (empty($alarm['StartTime']) || !isset($alarm['ID'], $alarm['Enabled'], $alarm['RoomUUID'])) {
                continue;
            }

            $timeParts = explode(':', $alarm['StartTime']);
            if (count($timeParts) < 2) {
                continue;
            }

            $minutesPastMidnight = ((intval($timeParts[0]) * 60) + intval($timeParts[1])) - 10;
            $room = recursive_array_search($alarm['RoomUUID'], $sonosZones);
            if ($room === false) {
                $room = 'NO ROOM';
            }

            $payload['min_' . $room . '_ID_' . $alarm['ID']] = $minutesPastMidnight;
            $payload['stat_' . $room . '_ID_' . $alarm['ID']] = $alarm['Enabled'];
        }
        return $payload;
    }
}


if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    S4L_AlarmPush::run();
    exit(0);
}
