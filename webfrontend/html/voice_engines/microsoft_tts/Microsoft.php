<?php

function t2s($messageid){
global $messageid, $words, $config, $filename, $fileolang, $voice, $accesskey, $secretkey, $fileo;

$engine = $config['TTS']['t2s_engine'];
$mpath = $config['SYSTEM']['messageStorePath'];
speak();

function speak() {}
$AccessTokenUri = "https://api.cognitive.microsoft.com/sts/v1.0/issueToken";
// Note: The way to get api key:
// Free: https://www.microsoft.com/cognitive-services/en-us/subscriptions?productId=/products/Bing.Speech.Preview
// Paid: https://portal.azure.com/#create/Microsoft.CognitiveServices/apitype/Bing.Speech/pricingtier/S0
$apiKey = "beb8fce14cbc47668d8c567e90e8fa13";
$ttsHost = "https://speech.platform.bing.com";
// use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        'header'  => "Ocp-Apim-Subscription-Key: ".$apiKey."\r\n" .
        "content-length: 0\r\n",
        'method'  => 'POST',
    ),
);
$context  = stream_context_create($options);
//get the Access Token
$access_token = file_get_contents($AccessTokenUri, false, $context);
if (!$access_token) {
    throw new Exception("Problem with $AccessTokenUri, $php_errormsg");
  }
else{
   echo "Access Token: ". $access_token. "<br>";
   $ttsServiceUri = "https://speech.platform.bing.com:443/synthesize";
   //$SsmlTemplate = "<speak version='1.0' xml:lang='en-us'><voice xml:lang='%s' xml:gender='%s' name='%s'>%s</voice></speak>";
   $doc = new DOMDocument();
   $root = $doc->createElement( "speak" );
   $root->setAttribute( "version" , "1.0" );
   $root->setAttribute( "xml:lang" , "en-us" );
   $voice = $doc->createElement( "voice" );
   $voice->setAttribute( "xml:lang" , "en-us" );
   $voice->setAttribute( "xml:gender" , "Female" );
   $voice->setAttribute( "name" , "Microsoft Server Speech Text to Speech Voice (en-US, ZiraRUS)" );
   $text = $doc->createTextNode( "This is a demo to call microsoft text to speech service in php." );
   $voice->appendChild( $text );
   $root->appendChild( $voice );
   $doc->appendChild( $root );
   $data = $doc->saveXML();
   echo "tts post data: ". $data . "<br>";
   $options = array(
    'http' => array(
        'header'  => "Content-type: application/ssml+xml\r\n" .
                    "X-Microsoft-OutputFormat: riff-16khz-16bit-mono-pcm\r\n" .
                    "Authorization: "."Bearer ".$access_token."\r\n" .
                    "X-Search-AppId: 07D3234E49CE426DAA29772419F436CA\r\n" .
                    "X-Search-ClientID: 1ECFAE91408841A480F00935DC390960\r\n" .
                    "User-Agent: TTSPHP\r\n" .
                    "content-length: ".strlen($data)."\r\n",
        'method'  => 'POST',
        'content' => $data,
        ),
    );
    $context  = stream_context_create($options);
    // get the wave data
    $result = file_get_contents($ttsServiceUri, false, $context);
	# added
	$file = $mpath . $fileolang . ".mp3";
	file_put_contents($file, $result);
	# end added
    if (!$result) {
        throw new Exception("Problem with $ttsServiceUri, $php_errormsg");
      }
    else{
        echo "Wave data length: ". strlen($result);
    }
}
}
$messageid = $fileolang;
return ($messageid);
}

?>  