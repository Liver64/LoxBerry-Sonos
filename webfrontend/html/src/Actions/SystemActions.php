<?php
/**
 * Sonos4Lox System Actions
 * Version: V02.0
 * Language: EN
 *
 * Purpose:
 * - Extract remaining system/control related actions from legacy Sonos.php.
 * - Preserve the existing public URL syntax and SonosAccess behaviour.
 * - Keep optional URL parameters such as playmode and timer compatible with the legacy preparation flow.
 *
 * Migrated actions in V01.0:
 * - stopall, softstop, softstopall
 * - playmode
 * - sleeptimer
 * - off, on
 *
 * Changes in V02.0:
 * - Skip TV mode zones before Stop/Pause/Ramp actions to avoid Sonos UPnP 701 invalid transition warnings.
 */

class S4L_SystemActions
{
    private $context;
    private $request;
    private $master;
    private $sonoszone;
    private $sonos;
    private $config;
    private $offFile;

    private $validPlayModes = array(
        'NORMAL',
        'REPEAT_ALL',
        'REPEAT_ONE',
        'SHUFFLE_NOREPEAT',
        'SHUFFLE',
        'SHUFFLE_REPEAT_ONE'
    );

    public function __construct($context, S4L_Request $request)
    {
        $this->context = is_array($context) ? $context : array();
        $this->request = $request;
        $this->master = $this->contextValue('master');
        $this->sonoszone = $this->contextValue('sonoszone', array());
        $this->sonos = $this->contextValue('sonos');
        $this->config = $this->contextValue('config', array());
        $this->offFile = $this->contextValue('off_file', null);
    }

    public function handle($action)
    {
        switch ($action) {
            case 'stopall':
                $this->stopAll();
                return;
            case 'softstop':
                $this->softStop();
                return;
            case 'softstopall':
                $this->softStopAll();
                return;
            case 'playmode':
                $this->playMode();
                return;
            case 'sleeptimer':
                $this->sleepTimer();
                return;
            case 'off':
                $this->scriptOff();
                return;
            case 'on':
                $this->scriptOn();
                return;
        }
    }

    private function stopAll()
    {
        foreach ($this->sonoszone as $zone => $player) {
            checkifmaster($zone);
            $zoneSonos = $this->newSonos($zone);

            if ($this->isTvMode($zoneSonos, $zone)) {
                S4L_Logger::info('Stop all skipped for zone ' . $zone . ' because player is in TV mode.');
                continue;
            }

            $state = $zoneSonos->GetTransportInfo();

            if ($state == '1') {
                $zoneStatus = getZoneStatus($zone);
                if ($zoneStatus <> 'member') {
                    try {
                        $zoneSonos->Pause();
                    } catch (Exception $e) {
                        try {
                            $zoneSonos->Stop();
                        } catch (Exception $stopException) {
                            $this->ignoreInvalidTransitionOrThrow($stopException, 'Stop all fallback for zone ' . $zone);
                        }
                    }
                }
            }
        }

        S4L_Logger::debug('Stop/Pause all has been executed.');
    }

    private function softStop()
    {
        if ($this->isTvMode($this->sonos, $this->master)) {
            S4L_Logger::info('Softstop skipped for zone ' . $this->master . ' because player is in TV mode.');
            return;
        }

        $saveVolume = $this->sonos->GetVolume();
        $this->sonos->RampToVolume('SLEEP_TIMER_RAMP_TYPE', '0');
        $this->waitUntilVolumeZero($this->sonos, $this->master);

        checkifmaster($this->master);
        $this->sonos = $this->newSonos($this->master);

        try {
            $this->sonos->Pause();
        } catch (Exception $e) {
            try {
                $this->sonos->Stop();
            } catch (Exception $stopException) {
                $this->ignoreInvalidTransitionOrThrow($stopException, 'Softstop fallback');
            }
        }

        $this->sonos->SetVolume($saveVolume);
        S4L_Logger::debug('Softstop has been executed.');
    }

    private function softStopAll()
    {
        foreach ($this->sonoszone as $zone => $player) {
            checkifmaster($zone);
            $zoneSonos = $this->newSonos($zone);

            if ($this->isTvMode($zoneSonos, $zone)) {
                S4L_Logger::info('Softstopall skipped for zone ' . $zone . ' because player is in TV mode.');
                continue;
            }

            $state = $zoneSonos->GetTransportInfo();

            if ($state == '1') {
                $zoneStatus = getZoneStatus($zone);
                if ($zoneStatus <> 'member') {
                    $saveVolume = $zoneSonos->GetVolume();
                    $zoneSonos->RampToVolume('SLEEP_TIMER_RAMP_TYPE', '0');
                    $this->waitUntilVolumeZero($zoneSonos, $zone);

                    checkifmaster($zone);
                    $zoneSonos = $this->newSonos($zone);
                    try {
                        $zoneSonos->Pause();
                    } catch (Exception $e) {
                        try {
                            $zoneSonos->Stop();
                        } catch (Exception $stopException) {
                            $this->ignoreInvalidTransitionOrThrow($stopException, 'Softstopall fallback for zone ' . $zone);
                        }
                    }
                    $zoneSonos->SetVolume($saveVolume);
                }
            }
        }

        S4L_Logger::debug('Softstopall has been executed.');
    }

