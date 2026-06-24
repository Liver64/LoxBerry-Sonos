<?php
/**
 * Sonos4Lox CronTaskFinalization V03
 * Max volume enforcement support class. Can be called directly from cron.
 */

if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}
require_once $lbphtmldir . '/src/Core/Sonos/sonosAccess.php';

class S4L_MaxVolumeEnforcer
{
    /**
     * Enforce temporary max volume restrictions stored in RAM.
     *
     * @return int Exit code.
     */
    public static function run(): int
    {
        global $lbplogdir;

        $maxvolfile = '/run/shm/s4lox_max_volume.json';
        $offFile = $lbplogdir . '/s4lox_off.tmp';

        if (file_exists($offFile)) {
            return 0;
        }

        if (!is_file($maxvolfile)) {
            return 0;
        }

        $result = json_decode(file_get_contents($maxvolfile), true);
        if (!is_array($result) || !isset($result['zones'], $result['volume']) || !is_array($result['zones'])) {
            return 1;
        }

        $maxVolume = (int)$result['volume'];
        foreach ($result['zones'] as $playerIp) {
            if ($playerIp === '') {
                continue;
            }

            try {
                $sonos = new SonosAccess($playerIp);
                $currentVolume = (int)$sonos->GetVolume();
                if ($currentVolume > $maxVolume) {
                    $sonos->SetVolume($maxVolume);
                }
            } catch (Throwable $e) {
                // Keep cron execution quiet; the next run can retry when the player is reachable again.
                continue;
            }
        }

        return 0;
    }
}


if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(S4L_MaxVolumeEnforcer::run());
}
