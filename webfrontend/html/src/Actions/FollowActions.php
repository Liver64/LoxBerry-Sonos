<?php
/**
 * Sonos4Lox Follow Actions
 * Version: V01.0
 * Language: EN
 *
 * Purpose:
 * - Extract follow-me actions from the legacy Sonos.php switch block.
 * - Preserve the existing public URL syntax and follow.php helper behaviour.
 * - Keep the current follow/leave implementation in follow.php for now.
 *
 * Migrated actions in V01.0:
 * - follow
 * - leave
 */

class S4L_FollowActions
{
    private $context;
    private $request;

    public function __construct($context, S4L_Request $request)
    {
        $this->context = is_array($context) ? $context : array();
        $this->request = $request;
    }

    public function handle($action)
    {
        switch ($action) {
            case 'follow':
                $this->runFollow();
                return;
            case 'leave':
                $this->runLeave();
                return;
        }
    }

    private function runFollow()
    {
        if (!function_exists('follow')) {
            S4L_Logger::error('follow() helper is not available. Follow action aborted.');
            return;
        }

        follow();
        S4L_Logger::debug('Follow action has been executed.');
    }

    private function runLeave()
    {
        if (!function_exists('leave')) {
            S4L_Logger::error('leave() helper is not available. Leave action aborted.');
            return;
        }

        leave();
        S4L_Logger::debug('Leave action has been executed.');
    }
}
