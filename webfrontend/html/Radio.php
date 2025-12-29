<?php

/**
* Submodul: Radio (obsolete da neues TuneIN)
*
**/

/**
/* Funktion : radio --> lädt einen Radiosender aus den TuneIn "Meine Radiosender" in eine Zone/Gruppe
/*
/* @param: Sender                             
/* @return: nichts
**/

function radio(){
	
	global $sonos, $volume, $config, $sonoszone, $master;
			
	if(isset($_GET['radio'])) {
        $playlist = $_GET['radio'];		
	} elseif (isset($_GET['playlist'])) {
		$playlist = $_GET['playlist'];		
	} else {
		LOGGING("radio.php: No radio stations found.", 4);
    }
	$orgpl = $playlist;
	# initial load of favorite
	if(isset($_GET['playlist']) or isset($_GET['radio']))   {
		$playlist = mb_strtolower($playlist);	
	} else {
		LOGERR("radio.php: You have maybe a typo! Correct syntax is: &action=radioplaylist&playlist=<PLAYLIST> or <RADIO>");
		exit;
	}
	#$check_stat = getZoneStatus($master);
	#if ($check_stat != (string)"single")  {
	#	$sonos->BecomeCoordinatorOfStandaloneGroup();
	#	LOGGING("radio.php: Zone ".$master." has been ungrouped.",5);
	#}
	CreateMember();
	$sonos = new SonosAccess($sonoszone[$master][0]);
	$coord = $master;
	$roomcord = getRoomCoordinator($coord);
	$sonosroom = new SonosAccess($roomcord[0]); //Sonos IP Adresse
	$sonosroom->SetQueue("x-rincon-queue:".$roomcord[1]."#0");
    $radiolists = $sonos->BrowseContentDirectory("R:0/0","BrowseDirectChildren");
	print_r($radiolists);
	foreach ($radiolists as $val => $item)  {
		$radiolists[$val]['titlelow'] = mb_strtolower($radiolists[$val]['title']);
	}
	$found = array();
	foreach ($radiolists as $key)    {
		if ($playlist === $key['titlelow'])   {
			$playlist = $key['titlelow'];
			array_push($found, array_multi_search($playlist, $radiolists, "titlelow"));
		}
	}
	$playlist = urldecode($playlist);
	if (count($found) > 1)  {
		LOGERR ("radio.php: Your entered Radio Station '".$playlist."' has more then 1 hit! Please specify more detailed.");
		exit;
	} elseif (count($found) == 0)  {
		LOGERR ("radio.php: Your entered Radio Station '".$orgpl."' could not be found.");
		exit;
	} else {
		LOGGING("radio.php: Radio Station '".$found[0][0]["title"]."' has been found.", 5);
	}
	$countradio = count($found);
	if ($countradio > 0)   {
		$sonos->SetRadio(urldecode($found[0][0]["res"]),$found[0][0]["title"]);
		if(!isset($_GET['load']) and !isset($_GET['rampto'])) {
			$sonos->SetMute(false);
			$sonos->Stop();
			$sonos->SetVolume($volume);
			$sonos->Play();
			LOGOK("radio.php: Your Plugin Radio '".$found[0][0]["title"]."' has been successful loaded and is playing!");
		} else {
			LOGOK("radio.php: Your Plugin Radio '".$found[0][0]["title"]."' has only been successful loaded. Please execute play seperatelly");
		}
		RampTo();
		LOGGING("radio.php: Radio Station '".$found[0][0]["title"]."' has been loaded successful",6);
	} else {
		LOGGING("radio.php: Radio Station '".$found[0][0]["title"]."' could not be loaded. Please check your input.",3);
		#if(isset($_GET['member'])) {
		#	removemember();
		#	LOGINF ("radio.php: Member has been removed");
		#}
		exit;
	}
	#if(isset($_GET['member']))   {
	#	AddMemberTo();
	#	LOGGING("radio.php: Group Radio has been called.", 7);
	#}
}



