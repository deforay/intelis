#!/bin/bash
set -Eeuo pipefail

# Sets up automatic off-machine backups of an InteLIS installation.
#
# To use this script:
#   intelis backup setup
#
# On a machine without the intelis command, fetch it directly instead:
#   cd ~
#   wget -O remote-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh
#   chmod u+x remote-backup.sh
#   sudo ./remote-backup.sh
#
# One script covers all three destinations:
#   1. Another Linux machine, over SSH
#   2. A Windows shared folder, over SMB
#   3. A USB or external drive plugged into this machine
#
# One backup machine can take the backups of many labs: each lab gets its own
# folder there, named from a UUID, and its own key on the same account.
#
# Answers are saved to /etc/intelis/backup.conf. Re-running this script while
# the saved destination still works asks one question, keep it or change it;
# when it no longer works, setup runs as it did the first time, with the saved
# answers offered. The backup runner installed at
# /usr/local/bin/intelis-backup.sh reads that file, so nothing is hard-coded
# into the runner and it can be replaced without losing the configuration.

trap 'echo -e "\033[1;91m❌ Error:\033[0m setup failed at line $LINENO (status $?)"' ERR

CONF_DIR="/etc/intelis"
CONF_FILE="${CONF_DIR}/backup.conf"
RUNNER="/usr/local/bin/intelis-backup.sh"
LEGACY_WINDOWS_RUNNER="/usr/local/bin/intelis-backup-windows.sh"
LAB_UUID_FILE="${CONF_DIR}/lab-uuid"
SSH_KEY="/root/.ssh/id_ed25519_intelis"
MOUNT_POINT="/mnt/intelis-backup"
USB_MOUNT="/mnt/intelis-usb"
SMB_CRED_FILE="${CONF_DIR}/smb-backup.cred"

# --- arguments ----------------------------------------------------------------
# Parsed before anything else so --help answers without needing root, and
# --refresh-runner is known before the first question would be asked.
SETUP_ACTION="setup"
case "${1:-}" in
  "") ;;
  --refresh-runner) SETUP_ACTION="refresh-runner" ;;
  --help | -h)
    # The whole header comment, up to the first blank line, so it cannot be
    # cut short again when the header grows.
    sed -n '3,/^$/{/^#/p}' "$0" | sed 's/^# \{0,1\}//'
    exit 0
    ;;
  *)
    echo "Unknown option: $1"
    echo "Try --help"
    exit 2
    ;;
esac

# Checked before anything else: the shared-functions bootstrap below writes
# under /usr/local/lib, so a non-root run has to fail here rather than partway
# through with a permission error nobody can read.
if [ "$(id -u)" -ne 0 ]; then
  echo "Need admin privileges. Run with sudo."
  exit 1
fi

# --- helpers ------------------------------------------------------------------
#
# print, and the gum-aware ask_* prompt layer, come from shared-functions.sh, so
# this script asks its questions exactly the way setup.sh does: menus where the
# answer space is closed, validation that re-asks instead of quietly accepting,
# and gum rendering wherever gum is installed.
#
# INTELIS_TRACK is pinned before sourcing. shared-functions.sh resolves the
# ref labs follow at source time with an untimed `git ls-remote`, and nothing
# in this script upgrades anything, so that lookup is pure latency here — on a
# bad lab link it is an indefinite stall before the first question is asked.
INTELIS_TRACK="${INTELIS_TRACK:-master}"

SHARED_FN_PATH="/usr/local/lib/intelis/shared-functions.sh"
SHARED_FN_URL="https://raw.githubusercontent.com/deforay/intelis/master/scripts/shared-functions.sh"

mkdir -p "$(dirname "$SHARED_FN_PATH")"

if command -v wget >/dev/null 2>&1; then
  download_to() { wget -q -O "$1" "$2"; }
elif command -v curl >/dev/null 2>&1; then
  download_to() { curl -fsSL -o "$1" "$2"; }
else
  download_to() { return 1; }
fi

# Stage the download and swap it in only once it looks like the real thing:
# `wget -O` and `curl -o` both truncate the destination before transferring, so
# a network hiccup leaves a zero-byte file that exists, sources cleanly, and
# defines nothing. Same guard as setup.sh, where that reached a lab.
fetch_shared_fn() {
  local dest="$1" url="$2" tmp
  tmp="$(mktemp "${dest}.XXXXXX" 2>/dev/null)" || return 1
  if download_to "$tmp" "$url" && [ -s "$tmp" ] && grep -q '^ask_choice()' "$tmp"; then
    # mktemp makes the staging file 0600, and mv keeps that. Set it back to a
    # readable mode: this is a library other scripts source, not a secret.
    chmod 0644 "$tmp"
    mv -f "$tmp" "$dest"
    return 0
  fi
  rm -f "$tmp"
  return 1
}

fetch_shared_fn "$SHARED_FN_PATH" "$SHARED_FN_URL" || true

if [ ! -r "$SHARED_FN_PATH" ]; then
  echo "Could not download shared-functions.sh and there is no copy at $SHARED_FN_PATH."
  echo "Fetch it onto this machine, then run this again:"
  echo "  sudo mkdir -p $(dirname "$SHARED_FN_PATH")"
  echo "  sudo wget -O $SHARED_FN_PATH $SHARED_FN_URL"
  exit 1
fi

# shellcheck disable=SC1090
source "$SHARED_FN_PATH"

# Present is not the same as usable — a truncated copy sources without error and
# defines nothing.
if ! declare -F ask_choice >/dev/null 2>&1; then
  echo "shared-functions.sh at $SHARED_FN_PATH is unusable (truncated or corrupt)."
  echo "Delete it and run this again: sudo rm -f $SHARED_FN_PATH"
  exit 1
fi

require_cmd() { command -v "$1" >/dev/null 2>&1 || { print error "Missing dependency: $1"; exit 1; }; }

trim() {
  local s=$1
  s="${s#"${s%%[![:space:]]*}"}"
  s="${s%"${s##*[![:space:]]}"}"
  printf '%s' "$s"
}

no_more_input() {
  print error "Ran out of answers — the input ended before the questions did."
  print info  "Run this directly in a terminal: intelis backup setup"
  exit 1
}

# A destination is being CHOSEN at these prompts, so an unanswered question must
# never resolve itself. ask_text falls back to its default when nobody is there
# to answer, which is right for setup.sh and wrong here: silently picking a
# backup destination nobody named is how backups end up going to the wrong place
# and nobody finds out until a restore. Checked once, up front, rather than
# after each read.
#
# Guarded at the point a question is actually asked rather than once up front,
# so a run that needs no answers is never refused for want of a terminal.

validate_nonempty() {
  [ -n "$(trim "$1")" ] && return 0
  print error "This cannot be left empty."
  return 1
}

# ask <var> <prompt> [default] — kept as the local spelling so every call site
# below reads the same as it always did, now rendered by gum where available.
ask() {
  ui_interactive || no_more_input
  local __var=$1 prompt=$2 default=${3:-}
  ask_text "$__var" "$default" "$prompt" validate_nonempty
  printf -v "$__var" '%s' "$(trim "${!__var}")"
}

# Deliberately not ask_password: that asks twice and compares, which is right
# when a password is being invented and wrong here. These are existing Windows
# and SSH passwords being recalled, and asking twice for one only invites a
# mistyped confirmation of a correct password.
ask_secret() {
  ui_interactive || no_more_input
  local __var=$1 prompt=$2 input
  while true; do
    if [ "$(ui_renderer)" = "gum" ]; then
      input=$(gum input --password --header "$prompt" 2>/dev/null) || input=""
    else
      printf '\n \033[1m%s\033[0m\n > ' "$prompt" >&2
      read -r -s input || no_more_input
      echo >&2
    fi
    [ -n "$input" ] && break
    print warning "This cannot be left empty. Try again."
  done
  printf -v "$__var" '%s' "$input"
}

confirm() { ui_interactive || no_more_input; ask_yes_no "$1" no; }

# choose <var> <default-key> <question> <key:label:description>...
# ask_choice with the same refusal to answer itself: its own fallback to the
# default key is right for setup.sh, where every default is safe, and wrong for
# a script that picks which lab's database gets overwritten.
choose() {
  ui_interactive || no_more_input
  ask_choice "$@"
}

# --- the backup runner -------------------------------------------------------
#
# The runner is a fixed script. Every setting it uses it reads from backup.conf
# at the moment it runs, so writing it needs none of the answers collected
# during setup — which is what lets --refresh-runner rewrite it during an
# upgrade without asking anyone anything.
#
# The body is flush against the margin deliberately. It writes a quoted
# heredoc, and a quoted heredoc ends only where its terminator sits at column
# zero; indenting this function to match the rest of the file stops
# RUNNER_SCRIPT closing it, and bash then reads the rest of the script as
# heredoc content and the file no longer parses at all.
install_backup_runner() {
print header "Installing the backup runner"

# Written aside and renamed into place: bash reads a script as it runs, so
# rewriting the file under a backup that is still running corrupts that run.
cat > "${RUNNER}.new" <<'RUNNER_SCRIPT'
#!/bin/bash
# InteLIS backup runner. Installed by remote-backup.sh; reads its settings from
# /etc/intelis/backup.conf. Safe to run by hand at any time.
set -Eeuo pipefail

CONF_FILE="/etc/intelis/backup.conf"
STATE_DIR="/var/lib/intelis"
STATUS_JSON="${STATE_DIR}/backup-status.json"
STATUS_ENV="${STATE_DIR}/backup-status.env"
LOGFILE="/var/log/intelis-backup.log"
LOCKFILE="/var/lock/intelis-backup.lock"

usage() {
  cat <<USAGE
This is the backup runner. It is normally reached through the intelis command,
which is the form to use and the form the documentation carries:

  intelis backup            Run a backup now
  intelis backup status     Show when the last backup ran and whether it worked
  intelis backup test       Check the connection and report what would be copied
  intelis backup disable    Stop the scheduled backups
  intelis backup enable     Start the scheduled backups again
  intelis backup setup      Choose or change where backups are sent

Running this file directly takes the same actions as options:

  (no option)   Run a backup now
  --status      Show when the last backup ran and whether it worked
  --test        Check the connection and report what would be copied, changing nothing
  --disable     Stop the scheduled backups
  --enable      Start the scheduled backups again
  --help        Show this message
USAGE
}

ACTION="run"
case "${1:-}" in
  "")         ACTION="run" ;;
  --status)   ACTION="status" ;;
  --test)     ACTION="test" ;;
  --disable)  ACTION="disable" ;;
  --enable)   ACTION="enable" ;;
  --help|-h)  usage; exit 0 ;;
  *)          echo "Unknown option: $1"; usage; exit 2 ;;
esac

[ -f "$CONF_FILE" ] || { echo "No backup configuration found at $CONF_FILE. Run remote-backup.sh first."; exit 1; }
# shellcheck disable=SC1090
. "$CONF_FILE"

