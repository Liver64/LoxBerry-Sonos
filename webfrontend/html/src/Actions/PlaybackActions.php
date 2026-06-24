<?php
/**
 * Sonos4Lox Playback Actions
 * Version: V07.0
 * Language: EN
 *
 * Purpose:
 * - First extraction of playback related cases from legacy Sonos.php.
 * - Preserve existing helper calls and SonosAccess behaviour.
 * - Keep the public URL syntax unchanged.
 *
 * Migrated actions:
 * - play, stop, pause, toggle, next, previous, rewind
 * - playqueue, clearqueue
 *
 * Changes in V05.0:
 * - nextpush moved to OneClickActions.
 * - nextradio and zapzone extraction references removed from PlaybackActions.
 *
 * Changes in V06.0:
 * - Obsolete queue item remove action removed from the refactored layer.
 *
 * Changes in V07.0:
 * - Play action now handles transient Sonos playback SOAP errors as a safe no-op.
 *
 * Compatibility note:
 * - playfile stays in the legacy switch until its exact behaviour is extracted.
 */

class S4L_PlaybackActions
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
            case 'play':
                $this->play();
                return;
            case 'stop':
                $this->stop();
                return;
            case 'pause':
                $this->pause();
                return;
            case 'toggle':
                $this->toggle();
                return;
            case 'next':
                $this->next();
                return;
            case 'previous':
                $this->previous();
                return;
            case 'rewind':
                $this->rewind();
                return;
            case 'playqueue':
                $this->playqueue();
                return;
            case 'clearqueue':
                $this->clearqueue();
                return;
        }
    }

    private function play()
    {
        try {
            $posinfo = $this->sonos->GetPositionInfo();
            $trackrunning = $this->sonos->GetTransportInfo();

            if (!empty($posinfo['TrackURI'])) {
                if (substr($posinfo['UpnpClass'], 0, 32) === 'object.item.audioItem.musicTrack') {
                    if ($trackrunning != '1') {
                        $this->sonos->Play();
                    } else {
                        S4L_Logger::debug('Zone is already playing. Canceled action play.');
                        return;
                    }
                } else {
                    $this->sonos->Play();
                }
                S4L_Logger::debug('Play has been executed.');
            } else {
                S4L_Logger::warning('Current Queue is empty.');
            }
        } catch (Exception $e) {
            S4L_Logger::warning('Play skipped because Sonos rejected the current playback state: ' . $e->getMessage());
        }
    }

    private function stop()
    {
        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);

        try {
            $this->sonos->Stop();
            S4L_Logger::debug('Stop has been executed.');
        } catch (Exception $e) {
            S4L_Logger::warning('Stop failed, trying Pause instead. Error: ' . $e->getMessage());
            try {
                $this->sonos->Pause();
                S4L_Logger::debug('Pause has been executed as fallback for Stop.');
            } catch (Exception $pauseException) {
                $this->ignoreInvalidTransitionOrThrow($pauseException, 'Pause fallback');
            }
        }
    }

    private function pause()
    {
        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);

        try {
            $this->sonos->Pause();
            S4L_Logger::debug('Pause has been executed.');
        } catch (Exception $e) {
            S4L_Logger::warning('Pause failed, trying Stop instead. Error: ' . $e->getMessage());
            try {
                $this->sonos->Stop();
                S4L_Logger::debug('Stop has been executed as fallback for Pause.');
            } catch (Exception $stopException) {
                $this->ignoreInvalidTransitionOrThrow($stopException, 'Stop fallback');
            }
        }
    }

    private function toggle()
    {
        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);

        if ($this->sonos->GetTransportInfo() == 1) {
            $this->sonos->Pause();
        } else {
            $this->sonos->Play();
        }
        S4L_Logger::debug('Toggle has been executed.');
    }

    private function next()
    {
        $positionInfo = $this->sonos->GetPositionInfo();
        $currentTrack = $positionInfo['Track'];
        $playlistCount = count($this->sonos->GetCurrentPlaylist());

        if ($currentTrack < $playlistCount) {
            checkifmaster($this->master);
            $this->sonos = $this->newSonos($this->master);
            @NextTrack();
        }
    }

    private function previous()
    {
        $positionInfo = $this->sonos->GetPositionInfo();
        $currentTrack = $positionInfo['Track'];

        if ($currentTrack <> '1') {
            checkifmaster($this->master);
            $this->sonos = $this->newSonos($this->master);
            $this->sonos->Previous();
            S4L_Logger::debug('Previous has been executed.');
        }
    }

    private function rewind()
    {
        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);
        $this->sonos->Rewind();
        S4L_Logger::debug('Rewind has been executed.');
    }

    private function playqueue()
    {
        $positionInfo = $this->sonos->GetPositionInfo();
        $currentTrack = $positionInfo['Track'];
        $playlistCount = count($this->sonos->GetCurrentPlaylist());

        if ($currentTrack < $playlistCount) {
            $this->sonos->SetQueue('x-rincon-queue:' . trim($this->sonoszone[$this->master][1]) . '#0');

            if (empty($this->config['TTS']['volrampto'])) {
                $this->config['TTS']['volrampto'] = '25';
                S4L_Logger::warning('Rampto Volume in config has not been set. Default 25% has been used.');
            }

            $volume = $this->contextValue('volume', null);
            if ($this->sonos->GetVolume() <= $this->config['TTS']['volrampto']) {
                $this->sonos->RampToVolume($this->config['TTS']['rampto'], $volume);
                $this->sonos->Play();
            } else {
                $this->sonos->Play();
            }
        } else {
            S4L_Logger::info('No tracks in playlist to play.');
        }

        S4L_Logger::debug('Playqueue has been executed.');
    }

    private function clearqueue()
    {
        $this->sonos->SetQueue('x-rincon-queue:' . trim($this->sonoszone[$this->master][1]) . '#0');
        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);
        $this->sonos->ClearQueue();
        S4L_Logger::debug('Queue has been cleared.');
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
