#!/bin/sh
# Sonos4Lox postroot.sh
# Version: POSTROOT_PIPER_ONLINE_GUARD_V05_2026_11_04
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
# Restore the complete Piper runtime staged by preroot.sh.
# The staging path is outside the plugin folder deleted on upgrade.
# ---------------------------------------------------------
restore_staged_piper() {
    case "$PTEMPDIR" in
        ""|*[!A-Za-z0-9_-]*)
            log_error "Unsafe or missing installer token for Piper restore: $PTEMPDIR"
            return 1
            ;;
    esac
    case "$PDIR" in
        ""|*[!A-Za-z0-9_-]*)
            log_error "Unsafe plugin directory name for Piper restore: $PDIR"
            return 1
            ;;
    esac

    PIPER_STAGE="$LBHOME/bin/plugins/.${PDIR}_piper_upgrade_${PTEMPDIR}"
    PIPER_PARENT="$LBHOME/bin/plugins/$PDIR"
    PIPER_RESTORE_DIR="$PIPER_PARENT/piper"

    if [ -L "$PIPER_STAGE" ]; then
        log_error "Piper staging path is a symlink; refusing restore: $PIPER_STAGE"
        return 1
    fi
    if [ ! -d "$PIPER_STAGE" ]; then
        log_info "No staged Piper runtime; a fresh installation may be needed."
        return 0
    fi

    if ! mkdir -p "$PIPER_PARENT"; then
        log_error "Could not create Piper destination parent: $PIPER_PARENT"
        return 1
    fi
    if [ -d "$PIPER_RESTORE_DIR" ] && [ ! -L "$PIPER_RESTORE_DIR" ]; then
        if [ -z "$(ls -A "$PIPER_RESTORE_DIR")" ]; then
            rmdir "$PIPER_RESTORE_DIR" || return 1
        fi
    fi
    if [ -e "$PIPER_RESTORE_DIR" ] || [ -L "$PIPER_RESTORE_DIR" ]; then
        log_error "Piper destination is not empty; leaving staged backup intact: $PIPER_STAGE"
        return 1
    fi
    if mv "$PIPER_STAGE" "$PIPER_RESTORE_DIR"; then
        log_ok "Piper runtime restored after plugin replacement: $PIPER_RESTORE_DIR"
        return 0
    fi
    log_error "Piper runtime restore failed; staged backup remains: $PIPER_STAGE"
    return 1
}
# ---------------------------------------------------------
# Restore online markers preserved by preroot.sh before the first online check.
# A failed restoration keeps the backup and prevents unintended autoplay.
# ---------------------------------------------------------
restore_staged_playerstatus() {
    case "$PTEMPDIR" in
        ""|*[!A-Za-z0-9_-]*)
            log_error "Unsafe installer token for PlayerStatus restore."
            return 1
            ;;
    esac
    case "$PDIR" in
        ""|*[!A-Za-z0-9_-]*)
            log_error "Unsafe plugin directory for PlayerStatus restore."
            return 1
            ;;
    esac
    STATUS_STAGE="$LBHOME/data/plugins/.${PDIR}_playerstatus_upgrade_${PTEMPDIR}"
    STATUS_DIR="$LBHOME/data/plugins/$PDIR/PlayerStatus"
    if [ -L "$STATUS_STAGE" ] || [ -L "$STATUS_DIR" ]; then
        log_error "PlayerStatus backup or destination is a symlink."
        return 1
    fi
    if [ -d "$STATUS_STAGE" ]; then
        if ! mkdir -p "$STATUS_DIR"; then
            log_error "Cannot create PlayerStatus destination: $STATUS_DIR"
            return 1
        fi
        for marker in "$STATUS_STAGE"/s4lox_on_*.txt; do
            [ -f "$marker" ] && [ ! -L "$marker" ] || continue
            target="$STATUS_DIR/$(basename "$marker")"
            if [ -L "$target" ]; then
                log_error "Refusing to overwrite linked PlayerStatus marker: $target"
                return 1
            fi
            if [ ! -e "$target" ]; then
                if ! cp -p "$marker" "$target"; then
                    log_error "Could not restore PlayerStatus marker: $target"
                    return 1
                fi
            fi
        done
        log_ok "PlayerStatus markers restored/preserved from upgrade backup."
    fi
    if [ -d "$STATUS_DIR" ]; then
        if ! chown loxberry:loxberry "$STATUS_DIR" || ! chmod 0755 "$STATUS_DIR"; then
            log_error "Cannot normalize PlayerStatus directory permissions: $STATUS_DIR"
            return 1
        fi
        for marker in "$STATUS_DIR"/s4lox_on_*.txt; do
            [ -e "$marker" ] || [ -L "$marker" ] || continue
            if [ -L "$marker" ] || [ ! -f "$marker" ] ||
               ! chown loxberry:loxberry "$marker" || ! chmod 0644 "$marker"; then
                log_error "Cannot normalize PlayerStatus marker: $marker"
                return 1
            fi
        done
        log_ok "PlayerStatus directory and marker permissions normalized."
    fi
    if [ -d "$STATUS_STAGE" ]; then
        rm -rf -- "$STATUS_STAGE" || {
            log_warning "Could not remove completed PlayerStatus backup: $STATUS_STAGE"
            return 0
        }
    fi
    return 0
}

