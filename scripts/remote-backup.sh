#!/bin/bash
set -Eeuo pipefail

# Sets up automatic off-machine backups of an InteLIS installation.
#
# To use this script:
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
# Answers are saved to /etc/intelis/backup.conf, so re-running this script is a
# matter of pressing Enter through the prompts. The backup runner installed at
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
SMB_CRED_FILE="${CONF_DIR}/smb-backup.cred"

# --- helpers ------------------------------------------------------------------

print() {
  local type=${1:-info}; shift || true
  local message=${1:-};  shift || true
  local header_char="="
  case "$type" in
    error)   printf "\033[1;91m❌ Error:\033[0m %s\n" "$message" ;;
    success) printf "\033[1;92m✅ Success:\033[0m %s\n" "$message" ;;
    warning) printf "\033[1;93m⚠️ Warning:\033[0m %s\n" "$message" ;;
    info)    printf "\033[1;96mℹ️ Info:\033[0m %s\n" "$message" ;;
    header)
      local term_width msg_length padding pad_str
      term_width=$(tput cols 2>/dev/null || echo 80)
      msg_length=${#message}
      padding=$(((term_width - msg_length) / 2)); ((padding<0)) && padding=0
      pad_str=$(printf '%*s' "$padding" '')
      printf "\n\033[1;96m%*s\033[0m\n" "$term_width" '' | tr ' ' "$header_char"
      printf "\033[1;96m%s%s\033[0m\n" "$pad_str" "$message"
      printf "\033[1;96m%*s\033[0m\n\n" "$term_width" '' | tr ' ' "$header_char"
      ;;
    *)       printf "%s\n" "$message" ;;
  esac
}

require_cmd() { command -v "$1" >/dev/null 2>&1 || { print error "Missing dependency: $1"; exit 1; }; }

trim() {
  local s=$1
  s="${s#"${s%%[![:space:]]*}"}"
  s="${s%"${s##*[![:space:]]}"}"
  printf '%s' "$s"
}

no_more_input() {
  print error "Ran out of answers — the input ended before the questions did."
  print info  "Run this script directly in a terminal: sudo ./remote-backup.sh"
  exit 1
}

# ask <var> <prompt> [default] — keeps asking until a non-empty answer is given.
ask() {
  local __var=$1 prompt=$2 default=${3:-} raw input rc
  while true; do
    rc=0; raw=""
    if [ -n "$default" ]; then
      read -r -p "$prompt [$default]: " raw || rc=$?
    else
      read -r -p "$prompt: " raw || rc=$?
    fi
    # An empty answer plus a read error means the input ended. Never fall back to
    # the default there: silently choosing an answer nobody gave is how the wrong
    # destination gets configured.
    [ -z "$raw" ] && [ "$rc" -ne 0 ] && no_more_input
    input="$(trim "${raw:-$default}")"
    [ -n "$input" ] && break
    print warning "This cannot be left empty. Try again."
  done
  printf -v "$__var" '%s' "$input"
}

ask_secret() {
  local __var=$1 prompt=$2 input rc
  while true; do
    rc=0
    read -r -s -p "$prompt: " input || rc=$?; echo
    [ -n "$input" ] && break
    [ "$rc" -ne 0 ] && no_more_input
    print warning "This cannot be left empty. Try again."
  done
  printf -v "$__var" '%s' "$input"
}

confirm() {
  local prompt=$1 answer rc=0
  read -r -p "$prompt (y/N): " answer || rc=$?
  [ "$rc" -ne 0 ] && [ -z "$answer" ] && no_more_input
  [[ "$answer" =~ ^[Yy]$ ]]
}

# --- preflight ----------------------------------------------------------------

if [ "$(id -u)" -ne 0 ]; then
  echo "Need admin privileges. Run with sudo."
  exit 1
fi

require_cmd realpath
require_cmd awk
require_cmd sed

mkdir -p "$CONF_DIR"
chmod 700 "$CONF_DIR"

# Load any previous answers so a re-run is press-Enter-all-the-way.
INSTANCE_NAME=""; LIS_PATH=""; DEST_MODE=""
SSH_USER=""; SSH_HOST=""; SSH_PORT=""
SMB_HOST=""; SMB_SHARE=""; SMB_USER=""; SMB_VERS=""
LOCAL_ROOT=""
if [ -f "$CONF_FILE" ]; then
  # shellcheck disable=SC1090
  . "$CONF_FILE"
  print info "Found an existing configuration at $CONF_FILE. Press Enter to keep each saved answer."
fi

print header "InteLIS backup setup"

# --- instance name ------------------------------------------------------------

ask INSTANCE_NAME "Lab name or lab code" "${INSTANCE_NAME:-$(hostname -s 2>/dev/null || echo lab)}"
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

while true; do
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

# --- destination --------------------------------------------------------------

print header "Where should the backup be sent?"
echo "  1) Another Linux machine on the network (over SSH)"
echo "  2) A shared folder on a Windows machine (over SMB)"
echo "  3) A USB or external drive plugged into this machine"
echo

default_choice=1
case "$DEST_MODE" in
  ssh)   default_choice=1 ;;
  smb)   default_choice=2 ;;
  local) default_choice=3 ;;
