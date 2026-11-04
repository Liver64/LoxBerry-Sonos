#!/bin/sh
# Sonos4Lox preroot.sh
# Version: PREROOT_PIPER_ONLINE_GUARD_V05_2026_11_04
# Will be executed as user "root".
# Back up the Piper runtime before LoxBerry removes the previous plugin tree.

COMMAND="$0"
PTEMPDIR="$1"
PSHNAME="$2"
PDIR="$3"
PVERSION="$4"
LBHOME="${5:-${LBHOMEDIR:-REPLACELBHOMEDIR}}"

if [ -z "$PDIR" ]; then
    PDIR="sonos4lox"
fi

log_info() { echo "<INFO> $*"; }
log_ok() { echo "<OK> $*"; }
log_warning() { echo "<WARNING> $*"; }
log_error() { echo "<ERROR> $*"; }

log_info "Using LBHOME: $LBHOME"

# Block online checks before the installer replaces configuration and data.
# The marker survives plugin folder removal; the restored postroot.sh clears it.
case "$PTEMPDIR" in
    ""|*[!A-Za-z0-9_-]*)
        log_error "Unsafe or missing installer token: $PTEMPDIR"
        exit 2
        ;;
esac
case "$PDIR" in
    ""|*[!A-Za-z0-9_-]*)
        log_error "Unsafe plugin directory: $PDIR"
        exit 2
        ;;
esac

ONLINE_GUARD_DIR="/run/shm/$PDIR"
ONLINE_GUARD_FILE="$ONLINE_GUARD_DIR/.online_check_upgrade"
if [ -L "$ONLINE_GUARD_DIR" ] || [ -L "$ONLINE_GUARD_FILE" ]; then
    log_error "Online-check guard path is a symlink; refusing to continue."
    exit 2
fi
# Undo the local pause if this preroot script itself fails. A later installer
# failure is handled by the expiring guard and the cron fallback.
PREROOT_COMPLETE=0
ONLINE_TIMER_WAS_ACTIVE=0
preroot_exit_cleanup() {
    if [ "$PREROOT_COMPLETE" -eq 1 ]; then
        return 0
    fi
    if [ -f "$ONLINE_GUARD_FILE" ] && [ ! -L "$ONLINE_GUARD_FILE" ] &&
       [ "$(cat "$ONLINE_GUARD_FILE" 2>/dev/null || true)" = "$PTEMPDIR" ]; then
        rm -f "$ONLINE_GUARD_FILE"
    fi
    if [ "$ONLINE_TIMER_WAS_ACTIVE" -eq 1 ]; then
        systemctl start sonos_check_on_state.timer >/dev/null 2>&1 || true
    fi
}
trap 'preroot_exit_cleanup' 0

if [ ! -d "$ONLINE_GUARD_DIR" ]; then
    if ! mkdir -p "$ONLINE_GUARD_DIR" ||
       ! chown loxberry:loxberry "$ONLINE_GUARD_DIR" ||
       ! chmod 0755 "$ONLINE_GUARD_DIR"; then
        log_error "Cannot create online-check guard directory: $ONLINE_GUARD_DIR"
        exit 2
    fi
fi
if ! printf '%s\n' "$PTEMPDIR" > "$ONLINE_GUARD_FILE" || ! chmod 0644 "$ONLINE_GUARD_FILE"; then
    log_error "Cannot create online-check upgrade guard: $ONLINE_GUARD_FILE"
    exit 2
fi
if command -v runuser >/dev/null 2>&1 &&
   ! runuser -u loxberry -- test -r "$ONLINE_GUARD_FILE"; then
    log_error "Online-check guard is not readable by loxberry: $ONLINE_GUARD_FILE"
    exit 2
fi
log_ok "Online checks paused for plugin replacement."

# Stop systemd scheduling and wait for any active one-shot check to finish.
# The cron fallback also observes the upgrade guard in CheckState.php.
if command -v systemctl >/dev/null 2>&1; then
    if systemctl is-active --quiet sonos_check_on_state.timer; then
        ONLINE_TIMER_WAS_ACTIVE=1
    fi
    systemctl stop sonos_check_on_state.timer >/dev/null 2>&1 || true
    if systemctl stop sonos_check_on_state.service >/dev/null 2>&1; then
        log_ok "Online-check systemd service stopped for upgrade."
    else
        log_info "Online-check systemd service was not active or could not be stopped."
    fi
