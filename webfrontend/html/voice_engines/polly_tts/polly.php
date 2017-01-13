<?php
/**
 * Created by PhpStorm.
 * User: Shaq
 * Date: 06.01.2017
 * Time: 19:07
 */

require 'aws/aws-autoloader.php';

class PollyClient
{

    public $version = 'latest';
    public $region = 'eu-west-1';
    public $voiceName = 'Marlene';
    public $outputFormatCodec = 'mp3';
    public $outputFormatSampleRate = '22050';
    public $key;
    public $secret;
    public $longDate;
    public $shortDate;
    public $string;
    public $FileName;
    public $enableDebug = 1;
    public $useCache = 1;

    #public $CacheDir = '//opt/loxberry/data/plugins/sonos4lox/tts/';

    public function __construct()
    {
        $this->debug(__METHOD__ . " Created instance of " . __CLASS__);
        $this->setDate();
        $this->setCredential();
    }

    private function debug($message)
    {
        if ($this->enableDebug) {
            syslog(LOG_DEBUG, $message);
        }
    }

    /**
     * Funktiom zum Auflisten der verfügbaren Voices
     *
     * @return   $pollyClient
     */
    public function ListVoices()
    {
        $config = [
            'version' => $this->version,
            'region' => $this->region,
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret,
            ]
        ];

        $sdk = new Aws\Sdk($config);
        $pollyClient = $sdk->createPolly();

        if ($pollyClient){
            return $pollyClient;
        }
        return 0;
    }

    /**
     * Funktion zum Aufruf von Polly und der Generierung der TTS-Datei
     *
     * @param    string $text
     * @param    array $params
     *
     * @return   $result
     */
    public function get($text, $params = null)
    {
        if ($params['VoiceName']) {
            $voiceName = $params['VoiceName'];
        } else {
            $voiceName = $this->voiceName;
        }

        $config = [
            'version' => $this->version,
            'region' => $this->region,
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret,
            ]
        ];

        $sdk = new Aws\Sdk($config);
        $pollyClient = $sdk->createPolly();

        $result = $pollyClient->synthesizeSpeech([
            'OutputFormat' => $this->outputFormatCodec,
            'SampleRate' => $this->outputFormatSampleRate,
            'Text' => $text,
            'TextType' => 'text',
            'VoiceId' => $voiceName,
        ]);

        if ($result['AudioStream']) {
            return $result['AudioStream'];
        }
        return 0;
    }


    /**
     * Funktion
     *
     * @param    string $text
     * @param    array $params
     *
     * @return   $this->save()
     */
    public function getSave($text, $params = null)
    {
        if ($params['VoiceName']) {
            $name = $params['VoiceName'];
        } else {
            $name = $this->voiceName;
        }
        if ($params['CacheDir']) {
            $mpath = $params['CacheDir'];
        } else {
            $mpath = $this->CacheDir;
        }
        $fileolang = $params['FileName'];
        $this->debug(__METHOD__ . ' Input params are: ' . print_r($params, true));
        // $content = $this->get($text);
        $uniqueString = urldecode($text) . '_' . $this->outputFormatCodec . '_' . $this->outputFormatSampleRate . '_' . $name;
        $this->debug(__METHOD__ . ' Unique string of TTS is: ' . $uniqueString);
        if ($this->useCache == TRUE) {
            if ($cached = $this->checkIfCached($uniqueString)) {
                $this->debug(__METHOD__ . ' File already Cached: ' . $cached);
                return $cached;
            }
        }
        if ($content = $this->get(urldecode($text), $params)) {
            return $this->save($content, $uniqueString);
        }
        $this->debug(__METHOD__ . ' Failed to get content of ' . $uniqueString);
        return 0;
    }


    /**
     * Funktion zum Vorinitialisieren des Datums
     */
    public function setDate()
    {
        $this->longDate = gmdate('Ymd\THis\Z', time());
        $this->shortDate = substr($this->longDate, 0, 8);
        $this->xAmzDate = $this->longDate;
    }


    /**
     * Funktion zum Holen der Logindaten aus der Konfiguration
     */
    public function setCredential()
    {
        global $config;
        $akey = $config['TTS']['API-key'];
        $asecret = $config['TTS']['secret-key'];

        $this->key = $akey;
        $this->secret = $asecret;
    }


    /**
     * Funktion zum Prüfen, ob die Datei bereits im Cache vorliegt
     */
    private function checkIfCached($string)
    {
        global $fileolang, $config, $mpath;
        $fileName = $fileolang;
        $mpath = $config['SYSTEM']['messageStorePath'];
        $savePath = $mpath . '' . $fileName . '.' . $this->outputFormatCodec;
        $dbPath = $mpath . '' . $fileName . '.' . $this->outputFormatCodec;
        if (file_exists($dbPath)) {
            $this->debug(__METHOD__ . " File $dbPath is there! ");
            return $dbPath;
        }
        $this->debug(__METHOD__ . " File $fileName not there!");
        return FALSE;
    }


    /**
     * Funktion zum Speichern der generierten Datei
     */
    private function save($resource, $string)
    {
        global $fileolang, $config, $mpath;
        $fileName = $fileolang;
        $mpath = $config['SYSTEM']['messageStorePath'];
        $savePath = $mpath . '' . $fileName . '.' . $this->outputFormatCodec;
        $dbPath = $mpath . '' . $fileName . '.' . $this->outputFormatCodec;
        file_put_contents($savePath, $resource);
        $this->debug(__METHOD__ . ' File saved:' . $dbPath);
        return $dbPath;
    }
}