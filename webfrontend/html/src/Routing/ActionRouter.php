<?php
/**
 * Sonos4Lox Action Router
 * Version: V27.0
 * Language: EN
 *
 * Purpose:
 * - Route selected legacy URL actions to refactored action classes.
 * - Return false for all non-migrated or legacy-protected actions so legacy Sonos.php can continue.
 *
 * Public URL compatibility:
 * - No query parameter is renamed.
 * - No public action name is changed.
 * - Deprecated or obsolete actions are intentionally not registered in the refactored layer.
 * - V27.0 improves user-facing log hints for invalid actions and unknown URL parameters.
 * - V27.1 whitelists the TTS playgong URL parameter.
 * - V27.0 whitelists external music provider URI parameters (trackuri, playlisturi, albumuri).
 * - V26.0 adds early URL parameter typo warnings before runtime preparation.
 * - V25.0 adds an early action availability guard for Sonos.php so unsupported
 *   URL actions are rejected before runtime preparation.
 * - V24.0 removes obsolete queue, group-management, device-data and
 *   radio/stream diagnostic actions from the refactored routing layer.
 * - V23.0 routes deprecated sendmessage/sendgroupmessage aliases through TtsActions.
 * - V22.0 adds AutoplaySettingsActions for TV/Soundbar autoplay setter actions.
 * - V21.0 moves present/absent from TtsActions to PresenceActions.
 * - V20.0 adds AlarmActions for alarmoff, alarmon and alarmstop.
 * - V19.0 adds DeviceActions for device, service and low-level status actions.
 * - V18.0 adds FollowActions for follow and leave.
 * - V17.0 adds ExtProviderActions for spotify, amazon, google, apple, napster and track.
 * - V16.0 removes obsolete one-click action aliases from the refactored routing layer.
 */

class S4L_ActionRouter
{
    private static $playbackActions = array(
        'play',
        'stop',
        'pause',
        'toggle',
        'next',
        'previous',
        'rewind',
        'playqueue',
        'clearqueue'
    );

    private static $oneClickActions = array(
        'nextradio',
        'zapzone',
        'nextpush'
    );


    private static $followActions = array(
        'follow',
        'leave'
    );

    private static $extProviderActions = array(
        'spotify',
        'amazon',
        'google',
        'apple',
        'napster',
        'track'
    );

    private static $deviceActions = array(
        'setmaxvolume',
        'masterplayer',
        'setledstate',
        'createstereopair',
        'seperatestereopair',
        'networkstatus',
        'linein',
        'battery',
        'getautolinkedzones',
        'getautoplayvolume',
        'getuseautoplayvolume',
        'debuginfo',
        'update',
        'services'
    );


    private static $alarmActions = array(
        'alarmoff',
        'alarmon',
        'alarmstop'
    );

    private static $presenceActions = array(
        'present',
        'absent'
    );

    private static $autoplaySettingsActions = array(
        'setautolinkedzones',
        'setautoplayvolume',
        'setuseautoplayvolume'
    );

    private static $volumeActions = array(
        'volume',
        'volumeup',
        'volumedown',
        'grvolup',
        'grvoldown',
        'mute',
        'togglemute',
        'getmute',
        'getvolume',
        'getgroupmute',
        'setgroupmute',
        'getgroupvolume',
        'setgroupvolume',
        'setrelativegroupvolume',
        'snapshotgroupvolume',
        'volumeout'
    );

    private static $soundActions = array(
        'setloudness',
        'getloudness',
        'settreble',
        'gettreble',
        'setbass',
        'getbass',
        'resetbasic',
        'crossfade',
        'surround',
        'subbass',
        'speech',
        'nightmode'
    );

    private static $groupActions = array(
        'addmember',
        'removemember',
        'group',
        'ungroup',
        'becomegroupcoordinator',
        'getzonegroupstate',
        'getzonegroupattributes',
        'getroomcoordinator',
        'getgroups',
        'getgroup'
    );

    private static $playlistActions = array(
        'sonosplaylist',
        'getfavorites',
        'browse',
        'playfavorite',
        'playallfavorites',
        'playtrackfavorites',
        'playradiofavorites',
        'playsonosplaylist',
        'playplfavorites',
        'getsonosplaylists',
        'getcurrentplaylist',
        'getimportedplaylists',
        'randomplaylist',
        'pluginradio'
    );


