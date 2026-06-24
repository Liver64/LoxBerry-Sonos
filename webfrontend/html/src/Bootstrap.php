<?php
/**
 * Sonos4Lox Refactor Bootstrap
 * Version: V30.0
 * Language: EN
 *
 * Purpose:
 * - Load the parallel refactoring layer.
 * - Keep the public URL syntax unchanged.
 * - Keep the legacy Sonos.php preparation and global helper functions available.
 *
 * Notes:
 * - File names are intentionally not versioned.
 * - Versioning is documented only in this file header.
 * - V30.0 adds BatteryMonitor as direct src/Support cron/action target.
 * - V28.0 adds RequestPreparation for structured Sonos.php runtime preparation.
 * - V27.0 documents that deprecated message aliases are now routed through TtsActions.
 * - V26.0 uses ErrorHandler V02 without HTTP client address logging.
 * - V25.0 adds ErrorHandler and removes the legacy s4l_log_application_error() helper from Sonos.php.
 * - V24.0 adds ShutdownHandler and removes the legacy shutdown() dependency from Sonos.php.
 * - V23.0 adds VolumeContext for request-wide volume preparation and group member volume handling.
 * - V22.0 adds AutoplaySettingsActions for TV/Soundbar autoplay setter actions.
 * - V21.0 adds PresenceActions and PresenceGuard for present/absent and TTS presence checks.
 * - V20.0 adds AlarmActions for alarmoff, alarmon and alarmstop.
 * - V19.0 adds DeviceActions for device, service and low-level status actions.
 * - V18.0 adds FollowActions for follow-me enter/leave actions.
 * - V17.0 adds ExtProviderActions for external provider and local track actions.
 * - V16.0 removes obsolete one-click action aliases from the refactor layer.
 * - V14.0 adds SystemActions for stopall, softstop, playmode, sleeptimer, off and on.
 * - V13.0 adds InfoActions for read-only diagnostics and status commands.
 * - V12.0 adds TtsActions for current Text-to-Speech public actions.
 * - V10.0 keeps PlaylistActions but removes obsolete alias and random selection routing from the refactored PHP layer.
 */

if (!defined('S4L_REFACTOR')) {
    define('S4L_REFACTOR', true);
}

require_once __DIR__ . '/Support/Logger.php';
require_once __DIR__ . '/Support/ShutdownHandler.php';
require_once __DIR__ . '/Support/ErrorHandler.php';
require_once __DIR__ . '/Support/PresenceGuard.php';
require_once __DIR__ . '/Support/VolumeContext.php';
require_once __DIR__ . '/Support/RequestPreparation.php';
require_once __DIR__ . '/Support/BatteryMonitor.php';
require_once __DIR__ . '/Http/Request.php';
require_once __DIR__ . '/Http/Response.php';
require_once __DIR__ . '/Actions/PlaybackActions.php';
require_once __DIR__ . '/Actions/OneClickActions.php';
require_once __DIR__ . '/Actions/ExtProviderActions.php';
require_once __DIR__ . '/Actions/FollowActions.php';
require_once __DIR__ . '/Actions/DeviceActions.php';
require_once __DIR__ . '/Actions/AlarmActions.php';
require_once __DIR__ . '/Actions/PresenceActions.php';
require_once __DIR__ . '/Actions/AutoplaySettingsActions.php';
require_once __DIR__ . '/Actions/VolumeActions.php';
require_once __DIR__ . '/Actions/SoundActions.php';
require_once __DIR__ . '/Actions/GroupActions.php';
require_once __DIR__ . '/Actions/PlaylistActions.php';
require_once __DIR__ . '/Actions/TtsActions.php';
require_once __DIR__ . '/Actions/InfoActions.php';
require_once __DIR__ . '/Actions/SystemActions.php';
require_once __DIR__ . '/Routing/ActionRouter.php';
