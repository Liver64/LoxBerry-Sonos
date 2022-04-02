#!/usr/bin/env php
<?php

	require_once "loxberry_system.php";
		
	$tmp_error = "/run/shm/s4lox_errorMP3Stream.json";							// path/file for error message
	$myConfigFolder = "$lbpconfigdir";								// get config folder
	$myConfigFile = "sonos.cfg";									// get config file
	$hostname = lbhostname();
	
	// Parsen der Konfigurationsdatei
	if (!file_exists($myConfigFolder.'/sonos.cfg')) {
		$fh = fopen($tmp_error, "w");
		fwrite($fh, "bin/url.php: The file sonos.cfg could not be opened, please try again!\n");
		fclose($fh);
		exit;
	} else {
		$config = parse_ini_file($myConfigFolder.'/sonos.cfg', TRUE);
		if ($config === false)  {
			$fh = fopen($tmp_error, "w");
			fwrite($fh, "bin/url.php: The file sonos.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file 'sonos.cfg' manually!\n");
			fclose($fh);
			exit(1);
		}
	}
	#function validatestreams()  {
		
		global $config, $result, $tmp_error;
		
		@unlink($tmp_error);
		$a = $config['RADIO'];
		$data = array();
		if (empty($a))  {
			exit;
		}
		foreach ($a as $v1) {
			foreach ($v1 as $v2) {
				$findmy   = ',';
				$b = substr($v2, strpos($v2, $findmy) + 1, strlen($v2));
				if (substr($b, -3) === "m3u")   {
					$c = file_get_contents($b);
					getMp3StreamTitle($c);
					if (getMp3StreamTitle($c) === false)   {
						$message = "Your URL ".$b." from your Radio favorites seems to be invalid. Please check";
						array_push($data, $message);
					}
				} else {
					getMp3StreamTitle($b);
					if (getMp3StreamTitle($b) === false)   {
						$message = "Your URL ".$b." from your Radio favorites seems to be invalid. Please check";
						array_push($data, $message);
					}
				}
			}
		}
		if (!empty($data))  {
			file_put_contents($tmp_error, json_encode($data));
		}
	#}


	function getMp3StreamTitle($url)  {
		
        $result = false;
        $icy_metaint = -1;
        $needle = 'StreamTitle=';
        $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.110 Safari/537.36';

        $opts = array(
            'http' => array(
                'method' => 'GET',
                'header' => 'Icy-MetaData: 1',
                'user_agent' => $ua
            )
        );

        $default = stream_context_set_default($opts);

        $stream = @fopen($url, 'r');

        if($stream && ($meta_data = stream_get_meta_data($stream)) && isset($meta_data['wrapper_data'])){
            foreach ($meta_data['wrapper_data'] as $header){
                if (strpos(strtolower($header), 'icy-metaint') !== false){
                    $tmp = explode(":", $header);
                    $icy_metaint = trim($tmp[1]);
                    break;
                }
            }
        }

        if($icy_metaint != -1)
        {
            $buffer = stream_get_contents($stream, 300, $icy_metaint);
			
            if(strpos($buffer, $needle) !== false)
            {
                $title = explode($needle, $buffer);
                $title = trim($title[1]);
                $result = substr($title, 1, strpos($title, ';') - 2);
            }
        }

        if($stream)
            fclose($stream); 
        return $result;
	}
	
?>