: "${INSTANCE_NAME:=}"; : "${LAB_UUID:=}"; : "${DEST_FOLDER:=}"; : "${LIS_PATH:=}"
: "${DEST_MODE:=}"; : "${DEST_BASE:=}"; : "${DEST_DIR:=}"
: "${SSH_USER:=}"; : "${SSH_HOST:=}"; : "${SSH_PORT:=22}"; : "${SSH_KEY:=/root/.ssh/id_ed25519_intelis}"
: "${SMB_HOST:=}"; : "${SMB_SHARE:=}"; : "${SMB_VERS:=3.0}"; : "${MOUNT_POINT:=/mnt/intelis-backup}"
: "${LOCAL_ROOT:=}"; : "${LOCAL_UUID:=}"
# Database history kept at the destination: one dump per day for HISTORY_DAYS
# days, then one per week for HISTORY_WEEKS weeks. Not asked at setup; change it
# in backup.conf.
: "${HISTORY_DAYS:=7}"; : "${HISTORY_WEEKS:=4}"

CRON_MARKER="/usr/local/bin/intelis-backup.sh"

# --- scheduling toggles (no lock or logging needed) ---------------------------

case "$ACTION" in
  disable)
    if crontab -l 2>/dev/null | grep -q "$CRON_MARKER"; then
      ( crontab -l 2>/dev/null | grep -v "$CRON_MARKER" || true ) | crontab -
      echo "Scheduled backups stopped. Run 'intelis backup enable' to start them again."
    else
      echo "Scheduled backups were already stopped."
    fi
    # pgrep -f also matches this process, so leave it out; rsync is a child of
    # the running backup and does not carry the marker, so stop it by parent.
    running=$(pgrep -f "$CRON_MARKER" | grep -vx "$$" || true)
    if [ -n "$running" ]; then
      for pid in $running; do pkill -P "$pid" 2>/dev/null || true; kill "$pid" 2>/dev/null || true; done
      echo "Stopped the backup that was running."
    fi
    exit 0
    ;;
  enable)
    ( crontab -l 2>/dev/null | grep -v "$CRON_MARKER" || true ) | crontab -
    ( crontab -l 2>/dev/null; echo "@reboot $CRON_MARKER >/dev/null 2>&1"; echo "0 */8 * * * $CRON_MARKER >/dev/null 2>&1" ) | crontab -
    echo "Scheduled backups started: every 8 hours and after every restart."
    exit 0
    ;;
esac

# --- status readout -----------------------------------------------------------

human_age() {
  local secs=$1
  if   [ "$secs" -lt 3600 ];  then echo "$((secs / 60)) minutes ago"
  elif [ "$secs" -lt 86400 ]; then echo "$((secs / 3600)) hours ago"
  else                             echo "$((secs / 86400)) days ago"; fi
}

if [ "$ACTION" = "status" ]; then
  echo "Lab            : ${INSTANCE_NAME} (${DEST_FOLDER})"
  case "$DEST_MODE" in
    ssh)   echo "Backing up to  : ${SSH_USER}@${SSH_HOST}:${DEST_DIR}" ;;
    smb)   echo "Backing up to  : //${SMB_HOST}/${SMB_SHARE} -> ${DEST_DIR}" ;;
    local) echo "Backing up to  : ${DEST_DIR}" ;;
  esac
  if [ -f "$STATUS_ENV" ]; then
    # shellcheck disable=SC1090
    . "$STATUS_ENV"
    : "${LAST_STATUS:=unknown}"; : "${LAST_SUCCESS_AT:=}"; : "${LAST_SUCCESS_EPOCH:=0}"
    : "${LAST_FAILURE_AT:=}"; : "${LAST_ERROR:=}"; : "${LAST_SIZE:=unknown}"; : "${LAST_DURATION:=0}"
    if [ "${LAST_SUCCESS_EPOCH:-0}" -gt 0 ]; then
      age=$(( $(date +%s) - LAST_SUCCESS_EPOCH ))
      echo "Last good backup: ${LAST_SUCCESS_AT} ($(human_age "$age"))"
      echo "Size on backup  : ${LAST_SIZE}"
      if [ "$age" -gt 86400 ]; then
        echo
        echo "⚠️  The last good backup is more than a day old. Run 'intelis backup test' to find out why."
      fi
    else
      echo "Last good backup: never"
    fi
    if [ "${HISTORY_COUNT:-0}" -gt 0 ]; then
      echo "History         : ${HISTORY_OLDEST} to ${HISTORY_NEWEST} (${HISTORY_COUNT} days)"
    else
      echo "History         : none yet"
    fi
    [ "$LAST_STATUS" = "failed" ] && { echo "Last attempt    : FAILED at ${LAST_FAILURE_AT}"; echo "Reason          : ${LAST_ERROR}"; }
    [ "$LAST_STATUS" = "ok" ]     &&   echo "Last attempt    : succeeded in ${LAST_DURATION}s"
  else
    echo "Last good backup: never (no backup has finished yet)"
  fi
  if crontab -l 2>/dev/null | grep -q "$CRON_MARKER"; then
    echo "Schedule        : every 8 hours and after every restart"
  else
    echo "Schedule        : OFF — backups are not scheduled"
  fi
  exit 0
fi

# --- logging ------------------------------------------------------------------
# Appended, never truncated: the record of a failure must survive the next run.

mkdir -p "$STATE_DIR"
umask 027
touch "$LOGFILE" 2>/dev/null || true
chmod 640 "$LOGFILE" 2>/dev/null || true
exec 1> >(tee -a "$LOGFILE")
exec 2>&1

print() {
  local t=${1:-info}; shift || true
  local m=${1:-};     shift || true
  local ts="[$(date '+%Y-%m-%d %H:%M:%S')]"
  case "$t" in
    error)   printf "%s \033[1;91m❌ Error:\033[0m %s\n" "$ts" "$m" ;;
    success) printf "%s \033[1;92m✅ Success:\033[0m %s\n" "$ts" "$m" ;;
    warning) printf "%s \033[1;93m⚠️ Warning:\033[0m %s\n" "$ts" "$m" ;;
    info)    printf "%s \033[1;96mℹ️ Info:\033[0m %s\n" "$ts" "$m" ;;
    *)       printf "%s %s\n" "$ts" "$m" ;;
  esac
}

# --- status file --------------------------------------------------------------

LAST_STATUS="never"; LAST_SUCCESS_AT=""; LAST_SUCCESS_EPOCH=0
LAST_FAILURE_AT=""; LAST_ERROR=""; LAST_SIZE="unknown"; LAST_DURATION=0
HISTORY_OLDEST=""; HISTORY_NEWEST=""; HISTORY_COUNT=0
if [ -f "$STATUS_ENV" ]; then
  # shellcheck disable=SC1090
  . "$STATUS_ENV" || true
fi

json_escape() { printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g' | tr -d '\n\r'; }

write_status() {
  local st=$1 msg=${2:-} size=${3:-unknown} duration=${4:-0}
  local now epoch
  now="$(date -u +%FT%TZ)"; epoch="$(date +%s)"
  if [ "$st" = "ok" ]; then
    LAST_SUCCESS_AT="$now"; LAST_SUCCESS_EPOCH="$epoch"; LAST_SIZE="$size"; LAST_ERROR=""
  else
    LAST_FAILURE_AT="$now"; LAST_ERROR="$msg"
  fi
  LAST_STATUS="$st"; LAST_DURATION="$duration"

  mkdir -p "$STATE_DIR"
  umask 022
  cat > "$STATUS_ENV" <<STATUS
LAST_STATUS='${LAST_STATUS}'
LAST_RUN_AT='${now}'
LAST_SUCCESS_AT='${LAST_SUCCESS_AT}'
LAST_SUCCESS_EPOCH='${LAST_SUCCESS_EPOCH}'
LAST_FAILURE_AT='${LAST_FAILURE_AT}'
LAST_ERROR='$(printf '%s' "$LAST_ERROR" | tr -d "'" | tr -d '\n\r')'
LAST_SIZE='${LAST_SIZE}'
LAST_DURATION='${LAST_DURATION}'
DB_DUMP_AGE_HOURS='${DB_DUMP_AGE_HOURS:--1}'
HISTORY_OLDEST='${HISTORY_OLDEST}'
HISTORY_NEWEST='${HISTORY_NEWEST}'
HISTORY_COUNT='${HISTORY_COUNT:-0}'
STATUS
  cat > "$STATUS_JSON" <<STATUS
{
  "instance": "$(json_escape "$INSTANCE_NAME")",
  "folder": "$(json_escape "$DEST_FOLDER")",
  "destination": "$(json_escape "$DEST_MODE")",
  "status": "$(json_escape "$LAST_STATUS")",
  "last_run_at": "${now}",
  "last_success_at": "$(json_escape "$LAST_SUCCESS_AT")",
  "last_success_epoch": ${LAST_SUCCESS_EPOCH:-0},
  "last_failure_at": "$(json_escape "$LAST_FAILURE_AT")",
  "last_error": "$(json_escape "$LAST_ERROR")",
  "size": "$(json_escape "$LAST_SIZE")",
  "duration_seconds": ${LAST_DURATION:-0},
  "db_dump_age_hours": ${DB_DUMP_AGE_HOURS:--1},
  "history_oldest": "$(json_escape "$HISTORY_OLDEST")",
  "history_newest": "$(json_escape "$HISTORY_NEWEST")",
  "history_count": ${HISTORY_COUNT:-0}
}
STATUS
  chmod 644 "$STATUS_JSON" "$STATUS_ENV" 2>/dev/null || true
}

fail() {
  trap - ERR
  local msg=$1
  print error "$msg"
  [ "$ACTION" = "run" ] && write_status failed "$msg" "unknown" "${SECONDS:-0}"
  exit 1
}
trap 'fail "backup failed at line $LINENO (status $?)"' ERR

# --- one run at a time --------------------------------------------------------

exec 9>"$LOCKFILE"
if ! flock -n 9; then
  print warning "Another backup is already running. Leaving it to finish."
  exit 0
fi

# --- destination helpers ------------------------------------------------------

SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=15 -o ServerAliveCountMax=4)

dest_exec() {
  case "$DEST_MODE" in
    ssh) ssh -n -i "$SSH_KEY" "${SSH_OPTS[@]}" -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" "$1" ;;
    *)   bash -c "$1" ;;
  esac
}