esac

while true; do
  ask choice "Choose 1, 2 or 3" "$default_choice"
  case "$choice" in
    1) DEST_MODE="ssh";   break ;;
    2) DEST_MODE="smb";   break ;;
    3) DEST_MODE="local"; break ;;
    *) print warning "Please enter 1, 2 or 3." ;;
  esac
done

# --- destination: another Linux machine over SSH ------------------------------

configure_ssh() {
  require_cmd ssh
  require_cmd ssh-keygen
  require_cmd ssh-copy-id

  print header "Backup server details"

  mkdir -p /root/.ssh; chmod 700 /root/.ssh
  if [ ! -f "$SSH_KEY" ]; then
    print info "Creating a dedicated SSH key for backups..."
    ssh-keygen -t ed25519 -C "intelis-backup-${SANITIZED_NAME}" -N "" -f "$SSH_KEY" >/dev/null
  fi
  chmod 600 "$SSH_KEY"; chmod 644 "${SSH_KEY}.pub"

  while true; do
    ask SSH_USER "Username on the backup server" "${SSH_USER:-}"
    ask SSH_HOST "Hostname or IP of the backup server" "${SSH_HOST:-}"
    ask SSH_PORT "SSH port" "${SSH_PORT:-22}"

    if ! [[ "$SSH_PORT" =~ ^[0-9]+$ ]] || [ "$SSH_PORT" -lt 1 ] || [ "$SSH_PORT" -gt 65535 ]; then
      print warning "'$SSH_PORT' is not a valid port number."
      SSH_PORT=""
      continue
    fi

    print info "Checking that ${SSH_HOST}:${SSH_PORT} is reachable..."
    if ! timeout 10 bash -c "</dev/tcp/${SSH_HOST}/${SSH_PORT}" 2>/dev/null; then
      print warning "Cannot reach ${SSH_HOST} on port ${SSH_PORT}."
      print info    "Check that the machine is switched on, on the same network, and that its SSH port is open."
      confirm "Try different details?" && { SSH_HOST=""; SSH_PORT=""; continue; }
      exit 1
    fi
    print success "Backup server is reachable"

    # Already trusted from a previous run?
    if ssh -i "$SSH_KEY" -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 \
         -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" true 2>/dev/null; then
      print success "Password-free login already works"
      break
    fi

    print info "Installing the backup key on the server. You will be asked for ${SSH_USER}'s password once."
    if ! ssh-copy-id -i "${SSH_KEY}.pub" -o StrictHostKeyChecking=accept-new \
         -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" >/dev/null; then
      print warning "Could not install the key. The username or password may be wrong, or the server may not allow password logins."
      confirm "Try again?" && { SSH_USER=""; SSH_HOST=""; SSH_PORT=""; continue; }
      exit 1
    fi

    if ! ssh -i "$SSH_KEY" -o BatchMode=yes -o ConnectTimeout=10 \
         -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" true; then
      print warning "The key was installed but password-free login still does not work."
      confirm "Try again?" && continue
      exit 1
    fi
    print success "Password-free login works"
    break
  done

  local remote_home
  remote_home="$(ssh -i "$SSH_KEY" -o BatchMode=yes -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" 'printf %s "$HOME"')"
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

