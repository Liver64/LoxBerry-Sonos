#!/usr/bin/env php
<?php
/**
 * Sonos4Lox - Sonos Watchdog
 * Version: WATCHDOG_LOGMANAGER_REGISTRATION_FIX_V04_2026_06_18
 *
 * Keeps the Sonos event listener and the player state check service healthy.
 * Runtime location: src/Core/Runtime/Watchdog.php.
 * Executed by systemd timer/service.
 */
declare(strict_types=1);

require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_system.php";
require_once "REPLACELBHOMEDIR/libs/phplib/loxberry_log.php";
require_once dirname(__DIR__, 2) . "/Support/Logger.php";

/* === Settings === */
const S4L_WATCHDOG_CONTEXT = 'src/Core/Runtime/Watchdog.php';
const MAX_LOG_BYTES        = 81920; // 80 KB
const RESTART_WINDOW_SEC   = 60;    // rate limit per unit
const NOTIFY_MAX_AGE_SEC   = 600;   // health stale threshold

$ramdir   = "/run/shm/sonos4lox";
$stateDir = "REPLACELBHOMEDIR/data/plugins/sonos4lox/watchdog";
$ramlog   = "$ramdir/sonos_watchdog.log";
$stdfile  = "REPLACELBHOMEDIR/log/plugins/sonos4lox/sonos_watchdog.log"; // symlink target
$marker   = "$ramdir/.watchdog.started";

$listener = "sonos_event_listener.service";
$checkSvc = "sonos_check_on_state.service";

/* === Helpers === */
function wd_log_level(string $level): int
{
    switch ($level) {
        case 'ERROR':
            return S4L_Logger::LEVEL_ERROR;
        case 'WARN':
            return S4L_Logger::LEVEL_WARNING;
        case 'OK':
            return S4L_Logger::LEVEL_OK;
        case 'START':
        case 'INFO':
            return S4L_Logger::LEVEL_INFO;
        case 'DEBUG':
        default:
            return S4L_Logger::LEVEL_DEBUG;
    }
}

function wd_log(string $level, string $message): void
{
    if (class_exists('S4L_Logger')) {
        S4L_Logger::write($message, wd_log_level($level), __FILE__);
        return;
    }

    echo '<' . $level . '> ' . S4L_WATCHDOG_CONTEXT . ': ' . $message . PHP_EOL;
}

function ensure_dir(string $dir, int $mode = 0775): bool
{
    if (!is_dir($dir)) {
        if (!@mkdir($dir, $mode, true) && !is_dir($dir)) {
            return false;
        }
    }

    @chmod($dir, $mode);
    return is_dir($dir) && is_writable($dir);
}

function ensure_symlink(string $target, string $link): bool
{
    $linkDir = dirname($link);
    if (!ensure_dir($linkDir, 0775)) {
        return false;
    }

    if (is_link($link)) {
        $currentTarget = @readlink($link);
        if ($currentTarget === $target) {
            return true;
        }
        @unlink($link);
    } elseif (file_exists($link)) {
        @unlink($link);
    }

    return @symlink($target, $link) || is_link($link);
}

function rotate_ramlog_if_needed(string $ramlog): bool
{
    clearstatcache(true, $ramlog);
    if (is_file($ramlog)) {
        $sz = @filesize($ramlog);
        if ($sz !== false && $sz > MAX_LOG_BYTES && !@unlink($ramlog)) {
            return false;
        }
    }

    if (!@touch($ramlog)) {
        return false;
    }

    @chmod($ramlog, 0664);
    return true;
}

function is_safe_systemd_unit(string $unit): bool
{
    return preg_match('/^[A-Za-z0-9_.@:\\-]+$/', $unit) === 1;
}

