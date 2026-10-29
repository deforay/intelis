#!/usr/bin/env bash
# Break MySQL the way it breaks in the field: an option line above the first
# [section] heading of my.cnf. MySQL then refuses to read the file and will
# not start.
set -euo pipefail
cnf="$(readlink -f /etc/mysql/my.cnf)"
printf 'innodb_large_prefix = ON\n' | cat - "$cnf" > /tmp/my.cnf.broken
cat /tmp/my.cnf.broken > "$cnf"
systemctl restart mysql 2>/dev/null && { echo "prepare: mysql still starts" >&2; exit 1; }
echo "prepare: mysql is down"
