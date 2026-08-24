<?php
/**
 * Sonos4Lox - Voice Engine shared helper functions
 * Version: VOICE_ENGINE_ROBUSTNESS_V07_2026_08_24
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

/* Provider-specific error normalization. */
if (!function_exists('s4l_ve_set_last_error')) {
    function s4l_ve_set_last_error(?array $e): void { $GLOBALS['S4L_VE_LAST_ERROR'] = $e; }
}
if (!function_exists('s4l_ve_get_last_error')) {
    function s4l_ve_get_last_error(): ?array { return $GLOBALS['S4L_VE_LAST_ERROR'] ?? null; }
}
if (!function_exists('s4l_ve_clear_last_error')) {
    function s4l_ve_clear_last_error(): void { unset($GLOBALS['S4L_VE_LAST_ERROR']); }
}
if (!function_exists('s4l_ve_provider_name')) {
    function s4l_ve_provider_name(string $p): string
    {
        $p = strtolower(trim($p));
        return ['google'=>'googlecloud','aws'=>'polly','msazure'=>'azure','ms_azure'=>'azure'][$p] ?? $p;
    }
}

if (!function_exists('s4l_ve_http_error_text_provider')) {
    function s4l_ve_http_error_text_provider(int $code, string $provider): string
    {
        $map = [
            'elevenlabs' => [
                400=>'ElevenLabs rejected the request.',
                401=>'ElevenLabs authentication failed. Check the API key.',
                403=>'ElevenLabs denied access. Check key permissions, IP restrictions and plan access.',
                404=>'The ElevenLabs voice or resource was not found.',
                429=>'ElevenLabs rate/concurrency limit exceeded or service busy.',
                500=>'ElevenLabs internal server error.',
                503=>'ElevenLabs temporarily unavailable.',
            ],
            'googlecloud' => [
                400=>'Google Cloud rejected the TTS request.',
                401=>'Google Cloud authentication failed.',
                403=>'Google Cloud denied access. Check permissions and billing.',
                404=>'Google Cloud voice or resource not found.',
                429=>'Google Cloud TTS quota or rate limit exceeded.',
                500=>'Google Cloud TTS internal server error.',
                503=>'Google Cloud TTS temporarily unavailable.',
                504=>'Google Cloud TTS request timed out.',
            ],
            'azure' => [
                400=>'Azure Speech request parameter missing or invalid.',
                401=>'Azure Speech authentication failed. Check key/token and region.',
                415=>'Azure Speech rejected the Content-Type; TTS requires application/ssml+xml.',
                429=>'Azure Speech quota or request rate exceeded.',
                502=>'Azure Speech gateway, network or server-side error.',
                503=>'Azure Speech temporarily unavailable.',
            ],
            'polly' => [
                400=>'Amazon Polly rejected the request.',
                401=>'Amazon Polly authentication failed.',
                403=>'Amazon Polly denied access. Check signature and IAM permissions.',
                429=>'Amazon Polly request rate limit exceeded.',
                500=>'Amazon Polly internal service error.',
                503=>'Amazon Polly temporarily unavailable.',
            ],
            'voicerss' => [
                400=>'VoiceRSS rejected the request.',
                401=>'VoiceRSS authentication failed.',
                403=>'VoiceRSS denied the request.',
                429=>'VoiceRSS request limit exceeded.',
                500=>'VoiceRSS internal server error.',
            ],
            'responsivevoice' => [
                400=>'ResponsiveVoice rejected the request.',
                401=>'ResponsiveVoice authentication failed.',
                403=>'ResponsiveVoice denied the request.',
                404=>'ResponsiveVoice resource not found.',
                429=>'ResponsiveVoice rate limit exceeded.',
                500=>'ResponsiveVoice internal server error.',
                503=>'ResponsiveVoice temporarily unavailable.',
            ],
        ];
        $provider = s4l_ve_provider_name($provider);
        return $map[$provider][$code] ?? '';
    }
}

