#!/usr/bin/env bash
# service-guard.sh
# Idempotent setup for a periodic guard that keeps Apache/MySQL up.

set -euo pipefail

# ---------- helpers ----------
need_root() {
  if [[ $EUID -ne 0 ]]; then
    echo "Re-running with sudo..."
    exec sudo -E bash "$0" "$@"
  fi
}

write_if_different() {
  # $1 target, $2 heredoc content label
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

detect_services() {
  SVC_APACHE=""
  SVC_MYSQL=""

  # Improved detection - no pipelines, works with set -e
  if systemctl list-unit-files apache2.service >/dev/null 2>&1; then
    SVC_APACHE="apache2"
  elif systemctl list-unit-files httpd.service >/dev/null 2>&1; then
    SVC_APACHE="httpd"
  fi

  if systemctl list-unit-files mysql.service >/dev/null 2>&1; then
    SVC_MYSQL="mysql"
  elif systemctl list-unit-files mariadb.service >/dev/null 2>&1; then
    SVC_MYSQL="mariadb"
  fi

  echo "Detected: Apache='${SVC_APACHE:-none}', MySQL='${SVC_MYSQL:-none}'"
}

is_active() { systemctl is-active --quiet "$1"; }
restart_if_running_and_changed() {
  local svc="$1" changed="$2"
  if [[ "$changed" == "1" ]]; then
    # Only restart to apply new override if it's already running (avoid starting a disabled service)
    if is_active "$svc"; then systemctl restart "$svc" || true; fi
  fi
}

# ---------- files ----------
install_guard_script() {
  install -d -m 0755 /usr/local/sbin
  local target="/usr/local/sbin/service-guard.sh"
  local changed=0
  if write_if_different "$target" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
PATH=/usr/sbin:/usr/bin:/sbin:/bin

log(){ logger -t service-guard "$*"; }

# One instance at a time
exec 9>/run/service-guard.lock
flock -n 9 || { log "another instance is running"; exit 0; }

# Detect services without pipelines (works with set -e)
services=()
if systemctl list-unit-files apache2.service >/dev/null 2>&1; then services+=("apache2"); fi
if systemctl list-unit-files httpd.service   >/dev/null 2>&1; then services+=("httpd");   fi
if systemctl list-unit-files mysql.service   >/dev/null 2>&1; then services+=("mysql");   fi
if systemctl list-unit-files mariadb.service >/dev/null 2>&1; then services+=("mariadb"); fi

log "Monitoring services: ${services[*]}"

# Health checks
hc_http(){
  # Config test if available
  if command -v apachectl >/dev/null 2>&1; then apachectl -t >/dev/null 2>&1 || return 1; fi
  # Probe localhost
  if command -v curl >/dev/null 2>&1; then
    curl -fsS --max-time 3 http://127.0.0.1/ >/dev/null 2>&1 || return 1
  elif command -v wget >/dev/null 2>&1; then
    wget -q -T 3 -O /dev/null http://127.0.0.1/ || return 1
  else
    # No HTTP client; fall back to config-only if present
    if command -v apachectl >/dev/null 2>&1; then return 0; else return 1; fi
  fi
}

hc_mysql(){
  if command -v mysqladmin >/dev/null 2>&1; then
    mysqladmin ping --silent >/dev/null 2>&1
  else
    # No client; rely on systemd status only
    return 0
  fi
}

for svc in "${services[@]}"; do
  if ! systemctl is-active --quiet "$svc"; then
    log "$svc not active; attempting start"
    if systemctl start "$svc"; then
      log "$svc started"
    else
      log "start failed for $svc"
      continue
    fi
  fi

  case "$svc" in
    apache2|httpd)
      if ! hc_http; then
        log "$svc health check failed; try-restart"
        systemctl try-restart "$svc" || log "try-restart failed for $svc"
      fi
      ;;
    mysql|mariadb)
      if ! hc_mysql; then
        log "$svc health check failed; try-restart"
        systemctl try-restart "$svc" || log "try-restart failed for $svc"
      fi
      ;;
  esac
done
EOF
  then
    chmod 0755 "$target"
    changed=1
  fi
  echo "$changed"
}

