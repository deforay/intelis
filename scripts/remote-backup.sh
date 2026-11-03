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
    sed -n '3,/^$/{ /^#/p; }' "$0" | sed 's/^# \{0,1\}//'
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
  download_to() { wget -q --timeout=15 --tries=1 -O "$1" "$2"; }
elif command -v curl >/dev/null 2>&1; then
  download_to() { curl -fsSL --connect-timeout 10 --max-time 30 -o "$1" "$2"; }
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
      input=$(gum input --password --header "$prompt") || input=""
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
# Files the mirror would delete or replace are moved to .deleted/ instead, and
# kept for DELETED_DAYS days. Old copies of audit-trail bundles, which are
# rewritten as they grow, are kept for DELETED_BUNDLE_DAYS days.
: "${DELETED_DAYS:=14}"; : "${DELETED_BUNDLE_DAYS:=3}"

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
    [ "$LAST_STATUS" = "warning" ] && { echo "Last attempt    : finished in ${LAST_DURATION}s, WITH A WARNING"; echo "Warning         : ${LAST_ERROR}"; }
  else
    echo "Last good backup: never (no backup has finished yet)"
  fi
  # What the backup machine said about its own snapshots at the last backup.
  # Only another Linux machine can keep them; a drive or a share has no one to.
  if [ "$DEST_MODE" = "ssh" ]; then
    snap_file="${STATE_DIR}/backup-snapshots.status"
    snap_result=""; snap_newest=""; snap_count=""; snap_message=""
    # Absent until a backup has asked the backup machine, and set -e would end
    # the status readout at the first read of a missing file.
    if [ -f "$snap_file" ]; then
      snap_result="$(sed -n 's/^result=//p' "$snap_file" | head -1)" || true
      snap_newest="$(sed -n 's/^newest=//p' "$snap_file" | head -1)" || true
      snap_count="$(sed -n 's/^count=//p' "$snap_file" | head -1)" || true
      snap_message="$(sed -n 's/^message=//p' "$snap_file" | head -1)" || true
    fi
    if [ -z "$snap_result" ]; then
      echo "Server snapshots: none. Run 'intelis backup setup' to add them."
    elif [ "$snap_result" = "ok" ] && [ -n "$snap_newest" ]; then
      echo "Server snapshots: ${snap_count:-0} kept, newest ${snap_newest:0:4}-${snap_newest:4:2}-${snap_newest:6:2}"
    elif [ "$snap_result" = "ok" ]; then
      echo "Server snapshots: set up, the first is taken tonight"
    else
      echo "Server snapshots: ${snap_message:-$snap_result}"
    fi
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
  local now epoch env_tmp json_tmp
  now="$(date -u +%FT%TZ)"; epoch="$(date +%s)"
  if [ "$st" = "ok" ]; then
    LAST_SUCCESS_AT="$now"; LAST_SUCCESS_EPOCH="$epoch"; LAST_SIZE="$size"; LAST_ERROR=""
  elif [ "$st" = "warning" ]; then
    # The copy finished, but left something out on purpose; msg says what.
    LAST_SUCCESS_AT="$now"; LAST_SUCCESS_EPOCH="$epoch"; LAST_SIZE="$size"; LAST_ERROR="$msg"
  else
    LAST_FAILURE_AT="$now"; LAST_ERROR="$msg"
  fi
  LAST_STATUS="$st"; LAST_DURATION="$duration"

  mkdir -p "$STATE_DIR"
  umask 022
  env_tmp="$(mktemp "${STATE_DIR}/backup-status.env.XXXXXX")"
  json_tmp="$(mktemp "${STATE_DIR}/backup-status.json.XXXXXX")"

  # The status file is sourced by the status command. %q keeps every value data,
  # even when an external command returns quotes or shell punctuation in an error.
  {
    printf 'LAST_STATUS=%q\n' "$LAST_STATUS"
    printf 'LAST_RUN_AT=%q\n' "$now"
    printf 'LAST_SUCCESS_AT=%q\n' "$LAST_SUCCESS_AT"
    printf 'LAST_SUCCESS_EPOCH=%q\n' "$LAST_SUCCESS_EPOCH"
    printf 'LAST_FAILURE_AT=%q\n' "$LAST_FAILURE_AT"
    printf 'LAST_ERROR=%q\n' "$LAST_ERROR"
    printf 'LAST_SIZE=%q\n' "$LAST_SIZE"
    printf 'LAST_DURATION=%q\n' "$LAST_DURATION"
    printf 'DB_DUMP_AGE_HOURS=%q\n' "${DB_DUMP_AGE_HOURS:--1}"
    printf 'HISTORY_OLDEST=%q\n' "$HISTORY_OLDEST"
    printf 'HISTORY_NEWEST=%q\n' "$HISTORY_NEWEST"
    printf 'HISTORY_COUNT=%q\n' "${HISTORY_COUNT:-0}"
  } > "$env_tmp"

  cat > "$json_tmp" <<STATUS
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
  # Readers must see either the previous complete status or the new complete
  # status. A killed backup must not leave a half-written file behind.
  mv -f "$env_tmp" "$STATUS_ENV"
  mv -f "$json_tmp" "$STATUS_JSON"
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
# The supported main-database names and archive formats. Keep this specific so
# interfacing dumps and pre-restore safety copies never stand in for lab data.
MAIN_DUMP=( \( -name 'vlsm-*' -o -name 'intelis-*' \)
            \( -name '*.sql' -o -name '*.sql.gz' -o -name '*.sql.zst'
               -o -name '*.sql.gpg' -o -name '*.sql.gz.gpg' -o -name '*.sql.zst.gpg' \) )
if [ -d "$DB_DUMP_DIR" ]; then
  newest_dump=$(find "$DB_DUMP_DIR" -maxdepth 1 -type f "${MAIN_DUMP[@]}" \
    -printf '%T@\n' 2>/dev/null | sort -nr | head -1 | cut -d. -f1)
  if [ -n "${newest_dump:-}" ]; then
    DB_DUMP_AGE_HOURS=$(( ( $(date +%s) - newest_dump ) / 3600 ))
    if [ "$DB_DUMP_AGE_HOURS" -gt 24 ]; then
      fail "The newest database dump is ${DB_DUMP_AGE_HOURS} hours old. The destination was not changed. Check that root's crontab still has the InteLIS scheduler line (sudo crontab -l | grep cron.sh), then run: intelis backup"
    else
      print info "Newest database dump is ${DB_DUMP_AGE_HOURS} hours old"
    fi
  else
    fail "No database dump was found in ${DB_DUMP_DIR}. The destination was not changed. Run 'intelis backup' to create and send a complete backup."
  fi
