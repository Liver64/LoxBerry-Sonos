<?php

/**
 * Submodul: Save_T2S
 *
 * Speichert den aktuellen Zustand der relevanten Zonen für T2S.
 * Fast-Path:
 *   - member=all      → alle Zonen sichern
 *   - member=<liste>  → nur Master + explizite Member + bestehende Gruppierung
 *   - kein member     → Master + bestehende Gruppierung
 */

/**
 * Liefert für das aktuell ausgewählte Sound-Profil die beteiligten Zonen
 * (Profil-Master + Member), aber OHNE Gruppen-/Lautstärke-Änderungen
 * vorzunehmen. Wird nur für die Snapshot-Erstellung verwendet.
 *
 * @return array  Liste von Zonennamen
 */
function getZonesFromProfileForSnapshot()
{
    global $sonoszone, $profile_details;

    // Kein Profil in der URL → nichts zu tun
    if (empty($_GET['profile'])) {
        return [];
    }

    // Profil-Details laden, falls noch nicht vorhanden
    if (empty($profile_details)) {
        if (function_exists('get_profile_details')) {
            // get_profile_details() setzt $profile_details global
            get_profile_details();
        } else {
            // Ohne Helfer können wir nichts Sinnvolles tun
            return [];
        }
    }

    if (!is_array($profile_details) || !isset($profile_details[0]['Group'])) {
        return [];
    }

    $zones     = [];
    $groupType = $profile_details[0]['Group'];

    switch ($groupType) {

        // Vollständige Gruppe aus Profil (Master + Member)
        case 'Group':
            foreach ($sonoszone as $zoneName => $_) {
                // Master-Zone (Flag "Master" = true)
                if (!empty($profile_details[0]['Player'][$zoneName][0]['Master']) &&
                    is_enabled($profile_details[0]['Player'][$zoneName][0]['Master'])) {
                    $zones[] = $zoneName;
                }

                // Member-Zonen (Flag "Member" == "true")
                if (!empty($profile_details[0]['Player'][$zoneName][0]['Member']) &&
                    $profile_details[0]['Player'][$zoneName][0]['Member'] === "true") {
                    $zones[] = $zoneName;
                }
            }
            break;

        // Single-Profil → nur der markierte Master
        case 'Single':
            foreach ($sonoszone as $zoneName => $_) {
                if (!empty($profile_details[0]['Player'][$zoneName][0]['Master']) &&
                    is_enabled($profile_details[0]['Player'][$zoneName][0]['Master'])) {
                    $zones[] = $zoneName;
                }
            }
            break;

        // NoGroup → nur der globale MASTER
        case 'NoGroup':
            if (defined('MASTER') && isset($sonoszone[MASTER])) {
                $zones[] = MASTER;
            }
            break;
    }

    // Nur gültige Zonen, Duplikate entfernen
    $zones = array_values(
        array_unique(
            array_filter($zones, function ($z) use ($sonoszone) {
                return isset($sonoszone[$z]);
            })
        )
    );

    return $zones;
}

/**
 * Ermittelt die Zonen, die für den Snapshot gesichert werden sollen.
 *
 * Logik:
 *   - Zonen aus Sound-Profil (falls profile=... gesetzt)
 *   - Master ($master) immer sichern (sofern gültig)
 *   - Wenn $_GET['member'] == 'all' → alle Zonen sichern
 *   - Sonst:
 *       - explizite Member aus $_GET['member'] (CSV) hinzufügen
 *       - bestehende Gruppierung des Masters (getGroup($master)) hinzufügen
 *
 * @return array  Array von Zonennamen (Keys aus $sonoszone)
 */
