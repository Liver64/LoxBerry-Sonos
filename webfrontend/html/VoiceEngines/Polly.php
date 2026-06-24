<?php
/**
 * Sonos4Lox - Amazon Polly Text-to-Speech
 * Version: VOICE_ENGINE_ROBUSTNESS_V04_2026_06_15
 *
 * Composer-free AWS Signature V4 implementation.
 */

require_once __DIR__ . '/VoiceEngineHelper.php';

if (!defined('S4L_POLLY_CONTEXT')) {
    define('S4L_POLLY_CONTEXT', 'VoiceEngines/Polly.php');
}

if (!class_exists('S4L_Polly_TTS', false)) {
    class S4L_Polly_TTS
    {
        private string $voice;
        private string $region;
        private string $apikey;
        private string $secretkey;
        private DateTimeZone $utcTz;

        private array $endpoint = [
            'us-east-1' => 'polly.us-east-1.amazonaws.com',
            'us-east-2' => 'polly.us-east-2.amazonaws.com',
            'us-west-2' => 'polly.us-west-2.amazonaws.com',
            'eu-west-1' => 'polly.eu-west-1.amazonaws.com',
            'eu-west-2' => 'polly.eu-west-2.amazonaws.com',
            'eu-central-1' => 'polly.eu-central-1.amazonaws.com',
        ];

        public function __construct(array $params)
        {
            $this->voice = (string)($params['voice'] ?? 'Joanna');
            $this->region = (string)($params['region'] ?? 'eu-west-1');
            $this->apikey = (string)($params['apikey'] ?? '');
            $this->secretkey = (string)($params['secretkey'] ?? '');
            $this->utcTz = new DateTimeZone('GMT');

            if ($this->apikey === '' || $this->secretkey === '') {
                throw new Exception('AWS credentials are missing.');
            }

            if (!array_key_exists($this->region, $this->endpoint)) {
                // Ignore non-Polly regions from shared TTS configuration (for example Azure region names)
                // and use the default Polly region silently.
                $this->region = 'eu-west-1';
            }
        }

        public function getMp3(string $text): string
        {
            $payload = json_encode([
                'OutputFormat' => 'mp3',
                'Text' => $text,
                'VoiceId' => $this->voice,
            ]);

            if (!is_string($payload)) {
                throw new Exception('Could not encode Polly payload as JSON.');
            }

            $datestamp = new DateTime('now', $this->utcTz);
            $longdate = $datestamp->format('Ymd\THis\Z');
            $shortdate = $datestamp->format('Ymd');

            $kSecret = 'AWS4' . $this->secretkey;
            $host = $this->endpoint[$this->region];

            $headers = [
                'host' => $host,
                'content-type' => 'application/json',
                'x-amz-date' => $longdate,
                'x-amz-content-sha256' => hash('sha256', $payload, false),
            ];

            $canonicalHeaders = '';
            $signedHeaders = '';
            ksort($headers);
            foreach ($headers as $k => $v) {
                $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
                $signedHeaders .= strtolower($k) . ';';
            }
            $signedHeaders = rtrim($signedHeaders, ';');

            $canonicalRequest = "POST\n/v1/speech\n\n{$canonicalHeaders}\n{$signedHeaders}\n" . hash('sha256', $payload, false);
            $stringToSign = "AWS4-HMAC-SHA256\n{$longdate}\n{$shortdate}/{$this->region}/polly/aws4_request\n" . hash('sha256', $canonicalRequest, false);

            $kDate = hash_hmac('sha256', $shortdate, $kSecret, true);
            $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
            $kService = hash_hmac('sha256', 'polly', $kRegion, true);
            $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
            $signature = hash_hmac('sha256', $stringToSign, $kSigning);

            $authHeader = "AWS4-HMAC-SHA256 Credential={$this->apikey}/{$shortdate}/{$this->region}/polly/aws4_request, SignedHeaders={$signedHeaders}, Signature={$signature}";

            $curlHeaders = [];
            foreach ($headers as $k => $v) {
                $curlHeaders[] = $k . ': ' . $v;
            }
            $curlHeaders[] = 'Authorization: ' . $authHeader;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://{$host}/v1/speech",
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'LoxBerry-Sonos4Lox/1.0',
            ]);

            $result = curl_exec($ch);
            $errno = (int)curl_errno($ch);
            $err = (string)curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0) {
                throw new Exception("cURL error [$errno]: $err");
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $snippet = is_string($result) ? substr($result, 0, 300) : '';
                throw new Exception("HTTP $httpCode from Polly. Response snippet: $snippet");
            }

            if (!is_string($result) || strlen($result) < 32) {
                throw new Exception('Polly returned empty or too short audio response.');
            }

            // Valid MP3 usually starts with ID3 or MPEG frame sync 0xFFEx.
            $prefix = substr($result, 0, 3);
            $hexPrefix = bin2hex(substr($result, 0, 2));
            if ($prefix !== 'ID3' && !in_array(strtolower($hexPrefix), ['fffb', 'fff3', 'fff2'], true)) {
                throw new Exception('Polly response does not look like MP3: ' . substr($result, 0, 200));
            }

            return $result;
        }
    }
}

function t2s(array $t2s_param)
{
    global $config;

    if (!s4l_ve_require_params($t2s_param, ['apikey', 'secretkey', 'filename', 'text', 'voice'], S4L_POLLY_CONTEXT)) {
        return false;
    }

    $apikey = (string)$t2s_param['apikey'];
    $secretkey = (string)$t2s_param['secretkey'];
    $filename = (string)$t2s_param['filename'];
    $text = (string)$t2s_param['text'];
    $voice = (string)$t2s_param['voice'];
    $region = trim((string)($t2s_param['region'] ?? 'eu-west-1'));
    if ($region === '') {
        $region = 'eu-west-1';
    }

    $voiceFilePath = LBPHTMLDIR . '/VoiceEngines/langfiles/polly_voices.json';
    if (is_file($voiceFilePath)) {
        $validVoices = s4l_ve_load_json($voiceFilePath, S4L_POLLY_CONTEXT);
        if (is_array($validVoices)) {
            $matchedVoice = array_values(array_filter($validVoices, static function ($v) use ($voice) {
                return is_array($v) && (($v['name'] ?? '') === $voice);
            }));
            if (!empty($matchedVoice[0])) {
                $voice = (string)$matchedVoice[0]['name'];
                $language = (string)($matchedVoice[0]['language'] ?? '');
                s4l_ve_log(S4L_POLLY_CONTEXT, 'OK', "TTS language '$language' and voice '$voice' validated.");
            } else {
                s4l_ve_log(S4L_POLLY_CONTEXT, 'WARNING', "Voice '$voice' not found in configuration. Continuing with configured voice name.");
            }
        }
    } else {
        s4l_ve_log(S4L_POLLY_CONTEXT, 'WARNING', "Voice configuration file not found: $voiceFilePath");
    }

    try {
        $polly = new S4L_Polly_TTS([
            'apikey' => $apikey,
            'secretkey' => $secretkey,
            'voice' => $voice,
            'region' => $region,
        ]);

        $audioData = $polly->getMp3($text);
        $outputPath = s4l_ve_output_path($config, $filename, S4L_POLLY_CONTEXT);
        return s4l_ve_write_mp3($outputPath, $audioData, S4L_POLLY_CONTEXT);
    } catch (Exception $e) {
        s4l_ve_log(S4L_POLLY_CONTEXT, 'ERROR', 'Failed to create MP3. Error: ' . $e->getMessage());
        return false;
    }
}