else
  fail "The database backup folder ${DB_DUMP_DIR} does not exist. The destination was not changed. Run 'intelis backup' to create and send a complete backup."
fi

# --- the audit trail ----------------------------------------------------------
# Changes written to var/audit-trail are deleted from the database, so that
# folder is the only record of them, and the copy at the destination is the
# only other one. The mirror deletes whatever this machine no longer has. That
# is right for a dump that housekeeping removed. It is wrong for an audit trail
# that a mistake, a disk fault or an unfinished restore has emptied: the next
# run would delete the last copy. So when the audit trail here looks lost, it is
# left out of this run. rsync never deletes an excluded path, so the copy at the
# destination stays exactly as it was, and the run finishes with a warning.
#
# Looks lost means one of two things:
#   - a restore of the audit trail started on this machine and did not finish
#     (restore_audit_trail leaves var/.audit-trail-restore-incomplete); or
#   - it holds less than 80% of the bytes the last good run recorded.
# Bytes rather than files, because bundling turns thousands of files into one
# and keeps the bytes. Nothing else removes audit history, so the total only
# grows, and the 20% margin absorbs the small changes that rewriting a bundle
# makes. The count lives in .lab-meta at the destination, so a rebuilt machine
# is still compared with what the backup holds.
AUDIT_LOCAL="${LIS_PATH}/var/audit-trail"
AUDIT_BYTES=0
[ -d "$AUDIT_LOCAL" ] && { AUDIT_BYTES=$(du -sb "$AUDIT_LOCAL" 2>/dev/null | cut -f1) || true; }
AUDIT_BYTES=${AUDIT_BYTES:-0}
AUDIT_BYTES_BACKUP="$(dest_exec "awk -F= '/^audit_bytes=/{print \$2}' ${Q_DEST}/.lab-meta 2>/dev/null || true" | tr -cd '0-9')" || true
human_bytes() {
  awk -v b="$1" 'BEGIN {
    if (b >= 1073741824) printf "%.1f GB", b / 1073741824
    else if (b >= 1048576) printf "%.0f MB", b / 1048576
    else printf "%.0f KB", b / 1024 }'
}
AUDIT_WARNING=""
if [ -e "${LIS_PATH}/var/.audit-trail-restore-incomplete" ]; then
  AUDIT_WARNING="The audit trail is still being restored on this machine, so the audit trail in the backup was not changed. Finish the restore with the command it printed, or run 'intelis restore' and choose 'Only the audit trail'."
elif [ -n "$AUDIT_BYTES_BACKUP" ] && [ "$AUDIT_BYTES" -lt $(( AUDIT_BYTES_BACKUP / 5 * 4 )) ]; then
  AUDIT_WARNING="This machine holds $(human_bytes "$AUDIT_BYTES") of audit trail, and the backup holds $(human_bytes "$AUDIT_BYTES_BACKUP"), so part of it is missing here. The audit trail in the backup was not changed. To put it back, run 'intelis restore' and choose 'Only the audit trail'."
fi
if [ -n "$AUDIT_WARNING" ]; then
  print warning "$AUDIT_WARNING"
  AUDIT_EXCLUDE=(--exclude=/var/audit-trail/)
  # Keep the last good figure, so the next run is compared with the backup and
  # not with what is left here.
  AUDIT_BYTES_RECORD="$AUDIT_BYTES_BACKUP"
else
  AUDIT_EXCLUDE=()
  AUDIT_BYTES_RECORD="$AUDIT_BYTES"
fi

# --- what to leave out --------------------------------------------------------
# composer.lock is deliberately kept: vendor/ is excluded, so the lock file is
# what makes the restored copy reproducible.
#
# The application itself is left out too. Every restore installs a fresh copy
# of it (setup.sh, or the installation already on the machine) and takes only
# the dumps, the settings archive, the audit trail and public/uploads from the
# backup. These are named one by one rather than the backup listing what to
# keep: a folder added later, which may hold data, is then copied until it is
# looked at, instead of being lost. var/track-api and var/metadata are rebuilt
# by the application.
SOURCE_PATHS=(
  .ddev .devcontainer .dockerignore .env.example .githooks .github .gitignore .hgignore
  .sonarcloud.properties Dockerfile LICENSE.md README.md app bin bootstrap.php composer.json
  cron.sh crunz.yml db-tools.php dev docker docker-compose.yml docs intelis
  mkdocs-requirements.txt mkdocs.yml phpunit.xml.dist run-once scripts sql sys tests user.ini
  public/.htaccess public/api public/assets public/favicon.ico public/files public/index.php
  public/robots.txt public/sitemap.xml public/sse
  var/track-api var/metadata
)

EXCLUDE_LIST="$(mktemp /tmp/intelis-backup-excludes.XXXXXX)"
trap 'rm -f "$EXCLUDE_LIST"' EXIT
cat > "$EXCLUDE_LIST" <<'EXCLUDES'
/public/temporary/
/var/logs/
/var/cache/
/var/.audit-trail-restore-incomplete
/var/.audit-trail-restore-plan
/var/.audit-trail-restore-plan.merge
/var/.audit-trail-restore-plan.held
/var/.audit-trail-restore-plan.held.tmp
/var/.audit-trail.lock
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
printf '/%s\n' "${SOURCE_PATHS[@]}" >> "$EXCLUDE_LIST"

# --- rsync options per destination --------------------------------------------

# Split deliberately. RSYNC_MODE_OPTS is everything about HOW to reach the
# destination — transport, compression, and the compatibility flags a filesystem
# that cannot hold POSIX metadata needs. RSYNC_FILTER_OPTS is everything about
# WHAT to send. The verification pass below reuses the transport but must not
# reuse the filters: it works from an explicit file list, and --delete needs a
# whole-tree walk, which is the cost this is here to avoid.
# Nothing the mirror removes is gone at once. --backup moves each file it would
# delete or replace into a folder for this run, under .deleted/ at the
# destination, so a file deleted here by mistake, or emptied, can still be
# copied back for DELETED_DAYS days. The mirror excludes .deleted/, so it never
# deletes from it. A relative --backup-dir is taken from the destination folder.
RUN_STAMP="$(date -u +%Y%m%d-%H%M%S)"
RSYNC_FILTER_OPTS=(--delete --backup --backup-dir=".deleted/${RUN_STAMP}" --partial-dir=.rsync-partial --timeout=900
  --exclude-from="$EXCLUDE_LIST" --exclude=.lab-meta --exclude=/.history/ --exclude=/.deleted/ "${AUDIT_EXCLUDE[@]}")
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
  fail "Verification found files that differ at the destination. The backup is not marked successful. Run 'intelis backup' again."
