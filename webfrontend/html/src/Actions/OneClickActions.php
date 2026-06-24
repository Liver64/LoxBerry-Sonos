<?php
/**
 * Sonos4Lox One-Click Actions
 * Version: V04.0
 * Language: EN
 *
 * Purpose:
 * - Extract current one-click and dynamic loop actions from legacy Sonos.php and PlaybackActions.
 * - Preserve the existing public URL syntax and SonosAccess behaviour.
 * - Keep nextpush, nextradio and zapzone URL compatibility unchanged in the one-click action group.
 * - V03.0 validates stale/empty zapzone state files before calling the legacy zap() helper.
 * - V04.0 removes the obsolete dynamic playlist helper dependency and keeps nextpush playlist stepping local.
 *
 * Migrated actions in V01.0:
 * - nextradio
 * - zapzone
 * - nextpush
 */

class S4L_OneClickActions
{
    private $context;
    private $request;
    private $master;
    private $sonoszone;
    private $sonos;
    private $volume;
    private $profileSelected;

    public function __construct($context, S4L_Request $request)
    {
        $this->context = is_array($context) ? $context : array();
        $this->request = $request;
        $this->master = $this->contextValue('master');
        $this->sonoszone = $this->contextValue('sonoszone', array());
        $this->sonos = $this->contextValue('sonos');
        $this->volume = $this->contextValue('volume', null);
        $this->profileSelected = $this->contextValue('profile_selected', array());
    }

    public function handle($action)
    {
        switch ($action) {
            case 'nextradio':
                $this->nextRadio();
                return;
            case 'zapzone':
                $this->zapZone();
                return;
            case 'nextpush':
                $this->nextPush();
                return;
        }
    }

    private function nextRadio()
    {
        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);
        nextradio();
        S4L_Logger::debug('Next radio has been executed.');
    }

    private function zapZone()
    {
        $this->resetStaleZapState();
        zap();
        S4L_Logger::debug('Zapzone has been executed.');
    }

    private function nextPush()
    {
        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);
        $posinfo = $this->sonos->GetPositionInfo();

        if ((empty($posinfo['TrackURI'])) and (empty($posinfo['UpnpClass']))) {
            nextradio();
            S4L_Logger::debug('Nextpush has been executed. Queue was empty.');
        } elseif (isset($posinfo['UpnpClass']) && $posinfo['UpnpClass'] === 'object.item') {
            nextradio();
            S4L_Logger::debug('Nextpush has been executed. Radio station was running.');
        } elseif (isset($posinfo['TrackURI']) && substr($posinfo['TrackURI'], 0, 18) === 'x-sonos-htastream:') {
            nextradio();
            S4L_Logger::debug('Nextpush has been executed. TV was running.');
        } elseif ((!empty($posinfo['TrackURI'])) and (!empty($posinfo['TrackDuration']))) {
            S4L_Logger::debug('Nextpush has been executed. Playlist was running.');
            try {
                $this->playNextPlaylistTrack();
            } catch (Exception $e) {
                $this->ignoreInvalidTransitionOrThrow($e, 'Nextpush');
            }
        } else {
            nextradio();
            S4L_Logger::debug('Nextpush has been executed. Unknown player state, fallback to radio.');
        }

        if ($this->request->has('profile') || $this->request->has('Profile')) {
            if (isset($this->profileSelected[0]['Player'][$this->master][0]['Volume'])) {
                $this->sonos->SetVolume($this->profileSelected[0]['Player'][$this->master][0]['Volume']);
                return;
            }
        }

        if ($this->volume !== null && $this->volume !== '') {
            $this->sonos->SetVolume($this->volume);
        }
    }


    private function playNextPlaylistTrack()
    {
        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);

        $positionInfo = $this->sonos->GetPositionInfo();
        $currentTrack = is_array($positionInfo) ? (int)($positionInfo['Track'] ?? 0) : 0;
        $trackUri = is_array($positionInfo) ? (string)($positionInfo['TrackURI'] ?? '') : '';

        $playlist = $this->sonos->GetCurrentPlaylist();
        $playlistCount = is_array($playlist) ? count($playlist) : 0;

        $this->sonos->SetPlayMode('0');
        $this->sonos->SetMute(false);

        if ($playlistCount < 1) {
            S4L_Logger::warning('Nextpush playlist handling found an empty queue. Falling back to nextradio.');
            nextradio();
            return;
        }

        if (($currentTrack > 0 && $currentTrack < $playlistCount) || substr($trackUri, 0, 9) === 'x-rincon:') {
            $this->sonos->Next();
            S4L_Logger::debug('Nextpush moved to the next playlist track.');
        } else {
            $this->sonos->SetTrack('1');
            S4L_Logger::debug('Nextpush restarted the playlist at track 1.');
        }

        $this->sonos->Play();
    }

    private function resetStaleZapState()
    {
        $zapFile = '/run/shm/s4lox_zap_' . $this->master . '.json';

        if (!is_file($zapFile)) {
            return;
        }

        $maxAge = 60;
        if (isset($GLOBALS['maxzap']) && is_numeric($GLOBALS['maxzap']) && (int)$GLOBALS['maxzap'] > 0) {
            $maxAge = (int)$GLOBALS['maxzap'];
        }

        $rawState = @file_get_contents($zapFile);
        $state = json_decode((string)$rawState, true);

        if (!is_array($state) || count($state) === 0) {
            @unlink($zapFile);
            S4L_Logger::debug('Zapzone state file has been reset because it was empty or invalid.');
            return;
        }

        $fileTime = @filemtime($zapFile);
        if ($fileTime === false) {
            @unlink($zapFile);
            S4L_Logger::debug('Zapzone state file has been reset because its file time could not be read.');
            return;
        }

        $age = time() - $fileTime;
        if ($age >= $maxAge) {
            @unlink($zapFile);
            S4L_Logger::debug('Zapzone state file has been reset because it is older than ' . $maxAge . ' seconds.');
            return;
        }

        if (isset($state['index'], $state['total']) && (int)$state['index'] >= (int)$state['total']) {
            S4L_Logger::debug('Zapzone state file is exhausted but still within the reset window. Keeping it for the configured fallback behaviour.');
        }
    }

    private function newSonos($zone)
    {
        return new SonosAccess($this->sonoszone[$zone][0]);
    }

    private function contextValue($name, $default = null)
    {
        return array_key_exists($name, $this->context) ? $this->context[$name] : $default;
    }

    private function ignoreInvalidTransitionOrThrow(Exception $exception, $label)
    {
        $message = $exception->getMessage();
        if (
            strpos($message, '701') !== false ||
            strpos($message, 'INVALID_TRANSITION') !== false ||
            strpos($message, 'ERROR_AV_UPNP_AVT_INVALID_TRANSITION') !== false
        ) {
            S4L_Logger::warning($label . ' ignored. Sonos returned invalid transition: ' . $message);
            return;
        }

        throw $exception;
    }
}
