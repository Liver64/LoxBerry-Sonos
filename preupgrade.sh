#!/bin/sh

# Sonos4Lox preupgrade.sh
# Version: PREUPGRADE_MEMORY_SAFE_V02_2026_06_24
#
# Executed before an update if the plugin is already installed.
# Runs as user "loxberry".
# Exit code 0: success
# Exit code 1: warning, installation continues
# Exit code 2: cancel installation
#
# Memory-safe changes in V02:
# - Keep /tmp upgrade backup small to avoid filling zram-backed /tmp.
# - Move large TTS data and Piper voice files to a persistent upgrade folder
#   instead of copying them to /tmp.
# - Do not copy logs into /tmp during upgrade; logs are not required for restore.

COMMAND=$0
PTEMPDIR=$1
PSHNAME=$2
PDIR=$3
PVERSION=$4
LBHOMEDIR=$5

PCGI=$LBPCGI/$PDIR
PHTML=$LBPHTML/$PDIR
PTEMPL=$LBPTEMPL/$PDIR
PDATA=$LBPDATA/$PDIR
PLOG=$LBPLOG/$PDIR
PCONFIG=$LBPCONFIG/$PDIR
PSBIN=$LBPSBIN/$PDIR
PBIN=$LBPBIN/$PDIR

log_info() { echo "<INFO> $1"; }
log_ok() { echo "<OK> $1"; }
log_warning() { echo "<WARNING> $1"; }
log_error() { echo "<ERROR> $1"; }

abort_install() {
	log_error "$1"
	exit 2
}

log_space_status() {
	LABEL=$1
	log_info "Space status ($LABEL)"
	if command -v df >/dev/null 2>&1; then
		df -h /tmp "$LBHOMEDIR" 2>/dev/null | sed 's/^/<INFO>   /'
	fi
	if command -v zramctl >/dev/null 2>&1; then
		zramctl 2>/dev/null | sed 's/^/<INFO>   /'
	elif [ -f /proc/swaps ]; then
		grep zram /proc/swaps 2>/dev/null | sed 's/^/<INFO>   /'
	fi
}

log_dir_size() {
	LABEL=$1
	DIR=$2
	if [ -d "$DIR" ] && command -v du >/dev/null 2>&1; then
		SIZE=$(du -sh "$DIR" 2>/dev/null | awk '{print $1}')
		[ -n "$SIZE" ] && log_info "$LABEL size: $SIZE ($DIR)"
	fi
}

copy_dir_contents_required() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3

	if [ ! -d "$SRC_DIR" ]; then
		abort_install "$LABEL source folder is missing: $SRC_DIR"
	fi

	mkdir -p "$DST_DIR" || abort_install "Could not create $LABEL backup folder: $DST_DIR"

	cp -p -r "$SRC_DIR/." "$DST_DIR/"
	RC=$?

	if [ $RC -ne 0 ]; then
		abort_install "$LABEL backup failed from $SRC_DIR to $DST_DIR (exit code $RC)"
	fi

	log_ok "$LABEL backup completed: $DST_DIR"
}

copy_dir_contents_optional() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3

	if [ ! -d "$SRC_DIR" ]; then
		log_warning "$LABEL source folder does not exist, skipping: $SRC_DIR"
		return 0
	fi

	mkdir -p "$DST_DIR" || {
		log_warning "Could not create $LABEL backup folder, skipping: $DST_DIR"
		return 1
	}

	cp -p -r "$SRC_DIR/." "$DST_DIR/"
	RC=$?

	if [ $RC -ne 0 ]; then
		log_warning "$LABEL backup failed from $SRC_DIR to $DST_DIR (exit code $RC)"
		return 1
	fi

	log_ok "$LABEL backup completed: $DST_DIR"
	return 0
}

copy_data_light_optional() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3

	if [ ! -d "$SRC_DIR" ]; then
		log_warning "$LABEL source folder does not exist, skipping: $SRC_DIR"
		return 0
	fi

	mkdir -p "$DST_DIR" || {
		log_warning "Could not create $LABEL backup folder, skipping: $DST_DIR"
		return 1
	}

	# Keep the /tmp backup small. Large/generated data is handled by move_large_dir_to_persistent_backup().
	# Exclusions intentionally cover common TTS/audio/model/cache payloads.
	if tar -cf - \
		-C "$SRC_DIR" \
		--exclude='./tts' \
		--exclude='./tts/*' \
		--exclude='./.upgrade*' \
		--exclude='*.mp3' \
		--exclude='*.wav' \
		--exclude='*.flac' \
		--exclude='*.ogg' \
		--exclude='*.onnx' \
		--exclude='*.onnx.json' \
		. | tar -xf - -C "$DST_DIR"; then
		log_ok "$LABEL light backup completed: $DST_DIR"
		return 0
	fi

	log_warning "$LABEL light backup had copy errors. Installation continues."
	return 1
}

