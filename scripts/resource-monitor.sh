#!/usr/bin/env bash
# resource-monitor.sh
# Idempotent setup for monitoring memory and disk usage with alerts and cleanup

set -euo pipefail

# ---------- configuration ----------
MEMORY_WARNING_THRESHOLD=80    # %
MEMORY_CRITICAL_THRESHOLD=90   # %
DISK_WARNING_THRESHOLD=85      # %
DISK_CRITICAL_THRESHOLD=95     # %

# ---------- helpers ----------
need_root() {
  if [[ $EUID -ne 0 ]]; then
    echo "Re-running with sudo..."
    exec sudo -E bash "$0" "$@"
  fi
}

install_dependencies() {
  local packages_to_install=()

  # Check for required commands
  if ! command -v bc >/dev/null 2>&1; then
    packages_to_install+=("bc")
  fi

  if [[ ${#packages_to_install[@]} -gt 0 ]]; then
    echo "Installing required packages: ${packages_to_install[*]}"

    # Detect package manager and install
    if command -v apt-get >/dev/null 2>&1; then
      apt-get update -qq
      apt-get install -y "${packages_to_install[@]}"
    elif command -v yum >/dev/null 2>&1; then
      yum install -y "${packages_to_install[@]}"
    elif command -v dnf >/dev/null 2>&1; then
      dnf install -y "${packages_to_install[@]}"
    elif command -v pacman >/dev/null 2>&1; then
      pacman -S --noconfirm "${packages_to_install[@]}"
    else
      echo "Warning: Could not detect package manager. Please install: ${packages_to_install[*]}"
      return 1
    fi

    echo "Dependencies installed successfully."
  fi
}

write_if_different() {
  local target="$1"
  local tmp
  tmp="$(mktemp)"
  cat >"$tmp"
  if [[ -f "$target" ]] && cmp -s "$tmp" "$target"; then
    rm -f "$tmp"
    return 1  # unchanged
  fi
  install -D -m 0644 "$tmp" "$target"
  rm -f "$tmp"
  return 0  # written/changed
}

# ---------- main monitoring script ----------
install_monitor_script() {
  install -d -m 0755 /usr/local/sbin
  local target="/usr/local/sbin/resource-monitor.sh"
  local changed=0

  if write_if_different "$target" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
PATH=/usr/sbin:/usr/bin:/sbin:/bin

# Configuration
MEMORY_WARNING=80
MEMORY_CRITICAL=90
DISK_WARNING=85
DISK_CRITICAL=95

log() { logger -t resource-monitor "$*"; }
alert() { log "ALERT: $*"; }

# One instance at a time
exec 9>/run/resource-monitor.lock
flock -n 9 || { log "another instance running"; exit 0; }

# Memory check
check_memory() {
  local mem_info
  mem_info=$(free | awk '/^Mem:/ {printf "%.0f", ($3/$2) * 100}')

  if (( mem_info >= MEMORY_CRITICAL )); then
    alert "Memory usage critical: ${mem_info}% (threshold: ${MEMORY_CRITICAL}%)"
    # Emergency cleanup
    sync && echo 3 > /proc/sys/vm/drop_caches
    log "Cleared system caches due to critical memory usage"
  elif (( mem_info >= MEMORY_WARNING )); then
    alert "Memory usage high: ${mem_info}% (threshold: ${MEMORY_WARNING}%)"
  else
    log "Memory usage normal: ${mem_info}%"
  fi
}

# Disk check for critical partitions
check_disk() {
  local partition usage mount_point

  # Check root partition and common mount points
  for mount_point in "/" "/var" "/tmp" "/home"; do
    if mountpoint -q "$mount_point" 2>/dev/null; then
      usage=$(df "$mount_point" | awk 'NR==2 {print int($5)}')

      if (( usage >= DISK_CRITICAL )); then
        alert "Disk ${mount_point} critical: ${usage}% (threshold: ${DISK_CRITICAL}%)"
        # Emergency cleanup for common directories
        case "$mount_point" in
          "/")
            # Clean system logs older than 7 days
            journalctl --vacuum-time=7d >/dev/null 2>&1 || true
            # Clean package cache
            apt-get clean >/dev/null 2>&1 || yum clean all >/dev/null 2>&1 || true
            ;;
          "/var")
            # Clean old logs
            find /var/log -name "*.log.*.gz" -mtime +30 -delete 2>/dev/null || true
            find /var/log -name "*.log.*" -mtime +7 -delete 2>/dev/null || true
            ;;
          "/tmp")
            # Clean old temp files
            find /tmp -type f -mtime +3 -delete 2>/dev/null || true
            ;;
        esac
        log "Performed emergency cleanup for ${mount_point}"
      elif (( usage >= DISK_WARNING )); then
        alert "Disk ${mount_point} high: ${usage}% (threshold: ${DISK_WARNING}%)"
      else
        log "Disk ${mount_point} normal: ${usage}%"
      fi
    fi
  done
}

# Check for high CPU processes
check_processes() {
  local high_cpu_procs
  high_cpu_procs=$(ps -eo pid,ppid,cmd,%cpu --sort=-%cpu | head -6 | tail -5)

  # Log top 5 CPU processes if any are above 50%
  if ps -eo %cpu --no-headers | awk '$1 > 50 {exit 0} END {exit 1}'; then
    log "High CPU processes detected:"
    while IFS= read -r line; do
      log "  $line"
    done <<< "$high_cpu_procs"
  fi
}

# Check load average (with bc for precision)
check_load() {
  local load_1min cores load_per_core
  load_1min=$(uptime | awk -F'load average:' '{print $2}' | awk -F',' '{gsub(/^ */, "", $1); print $1}')
  cores=$(nproc)

  if command -v bc >/dev/null 2>&1; then
    load_per_core=$(echo "scale=2; $load_1min / $cores" | bc)
    if (( $(echo "$load_per_core > 2.0" | bc) )); then
      alert "Load average high: $load_1min (${load_per_core} per core)"
    else
      log "Load average normal: $load_1min (${load_per_core} per core, ${cores} cores)"
    fi
  else
    # Fallback without bc - simple integer comparison
    local load_int cores_threshold
    load_int=$(echo "$load_1min" | awk '{printf "%.0f", $1 * 100}')
    cores_threshold=$((cores * 200))

    if (( load_int > cores_threshold )); then
      alert "Load average high: $load_1min on ${cores} cores"
    else
      log "Load average normal: $load_1min (${cores} cores)"
    fi
  fi
}

# Main checks
log "Starting resource monitoring check"
check_memory
check_disk
check_processes
check_load
log "Resource monitoring check completed"
EOF
  then
    chmod 0755 "$target"
    changed=1
  fi
  echo "$changed"
}

