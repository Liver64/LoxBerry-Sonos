#!/bin/sh
# Will be executed as user "root".

# ---------------------------------------------------------
# Resolve LoxBerry home robustly
# - Prefer $5 (installer arg)
# - Then $LBHOMEDIR (if exported)
# - Fallback: REPLACELBHOMEDIR
# ---------------------------------------------------------
LBHOME="${5:-${LBHOMEDIR:-REPLACELBHOMEDIR}}"

echo "<INFO> Using LBHOME: $LBHOME"

# ---------------------------------------------------------
# Piper Installation (robust, fixed path/layout)
# Target:
#   $LBHOME/bin/plugins/sonos4lox/piper/piper
#   + libs/assets in same folder
#   /usr/bin/piper -> $LBHOME/bin/plugins/sonos4lox/piper/piper
# ---------------------------------------------------------

PIPER_TARGET_DIR="$LBHOME/bin/plugins/sonos4lox"
PIPER_DIR="$PIPER_TARGET_DIR/piper"
PIPER_BINARY="$PIPER_DIR/piper"

mkdir -p "$PIPER_DIR"

echo "<INFO> Piper installation check – target binary: $PIPER_BINARY"

# Validate later if already present
NEED_INSTALL=1
if [ -f "$PIPER_BINARY" ] && [ -x "$PIPER_BINARY" ]; then
    echo "<INFO> Existing Piper binary found at $PIPER_BINARY – will validate."
    NEED_INSTALL=0
fi

ARCH="$(uname -m)"
echo "<INFO> Detected kernel architecture via uname: $ARCH"

TARBALL=""
URL=""

# LBSCONFIG fallback (avoid empty)
LBSCONFIG="${LBSCONFIG:-$LBHOME/config/system}"

if [ "$NEED_INSTALL" -eq 1 ]; then
    case "$ARCH" in
        armv7l)
            echo "<INFO> Assuming 32-bit ARM (armv7l)"
            TARBALL="piper_linux_armv7l.tar.gz"
            ;;
        aarch64|armv8*)
            echo "<INFO> Assuming 64-bit ARM (aarch64)"
            TARBALL="piper_linux_aarch64.tar.gz"
            ;;
        x86_64|amd64)
            echo "<INFO> Assuming 64-bit x86_64"
            TARBALL="piper_linux_x86_64.tar.gz"
            ;;
        *)
            echo "<WARNING> Unknown architecture '$ARCH' – trying LoxBerry markers..."
            if [ -e "$LBSCONFIG/is_arch_armv7l.cfg" ]; then
                echo "<INFO> LB marker: armv7l"
                TARBALL="piper_linux_armv7l.tar.gz"
            elif [ -e "$LBSCONFIG/is_arch_aarch64.cfg" ]; then
                echo "<INFO> LB marker: aarch64"
                TARBALL="piper_linux_aarch64.tar.gz"
            elif [ -e "$LBSCONFIG/is_x86.cfg" ] || [ -e "$LBSCONFIG/is_x64.cfg" ]; then
                echo "<INFO> LB marker: x86/x64"
                TARBALL="piper_linux_x86_64.tar.gz"
            else
                echo "<ERROR> Could not determine Piper architecture – aborting Piper installation."
                TARBALL=""
            fi
            ;;
    esac

    if [ -n "$TARBALL" ]; then
        URL="https://github.com/rhasspy/piper/releases/download/2023.11.14-2/$TARBALL"
        echo "<INFO> Downloading Piper from $URL"

        rm -f "$PIPER_DIR/$TARBALL"

        if wget -O "$PIPER_DIR/$TARBALL" "$URL"; then
            if tar -xvzf "$PIPER_DIR/$TARBALL" --strip-components=1 -C "$PIPER_DIR"; then
                echo "<OK> Piper archive $TARBALL extracted successfully (flattened)."
                rm -f "$PIPER_DIR/$TARBALL"
            else
                echo "<ERROR> Failed to extract $TARBALL"
                rm -f "$PIPER_DIR/$TARBALL"
                exit 1
            fi
        else
            echo "<ERROR> Failed to download Piper from $URL"
            rm -f "$PIPER_DIR/$TARBALL"
            exit 1
        fi
    fi