if (!function_exists('s4l_ve_provider_code_details')) {
    function s4l_ve_provider_code_details(string $provider, string $code): array
    {
        $map = [
            'elevenlabs' => [
                'invalid_api_key'=>['AUTHENTICATION','ElevenLabs API key is invalid.'],
                'invalid_api_key_length'=>['AUTHENTICATION','ElevenLabs API key has an invalid length.'],
                'missing_api_key'=>['AUTHENTICATION','ElevenLabs API key is missing.'],
                'authentication_error'=>['AUTHENTICATION','ElevenLabs authentication failed.'],
                'api_key_id_used_as_api_key'=>['AUTHENTICATION','ElevenLabs API key ID used instead of secret key.'],
                'quota_exceeded'=>['QUOTA_EXCEEDED','ElevenLabs quota or credits exhausted.'],
                'insufficient_credits'=>['QUOTA_EXCEEDED','ElevenLabs credits exhausted.'],
                'voice_not_found'=>['NOT_FOUND','ElevenLabs voice not found.'],
                'model_access_denied'=>['AUTHORIZATION','ElevenLabs model access denied.'],
                'max_character_limit_exceeded'=>['INVALID_REQUEST','ElevenLabs character limit exceeded.'],
                'rate_limit_exceeded'=>['RATE_LIMIT','ElevenLabs rate limit exceeded.'],
                'concurrent_limit_exceeded'=>['RATE_LIMIT','ElevenLabs concurrency limit exceeded.'],
                'too_many_concurrent_requests'=>['RATE_LIMIT','ElevenLabs concurrency limit exceeded.'],
                'system_busy'=>['SERVICE_UNAVAILABLE','ElevenLabs service busy.'],
                'service_unavailable'=>['SERVICE_UNAVAILABLE','ElevenLabs temporarily unavailable.'],
            ],
            'googlecloud' => [
                'API_KEY_INVALID'=>['AUTHENTICATION','Google Cloud API key is invalid.'],
                'API_KEY_SERVICE_BLOCKED'=>['AUTHORIZATION','Google Cloud API key is not allowed to access this service.'],
                'API_KEY_HTTP_REFERRER_BLOCKED'=>['AUTHORIZATION','Google Cloud API key HTTP referrer restriction blocked the request.'],
                'API_KEY_IP_ADDRESS_BLOCKED'=>['AUTHORIZATION','Google Cloud API key IP address restriction blocked the request.'],
                'API_KEY_ANDROID_APP_BLOCKED'=>['AUTHORIZATION','Google Cloud API key Android application restriction blocked the request.'],
                'API_KEY_IOS_APP_BLOCKED'=>['AUTHORIZATION','Google Cloud API key iOS application restriction blocked the request.'],
                'SERVICE_DISABLED'=>['AUTHORIZATION','Google Cloud Text-to-Speech API is disabled for this project.'],
                'BILLING_DISABLED'=>['AUTHORIZATION','Google Cloud billing is disabled for this project.'],
                'CONSUMER_INVALID'=>['AUTHORIZATION','Google Cloud project or consumer is invalid.'],
                'INVALID_ARGUMENT'=>['INVALID_REQUEST','Google Cloud rejected the TTS request.'],
                'UNAUTHENTICATED'=>['AUTHENTICATION','Google Cloud authentication failed.'],
                'PERMISSION_DENIED'=>['AUTHORIZATION','Google Cloud TTS access denied.'],
                'NOT_FOUND'=>['NOT_FOUND','Google Cloud voice or resource not found.'],
                'RESOURCE_EXHAUSTED'=>['RATE_LIMIT','Google Cloud TTS quota or rate limit exceeded.'],
                'DEADLINE_EXCEEDED'=>['TIMEOUT','Google Cloud TTS request timed out.'],
                'UNAVAILABLE'=>['SERVICE_UNAVAILABLE','Google Cloud TTS temporarily unavailable.'],
                'INTERNAL'=>['SERVICE_UNAVAILABLE','Google Cloud TTS internal server error.'],
            ],
            'polly' => [
                'UnrecognizedClientException'=>['AUTHENTICATION','Amazon Polly credentials rejected.'],
                'InvalidSignatureException'=>['AUTHENTICATION','Amazon Polly request signature invalid.'],
                'AccessDeniedException'=>['AUTHORIZATION','Amazon Polly access denied.'],
                'ThrottlingException'=>['RATE_LIMIT','Amazon Polly request rate limit exceeded.'],
                'RequestTimeoutException'=>['TIMEOUT','Amazon Polly request timed out.'],
                'ServiceUnavailable'=>['SERVICE_UNAVAILABLE','Amazon Polly temporarily unavailable.'],
                'ServiceFailureException'=>['SERVICE_UNAVAILABLE','Amazon Polly internal service error.'],
                'TextLengthExceededException'=>['INVALID_REQUEST','Amazon Polly text length exceeded.'],
                'InvalidSsmlException'=>['INVALID_REQUEST','Amazon Polly SSML is invalid.'],
                'LanguageNotSupportedException'=>['INVALID_REQUEST','Amazon Polly language not supported.'],
                'EngineNotSupportedException'=>['INVALID_REQUEST','Amazon Polly engine not supported.'],
                'LexiconNotFoundException'=>['NOT_FOUND','Amazon Polly lexicon not found.'],
            ],
            'voicerss' => [
                'API_KEY_NOT_SPECIFIED'=>['AUTHENTICATION','VoiceRSS API key not specified.'],
                'API_KEY_NOT_AVAILABLE'=>['AUTHENTICATION','VoiceRSS API key invalid or unavailable.'],
                'ACCOUNT_INACTIVE'=>['AUTHORIZATION','VoiceRSS account inactive.'],
                'LIMIT_EXCEEDED'=>['QUOTA_EXCEEDED','VoiceRSS subscription or request limit exceeded.'],
                'LANGUAGE_NOT_SUPPORTED'=>['INVALID_REQUEST','VoiceRSS language not supported.'],
                'TEXT_TOO_LONG'=>['INVALID_REQUEST','VoiceRSS text/request limit exceeded.'],
                'VOICE_RSS_ERROR'=>['PROVIDER_ERROR','VoiceRSS rejected the request.'],
            ],
            'responsivevoice' => [
                'INVALID_API_KEY'=>['AUTHENTICATION','ResponsiveVoice API key is invalid.'],
            ],
        ];
        $provider = s4l_ve_provider_name($provider);
        return $map[$provider][$code] ?? [];
    }
}