fi

# Check the files needed for recovery on every run, including files rsync did not
# copy this time. Size and timestamp comparisons cannot detect same-size damage
# at rest. Restricting the checksum walk to db and config keeps it inexpensive.
verify_recovery_files() {
  local out differing
  out=$(rsync "${RSYNC_MODE_OPTS[@]}" --checksum --dry-run --out-format='%i|%n' \
              --include='/backups/' \
              --include='/backups/db/' --include='/backups/db/***' \
              --include='/backups/config/' --include='/backups/config/***' \
              --exclude='*' "${LIS_PATH}/" "$RSYNC_TARGET" 2>/dev/null) || return 1
  differing=$(printf '%s\n' "$out" | grep -c '^[<>]f' || true)
  [ "${differing:-0}" -eq 0 ]
}

if verify_recovery_files; then
  print success "Verified: database and settings backups match by checksum"
else
  fail "A database or settings backup differs at the destination. The backup is not marked successful. Run 'intelis backup' again."
fi

# Housekeeping can run while rsync works. Refuse success if it removed the last
# main dump after the preflight check, even if rsync itself completed cleanly.
if [ -z "$(find "$DB_DUMP_DIR" -maxdepth 1 -type f "${MAIN_DUMP[@]}" -print -quit 2>/dev/null)" ]; then
  fail "The main database dump disappeared while files were being copied. The backup is not marked successful. Run 'intelis backup' again."
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

