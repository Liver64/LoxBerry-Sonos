#!/usr/bin/env php
<?php
	
	require_once "loxberry_system.php";
	require_once "loxberry_log.php";

	$tmp_error = "/run/shm/errorMP3Stream";							// path/file for error message
	$myConfigFolder = "$lbpconfigdir";								// get config folder
	$myConfigFile = "sonos.cfg";									// get config file
	$hostname = lbhostname();
	
	global $config, $result, $tmp_error;
	
	// Parsen der Konfigurationsdatei
	if (!file_exists($myConfigFolder.'/sonos.cfg')) {
		echo "<OK> The file sonos.cfg could not be opened, please try again!".PHP_EOL;
		#echo "<br>";
		exit(1);
	} else {
		$config = parse_ini_file($myConfigFolder.'/sonos.cfg', TRUE);
		if ($config === false)  {
			echo "<ERROR> The file sonos.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file 'sonos.cfg' manually!".PHP_EOL;
			#echo "<br>";
			exit(1);
		}

	}

	
		@unlink($tmp_error);
		if (!isset($config['RADIO']) or empty($config['RADIO']))  {
			echo "<INFO> Nothing to do :-)".PHP_EOL;
			exit;
		} else {
			$a = $config['RADIO'];
		}
		$e = "0";
		foreach ($a as $v1) {
			foreach ($v1 as $v2) {
				$findmy   = ',';
				$b = substr($v2, strpos($v2, $findmy) + 1, strlen($v2));
				if (substr($b, -3) === "m3u")   {
					$c = file_get_contents($b);
					getMp3StreamTitle($c);
					if (getMp3StreamTitle($c) === false)   {
						$fh = fopen($tmp_error, "w");
						fwrite($fh, "Your URL ".$b." from your Radio favorites is invalid. Please checkPlease check your entries!".PHP_EOL);
						fclose($fh);
						$e = "1";
						echo "<ERROR> Your URL ".$b." from your Radio favorites is invalid :-( Please check your entries!".PHP_EOL;
						notify( LBPPLUGINDIR, "POSTUPGRADE", "Your URL ".$b." from your Radio favorites is invalid :-( Please check your Radio favorites", "error");
					}
				} else {
					getMp3StreamTitle($b);
					if (getMp3StreamTitle($b) === false)   {
						$fh = fopen($tmp_error, "w");
						fwrite($fh, "Your URL ".$b." from your Radio favorites is invalid. Please check".PHP_EOL);
						fclose($fh);
						$e = "1";
						echo "<ERROR> Your URL ".$b." from your Radio favorites is invalid :-( Please check your entries!".PHP_EOL;
						notify( LBPPLUGINDIR, "POSTUPGRADE", "Your URL ".$b." from your Radio favorites is invalid :-( Please check your Radio favorites", "error");
					}
				}
			}
		}
		if ($e == "0")  {
			echo "<OK> All Radio favorites are valid".PHP_EOL;
			echo "<INFO> End of Radio favorites validation.";
		} else {
			echo "<WARNING> Your Radio favorites require to be updated!".PHP_EOL;
			echo "<INFO> End of Radio favorites validation.";
		}

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


