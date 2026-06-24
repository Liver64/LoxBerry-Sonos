<?php
/**
 * Sonos4Lox Info Actions
 * Version: V03.0
 * Language: EN
 *
 * Purpose:
 * - Extract read-only information and diagnostic cases from legacy Sonos.php.
 * - Preserve the existing public URL syntax and SonosAccess behaviour.
 * - Keep state-changing service commands in the legacy layer until the cleanup phase.
 *
 * Migrated actions in V01.0:
 * - getmediainfo, getpositioninfo, getzoneinfo
 * - gettransportsettings, gettransportinfo
 * - getaudioinputattributes, getzoneattributes, getcurrenttransportactions
 * - getzonestatus, listalarms, getledstate
 *
 * Not migrated intentionally:
 * - getvolume is already handled by VolumeActions.
 * - getmute is already handled by VolumeActions.
 * - getloudness, gettreble and getbass are already handled by SoundActions.
 * - getzonegroupstate and getzonegroupattributes are already handled by GroupActions.
 * - getfavorites and playlist list actions are already handled by PlaylistActions.
 * - alarmoff, alarmon, alarmstop and setledstate change state and stay legacy until cleanup.
 *
 * Changes in V02.0:
 * - Obsolete radio/stream diagnostic actions removed from the refactored layer.
 *
 * Changes in V03.0:
 * - Sonos information calls now return a safe warning output when a player does not support a requested service.
 */

class S4L_InfoActions
{
    private $context;
    private $request;
    private $master;
    private $sonoszone;
    private $sonoszonen;
    private $sonos;

    public function __construct($context, S4L_Request $request)
    {
        $this->context = is_array($context) ? $context : array();
        $this->request = $request;
        $this->master = $this->contextValue('master');
        $this->sonoszone = $this->contextValue('sonoszone', array());
        $this->sonoszonen = $this->contextValue('sonoszonen', array());
        $this->sonos = $this->contextValue('sonos');
    }

    public function handle($action)
    {
        switch ($action) {
            case 'getmediainfo':
                $this->printSonosResult('GetMediaInfo', 'Get media info');
                return;
            case 'getpositioninfo':
                $this->printSonosResult('GetPositionInfo', 'Get position info');
                return;
            case 'gettransportsettings':
                $this->printSonosResult('GetTransportSettings', 'Get transport settings');
                return;
            case 'gettransportinfo':
                $this->printSonosResult('GetTransportInfo', 'Get transport info');
                return;
            case 'getaudioinputattributes':
                $this->printSonosResult('GetAudioInputAttributes', 'Get audio input attributes');
                return;
            case 'getzoneattributes':
                $this->printSonosResult('GetZoneAttributes', 'Get zone attributes');
                return;
            case 'getcurrenttransportactions':
                $this->printSonosResult('GetCurrentTransportActions', 'Get current transport actions');
                return;
            case 'getzonestatus':
                $this->getZoneStatus();
                return;
            case 'getzoneinfo':
                $this->getZoneInfo();
                return;
            case 'listalarms':
                $this->listAlarms();
                return;
            case 'getledstate':
                $this->getLedState();
                return;
        }
    }

    private function printSonosResult($method, $label)
    {
        echo '<PRE>';
        if (!method_exists($this->sonos, $method)) {
            echo 'SonosAccess method ' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . ' is not available.';
            echo '</PRE>';
            S4L_Logger::warning($label . ' could not be executed because SonosAccess method ' . $method . ' is missing.');
            return;
        }

        try {
            print_r($this->sonos->{$method}());
            echo '</PRE>';
            S4L_Logger::debug($label . ' has been executed.');
        } catch (Exception $e) {
            echo htmlspecialchars($label . ' is not available for this player or current player state.', ENT_QUOTES, 'UTF-8');
            echo '</PRE>';
            S4L_Logger::warning($label . ' could not be executed. Sonos returned: ' . $e->getMessage());
        }
    }

