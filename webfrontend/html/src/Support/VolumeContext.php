<?php
/**
 * Sonos4Lox Volume Context Support
 * Version: V03.5
 * Language: EN
 *
 * Purpose:
 * - Resolve the effective request volume for the master zone before routing.
 * - Keep the legacy global $volume value available for Play_T2S.php and action classes.
 * - Move the legacy volume_group() helper out of Sonos.php without changing callers.
 *
 * Compatibility:
 * - Public URL parameters are unchanged.
 * - The global function volume_group() is kept as a compatibility wrapper.
 */

class S4L_VolumeContext
{
    public static function resolveMasterVolume($config, $sonoszone, $master, $minVol, $profileSelected = null)
    {
        if (isset($_GET['profile']) && isset($_GET['volume'])) {
            self::log("optional parameter 'volume' in conjunction with 'profile' could not be used. Please correct your syntax!", 3);
            exit;
        }

        if ($master === null || $master === '' || !isset($sonoszone[$master])) {
            self::log("Master zone '" . $master . "' is not set or unknown. Volume context skipped.", 4);
            return null;
        }

        if (
            (isset($_GET['volume']) && is_numeric($_GET['volume']) && $_GET['volume'] >= 0 && $_GET['volume'] <= 100)
            || isset($_GET['keepvolume'])
            || isset($_GET['rampto'])
        ) {
            if (isset($_GET['volume'])) {
                $volume = $_GET['volume'];

                if ($volume > $sonoszone[$master][5]) {
                    $volume = $sonoszone[$master][5];
                    self::log("Requested Volume for Player '" . $master . "' (" . $_GET['volume'] . ") was reduced to Max Volume " . $volume . ".", 7);
                } else {
                    self::log('Volume for Player ' . $master . ' has been set to: ' . $volume, 7);
                }

                // Publish the normalized request volume back to the superglobals.
                // Several legacy playback helpers still read $_GET['volume'] directly
                // after request preparation. Without this, they can overwrite the
                // already limited context volume with the original out-of-range value.
                $_GET['volume'] = $volume;
                if (isset($_REQUEST['volume'])) {
                    $_REQUEST['volume'] = $volume;
                }

                return $volume;
            }

            if (isset($_GET['keepvolume'])) {
                $sonos = new SonosAccess($sonoszone[$master][0]);
                $currentVolume = $sonos->GetVolume();

                if ($currentVolume >= $minVol) {
                    self::log('Volume for Player ' . $master . ' has been set to current volume', 7);
                    return $currentVolume;
                }

                if (self::isTtsLikeRequest()) {
                    $volume = $sonoszone[$master][3];
                    self::log('T2S Volume for Player ' . $master . ' is less then ' . $minVol . ' and has been set exceptional to Standard volume ' . $volume, 7);
                    return $volume;
                }

                $volume = $sonoszone[$master][4];
                self::log('Volume for Player ' . $master . ' is less then ' . $minVol . ' and has been set exceptional to Standard volume ' . $volume, 7);
                return $volume;
            }

            // Preserve legacy behaviour: rampto without an explicit volume does not force a new volume value here.
            return null;
        }

        if (!isset($_GET['volume']) && !isset($_GET['profile'])) {
            if (self::isTtsLikeRequest()) {
                $volume = $sonoszone[$master][3];
                self::log('Standard T2S Volume for Player ' . $master . ' has been set to: ' . $volume, 7);
                return $volume;
            }

            $requestedVolume = $sonoszone[$master][4];
            $volume = self::limitToMaxVolume($requestedVolume, $sonoszone, $master);

            if ((float)$volume < (float)$requestedVolume) {
                self::log('Standard Sonos Volume for Player ' . $master . ' has been limited to Max Volume: ' . $volume, 7);
            } else {
                self::log('Standard Sonos Volume for Player ' . $master . ' has been set to: ' . $volume, 7);
            }

            return $volume;
        }

        return null;
    }

