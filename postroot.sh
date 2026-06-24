#!/bin/sh
# Sonos4Lox postroot.sh
# Version: POSTROOT_MEMORY_SAFE_V02_2026_06_24
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
log_error() { echo "<ERROR> $*"; }

log_info "Using LBHOME: $LBHOME"

# ---------------------------------------------------------
# Normalize plugin config permissions if config exists.
# This prevents failed saves after root-owned backup/copy operations.
# ---------------------------------------------------------
normalize_config_permissions() {
    CONFIG_DIR="$LBHOME/config/plugins/$PDIR"

    if [ ! -d "$CONFIG_DIR" ]; then
        log_info "Config folder not found yet, skipping permission normalization: $CONFIG_DIR"
        return 0
    fi

    chown -R loxberry:loxberry "$CONFIG_DIR" 2>/dev/null || true
    chmod 0755 "$CONFIG_DIR" 2>/dev/null || true
    find "$CONFIG_DIR" -type f -exec chmod 0644 {} \; 2>/dev/null || true

    log_ok "Config permissions normalized: $CONFIG_DIR"
    return 0
}

# ---------------------------------------------------------
# Piper Installation (robust, fixed path/layout)
# Target:
#   $LBHOME/bin/plugins/$PDIR/piper/piper
#   /usr/bin/piper -> $LBHOME/bin/plugins/$PDIR/piper/piper
# ---------------------------------------------------------
install_or_validate_piper() {
    PIPER_TARGET_DIR="$LBHOME/bin/plugins/$PDIR"
    PIPER_DIR="$PIPER_TARGET_DIR/piper"
    PIPER_BINARY="$PIPER_DIR/piper"

    mkdir -p "$PIPER_DIR"

    log_info "Piper installation check – target binary: $PIPER_BINARY"

    NEED_INSTALL=1
    if [ -f "$PIPER_BINARY" ] && [ -x "$PIPER_BINARY" ]; then
        log_info "Existing Piper binary found at $PIPER_BINARY – will validate."
        NEED_INSTALL=0
    fi

    ARCH="$(uname -m)"
    log_info "Detected kernel architecture via uname: $ARCH"

    TARBALL=""
    URL=""
    LBSCONFIG="${LBSCONFIG:-$LBHOME/config/system}"

    if [ "$NEED_INSTALL" -eq 1 ]; then
        case "$ARCH" in
            armv7l)
                log_info "Assuming 32-bit ARM (armv7l)"
                TARBALL="piper_linux_armv7l.tar.gz"
                ;;
            aarch64|armv8*)
                log_info "Assuming 64-bit ARM (aarch64)"
                TARBALL="piper_linux_aarch64.tar.gz"
                ;;
            x86_64|amd64)
                log_info "Assuming 64-bit x86_64"
                TARBALL="piper_linux_x86_64.tar.gz"
                ;;
            *)
                log_warning "Unknown architecture '$ARCH' – trying LoxBerry markers..."
                if [ -e "$LBSCONFIG/is_arch_armv7l.cfg" ]; then
                    TARBALL="piper_linux_armv7l.tar.gz"
                elif [ -e "$LBSCONFIG/is_arch_aarch64.cfg" ]; then
                    TARBALL="piper_linux_aarch64.tar.gz"
                elif [ -e "$LBSCONFIG/is_x86.cfg" ] || [ -e "$LBSCONFIG/is_x64.cfg" ]; then
                    TARBALL="piper_linux_x86_64.tar.gz"
                else
                    log_error "Could not determine Piper architecture – skipping Piper installation."
                    TARBALL=""
                fi
                ;;
        esac

        if [ -n "$TARBALL" ]; then
            URL="https://github.com/rhasspy/piper/releases/download/2023.11.14-2/$TARBALL"
            log_info "Downloading Piper from $URL"

            rm -f "$PIPER_DIR/$TARBALL" "$PIPER_DIR/$TARBALL.download"

            if wget --no-verbose -O "$PIPER_DIR/$TARBALL.download" "$URL"; then
                mv -f "$PIPER_DIR/$TARBALL.download" "$PIPER_DIR/$TARBALL"
                if tar -xzf "$PIPER_DIR/$TARBALL" --strip-components=1 -C "$PIPER_DIR"; then
                    log_ok "Piper archive $TARBALL extracted successfully (flattened)."
                    rm -f "$PIPER_DIR/$TARBALL"
                else
                    log_error "Failed to extract $TARBALL"
                    rm -f "$PIPER_DIR/$TARBALL"
                fi
            else
                log_error "Failed to download Piper from $URL"
                rm -f "$PIPER_DIR/$TARBALL.download" "$PIPER_DIR/$TARBALL"
            fi
        fi
    fi

    if [ -d "$PIPER_DIR/piper" ] && [ -f "$PIPER_DIR/piper/piper" ]; then
        log_warning "Detected legacy nested Piper folder '$PIPER_DIR/piper' – flattening."
        find "$PIPER_DIR/piper" -mindepth 1 -maxdepth 1 -exec mv -f {} "$PIPER_DIR/" \;
        rmdir "$PIPER_DIR/piper" 2>/dev/null || true
    fi

    if [ -f "$PIPER_BINARY" ]; then
        chmod +x "$PIPER_BINARY" 2>/dev/null || true
    fi

    if [ -f "$PIPER_BINARY" ] && [ -x "$PIPER_BINARY" ]; then
        log_info "Validating Piper binary: $PIPER_BINARY"
        if "$PIPER_BINARY" --help >/dev/null 2>&1; then
            log_ok "Piper binary appears to be executable for this architecture."
        else
            log_error "Piper binary at $PIPER_BINARY is not executable on this system."
            log_error "Disabling Piper – fallback will use 't2s_not_available.mp3'."
            rm -f "$PIPER_BINARY"
        fi
    else
        if [ -d "$PIPER_BINARY" ]; then
            log_error "Piper path '$PIPER_BINARY' is a directory (wrong extraction layout)."
        else
            log_warning "Piper binary not found after installation attempt."
        fi
    fi

    PIPER_LINK="/usr/bin/piper"
    if [ -f "$PIPER_BINARY" ] && [ -x "$PIPER_BINARY" ]; then
        if [ -L "$PIPER_LINK" ]; then
            CURRENT_TARGET="$(readlink "$PIPER_LINK" 2>/dev/null || true)"
            if [ "$CURRENT_TARGET" = "$PIPER_BINARY" ]; then
                log_info "Symlink /usr/bin/piper already points to the current plugin binary."
            else
                ln -sf "$PIPER_BINARY" "$PIPER_LINK"
                log_ok "Symlink /usr/bin/piper updated: $PIPER_BINARY"
            fi
        elif [ -e "$PIPER_LINK" ] && [ ! -L "$PIPER_LINK" ]; then
            log_warning "A non-symlink /usr/bin/piper already exists – NOT overwritten."
        else
            ln -sf "$PIPER_BINARY" "$PIPER_LINK"
            log_ok "Symlink /usr/bin/piper created: $PIPER_BINARY"
        fi
    else
        log_warning "No valid Piper binary – no /usr/bin/piper symlink will be created."
    fi

    return 0
}