# --- deleted files ------------------------------------------------------------
# Each run's .deleted/<YYYYMMDD-HHMMSS>/ holds what that run removed or
# replaced, at its own path. Three things are trimmed here:
#   - backups/ in every run folder: old dumps and settings archives. The dated
#     history above already keeps one per day, and a dump is the largest thing
#     a lab has, so a second copy here would only double the space.
#   - audit-trail bundles in runs older than DELETED_BUNDLE_DAYS days. Bundling
#     rewrites a month's bundle every day it grows, so each old copy is nearly
#     the whole of the new one.
#   - every run older than DELETED_DAYS days.
# Days are counted from the newest run folders, not from the clock, for the same
# reason as the history: a lab with a wrong date must not prune everything.
prune_deleted() {
  local listing day keep_days=() bundle_days=() d cmds=()
  # shellcheck disable=SC2016  # expanded at the destination, not here
  listing="$(dest_exec "cd ${Q_DEST} && { [ ! -d .deleted ] || { rm -rf .deleted/*/backups; rmdir .deleted/${RUN_STAMP} 2>/dev/null; ls -1 .deleted; }; }")" || return 1
  # Each pipeline ends in "|| true": a grep that matches nothing fails, and with
  # set -E the ERR trap would fire inside the substitution and print into it.
  mapfile -t keep_days < <({ printf '%s\n' "$listing" | tr -d '\r' | grep -E '^[0-9]{8}-[0-9]{6}$' | cut -c1-8 | sort -ru | head -n "$DELETED_DAYS"; } || true)
  bundle_days=("${keep_days[@]:0:$DELETED_BUNDLE_DAYS}")
  while read -r d; do
    [ -n "$d" ] || continue
    day="${d:0:8}"
    if [[ " ${keep_days[*]} " != *" ${day} "* ]]; then
      cmds+=("rm -rf \".deleted/${d}\" || e=1")
    elif [[ " ${bundle_days[*]} " != *" ${day} "* ]]; then
      cmds+=("{ [ ! -d \".deleted/${d}/var/audit-trail\" ] || find \".deleted/${d}/var/audit-trail\" -name '*.zip' -type f -delete; } || e=1")
    fi
  done < <({ printf '%s\n' "$listing" | tr -d '\r' | grep -E '^[0-9]{8}-[0-9]{6}$'; } || true)
  DELETED_OLDEST=""
  [ "${#keep_days[@]}" -eq 0 ] || DELETED_OLDEST="${keep_days[-1]:0:4}-${keep_days[-1]:4:2}-${keep_days[-1]:6:2}"
  [ "${#cmds[@]}" -gt 0 ] || return 0
  dest_exec "cd ${Q_DEST} && e=0; $(printf '%s; ' "${cmds[@]}") exit \$e"
}

DELETED_OLDEST=""
if prune_deleted; then
  [ -z "$DELETED_OLDEST" ] || print info "Deleted and replaced files are kept at the destination since ${DELETED_OLDEST}, in .deleted/"
else
  print warning "Could not tidy the deleted files kept at the destination. The copy itself is complete; the next backup tries again."
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

# The backup machine's own note on its snapshots, kept here for 'backup status'.
# Dates and counts only. A machine without snapshots has no note, and the
# status then says how to add them.
if [ "$DEST_MODE" = "ssh" ]; then
  snap_note="$(dest_exec "cat /var/lib/intelis-backup-server/snapshots-$(printf '%q' "$SSH_USER").status 2>/dev/null || true" \
    | tr -d '\r' | grep -E '^(version|result|message|last_run|count|oldest|newest)=' || true)"
  printf '%s\n' "$snap_note" > "${STATE_DIR}/backup-snapshots.status.tmp" 2>/dev/null &&
    mv -f "${STATE_DIR}/backup-snapshots.status.tmp" "${STATE_DIR}/backup-snapshots.status" 2>/dev/null || true
fi

# Copies made before the application was left out still hold it. rsync never
# deletes an excluded path, so the same named paths are removed here, and only
# those. Running it every time costs one stat per path.
q_source=""
for source_path in "${SOURCE_PATHS[@]}"; do q_source+=" $(printf '%q' "$source_path")"; done
dest_exec "cd ${Q_DEST} && rm -rf --${q_source}" ||
  print warning "Could not remove the old copy of the application from the destination. The next backup tries again."

# Restore listings use this timestamp. Write it only after the copy and checksum
# verification finish, so it never claims that a failed run updated the backup.
meta_tmp="${DEST_DIR}/.lab-meta.tmp.$$"
q_meta_tmp="$(printf '%q' "$meta_tmp")"
q_meta="$(printf '%q' "${DEST_DIR}/.lab-meta")"
if ! dest_exec "printf 'lab_uuid=%s\ninstance=%s\nhostname=%s\nupdated_at=%s\n' \
  $(printf '%q' "$LAB_UUID") $(printf '%q' "$INSTANCE_NAME") \
  $(printf '%q' "$(hostname -f 2>/dev/null || hostname)") \
  $(printf '%q' "$(date -u +%FT%TZ)") > ${q_meta_tmp} && \
  { [ -z $(printf '%q' "$AUDIT_BYTES_RECORD") ] || printf 'audit_bytes=%s\n' $(printf '%q' "$AUDIT_BYTES_RECORD") >> ${q_meta_tmp}; } && \
  mv -f ${q_meta_tmp} ${q_meta}"; then
  fail "The files were copied, but the destination metadata could not be updated. Run 'intelis backup' again."
fi

if [ -n "$AUDIT_WARNING" ]; then
  write_status warning "$AUDIT_WARNING" "$BACKUP_SIZE" "$SECONDS"
  print warning "Backup finished in ${SECONDS}s, without the audit trail. The reason is in the warning above."
else
  write_status ok "" "$BACKUP_SIZE" "$SECONDS"
  print success "Backup finished in ${SECONDS}s. ${AVAILABLE_GB} GB free at the destination."
fi
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
# --- snapshots on the backup machine -------------------------------------------
#
# Every lab's key can write to its own backup folder, so a lab machine that is
# hacked or encrypted by ransomware can also delete or encrypt its backups, and
# .history/ and .deleted/ with them, because they sit in the same folder. The
# snapshots are the copy no lab can reach: root on the backup machine takes one
# each night into /var/backups/intelis-snapshots, a folder only root can open.
#
# It is installed with the administrator login that also creates the backup
# account, and again whenever a lab is set up that way, so every lab keeps it
# current. SNAPSHOT_VERSION lets setup see an older copy and offer to replace it.
SNAPSHOT_VERSION=1
SNAPSHOT_SCRIPT_PATH=/usr/local/sbin/intelis-backup-snapshots

# The flush-left body is a quoted heredoc, as for the backup runner above.
snapshot_server_script() {
cat <<'SNAPSHOT_SCRIPT'
#!/bin/bash
# InteLIS backup machine: daily snapshots of the labs' backups.
#
# Installed by remote-backup.sh through the backup machine's administrator
# login. Runs as root from /etc/cron.d/intelis-backup-snapshots.
#
#   intelis-backup-snapshots run       take today's snapshot (what cron runs)
#   intelis-backup-snapshots list      the snapshots kept, and the labs in each
#   intelis-backup-snapshots restore   copy one lab's folder out of a snapshot
#
# What is kept: each backup account's backups/ folder, every lab in it, except
# backups/db/ (the newest dumps; .history/db/ holds one per day) and .deleted/.
# Unchanged files are hard links to the previous snapshot, so a snapshot costs
# only what changed that day. The newest KEEP_DAYS snapshots are kept, then one
# per week for KEEP_WEEKS weeks, counted from the snapshots present rather than
# from the clock. A snapshot is skipped, never forced, when the disk is low:
# every lab's live backups share this disk.
SNAPSHOT_VERSION=__SNAPSHOT_VERSION__
set -euo pipefail
# cron runs this with PATH=/usr/bin:/bin, and runuser is in /usr/sbin.
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
export PATH

SELF="$(readlink -f -- "$0")"
CONF=/etc/intelis-backup-server.conf
SNAP_ROOT=/var/backups/intelis-snapshots
STATE_DIR=/var/lib/intelis-backup-server
LOG=/var/log/intelis-backup-snapshots.log
USERS=""
KEEP_DAYS=7
KEEP_WEEKS=4
MIN_FREE_PERCENT=15
MIN_FREE_GB=5
# shellcheck disable=SC1090
[ -f "$CONF" ] && . "$CONF"

[ "$(id -u)" -eq 0 ] || { echo "Run as root: sudo $0 $*"; exit 1; }
umask 077
mkdir -p "$SNAP_ROOT" "$STATE_DIR"
chmod 700 "$SNAP_ROOT"
chmod 755 "$STATE_DIR"

say() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }

# pick <var> <prompt> <items...>: a numbered menu. Anything but one of the
# numbers shown changes nothing.
pick() {
  local __var=$1 prompt=$2 i answer
  shift 2
  for i in $(seq 1 $#); do echo "  $i) ${!i}"; done
  read -r -p "$prompt " answer
  if [[ "$answer" =~ ^[0-9]+$ ]] && [ "$answer" -ge 1 ] && [ "$answer" -le $# ]; then
    printf -v "$__var" '%s' "${!answer}"
    return 0
  fi
  echo "Nothing was changed."
  return 1
}

# A status anyone on this machine can read, labs included. It names dates and
# counts only, never a lab's data.
write_status() { # write_status <user> <result> <message>
  local u=$1 result=$2 message=$3 days oldest newest count tmp
  days="$(snapshot_days "$u")"
  count="$(printf '%s\n' "$days" | grep -c . || true)"
  oldest="$(printf '%s\n' "$days" | head -1)"
  newest="$(printf '%s\n' "$days" | tail -1)"
  tmp="$(mktemp "$STATE_DIR/.status.XXXXXX")"
  printf 'version=%s\nresult=%s\nmessage=%s\nlast_run=%s\ncount=%s\noldest=%s\nnewest=%s\n' \
    "$SNAPSHOT_VERSION" "$result" "$message" "$(date -u +%FT%TZ)" "$count" "$oldest" "$newest" >"$tmp"
  chmod 644 "$tmp"
  mv -f "$tmp" "$STATE_DIR/snapshots-$u.status"
}

snapshot_days() { ls -1 "$SNAP_ROOT/$1" 2>/dev/null | grep -E '^[0-9]{8}$' | sort || true; }

# The account's backups folder, resolved. The account owns it and could swap
# it for a link to anywhere, which root would then read, so a folder that is a
# link, or that resolves anywhere but <home>/backups, is refused.
backups_of() {
  local h real
  h="$(getent passwd "$1" | cut -d: -f6)"
  [ -n "$h" ] && [ -d "$h/backups" ] && [ ! -L "$h/backups" ] || return 1
  real="$(realpath -e -- "$h/backups")" || return 1
  [ "$real" = "$(realpath -e -- "$h")/backups" ] || return 1
  printf '%s' "$real"
}

prune() { # prune <user>
  local u=$1 n=0 weeks=0 wk last_wk="" day
  local -A keep=()
  while read -r day; do
    [ -n "$day" ] || continue
    n=$((n + 1))
    if [ "$n" -le "$KEEP_DAYS" ]; then
      keep[$day]=1
      [ "$n" -eq "$KEEP_DAYS" ] && last_wk="$(date -u -d "$day" +%G-%V 2>/dev/null || echo "$day")"
    else
      wk="$(date -u -d "$day" +%G-%V 2>/dev/null || echo "$day")"
      if [ "$wk" != "$last_wk" ] && [ "$weeks" -lt "$KEEP_WEEKS" ]; then
        keep[$day]=1
        weeks=$((weeks + 1))
      fi
      last_wk="$wk"
    fi
  done < <(snapshot_days "$u" | sort -r)
  while read -r day; do
    [ -n "$day" ] || continue
    [ -n "${keep[$day]:-}" ] || {
      say "Removing the snapshot of $day for $u"
      rm -rf -- "${SNAP_ROOT:?}/$u/$day" "${SNAP_ROOT:?}/$u/.taken-$day"
    }
  done < <(snapshot_days "$u")
  rm -rf -- "$SNAP_ROOT/$u"/.partial-* 2>/dev/null || true
}

take() { # take <user>
  local u=$1 src today prev dst avail size pct
  if ! src="$(backups_of "$u")"; then
    write_status "$u" none "The account $u has no backups folder, or it is not a plain folder in its home."
    return 0
  fi
  today="$(date -u +%Y%m%d)"
  mkdir -p "$SNAP_ROOT/$u"
  if [ -d "$SNAP_ROOT/$u/$today" ]; then
    write_status "$u" ok "Today's snapshot was already taken."
    return 0
  fi

  prev="$(snapshot_days "$u" | tail -1)"

  # A lab's newest dumps are left out only when its .history/db holds a main
  # database dump (vlsm-* or intelis-*, not an interfacing one) from the last
  # two days. Otherwise, with history missing, failing or stale, its snapshot
  # would hold no current database. A lab whose name is not plain is not given
  # an exclude pattern, so it keeps them too. Read as the account.
  # Only the main database's dumps are left out, never an interfacing one:
  # .history/ may not hold those. The same choices feed the estimate below,
  # through skip_paths, so it counts what the copy will actually send.
  local -a excludes=(--exclude='.rsync-partial/') skip_paths=()
  local lab
  for lab in "$src"/*/; do
    lab="$(basename "$lab")"
    [[ "$lab" =~ ^[A-Za-z0-9._-]+$ ]] || continue
    excludes+=(--exclude="/$lab/.deleted/")
    skip_paths+=(-path "$src/$lab/.deleted" -prune -o)
    if [ -n "$(runuser -u "$u" -- find "$src/$lab/.history/db" -maxdepth 1 -type f -mmin -2880 \
         -regextype posix-extended -regex '.*/(vlsm|intelis)-[^/]*\.sql(\.gz|\.zst|\.zip)?(\.gpg)?' \
         -print -quit 2>/dev/null)" ]; then
      excludes+=(--exclude="/$lab/backups/db/vlsm-*" --exclude="/$lab/backups/db/intelis-*")
      skip_paths+=(-path "$src/$lab/backups/db/vlsm-*" -prune -o -path "$src/$lab/backups/db/intelis-*" -prune -o)
    fi
  done

  # What this snapshot will add: everything the first time, then only files
  # that arrived since the last one (their change time on this disk is newer
  # than that snapshot). It is skipped unless the reserve still holds after it:
  # every lab's live backups share this disk.
  local incoming reserve
  # The reference is the marker written beside the last snapshot or, if that
  # is missing, the snapshot folder itself, whose change time is set when it
  # was moved into place. Both are in the folder only root owns.
  local taken="$SNAP_ROOT/$u/.taken-$prev"
  [ -n "$prev" ] && { [ -f "$taken" ] && [ ! -L "$taken" ]; } || taken="$SNAP_ROOT/$u/$prev"
  # Read as the account, like the copy below: root only looks at the reference.
  if [ -n "$prev" ] && [ ! -L "$taken" ] && [ -e "$taken" ]; then
    incoming="$(runuser -u "$u" -- find "$src" "${skip_paths[@]}" -type f -newerct "@$(stat -c %Z -- "$taken")" -printf '%s\n' 2>/dev/null \
      | awk '{ s += $1 } END { printf "%d", s / 1024 }')"
  else
    incoming="$(runuser -u "$u" -- find "$src" "${skip_paths[@]}" -type f -printf '%s\n' 2>/dev/null \
      | awk '{ s += $1 } END { printf "%d", s / 1024 }')"
  fi
  read -r size avail < <(df -Pk "$SNAP_ROOT" | awk 'NR==2 {print $2, $4}')
  reserve=$(( size * MIN_FREE_PERCENT / 100 ))
  [ "$reserve" -ge $((MIN_FREE_GB * 1048576)) ] || reserve=$((MIN_FREE_GB * 1048576))
  if [ $(( avail - ${incoming:-0} )) -lt "$reserve" ]; then
    pct=$(( avail * 100 / (size > 0 ? size : 1) ))
    say "Skipped the snapshot for $u: it needs about $(( ${incoming:-0} / 1024 )) MB, and only ${pct}% of the disk is free."
    write_status "$u" skipped "No snapshot was taken: the disk is too full (${pct}% free). The labs' backups come first."
    return 0
  fi

  dst="$SNAP_ROOT/$u/.partial-$today"
  rm -rf -- "$dst"
  # The reading side runs as the account, through rsync's remote-shell hook
  # (the _as command below), so a link the account puts anywhere in its folder,
  # even between the check above and this copy, reaches only what the account
  # could read itself. Root only writes, into its own folder.
  local rc=0
  rsync -a --delete "${excludes[@]}" ${prev:+--link-dest="$SNAP_ROOT/$u/$prev"} \
    -e "$SELF _as $u" "localhost:$src/" "$dst/" || rc=$?
  # 24: files vanished while they were copied, as a lab backing up at the same
  # moment rotates its dumps. The rest is a good snapshot.
  if [ "$rc" -eq 0 ] || [ "$rc" -eq 24 ]; then
    if ! mv -T -- "$dst" "$SNAP_ROOT/$u/$today"; then
      rm -rf -- "$dst"
      say "The snapshot for $u could not be put in place."
      write_status "$u" failed "The snapshot could not be put in place. See $LOG on the backup machine."
      return 1
    fi
    # When it was taken, for the next night's estimate of what arrived since.
    # Kept beside the snapshot, in the folder only root owns, not inside it:
    # everything inside came from the account, links included.
    rm -f -- "$SNAP_ROOT/$u/.taken-$today"
    : > "$SNAP_ROOT/$u/.taken-$today"
    prune "$u"
    say "Took the snapshot of $today for $u"
    write_status "$u" ok "Snapshot taken."
  else
    rm -rf -- "$dst"
    say "The snapshot for $u failed."
    write_status "$u" failed "The snapshot failed. See $LOG on the backup machine."
    return 1
  fi
}

cmd_run() {
  exec 9>/run/intelis-backup-snapshots.lock
  flock -n 9 || { say "Another snapshot is running."; return 0; }
  local u rc=0
  for u in $USERS; do take "$u" || rc=1; done
  return "$rc"
}

cmd_list() {
  local u day
  for u in $USERS; do
    echo "Backups of the account $u:"
    while read -r day; do
      [ -n "$day" ] || continue
      printf '  %s-%s-%s:' "${day:0:4}" "${day:4:2}" "${day:6:2}"
      (cd "$SNAP_ROOT/$u/$day" && ls -1) | tr '\n' ' '
      echo
    done < <(snapshot_days "$u" | sort -r)
  done
}

# One lab's folder, from one snapshot, copied next to the live one as
# <folder>-snapshot-<date>. The live folder is never touched. backups/db/ is
# left out of snapshots, so the dumps that day's .history/ kept are put there,
# and `intelis restore` then reads the copy like any lab's folder.
cmd_restore() {
  # The nightly run prunes snapshots, so it must not delete the one being read.
  exec 9>/run/intelis-backup-snapshots.lock
  if ! flock -n 9; then
    echo "A snapshot is being taken. Waiting for it to finish..."
    flock 9
  fi
  local u day folder src dest
  local -a users days folders
  read -r -a users <<<"$USERS"
  [ "${#users[@]}" -gt 0 ] || { echo "No backup accounts are set up here."; return 1; }
  u="${users[0]}"
  if [ "${#users[@]}" -gt 1 ]; then pick u "Which backup account?" "${users[@]}" || return 1; fi
  mapfile -t days < <(snapshot_days "$u" | sort -r)
  [ "${#days[@]}" -gt 0 ] || { echo "There are no snapshots for $u yet."; return 1; }
  # Every lab found in any snapshot, so one deleted from the newest is still here.
  mapfile -t folders < <(for d in "${days[@]}"; do (cd "$SNAP_ROOT/$u/$d" && ls -1); done | sort -u)
  [ "${#folders[@]}" -gt 0 ] || { echo "The snapshots hold no labs."; return 1; }
  pick folder "Which lab?" "${folders[@]}" || return 1
  # The name came from a folder the account made, and goes into a tar pattern.
  [[ "$folder" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "That folder name cannot be restored here. Nothing was changed."; return 1; }

  # The lab's size in each snapshot. After a lab machine was hacked or
  # encrypted, every snapshot since holds the damage, and the good one is the
  # last before the size dropped.
  local -a options=()
  for d in "${days[@]}"; do
    [ -d "$SNAP_ROOT/$u/$d/$folder" ] || continue
    options+=("$d  $(du -sh --apparent-size "$SNAP_ROOT/$u/$d/$folder" 2>/dev/null | cut -f1)")
  done
  echo "The lab's size in each snapshot, newest first. Choose the last one before it dropped:"
  pick day "Which date?" "${options[@]}" || return 1
  day="${day%% *}"

  # Root only reads, from its own folder. Every write runs as the backup
  # account (runuser), so a link the account left at the new name, or in the
  # folders on the way, can only ever reach what the account could write anyway.
  local home name
  src="$SNAP_ROOT/$u/$day/$folder"
  # Root reads from here, and the account made every name inside it, links
  # included. A folder is read only when it is a real one: its resolved path is
  # itself, so no link sits anywhere on the way.
  real_dir() { [ -d "$1" ] && [ ! -L "$1" ] && [ "$(realpath -e -- "$1")" = "$1" ]; }
  real_dir "$src" || { echo "That lab's folder in the snapshot is not a plain folder. Nothing was changed."; return 1; }
  # Ransomware can delete the whole backups folder. It is made again, as the
  # account, unless something that is not a folder holds the name.
  local h
  h="$(getent passwd "$u" | cut -d: -f6)"
  if [ -n "$h" ] && [ ! -e "$h/backups" ] && [ ! -L "$h/backups" ]; then
    runuser -u "$u" -- mkdir -m 700 -- "$h/backups"
  fi
  home="$(backups_of "$u")" || { echo "The account $u has no backups folder it can use. Nothing was changed."; return 1; }
  name="${folder}-snapshot-${day}"
  dest="$home/$name"
  if runuser -u "$u" -- test -e "$dest" || runuser -u "$u" -- test -L "$dest"; then
    echo "$dest already exists. Nothing was changed."
    return 1
  fi
  echo "Copying $folder from ${day:0:4}-${day:4:2}-${day:6:2} to $dest ..."
  tar -C "$SNAP_ROOT/$u/$day" -cf - -- "$folder" \
    | runuser -u "$u" -- tar -C "$home" --no-same-owner --transform="s|^${folder}|${name}|" -xf -
  runuser -u "$u" -- mkdir -p "$dest/backups/db"
  if real_dir "$src/.history/db"; then
    tar -C "$src/.history/db" -cf - . | runuser -u "$u" -- tar -C "$dest/backups/db" --no-same-owner -xf -
  fi
  if real_dir "$src/.history/config" && [ ! -e "$src/backups/config" ] && [ ! -L "$src/backups/config" ]; then
    runuser -u "$u" -- mkdir -p "$dest/backups/config"
    tar -C "$src/.history/config" -cf - . | runuser -u "$u" -- tar -C "$dest/backups/config" --no-same-owner -xf -
  fi
  runuser -u "$u" -- chmod 700 "$dest"
  echo "Done. On the lab machine, run: intelis restore"
  echo "and choose the lab ${folder}-snapshot-${day}. The live backup folder was not changed."
}

case "${1:-}" in
  # rsync's remote shell for take(): `_as <user> <host> rsync --server ...`.
  # Runs only rsync, and only as a listed backup account.
  _as)
    u="${2:-}"
    case " $USERS " in *" $u "*) ;; *) echo "Not a backup account: $u" >&2; exit 1 ;; esac
    [ "${4:-}" = "rsync" ] || { echo "Only rsync runs this way." >&2; exit 1; }
    shift 3
    exec runuser -u "$u" -- "$@"
    ;;
  # Right after installing: a status for every account that has none yet, so
  # the labs' `backup status` says the snapshots are set up, not that there are
  # none, until the first night.
  init)
    for u in $USERS; do
      [ -e "$STATE_DIR/snapshots-$u.status" ] || write_status "$u" ok "Set up. The first snapshot is taken tonight."
    done
    ;;
  run) cmd_run >>"$LOG" 2>&1 ;;
  list) cmd_list ;;
  restore) cmd_restore ;;
  *) echo "Usage: $0 run|list|restore|init"; exit 2 ;;
