#!/bin/bash
set -Eeuo pipefail

# Fetches an InteLIS backup back from wherever remote-backup.sh sent it.
#
# To use this script:
#   intelis restore
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
# in this script upgrades anything, so that lookup is pure latency here. This is
# the script an operator reaches for when a machine has already gone wrong, and
# a dead link must not hang it before the first question is asked.
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
  print info  "Run this directly in a terminal: intelis restore"
  exit 1
}

# Which lab gets restored is being CHOSEN at these prompts, so an unanswered
# question must never resolve itself. ask_text and ask_choice fall back to their
# defaults when nobody is there to answer, which is right for setup.sh and wrong
# here: this script overwrites a live database, and a default nobody picked
# would overwrite it with the wrong lab. Checked once, up front, rather than
# after each read.
#
# Guarded at the point a question is actually asked, not once up front. --list
# only reads the destination and prints it, so it stays usable from cron or a
# pipe when the saved settings already answer everything; it fails here only if
# it reaches a question nobody is there to answer.

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

require_cmd rsync
require_cmd awk

# Best-effort, bounded, and never fatal: gum is a nicety here exactly as it is
# in setup.sh, and the plain prompts below are already correct without it.
ensure_gum || true

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
  # Listing only reads the destination, so there is nothing to agree to. Asking
  # "Fetch the backup from there?" ahead of a --list describes something that is
  # not about to happen, and it stops the listing being usable from anywhere
  # without a person sitting at the keyboard.
  if [ "$ACTION" != "list" ]; then
    confirm "Fetch the backup from there?" || SRC_MODE=""
  fi
fi

if [ -z "$SRC_MODE" ]; then
  choose SRC_MODE ssh "Where is the backup stored?" \
    "ssh:On another Linux machine:Fetched over SSH from a server on the network." \
    "smb:In a shared folder on a Windows machine:Fetched over SMB from a folder shared from Windows." \
    "local:On a USB or external drive plugged into this machine:Read from a drive attached to this machine."
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
    if ssh -n "${SSH_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" true; then
      print success "Connected"
      break
    fi
    print warning "Could not connect to ${SSH_HOST}."
    confirm "Try different details?" || exit 1
    SSH_HOST=""
  done

  if [ -z "$SRC_BASE" ]; then
    local remote_home
    remote_home="$(ssh -n "${SSH_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" 'printf %s "$HOME"')"
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
    ssh) ssh -n "${SSH_ARGS[@]}" "${SSH_USER}@${SSH_HOST}" "$1" ;;
    *)   bash -c "$1" ;;
  esac
}

Q_BASE="$(printf '%q' "$SRC_BASE")"

# --- which lab? ---------------------------------------------------------------

print header "Labs stored at this destination"

src_exec "test -d ${Q_BASE}" 2>/dev/null || { print error "No backups folder found at ${SRC_BASE}."; exit 1; }

mapfile -t FOLDERS < <(src_exec "ls -1 ${Q_BASE} 2>/dev/null" | tr -d '\r' | grep -v '^$' || true)
[ "${#FOLDERS[@]}" -gt 0 ] || { print error "There are no backups in ${SRC_BASE}."; exit 1; }

# Read each folder's metadata once, into both shapes it is needed in: the full
# multi-line listing --list prints, and the one-line-per-lab summary the menu
# shows. Reading it twice would mean a second round of remote commands per lab
# over the same link the backup is about to come down.
declare -a CHOICES=() OPTIONS=()
idx=0
for folder in "${FOLDERS[@]}"; do
  q_folder="$(printf '%q' "${SRC_BASE}/${folder}")"
  meta="$(src_exec "cat ${q_folder}/.lab-meta 2>/dev/null || true" | tr -d '\r')"
  instance="$(printf '%s' "$meta" | awk -F= '/^instance=/{print $2}')"
  host="$(printf '%s' "$meta" | awk -F= '/^hostname=/{print $2}')"
  updated="$(printf '%s' "$meta" | awk -F= '/^updated_at=|^created_at=/{print $2}' | head -1)"
  newest="$(src_exec "ls -1t ${q_folder}/backups/db 2>/dev/null | head -1 || true" | tr -d '\r')"

  idx=$((idx + 1))
  CHOICES+=("$folder")

  # The menu row has one line for the whole lab, so the fields are joined rather
  # than listed. "no database dumps" stays first when it applies: a folder with
  # nothing restorable in it is the one thing worth knowing before choosing.
  summary=""
  if [ -z "$newest" ]; then
    summary="no database dumps in this folder"
  else
    summary="newest dump ${newest}"
  fi
  [ -n "$instance" ] && summary="${summary} · lab ${instance}${host:+ on ${host}}"
  [ -n "$updated" ]  && summary="${summary} · updated ${updated}"
  OPTIONS+=("${folder}:${folder}:${summary}")

  # --- the long form, for --list ---
  if [ "$ACTION" = "list" ]; then
    printf "  \033[1m%2d)\033[0m %s\n" "$idx" "$folder"
    [ -n "$instance" ] && printf "      lab: %s%s\n" "$instance" "${host:+  (machine: $host)}"
    [ -n "$updated" ]  && printf "      backup last updated: %s\n" "$updated"
    if [ -n "$newest" ]; then
      printf "      newest database dump: %s\n" "$newest"
    else
      printf "      \033[1;93mno database dumps in this folder\033[0m\n"
    fi
    echo
  fi
done

[ "$ACTION" = "list" ] && exit 0

choose CHOSEN "${FOLDERS[0]}" "Which lab should be restored?" "${OPTIONS[@]}"
SRC_DIR="${SRC_BASE}/${CHOSEN}"
print success "Restoring from ${CHOSEN}"

# --- what should come back? ---------------------------------------------------

choose what db "What should be copied back?" \
  "db:Just the database backups:The right choice for rebuilding a machine." \
  "all:Everything, including uploaded files and attachments:Much larger, and slower over a link."
# shellcheck disable=SC2154  # set by `choose` above, via printf -v
case "$what" in
  db) SUBPATH="/backups/db" ;;
  *)  SUBPATH="" ;;
esac

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
  # Names the lab, not just the path. ask_choice falls back to its default when
  # a gum menu is dismissed with Esc, so this confirm is the last point at which
  # restoring the wrong lab's database over this one is still catchable.
  if confirm "Restore ${CHOSEN} into ${LIS_PATH} now?"; then
    newest_dump="$(find "$DUMP_DIR" -maxdepth 1 -type f -name 'vlsm-*' | sort | tail -1)"
    if [ -z "$newest_dump" ]; then
      print error "No main-database backup (a file starting with 'vlsm-') was found in ${DUMP_DIR}."
      exit 1
    fi
    print info "Restoring $(basename "$newest_dump")..."
    if sudo -u www-data php "${LIS_PATH}/vendor/bin/db-tools" restore "$newest_dump"; then
      print success "Database restored"
      sudo -u www-data php "${LIS_PATH}/bin/migrate.php" || print warning "Could not apply database migrations; run 'intelis migrate' by hand."
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
