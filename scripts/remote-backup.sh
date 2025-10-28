#!/bin/bash
set -Eeuo pipefail

# To use this script:
#   cd ~
#   wget -O remote-intelis-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/remote-backup.sh
#   chmod u+x remote-intelis-backup.sh
#   sudo ./remote-intelis-backup.sh

trap 'echo -e "\033[1;91m❌ Error:\033[0m setup failed at line $LINENO (status $?)"' ERR

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
escape_sed() { printf '%s' "$1" | sed 's/[&/\]/\\&/g'; }

# --- preflight ----------------------------------------------------------------

if [ "$(id -u)" -ne 0 ]; then
  echo "Need admin privileges. Run with sudo."
  exit 1
fi

require_cmd realpath
require_cmd ssh
require_cmd ssh-keygen
require_cmd ssh-copy-id
require_cmd rsync
require_cmd awk
require_cmd sed

# Idempotency check
backup_script="/usr/local/bin/intelis-backup.sh"
if [ -f "$backup_script" ]; then
  print warning "Backup script already exists at $backup_script."
  read -r -p "Reconfigure anyway? (y/N): " answer
  [[ "$answer" =~ ^[Yy]$ ]] || { print info "Cancelled."; exit 0; }
fi

# --- instance name ------------------------------------------------------------

print header "Setting up instance name"
read -r -p "Enter the current lab name or lab code: " instance_name
if [ -z "${instance_name// }" ]; then
  print error "Instance name cannot be empty."
  exit 1
fi
sanitized_name=$(echo "$instance_name" | xargs | tr -s '[:space:]' '-' | tr -cd '[:alnum:]-' | sed 's/-*$//')
instance_name_file="/var/www/.instance_name"
mkdir -p /var/www
echo "$sanitized_name" > "$instance_name_file"
print success "Instance name set to: $sanitized_name"

# --- LIS path -----------------------------------------------------------------

