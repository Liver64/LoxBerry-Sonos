#!/bin/sh
# postroot.sh — finalize T2S installation (root)

# /bin/sh (dash) kann kein -o pipefail, daher nur -eu
set -eu

INST=false

install_piper() {
    # LBSCONFIG absichern (System-Config-Verzeichnis)
    LBSCONFIG_LOCAL="${LBSCONFIG:-/opt/loxberry/config/system}"
    piper_root="/usr/local/bin/piper"
    piper_bin="$piper_root/piper"
    expected_arch=""
    url=""
    archive=""

    # ---- Architektur ermitteln ----
    if [ -e "$LBSCONFIG_LOCAL/is_raspberry.cfg" ]; then
        expected_arch="aarch64"
        echo "<INFO> Piper install: Detected Raspberry platform (aarch64)."
    elif [ -e "$LBSCONFIG_LOCAL/is_x64.cfg" ]; then
        expected_arch="x86_64"
        echo "<INFO> Piper install: Detected x64 platform (x86_64)."
    else
        # Fallback über uname -m
        uname_arch="$(uname -m)"
        case "$uname_arch" in
            x86_64)
                expected_arch="x86_64"
                echo "<INFO> Piper install: Fallback detected x86_64 via uname."
                ;;
            aarch64|arm64)
                expected_arch="aarch64"
                echo "<INFO> Piper install: Fallback detected aarch64 via uname."
                ;;
            *)
                echo "<WARNING> Piper install: Unsupported architecture '$uname_arch' – skipping automatic Piper install."
                return 0
                ;;
        esac
    fi

    # ---- Bereits existierendes Piper prüfen ----
    if [ -x "$piper_bin" ]; then
        file_out="$(file -b "$piper_bin" 2>/dev/null || echo "")"
        current_arch="unknown"

        echo "$file_out" | grep -qi "x86-64" && current_arch="x86_64"
        echo "$file_out" | grep -qi "aarch64" && current_arch="aarch64"

        if [ "$current_arch" = "$expected_arch" ]; then
            echo "<OK> Piper binary already present with matching architecture ($expected_arch) – nothing to do."
            return 0
        else
            echo "<WARNING> Piper binary architecture mismatch (have: $current_arch, need: $expected_arch) – reinstalling Piper."
            rm -rf "$piper_root"
        fi
    fi

    # ---- Download-URL anhand der erwarteten Architektur setzen ----
    case "$expected_arch" in
        aarch64)
            url="https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_aarch64.tar.gz"
            archive="piper_linux_aarch64.tar.gz"
            ;;
        x86_64)
            # Korrigierter x64-Buildname
            url="https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_x86_64.tar.gz"
            archive="piper_linux_x86_64.tar.gz"
            ;;
        *)
            echo "<WARNING> Piper install: No download mapping for arch '$expected_arch'."
            return 0
            ;;
    esac

    echo "<INFO> Piper install: Downloading Piper for architecture $expected_arch ..."
    mkdir -p /usr/local/bin
    cd /usr/local/bin

    if wget -q "$url" -O "$archive"; then
        tar -xzf "$archive"
        rm -f "$archive"

        if [ -x "$piper_bin" ]; then
            echo "<OK> Piper successfully installed at $piper_bin"
            INST=true
        else
            echo "<ERROR> Piper archive extracted, but '$piper_bin' not found or not executable."
        fi
    else
        echo "<ERROR> Piper download failed from $url"
    fi
}

# ===== Aufruf gleich zu Beginn von postroot.sh =====
install_piper

sym="/usr/bin/piper"
if [ ! -L "$sym" ]; then
    if [ -x /usr/local/bin/piper/piper ]; then
        chmod +x /usr/local/bin/piper/piper
        PATH=/usr/local/bin/piper:$PATH
        export PATH
        ln -s /usr/local/bin/piper/piper "$sym"
        echo "<INFO> Symlink 'piper' has been created in /usr/bin"
    else
        echo "<WARNING> Piper binary not found at /usr/local/bin/piper/piper – cannot create symlink."
    fi
fi

exit 0
