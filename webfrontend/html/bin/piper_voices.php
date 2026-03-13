<?php

require_once "loxberry_system.php";

$piperDir        = $lbphtmldir . "/voice_engines/piper-voices/";
$piperOutLang    = $lbphtmldir . "/voice_engines/langfiles/piper.json";
$piperOutVoices  = $lbphtmldir . "/voice_engines/langfiles/piper_voices.json";

$languages = [];
$voices    = [];

$seenLang  = [];
$seenVoice = [];


/*
 * Spezialfix für Thorsten Hessisch Voice
 */
$hessFile = $piperDir . "Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx.json";

if (file_exists($hessFile)) {

    $piper = json_decode(file_get_contents($hessFile), true);

    if (!is_array($piper)) {
        $piper = [];
    }

    if (!(isset($piper['language']) && isset($piper['dataset']))) {

        $array = [];
        $array['language'] = [];
        $array['language']['code'] = 'de_DE';
        $array['language']['family'] = 'de';
        $array['language']['region'] = 'DE';
        $array['language']['name_native'] = 'Deutsch';
        $array['language']['name_english'] = 'German';
        $array['language']['country_english'] = 'Germany';
        $array['dataset'] = 'thorsten_hessisch';

        $piper = array_merge($piper, $array);

        file_put_contents(
            $hessFile,
            json_encode($piper, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}


/*
 * Alte JSON Dateien löschen damit entfernte Sprachen/Voices
 * garantiert aus dem Index verschwinden
 */
if (file_exists($piperOutLang)) {
    unlink($piperOutLang);
}

if (file_exists($piperOutVoices)) {
    unlink($piperOutVoices);
}


$files = scandir($piperDir);

foreach ($files as $file) {

    if (!preg_match('/\.json$/i', $file)) {
        continue;
    }

    $full = $piperDir . $file;

    if (!is_file($full)) {
        continue;
    }

    $json = file_get_contents($full);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        continue;
    }

    if (!isset($data['language']['code'])) {
        continue;
    }

    $code    = $data['language']['code'];
    $country = $data['language']['country_english'] ?? '';
    $dataset = $data['dataset'] ?? '';

    if ($dataset === '') {
        continue;
    }

    # Filename normalisieren (.onnx.json oder .json)
    $fname = preg_replace('/\.onnx\.json$|\.json$/i', '', $file);

    # prüfen ob ONNX existiert
    if (!file_exists($piperDir . $fname . ".onnx")) {
        continue;
    }

    # Duplicate Check Voice
    $voiceKey = $code . "_" . $dataset;

    if (!isset($seenVoice[$voiceKey])) {

        $voices[] = [
            "name"     => $dataset,
            "language" => $code,
            "filename" => $fname . ".onnx"
        ];

        $seenVoice[$voiceKey] = true;
    }

    # Duplicate Check Language
    if (!isset($seenLang[$code])) {

        $languages[] = [
            "country" => $country,
            "value"   => $code
        ];

        $seenLang[$code] = true;
    }
}


# Sortierung Languages
usort($languages, function($a, $b) {
    return strcasecmp($a['country'], $b['country']);
});


# Sortierung Voices
usort($voices, function($a, $b) {

    $cmp = strcasecmp($a['language'], $b['language']);
    if ($cmp !== 0) {
        return $cmp;
    }

    return strcasecmp($a['name'], $b['name']);
});


# JSON schreiben
file_put_contents(
    $piperOutLang,
    json_encode($languages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

file_put_contents(
    $piperOutVoices,
    json_encode($voices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);


echo " Piper index rebuilt (" . count($languages) . " language(s), " . count($voices) . " voices)\n";

?>