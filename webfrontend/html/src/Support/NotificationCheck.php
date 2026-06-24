<?php
/**
 * Sonos4Lox notification support helper.
 * Version: NOTIFICATION_FINALIZATION_V01_2026_06_10
 */

if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}

require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_web.php';
require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';

class S4L_NotificationCheck
{
    /**
     * Create an upgrade/config notification if no main player is marked.
     *
     * @return int Exit code.
     */
    public static function run(): int
    {
        global $lbpconfigdir, $lbplogdir;

        $configFile = 's4lox_config.json';
        $offFile = $lbplogdir . '/s4lox_off.tmp';

        if (file_exists($offFile)) {
            return 0;
        }

        $configPath = $lbpconfigdir . '/' . $configFile;
        if (!file_exists($configPath)) {
            echo '<ERROR> Configuration file could not be loaded' . PHP_EOL;
            return 1;
        }

        $config = json_decode((string) file_get_contents($configPath), true);
        if (!is_array($config) || empty($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
            echo '<ERROR> Configuration file is invalid or does not contain sonoszonen' . PHP_EOL;
            return 1;
        }

        echo '<OK> Configuration file has been loaded' . PHP_EOL;

        $mainPlayers = [];
        foreach ($config['sonoszonen'] as $zone => $player) {
            $src = isset($player[6]) ? $player[6] : '';
            if ($src === 'on') {
                $mainPlayers[] = $zone;
            }
        }

        if (count($mainPlayers) < 1) {
            $L = LBSystem::readlanguage('sonos.ini');
            notify(LBPPLUGINDIR, 'Sonos', $L['ERRORS.NOTE_UPGRADE']);
            echo '<OK> Notify to update Config has been created' . PHP_EOL;
            return 0;
        }

        echo '<OK> Player already marked, nothing to do' . PHP_EOL;
        return 0;
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(S4L_NotificationCheck::run());
}
