<?php
/**
 * Sonos4Lox Device Actions
 * Version: V03.0
 * Language: EN
 *
 * Purpose:
 * - Extract device, service and low-level status actions from legacy Sonos.php.
 * - Keep the public URL syntax unchanged.
 * - Keep destructive pairing actions available but isolated for safer testing.
 *
 * Migrated actions in V01.0:
 * - setmaxvolume, masterplayer, setledstate, createstereopair, seperatestereopair
 * - networkstatus, linein, battery
 * - getautolinkedzones, getautoplayvolume, getuseautoplayvolume
 * - debuginfo, update, services
 *
 * Changes in V03.0:
 * - Battery action now calls S4L_BatteryMonitor directly.
 * - bin/battery.php wrapper is no longer required.
 *
 * Changes in V02.0:
 * - Obsolete low-level device data action removed from the refactored layer.
 */

class S4L_DeviceActions
{
    private $context;
    private $request;
    private $master;
    private $sonoszone;
    private $sonoszonen;
    private $sonos;
    private $config;

    public function __construct($context, S4L_Request $request)
    {
        $this->context = is_array($context) ? $context : array();
        $this->request = $request;
        $this->master = $this->contextValue('master');
        $this->sonoszone = $this->contextValue('sonoszone', array());
        $this->sonoszonen = $this->contextValue('sonoszonen', array());
        $this->sonos = $this->contextValue('sonos');
        $this->config = $this->contextValue('config', array());
    }

    public function handle($action)
    {
        switch ($action) {
            case 'setmaxvolume':
                $this->setMaxVolume();
                return;
            case 'masterplayer':
                $this->masterPlayer();
                return;
            case 'setledstate':
                $this->setLedState();
                return;
            case 'createstereopair':
                $this->callLegacyFunction('CreateStereoPair', 'Create stereo pair');
                return;
            case 'seperatestereopair':
                $this->callLegacyFunction('SeperateStereoPair', 'Separate stereo pair');
                return;
            case 'networkstatus':
                $this->callLegacyFunction('networkstatus', 'Network status');
                return;
            case 'linein':
                $this->callLegacyFunction('LineIn', 'Line-in');
                return;
            case 'battery':
                $this->battery();
                return;
            case 'getautolinkedzones':
                $this->callLegacyFunction('GetAutoplayLinkedZones', 'Get autoplay linked zones');
                return;
            case 'getautoplayvolume':
                $this->callLegacyFunction('GetAutoplayVolume', 'Get autoplay volume');
                return;
            case 'getuseautoplayvolume':
                $this->callLegacyFunction('GetUseAutoplayVolume', 'Get use autoplay volume');
                return;
            case 'debuginfo':
                $this->callLegacyFunction('debugInfo', 'Debug info');
                return;
            case 'update':
                $this->update();
                return;
            case 'services':
                $this->services();
                return;
        }
    }

    private function setMaxVolume()
    {
        global $maxvolfile;

        $various = isset($this->config['VARIOUS']) && is_array($this->config['VARIOUS']) ? $this->config['VARIOUS'] : array();
        $volmax = isset($various['volmax']) ? $various['volmax'] : null;

        if (function_exists('is_enabled') && !is_enabled($volmax)) {
            S4L_Logger::warning('Max volume function is turned off in Sonos plugin config.');
            return;
        }

        if ($this->request->has('reset')) {
            if (!empty($maxvolfile)) {
                @unlink($maxvolfile);
            }
            S4L_Logger::debug('Max volume limit has been reset.');
            return;
        }

        if (!$this->request->has('volume')) {
            S4L_Logger::warning('Max volume could not be set because parameter volume is missing.');
            return;
        }

        $maxVolume = (int)$this->request->get('volume');
        if ($maxVolume < 0) {
            $maxVolume = 0;
        }
        if ($maxVolume > 100) {
            $maxVolume = 100;
        }

        $zones = array();
        if (isset($this->sonoszonen[$this->master][0])) {
            $zones[] = $this->sonoszonen[$this->master][0];
        } elseif (isset($this->sonoszone[$this->master][0])) {
            $zones[] = $this->sonoszone[$this->master][0];
        }

        if ($this->request->has('member')) {
            $members = explode(',', (string)$this->request->get('member'));
            foreach ($members as $member) {
                $member = trim($member);
                if ($member === '') {
                    continue;
                }
                if (isset($this->sonoszone[$member][0])) {
                    $zones[] = $this->sonoszone[$member][0];
                }
            }
        }

        $data = array(
            'volume' => $maxVolume,
            'zones'  => array_values(array_unique($zones))
        );

        if (empty($maxvolfile)) {
            S4L_Logger::warning('Max volume could not be stored because max volume file path is missing.');
            return;
        }

        file_put_contents($maxvolfile, json_encode($data));
        S4L_Logger::debug('Max volume has been set to ' . $maxVolume . '.');
    }

