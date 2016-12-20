<?php

class IvonaClient
{
    public $string;
    public $longDate;
    public $shortDate;
    public $IvonaURL = "https://tts.eu-west-1.ivonacloud.com";
    public $inputType = 'text%2Fplain';
    public $outputFormatCodec = 'MP3';
    public $outputFormatSampleRate = '22050';
    public $parametersRate = 'default';  //x-slow, slow, medium, fast, x-fast, default
    public $voiceLanguage = 'de-DE';
    public $voiceName = 'Marlene';
    public $xAmzAlgorithm = 'AWS4-HMAC-SHA256';
    public $xAmzSignedHeaders = 'host';
    public $xAmzDate;
    public $xAmzCredential;
	public $FileName;
	public $enableDebug = 1;
    public $useCache = 1;
    #public $CacheDir = '//opt/loxberry/data/plugins/sonos4lox/tts/';
			
	
    public function __construct()
    {
	$this->debug(__METHOD__." Created instance of ".__CLASS__);
        $this->setDate();
        $this->setCredential();
    }
    private function debug($message)
    {
	if ($this->enableDebug){
		syslog(LOG_DEBUG, $message);
	}
    }
	
	
    public function ListVoices($language=null, $gender=null)
    {
	$payloadArray = (object)array();
	if($language){
		$payloadArray->Voice["Language"] = $language;
	}
	if($gender){
		$payloadArray->Voice["Gender"] = $gender;
	}
	
	$obj = json_encode($payloadArray);
        $canonicalizedGetRequest = $this->getCanonicalRequest("ListVoices", $obj);
        $stringToSign = $this->getStringToSign($canonicalizedGetRequest);
        $signature = $this->getSignature($stringToSign);
	$url =  $this->IvonaURL . "/ListVoices";
	$postData = array();
	$postData[] = 'X-Amz-Date: '.$this->xAmzDate;
	$postData[] = 	'Authorization: ' . 'AWS4-HMAC-SHA256 Credential='.$this->xAmzCredential.',SignedHeaders=host,Signature='.$signature;
	$postData[] = 	'Content-Type: '. 'application/json';
	$postData[] = 	'Host: ' . 'tts.eu-west-1.ivonacloud.com';
	$postData[] = 	'User-Agent: ' . 'TestClient 1.0';
	$postData[] = 	'Expect:'; 
	
	$response = $this->reqPost($url, $postData, $obj);
	if ($response){
		return json_decode($response);
	} 
	return 0;
    }
	
	

    public function get($text, $params = null)
    {
	$payloadArray = (object)array();
	$payloadArray->Input["Data"] = $text;
	$payloadArray->Input["Type"] = "text/plain";
	
	$payloadArray->OutputFormat["Codec"] = $this->outputFormatCodec; 
	$payloadArray->OutputFormat["SampleRate"] = (int) $this->outputFormatSampleRate;
	
	if($params['Language']){ 
		$payloadArray->Voice["Language"] = $params['Language'];
	} else {
		$payloadArray->Voice["Language"] = $this->voiceLanguage;
	}
	if($params['VoiceName']){ 
		$payloadArray->Voice["Name"] = $params['VoiceName'];
	} else {
		$payloadArray->Voice["Name"] = $this->voiceName; 
	}
	if($params['VoiceRate']){
		$payloadArray->Parameters["Rate"] = $params['VoiceRate'];
 	} else {
		$payloadArray->Parameters["Rate"] = $this->parametersRate;
	}
	$obj = json_encode($payloadArray);
        $canonicalizedGetRequest = $this->getCanonicalRequest("CreateSpeech", $obj);
        $stringToSign = $this->getStringToSign($canonicalizedGetRequest);
        $signature = $this->getSignature($stringToSign);
	$url =  $this->IvonaURL . "/CreateSpeech";
	$postData = array();
	$postData[] = 	'X-Amz-Date: '.$this->xAmzDate;
	$postData[] = 	'Authorization: ' . 'AWS4-HMAC-SHA256 Credential='.$this->xAmzCredential.',SignedHeaders=host,Signature='.$signature;
	$postData[] = 	'Content-Type: '. 'application/json';
	$postData[] = 	'Host: ' . 'tts.eu-west-1.ivonacloud.com';
	$postData[] = 	'User-Agent: ' . 'TestClient 1.0';
	$postData[] = 	'Expect:'; 
	
	$response = $this->reqPost($url, $postData, $obj);
	if ($response){
		return $response;
	} 
	return 0;
    }
	
	
	
