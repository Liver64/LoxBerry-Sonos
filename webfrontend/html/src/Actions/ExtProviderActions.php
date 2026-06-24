<?php
/**
 * Sonos4Lox External Provider Actions
 * Version: V01.0
 * Language: EN
 *
 * Purpose:
 * - Extract external music provider and local track actions from legacy Sonos.php.
 * - Preserve the existing public URL syntax and MusicService.php helper behaviour.
 * - Keep provider helper implementation in MusicService.php for now.
 *
 * Migrated actions in V01.0:
 * - spotify
 * - amazon
 * - google (guarded: only executed if AddGoogle() exists in MusicService.php)
 * - apple
 * - napster
 * - track
 */

class S4L_ExtProviderActions
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
        if (!$this->loadMusicService()) {
            return;
        }

        switch ($action) {
            case 'spotify':
                $this->runProvider('AddSpotify', 'Spotify');
                return;
            case 'amazon':
                $this->runProvider('AddAmazon', 'Amazon');
                return;
            case 'google':
                $this->runProvider('AddGoogle', 'Google');
                return;
            case 'apple':
                $this->runProvider('AddApple', 'Apple');
                return;
            case 'napster':
                $this->runProvider('AddNapster', 'Napster');
                return;
            case 'track':
                $this->runProvider('AddTrack', 'Local track');
                return;
        }
    }

    private function loadMusicService()
    {
        $musicServiceFile = dirname(dirname(__DIR__)) . '/MusicService.php';

        if (!file_exists($musicServiceFile)) {
            S4L_Logger::error('MusicService.php could not be found. External provider action aborted.');
            return false;
        }

        require_once $musicServiceFile;
        return true;
    }

    private function runProvider($functionName, $label)
    {
        if (!function_exists($functionName)) {
            S4L_Logger::warning($label . ' provider action cannot be executed because helper function ' . $functionName . '() is not available.');
            return;
        }

        if (function_exists('VolumeProfiles')) {
            VolumeProfiles();
        }

        $functionName();
        S4L_Logger::debug($label . ' provider action has been executed.');
    }

    private function contextValue($name, $default = null)
    {
        return array_key_exists($name, $this->context) ? $this->context[$name] : $default;
    }
}
