<?php

/**
 * Submodule: Restore_T2S
 * Optimized restore of previous Sonos state after Text-to-Speech
 * Version: T2S_SAVE_RESTORE_LOG_HARDENING_V01_2026_06_19
 *
 * This file provides:
 *  - restoreSingleZone()  – for single-zone T2S
 *  - restoreGroupZone()   – for group T2S
 *  - restore_details()    – helper that restores content (Track / Radio / TV / LineIn / Nothing)
 *  - RestoreShuffle()     – helper that restores previous playmode if needed
 *
 * It expects that saveZonesStatus() has populated the global $actual array.
 */

if (!function_exists('restoreSingleZone')) {

/**
 * Returns true if a saved transport state represents active playback.
 * The legacy Sonos wrapper may return either an integer-like value or an array.
 */
function s4lox_restore_t2s_was_playing($transportInfo)
{
    if (is_array($transportInfo)) {
        $state = isset($transportInfo['CurrentTransportState'])
            ? strtoupper((string)$transportInfo['CurrentTransportState'])
            : '';

        return ($state === 'PLAYING');
    }

    return ((string)$transportInfo === '1' || strtoupper((string)$transportInfo) === 'PLAYING');
}

/**
 * Safely returns an array snapshot section.
 */
function s4lox_restore_t2s_array($value)
{
    return is_array($value) ? $value : array();
}

/**
 * Restore previous settings for a single zone T2S.
 */
function restoreSingleZone() {

    global $sonoszone, $sonos, $actual, $master, $tts_stat;

    if (empty($master) || !isset($sonoszone[$master])) {
        LOGERR("Restore_T2S.php: Cannot restore single zone – master is not set or unknown.");
        return;
    }

    if (!isset($actual[$master])) {
        LOGWARN("Restore_T2S.php: No snapshot data for master '$master' – nothing to restore.");
        return;
    }

    $t0 = microtime(true);

    $sonos  = new SonosAccess($sonoszone[$master][0]);
    $status = isset($actual[$master]['ZoneStatus']) ? $actual[$master]['ZoneStatus'] : 'unknown';

    switch ($status) {

        // ------------------------------------------------------------
        // Zone war vorher Single-Player
        // ------------------------------------------------------------
        case 'single':
            restore_details($master);

            if (isset($actual[$master]['Volume'])) {
                $sonos->SetVolume($actual[$master]['Volume']);
            }
            if (isset($actual[$master]['Treble'])) {
                $sonos->SetTreble($actual[$master]['Treble']);
            }
            if (isset($actual[$master]['Bass'])) {
                $sonos->SetBass($actual[$master]['Bass']);
            }
            if (isset($actual[$master]['Mute'])) {
                $sonos->SetMute($actual[$master]['Mute']);
            }

            // Resume only if the zone was really playing before TTS
            if (s4lox_restore_t2s_was_playing($actual[$master]['TransportInfo'] ?? null)) {
                try {
                    $sonos->Play();
                    RestoreShuffle($master);
                    LOGOK("Restore_T2S.php: Single zone '$master' playback has been resumed.");
                } catch (Exception $e) {
                    LOGERR("Restore_T2S.php: Single zone '$master' could not resume playback: ".$e->getMessage());
                }
            } else {
                LOGOK("Restore_T2S.php: Single zone '$master' has been restored (was not playing before T2S).");
            }
            break;

        // ------------------------------------------------------------
        // Zone war vorher Mitglied einer Gruppe
        // ------------------------------------------------------------
        case 'member':
            $pos = s4lox_restore_t2s_array($actual[$master]['PositionInfo'] ?? array());
            if (!empty($pos) && !empty($pos['TrackURI'])) {
                $sonos->SetAVTransportURI($pos['TrackURI']);
            }

            if (isset($actual[$master]['Volume'])) {
                $sonos->SetVolume($actual[$master]['Volume']);
            }
            if (isset($actual[$master]['Treble'])) {
                $sonos->SetTreble($actual[$master]['Treble']);
            }
            if (isset($actual[$master]['Bass'])) {
                $sonos->SetBass($actual[$master]['Bass']);
            }
            if (isset($actual[$master]['Mute'])) {
                $sonos->SetMute($actual[$master]['Mute']);
            }
            LOGOK("Restore_T2S.php: Member zone '$master' has been added back to its group (single-mode restore).");
            break;

        // ------------------------------------------------------------
        // Zone war vorher Gruppen-Master
        // (Single-T2S auf bisherigem Gruppenkoordinator)
        // ------------------------------------------------------------
        case 'master':
            restore_details($master);

            if (isset($actual[$master]['Volume'])) {
                $sonos->SetVolume($actual[$master]['Volume']);
            }
            if (isset($actual[$master]['Treble'])) {
                $sonos->SetTreble($actual[$master]['Treble']);
            }
            if (isset($actual[$master]['Bass'])) {
                $sonos->SetBass($actual[$master]['Bass']);
            }
            if (isset($actual[$master]['Mute'])) {
                $sonos->SetMute($actual[$master]['Mute']);
            }

            if (s4lox_restore_t2s_was_playing($actual[$master]['TransportInfo'] ?? null)) {
                try {
                    $sonos->Play();
                    RestoreShuffle($master);
                    LOGOK("Restore_T2S.php: Master zone '$master' playback has been resumed (single-mode restore).");
                } catch (Exception $e) {
                    LOGERR("Restore_T2S.php: Master zone '$master' could not resume playback: ".$e->getMessage());
                }
            } else {
                LOGOK("Restore_T2S.php: Master zone '$master' has been restored (was not playing before T2S).");
            }
            break;

        default:
            LOGWARN("Restore_T2S.php: Unknown previous status for '$master' – no restore performed.");
            break;
    }

    // Signal TTS end
    if (function_exists('send_tts_source')) {
        $tts_stat = 0;
        send_tts_source($tts_stat);
    }

    $elapsed = microtime(true) - $t0;
    LOGINF("Restore_T2S.php: (Single) Zone restore took ".round($elapsed, 3)." seconds.");
}


/**
 * Restore previous settings for a group T2S.
 * This function restores:
 *  - former master and its content
 *  - all members that belonged to the original group snapshot
 *  - all member zones that an der T2S beteiligt waren (MEMBER / $member)
 */
function restoreGroupZone() {

    global $sonoszone, $sonos, $actual, $master, $member, $tts_stat;

    if (empty($master) || !isset($sonoszone[$master])) {
        LOGERR("Restore_T2S.php: Cannot restore group – master is not set or unknown.");
        return;
    }

    if (empty($actual) || !isset($actual[$master])) {
        LOGWARN("Restore_T2S.php: No snapshot data – group restore skipped.");
        return;
    }

    $t0 = microtime(true);

    // ------------------------------------------------------------
    // 1) Zonen sammeln, die überhaupt wiederhergestellt werden sollen
    // ------------------------------------------------------------

    $zonesToRestore = array();

    // a) Ursprüngliche Gruppierung aus Snapshot (Master)
    if (isset($actual[$master]['Grouping']) && is_array($actual[$master]['Grouping'])) {
        foreach ($actual[$master]['Grouping'] as $z) {
            if (!empty($z) && isset($sonoszone[$z])) {
                $zonesToRestore[$z] = true;
            }
        }
    }

    // b) Globales $member-Array (T2S-Beteiligte)
    if (!empty($member) && is_array($member)) {
        foreach ($member as $z) {
            if (!empty($z) && isset($sonoszone[$z])) {
                $zonesToRestore[$z] = true;
            }
        }
    }

    // c) Konstante MEMBER (Single Source of Truth aus CreateMember)
    if (defined('MEMBER') && is_array(MEMBER)) {
        foreach (MEMBER as $z) {
            if (!empty($z) && isset($sonoszone[$z])) {
                $zonesToRestore[$z] = true;
            }
        }
    }

    // d) Master immer hinzufügen
    if (isset($sonoszone[$master])) {
        $zonesToRestore[$master] = true;
    }

    if (empty($zonesToRestore)) {
        LOGWARN("Restore_T2S.php: restoreGroupZone: No target zones found – nothing to restore.");
        // Still signal TTS end
        if (function_exists('send_tts_source')) {
            $tts_stat = 0;
            send_tts_source($tts_stat);
        }
        return;
    }

    $zonesList = implode(',', array_keys($zonesToRestore));
    LOGINF("Restore_T2S.php: Group restore for zones: ".$zonesList);

    // ------------------------------------------------------------
    // 2) Alle betroffenen Player erstmal in Single-Zonen auflösen
    //    (wie in deiner PROD-Version: BecomeCoordinatorOfStandaloneGroup)
    // ------------------------------------------------------------

    foreach (array_keys($zonesToRestore) as $player) {
        if (!isset($sonoszone[$player])) {
            continue;
        }
        try {
            $s = new SonosAccess($sonoszone[$player][0]);
            $s->BecomeCoordinatorOfStandaloneGroup();
        } catch (Exception $e) {
            LOGWARN("Restore_T2S.php: Ungroup of '$player' failed: ".$e->getMessage());
        }
    }

    // ------------------------------------------------------------
    // 3) Für jede Zone den Zustand gemäß Snapshot wiederherstellen
    //    - single  → Inhalt + Volume/Mute + ggf. Play/Stop
    //    - member  → x-rincon:... bzw. Snapshot-URI + Volume/Mute
    //    - master  → Inhalt + Group-Member + Volume/Mute + ggf. Play
    // ------------------------------------------------------------

    foreach (array_keys($zonesToRestore) as $player) {

        if (!isset($sonoszone[$player]) || !isset($actual[$player])) {
            continue;
        }

        $restoreState = isset($actual[$player]['ZoneStatus']) ? $actual[$player]['ZoneStatus'] : 'single';

        try {
            $sonos = new SonosAccess($sonoszone[$player][0]);
        } catch (Exception $e) {
            LOGERR("Restore_T2S.php: Zone '$player' could not be restored (no Sonos access): ".$e->getMessage());
            continue;
        }

        switch ($restoreState) {

            // ----------------------------------------------------
            // Player war vorher Single
            // ----------------------------------------------------
            case 'single':
			// Echte Single-Zone immer vollständig restaurieren
			restore_details($player);

			if (isset($actual[$player]['Volume'])) {
				$sonos->SetVolume($actual[$player]['Volume']);
			}
			if (isset($actual[$player]['Treble'])) {
				$sonos->SetTreble($actual[$player]['Treble']);
			}
			if (isset($actual[$player]['Bass'])) {
				$sonos->SetBass($actual[$player]['Bass']);
			}
			if (isset($actual[$player]['Mute'])) {
				$sonos->SetMute($actual[$player]['Mute']);
			}

			// Was the player active before TTS?
			if (s4lox_restore_t2s_was_playing($actual[$player]['TransportInfo'] ?? null)) {
				try {
					$sonos->Play();
					RestoreShuffle($player);
					LOGOK("Restore_T2S.php: Single zone '$player' has been restored and playback resumed.");
				} catch (Exception $e) {
					LOGERR("Restore_T2S.php: Single zone '$player' could not resume playback: ".$e->getMessage());
				}
			} else {
				LOGOK("Restore_T2S.php: Single zone '$player' has been restored (was not playing before T2S).");
			}
			break;


            // ----------------------------------------------------
            // Player war vorher Member einer Gruppe
            // ----------------------------------------------------
            case 'member':
                $pos = s4lox_restore_t2s_array($actual[$player]['PositionInfo'] ?? array());
                if (!empty($pos) && !empty($pos['TrackURI'])) {
                    try {
                        $sonos->SetAVTransportURI($pos['TrackURI']);
                    } catch (Exception $e) {
                        LOGERR("Restore_T2S.php: Member '$player' could not re-attach to group: ".$e->getMessage());
                    }
                }

                if (isset($actual[$player]['Volume'])) {
                    $sonos->SetVolume($actual[$player]['Volume']);
                }
                if (isset($actual[$player]['Treble'])) {
                    $sonos->SetTreble($actual[$player]['Treble']);
                }
                if (isset($actual[$player]['Bass'])) {
                    $sonos->SetBass($actual[$player]['Bass']);
                }
                if (isset($actual[$player]['Mute'])) {
                    $sonos->SetMute($actual[$player]['Mute']);
                }

                LOGOK("Restore_T2S.php: Member zone '$player' has been added back to its group.");
                break;

            // ----------------------------------------------------
            // Player war vorher Master einer Gruppe
            // ----------------------------------------------------
            case 'master':
                // Zuerst eigene Quelle wiederherstellen
                restore_details($player);

                // Dann Member aus ursprünglicher Gruppierung zurückholen
                if (isset($actual[$player]['Grouping']) && is_array($actual[$player]['Grouping'])) {
                    $tmp_group = $actual[$player]['Grouping'];
                    // Erster Eintrag ist typischerweise der Master selbst
                    $tmp_group1st = array_shift($tmp_group);

                    foreach ($tmp_group as $groupmem) {
                        if (!isset($sonoszone[$groupmem]) || !isset($actual[$groupmem])) {
                            continue;
                        }
                        try {
                            $sMem = new SonosAccess($sonoszone[$groupmem][0]);
                            $pos  = $actual[$groupmem]['PositionInfo'];
                            if (!empty($pos['TrackURI'])) {
                                $sMem->SetAVTransportURI($pos['TrackURI']);
                            }
                            if (isset($actual[$groupmem]['Volume'])) {
                                $sMem->SetVolume($actual[$groupmem]['Volume']);
                            }
                            if (isset($actual[$groupmem]['Mute'])) {
                                $sMem->SetMute($actual[$groupmem]['Mute']);
                            }
                        } catch (Exception $e) {
                            LOGERR("Restore_T2S.php: Master '$player' – member '$groupmem' could not be restored: ".$e->getMessage());
                        }
                    }
                }

                // Master-Lautstärke & Mute wiederherstellen
                if (isset($actual[$player]['Volume'])) {
                    $sonos->SetVolume($actual[$player]['Volume']);
                }
                if (isset($actual[$player]['Treble'])) {
                    $sonos->SetTreble($actual[$player]['Treble']);
                }
                if (isset($actual[$player]['Bass'])) {
                    $sonos->SetBass($actual[$player]['Bass']);
                }
                if (isset($actual[$player]['Mute'])) {
                    $sonos->SetMute($actual[$player]['Mute']);
                }

                // Und ggf. wieder starten
                if (s4lox_restore_t2s_was_playing($actual[$player]['TransportInfo'] ?? null)) {
                    try {
                        $sonos->Play();
                        RestoreShuffle($player);
                        LOGOK("Restore_T2S.php: Master zone '$player' has been added back to group and playback resumed.");
                    } catch (Exception $e) {
                        LOGERR("Restore_T2S.php: Master zone '$player' could not resume playback: ".$e->getMessage());
                    }
                } else {
                    LOGOK("Restore_T2S.php: Master zone '$player' has been added back to group (was not playing before T2S).");
                }
                break;

            // ----------------------------------------------------
            default:
                LOGWARN("Restore_T2S.php: Unknown ZoneStatus '$restoreState' for '$player' – basic restore only.");
                restore_details($player);
                break;
        }
    }

    // Signal TTS end (virtueller Text-Eingang zurück auf 0)
    if (function_exists('send_tts_source')) {
        $tts_stat = 0;
        send_tts_source($tts_stat);
    }

    $elapsed = microtime(true) - $t0;
    LOGINF("Restore_T2S.php: (Group) Restore took ".round($elapsed, 3)." seconds.");
}


/**
 * Helper: restore_details()
 * Restores the actual content (Track/Radio/TV/LineIn/Empty queue) for a zone.
 */
function restore_details($zone) {

    global $sonoszone, $sonos, $actual;

    if (!isset($sonoszone[$zone]) || !isset($actual[$zone])) {
        LOGWARN("Restore_T2S.php: restore_details() – no data for zone '$zone'.");
        return;
    }

    // Ensure that a valid SonosAccess instance is available
    if (!isset($sonos) || !($sonos instanceof SonosAccess)) {
        $sonos = new SonosAccess($sonoszone[$zone][0]);
    }

    $type = isset($actual[$zone]['Type']) ? $actual[$zone]['Type'] : '';

    // --------------------------------------------------------
    // Track (Playlist / Queue / externe Musikdienste in Queue)
    // -> Restore wie PROD: immer Queue-basiert
    // --------------------------------------------------------
    if ($type === "Track") {

        $pos = s4lox_restore_t2s_array($actual[$zone]['PositionInfo'] ?? array());

        // Restore queue position only if a valid track number exists.
        if (!empty($pos['Track']) &&
            $pos['Track'] !== "0" &&
            $pos['Track'] !== "NOT_IMPLEMENTED")
        {
            try {
                $sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$zone][1]) . "#0");
                $sonos->SetTrack($pos['Track']);

                if (!empty($pos['RelTime']) && $pos['RelTime'] !== "NOT_IMPLEMENTED") {
                    $sonos->Seek("REL_TIME", $pos['RelTime']);
                }

                LOGOK("Restore_T2S.php: Source 'Track' has been set for '$zone'.");

            } catch (Exception $e) {
                LOGWARN("Restore_T2S.php: Source 'Track' for '$zone' could not be restored: " . $e->getMessage());
            }
        } else {
            LOGWARN("Restore_T2S.php: Source 'Track' for '$zone' could not be restored because no valid track number was saved.");
        }

        return;

    // --------------------------------------------------------
    // TV
    // --------------------------------------------------------
    } elseif ($type === "TV") {
        $pos = s4lox_restore_t2s_array($actual[$zone]['PositionInfo'] ?? array());
        $trackUri = (string)($pos["TrackURI"] ?? "");

        if ($trackUri === "") {
            LOGWARN("Restore_T2S.php: Source 'TV' for '$zone' could not be restored because no TrackURI was saved.");
        } else {
            try {
                $sonos->SetAVTransportURI($trackUri);
                LOGOK("Restore_T2S.php: Source 'TV' has been set for '$zone'.");
            } catch (Exception $e) {
                LOGWARN("Restore_T2S.php: Source 'TV' for '$zone' could not be restored: " . $e->getMessage());
            }
        }

    // --------------------------------------------------------
    // Line-In
    // --------------------------------------------------------
    } elseif ($type === "LineIn") {
        $pos = s4lox_restore_t2s_array($actual[$zone]['PositionInfo'] ?? array());
        $trackUri = (string)($pos["TrackURI"] ?? "");

        if ($trackUri === "") {
            LOGWARN("Restore_T2S.php: Source 'LineIn' for '$zone' could not be restored because no TrackURI was saved.");
        } else {
            try {
                $sonos->SetAVTransportURI($trackUri);
                LOGOK("Restore_T2S.php: Source 'LineIn' has been set for '$zone'.");
            } catch (Exception $e) {
                LOGWARN("Restore_T2S.php: Source 'LineIn' for '$zone' could not be restored: " . $e->getMessage());
            }
        }

    // --------------------------------------------------------
    // Radio
    // --------------------------------------------------------
    } elseif ($type === "Radio") {
        $media = s4lox_restore_t2s_array($actual[$zone]['MediaInfo'] ?? array());
        $currentUri = (string)($media["CurrentURI"] ?? "");

        if ($currentUri === "") {
            LOGWARN("Restore_T2S.php: Source 'Radio' for '$zone' could not be restored because no CurrentURI was saved.");
        } else {
            try {
                $sonos->SetAVTransportURI(
                    $currentUri,
                    htmlspecialchars_decode((string)($media["CurrentURIMetaData"] ?? ""))
                );
                LOGOK("Restore_T2S.php: Source 'Radio' has been set for '$zone'.");
            } catch (Exception $e) {
                LOGWARN("Restore_T2S.php: Source 'Radio' for '$zone' could not be restored: " . $e->getMessage());
            }
        }

    // --------------------------------------------------------
    // No queue / idle
    // --------------------------------------------------------
    } elseif ($type === "Nothing") {
        LOGINF("Restore_T2S.php: Player '$zone' had no queue (Nothing).");

    // --------------------------------------------------------
    // Fallback
    // --------------------------------------------------------
    } else {
        LOGWARN("Restore_T2S.php: Unexpected type for player '$zone' – attempting basic queue restore.");
        try {
            $sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$zone][1]) . "#0");
        } catch (Exception $e) {
            LOGWARN("Restore_T2S.php: Basic queue restore for '$zone' failed: " . $e->getMessage());
        }
    }
}



/**
 * Helper: RestoreShuffle()
 * Restores previous playmode if there was a queue and a non-default mode.
 */
function RestoreShuffle($player) {

    global $sonoszone, $actual;

    if (!isset($sonoszone[$player]) || !isset($actual[$player])) {
        return;
    }

    $sonos = new SonosAccess($sonoszone[$player][0]);
    $mode  = isset($actual[$player]['TransportSettings']) ? $actual[$player]['TransportSettings'] : 0;

    try {
        $pl = $sonos->GetCurrentPlaylist();
        if (!is_array($pl)) {
            $pl = array();
        }
    } catch (Exception $e) {
        LOGWARN("Restore_T2S.php: RestoreShuffle('$player') failed to get playlist: ".$e->getMessage());
        return;
    }

    if (count($pl) > 1 && $mode != 0) {
        $modereal = playmode_detection($player, $mode);
        LOGOK("Restore_T2S.php: Previous playmode '$modereal' for '$player' has been restored.");
    }
}

} // function_exists guard