esac
SNAPSHOT_SCRIPT
}

# Root's lines on the backup machine that install or update the snapshots for
# backup account $1. Idempotent: every lab set up through the administrator
# login runs them again, which keeps the script current.
snapshot_install_lines() {
  local user=$1
  printf '%s\n' \
    "need=''; command -v rsync >/dev/null 2>&1 || need=rsync; [ -d /etc/cron.d ] && { command -v cron >/dev/null 2>&1 || command -v crond >/dev/null 2>&1; } || need=\"\$need cron\"" \
    "if [ -n \"\$need\" ]; then apt-get install -y \$need >/dev/null 2>&1 || { apt-get update >/dev/null 2>&1 && apt-get install -y \$need >/dev/null 2>&1; } || { echo \"Could not install \$need on the backup machine.\"; exit 1; }; fi" \
    "systemctl enable --now cron >/dev/null 2>&1 || true"
  # The script travels as short base64 lines in a heredoc. These lines reach
  # root's shell through `ssh -t ... | sudo sh`, and sudo can pass its input
  # through a terminal, which cuts every line at 4096 bytes. One long line
  # holding the whole script was cut short there, and sh stopped at
  # "Unterminated quoted string".
  printf '%s\n' "base64 -d > ${SNAPSHOT_SCRIPT_PATH}.new <<'INTELIS_SNAPSHOT_SCRIPT'"
  snapshot_server_script | sed "s/__SNAPSHOT_VERSION__/${SNAPSHOT_VERSION}/" | base64 | fold -w 76
  printf '%s\n' "INTELIS_SNAPSHOT_SCRIPT" \
    "chmod 755 ${SNAPSHOT_SCRIPT_PATH}.new && mv -f ${SNAPSHOT_SCRIPT_PATH}.new ${SNAPSHOT_SCRIPT_PATH}" \
    "c=/etc/intelis-backup-server.conf; touch \"\$c\"; chmod 644 \"\$c\"" \
    "cur=\$(sed -n 's/^USERS=\"\\(.*\\)\"\$/\\1/p' \"\$c\" | head -1)" \
    "case \" \$cur \" in *' ${user} '*) ;; *) new=\"\${cur:+\$cur }${user}\"; grep -v '^USERS=' \"\$c\" > \"\$c.new\" || true; echo \"USERS=\\\"\$new\\\"\" >> \"\$c.new\"; chmod 644 \"\$c.new\"; mv -f \"\$c.new\" \"\$c\" ;; esac" \
    "printf '%s\\n' 'SHELL=/bin/bash' '30 2 * * * root ${SNAPSHOT_SCRIPT_PATH} run' > /etc/cron.d/intelis-backup-snapshots" \
    "chmod 644 /etc/cron.d/intelis-backup-snapshots" \
    "${SNAPSHOT_SCRIPT_PATH} init"
}

