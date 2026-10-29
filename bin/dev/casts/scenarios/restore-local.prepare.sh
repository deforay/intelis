#!/usr/bin/env bash
# Take a local backup, so the restore has a vlsm- file to choose. No
# off-machine backups are set up, so this only backs up on this machine.
set -euo pipefail
intelis backup </dev/null >/tmp/backup.log 2>&1 || { tail -n 30 /tmp/backup.log >&2; exit 1; }
ls /var/www/intelis/backups/db | grep -q '^vlsm-' || { echo "prepare: no vlsm- backup made" >&2; exit 1; }
echo "prepare: local backup taken"
