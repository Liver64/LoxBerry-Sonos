<?php
/**
 * Sonos4Lox Max Volume Enforcer
 * Version: MAX_VOLUME_ENFORCER_V04_2026_09_26
 *
 * Purpose:
 * - Enforce the configured per-player Max Vol from s4lox_config.json.
 * - If a player exceeds its configured Max Vol, reset it to its configured Audio Vol.
 * - Keep the existing temporary action=setmaxvolume restriction working in parallel.
 * - Log only when an actual volume correction is performed.
 */

if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}
require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';
require_once $lbphtmldir . '/src/Core/Sonos/sonosAccess.php';

class S4L_MaxVolumeEnforcer
{
    private const TEMP_LIMIT_FILE = '/run/shm/s4lox_max_volume.json';

    /**
     * Enforce configured and temporary max volume restrictions.
     *
     * @return int Exit code.
     */
    public static function run(): int
    {
        global $lbpconfigdir, $lbplogdir;

        $offFile = rtrim((string)$lbplogdir, '/') . '/s4lox_off.tmp';
        if (file_exists($offFile)) {
            return 0;
        }

        $configFile = rtrim((string)$lbpconfigdir, '/') . '/s4lox_config.json';
        if (!is_file($configFile)) {
            return 1;
        }

        $config = json_decode((string)@file_get_contents($configFile), true);
        if (!is_array($config) || empty($config['sonoszonen']) || !is_array($config['sonoszonen'])) {
            return 1;
        }

        $volmaxEnabled = $config['VARIOUS']['volmax'] ?? false;
        if (!self::isEnabled($volmaxEnabled)) {
            return 0;
        }

        $temporaryLimit = self::loadTemporaryLimit();
        $temporaryMax = $temporaryLimit['volume'];
        $temporaryIps = array_fill_keys($temporaryLimit['zones'], true);
        $processedIps = [];

        foreach ($config['sonoszonen'] as $zone => $player) {
            if (!is_array($player)) {
                continue;
            }

            $playerIp = isset($player[0]) ? trim((string)$player[0]) : '';
            if ($playerIp === '') {
                continue;
            }

            $processedIps[$playerIp] = true;

            $audioVolume = self::normaliseVolume($player[4] ?? null);
            $configuredMax = self::normaliseVolume($player[5] ?? null);
            $temporaryMaxForPlayer = isset($temporaryIps[$playerIp]) ? $temporaryMax : null;

            self::enforcePlayer(
                (string)$zone,
                $playerIp,
                $audioVolume,
                $configuredMax,
                $temporaryMaxForPlayer
            );
        }

        // Preserve the legacy temporary setmaxvolume behaviour even if an IP from
        // the RAM file can no longer be mapped to a configured zone.
        if ($temporaryMax !== null) {
            foreach ($temporaryLimit['zones'] as $playerIp) {
                if ($playerIp === '' || isset($processedIps[$playerIp])) {
                    continue;
                }

                self::enforcePlayer(
                    $playerIp,
                    $playerIp,
                    null,
                    null,
                    $temporaryMax
                );
            }
        }

        return 0;
    }

    /**
     * Check one player and apply the most restrictive required correction.
     */
    private static function enforcePlayer(
        string $zone,
        string $playerIp,
        ?int $audioVolume,
        ?int $configuredMax,
        ?int $temporaryMax
    ): void {
        try {
            $sonos = new SonosAccess($playerIp);
            $currentVolume = (int)$sonos->GetVolume();

            $configuredExceeded = $configuredMax !== null && $currentVolume > $configuredMax;
            $temporaryExceeded = $temporaryMax !== null && $currentVolume > $temporaryMax;

            if (!$configuredExceeded && !$temporaryExceeded) {
                return;
            }

            $targets = [];
            $reasons = [];

            if ($configuredExceeded) {
                // The configured Max Vol is the trigger threshold. Once exceeded,
                // return to the normal Audio Vol instead of sticking at Max Vol.
                $configuredTarget = $audioVolume !== null
                    ? min($audioVolume, $configuredMax)
                    : $configuredMax;

                $targets[] = $configuredTarget;
                $reasons[] = 'configured Max Volume ' . $configuredMax;
            }

            if ($temporaryExceeded) {
                $targets[] = $temporaryMax;
                $reasons[] = 'temporary Max Volume ' . $temporaryMax;
            }

            if (empty($targets)) {
                return;
            }

            $targetVolume = min($targets);
            if ($targetVolume >= $currentVolume) {
                return;
            }

            $sonos->SetVolume($targetVolume);

            $targetDescription = 'Volume has been set to ' . $targetVolume . '.';
            if ($configuredExceeded && !$temporaryExceeded && $audioVolume !== null && $targetVolume === min($audioVolume, $configuredMax)) {
                $targetDescription = 'Volume has been reset to Audio Volume ' . $targetVolume . '.';
            }

            self::logCorrection(
                "Player '" . $zone . "' exceeded " . implode(' and ', $reasons)
                . ' (current: ' . $currentVolume . '). ' . $targetDescription
            );
        } catch (Throwable $e) {
            // Keep cron execution quiet for unreachable/offline players.
            // The next cron run retries automatically.
        }
    }

    /**
     * Read the optional temporary restriction created by action=setmaxvolume.
     * Invalid or missing data must not disable the configured per-player limit.
     *
     * @return array{volume:?int,zones:array<int,string>}
     */
    private static function loadTemporaryLimit(): array
    {
        if (!is_file(self::TEMP_LIMIT_FILE)) {
            return ['volume' => null, 'zones' => []];
        }

        $result = json_decode((string)@file_get_contents(self::TEMP_LIMIT_FILE), true);
        if (!is_array($result) || !isset($result['zones'], $result['volume']) || !is_array($result['zones'])) {
            return ['volume' => null, 'zones' => []];
        }

        $maxVolume = self::normaliseVolume($result['volume']);
        if ($maxVolume === null) {
            return ['volume' => null, 'zones' => []];
        }

        $zones = [];
        foreach ($result['zones'] as $playerIp) {
            $playerIp = trim((string)$playerIp);
            if ($playerIp !== '') {
                $zones[] = $playerIp;
            }
        }

        return [
            'volume' => $maxVolume,
            'zones' => array_values(array_unique($zones)),
        ];
    }

    /**
     * Convert a volume setting into a valid Sonos volume value.
     */
    private static function normaliseVolume($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $volume = (int)$value;
        if ($volume < 0) {
            return 0;
        }
        if ($volume > 100) {
            return 100;
        }

        return $volume;
    }

    /**
     * Interpret the LoxBerry-style boolean values used in JSON config files.
     */
    private static function isEnabled($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int)$value) !== 0;
        }

        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'on', 'yes', 'enabled'], true);
    }

    /**
     * Log only real corrections so the 10-second cron does not flood sonos.log.
     */
    private static function logCorrection(string $message): void
    {
        global $lbplogdir;

        LBLog::newLog([
            'name' => 'Sonos max volume check',
            'filename' => rtrim((string)$lbplogdir, '/') . '/sonos.log',
            'append' => 1,
            'stderr' => 0,
            'addtime' => 1,
        ]);

        $message = 'src/Support/MaxVolumeEnforcer.php: ' . $message;
        if (function_exists('LOGWARN')) {
            LOGWARN($message);
            return;
        }

        error_log($message);
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(S4L_MaxVolumeEnforcer::run());
}
