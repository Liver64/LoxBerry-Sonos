<?php
class POLLY_TTS{
    private $utc_tz      = "";
    private $access_key  = "";
    private $secret_key  = "";
	private $region      = "eu-west-1"; 
    private $voice       = "";
    private $endpoint    = array( 'us-east-1' => 'polly.us-east-1.amazonaws.com',
                                  'us-east-2' => 'polly.us-east-2.amazonaws.com',
                                  'us-west-2' => 'polly.us-west-2.amazonaws.com',
                                  'eu-west-1' => 'polly.eu-west-1.amazonaws.com' );
								  
    public function __construct(){
        $this->utc_tz      = new \DateTimeZone( 'GMT' );
        $this->setCredential();
    }
    public function save_mp3($text, $filename) {
            $mp3 = $this->get_mp3($text);
            file_put_contents($filename, $mp3);
     }
	public function setCredential()
    {
        global $config;
        $akey = $config['TTS']['apikey'];
        $secretkey = $config['TTS']['secretkey'];

        $this->access_key = $akey;
        $this->secret_key = $secretkey;
    } 
	 
    public function get_mp3( $text )
    {
		global $voice;
		
		$payload = json_encode(array( ('OutputFormat')  => 'mp3',
                                      ('Text')          => urldecode($text),
                                      ('VoiceId')       => $voice ) 
									  );
        $datestamp                = new \DateTime( "now", $this->utc_tz );
        $longdate                 = $datestamp->format( "Ymd\\THis\\Z");
        $shortdate                = $datestamp->format( "Ymd" );
        $ksecret                  = 'AWS4' . $this->secret_key;
        $params                   = array( 'host'                 => $this->endpoint[$this->region],
                                           'content-type'         => 'application/json',
                                           'x-amz-content-sha256' => hash( 'sha256', $payload, false ),
                                           'x-amz-date'           => $longdate );
        $canonical_request        = $this->createCanonicalRequest( $params, $payload );
        $signed_request           = hash( 'sha256', $canonical_request );
        $sign_string              = "AWS4-HMAC-SHA256\n{$longdate}\n$shortdate/$this->region/polly/aws4_request\n" . $signed_request;
        $signature                = hash_hmac( 'sha256', $sign_string, hash_hmac( 'sha256', 'aws4_request', hash_hmac( 'sha256', 'polly', hash_hmac( 'sha256', $this->region, hash_hmac( 'sha256', $shortdate, $ksecret, true ) , true ) , true ), true ));
        $params['Authorization']  = "AWS4-HMAC-SHA256 Credential=" . $this->access_key . "/$shortdate/$this->region/polly/aws4_request, " .
                                    "SignedHeaders=content-type;host;x-amz-content-sha256;x-amz-date, " .
                                    "Signature=$signature";
        $params['content-length'] = strlen( $payload ) ;
        /*
         * Execute Crafted Request
         */
		#echo urldecode($text);
        $url    = "https://".$this->endpoint[$this->region]."/v1/speech";
        $ch     = curl_init();
        $curl_headers = array();
        foreach( $params as $p => $k )
            $curl_headers[] = $p . ": " . $k;
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false );
        // debug opts
        {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'rw+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            $result = curl_exec($ch); // raw result
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            #echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
        }
        // check for magic number of mp3 with ID3 tag
        if ( substr(bin2hex($result), 0, 6) != "494433" )
          throw new Exception("Response from Polly is no mp3: ".$result);
        return $result;
    }
    private function createCanonicalRequest( Array $params, $payload )
    {
        $canonical_request      = array();
        $canonical_request[]    = 'POST';
        $canonical_request[]    = '/v1/speech';
        $canonical_request[]    = '';
        $can_headers            = array(
          'host' => $this->endpoint[$this->region]
        );
        foreach( $params as $k => $v )
            $can_headers[ strtolower( $k ) ] = trim( $v );
        uksort( $can_headers, 'strcmp' );
        foreach ( $can_headers as $k => $v )
            $canonical_request[] = $k . ':' . $v;
        $canonical_request[] = '';
        $canonical_request[] = implode( ';', array_keys( $can_headers ) );
        $canonical_request[] = hash( 'sha256', $payload, false );
        $canonical_request = implode( "\n", $canonical_request );
        return $canonical_request;
    }
}


?>