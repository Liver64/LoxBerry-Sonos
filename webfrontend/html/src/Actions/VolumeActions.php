<?php
/**
 * Sonos4Lox Volume Actions
 * Version: V02.0
 * Language: EN
 *
 * Purpose:
 * - Extract volume and mute related cases from legacy Sonos.php.
 * - Preserve the existing public URL syntax and SonosAccess behaviour.
 * - Keep sound/EQ actions for the separate SoundActions migration step.
 *
 * Migrated actions in V01.0:
 * - volume, volumeup, volumedown
 * - grvolup, grvoldown
 * - mute, togglemute
 * - getmute, getvolume
 * - getgroupmute, setgroupmute
 * - getgroupvolume, setgroupvolume, setrelativegroupvolume, snapshotgroupvolume
 * - volumeout
 *
 * Compatibility note:
 * - phonemute and phoneunmute intentionally stay in the legacy switch for now because
 *   they use global state files and exit paths.
 * - V02.0 keeps URL volume actions here and moves request-wide volume preparation
 *   plus the legacy volume_group() helper into src/Support/VolumeContext.php.
 * - setmaxvolume intentionally stays in a separate device/settings action group.
 */

class S4L_VolumeActions
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
            case 'volume':
                $this->setVolume();
                return;
            case 'volumeup':
                $this->volumeUp();
                return;
            case 'volumedown':
                $this->volumeDown();
                return;
            case 'grvolup':
                $this->groupVolumeUp();
                return;
            case 'grvoldown':
                $this->groupVolumeDown();
                return;
            case 'mute':
                $this->mute();
                return;
            case 'togglemute':
                $this->toggleMute();
                return;
            case 'getmute':
                $this->getMute();
                return;
            case 'getvolume':
                $this->getVolume();
                return;
            case 'getgroupmute':
                $this->getGroupMute();
                return;
            case 'setgroupmute':
                $this->setGroupMute();
                return;
            case 'getgroupvolume':
                $this->getGroupVolume();
                return;
            case 'setgroupvolume':
                $this->setGroupVolume();
                return;
            case 'setrelativegroupvolume':
                $this->setRelativeGroupVolume();
                return;
            case 'snapshotgroupvolume':
                $this->snapshotGroupVolume();
                return;
            case 'volumeout':
                $this->volumeOut();
                return;
        }
    }

    private function setVolume()
    {
        $volume = $this->contextValue('volume', null);

        if ($volume !== null && $volume !== '' && is_numeric($volume)) {
            $this->sonos->SetVolume($volume);
            S4L_Logger::debug('Volume has been set to ' . $volume . '.');
            return;
        }

        S4L_Logger::warning('Wrong range of values for volume has been entered, only 0-100 is permitted.');
        exit;
    }

    private function volumeUp()
    {
        $currentVolume = $this->sonos->GetVolume();
        usleep(500000);
        $newVolume = $currentVolume + $this->configValue(array('MP3', 'volumeup'), 0);
        $this->sonos->SetVolume($newVolume);
        S4L_Logger::debug('Volume up has been executed. New target volume: ' . $newVolume . '.');
    }

    private function volumeDown()
    {
        $currentVolume = $this->sonos->GetVolume();
        usleep(500000);
        $newVolume = $currentVolume - $this->configValue(array('MP3', 'volumedown'), 0);
        $this->sonos->SetVolume($newVolume);
        S4L_Logger::debug('Volume down has been executed. New target volume: ' . $newVolume . '.');
    }

    private function groupVolumeUp()
    {
        $this->sonos = $this->newSonos($this->master);
        $currentVolume = $this->sonos->GetGroupVolume();
        usleep(500000);
        $newVolume = $currentVolume + $this->configValue(array('MP3', 'volumeup'), 0);
        SetGroupVolume($newVolume);
        S4L_Logger::debug('Group volume up has been executed. New target group volume: ' . $newVolume . '.');
    }

    private function groupVolumeDown()
    {
        $this->sonos = $this->newSonos($this->master);
        $currentVolume = $this->sonos->GetGroupVolume();
        usleep(500000);

        // Preserve legacy behaviour: grvoldown uses MP3.volumeup, not MP3.volumedown.
        $newVolume = $currentVolume - $this->configValue(array('MP3', 'volumeup'), 0);
        SetGroupVolume($newVolume);
        S4L_Logger::debug('Group volume down has been executed. New target group volume: ' . $newVolume . '.');
    }

    private function mute()
    {
        $mute = $this->request->get('mute', null);

        if ($mute == 'false') {
            $this->sonos->SetMute(false);
            S4L_Logger::debug('Mute has been set to false.');
            return;
        }

        if ($mute == 'true') {
            $this->sonos->SetMute(true);
            S4L_Logger::debug('Mute has been set to true.');
            return;
        }

        S4L_Logger::error('Wrong mute parameter selected. Please correct.');
        exit;
    }

    private function toggleMute()
    {
        $mute = $this->sonos->GetMute();

        if ($mute === true) {
            $this->sonos->SetMute(false);
        } else {
            $this->sonos->SetMute(true);
        }

        S4L_Logger::debug('Toggle mute has been executed.');
    }

    private function getMute()
    {
        echo '<PRE>';
        print_r($this->sonos->GetMute());
        echo '</PRE>';
        S4L_Logger::debug('Get mute has been executed.');
    }

    private function getVolume()
    {
        echo '<PRE>';
        print_r($this->sonos->GetVolume());
        echo '</PRE>';
        S4L_Logger::debug('Get volume has been executed.');
    }

    private function getGroupMute()
    {
        // Preserve legacy behaviour: the value is fetched but not printed.
        $this->sonos->GetGroupMute();
        S4L_Logger::debug('Get group mute has been executed.');
    }

    private function setGroupMute()
    {
        $mute = $this->request->get('mute', null);

        if (($mute == 1) || ($mute == 0)) {
            $this->sonos->SetGroupMute($mute);
            S4L_Logger::info('Group mute has been set to ' . $mute . '.');
            return;
        }

        S4L_Logger::warning('Unknown group mute value.');
    }

    private function getGroupVolume()
    {
        $this->sonos = $this->newSonos($this->master);
        $this->sonos->SnapshotGroupVolume();
        $groupVolume = $this->sonos->GetGroupVolume();
        echo $groupVolume;
        S4L_Logger::debug('Get group volume has been executed.');
    }

    private function setGroupVolume()
    {
        $groupVolume = $this->request->get('volume', null);
        SetGroupVolume($groupVolume);
        S4L_Logger::info('Group volume has been set.');
    }

    private function setRelativeGroupVolume()
    {
        $groupVolume = $this->request->get('volume', null);
        SetRelativeGroupVolume($groupVolume);
        S4L_Logger::info('Relative group volume has been set.');
    }

    private function snapshotGroupVolume()
    {
        $this->sonos->SnapshotGroupVolume();
        S4L_Logger::debug('Snapshot group volume has been executed.');
    }

    private function volumeOut()
    {
        curr_volume();
        S4L_Logger::debug('Volume output has been executed.');
    }

    private function newSonos($zone)
    {
        return new SonosAccess($this->sonoszone[$zone][0]);
    }

    private function contextValue($name, $default = null)
    {
        return array_key_exists($name, $this->context) ? $this->context[$name] : $default;
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
}