ensure_destination_available() {
  case "$DEST_MODE" in
    ssh)
      dest_exec "true" >/dev/null 2>&1 || fail "Cannot reach the backup server ${SSH_HOST}. Is it switched on and on the network?"
      ;;
    smb)
      if ! mountpoint -q "$MOUNT_POINT"; then
        print warning "The Windows shared folder is not connected; reconnecting."
        mount "$MOUNT_POINT" >/dev/null 2>&1 || fail "Cannot connect to //${SMB_HOST}/${SMB_SHARE}. Is the Windows machine switched on and on the network?"
      fi
      ;;
    local)
      if [ -n "$LOCAL_UUID" ]; then
        # LOCAL_ROOT is a fixed folder that exists whether or not the drive is
        # mounted on it, so its presence proves nothing: an unmounted one is a
        # folder on this machine's own disk. Only a mount point counts.
        if ! mountpoint -q "$LOCAL_ROOT"; then
          print warning "The backup drive is not connected; connecting it."
          mount "$LOCAL_ROOT" >/dev/null 2>&1 || true
          mountpoint -q "$LOCAL_ROOT" || fail "The backup drive is not plugged in. Plug it in, then run: intelis backup"
        fi
      else
        [ -d "$LOCAL_ROOT" ] || fail "The backup drive at ${LOCAL_ROOT} is not there. Is it plugged in? Run 'intelis backup setup' and choose the drive from the list, so it is connected by itself after a restart."
      fi
      ;;
  esac
}

Q_DEST="$(printf '%q' "$DEST_DIR")"

# --- checks -------------------------------------------------------------------

print info "Starting backup of ${INSTANCE_NAME}"
print info "From: ${LIS_PATH}/"
print info "To  : ${DEST_DIR}/"

[ -d "$LIS_PATH" ] || fail "The installation folder ${LIS_PATH} does not exist."
# The copy mirrors with --delete. A folder without its configuration is an
# emptied or freshly reinstalled one, and mirroring it would delete the dumps,
# uploads and audit trail from the only copy that still has them.
[ -f "${LIS_PATH}/configs/config.production.php" ] ||
  fail "${LIS_PATH} has no configs/config.production.php, so it looks emptied or freshly reinstalled. The backup was NOT updated, to protect the copy at the destination. Restore the installation first (see the restore guide)."

ensure_destination_available

# The folder must still belong to this lab before anything is written into it.
REMOTE_UUID="$(dest_exec "awk -F= '/^lab_uuid=/{print \$2}' ${Q_DEST}/.lab-meta 2>/dev/null || true" | tr -d '\r\n')"
[ "$REMOTE_UUID" = "$LAB_UUID" ] || fail "The backup folder does not belong to this installation any more. Re-run remote-backup.sh."

# Free space at the destination.
AVAILABLE_GB=$(dest_exec "df -Pk ${Q_DEST} 2>/dev/null | awk 'NR==2{print int(\$4/1024/1024)}'" || echo 0)
AVAILABLE_GB=${AVAILABLE_GB:-0}
if [ "$AVAILABLE_GB" -lt 5 ]; then
  print warning "Only ${AVAILABLE_GB} GB free where the backup is stored."
  [ "$AVAILABLE_GB" -ge 2 ] || fail "Less than 2 GB free at the destination. Free up space and run the backup again."
fi

# How old is the newest database dump? The files on disk are only half a backup;
# the data lives in the dump written by the scheduled job every 6 hours.
DB_DUMP_AGE_HOURS=-1
DB_DUMP_DIR="${LIS_PATH}/backups/db"
if [ -d "$DB_DUMP_DIR" ]; then
  newest_dump=$(find "$DB_DUMP_DIR" -maxdepth 1 -type f \( -name '*.sql' -o -name '*.sql.gz' -o -name '*.sql.zst' -o -name '*.gpg' \) -printf '%T@\n' 2>/dev/null | sort -nr | head -1 | cut -d. -f1)
  if [ -n "${newest_dump:-}" ]; then
    DB_DUMP_AGE_HOURS=$(( ( $(date +%s) - newest_dump ) / 3600 ))
    if [ "$DB_DUMP_AGE_HOURS" -gt 24 ]; then
      print warning "The newest database dump is ${DB_DUMP_AGE_HOURS} hours old. The scheduled backup job may have stopped running; check that root's crontab still has the InteLIS scheduler line (sudo crontab -l | grep cron.sh)."
    else
      print info "Newest database dump is ${DB_DUMP_AGE_HOURS} hours old"
    fi
  else
    print warning "No database dump found in ${DB_DUMP_DIR}. Only files will be copied, not the data."
  fi
fi

# --- what to leave out --------------------------------------------------------
# composer.lock is deliberately kept: vendor/ is excluded, so the lock file is
# what makes the restored copy reproducible.

EXCLUDE_LIST="$(mktemp /tmp/intelis-backup-excludes.XXXXXX)"
trap 'rm -f "$EXCLUDE_LIST"' EXIT
cat > "$EXCLUDE_LIST" <<'EXCLUDES'
/public/temporary/
/var/logs/
/var/cache/
/vendor/
/node_modules/
/bower_components/
.git/
.svn/
.hg/
/.vscode/
/.idea/
*.tmp
*.temp
*.cache
*.pid
*.swp
*.swo
*~
*.sql.tmp
*.sql.partial
.DS_Store
Thumbs.db
desktop.ini
.directory
EXCLUDES

# --- rsync options per destination --------------------------------------------

# Split deliberately. RSYNC_MODE_OPTS is everything about HOW to reach the
# destination — transport, compression, and the compatibility flags a filesystem
# that cannot hold POSIX metadata needs. RSYNC_FILTER_OPTS is everything about
# WHAT to send. The verification pass below reuses the transport but must not
# reuse the filters: it works from an explicit file list, and --delete needs a
# whole-tree walk, which is the cost this is here to avoid.
RSYNC_FILTER_OPTS=(--delete --partial-dir=.rsync-partial --timeout=900 --exclude-from="$EXCLUDE_LIST" --exclude=.lab-meta --exclude=/.history/)
RSYNC_MODE_OPTS=()

# findmnt names the filesystem as the kernel mounted it. stat -f is kept as the
# fallback only: older coreutils print "UNKNOWN (0x2011bab0)" for exFAT, which
# then got full POSIX flags, and rsync failed setting owners the drive cannot
# hold.
dest_fstype() {
  findmnt -no FSTYPE -T "$1" 2>/dev/null || stat -f -c %T "$1" 2>/dev/null || echo unknown
}

needs_compat_flags() {
  # Filesystems that cannot hold POSIX ownership, permissions, or symlinks.
  case "$(dest_fstype "$1")" in
    vfat|exfat|msdos|ntfs|ntfs3|fuseblk|cifs|smb2|smb3) return 0 ;;
    *) return 1 ;;
  esac
}

# FAT32 cannot hold a file of 4 GB or more. A dump that size makes rsync fail
# with "File too large" on every run from then on, which reads like a disk
# fault. Say what it is instead. Setup no longer offers FAT32 drives; this is
# for drives set up before it refused them. Only backups/ is searched: the dumps
# are the only files that grow that large, and a walk of the whole tree is the
# cost the verification pass was rewritten to avoid.
if [ "$DEST_MODE" = "local" ]; then
  case "$(dest_fstype "$LOCAL_ROOT")" in
    vfat|msdos)
      too_big="$(find "${LIS_PATH}/backups" -type f -size +4095M -print -quit 2>/dev/null || true)"
      [ -z "$too_big" ] || fail "The backup drive is formatted as FAT32, which cannot hold files of 4 GB or more, and $(basename "$too_big") is larger. Reformat the drive as exFAT or ext4 (this erases it), then run 'intelis backup setup'."
      ;;
  esac
fi

case "$DEST_MODE" in
  ssh)
    RSYNC_MODE_OPTS+=(-aHz -e "ssh -i ${SSH_KEY} -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=15 -o ServerAliveCountMax=4 -p ${SSH_PORT}")
    RSYNC_TARGET="${SSH_USER}@${SSH_HOST}:${DEST_DIR}/"
    ;;
  smb)
    # -L copies what symlinks point at, because Windows shares cannot store them.
    RSYNC_MODE_OPTS+=(-rtLz --no-perms --no-owner --no-group --omit-dir-times --modify-window=2)
    RSYNC_TARGET="${DEST_DIR}/"
    ;;
  local)
    if needs_compat_flags "$LOCAL_ROOT"; then
      RSYNC_MODE_OPTS+=(-rtL --no-perms --no-owner --no-group --omit-dir-times --modify-window=2)
    else
      RSYNC_MODE_OPTS+=(-aH)
    fi
    RSYNC_TARGET="${DEST_DIR}/"
    ;;
esac

RSYNC_OPTS=("${RSYNC_FILTER_OPTS[@]}" "${RSYNC_MODE_OPTS[@]}")

# --- dry run ------------------------------------------------------------------

if [ "$ACTION" = "test" ]; then
  print info "Test run: nothing will be changed."
  DRY_LOG="$(mktemp /tmp/intelis-backup-dry.XXXXXX)"
  if ! rsync "${RSYNC_OPTS[@]}" --dry-run --itemize-changes "${LIS_PATH}/" "$RSYNC_TARGET" >"$DRY_LOG" 2>&1; then
    tail -20 "$DRY_LOG"; rm -f "$DRY_LOG"
    fail "The test copy failed. The details are above."
  fi
  pending=$(grep -c '^[<>]f' "$DRY_LOG" || true)
  rm -f "$DRY_LOG"
  print success "Connection works. ${AVAILABLE_GB} GB free at the destination."
  print info    "${pending} file(s) would be copied by a real backup."
  [ "$DB_DUMP_AGE_HOURS" -ge 0 ] && print info "Newest database dump: ${DB_DUMP_AGE_HOURS} hours old."
  exit 0
fi

# --- the backup ---------------------------------------------------------------

SECONDS=0
print info "Copying files..."

# The transfer's own account of itself, kept rather than discarded.
#
# This used to end in `>/dev/null`, and verification was a second full
# --dry-run over the whole tree. That is the single most expensive thing a
# backup did: an installation carries one audit-trail file per sample, so a
# busy lab has hundreds of thousands of them, and re-statting every one to
# rediscover what rsync had just finished telling us dominated the run. Over
# SMB, where the cost is per-file latency rather than bytes, most of a backup
# was spent re-confirming that immutable files were still unchanged.
#
# rsync already computed the answer in order to do the work. %i is the itemized
# change string and %n the path, so this list is the transfer, exactly.
TRANSFER_LOG="$(mktemp /tmp/intelis-backup-transfer.XXXXXX)"
trap 'rm -f "$EXCLUDE_LIST" "$TRANSFER_LOG" "${TRANSFER_LOG}.files"' EXIT

rsync_rc=0
rsync "${RSYNC_OPTS[@]}" --out-format='%i|%n' "${LIS_PATH}/" "$RSYNC_TARGET" >"$TRANSFER_LOG" 2>&1 || rsync_rc=$?
if [ "$rsync_rc" -eq 24 ]; then
  print warning "Some files disappeared while they were being copied (usually an old dump being tidied away). The rest was copied."
elif [ "$rsync_rc" -ne 0 ]; then
  # The transfer log is deleted on exit; keep rsync's own reason in the log.
  grep -v '|' "$TRANSFER_LOG" | tail -30 || true
  fail "The copy did not finish (rsync exit code ${rsync_rc}). See ${LOGFILE} for the details."
fi

