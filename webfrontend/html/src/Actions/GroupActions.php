<?php
/**
 * Sonos4Lox Group Actions
 * Version: V03.0
 * Language: EN
 *
 * Purpose:
 * - Extract group and member related cases from legacy Sonos.php.
 * - Preserve the existing public URL syntax and SonosAccess behaviour.
 * - Keep follow/presence logic in the legacy switch for now because it uses
 *   runtime files and additional fallback flows.
 *
 * Migrated actions in V01.0:
 * - addmember, removemember
 * - group, ungroup
 * - becomegroupcoordinator
 * - getzonegroupstate, getzonegroupattributes
 * - getroomcoordinator, getgroups, getgroup
 *
 * Compatibility note:
 * - follow and leave intentionally stay in the legacy switch for now.
 * - grouping is intentionally not migrated because the legacy case calls Group($master),
 *   but no matching function exists in the current code base.
 * - createstereopair and seperatestereopair stay legacy-protected because they are
 *   destructive pairing actions and need a separate cleanup step.
 *
 * Changes in V03.0:
 * - Obsolete group subscription action removed from the refactored layer.
 */

class S4L_GroupActions
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
            case 'addmember':
                $this->addMember();
                return;
            case 'removemember':
                $this->removeMember();
                return;
            case 'group':
                $this->groupAll();
                return;
            case 'ungroup':
                $this->ungroupAll();
                return;
            case 'becomegroupcoordinator':
                $this->becomeGroupCoordinator();
                return;
            case 'getzonegroupstate':
                $this->getZoneGroupState();
                return;
            case 'getzonegroupattributes':
                $this->getZoneGroupAttributes();
                return;
            case 'getroomcoordinator':
                $this->getRoomCoordinator();
                return;
            case 'getgroups':
                $this->getGroups();
                return;
            case 'getgroup':
                $this->getGroup();
                return;
        }
    }

    private function addMember()
    {
        global $memberon, $sleepaddmember;

        $memberRaw = $this->request->get('member', null);
        if ($memberRaw === null || $memberRaw === '') {
            S4L_Logger::warning('No member parameter has been entered for addmember.');
            return;
        }

        if ($memberRaw === 'all') {
            $memberon = array();
            foreach ($this->sonoszone as $zone => $ip) {
                if ($zone !== $this->master) {
                    $memberon[] = $zone;
                }
            }
        } else {
            $memberon = explode(',', $memberRaw);
        }

        $member = function_exists('member_on') ? member_on($memberon) : $memberon;

        if (!defined('MEMBER')) {
            define('MEMBER', $member);
        }
        if (!defined('GROUPMASTER')) {
            define('GROUPMASTER', $this->master);
        }
        if (!defined('T2SMASTER')) {
            define('T2SMASTER', $this->master);
        }

        $sleepSeconds = is_numeric($sleepaddmember) ? (float)$sleepaddmember : 0;

        foreach ($member as $zone) {
            if ($zone === $this->master) {
                continue;
            }

            if (!isset($this->sonoszone[$zone][0])) {
                S4L_Logger::warning('Zone ' . $zone . ' could not be added because it is unknown.');
                continue;
            }

            $memberSonos = new SonosAccess($this->sonoszone[$zone][0]);
            try {
                $memberSonos->SetAVTransportURI('x-rincon:' . trim($this->sonoszone[$this->master][1]));
                S4L_Logger::info('Zone ' . $zone . ' has been added to master ' . $this->master . '.');
            } catch (Exception $e) {
                S4L_Logger::warning('Zone ' . $zone . ' could not be added to master ' . $this->master . '. Error: ' . $e->getMessage());
            }

            $memberSonos->SetMute(false);

            if ($sleepSeconds > 0) {
                usleep((int)($sleepSeconds * 1000000));
            }
        }

        $this->sonos = $this->newSonos($this->master);
    }

    private function removeMember()
    {
        $memberRaw = $this->request->get('member', null);
        if ($memberRaw === null || $memberRaw === '') {
            S4L_Logger::warning('No member parameter has been entered for removemember.');
            return;
        }

        $members = explode(',', $memberRaw);
        if (in_array($this->master, $members, true)) {
            S4L_Logger::warning('The zone ' . $this->master . ' could not be entered as member again. Remove it from the member parameter.');
        }

        $memberon = array();
        foreach ($members as $zone) {
            if (!isset($this->sonoszone[$zone][0])) {
                S4L_Logger::warning('Zone ' . $zone . ' could not be removed because it is unknown.');
                continue;
            }

            $zoneOnline = function_exists('checkZoneOnline') ? checkZoneOnline($zone) : true;
            if ($zoneOnline === true) {
                $memberon[] = $zone;
            }
        }

        foreach ($memberon as $zone) {
            $memberSonos = new SonosAccess($this->sonoszone[$zone][0]);
            $memberSonos->BecomeCoordinatorOfStandaloneGroup();
            S4L_Logger::info('Player ' . $zone . ' has been removed from group ' . $this->master . '.');
        }
    }

    private function groupAll()
    {
        $requestedZone = $this->request->get('zone', $this->master);

        foreach ($this->sonoszone as $zone => $ip) {
            if ($zone === $requestedZone) {
                continue;
            }

            $memberSonos = new SonosAccess($this->sonoszone[$zone][0]);
            $memberSonos->SetAVTransportURI('x-rincon:' . trim($this->sonoszone[$this->master][1]));
        }

        S4L_Logger::info('All Sonos players have been grouped to master ' . $this->master . '.');
    }

    private function ungroupAll()
    {
        foreach ($this->sonoszone as $zone => $ip) {
            $memberSonos = new SonosAccess($this->sonoszone[$zone][0]);
            $memberSonos->SetQueue('x-rincon-queue:' . trim($this->sonoszone[$zone][1]) . '#0');
        }

        S4L_Logger::info('All Sonos players have been ungrouped.');
    }

    private function becomeGroupCoordinator()
    {
        echo '<PRE>';
        $this->sonos->BecomeCoordinatorOfStandaloneGroup();
        echo '</PRE>';
        S4L_Logger::debug('Zone ' . $this->master . ' is now in single mode.');
    }

    private function getZoneGroupState()
    {
        if (function_exists('GetZoneState')) {
            GetZoneState();
            S4L_Logger::debug('Get zone group state has been executed.');
            return;
        }

        echo '<PRE>';
        print_r($this->sonos->GetZoneStates());
        echo '</PRE>';
        S4L_Logger::debug('Get zone group state has been executed via SonosAccess fallback.');
    }

    private function getZoneGroupAttributes()
    {
        echo '<PRE>';
        print_r($this->sonos->GetZoneGroupAttributes());
        echo '</PRE>';
        S4L_Logger::debug('Get zone group attributes has been executed.');
    }

    private function getRoomCoordinator()
    {
        if (function_exists('getRoomCoordinator')) {
            getRoomCoordinator($this->master);
            S4L_Logger::debug('Get room coordinator has been executed for zone ' . $this->master . '.');
            return;
        }

        S4L_Logger::warning('getRoomCoordinator function is not available.');
    }

    private function getGroups()
    {
        if (function_exists('getGroups')) {
            getGroups();
            S4L_Logger::debug('Get groups has been executed.');
            return;
        }

        S4L_Logger::warning('getGroups function is not available.');
    }

    private function getGroup()
    {
        if (function_exists('getGroup')) {
            echo '<PRE>';
            print_r(getGroup($this->master));
            echo '</PRE>';
            S4L_Logger::debug('Get group has been executed for zone ' . $this->master . '.');
            return;
        }

        S4L_Logger::warning('getGroup function is not available.');
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