# ---------------------------------------------------------
# Systemd support
# ---------------------------------------------------------
SYSTEMD_OK=0
if command -v systemctl >/dev/null 2>&1; then
    SYSTEMD_OK=1
else
    log_warning "systemctl not found – skipping systemd unit installation."
fi

INSTALL_ANY_UNIT=0
LOXDATEN_ENABLED=0
CFG_S4LOX="$LBHOME/config/plugins/$PDIR/s4lox_config.json"

detect_loxdaten_enabled() {
    LOXDATEN_ENABLED=0

    if [ "$SYSTEMD_OK" -ne 1 ]; then
        return 0
    fi

    if [ ! -f "$CFG_S4LOX" ]; then
        log_info "Config not found ($CFG_S4LOX) – services/timers will be DISABLED."
        return 0
    fi

    if command -v php >/dev/null 2>&1; then
        V="$(php -r '$f=$argv[1]; $j=@json_decode(@file_get_contents($f), true); $v=$j["LOXONE"]["LoxDaten"] ?? "false"; echo strtolower((string)$v);' "$CFG_S4LOX" 2>/dev/null || echo "false")"
    elif command -v jq >/dev/null 2>&1; then
        V="$(jq -r '(.LOXONE.LoxDaten // "false") | tostring | ascii_downcase' "$CFG_S4LOX" 2>/dev/null || echo "false")"
    else
        log_warning "Neither php nor jq found – cannot parse config. Services/timers will be DISABLED."
        V="false"
    fi

    if [ "$V" = "true" ] || [ "$V" = "1" ]; then
        LOXDATEN_ENABLED=1
        log_info "Config: LOXONE.LoxDaten = true – services/timers will be ENABLED."
    else
        LOXDATEN_ENABLED=0
        log_info "Config: LOXONE.LoxDaten != true – services/timers will be DISABLED."
    fi

    return 0
}