# Regular files that were actually sent. Directories, symlinks and deletions are
# not re-checked: a stale extra file at the destination is not a loss, and the
# question this answers is whether what was sent arrived intact.
CHANGED_LIST="${TRANSFER_LOG}.files"
awk -F'|' '$1 ~ /^[<>]f/ { sub(/^[^|]*\|/, ""); print }' "$TRANSFER_LOG" > "$CHANGED_LIST" || true
CHANGED_COUNT=$(wc -l < "$CHANGED_LIST" | tr -d ' ')
CHANGED_COUNT=${CHANGED_COUNT:-0}

print success "Files copied (${CHANGED_COUNT} changed)"

# --- verification -------------------------------------------------------------
# Only what this run touched, and by content rather than by size and timestamp.
#
# Checking the delta instead of the tree is not a weaker guarantee, it is a
# stronger one. The old whole-tree pass compared size and mtime, so a file that
# arrived corrupt but plausible passed it. Restricting the check to the handful
# of files that actually moved makes -c affordable, and -c reads both copies and
# compares checksums.
#
# rsync escapes non-printable bytes in %n as \#nnn. A path like that cannot be
# fed back through --files-from safely, so the whole-tree check is used instead
# rather than silently verifying the wrong paths.
verify_transfer() {
  if grep -q '\\#' "$CHANGED_LIST"; then
    print info "Some file names need escaping; verifying the whole tree instead."
    local remaining
    local out
    out=$(rsync "${RSYNC_OPTS[@]}" --dry-run --itemize-changes "${LIS_PATH}/" "$RSYNC_TARGET" 2>/dev/null) || return 1
    remaining=$(printf '%s\n' "$out" | grep -c '^[<>]f' || true)
    [ "${remaining:-0}" -eq 0 ]
    return $?
  fi

  local differing out
  out=$(rsync "${RSYNC_MODE_OPTS[@]}" --files-from="$CHANGED_LIST" \
              --checksum --dry-run --out-format='%i|%n' \
              "${LIS_PATH}/" "$RSYNC_TARGET" 2>/dev/null) || return 1
  differing=$(printf '%s\n' "$out" | grep -c '^[<>]f' || true)
  [ "${differing:-0}" -eq 0 ]
}

if [ "$CHANGED_COUNT" -eq 0 ]; then
  print success "Verified: nothing needed copying, the backup already matches"
elif verify_transfer; then
  print success "Verified: ${CHANGED_COUNT} file(s) match at the destination"
else
  print warning "Some files still differ after the copy. They may have changed while the backup was running; the next backup should pick them up."
fi

# --- database history ---------------------------------------------------------
# The mirror holds what the lab machine holds, and db-tools keeps only its newest
# 7 dumps: about 2 days. .history/ keeps one dump per day for HISTORY_DAYS days
# and one per week for HISTORY_WEEKS weeks after that, so a mistake noticed late
# can still be undone. The mirror excludes it, and rsync never deletes an
# excluded path, so a wiped or reinstalled lab machine cannot reach it.
#
# Every decision is made here, from the timestamps in the file names. The
# destination only runs ls, ln, cp, mv and rm, so a bare Linux server, a Windows
# share and a USB drive all behave the same. Copies are made at the destination
# from the mirror, so no dump crosses the network twice, and ln is tried first:
# where hard links work, a history entry costs no space at all.

# Prints "label YYYYMMDD" for a dump or config archive name, nothing otherwise.
history_key() {
  local n=$1
  [[ "$n" =~ ^[A-Za-z0-9._-]+$ ]] || return 0
  case "$n" in *.meta.json|*.part|pre-restore-*) return 0 ;; esac
  if [[ "$n" =~ ^config-([0-9]{4})-([0-9]{2})-([0-9]{2})_[0-9-]+\.tgz$ ]]; then
    printf 'config %s%s%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}" "${BASH_REMATCH[3]}"
  elif [[ "$n" =~ ^(.+)-([0-9]{8})-[0-9]{6}.*\.sql(\.gz|\.zst|\.zip)?(\.gpg)?$ ]]; then
    printf '%s %s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[2]}"
  fi
}

