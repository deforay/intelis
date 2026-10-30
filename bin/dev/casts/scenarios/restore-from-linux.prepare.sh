#!/usr/bin/env bash
# Set up off-machine backups and take the first one, by running the
# backup-setup-linux scenario without keeping its recording. The restore then
# has a backup machine with a backup of this lab on it.
set -euo pipefail
here="$(dirname "$0")"
runuser -u labuser -- env HOME=/home/labuser BACKUP_SERVER="$BACKUP_SERVER" \
    expect "$here/backup-setup-linux.exp" /tmp/setup.cast >/tmp/setup.log 2>&1 ||
    { tail -n 30 /tmp/setup.log >&2; exit 1; }
rm -f /tmp/setup.cast
echo "prepare: backups set up on $BACKUP_SERVER"