/**
* Function: nextradio --> iterate through Radio Favorites (endless)
*
* @param: empty
* @return:
**/
function nextradio() {

    global $sonos, $config, $profile_selected, $master, $debug, $min_vol, $volume,
           $tmp_tts, $sonoszone, $tmp_error, $stst, $profile_details;

    $radioanzahl_check = count($config['RADIO']);
    if ($radioanzahl_check == 0) {
        LOGGING("radio.php: There are no Radio Stations maintained in the config. Pls update before using function NEXTRADIO or ZAPZONE!", 3);
        exit;
    }
    if (file_exists($tmp_tts)) {
        LOGGING("radio.php: Currently a T2S is running, we skip nextradio for now. Please try again later.", 6);
        exit;
    }
    VolumeProfiles();

    if (isset($_GET['member']) && isset($_GET['profile'])) {
        $master = GROUPMASTER;
    } elseif (isset($_GET['profile'])) {
        $master = GROUPMASTER;
    } else {
        $master = MASTER;
    }
	if (isset($_GET['member']) && trim($_GET['member']) !== '') {
        // CreateMember wurde i.d.R. schon in sonos.php aufgerufen – ist idempotent
        SyncGroupForPlaybackToMember();
    }

    $textan = "0";

    // -----------------------------
    // Fehler-Info aus error.json einmal am Tag ansagen
    // -----------------------------
    if (file_exists($tmp_error)) {
        $err = json_decode(file_get_contents($tmp_error));
        foreach ($err as $key => $value) {
            LOGWARN("Sonos: radio.php: " . $value);
        }
        check_date_once();
        if ($stst == "true") {
            select_error_lang();
            // HIER: echten Fehlertext aus select_error_lang() nutzen (global $errortext)
            global $errortext;
            say_radio_station($errortext);
            $textan = "1";
            LOGINF("Sonos: radio.php: Info of broken Radio URL has been announced once.");
        }
    }

    // Grundlegende Sonos-Infos
    $sonos = new SonosAccess($sonoszone[$master][0]);
    $playstatus = $sonos->GetTransportInfo();
    $radioname  = $sonos->GetMediaInfo();

    if (!empty($radioname["title"])) {
        $senderuri = $radioname["title"];
    } else {
        $senderuri = "";
    }

    $radio       = $config['RADIO']['radio'];
    ksort($radio);
    $radioanzahl = count($config['RADIO']['radio']);

    $radio_name     = [];
    $radio_adresse  = [];
    $radio_coverurl = [];

    foreach ($radio as $key) {
        $radiosplit = explode(',', $key);
        $radio_name[]    = $radiosplit[0];
        $radio_adresse[] = $radiosplit[1];
        if (array_key_exists(2, $radiosplit)) {
            $radio_coverurl[] = $radiosplit[2];
        } else {
            $radio_coverurl[] = "";
        }
    }

    // Aktuelle Position im Radio-Array
    $senderaktuell = array_search($senderuri, $radio_name);

    // Nächsten Sender bestimmen (Name, URL, Cover), aber NOCH NICHT laden
    if ($senderaktuell < ($radioanzahl) - 1) {
        $next_index = $senderaktuell + 1;
    } else {
        // Letzter Sender -> zurück zum ersten
        $next_index = 0;
    }

    $next_name  = $radio_name[$next_index];
    $next_url   = 'x-rincon-mp3radio://' . trim($radio_adresse[$next_index]);
    $next_cover = trim($radio_coverurl[$next_index]);

    // -----------------------------
    // Optional: Radio-Ansage vor dem Senderwechsel
    // -----------------------------
    $ann_volume = null;
    if ($config['VARIOUS']['announceradio'] == 1 && $textan == "0") {
        // Ansage "Radio <NextName>" mit Lautstärke-Logik aus say_radio_station()
        $ann_volume = say_radio_station('', $next_name);
    }

    // -----------------------------
    // Jetzt neuen Sender laden und starten
    // -----------------------------
    $coord = getRoomCoordinator($master);
    $sonos = new SonosAccess($coord[0]);
    $sonos->SetMute(false);

    // Lautstärke für den neuen Sender:
    if (isset($_GET['profile']) or isset($_GET['Profile'])) {
        // Profil-Lautstärke gewinnt
        $volume = $profile_details[0]['Player'][$master][0]['Volume'];
    } elseif ($ann_volume !== null) {
        // Wenn Ansage gelaufen ist, deren berechnete Lautstärke verwenden
        $volume = $ann_volume;
    } else {
        // Standard aus Config / sonoszone
        $volume = $sonoszone[$master][4];
    }

    // Jetzt neuen Sender setzen und starten
    $sonos->SetRadio($next_url, $next_name, $next_cover);
    $sonos->SetVolume($volume);
    $sonos->Play();

    LOGGING("radio.php: Radio Station '" . $next_name . "' has been loaded successful by nextradio", 5);
}


