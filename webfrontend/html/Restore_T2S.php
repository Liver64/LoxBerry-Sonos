<?php

/**
 * Submodule: Restore_T2S
 * Optimized restore of previous Sonos state after Text-to-Speech
 * Version: 2025-11-19-2
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
 * Restore previous settings for a single zone T2S.
 */
function restoreSingleZone() {

    global $sonoszone, $sonos, $actual, $master, $tts_stat;

    if (empty($master) || !isset($sonoszone[$master])) {
        LOGGING("restore_t2s.php: Cannot restore single zone – master is not set or unknown.", 3);
        return;
    }

    if (!isset($actual[$master])) {
        LOGGING("restore_t2s.php: No snapshot data for master '$master' – nothing to restore.", 4);
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

            // Nur dann wieder starten, wenn vor T2S wirklich gespielt wurde
            if (isset($actual[$master]['TransportInfo']) && $actual[$master]['TransportInfo'] == 1) {
                try {
                    $sonos->Play();
                    RestoreShuffle($master);
                    LOGGING("restore_t2s.php: Single zone '$master' playback has been resumed.", 6);
                } catch (Exception $e) {
                    LOGGING("restore_t2s.php: Single zone '$master' could not resume playback: ".$e->getMessage(), 3);
                }
            } else {
                LOGGING("restore_t2s.php: Single zone '$master' has been restored (was not playing before T2S).", 6);
            }
            break;

        // ------------------------------------------------------------
        // Zone war vorher Mitglied einer Gruppe
        // ------------------------------------------------------------
        case 'member':
            $pos = isset($actual[$master]['PositionInfo']) ? $actual[$master]['PositionInfo'] : array();
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
            LOGGING("restore_t2s.php: Member zone '$master' has been added back to its group (single-mode restore).", 6);
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

            if (isset($actual[$master]['TransportInfo']) && $actual[$master]['TransportInfo'] == 1) {
                try {
                    $sonos->Play();
                    RestoreShuffle($master);
                    LOGGING("restore_t2s.php: Master zone '$master' playback has been resumed (single-mode restore).", 6);
                } catch (Exception $e) {
                    LOGGING("restore_t2s.php: Master zone '$master' could not resume playback: ".$e->getMessage(), 3);
                }
            } else {
                LOGGING("restore_t2s.php: Master zone '$master' has been restored (was not playing before T2S).", 6);
            }
            break;

        default:
            LOGGING("restore_t2s.php: Unknown previous status for '$master' – no restore performed.", 4);
            break;
    }

    // T2S Ende signalisieren
    if (function_exists('send_tts_source')) {
        $tts_stat = 0;
        send_tts_source($tts_stat);
    }

    $elapsed = microtime(true) - $t0;
    LOGGING("Restore.php: (Single) Zone restore took ".round($elapsed, 3)." seconds.", 6);
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
        LOGGING("restore_t2s.php: Cannot restore group – master is not set or unknown.", 3);
        return;
    }

    if (empty($actual) || !isset($actual[$master])) {
        LOGGING("restore_t2s.php: No snapshot data – group restore skipped.", 4);
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
        LOGGING("restore_t2s.php: restoreGroupZone: No target zones found – nothing to restore.", 4);
        // trotzdem T2S Ende signalisieren
        if (function_exists('send_tts_source')) {
            $tts_stat = 0;
            send_tts_source($tts_stat);
        }
        return;
    }

    $zonesList = implode(',', array_keys($zonesToRestore));
    LOGGING("restore_t2s.php: Group restore for zones: ".$zonesList, 6);

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
            LOGGING("restore_t2s.php: Ungroup of '$player' failed: ".$e->getMessage(), 4);
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
            LOGGING("restore_t2s.php: Zone '$player' could not be restored (no Sonos access): ".$e->getMessage(), 3);
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

			// War der Player vorher aktiv?
			if (isset($actual[$player]['TransportInfo']) && $actual[$player]['TransportInfo'] == 1) {
				try {
					$sonos->Play();
					RestoreShuffle($player);
					LOGGING("restore_t2s.php: Single zone '$player' has been restored and playback resumed.", 6);
				} catch (Exception $e) {
					LOGGING("restore_t2s.php: Single zone '$player' could not resume playback: ".$e->getMessage(), 3);
				}
			} else {
				LOGGING("restore_t2s.php: Single zone '$player' has been restored (was not playing before T2S).", 6);
			}
			break;


            // ----------------------------------------------------
            // Player war vorher Member einer Gruppe
            // ----------------------------------------------------
            case 'member':
                $pos = isset($actual[$player]['PositionInfo']) ? $actual[$player]['PositionInfo'] : array();
                if (!empty($pos) && !empty($pos['TrackURI'])) {
                    try {
                        $sonos->SetAVTransportURI($pos['TrackURI']);
                    } catch (Exception $e) {
                        LOGGING("restore_t2s.php: Member '$player' could not re-attach to group: ".$e->getMessage(), 3);
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

                LOGGING("restore_t2s.php: Member zone '$player' has been added back to its group.", 6);
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
                            LOGGING("restore_t2s.php: Master '$player' – member '$groupmem' could not be restored: ".$e->getMessage(), 3);
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
                if (isset($actual[$player]['TransportInfo']) && $actual[$player]['TransportInfo'] == 1) {
                    try {
                        $sonos->Play();
                        RestoreShuffle($player);
                        LOGGING("restore_t2s.php: Master zone '$player' has been added back to group and playback resumed.", 6);
                    } catch (Exception $e) {
                        LOGGING("restore_t2s.php: Master zone '$player' could not resume playback: ".$e->getMessage(), 3);
                    }
                } else {
                    LOGGING("restore_t2s.php: Master zone '$player' has been added back to group (was not playing before T2S).", 6);
                }
                break;

            // ----------------------------------------------------
            default:
                LOGGING("restore_t2s.php: Unknown ZoneStatus '$restoreState' for '$player' – basic restore only.", 4);
                restore_details($player);
                break;
        }
    }

    // T2S Ende signalisieren (virtueller Text-Eingang zurück auf 0)
    if (function_exists('send_tts_source')) {
        $tts_stat = 0;
        send_tts_source($tts_stat);
    }

    $elapsed = microtime(true) - $t0;
    LOGGING("Restore.php: (Group) Restore took ".round($elapsed, 3)." seconds.", 6);
}


