<?php
/**
 * Sonos4Lox TTS Actions
 * Version: V03.0
 * Language: EN
 *
 * Purpose:
 * - Extract current Text-to-Speech related cases from legacy Sonos.php.
 * - V03.0 handles deprecated public URL aliases sendmessage/sendgroupmessage by forwarding to action=say logic.
 * - V02.0 moves present/absent to PresenceActions.
 * - Preserve existing public URL syntax and legacy helper behaviour.
 * - Keep deprecated aliases and non-documented debug/interface flows out of the refactored layer.
 *
 * Migrated actions in V01.0:
 * - say
 * - doorbell
 * - playbatch
 * - mp3rights
 * - sendmessage (deprecated public URL alias, internally forwarded to say)
 * - sendgroupmessage (deprecated public URL alias, internally forwarded to say)
 *
 * Not migrated intentionally:
 * - audioclip is not part of the current public command reference; AudioClip stays a parameter of action=say via &clip.
 * - say1, sayradio and ttsp stay legacy/cleanup candidates until explicitly confirmed as current public commands.
 */

class S4L_TtsActions
{
    private $context;
    private $request;
    private $master;
    private $sonoszone;
    private $sonos;
    private $config;

    public function __construct($context, S4L_Request $request)
    {
        $this->context = is_array($context) ? $context : array();
        $this->request = $request;
        $this->master = $this->contextValue('master');
        $this->sonoszone = $this->contextValue('sonoszone', array());
        $this->sonos = $this->contextValue('sonos');
        $this->config = $this->contextValue('config', array());
    }

    public function handle($action)
    {
        switch ($action) {
            case 'say':
                $this->say();
                return;
            case 'sendmessage':
            case 'sendgroupmessage':
                $this->deprecatedSayAlias($action);
                return;
            case 'doorbell':
                $this->doorbell();
                return;
            case 'playbatch':
                $this->playBatch();
                return;
            case 'mp3rights':
                $this->mp3Rights();
                return;
        }
    }


    private function deprecatedSayAlias($action)
    {
        $action = strtolower((string)$action);

        S4L_Logger::info("Deprecated action=" . $action . " was called. Please change the URL syntax to action=say.");

        if ($this->request->has('profile')) {
            S4L_Logger::error("Usage of profiles with deprecated action=" . $action . " is not allowed. Please use action=say with the profile parameter instead.");
            return;
        }

        if ($action === 'sendgroupmessage') {
            S4L_Logger::debug("Deprecated action=sendgroupmessage is forwarded to action=say without changing the public URL parameters.");
        } else {
            S4L_Logger::debug("Deprecated action=sendmessage is forwarded to action=say without changing the public URL parameters.");
        }

        $this->say();
    }

    private function say()
    {
        global $filenst, $min_sec, $tts_stat;

        $oldText = 'old';
        $newText = 'new';
        $last = time();
        $tts_stat = 1;

        if (function_exists('send_tts_source')) {
            send_tts_source($tts_stat);
        }

        if ($this->request->has('text')) {
            $newText = $this->request->get('text');
        }

        if (!empty($filenst) && file_exists($filenst)) {
            $last = time() - filemtime($filenst);
            $handle = fopen($filenst, 'r');
            if ($handle === false) {
                S4L_Logger::warning('Unable to open temporary TTS duplicate-check file.');
            } else {
                $oldText = fread($handle, 8192);
                fclose($handle);
            }
            @unlink($filenst);
        }

        $minSeconds = is_numeric($min_sec) ? (int)$min_sec : 0;

        if ((($oldText == $newText) && ($last > $minSeconds)) || ($oldText != $newText)) {
            say();
            $this->writeDuplicateCheckFile($newText);
            S4L_Logger::debug('Say has been executed.');
            return;
        }

        S4L_Logger::ok('Same text has been announced within the last ' . $minSeconds . ' seconds. We skip this announcement.');
    }

    private function doorbell()
    {
        doorbell();
        S4L_Logger::debug('Doorbell has been executed.');
    }

    private function playBatch()
    {
        t2s_playbatch();
        S4L_Logger::debug('TTS playbatch has been executed.');
    }

    private function mp3Rights()
    {
        $ttsPath = $this->configValue('SYSTEM', 'ttspath', '');
        $mp3Path = $this->configValue('SYSTEM', 'mp3path', '');

        if ($ttsPath === '' || $mp3Path === '') {
            S4L_Logger::warning('TTS path or MP3 path is missing in plugin configuration. Access rights have not been changed.');
            return;
        }

        system('chmod -R 0755 ' . escapeshellarg($ttsPath));
        system('chmod -R 0755 ' . escapeshellarg($mp3Path));
        S4L_Logger::debug('Access rights for all files in the TTS folder have been set to 0755.');
    }

    private function writeDuplicateCheckFile($text)
    {
        global $filenst;

        if (empty($filenst)) {
            S4L_Logger::warning('Temporary TTS duplicate-check file path is empty.');
            return;
        }

        $handle = fopen($filenst, 'w');
        if ($handle === false) {
            S4L_Logger::warning('Unable to write temporary TTS duplicate-check file.');
            return;
        }

        fwrite($handle, $text);
        fclose($handle);
    }

    private function configValue($section, $key, $default = null)
    {
        if (isset($this->config[$section]) && is_array($this->config[$section]) && array_key_exists($key, $this->config[$section])) {
            return $this->config[$section][$key];
        }

        return $default;
    }

    private function contextValue($name, $default = null)
    {
        return array_key_exists($name, $this->context) ? $this->context[$name] : $default;
    }
}