    public static function applyGroupVolume()
    {
        global $sonoszone, $sonos, $master, $volume, $config, $sonoszonen, $min_vol, $profile_details;

        if (isset($_GET['zone']) && $_GET['zone'] !== '') {
            $master = $_GET['zone'];
        }

        if (!defined('MEMBER') || !is_array(MEMBER) || count(MEMBER) === 0) {
            self::log('volume_group(): MEMBER is undefined or empty... skipping group volume (master only).', 7);
            return;
        }

        if (empty($master) || !isset($sonoszone[$master])) {
            self::log("volume_group(): Master zone '" . $master . "' is not set or unknown - aborting group volume.", 3);
            return;
        }

        $sonos = new SonosAccess($sonoszone[$master][0]);
        $appliedVolumes = array();

        foreach (MEMBER as $memplayer => $zone2) {
            if (!isset($sonoszone[$zone2])) {
                self::log("volume_group(): Unknown member zone '" . $zone2 . "' - skipped in volume_group.", 4);
                continue;
            }

            $sonos = new SonosAccess($sonoszone[$zone2][0]);

            // Keep the master's resolved volume untouched. The previous code reused the
            // global $volume for every member, so the last member volume accidentally
            // became the master volume when the caller continued after volume_group().
            $memberVolume = $volume;

            if (isset($_GET['volume']) || isset($_GET['groupvolume']) || isset($_GET['keepvolume'])) {
                if (isset($_GET['volume'])) {
                    $requestedMemberVolume = $_GET['volume'];
                    $memberVolume = self::limitToMaxVolume($requestedMemberVolume, $sonoszone, $zone2);
                    if ((float)$memberVolume < (float)$requestedMemberVolume) {
                        self::log("Requested Volume for Group Member '" . $zone2 . "' (" . $requestedMemberVolume . ") was reduced to Max Volume " . $memberVolume . ".", 7);
                    } else {
                        self::log('Volume for Group Member ' . $zone2 . ' has been set to: ' . $memberVolume, 7);
                    }
                } elseif (isset($_GET['groupvolume'])) {
                    $groupVolume = $_GET['groupvolume'];
                    $currentVolume = $sonos->GetVolume();
                    $memberVolume = $currentVolume + ($currentVolume * ($groupVolume / 100));

                    if ($memberVolume > 100) {
                        $memberVolume = 100;
                    }

                    $memberVolume = self::limitToMaxVolume($memberVolume, $sonoszone, $zone2);
                    self::log('Group Volume for Member ' . $zone2 . ' has been set to: ' . $memberVolume, 7);
                } elseif (isset($_GET['keepvolume'])) {
                    $currentMemberVolume = $sonos->GetVolume();

                    if ($currentMemberVolume >= $min_vol) {
                        $memberVolume = self::limitToMaxVolume($currentMemberVolume, $sonoszone, $zone2);
                        self::log('Volume for Member ' . $zone2 . ' has been set to current volume: ' . $memberVolume, 7);
                    } else {
                        if (self::isTtsLikeRequest()) {
                            $memberVolume = $sonoszone[$zone2][3];
                            self::log('T2S Volume for Member ' . $zone2 . ' is less then ' . $min_vol . ' and has been set exceptional to Standard volume ' . $memberVolume, 7);
                        } else {
                            $memberVolume = $sonoszone[$zone2][4];
                            self::log('Volume for Member ' . $zone2 . ' is less then ' . $min_vol . ' and has been set exceptional to Standard volume ' . $memberVolume, 7);
                        }
                    }
                }
            } else {
                if (self::isTtsLikeRequest()) {
                    $memberVolume = $sonoszone[$zone2][3];
                    self::log('Standard T2S Volume for Member ' . $zone2 . ' has been set to: ' . $memberVolume, 7);
                } else {
                    if ((isset($_GET['profile']) || isset($_GET['Profile'])) && self::hasProfileVolume($profile_details, $zone2)) {
                        $memberVolume = $profile_details[0]['Player'][$zone2][0]['Volume'];
                        self::log("Profile volume for Group Member '" . $zone2 . "' has been selected: " . $memberVolume, 7);
                    } else {
                        $memberVolume = $sonoszone[$zone2][4];
                        self::log('Standard Sonos Volume for Group Member ' . $zone2 . ' has been set to: ' . $memberVolume, 7);
                    }
                }
            }

            // Max Volume is a ceiling for normal audio/group volume handling. It must
            // limit the requested target volume, never be derived from the player's
            // previously observed current volume.
            if (!self::isTtsLikeRequest()) {
                $memberVolume = self::limitToMaxVolume($memberVolume, $sonoszone, $zone2);
            }

            @$sonos->SetMute(false);
            $sonos->SetVolume($memberVolume);
            $appliedVolumes[$zone2] = $memberVolume;
        }

        return $appliedVolumes;
    }

    private static function limitToMaxVolume($requestedVolume, $sonoszone, $zone)
    {
        if (!isset($sonoszone[$zone]) || !isset($sonoszone[$zone][5]) || !is_numeric($sonoszone[$zone][5])) {
            return $requestedVolume;
        }

        $maxVolume = (float)$sonoszone[$zone][5];
        if (!is_numeric($requestedVolume)) {
            return $requestedVolume;
        }

        return ((float)$requestedVolume > $maxVolume) ? $sonoszone[$zone][5] : $requestedVolume;
    }

    private static function isTtsLikeRequest()
    {
        $ttsFlags = array(
            'text',
            'messageid',
            'sonos',
            'weather',
            'abfall',
            'witz',
            'pollen',
            'warning',
            'distance',
            'clock',
            'calendar'
        );

        foreach ($ttsFlags as $flag) {
            if (isset($_GET[$flag])) {
                return true;
            }
        }

        return isset($_GET['action']) && $_GET['action'] === 'playbatch';
    }

    private static function hasProfileVolume($profileSelected, $zone)
    {
        return is_array($profileSelected)
            && isset($profileSelected[0])
            && isset($profileSelected[0]['Player'])
            && isset($profileSelected[0]['Player'][$zone])
            && isset($profileSelected[0]['Player'][$zone][0])
            && isset($profileSelected[0]['Player'][$zone][0]['Volume']);
    }

    private static function log($message, $level)
    {
        if (!class_exists('S4L_Logger')) {
            $loggerFile = __DIR__ . '/Logger.php';
            if (is_readable($loggerFile)) {
                require_once $loggerFile;
            }
        }

        if (class_exists('S4L_Logger')) {
            S4L_Logger::write($message, (int)$level, __FILE__);
            return;
        }

        $message = 'src/Support/VolumeContext.php: ' . $message;
        $function = self::nativeLogFunction((int)$level);
        if ($function !== '' && function_exists($function)) {
            $function($message);
            return;
        }

        error_log($message);
    }

    private static function nativeLogFunction($level)
    {
        switch ((int)$level) {
            case 3:
                return 'LOGERR';
            case 4:
                return 'LOGWARN';
            case 5:
                return 'LOGOK';
            case 6:
                return 'LOGINF';
            case 7:
                return 'LOGDEB';
            default:
                return 'LOGDEB';
        }
    }
}

if (!function_exists('volume_group')) {
    function volume_group()
    {
        return S4L_VolumeContext::applyGroupVolume();
    }
}