install_units() {
  local srv_changed=0 tim_changed=0
  srv_changed=0
  if write_if_different "/etc/systemd/system/service-guard.service" <<'EOF'
[Unit]
Description=Service guard for Apache/MySQL
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/service-guard.sh
EOF
  then srv_changed=1; fi

  tim_changed=0
  if write_if_different "/etc/systemd/system/service-guard.timer" <<'EOF'
[Unit]
Description=Run service guard every minute

[Timer]
OnBootSec=90s
OnUnitActiveSec=60s
AccuracySec=5s
Unit=service-guard.service
Persistent=true

[Install]
WantedBy=timers.target
EOF
  then tim_changed=1; fi

  echo "$srv_changed$tim_changed"  # e.g., "10", "01", "11", "00"
}

install_overrides() {
  local any_changed=0

  if [[ -n "${SVC_APACHE}" ]]; then
    mkdir -p "/etc/systemd/system/${SVC_APACHE}.service.d"
    if write_if_different "/etc/systemd/system/${SVC_APACHE}.service.d/override.conf" <<'EOF'
[Service]
Restart=always
RestartSec=5s
StartLimitIntervalSec=2min
StartLimitBurst=10
EOF
    then any_changed=1; APACHE_CHANGED=1
    else APACHE_CHANGED=0
    fi
  else
    APACHE_CHANGED=0
  fi

  if [[ -n "${SVC_MYSQL}" ]]; then
    mkdir -p "/etc/systemd/system/${SVC_MYSQL}.service.d"
    if write_if_different "/etc/systemd/system/${SVC_MYSQL}.service.d/override.conf" <<'EOF'
[Service]
Restart=always
RestartSec=5s
StartLimitIntervalSec=2min
StartLimitBurst=10
EOF
    then any_changed=1; MYSQL_CHANGED=1
    else MYSQL_CHANGED=0
    fi
  else
    MYSQL_CHANGED=0
  fi

  echo "$any_changed"
}

enable_timer_idempotent() {
  systemctl daemon-reload
  if ! systemctl is-enabled --quiet service-guard.timer; then
    systemctl enable --now service-guard.timer >/dev/null
  else
    # Ensure it's running
    systemctl start service-guard.timer >/dev/null || true
  fi
}

uninstall_all() {
  systemctl disable --now service-guard.timer 2>/dev/null || true
  rm -f /etc/systemd/system/service-guard.timer
  rm -f /etc/systemd/system/service-guard.service
  rm -f /usr/local/sbin/service-guard.sh

  detect_services
  [[ -n "${SVC_APACHE:-}" ]] && rm -rf "/etc/systemd/system/${SVC_APACHE}.service.d" || true
  [[ -n "${SVC_MYSQL:-}"  ]] && rm -rf "/etc/systemd/system/${SVC_MYSQL}.service.d"  || true

  systemctl daemon-reload
  echo "Service Guard uninstalled."
  exit 0
}

# ---------- main ----------
main() {
  if [[ "${1:-}" == "--uninstall" ]]; then
    need_root "$@"
    uninstall_all
  fi

  need_root "$@"
  detect_services

  # 1) Guard script
  GUARD_CHANGED="$(install_guard_script)"

  # 2) Units
  UNITS_CHANGED="$(install_units)"  # "00","10","01","11"
  if [[ "$UNITS_CHANGED" != "00" || "$GUARD_CHANGED" == "1" ]]; then
    systemctl daemon-reload
  fi
  enable_timer_idempotent

  # 3) Overrides
  OV_CHANGED="$(install_overrides)"
  if [[ "$OV_CHANGED" == "1" ]]; then
    systemctl daemon-reload
  fi

  # 4) Apply override changes **only if changed** and service is already running
  if [[ -n "${SVC_APACHE}" ]]; then restart_if_running_and_changed "${SVC_APACHE}" "${APACHE_CHANGED:-0}"; fi
  if [[ -n "${SVC_MYSQL}"  ]]; then restart_if_running_and_changed "${SVC_MYSQL}"  "${MYSQL_CHANGED:-0}";  fi

  echo "✅ Installed/updated Service Guard idempotently."
  echo "Detected services: Apache='${SVC_APACHE:-none}', MySQL='${SVC_MYSQL:-none}'."
  echo
  echo "Timer status: systemctl status service-guard.timer"
  echo "Logs (guard runs): journalctl -u service-guard.service -n 100 --no-pager"
}

main "$@"
