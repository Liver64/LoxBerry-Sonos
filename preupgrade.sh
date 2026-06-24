#!/bin/sh

# Sonos4Lox preupgrade.sh
# Version: PREUPGRADE_ROBUST_BACKUP_V01_2026_06_15
#
# Executed before an update if the plugin is already installed.
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

abort_install() {
	log_error "$1"
	exit 2
}

copy_dir_contents_required() {
	SRC_DIR=$1
	DST_DIR=$2
	LABEL=$3

	if [ ! -d "$SRC_DIR" ]; then
		abort_install "$LABEL source folder is missing: $SRC_DIR"
	fi

	mkdir -p "$DST_DIR" || abort_install "Could not create $LABEL backup folder: $DST_DIR"

	cp -p -v -r "$SRC_DIR/." "$DST_DIR/"
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

	cp -p -v -r "$SRC_DIR/." "$DST_DIR/"
	RC=$?

	if [ $RC -ne 0 ]; then
		log_warning "$LABEL backup failed from $SRC_DIR to $DST_DIR (exit code $RC)"
		return 1
	fi

	log_ok "$LABEL backup completed: $DST_DIR"
	return 0
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
		cp -p -v "$FILE" "$DST_DIR/" || ERRORS=1
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

if [ -z "$PTEMPDIR" ] || [ -z "$PDIR" ] || [ -z "$LBHOMEDIR" ]; then
	abort_install "Missing required upgrade arguments. PTEMPDIR='$PTEMPDIR' PDIR='$PDIR' LBHOMEDIR='$LBHOMEDIR'"
fi

UPGRADE_DIR="/tmp/${PTEMPDIR}_upgrade"

log_info "Creating temporary folders for upgrading"
rm -rf "$UPGRADE_DIR"
mkdir -p "$UPGRADE_DIR/config" \
	 "$UPGRADE_DIR/log" \
	 "$UPGRADE_DIR/data" \
	 "$UPGRADE_DIR/webfrontend/images" \
	 "$UPGRADE_DIR/templates" || abort_install "Could not create temporary upgrade folders: $UPGRADE_DIR"

log_info "Backing up existing config files"
copy_dir_contents_required "$LBHOMEDIR/config/plugins/$PDIR" "$UPGRADE_DIR/config/$PDIR" "Config"

if [ ! -f "$UPGRADE_DIR/config/$PDIR/s4lox_config.json" ]; then
	log_warning "Config backup does not contain s4lox_config.json: $UPGRADE_DIR/config/$PDIR/s4lox_config.json"
else
	log_ok "Main config file has been backed up."
fi

log_info "Backing up existing Sonos image files"
copy_files_by_pattern_optional "$LBHOMEDIR/webfrontend/html/plugins/$PDIR/images/icon*" "$UPGRADE_DIR/webfrontend/images" "Sonos image"

if [ -d "$LBHOMEDIR/webfrontend/html/plugins/$PDIR/VoiceEngines/piper-voices" ]; then
	log_info "Backing up existing Piper files"
	copy_dir_contents_optional "$LBHOMEDIR/webfrontend/html/plugins/$PDIR/VoiceEngines/piper-voices" "$UPGRADE_DIR/webfrontend/piper-voices" "Piper"
else
	log_info "No Piper voice folder found, skipping Piper backup."
fi

log_info "Backing up existing log files"
copy_dir_contents_optional "$LBHOMEDIR/log/plugins/$PDIR" "$UPGRADE_DIR/log/$PDIR" "Log"

log_info "Backing up existing data and MP3 files"
copy_dir_contents_optional "$LBHOMEDIR/data/plugins/$PDIR" "$UPGRADE_DIR/data/$PDIR" "Data"

log_info "Backing up existing text files"
copy_files_by_pattern_optional "$LBHOMEDIR/templates/plugins/$PDIR/lang/t2s-text_*.*" "$UPGRADE_DIR/templates" "Text"

touch "$UPGRADE_DIR/PREUPGRADE_OK" || abort_install "Could not write preupgrade marker: $UPGRADE_DIR/PREUPGRADE_OK"
log_ok "Preupgrade backup finished successfully."

exit 0
