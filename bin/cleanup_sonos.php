#!/usr/bin/env php
<?php

require_once "loxberry_system.php";
require_once "loxberry_log.php";

register_shutdown_function('shutdown');

$log = LBLog::newLog( [ "name" => "Cronjobs", "stderr" => 1, "addtime" => 1 ] );

LOGSTART("Cleanup MP3 files");

$myConfigFolder = "$lbpconfigdir";								// get config folder
$myConfigFile = "sonos.cfg";									// get config file
$hostname = lbhostname();
echo'<PRE>';
// Parsen der Konfigurationsdatei
if (!file_exists($myConfigFolder.'/sonos.cfg')) {
	LOGCRIT('The file sonos.cfg could not be opened, please try again!');
	exit;
} else {
	$config = parse_ini_file($myConfigFolder.'/sonos.cfg', TRUE);
	if ($config === false)  {
		LOGERR('The file sonos.cfg could not be parsed, the file may be disrupted. Please check/save your Plugin Config or check file "sonos.cfg" manually!');
		exit(1);
	}
	LOGOK("Sonos config has been loaded");
}

$folderpeace = explode("/",$config['SYSTEM']['path']);
if ($folderpeace[3] != "data") {
	// wenn NICHT local dir als Speichermedium selektiert wurde
	#$MessageStorepath = $config['SYSTEM']['path']."/".$hostname."/tts/";
	$MessageStorepath = $config['SYSTEM']['path']."/tts/";
} else {
	// wenn local dir als Speichermedium selektiert wurde
	$MessageStorepath = $config['SYSTEM']['ttspath']."/";
}

// Set defaults if needed
$storageinterval = trim($config['MP3']['MP3store']);
$cachesize = !empty($config['MP3']['cachesize']) ? trim($config['MP3']['cachesize']) : "100";
$tosize = $cachesize * 1024 * 1024;
if(empty($tosize)) {
	LOGCRIT("The size limit is not valid - stopping operation");
	LOGDEB("Config parameter MP3/cachesize is {$config['MP3']['cachesize']}, tosize is '$tosize'");
	exit;
}
delmp3();

exit;


/**
/* Funktion : delmp3 --> löscht die hash5 codierten MP3 Dateien aus dem Verzeichnis 'messageStorePath'
/*
/* @param:  nichts
/* @return: nichts
**/

function delmp3() {
	global $config, $MessageStorepath, $storageinterval, $tosize, $cachesize, $storageinterval;
		
	LOGINF("Deleting oldest MP3 files to reach $cachesize MB...");

	$dir = $MessageStorepath;
    
	LOGDEB ("Directory: $dir");
	$allfiles = glob("$dir/*");
	$files = array();
	foreach($allfiles as $file)  {
		$fileType = substr($file, -3); 
		if ($fileType == "mp3")  {
			array_push($files, $file);
		}
	}
	// print_r($files);
	usort($files, function($a, $b) {
		return @filemtime($a) > @filemtime($b);
	});

	/******************/
	/* Delete to size */
	// First get full size
	$fullsize = 0;
	foreach($files as $file){
		if(!is_file($file)) {
			unset($files[$key]);
			continue;
		}
		$fullsize += filesize($file);
	}
	
	// Are we below the limit? Then nothing to do
	if ($fullsize < $tosize) {
		LOGINF("Current size $fullsize is below destination size $tosize");
		LOGOK ("Nothing to do, quitting");

	} else {
		
		// We need to delete
		$newsize = $fullsize;
		foreach($files as $file){
			$filesize = filesize($file);
			if ( @unlink($file) != false ) {
				LOGDEB(basename($file).' has been deleted');
				$newsize -= $filesize;
			} else {
				LOGWARN(basename($file).' could not be deleted');
			}
		
			// Check again after each file
			if ($newsize < $tosize) {
				LOGOK("New size $newsize reached destination size $tosize");
				break;
			}
		}
		
		// Check after all files
		if ($newsize > $tosize) {
			LOGERR("Used size $newsize is still greater than destination size $tosize - Something is strange.");
		}
			
	}
	LOGINF("Now check if MP3 files older x days should be deleted, too...");

	if ($storageinterval != "0") {

		LOGINF("Deleting MP3 files older than $storageinterval days...");

		/******************/
		/* Delete to time */
		$deltime = time() - $storageinterval * 24 * 60 * 60;
		foreach($files as $key => $file){
			if(!is_file($file)) {
				unset($files[$key]);
				continue;
			}
			$filetime = @filemtime($file);
			LOGDEB("Checking file ".basename($file)." (".date(DATE_ATOM, $filetime).")");
			if($filetime < $deltime) {
				if ( @unlink($file) != false )
					LOGINF(basename($file).' has been deleted');
				else
					LOGWARN(basename($file).' could not be deleted');
			}
		}
	} else { 

		LOGINF("MP3 Files should be stored forever. Nothing to do here.");

	}
		
	LOGOK("Sonos file reduction has been completed");
    return; 	 
}

function shutdown()
{
	global $log;
	$log->LOGEND("Cleanup finished");
	
}


