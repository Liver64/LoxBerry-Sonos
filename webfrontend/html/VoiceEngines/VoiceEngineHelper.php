<?php
/**
 * Sonos4Lox - Voice Engine shared helper functions
 * Version: VOICE_ENGINE_ROBUSTNESS_V03_2026_06_15
 *
 * Shared helpers for VoiceEngines/*.php.
 * Kept below VoiceEngines/Support by design because voice engines are a core
 * plugin component and should stay self-contained in the VoiceEngines tree.
 */

if (!function_exists('s4l_ve_log')) {
    function s4l_ve_log(string $context, string $level, string $message): void
    {
        $line = $context . ': ' . $message;
        $level = strtoupper($level);

        if ($level === 'ERROR' && function_exists('LOGERR')) {
            LOGERR($line);
            return;
        }
        if ($level === 'WARNING' && function_exists('LOGWARN')) {
            LOGWARN($line);
            return;
        }
        if ($level === 'OK' && function_exists('LOGOK')) {
            LOGOK($line);
            return;
        }
        if ($level === 'DEBUG' && function_exists('LOGDEB')) {
            LOGDEB($line);
            return;
        }
        if (function_exists('LOGINF')) {
            LOGINF($line);
            return;
        }
        error_log($line);
    }
}

if (!function_exists('s4l_ve_require_params')) {
    function s4l_ve_require_params(array $params, array $required, string $context): bool
    {
        $missing = [];
        foreach ($required as $key) {
            if (!isset($params[$key]) || trim((string)$params[$key]) === '') {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            s4l_ve_log($context, 'ERROR', 'Missing required parameter(s): ' . implode(', ', $missing) . '.');
            return false;
        }

        return true;
    }
}

if (!function_exists('s4l_ve_output_dir')) {
    function s4l_ve_output_dir(array $config, string $context): string
    {
        $dir = rtrim((string)($config['SYSTEM']['ttspath'] ?? '/tmp'), '/');
        if ($dir === '') {
            $dir = '/tmp';
        }

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                s4l_ve_log($context, 'ERROR', "Output directory '$dir' does not exist and could not be created.");
                return '';
            }
        }

        return $dir;
    }
}

if (!function_exists('s4l_ve_safe_filename')) {
    function s4l_ve_safe_filename(string $filename): string
    {
        $filename = trim($filename);
        $filename = basename($filename);
        $filename = preg_replace('/\.mp3$/i', '', $filename);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        return $filename !== '' ? $filename : 'tts_output';
    }
}

if (!function_exists('s4l_ve_output_path')) {
    function s4l_ve_output_path(array $config, string $filename, string $context): string
    {
        $dir = s4l_ve_output_dir($config, $context);
        if ($dir === '') {
            return '';
        }

        return $dir . '/' . s4l_ve_safe_filename($filename) . '.mp3';
    }
}

if (!function_exists('s4l_ve_write_mp3')) {
    function s4l_ve_write_mp3(string $path, $audioData, string $context)
    {
        if ($path === '') {
            return false;
        }

        if (!is_string($audioData) || strlen($audioData) < 32) {
            s4l_ve_log($context, 'ERROR', 'Audio response is empty or too short. MP3 file was not written.');
            return false;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                s4l_ve_log($context, 'ERROR', "Output directory '$dir' does not exist and could not be created.");
                return false;
            }
        }

        $bytes = @file_put_contents($path, $audioData, LOCK_EX);
        if ($bytes === false || $bytes <= 0) {
            s4l_ve_log($context, 'ERROR', "Failed to write MP3 file '$path'.");
            return false;
        }

        if (!is_file($path) || filesize($path) <= 0) {
            s4l_ve_log($context, 'ERROR', "MP3 file '$path' was not created or is empty.");
            return false;
        }

        @chmod($path, 0664);
        s4l_ve_log($context, 'OK', "MP3 file successfully saved to $path");
        return basename($path, '.mp3');
    }
}

if (!function_exists('s4l_ve_load_json')) {
    function s4l_ve_load_json(string $path, string $context)
    {
        if (!is_file($path)) {
            s4l_ve_log($context, 'ERROR', "JSON file not found: $path");
            return false;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            s4l_ve_log($context, 'ERROR', "Could not read JSON file: $path");
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            s4l_ve_log($context, 'ERROR', "Invalid JSON file: $path");
            return false;
        }

        return $data;
    }
}

if (!function_exists('s4l_ve_curl_request')) {
    function s4l_ve_curl_request(string $url, array $options, string $context)
    {
        if (!function_exists('curl_init')) {
            s4l_ve_log($context, 'ERROR', 'PHP cURL extension is not available.');
            return false;
        }

        $defaultOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, $defaultOptions + $options);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlNo = (int)curl_errno($ch);
        $curlErr = (string)curl_error($ch);
        curl_close($ch);

        if ($curlNo !== 0) {
            s4l_ve_log($context, 'ERROR', "cURL error [$curlNo]: $curlErr");
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            s4l_ve_log($context, 'ERROR', "HTTP error: status code $httpCode.");
            if (is_string($response) && $response !== '') {
                s4l_ve_log($context, 'DEBUG', 'HTTP response snippet: ' . substr($response, 0, 300));
            }
            return false;
        }

        return $response;
    }
}
