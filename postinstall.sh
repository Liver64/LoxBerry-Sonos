#!/bin/bash
# Sonos4Lox postinstall.sh
# Version: INSTALL_SCRIPT_ROBUSTNESS_V01_2026_06_15
# Will be executed as user "loxberry".

COMMAND="$0"
PTEMPDIR="$1"
PSHNAME="$2"
PDIR="$3"
PVERSION="$4"
LBHOME="${5:-${LBHOMEDIR:-REPLACELBHOMEDIR}}"

if [ -z "$PDIR" ]; then
    PDIR="sonos4lox"
fi

PCGI="$LBPCGI/$PDIR"
PHTML="$LBPHTML/$PDIR"
PTEMPL="$LBPTEMPL/$PDIR"
PDATA="$LBPDATA/$PDIR"
PLOG="$LBPLOG/$PDIR"
PCONFIG="$LBPCONFIG/$PDIR"
PSBIN="$LBPSBIN/$PDIR"
PBIN="$LBPBIN/$PDIR"

log_info() { echo "<INFO> $*"; }
log_ok() { echo "<OK> $*"; }
log_warning() { echo "<WARNING> $*"; }
log_error() { echo "<ERROR> $*"; }

VOICE_DIR="$LBHOME/webfrontend/html/plugins/$PDIR/VoiceEngines/piper-voices"
UPGRADE_VOICE_BACKUP="/tmp/${PTEMPDIR}_upgrade/webfrontend/piper-voices"
PIPER_INDEX="$LBHOME/webfrontend/html/plugins/$PDIR/src/Support/PiperVoiceIndex.php"

mkdir -p "$VOICE_DIR"

# During upgrades, preupgrade/postupgrade preserves existing voices.
# Do not redownload them in postinstall if an upgrade backup is present.
if [ -d "$UPGRADE_VOICE_BACKUP" ]; then
    log_info "Piper voice backup exists for upgrade; skipping voice download in postinstall."
    exit 0
fi

download_if_missing() {
    local url="$1"
    local target="$2"
    local tmp="${target}.download"

    if [ -s "$target" ]; then
        log_info "Voice file already exists, skipping: $target"
        return 0
    fi

    log_info "Downloading: $url"
    rm -f "$tmp"

    if wget -O "$tmp" "$url"; then
        if [ -s "$tmp" ]; then
            mv -f "$tmp" "$target"
            log_ok "Downloaded: $target"
            return 0
        fi
        rm -f "$tmp"
        log_warning "Downloaded file is empty: $target"
        return 1
    fi

    rm -f "$tmp"
    log_warning "Download failed: $url"
    return 1
}

DOWNLOAD_ERRORS=0

download_if_missing \
    "https://huggingface.co/Thorsten-Voice/Hessisch/resolve/main/Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx" \
    "$VOICE_DIR/Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx" || DOWNLOAD_ERRORS=$((DOWNLOAD_ERRORS + 1))

download_if_missing \
    "https://huggingface.co/Thorsten-Voice/Hessisch/resolve/main/Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx.json" \
    "$VOICE_DIR/Thorsten-Voice_Hessisch_Piper_high-Oct2023.onnx.json" || DOWNLOAD_ERRORS=$((DOWNLOAD_ERRORS + 1))

download_if_missing \
    "https://huggingface.co/rhasspy/piper-voices/resolve/main/de/de_DE/thorsten/low/de_DE-thorsten-low.onnx" \
    "$VOICE_DIR/de_DE-thorsten-low.onnx" || DOWNLOAD_ERRORS=$((DOWNLOAD_ERRORS + 1))

download_if_missing \
    "https://huggingface.co/rhasspy/piper-voices/resolve/main/de/de_DE/thorsten/low/de_DE-thorsten-low.onnx.json" \
    "$VOICE_DIR/de_DE-thorsten-low.onnx.json" || DOWNLOAD_ERRORS=$((DOWNLOAD_ERRORS + 1))

download_if_missing \
    "https://huggingface.co/rhasspy/piper-voices/resolve/main/de/de_DE/thorsten_emotional/medium/de_DE-thorsten_emotional-medium.onnx" \
    "$VOICE_DIR/de_DE-thorsten_emotional-medium.onnx" || DOWNLOAD_ERRORS=$((DOWNLOAD_ERRORS + 1))

download_if_missing \
    "https://huggingface.co/rhasspy/piper-voices/resolve/main/de/de_DE/thorsten_emotional/medium/de_DE-thorsten_emotional-medium.onnx.json" \
    "$VOICE_DIR/de_DE-thorsten_emotional-medium.onnx.json" || DOWNLOAD_ERRORS=$((DOWNLOAD_ERRORS + 1))

if [ -f "$PIPER_INDEX" ]; then
    if /usr/bin/php "$PIPER_INDEX"; then
        log_ok "Piper voice index has been rebuilt."
    else
        log_warning "Piper voice index rebuild returned a warning/error."
    fi
else
    log_warning "Piper voice index script not found: $PIPER_INDEX"
fi

if [ "$DOWNLOAD_ERRORS" -gt 0 ]; then
    log_warning "Piper voice download finished with $DOWNLOAD_ERRORS warning(s). Installation continues."
else
    log_ok "Piper voices are available."
fi

exit 0
