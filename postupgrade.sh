#!/bin/sh

# Sonos4Lox postupgrade.sh
# Version: POSTUPGRADE_MEMORY_SAFE_RESTORE_V02_2026_06_24
#
# Executed after postinstall if the plugin is already installed.
# Runs as user "loxberry".
# Exit code 0: success
# Exit code 1: warning, installation continues
# Exit code 2: cancel installation
#
# Memory-safe changes in V02:
# - Restores the small /tmp backup first.
# - Restores large TTS/Piper payloads from the persistent upgrade folder.
# - Keeps the persistent backup folder if a critical restore step fails.

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

abort_install_keep_backup() {
	log_error "$1"
	log_error "Keeping temporary upgrade backup folder for manual recovery: $UPGRADE_DIR"
	log_error "Keeping persistent upgrade backup folder for manual recovery: $PERSISTENT_UPGRADE_DIR"
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

copy_dir_contents_required() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3

	if [ ! -d "$SRC_DIR" ]; then
		abort_install_keep_backup "$LABEL backup folder is missing: $SRC_DIR"
	fi

	mkdir -p "$DST_DIR" || abort_install_keep_backup "Could not create $LABEL destination folder: $DST_DIR"

	cp -p -r "$SRC_DIR/." "$DST_DIR/"
	RC=$?

	if [ $RC -ne 0 ]; then
		abort_install_keep_backup "$LABEL restore failed from $SRC_DIR to $DST_DIR (exit code $RC)"
	fi

	log_ok "$LABEL restore completed: $DST_DIR"
}

copy_dir_contents_optional() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3

	if [ ! -d "$SRC_DIR" ]; then
		log_warning "$LABEL backup folder does not exist, skipping: $SRC_DIR"
		return 0
	fi

	mkdir -p "$DST_DIR" || {
		log_warning "Could not create $LABEL destination folder, skipping: $DST_DIR"
		return 1
	}

	cp -p -r "$SRC_DIR/." "$DST_DIR/"
	RC=$?

	if [ $RC -ne 0 ]; then
		log_warning "$LABEL restore failed from $SRC_DIR to $DST_DIR (exit code $RC)"
		return 1
	fi

	log_ok "$LABEL restore completed: $DST_DIR"
	return 0
}