# Which snapshot version the backup machine keeps for this lab's backup
# account, read as that account: the script and its list of accounts are
# world-readable. Empty when there is none, or when the script is there but the
# account is not on its list, as a second account on the same machine would be.
server_snapshot_version() {
  ssh -n -i "$SSH_KEY" -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -p "$SSH_PORT" \
    "${SSH_USER}@${SSH_HOST}" \
    "grep -Eq '^USERS=\"(.* )?${SSH_USER}( .*)?\"' /etc/intelis-backup-server.conf 2>/dev/null && sed -n 's/^SNAPSHOT_VERSION=//p' ${SNAPSHOT_SCRIPT_PATH} 2>/dev/null | head -1" \
    2>/dev/null | tr -cd '0-9'
}

# Runs root's lines on the backup machine through its administrator login.
# Asks for the account, and its password if the machine wants one.
run_as_backup_admin() { # run_as_backup_admin <lines...>
  local admin
  ask admin "Administrator account on the backup machine (the account used to manage it)" "${BACKUP_ADMIN:-}"
  [[ "$admin" =~ ^[a-z_][a-z0-9_.-]*$ ]] || { print warning "'$admin' is not a valid username."; return 1; }
  BACKUP_ADMIN="$admin"
  print info "Logging in as ${admin}. If it asks for a password, it is ${admin}'s password on the backup server."
  admin_run_lines "$admin" "$@"
}