copy_files_by_pattern_optional() {
	SRC_PATTERN=$1
	DST_DIR=$2
	LABEL=$3
	FOUND=0
	ERRORS=0

	mkdir -p "$DST_DIR" || {
		log_warning "Could not create $LABEL backup folder, skipping: $DST_DIR"
		return 1
	}

	for FILE in $SRC_PATTERN; do
		if [ ! -e "$FILE" ]; then
			continue
		fi
		FOUND=1
		cp -p "$FILE" "$DST_DIR/" || ERRORS=1
	done

	if [ $FOUND -eq 0 ]; then
		log_warning "$LABEL files not found, skipping: $SRC_PATTERN"
		return 0
	fi

	if [ $ERRORS -ne 0 ]; then
		log_warning "$LABEL backup had copy errors."
		return 1
	fi

	log_ok "$LABEL backup completed: $DST_DIR"
	return 0
}

move_large_dir_to_persistent_backup() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3

	if [ ! -d "$SRC_DIR" ]; then
		log_info "$LABEL folder not found, skipping persistent move: $SRC_DIR"
		return 0
	fi

	log_dir_size "$LABEL" "$SRC_DIR"
	mkdir -p "$(dirname "$DST_DIR")" || {
		log_warning "Could not create persistent $LABEL backup parent, leaving source unchanged: $(dirname "$DST_DIR")"
		return 1
	}

	rm -rf "$DST_DIR"
	if mv "$SRC_DIR" "$DST_DIR"; then
		log_ok "$LABEL moved to persistent upgrade backup: $DST_DIR"
		return 0
	fi

	log_warning "$LABEL could not be moved to persistent upgrade backup; leaving normal installation flow unchanged."
	return 1
}

if [ -z "$PTEMPDIR" ] || [ -z "$PDIR" ] || [ -z "$LBHOMEDIR" ]; then
	abort_install "Missing required upgrade arguments. PTEMPDIR='$PTEMPDIR' PDIR='$PDIR' LBHOMEDIR='$LBHOMEDIR'"
fi

UPGRADE_DIR="/tmp/${PTEMPDIR}_upgrade"
PERSISTENT_UPGRADE_DIR="$LBHOMEDIR/data/plugins/${PDIR}_upgrade_${PTEMPDIR}"

log_space_status "before preupgrade backup"
log_dir_size "Config" "$LBHOMEDIR/config/plugins/$PDIR"
log_dir_size "Data" "$LBHOMEDIR/data/plugins/$PDIR"
log_dir_size "Piper voices" "$LBHOMEDIR/webfrontend/html/plugins/$PDIR/VoiceEngines/piper-voices"

log_info "Creating temporary folders for upgrading"
rm -rf "$UPGRADE_DIR"
rm -rf "$PERSISTENT_UPGRADE_DIR"
mkdir -p "$UPGRADE_DIR/config" \
	 "$UPGRADE_DIR/data" \
	 "$UPGRADE_DIR/webfrontend/images" \
	 "$UPGRADE_DIR/templates" || abort_install "Could not create temporary upgrade folders: $UPGRADE_DIR"
mkdir -p "$PERSISTENT_UPGRADE_DIR" || abort_install "Could not create persistent upgrade folder: $PERSISTENT_UPGRADE_DIR"

log_info "Backing up existing config files"
copy_dir_contents_required "$LBHOMEDIR/config/plugins/$PDIR" "$UPGRADE_DIR/config/$PDIR" "Config"

if [ ! -f "$UPGRADE_DIR/config/$PDIR/s4lox_config.json" ]; then
	log_warning "Config backup does not contain s4lox_config.json: $UPGRADE_DIR/config/$PDIR/s4lox_config.json"
else
	log_ok "Main config file has been backed up."
fi

log_info "Backing up existing Sonos image files"
copy_files_by_pattern_optional "$LBHOMEDIR/webfrontend/html/plugins/$PDIR/images/icon*" "$UPGRADE_DIR/webfrontend/images" "Sonos image"

log_info "Moving large Piper voice files out of the plugin folder without copying them to /tmp"
move_large_dir_to_persistent_backup \
	"$LBHOMEDIR/webfrontend/html/plugins/$PDIR/VoiceEngines/piper-voices" \
	"$PERSISTENT_UPGRADE_DIR/piper-voices" \
	"Piper voices"

log_info "Moving large/generated TTS data out of the plugin data folder without copying it to /tmp"
move_large_dir_to_persistent_backup \
	"$LBHOMEDIR/data/plugins/$PDIR/tts" \
	"$PERSISTENT_UPGRADE_DIR/data_tts" \
	"TTS data"

log_info "Backing up existing plugin data without large/generated audio/model files"
copy_data_light_optional "$LBHOMEDIR/data/plugins/$PDIR" "$UPGRADE_DIR/data/$PDIR" "Data"

log_info "Skipping log backup to keep zram/tmp usage low. Existing logs are not required for plugin restore."

log_info "Backing up existing text files"
copy_files_by_pattern_optional "$LBHOMEDIR/templates/plugins/$PDIR/lang/t2s-text_*.*" "$UPGRADE_DIR/templates" "Text"

touch "$UPGRADE_DIR/PREUPGRADE_OK" || abort_install "Could not write preupgrade marker: $UPGRADE_DIR/PREUPGRADE_OK"
log_ok "Preupgrade backup finished successfully. Persistent backup folder: $PERSISTENT_UPGRADE_DIR"
log_space_status "after preupgrade backup"

exit 0