    private static $ttsActions = array(
        'say',
        'sendmessage',
        'sendgroupmessage',
        'doorbell',
        'playbatch',
        'mp3rights'
    );


    private static $systemActions = array(
        'stopall',
        'softstop',
        'softstopall',
        'playmode',
        'sleeptimer',
        'off',
        'on'
    );

    private static $infoActions = array(
        'getmediainfo',
        'getpositioninfo',
        'getzoneinfo',
        'gettransportsettings',
        'gettransportinfo',
        'getaudioinputattributes',
        'getzoneattributes',
        'getcurrenttransportactions',
        'getzonestatus',
        'listalarms',
        'getledstate'
    );

    private static $legacyProtectedActions = array(
    );



    private static $knownUrlParameters = array(
        'action',
        'zone',
        'member',
        'volume',
        'text',
        'source',
        'playlist',
        'radio',
        'favorite',
        'profile',
        'Profile',
        'batch',
        'debug',
        'nocache',
        'high',
        'urgent',
        'load',
        'rampto',
        'wait',
        'delay',
        'keepvolume',
        'zero',
        'except',
        'id',
        'timer',
        'host',
        'play',
        'function',
        'mode',
        'pair',
        'ip',
        'state',
        'value',
        'level',
        'bass',
        'treble',
        'loudness',
        'speech',
        'nightmode',
        'crossfade',
        'sub',
        'surround',
        'language',
        'lang',
        'voice',
        'engine',
        't2sengine',
        'apikey',
        'seckey',
        'message',
        'messageid',
        'file',
        'trackuri',
        'playlisturi',
        'albumuri',
        'playgong',
        'url'
    );

    private static $invalidActionHints = array(
        'trackfavorites' => 'playtrackfavorites',
        'radiofavorites' => 'playradiofavorites',
        'playlistfavorites' => 'playplfavorites'
    );

    public static function isKnownAction($action)
    {
        $action = self::normalizeAction($action);

        if ($action === '') {
            return false;
        }

        return in_array($action, self::knownActions(), true);
    }

    public static function logInvalidAction($action)
    {
        $action = self::normalizeAction($action);

        if ($action === '') {
            S4L_Logger::error('Required URL parameter action is missing. Request aborted before runtime preparation.');
            return;
        }

        $message = "Unsupported URL action '" . self::safeLogValue($action) . "'. The request was stopped before any Sonos command was executed. Please check the 'action' URL parameter.";

        if (isset(self::$invalidActionHints[$action])) {
            $message .= " Did you mean '" . self::$invalidActionHints[$action] . "'?";
        }

        S4L_Logger::error($message);
    }


    public static function warnUnknownParameters($action, array $queryParams)
    {
        $action = self::normalizeAction($action);

        if ($action === '' || empty($queryParams)) {
            return;
        }

        $knownParameters = self::knownUrlParameters();

        foreach (array_keys($queryParams) as $parameter) {
            $parameter = trim((string)$parameter);

            if ($parameter === '') {
                continue;
            }

            if (in_array($parameter, $knownParameters, true)) {
                continue;
            }

            $message = "Unknown URL parameter '" . self::safeLogValue($parameter) . "' for action '" . self::safeLogValue($action) . "'. The parameter was ignored. Please check the spelling of the URL parameter.";
            $hint = self::nearestParameterHint($parameter, $knownParameters);

            if ($hint !== '') {
                $message .= " Did you mean '" . $hint . "'?";
            }

            S4L_Logger::warning($message);
        }
    }

    private static function normalizeAction($action)
    {
        return strtolower(trim((string)$action));
    }

    private static function safeLogValue($value)
    {
        return str_replace(array("\r", "\n"), ' ', (string)$value);
    }


    private static function knownUrlParameters()
    {
        static $parameters = null;

        if ($parameters !== null) {
            return $parameters;
        }

        $parameters = array_values(array_unique(self::$knownUrlParameters));
        sort($parameters);

        return $parameters;
    }

