<?php

function udp_send_mem($msnr, $udpport, $prefix, $params)
{
	global $mem_sendall_sec;
	global $mem_sendall;
	
	$memfile = "/run/shm/msudp_mem_{$msnr}_{$udpport}.json";
	
	if(empty($udpport) || $udpport > 65535) {
		LOGERR("UDP port $udpport invalid or not defined");
		return 0;
	}
	// lade file aus RAM
	if(file_exists($memfile)) {
		// echo "Read file\n";
		$jsonstr = file_get_contents($memfile);
		if(isset($jsonstr)) {
			$mem = json_decode($jsonstr, true);
		}
	}
	
	// wenn kein timestamp im RAM file dann hinzufügen
	if(empty($mem['Main']['timestamp'])) {
		$mem['Main']['timestamp'] = time();
	}
	
	// wenn timestamp von RAM file kleiner als jetzige Zeit minus Wert von $mem_sendall_sec) dann setze 1 Sekunde
	if( $mem['Main']['timestamp'] < (time()-$mem_sendall_sec) ) {
		$mem_sendall = 1;
	}
	
	if (empty($mem['Main']['lastMSRebootCheck']) || $mem['Main']['lastMSRebootCheck'] < (time()-300)) {
		// Check if Miniserver was rebooted after 5 minutes
		$mem['Main']['lastMSRebootCheck'] = time();
		list($newtxp, $code) = rest_call($msnr, "/dev/lan/txp");
		// echo "newtxp: $newtxp Code: $code\n";
		if($code == "200" && ( !isset($mem['Main']['MSTXP']) || $newtxp < $mem['Main']['MSTXP']) ) {
			$mem_sendall = 1;
			$mem['Main']['MSTXP'] = $newtxp;
		}
	}
	// echo "mem_sendall: $mem_sendall\n";
	
	if( $mem_sendall <> 0 ) {
		$mem['Params'] = Null;
		$mem['Main']['timestamp'] = time();
		$mem_sendall = 0;
	}
	
	$newparams = array();
	foreach ($params as $param => $value) {
		if( !isset($mem['Params'][$param]) || $mem['Params'][$param] !== $value ) {
			
			// Param has changed
			// echo "Param changed: $param = $value\n";
			$newparams[$param] = $value;
		}
	}
		
	if(!empty($newparams)) {
		$udpres = ms_udp_send($msnr, $udpport, $prefix, $newparams);
		if ($udpres != null) {
			if(!isset($mem['Params'])) {
				$mem['Params'] = array();
			}
			$mem['Params'] = array_merge($mem['Params'], $newparams);
			// array_push($mem['Params'], $newparams);
			//echo "AFTER:\n";
			//echo var_dump($mem);
			$jsonstr = json_encode( $mem, JSON_PRETTY_PRINT, 20);
			file_put_contents($memfile, $jsonstr);
			
		}
	}
	#return $udpres;
}



// msudp_send
function ms_udp_send($msnr, $udpport, $prefix, $params)
{
	global $udpsocket;
	
	if(empty($udpport) || $udpport > 65535) {
		LOGERR("UDP port $udpport invalid or not defined");
		return 0;
	}
	
	$ms = LBSystem::get_miniservers();
	if (!isset($ms[$msnr])) {
		LOGERR("Miniserver $msnr not defined");
		return 0;
	}
	if (!empty($prefix)) {
		$prefix = "$prefix: ";
	} else {
		$prefix = "";
	}
	
	// Handle socket
	if (!isset($udpsocket)) {
		$udpsocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if($udpsocket == NULL) {
			LOGERR("Could not create udp socket: " . socket_last_error($udpsocket));
			return 0;
		}
	}
	
	// Handle sending a raw string
	if(!is_array($params)) {
		$message = substr($prefix.$params, 0, 250);
		$udpresp = _udp_send($udpsocket, $message, $ms[$msnr]['IPAddress'], $udpport);
		if(!empty($udpresp)) {
			return 0;
		} else {
			return "OK";
		}
	}
	// Handle sending multiple values
	$parinline = 0;
	$udperror = 0;
	$line = "";
	foreach ($params as $param => $value) {
		// echo "Param: $param Value $value\n";
		$parinline++;
		$oldline = $line;
		$line .= $param . '@' . $value . ' ';
		// echo "Line: $line\n";
		$currlen = strlen($prefix) + strlen($line);
		if ($parinline == 1 && $currlen > 220) {
			// If this is the first parameter and it is to long - skip
			LOGERR("msudp_send: Line with one parameter is too long. Parameter $param Value $value skipped.");
			$line = $oldline;
			$udperror = 1;
			continue;
		}
		if ($currlen > 220) {
			// If we've reached the max length, send the old line
			$message = $prefix.$oldline;
			$udpresp = _udp_send($udpsocket, $message, $ms[$msnr]['IPAddress'], $udpport);
			if(!empty($udpresp)) {
				$udperror = 1;
			}
			$line = $param . '@' . $value . ' ';
			$parinline = 1;
		}
	}
	
	// Send the rest if $line has items
	if (!empty($line)) {
		$message = $prefix.$line;
		$udpresp = _udp_send($udpsocket, $message, $ms[$msnr]['IPAddress'], $udpport);
		if(!empty($udpresp)) {
			$udperror = 1;
		}
	}
	
	// Return
	if($udperror != 0) {
		return Null;
	} else {
		return "OK";
	}
}


