<?php
/**
 * Sonos4Lox Alarm Actions
 * Version: V02.0
 * Language: EN
 *
 * Purpose:
 * - Extract Sonos alarm related actions from legacy Sonos.php.
 * - Keep the public URL syntax unchanged.
 * - Keep the existing Alarm.php helper functions as low-level implementation for now.
 *
 * Migrated actions in V01.0:
 * - alarmoff
 * - alarmon
 * - alarmstop
 *
 * Changes in V02.0:
 * - Treat Sonos invalid transition 701 during alarmstop pause as informational fallback.
 * - Skip restore calls cleanly when no snapshot data exists for the requested master.
 */

class S4L_AlarmActions
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
            case 'alarmoff':
                $this->alarmOff();
                return;
            case 'alarmon':
                $this->alarmOn();
                return;
            case 'alarmstop':
                $this->alarmStop();
                return;
        }
    }

    private function alarmOff()
    {
        if ($this->request->has('id')) {
            $this->callLegacyFunction('turn_off_alarm', 'Specific Sonos alarm has been turned off.');
            return;
        }

        $this->callLegacyFunction('turn_off_alarms', 'All Sonos alarms have been turned off.');
    }

    private function alarmOn()
    {
        if ($this->request->has('id')) {
            $this->callLegacyFunction('restore_alarm', 'Specific Sonos alarm has been restored.');
            return;
        }

        $this->callLegacyFunction('restore_alarms', 'Previously saved Sonos alarms have been restored.');
    }

    private function alarmStop()
    {
        if (!is_object($this->sonos)) {
            S4L_Logger::warning('Alarm stop could not be executed because the Sonos instance is missing.');
            return;
        }

        try {
            $this->sonos->Pause();
        } catch (Exception $e) {
            if ($this->isInvalidTransitionException($e)) {
                S4L_Logger::info('Alarm stop pause fallback ignored for zone ' . $this->master . ' because the current transport state does not allow pause.');
            } else {
                S4L_Logger::warning('Alarm stop pause fallback ignored for zone ' . $this->master . '. Sonos returned: ' . $e->getMessage());
            }
        }

        if (!$this->hasSnapshotForMaster()) {
            S4L_Logger::info('Alarm stop skipped restore because no snapshot data exists for zone ' . $this->master . '.');
            return;
        }

        if ($this->request->has('member')) {
            $this->callLegacyFunction('restoreGroupZone', 'Alarm group restore has been executed.');
            return;
        }

        $this->callLegacyFunction('restoreSingleZone', 'Alarm single zone restore has been executed.');
    }

    private function isInvalidTransitionException(Exception $exception)
    {
        $message = $exception->getMessage();

        return strpos($message, 'ERROR_AV_UPNP_AVT_INVALID_TRANSITION') !== false
            || strpos($message, 'UPnPError s:Client 701') !== false
            || strpos($message, 'UPnPError 701') !== false;
    }

    private function hasSnapshotForMaster()
    {
        global $actual;

        if ($this->master === null || $this->master === '') {
            return false;
        }

        return is_array($actual) && isset($actual[$this->master]);
    }

    private function callLegacyFunction($functionName, $successMessage)
    {
        if (function_exists($functionName)) {
            call_user_func($functionName);
            S4L_Logger::debug($successMessage);
            return;
        }

        echo '<PRE>' . htmlspecialchars($functionName, ENT_QUOTES, 'UTF-8') . '() is not available.</PRE>';
        S4L_Logger::warning($functionName . ' could not be executed because the function is missing.');
    }

    private function contextValue($name, $default = null)
    {
        return array_key_exists($name, $this->context) ? $this->context[$name] : $default;
    }
}
