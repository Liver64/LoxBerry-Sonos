<?php
/**
 * Sonos4Lox - PiperVoiceIndex support class and direct runner
 * Version: PIPER_VOICE_INDEX_FINALIZATION_V05_2026_06_12
 *
 * Centralizes Piper voice metadata repair, language index creation and
 * duplicate cleanup. This file can be included as a support class and can
 * also be executed directly as CLI/HTTP endpoint.
 */

class S4L_PiperVoiceIndex
{
    private const CONTEXT = 'src/Support/PiperVoiceIndex.php: ';

    private const THORSTEN_HESSISCH_FILE = 'Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx.json';

    public static function fixThorstenHessisch(string $piperDir): bool
    {
        $file = rtrim($piperDir, '/') . '/' . self::THORSTEN_HESSISCH_FILE;

        if (!file_exists($file)) {
            self::writeLine('Thorsten Hessisch metadata file was not found, nothing to fix.');
            return false;
        }

        $piper = self::readJsonFile($file, true);
        if (!is_array($piper)) {
            $piper = [];
        }

        if (isset($piper['language']) && isset($piper['dataset'])) {
            self::writeLine('Thorsten Hessisch metadata already contains language and dataset details.');
            return false;
        }

        $piper = array_merge($piper, self::getThorstenHessischDetails());

        if (!self::writeJson($file, $piper)) {
            self::writeLine("Thorsten Hessisch metadata could not be updated: {$file}");
            return false;
        }

        self::writeLine('Thorsten Hessisch metadata has been updated.');
        return true;
    }

    public static function rebuildIndex(string $piperDir, string $languageOutputFile, string $voiceOutputFile): array
    {
        $piperDir = rtrim($piperDir, '/') . '/';
        $languages = [];
        $voices = [];
        $seenLanguages = [];
        $seenVoices = [];
        $success = true;

        self::fixThorstenHessisch($piperDir);

        if (!is_dir($piperDir)) {
            self::writeLine("Piper voice directory does not exist: {$piperDir}");
            $success = self::writeJson($languageOutputFile, $languages) && $success;
            $success = self::writeJson($voiceOutputFile, $voices) && $success;
            return ['languages' => 0, 'voices' => 0, 'success' => $success];
        }

        if (!is_readable($piperDir)) {
            self::writeLine("Piper voice directory is not readable: {$piperDir}");
            $success = self::writeJson($languageOutputFile, $languages) && $success;
            $success = self::writeJson($voiceOutputFile, $voices) && $success;
            return ['languages' => 0, 'voices' => 0, 'success' => false];
        }

        $files = scandir($piperDir);
        if (!is_array($files)) {
            self::writeLine("Piper voice directory could not be read: {$piperDir}");
            $success = self::writeJson($languageOutputFile, $languages) && $success;
            $success = self::writeJson($voiceOutputFile, $voices) && $success;
            return ['languages' => 0, 'voices' => 0, 'success' => false];
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $file) {
            if (!preg_match('/\.json$/i', $file)) {
                continue;
            }

            $fullPath = $piperDir . $file;
            if (!is_file($fullPath)) {
                continue;
            }

            $data = self::readJsonFile($fullPath, false);
            if (!is_array($data)) {
                continue;
            }

            if (!isset($data['language']) || !is_array($data['language']) || !isset($data['language']['code'])) {
                continue;
            }

            $code = trim((string)$data['language']['code']);
            $country = trim((string)($data['language']['country_english'] ?? ''));
            $dataset = trim((string)($data['dataset'] ?? ''));

            if ($code === '' || $dataset === '') {
                continue;
            }

            $baseFilename = preg_replace('/\.onnx\.json$|\.json$/i', '', $file);
            if (!is_string($baseFilename) || $baseFilename === '') {
                continue;
            }

            if (!file_exists($piperDir . $baseFilename . '.onnx')) {
                continue;
            }

            $voiceKey = $code . '_' . $dataset;
            if (!isset($seenVoices[$voiceKey])) {
                $voices[] = [
                    'name' => $dataset,
                    'language' => $code,
                    'filename' => $baseFilename . '.onnx',
                ];
                $seenVoices[$voiceKey] = true;
            }

            if (!isset($seenLanguages[$code])) {
                $languages[] = [
                    'country' => $country,
                    'value' => $code,
                ];
                $seenLanguages[$code] = true;
            }
        }

        usort($languages, static function ($a, $b): int {
            return strcasecmp((string)$a['country'], (string)$b['country']);
        });

        usort($voices, static function ($a, $b): int {
            $languageCompare = strcasecmp((string)$a['language'], (string)$b['language']);
            if ($languageCompare !== 0) {
                return $languageCompare;
            }
            return strcasecmp((string)$a['name'], (string)$b['name']);
        });

        $success = self::writeJson($languageOutputFile, $languages) && $success;
        $success = self::writeJson($voiceOutputFile, $voices) && $success;

        self::writeLine('Piper index rebuilt (' . count($languages) . ' language(s), ' . count($voices) . ' voice(s)).');

        return ['languages' => count($languages), 'voices' => count($voices), 'success' => $success];
    }

