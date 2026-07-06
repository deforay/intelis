#!/bin/bash
set -Eeuo pipefail

# To use this script:
#   cd ~
#   wget -O remote-backup-windows.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/remote-backup-windows.sh
#   chmod u+x remote-intelis-backup-windows.sh
#   sudo ./remote-intelis-backup-windows.sh
#
# This is the Windows-destination twin of remote-backup.sh.
# Instead of rsync-over-SSH to an Ubuntu box, it mounts a Windows shared
# folder over SMB/CIFS and rsyncs the LIS directory into it.

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
require_cmd rsync
require_cmd awk
require_cmd sed
require_cmd mount

# Idempotency check
backup_script="/usr/local/bin/intelis-backup-windows.sh"
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

# --- Windows destination ------------------------------------------------------

print header "Setting up Windows backup destination"
print info "On the Windows machine, share a folder (e.g. C:\\InteLIS-Backups) and"
print info "create a dedicated local user with Change+Read access to that share."
read -r -p "Enter the Windows hostname or IP (e.g. 192.168.1.50): " win_host
read -r -p "Enter the Windows share name (e.g. InteLIS-Backups): " win_share
read -r -p "Enter the Windows username: " win_user
read -r -s -p "Enter the Windows password: " win_pass; echo
read -r -p "Enter the SMB protocol version [3.0]: " smb_vers
smb_vers=${smb_vers:-3.0}

if [ -z "${win_host// }" ] || [ -z "${win_share// }" ] || [ -z "${win_user// }" ]; then
  print error "Windows host, share, and username are required."
  exit 1
fi
if printf '%s' "$win_share" | grep -q ' '; then
  print error "Share name contains spaces. Re-share the folder with a space-free name (e.g. InteLIS-Backups)."
  exit 1
fi

# --- ensure tools -------------------------------------------------------------

print header "Ensuring required tools"
apt-get update -y
apt-get install -y cifs-utils rsync
require_cmd mount.cifs
print success "Tools installed"

# --- credentials + mount ------------------------------------------------------

print header "Configuring the SMB mount"
cred_file="/etc/intelis/smb-backup.cred"
mount_point="/mnt/intelis-backup"
mkdir -p /etc/intelis "$mount_point"

# Root-only credentials file so the password never lands in the mount table
umask 077
cat > "$cred_file" <<CREDS
username=$win_user
password=$win_pass
CREDS
chmod 600 "$cred_file"
print success "Credentials stored at $cred_file (root-only)"

unc="//${win_host}/${win_share}"
fstab_line="${unc} ${mount_point} cifs credentials=${cred_file},vers=${smb_vers},uid=root,gid=root,iocharset=utf8,file_mode=0640,dir_mode=0750,nofail,_netdev 0 0"

# Idempotent fstab management: drop any prior line for this mountpoint, add ours
if grep -qE "[[:space:]]${mount_point}[[:space:]]" /etc/fstab; then
  sed -i "\#[[:space:]]${mount_point}[[:space:]]#d" /etc/fstab
  print info "Replaced existing /etc/fstab entry for $mount_point"
fi
echo "$fstab_line" >> /etc/fstab
print success "Added /etc/fstab entry for $unc"

# Mount now and prove it is writable
mountpoint -q "$mount_point" && umount "$mount_point" 2>/dev/null || true
if ! mount "$mount_point"; then
  print error "Failed to mount $unc at $mount_point. Check host/share/credentials and that File and Printer Sharing is allowed through the Windows firewall."
  exit 1
fi
if ! ( : > "${mount_point}/.intelis-writetest" && rm -f "${mount_point}/.intelis-writetest" ); then
  print error "Mounted but not writable. Grant the Windows user Change permission on the share."
  exit 1
fi
print success "Windows share mounted and writable at $mount_point"

# --- Lab identity (UUID) ------------------------------------------------------

