<?php
/**
 * Sonos4Lox volume profile initialization support helper.
 * Version: VOLUME_PROFILE_FINALIZATION_V02_2026_06_10
 */

if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}

require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';
require_once $lbphtmldir . '/src/Core/Sonos/sonosAccess.php';
require_once $lbphtmldir . '/Speaker.php';

class S4L_VolumeProfileInitializer
{
    /**
     * Build the initial/current volume profile data.
     *
     * @return array
     */
    public static function build()
    {
        global $lbpconfigdir, $lbpdatadir;

        $configFile = 's4lox_config.json';
        $folfilePlOn = $lbpdatadir . '/PlayerStatus/s4lox_on_';
        $configPath = $lbpconfigdir . '/' . $configFile;

        $config = json_decode(file_get_contents($configPath), true);
        if (!is_array($config) || empty($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
            return [];
        }

        $onlineZones = [];
        $offlineZones = [];
        foreach ($config['sonoszonen'] as $zone => $data) {
            if (is_file($folfilePlOn . $zone . '.txt')) {
                $onlineZones[$zone] = $data;
            } else {
                $offlineZones[$zone] = $data;
            }
        }

        $profile = [];
        $profile['Name'] = 'current values';

        foreach ($onlineZones as $player => $value) {
            $profile['Player'][$player] = [self::readPlayerValues($player, $onlineZones)];
        }

        foreach ($offlineZones as $player => $value) {
            $profile['Player'][$player] = [[
                'Volume'          => '',
                'Bass'            => '',
                'Treble'          => '',
                'Loudness'        => 'false',
                'Subwoofer'       => 'na',
                'Subwoofer_level' => '',
                'Surround'        => 'na',
            ]];
        }

        return [$profile];
    }

    /**
     * Persist the current volume profile file.
     *
     * @param array $data Profile data.
     * @return bool
     */
    public static function save($data)
    {
        global $lbpconfigdir;

        $target = $lbpconfigdir . '/s4lox_vol_profiles.json';
        return file_put_contents(
            $target,
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT)
        ) !== false;
    }

    /**
     * Execute the legacy wrapper behavior.
     *
     * @param bool $ajax Whether output should be JSON only.
     * @return int Exit code.
     */
    public static function run($ajax = false)
    {
        $data = self::build();

        if ($ajax) {
            echo json_encode($data);
            return 0;
        }

        self::save($data);
        echo '<PRE>';
        print_r($data);
        return 0;
    }

    /**
     * Read current EQ/volume values for one player.
     *
     * @param string $player Player key.
     * @param array $sonoszone Online zone array.
     * @return array
     */
    private static function readPlayerValues($player, $sonoszone)
    {
        $sonos = new SonosAccess($sonoszone[$player][0]);

        $data = [];
        $data['Volume'] = $sonos->GetVolume();
        $data['Bass'] = $sonos->GetBass();
        $data['Treble'] = $sonos->GetTreble();
        $data['Loudness'] = is_enabled($sonos->GetLoudness()) ? 'true' : 'false';

        $dialog = [];
        $previousGlobalSonos = isset($GLOBALS['sonos']) ? $GLOBALS['sonos'] : null;
        $hadPreviousGlobalSonos = array_key_exists('sonos', $GLOBALS);

        try {
            // Speaker.php::Getdialoglevel() is a legacy helper and still reads
            // the active SonosAccess instance from the global $sonos variable.
            // Keep that compatibility here, but restore the previous global
            // value afterwards so the wrapper has no side effects.
            $GLOBALS['sonos'] = $sonos;
            $dialog = Getdialoglevel();
            if (!is_array($dialog)) {
                $dialog = [];
            }
        } catch (Throwable $e) {
            $dialog = [];
        }

        if ($hadPreviousGlobalSonos) {
            $GLOBALS['sonos'] = $previousGlobalSonos;
        } else {
            unset($GLOBALS['sonos']);
        }

        if (isset($sonoszone[$player][8]) && $sonoszone[$player][8] === 'NOSUB') {
            $data['Subwoofer'] = 'na';
            $data['Subwoofer_level'] = '';
        } else {
            $data['Subwoofer'] = (!empty($dialog['SubEnable']) && is_enabled((string)$dialog['SubEnable'])) ? 'true' : 'false';
            $data['Subwoofer_level'] = isset($dialog['SubGain']) ? $dialog['SubGain'] : '';
        }

        if (isset($sonoszone[$player][10]) && $sonoszone[$player][10] === 'NOSUR') {
            $data['Surround'] = 'na';
        } else {
            $data['Surround'] = (!empty($dialog['SurroundEnable']) && is_enabled((string)$dialog['SurroundEnable'])) ? 'true' : 'false';
        }

        return $data;
    }
}

/*
 * Direct CLI entry point.
 * HTTP/Ajax calls use ajax/volume_profile_initializer.php so this class file
 * can stay an implementation file under src/Support.
 */
if (realpath(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '') === __FILE__) {

    if (PHP_SAPI === 'cli') {
        exit(S4L_VolumeProfileInitializer::run(false));
    }

    header('Content-Type: application/json; charset=utf-8');
    exit(S4L_VolumeProfileInitializer::run(true));
}
