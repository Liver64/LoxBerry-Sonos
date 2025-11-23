#!/bin/sh
# postroot.sh — finalize Sonos4Lox Piper installation (root)

# /bin/sh (dash) kann kein -o pipefail, daher nur -eu
set -eu

# Piper Release-Version (bei Bedarf einfach anpassen)
PIPER_VERSION="2023.11.14-2"
PIPER_BASE_URL="${PIPER_BASE_URL:-https://github.com/rhasspy/piper/releases/download/$PIPER_VERSION}"

INST=false

install_piper() {
    piper_root="/usr/local/bin/piper"
    piper_bin="$piper_root/piper"
    expected_arch=""
    url=""
    archive=""

    # ---- Architektur ermitteln (uname) ----
    uname_arch="$(uname -m || echo unknown)"

    case "$uname_arch" in
        x86_64)
            expected_arch="x86_64"
            echo "<INFO> Piper install: Detected x86_64 via uname."
            ;;
        aarch64|arm64)
            expected_arch="aarch64"
            echo "<INFO> Piper install: Detected aarch64 via uname."
            ;;
        armv7l|armv7)
            expected_arch="armv7l"
            echo "<INFO> Piper install: Detected armv7l via uname."
            ;;
        *)
            echo "<WARNING> Piper install: Unsupported uname architecture '$uname_arch'."
            # Wir versuchen später optional einen generischen Namen
            expected_arch=""
            ;;
    esac

    # ---- Bereits existierendes Piper prüfen ----
    if [ -x "$piper_bin" ]; then
        file_out="$(file -b "$piper_bin" 2>/dev/null || echo "")"
        current_arch="unknown"

        echo "$file_out" | grep -qi "x86-64" && current_arch="x86_64"
        echo "$file_out" | grep -qi "aarch64" && current_arch="aarch64"
        echo "$file_out" | grep -qi "ARM" && current_arch="armv7l"

        if [ -n "$expected_arch" ] && [ "$current_arch" = "$expected_arch" ]; then
            echo "<OK> Piper binary already present with matching architecture ($expected_arch) – nothing to do."
            return 0
        elif [ "$current_arch" != "unknown" ]; then
            echo "<WARNING> Piper binary architecture mismatch (have: $current_arch, need: ${expected_arch:-unknown}) – reinstalling Piper."
            rm -rf "$piper_root"
        else
            echo "<INFO> Piper binary present but architecture could not be detected – reinstalling as precaution."
            rm -rf "$piper_root"
        fi
    fi

    # ---- Download-Archiv anhand der erwarteten Architektur setzen ----
    case "$expected_arch" in
        aarch64)
            archive="piper_linux_aarch64.tar.gz"
            ;;
        x86_64)
            archive="piper_linux_x86_64.tar.gz"
            ;;
        armv7l)
            archive="piper_linux_armv7l.tar.gz"
            ;;
        "")
            # Fallback: wir versuchen einen generischen Namen aus uname_arch
            archive="piper_linux_${uname_arch}.tar.gz"
            echo "<WARNING> Piper install: No known mapping for '$uname_arch'. Trying generic archive name '$archive'."
            ;;
        *)
            echo "<WARNING> Piper install: No download mapping for arch '$expected_arch'. Skipping automatic Piper install."
            return 0
            ;;
    esac

    url="$PIPER_BASE_URL/$archive"

    echo "<INFO> Piper install: Downloading '$archive' for architecture '${expected_arch:-$uname_arch}' from:"
    echo "<INFO>   $url"
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

# ---- Symlink /usr/bin/piper anlegen ----
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