/**
 * Helper: restore_details()
 * Restores the actual content (Track/Radio/TV/LineIn/Empty queue) for a zone.
 */
function restore_details($zone) {

    global $sonoszone, $sonos, $actual;

    if (!isset($sonoszone[$zone]) || !isset($actual[$zone])) {
        LOGGING("restore_t2s.php: restore_details() – no data for zone '$zone'.", 4);
        return;
    }

    // Sicherstellen, dass wir einen gültigen SonosAccess haben
    if (!($sonos instanceof SonosAccess)) {
        $sonos = new SonosAccess($sonoszone[$zone][0]);
    }

    $type = isset($actual[$zone]['Type']) ? $actual[$zone]['Type'] : '';

    // --------------------------------------------------------
    // Track (Playlist / Queue / externe Musikdienste in Queue)
    // -> Restore wie PROD: immer Queue-basiert
    // --------------------------------------------------------
    if ($type === "Track") {

    $pos = $actual[$zone]['PositionInfo'];

    // Nur wenn Track existiert
    if (!empty($pos['Track']) &&
        $pos['Track'] !== "0" &&
        $pos['Track'] !== "NOT_IMPLEMENTED")
    {
        try {
            // Queue setzen wie in PROD
            $sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$zone][1]) . "#0");

            // Auf Titel springen
            $sonos->SetTrack($pos['Track']);

            // Zeitpunkt im Titel
            if (!empty($pos['RelTime']) && $pos['RelTime'] !== "NOT_IMPLEMENTED") {
                $sonos->Seek("REL_TIME", $pos['RelTime']);
            }

            LOGGING("restore_t2s.php: Source 'Track' has been set for '$zone'.", 6);

        } catch (Exception $e) {
            LOGGING("restore_t2s.php: Source 'Track' for '$zone' could not be restored: ".$e->getMessage(), 4);
        }
    }

    // WICHTIG: sauberer return
    return;






    // --------------------------------------------------------
    // TV
    // --------------------------------------------------------
    } elseif ($type === "TV") {
        $pos = $actual[$zone]['PositionInfo'];
        try {
            $sonos->SetAVTransportURI($pos["TrackURI"]);
            LOGGING("restore_t2s.php: Source 'TV' has been set for '$zone'.", 6);
        } catch (Exception $e) {
            LOGGING("restore_t2s.php: Source 'TV' for '$zone' could not be restored: ".$e->getMessage(), 4);
        }

    // --------------------------------------------------------
    // Line-In
    // --------------------------------------------------------
    } elseif ($type === "LineIn") {
        $pos = $actual[$zone]['PositionInfo'];
        try {
            $sonos->SetAVTransportURI($pos["TrackURI"]);
            LOGGING("restore_t2s.php: Source 'LineIn' has been set for '$zone'.", 6);
        } catch (Exception $e) {
            LOGGING("restore_t2s.php: Source 'LineIn' for '$zone' could not be restored: ".$e->getMessage(), 4);
        }

    // --------------------------------------------------------
    // Radio
    // --------------------------------------------------------
    } elseif ($type === "Radio") {
        $media = $actual[$zone]['MediaInfo'];
        try {
            $sonos->SetAVTransportURI(
                $media["CurrentURI"],
                htmlspecialchars_decode($media["CurrentURIMetaData"])
            );
            LOGGING("restore_t2s.php: Source 'Radio' has been set for '$zone'.", 6);
        } catch (Exception $e) {
            LOGGING("restore_t2s.php: Source 'Radio' for '$zone' could not be restored: ".$e->getMessage(), 4);
        }

    // --------------------------------------------------------
    // Keine Queue / Idle
    // --------------------------------------------------------
    } elseif ($type === "Nothing") {
        LOGGING("restore_t2s.php: Player '$zone' had no queue (Nothing).", 6);

    // --------------------------------------------------------
    // Fallback
    // --------------------------------------------------------
    } else {
        LOGGING("restore_t2s.php: Unexpected type for player '$zone' – attempting basic queue restore.", 4);
        try {
            $sonos->SetQueue("x-rincon-queue:" . trim($sonoszone[$zone][1]) . "#0");
        } catch (Exception $e) {
            LOGGING("restore_t2s.php: Basic queue restore for '$zone' failed: " . $e->getMessage(), 4);
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
    } catch (Exception $e) {
        LOGGING("restore_t2s.php: RestoreShuffle('$player') failed to get playlist: ".$e->getMessage(), 4);
        return;
    }

    if (count($pl) > 1 && $mode != 0) {
        $modereal = playmode_detection($player, $mode);
        LOGGING("restore_t2s.php: Previous playmode '$modereal' for '$player' has been restored.", 6);
    }
}

} // function_exists guard

?>