print header "Setting up LIS folder path"
default_lis_path="/var/www/intelis"
read -r -p "Enter the LIS folder path [default: $default_lis_path]: " lis_path
lis_path=${lis_path:-$default_lis_path}
[[ "$lis_path" != /* ]] && lis_path="$(realpath "$lis_path")" && print info "Converted to absolute path: $lis_path"

[ -d "$lis_path" ] || { print error "Path '$lis_path' does not exist."; exit 1; }

# Minimal installation sanity check
if [ ! -f "$lis_path/configs/config.production.php" ] || [ ! -d "$lis_path/public" ]; then
  print error "'$lis_path' does not look like a valid LIS installation."
  exit 1
fi

print success "Valid LIS installation: $lis_path"

# --- remote host --------------------------------------------------------------

print header "Setting up backup destination"
read -r -p "Enter the backup Ubuntu username: " backup_user
read -r -p "Enter the backup Ubuntu hostname or IP: " backup_host
read -r -p "Enter the SSH port [22]: " backup_port
backup_port=${backup_port:-22}

# --- SSH keys -----------------------------------------------------------------

print header "Setting up SSH keys"
ssh_dir="$HOME/.ssh"
ssh_key="$ssh_dir/id_ed25519"
mkdir -p "$ssh_dir"; chmod 700 "$ssh_dir"
if [ ! -f "$ssh_key" ]; then
  print info "Generating ed25519 SSH key..."
  ssh-keygen -t ed25519 -C "$sanitized_name" -N "" -f "$ssh_key"
else
  print info "SSH key already exists."
fi
chmod 600 "$ssh_key" "$ssh_key.pub"

print info "Copying SSH key to backup machine (one-time password expected)..."
if ! ssh-copy-id -p "$backup_port" "$backup_user@$backup_host" >/dev/null; then
  print error "ssh-copy-id failed. Check host/user/port."
  exit 1
fi

print info "Testing passwordless SSH..."
if ! ssh -p "$backup_port" "$backup_user@$backup_host" "echo ok" >/dev/null; then
  print error "SSH key auth test failed."
  exit 1
fi
print success "SSH key setup successful"

# --- Lab identity (UUID) ------------------------------------------------------

LAB_UUID_FILE="/etc/intelis/lab-uuid"
mkdir -p /etc/intelis
if [ ! -f "$LAB_UUID_FILE" ]; then
  LAB_UUID="$(cat /proc/sys/kernel/random/uuid)"
  printf '%s\n' "$LAB_UUID" > "$LAB_UUID_FILE"
  chmod 600 "$LAB_UUID_FILE"
else
  LAB_UUID="$(cat "$LAB_UUID_FILE")"
fi
print info "Lab UUID: $LAB_UUID"

# --- Remote folder name & ownership ------------------------------------------

print header "Remote lab folder"
REMOTE_LAB_FOLDER="$sanitized_name"
print info "Using lab name as remote folder: $REMOTE_LAB_FOLDER"

# Resolve remote HOME to avoid '~' expansion issues
REMOTE_HOME="$(ssh -p "$backup_port" "$backup_user@$backup_host" 'printf %s "$HOME"')"
REMOTE_BASE_DIR="${REMOTE_HOME}/backups"
DEST_DIR="${REMOTE_BASE_DIR}/${REMOTE_LAB_FOLDER}"
REMOTE_META="${DEST_DIR}/.lab-meta"

print info "Checking ${backup_user}@${backup_host}:${DEST_DIR}"
if ssh -p "$backup_port" "$backup_user@$backup_host" "test -d \"$DEST_DIR\""; then
  # Existing folder: read remote UUID (if any)
  REMOTE_UUID="$(ssh -p "$backup_port" "$backup_user@$backup_host" \
    "awk -F= '/^lab_uuid=/{print \$2}' \"$REMOTE_META\" 2>/dev/null || true")"

  if [ -n "$REMOTE_UUID" ]; then
    if [ "$REMOTE_UUID" = "$LAB_UUID" ]; then
      # SAME LAB → allow with confirmation
      if [ "${AUTO_CONFIRM:-0}" != "1" ]; then
        print warning "Existing folder belongs to THIS lab (UUID matched)."
        read -r -p "Proceed to reuse this folder (re-setup/restore scenario)? (y/N): " ans
        [[ "$ans" =~ ^[Yy]$ ]] || { print info "Aborted by user."; exit 1; }
        print info "Proceeding with same-lab reuse."
      else
        print info "AUTO_CONFIRM=1 set; proceeding with same-lab reuse."
      fi
    else
      # DIFFERENT LAB → block unless operator claims
      print error "Folder exists but UUID differs."
      print info  "Remote: $REMOTE_UUID"
      print info  "Local : $LAB_UUID"
      if [ "${ALLOW_CLAIM:-0}" != "1" ]; then
        print warning "Refusing to reuse. To force, rerun with ALLOW_CLAIM=1."
        exit 1
      fi
      read -r -p "Type 'CLAIM' to attach this machine to that folder: " c
      [ "$c" = "CLAIM" ] || { print error "Claim aborted."; exit 1; }
      ssh -p "$backup_port" "$backup_user@$backup_host" \
        "printf 'lab_uuid=%s\nclaimed_at=%s\n' '$LAB_UUID' '$(date -u +%FT%TZ)' > \"$REMOTE_META\""
      print warning "Folder claimed with new UUID."
    fi
  else
    # No metadata → cautious: require explicit claim
    print warning "Existing folder has no metadata."
    if [ "${ALLOW_CLAIM:-0}" != "1" ]; then
      print warning "Set ALLOW_CLAIM=1 to use it, or choose another name."
      exit 1
    fi
    read -r -p "Type 'CLAIM' to initialize metadata for this folder: " c
    [ "$c" = "CLAIM" ] || { print error "Claim aborted."; exit 1; }
    ssh -p "$backup_port" "$backup_user@$backup_host" \
      "printf 'lab_uuid=%s\ninitialized_at=%s\n' '$LAB_UUID' '$(date -u +%FT%TZ)' > \"$REMOTE_META\""
    print success "Metadata written."
  fi
else
  # New folder: create + write metadata
  ssh -p "$backup_port" "$backup_user@$backup_host" \
    "mkdir -p \"$DEST_DIR\" && printf 'lab_uuid=%s\ncreated_at=%s\n' '$LAB_UUID' '$(date -u +%FT%TZ)' > \"$REMOTE_META\""
  print success "Created $DEST_DIR and metadata."
fi

print success "Remote structure ready."

# --- ensure tools -------------------------------------------------------------

print header "Ensuring required tools"
apt-get update -y
apt-get install -y rsync openssh-client
print success "Tools installed"

# --- Write backup runner ------------------------------------------------------

print header "Creating backup runner"
cat >/usr/local/bin/intelis-backup.sh <<'BACKUP_SCRIPT'
#!/bin/bash
set -Eeuo pipefail
trap 'echo -e "\033[1;91m❌ Error:\033[0m backup failed at line $LINENO (status $?)" | tee -a "$LOGFILE"' ERR

# Handle disable option
if [ "${1:-}" = "--disable" ]; then
    echo "🛑 Disabling Intelis backup system..."

    # Remove cron jobs
    if crontab -l 2>/dev/null | grep -q "/usr/local/bin/intelis-backup.sh"; then
        crontab -l 2>/dev/null | grep -v "/usr/local/bin/intelis-backup.sh" | crontab -
        echo "✅ Removed scheduled backups from cron"
    else
        echo "ℹ️  No scheduled backups found in cron"
    fi

    # Kill any running backup process
    if pkill -f "intelis-backup.sh" 2>/dev/null; then
        echo "✅ Stopped running backup process"
    else
        echo "ℹ️  No backup process currently running"
    fi

    echo ""
    echo "✅ Backup system disabled successfully!"
    echo "ℹ️  To re-enable, run the setup script again or manually add to cron"
    echo "ℹ️  Manual backup still available: $0"
    exit 0
fi

# Logging
LOGFILE="/var/log/intelis-backup.log"
umask 027
: > "$LOGFILE" 2>/dev/null || true
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

# Filled by setup
SOURCE_DIR="__LIS_PATH__"
BACKUP_USER="__BACKUP_USER__"
BACKUP_HOST="__BACKUP_HOST__"
BACKUP_PORT="__BACKUP_PORT__"
DEST_DIR="__DEST_DIR__"
LAB_UUID="__LAB_UUID__"

RSYNC_BIN="/usr/bin/rsync"
SSH_RAW="/usr/bin/ssh"
SSH_BIN="/usr/bin/ssh -o BatchMode=yes -o ConnectTimeout=10"

print info "Starting full LIS backup"
print info "Source: ${SOURCE_DIR}/"
print info "Dest  : ${BACKUP_USER}@${BACKUP_HOST}:${DEST_DIR}/"

# Verify remote UUID before doing anything
REMOTE_UUID="$($SSH_BIN -p "${BACKUP_PORT}" "${BACKUP_USER}@${BACKUP_HOST}" \
  "awk -F= '/^lab_uuid=/{print \$2}' \"${DEST_DIR}/.lab-meta\" 2>/dev/null || true")"
if [ -z "$REMOTE_UUID" ] || [ "$REMOTE_UUID" != "$LAB_UUID" ]; then
  print error "Remote lab UUID mismatch or missing; aborting sync."
  print info  "Remote: ${REMOTE_UUID:-<none>}  Local: $LAB_UUID"
  exit 1
fi

# Verify source directory exists
[ -d "${SOURCE_DIR}" ] || { print error "Source directory ${SOURCE_DIR} does not exist"; exit 1; }

# Disk-space check on remote (GiB, locale-agnostic)
check_disk_space() {
  local available
  available=$($SSH_BIN -p "${BACKUP_PORT}" "${BACKUP_USER}@${BACKUP_HOST}" \
    "df -Pk \$(printf %q \"${DEST_DIR}\") 2>/dev/null | awk 'NR==2{print int(\$4/1024/1024)}'" \
    || echo 0)
  if [ "$available" -lt 5 ]; then
    print warning "Low disk space on backup server at ${DEST_DIR}: ${available} GiB available"
    [ "$available" -ge 2 ] || { print error "Critical: <2 GiB available"; return 1; }
  fi
  return 0
}

verify_sync() {
  local source_count dest_count diff
  # Count files in source (excluding .lab-meta to avoid confusion)
  source_count=$(find "$SOURCE_DIR" -type f | wc -l | tr -d ' ')
  # Count files in destination (excluding .lab-meta)
  dest_count=$($SSH_BIN -p "${BACKUP_PORT}" "${BACKUP_USER}@${BACKUP_HOST}" \
    "find \$(printf %q \"${DEST_DIR}\") -name '.lab-meta' -prune -o -type f -print 2>/dev/null | wc -l | tr -d ' '" \
    || echo 0)
  print info "Verification: Source: ${source_count} files, Dest: ${dest_count} files"
  diff=$((source_count - dest_count))
  [ "${diff#-}" -le 10 ] || return 1  # Allow slightly larger tolerance for full backup
  return 0
}

check_disk_space || { print error "Disk space check failed - aborting"; exit 1; }

# Get source directory name for better remote organization
SOURCE_BASE_NAME=$(basename "${SOURCE_DIR}")

print info "Syncing entire LIS directory: ${SOURCE_BASE_NAME}/ …"

# Create exclusion list for sensitive/unnecessary files
EXCLUDE_LIST="/tmp/backup-excludes.$"
cat > "$EXCLUDE_LIST" <<'EXCLUDES'
# LIS-specific directories to exclude
/public/temporary/
/var/logs/
/vendor/
/var/cache/

# Temporary and cache files
*.tmp
*.temp
*.cache
.DS_Store
Thumbs.db

# Lock files
*.lock
*.pid

# Session files
/tmp/
/temp/

# Version control
.git/
.svn/
.hg/

# IDE and editor files
/.vscode/
/.idea/
*.swp
*.swo
*~

# OS generated files
.directory
desktop.ini

# Development dependencies that can be regenerated
/node_modules/
/bower_components/

# Database dumps that might be in progress
*.sql.tmp
*.sql.partial
EXCLUDES

# Perform the full backup with rsync
if $RSYNC_BIN -aHz --delete --partial --timeout=900 \
    --exclude-from="$EXCLUDE_LIST" \
    --exclude='.lab-meta' \
    -e "$SSH_RAW -o BatchMode=yes -o ConnectTimeout=10 -p ${BACKUP_PORT}" \
    "${SOURCE_DIR}/" "${BACKUP_USER}@${BACKUP_HOST}:${DEST_DIR}/"; then
  print success "Full LIS directory sync completed"

  # Clean up exclude list
  rm -f "$EXCLUDE_LIST"

  if ! verify_sync; then
    print warning "File count mismatch detected (may be normal due to excludes)"
  fi

  # Apply retention policy for backup directories within the synced content
  print info "Applying retention policy for internal backup directories..."
  if $SSH_BIN -p "${BACKUP_PORT}" "${BACKUP_USER}@${BACKUP_HOST}" "test -d \"${DEST_DIR}/backups\""; then
    $SSH_BIN -p "${BACKUP_PORT}" "${BACKUP_USER}@${BACKUP_HOST}" \
      "cd \$(printf %q \"${DEST_DIR}/backups\") 2>/dev/null && ls -1t | tail -n +8 | xargs -r -I{} rm -rf -- \"{}\" 2>/dev/null || true"
    print info "Applied retention policy to remote backups directory (kept 7 newest)"
  fi

else
  rm -f "$EXCLUDE_LIST"
  print error "Full LIS directory sync failed"; exit 1
fi

# Final verification
if $SSH_BIN -p "${BACKUP_PORT}" "${BACKUP_USER}@${BACKUP_HOST}" "ls -la \$(printf %q \"${DEST_DIR}\") >/dev/null"; then
  print success "Full backup completed successfully at $(date)"

  # Report backup size
  BACKUP_SIZE=$($SSH_BIN -p "${BACKUP_PORT}" "${BACKUP_USER}@${BACKUP_HOST}" \
    "du -sh \$(printf %q \"${DEST_DIR}\") 2>/dev/null | cut -f1" || echo "unknown")
  print info "Total backup size: ${BACKUP_SIZE}"
else
  print error "Final verification failed - destination not accessible"; exit 1
fi

# Light log cleanup (recommend logrotate for production)
find /var/log -maxdepth 1 -name "intelis-backup.log*" -mtime +30 -type f -delete 2>/dev/null || true
BACKUP_SCRIPT

chmod 0755 /usr/local/bin/intelis-backup.sh

# Substitute setup-time values into runner
sed -i \
  -e "s#__LIS_PATH__#$(escape_sed "$lis_path")#g" \
  -e "s#__BACKUP_USER__#$(escape_sed "$backup_user")#g" \
  -e "s#__BACKUP_HOST__#$(escape_sed "$backup_host")#g" \
  -e "s#__BACKUP_PORT__#$(escape_sed "$backup_port")#g" \
  -e "s#__DEST_DIR__#$(escape_sed "$DEST_DIR")#g" \
  -e "s#__LAB_UUID__#$(escape_sed "$LAB_UUID")#g" \
  /usr/local/bin/intelis-backup.sh

print success "Backup runner written to $backup_script"

# --- cron scheduling ----------------------------------------------------------

print header "Scheduling backups (cron)"
( crontab -l 2>/dev/null | grep -v "/usr/local/bin/intelis-backup.sh" || true ) | crontab -
( crontab -l 2>/dev/null; echo "@reboot /usr/local/bin/intelis-backup.sh" ; echo "0 */8 * * * /usr/local/bin/intelis-backup.sh" ) | crontab -
print success "Scheduled backups configured (every 8 hours and at reboot)"

# --- initial backup -----------------------------------------------------------

print header "Starting Initial Backup"
print info "Launching first backup in background to verify configuration..."
print info "You can monitor progress with: tail -f /var/log/intelis-backup.log"

# Start backup in background and capture PID
nohup /usr/local/bin/intelis-backup.sh > /dev/null 2>&1 &
BACKUP_PID=$!

print success "Initial backup started (PID: $BACKUP_PID)"
print info "Setup complete! Backup is running in background."
print info "Check status: ps -p $BACKUP_PID || echo 'Backup finished'"

# --- summary ------------------------------------------------------------------

print header "Setup Complete"
print success "Backup system configured! Initial backup is running in background."
print info    "Monitor backup: tail -f /var/log/intelis-backup.log"
print info    "Manual run: /usr/local/bin/intelis-backup.sh"
print info    "Disable backups: /usr/local/bin/intelis-backup.sh --disable"
print info    "LIS path  : $lis_path (entire directory will be backed up)"
print info    "Remote    : ${DEST_DIR} on ${backup_user}@${backup_host}:${backup_port}"
print info    "Schedule  : Every 8 hours and at reboot"
print info    "Excluded items: public/temporary/, var/logs/, vendor/, var/cache/, temp files, version control"