    public function getSave($text, $params = null)
    {
	if($params['Language']){ 
		$lang = $params['Language'];
	} else {
		$lang = $this->voiceLanguage;
	}
	if($params['VoiceName']){ 
		$name = $params['VoiceName'];
	} else {
		$name = $this->voiceName; 
	}
	if($params['VoiceRate']){
		$rate = $params['VoiceRate'];
 	} else {
		$rate = $this->parametersRate;
	}
	if($params['CacheDir']){
		$mpath = $params['CacheDir'];
 	} else {
		$mpath = $this->CacheDir;
	}
	$fileolang = $params['FileName'];
	$this->debug(__METHOD__ . ' Input params are: '. print_r($params,true));
	// $content = $this->get($text);
	$uniqueString = urldecode($text) . '_' . $this->outputFormatCodec . '_' . $this->outputFormatSampleRate . '_' . $name . '_' . $lang . '_' . $rate;
	$this->debug(__METHOD__ . ' Unique string of TTS is: '.$uniqueString);
	if($this->useCache == TRUE){
		if ($cached = $this->checkIfCached($uniqueString)){
			$this->debug(__METHOD__ . ' File already Cached: '.$cached);
			return $cached;
		}	
	} 
	if ($content = $this->get(urldecode($text), $params)){
		return $this->save($content, $uniqueString);
	} 
	$this->debug(__METHOD__ . ' Failed to get content of '.$uniqueString);
	return 0;
    }
	
	
	
    private function reqPost($url, $headers, $payload){
   	$ch2 = curl_init();
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch2, CURLOPT_URL, $url);
	curl_setopt($ch2, CURLOPT_POST, true);
	
	curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload);
	curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
	$response = curl_exec($ch2);
	if(!curl_errno($ch2))
	{
 		$info = curl_getinfo($ch2);
 		$this->debug(__METHOD__.' Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'] . " Response Code: ".$info['http_code']);
	} else {
		$this->debug(__METHOD__ . ' CURL returned ERROR:'. curl_error($ch2) . "Response: ". $response);
	}
	if($info['http_code'] !=200){
		$this->debug(__METHOD__ . ' CURL returned ERROR:'. curl_error($ch2) . "Response: ". $response);
		return false;
	}
	return $response;
    }
	
	
    public function setDate()
    {
        $this->longDate = gmdate('Ymd\THis\Z', time());
        $this->shortDate = substr($this->longDate, 0, 8);
        $this->xAmzDate = $this->longDate;
    }
	
	
    public function setCredential()
    {
		global $config;
		$akey = $config['TTS']['API-key'];
		
        $this->xAmzCredential = $akey . "/" . $this->shortDate . "/eu-west-1/tts/aws4_request";
    }
	
	
    private function getCanonicalRequest($service, $payload=null)
    {
        $canonicalizedGetRequest =
            "POST" .
            "\n/$service" .
            "\n" .
            "\nhost:tts.eu-west-1.ivonacloud.com" .
            "\n" .
            "\nhost" .
            "\n" . hash("sha256", $payload);
        return $canonicalizedGetRequest;
    }
	
	
    private function getStringToSign($canonicalizedGetRequest)
    {
        $stringToSign = "AWS4-HMAC-SHA256" .
            "\n$this->longDate" .
            "\n$this->shortDate/eu-west-1/tts/aws4_request" .
            "\n" . hash("sha256", $canonicalizedGetRequest);
        return $stringToSign;
    }
	
	
	
    private function getSignature($stringToSign)
    {
		global $config;
		$skey = $config['TTS']['secret-key'];
        $dateKey = hash_hmac('sha256', $this->shortDate, "AWS4" . $skey, true);
        $dateRegionKey = hash_hmac('sha256', "eu-west-1", $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', "tts", $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', "aws4_request", $dateRegionServiceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        return $signature;
    }
    
	
	private function getFileName($string) // not in use
    {
	$fileName = hash('md5', $string);
	if ($this->outputFormatCodec == "MP3"){
		$fileName .= '.mp3';
	} elseif ($this->outputFormatCodec == "OGG") {
		$fileName .= '.ogg';
	}
	
	return $fileName;
    }
	
	
	
    private function checkIfCached($string)
    {
	global $fileolang, $config, $mpath;
	$fileName = $fileolang;
	$mpath = $config['SYSTEM']['messageStorePath'];
        $savePath = $mpath . ''.$fileName.'.'.$this->outputFormatCodec;
        $dbPath = $mpath . ''.$fileName.'.'.$this->outputFormatCodec;
	if (file_exists($dbPath))
	{
		$this->debug(__METHOD__ . " File $dbPath is there! ");
		return $dbPath;
	}
	$this->debug(__METHOD__ . " File $fileName not there!");
	return FALSE;
    }
	
	
	
    private function save($resource, $string)
    {
	global $fileolang, $config, $mpath;
		$fileName = $fileolang;
		$mpath = $config['SYSTEM']['messageStorePath'];
		$savePath = $mpath . ''.$fileName.'.'.$this->outputFormatCodec;
        $dbPath = $mpath . ''.$fileName.'.'.$this->outputFormatCodec;
		file_put_contents($savePath, $resource);
		$this->debug(__METHOD__ . ' File saved:'. $dbPath);
        return $dbPath;
    }
}