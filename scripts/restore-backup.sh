#!/bin/bash
set -Eeuo pipefail

# Fetches an InteLIS backup back from wherever remote-backup.sh sent it.
#
# To use this script:
#   sudo intelis restore
#
# On a machine with no InteLIS on it yet, fetch it directly instead:
#   cd ~
#   wget -O restore-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/restore-backup.sh
#   chmod u+x restore-backup.sh
#   sudo ./restore-backup.sh
#
# It connects to the backup server, Windows share, or drive, shows every lab
# stored there, and copies the chosen one back to this machine. It then either
# prints the exact command to rebuild a machine from it, or — on a machine that
# already runs InteLIS — restores the database in place.
#
# Options:
#   --list    Only show which labs are stored at the backup destination
#   --help    Show usage

trap 'echo -e "\033[1;91m❌ Error:\033[0m restore failed at line $LINENO (status $?)"' ERR

CONF_FILE="/etc/intelis/backup.conf"
SSH_CONTROL="/tmp/intelis-restore-ssh-%r@%h:%p"

# Deliberately not named MOUNT_POINT or SMB_CRED_FILE. Reading backup.conf below
# defines those names, and it must not be able to redirect this script's mount.
RESTORE_MOUNT="/mnt/intelis-restore"
RESTORE_CRED="/etc/intelis/smb-restore.cred"

ACTION="restore"
case "${1:-}" in
  "")        ACTION="restore" ;;
  --list)    ACTION="list" ;;
  --help|-h) sed -n '3,20p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
  *)         echo "Unknown option: $1"; echo "Try --help"; exit 2 ;;
esac

# --- helpers ------------------------------------------------------------------

print() {
  local type=${1:-info}; shift || true
  local message=${1:-};  shift || true
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
      printf "\n\033[1;96m%*s\033[0m\n" "$term_width" '' | tr ' ' '='
      printf "\033[1;96m%s%s\033[0m\n" "$pad_str" "$message"
      printf "\033[1;96m%*s\033[0m\n\n" "$term_width" '' | tr ' ' '='
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
  print info  "Run this directly in a terminal: sudo intelis restore"
  exit 1
}

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
    # the default there: restoring the wrong lab is not a recoverable mistake.
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

cleanup() {
  # Close the shared SSH connection so no stray agent is left behind.
  if [ "${SRC_MODE:-}" = "ssh" ] && [ -n "${SSH_HOST:-}" ]; then
    ssh -o ControlPath="$SSH_CONTROL" -O exit "${SSH_USER}@${SSH_HOST}" 2>/dev/null || true
  fi
  # The copy is on this machine by now, so the share does not need to stay mounted.
  if mountpoint -q "$RESTORE_MOUNT" 2>/dev/null; then
    umount "$RESTORE_MOUNT" 2>/dev/null || true
  fi
}
trap cleanup EXIT

# --- preflight ----------------------------------------------------------------

if [ "$(id -u)" -ne 0 ]; then
  echo "Need admin privileges. Run with sudo."
  exit 1
fi

require_cmd rsync
require_cmd awk

print header "InteLIS restore"

# --- where is the backup? -----------------------------------------------------

SRC_MODE=""; SSH_USER=""; SSH_HOST=""; SSH_PORT="22"; SSH_KEY=""
SMB_HOST=""; SMB_SHARE=""; SMB_USER=""; SMB_VERS=""
LOCAL_ROOT=""; SRC_BASE=""

# On the original machine the backup settings are already known, so there is
# nothing to type. On a replacement machine they are not, so ask.
if [ -f "$CONF_FILE" ]; then
  # shellcheck disable=SC1090
  . "$CONF_FILE"
  SRC_MODE="${DEST_MODE:-}"
  SRC_BASE="${DEST_BASE:-}"
  print info "Using the backup settings already on this machine ($CONF_FILE)."
  case "$SRC_MODE" in
    ssh)   print info "Backup server: ${SSH_USER}@${SSH_HOST}" ;;
    smb)   print info "Windows share: //${SMB_HOST}/${SMB_SHARE}" ;;
    local) print info "Drive: ${LOCAL_ROOT}" ;;
  esac
  confirm "Fetch the backup from there?" || SRC_MODE=""
