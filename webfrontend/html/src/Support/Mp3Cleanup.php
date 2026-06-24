<?php
/**
 * Sonos4Lox MP3 cleanup support helper.
 * Version: MP3_CLEANUP_CRON_INCLUDE_CLEANUP_V01_2026_06_15
 *
 * Changes:
 * - Updated log message context from bin/cleanup_sonos.php to src/Support/Mp3Cleanup.php.
 */

if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}
require_once 'loxberry_log.php';
require_once __DIR__ . '/LegacyLogging.php';

class S4L_Mp3Cleanup
{
    /**
     * Run MP3 cache cleanup according to plugin configuration.
     *
     * @return int Exit code.
     */
    public static function run()
    {
        global $lbplogdir, $lbpconfigdir;

        $offFile = $lbplogdir . '/s4lox_off.tmp';
        $configFile = 's4lox_config.json';

        if (file_exists($offFile)) {
            return 0;
        }

        LBLog::newLog(['name' => 'Cronjobs', 'stderr' => 1, 'addtime' => 1]);
        LOGSTART('Cleanup MP3 files');

        echo '<PRE>';

        $configPath = $lbpconfigdir . '/' . $configFile;
        if (!file_exists($configPath)) {
            LOGCRIT('src/Support/Mp3Cleanup.php: The configuration file could not be loaded, the file may be disrupted. We have to abort.');
            LOGEND('Cleanup finished');
            return 1;
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!is_array($config)) {
            LOGCRIT('src/Support/Mp3Cleanup.php: The configuration file could not be parsed.');
            LOGEND('Cleanup finished');
            return 1;
        }

        $messageStorePath = self::resolveMessageStorePath($config);
        $storageInterval = trim(isset($config['MP3']['MP3store']) ? $config['MP3']['MP3store'] : '0');
        $cacheSize = !empty($config['MP3']['cachesize']) ? trim($config['MP3']['cachesize']) : '100';
        $targetSize = (int)$cacheSize * 1024 * 1024;

        if (empty($targetSize)) {
            LOGCRIT('src/Support/Mp3Cleanup.php: The size limit is not valid - stopping operation.');
            LOGDEB('src/Support/Mp3Cleanup.php: Config parameter MP3/cachesize is ' . (isset($config['MP3']['cachesize']) ? $config['MP3']['cachesize'] : '') . ", target size is '$targetSize'.");
            LOGEND('Cleanup finished');
            return 1;
        }

        self::deleteMp3Files($messageStorePath, $storageInterval, $targetSize, $cacheSize);
        LOGEND('Cleanup finished');
        return 0;
    }

    /**
     * Resolve the MP3 storage path from configuration.
     *
     * @param array $config Plugin configuration.
     * @return string
     */
    private static function resolveMessageStorePath($config)
    {
        $path = isset($config['SYSTEM']['path']) ? $config['SYSTEM']['path'] : '';
        $pathParts = explode('/', $path);

        if (isset($pathParts[3]) && $pathParts[3] !== 'data') {
            return rtrim($path, '/') . '/tts/';
        }

        return rtrim($config['SYSTEM']['ttspath'], '/') . '/';
    }

    /**
     * Delete MP3 files by cache size and storage age.
     *
     * @param string $directory MP3 directory.
     * @param string|int $storageInterval Days to keep MP3 files.
     * @param int $targetSize Target cache size in bytes.
     * @param string|int $cacheSize Cache size in MB.
     * @return void
     */
    private static function deleteMp3Files($directory, $storageInterval, $targetSize, $cacheSize)
    {
        LOGINF("src/Support/Mp3Cleanup.php: Deleting oldest MP3 files to reach $cacheSize MB...");
        LOGDEB('src/Support/Mp3Cleanup.php: Directory: ' . $directory);

        $allFiles = glob(rtrim($directory, '/') . '/*');
        $files = [];
        foreach ($allFiles as $file) {
            if (substr($file, -3) === 'mp3') {
                $files[] = $file;
            }
        }

        usort($files, function ($a, $b) {
            return @filemtime($a) > @filemtime($b);
        });

        $fullSize = 0;
        foreach ($files as $key => $file) {
            if (!is_file($file)) {
                unset($files[$key]);
                continue;
            }
            $fullSize += filesize($file);
        }

        if ($fullSize < $targetSize) {
            LOGINF("src/Support/Mp3Cleanup.php: Current size $fullSize is below destination size $targetSize.");
            LOGOK('src/Support/Mp3Cleanup.php: Nothing to do, quitting.');
        } else {
            self::deleteToTargetSize($files, $fullSize, $targetSize);
        }

        LOGINF('src/Support/Mp3Cleanup.php: Now check if MP3 files older x days should be deleted, too...');
        self::deleteByAge($files, $storageInterval);
        LOGOK('src/Support/Mp3Cleanup.php: Sonos file reduction has been completed.');
    }

    /**
     * Delete oldest files until target size is reached.
     *
     * @param array $files MP3 files.
     * @param int $fullSize Current size.
     * @param int $targetSize Target size.
     * @return void
     */
    private static function deleteToTargetSize($files, $fullSize, $targetSize)
    {
        $newSize = $fullSize;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $fileSize = filesize($file);
            if (@unlink($file) !== false) {
                LOGDEB('src/Support/Mp3Cleanup.php: ' . basename($file) . ' has been deleted.');
                $newSize -= $fileSize;
            } else {
                LOGWARN('src/Support/Mp3Cleanup.php: ' . basename($file) . ' could not be deleted.');
            }

            if ($newSize < $targetSize) {
                LOGOK("src/Support/Mp3Cleanup.php: New size $newSize reached destination size $targetSize.");
                break;
            }
        }

        if ($newSize > $targetSize) {
            LOGERR("src/Support/Mp3Cleanup.php: Used size $newSize is still greater than destination size $targetSize - something is strange.");
        }
    }

    /**
     * Delete files older than the configured storage interval.
     *
     * @param array $files MP3 files.
     * @param string|int $storageInterval Days to keep files.
     * @return void
     */
    private static function deleteByAge($files, $storageInterval)
    {
        if ((string)$storageInterval === '0') {
            LOGINF('src/Support/Mp3Cleanup.php: MP3 files should be stored forever. Nothing to do here.');
            return;
        }

        LOGINF("src/Support/Mp3Cleanup.php: Deleting MP3 files older than $storageInterval days...");
        $deleteTime = time() - ((int)$storageInterval * 24 * 60 * 60);

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $fileTime = @filemtime($file);
            LOGDEB('src/Support/Mp3Cleanup.php: Checking file ' . basename($file) . ' (' . date(DATE_ATOM, $fileTime) . ').');
            if ($fileTime < $deleteTime) {
                if (@unlink($file) !== false) {
                    LOGINF('src/Support/Mp3Cleanup.php: ' . basename($file) . ' has been deleted.');
                } else {
                    LOGWARN('src/Support/Mp3Cleanup.php: ' . basename($file) . ' could not be deleted.');
                }
            }
        }
    }
}


if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(S4L_Mp3Cleanup::run());
}
