<?php
/**
 * Polly Text-to-Speech (Composer-frei, kein Cache)
 * 
 * Nutzung:
 * $t2s_param = [
 *     'apikey'    => 'AWS_ACCESS_KEY',
 *     'secretkey' => 'AWS_SECRET_KEY',
 *     'filename'  => 'testfile',
 *     'text'      => 'Hallo Welt!',
 *     'voice'     => 'Joanna',
 *     'region'    => 'eu-west-1' // optional
 * ];
 */

function t2s(array $t2s_param)
{
    global $config, $pathlanguagefile;

    // ===== 1. Parameter validieren =====
    $required = ['apikey', 'secretkey', 'filename', 'text', 'voice'];
    foreach ($required as $param) {
        if (empty($t2s_param[$param])) {
            LOGERR("voice_engines\\Polly.php: Missing required parameter '$param'.");
            return false;
        }
    }

    $apikey    = $t2s_param['apikey'];
    $secretkey = $t2s_param['secretkey'];
    $filename  = $t2s_param['filename'];
    $text      = $t2s_param['text'];
    $voice     = $t2s_param['voice'];

    // Region: aus Param, sonst Default
    $region    = $t2s_param['region'] ?? 'eu-west-1';
    $region    = trim($region);
    if ($region === '') {
        $region = 'eu-west-1';
    }

    // ===== 2. Stimme validieren =====
    $voiceFilePath = LBPHTMLDIR . "/voice_engines/langfiles/polly_voices.json";
    if (file_exists($voiceFilePath)) {
        $validVoices = json_decode(file_get_contents($voiceFilePath), true);
        if (is_array($validVoices)) {
            $matchedVoice = array_filter($validVoices, fn($v) => $v['name'] === $voice);
            if (!empty($matchedVoice)) {
                $voiceData = array_values($matchedVoice)[0];
                $voice = $voiceData['name'];
                $language = $voiceData['language'];
                LOGOK("voice_engines\\Polly.php: TTS language '$language' and voice '$voice' validated.");
            } else {
                LOGERR("voice_engines\\Polly.php: Voice '$voice' not found in configuration. Using default.");
            }
        } else {
            LOGERR("voice_engines\\Polly.php: Invalid JSON in voice configuration file.");
        }
    } else {
        LOGERR("voice_engines\\Polly.php: Voice configuration file not found: $voiceFilePath");
    }

    // ===== 3. Polly-Klasse =====
    class POLLY_TTS {
        private string $voice;
        private string $region;
        private string $apikey;
        private string $secretkey;
        private $utc_tz;

        private array $endpoint = [
            'us-east-1' => 'polly.us-east-1.amazonaws.com',
            'us-east-2' => 'polly.us-east-2.amazonaws.com',
            'us-west-2' => 'polly.us-west-2.amazonaws.com',
            'eu-west-1' => 'polly.eu-west-1.amazonaws.com',
        ];

        public function __construct(array $params) {
            $this->voice     = $params['voice'] ?? 'Joanna';
            $this->region    = $params['region'] ?? 'eu-west-1';
            $this->apikey    = $params['apikey'];
            $this->secretkey = $params['secretkey'];
            $this->utc_tz    = new \DateTimeZone('GMT');

            if (empty($this->apikey) || empty($this->secretkey)) {
                throw new Exception("AWS Credentials fehlen.");
            }

            // Region validieren: wenn unbekannt -> auf eu-west-1 zurückfallen
            if (!array_key_exists($this->region, $this->endpoint)) {
                #LOGWARN("voice_engines\\Polly.php: Region '{$this->region}' is not valid for Polly. Falling back to 'eu-west-1'.");
                $this->region = 'eu-west-1';
            }
        }

        public function save_mp3(string $text, string $filename): void {
            $mp3 = $this->get_mp3($text);
            file_put_contents($filename, $mp3);
        }

        public function get_mp3(string $text): string {
            $payload = json_encode([
                'OutputFormat' => 'mp3',
                'Text' => $text,
                'VoiceId' => $this->voice,
            ]);

            $datestamp = new \DateTime("now", $this->utc_tz);
            $longdate  = $datestamp->format("Ymd\\THis\\Z");
            $shortdate = $datestamp->format("Ymd");

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

            $kDate    = hash_hmac('sha256', $shortdate, $kSecret, true);
            $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
            $kService = hash_hmac('sha256', 'polly', $kRegion, true);
            $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
            $signature = hash_hmac('sha256', $stringToSign, $kSigning);

            $authHeader = "AWS4-HMAC-SHA256 Credential={$this->apikey}/{$shortdate}/{$this->region}/polly/aws4_request, SignedHeaders={$signedHeaders}, Signature={$signature}";

            $curlHeaders = [];
            foreach ($headers as $k => $v) {
                $curlHeaders[] = $k . ": " . $v;
            }
            $curlHeaders[] = 'Authorization: ' . $authHeader;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://{$host}/v1/speech",
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('cURL Fehler: ' . curl_error($ch));
            }
            curl_close($ch);

            // MP3-Magics: "ID3" = 0x49 0x44 0x33
            if (substr(bin2hex($result), 0, 6) !== '494433') {
                throw new Exception("Polly Response kein MP3: " . substr($result, 0, 200));
            }

            return $result;
        }

        public function setVoice(string $voice): void { $this->voice = $voice; }
        public function setRegion(string $region): void {
            if (array_key_exists($region, $this->endpoint)) {
                $this->region = $region;
            } else {
                LOGWARN("voice_engines\\Polly.php: setRegion('$region') invalid, keeping '{$this->region}'.");
            }
        }
    }

    // ===== 4. MP3 erzeugen =====
    try {
        $polly = new POLLY_TTS([
            'apikey'    => $apikey,
            'secretkey' => $secretkey,
            'voice'     => $voice,
            'region'    => $region
        ]);

        $outputPath = rtrim($config['SYSTEM']['ttspath'], '/') . '/' . $filename . '.mp3';
        $polly->save_mp3($text, $outputPath);

        LOGOK("voice_engines\\Polly.php: MP3 file successfully saved at '$outputPath'.");
        return $filename;

    } catch (Exception $e) {
        LOGERR("voice_engines\\Polly.php: Failed to create MP3. Error: " . $e->getMessage());
        return false;
    }
}
