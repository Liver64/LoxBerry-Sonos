<?php

function t2s($textstring, $filename)

// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an ResponsiveVoice und 
// speichert das zurückkommende file lokal ab

{
	global $config, $pathlanguagefile, $filename;
	
	$Rkey = "WQAwyp72";		// ResponsiveVoice Key
	$file = "respvoice.json";
	$url = $pathlanguagefile."".$file;
	$textstring = urlencode($textstring);
	$valid_languages = File_Get_Array_From_JSON($url, $zip=false);
	
		if (isset($_GET['lang'])) {
			$language = $_GET['lang'];
			$isvalid = array_multi_search($language, $valid_languages, $sKey = "value");
			if (!empty($isvalid)) {
				$language = $_GET['lang'];
				LOGGING('T2S language has been successful entered',5);
			} else {
				LOGGING("The entered ResponsiveVoice language key is not supported. Please correct (see Wiki)!",3);
				exit;
			}
		} else {
			$language = $config['TTS']['messageLang'];
		}
						
		#####################################################################################################################
		# zu testen da auf Google Translate basierend (urlencode)
		# ersetzt Umlaute um die Sprachqualität zu verbessern
		# search = array('ä','ü','ö','Ä','Ü','Ö','ß','°','%20','%C3%84','%C4','%C3%9C','%FC','%C3%96','%F6','%DF','%C3%9F');
		# replace = array('ae','ue','oe','Ae','Ue','Oe','ss','Grad',' ','ae','ae','ue','ue','oe','oe','ss','ss');
		# words = str_replace($search,$replace,$textstring);
		#####################################################################################################################	

		# Speicherort der MP3 Datei
		$file = $config['SYSTEM']['ttspath'] ."/". $filename . ".mp3";
		
		LOGGING("ResponsiveVoice has been successful selected", 7);	
		
		# Übermitteln des strings an ResponsiveVoice
		$url = 'https://code.responsivevoice.org/getvoice.php?t='.$textstring.'&tl='.$language;
		$mp3 =  my_curl($url);
		file_put_contents($file, $mp3);
		LOGGING('The text has been passed to Responsive Voice for MP3 creation',5);
		return $filename;
		
						  	
}




function my_curl($url, $timeout=2, $error_report=FALSE)
{
	global $Rkey; 
		
    $curl = curl_init();
	// HEADERS FROM FIREFOX - APPEARS TO BE A BROWSER REFERRED BY GOOGLE
    $header[] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
    $header[] = "Cache-Control: max-age=0";
    $header[] = "Connection: keep-alive";
    $header[] = "Keep-Alive: 300";
    $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
    $header[] = "Accept-Language: en-us,en;q=0.5";
    $header[] = "Pragma: "; // browsers keep this blank.

    // SET THE CURL OPTIONS - SEE http://php.net/manual/en/function.curl-setopt.php
    curl_setopt($curl, CURLOPT_URL,            $url);
    curl_setopt($curl, CURLOPT_USERAGENT,      'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6');
    curl_setopt($curl, CURLOPT_HTTPHEADER,     $header);
    curl_setopt($curl, CURLOPT_REFERER,        'https://code.responsivevoice.org/responsivevoice.js?key='.$Rkey);
    curl_setopt($curl, CURLOPT_ENCODING,       'gzip,deflate');
    curl_setopt($curl, CURLOPT_AUTOREFERER,    TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_TIMEOUT,        $timeout);

    // RUN THE CURL REQUEST AND GET THE RESULTS
    $htm = curl_exec($curl);
    $err = curl_errno($curl);
    $inf = curl_getinfo($curl);
    curl_close($curl);

    // ON FAILURE
    if (!$htm)
    {
        // PROCESS ERRORS HERE
        if ($error_report)
        {
			LOGGING('CURL FAIL: $url TIMEOUT=$timeout, CURL_ERRNO=$err',3);
            #echo "CURL FAIL: $url TIMEOUT=$timeout, CURL_ERRNO=$err";
            #var_dump($inf);
        }
        return FALSE;
    }

    // ON SUCCESS
    return $htm;
}

?>