/**
* Funktion : 	random_radio --> lädt per Zufallsgenerator einen Radiosender und spielt ihn ab.
*
* @param: empty
* @return: Radio Sender
**/

function random_radio() {
	global $sonos, $profile_selected, $sonoszone, $master, $volume, $min_vol, $config, $tmp_tts;
	
	if (file_exists($tmp_tts))  {
		LOGGING("radio.php: Currently a T2S is running, we skip nextradio for now. Please try again later.",6);
		exit;
	}
	#if (isset($_GET['member']))  {
	#	LOGGING("radio.php: Function could not be used within Groups!!", 6);
	#	exit;
	#}
	#try {
	#	$sonos->BecomeCoordinatorOfStandaloneGroup();
		#LOGGING("radio.php: Player ".$master." has been ungrouped!", 6);
	#} catch (Exception $e) {
		#LOGGING("radio.php: Player ".$master." is Single!", 7);
	#}
	$sonoslists = $sonos->BrowseContentDirectory("R:0/0","BrowseDirectChildren");
	#print_r($sonoslists);
	if(!isset($_GET['except'])) {
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	} else {
		$except = $_GET['except'];
		$exception = explode(',',$except);
		for($i = 0; $i < count($exception); $i++) {
			$exception[$i] = str_replace(' ', '', $exception[$i]);
		}
		foreach ($exception as $key => $val) {
			unset($sonoslists[$val]);
		}
		$sonoslists = array_values($sonoslists);
		$countpl = count($sonoslists);
		$random = mt_rand(0, $countpl - 1);
	}
	$sonos->ClearQueue();
	$sonos->SetMute(false);
	$sonos->SetRadio(urldecode($sonoslists[$random]["res"]),$sonoslists[$random]["title"]);
	if (isset($_GET['profile']) or isset($_GET['Profile']))    {
		$volume = $profile_selected[0]['Player'][$master][0]['Volume'];
	}
	$sonos->SetVolume($volume);
	$sonos->Play();
	LOGGING("radio.php: Radio Station '".$sonoslists[$random]["title"]."' has been loaded successful by randomradio",6);
}



/**
 * Function : say_radio_station --> announce radio station before playing Station
 *
 * @param string $errortext    Optional: Fehler-/Infotext (z.B. aus error.json)
 * @param string $stationTitle Optional: Name des kommenden Senders (z.B. "hr3")
 * @return int                 Lautstärke, die nach der Ansage für den neuen Sender genutzt werden soll
 **/