fi

# ---------------------------------------------------------
# Fix legacy wrong layout (if any): $PIPER_DIR/piper/piper
# ---------------------------------------------------------
if [ -d "$PIPER_DIR/piper" ] && [ -f "$PIPER_DIR/piper/piper" ]; then
    echo "<WARNING> Detected legacy nested Piper folder '$PIPER_DIR/piper' – flattening…"
    find "$PIPER_DIR/piper" -mindepth 1 -maxdepth 1 -exec mv -f {} "$PIPER_DIR/" \;
    rmdir "$PIPER_DIR/piper" 2>/dev/null || true
fi

# ---------------------------------------------------------
# Validate Piper binary (must be a FILE, not a directory)
# ---------------------------------------------------------
if [ -f "$PIPER_BINARY" ]; then
    chmod +x "$PIPER_BINARY" 2>/dev/null || true
fi

if [ -f "$PIPER_BINARY" ] && [ -x "$PIPER_BINARY" ]; then
    echo "<INFO> Validating Piper binary: $PIPER_BINARY"
    if "$PIPER_BINARY" --help >/dev/null 2>&1; then
        echo "<OK> Piper binary appears to be executable for this architecture."
    else
        echo "<ERROR> Piper binary at $PIPER_BINARY is not executable on this system (likely Exec format error)."
        echo "<ERROR> Disabling Piper – fallback will use 't2s_not_available.mp3'."
        rm -f "$PIPER_BINARY"
    fi
else
    if [ -d "$PIPER_BINARY" ]; then
        echo "<ERROR> Piper path '$PIPER_BINARY' is a directory (wrong extraction layout)."
        echo "<ERROR> Please delete '$PIPER_DIR' and reinstall, or ensure tar extraction is flattened."
    else
        echo "<WARNING> Piper binary not found after installation attempt."
    fi
fi

# ---------------------------------------------------------
# /usr/bin/piper symlink (only if valid binary exists)
# ---------------------------------------------------------
piper="/usr/bin/piper"

if [ -f "$PIPER_BINARY" ] && [ -x "$PIPER_BINARY" ]; then
    if [ -L "$piper" ]; then
        echo "<INFO> Symlink 'piper' already exists in /usr/bin – leaving it as is."
    elif [ -e "$piper" ] && [ ! -L "$piper" ]; then
        echo "<WARNING> A non-symlink /usr/bin/piper already exists – NOT overwritten."
    else
        ln -sf "$PIPER_BINARY" "$piper"
        echo "<INFO> Symlink 'piper' has been created in /usr/bin"
    fi
else
    echo "<WARNING> No valid Piper binary – no /usr/bin/piper symlink will be created."
fi


# ---------------------------------------------------------
# Systemd units: Sonos Check-On-State (service+timer) + Event Listener service + Watchdog (service+timer)
# - copy units
# - ONE daemon-reload at the end (only if we actually copied units)
# - enable/start OR disable/stop depending on config LOXONE.LoxDaten
# ---------------------------------------------------------

SYSTEMD_OK=0
if command -v systemctl >/dev/null 2>&1; then
    SYSTEMD_OK=1
else
    echo "<WARNING> systemctl not found – skipping systemd unit installation."
fi

INSTALL_ANY_UNIT=0

# ---------------------------------------------------------
# Decide if Loxone Datentransfer is enabled (LOXONE.LoxDaten)
# - If config missing or jq missing -> treat as disabled (safe default)
# ---------------------------------------------------------
LOXDATEN_ENABLED=0
CFG_S4LOX="REPLACELBHOMEDIR/config/plugins/sonos4lox/s4lox_config.json"

