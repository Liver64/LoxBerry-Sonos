#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once "/opt/loxberry/libs/phplib/loxberry_system.php";
require_once "/opt/loxberry/libs/phplib/loxberry_log.php";

/* === Settings === */
$ramdir   = "/dev/shm/sonos4lox";
$ramlog   = "$ramdir/sonos_watchdog.log";
$stdfile  = "/opt/loxberry/log/plugins/sonos4lox/sonos_watchdog.log"; // symlink target
$marker   = "/run/shm/sonos4lox.watchdog.started";

const MAX_LOG_BYTES        = 81920; // 80 KB
const RESTART_WINDOW_SEC   = 60;    // rate limit per unit
const NOTIFY_MAX_AGE_SEC   = 600;   // health stale threshold

$listener = "sonos_event_listener.service";
$checkSvc = "sonos_check_on_state.service";

/* === Helpers === */
function ensure_dir(string $dir, int $mode = 0775): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, $mode, true);
    }
}

function ensure_symlink(string $target, string $link): void
{
    if (file_exists($link) && !is_link($link)) {
        @unlink($link);
    }
    if (!is_link($link)) {
        @symlink($target, $link);
    }
}

function rotate_ramlog_if_needed(string $ramlog): void
{
    clearstatcache(true, $ramlog);
    if (is_file($ramlog)) {
        $sz = @filesize($ramlog);
        if ($sz !== false && $sz > MAX_LOG_BYTES) {
            @unlink($ramlog);
        }
    }
    @touch($ramlog);
    @chmod($ramlog, 0664);
}

/**
 * Run systemctl via sudo -n (never prompt for password)
 * @param string $args systemctl args (without "systemctl")
 * @param string|null $stdout receives stdout (trimmed)
 * @return int exit code
 */
function sysctl(string $args, ?string &$stdout = null): int
{
    $cmd = "sudo -n /bin/systemctl $args 2>/dev/null";
    $out = [];
    $rc  = 0;
    @exec($cmd, $out, $rc);
    $stdout = trim(implode("\n", $out));
    return $rc;
}

function unit_exists(string $unit): bool
{
    $stdout = '';
    $rc = sysctl("list-unit-files --type=service --type=timer --no-legend --no-pager", $stdout);
    if ($rc !== 0 || $stdout === '') return false;

    foreach (explode("\n", $stdout) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = preg_split('/\s+/', $line);
        if (!empty($parts[0]) && $parts[0] === $unit) return true;
    }
    return false;
}

function is_active(string $unit): string
{
    $stdout = '';
    sysctl("is-active " . escapeshellarg($unit), $stdout);
    return $stdout !== '' ? $stdout : 'unknown';
}

function is_failed(string $unit): string
{
    // outputs: failed | active | inactive | activating | deactivating | unknown
    $stdout = '';
    sysctl("is-failed " . escapeshellarg($unit), $stdout);
    return $stdout !== '' ? $stdout : 'unknown';
}

function start_unit(string $unit): bool
{
    $stdout = '';
    $rc = sysctl("start " . escapeshellarg($unit), $stdout);
    return $rc === 0;
}

function restart_unit(string $unit): bool
{
    $stdout = '';
    $rc = sysctl("restart " . escapeshellarg($unit), $stdout);
    return $rc === 0;
}

function restart_rate_limited(string $ramdir, string $unit): bool
{
    $stateFile = $ramdir . "/." . preg_replace('/[^A-Za-z0-9_.-]/', '_', $unit) . ".restart.ts";
    $now  = time();
    $last = (int)@file_get_contents($stateFile);

    if ($last && ($now - $last) < RESTART_WINDOW_SEC) {
        $wait = RESTART_WINDOW_SEC - ($now - $last);
        LOGINF("[$unit] Restart skipped (rate limit, wait {$wait}s)");
        return false;
    }
    @file_put_contents($stateFile, (string)$now, LOCK_EX);
    return true;
}

function read_health(string $file): ?array
{
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return null;
    $j = json_decode($raw, true);
    if (!is_array($j)) return null;
    return $j;
}

function health_age_seconds(array $h): ?int
{
    if (isset($h['last_notify_age_sec']) && is_numeric($h['last_notify_age_sec'])) {
        return (int)$h['last_notify_age_sec'];
    }
    if (isset($h['last_notify_ts']) && is_numeric($h['last_notify_ts'])) {
        return time() - (int)$h['last_notify_ts'];
    }
    if (isset($h['timestamp']) && is_numeric($h['timestamp'])) {
        return time() - (int)$h['timestamp'];
    }
    return null;
}

/* === Prepare dirs & log === */
ensure_dir($ramdir, 0775);
rotate_ramlog_if_needed($ramlog);
@ensure_symlink($ramlog, $stdfile);