update_history() {
  local listing where dir name k key label day
  # shellcheck disable=SC2016  # expanded at the destination, not here
  listing="$(dest_exec "cd ${Q_DEST} && mkdir -p .history/db .history/config && rm -f .history/db/*.part .history/config/*.part && for d in db config; do (cd \"backups/\$d\" 2>/dev/null && ls -1) | sed \"s|^|mirror \$d |\"; (cd \".history/\$d\" && ls -1) | sed \"s|^|history \$d |\"; done")" || return 1

  # want: the newest mirror file for each folder|label|day. have: history entries.
  declare -A want=() have=()
  while read -r where dir name; do
    [ -n "${name:-}" ] || continue
    k="$(history_key "$name")"
    [ -n "$k" ] || continue
    key="${dir}|${k% *}|${k#* }"
    if [ "$where" = "mirror" ]; then
      if [ -z "${want[$key]:-}" ] || [[ "$name" > "${want[$key]}" ]]; then want[$key]="$name"; fi
    else
      have[$key]="${have[$key]:+${have[$key]} }${name}"
    fi
  done < <(printf '%s\n' "$listing" | tr -d '\r')

  # One entry per folder|label|day: the newest of the mirror's and history's.
  local -a cmds=()
  declare -A final=()
  local best h
  for key in "${!want[@]}" "${!have[@]}"; do
    [ -z "${final[$key]:-}" ] || continue
    best="${want[$key]:-}"
    for h in ${have[$key]:-}; do [[ "$h" > "$best" ]] && best="$h"; done
    final[$key]="$best"
    dir="${key%%|*}"
    if [ -n "${want[$key]:-}" ] && [ "$best" = "${want[$key]}" ] && [[ " ${have[$key]:-} " != *" ${best} "* ]]; then
      cmds+=("{ ln -f \"backups/${dir}/${best}\" \".history/${dir}/${best}\" 2>/dev/null || { cp \"backups/${dir}/${best}\" \".history/${dir}/${best}.part\" && mv -f \".history/${dir}/${best}.part\" \".history/${dir}/${best}\"; }; } || e=1")
    fi
    for h in ${have[$key]:-}; do
      [ "$h" = "$best" ] || cmds+=("rm -f \".history/${dir}/${h}\" || e=1")
    done
  done

  # Retention, per folder|label, by count rather than by age: the newest
  # HISTORY_DAYS days, then the newest day of each of the next HISTORY_WEEKS
  # weeks. Age would need a trustworthy clock, and labs with a flat CMOS battery
  # write dumps dated years ahead; measured by age, one such dump pruned every
  # other entry. Counted, a wrong date costs one slot and nothing else.
  declare -A days_of=() keep=()
  for key in "${!final[@]}"; do
    label="${key%|*}"; day="${key##*|}"
    days_of[$label]="${days_of[$label]:+${days_of[$label]} }${day}"
  done
  local n weeks_seen wk last_wk
  local -A db_days=()
  for label in "${!days_of[@]}"; do
    n=0; weeks_seen=0; last_wk=""
    for day in $(printf '%s\n' ${days_of[$label]} | sort -r); do
      n=$((n + 1))
      if [ "$n" -le "$HISTORY_DAYS" ]; then
        keep[$label|$day]=1
        # The oldest daily entry's week is already covered.
        [ "$n" -eq "$HISTORY_DAYS" ] && last_wk="$(date -u -d "$day" +%G-%V 2>/dev/null || echo "$day")"
      else
        wk="$(date -u -d "$day" +%G-%V 2>/dev/null || echo "$day")"
        # Newest first, so the first day met in a week is that week's newest.
        if [ "$wk" != "$last_wk" ] && [ "$weeks_seen" -lt "$HISTORY_WEEKS" ]; then
          keep[$label|$day]=1
          weeks_seen=$((weeks_seen + 1))
        fi
        last_wk="$wk"
      fi
      [ -n "${keep[$label|$day]:-}" ] && [ "${label%%|*}" = "db" ] && db_days[$day]=1
    done
  done
  for key in "${!final[@]}"; do
    [ -n "${keep[$key]:-}" ] || cmds+=("rm -f \".history/${key%%|*}/${final[$key]}\" || e=1")
  done

  # Reported as days covered, whichever databases they hold.
  HISTORY_COUNT=${#db_days[@]}; HISTORY_OLDEST=""; HISTORY_NEWEST=""
  if [ "$HISTORY_COUNT" -gt 0 ]; then
    HISTORY_OLDEST="$(printf '%s\n' "${!db_days[@]}" | sort | head -1)"
    HISTORY_NEWEST="$(printf '%s\n' "${!db_days[@]}" | sort | tail -1)"
  fi
  [ -n "$HISTORY_OLDEST" ] && HISTORY_OLDEST="${HISTORY_OLDEST:0:4}-${HISTORY_OLDEST:4:2}-${HISTORY_OLDEST:6:2}"
  [ -n "$HISTORY_NEWEST" ] && HISTORY_NEWEST="${HISTORY_NEWEST:0:4}-${HISTORY_NEWEST:4:2}-${HISTORY_NEWEST:6:2}"

  [ "${#cmds[@]}" -gt 0 ] || return 0
  local joined
  joined="$(printf '%s; ' "${cmds[@]}")"
  dest_exec "cd ${Q_DEST} && e=0; ${joined} exit \$e"
}

if update_history; then
  if [ "$HISTORY_COUNT" -gt 0 ]; then
    print success "History: ${HISTORY_OLDEST} to ${HISTORY_NEWEST} (${HISTORY_COUNT} days)"
  fi
else
  print warning "Could not update the database history at the destination. The copy itself is complete; the next backup tries again."
fi

# The size on the backup, for `intelis backup status` — measured only where
# measuring is cheap.
#
# `du -sh` is a recursive stat of every file the lab has ever backed up. Over SSH
# that runs on the backup server as a local walk and the network cost is one
# round trip, so it stays. On a CIFS mount every one of those stats is a network
# round trip, which is the same per-file cost this change exists to remove — and
# an installation carries one audit-trail file per sample. So SMB does not pay
# it. Nothing depends on the number: it is displayed by `backup status` and
# stored in the status JSON, and both already handle "unknown".
case "$DEST_MODE" in
  smb)
    BACKUP_SIZE="not measured"
    ;;
  *)
    BACKUP_SIZE=$(dest_exec "du -sh ${Q_DEST} 2>/dev/null | cut -f1" || echo "unknown")
    ;;
esac
BACKUP_SIZE=${BACKUP_SIZE:-unknown}

write_status ok "" "$BACKUP_SIZE" "$SECONDS"
print success "Backup finished in ${SECONDS}s. ${AVAILABLE_GB} GB free at the destination."
[ "$BACKUP_SIZE" = "not measured" ] || print info "Size on the backup: ${BACKUP_SIZE}"
RUNNER_SCRIPT

chmod 0755 "${RUNNER}.new"
mv -f "${RUNNER}.new" "$RUNNER"
print success "Backup runner installed at $RUNNER"

# The Windows-only runner from the older setup is replaced by the unified one.
if [ -f "$LEGACY_WINDOWS_RUNNER" ]; then
  rm -f "$LEGACY_WINDOWS_RUNNER"
  print info "Removed the old Windows-only runner; one runner now handles every destination."
fi
}

# --- preflight ----------------------------------------------------------------

require_cmd realpath
require_cmd awk
require_cmd sed

mkdir -p "$CONF_DIR"
chmod 700 "$CONF_DIR"

# --- refreshing the runner on an upgrade --------------------------------------
#
# The runner is written once, on the day backups are set up, and then never
# again: nothing in the upgrade path rewrote it. Every improvement made to it
# since a lab was configured therefore sat in the repository and reached that
# lab only if somebody re-ran setup by hand — and nobody re-runs setup on a
# thing that is working. Shipping a faster or safer runner did not deliver one.
#
# upgrade.sh now calls this once the new code is in place, on the principle it
# already applies to the remote command runner: install from the code that has
# just arrived, not the code being replaced.
#
# Non-interactive by design, since it runs inside an upgrade. It will not invent
# a configuration: with no backup.conf there is nothing to refresh and nothing
# to guess, and deciding to back a lab up is a decision for a person.
if [ "$SETUP_ACTION" = "refresh-runner" ]; then
  if [ ! -f "$CONF_FILE" ]; then
    exit 0
  fi

  if install_backup_runner >/dev/null 2>&1; then
    print success "Backup runner refreshed from the installed version."
    exit 0
  fi

  print warning "Could not refresh the backup runner; the existing one is left in place."
  exit 1
fi

# Load any previous answers so a re-run is press-Enter-all-the-way.
INSTANCE_NAME=""; LIS_PATH=""; DEST_MODE=""
SSH_USER=""; SSH_HOST=""; SSH_PORT=""
SMB_HOST=""; SMB_SHARE=""; SMB_USER=""; SMB_VERS=""
LOCAL_ROOT=""; LOCAL_UUID=""; DEST_BASE=""
if [ -f "$CONF_FILE" ]; then
  # shellcheck disable=SC1090
  . "$CONF_FILE"
fi

# Best-effort, bounded, and never fatal: gum is a nicety here exactly as it is
# in setup.sh, and the plain prompts below are already correct without it.
ensure_gum || true

print header "InteLIS backup setup"

# --- re-running setup ---------------------------------------------------------
# Setup is re-run to repair or update a lab far more often than to move its
# backups, and walking through every question to keep every answer is where a
# mistyped Enter changes one. So when the saved destination still works, the
# only question is whether to keep it. When it does not work, keeping it would
# be keeping something broken, and setup runs as it would the first time, with
# the saved answers offered as defaults.

describe_destination() {
  case "$DEST_MODE" in
    ssh)   printf '%s@%s' "$SSH_USER" "$SSH_HOST" ;;
    smb)   printf '//%s/%s' "$SMB_HOST" "$SMB_SHARE" ;;
    local) printf '%s' "$LOCAL_ROOT" ;;
  esac
}

# Checks the saved destination can be reached and written to, without asking
# anything: no password and no key installation. The only change it can make
# is creating the shared backups folder, which the first backup creates anyway.
saved_destination_works() {
  case "$DEST_MODE" in
    ssh)
      [ -n "$SSH_HOST" ] && [ -n "$SSH_USER" ] && [ -f "$SSH_KEY" ] || return 1
      ssh -n -i "$SSH_KEY" -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new \
          -p "${SSH_PORT:-22}" "${SSH_USER}@${SSH_HOST}" "mkdir -p $(printf '%q' "$DEST_BASE") && test -w $(printf '%q' "$DEST_BASE")" >/dev/null 2>&1
      ;;
    smb)
      [ -n "$SMB_HOST" ] && [ -f "$SMB_CRED_FILE" ] || return 1
      mountpoint -q "$MOUNT_POINT" || mount "$MOUNT_POINT" >/dev/null 2>&1 || return 1
      ( : > "${MOUNT_POINT}/.intelis-writetest" && rm -f "${MOUNT_POINT}/.intelis-writetest" ) 2>/dev/null
      ;;
    local)
      [ -n "$LOCAL_ROOT" ] || return 1
      if [ -n "$LOCAL_UUID" ]; then
        mountpoint -q "$LOCAL_ROOT" || mount "$LOCAL_ROOT" >/dev/null 2>&1 || return 1
        mountpoint -q "$LOCAL_ROOT" || return 1
      fi
      ( : > "${LOCAL_ROOT}/.intelis-writetest" && rm -f "${LOCAL_ROOT}/.intelis-writetest" ) 2>/dev/null
      ;;
    *) return 1 ;;
  esac
}

KEEP_DEST=false
if [ -n "$DEST_MODE" ] && [ -n "$DEST_BASE" ] && [ -n "$INSTANCE_NAME" ] &&
   [ -f "${LIS_PATH}/configs/config.production.php" ]; then
  print info "Checking the saved backup destination, $(describe_destination)..."
  if saved_destination_works; then
    print success "Backups from this machine go to $(describe_destination), and it can be reached."
    choose keep_choice keep "Keep sending backups there?" \
      "keep:Keep it:Nothing is asked. The backup runner and schedule are refreshed and a backup runs now." \
      "change:Change where backups go:Asks every question again, with the saved answers offered."
    # shellcheck disable=SC2154  # set by `choose` above, via printf -v
    [ "$keep_choice" = "keep" ] && KEEP_DEST=true
  else
    print warning "The saved backup destination, $(describe_destination), cannot be reached or written to."
    print info    "Setting up as if for the first time. The saved answers are offered; press Enter to keep one."
  fi
fi

# --- instance name ------------------------------------------------------------

$KEEP_DEST || ask INSTANCE_NAME "Lab name or lab code" "${INSTANCE_NAME:-$(hostname -s 2>/dev/null || echo lab)}"
SANITIZED_NAME=$(printf '%s' "$INSTANCE_NAME" | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-' | sed 's/-*$//;s/^-*//')
if [ -z "$SANITIZED_NAME" ]; then
  print error "That name has no letters or numbers in it. Use something like 'kigali-central'."
  exit 1
fi

# --- lab identity -------------------------------------------------------------
# Every installation gets a permanent UUID. The destination folder is named from
# it, so two labs that pick the same lab name still get separate folders and can
# never overwrite each other.

if [ ! -f "$LAB_UUID_FILE" ]; then
  LAB_UUID="$(cat /proc/sys/kernel/random/uuid)"
  printf '%s\n' "$LAB_UUID" > "$LAB_UUID_FILE"
  chmod 600 "$LAB_UUID_FILE"
else
  LAB_UUID="$(trim "$(cat "$LAB_UUID_FILE")")"
fi
UUID_SHORT="${LAB_UUID:0:8}"
DEST_FOLDER="${SANITIZED_NAME}-${UUID_SHORT}"

print success "This installation is '${SANITIZED_NAME}' (id ${UUID_SHORT})"
print info    "Its backups will live in a folder called: ${DEST_FOLDER}"

# --- LIS path -----------------------------------------------------------------

looks_like_lis() { [ -f "$1/configs/config.production.php" ] && [ -d "$1/public" ]; }

if ! $KEEP_DEST; then
print header "Which installation should be backed up?"

if [ -z "$LIS_PATH" ]; then
  for candidate in /var/www/intelis /var/www/vlsm; do
    if looks_like_lis "$candidate"; then LIS_PATH="$candidate"; break; fi
  done
fi
if [ -z "$LIS_PATH" ]; then
  for candidate in /var/www/*/; do
    candidate="${candidate%/}"
    if looks_like_lis "$candidate"; then LIS_PATH="$candidate"; break; fi
  done
fi
[ -n "$LIS_PATH" ] && print info "Detected an installation at $LIS_PATH"

# Asked only when there is a choice to make. With one installation on the
# machine, the question only ever had one right answer.
lis_count=0
for candidate in /var/www/*/; do
  looks_like_lis "${candidate%/}" && lis_count=$((lis_count + 1))
done
if [ -n "$LIS_PATH" ] && looks_like_lis "$LIS_PATH" && [ "$lis_count" -le 1 ]; then
  lis_asked=false
else
  lis_asked=true
fi

while $lis_asked; do
  ask LIS_PATH "InteLIS folder path" "${LIS_PATH:-/var/www/intelis}"
  [[ "$LIS_PATH" != /* ]] && LIS_PATH="$(realpath "$LIS_PATH" 2>/dev/null || printf '%s' "$LIS_PATH")"
  if [ ! -d "$LIS_PATH" ]; then
    print warning "'$LIS_PATH' does not exist. Try again."
    LIS_PATH=""
    continue
  fi
  if ! looks_like_lis "$LIS_PATH"; then
    print warning "'$LIS_PATH' does not look like an InteLIS installation (no configs/config.production.php)."
    LIS_PATH=""
    continue
  fi
  break
done
print success "Backing up: $LIS_PATH"
fi

# --- destination --------------------------------------------------------------

$KEEP_DEST || choose DEST_MODE "${DEST_MODE:-ssh}" "Where should the backup be sent?" \
  "ssh:Another Linux machine on the network (recommended):One backup machine can take the backups of every lab." \
  "smb:A shared folder on a Windows machine:Sent over SMB to a folder shared from Windows." \
  "local:A USB or external drive plugged into this machine:Only when there is no other machine. A drive is easily unplugged, and is lost with this computer in a theft or fire."

# --- destination: another Linux machine over SSH ------------------------------

# Adds the backup key for SSH_USER on the backup server by logging in once as
# an account that can already get in: root's own key, an ssh-agent, or a
# password if the server takes one for that account. Needed when the server
# accepts keys only (the default on cloud servers), so ssh-copy-id can never
# ask for SSH_USER's password, and when SSH_USER does not exist yet.
install_key_via_admin() {
  local admin key create=0 exists b64 run
  # One connection for both commands, so a password is typed once.
  local mux=(-o ControlMaster=auto -o "ControlPath=/tmp/intelis-admin-%C" -o ControlPersist=60)
  # No default. Ubuntu refuses root logins by password, so "root" is the wrong
  # guess on most backup machines; the account is the one used to manage it.
  ask admin "Administrator account on the backup machine (the account used to manage it)" ""
  [[ "$admin" =~ ^[a-z_][a-z0-9_.-]*$ ]] || { print warning "'$admin' is not a valid username."; return 1; }

  print info "Logging in as ${admin}. If it asks for a password, it is ${admin}'s password on the backup server."
  exists="$(ssh "${mux[@]}" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -p "$SSH_PORT" \
              "${admin}@${SSH_HOST}" "id -u '${SSH_USER}' >/dev/null 2>&1 && echo yes || echo no" </dev/null)" ||
    { print warning "Could not log in as ${admin} either."; return 1; }
  # Not confirmed first. The first lab to use a backup machine always lands
  # here, the account is a fixed name rather than something typed, and it is
  # created for backups only.
  if [ "$(printf '%s' "$exists" | tr -d '\r')" = "no" ]; then
    print info "Creating the account '${SSH_USER}' on the backup machine, for backups only."
    create=1
  fi

  key="$(cat "${SSH_KEY}.pub")"
  # Sent encoded so no quoting survives two shells; everything in it is a fixed
  # command, the validated username and the public key line.
  b64="$(printf '%s\n' \
    "set -e" \
    "u='${SSH_USER}'" \
    "if [ ${create} -eq 1 ] && ! id -u \"\$u\" >/dev/null 2>&1; then useradd -m -s /bin/bash \"\$u\"; fi" \
    "h=\$(getent passwd \"\$u\" | cut -d: -f6)" \
    "mkdir -p \"\$h/.ssh\"; touch \"\$h/.ssh/authorized_keys\"" \
    "grep -qxF '${key}' \"\$h/.ssh/authorized_keys\" || echo '${key}' >> \"\$h/.ssh/authorized_keys\"" \
    "chown -R \"\$u\": \"\$h/.ssh\"; chmod 700 \"\$h/.ssh\"; chmod 600 \"\$h/.ssh/authorized_keys\"" \
    | base64 | tr -d '\n')"
  run="sh"
  [ "$admin" = "root" ] || run="sudo sh"
  # -t so sudo can ask for its password on this terminal.
  ssh -t "${mux[@]}" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -p "$SSH_PORT" \
      "${admin}@${SSH_HOST}" "echo ${b64} | base64 -d | ${run}" ||
    { print warning "Logged in as ${admin}, but adding the key failed. ${admin} may not be allowed to use sudo."; return 1; }
  print success "This machine can now send backups to ${SSH_HOST} as ${SSH_USER}"
}

print_manual_key_steps() {
  print info "Log in to the backup server the usual way and run these commands to add the key:"
  echo
  # Creates the account first: ~${SSH_USER} does not expand for a missing user,
  # so mkdir would make a literal "~${SSH_USER}" folder instead.
  echo "    id -u ${SSH_USER} >/dev/null 2>&1 || sudo useradd -m -s /bin/bash ${SSH_USER}"
  echo "    sudo mkdir -p ~${SSH_USER}/.ssh"
  echo "    echo '$(cat "${SSH_KEY}.pub")' | sudo tee -a ~${SSH_USER}/.ssh/authorized_keys"
  echo "    sudo chown -R ${SSH_USER}: ~${SSH_USER}/.ssh && sudo chmod 700 ~${SSH_USER}/.ssh && sudo chmod 600 ~${SSH_USER}/.ssh/authorized_keys"
  echo
}

# The backup key is not one of ssh's default keys, so a plain
# `ssh user@backup-server` from this machine was refused even though every
# backup worked, which sends people hunting for a fault that is not there. A
# Host entry makes a manual login use the same key. An existing entry for the
# host is someone's own configuration and is left alone.
ensure_ssh_config_entry() {
  local cfg=/root/.ssh/config
  if [ -f "$cfg" ] && grep -qiE "^[[:space:]]*Host([[:space:]]+[^[:space:]]+)*[[:space:]]+${SSH_HOST//./\\.}([[:space:]]|$)" "$cfg"; then
    return 0
  fi
  {
    [ -s "$cfg" ] && echo
    echo "# Added by InteLIS backup setup, so a plain ssh to the backup server uses the backup key."
    echo "Host ${SSH_HOST}"
    echo "  User ${SSH_USER}"
    [ "$SSH_PORT" = "22" ] || echo "  Port ${SSH_PORT}"
    echo "  IdentityFile ${SSH_KEY}"
    # An IdentityFile line replaces ssh's default keys for this host, which
    # would lock out whoever logs in there with their own key. Keep them.
    local k
    for k in /root/.ssh/id_ed25519 /root/.ssh/id_ecdsa /root/.ssh/id_rsa; do
      [ -f "$k" ] && [ "$k" != "$SSH_KEY" ] && echo "  IdentityFile ${k}"
    done
  } >> "$cfg"
  chmod 600 "$cfg"
  print info "Added ${SSH_HOST} to ${cfg}, so 'ssh ${SSH_HOST}' logs in as ${SSH_USER} with the backup key."
}

# One question for where the backups go. Most answers are a bare address; the
# rare other account or port is written into it (backup@host, host:2222)
# rather than asked on every run, where the answer is nearly always the same.
compose_address() {
  local a="${SSH_HOST}"
  [ -n "$a" ] || return 0
  if [ "${SSH_PORT:-22}" != "22" ]; then
    [[ "$a" == *:* ]] && a="[${a}]" # an IPv6 address needs brackets before a port
    a="${a}:${SSH_PORT}"
  fi
  [ "${SSH_USER:-lisbackup}" = "lisbackup" ] || a="${SSH_USER}@${a}"
  printf '%s' "$a"
}

parse_address() {
  local a=$1
  SSH_USER="lisbackup"; SSH_PORT="22"
  if [[ "$a" == *@* ]]; then SSH_USER="${a%%@*}"; a="${a#*@}"; fi
  if [[ "$a" =~ ^\[(.+)\]:([0-9]+)$ ]] || [[ "$a" =~ ^([^:]+):([0-9]+)$ ]]; then
    a="${BASH_REMATCH[1]}"; SSH_PORT="${BASH_REMATCH[2]}"
  fi
  SSH_HOST="${a#[}"; SSH_HOST="${SSH_HOST%]}"
  if ! [[ "$SSH_USER" =~ ^[a-z_][a-z0-9_.-]*$ ]]; then
    print warning "'${SSH_USER}' is not a valid account name."; return 1
  fi
  if [ -z "$SSH_HOST" ] || [[ "$SSH_HOST" =~ [[:space:]/] ]]; then
    print warning "'$1' is not an address. Type an IP address such as 192.168.1.60, or a machine name."; return 1
  fi
  if [ "$SSH_PORT" -lt 1 ] || [ "$SSH_PORT" -gt 65535 ]; then
    print warning "'${SSH_PORT}' is not a valid port number."; return 1
  fi
}

backup_key_works() {
  ssh -n -i "$SSH_KEY" -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 \
      -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" true 2>/dev/null
}

configure_ssh() {
  require_cmd ssh
  require_cmd ssh-keygen
  require_cmd ssh-copy-id

  print header "Backup machine"

  mkdir -p /root/.ssh; chmod 700 /root/.ssh
  if [ ! -f "$SSH_KEY" ]; then
    print info "Creating a dedicated SSH key for backups..."
    ssh-keygen -t ed25519 -C "intelis-backup-${SANITIZED_NAME}" -N "" -f "$SSH_KEY" >/dev/null
  fi
  chmod 600 "$SSH_KEY"; chmod 644 "${SSH_KEY}.pub"

  # recheck: the address is already known, so go straight to the checks. Set
  # after access was added, and after an attempt that failed, so a failure
  # returns to the choice of how to add access rather than to the address.
  local recheck=false just_added=false addr access copy_err key_only
  while true; do
    if $recheck; then
      recheck=false
    else
      ask addr "Address of the backup machine (its IP address or name)" "$(compose_address)"
      parse_address "$addr" || continue
    fi

    print info "Checking that ${SSH_HOST} is reachable..."
    if ! timeout 10 bash -c "</dev/tcp/${SSH_HOST}/${SSH_PORT}" 2>/dev/null; then
      print warning "Cannot reach ${SSH_HOST} on port ${SSH_PORT}."
      print info    "Check that the machine is switched on, on the same network, and has the SSH server installed (sudo apt install openssh-server)."
      confirm "Try again?" && continue
      exit 1
    fi

    if backup_key_works; then
      print success "This machine can send backups to ${SSH_HOST}"
      break
    fi
    if $just_added; then
      print warning "Access was added, but logging in with it still fails. The backup machine may refuse key logins for ${SSH_USER}."
    fi
    just_added=false

    # Asked before anything is tried, rather than after a failed password
    # attempt. With many labs sending to one backup machine, the administrator
    # route is the one that works every time: for the first lab it creates the
    # account, and for every later lab it adds that lab's access to it.
    choose access admin "This machine needs access to ${SSH_HOST}. How should it be given?" \
      "admin:Log in as the backup machine's administrator (recommended):Creates the ${SSH_USER} account the first time, then gives this lab access. The same for every lab." \
      "password:Type the ${SSH_USER} password:Only where ${SSH_USER} was given a password on the backup machine." \
      "manual:Add it by hand on the backup machine:Shows the commands to run there." \
      "retry:Type the address again:For a mistyped address." \
      "stop:Stop:Nothing is changed."

    case "$access" in
      admin)
        install_key_via_admin && just_added=true || print warning "Access was not added."
        recheck=true ;;
      password)
        print info "Type ${SSH_USER}'s password on the backup machine when asked."
        copy_err="$(mktemp)"
        if ssh-copy-id -i "${SSH_KEY}.pub" -o StrictHostKeyChecking=accept-new \
             -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" 2>"$copy_err" >/dev/null; then
          just_added=true
        else
          # The password prompt goes to the terminal, not stderr, so stderr
          # holds only ssh-copy-id's own chatter. "Permission denied
          # (publickey)" with no other method listed means the machine takes
          # keys only (the default on cloud servers): no password can work.
          key_only=false
          grep -q 'Permission denied (publickey)' "$copy_err" && key_only=true
          if $key_only; then
            print warning "The backup machine does not accept passwords, only keys. Choose the administrator login instead."
          else
            grep -v '^/usr/bin/ssh-copy-id: INFO' "$copy_err" | tail -3 >&2 || true
            print warning "That did not work. The password may be wrong, or ${SSH_USER} may have no password. Choose the administrator login instead."
          fi
        fi
        rm -f "$copy_err"
        recheck=true ;;
      manual)
        print_manual_key_steps
        confirm "Has it been added? Check now?" || exit 1
        just_added=true
        recheck=true ;;
      retry) ;;
      *) exit 1 ;;
    esac
  done

  ensure_ssh_config_entry || print warning "Could not add ${SSH_HOST} to /root/.ssh/config. Backups are not affected."

  local remote_home
  remote_home="$(ssh -n -i "$SSH_KEY" -o BatchMode=yes -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" 'printf %s "$HOME"')"
  DEST_BASE="${remote_home}/backups"
  DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"
}

# --- destination: Windows shared folder over SMB ------------------------------

configure_smb() {
  print header "Windows shared folder details"
  print info "On the Windows machine: share a folder (for example C:\\InteLIS-Backups)"
  print info "and give a Windows user Change + Read permission on that share."
  echo

  print info "Installing the tools needed to talk to Windows shares..."
  apt-get update -y >/dev/null 2>&1 || print warning "Could not refresh the package lists; continuing."
  apt-get install -y cifs-utils rsync >/dev/null || { print error "Could not install cifs-utils. Fix the package manager and re-run."; exit 1; }
  require_cmd mount.cifs

  mkdir -p "$MOUNT_POINT"

  local smb_pass
  while true; do
    ask SMB_HOST  "Windows hostname or IP (for example 192.168.1.50)" "${SMB_HOST:-}"
    ask SMB_SHARE "Name of the shared folder (for example InteLIS-Backups)" "${SMB_SHARE:-}"
    ask SMB_USER  "Windows username" "${SMB_USER:-}"
    ask_secret smb_pass "Windows password for ${SMB_USER}"

    if printf '%s' "$SMB_SHARE" | grep -q ' '; then
      print warning "The share name contains a space. Re-share the folder using a name without spaces, such as InteLIS-Backups."
      SMB_SHARE=""
      continue
    fi

    umask 077
    printf 'username=%s\npassword=%s\n' "$SMB_USER" "$smb_pass" > "$SMB_CRED_FILE"
    chmod 600 "$SMB_CRED_FILE"
    umask 022

    local unc="//${SMB_HOST}/${SMB_SHARE}"
    mountpoint -q "$MOUNT_POINT" && umount "$MOUNT_POINT" 2>/dev/null || true

    # Try the modern dialect first and fall back, so the operator never has to
    # know what an "SMB protocol version" is.
    local mounted=0 v
    for v in "${SMB_VERS:-3.1.1}" 3.0 2.1 1.0; do
      if mount -t cifs "$unc" "$MOUNT_POINT" \
           -o "credentials=${SMB_CRED_FILE},vers=${v},uid=root,gid=root,iocharset=utf8,file_mode=0640,dir_mode=0750" 2>/dev/null; then
        SMB_VERS="$v"; mounted=1; break
      fi
    done

    if [ "$mounted" -ne 1 ]; then
      print warning "Could not connect to ${unc}."
      print info    "Check the username and password, that the folder is actually shared,"
      print info    "and that File and Printer Sharing is allowed through the Windows firewall."
      rm -f "$SMB_CRED_FILE"
      confirm "Try again?" && { SMB_HOST=""; SMB_SHARE=""; SMB_USER=""; SMB_VERS=""; continue; }
      exit 1
    fi

    if ! ( : > "${MOUNT_POINT}/.intelis-writetest" && rm -f "${MOUNT_POINT}/.intelis-writetest" ); then
      print warning "Connected, but the folder is read-only. Give ${SMB_USER} the Change permission on the share in Windows."
      umount "$MOUNT_POINT" 2>/dev/null || true
      confirm "Try again?" && continue
      exit 1
    fi

    print success "Connected to ${unc} (SMB ${SMB_VERS}) and it is writable"
    break
  done

  # Remount automatically after a reboot.
  local fstab_line="//${SMB_HOST}/${SMB_SHARE} ${MOUNT_POINT} cifs credentials=${SMB_CRED_FILE},vers=${SMB_VERS},uid=root,gid=root,iocharset=utf8,file_mode=0640,dir_mode=0750,nofail,_netdev 0 0"
  if grep -qE "[[:space:]]${MOUNT_POINT}[[:space:]]" /etc/fstab; then
    sed -i "\#[[:space:]]${MOUNT_POINT}[[:space:]]#d" /etc/fstab
  fi
  echo "$fstab_line" >> /etc/fstab
  print success "The share will reconnect by itself after a restart"

  DEST_BASE="${MOUNT_POINT}/backups"
  DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"
}

# --- destination: local USB or external drive ---------------------------------
#
# The drive is picked from a list and mounted at a fixed folder, by its UUID,
# through /etc/fstab. It used to be a path typed from lsblk's MOUNTPOINT column,
# and on a desktop that is where the Files app mounted the drive for whoever was
# logged in: /media/<user>/<label>. That folder exists only while someone is
# logged in and has opened the drive, so after an unattended restart every
# backup failed with "drive not there" until somebody did. Mounted from fstab,
# the drive is there at boot, and the runner remounts it after a replug.

# The whole disks holding this machine's own system or installation, one per
# line. Nothing on them is offered: a backup on the disk it protects is not a
# backup.
system_disks() {
  local m src
  for m in / /boot /boot/efi "$LIS_PATH"; do
    src="$(findmnt -no SOURCE -T "$m" 2>/dev/null)" || continue
    src="${src%%\[*}" # btrfs subvolumes read /dev/sda2[/@]
    [[ "$src" == /dev/* ]] || continue
    lsblk -lspno NAME,TYPE "$src" 2>/dev/null | awk '$2=="disk"{print $1}'
  done | sort -u
}

# lsblk -P prints KEY="value" pairs and escapes anything unusual in a value as
# \xNN, so a label with a space or a quote in it cannot break the parsing.
lsblk_field() {
  local re="(^|[[:space:]])${2}=\"([^\"]*)\""
  [[ "$1" =~ $re ]] || return 0
  printf '%b' "${BASH_REMATCH[2]}"
}

# ':' separates the fields of a menu row.
menu_safe() { printf '%s' "$1" | tr ':' '-'; }

# Fills DRIVE_KEYS (UUIDs, in lsblk order) and the per-UUID maps.
declare -a DRIVE_KEYS=()
declare -A DRIVE_DEV=() DRIVE_FS=() DRIVE_LABEL=() DRIVE_DESC=()
list_drives() {
  DRIVE_KEYS=(); DRIVE_DEV=(); DRIVE_FS=(); DRIVE_LABEL=(); DRIVE_DESC=()
  local sys line dev type fs size uuid label mnt disk model
  sys="$(system_disks)"
  while IFS= read -r line; do
    dev="$(lsblk_field "$line" NAME)";  type="$(lsblk_field "$line" TYPE)"
    fs="$(lsblk_field "$line" FSTYPE)"; size="$(lsblk_field "$line" SIZE)"
    uuid="$(lsblk_field "$line" UUID)"; label="$(lsblk_field "$line" LABEL)"
    mnt="$(lsblk_field "$line" MOUNTPOINT)"
    case "$type" in part|disk) ;; *) continue ;; esac
    # lsblk reads these from udev's database. Where udev has not probed the
    # drive (a minimal system, or a drive that has just been plugged in), ask
    # the drive itself.
    if [ -z "$uuid" ] && command -v blkid >/dev/null 2>&1; then
      fs="$(blkid -s TYPE -o value "$dev" 2>/dev/null || true)"
      uuid="$(blkid -s UUID -o value "$dev" 2>/dev/null || true)"
      label="$(blkid -s LABEL -o value "$dev" 2>/dev/null || true)"
    fi
    [ -n "$uuid" ] && [ -n "$fs" ] || continue
    case "$fs" in swap|LVM2_member|crypto_LUKS|linux_raid_member|zfs_member|squashfs|erofs|iso9660|udf) continue ;; esac
    case "$mnt" in /|/boot|/boot/*|/snap/*|"[SWAP]") continue ;; esac
    [ -z "${DRIVE_DEV[$uuid]:-}" ] || continue # a cloned drive repeats a UUID
    disk="$(lsblk -lspno NAME,TYPE "$dev" 2>/dev/null | awk '$2=="disk"{print $1; exit}')"
    if [ -n "$disk" ] && [ -n "$sys" ] && grep -qxF "$disk" <<<"$sys"; then continue; fi
    model="$(lsblk -dno VENDOR,MODEL "${disk:-$dev}" 2>/dev/null | tr -s '[:space:]' ' ' | sed 's/^ //;s/ $//')"
    DRIVE_KEYS+=("$uuid")
    DRIVE_DEV[$uuid]="$dev"
    DRIVE_FS[$uuid]="$fs"
    DRIVE_LABEL[$uuid]="$(menu_safe "${label:-Unnamed drive} (${size})")"
    DRIVE_DESC[$uuid]="$(menu_safe "${model:+${model} · }${fs} · ${dev}")"
  done < <(lsblk -Ppno NAME,TYPE,FSTYPE,SIZE,UUID,LABEL,MOUNTPOINT 2>/dev/null)
}

apt_install_quiet() {
  apt-get update -y >/dev/null 2>&1 || true
  apt-get install -y "$@" >/dev/null 2>&1
}

# Mounts the drive with this UUID at USB_MOUNT and records it in /etc/fstab.
# fstab is written only after the mount has worked, so a drive that cannot be
# mounted leaves no entry behind.
mount_backup_drive() {
  local uuid=$1 dev fs type opts="defaults" t
  dev="${DRIVE_DEV[$uuid]}"; fs="${DRIVE_FS[$uuid]}"; type="$fs"

  case "$fs" in
    ntfs)
      type="ntfs-3g"
      command -v ntfs-3g >/dev/null 2>&1 || { print info "Installing the tools needed to write to NTFS drives..."; apt_install_quiet ntfs-3g; }
      ;;
  esac
  # Drives that cannot store owners get root's, and are readable by root alone:
  # the backup holds the database password.
  case "$fs" in vfat|exfat|ntfs) opts="uid=0,gid=0,umask=077" ;; esac

  # The desktop mounts a plugged-in drive under /media for whoever is logged
  # in. Move it: a FUSE filesystem (NTFS) cannot be mounted twice.
  while IFS= read -r t; do
    [ -n "$t" ] || continue
    t="$(printf '%b' "$t")" # -r escapes a space in the folder name as \x20
    case "$t" in
      "$USB_MOUNT") ;;
      /media/*|/run/media/*)
        umount "$t" 2>/dev/null || {
          print warning "The drive is open at ${t}. Close any window showing it, then try again."
          return 1
        } ;;
    esac
  done < <(findmnt -rno TARGET -S "UUID=${uuid}" 2>/dev/null || true)

  # Switching drives: whatever is on the backup folder now is the old one.
  if mountpoint -q "$USB_MOUNT" && [ "$(findmnt -no UUID "$USB_MOUNT" 2>/dev/null)" != "$uuid" ]; then
    umount "$USB_MOUNT" 2>/dev/null || { print warning "The previous backup drive at ${USB_MOUNT} is still in use."; return 1; }
  fi

  mkdir -p "$USB_MOUNT"
  if ! mountpoint -q "$USB_MOUNT"; then
    if ! mount -t "$type" -o "$opts" "UUID=${uuid}" "$USB_MOUNT" 2>/dev/null; then
      # exFAT is in the kernel from 5.7 on; older kernels need the FUSE driver.
      if [ "$fs" = "exfat" ]; then
        print info "Installing the tools needed to write to exFAT drives..."
        apt_install_quiet exfat-fuse || true
      fi
      mount -t "$type" -o "$opts" "UUID=${uuid}" "$USB_MOUNT" 2>/dev/null || {
        print warning "Could not connect ${dev} (${fs})."
        return 1
      }
    fi
  fi

  # nofail: a restart with the drive unplugged must still boot. The short
  # device timeout stops it waiting 90 seconds for a drive that is not there.
  sed -i "\#[[:space:]]${USB_MOUNT}[[:space:]]#d" /etc/fstab
  echo "UUID=${uuid} ${USB_MOUNT} ${type} ${opts},nofail,x-systemd.device-timeout=10s 0 0" >> /etc/fstab
  systemctl daemon-reload >/dev/null 2>&1 || true
  return 0
}

# The older way, kept for a drive or network folder this machine already
# connects by itself. The folder is used as typed and nothing is mounted.
configure_local_folder() {
  while true; do
    ask LOCAL_ROOT "Folder to back up into" "${LOCAL_ROOT:-/media/backup}"
    if [ ! -d "$LOCAL_ROOT" ]; then
      print warning "'$LOCAL_ROOT' does not exist. Is the drive plugged in and mounted?"
      LOCAL_ROOT=""
      continue
    fi
    if ! ( : > "${LOCAL_ROOT}/.intelis-writetest" && rm -f "${LOCAL_ROOT}/.intelis-writetest" ); then
      print warning "'$LOCAL_ROOT' is not writable."
      LOCAL_ROOT=""
      continue
    fi
    # A backup on the same disk as the original is not a backup.
    if [ "$(stat -c %d "$LOCAL_ROOT" 2>/dev/null || echo 0)" = "$(stat -c %d "$LIS_PATH" 2>/dev/null || echo 1)" ]; then
      print warning "'$LOCAL_ROOT' is on the same disk as the installation, so it would not survive a disk failure."
      confirm "Use it anyway?" || { LOCAL_ROOT=""; continue; }
    fi
    break
  done
  LOCAL_UUID=""
}

configure_local() {
  print header "External drive details"
  require_cmd lsblk
  require_cmd findmnt
  print info "Plug the backup drive in before continuing."

  local choice default key
  local -a options
  while true; do
    list_drives
    options=()
    for key in "${DRIVE_KEYS[@]}"; do
      options+=("${key}:${DRIVE_LABEL[$key]}:${DRIVE_DESC[$key]}")
    done
    options+=("rescan:Look again:After plugging the drive in."
              "folder:Type a folder instead:For a drive or network folder this machine already connects by itself.")
    [ "${#DRIVE_KEYS[@]}" -gt 0 ] ||
      print warning "No drive was found apart from this machine's own disk. Plug the backup drive in, then choose Look again."

    default="${LOCAL_UUID:-}"
    [ -n "$default" ] && [ -n "${DRIVE_DEV[$default]:-}" ] || default="${DRIVE_KEYS[0]:-rescan}"
    choose choice "$default" "Which drive should the backups go to?" "${options[@]}"

    case "$choice" in
      rescan) continue ;;
      folder) configure_local_folder; break ;;
    esac

    case "${DRIVE_FS[$choice]}" in
      vfat|msdos)
        print warning "This drive is formatted as FAT32, which cannot hold a file of 4 GB or more. Database backups grow past that, and every backup would then fail."
        print info    "Reformat it as exFAT or ext4 (this erases it): open the Disks app, select the drive, then Format Partition. Then choose Look again."
        continue ;;
    esac

    mount_backup_drive "$choice" || { confirm "Choose again?" && continue; exit 1; }
    if ! ( : > "${USB_MOUNT}/.intelis-writetest" && rm -f "${USB_MOUNT}/.intelis-writetest" ); then
      print warning "The drive is connected but cannot be written to. It may be write-protected, or damaged."
      confirm "Choose again?" && continue
      exit 1
    fi
    LOCAL_UUID="$choice"
    LOCAL_ROOT="$USB_MOUNT"
    print success "The drive is connected at ${USB_MOUNT}, and will be connected by itself after a restart"
    break
  done

  print success "Backing up to $LOCAL_ROOT"
  DEST_BASE="${LOCAL_ROOT}/backups"
  DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"
}

if $KEEP_DEST; then
  DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"
else
  case "$DEST_MODE" in
    ssh)   configure_ssh ;;
    smb)   configure_smb ;;
    local) configure_local ;;
  esac
fi

# --- destination folder -------------------------------------------------------
# dest_exec runs a command wherever the backup lands, so the folder handling
# below is written once instead of once per destination type.

dest_exec() {
  case "$DEST_MODE" in
    ssh) ssh -n -i "$SSH_KEY" -o BatchMode=yes -o ConnectTimeout=10 -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" "$1" ;;
    *)   bash -c "$1" ;;
  esac
}

print header "Preparing the backup folder"

q_dest="$(printf '%q' "$DEST_DIR")"
q_meta="$(printf '%q' "${DEST_DIR}/.lab-meta")"

# A folder from the older scripts was named after the lab alone. If it belongs to
# this installation, rename it instead of re-uploading everything from scratch.
LEGACY_DIR="${DEST_BASE}/${SANITIZED_NAME}"
if [ "$LEGACY_DIR" != "$DEST_DIR" ]; then
  q_legacy="$(printf '%q' "$LEGACY_DIR")"
  if dest_exec "test -d ${q_legacy}" 2>/dev/null && ! dest_exec "test -d ${q_dest}" 2>/dev/null; then
    legacy_uuid="$(dest_exec "awk -F= '/^lab_uuid=/{print \$2}' ${q_legacy}/.lab-meta 2>/dev/null || true" | tr -d '\r\n')"
    if [ "$legacy_uuid" = "$LAB_UUID" ]; then
      print info "Found this lab's previous backup folder. Renaming it to ${DEST_FOLDER} to keep the history."
      dest_exec "mv ${q_legacy} ${q_dest}"
      print success "Renamed"
    elif [ -z "$legacy_uuid" ]; then
      print warning "There is an older folder called '${SANITIZED_NAME}' with no identity marker."
      if confirm "Is it this lab's? Answer no to leave it alone and start a fresh folder"; then
        dest_exec "mv ${q_legacy} ${q_dest}"
        print success "Renamed to ${DEST_FOLDER}"
      fi
    fi
  fi
fi

if dest_exec "test -d ${q_dest}" 2>/dev/null; then
  remote_uuid="$(dest_exec "awk -F= '/^lab_uuid=/{print \$2}' ${q_meta} 2>/dev/null || true" | tr -d '\r\n')"
  if [ -n "$remote_uuid" ] && [ "$remote_uuid" != "$LAB_UUID" ]; then
    # Effectively unreachable now that folders carry the UUID, but a wrong answer
    # here would overwrite another lab's backup, so refuse rather than guess.
    print error "The folder ${DEST_FOLDER} already belongs to a different installation."
    print info  "Nothing has been changed. Contact support before continuing."
    exit 1
  fi
  print info "Reusing the existing folder for this lab"
fi

dest_exec "mkdir -p ${q_dest} && printf 'lab_uuid=%s\ninstance=%s\nhostname=%s\nupdated_at=%s\n' \
  $(printf '%q' "$LAB_UUID") $(printf '%q' "$SANITIZED_NAME") $(printf '%q' "$(hostname -f 2>/dev/null || hostname)") $(printf '%q' "$(date -u +%FT%TZ)") > ${q_meta}"
print success "Backup folder ready: ${DEST_DIR}"

# --- tools --------------------------------------------------------------------

if ! command -v rsync >/dev/null 2>&1; then
  print info "Installing rsync..."
  apt-get update -y >/dev/null 2>&1 || true
  apt-get install -y rsync >/dev/null || { print error "Could not install rsync."; exit 1; }
fi
require_cmd rsync

# --- save configuration -------------------------------------------------------

umask 077
cat > "$CONF_FILE" <<CONF
# InteLIS backup configuration. Written by remote-backup.sh.
# Re-run 'intelis backup setup' to change any of this.
INSTANCE_NAME='${SANITIZED_NAME}'
LAB_UUID='${LAB_UUID}'
DEST_FOLDER='${DEST_FOLDER}'
LIS_PATH='${LIS_PATH}'
DEST_MODE='${DEST_MODE}'
DEST_BASE='${DEST_BASE}'
DEST_DIR='${DEST_DIR}'
SSH_USER='${SSH_USER}'
SSH_HOST='${SSH_HOST}'
SSH_PORT='${SSH_PORT}'
SSH_KEY='${SSH_KEY}'
SMB_HOST='${SMB_HOST}'
SMB_SHARE='${SMB_SHARE}'
SMB_USER='${SMB_USER}'
SMB_VERS='${SMB_VERS}'
SMB_CRED_FILE='${SMB_CRED_FILE}'
MOUNT_POINT='${MOUNT_POINT}'
LOCAL_ROOT='${LOCAL_ROOT}'
# Set when the backup drive was chosen from the list: the drive is then mounted
# at LOCAL_ROOT by this UUID, through /etc/fstab. Empty for a typed folder.
LOCAL_UUID='${LOCAL_UUID}'
# Database history at the destination: one dump per day for HISTORY_DAYS days,
# then one per week for HISTORY_WEEKS weeks.
HISTORY_DAYS='${HISTORY_DAYS:-7}'
HISTORY_WEEKS='${HISTORY_WEEKS:-4}'
CONF
chmod 600 "$CONF_FILE"
umask 022
print success "Settings saved to $CONF_FILE"

install_backup_runner

# --- log rotation -------------------------------------------------------------

cat > /etc/logrotate.d/intelis-backup <<'LOGROTATE'
/var/log/intelis-backup.log {
    weekly
    rotate 8
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
}
LOGROTATE
print success "Log rotation configured"

# --- schedule -----------------------------------------------------------------

print header "Scheduling"
( crontab -l 2>/dev/null | grep -v "intelis-backup.sh" || true ) | crontab -
( crontab -l 2>/dev/null; echo "@reboot ${RUNNER} >/dev/null 2>&1"; echo "0 */8 * * * ${RUNNER} >/dev/null 2>&1" ) | crontab -
print success "Backups will run every 8 hours and after every restart"

# --- first backup, in the foreground -----------------------------------------
# The operator must not walk away believing this worked when it did not.

print header "Running the first backup now"
print info "This can take a while the first time. Leave this window open."
echo

if "$RUNNER"; then
  echo
  print header "All done"
  print success "Backups are set up and the first one completed."
else
  echo
  print header "Setup finished, but the first backup failed"
  print error "The settings have been saved, but the first backup did not complete."
  print info  "Read the messages above, fix the problem, then run: sudo ${RUNNER}"
  exit 1
fi

# --- summary ------------------------------------------------------------------

echo
print info "Lab            : ${SANITIZED_NAME}"
print info "Backup folder  : ${DEST_FOLDER}  (unique to this installation)"
case "$DEST_MODE" in
  ssh)   print info "Destination    : ${SSH_USER}@${SSH_HOST}:${DEST_DIR}" ;;
  smb)   print info "Destination    : //${SMB_HOST}/${SMB_SHARE} -> ${DEST_DIR}" ;;
  local) print info "Destination    : ${DEST_DIR}" ;;
esac
print info "Schedule       : every 8 hours and after every restart"
echo
print info "Check it is working : intelis backup status"
print info "Test the connection : intelis backup test"
print info "Back up right now   : intelis backup"
print info "Stop the backups    : intelis backup disable"