# Remove only the guard created by this installation; never clear another
# concurrently staged install marker.
resume_online_checks() {
    case "$PTEMPDIR" in
        ""|*[!A-Za-z0-9_-]*) return 1 ;;
    esac
    ONLINE_GUARD_FILE="/run/shm/$PDIR/.online_check_upgrade"
    if [ -L "$ONLINE_GUARD_FILE" ]; then
        log_error "Online-check guard changed to a symlink."
        return 1
    fi
    if [ -f "$ONLINE_GUARD_FILE" ]; then
        GUARD_TOKEN="$(cat "$ONLINE_GUARD_FILE" 2>/dev/null || true)"
        if [ "$GUARD_TOKEN" != "$PTEMPDIR" ]; then
            log_error "Online-check guard belongs to a different installation."
            return 1
        fi
        rm -f "$ONLINE_GUARD_FILE" || return 1
        log_ok "Online-check upgrade guard cleared."
    fi
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

    mkdir -p "$PIPER_DIR" || {
        log_error "Could not create Piper installation folder: $PIPER_DIR"
        return 1
    }
    log_info "Piper installation check – target binary: $PIPER_BINARY"

    NEED_INSTALL=1
    if [ -f "$PIPER_BINARY" ] && [ -x "$PIPER_BINARY" ] && "$PIPER_BINARY" --help >/dev/null 2>&1; then
        log_info "Existing Piper binary was validated; no download needed: $PIPER_BINARY"
        NEED_INSTALL=0
    elif [ -e "$PIPER_BINARY" ]; then
        log_warning "Existing Piper binary is invalid; attempting a clean reinstall."
        if ! rm -f "$PIPER_BINARY"; then
            log_error "Could not remove invalid Piper binary: $PIPER_BINARY"
            return 1
        fi
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
        if [ -L "$PIPER_LINK" ]; then
            CURRENT_TARGET="$(readlink "$PIPER_LINK" 2>/dev/null || true)"
            case "$CURRENT_TARGET" in
                "$PIPER_TARGET_DIR"/*)
                    if rm -f "$PIPER_LINK"; then
                        log_ok "Removed stale plugin-owned Piper symlink: $PIPER_LINK"
                    else
                        log_warning "Could not remove stale Piper symlink: $PIPER_LINK"
                    fi
                    ;;
            esac
        fi
    fi

    # Restore normal ownership after root downloads or restores the runtime.
    # Keep existing executable bits on Piper and its bundled helper binaries.
    if [ -d "$PIPER_DIR" ] && [ ! -L "$PIPER_DIR" ]; then
        if chown -R loxberry:loxberry "$PIPER_DIR" && chown loxberry:loxberry "$PIPER_TARGET_DIR"; then
            log_ok "Piper runtime ownership normalized to loxberry:loxberry."
        else
            log_warning "Could not fully normalize Piper runtime ownership."
        fi
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

# Remember whether the event listener was running before this installation.
LISTENER_WAS_ACTIVE=0
if [ "$SYSTEMD_OK" -eq 1 ] && systemctl is-active --quiet sonos_event_listener.service; then
    LISTENER_WAS_ACTIVE=1
fi

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

# Repair only files generated by Updateplayer.php (and its Piper index helper).
# Avoid recursive chown of the web frontend and user-controlled media folders.
normalize_player_generated_permissions() {
    for file in "$CFG_S4LOX" "$CFG_S4LOX.bak"; do
        if [ -f "$file" ] && [ ! -L "$file" ]; then
            chown loxberry:loxberry "$file" || log_warning "Could not restore owner: $file"
            chmod 0664 "$file" || log_warning "Could not restore permissions: $file"
        fi
    done

    PIPER_LANG_DIR="$LBHOME/webfrontend/html/plugins/$PDIR/VoiceEngines/langfiles"
    if [ -d "$PIPER_LANG_DIR" ] && [ ! -L "$PIPER_LANG_DIR" ]; then
        chown loxberry:loxberry "$PIPER_LANG_DIR" || log_warning "Could not restore owner: $PIPER_LANG_DIR"
        chmod 0755 "$PIPER_LANG_DIR" || log_warning "Could not restore permissions: $PIPER_LANG_DIR"
        for name in piper.json piper_voices.json; do
            file="$PIPER_LANG_DIR/$name"
            if [ -f "$file" ] && [ ! -L "$file" ]; then
                chown loxberry:loxberry "$file" || log_warning "Could not restore owner: $file"
                chmod 0644 "$file" || log_warning "Could not restore permissions: $file"
            fi
        done
    fi

    PIPER_VOICE_DIR="$LBHOME/webfrontend/html/plugins/$PDIR/VoiceEngines/piper-voices"
    PIPER_HESSISCH_JSON="$PIPER_VOICE_DIR/Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx.json"
    if [ -f "$PIPER_HESSISCH_JSON" ] && [ ! -L "$PIPER_HESSISCH_JSON" ]; then
        chown loxberry:loxberry "$PIPER_HESSISCH_JSON" || log_warning "Could not restore owner: $PIPER_HESSISCH_JSON"
        chmod 0644 "$PIPER_HESSISCH_JSON" || log_warning "Could not restore permissions: $PIPER_HESSISCH_JSON"
    fi
}

# Run a PHP installer helper as the unprivileged LoxBerry user.
run_unprivileged_php() {
    HELPER_LABEL="$1"
    HELPER_SCRIPT="$2"
    if [ ! -f "$HELPER_SCRIPT" ]; then
        log_warning "$HELPER_LABEL was not found: $HELPER_SCRIPT"
        return 0
    fi
    if ! command -v runuser >/dev/null 2>&1; then
        log_warning "runuser is not available; skipping $HELPER_LABEL."
        return 0
    fi
    log_info "Running $HELPER_LABEL as loxberry during install/upgrade."
    if runuser -u loxberry -- /usr/bin/php -q "$HELPER_SCRIPT"; then
        log_ok "$HELPER_LABEL completed."
    else
        log_warning "$HELPER_LABEL failed; see the installation log."
    fi
    return 0
}

# Run after all install/upgrade restores are finished. The player update needs
# root privileges; CheckState.php must run with the unprivileged plugin user.
run_player_initialization() {
    PHP_BIN="/usr/bin/php"
    PLAYER_SCRIPT="$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/Runtime/Updateplayer.php"
    STATE_SCRIPT="$LBHOME/webfrontend/html/plugins/$PDIR/src/Core/Runtime/CheckState.php"

    if [ ! -x "$PHP_BIN" ]; then
        log_warning "PHP CLI is missing; skipping Sonos player initialization."
        return 0
    fi
    if [ ! -f "$CFG_S4LOX" ]; then
        log_info "Sonos configuration is not present yet; skipping player initialization."
        return 0
    fi

    if [ -f "$PLAYER_SCRIPT" ]; then
        log_info "Running Updateplayer.php as root during install/upgrade."
        if "$PHP_BIN" -q "$PLAYER_SCRIPT"; then
            log_ok "Updateplayer.php completed."
        else
            log_warning "Updateplayer.php failed; see the installation log."
        fi
        normalize_player_generated_permissions
    else
        log_warning "Updateplayer.php was not found: $PLAYER_SCRIPT"
    fi

    # Preserve the previous upgrade helper order after the root player update.
    run_unprivileged_php "NotificationCheck.php" "$LBHOME/webfrontend/html/plugins/$PDIR/src/Support/NotificationCheck.php"
    run_unprivileged_php "CheckState.php" "$STATE_SCRIPT"
    run_unprivileged_php "TextUpdate.php" "$LBHOME/webfrontend/html/plugins/$PDIR/src/Support/TextUpdate.php"
    return 0
}

# Reload Apache for updated PHP modules without requesting an OS reboot.
reload_apache_if_running() {
    if [ "$SYSTEMD_OK" -eq 1 ] && systemctl is-active --quiet apache2.service; then
        if systemctl reload apache2.service >/dev/null 2>&1; then
            log_ok "Apache configuration reloaded."
        else
            log_warning "Apache reload failed; verify apache2.service."
        fi
    fi
}

# Updated listener PHP code is not picked up by enable --now if already active.
restart_existing_listener() {
    if [ "$SYSTEMD_OK" -eq 1 ] && [ "$LISTENER_WAS_ACTIVE" -eq 1 ] && [ "$LOXDATEN_ENABLED" -eq 1 ]; then
        if systemctl restart sonos_event_listener.service >/dev/null 2>&1; then
            log_ok "Sonos Event Listener restarted with updated code."
        else
            log_warning "Could not restart Sonos Event Listener."
        fi
    fi
}

normalize_config_permissions
if ! restore_staged_piper; then
    log_error "Piper runtime recovery failed. Installation stopped; backup was preserved."
    exit 2
fi
install_or_validate_piper
if ! restore_staged_playerstatus; then
    log_error "PlayerStatus recovery failed. Backup was retained; installation stopped."
    exit 2
fi
install_systemd_units
protect_check_on_state_execstart
protect_exec_bits
remove_sonos_on_check_cronfiles
if ! resume_online_checks; then
    log_error "Could not safely resume online checks; aborting before service activation."
    exit 2
fi
run_player_initialization
detect_loxdaten_enabled
apply_systemd_state
restart_existing_listener
reload_apache_if_running

exit 0