// rest_call
function rest_call($msnr, $command) 
{
	$ms = LBSystem::get_miniservers();
	if (!isset($ms[$msnr])) {
		error_log("Miniserver $msnr not defined\n");
		return array (null, 601, null);
	}
	
	$mscred = $ms[$msnr]['Credentials'];
	$msip = $ms[$msnr]['IPAddress'];
	$msport = $ms[$msnr]['Port'];
	
	$url = "http://$mscred@$msip:$msport" . $command;
	
	// echo "URL: $url\n";
	
	$xmlresp = @simplexml_load_file($url);
	if ($xmlresp === false) {
		// echo "Errors occured\n";
		$errors = libxml_get_errors();
		LOGERR("rest_call: An error occured loading the XML:");
		foreach($errors as $error) {
			LOGERR(display_xml_error($error, $xmlresp));
		}
		return array (null, 500, null);
	}
	
	$value = (string)$xmlresp->attributes()->value;
	$code = (string)$xmlresp->attributes()->Code;
	
	return array ($value, $code, $xmlresp);
	
}


// http_send
function http_send($msnr, $inputs, $value = null)
{
	
	$ms = LBSystem::get_miniservers();
	if (!isset($ms[$msnr])) {
		LOGERR("Miniserver $msnr not defined\n");
		return;
	}
	
	if(!is_array($inputs)) {
		if($value === null) {
			LOGERR("http_send: Input string provided, but value missing");
			return;
		}
		// echo "Input is flat\n";
		$inputs = [ $inputs => $value ];
		$input_was_string = true;
	}
	
	foreach ($inputs as $input => $val) {
		// echo "Sending param: $input = $val \n";
		list($respvalue, $respcode) = rest_call($msnr, '/dev/sps/io/' . rawurlencode($input) . '/' . rawurlencode($val)); 
		// echo "Responseval: $respvalue Respcode: $respcode\n";
		if($respcode == 200) {
			$response[$input] = $respvalue;
		} else {
			$response[$input] = null;
		}
	}
	
	if (isset($input_was_string)) {
		
		return array_values($response)[0];
	} else {
		return $response;
	}
}

// ms_send_mem
function ms_send_mem($msnr, $params, $value = null)
{
	global $mem_sendall_sec;
	global $mem_sendall;
	
	$memfile = "/run/shm/mshttp_mem_{$msnr}.json";
	
	if(file_exists($memfile)) {
		// echo "Read file\n";
		$jsonstr = file_get_contents($memfile);
		if(isset($jsonstr)) {
			$mem = json_decode($jsonstr, true);
		}
	}
	
	if(empty($mem['Main']['timestamp'])) {
		$mem['Main']['timestamp'] = time();
	}
	
	if( $mem['Main']['timestamp'] < (time()-$mem_sendall_sec) ) {
		$mem_sendall = 1;
	}
	
	if ( empty($mem['Main']['lastMSRebootCheck']) || $mem['Main']['lastMSRebootCheck'] < (time()-300)) {
		// Check if Miniserver was rebooted after 5 minutes
		$mem['Main']['lastMSRebootCheck'] = time();
		list($newtxp, $code) = rest_call($msnr, "/dev/lan/txp");
		// echo "newtxp: $newtxp Code: $code\n";
		if($code == "200" && ( !isset($mem['Main']['MSTXP']) || $newtxp < $mem['Main']['MSTXP']) ) {
			$mem_sendall = 1;
			$mem['Main']['MSTXP'] = $newtxp;
		}
	}
	//echo "mem_sendall: $mem_sendall\n";
	
	if( $mem_sendall <> 0 ) {
		$mem['Params'] = Null;
		$mem['Main']['timestamp'] = time();
		$mem_sendall = 0;
	}
	
	if(!is_array($params)) {
		if($value === null) {
			LOGERR("ms_send_mem: Input string provided, but value missing");
			return;
		}
		// echo "Input is flat\n";
		$params = [ $params => $value ];
		$input_was_string = true;
	}
	
	
	$newparams = array();
	
	foreach ($params as $param => $value) {
		if( !isset($mem['Params'][$param]) || $mem['Params'][$param] !== $value ) {
			// Param has changed
			// echo "Param changed: $param = $value\n";
			$newparams[$param] = $value;
		}
	}
	
	if(!empty($newparams)) {
		$httpres = http_send($msnr, $newparams);
		if ($httpres != null) {
			if(!isset($mem['Params'])) {
				$mem['Params'] = array();
			}
			$mem['Params'] = array_merge($mem['Params'], $newparams);
			$jsonstr = json_encode( $mem, JSON_PRETTY_PRINT, 20);
			file_put_contents($memfile, $jsonstr);
		}
	}
	
	// We need to generate a response for all values if it came from ram
	foreach ($params as $param => $value) {
		if(isset($mem['Params'][$param])) {
			$httpres[$param] = $value;
		}
	}
	
	if (isset($input_was_string)) {
		return array_values($httpres)[0];
	} else {
		return $httpres;
	}
}


?>