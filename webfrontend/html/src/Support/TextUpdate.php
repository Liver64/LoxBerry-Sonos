<?php
/**
 * Sonos4Lox language text update support helper.
 * Version: TEXT_UPDATE_FINALIZATION_V01_2026_06_10
 */


if (!defined('LBHOMEDIR')) {
    require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
}

require_once 'REPLACELBHOMEDIR/libs/phplib/loxberry_log.php';

class S4L_TextUpdate
{
    /**
     * Process pending language update INI files.
     *
     * @return int Exit code.
     */
    public static function run()
    {
        global $lbptemplatedir;

        $templatePath = $lbptemplatedir;
        $updates = [
            [
                'language'   => 'DE',
                'base_file'  => 't2s-text_de.ini',
                'update_file'=> 'update-text_de.ini',
            ],
            [
                'language'   => 'EN',
                'base_file'  => 't2s-text_en.ini',
                'update_file'=> 'update-text_en.ini',
            ],
        ];

        foreach ($updates as $entry) {
            self::processLanguage($templatePath, $entry['language'], $entry['base_file'], $entry['update_file']);
        }

        return 0;
    }

    /**
     * Process one language update file.
     *
     * @param string $templatePath Template base path.
     * @param string $language Human-readable language code.
     * @param string $baseFile Base language file.
     * @param string $updateFile Update language file.
     * @return void
     */
    private static function processLanguage($templatePath, $language, $baseFile, $updateFile)
    {
        $basePath = $templatePath . '/lang/' . $baseFile;
        $updatePath = $templatePath . '/lang/' . $updateFile;

        if (!file_exists($updatePath)) {
            echo 'There is no update for language ' . $language . ' to be processed!' . PHP_EOL;
            return;
        }

        if (!file_exists($basePath)) {
            echo '<WARNING> The file ' . $baseFile . ' could not be opened, we skip here!' . PHP_EOL;
            return;
        }

        $updateData = parse_ini_file($updatePath, true);
        $baseData = parse_ini_file($basePath, true);

        if (!is_array($updateData) || !is_array($baseData)) {
            echo '<WARNING> Language update for ' . $baseFile . ' could not be parsed, we skip here!' . PHP_EOL;
            return;
        }

        echo "Update file '" . $updateFile . "' has been located and loaded" . PHP_EOL;

        if (isset($baseData['VERSION']['V_NO']) && (string)$baseData['VERSION']['V_NO'] === '1') {
            echo '<OK> Nothing to do, Update for ' . $baseFile . ' already processed' . PHP_EOL;
            @unlink($updatePath);
            return;
        }

        @mkdir($templatePath . '/lang/backup', 0755, true);
        @copy($basePath, $templatePath . '/lang/backup/' . $baseFile);

        $merged = array_replace_recursive($baseData, $updateData);
        self::writeIniFile($merged, $basePath, true);
        @unlink($updatePath);

        echo "Update for '" . $baseFile . "' file has been successfully processed" . PHP_EOL;
    }

    /**
     * Write an array as INI file.
     *
     * @param array $config INI data.
     * @param string $file Target file.
     * @param bool $hasSection Whether to write sections.
     * @param bool $writeToFile Whether to write or return content.
     * @return int|string|null
     */
    public static function writeIniFile($config, $file, $hasSection = false, $writeToFile = true)
    {
        $fileContent = '';

        if (!empty($config)) {
            foreach ($config as $key => $value) {
                if ($hasSection) {
                    $fileContent .= '[' . $key . "]\n" . self::writeIniFile($value, $file, false, false);
                } elseif (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $fileContent .= $key . '[' . $subKey . ']=' . (is_numeric($subValue) ? $subValue : '"' . $subValue . '"') . "\n";
                    }
                } else {
                    $fileContent .= $key . '=' . (is_numeric($value) ? $value : '"' . $value . '"') . "\n";
                }
            }
        }

        if ($writeToFile && strlen($fileContent)) {
            return file_put_contents($file, $fileContent, LOCK_EX);
        }

        return $fileContent;
    }
}


if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(S4L_TextUpdate::run());
}