    private function masterPlayer()
    {
        foreach ($this->sonoszone as $player => $ip) {
            if (!is_array($ip) || empty($ip[0])) {
                continue;
            }

            $sonos = new SonosAccess($ip[0]);
            $positionInfo = $sonos->GetPositionInfo($ip);
            $trackUri = isset($positionInfo['TrackURI']) ? $positionInfo['TrackURI'] : '';
            $masterRincon = substr($trackUri, 9, 24);

            foreach ($this->sonoszone as $masterPlayer => $masterIp) {
                if (isset($this->sonoszone[$masterPlayer][1]) && trim($this->sonoszone[$masterPlayer][1]) === $masterRincon) {
                    echo '<br>' . htmlspecialchars($player, ENT_QUOTES, 'UTF-8') . ' -> ';
                    echo 'Master des Players: ' . htmlspecialchars($masterPlayer, ENT_QUOTES, 'UTF-8');
                }
            }
        }

        S4L_Logger::debug('Master player information has been executed.');
    }

    private function setLedState()
    {
        $state = (string)$this->request->get('state', '');
        $normalized = strtolower($state);

        if ($normalized !== 'on' && $normalized !== 'off') {
            echo '</PRE>';
            S4L_Logger::warning('LED state could not be set. Only On or Off is allowed.');
            return;
        }

        $sonosState = ($normalized === 'on') ? 'On' : 'Off';
        $this->sonos->SetLEDState($sonosState);
        S4L_Logger::debug('LED state for player ' . $this->master . ' has been set to ' . $sonosState . '.');
    }

    private function battery()
    {
        if (!class_exists('S4L_BatteryMonitor')) {
            S4L_Logger::warning('Battery information could not be executed because S4L_BatteryMonitor is not available.');
            return;
        }

        S4L_BatteryMonitor::run();
        S4L_Logger::debug('Battery information has been executed.');
    }

    private function update()
    {
        if (method_exists($this->sonos, 'CheckForUpdate')) {
            $update = $this->sonos->CheckForUpdate();
            if ($update !== null) {
                print_r($update);
            }
            S4L_Logger::debug('Sonos firmware update check has been executed.');
            return;
        }

        S4L_Logger::warning('Sonos firmware update check could not be executed because CheckForUpdate() is missing.');
    }

    private function services()
    {
        if (function_exists('loadServices')) {
            $services = loadServices();
            print_r($services);
            S4L_Logger::debug('Services list has been executed.');
            return;
        }

        echo '<PRE>loadServices() is not available.</PRE>';
        S4L_Logger::warning('Services list could not be executed because loadServices() is missing.');
    }

    private function callLegacyFunction($functionName, $label)
    {
        if (function_exists($functionName)) {
            call_user_func($functionName);
            S4L_Logger::debug($label . ' has been executed.');
            return;
        }

        echo '<PRE>' . htmlspecialchars($functionName, ENT_QUOTES, 'UTF-8') . '() is not available.</PRE>';
        S4L_Logger::warning($label . ' could not be executed because ' . $functionName . '() is missing.');
    }

    private function contextValue($name, $default = null)
    {
        return array_key_exists($name, $this->context) ? $this->context[$name] : $default;
    }
}
