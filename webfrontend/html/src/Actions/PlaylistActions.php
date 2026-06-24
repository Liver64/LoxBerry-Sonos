<?php
/**
 * Sonos4Lox Playlist Actions
 * Version: V04.0
 * Language: EN
 *
 * Purpose:
 * - Extract Sonos playlist, Sonos favorite and radio-favorite related cases from legacy Sonos.php.
 * - Preserve the existing public URL syntax and helper function behaviour.
 * - Keep TTS and generic info actions in their own later migration groups.
 *
 * Migrated actions in V04.0:
 * - sonosplaylist
 * - getfavorites, browse
 * - playfavorite, playallfavorites, playtrackfavorites, playradiofavorites
 * - playsonosplaylist, playplfavorites
 * - getsonosplaylists, getcurrentplaylist, getimportedplaylists
 * - randomplaylist, pluginradio
 *
 * Removed from the refactored PHP layer in V04.0:
 * - Old radio helper actions are deprecated due to New TuneIn.
 * - Old one-word/group playlist aliases are obsolete and remain legacy-cleanup candidates.
 * - playtuneinfavorites is not part of the current command reference and belongs to old TuneIn handling.
 *
 * Compatibility note:
 * - nextradio and zapzone stay legacy-protected in PlaybackActions/ActionRouter for now.
 * - savesonos, add and radiourl stay legacy for now because they are
 *   debug/metadata helper flows and fit better into the later cleanup groups.
 */

class S4L_PlaylistActions
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
            case 'sonosplaylist':
                $this->sonosPlaylist(true);
                return;
            case 'getfavorites':
                $this->getFavorites();
                return;
            case 'browse':
                $this->browse();
                return;
            case 'playfavorite':
                $this->playFavorite();
                return;
            case 'playallfavorites':
                $this->playAllFavorites();
                return;
            case 'playtrackfavorites':
                $this->playTrackFavorites();
                return;
            case 'playradiofavorites':
                $this->playRadioFavorites();
                return;
            case 'playsonosplaylist':
                $this->playSonosPlaylist();
                return;
            case 'playplfavorites':
                $this->playPlaylistFavorites();
                return;
            case 'getsonosplaylists':
                $this->getSonosPlaylists();
                return;
            case 'getcurrentplaylist':
                $this->getCurrentPlaylist();
                return;
            case 'getimportedplaylists':
                $this->getImportedPlaylists();
                return;
            case 'randomplaylist':
                $this->randomPlaylist();
                return;
            case 'pluginradio':
                $this->pluginRadio();
                return;
        }
    }

    private function sonosPlaylist($applyVolumeProfiles)
    {
        if ($applyVolumeProfiles && function_exists('VolumeProfiles')) {
            VolumeProfiles();
        }

        playlist();
        S4L_Logger::debug('Playlist helper has been executed.');
    }


    private function getFavorites()
    {
        echo '<PRE>';
        GetFavorites();
        echo '</PRE>';
        S4L_Logger::debug('Get favorites has been executed.');
    }

    private function browse()
    {
        echo '<PRE>';
        $result = AddDetailsToMetadata();
        print_r($result);
        echo '<br>';
        S4L_Logger::debug('Browse favorites metadata has been executed.');
    }

    private function playFavorite()
    {
        if (function_exists('VolumeProfiles')) {
            VolumeProfiles();
        }

        PlayFavorite();
        S4L_Logger::debug('Play favorite has been executed.');
    }

    private function playAllFavorites()
    {
        PlayAllFavorites();
        S4L_Logger::debug('Play all favorites has been executed.');
    }

    private function playTrackFavorites()
    {
        if (function_exists('VolumeProfiles')) {
            VolumeProfiles();
        }

        PlayTrackFavorites();
        S4L_Logger::debug('Play track favorites has been executed.');
    }

    private function playRadioFavorites()
    {
        if (function_exists('VolumeProfiles')) {
            VolumeProfiles();
        }

        PlayRadioFavorites();
        S4L_Logger::debug('Play radio favorites has been executed.');
    }

    private function playSonosPlaylist()
    {
        if (function_exists('VolumeProfiles')) {
            VolumeProfiles();
        }

        PlaySonosPlaylist();
        S4L_Logger::debug('Play Sonos playlist favorite has been executed.');
    }


    private function playPlaylistFavorites()
    {
        if (function_exists('VolumeProfiles')) {
            VolumeProfiles();
        }

        PlayPlaylistFavorites();
        S4L_Logger::debug('Play playlist favorites has been executed.');
    }

    private function getSonosPlaylists()
    {
        echo '<PRE>';
        print_r($this->sonos->GetSonosPlaylists());
        echo '</PRE>';
        S4L_Logger::debug('Get Sonos playlists has been executed.');
    }

    private function getCurrentPlaylist()
    {
        echo '<PRE>';
        print_r($this->sonos->GetCurrentPlaylist());
        echo '</PRE>';
        S4L_Logger::debug('Get current playlist has been executed.');
    }

    private function getImportedPlaylists()
    {
        echo '<PRE>';
        print_r($this->sonos->GetImportedPlaylists());
        echo '</PRE>';
        S4L_Logger::debug('Get imported playlists has been executed.');
    }

    private function randomPlaylist()
    {
        if (function_exists('VolumeProfiles')) {
            VolumeProfiles();
        }

        random_playlist();
        S4L_Logger::debug('Random playlist has been executed.');
    }

    private function pluginRadio()
    {
        PluginRadio();
        S4L_Logger::debug('Plugin radio has been executed.');
    }

    private function contextValue($name, $default = null)
    {
        return array_key_exists($name, $this->context) ? $this->context[$name] : $default;
    }
}