# admin_run_lines <admin> <lines...>: runs the lines as root on the backup
# machine, through the administrator's login.
#
# They are uploaded to a file first, and only then run. Sent as one long
# command argument (echo <base64> | base64 -d | sudo sh), the payload arrived
# cut short on a real setup, and sh stopped half-way at "Unterminated quoted
# string" after running the lines before the cut. The upload's checksum is
# compared with what was sent, so a cut-short copy is refused rather than
# half-run. Then `ssh -t`, so sudo can ask for its password on this terminal.
admin_run_lines() {
  local admin=$1 payload sum remote file run
  shift
  local mux=(-o ControlMaster=auto -o "ControlPath=/tmp/intelis-admin-%C" -o ControlPersist=60)
  payload="$(printf '%s\n' "set -e" "$@")"
  sum="$(printf '%s\n' "$payload" | sha256sum | cut -d' ' -f1)"
  # shellcheck disable=SC2016  # expanded on the backup machine
  remote="$(printf '%s\n' "$payload" | ssh "${mux[@]}" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 \
      -p "$SSH_PORT" "${admin}@${SSH_HOST}" \
      'umask 077; f=$(mktemp /tmp/intelis-setup.XXXXXX) && cat > "$f" && printf "%s %s\n" "$f" "$(sha256sum < "$f" | cut -d" " -f1)"')" ||
    { print warning "Could not send the setup commands to the backup machine as ${admin}."; return 1; }
  remote="$(printf '%s' "$remote" | tr -d '\r' | tail -1)"
  file="${remote%% *}"
  if [[ ! "$file" =~ ^/tmp/intelis-setup\.[A-Za-z0-9]+$ ]] || [ "${remote##* }" != "$sum" ]; then
    print warning "The setup commands did not arrive whole on the backup machine. Nothing was run."
    return 1
  fi
  run="sh"
  [ "$admin" = "root" ] || run="sudo sh"
  ssh -t "${mux[@]}" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -p "$SSH_PORT" \
      "${admin}@${SSH_HOST}" "${run} ${file}; rc=\$?; rm -f ${file}; exit \$rc"
}

