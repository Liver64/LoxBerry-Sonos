<?php
/**
 * Piper Text-to-Speech
 * 
 * Entry points:
 *  - t2s(array $t2s_param) ODER t2s(string $text, string $filename)
 *      -> Modern T2S-API (Text2speech-Plugin) + Legacy-Sonos-Aufruf
 *  - t2s_piper(string $text, string $filename)
 *      -> Kompatibilitäts-Wrapper für Sonos (Fallback in create_tts)
 *
 * Beispiel (modern, T2S-Plugin):
 * $t2s_param = [
 *     'filename'       => 'testfile',
 *     'text'           => 'Hallo Welt!',
 *     'voice'          => 'Thorsten',
 *     'encode_profile' => 'fast', // optional: fast|balanced|hq
 * ];
 * t2s($t2s_param);
 *
 * Beispiel (legacy, Sonos):
 * t2s("Hallo Welt!", "md5filename");
 */

if (!function_exists('piper_core')) {
    /**
     * Kern-Implementierung von Piper TTS.
     * Erwartet ein $t2s_param-Array:
     *  - filename
     *  - text
     *  - voice
     *  - encode_profile (optional: fast|balanced|hq)
     */
    function piper_core(array $t2s_param)
    {
        global $config;

        $filename   = $t2s_param['filename'] ?? 'tts_output';
        $text       = $t2s_param['text'] ?? '';
        $voice      = $t2s_param['voice'] ?? '';
        $speaker    = isset($_GET['speaker']) ? max(0, min(7, (int)$_GET['speaker'])) : 4;

        if ($text === '') {
            LOGERR("voice_engines/Piper.php: No text provided.");
            return false;
        }

        // ---------- Pfade ----------
        $ttspath  = rtrim($config['SYSTEM']['ttspath'] ?? '/tmp', '/');
        if (!is_dir($ttspath)) {
            @mkdir($ttspath, 0775, true);
        }

        $mp3Path  = "$ttspath/$filename.mp3";
        $wavPath  = "$ttspath/$filename.piper.wav";

        // Alte Dateien ggf. entfernen
        if (is_file($mp3Path)) { @unlink($mp3Path); }
        if (is_file($wavPath)) { @unlink($wavPath); }

        // ---------- Voice-Model ----------
        $voicefile = LBPHTMLDIR . "/voice_engines/langfiles/piper_voices.json";
        if (!is_file($voicefile)) {
            LOGERR("voice_engines/Piper.php: Voice file not found: $voicefile");
            return false;
        }

        $voices = json_decode(file_get_contents($voicefile), true);
        if (!$voices || !is_array($voices)) {
            LOGERR("voice_engines/Piper.php: Failed to decode voice file.");
            return false;
        }

        $modelFile      = null;
        $voiceNameUsed  = $voice;

        // Voice passend zum Namen suchen
        $match = array_multi_search($voice, $voices, "name");

        if (!empty($match[0]['filename'])) {
            // Gewünschte Voice gefunden
            $modelFile     = LBPHTMLDIR . "/voice_engines/piper-voices/" . $match[0]['filename'];
            $voiceNameUsed = $match[0]['name'] ?? $voice;

        } else {
            // Voice nicht in Liste -> auf Default zurückfallen
            $first = reset($voices);
            if (!empty($first['filename'])) {
                $modelFile     = LBPHTMLDIR . "/voice_engines/piper-voices/" . $first['filename'];
                $voiceNameUsed = $first['name'] ?? 'default';
                LOGWARN("voice_engines/Piper.php: Voice '$voice' not in list. Falling back to default Piper voice '{$voiceNameUsed}'.");
            } else {
                LOGERR("voice_engines/Piper.php: No valid voices found in piper_voices.json.");
                return false;
            }
        }

        if (isset($_GET['speaker'])) {
            LOGINF("voice_engines/Piper.php: Voice '{$voiceNameUsed}' speaker $speaker");
        } else {
            LOGINF("voice_engines/Piper.php: Voice '{$voiceNameUsed}'");
        }

        // ---------- OPTIONAL: Piper-Flags ----------
        $piperOpts = [];
        // $piperOpts[] = '--sentence_silence 0.1';
        // $piperOpts[] = '--length_scale 0.95';

        // ---------- Encoding Profile ----------
        $profile = $t2s_param['encode_profile'] ?? ($_GET['encode'] ?? 'fast'); // 'fast'|'balanced'|'hq'
        $ff_fast = '-codec:a libshine -ar 44100 -ac 1 -b:a 96k';   // schnell + kompatibel
        $ff_bal  = '-codec:a libmp3lame -qscale:a 5 -ac 1';        // solide
        $ff_hq   = '-codec:a libmp3lame -qscale:a 2 -ac 1';        // beste Qualität
        $ffmpegCodec = ($profile === 'fast') ? $ff_fast : (($profile === 'balanced') ? $ff_bal : $ff_hq);

        // ----------------------------------------------------------------------
        // 1) Piper erzeugt WAV-Datei
        // ----------------------------------------------------------------------
        $piperBin = '/usr/bin/piper'; // über Symlink von /usr/local/bin/piper/piper

        if (!is_executable($piperBin)) {
            LOGERR("voice_engines/Piper.php: Piper binary not executable or not found at $piperBin");
            return false;
        }

        $cmdPiper = 'bash -lc ' . escapeshellarg(
            'set -o pipefail; ' .
            'printf %s ' . escapeshellarg($text) .
            ' | ' . escapeshellarg($piperBin) .
            ' -m ' . escapeshellarg($modelFile) .
            ' --speaker ' . escapeshellarg((string)$speaker) . ' ' .
            implode(' ', $piperOpts) . ' ' .
            '--output_file ' . escapeshellarg($wavPath) . ' 2>&1'
        );

        $outPiper = []; $rcPiper = 0;
        exec($cmdPiper, $outPiper, $rcPiper);

        if ($rcPiper !== 0 || !is_file($wavPath) || filesize($wavPath) === 0) {
            LOGERR("voice_engines/Piper.php: Piper failed to create WAV file. Exit: $rcPiper");
            if (!empty($outPiper)) {
                LOGDEB("piper: " . implode("\n", $outPiper));
            }
            if (is_file($wavPath)) { @unlink($wavPath); }
            return false;
        }

        // ----------------------------------------------------------------------
        // 2) ffmpeg: WAV -> MP3 (Profil abhängig)
        // ----------------------------------------------------------------------
        $cmdFfmpeg = 'bash -lc ' . escapeshellarg(
            'set -o pipefail; ' .
            '/usr/bin/ffmpeg -y -hide_banner -loglevel error ' .
            '-i ' . escapeshellarg($wavPath) . ' ' .
            $ffmpegCodec . ' ' . escapeshellarg($mp3Path) . ' 2>&1'
        );

        $outFfmpeg = []; $rcFfmpeg = 0;
        exec($cmdFfmpeg, $outFfmpeg, $rcFfmpeg);

        $ok = ($rcFfmpeg === 0 && is_file($mp3Path) && filesize($mp3Path) > 0);

        // Optionaler zweiter Versuch bei "fast" (nur ffmpeg, WAV ist schon da)
        if (!$ok && $profile === 'fast') {
            $retryCodec = $ff_bal; // libmp3lame, q=5
            $cmdRetry = 'bash -lc ' . escapeshellarg(
                'set -o pipefail; ' .
                '/usr/bin/ffmpeg -y -hide_banner -loglevel error ' .
                '-i ' . escapeshellarg($wavPath) . ' ' .
                $retryCodec . ' ' . escapeshellarg($mp3Path) . ' 2>&1'
            );
            $outRetry = []; $rcRetry = 0;
            exec($cmdRetry, $outRetry, $rcRetry);
            $ok = ($rcRetry === 0 && is_file($mp3Path) && filesize($mp3Path) > 0);
            if (!$ok) {
                LOGERR("voice_engines/Piper.php: ffmpeg failed (fast->balanced fallback). Exit: $rcRetry");
                if (!empty($outRetry)) {
                    LOGDEB("ffmpeg(retry): " . implode("\n", $outRetry));
                }
            }
        }

        // WAV-Datei nach erfolgreicher Konvertierung entfernen
        if (is_file($wavPath)) {
            @unlink($wavPath);
        }

        if (!$ok) {
            LOGERR("voice_engines/Piper.php: MP3 could not be created: $mp3Path");
            if (!empty($outFfmpeg)) {
                LOGDEB("ffmpeg: " . implode("\n", $outFfmpeg));
            }
            if (is_file($mp3Path)) { @unlink($mp3Path); }
            return false;
        }

        LOGOK("voice_engines/Piper.php: MP3 created: $mp3Path (profile=$profile)");
        LOGOK("voice_engines/Piper.php: MP3 file successfully saved");
        return basename($mp3Path, '.mp3');
    }
}