LAB_UUID_FILE="/etc/intelis/lab-uuid"
if [ ! -f "$LAB_UUID_FILE" ]; then
  LAB_UUID="$(cat /proc/sys/kernel/random/uuid)"
  printf '%s\n' "$LAB_UUID" > "$LAB_UUID_FILE"
  chmod 600 "$LAB_UUID_FILE"
else
  LAB_UUID="$(cat "$LAB_UUID_FILE")"
fi
print info "Lab UUID: $LAB_UUID"

# --- Remote folder name & identity guard -------------------------------------

print header "Remote lab folder"
REMOTE_LAB_FOLDER="$sanitized_name"
DEST_DIR="${mount_point}/backups/${REMOTE_LAB_FOLDER}"
REMOTE_META="${DEST_DIR}/.lab-meta"
print info "Using lab name as remote folder: $REMOTE_LAB_FOLDER"
print info "Destination: $DEST_DIR"

if [ -d "$DEST_DIR" ]; then
  REMOTE_UUID="$(awk -F= '/^lab_uuid=/{print $2}' "$REMOTE_META" 2>/dev/null || true)"
  if [ -n "$REMOTE_UUID" ]; then
    if [ "$REMOTE_UUID" = "$LAB_UUID" ]; then
      if [ "${AUTO_CONFIRM:-0}" != "1" ]; then
        print warning "Existing folder belongs to THIS lab (UUID matched)."
        read -r -p "Proceed to reuse this folder (re-setup/restore scenario)? (y/N): " ans
        [[ "$ans" =~ ^[Yy]$ ]] || { print info "Aborted by user."; exit 1; }
        print info "Proceeding with same-lab reuse."
      else
        print info "AUTO_CONFIRM=1 set; proceeding with same-lab reuse."
      fi
    else
      print error "Folder exists but UUID differs."
      print info  "Remote: $REMOTE_UUID"
      print info  "Local : $LAB_UUID"
      if [ "${ALLOW_CLAIM:-0}" != "1" ]; then
        print warning "Refusing to reuse. To force, rerun with ALLOW_CLAIM=1."
        exit 1
      fi
      read -r -p "Type 'CLAIM' to attach this machine to that folder: " c
      [ "$c" = "CLAIM" ] || { print error "Claim aborted."; exit 1; }
      printf 'lab_uuid=%s\nclaimed_at=%s\n' "$LAB_UUID" "$(date -u +%FT%TZ)" > "$REMOTE_META"
      print warning "Folder claimed with new UUID."
    fi
  else
    print warning "Existing folder has no metadata."
    if [ "${ALLOW_CLAIM:-0}" != "1" ]; then
      print warning "Set ALLOW_CLAIM=1 to use it, or choose another name."
      exit 1
    fi
    read -r -p "Type 'CLAIM' to initialize metadata for this folder: " c
    [ "$c" = "CLAIM" ] || { print error "Claim aborted."; exit 1; }
    printf 'lab_uuid=%s\ninitialized_at=%s\n' "$LAB_UUID" "$(date -u +%FT%TZ)" > "$REMOTE_META"
    print success "Metadata written."
  fi
else
  mkdir -p "$DEST_DIR"
  printf 'lab_uuid=%s\ncreated_at=%s\n' "$LAB_UUID" "$(date -u +%FT%TZ)" > "$REMOTE_META"
  print success "Created $DEST_DIR and metadata."
fi

print success "Remote structure ready."

# --- Write backup runner ------------------------------------------------------

print header "Creating backup runner"
cat >/usr/local/bin/intelis-backup-windows.sh <<'BACKUP_SCRIPT'
#!/bin/bash
set -Eeuo pipefail
trap 'echo -e "\033[1;91m❌ Error:\033[0m backup failed at line $LINENO (status $?)" | tee -a "$LOGFILE"' ERR