install_key_via_admin() {
  local admin key create=0 exists
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
  # Everything in it is a fixed command, the validated username and the public
  # key line. The same login also installs, or updates, the snapshots of every
  # lab's backups on that machine.
  local -a lines
  mapfile -t lines < <({ printf '%s\n' \
    "u='${SSH_USER}'" \
    "if [ ${create} -eq 1 ] && ! id -u \"\$u\" >/dev/null 2>&1; then useradd -m -s /bin/bash \"\$u\"; fi" \
    "h=\$(getent passwd \"\$u\" | cut -d: -f6)" \
    "mkdir -p \"\$h/.ssh\"; touch \"\$h/.ssh/authorized_keys\"" \
    "grep -qxF '${key}' \"\$h/.ssh/authorized_keys\" || echo '${key}' >> \"\$h/.ssh/authorized_keys\"" \
    "chown -R \"\$u\": \"\$h/.ssh\"; chmod 700 \"\$h/.ssh\"; chmod 600 \"\$h/.ssh/authorized_keys\""
    snapshot_install_lines "$SSH_USER"; })
  admin_run_lines "$admin" "${lines[@]}" ||
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
# On an SSH backup machine, every lab writes as the same dedicated account. Keep
# other local accounts from traversing the shared root and reading DB passwords.
if [ "$DEST_MODE" = "ssh" ]; then
  dest_exec "chmod 700 $(printf '%q' "$DEST_BASE") ${q_dest}"
fi
print success "Backup folder ready: ${DEST_DIR}"

# Snapshots on the backup machine (see snapshot_server_script). A lab whose key
# already worked never needed the administrator login that installs them, so a
# machine set up before them, or with an older copy, is offered them here once.
# Declining changes nothing, and the next setup asks again.
if [ "$DEST_MODE" = "ssh" ]; then
  snap_have="$(server_snapshot_version || true)"
  if [ "${snap_have:-0}" -ge "$SNAPSHOT_VERSION" ] 2>/dev/null; then
    print success "The backup machine keeps nightly snapshots that no lab can change"
  else
    if [ -z "$snap_have" ]; then
      print warning "The backup machine keeps no snapshots. A hacked or encrypted lab machine could delete its own backups there."
    else
      print info "The backup machine's snapshots can be updated."
    fi
    if ui_interactive; then
      choose snap_choice add "Add nightly snapshots on the backup machine? It needs its administrator login once." \
        "add:Add them (recommended):Root on the backup machine keeps a nightly copy of every lab's backups that no lab can change." \
        "skip:Not now:Nothing is changed. The next setup asks again."
      # shellcheck disable=SC2154  # set by `choose` above, via printf -v
      if [ "$snap_choice" = "add" ]; then
        if mapfile -t snap_lines < <(snapshot_install_lines "$SSH_USER") && run_as_backup_admin "${snap_lines[@]}"; then
          print success "Nightly snapshots are set up on the backup machine"
        else
          print warning "The snapshots were not set up. Backups are not affected. Run 'intelis backup setup' again to retry."
        fi
      fi
    fi
  fi
fi

# --- tools --------------------------------------------------------------------

if ! command -v rsync >/dev/null 2>&1; then
  print info "Installing rsync..."
  apt-get update -y >/dev/null 2>&1 || true
  apt-get install -y rsync >/dev/null || { print error "Could not install rsync."; exit 1; }
fi
require_cmd rsync

# --- save configuration -------------------------------------------------------

umask 077
config_tmp="$(mktemp "${CONF_FILE}.XXXXXX")"
{
  echo '# InteLIS backup configuration. Written by remote-backup.sh.'
  echo "# Re-run 'intelis backup setup' to change any of this."
  printf 'INSTANCE_NAME=%q\n' "$SANITIZED_NAME"
  printf 'LAB_UUID=%q\n' "$LAB_UUID"
  printf 'DEST_FOLDER=%q\n' "$DEST_FOLDER"
  printf 'LIS_PATH=%q\n' "$LIS_PATH"
  printf 'DEST_MODE=%q\n' "$DEST_MODE"
  printf 'DEST_BASE=%q\n' "$DEST_BASE"
  printf 'DEST_DIR=%q\n' "$DEST_DIR"
  printf 'SSH_USER=%q\n' "$SSH_USER"
  printf 'SSH_HOST=%q\n' "$SSH_HOST"
  printf 'SSH_PORT=%q\n' "$SSH_PORT"
  printf 'SSH_KEY=%q\n' "$SSH_KEY"
  printf 'SMB_HOST=%q\n' "$SMB_HOST"
  printf 'SMB_SHARE=%q\n' "$SMB_SHARE"
  printf 'SMB_USER=%q\n' "$SMB_USER"
  printf 'SMB_VERS=%q\n' "$SMB_VERS"
  printf 'SMB_CRED_FILE=%q\n' "$SMB_CRED_FILE"
  printf 'MOUNT_POINT=%q\n' "$MOUNT_POINT"
  printf 'LOCAL_ROOT=%q\n' "$LOCAL_ROOT"
  echo '# Set when the backup drive was chosen from the list. The drive is mounted'
  echo '# at LOCAL_ROOT by this UUID through /etc/fstab. Empty for a typed folder.'
  printf 'LOCAL_UUID=%q\n' "$LOCAL_UUID"
  echo '# Database history at the destination: one dump per day for HISTORY_DAYS days,'
  echo '# then one per week for HISTORY_WEEKS weeks.'
  printf 'HISTORY_DAYS=%q\n' "${HISTORY_DAYS:-7}"
  printf 'HISTORY_WEEKS=%q\n' "${HISTORY_WEEKS:-4}"
} > "$config_tmp"
chmod 600 "$config_tmp"
mv -f "$config_tmp" "$CONF_FILE"
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
if php -r '$c = json_decode(file_get_contents($argv[1]), true); exit(isset($c["scripts"]["backup"]) ? 0 : 1);' \
    "${LIS_PATH}/composer.json"; then
  print info "Creating a fresh database and settings backup before anything is sent."
  if ! (cd "$LIS_PATH" && sudo -u www-data composer backup); then
    print error "The local database and settings backup did not finish. Nothing was sent off this machine."
    print info  "Fix the error above, then run: intelis backup"
    exit 1
  fi
  print success "Fresh database and settings backup created"
else
  # Releases before the combined Composer command still create database dumps
  # through cron. The runner below accepts those installs only when their main
  # dump is present and no more than 24 hours old.
  print warning "This older InteLIS release cannot create a fresh backup with Composer."
  print info "Checking that its scheduled database dump is complete and recent before sending anything."
fi
print info "Sending the backup can take a while the first time. Leave this window open."
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
