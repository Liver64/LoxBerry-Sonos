<?php
/**
 * Sonos4Lox Request Preparation Support
 * Version: V03.0
 * Language: EN
 *
 * Purpose:
 * - Keep Sonos.php as a slim public entry point.
 * - Move request-wide preparation and legacy global setup into a structured helper.
 * - Preserve the existing global variables, constants and helper expectations.
 *
 * V02:
 * - Publish legacy global $config and $sonoszonen before calling sonoszonen_on().
 * - Fix Helper.php warnings/offline false positives caused by missing legacy globals.
 */

class S4L_RequestPreparation
{
    private const LOG_PREFIX = 'src/Support/RequestPreparation.php: ';

    /**
     * Prepare the legacy runtime environment required by existing helpers and actions.
     *
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function prepare(array $args): array
    {
        $action = isset($_GET['action']) ? (string)$_GET['action'] : '';
        $zone = isset($_GET['zone']) ? (string)$_GET['zone'] : '';

        self::assertScriptEnabled((string)$args['off_file'], $action);
        self::guardTtsAndPhoneState((string)$args['tmp_tts'], (string)$args['tmp_phone']);
        self::resetOneClickTempFilesIfNeeded($action);

        $config = self::loadConfig((string)$args['config_path']);
        $sonoszonen = isset($config['sonoszonen']) && is_array($config['sonoszonen'])
            ? $config['sonoszonen']
            : array();

        // Several legacy helper functions, especially sonoszonen_on(), still read
        // $config and $sonoszonen from the global scope. Publish them before the
        // online-zone check to keep the original runtime behavior intact.
        self::publishLegacyGlobalsBeforeOnlineCheck($config, $sonoszonen);

        $sonoszone = self::loadOnlineZones($zone);

        self::assertTtsEnabled($config);

        $t2sLangFile = 't2s-text_' . strtolower(substr((string)$config['TTS']['messageLang'], 0, 2) . '.ini');
        $messageStorePath = (string)$config['SYSTEM']['ttspath'];
        $minVol = $config['TTS']['phonemute'];
        $minSec = $config['TTS']['waiting'];

        self::defineLegacyConstants($config, $sonoszone, $sonoszonen, $zone);
        create_symlinks();

        self::assertNoProfileVolumeConflict();

        $volume = S4L_VolumeContext::resolveMasterVolume(
            $config,
            $sonoszone,
            $zone,
            $minVol,
            $args['profile_selected']
        );

        self::applyOptionalPlayMode($sonoszone, $zone);
        self::applyOptionalDelay();
        self::applyOptionalSleeptimer();

        return array(
            'config' => $config,
            'sonoszonen' => $sonoszonen,
            'sonoszone' => $sonoszone,
            'master' => $zone,
            'volume' => $volume,
            't2s_langfile' => $t2sLangFile,
            'MessageStorepath' => $messageStorePath,
            'min_vol' => $minVol,
            'min_sec' => $minSec,
        );
    }

    private static function assertScriptEnabled(string $offFile, string $action): void
    {
        if (file_exists($offFile) && $action !== 'on') {
            self::log('Script is off.', 5);
            echo 'sonos.php: Script is off! Please turn on using ...action=on';
            exit;
        }
    }

    private static function guardTtsAndPhoneState(string $tmpTts, string $tmpPhone): void
    {
        if (!self::requestContainsMediaOrTtsPayload()) {
            return;
        }

        if (file_exists($tmpTts)) {
            while (file_exists($tmpTts)) {
                usleep(200000);
            }
            LOGINF(self::LOG_PREFIX . 'Currently a TTS is running, waiting before processing this request.');
            sleep(5);
        }

        if (file_exists($tmpPhone)) {
            LOGINF(self::LOG_PREFIX . 'Currently a phone call is running, aborting this request.');
            exit(0);
        }

        if (isset($_GET['text']) && ($_GET['text'] === 'null' || $_GET['text'] === '0')) {
            self::log('NULL, 0 or empty Loxone status text was entered, skipping TTS.', 6);
            exit;
        }
    }

    private static function requestContainsMediaOrTtsPayload(): bool
    {
        $keys = array(
            'text',
            'messageid',
            'sonos',
            'weather',
            'abfall',
            'witz',
            'pollen',
            'warning',
            'distance',
            'clock',
            'calendar',
            'playlist',
            'playlisturi',
            'albumuri',
            'file',
        );

        foreach ($keys as $key) {
            if (isset($_GET[$key])) {
                return true;
            }
        }

        return false;
    }

    private static function resetOneClickTempFilesIfNeeded(string $action): void
    {
        $skipActions = array(
            'playallfavorites',
            'playtrackfavorites',
            'playtuneinfavorites',
            'playradiofavorites',
            'playsonosplaylist',
            'audioclip',
            'say',
            'playfavorite',
            'play',
            'stop',
            'toggle',
            'playplfavorites',
            'next',
            'previous',
            'volume',
            'pause',
            'zapzone',
            'sendmessage',
            'sonosplaylist',
            'sendgroupmessage',
            'volumeup',
            'gettransportinfo',
            'volumedown',
            'leave',
            'follow',
        );

        $skipByParameter = isset($_GET['volume']) || isset($_GET['keepvolume']) || isset($_GET['groupvolume']);

        if (in_array($action, $skipActions, true) || $skipByParameter) {
            self::log('No exception to delete temp files has been called.', 7);
            return;
        }

        DeleteTmpFavFiles();
        self::log('Exception to delete temp files has been called. ONE-click functions are reset.', 6);
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadConfig(string $configPath): array
    {
        if (!file_exists($configPath)) {
            self::log('Configuration file is missing: ' . $configPath, 2);
            exit;
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!is_array($config)) {
            self::log('Configuration file could not be decoded: ' . $configPath, 2);
            exit;
        }

        return $config;
    }


    /**
     * Publish legacy globals required by older helper functions before online checks.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed> $sonoszonen
     */
    private static function publishLegacyGlobalsBeforeOnlineCheck(array $config, array $sonoszonen): void
    {
        $GLOBALS['config'] = $config;
        $GLOBALS['sonoszonen'] = $sonoszonen;
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadOnlineZones(string $zone): array
    {
        $sonoszone = sonoszonen_on();

        if (!array_key_exists($zone, $sonoszone)) {
            self::log("Requested master zone '$zone' seems to be offline. Check power, online status or time restrictions.", 4);
            exit;
        }

        self::log('All variables have been collected.', 7);
        return $sonoszone;
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function assertTtsEnabled(array $config): void
    {
        if (is_disabled($config['TTS']['t2son']) && !isset($_GET['urgent'])) {
            if (isset($_GET['text']) || isset($_GET['messageid'])) {
                self::log('Text-to-speech blocked because the TTS function is disabled in the plugin configuration.', 4);
                exit(1);
            }
        }
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $sonoszone
     * @param array<string,mixed> $sonoszonen
     */
    private static function defineLegacyConstants(array $config, array $sonoszone, array $sonoszonen, string $master): void
    {
        if (!defined('CONFIG')) {
            define('CONFIG', $config);
        }
        if (!defined('SONOSZONE')) {
            define('SONOSZONE', $sonoszone);
        }
        if (!defined('SONOSZONEN')) {
            define('SONOSZONEN', $sonoszonen);
        }
        if (!defined('MASTER')) {
            define('MASTER', $master);
        }
    }

    private static function assertNoProfileVolumeConflict(): void
    {
        if (isset($_GET['profile']) && isset($_GET['volume'])) {
            self::log("Optional parameter 'volume' cannot be used together with 'profile'. Please correct the URL syntax.", 3);
            exit;
        }
    }

    /**
     * @param array<string,mixed> $sonoszone
     */
    private static function applyOptionalPlayMode(array $sonoszone, string $master): void
    {
        if (!isset($_GET['playmode']) || (isset($_GET['action']) && strtolower((string)$_GET['action']) === 'playmode')) {
            return;
        }

        $validPlaymodes = array(
            'NORMAL',
            'REPEAT_ALL',
            'REPEAT_ONE',
            'SHUFFLE_NOREPEAT',
            'SHUFFLE',
            'SHUFFLE_REPEAT_ONE',
        );

        $playmode = preg_replace('/[^a-zA-Z0-9_]+/', '', strtoupper((string)$_GET['playmode']));
        $sonos = new SonosAccess($sonoszone[$master][0]);

        if (in_array($playmode, $validPlaymodes, true)) {
            $sonos->SetQueue('x-rincon-queue:' . $sonoszone[$master][1] . '#0');
            SetPlaymodes($master, $playmode);
            self::log('PlayMode "' . $playmode . '" has been set for player "' . $master . '".', 7);
            return;
        }

        self::log('Incorrect PlayMode selected. Please correct the URL syntax.', 4);
    }

    private static function applyOptionalDelay(): void
    {
        if (isset($_GET['wait'])) {
            delay();
        }
    }

    private static function applyOptionalSleeptimer(): void
    {
        if (isset($_GET['timer']) && (!isset($_GET['action']) || strtolower((string)$_GET['action']) !== 'sleeptimer')) {
            sleeptimer();
        }
    }

    private static function log(string $message, int $level): void
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

        $message = self::LOG_PREFIX . $message;
        $function = self::nativeLogFunction($level);
        if ($function !== '' && function_exists($function)) {
            $function($message);
            return;
        }

        error_log($message);
    }

    private static function nativeLogFunction(int $level): string
    {
        switch ($level) {
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
