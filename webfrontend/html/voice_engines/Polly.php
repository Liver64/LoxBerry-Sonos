<?php
/**
 * Created by PhpStorm.
 * User: Shaq
 * Date: 06.01.2017
 * Time: 19:03
 */

function t2s($messageid)

// text-to-speech: Erstellt basierend auf Input eine TTS Nachricht, übermittelt sie an Polly / Amazon AWS und
// speichert das zurückkommende file lokal ab
// @Parameter = $messageid von sonos2.php
{
    global $messageid, $words, $config, $filename, $fileolang, $voice, $accesskey, $secretkey, $fileo;
    include 'polly_tts/polly.php';

    #-- Übernahme der Variablen aus config.php --
    $mpath = $config['SYSTEM']['messageStorePath'];

    #-- Aufruf der POLLY Class zum generieren der t2s --
    $a = new PollyClient();
    $a->getSave($words, array('VoiceName' => $voice, 'CacheDir' => $mpath, 'FileName' => $fileolang));
    $messageid = $fileolang;

    return ($messageid);
}