    public static function deduplicateLanguageFile(string $languageOutputFile): int
    {
        if (!file_exists($languageOutputFile)) {
            self::writeLine("Piper language file was not found: {$languageOutputFile}");
            return 0;
        }

        $data = self::readJsonFile($languageOutputFile, true);
        if (!is_array($data)) {
            self::writeLine("Piper language file is invalid JSON: {$languageOutputFile}");
            return 0;
        }

        $deduplicated = self::deduplicateByKey($data, 'value');
        if (!self::writeJson($languageOutputFile, $deduplicated)) {
            return 0;
        }

        self::writeLine('Piper language file deduplicated (' . count($deduplicated) . ' language(s)).');
        return count($deduplicated);
    }

    public static function runDirect(): int
    {
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }

        $loxberrySystem = 'REPLACELBHOMEDIR/libs/phplib/loxberry_system.php';
        if (!is_file($loxberrySystem)) {
            self::writeLine("Required LoxBerry system library was not found: {$loxberrySystem}");
            return 1;
        }

        require_once $loxberrySystem;

        $pluginHtmlDir = self::resolvePluginHtmlDir();
        if ($pluginHtmlDir === '') {
            self::writeLine('Plugin HTML directory could not be resolved.');
            return 1;
        }

        $piperDir = $pluginHtmlDir . '/VoiceEngines/piper-voices/';
        $piperOutLang = $pluginHtmlDir . '/VoiceEngines/langfiles/piper.json';
        $piperOutVoices = $pluginHtmlDir . '/VoiceEngines/langfiles/piper_voices.json';

        try {
            $result = self::rebuildIndex($piperDir, $piperOutLang, $piperOutVoices);

            $languageCount = (int)($result['languages'] ?? 0);
            $voiceCount = (int)($result['voices'] ?? 0);
            $success = !isset($result['success']) || (bool)$result['success'];

            if (!$success) {
                self::writeLine("Piper voice index rebuild finished with warnings ({$languageCount} language(s), {$voiceCount} voice(s)).");
                return 1;
            }

            self::writeLine("Piper voice index rebuild finished successfully ({$languageCount} language(s), {$voiceCount} voice(s)).");
            return 0;
        } catch (Throwable $e) {
            self::writeLine('Piper voice index rebuild failed: ' . $e->getMessage());
            return 1;
        }
    }

    private static function resolvePluginHtmlDir(): string
    {
        global $lbphtmldir;

        if (isset($lbphtmldir) && is_string($lbphtmldir) && $lbphtmldir !== '') {
            return rtrim($lbphtmldir, '/');
        }

        $fallbackDir = realpath(dirname(__DIR__, 2));
        if (is_string($fallbackDir) && $fallbackDir !== '') {
            return rtrim($fallbackDir, '/');
        }

        return '';
    }

    private static function deduplicateByKey(array $items, string $key): array
    {
        $deduplicated = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item[$key])) {
                continue;
            }
            if (!isset($deduplicated[$item[$key]])) {
                $deduplicated[$item[$key]] = $item;
            }
        }
        return array_values($deduplicated);
    }

    private static function readJsonFile(string $file, bool $logInvalid): ?array
    {
        if (!is_readable($file)) {
            if ($logInvalid) {
                self::writeLine("JSON file is not readable: {$file}");
            }
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            if ($logInvalid) {
                self::writeLine("JSON file could not be read: {$file}");
            }
            return null;
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            if ($logInvalid) {
                self::writeLine("JSON file is invalid: {$file} (" . json_last_error_msg() . ')');
            }
            return null;
        }

        return $data;
    }

    private static function writeJson(string $file, array $data): bool
    {
        $directory = dirname($file);
        if (!self::ensureDirectory($directory)) {
            return false;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            self::writeLine("JSON encoding failed for {$file}: " . json_last_error_msg());
            return false;
        }

        $tmpFile = $file . '.tmp.' . getmypid();
        $bytes = @file_put_contents($tmpFile, $json . PHP_EOL, LOCK_EX);
        if ($bytes === false) {
            self::writeLine("Could not write temporary JSON file: {$tmpFile}");
            return false;
        }

        @chmod($tmpFile, 0644);

        if (!@rename($tmpFile, $file)) {
            @unlink($tmpFile);
            self::writeLine("Could not replace JSON file atomically: {$file}");
            return false;
        }

        return true;
    }

    private static function ensureDirectory(string $directory): bool
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
            self::writeLine("Output directory could not be created: {$directory}");
            return false;
        }

        return is_writable($directory);
    }

    private static function writeLine(string $message): void
    {
        if (strncmp($message, self::CONTEXT, strlen(self::CONTEXT)) !== 0) {
            $message = self::CONTEXT . $message;
        }
        echo $message . PHP_EOL;
    }

    private static function getThorstenHessischDetails(): array
    {
        return [
            'language' => [
                'code' => 'de_DE',
                'family' => 'de',
                'region' => 'DE',
                'name_native' => 'Deutsch',
                'name_english' => 'German',
                'country_english' => 'Germany',
            ],
            'dataset' => 'thorsten_hessisch',
        ];
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(S4L_PiperVoiceIndex::runDirect());
}
