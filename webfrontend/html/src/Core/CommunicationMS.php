<?php
/**
 * Sonos4Lox - Communication Miniserver helpers
 * Version: COMMUNICATION_MS_LOGGING_V02_2026_06_19
 * Patch: CORE_MQTT_RELOCATION_V01_2026_06_12
 *
 * Provides the legacy sendUDP() and sendMQTT() functions for status forwarding.
 * The file intentionally stays procedural-compatible because Play_T2S.php and
 * other legacy callers still call sendUDP()/sendMQTT() directly.
 */

if (!class_exists('S4L_CommunicationMS')) {
    class S4L_CommunicationMS
    {
        private const LOG_CONTEXT = 'src/Core/CommunicationMS.php';

        /**
         * Send a status value to the Loxone Miniserver via UDP.
         *
         * @param mixed  $value Status value to send.
         * @param string $name  Status name/key.
         * @return bool True when both UDP publish calls were attempted successfully.
         */
        public static function sendUDP($value, $name)
        {
            global $config;

            $name = trim((string)$name);
            if ($name === '') {
                self::log('UDP send skipped because the status name is empty.', 'WARNING');
                return false;
            }

            $pluginHtmlDir = self::getPluginHtmlDir();
            if ($pluginHtmlDir === '') {
                self::log('UDP send skipped because plugin HTML directory could not be resolved.', 'ERROR');
                return false;
            }

            if (!self::includeOnce($pluginHtmlDir . '/src/Core/Communication/io-modul.php', true)) {
                return false;
            }
            if (!self::includeOnce('REPLACELBHOMEDIR/libs/phplib/loxberry_io.php', true)) {
                return false;
            }

            if (!function_exists('udp_send_mem')) {
                self::log('UDP send skipped because function udp_send_mem() is not available.', 'ERROR');
                return false;
            }

            if (!is_array($config)) {
                self::log('UDP send skipped because global config is not available.', 'ERROR');
                return false;
            }

            $serverPort = self::configValue($config, array('LOXONE', 'UDP'), '');
            $miniserver = self::configValue($config, array('LOXONE', 'Loxone'), '');

            if ($serverPort === '' || $miniserver === '') {
                self::log("UDP send skipped for '{$name}' because Loxone UDP configuration is incomplete.", 'WARNING');
                return false;
            }

            $payload = array($name => $value);

            try {
                udp_send_mem($miniserver, $serverPort, 'Sonos4lox', $payload);
                udp_send_mem($miniserver, $serverPort, 's4lox', $payload);
            } catch (Throwable $e) {
                self::log("UDP send failed for '{$name}': " . $e->getMessage(), 'ERROR');
                return false;
            }

            return true;
        }

        /**
         * Send a status value to the LoxBerry MQTT gateway.
         *
         * @param mixed  $value Status value to send.
         * @param string $name  Status name/key.
         * @return bool True when both MQTT publish calls were attempted successfully.
         */
        public static function sendMQTT($value, $name)
        {
            $name = trim((string)$name);
            if ($name === '') {
                self::log('MQTT send skipped because the status name is empty.', 'WARNING');
                return false;
            }

            $pluginHtmlDir = self::getPluginHtmlDir();
            if ($pluginHtmlDir === '') {
                self::log('MQTT send skipped because plugin HTML directory could not be resolved.', 'ERROR');
                return false;
            }

            if (!self::includeOnce('REPLACELBHOMEDIR/libs/phplib/loxberry_io.php', true)) {
                return false;
            }
            if (!self::includeOnce($pluginHtmlDir . '/src/Core/Mqtt/phpMQTT.php', true)) {
                return false;
            }
            if (!self::includeOnce($pluginHtmlDir . '/src/Core/Communication/io-modul.php', true)) {
                return false;
            }

            if (!function_exists('mqtt_connectiondetails')) {
                self::log('MQTT send skipped because function mqtt_connectiondetails() is not available.', 'ERROR');
                return false;
            }
            if (!class_exists('Bluerhinos\\phpMQTT')) {
                self::log('MQTT send skipped because class Bluerhinos\\phpMQTT is not available.', 'ERROR');
                return false;
            }

            try {
                $creds = mqtt_connectiondetails();
            } catch (Throwable $e) {
                self::log('MQTT connection details could not be loaded: ' . $e->getMessage(), 'ERROR');
                return false;
            }

            if (!is_array($creds)) {
                self::log('MQTT send skipped because mqtt_connectiondetails() returned invalid data.', 'ERROR');
                return false;
            }

            $brokerHost = isset($creds['brokerhost']) ? $creds['brokerhost'] : '';
            $brokerPort = isset($creds['brokerport']) ? $creds['brokerport'] : '';
            $brokerUser = isset($creds['brokeruser']) ? $creds['brokeruser'] : null;
            $brokerPass = isset($creds['brokerpass']) ? $creds['brokerpass'] : null;

            if ($brokerHost === '' || $brokerPort === '') {
                self::log("MQTT send skipped for '{$name}' because MQTT broker host or port is missing.", 'WARNING');
                return false;
            }

            $clientId = uniqid(gethostname() . '_s4lox_', true);
            $mqtt = null;

            try {
                $mqtt = new Bluerhinos\phpMQTT($brokerHost, $brokerPort, $clientId);
                $connected = $mqtt->connect(true, null, $brokerUser, $brokerPass);

                if (!$connected) {
                    self::log("MQTT send failed for '{$name}' because connection to broker '{$brokerHost}:{$brokerPort}' failed.", 'ERROR');
                    return false;
                }

                $mqtt->publish('Sonos4lox/' . $name, (string)$value, 0, 1);
                $mqtt->publish('s4lox/' . $name, (string)$value, 0, 1);

                return true;
            } catch (Throwable $e) {
                self::log("MQTT send failed for '{$name}': " . $e->getMessage(), 'ERROR');
                return false;
            } finally {
                if (is_object($mqtt) && method_exists($mqtt, 'close')) {
                    try {
                        $mqtt->close();
                    } catch (Throwable $e) {
                        self::log('MQTT close failed: ' . $e->getMessage(), 'WARNING');
                    }
                }
            }
        }

        /**
         * Resolve the plugin HTML directory from the caller context or this file location.
         *
         * @return string Absolute plugin HTML directory or empty string.
         */
        private static function getPluginHtmlDir()
        {
            global $lbphtmldir;

            if (isset($lbphtmldir) && is_string($lbphtmldir) && $lbphtmldir !== '' && is_dir($lbphtmldir)) {
                return rtrim($lbphtmldir, '/');
            }

            if (defined('LBPHTMLDIR') && is_string(LBPHTMLDIR) && LBPHTMLDIR !== '' && is_dir(LBPHTMLDIR)) {
                return rtrim(LBPHTMLDIR, '/');
            }

            // Current file location: <plugin>/src/Core/CommunicationMS.php
            $inferred = dirname(__DIR__, 2);
            if (is_dir($inferred)) {
                return rtrim($inferred, '/');
            }

            return '';
        }

        /**
         * Include a file from an absolute path or PHP include_path.
         *
         * @param string $file     Absolute path or include_path file name.
         * @param bool   $required Whether missing files should be logged as errors.
         * @return bool True if included or already resolvable.
         */
        private static function includeOnce($file, $required)
        {
            if ($file === '') {
                if ($required) {
                    self::log('Required include skipped because the file path is empty.', 'ERROR');
                }
                return false;
            }

            if (is_readable($file)) {
                require_once $file;
                return true;
            }

            $resolved = stream_resolve_include_path($file);
            if ($resolved !== false && is_readable($resolved)) {
                require_once $resolved;
                return true;
            }

            if ($required) {
                self::log("Required include not found or not readable: {$file}", 'ERROR');
            }

            return false;
        }

        /**
         * Read a nested config value without raising undefined index warnings.
         *
         * @param array $source  Source array.
         * @param array $path    Path segments.
         * @param mixed $default Default value.
         * @return mixed Config value or default.
         */
        private static function configValue(array $source, array $path, $default)
        {
            $current = $source;
            foreach ($path as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    return $default;
                }
                $current = $current[$segment];
            }
            return $current;
        }

        /**
         * Log with the mandatory Sonos4Lox file context prefix.
         *
         * @param string $message Message without file context.
         * @param string $level   INFO, WARNING or ERROR.
         * @return void
         */
        private static function log($message, $level)
        {
            $logLevel = 7;
            if ($level === 'ERROR') {
                $logLevel = 3;
            } elseif ($level === 'WARNING') {
                $logLevel = 4;
            } elseif ($level === 'INFO') {
                $logLevel = 6;
            }

            if (!class_exists('S4L_Logger')) {
                $loggerFile = dirname(__DIR__, 1) . '/Support/Logger.php';
                if (is_readable($loggerFile)) {
                    require_once $loggerFile;
                }
            }

            if (class_exists('S4L_Logger')) {
                S4L_Logger::write($message, $logLevel, __FILE__);
                return;
            }

            $message = self::LOG_CONTEXT . ': ' . $message;
            $function = self::nativeLogFunction($logLevel);
            if ($function !== '' && function_exists($function)) {
                $function($message);
                return;
            }

            error_log($message);
        }

        private static function nativeLogFunction($level)
        {
            switch ((int)$level) {
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
}

if (!class_exists('CommunicationMS', false)) {
    class_alias('S4L_CommunicationMS', 'CommunicationMS');
}

if (!function_exists('sendUDP')) {
    /**
     * Legacy wrapper for existing callers.
     *
     * @param mixed  $value Status value to send.
     * @param string $name  Status name/key.
     * @return bool
     */
    function sendUDP($value, $name)
    {
        return S4L_CommunicationMS::sendUDP($value, $name);
    }
}

if (!function_exists('sendMQTT')) {
    /**
     * Legacy wrapper for existing callers.
     *
     * @param mixed  $value Status value to send.
     * @param string $name  Status name/key.
     * @return bool
     */
    function sendMQTT($value, $name)
    {
        return S4L_CommunicationMS::sendMQTT($value, $name);
    }
}