function getZonesToSaveForT2S()
{
    global $sonoszone, $master;

    $zonesToSave = [];

    // ------------------------------------------------------------------
    // 0) Zonen aus Sound-Profil (Profil-Master + Member) vorab ergänzen
    // ------------------------------------------------------------------
    if (!empty($_GET['profile'])) {
        $profileZones = getZonesFromProfileForSnapshot();
        foreach ($profileZones as $z) {
            $zonesToSave[$z] = true;
        }
    }

    // Master immer sichern, wenn vorhanden
    if (!empty($master) && isset($sonoszone[$master])) {
        $zonesToSave[$master] = true;
    }

    // member-Parameter aus dem Request auslesen (falls vorhanden)
    $rawMember   = isset($_GET['member']) ? trim($_GET['member']) : '';
    $memberParam = strtolower($rawMember);

    // ------------------------------------------------------------------
    // 1) member=all → komplette System-Sicherung
    // ------------------------------------------------------------------
    if ($memberParam === 'all') {
        foreach ($sonoszone as $zoneName => $_) {
            $zonesToSave[$zoneName] = true;
        }
        return array_keys($zonesToSave);
    }

    // ------------------------------------------------------------------
    // 2) Explizite Member-Liste (z.B. "schlafzimmer,kueche")
    //    → Namen direkt aus $_GET übernehmen, case-insensitive auflösen
    // ------------------------------------------------------------------
    if ($memberParam !== '') {
        $members = explode(',', $memberParam);
        foreach ($members as $m) {
            $m = trim($m);
            if ($m === '') {
                continue;
            }

            // Direkter Treffer?
            if (isset($sonoszone[$m])) {
                $zonesToSave[$m] = true;
                continue;
            }

            // Case-insensitive Match über alle Zonen (falls nötig)
            foreach ($sonoszone as $zname => $_) {
                if (strtolower($zname) === $m) {
                    $zonesToSave[$zname] = true;
                    break;
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // 3) Bestehende Gruppierung des Masters sichern
    //    (z.B. Kids + Duschbad schon als Gruppe vor T2S)
    // ------------------------------------------------------------------
    if (!empty($master) && isset($sonoszone[$master])) {
        $group = getGroup($master);
        if (is_array($group)) {
            foreach ($group as $z) {
                if (!empty($z) && isset($sonoszone[$z])) {
                    $zonesToSave[$z] = true;
                }
            }
        }
    }

    // Fallback: was hier drin ist, wird gesichert – mehr brauchen wir nicht.
    return array_keys($zonesToSave);
}

/**
 * Function : saveZonesStatus --> saves current details for each Zone
 *
 * Fast-Path:
 *   - Sichert nur die Zonen aus getZonesToSaveForT2S()
 *
 * @param   void
 * @return  array  Snapshot der relevanten Zonen
 */
function saveZonesStatus()
{
    global $sonoszone, $member, $config, $master, $sonos, $player, $actual, $time_start;

    $start  = microtime(true);
    $actual = array();

    // Ermitteln, welche Zonen überhaupt gesichert werden sollen
    $zonesToSave = getZonesToSaveForT2S();
    $zonesList   = implode(',', $zonesToSave);

    LOGGING("save_t2s.php: Snapshot will cover zones: ".$zonesList, 5);

    // ------------------------------------------------------------------
    // Status der relevanten Zonen auslesen
    // ------------------------------------------------------------------
    foreach ($zonesToSave as $zoneName) {

        if (!isset($sonoszone[$zoneName])) {
            continue;
        }

        $player = $zoneName;

        // SonosAccess für diese Zone
        $sonos = new SonosAccess($sonoszone[$player][0]);

        // 1) Basis-Status
        $actual[$player]['Mute']              = $sonos->GetMute($player);
        $actual[$player]['Volume']            = $sonos->GetVolume($player);
        $actual[$player]['MediaInfo']         = $sonos->GetMediaInfo($player);
        $actual[$player]['PositionInfo']      = $sonos->GetPositionInfo($player);
        $actual[$player]['TransportInfo']     = $sonos->GetTransportInfo($player);
        $actual[$player]['TransportSettings'] = $sonos->GetTransportSettings($player);

        // Koordinator bleibt wie bisher am Szenario-Master orientiert
        $actual[$player]['Coordinator'] = $master;

        // Gruppenzugehörigkeit (bestehende Logik aus getGroup() weiterverwenden)
        $actual[$player]['Grouping'] = getGroup($player);

        // ZoneStatus (master/member/single)
        $zonestatus                    = getZoneStatus($player);
        $actual[$player]['ZoneStatus'] = $zonestatus;

        // Typ-Erkennung nur für Master / Single-Zonen
        if ($zonestatus != "member") {

            $posinfo = $actual[$player]['PositionInfo'];
            $media   = $actual[$player]['MediaInfo'];

            if (substr($posinfo["TrackURI"], 0, 18) == "x-sonos-htastream:") {
                $actual[$player]['Type'] = "TV";

            } elseif (substr($media['UpnpClass'], 0, 36) == "object.item.audioItem.audioBroadcast") {
                $actual[$player]['Type'] = "Radio";

            } elseif (substr($posinfo["TrackURI"], 0, 15) == "x-rincon-stream") {
                $actual[$player]['Type'] = "LineIn";

            } elseif (empty($posinfo["CurrentURIMetaData"])) {
                $actual[$player]['Type'] = "Nothing";

            } else {
                $actual[$player]['Type'] = "Track";
            }
        }
    }

    // ------------------------------------------------------------------
    // Logging & Rückgabe
    // ------------------------------------------------------------------
    $elapsed = microtime(true) - $start;
    $elapsed = round($elapsed, 3);

    LOGGING("save_t2s.php: All relevant zone settings have been saved successfully (" . $elapsed . " seconds).", 5);

    return $actual;
}

?>
