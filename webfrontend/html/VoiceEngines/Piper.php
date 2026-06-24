<?php
/**
 * Sonos4Lox - Piper Text-to-Speech
 * Version: VOICE_ENGINE_ROBUSTNESS_V03_2026_06_15
 *
 * Local/offline TTS engine. No API keys are required.
 */

require_once __DIR__ . '/VoiceEngineHelper.php';

if (!defined('S4L_PIPER_CONTEXT')) {
    define('S4L_PIPER_CONTEXT', 'VoiceEngines/Piper.php');
}

if (!function_exists('s4l_piper_find_voice')) {
    function s4l_piper_find_voice(array $voices, string $voice): array
    {
        $voice = trim($voice);

        foreach ($voices as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['name'] ?? '') === $voice && !empty($entry['filename'])) {
                return $entry;
            }
        }

        foreach ($voices as $entry) {
            if (is_array($entry) && !empty($entry['filename'])) {
                return $entry;
            }
        }

        return [];
    }
}

if (!function_exists('piper_core')) {
    /**
     * Core Piper TTS implementation.
     * Expected params: filename, text, voice; optional: speaker, encode_profile.
     */
    function piper_core(array $t2s_param)
    {
        global $config;

        $filename = s4l_ve_safe_filename((string)($t2s_param['filename'] ?? 'tts_output'));
        $text     = (string)($t2s_param['text'] ?? '');
        $voice    = (string)($t2s_param['voice'] ?? '');

        // Piper does not need any API key. Speaker can come from the normalized
        // TTS params first; $_GET is only kept as legacy fallback.
        if (isset($t2s_param['speaker'])) {
            $speaker = max(0, min(7, (int)$t2s_param['speaker']));
        } elseif (isset($_GET['speaker'])) {
            $speaker = max(0, min(7, (int)$_GET['speaker']));
        } else {
            $speaker = 4;
        }

        if ($text === '') {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'ERROR', 'No text provided.');
            return false;
        }

        $mp3Path = s4l_ve_output_path($config, $filename, S4L_PIPER_CONTEXT);
        if ($mp3Path === '') {
            return false;
        }

        $ttspath = dirname($mp3Path);
        $wavPath = $ttspath . '/' . $filename . '.piper.wav';

        @unlink($mp3Path);
        @unlink($wavPath);

        $voicefile = LBPHTMLDIR . '/VoiceEngines/langfiles/piper_voices.json';
        $voices = s4l_ve_load_json($voicefile, S4L_PIPER_CONTEXT);
        if (!is_array($voices)) {
            return false;
        }

        $voiceData = s4l_piper_find_voice($voices, $voice);
        if (empty($voiceData['filename'])) {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'ERROR', 'No valid voices found in piper_voices.json.');
            return false;
        }

        $voiceNameUsed = (string)($voiceData['name'] ?? 'default');
        if ($voice !== '' && $voiceNameUsed !== $voice) {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'WARNING', "Voice '$voice' not in list. Falling back to default Piper voice '$voiceNameUsed'.");
        }

        $modelFile = LBPHTMLDIR . '/VoiceEngines/piper-voices/' . $voiceData['filename'];
        if (!is_file($modelFile)) {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'ERROR', "Piper model file not found: $modelFile");
            return false;
        }

        s4l_ve_log(S4L_PIPER_CONTEXT, 'INFO', "Voice '$voiceNameUsed' speaker $speaker.");

        $profile = (string)($t2s_param['encode_profile'] ?? ($_GET['encode'] ?? 'fast'));
        if (!in_array($profile, ['fast', 'balanced', 'hq'], true)) {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'WARNING', "Unknown encode profile '$profile'. Falling back to 'fast'.");
            $profile = 'fast';
        }

        $ffFast = '-codec:a libshine -ar 44100 -ac 1 -b:a 96k';
        $ffBalanced = '-codec:a libmp3lame -qscale:a 5 -ac 1';
        $ffHq = '-codec:a libmp3lame -qscale:a 2 -ac 1';
        $ffmpegCodec = ($profile === 'fast') ? $ffFast : (($profile === 'balanced') ? $ffBalanced : $ffHq);

        $piperBin = 'REPLACELBHOMEDIR/bin/plugins/sonos4lox/piper/piper';
        if (!is_executable($piperBin)) {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'ERROR', "Piper binary not executable or not found at $piperBin");
            return false;
        }

        $cmdPiper = 'bash -lc ' . escapeshellarg(
            'set -o pipefail; ' .
            'printf %s ' . escapeshellarg($text) .
            ' | ' . escapeshellarg($piperBin) .
            ' -m ' . escapeshellarg($modelFile) .
            ' --speaker ' . escapeshellarg((string)$speaker) . ' ' .
            '--output_file ' . escapeshellarg($wavPath) . ' 2>&1'
        );

        $outPiper = [];
        $rcPiper = 0;
        exec($cmdPiper, $outPiper, $rcPiper);

        if ($rcPiper !== 0 || !is_file($wavPath) || filesize($wavPath) <= 0) {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'ERROR', "Piper failed to create WAV file. Exit: $rcPiper");
            if (!empty($outPiper)) {
                s4l_ve_log(S4L_PIPER_CONTEXT, 'DEBUG', 'piper: ' . implode("\n", $outPiper));
            }
            @unlink($wavPath);
            return false;
        }

        $cmdFfmpeg = 'bash -lc ' . escapeshellarg(
            'set -o pipefail; ' .
            '/usr/bin/ffmpeg -y -hide_banner -loglevel error ' .
            '-i ' . escapeshellarg($wavPath) . ' ' .
            $ffmpegCodec . ' ' . escapeshellarg($mp3Path) . ' 2>&1'
        );

        $outFfmpeg = [];
        $rcFfmpeg = 0;
        exec($cmdFfmpeg, $outFfmpeg, $rcFfmpeg);
        $ok = ($rcFfmpeg === 0 && is_file($mp3Path) && filesize($mp3Path) > 0);

        if (!$ok && $profile === 'fast') {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'WARNING', 'ffmpeg fast profile failed. Retrying with balanced profile.');
            $cmdRetry = 'bash -lc ' . escapeshellarg(
                'set -o pipefail; ' .
                '/usr/bin/ffmpeg -y -hide_banner -loglevel error ' .
                '-i ' . escapeshellarg($wavPath) . ' ' .
                $ffBalanced . ' ' . escapeshellarg($mp3Path) . ' 2>&1'
            );
            $outRetry = [];
            $rcRetry = 0;
            exec($cmdRetry, $outRetry, $rcRetry);
            $ok = ($rcRetry === 0 && is_file($mp3Path) && filesize($mp3Path) > 0);
            if (!$ok && !empty($outRetry)) {
                s4l_ve_log(S4L_PIPER_CONTEXT, 'DEBUG', 'ffmpeg(retry): ' . implode("\n", $outRetry));
            }
        }

        @unlink($wavPath);

        if (!$ok) {
            s4l_ve_log(S4L_PIPER_CONTEXT, 'ERROR', "MP3 could not be created: $mp3Path");
            if (!empty($outFfmpeg)) {
                s4l_ve_log(S4L_PIPER_CONTEXT, 'DEBUG', 'ffmpeg: ' . implode("\n", $outFfmpeg));
            }
            @unlink($mp3Path);
            return false;
        }

        @chmod($mp3Path, 0664);
        s4l_ve_log(S4L_PIPER_CONTEXT, 'OK', "MP3 created: $mp3Path (profile=$profile).");
        return basename($mp3Path, '.mp3');
    }
}

if (!function_exists('t2s')) {
    function t2s($arg1, $arg2 = null, $arg3 = null)
    {
        if (is_array($arg1) && $arg2 === null && $arg3 === null) {
            return piper_core($arg1);
        }

        global $config;

        $text = (string)$arg1;
        $filename = $arg2 !== null ? (string)$arg2 : md5($text);
        $voice = $config['TTS']['piper_voice'] ?? ($config['TTS']['voice'] ?? '');

        return piper_core([
            'filename' => $filename,
            'text' => $text,
            'voice' => $voice,
        ]);
    }
}

if (!function_exists('t2s_piper')) {
    function t2s_piper($text, $filename)
    {
        global $config;

        $voice = $config['TTS']['piper_voice'] ?? ($config['TTS']['voice'] ?? '');

        return piper_core([
            'filename' => $filename,
            'text' => (string)$text,
            'voice' => $voice,
        ]);
    }
}