configure_local() {
  print header "External drive details"
  print info "Plug the drive in and make sure it is mounted before continuing."
  if command -v lsblk >/dev/null 2>&1; then
    echo
    lsblk -o NAME,SIZE,FSTYPE,MOUNTPOINT 2>/dev/null | grep -v '^loop' || true
    echo
  fi

  while true; do
    ask LOCAL_ROOT "Folder on the drive to back up into" "${LOCAL_ROOT:-/media/backup}"
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

  print success "Backing up to $LOCAL_ROOT"
  DEST_BASE="${LOCAL_ROOT}/backups"
  DEST_DIR="${DEST_BASE}/${DEST_FOLDER}"
}

case "$DEST_MODE" in
  ssh)   configure_ssh ;;
  smb)   configure_smb ;;
  local) configure_local ;;
esac

# --- destination folder -------------------------------------------------------
# dest_exec runs a command wherever the backup lands, so the folder handling
# below is written once instead of once per destination type.

dest_exec() {
  case "$DEST_MODE" in
    ssh) ssh -i "$SSH_KEY" -o BatchMode=yes -o ConnectTimeout=10 -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" "$1" ;;
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
# Re-run 'sudo ./remote-backup.sh' to change any of this.
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
CONF
chmod 600 "$CONF_FILE"
umask 022
print success "Settings saved to $CONF_FILE"

# --- install the backup runner ------------------------------------------------

print header "Installing the backup runner"

cat > "$RUNNER" <<'RUNNER_SCRIPT'
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
Usage: intelis-backup.sh [option]

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
: "${LOCAL_ROOT:=}"

CRON_MARKER="/usr/local/bin/intelis-backup.sh"

# --- scheduling toggles (no lock or logging needed) ---------------------------

case "$ACTION" in
  disable)
    if crontab -l 2>/dev/null | grep -q "$CRON_MARKER"; then
      crontab -l 2>/dev/null | grep -v "$CRON_MARKER" | crontab -
      echo "Scheduled backups stopped. Run 'intelis-backup.sh --enable' to start them again."
    else
      echo "Scheduled backups were already stopped."
    fi
    pkill -f "$CRON_MARKER" 2>/dev/null && echo "Stopped the backup that was running." || true
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
        echo "⚠️  The last good backup is more than a day old. Run 'intelis-backup.sh --test' to find out why."
      fi
    else
      echo "Last good backup: never"
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
  "db_dump_age_hours": ${DB_DUMP_AGE_HOURS:--1}
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

SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new)

dest_exec() {
  case "$DEST_MODE" in
    ssh) ssh -i "$SSH_KEY" "${SSH_OPTS[@]}" -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" "$1" ;;
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
      [ -d "$LOCAL_ROOT" ] || fail "The backup drive at ${LOCAL_ROOT} is not there. Is it plugged in?"
      ;;
  esac
}

Q_DEST="$(printf '%q' "$DEST_DIR")"

# --- checks -------------------------------------------------------------------

print info "Starting backup of ${INSTANCE_NAME}"
print info "From: ${LIS_PATH}/"
print info "To  : ${DEST_DIR}/"

[ -d "$LIS_PATH" ] || fail "The installation folder ${LIS_PATH} does not exist."

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
      print warning "The newest database dump is ${DB_DUMP_AGE_HOURS} hours old. The scheduled backup job may have stopped running; check that cron.sh is in the crontab."
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

RSYNC_OPTS=(--delete --partial --timeout=900 --exclude-from="$EXCLUDE_LIST" --exclude=.lab-meta)

