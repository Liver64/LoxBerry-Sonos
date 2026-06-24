<?php
/**
 * Sonos4Lox Sound Actions
 * Version: V01.0
 * Language: EN
 *
 * Purpose:
 * - Extract sound, EQ and soundbar related cases from legacy Sonos.php.
 * - Preserve the existing public URL syntax and SonosAccess behaviour.
 * - Keep device-specific TV mode actions safe and compatible.
 *
 * Migrated actions in V01.0:
 * - setloudness, getloudness
 * - settreble, gettreble
 * - setbass, getbass
 * - resetbasic
 * - crossfade
 * - surround, subbass, speech, nightmode
 *
 * Compatibility note:
 * - playmode is intentionally not routed here because Sonos.php already handles
 *   the playmode query parameter before the router is called.
 */

class S4L_SoundActions
{
    private $context;
    private $request;
    private $master;
    private $sonoszone;
    private $sonos;

    public function __construct($context, S4L_Request $request)
    {
        $this->context = is_array($context) ? $context : array();
        $this->request = $request;
        $this->master = $this->contextValue('master');
        $this->sonoszone = $this->contextValue('sonoszone', array());
        $this->sonos = $this->contextValue('sonos');
    }

    public function handle($action)
    {
        switch ($action) {
            case 'setloudness':
                $this->setLoudness();
                return;
            case 'getloudness':
                $this->getLoudness();
                return;
            case 'settreble':
                $this->setTreble();
                return;
            case 'gettreble':
                $this->getTreble();
                return;
            case 'setbass':
                $this->setBass();
                return;
            case 'getbass':
                $this->getBass();
                return;
            case 'resetbasic':
                $this->resetBasicEq();
                return;
            case 'crossfade':
                $this->setCrossfade();
                return;
            case 'surround':
                $this->setTvDialogLevel('SurroundEnable', 'Surround');
                return;
            case 'subbass':
                $this->setTvDialogLevel('SubEnable', 'SubBass');
                return;
            case 'speech':
                $this->setTvDialogLevel('DialogLevel', 'Speech enhancement');
                return;
            case 'nightmode':
                $this->setTvDialogLevel('NightMode', 'Nightmode');
                return;
        }
    }

    private function setLoudness()
    {
        $loudness = $this->request->get('loudness', null);

        if (($loudness == 1) || ($loudness == 0)) {
            $this->sonos->SetLoudness($loudness);
            S4L_Logger::debug('Loudness has been set to ' . $loudness . '.');
            return;
        }

        S4L_Logger::ok('Wrong loudness mode selected.');
    }

    private function getLoudness()
    {
        echo '<PRE>';
        print_r($this->sonos->GetLoudness());
        echo '</PRE>';
        S4L_Logger::debug('Get loudness has been executed.');
    }

    private function setTreble()
    {
        $treble = $this->request->get('treble', null);
        $this->sonos->SetTreble($treble);
        S4L_Logger::debug('Treble has been set to ' . $treble . '.');
    }

    private function getTreble()
    {
        echo '<PRE>';
        print_r($this->sonos->GetTreble());
        echo '</PRE>';
        S4L_Logger::debug('Get treble has been executed.');
    }

    private function setBass()
    {
        $bass = $this->request->get('bass', null);
        $this->sonos->SetBass($bass);
        S4L_Logger::debug('Bass has been set to ' . $bass . '.');
    }

    private function getBass()
    {
        echo '<PRE>';
        print_r($this->sonos->GetBass());
        echo '</PRE>';
        S4L_Logger::debug('Get bass has been executed.');
    }

    private function resetBasicEq()
    {
        $this->sonos->ResetBasicEQ();
        S4L_Logger::debug('Basic EQ settings for player ' . $this->master . ' have been reset.');
    }

    private function setCrossfade()
    {
        $crossfade = $this->request->get('crossfade', null);

        if (!(is_numeric($crossfade) && (((int)$crossfade === 0) || ((int)$crossfade === 1)))) {
            S4L_Logger::warning('Wrong crossfade value entered. Use 0 for off or 1 for on.');
            exit;
        }

        $crossfade = (int)$crossfade;

        if (method_exists($this->sonos, 'SetCrossfadeMode')) {
            $this->sonos->SetCrossfadeMode($crossfade);
        } else {
            $this->sonos->SetCrossfade($crossfade);
        }

        S4L_Logger::debug('Crossfade has been set to ' . $crossfade . '.');
    }

    private function setTvDialogLevel($dialogKey, $label)
    {
        $this->sonos = $this->newSonos($this->master);
        $positionInfo = $this->sonos->GetPositionInfo();
        $trackUri = isset($positionInfo['TrackURI']) ? $positionInfo['TrackURI'] : '';

        if (substr($trackUri, 0, 18) !== 'x-sonos-htastream:') {
            S4L_Logger::warning('Player ' . $this->master . ' is not in TV mode. ' . $label . ' has not been changed.');
            return;
        }

        $mode = $this->request->get('mode', null);
        $value = ($mode === 'on') ? '1' : '0';

        $this->sonos->SetDialogLevel($value, $dialogKey);

        if ($value === '1') {
            S4L_Logger::debug($label . ' for player ' . $this->master . ' has been turned on.');
        } else {
            S4L_Logger::debug($label . ' for player ' . $this->master . ' has been turned off.');
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
}