copy_files_from_dir_optional() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3
	FOUND=0
	ERRORS=0

	if [ ! -d "$SRC_DIR" ]; then
		log_warning "$LABEL backup folder does not exist, skipping: $SRC_DIR"
		return 0
	fi

	mkdir -p "$DST_DIR" || {
		log_warning "Could not create $LABEL destination folder, skipping: $DST_DIR"
		return 1
	}

	for FILE in "$SRC_DIR"/*; do
		if [ ! -e "$FILE" ]; then
			continue
		fi
		FOUND=1
		cp -p "$FILE" "$DST_DIR/" || ERRORS=1
	done

	if [ $FOUND -eq 0 ]; then
		log_warning "$LABEL backup folder is empty, skipping: $SRC_DIR"
		return 0
	fi

	if [ $ERRORS -ne 0 ]; then
		log_warning "$LABEL restore had copy errors."
		return 1
	fi

	log_ok "$LABEL restore completed: $DST_DIR"
	return 0
}

run_optional_php() {
	LABEL=$1
	SCRIPT=$2

	if [ ! -f "$SCRIPT" ]; then
		log_warning "$LABEL skipped, script not found: $SCRIPT"
		return 0
	fi

	log_info "$LABEL"
	/usr/bin/php -q "$SCRIPT"
	RC=$?

	if [ $RC -ne 0 ]; then
		log_warning "$LABEL returned exit code $RC"
		return 1
	fi

	log_ok "$LABEL completed."
	return 0
}

restore_persistent_tts_data() {
	SRC_TTS="$PERSISTENT_UPGRADE_DIR/data_tts"
	DST_TTS="$LBHOMEDIR/data/plugins/$PDIR/tts"
	PACKAGE_UPDATE_BACKUP="$PERSISTENT_UPGRADE_DIR/package_mp3_update"

	if [ ! -d "$SRC_TTS" ]; then
		log_info "No persistent TTS data backup found, skipping TTS restore."
		return 0
	fi

	mkdir -p "$LBHOMEDIR/data/plugins/$PDIR" || abort_install_keep_backup "Could not create plugin data folder: $LBHOMEDIR/data/plugins/$PDIR"

	if [ -d "$DST_TTS/mp3/update" ]; then
		log_info "Preserving package MP3 update folder before restoring existing TTS data."
		rm -rf "$PACKAGE_UPDATE_BACKUP"
		mkdir -p "$PACKAGE_UPDATE_BACKUP" || abort_install_keep_backup "Could not create package update backup folder: $PACKAGE_UPDATE_BACKUP"
		cp -p -r "$DST_TTS/mp3/update/." "$PACKAGE_UPDATE_BACKUP/" || abort_install_keep_backup "Could not preserve package MP3 update folder."
	fi

	if [ -d "$DST_TTS" ]; then
		log_info "Removing newly installed TTS folder before moving back the existing TTS data."
		rm -rf "$DST_TTS" || abort_install_keep_backup "Could not remove temporary installed TTS folder: $DST_TTS"
	fi

	if mv "$SRC_TTS" "$DST_TTS"; then
		log_ok "Existing TTS data restored without copying through /tmp: $DST_TTS"
	else
		abort_install_keep_backup "Could not restore persistent TTS data from $SRC_TTS to $DST_TTS"
	fi

	if [ -d "$PACKAGE_UPDATE_BACKUP" ]; then
		log_info "Applying package MP3 update files after TTS restore."
		mkdir -p "$DST_TTS/mp3" || abort_install_keep_backup "Could not create MP3 destination folder: $DST_TTS/mp3"
		copy_files_from_dir_optional "$PACKAGE_UPDATE_BACKUP" "$DST_TTS/mp3" "MP3 update"
		rm -rf "$PACKAGE_UPDATE_BACKUP"
	fi

	return 0
}

restore_persistent_piper_voices() {
	SRC_PIPER="$PERSISTENT_UPGRADE_DIR/piper-voices"
	DST_PIPER="$LBHOMEDIR/webfrontend/html/plugins/$PDIR/VoiceEngines/piper-voices"

	if [ ! -d "$SRC_PIPER" ]; then
		log_info "No persistent Piper voice backup found, skipping Piper voice restore."
		return 0
	fi

	mkdir -p "$DST_PIPER" || abort_install_keep_backup "Could not create Piper voice destination folder: $DST_PIPER"
	copy_dir_contents_optional "$SRC_PIPER" "$DST_PIPER" "Piper"
	rm -rf "$SRC_PIPER"
	log_ok "Piper voice files restored from persistent backup."
	return 0
}

if [ -z "$PTEMPDIR" ] || [ -z "$PDIR" ] || [ -z "$LBHOMEDIR" ]; then
	UPGRADE_DIR="/tmp/${PTEMPDIR}_upgrade"
	PERSISTENT_UPGRADE_DIR="$LBHOMEDIR/data/plugins/${PDIR}_upgrade_${PTEMPDIR}"
	abort_install_keep_backup "Missing required upgrade arguments. PTEMPDIR='$PTEMPDIR' PDIR='$PDIR' LBHOMEDIR='$LBHOMEDIR'"
fi

UPGRADE_DIR="/tmp/${PTEMPDIR}_upgrade"
PERSISTENT_UPGRADE_DIR="$LBHOMEDIR/data/plugins/${PDIR}_upgrade_${PTEMPDIR}"

log_space_status "before postupgrade restore"

if [ ! -d "$UPGRADE_DIR" ]; then
	abort_install_keep_backup "Temporary upgrade backup folder is missing: $UPGRADE_DIR"
fi

if [ ! -f "$UPGRADE_DIR/PREUPGRADE_OK" ]; then
	log_warning "Preupgrade marker is missing: $UPGRADE_DIR/PREUPGRADE_OK"
	log_warning "Continuing restore attempt, but backup may be incomplete."
fi

log_info "Copy back config files"
copy_dir_contents_required "$UPGRADE_DIR/config/$PDIR" "$LBHOMEDIR/config/plugins/$PDIR" "Config"

if [ ! -f "$LBHOMEDIR/config/plugins/$PDIR/s4lox_config.json" ]; then
	abort_install_keep_backup "Restored config does not contain s4lox_config.json: $LBHOMEDIR/config/plugins/$PDIR/s4lox_config.json"
fi

chown -R loxberry:loxberry "$LBHOMEDIR/config/plugins/$PDIR" 2>/dev/null || true
chmod 0755 "$LBHOMEDIR/config/plugins/$PDIR" 2>/dev/null || true
find "$LBHOMEDIR/config/plugins/$PDIR" -type f -exec chmod 0644 {} \; 2>/dev/null || true

log_info "Copy back light plugin data files"
copy_dir_contents_optional "$UPGRADE_DIR/data/$PDIR" "$LBHOMEDIR/data/plugins/$PDIR" "Data"

restore_persistent_tts_data

log_info "Copy back text files"
copy_files_from_dir_optional "$UPGRADE_DIR/templates" "$LBHOMEDIR/templates/plugins/$PDIR/lang" "Text"

log_info "Copy back Sonos image files"
copy_files_from_dir_optional "$UPGRADE_DIR/webfrontend/images" "$LBHOMEDIR/webfrontend/html/plugins/$PDIR/images" "Sonos image"

restore_persistent_piper_voices

if [ -d "$LBHOMEDIR/data/plugins/$PDIR/tts/mp3/update" ]; then
	log_info "Update MP3 files in tts/mp3"
	copy_files_from_dir_optional "$LBHOMEDIR/data/plugins/$PDIR/tts/mp3/update" "$LBHOMEDIR/data/plugins/$PDIR/tts/mp3" "MP3 update"
else
	log_info "No MP3 update folder found, skipping MP3 update copy."
fi

log_info "Remove temporary/update folders"
rm -rf "$UPGRADE_DIR"
if [ -d "$LBHOMEDIR/data/plugins/$PDIR/tts/mp3/update" ]; then
	rm -rf "$LBHOMEDIR/data/plugins/$PDIR/tts/mp3/update"
fi
if [ -d "$PERSISTENT_UPGRADE_DIR" ]; then
	rmdir "$PERSISTENT_UPGRADE_DIR" 2>/dev/null || log_warning "Persistent upgrade folder is not empty and was kept: $PERSISTENT_UPGRADE_DIR"
fi

run_optional_php "Start update player configuration" "REPLACELBPHTMLDIR/src/Core/Runtime/Updateplayer.php"
run_optional_php "Check T2S announcement configuration" "REPLACELBPHTMLDIR/src/Support/NotificationCheck.php"
run_optional_php "Start update player online status" "REPLACELBPHTMLDIR/src/Core/Runtime/CheckState.php"
run_optional_php "Call t2s-text update check" "REPLACELBPHTMLDIR/src/Support/TextUpdate.php"

log_ok "Postupgrade restore finished."
log_space_status "after postupgrade restore"

exit 0
