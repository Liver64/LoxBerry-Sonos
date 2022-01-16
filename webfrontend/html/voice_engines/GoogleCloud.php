<?php
function t2s($textstring, $filename)

{
	global $config, $errortext, $sPassword, $errorvoice, $lbpplugindir, $lbhomedir, $errorlang;
		
		#echo $errortext;
		#echo '<br>';
		#echo $errorvoice;
		#echo '<br>';
		#echo $errorlang;
		$sFilename = $lbhomedir.'/webfrontend/html/plugins/'.$lbpplugindir.'/system/service.dat';
				
		if (isset($_GET['lang'])) {
			$language = $_GET['lang'];
		} else {
			$language = $config['TTS']['messageLang'];
		}
		
		if (isset($_GET['voice'])) {
			$voice = $_GET['voice'];
			$language = substr($voice, 5); 
		} else {
			$voice = $config['TTS']['voice'];
		}
		
		if (isset($errorvoice)) {
			$language = $errorlang;
			$voice = $errorvoice;
			$textstring = $errortext;
			$speech_api_key = OpenSSLFile::decrypt($sFilename, $sPassword);
			LOGGING("voice_engines\googleCloud.php: 'nextradio' errormesssage has been announced", 6);
		} else {
			$speech_api_key = $config['TTS']['API-key'];
		}
		#echo $voice;
		
								  		
		LOGGING("voice_engines\googleCloud.php: Google Cloud TTS has been successful selected", 7);	

		$params = [
			"audioConfig"=>[
				"audioEncoding"=>"MP3"
			],
			"input"=>[
				"text"=>$textstring
			],
			"voice"=>[
				"languageCode"=> $language,
				"name" => $voice
			]
		];
		$data_string = json_encode($params);
		$url = 'https://texttospeech.googleapis.com/v1/text:synthesize';

		$handle = curl_init($url);

		curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "POST"); 
		curl_setopt($handle, CURLOPT_POSTFIELDS, $data_string);  
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($handle, CURLOPT_HTTPHEADER, [                                                                          
			'Content-Type: application/json',                                                                                
			'Content-Length: ' . strlen($data_string),
			'X-Goog-Api-Key: ' . $speech_api_key
			]                                                                       
		);
		$response = curl_exec($handle);            
		$responseDecoded = json_decode($response, true);  
		curl_close($handle);
		#print_r($responseDecoded);
		
		if (array_key_exists('audioContent', $responseDecoded)) {
			# Speicherort der MP3 Datei
			$file = $config['SYSTEM']['ttspath'] ."/". $filename . ".mp3";
			file_put_contents($file, base64_decode($responseDecoded['audioContent']));  
			LOGGING('voice_engines\googleCloud.php: The text has been passed to googleCloud engine for MP3 creation',5);
			return ($filename); 	
		} else {
			# Error handling		
			LOGGING('voice_engines\googleCloud.php: '.$responseDecoded['error']['message'],3);
			exit(1);
		}

		LOGGING('voice_engines\googleCloud.php: Something went wrong!',5);
		return;
}