install_units() {
  local srv_changed=0 tim_changed=0

  if write_if_different "/etc/systemd/system/resource-monitor.service" <<'EOF'
[Unit]
Description=Resource monitor for memory and disk usage
After=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/resource-monitor.sh
EOF
  then srv_changed=1; fi

  if write_if_different "/etc/systemd/system/resource-monitor.timer" <<'EOF'
[Unit]
Description=Run resource monitor every 2 minutes

[Timer]
OnBootSec=120s
OnUnitActiveSec=120s
AccuracySec=10s
Unit=resource-monitor.service
Persistent=true

[Install]
WantedBy=timers.target
EOF
  then tim_changed=1; fi

  echo "$srv_changed$tim_changed"
}

enable_timer_idempotent() {
  systemctl daemon-reload
  if ! systemctl is-enabled --quiet resource-monitor.timer; then
    systemctl enable --now resource-monitor.timer >/dev/null
  else
    systemctl start resource-monitor.timer >/dev/null || true
  fi
}

uninstall_all() {
  systemctl disable --now resource-monitor.timer 2>/dev/null || true
  rm -f /etc/systemd/system/resource-monitor.timer
  rm -f /etc/systemd/system/resource-monitor.service
  rm -f /usr/local/sbin/resource-monitor.sh
  systemctl daemon-reload
  echo "Resource Monitor uninstalled."
  exit 0
}

# ---------- main ----------
main() {
  if [[ "${1:-}" == "--uninstall" ]]; then
    need_root "$@"
    uninstall_all
  fi

  need_root "$@"

  # Install any missing dependencies
  install_dependencies

  # Install monitoring script
  MONITOR_CHANGED="$(install_monitor_script)"

  # Install systemd units
  UNITS_CHANGED="$(install_units)"
  if [[ "$UNITS_CHANGED" != "00" || "$MONITOR_CHANGED" == "1" ]]; then
    systemctl daemon-reload
  fi

  enable_timer_idempotent

  echo "✅ Installed/updated Resource Monitor idempotently."
  echo
  echo "Monitoring thresholds:"
  echo "  Memory: Warning ${MEMORY_WARNING_THRESHOLD}%, Critical ${MEMORY_CRITICAL_THRESHOLD}%"
  echo "  Disk: Warning ${DISK_WARNING_THRESHOLD}%, Critical ${DISK_CRITICAL_THRESHOLD}%"
  echo
  echo "Timer status: systemctl status resource-monitor.timer"
  echo "Logs: journalctl -u resource-monitor.service -n 50 --no-pager"
  echo "Manual run: sudo /usr/local/sbin/resource-monitor.sh"
}

main "$@"