/**
 * Run systemctl via sudo -n. This must never prompt for a password.
 *
 * @param string $args systemctl args without "systemctl"
 * @param string|null $stdout receives stdout trimmed
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

function sysctl_unit(string $action, string $unit, ?string &$stdout = null): int
{
    if (!is_safe_systemd_unit($unit)) {
        $stdout = '';
        wd_log('ERROR', "Unsafe systemd unit name rejected: '$unit'");
        return 1;
    }

    return sysctl($action . ' ' . escapeshellarg($unit), $stdout);
}

function unit_exists(string $unit): bool
{
    static $unitCache = null;

    if (!is_safe_systemd_unit($unit)) {
        wd_log('ERROR', "Unsafe systemd unit name rejected while checking existence: '$unit'");
        return false;
    }

    if ($unitCache === null) {
        $stdout = '';
        $rc = sysctl("list-unit-files --type=service --type=timer --no-legend --no-pager", $stdout);
        $unitCache = [];

        if ($rc === 0 && $stdout !== '') {
            foreach (explode("\n", $stdout) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = preg_split('/\s+/', $line);
                if (!empty($parts[0])) {
                    $unitCache[$parts[0]] = true;
                }
            }
        }
    }

    return isset($unitCache[$unit]);
}

function is_active(string $unit): string
{
    $stdout = '';
    sysctl_unit('is-active', $unit, $stdout);
    return $stdout !== '' ? $stdout : 'unknown';
}

function is_failed(string $unit): string
{
    // outputs: failed | active | inactive | activating | deactivating | unknown
    $stdout = '';
    sysctl_unit('is-failed', $unit, $stdout);
    return $stdout !== '' ? $stdout : 'unknown';
}

function start_unit(string $unit): bool
{
    $stdout = '';
    $rc = sysctl_unit('start', $unit, $stdout);
    return $rc === 0;
}

function restart_unit(string $unit): bool
{
    $stdout = '';
    $rc = sysctl_unit('restart', $unit, $stdout);
    return $rc === 0;
}

function restart_rate_limited(string $stateDir, string $unit): bool
{
    if (!ensure_dir($stateDir, 0775)) {
        wd_log('WARN', "[$unit] Restart rate-limit directory '$stateDir' is not writable; continuing without persistent rate limit state.");
        return true;
    }

    $stateFile = $stateDir . "/." . preg_replace('/[^A-Za-z0-9_.-]/', '_', $unit) . ".restart.ts";
    $now  = time();
    $last = (int)@file_get_contents($stateFile);

    if ($last > 0 && ($now - $last) < RESTART_WINDOW_SEC) {
        $wait = RESTART_WINDOW_SEC - ($now - $last);
        wd_log('INFO', "[$unit] Restart skipped due to rate limit. Wait {$wait}s.");
        return false;
    }

    if (@file_put_contents($stateFile, (string)$now, LOCK_EX) === false) {
        wd_log('WARN', "[$unit] Could not write restart rate-limit state file '$stateFile'.");
    }

    return true;
}

function restart_listener_with_reason(string $stateDir, string $listener, string $reason, int &$rc_total): void
{
    wd_log('WARN', "[$listener] $reason -> restarting listener.");

    if (!restart_rate_limited($stateDir, $listener)) {
        $rc_total = 1;
        return;
    }

    if (restart_unit($listener)) {
        wd_log('OK', "[$listener] Restart successful ($reason).");
        return;
    }

    wd_log('ERROR', "[$listener] Restart failed ($reason). Check sudoers/systemd permissions.");
    $rc_total = 1;
}

function read_health(string $file): ?array
{
    if (!is_file($file) || !is_readable($file)) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $json;
}

function health_age_seconds(array $health): ?int
{
    if (isset($health['last_notify_age_sec']) && is_numeric($health['last_notify_age_sec'])) {
        return (int)$health['last_notify_age_sec'];
    }
    if (isset($health['last_notify_ts']) && is_numeric($health['last_notify_ts'])) {
        return time() - (int)$health['last_notify_ts'];
    }
    if (isset($health['timestamp']) && is_numeric($health['timestamp'])) {
        return time() - (int)$health['timestamp'];
    }

    return null;
}

/* === Prepare dirs & log === */
ensure_dir($ramdir, 0775);
ensure_dir(dirname($stdfile), 0775);

$ramlog  = $ramdir . "/sonos_watchdog.log";
$stdfile = "REPLACELBHOMEDIR/log/plugins/sonos4lox/sonos_watchdog.log";

/*
 * We keep the visible LoxBerry log path as symlink, but the real file is in RAM.
 * This avoids SD writes while keeping the LoxBerry Log Manager path stable.
 */
$ramlogWasMissing = !file_exists($ramlog);
$markerWasMissing = !file_exists($marker);
$markerWasStale   = false;
$stdfileWasMissing = (!file_exists($stdfile) && !is_link($stdfile));
$stdfileNeedsRepair = false;

if (is_link($stdfile)) {
    $stdfileNeedsRepair = (@readlink($stdfile) !== $ramlog);
} elseif (file_exists($stdfile)) {
    // A regular file at the visible Log Manager path must be replaced by the RAM-log symlink.
    $stdfileNeedsRepair = true;
}

if (!$markerWasMissing) {
    $markerMtime = @filemtime($marker);
    if ($markerMtime === false || (time() - (int)$markerMtime) > 21600) { // Refresh Log Manager registration every 6 hours.
        $markerWasStale = true;
    }
}

$ramlogPrepared = rotate_ramlog_if_needed($ramlog);
$symlinkPrepared = ensure_symlink($ramlog, $stdfile);