fi

if [ -z "$SRC_MODE" ]; then
  print header "Where is the backup stored?"
  echo "  1) On another Linux machine (over SSH)"
  echo "  2) In a shared folder on a Windows machine (over SMB)"
  echo "  3) On a USB or external drive plugged into this machine"
  echo
  while true; do
    ask choice "Choose 1, 2 or 3" "1"
    case "$choice" in
      1) SRC_MODE="ssh";   break ;;
      2) SRC_MODE="smb";   break ;;
      3) SRC_MODE="local"; break ;;
      *) print warning "Please enter 1, 2 or 3." ;;
    esac
  done
  SRC_BASE=""
fi

# --- connect ------------------------------------------------------------------

SSH_ARGS=()

connect_ssh() {
  if ! command -v ssh >/dev/null 2>&1; then
    print info "Installing the SSH client..."
    apt-get update -y >/dev/null 2>&1 || true
    apt-get install -y openssh-client >/dev/null || { print error "Could not install openssh-client."; exit 1; }
  fi
  # ControlMaster keeps one authenticated connection open, so a password is
  # typed once instead of at every step.
  SSH_ARGS=(-o ControlMaster=auto -o ControlPath="$SSH_CONTROL" -o ControlPersist=10m
            -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -p "$SSH_PORT")
  [ -n "$SSH_KEY" ] && [ -f "$SSH_KEY" ] && SSH_ARGS+=(-i "$SSH_KEY")

  while true; do
    if [ -z "$SSH_HOST" ]; then
      ask SSH_USER "Username on the backup server" "${SSH_USER:-}"
      ask SSH_HOST "Hostname or IP of the backup server" ""
      ask SSH_PORT "SSH port" "22"
      SSH_ARGS=(-o ControlMaster=auto -o ControlPath="$SSH_CONTROL" -o ControlPersist=10m
                -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 -p "$SSH_PORT")
      [ -n "$SSH_KEY" ] && [ -f "$SSH_KEY" ] && SSH_ARGS+=(-i "$SSH_KEY")
    fi
    print info "Connecting to ${SSH_HOST}... (you may be asked for ${SSH_USER}'s password)"
    if ssh "${SSH_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" true; then
      print success "Connected"
      break
    fi
    print warning "Could not connect to ${SSH_HOST}."
    confirm "Try different details?" || exit 1
    SSH_HOST=""
  done

  if [ -z "$SRC_BASE" ]; then
    local remote_home
    remote_home="$(ssh "${SSH_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" 'printf %s "$HOME"')"
    SRC_BASE="${remote_home}/backups"
  fi
}

try_smb_mount() {
  local cred=$1 v
  mountpoint -q "$RESTORE_MOUNT" && umount "$RESTORE_MOUNT" 2>/dev/null || true
  for v in "${SMB_VERS:-3.1.1}" 3.0 2.1 1.0; do
    if mount -t cifs "//${SMB_HOST}/${SMB_SHARE}" "$RESTORE_MOUNT" \
         -o "credentials=${cred},vers=${v},uid=root,gid=root,iocharset=utf8,ro" 2>/dev/null; then
      return 0
    fi
  done
  return 1
}

connect_smb() {
  print info "Installing the tools needed to read Windows shares..."
  apt-get update -y >/dev/null 2>&1 || true
  apt-get install -y cifs-utils >/dev/null || { print error "Could not install cifs-utils."; exit 1; }

  mkdir -p /etc/intelis "$RESTORE_MOUNT"

  # On the machine that made the backups, the password is already saved. Use it
  # rather than asking for something the operator may not have to hand.
  local saved_cred="${SMB_CRED_FILE:-}"
  if [ -n "$SMB_HOST" ] && [ -n "$SMB_SHARE" ] && [ -n "$saved_cred" ] && [ -f "$saved_cred" ]; then
    if try_smb_mount "$saved_cred"; then
      print success "Connected to //${SMB_HOST}/${SMB_SHARE} using the saved credentials"
      SRC_BASE="${RESTORE_MOUNT}/backups"
      return
    fi
    print warning "The saved credentials no longer work. Asking for them again."
  fi

  local smb_pass
  while true; do
    ask SMB_HOST  "Windows hostname or IP" "${SMB_HOST:-}"
    ask SMB_SHARE "Name of the shared folder" "${SMB_SHARE:-}"
    ask SMB_USER  "Windows username" "${SMB_USER:-}"
    ask_secret smb_pass "Windows password for ${SMB_USER}"

    umask 077
    printf 'username=%s\npassword=%s\n' "$SMB_USER" "$smb_pass" > "$RESTORE_CRED"
    chmod 600 "$RESTORE_CRED"
    umask 022

    if try_smb_mount "$RESTORE_CRED"; then
      print success "Connected to //${SMB_HOST}/${SMB_SHARE}"
      break
    fi
    print warning "Could not connect. Check the name, the password, and that the folder is shared."
    rm -f "$RESTORE_CRED"
    confirm "Try again?" || exit 1
    SMB_HOST=""; SMB_SHARE=""; SMB_USER=""
  done
  SRC_BASE="${RESTORE_MOUNT}/backups"
}

connect_local() {
  # The settings file already names the drive on the machine that made the backup.
  if [ -n "$SRC_BASE" ] && [ -d "$SRC_BASE" ]; then
    print success "Reading from $SRC_BASE"
    return
  fi
  if command -v lsblk >/dev/null 2>&1; then
    echo
    lsblk -o NAME,SIZE,FSTYPE,MOUNTPOINT 2>/dev/null | grep -v '^loop' || true
    echo
  fi
  while true; do
    ask LOCAL_ROOT "Folder on the drive that holds the backups" "${LOCAL_ROOT:-/media/backup}"
    if [ -d "${LOCAL_ROOT}/backups" ]; then SRC_BASE="${LOCAL_ROOT}/backups"; break; fi
    if [ -d "$LOCAL_ROOT" ]; then SRC_BASE="$LOCAL_ROOT"; break; fi
    print warning "'$LOCAL_ROOT' does not exist. Is the drive plugged in and mounted?"
    LOCAL_ROOT=""
  done
  print success "Reading from $SRC_BASE"
}

case "$SRC_MODE" in
  ssh)   connect_ssh ;;
  smb)   connect_smb ;;
  local) connect_local ;;