needs_compat_flags() {
  # Filesystems that cannot hold POSIX ownership, permissions, or symlinks.
  local fstype
  fstype=$(stat -f -c %T "$1" 2>/dev/null || echo unknown)
  case "$fstype" in
    vfat|exfat|msdos|ntfs|fuseblk|cifs|smb2) return 0 ;;
    *) return 1 ;;
  esac
}

case "$DEST_MODE" in
  ssh)
    RSYNC_OPTS+=(-aHz -e "ssh -i ${SSH_KEY} -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new -p ${SSH_PORT}")
    RSYNC_TARGET="${SSH_USER}@${SSH_HOST}:${DEST_DIR}/"
    ;;
  smb)
    # -L copies what symlinks point at, because Windows shares cannot store them.
    RSYNC_OPTS+=(-rtLz --no-perms --no-owner --no-group --omit-dir-times --modify-window=2)
    RSYNC_TARGET="${DEST_DIR}/"
    ;;
  local)
    if needs_compat_flags "$LOCAL_ROOT"; then
      RSYNC_OPTS+=(-rtL --no-perms --no-owner --no-group --omit-dir-times --modify-window=2)
    else
      RSYNC_OPTS+=(-aH)
    fi
    RSYNC_TARGET="${DEST_DIR}/"
    ;;
esac

# --- dry run ------------------------------------------------------------------

if [ "$ACTION" = "test" ]; then
  print info "Test run: nothing will be changed."
  pending=$(rsync "${RSYNC_OPTS[@]}" --dry-run --itemize-changes "${LIS_PATH}/" "$RSYNC_TARGET" | grep -c '^[<>]f' || true)
  print success "Connection works. ${AVAILABLE_GB} GB free at the destination."
  print info    "${pending} file(s) would be copied by a real backup."
  [ "$DB_DUMP_AGE_HOURS" -ge 0 ] && print info "Newest database dump: ${DB_DUMP_AGE_HOURS} hours old."
  exit 0
fi

# --- the backup ---------------------------------------------------------------

SECONDS=0
print info "Copying files..."
rsync "${RSYNC_OPTS[@]}" "${LIS_PATH}/" "$RSYNC_TARGET" >/dev/null || fail "The copy did not finish. See ${LOGFILE} for the details."
print success "Files copied"

# Verification that understands the exclusions: ask rsync what is still
# outstanding. A correct backup leaves nothing to transfer.
REMAINING=$(rsync "${RSYNC_OPTS[@]}" --dry-run --itemize-changes "${LIS_PATH}/" "$RSYNC_TARGET" | grep -c '^[<>]f' || true)
if [ "${REMAINING:-0}" -gt 0 ]; then
  print warning "${REMAINING} file(s) still differ after the copy. They may have changed while the backup was running; the next backup should pick them up."
else
  print success "Verified: the backup matches the installation"
fi

BACKUP_SIZE=$(dest_exec "du -sh ${Q_DEST} 2>/dev/null | cut -f1" || echo "unknown")
BACKUP_SIZE=${BACKUP_SIZE:-unknown}

write_status ok "" "$BACKUP_SIZE" "$SECONDS"
print success "Backup finished in ${SECONDS}s. Total size at the destination: ${BACKUP_SIZE}"
RUNNER_SCRIPT

chmod 0755 "$RUNNER"
print success "Backup runner installed at $RUNNER"

# The Windows-only runner from the older setup is replaced by the unified one.
if [ -f "$LEGACY_WINDOWS_RUNNER" ]; then
  rm -f "$LEGACY_WINDOWS_RUNNER"
  print info "Removed the old Windows-only runner; one runner now handles every destination."
fi

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
print info "Check it is working : sudo ${RUNNER} --status"
print info "Test the connection : sudo ${RUNNER} --test"
print info "Back up right now   : sudo ${RUNNER}"
print info "Stop the backups    : sudo ${RUNNER} --disable"
