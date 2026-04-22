#!/bin/bash
#
# install-runner.sh
#
# Idempotent installer for the InteLIS remote command runner and its
# systemd timer. Run as root. Called from scripts/upgrade.sh during
# intelis-update so every lab that runs a normal upgrade gets the runner
# installed or refreshed — no separate bootstrap.
#
# Usage:
#   sudo bash install-runner.sh [--source-dir <path>]
#
# If --source-dir is omitted, the script assumes it is being executed
# from inside an InteLIS checkout and locates its siblings relatively.

set -euo pipefail

if [ "$EUID" -ne 0 ]; then
    echo "install-runner.sh must be run as root" >&2
    exit 1
fi

source_dir=""
while [ $# -gt 0 ]; do
    case "$1" in
        --source-dir) source_dir="$2"; shift 2 ;;
        --source-dir=*) source_dir="${1#--source-dir=}"; shift ;;
        *) echo "Unknown argument: $1" >&2; exit 2 ;;
    esac
done

if [ -z "$source_dir" ]; then
    source_dir="$(cd "$(dirname "$0")" && pwd)"
fi

RUNNER_SRC="$source_dir/intelis-runner.sh"
SYSTEMD_SRC="$source_dir/systemd"
RUNNER_DST="/usr/local/bin/intelis-runner"
SERVICE_DST="/etc/systemd/system/intelis-runner.service"
TIMER_DST="/etc/systemd/system/intelis-runner.timer"

if [ ! -f "$RUNNER_SRC" ]; then
    echo "Cannot find runner source at $RUNNER_SRC" >&2
    exit 1
fi
if [ ! -f "$SYSTEMD_SRC/intelis-runner.service" ] || [ ! -f "$SYSTEMD_SRC/intelis-runner.timer" ]; then
    echo "Cannot find systemd units under $SYSTEMD_SRC" >&2
    exit 1
fi

echo "Installing jq (required for runner JSON parsing) if missing..."
if ! command -v jq >/dev/null 2>&1; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y jq >/dev/null 2>&1 || {
        echo "WARNING: apt-get install jq failed; runner will be a no-op until jq is available" >&2
    }
fi

echo "Installing runner binary at $RUNNER_DST"
install -o root -g root -m 0755 "$RUNNER_SRC" "$RUNNER_DST"

echo "Installing systemd unit files"
install -o root -g root -m 0644 "$SYSTEMD_SRC/intelis-runner.service" "$SERVICE_DST"
install -o root -g root -m 0644 "$SYSTEMD_SRC/intelis-runner.timer"   "$TIMER_DST"

mkdir -p /var/log/intelis-runner
chown root:root /var/log/intelis-runner
chmod 0755 /var/log/intelis-runner

# Log rotation so the daily runner logs don't fill the disk.
cat > /etc/logrotate.d/intelis-runner <<'EOF'
/var/log/intelis-runner/runner-*.log {
    weekly
    missingok
    rotate 8
    compress
    delaycompress
    notifempty
    copytruncate
}
EOF

echo "Reloading systemd and enabling timer"
systemctl daemon-reload
systemctl enable --now intelis-runner.timer

echo "intelis-runner installed and active."
echo "Status:"
systemctl --no-pager --lines=0 status intelis-runner.timer || true
