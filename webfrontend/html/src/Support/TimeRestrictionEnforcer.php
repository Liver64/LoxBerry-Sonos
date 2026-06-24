<?php
/**
 * Sonos4Lox CronTaskFinalization V03
 * Time restriction enforcement support class. Can be called directly from cron.
 */
if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}
require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_web.php';
require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';
require_once $lbphtmldir . '/src/Core/Sonos/sonosAccess.php';
require_once $lbphtmldir . '/Helper.php';
require_once $lbphtmldir . '/Grouping.php';


class S4L_TimeRestrictionEnforcer
{
    private static $timeStart = 0.0;

    public static function run(): void
    {
        global $lbpconfigdir, $lbplogdir, $lbpdatadir, $lbpplugindir, $sonoszonen, $folfilePlOn;

        $configFileName = 's4lox_config.json';
        $offFile = $lbplogdir . '/s4lox_off.tmp';
        $folfilePlOn = $lbpdatadir . '/PlayerStatus/s4lox_on_';
        $actionLogFile = $lbplogdir . '/sonos.log';
        $actionLogStateFile = '/run/shm/' . $lbpplugindir . '/s4lox_timecheck_last_action_log.json';

        echo '<PRE>';

        if (file_exists($offFile)) {
            return;
        }

        self::$timeStart = microtime(true);
        register_shutdown_function([__CLASS__, 'shutdown']);

        $configPath = $lbpconfigdir . '/' . $configFileName;
        if (!file_exists($configPath)) {
            echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')" . PHP_EOL;
            return;
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!is_array($config) || empty($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
            echo "The configuration file could not be loaded, the file may be disrupted. We have to abort :-(')" . PHP_EOL;
            return;
        }

        $sonoszonen = $config['sonoszonen'];
        if (!self::hasTimeRestrictions($sonoszonen)) {
            echo 'No time restrictions are entered. We abort here.' . PHP_EOL;
            return;
        }

        $sonoszone = sonoszonen_on();
        if (!is_array($sonoszone) || count($sonoszone) === 0) {
            echo 'No active Player determined. We abort here.' . PHP_EOL;
            return;
        }

        $restrictedOnlineZones = self::getRestrictedOnlineZones($sonoszonen, $sonoszone, $folfilePlOn);
        $messages = self::processRestrictedZones($restrictedOnlineZones);
        self::logActionsHourly($messages, $actionLogFile, $actionLogStateFile);
    }

    private static function hasTimeRestrictions(array $zones): bool
    {
        foreach ($zones as $data) {
            if (isset($data[15], $data[16]) && $data[15] !== '' && $data[16] !== '') {
                return true;
            }
        }
        return false;
    }

    private static function getRestrictedOnlineZones(array $allZones, array $allowedZones, string $onlinePrefix): array
    {
        $diff = @array_diff_assoc($allZones, $allowedZones);
        $restricted = [];
        foreach ($diff as $zone => $data) {
            if (is_file($onlinePrefix . $zone . '.txt')) {
                $restricted[$zone] = $data;
            }
        }
        return $restricted;
    }

    private static function processRestrictedZones(array $zones): array
    {
        $messages = [];
        foreach ($zones as $zone => $data) {
            try {
                $sonos = new SonosAccess($data[0]);
                $transportInfo = $sonos->GetTransportInfo();
                if ($transportInfo != '1') {
                    continue;
                }

                $groupAttributes = $sonos->GetZoneGroupAttributes();
                $groupName = isset($groupAttributes['CurrentZoneGroupName']) ? $groupAttributes['CurrentZoneGroupName'] : '';
                $groupPlayers = isset($groupAttributes['CurrentZonePlayerUUIDsInGroup'])
                    ? explode(',', $groupAttributes['CurrentZonePlayerUUIDsInGroup'])
                    : [];

                if ($groupName === '') {
                    $sonos->BecomeCoordinatorOfStandaloneGroup();
                    $messages[] = "Player '" . $zone . "' has been removed from Group because it is outside the allowed time range (was Member)";
                } elseif ($groupName !== '' && count($groupPlayers) > 1) {
                    $sonos->BecomeCoordinatorOfStandaloneGroup();
                    $messages[] = "Player '" . $zone . "' has been removed from Group because it is outside the allowed time range (was Master)";
                } elseif ($groupName !== '' && count($groupPlayers) < 2) {
                    $sonos->Stop();
                    $messages[] = "Player '" . $zone . "' has been stopped streaming because it is outside the allowed time range (was Single)";
                } else {
                    echo "Unknown status of Player '" . $zone . "'. Please check" . PHP_EOL;
                }
            } catch (Exception $e) {
                // Zone is probably offline or not reachable. Keep silent to avoid log spam.
            }
        }
        foreach ($messages as $message) {
            echo $message . PHP_EOL;
        }
        return $messages;
    }

    private static function logActionsHourly(array $messages, string $logFile, string $stateFile): void
    {
        if (count($messages) === 0) {
            return;
        }

        $now = time();
        $lastLog = 0;
        if (is_file($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true);
            if (is_array($state) && isset($state['last_log'])) {
                $lastLog = intval($state['last_log']);
            }
        }

        if (($now - $lastLog) < 3600) {
            return;
        }

        $log = LBLog::newLog([
            'name' => 'Sonos time restriction check',
            'filename' => $logFile,
            'append' => 1,
            'stderr' => 0,
            'addtime' => 1,
        ]);

        LOGSTART('Sonos time restriction check');
        foreach ($messages as $message) {
            LOGWARN($message);
        }
        LOGEND('Sonos time restriction check finished');

        self::notify('Sonos time restriction action performed:' . PHP_EOL . implode(PHP_EOL, $messages), 'warning');
        @mkdir(dirname($stateFile), 0775, true);
        file_put_contents($stateFile, json_encode(['last_log' => $now, 'iso_time' => date('c')], JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function notify(string $message, string $type = 'info'): void
    {
        if (!function_exists('notify')) {
            return;
        }

        if ($type === 'info' || $type === '') {
            notify(LBPPLUGINDIR, 'Sonos', $message);
        } else {
            notify(LBPPLUGINDIR, 'Sonos', $message, $type);
        }
    }

    public static function shutdown(): void
    {
        $processTime = microtime(true) - self::$timeStart;
        echo 'Timecheck took ' . round($processTime, 2) . ' seconds to process' . PHP_EOL;
    }
}


if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    S4L_TimeRestrictionEnforcer::run();
    exit(0);
}