if (!function_exists('s4l_ve_resolve_provider_error')) {
    function s4l_ve_resolve_provider_error(string $provider, int $httpCode, string $body = '', int $curlNo = 0, string $curlErr = ''): array
    {
        $provider = s4l_ve_provider_name($provider);
        $code = ''; $category = 'PROVIDER_ERROR'; $message = '';

        if ($curlNo !== 0) {
            $category = ($curlNo === 28) ? 'TIMEOUT' : 'NETWORK';
            $message = ($curlNo === 28) ? 'Provider request timed out.' : 'Provider could not be reached.';
        } else {
            $json = json_decode($body, true);
            if ($provider === 'elevenlabs' && is_array($json) && is_array($json['detail'] ?? null)) {
                $d = $json['detail'];
                // Prefer a recognized, more specific legacy status when present;
                // otherwise use the documented code, then the broad error type.
                $candidates = [
                    (string)($d['status'] ?? ''),
                    (string)($d['code'] ?? ''),
                    (string)($d['type'] ?? ''),
                ];
                foreach ($candidates as $candidate) {
                    if ($candidate !== '' && s4l_ve_provider_code_details($provider, $candidate)) {
                        $code = $candidate;
                        break;
                    }
                }
                if ($code === '') {
                    $code = (string)($d['code'] ?? $d['status'] ?? $d['type'] ?? '');
                }
            } elseif ($provider === 'googlecloud' && is_array($json)) {
                $error = is_array($json['error'] ?? null) ? $json['error'] : [];
                $reason = '';
                foreach (($error['details'] ?? []) as $detail) {
                    if (!is_array($detail)) continue;
                    $candidate = (string)($detail['reason'] ?? '');
                    if ($candidate !== '' && s4l_ve_provider_code_details($provider, $candidate)) {
                        $reason = $candidate;
                        break;
                    }
                    if ($reason === '' && $candidate !== '') $reason = $candidate;
                }
                $status = (string)($error['status'] ?? '');
                if ($reason !== '' && s4l_ve_provider_code_details($provider, $reason)) $code = $reason;
                elseif ($status !== '') $code = $status;
                else $code = $reason;
            } elseif ($provider === 'polly') {
                if (is_array($json)) {
                    $code = (string)($json['__type'] ?? $json['code'] ?? $json['Code'] ?? '');
                    if (strpos($code, '#') !== false) $code = substr($code, strrpos($code, '#') + 1);
                }
                if ($code === '' && preg_match('/<Code>([^<]+)<\/Code>/i', $body, $m)) $code = trim($m[1]);
            } elseif ($provider === 'responsivevoice' && is_array($json)) {
                $error = is_array($json['error'] ?? null) ? $json['error'] : [];
                $code = (string)($error['code'] ?? '');
            } elseif ($provider === 'voicerss' && stripos(trim($body), 'ERROR') === 0) {
                $b = strtolower($body);
                if (strpos($b, 'api key is not specified') !== false) $code = 'API_KEY_NOT_SPECIFIED';
                elseif (strpos($b, 'api key is not available') !== false) $code = 'API_KEY_NOT_AVAILABLE';
                elseif (strpos($b, 'account is inactive') !== false) $code = 'ACCOUNT_INACTIVE';
                elseif (strpos($b, 'subscription is expired') !== false || strpos($b, 'limitation is exceeded') !== false) $code = 'LIMIT_EXCEEDED';
                elseif (strpos($b, 'language does not support') !== false) $code = 'LANGUAGE_NOT_SUPPORTED';
                elseif (strpos($b, 'text is too long') !== false || strpos($b, 'content length') !== false) $code = 'TEXT_TOO_LONG';
                else $code = 'VOICE_RSS_ERROR';
            }

            if ($code !== '') {
                $d = s4l_ve_provider_code_details($provider, $code);
                if ($d) [$category, $message] = $d;
            }
            if ($message === '') {
                $message = s4l_ve_http_error_text_provider($httpCode, $provider);
                if ($message === '') $message = $httpCode ? "Provider request failed with HTTP $httpCode." : 'Provider request failed.';
                if ($httpCode === 401) $category = 'AUTHENTICATION';
                elseif ($httpCode === 402) $category = 'QUOTA_EXCEEDED';
                elseif ($httpCode === 403) $category = 'AUTHORIZATION';
                elseif ($httpCode === 404) $category = 'NOT_FOUND';
                elseif ($httpCode === 429) $category = 'RATE_LIMIT';
                elseif (in_array($httpCode,[408,504],true)) $category = 'TIMEOUT';
                elseif (in_array($httpCode,[500,502,503],true)) $category = 'SERVICE_UNAVAILABLE';
                elseif (in_array($httpCode,[400,413,415,422],true)) $category = 'INVALID_REQUEST';
            }
        }

        $e = ['provider'=>$provider,'http_code'=>$httpCode,'code'=>$code,'category'=>$category,'message'=>$message];
        s4l_ve_set_last_error($e);
        return $e;
    }
}