    private function getZoneStatus()
    {
        echo '<PRE>';
        if (function_exists('getZoneStatus')) {
            print_r(getZoneStatus($this->master));
            echo '</PRE>';
            S4L_Logger::debug('Get zone status has been executed.');
            return;
        }

        echo 'getZoneStatus() is not available.';
        echo '</PRE>';
        S4L_Logger::warning('Get zone status could not be executed because getZoneStatus() is missing.');
    }

    private function getZoneInfo()
    {
        $zoneInfo = $this->sonos->GetzoneInfo();

        echo '<PRE>';
        echo 'Technical details for selected zone: ' . $this->master;
        echo '<PRE>';
        echo '<PRE>';
        echo 'IP address: ' . $this->safeSubstr($zoneInfo, 'IPAddress', 30);
        echo '<PRE>';
        echo 'Serial number: ' . $this->safeSubstr($zoneInfo, 'SerialNumber', 50);
        echo '<PRE>';
        echo 'Software version: ' . $this->safeSubstr($zoneInfo, 'SoftwareVersion', 30);
        echo '<PRE>';
        echo 'Hardware version: ' . $this->safeSubstr($zoneInfo, 'HardwareVersion', 30);
        echo '<PRE>';
        echo 'MAC address: ' . $this->safeSubstr($zoneInfo, 'MACAddress', 30);
        echo '<PRE>';
        echo '<PRE>';
        echo 'RinconID: ' . trim($this->zoneValue($this->master, 1, ''));
        echo '</PRE>';

        S4L_Logger::debug('Get zone info has been executed.');
    }

    private function listAlarms()
    {
        $allAlarms = $this->sonos->ListAlarms();

        if (!is_array($allAlarms)) {
            echo '<PRE>';
            print_r($allAlarms);
            echo '</PRE>';
            S4L_Logger::warning('List alarms returned a non-array result.');
            return;
        }

        foreach ($allAlarms as $key => $value) {
            $startTime = isset($value['StartTime']) ? $value['StartTime'] : '00:00:00';
            $ex = explode(':', $startTime);
            $hour = isset($ex[0]) && is_numeric($ex[0]) ? (int)$ex[0] : 0;
            $minute = isset($ex[1]) && is_numeric($ex[1]) ? (int)$ex[1] : 0;
            $allAlarms[$key]['minpastmid'] = (($hour * 60) + $minute) - 10;
        }

        foreach ($allAlarms as $key => $value) {
            $rinc = isset($value['RoomUUID']) ? $value['RoomUUID'] : '';
            $room = function_exists('recursive_array_search') ? recursive_array_search($rinc, $this->sonoszonen) : false;
            $roomName = ($room === false) ? 'NO ROOM' : $room;
            $id = isset($allAlarms[$key]['ID']) ? $allAlarms[$key]['ID'] : 'UNKNOWN';
            $enabled = isset($allAlarms[$key]['Enabled']) ? $allAlarms[$key]['Enabled'] : '';

            $allAlarms[$key]['Room'] = $roomName;
            $allAlarms[$key]['min_' . $roomName . '_ID_' . $id] = $allAlarms[$key]['minpastmid'];
            $allAlarms[$key]['stat_' . $roomName . '_ID_' . $id] = $enabled;
        }

        echo '<PRE>';
        print_r($allAlarms);
        echo '</PRE>';
        S4L_Logger::debug('List alarms has been executed.');
    }

    private function getLedState()
    {
        echo '<PRE>';
        print_r($this->sonos->GetLEDState());
        echo '</PRE>';
        S4L_Logger::debug('Get LED state has been executed.');
    }

    private function safeSubstr($array, $key, $length)
    {
        $value = (is_array($array) && array_key_exists($key, $array)) ? $array[$key] : '';
        return substr((string)$value, 0, $length);
    }

    private function zoneValue($zone, $index, $default = '')
    {
        if (isset($this->sonoszone[$zone]) && is_array($this->sonoszone[$zone]) && array_key_exists($index, $this->sonoszone[$zone])) {
            return $this->sonoszone[$zone][$index];
        }

        return $default;
    }

    private function contextValue($name, $default = null)
    {
        return array_key_exists($name, $this->context) ? $this->context[$name] : $default;
    }
}