detect_loxdaten_enabled() {
    if [ "$SYSTEMD_OK" -ne 1 ]; then
        LOXDATEN_ENABLED=0
        return 0
    fi

    if [ ! -f "$CFG_S4LOX" ]; then
        echo "<INFO> Config not found ($CFG_S4LOX) – services/timers will be DISABLED."
        LOXDATEN_ENABLED=0
        return 0
    fi

    if ! command -v jq >/dev/null 2>&1; then
        echo "<WARNING> jq not found – cannot parse config. Services/timers will be DISABLED (safe default)."
        LOXDATEN_ENABLED=0
        return 0
    fi

    # Works for boolean true/false AND for strings "true"/"false"
    local v
    v="$(jq -r '(.LOXONE.LoxDaten // "false") | tostring | ascii_downcase' "$CFG_S4LOX" 2>/dev/null || echo "false")"

    if [ "$v" = "true" ]; then
        LOXDATEN_ENABLED=1
        echo "<INFO> Config: LOXONE.LoxDaten = true – services/timers will be ENABLED."
    else
        LOXDATEN_ENABLED=0
        echo "<INFO> Config: LOXONE.LoxDaten != true – services/timers will be DISABLED."
    fi

    return 0
}

install_sonos_event_listener_service() {
    SERVICE_SRC="$LBHOME/webfrontend/html/plugins/sonos4lox/bin/cron/sonos_event_listener.service"
    SERVICE_DEST="/etc/systemd/system/sonos_event_listener.service"

    if [ "$SYSTEMD_OK" -ne 1 ]; then
        return 0
    fi

    if [ ! -f "$SERVICE_SRC" ]; then
        echo "<WARNING> Sonos Event Listener service file not found at $SERVICE_SRC – skipping."
        return 0
    fi

    echo "<INFO> Installing Sonos Event Listener systemd service…"
    cp "$SERVICE_SRC" "$SERVICE_DEST"
    chmod 644 "$SERVICE_DEST"
    echo "<OK> sonos_event_listener.service installed."
    INSTALL_ANY_UNIT=1
}

install_sonos_check_on_state_units() {
    SERVICE_SRC_CHECK="$LBHOME/webfrontend/html/plugins/sonos4lox/bin/cron/sonos_check_on_state.service"
    TIMER_SRC_CHECK="$LBHOME/webfrontend/html/plugins/sonos4lox/bin/cron/sonos_check_on_state.timer"

    SERVICE_DEST_CHECK="/etc/systemd/system/sonos_check_on_state.service"
    TIMER_DEST_CHECK="/etc/systemd/system/sonos_check_on_state.timer"

    if [ "$SYSTEMD_OK" -ne 1 ]; then
        return 0
    fi

    if [ -f "$SERVICE_SRC_CHECK" ]; then
        echo "<INFO> Installing Sonos Check-On-State systemd service…"
        cp "$SERVICE_SRC_CHECK" "$SERVICE_DEST_CHECK"
        chmod 644 "$SERVICE_DEST_CHECK"
        echo "<OK> sonos_check_on_state.service installed."
        INSTALL_ANY_UNIT=1
    else
        echo "<WARNING> Sonos Check-On-State service file not found at $SERVICE_SRC_CHECK – skipping."
    fi

    if [ -f "$TIMER_SRC_CHECK" ]; then
        echo "<INFO> Installing Sonos Check-On-State systemd timer…"
        cp "$TIMER_SRC_CHECK" "$TIMER_DEST_CHECK"
        chmod 644 "$TIMER_DEST_CHECK"
        echo "<OK> sonos_check_on_state.timer installed."
        INSTALL_ANY_UNIT=1
    else
        echo "<WARNING> Sonos Check-On-State timer file not found at $TIMER_SRC_CHECK – skipping."
    fi
}