esac

src_exec() {
  case "$SRC_MODE" in
    ssh) ssh "${SSH_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" "$1" ;;
    *)   bash -c "$1" ;;
  esac
}

Q_BASE="$(printf '%q' "$SRC_BASE")"

# --- which lab? ---------------------------------------------------------------

print header "Labs stored at this destination"

src_exec "test -d ${Q_BASE}" 2>/dev/null || { print error "No backups folder found at ${SRC_BASE}."; exit 1; }

mapfile -t FOLDERS < <(src_exec "ls -1 ${Q_BASE} 2>/dev/null" | tr -d '\r' | grep -v '^$' || true)
[ "${#FOLDERS[@]}" -gt 0 ] || { print error "There are no backups in ${SRC_BASE}."; exit 1; }

idx=0
declare -a CHOICES=()
for folder in "${FOLDERS[@]}"; do
  q_folder="$(printf '%q' "${SRC_BASE}/${folder}")"
  meta="$(src_exec "cat ${q_folder}/.lab-meta 2>/dev/null || true" | tr -d '\r')"
  instance="$(printf '%s' "$meta" | awk -F= '/^instance=/{print $2}')"
  host="$(printf '%s' "$meta" | awk -F= '/^hostname=/{print $2}')"
  updated="$(printf '%s' "$meta" | awk -F= '/^updated_at=|^created_at=/{print $2}' | head -1)"
  newest="$(src_exec "ls -1t ${q_folder}/backups/db 2>/dev/null | head -1 || true" | tr -d '\r')"

  idx=$((idx + 1))
  CHOICES+=("$folder")
  printf "  \033[1m%2d)\033[0m %s\n" "$idx" "$folder"
  [ -n "$instance" ] && printf "      lab: %s%s\n" "$instance" "${host:+  (machine: $host)}"
  [ -n "$updated" ]  && printf "      backup last updated: %s\n" "$updated"
  if [ -n "$newest" ]; then
    printf "      newest database dump: %s\n" "$newest"
  else
    printf "      \033[1;93mno database dumps in this folder\033[0m\n"
  fi
  echo