/**
 * t2s() – akzeptiert:
 *  - t2s(array $t2s_param)  (modern)
 *  - t2s(string $text, string $filename)  (legacy Sonos)
 */
if (!function_exists('t2s')) {
    function t2s($arg1, $arg2 = null, $arg3 = null)
    {
        // Moderner Aufruf: t2s($t2s_param_array)
        if (is_array($arg1) && $arg2 === null && $arg3 === null) {
            return piper_core($arg1);
        }

        // Legacy-Aufruf: t2s($text, $filename)
        global $config;

        $text     = (string)$arg1;
        $filename = $arg2 !== null ? (string)$arg2 : md5($text);

        $voice = $config['TTS']['piper_voice'] ?? ($config['TTS']['voice'] ?? '');

        $params = [
            'filename' => $filename,
            'text'     => $text,
            'voice'    => $voice,
        ];

        return piper_core($params);
    }
}

/**
 * Kompatibilitäts-Wrapper für Sonos:
 *  t2s_piper(string $text, string $filename)
 * Ruft intern dieselbe Kernlogik auf, nutzt aber (wenn vorhanden)
 * eine dedizierte Config-Voice für Piper: $config['TTS']['piper_voice'].
 */
if (!function_exists('t2s_piper')) {
    function t2s_piper($text, $filename)
    {
        global $config;

        // Optional dedizierte Piper-Voice:
        //  - TTS.piper_voice (wenn vorhanden)
        //  - sonst Fallback auf TTS.voice
        $voice = $config['TTS']['piper_voice'] ?? ($config['TTS']['voice'] ?? '');

        $params = [
            'filename'       => $filename,
            'text'           => $text,
            'voice'          => $voice,
            // encode_profile optional – kann bei Bedarf später aus Config / GET gezogen werden
        ];

        return piper_core($params);
    }
}
?>