install_sonos_watchdog_units() {
    SERVICE_SRC_WD="$LBHOME/webfrontend/html/plugins/sonos4lox/bin/cron/sonos_watchdog.service"
    TIMER_SRC_WD="$LBHOME/webfrontend/html/plugins/sonos4lox/bin/cron/sonos_watchdog.timer"

    SERVICE_DEST_WD="/etc/systemd/system/sonos_watchdog.service"
    TIMER_DEST_WD="/etc/systemd/system/sonos_watchdog.timer"

    if [ "$SYSTEMD_OK" -ne 1 ]; then
        return 0
    fi

    if [ -f "$SERVICE_SRC_WD" ]; then
        echo "<INFO> Installing Sonos Watchdog systemd service…"
        cp "$SERVICE_SRC_WD" "$SERVICE_DEST_WD"
        chmod 644 "$SERVICE_DEST_WD"
        echo "<OK> sonos_watchdog.service installed."
        INSTALL_ANY_UNIT=1
    else
        echo "<WARNING> Sonos Watchdog service file not found at $SERVICE_SRC_WD – skipping."
    fi

    if [ -f "$TIMER_SRC_WD" ]; then
        echo "<INFO> Installing Sonos Watchdog systemd timer…"
        cp "$TIMER_SRC_WD" "$TIMER_DEST_WD"
        chmod 644 "$TIMER_DEST_WD"
        echo "<OK> sonos_watchdog.timer installed."
        INSTALL_ANY_UNIT=1
    else
        echo "<WARNING> Sonos Watchdog timer file not found at $TIMER_SRC_WD – skipping."
    fi
}

# ---------------------------------------------------------
# Protection against 203/EXEC:
# - If the PHP script loses its +x bit (e.g., update/copy), systemd ExecStart fails.
# - Patch the installed service to call /usr/bin/php explicitly.
# ---------------------------------------------------------
protect_check_on_state_execstart() {
    SERVICE_DEST="/etc/systemd/system/sonos_check_on_state.service"
    PHP="/usr/bin/php"
    TARGET="$LBHOME/webfrontend/html/plugins/sonos4lox/bin/check_on_state.php"

    if [ "$SYSTEMD_OK" -ne 1 ]; then
        return 0
    fi

    if [ ! -f "$SERVICE_DEST" ]; then
        return 0
    fi

    # Only patch if ExecStart points directly to check_on_state.php (no interpreter)
    if grep -q '^ExecStart=.*check_on_state\.php' "$SERVICE_DEST"; then
        if grep -q "^ExecStart=$PHP " "$SERVICE_DEST"; then
            echo "<INFO> Protection: sonos_check_on_state.service already uses $PHP."
        else
            echo "<INFO> Protection: Patching ExecStart to use $PHP (prevents 203/EXEC if +x gets lost)."
            sed -i "s|^ExecStart=.*check_on_state\.php.*$|ExecStart=$PHP $TARGET|" "$SERVICE_DEST"
            INSTALL_ANY_UNIT=1
            echo "<OK> Protection: ExecStart patched in /etc/systemd/system/sonos_check_on_state.service"
        fi
    fi

    return 0
}

# ---------------------------------------------------------
# Optional: keep certain scripts executable for manual runs
# ---------------------------------------------------------
protect_exec_bits() {
    for f in \
        "$LBHOME/webfrontend/html/plugins/sonos4lox/bin/check_on_state.php" \
        "$LBHOME/webfrontend/html/plugins/sonos4lox/watchdog.php"
    do
        if [ -f "$f" ]; then
            chmod +x "$f" 2>/dev/null || true
        fi
    done
    return 0
}