fi

# Keep the small online-state markers outside the plugin data tree so a
# restored marker cannot be mistaken for a newly online player after upgrade.
STATUS_DIR="$LBHOME/data/plugins/$PDIR/PlayerStatus"
STATUS_STAGE="$LBHOME/data/plugins/.${PDIR}_playerstatus_upgrade_${PTEMPDIR}"
if [ -L "$STATUS_DIR" ] || [ -L "$STATUS_STAGE" ] || [ -e "$STATUS_STAGE" ]; then
    log_error "Unsafe or occupied PlayerStatus backup path: $STATUS_STAGE"
    rm -f "$ONLINE_GUARD_FILE"
    exit 2
fi
if [ -d "$STATUS_DIR" ]; then
    if cp -a "$STATUS_DIR" "$STATUS_STAGE"; then
        log_ok "PlayerStatus markers preserved before plugin replacement: $STATUS_STAGE"
    else
        log_error "PlayerStatus backup failed; keeping the existing installation."
        rm -f "$ONLINE_GUARD_FILE"
        exit 2
    fi
else
    log_info "No PlayerStatus folder to preserve."
fi
# ---------------------------------------------------------
# Remove old cron.d artifact if still present.
# ---------------------------------------------------------
CRON_D_FILE="$LBHOME/system/cron/cron.d/Sonos"

if [ -f "$CRON_D_FILE" ]; then
    if rm -f "$CRON_D_FILE"; then
        log_ok "Cronjob cron.d/Sonos has been deleted."
    else
        log_warning "Could not delete legacy cronjob: $CRON_D_FILE"
    fi
else
    log_info "Legacy cronjob cron.d/Sonos was not present."
fi

# ---------------------------------------------------------
# Back up the complete Piper runtime OUTSIDE the plugin tree.
# Keep the original running if the upgrade is cancelled before replacement.
# The copy stays on disk (not in /tmp) and does not use zram-backed memory.
# PREUPGRADE clears its own backup folder, so it must not be used here.
# ---------------------------------------------------------
case "$PTEMPDIR" in
    ""|*[!A-Za-z0-9_-]*)
        log_error "Unsafe or missing installer token for Piper preservation: $PTEMPDIR"
        exit 2
        ;;
esac
case "$PDIR" in
    ""|*[!A-Za-z0-9_-]*)
        log_error "Unsafe plugin directory name for Piper preservation: $PDIR"
        exit 2
        ;;
esac

PIPER_DIR="$LBHOME/bin/plugins/$PDIR/piper"
PIPER_STAGE="$LBHOME/bin/plugins/.${PDIR}_piper_upgrade_${PTEMPDIR}"

if [ -L "$PIPER_DIR" ]; then
    log_error "Piper folder is a symlink, refusing to back it up: $PIPER_DIR"
    exit 2
elif [ -d "$PIPER_DIR" ]; then
    if [ -e "$PIPER_STAGE" ] || [ -L "$PIPER_STAGE" ]; then
        log_error "Piper backup already exists; refusing to overwrite it: $PIPER_STAGE"
        exit 2
    fi
    if cp -a "$PIPER_DIR" "$PIPER_STAGE"; then
        log_ok "Piper runtime backed up safely before plugin replacement: $PIPER_STAGE"
    else
        log_error "Could not back up Piper runtime. Aborting upgrade to protect existing binaries: $PIPER_DIR"
        exit 2
    fi

    # Let the LoxBerry installer remove the original tree without permission
    # errors. Do not change executable bits on binaries or shared libraries.
    PIPER_PARENT="$LBHOME/bin/plugins/$PDIR"
    if chown -R loxberry:loxberry "$PIPER_DIR" && \
       chown loxberry:loxberry "$PIPER_PARENT" && \
       chmod u+rwx "$PIPER_PARENT" && \
       find "$PIPER_DIR" -type d -exec chmod u+rwx {} +; then
        log_ok "Piper runtime permissions prepared for LoxBerry plugin replacement."
    else
        log_error "Could not prepare Piper permissions; aborting before plugin replacement."
        exit 2
    fi
else
    log_info "No existing Piper runtime to stage: $PIPER_DIR"
fi

# The existing /usr/bin/piper symlink is intentionally left untouched until
# postroot.sh completes. This keeps Piper functional if preupgrade aborts.
PREROOT_COMPLETE=1
exit 0
