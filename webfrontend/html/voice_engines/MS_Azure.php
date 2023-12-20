<?php

/**
* Copyright (c) Microsoft Corporation
* All rights reserved. 
* MIT License
* Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the ""Software""), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
* The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
* THE SOFTWARE IS PROVIDED *AS IS*, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
**/	


function t2s($textstring, $filename)  
{
	
	global $config;

	// Note: new unified SpeechService API key and issue token uri is per region
	// New unified SpeechService key
	// Paid: https://go.microsoft.com/fwlink/?LinkId=872236
	// Preis Übersicht: https://azure.microsoft.com/de-de/pricing/details/cognitive-services/speech-services/


	// User to create Azure account for "westeurope" to get Azure working


	$region = $config['TTS']['regionms']; //"westeurope";
	$apiKey = $config['TTS']['apikey'];
	$lang = $config['TTS']['messageLang'];
	
	$AccessTokenUri = "https://".$region.".api.cognitive.microsoft.com/sts/v1.0/issueToken";

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
		LOGERR("'Sonos: voice_engines\MS_Azure.php: Problem with $AccessTokenUri, $php_errormsg");
		#throw new Exception("Problem with $AccessTokenUri, $php_errormsg");
	} else {
	   #echo "Access Token: ". $access_token. "<br>";
		$ttsServiceUri = "https://".$region.".tts.speech.microsoft.com/cognitiveservices/v1";
	
	   //$SsmlTemplate = "<speak version='1.0' xml:lang='en-us'><voice xml:lang='%s' xml:gender='%s' name='%s'>%s</voice></speak>";
	   $doc = new DOMDocument();
	   $root = $doc->createElement( "speak" );
	   $root->setAttribute( "version" , "1.0" );
	   $root->setAttribute( "xml:lang" , substr($lang,0,5));
	   $voice = $doc->createElement( "voice" );
	   $voice->setAttribute( "xml:lang" , substr($lang,0,5));
	   #$voice->setAttribute( "xml:gender" , "" );
	   $voice->setAttribute( "name" , $lang); // Short name for "Microsoft Server Speech Text to Speech Voice (en-US, Guy24KRUS)"

	   $text = $doc->createTextNode($textstring);
	   $voice->appendChild( $text );
	   $root->appendChild( $voice );
	   $doc->appendChild( $root );
	   $data = $doc->saveXML();

	   #echo "tts post data: ". $data . "<br>";
	   $options = array(
		'http' => array(
			'header'  => "Content-type: application/ssml+xml\r\n" .
						"X-Microsoft-OutputFormat: audio-48khz-192kbitrate-mono-mp3\r\n" .
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
	
	LOGGING("voice_engines\MS_Azure.php: Microsoft TTS has been successful selected", 7);	
	
	$file = $config['SYSTEM']['ttspath'] ."/". $filename . ".mp3";
    // get the wave data
    $result = file_get_contents($ttsServiceUri, false, $context);
	file_put_contents($file, $result);
	LOGGING('voice_engines\MS_Azure.php: The text has been passed to Microsoft engine for MP3 creation',5);
	
    if (!$result) {
		LOGERR("'Sonos: voice_engines\MS_Azure.php: No File created --> Problem with $ttsServiceUri, $php_errormsg");
        #throw new Exception("Problem with $ttsServiceUri, $php_errormsg");
    } else {
        LOGGING('voice_engines\MS_Azure.php: Everything went well during TTS creation!',5);
    }
	return;
	}
}

?>  