/* === Initialize LoxBerry log (write to symlink -> physically RAM) === */
$params = [
    "name"     => "Sonos Watchdog",
    "filename" => $stdfile,
    "append"   => 1,
    "addtime"  => 1
];
$log = LBLog::newLog($params);

/* === Boot header (only once per boot) === */
if (!file_exists($marker)) {
    @file_put_contents($marker, (string)time());
    LOGSTART("Watchdog started");
} else {
    LOGINF("Watchdog timer cycle");
}

$rc_total = 0;

/* 1) Check sonos_check_on_state.service (oneshot!) */
if (!unit_exists($checkSvc)) {
    LOGWARN("[$checkSvc] Unit missing (skipping).");
    $rc_total = 1;
} else {
    $active = is_active($checkSvc);
    $failed = is_failed($checkSvc);
    LOGDEB("[$checkSvc] active=$active failed=$failed");

    // oneshot normal states: inactive (dead) or active (running right now)
    if ($failed === "failed") {
        LOGWARN("[$checkSvc] is FAILED -> restarting.");
        if (restart_rate_limited($ramdir, $checkSvc)) {
            // start is enough for oneshot, restart is fine too
            if (start_unit($checkSvc)) {
                LOGOK("[$checkSvc] start successful (recovered from failed).");
            } else {
                LOGERR("[$checkSvc] start failed (check sudoers).");
                $rc_total = 1;
            }
        } else {
            $rc_total = 1;
        }
    } else {
        // not failed => OK (inactive is expected)
        LOGOK("[$checkSvc] OK (oneshot; last run finished, not failed).");
    }
}

/* 2) Ensure listener is active (daemon) */
if (!unit_exists($listener)) {
    LOGERR("[$listener] Unit missing.");
    $rc_total = 1;
} else {
    $state = is_active($listener);
    LOGDEB("[$listener] state=$state");

    if ($state === "active") {
        LOGOK("[$listener] is active.");
    } else {
        LOGWARN("[$listener] not active (state='$state') -> restarting.");
        if (restart_rate_limited($ramdir, $listener)) {
            if (restart_unit($listener)) {
                LOGOK("[$listener] restart successful.");
            } else {
                LOGERR("[$listener] restart failed (check sudoers).");
                $rc_total = 1;
            }
        } else {
            $rc_total = 1;
        }
    }
}

/* 3) Health check (only if listener is active) */
$healthFile = "$ramdir/health.json";
$state = is_active($listener);

if ($state === "active") {
    $h = read_health($healthFile);
    if ($h === null) {
        LOGWARN("[$listener] health.json missing/invalid -> restarting listener.");
        if (restart_rate_limited($ramdir, $listener)) {
            if (restart_unit($listener)) {
                LOGOK("[$listener] restart successful after health check.");
            } else {
                LOGERR("[$listener] restart failed after health check (check sudoers).");
                $rc_total = 1;
            }
        } else {
            $rc_total = 1;
        }
    } else {
        $age = health_age_seconds($h);
        $pid = $h['pid'] ?? '?';
        $on  = $h['online_players'] ?? '?';
        $off = $h['offline_players'] ?? '?';
        $tot = $h['total_players'] ?? '?';

        LOGINF("[$listener] Health: pid=$pid players=$on online / $off offline (total $tot)");

        if ($age === null) {
            LOGWARN("[$listener] Health: no usable timestamp fields -> restarting listener.");
            if (restart_rate_limited($ramdir, $listener)) {
                if (restart_unit($listener)) {
                    LOGOK("[$listener] restart successful (missing health fields).");
                } else {
                    LOGERR("[$listener] restart failed (missing health fields).");
                    $rc_total = 1;
                }
            } else {
                $rc_total = 1;
            }
        } else {
            if ($age < 0) {
                LOGINF("[$listener] Health age is in the future (clock drift) -> OK");
            } elseif ($age > NOTIFY_MAX_AGE_SEC) {
                LOGWARN("[$listener] Health stale (age={$age}s > " . NOTIFY_MAX_AGE_SEC . "s) -> restarting listener.");
                if (restart_rate_limited($ramdir, $listener)) {
                    if (restart_unit($listener)) {
                        LOGOK("[$listener] restart successful (health stale).");
                    } else {
                        LOGERR("[$listener] restart failed (health stale).");
                        $rc_total = 1;
                    }
                } else {
                    $rc_total = 1;
                }
            } else {
                LOGOK("[$listener] Health OK (age={$age}s <= " . NOTIFY_MAX_AGE_SEC . "s).");
            }
        }
    }
} else {
    LOGINF("[$listener] Skipping health check (state='$state').");
}

/* === Final === */
if ($rc_total !== 0) {
    LOGWARN("Watchdog finished with issues (rc=$rc_total).");
}

exit($rc_total);
