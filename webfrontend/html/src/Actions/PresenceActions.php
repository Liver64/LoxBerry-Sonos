<?php
/**
 * Sonos4Lox Presence Actions
 * Version: V01.0
 * Language: EN
 *
 * Purpose:
 * - Handle the public present/absent actions outside legacy Sonos.php.
 * - Keep URL compatibility unchanged.
 * - Store the TTS presence state in the existing Sonos4Lox JSON config.
 *
 * Public actions:
 * - present
 * - absent
 */

class S4L_PresenceActions
{
    private $request;

    public function __construct($context, S4L_Request $request)
    {
        $this->request = $request;
    }

    public function handle($action)
    {
        switch ($action) {
            case 'present':
                S4L_PresenceGuard::setPresenceEnabled(true);
                S4L_Logger::ok('Presence state has been changed by action present.');
                return;
            case 'absent':
                S4L_PresenceGuard::setPresenceEnabled(false);
                S4L_Logger::ok('Presence state has been changed by action absent.');
                return;
        }
    }
}