install_unit_file() {
    SRC="$1"
    DEST="$2"
    NAME="$3"

    if [ "$SYSTEMD_OK" -ne 1 ]; then
        return 0
    fi

    if [ ! -f "$SRC" ]; then
        log_warning "$NAME file not found at $SRC – skipping."
        return 0
    fi

    log_info "Installing $NAME..."
    if cp "$SRC" "$DEST"; then
        chmod 0644 "$DEST" 2>/dev/null || true
        log_ok "$NAME installed: $DEST"
        INSTALL_ANY_UNIT=1
    else
        log_warning "Could not install $NAME from $SRC to $DEST"
    fi

    return 0
}

install_systemd_units() {
    install_unit_file "$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/systemd/sonos_check_on_state.service" "/etc/systemd/system/sonos_check_on_state.service" "sonos_check_on_state.service"
    install_unit_file "$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/systemd/sonos_check_on_state.timer" "/etc/systemd/system/sonos_check_on_state.timer" "sonos_check_on_state.timer"
    install_unit_file "$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/systemd/sonos_event_listener.service" "/etc/systemd/system/sonos_event_listener.service" "sonos_event_listener.service"
    install_unit_file "$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/systemd/sonos_watchdog.service" "/etc/systemd/system/sonos_watchdog.service" "sonos_watchdog.service"
    install_unit_file "$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/systemd/sonos_watchdog.timer" "/etc/systemd/system/sonos_watchdog.timer" "sonos_watchdog.timer"
    return 0
}

protect_check_on_state_execstart() {
    SERVICE_DEST="/etc/systemd/system/sonos_check_on_state.service"
    PHP="/usr/bin/php"
    TARGET="$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/Runtime/CheckState.php"

    if [ "$SYSTEMD_OK" -ne 1 ] || [ ! -f "$SERVICE_DEST" ]; then
        return 0
    fi

    if grep -Eq '^ExecStart=.*/(check_on_state\.php|CheckState\.php)' "$SERVICE_DEST"; then
        if grep -q "^ExecStart=$PHP " "$SERVICE_DEST"; then
            log_info "Protection: sonos_check_on_state.service already uses $PHP."
        else
            log_info "Protection: Patching ExecStart to use $PHP."
            sed -i "s|^ExecStart=.*$|ExecStart=$PHP $TARGET|" "$SERVICE_DEST"
            INSTALL_ANY_UNIT=1
            log_ok "Protection: ExecStart patched in sonos_check_on_state.service."
        fi
    fi

    return 0
}

protect_exec_bits() {
    for f in \
        "$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/Runtime/CheckState.php" \
        "$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/Runtime/Watchdog.php" \
        "$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/Event/EventHandler.php"
    do
        if [ -f "$f" ]; then
            chmod +x "$f" 2>/dev/null || true
        fi
    done
    return 0
}

remove_sonos_on_check_cronfiles() {
    CRONROOT="$LBHOME/system/cron"

    log_info "Cron cleanup: Searching for 'Sonos_On_check' under: $CRONROOT"

    if [ ! -d "$CRONROOT" ]; then
        log_info "Cron cleanup: Folder not found – skipping: $CRONROOT"
        return 0
    fi

    find "$CRONROOT" -type f -name 'Sonos_On_check' 2>/dev/null | while IFS= read -r f; do
        [ -n "$f" ] || continue
        if rm -f "$f" 2>/dev/null; then
            log_ok "Cron cleanup: Removed $f"
        else
            log_warning "Cron cleanup: Could not remove $f"
        fi
    done

    return 0
}

apply_systemd_state() {
    if [ "$SYSTEMD_OK" -ne 1 ]; then
        return 0
    fi

    if [ "$INSTALL_ANY_UNIT" -eq 1 ]; then
        if systemctl daemon-reload >/dev/null 2>&1; then
            log_info "systemd daemon reloaded."
        else
            log_warning "Failed to reload systemd daemon."
        fi
    fi

    for unit in sonos_check_on_state.timer sonos_event_listener.service sonos_watchdog.timer; do
        if [ ! -f "/etc/systemd/system/$unit" ]; then
            continue
        fi

        if [ "$LOXDATEN_ENABLED" -eq 1 ]; then
            if systemctl enable --now "$unit" >/dev/null 2>&1; then
                log_ok "$unit has been enabled and started."
            else
                log_warning "Failed to enable/start $unit. Please check 'systemctl status $unit'."
            fi
        else
            systemctl disable --now "$unit" >/dev/null 2>&1 || true
            log_ok "$unit has been disabled and stopped."
        fi
    done

    return 0
}

normalize_config_permissions
install_or_validate_piper
install_systemd_units
protect_check_on_state_execstart
protect_exec_bits
remove_sonos_on_check_cronfiles
detect_loxdaten_enabled
apply_systemd_state

exit 0