# Handle disable option
if [ "${1:-}" = "--disable" ]; then
    echo "🛑 Disabling Intelis (Windows) backup system..."
    if crontab -l 2>/dev/null | grep -q "/usr/local/bin/intelis-backup-windows.sh"; then
        crontab -l 2>/dev/null | grep -v "/usr/local/bin/intelis-backup-windows.sh" | crontab -
        echo "✅ Removed scheduled backups from cron"
    else
        echo "ℹ️  No scheduled backups found in cron"
    fi
    if pkill -f "intelis-backup-windows.sh" 2>/dev/null; then
        echo "✅ Stopped running backup process"
    else
        echo "ℹ️  No backup process currently running"
    fi
    echo ""
    echo "✅ Backup system disabled successfully!"
    echo "ℹ️  The Windows share stays mounted; remove its /etc/fstab line to fully detach."
    exit 0
fi

# Logging
LOGFILE="/var/log/intelis-backup-windows.log"
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
MOUNT_POINT="__MOUNT_POINT__"
DEST_DIR="__DEST_DIR__"
LAB_UUID="__LAB_UUID__"

RSYNC_BIN="/usr/bin/rsync"

print info "Starting full LIS backup to Windows share"
print info "Source: ${SOURCE_DIR}/"
print info "Dest  : ${DEST_DIR}/ (SMB mount at ${MOUNT_POINT})"

# Ensure the Windows share is mounted (fstab entry makes this a no-arg mount)
if ! mountpoint -q "$MOUNT_POINT"; then
  print warning "Share not mounted; attempting to mount ${MOUNT_POINT}"
  mount "$MOUNT_POINT" || { print error "Could not mount ${MOUNT_POINT}; is the Windows machine on and reachable?"; exit 1; }
fi

# Verify remote UUID before doing anything (guards against a re-pointed share)
REMOTE_UUID="$(awk -F= '/^lab_uuid=/{print $2}' "${DEST_DIR}/.lab-meta" 2>/dev/null || true)"
if [ -z "$REMOTE_UUID" ] || [ "$REMOTE_UUID" != "$LAB_UUID" ]; then
  print error "Remote lab UUID mismatch or missing; aborting sync."
  print info  "Remote: ${REMOTE_UUID:-<none>}  Local: $LAB_UUID"
  exit 1
fi

# Verify source directory exists
[ -d "${SOURCE_DIR}" ] || { print error "Source directory ${SOURCE_DIR} does not exist"; exit 1; }

# Disk-space check on the mounted share (GiB, locale-agnostic)
check_disk_space() {
  local available
  available=$(df -Pk "$MOUNT_POINT" 2>/dev/null | awk 'NR==2{print int($4/1024/1024)}' || echo 0)
  available=${available:-0}
  if [ "$available" -lt 5 ]; then
    print warning "Low disk space on Windows share at ${MOUNT_POINT}: ${available} GiB available"
    [ "$available" -ge 2 ] || { print error "Critical: <2 GiB available"; return 1; }
  fi
  return 0
}

verify_sync() {
  local source_count dest_count diff
  source_count=$(find "$SOURCE_DIR" -type f | wc -l | tr -d ' ')
  dest_count=$(find "$DEST_DIR" -name '.lab-meta' -prune -o -type f -print 2>/dev/null | wc -l | tr -d ' ')
  print info "Verification: Source: ${source_count} files, Dest: ${dest_count} files"
  diff=$((source_count - dest_count))
  [ "${diff#-}" -le 10 ] || return 1
  return 0
}

check_disk_space || { print error "Disk space check failed - aborting"; exit 1; }

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

