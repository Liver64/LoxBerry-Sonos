<?php
function t2s($textstring, $filename)

{
	
	global $config, $voice, $urlvoice, $audio, $apikey, $filename, $textstring, $modelid, $lbpplugindir, $lbhomedir, $stability, $similarity_boost, $style, $use_speaker_boost, $jsondata;
	
	echo "<PRE>";
	
	# check if API key exists
	if (isset($config['TTS']['apikey']))    {
		$apikey = $config['TTS']['apikey'];
	}
	
	# set Variables
	$audio = "mp3_44100_128";
	$modelid = "eleven_multilingual_v2";
	$stability = "0.5";
	$similarity_boost = "0.75";
	$style = "0";
	$use_speaker_boost = true;

	LOGOK("voice_engines/ElevenLabs.php: ElevenLabs has been selected");	
	
	# get voice even from config or URL
	if (isset($_GET['voice'])) {
		$urlvoice = $_GET['voice'];
		$voice = getVoices($urlvoice);
		LOGDEB("voice_engines/ElevenLabs.php: Voice '".$urlvoice."' has been adopted from URL");	
	} else {
		$voice = $config['TTS']['voice'];
	}
	//getModels();
	text2speech();

}


/**
* Function : text2speech --> generate Text-to-speech
*
* @param: 
* @return: 
**/

function text2speech()     {
	
	global $config, $apikey, $audio, $modelid, $voice, $textstring, $filename, $similarity_boost, $stability, $style, $use_speaker_boost, $jsondata;
	
	$curl = curl_init();

	curl_setopt_array($curl, [
	  CURLOPT_URL => "https://api.elevenlabs.io/v1/text-to-speech/".$voice."?output_format=".$audio."",
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 5,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "POST",
	  CURLOPT_POSTFIELDS => "{\n  \"model_id\": \"".$modelid."\",
							  \n  \"text\": \"".$textstring."\",
							  \n  \"voice_settings\": {
								  \n    \"similarity_boost\": ".$similarity_boost.",
								  \n    \"stability\": ".$stability.",
								  \n    \"style\": ".$style.",
								  \n    \"use_speaker_boost\": ".$use_speaker_boost."
								  \n  }
								  \n}",
	  CURLOPT_HTTPHEADER => ["Content-Type: application/json", "xi-api-key: ".$apikey.""],
	]);

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);
	
	$notify = json_decode($response, true);
	
	# check if notify exist, then exit
	if (isset($notify['detail']))   {
		LOGERR("voice_engines/ElevenLabs.php: Error status: ".$notify['detail']['status'].", Error message: ".$notify['detail']['message']);
		#@unlink($config['SYSTEM']['ttspath'] ."/". $filename . ".mp3");
		exit;
	}

	if ($err) {
	  #echo "cURL Error #:" . $err;
	  LOGERR("voice_engines/ElevenLabs.php: cURL Error #:" . $err);
	} else {
	  $file = $config['SYSTEM']['ttspath'] ."/". $filename . ".mp3";
	  file_put_contents($file, $response);  
	  LOGOK("voice_engines/ElevenLabs.php: MP3 File has been successful saved.");
	}
}


	
/**
* Function : getVoices --> get all available voices
*
* @param: $voice
* @return: voice_id
**/	
	
function getVoices($urlvoice)     {	

	global $apikey, $voice, $urlvoice; 
	
	$curl = curl_init();

	curl_setopt_array($curl, [
	  CURLOPT_URL => "https://api.elevenlabs.io/v1/voices",
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "GET",
	  CURLOPT_HTTPHEADER => ["xi-api-key: ".$apikey.""],
	]);

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
	  LOGERR("voice_engines/ElevenLabs.php: cURL Error #:" . $err);
	} else {
	  $raw = json_decode($response, true);
	  $voices = $raw['voices'];
	  $id = array_multi_search($urlvoice, $voices, $sKey = "");
	  if (empty($id))    {
		//echo("voice_engines/ElevenLabs.php: Entered voice '".$urlvoice."' does not exist. Please correct your URL!");
		LOGERR("voice_engines/ElevenLabs.php: Entered voice '".$urlvoice."' does not exist. Please correct your URL!");
		exit;
	  } else {
		$voice_id = $id[0]['voice_id'];
		return $voice_id;
	  }
	}
}	
	
/**
* Function : getModels --> get available languages
*
* @param: API key
* @return: 
**/

function getModels()    {
	
	global $apikey;
	
	$curl = curl_init();

	curl_setopt_array($curl, [
	  CURLOPT_URL => "https://api.elevenlabs.io/v1/models",
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "GET",
	  CURLOPT_HTTPHEADER => ["xi-api-key: ".$apikey.""],
	]);

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
	  echo "cURL Error #:" . $err;
	  LOGERR("voice_engines/ElevenLabs.php: cURL Error #:" . $err);
	} else {
	  $raw = json_decode($response, true);
	  $lang = $raw[0]['languages'];
	  print_r($lang);
	}

	
}

?>



