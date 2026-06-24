<?php
/**
 * Sonos4Lox Autoplay Settings Actions
 * Version: V01.0
 * Language: EN
 *
 * Purpose:
 * - Extract TV/Soundbar autoplay setting actions from legacy Sonos.php.
 * - Keep the public URL syntax unchanged.
 * - Keep the actions isolated because they are device capability dependent.
 *
 * Migrated actions in V01.0:
 * - setautolinkedzones
 * - setautoplayvolume
 * - setuseautoplayvolume
 */

class S4L_AutoplaySettingsActions
{
    private $context;
    private $request;
    private $master;
    private $sonos;

    public function __construct($context, S4L_Request $request)
    {
        $this->context = is_array($context) ? $context : array();
        $this->request = $request;
        $this->master = $this->contextValue('master');
        $this->sonos = $this->contextValue('sonos');
    }

    public function handle($action)
    {
        switch ($action) {
            case 'setautolinkedzones':
                $this->setAutoplayLinkedZones();
                return;
            case 'setautoplayvolume':
                $this->setAutoplayVolume();
                return;
            case 'setuseautoplayvolume':
                $this->setUseAutoplayVolume();
                return;
        }
    }

    private function setAutoplayLinkedZones()
    {
        if (!$this->request->has('status')) {
            S4L_Logger::warning('Autoplay linked zones could not be set because parameter status is missing. Use status=true or status=false.');
            echo 'For Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' the status is missing. Please add &status=true or &status=false to your syntax';
            return;
        }

        $value = $this->normalizeBooleanString($this->request->get('status'));

        try {
            $this->sonos->SetAutoplayLinkedZones($value);
            S4L_Logger::debug('Autoplay linked zones for player ' . $this->master . ' has been set to ' . $value . ' in TV Autoplay mode.');
            echo 'Include linked zones for Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' has been set to ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ' in TV Autoplay mode.';
        } catch (Exception $e) {
            S4L_Logger::warning('Autoplay linked zones for player ' . $this->master . ' could not be set. Sonos returned: ' . $e->getMessage());
            echo 'Include linked zones for Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' could not be set to TV Autoplay mode.';
        }
    }

    private function setAutoplayVolume()
    {
        if (!$this->request->has('volume')) {
            S4L_Logger::warning('Autoplay volume could not be set because parameter volume is missing.');
            echo 'For Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' the volume is missing. Please add &volume=<VALUE> to your syntax';
            return;
        }

        $value = (int)$this->request->get('volume');
        if ($value < 0) {
            $value = 0;
        }
        if ($value > 100) {
            $value = 100;
        }

        try {
            $this->sonos->SetAutoplayVolume($value);
            S4L_Logger::debug('Autoplay volume for player ' . $this->master . ' has been set to ' . $value . ' in TV Autoplay mode.');
            echo 'Autoplay Volume for Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' has been set to ' . $value . ' in TV Autoplay mode.';
        } catch (Exception $e) {
            S4L_Logger::warning('Autoplay volume for player ' . $this->master . ' could not be set. Sonos returned: ' . $e->getMessage());
            echo 'Autoplay Volume for Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' could not be set!';
        }
    }

    private function setUseAutoplayVolume()
    {
        if (!$this->request->has('status')) {
            S4L_Logger::warning('Use autoplay volume could not be set because parameter status is missing. Use status=true or status=false.');
            echo 'For Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' the status is missing. Please add &status=true or &status=false to your syntax';
            return;
        }

        $value = $this->normalizeBooleanString($this->request->get('status'));

        try {
            $this->sonos->SetUseAutoplayVolume($value);
            S4L_Logger::debug('Use autoplay volume for player ' . $this->master . ' has been set to ' . $value . ' in TV Autoplay mode.');
            echo 'Use Auto Play Volume for Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' has been set to ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ' in TV Autoplay mode.';
        } catch (Exception $e) {
            S4L_Logger::warning('Use autoplay volume for player ' . $this->master . ' could not be set. Sonos returned: ' . $e->getMessage());
            echo 'Use Auto Play Volume for Player ' . htmlspecialchars($this->master, ENT_QUOTES, 'UTF-8') . ' could not be set!';
        }
    }

    private function normalizeBooleanString($value)
    {
        $normalized = strtolower(trim((string)$value));

        if ($normalized === '1' || $normalized === 'on' || $normalized === 'yes' || $normalized === 'true') {
            return 'true';
        }

        if ($normalized === '0' || $normalized === 'off' || $normalized === 'no' || $normalized === 'false') {
            return 'false';
        }

        return $normalized;
    }

    private function contextValue($key, $default = null)
    {
        return array_key_exists($key, $this->context) ? $this->context[$key] : $default;
    }
}