function say_radio_station($errortext = '', $stationTitle = '')
{
    global $master, $sonoszone, $config, $min_vol, $volume,
           $sonos, $coord, $errorvoice, $errorlang;

    // batch-Modus darf hier NICHT verwendet werden
    if (isset($_GET['batch'])) {
        LOGGING("radio.php: The parameter 'batch' could not be used to announce the radio station!", 4);
        exit;
    }

    // Raum-Koordinator ermitteln und Sonos-Objekt initialisieren
    $coord = getRoomCoordinator($master);
    LOGGING("radio.php: Room Coordinator been identified", 7);
    $sonos = new SonosAccess($coord[0]);

    // Aktuelle Lautstärke merken (für keepvolume-Logik / Rückgabewert)
    $tmp_volume = $sonos->GetVolume();

    // Vor der Ansage erstmal muten
    #$sonos->SetMute(true);

    // Aktuelle Radio-Infos holen (falls wir keinen stationTitle übergeben bekommen)
    $temp_radio = $sonos->GetMediaInfo();

    // ********************** T2S-Text-Baustein laden **********************
    $TL = load_t2s_text();
    if (!empty($TL) && !empty($TL['SONOS-TO-SPEECH']['ANNOUNCE_RADIO'])) {
        // z.B. "Radio" oder "Sie hören"
        $play_stat = $TL['SONOS-TO-SPEECH']['ANNOUNCE_RADIO'];
    } else {
        $play_stat = 'Radio';
    }
    // ********************************************************************

    // -----------------------------
    // Ansagetext bestimmen
    // -----------------------------
    if ($errortext !== '') {
        // Fehlerfall: expliziter Fehler-/Infotext
        $textstring = $errortext;

    } elseif ($stationTitle !== '') {
        // Normalfall: kommender Sender wurde explizit übergeben
        $textstring = $play_stat . ' ' . $stationTitle;

    } else {
        // Fallback: Radio-Infos aus Sonos (aktueller Sender)
        $title = $temp_radio['title'] ?? '';

        if ($title === '') {
            // Wenn Sonos keinen Titel liefert, nur den Announce-Text sagen
            $textstring = $play_stat;
        } elseif (strncmp($title, $play_stat, strlen($play_stat)) === 0) {
            // Wenn Titel schon mit Announce-Text beginnt, nur den Titel sprechen
            $textstring = $title;
        } else {
            // Ansonsten: Announce-Text + Sendername
            $textstring = $play_stat . ' ' . $title;
        }
    }

    // Sicherheit: Niemals mit leerem Text ins TTS laufen
    if (trim($textstring) === '') {
        LOGWARN("radio.php: Empty announcement text – skipping TTS.");
        return $tmp_volume;
    }

    // -----------------------------
    // TTS-Overrides für Fehlerfall
    // -----------------------------
    $override    = [];
    $log_context = 'radio.php: ';

    if ($errortext !== '') {
        // Im Fehlerfall:
        //  - GET-Parameter ignorieren (stabile Systemansage)
        //  - Sprache/Voice aus error.json verwenden, wenn gesetzt
        $override['ignore_get'] = true;

        if (!empty($errorvoice)) {
            $override['voice'] = $errorvoice;
        }

        if (!empty($errorlang)) {
            $override['language'] = $errorlang;
        }

        #$log_context .= ' [error]';
    }

    // -----------------------------
    // Gewünschte Lautstärke bestimmen (für Ansage + folgenden Sender)
    // -----------------------------
    if (isset($_GET['volume'])) {
        // Feste Lautstärke aus URL
        $volume = (int)$_GET['volume'];

    } elseif (isset($_GET['keepvolume'])) {
        // Bisherige Lautstärke beibehalten, Mindestlautstärke beachten
        if ($tmp_volume >= $min_vol) {
            $volume = $tmp_volume;
        } else {
            $volume = $sonoszone[$master][4];
        }

    } else {
        // Standardlautstärke aus Config
        $volume = $sonoszone[$master][4];
    }

    // -----------------------------
    // Einfache TTS-Ansage ohne Snapshot/Restore (wartet bis fertig)
    // -----------------------------
    t2s_basic_say($textstring, $override);
    LOGGING("radio.php: Radio Station Announcement has been announced", 6);

    return $volume;
}



/**
* Funktion : 	select_error_lang --> wählt die Sprache der error message aus.
*
* @param: empty
* @return: translations form error.json file
**/

function select_error_lang() {
	
	global $config, $pathlanguagefile, $errortext, $errorvoice, $errorlang;
	
	$file = "error.json";
	$url = $pathlanguagefile."".$file;
	$valid_languages = File_Get_Array_From_JSON($url, $zip=false);
	#print_r($valid_languages);
	$language = $config['TTS']['messageLang'];
	$language = substr($language, 0, 5);
	#echo $language;
	$isvalid = array_multi_search($language, $valid_languages, $sKey = "language");
	if (!empty($isvalid)) {
		$errortext = $isvalid[0]['value']; // Text
		$errorvoice = $isvalid[0]['voice']; // de-DE-Standard-A
		$errorlang = $isvalid[0]['language']; // de-DE
	} else {
		# if no translation for error exit use English
		$errortext = 'the function nextradio is not working, please check Sonos Plugin error log.';
		$errorvoice = 'en-US-Wavenet-A';
		$errorlang = 'en-US';
		LOGGING("radio.php: Translation for your Standard language is not available, EN has been selected", 6);	
	}
	#print_r($valid_languages);
	

}

/**
* Funktion : 	check_date_once --> check for execution once a day (cronjob daily deletes file)
*
* @param: empty
* @return: true or false
**/

function check_date_once() {
	
	global $check_date, $stst, $tmp_error;
	
	if (file_exists($check_date) and file_exists($tmp_error)) {
		$stst = "false";
		return $stst;
	} else {
		$now = date("d.m.Y");
		file_put_contents($check_date, $now);
		$stst = "true";
		return $stst;
	};
}


/**
/* Funktion : PluginRadio --> lädt einen Radiosender aus den Plugin Radio Favoriten in eine Zone/Gruppe
/*
/* @param: Sender                             
/* @return: nichts
**/