done

[ "$ACTION" = "list" ] && exit 0

while true; do
  ask pick "Which one should be restored? Enter a number" "1"
  if [[ "$pick" =~ ^[0-9]+$ ]] && [ "$pick" -ge 1 ] && [ "$pick" -le "${#CHOICES[@]}" ]; then break; fi
  print warning "Please enter a number between 1 and ${#CHOICES[@]}."
done
CHOSEN="${CHOICES[$((pick - 1))]}"
SRC_DIR="${SRC_BASE}/${CHOSEN}"
print success "Restoring from ${CHOSEN}"

# --- what should come back? ---------------------------------------------------

print header "What should be copied back?"
echo "  1) Just the database backups — the right choice for rebuilding a machine"
echo "  2) Everything, including uploaded files and attachments"
echo

while true; do
  ask what "Choose 1 or 2" "1"
  case "$what" in
    1) SUBPATH="/backups/db"; break ;;
    2) SUBPATH="";            break ;;
    *) print warning "Please enter 1 or 2." ;;
  esac
done

ask STAGING "Where should the files be put on this machine?" "/root/intelis-restore/${CHOSEN}"
mkdir -p "$STAGING"

# --- copy it down -------------------------------------------------------------

print header "Copying the backup to this machine"
print info "This can take a while. Leave this window open."

case "$SRC_MODE" in
  ssh)
    ssh_cmd="ssh -o ControlPath=${SSH_CONTROL} -o StrictHostKeyChecking=accept-new -p ${SSH_PORT}"
    [ -n "$SSH_KEY" ] && [ -f "$SSH_KEY" ] && ssh_cmd="${ssh_cmd} -i ${SSH_KEY}"
    rsync -rtLz --info=progress2 -e "$ssh_cmd" \
      "${SSH_USER}@${SSH_HOST}:${SRC_DIR}${SUBPATH}/" "${STAGING}/" \
      || { print error "The copy did not finish."; exit 1; }
    ;;
  *)
    rsync -rtL --info=progress2 "${SRC_DIR}${SUBPATH}/" "${STAGING}/" \
      || { print error "The copy did not finish."; exit 1; }
    ;;
esac
print success "Copied to ${STAGING}"

# --- check the dumps are readable --------------------------------------------
# A backup nobody has opened is a rumour, so verify before telling anyone it worked.

print header "Checking the database backups"

DUMP_DIR="$STAGING"
[ -z "$SUBPATH" ] && DUMP_DIR="${STAGING}/backups/db"

if [ ! -d "$DUMP_DIR" ]; then
  print warning "No database backups were found in what was copied."
else
  ok_count=0; bad_count=0; gpg_count=0
  while IFS= read -r dump; do
    name="$(basename "$dump")"
    case "$name" in
      *.gpg)
        gpg_count=$((gpg_count + 1))
        printf "  🔒 %s (encrypted — the key is needed to open it)\n" "$name"
        ;;
      *.zst)
        if command -v zstd >/dev/null 2>&1; then
          if zstd -t "$dump" >/dev/null 2>&1; then printf "  ✅ %s\n" "$name"; ok_count=$((ok_count + 1))
          else printf "  ❌ %s is damaged\n" "$name"; bad_count=$((bad_count + 1)); fi
        else
          printf "  ➖ %s (install zstd to check this one)\n" "$name"
        fi
        ;;
      *.gz)
        if gzip -t "$dump" 2>/dev/null; then printf "  ✅ %s\n" "$name"; ok_count=$((ok_count + 1))
        else printf "  ❌ %s is damaged\n" "$name"; bad_count=$((bad_count + 1)); fi
        ;;
      *.sql)
        if [ -s "$dump" ]; then printf "  ✅ %s\n" "$name"; ok_count=$((ok_count + 1))
        else printf "  ❌ %s is empty\n" "$name"; bad_count=$((bad_count + 1)); fi
        ;;
    esac
  done < <(find "$DUMP_DIR" -maxdepth 1 -type f \( -name '*.sql' -o -name '*.sql.gz' -o -name '*.sql.zst' -o -name '*.gpg' \) | sort)

  echo
  if [ "$bad_count" -gt 0 ]; then
    print error "${bad_count} backup file(s) are damaged and cannot be restored."
    print info  "Try an older backup from the same folder, or check the backup destination's disk."
  elif [ "$ok_count" -eq 0 ] && [ "$gpg_count" -gt 0 ]; then
    print info "All the backups here are encrypted. They are restored with the key, as described below."
  elif [ "$ok_count" -eq 0 ]; then
    print warning "No database backups were found to check."
  else
    print success "${ok_count} backup file(s) checked and readable"
  fi