    private static function nearestParameterHint($parameter, array $knownParameters)
    {
        $parameterLower = strtolower((string)$parameter);
        $best = '';
        $bestDistance = 99;

        foreach ($knownParameters as $knownParameter) {
            $knownLower = strtolower((string)$knownParameter);
            $distance = levenshtein($parameterLower, $knownLower);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $knownParameter;
            }
        }

        if ($bestDistance <= 2) {
            return $best;
        }

        return '';
    }

    private static function knownActions()
    {
        static $actions = null;

        if ($actions !== null) {
            return $actions;
        }

        $actions = array_values(array_unique(array_merge(
            self::$playbackActions,
            self::$oneClickActions,
            self::$followActions,
            self::$extProviderActions,
            self::$deviceActions,
            self::$alarmActions,
            self::$presenceActions,
            self::$autoplaySettingsActions,
            self::$volumeActions,
            self::$soundActions,
            self::$groupActions,
            self::$playlistActions,
            self::$ttsActions,
            self::$systemActions,
            self::$infoActions,
            self::$legacyProtectedActions
        )));

        sort($actions);
        return $actions;
    }

    public static function dispatchIfHandled($context)
    {
        $request = S4L_Request::fromGlobals();
        $action = strtolower($request->action());

        if ($action === '') {
            return false;
        }

        if (in_array($action, self::$legacyProtectedActions, true)) {
            S4L_Logger::debug('Refactor router leaves legacy-protected action to Sonos.php: ' . $action);
            return false;
        }

        if (in_array($action, self::$playbackActions, true)) {
            S4L_Logger::debug('Refactor router handles playback action: ' . $action);
            $handler = new S4L_PlaybackActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$oneClickActions, true)) {
            S4L_Logger::debug('Refactor router handles one-click action: ' . $action);
            $handler = new S4L_OneClickActions($context, $request);
            $handler->handle($action);
            return true;
        }


        if (in_array($action, self::$followActions, true)) {
            S4L_Logger::debug('Refactor router handles follow action: ' . $action);
            $handler = new S4L_FollowActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$extProviderActions, true)) {
            S4L_Logger::debug('Refactor router handles external provider action: ' . $action);
            $handler = new S4L_ExtProviderActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$deviceActions, true)) {
            S4L_Logger::debug('Refactor router handles device action: ' . $action);
            $handler = new S4L_DeviceActions($context, $request);
            $handler->handle($action);
            return true;
        }


        if (in_array($action, self::$alarmActions, true)) {
            S4L_Logger::debug('Refactor router handles alarm action: ' . $action);
            $handler = new S4L_AlarmActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$presenceActions, true)) {
            S4L_Logger::debug('Refactor router handles presence action: ' . $action);
            $handler = new S4L_PresenceActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$autoplaySettingsActions, true)) {
            S4L_Logger::debug('Refactor router handles autoplay settings action: ' . $action);
            $handler = new S4L_AutoplaySettingsActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$volumeActions, true)) {
            S4L_Logger::debug('Refactor router handles volume action: ' . $action);
            $handler = new S4L_VolumeActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$soundActions, true)) {
            S4L_Logger::debug('Refactor router handles sound action: ' . $action);
            $handler = new S4L_SoundActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$groupActions, true)) {
            S4L_Logger::debug('Refactor router handles group action: ' . $action);
            $handler = new S4L_GroupActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$playlistActions, true)) {
            S4L_Logger::debug('Refactor router handles playlist action: ' . $action);
            $handler = new S4L_PlaylistActions($context, $request);
            $handler->handle($action);
            return true;
        }


        if (in_array($action, self::$ttsActions, true)) {
            S4L_Logger::debug('Refactor router handles TTS action: ' . $action);
            $handler = new S4L_TtsActions($context, $request);
            $handler->handle($action);
            return true;
        }


        if (in_array($action, self::$systemActions, true)) {
            S4L_Logger::debug('Refactor router handles system action: ' . $action);
            $handler = new S4L_SystemActions($context, $request);
            $handler->handle($action);
            return true;
        }

        if (in_array($action, self::$infoActions, true)) {
            S4L_Logger::debug('Refactor router handles info action: ' . $action);
            $handler = new S4L_InfoActions($context, $request);
            $handler->handle($action);
            return true;
        }

        return false;
    }
}