function PluginRadio()   
{
    global $sonos, $sonoszone, $profile_details, $master, $config, $volume;
    
    if (isset($_GET['radio'])) {
        if (empty($_GET['radio']))    {
            LOGGING("radio.php: No radio station been entered. Please use ...action=pluginradio&radio=<STATION>", 4);
            exit(1);
        }
    }

    // Master bestimmen
    if (isset($_GET['member']) && isset($_GET['profile']) && defined('GROUPMASTER')) {
        $master = GROUPMASTER;
    } elseif (isset($_GET['profile']) && defined('GROUPMASTER')) {
        $master = GROUPMASTER;
    } else {
        // Fallback: ganz normal MASTER benutzen
        $master = MASTER;
    }
	
	if (isset($_GET['member']) && trim($_GET['member']) !== '') {
        // CreateMember wurde i.d.R. schon in sonos.php aufgerufen – ist idempotent
        SyncGroupForPlaybackToMember();
    }

    $sonos = new SonosAccess($sonoszone[$master][0]);
	
    $enteredRadio = mb_strtolower($_GET['radio']);
    $radios       = $config['RADIO']['radio'];
    $valid        = array();

    // Array vorbereiten und Details hinzufügen
    foreach ($radios as $val => $item)  {
        $split = explode(',' , $item);
        $split['lower'] = mb_strtolower($split[0]);
        array_push($valid, $split);
    }

    $re = array();
    // durchsuchen
    foreach ($valid as $item)  {
        $radiocheck = contains($item['lower'], $enteredRadio);
        if ($radiocheck === true)   {
            $favorite = $item['lower'];
            array_push($re, array_multi_search($favorite, $valid));
        }
    }

    // mehr als 1 Treffer?
    if (count($re) > 1)  {
        LOGERR ("radio.php: Your entered favorite/keyword '".$enteredRadio."' has more then 1 hit! Please specify more detailed.");
        exit;
    }
    // kein Treffer?
    if (count($re) < 1)  {
        LOGERR ("radio.php: Your entered favorite/keyword '".$enteredRadio."' could not be found! Please specify more detailed.");
        exit;
    }

    // Ab hier wissen wir: genau ein passender Eintrag
    $stationName = $re[0][0][0]; // z.B. "Top 100"
    $stationUrl  = $re[0][0][1]; // Stream-URL
    $stationMeta = $re[0][0][2]; // Metadaten

    // ------------------------------------------------------------
    // 1) SENDER-ANSAGE (separat, darf Radio NICHT beeinflussen)
    //    → Wenn hier was schiefgeht, trotzdem Radio starten.
    // ------------------------------------------------------------
    try {
		if (is_enabled($config['VARIOUS']['announceradio'])) {
			say_radio_station('', $stationName);
			$sonos->ClearQueue();
		}
    } catch (Exception $e) {
        LOGWARN("radio.php: failed: ".$e->getMessage());
    }

    // ------------------------------------------------------------
    // 2) RADIO LADEN & STARTEN (eigener try/catch)
    //    → Endzustand soll IMMER: Radiosender spielt
    // ------------------------------------------------------------
    try {
        $sonos = new SonosAccess($sonoszone[$master][0]);

        // Sender setzen
        $uri = 'x-rincon-mp3radio://' . $stationUrl;
        $sonos->SetRadio($uri, $stationName, $stationMeta);
        $sonos->SetGroupMute(false);

        // Lautstärke anhand Profil/Member
        if (isset($_GET['profile']) or isset($_GET['Profile'])) {
            $volume = $profile_details[0]['Player'][$master][0]['Volume'];
        } elseif (isset($_GET['member'])) {
            volume_group();
            $sonos = new SonosAccess($sonoszone[$master][0]);
        }

        if (!isset($_GET['load']) && !isset($_GET['rampto'])) {
            $sonos->SetMute(false);
            $sonos->Stop();
            $sonos->SetVolume($volume);
            $sonos->Play();
            LOGOK("radio.php: Your Plugin Radio '".$stationName."' has been successful loaded and is playing!");
        }

        // Rampto nur für Lautstärke, darf ruhig nach dem Start kommen
        RampTo();

    } catch (Exception $e) {
        LOGERR("radio.php: Something went unexpected wrong while loading radio '".$enteredRadio."': ".$e->getMessage());
        return;
    }
}


?>