if (!function_exists('s4l_ve_log_provider_error')) {
    function s4l_ve_log_provider_error(string $context, array $e): void
    {
        $p = [];
        if (!empty($e['http_code'])) $p[] = 'HTTP ' . $e['http_code'];
        if (!empty($e['code'])) $p[] = $e['code'];
        s4l_ve_log($context, 'ERROR', ($p ? 'Provider error ['.implode('] [',$p).']' : 'Provider error') . ': ' . ($e['message'] ?? 'Unknown provider error.'));
    }
}

if (!function_exists('s4l_ve_curl_request')) {
    function s4l_ve_curl_request(string $url, array $options, string $context, string $provider = '')
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
        curl_setopt_array($ch, array_replace($defaultOptions, $options));

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlNo = (int)curl_errno($ch);
        $curlErr = (string)curl_error($ch);
        curl_close($ch);

        if ($curlNo !== 0) {
            if ($provider !== '') s4l_ve_log_provider_error($context, s4l_ve_resolve_provider_error($provider, $httpCode, (string)$response, $curlNo, $curlErr));
            else s4l_ve_log($context, 'ERROR', "cURL error [$curlNo]: $curlErr");
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            if ($provider !== '') s4l_ve_log_provider_error($context, s4l_ve_resolve_provider_error($provider, $httpCode, (string)$response));
            else s4l_ve_log($context, 'ERROR', "HTTP error: status code $httpCode.");
            if (is_string($response) && $response !== '') s4l_ve_log($context, 'DEBUG', 'HTTP response snippet: ' . substr($response, 0, 300));
            return false;
        }

        if ($provider !== '') s4l_ve_clear_last_error();
        return $response;
    }
}