fi

# --- what to do next ----------------------------------------------------------

LIS_PATH=""
for candidate in /var/www/intelis /var/www/vlsm; do
  if [ -f "$candidate/configs/config.production.php" ]; then LIS_PATH="$candidate"; break; fi
done

print header "What to do next"

if [ -n "$LIS_PATH" ]; then
  # A working installation is already here, so the database can go straight back.
  print info "InteLIS is already installed at ${LIS_PATH}."
  echo
  print warning "Restoring the database REPLACES everything currently in it."
  print info    "A safety copy of the current database is taken first, so this can be undone."
  echo
  if confirm "Restore the database into ${LIS_PATH} now?"; then
    newest_dump="$(find "$DUMP_DIR" -maxdepth 1 -type f -name 'vlsm-*' | sort | tail -1)"
    if [ -z "$newest_dump" ]; then
      print error "No main-database backup (a file starting with 'vlsm-') was found in ${DUMP_DIR}."
      exit 1
    fi
    print info "Restoring $(basename "$newest_dump")..."
    if sudo -u www-data php "${LIS_PATH}/vendor/bin/db-tools" restore "$newest_dump"; then
      print success "Database restored"
      sudo -u www-data php "${LIS_PATH}/bin/migrate.php" || print warning "Could not apply database migrations; run 'sudo intelis migrate' by hand."
      print info "Log in and check Admin → System Config."
    else
      print error "The restore did not finish. The safety copy of the previous database is in ${LIS_PATH}/backups/db."
      exit 1
    fi
  else
    print info "Nothing was changed. To restore by hand later:"
    echo
    echo "    cd ${LIS_PATH} && sudo -u www-data php vendor/bin/db-tools restore ${DUMP_DIR}/<file>"
    echo
  fi
  if find "$DUMP_DIR" -maxdepth 1 -name 'interfacing-*' | grep -q .; then
    print info "There are interfacing-database backups here too. Restore one separately if that database is in use:"
    echo
    echo "    cd ${LIS_PATH} && sudo -u www-data php vendor/bin/db-tools restore ${DUMP_DIR}/<interfacing-file>"
    echo
  fi
else
  # Bare machine: setup.sh installs the stack and restores in one step.
  print info "InteLIS is not installed on this machine yet."
  print info "Install it and restore the backup in one step by running:"
  echo
  echo "    cd ~ && wget -O setup.sh \"https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=\$(date +%s)\" \\"
  echo "      && sudo bash setup.sh --db latest:${DUMP_DIR}"
  echo
  if find "$DUMP_DIR" -maxdepth 1 -name '*.gpg' | grep -q .; then
    print info "These backups are encrypted. If the new machine uses the same MySQL root password as the old one,"
    print info "the command above is all that is needed. If not, ask the STS administrator for a recovery token and add:"
    echo
    echo "      --sts-url https://your-sts.example.org --recovery-token ABCD-EFGH-JKMN-PQRS"
    echo
  fi
  print info "Full instructions: docs/guides/migrating-ubuntu-machines.md"
fi

echo
print success "The backup is on this machine, in ${STAGING}"
