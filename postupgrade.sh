#!/bin/sh

# Sonos4Lox postupgrade.sh
# Version: POSTUPGRADE_ROBUST_RESTORE_V01_2026_06_15
#
# Executed after postinstall if the plugin is already installed.
# Runs as user "loxberry".
# Exit code 0: success
# Exit code 1: warning, installation continues
# Exit code 2: cancel installation

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
	exit 2
}

copy_dir_contents_required() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3

	if [ ! -d "$SRC_DIR" ]; then
		abort_install_keep_backup "$LABEL backup folder is missing: $SRC_DIR"
	fi

	mkdir -p "$DST_DIR" || abort_install_keep_backup "Could not create $LABEL destination folder: $DST_DIR"

	cp -p -v -r "$SRC_DIR/." "$DST_DIR/"
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

	cp -p -v -r "$SRC_DIR/." "$DST_DIR/"
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
		cp -p -v "$FILE" "$DST_DIR/" || ERRORS=1
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

if [ -z "$PTEMPDIR" ] || [ -z "$PDIR" ] || [ -z "$LBHOMEDIR" ]; then
	UPGRADE_DIR="/tmp/${PTEMPDIR}_upgrade"
	abort_install_keep_backup "Missing required upgrade arguments. PTEMPDIR='$PTEMPDIR' PDIR='$PDIR' LBHOMEDIR='$LBHOMEDIR'"
fi

UPGRADE_DIR="/tmp/${PTEMPDIR}_upgrade"

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

log_info "Copy back log files"
copy_dir_contents_optional "$UPGRADE_DIR/log/$PDIR" "$LBHOMEDIR/log/plugins/$PDIR" "Log"

log_info "Copy back MP3 and plugin data files"
copy_dir_contents_optional "$UPGRADE_DIR/data/$PDIR" "$LBHOMEDIR/data/plugins/$PDIR" "Data"

log_info "Copy back text files"
copy_files_from_dir_optional "$UPGRADE_DIR/templates" "$LBHOMEDIR/templates/plugins/$PDIR/lang" "Text"

log_info "Copy back Sonos image files"
copy_files_from_dir_optional "$UPGRADE_DIR/webfrontend/images" "$LBHOMEDIR/webfrontend/html/plugins/$PDIR/images" "Sonos image"

if [ -d "$UPGRADE_DIR/webfrontend/piper-voices" ]; then
	log_info "Copy back Piper files"
	copy_dir_contents_optional "$UPGRADE_DIR/webfrontend/piper-voices" "$LBHOMEDIR/webfrontend/html/plugins/$PDIR/VoiceEngines/piper-voices" "Piper"
else
	log_info "No Piper backup folder found, skipping Piper restore."
fi

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

run_optional_php "Start update player configuration" "REPLACELBPHTMLDIR/src/Core/Runtime/Updateplayer.php"
run_optional_php "Check T2S announcement configuration" "REPLACELBPHTMLDIR/src/Support/NotificationCheck.php"
run_optional_php "Start update player online status" "REPLACELBPHTMLDIR/src/Core/Runtime/CheckState.php"
run_optional_php "Call t2s-text update check" "REPLACELBPHTMLDIR/src/Support/TextUpdate.php"

log_ok "Postupgrade restore finished."

exit 0
