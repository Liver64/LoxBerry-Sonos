#!/bin/sh
# postroot.sh — finalize Sonos4Lox Piper installation (root)

# /bin/sh (dash) kann kein -o pipefail, daher nur -eu
set -eu

# Piper Release-Version (bei Bedarf anpassbar)
PIPER_VERSION="2023.11.14-2"
PIPER_RELEASE_BASE="${PIPER_BASE_URL:-https://github.com/rhasspy/piper/releases/download/$PIPER_VERSION}"

# Globale Pfade/Flags
PIPER_ROOT="/usr/local/bin/piper"
PIPER_BIN="$PIPER_ROOT/piper"
SYM="/usr/bin/piper"
INST="false"

log() {
    # Einfaches Logging, damit die Ausgabe einheitlich ist
    echo "$1"
}

install_piper() {
    piper_root="$PIPER_ROOT"
    piper_bin="$PIPER_BIN"
    expected_arch=""
    archive=""
    url=""

    # ---- Architektur ermitteln (uname) ----
    uname_arch="$(uname -m 2>/dev/null || echo unknown)"

    case "$uname_arch" in
        x86_64)
            expected_arch="x86_64"
            log "<INFO> Piper install: Detected x86_64 via uname."
            ;;
        aarch64|arm64)
            expected_arch="aarch64"
            log "<INFO> Piper install: Detected aarch64 via uname."
            ;;
        armv7l|armv7)
            expected_arch="armv7l"
            log "<INFO> Piper install: Detected armv7l via uname."
            ;;
        *)
            log "<WARNING> Piper install: Unsupported uname architecture '$uname_arch'."
            # Wir versuchen später einen generischen Namen
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
            log "<OK> Piper binary already present with matching architecture ($expected_arch) – nothing to do."
            INST="true"
            return 0
        elif [ "$current_arch" != "unknown" ]; then
            log "<WARNING> Piper binary architecture mismatch (have: $current_arch, need: ${expected_arch:-unknown}) – removing old install."
            rm -rf "$piper_root"
        else
            log "<INFO> Piper binary present but architecture could not be detected – removing old install as precaution."
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
            # Fallback: generischer Name
            archive="piper_linux_${uname_arch}.tar.gz"
            log "<WARNING> Piper install: No known mapping for '$uname_arch'. Trying generic archive name '$archive'."
            ;;
        *)
            log "<ERROR> Piper install: No download mapping for arch '$expected_arch'. Skipping automatic Piper install."
            return 1
            ;;
    esac

    url="$PIPER_RELEASE_BASE/$archive"

    attempts=0
    max_attempts=2

    mkdir -p /usr/local/bin
    cd /usr/local/bin

    while [ "$attempts" -lt "$max_attempts" ]; do
        attempts=$((attempts + 1))
        log "<INFO> Piper install: Download attempt $attempts of $max_attempts..."
        log "<INFO>   URL: $url"

        # Alte Reste entfernen, falls vorher was schiefging
        rm -rf "$piper_root"

        if wget -q "$url" -O "$archive"; then
            if tar -xzf "$archive"; then
                rm -f "$archive"

                if [ -x "$piper_bin" ]; then
                    # Architektur der extrahierten Binary überprüfen
                    file_out="$(file -b "$piper_bin" 2>/dev/null || echo "")"
                    extracted_arch="unknown"
                    echo "$file_out" | grep -qi "x86-64" && extracted_arch="x86_64"
                    echo "$file_out" | grep -qi "aarch64" && extracted_arch="aarch64"
                    echo "$file_out" | grep -qi "ARM" && extracted_arch="armv7l"

                    if [ -n "$expected_arch" ] && [ "$extracted_arch" != "unknown" ] && [ "$extracted_arch" != "$expected_arch" ]; then
                        log "<ERROR> Piper install: Extracted binary architecture '$extracted_arch' does not match expected '$expected_arch'. Removing and retrying."
                        rm -rf "$piper_root"
                        continue
                    fi

                    # Kurzer Runtime-Test (Exec-Format etc. abfangen)
                    if "$piper_bin" --help >/dev/null 2>&1; then
                        log "<OK> Piper successfully installed at $piper_bin"
                        INST="true"
                        break
                    else
                        rc=$?
                        log "<ERROR> Piper test run failed with exit code $rc. Removing broken binary and retrying."
                        rm -rf "$piper_root"
                        continue
                    fi
                else
                    log "<ERROR> Piper archive extracted, but '$piper_bin' not found or not executable."
                    rm -rf "$piper_root"
                fi
            else
                log "<ERROR> Piper install: Failed to extract archive '$archive'."
                rm -f "$archive"
                rm -rf "$piper_root"
            fi
        else
            log "<ERROR> Piper download failed from $url"
            rm -f "$archive" 2>/dev/null || true
        fi
    done

    if [ "$INST" != "true" ]; then
        log "<ERROR> Piper installation failed after $max_attempts attempts. Please install Piper manually or check architecture mapping."
        return 1
    fi

    return 0
}

# ===== Aufruf gleich zu Beginn von postroot.sh =====
install_piper

# ---- Symlink /usr/bin/piper anlegen ----
if [ -x "$PIPER_BIN" ]; then
    chmod +x "$PIPER_BIN"
    # vorhandenen Symlink/Datei ggf. entfernen
    if [ -L "$SYM" ] || [ -e "$SYM" ]; then
        rm -f "$SYM"
    fi
    ln -s "$PIPER_BIN" "$SYM"
    log "<INFO> Symlink 'piper' has been created in /usr/bin"
else
    log "<WARNING> Piper binary not found at $PIPER_BIN – cannot create symlink."
fi

exit 0
