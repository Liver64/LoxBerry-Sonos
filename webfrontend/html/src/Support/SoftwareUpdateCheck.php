<?php
/**
 * Sonos4Lox CronTaskFinalization V03
 * Scheduled software update check support class. Can be called directly from cron.
 */
if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}
require_once $lbphtmldir . '/src/Core/Sonos/sonosAccess.php';
require_once $lbphtmldir . '/Helper.php';
require_once $lbphtmldir . '/src/Core/CommunicationMS.php';


class S4L_SoftwareUpdateCheck
{
    private static $logName = '';
    private static $send = '';
    private static $updateFile = '/run/shm/Sonos4lox_update.json';
    private static $config = [];

    public static function run(): void
    {
        global $lbpconfigdir, $lbpdatadir, $lbphtmldir;

        ini_set('max_execution_time', '2000');
        register_shutdown_function([__CLASS__, 'shutdown']);

        echo '<PRE>';

        $configFile = $lbpconfigdir . '/s4lox_config.json';
        if (!file_exists($configFile)) {
            self::$logName = startlog('Firmware Update', 'update');
            LOGERR('bin/SW_Update.php: The configuration file could not be loaded, the file may be disrupted. We have to abort...');
            return;
        }

        self::$config = json_decode(file_get_contents($configFile), true);
        if (!is_array(self::$config)) {
            self::$logName = startlog('Firmware Update', 'update');
            LOGERR('bin/SW_Update.php: The configuration file could not be loaded, the file may be disrupted. We have to abort...');
            return;
        }

        if (!self::isScheduledNow()) {
            return;
        }

        self::$logName = startlog('Firmware Update', 'update');
        LOGOK('bin/SW_Update.php: Run Updatecheck for Players');

        $sonosZones = self::$config['sonoszonen'];
        $onlineZones = self::checkZonesOn($sonosZones, $lbpdatadir . '/PlayerStatus/s4lox_on_');

        if (is_enabled(self::$config['SYSTEM']['hw_update_power'])) {
            self::$send = self::sendPowerState('1');
            LOGDEB('bin/SW_Update.php: We wait ~7 Minutes until all Players are Online...');
            sleep(400);
            require_once $lbphtmldir . '/src/Core/Runtime/CheckState.php';
            $onlineZones = self::checkZonesOn($sonosZones, $lbpdatadir . '/PlayerStatus/s4lox_on_');
            sleep(20);
            file_put_contents(self::$updateFile, json_encode('1', JSON_PRETTY_PRINT));
        }

        self::checkAndStartUpdates($onlineZones);
    }

    private static function isScheduledNow(): bool
    {
        $hour = date('H');
        $day = date('w');

        $enabled = is_enabled(self::$config['SYSTEM']['hw_update']);
        $timeMatches = self::$config['SYSTEM']['hw_update_time'] == $hour;
        $dayMatches = self::$config['SYSTEM']['hw_update_day'] == $day || self::$config['SYSTEM']['hw_update_day'] == '10';

        return $enabled && $timeMatches && $dayMatches;
    }

    private static function checkZonesOn(array $zones, string $onlinePrefix): array
    {
        $online = [];
        $offline = [];
        foreach ($zones as $zone => $data) {
            if (is_file($onlinePrefix . $zone . '.txt')) {
                $online[$zone] = $data;
            } else {
                $offline[] = $zone;
            }
        }

        $GLOBALS['zonesoffline'] = $offline;
        $GLOBALS['zonesonline'] = array_keys($online);
        return $online;
    }

    private static function checkAndStartUpdates(array $onlineZones): void
    {
        $countMajor = 0;
        $referenceUpdate = null;

        foreach ($onlineZones as $zone => $data) {
            if (!is_enabled($data[6])) {
                continue;
            }

            $countMajor++;
            try {
                $sonos = new SonosAccess($data[0]);
                $referenceUpdate = $sonos->CheckForUpdate();
                LOGOK("bin/SW_Update.php: Updatecheck for Player '" . $zone . "' executed. Actual Version is: 'v" . $referenceUpdate['version'] . "' Build: '" . $referenceUpdate['build'] . "'");
            } catch (Exception $e) {
                LOGWARN('bin/SW_Update.php: Updatecheck could not be executed');
                return;
            }
        }

        if ($countMajor === 0 || !is_array($referenceUpdate)) {
            LOGERR('bin/SW_Update.php: Updatecheck could not be executed. Please check if min. 1 Player is marked for T2S and this Player is Online too!');
            return;
        }

        $updateNeeded = [];
        foreach ($onlineZones as $zone => $data) {
            $info = json_decode(@file_get_contents('http://' . $data[0] . ':1400/info'), true);
            if (!is_array($info) || !isset($info['device']['softwareVersion'])) {
                continue;
            }

            $currentBuild = $info['device']['softwareVersion'];
            if (!is_null($referenceUpdate['build'])) {
                if ($currentBuild != $referenceUpdate['build']) {
                    LOGINF("bin/SW_Update.php: Update for Player '" . $zone . "' required. Current Version is: '" . $currentBuild . "' and will be updated to: '" . $referenceUpdate['build'] . "'");
                    $updateNeeded[] = $zone;
                } else {
                    LOGDEB("bin/SW_Update.php: Update for Player '" . $zone . "' is not required. Current Version: '" . $currentBuild . "' is the most actual");
                }
            }
        }

        if (!empty($GLOBALS['zonesoffline'])) {
            LOGWARN("bin/SW_Update.php: Updatecheck for Player '" . implode(', ', $GLOBALS['zonesoffline']) . "' could not be executed, may be they are Offline");
        }

        if (count($updateNeeded) > 0) {
            foreach ($updateNeeded as $zone) {
                $sonos = new SonosAccess($onlineZones[$zone][0]);
                $sonos->BeginSoftwareUpdate($referenceUpdate['updateurl']);
                sleep(1);
            }
            LOGDEB('bin/SW_Update.php: We wait 10 Minutes until all players were updated...');
            sleep(800);
            LOGOK('bin/SW_Update.php: Update for Playes Online finished successful.');
        } else {
            LOGDEB('bin/SW_Update.php: No update for Player Online are required.');
        }
    }

    private static function sendPowerState(string $value): string
    {
        if (!is_enabled(self::$config['LOXONE']['LoxDaten'])) {
            LOGERR('bin/SW_Update.php: You have turned on Auto Update and marked Power-On before Update, but Communication to Loxone is switched off. Please turn on!!');
            if (function_exists('notify')) {
                notify(LBPPLUGINDIR, 'Sonos4lox', 'You have turned on Auto Update and marked Power-On before Update, but Communication to Loxone is switched off. Please turn on!!', 1);
            }
            return '';
        }

        if (is_enabled(self::$config['LOXONE']['LoxDatenMQTT'])) {
            sendMQTT($value, 'update');
            LOGINF('bin/SW_Update.php: Power ' . ($value == '1' ? 'On' : 'Off') . ' has been send to MS via MQTT');
        } else {
            sendUDP($value, 'update');
            LOGINF('bin/SW_Update.php:: Power ' . ($value == '1' ? 'On' : 'Off') . ' has been send to MS via UDP');
        }

        return $value;
    }

    public static function shutdown(): void
    {
        if (self::$send === '1') {
            self::$send = self::sendPowerState('0');
        }
        @unlink(self::$updateFile);
        if (function_exists('LOGEND') && self::$logName !== '') {
            LOGEND(self::$logName);
        }
    }
}


if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    S4L_SoftwareUpdateCheck::run();
    exit(0);
}