# Perform the full backup with rsync.
# NOTE: CIFS cannot store POSIX ownership/permissions/symlinks, so we do NOT use -a.
#   --no-perms/--no-owner/--no-group : don't try to push metadata CIFS rejects
#   --omit-dir-times                 : CIFS dir mtimes are unreliable and cause churn
#   -L                               : copy symlink targets as real files (Windows has no symlinks)
#   --modify-window=2                : tolerate FAT/NTFS 2s timestamp granularity
if $RSYNC_BIN -rtLz --delete --partial --timeout=900 \
    --no-perms --no-owner --no-group --omit-dir-times --modify-window=2 \
    --exclude-from="$EXCLUDE_LIST" \
    --exclude='.lab-meta' \
    "${SOURCE_DIR}/" "${DEST_DIR}/"; then
  print success "Full LIS directory sync completed"
  rm -f "$EXCLUDE_LIST"

  if ! verify_sync; then
    print warning "File count mismatch detected (may be normal due to excludes)"
  fi

  # Apply retention policy for backup directories within the synced content
  print info "Applying retention policy for internal backup directories..."
  if [ -d "${DEST_DIR}/backups" ]; then
    ( cd "${DEST_DIR}/backups" && ls -1t | tail -n +8 | xargs -r -I{} rm -rf -- "{}" ) 2>/dev/null || true
    print info "Applied retention policy to remote backups directory (kept 7 newest)"
  fi
else
  rm -f "$EXCLUDE_LIST"
  print error "Full LIS directory sync failed"; exit 1
fi

# Final verification
if ls -la "$DEST_DIR" >/dev/null 2>&1; then
  print success "Full backup completed successfully at $(date)"
  BACKUP_SIZE=$(du -sh "$DEST_DIR" 2>/dev/null | cut -f1 || echo "unknown")
  print info "Total backup size: ${BACKUP_SIZE}"
else
  print error "Final verification failed - destination not accessible"; exit 1
fi

# Light log cleanup (recommend logrotate for production)
find /var/log -maxdepth 1 -name "intelis-backup-windows.log*" -mtime +30 -type f -delete 2>/dev/null || true
BACKUP_SCRIPT

chmod 0755 /usr/local/bin/intelis-backup-windows.sh

# Substitute setup-time values into runner
sed -i \
  -e "s#__LIS_PATH__#$(escape_sed "$lis_path")#g" \
  -e "s#__MOUNT_POINT__#$(escape_sed "$mount_point")#g" \
  -e "s#__DEST_DIR__#$(escape_sed "$DEST_DIR")#g" \
  -e "s#__LAB_UUID__#$(escape_sed "$LAB_UUID")#g" \
  /usr/local/bin/intelis-backup-windows.sh

print success "Backup runner written to $backup_script"

# --- cron scheduling ----------------------------------------------------------

print header "Scheduling backups (cron)"
( crontab -l 2>/dev/null | grep -v "/usr/local/bin/intelis-backup-windows.sh" || true ) | crontab -
( crontab -l 2>/dev/null; echo "@reboot /usr/local/bin/intelis-backup-windows.sh" ; echo "0 */8 * * * /usr/local/bin/intelis-backup-windows.sh" ) | crontab -
print success "Scheduled backups configured (every 8 hours and at reboot)"

# --- initial backup -----------------------------------------------------------

print header "Starting Initial Backup"
print info "Launching first backup in background to verify configuration..."
print info "You can monitor progress with: tail -f /var/log/intelis-backup-windows.log"

nohup /usr/local/bin/intelis-backup-windows.sh > /dev/null 2>&1 &
BACKUP_PID=$!

print success "Initial backup started (PID: $BACKUP_PID)"

# --- summary ------------------------------------------------------------------

print header "Setup Complete"
print success "Backup system configured! Initial backup is running in background."
print info    "Monitor backup: tail -f /var/log/intelis-backup-windows.log"
print info    "Manual run: /usr/local/bin/intelis-backup-windows.sh"
print info    "Disable backups: /usr/local/bin/intelis-backup-windows.sh --disable"
print info    "LIS path  : $lis_path (entire directory will be backed up)"
print info    "Windows   : $unc mounted at $mount_point"
print info    "Dest dir  : $DEST_DIR"
print info    "Schedule  : Every 8 hours and at reboot"
print info    "Excluded items: public/temporary/, var/logs/, vendor/, var/cache/, temp files, version control"
