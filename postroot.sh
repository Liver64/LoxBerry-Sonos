#!/bin/sh
# Will be executed as user "root".

PIPER_TARGET_DIR="$5/bin/plugins/sonos4lox"
PIPER_BINARY="$PIPER_TARGET_DIR/piper/piper"

# ---------------------------------------------------------
# Piper Installation
# ---------------------------------------------------------

if [ ! -e "$PIPER_BINARY" ]; then

    echo "<INFO> Piper TTS binary not found at $PIPER_BINARY – starting installation."
    mkdir -p "$PIPER_TARGET_DIR"

    TARBALL=""
    URL=""

    # Architektur-Erkennung
    if [ -e "$LBSCONFIG/is_arch_armv7l.cfg" ]; then
        echo "<INFO> Detected Raspberry Pi (32-bit, armv7l)"
        TARBALL="piper_linux_armv7l.tar.gz"
        URL="https://github.com/rhasspy/piper/releases/download/2023.11.14-2/$TARBALL"

    elif [ -e "$LBSCONFIG/is_arch_aarch64.cfg" ]; then
        echo "<INFO> Detected Raspberry Pi / ARM device (64-bit, aarch64)"
        TARBALL="piper_linux_aarch64.tar.gz"
        URL="https://github.com/rhasspy/piper/releases/download/2023.11.14-2/$TARBALL"

    elif [ -e "$LBSCONFIG/is_raspberry.cfg" ]; then
        # Fallback: Raspberry, aber kein expliziter Arch-Marker
        echo "<INFO> Detected Raspberry Pi (no explicit arch marker) – assuming 64-bit aarch64"
        TARBALL="piper_linux_aarch64.tar.gz"
        URL="https://github.com/rhasspy/piper/releases/download/2023.11.14-2/$TARBALL"

    elif [ -e "$LBSCONFIG/is_x86.cfg" ] || [ -e "$LBSCONFIG/is_x64.cfg" ]; then
        echo "<INFO> Detected x86_64 system"
        TARBALL="piper_linux_x86_64.tar.gz"
        URL="https://github.com/rhasspy/piper/releases/download/2023.11.14-2/$TARBALL"

    else
        echo "<WARNING> Unknown hardware/architecture – skipping automatic Piper installation."
    fi

    if [ -n "$URL" ] && [ -n "$TARBALL" ]; then
        echo "<INFO> Downloading Piper from $URL"
        if wget -O "$PIPER_TARGET_DIR/$TARBALL" "$URL"; then
            cd "$PIPER_TARGET_DIR" || {
                echo "<ERROR> Cannot change directory to $PIPER_TARGET_DIR"
                exit 1
            }

            if tar -xvzf "$TARBALL"; then
                echo "<OK> Piper archive $TARBALL extracted successfully."
                rm "$TARBALL"
            else
                echo "<ERROR> Failed to extract $TARBALL"
                exit 1
            fi
        else
            echo "<ERROR> Failed to download Piper from $URL"
            exit 1
        fi
    else
        echo "<WARNING> No valid Piper download URL determined – installation skipped."
    fi
else
    echo "<INFO> Piper TTS is already installed at $PIPER_BINARY – nothing to do."
fi

# Make sure binary is executable
if [ -e "$PIPER_BINARY" ]; then
    chmod +x "$PIPER_BINARY"
    # Add to PATH for this script run
    export PATH="$PIPER_TARGET_DIR/piper:$PATH"
else
    echo "<WARNING> Piper binary not found after installation attempt."
fi

# Create /usr/bin/piper symlink for convenience
piper="/usr/bin/piper"
if [ -L "$piper" ]; then
    echo "<INFO> Symlink 'piper' is already available in /usr/bin"
elif [ -x "$PIPER_BINARY" ]; then
    ln -s "$PIPER_BINARY" "$piper"
    echo "<INFO> Symlink 'piper' has been created in /usr/bin"
else
    echo "<WARNING> No executable Piper binary to link in /usr/bin"
fi

# ---------------------------------------------------------
# Sonos Event Listener systemd-Service
# ---------------------------------------------------------
install_sonos_event_listener_service() {
    SERVICE_SRC="REPLACELBHOMEDIR/webfrontend/html/plugins/sonos4lox/bin/cron/sonos_event_listener.service"
    SERVICE_DEST="/etc/systemd/system/sonos_event_listener.service"

    if [ ! -f "$SERVICE_SRC" ]; then
        echo "<WARNING> Sonos Event Listener service file not found at $SERVICE_SRC – skipping service install."
        return 0
    fi

    if ! command -v systemctl >/dev/null 2>&1; then
        echo "<WARNING> systemctl not found – cannot install sonos_event_listener.service."
        return 0
    fi

    echo "<INFO> Installing Sonos Event Listener systemd service…"

    cp "$SERVICE_SRC" "$SERVICE_DEST"
    chmod 644 "$SERVICE_DEST"

    if systemctl daemon-reload >/dev/null 2>&1; then
        echo "<INFO> systemd daemon reloaded."
    else
        echo "<WARNING> Failed to reload systemd daemon (daemon-reload)."
    fi

    if systemctl enable --now sonos_event_listener.service >/dev/null 2>&1; then
        echo "<OK> sonos_event_listener.service has been enabled and started."
    else
        echo "<WARNING> Failed to enable/start sonos_event_listener.service. Please check 'systemctl status sonos_event_listener.service'."
    fi
}

# ===== Sonos Event Listener systemd-Service installieren =====
install_sonos_event_listener_service

exit 0
