#!/bin/sh
# Sonos4Lox preroot.sh
# Version: INSTALL_SCRIPT_ROBUSTNESS_V01_2026_06_15
# Will be executed as user "root".

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

log_info "Using LBHOME: $LBHOME"

# ---------------------------------------------------------
# Remove old cron.d artifact if still present
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
# Remove old plugin-owned Piper installation folder.
# postroot.sh installs/validates Piper again using the current layout.
# ---------------------------------------------------------
PIPER_DIR="$LBHOME/bin/plugins/$PDIR/piper"

if [ -d "$PIPER_DIR" ]; then
    if rm -rf "$PIPER_DIR"; then
        log_ok "Legacy Piper folder has been deleted: $PIPER_DIR"
    else
        log_warning "Could not delete legacy Piper folder: $PIPER_DIR"
    fi
else
    log_info "Legacy Piper folder was not present: $PIPER_DIR"
fi

# ---------------------------------------------------------
# Remove stale /usr/bin/piper symlink only if it points into this plugin
# or if the target no longer exists. Do not overwrite a valid system binary.
# ---------------------------------------------------------
PIPER_LINK="/usr/bin/piper"

if [ -L "$PIPER_LINK" ]; then
    LINK_TARGET="$(readlink "$PIPER_LINK" 2>/dev/null || true)"
    case "$LINK_TARGET" in
        "$LBHOME/bin/plugins/$PDIR"/*|"$PIPER_DIR"/*)
            if rm -f "$PIPER_LINK"; then
                log_ok "Removed legacy Piper symlink: $PIPER_LINK -> $LINK_TARGET"
            else
                log_warning "Could not remove Piper symlink: $PIPER_LINK -> $LINK_TARGET"
            fi
            ;;
        *)
            if [ -n "$LINK_TARGET" ] && [ ! -e "$LINK_TARGET" ]; then
                if rm -f "$PIPER_LINK"; then
                    log_ok "Removed broken Piper symlink: $PIPER_LINK -> $LINK_TARGET"
                else
                    log_warning "Could not remove broken Piper symlink: $PIPER_LINK -> $LINK_TARGET"
                fi
            else
                log_info "Existing /usr/bin/piper symlink does not belong to this plugin; leaving unchanged: $LINK_TARGET"
            fi
            ;;
    esac
else
    log_info "No /usr/bin/piper symlink present."
fi

exit 0