    private function playMode()
    {
        $playMode = preg_replace('/[^a-zA-Z0-9_]+/', '', strtoupper((string)$this->request->get('playmode', '')));

        if ($playMode === '' || !in_array($playMode, $this->validPlayModes, true)) {
            S4L_Logger::warning('Wrong PlayMode parameter selected. Please correct.');
            return;
        }

        $this->sonos = $this->newSonos($this->master);
        $rincon = $this->zoneValue($this->master, 1, '');
        if ($rincon !== '') {
            $this->sonos->SetQueue('x-rincon-queue:' . $rincon . '#0');
        }

        $mode = SetPlaymodes($this->master, $playMode);
        echo 'playmode: ' . $mode;
        S4L_Logger::debug("Playmode '" . $playMode . "' for player '" . $this->master . "' has been executed.");
    }

    private function sleepTimer()
    {
        $timer = $this->request->get('timer', null);

        if (!is_numeric($timer) || $timer <= 0 || $timer > 120) {
            S4L_Logger::warning('The entered sleeptimer value is not correct. Minutes between 1 and 120 are allowed.');
            return;
        }

        $timer = (int)$timer;
        if ($timer < 10) {
            $hours = '00';
            $minutes = '0' . $timer;
        } elseif ($timer > 60) {
            $hours = '0' . intval($timer / 60);
            $minutes = intval($timer % 60);
            if ($minutes < 10) {
                $minutes = '0' . $minutes;
            }
        } else {
            $hours = '00';
            $minutes = (string)$timer;
        }
        $seconds = '00';

        $this->sonos = $this->newSonos($this->master);
        $this->sonos->SetSleeptimer($hours, $minutes, $seconds);
        S4L_Logger::info("Sleeptimer has been switched on. Time to sleep for zone '" . $this->master . "' is '" . $timer . "' minutes.");
    }

    private function scriptOff()
    {
        $offFile = $this->offFile;
        if ($offFile === null || $offFile === '') {
            S4L_Logger::error('Script off could not be executed because the off file path is missing.');
            exit;
        }

        if (!touch($offFile)) {
            S4L_Logger::error('No permission to write off file.');
            exit;
        }

        $cronValue = $this->configValue(array('VARIOUS', 'cron'), '');
        $handle = fopen($offFile, 'w');
        if ($handle === false) {
            S4L_Logger::error('Unable to open off file for writing.');
            exit;
        }

        fwrite($handle, $cronValue);
        fclose($handle);

        echo 'sonos.php: Script has been turned OFF';
        S4L_Logger::ok('All actions for Sonos4Lox have been turned OFF.');
    }

    private function scriptOn()
    {
        $offFile = $this->offFile;
        if ($offFile === null || $offFile === '') {
            S4L_Logger::error('Script on could not be executed because the off file path is missing.');
            exit;
        }

        if (file_exists($offFile) === false) {
            echo 'sonos.php: Script is already ON';
            S4L_Logger::ok('Online check, UDP, HTTP and Sonos4Lox have not been turned off previously.');
            return;
        }

        @unlink($offFile);
        echo 'sonos.php: Script has been turned ON';
        S4L_Logger::ok('All actions for Sonos4Lox have been turned ON.');
    }


    private function isTvMode($sonos, $zone)
    {
        try {
            $positionInfo = $sonos->GetPositionInfo();
        } catch (Exception $e) {
            S4L_Logger::warning('TV mode check failed for zone ' . $zone . '. Continuing with normal handling. Sonos returned: ' . $e->getMessage());
            return false;
        }

        $trackUri = '';
        if (is_array($positionInfo) && array_key_exists('TrackURI', $positionInfo)) {
            $trackUri = (string)$positionInfo['TrackURI'];
        }

        if (strpos($trackUri, 'x-sonos-htastream:') === 0) {
            return true;
        }

        return false;
    }

    private function waitUntilVolumeZero($sonos, $zone)
    {
        $maxWaitSeconds = 70;
        $waitedSeconds = 0;

        while ($sonos->GetVolume() > 0) {
            sleep(1);
            $waitedSeconds++;

            if ($waitedSeconds >= $maxWaitSeconds) {
                S4L_Logger::warning('Volume ramp to zero timed out for zone ' . $zone . '. Continuing with stop/pause.');
                break;
            }
        }
    }

    private function newSonos($zone)
    {
        return new SonosAccess($this->sonoszone[$zone][0]);
    }

    private function zoneValue($zone, $index, $default = '')
    {
        if (isset($this->sonoszone[$zone]) && is_array($this->sonoszone[$zone]) && array_key_exists($index, $this->sonoszone[$zone])) {
            return $this->sonoszone[$zone][$index];
        }

        return $default;
    }

    private function configValue($path, $default = null)
    {
        $value = $this->config;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
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