$needLogStart = $markerWasMissing
    || $markerWasStale
    || $ramlogWasMissing
    || $stdfileWasMissing
    || $stdfileNeedsRepair
    || !$ramlogPrepared
    || !$symlinkPrepared;

/* Let LBLog register the visible LoxBerry logfile path. */
$params = [
    "name"     => "Sonos Watchdog",
    "package"  => "sonos4lox",
    "filename" => $stdfile,

    "NAME"     => "Sonos Watchdog",
    "PACKAGE"  => "sonos4lox",
    "LOGFILE"  => $stdfile,

    "append"   => 1,
    "addtime"  => 1,
];
$log = LBLog::newLog($params);

/* === Boot/header registration === */
if ($needLogStart) {
    @file_put_contents($marker, (string)time(), LOCK_EX);

    if (function_exists('LOGSTART')) {
        LOGSTART('Watchdog started or log registration refreshed.');
    }

    if ($markerWasMissing || $ramlogWasMissing) {
        wd_log('START', 'Watchdog started.');
    } else {
        wd_log('INFO', 'Watchdog Log Manager registration refreshed.');
    }

    if (!$ramlogPrepared) {
        wd_log('WARN', 'RAM watchdog log file could not be prepared cleanly.');
    }
    if (!$symlinkPrepared) {
        wd_log('WARN', 'Visible watchdog log symlink for LoxBerry Log Manager could not be prepared cleanly.');
    }
} else {
    wd_log('INFO', 'Watchdog timer cycle.');
}

$rc_total = 0;

/* 1) Check sonos_check_on_state.service (oneshot) */
if (!unit_exists($checkSvc)) {
    wd_log('WARN', "[$checkSvc] Unit missing. Skipping oneshot check.");
    $rc_total = 1;
} else {
    $active = is_active($checkSvc);
    $failed = is_failed($checkSvc);
    wd_log('DEBUG', "[$checkSvc] active=$active failed=$failed");

    // Normal oneshot states: inactive (dead) or active (running right now).
    if ($failed === 'failed') {
        wd_log('WARN', "[$checkSvc] Unit is failed. Starting oneshot service.");
        if (restart_rate_limited($stateDir, $checkSvc)) {
            if (start_unit($checkSvc)) {
                wd_log('OK', "[$checkSvc] Start successful. Recovered from failed state.");
            } else {
                wd_log('ERROR', "[$checkSvc] Start failed. Check sudoers/systemd permissions.");
                $rc_total = 1;
            }
        } else {
            $rc_total = 1;
        }
    } else {
        wd_log('OK', "[$checkSvc] OK. Oneshot last run finished and is not failed.");
    }
}

/* 2) Ensure listener is active (daemon) */
if (!unit_exists($listener)) {
    wd_log('ERROR', "[$listener] Unit missing.");
    $rc_total = 1;
} else {
    $state = is_active($listener);
    wd_log('DEBUG', "[$listener] state=$state");

    if ($state === 'active') {
        wd_log('OK', "[$listener] Service is active.");
    } else {
        restart_listener_with_reason($stateDir, $listener, "Service is not active (state='$state')", $rc_total);
    }
}

/* 3) Health check, only when listener is active */
$healthFile = "$ramdir/health.json";
$state = is_active($listener);

if ($state === 'active') {
    $health = read_health($healthFile);
    if ($health === null) {
        restart_listener_with_reason($stateDir, $listener, "health.json is missing or invalid", $rc_total);
    } else {
        $age = health_age_seconds($health);
        $pid = $health['pid'] ?? '?';
        $on  = $health['online_players'] ?? '?';
        $off = $health['offline_players'] ?? '?';
        $tot = $health['total_players'] ?? '?';

        wd_log('INFO', "[$listener] Health: pid=$pid players=$on online / $off offline (total $tot).");

        if ($age === null) {
            restart_listener_with_reason($stateDir, $listener, "Health has no usable timestamp fields", $rc_total);
        } elseif ($age < 0) {
            wd_log('INFO', "[$listener] Health age is in the future (clock drift, age={$age}s). Keeping listener active.");
        } elseif ($age > NOTIFY_MAX_AGE_SEC) {
            restart_listener_with_reason($stateDir, $listener, "Health is stale (age={$age}s > " . NOTIFY_MAX_AGE_SEC . "s)", $rc_total);
        } else {
            wd_log('OK', "[$listener] Health OK (age={$age}s <= " . NOTIFY_MAX_AGE_SEC . "s).");
        }
    }
} else {
    wd_log('INFO', "[$listener] Skipping health check because service state is '$state'.");
}

/* === Final === */
if ($rc_total !== 0) {
    wd_log('WARN', "Watchdog finished with issues (rc=$rc_total).");
} else {
    wd_log('OK', 'Watchdog finished successfully.');
}

exit($rc_total);