# ---------------------------------------------------------
# Cleanup legacy cron artifacts:
# - Search ALL subfolders under "$5/system/cron" for file "Sonos_On_check"
# - Delete matches
# ---------------------------------------------------------
remove_sonos_on_check_cronfiles() {

    CRONROOT="$LBHOME/system/cron"

    echo "<INFO> Cron cleanup: Searching for 'Sonos_On_check' under: $CRONROOT"

    if [ ! -d "$CRONROOT" ]; then
        echo "<INFO> Cron cleanup: Folder not found – skipping: $CRONROOT"
        return 0
    fi

    FOUND="$(find "$CRONROOT" -type f -name 'Sonos_On_check' 2>/dev/null || true)"

    if [ -z "$FOUND" ]; then
        echo "<INFO> Cron cleanup: No matching files found – nothing to remove."
        return 0
    fi

    echo "<INFO> Cron cleanup: Removing matching files..."
    echo "$FOUND" | while IFS= read -r f; do
        [ -n "$f" ] || continue
        if rm -f "$f" 2>/dev/null; then
            echo "<OK> Cron cleanup: Removed $f"
        else
            echo "<WARNING> Cron cleanup: Could not remove $f"
        fi
    done

    return 0
}

# --- install units ---
install_sonos_check_on_state_units
install_sonos_event_listener_service
install_sonos_watchdog_units

# --- protection steps (after units copied) ---
protect_check_on_state_execstart
protect_exec_bits

remove_sonos_on_check_cronfiles

# --- decide desired state from config ---
detect_loxdaten_enabled

# --- reload systemd only if we copied something ---
if [ "$SYSTEMD_OK" -eq 1 ] && [ "$INSTALL_ANY_UNIT" -eq 1 ]; then
    if systemctl daemon-reload >/dev/null 2>&1; then
        echo "<INFO> systemd daemon reloaded."
    else
        echo "<WARNING> Failed to reload systemd daemon (daemon-reload)."
    fi
fi

# --- apply state ALWAYS (stable across updates), if units exist ---
if [ "$SYSTEMD_OK" -eq 1 ]; then

    if [ -f /etc/systemd/system/sonos_check_on_state.timer ]; then
        if [ "$LOXDATEN_ENABLED" -eq 1 ]; then
            if systemctl enable --now sonos_check_on_state.timer >/dev/null 2>&1; then
                echo "<OK> sonos_check_on_state.timer has been enabled and started."
            else
                echo "<WARNING> Failed to enable/start sonos_check_on_state.timer. Please check 'systemctl status sonos_check_on_state.timer'."
            fi
        else
            systemctl disable --now sonos_check_on_state.timer >/dev/null 2>&1 || true
            echo "<OK> sonos_check_on_state.timer has been disabled and stopped."
        fi
    fi

    if [ -f /etc/systemd/system/sonos_event_listener.service ]; then
        if [ "$LOXDATEN_ENABLED" -eq 1 ]; then
            if systemctl enable --now sonos_event_listener.service >/dev/null 2>&1; then
                echo "<OK> sonos_event_listener.service has been enabled and started."
            else
                echo "<WARNING> Failed to enable/start sonos_event_listener.service. Please check 'systemctl status sonos_event_listener.service'."
            fi
        else
            systemctl disable --now sonos_event_listener.service >/dev/null 2>&1 || true
            echo "<OK> sonos_event_listener.service has been disabled and stopped."
        fi
    fi

    if [ -f /etc/systemd/system/sonos_watchdog.timer ]; then
        if [ "$LOXDATEN_ENABLED" -eq 1 ]; then
            if systemctl enable --now sonos_watchdog.timer >/dev/null 2>&1; then
                echo "<OK> sonos_watchdog.timer has been enabled and started."
            else
                echo "<WARNING> Failed to enable/start sonos_watchdog.timer. Please check 'systemctl status sonos_watchdog.timer'."
            fi
        else
            systemctl disable --now sonos_watchdog.timer >/dev/null 2>&1 || true
            echo "<OK> sonos_watchdog.timer has been disabled and stopped."
        fi
    fi

fi

exit 0
