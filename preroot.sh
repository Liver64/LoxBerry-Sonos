#!/bin/sh
# Sonos4Lox preroot.sh
# Version: PREROOT_PIPER_SAFE_COPY_V04_2026_11_04
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
exit 0
