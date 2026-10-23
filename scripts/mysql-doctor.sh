#!/bin/bash
set -Eeuo pipefail

# Works out why the database is down, or why InteLIS cannot reach it, says so in
# plain language, and offers to repair what is safe to repair.
#
# To use this script:
#   sudo intelis fix-database
#
# That command does not exist on every machine, and a machine with a broken
# database is exactly the machine that cannot be updated to get it. So this runs
# on its own, straight from the internet, on a machine of any age:
#
#   sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/mysql-doctor.sh)"
#
# or, where curl is installed instead of wget:
#
#   sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/deforay/intelis/master/scripts/mysql-doctor.sh)"
#
# Note that is `bash -c "$(…)"`, not `… | bash`. Piping would hand this script
# its own text as the answers to the questions it asks below.
#
# Run in a terminal, it explains what it found and asks before changing
# anything. Run anywhere else (a cron line, a remote command) it only reports.
#
# Options:
#   --check     Only look and report. Never change anything, even when asked to
#   --yes       Apply every safe repair without asking. For unattended use
#   --quiet     Print the verdict and the report path, nothing else
#   --help      Show usage
#
# It always writes a full report to /var/log/intelis-mysql-doctor-<timestamp>.log.
# Send that file when asking for help: it holds the service log, the database
# error log, memory, disk and configuration, which is what the answer depends on.

# ------------------------------------------------------------------------------
# This runs on a machine that is already in trouble, so every probe below is
# individually guarded and none of them may abort the run. The ERR trap is for
# genuine bugs in this script, not for a grep that found nothing.
# ------------------------------------------------------------------------------

trap 'echo "fix-database stopped unexpectedly at line $LINENO (status $?). Please report this."' ERR

MODE=ask          # ask | check | yes
QUIET=false

# Spelled out here rather than read back out of "$0", because the whole point of
# the invocation above is that there is no file on disk to read.
usage() {
  cat <<'USAGE'
Finds out why the database is down and offers to repair it.

  sudo intelis fix-database

Where that command does not exist:

  sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/mysql-doctor.sh)"

Options:
  --check   Only look and report. Change nothing
  --yes     Apply every safe repair without asking
  --quiet   Print the verdict and the report path, nothing else
  --help    Show this

A full report is always written to /var/log/intelis-mysql-doctor-<timestamp>.log.
USAGE
}

while [ $# -gt 0 ]; do
  case "$1" in
    --check|--report-only|--dry-run) MODE=check ;;
    --yes|-y)                        MODE=yes ;;
    --quiet|-q)                      QUIET=true ;;
    --help|-h)                       usage; exit 0 ;;
    "") : ;;
    *) echo "Unknown option: $1"; echo "Try --help"; exit 2 ;;
  esac
  shift
done

# Nothing here can be answered by somebody who is not there. A run with no
# terminal reports and stops rather than making changes nobody chose.
if [ "$MODE" = ask ] && [ ! -t 0 ]; then
  MODE=check
fi

if [ "$EUID" -ne 0 ]; then
  echo "This needs administrator rights. Run: sudo intelis fix-database"
  exit 1
fi

# --- output -------------------------------------------------------------------
#
# The same layout as intelis doctor, which runs this script inside its own run:
# a verdict card, a checklist, then the repairs. The card is drawn by hand
# rather than with gum, so it looks the same on a machine without gum.

if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
  C_OK=$'\033[1;92m'; C_BAD=$'\033[1;91m'; C_WARN=$'\033[1;93m'; C_INFO=$'\033[1;96m'
  C_BOLD=$'\033[1m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
else
  C_OK=""; C_BAD=""; C_WARN=""; C_INFO=""; C_BOLD=""; C_DIM=""; C_OFF=""
fi

print() {
  local type=${1:-info}; shift || true
  local message=${1:-}
  case "$type" in
    error)   printf '  %s✘%s  %s\n' "$C_BAD" "$C_OFF" "$message" ;;
    success) printf '  %s✔%s  %s\n' "$C_OK" "$C_OFF" "$message" ;;
    warning) printf '  %s!%s  %s\n' "$C_WARN" "$C_OFF" "$message" ;;
    info)    printf '  %s›%s  %s\n' "$C_INFO" "$C_OFF" "$message" ;;
    step)    printf '  %s→%s  %s\n' "$C_INFO" "$C_OFF" "$message" ;;
    header)  printf '\n%s%s%s\n' "$C_BOLD" "$message" "$C_OFF" ;;
    *)       printf '%s\n' "$message" ;;
  esac
}

# Long sentences, wrapped and indented to sit under the text of a line above.
print_wrapped() { # print_wrapped <text>
  printf '%s\n' "$1" | fold -s -w 70 | sed 's/^/     /'
}

CARD_WIDTH=66

# printf pads by bytes and the marks are several bytes each, so padding counts
# characters instead.
pad_to() { # pad_to <width> <text>
  local n
  n="$(printf '%s' "$2" | LC_ALL=C.UTF-8 wc -m 2>/dev/null || printf '%s' "${#2}")"
  printf '%s%*s' "$2" $(( $1 > n ? $1 - n : 0 )) ''
}

card() { # card <ok|bad|warn> <title> [paragraph...]
  local tone="$1" title="$2" color mark rule="" line first=true
  shift 2
  case "$tone" in
    ok)  color="$C_OK";   mark="✔" ;;
    bad) color="$C_BAD";  mark="✘" ;;
    *)   color="$C_WARN"; mark="!" ;;
  esac
  rule="$(printf '─%.0s' $(seq 1 "$CARD_WIDTH"))"

  printf '\n%s╭%s╮%s\n' "$color" "$rule" "$C_OFF"
  while IFS= read -r line; do
    if $first; then line="${mark} ${line}"; first=false; else line="  ${line}"; fi
    printf '%s│%s %s%s%s %s│%s\n' "$color" "$C_OFF" "$color" "$(pad_to $((CARD_WIDTH - 2)) "$line")" "$C_OFF" "$color" "$C_OFF"
  done < <(printf '%s\n' "$title" | fold -s -w $((CARD_WIDTH - 6)))
  for paragraph in "$@"; do
    [ -n "$paragraph" ] || continue
    printf '%s│%s %s %s│%s\n' "$color" "$C_OFF" "$(pad_to $((CARD_WIDTH - 2)) "")" "$color" "$C_OFF"
    while IFS= read -r line; do
      printf '%s│%s %s %s│%s\n' "$color" "$C_OFF" "$(pad_to $((CARD_WIDTH - 2)) "  ${line}")" "$color" "$C_OFF"
    done < <(printf '%s\n' "$paragraph" | fold -s -w $((CARD_WIDTH - 6)))
  done
  printf '%s╰%s╯%s\n' "$color" "$rule" "$C_OFF"
}

row() { # row <ok|bad|warn|skip> <label> <detail>
  local mark color
  case "$1" in
    ok)   mark="✔"; color="$C_OK" ;;
    bad)  mark="✘"; color="$C_BAD" ;;
    warn) mark="!"; color="$C_WARN" ;;
    *)    mark="·"; color="$C_DIM" ;;
  esac
  printf '  %-14s %s%s%s  %s\n' "$2" "$color" "$mark" "$C_OFF" "$3"
}

# The verdict card names the most serious finding, and the rest are listed
# under the checklist. With nothing found, there is no problems section at all.
headline_index() {
  local i
  for i in "${!FINDING_SEVERITIES[@]}"; do
    [ "${FINDING_SEVERITIES[$i]}" = critical ] && { echo "$i"; return; }
  done
  echo 0
}

render_other_problems() {
  local first="$1" i shown=false
  for i in "${!FINDING_TITLES[@]}"; do
    [ "$i" = "$first" ] && continue
    $shown || { print header "Also wrong"; shown=true; }
    case "${FINDING_SEVERITIES[$i]}" in
      critical) print error   "${FINDING_TITLES[$i]}" ;;
      *)        print warning "${FINDING_TITLES[$i]}" ;;
    esac
    print_wrapped "${FINDING_DETAILS[$i]}"
  done
}

report_findings() {
  local i
  for i in "${!FINDING_TITLES[@]}"; do
    report "FINDING [${FINDING_SEVERITIES[$i]}/${FINDING_KEYS[$i]}] ${FINDING_TITLES[$i]} :: ${FINDING_DETAILS[$i]}"
  done
}

say() { $QUIET || print "$@"; }

REPORT="/var/log/intelis-mysql-doctor-$(date +%Y%m%d%H%M%S).log"
( umask 077 && : >"$REPORT" ) 2>/dev/null ||
  { REPORT="/tmp/intelis-mysql-doctor-$(date +%Y%m%d%H%M%S).log"; ( umask 077 && : >"$REPORT" ) 2>/dev/null || true; }
chmod 600 "$REPORT" 2>/dev/null || true

# One report on the machine, not a pile of them. Each run writes a new file, and
# a lab that tries three times has three copies of its own logs and settings
# sitting in /var/log for good. The report exists to be sent and then to stop
# existing, so every earlier one goes now: a crash later still leaves exactly the
# current run behind and nothing older.
prune_old_reports() {
  local old
  for old in /var/log/intelis-mysql-doctor-*.log /tmp/intelis-mysql-doctor-*.log; do
    [ -f "$old" ] || continue
    [ "$old" = "$REPORT" ] && continue
    rm -f -- "$old" 2>/dev/null || true
  done
}
prune_old_reports

# The whole point of the report is that it gets sent somewhere: attached to an
# email, or photographed, or forwarded over WhatsApp from a lab. So nothing
# secret may reach it. /etc/mysql/debian.cnf alone holds the debian-sys-maint
# password in clear text, and it is picked up by any sweep of that directory.
# Everything written here goes through this first.
redact() {
  sed -E \
    -e 's/^([[:space:]]*(pass|password|passwd|encryption_password)[[:space:]]*=[[:space:]]*).*/\1[removed]/I' \
    -e 's/(--password=)[^[:space:]]*/\1[removed]/Ig' \
    -e 's/(MYSQL_PWD=)[^[:space:]]*/\1[removed]/Ig' \
    -e "s/(['\"]password['\"][[:space:]]*=>[[:space:]]*)['\"][^'\"]*['\"]/\1'[removed]'/Ig" \
    2>/dev/null || cat
}

report() { printf '%s\n' "$*" | redact >>"$REPORT" 2>/dev/null || true; }

report_cmd() {
  local label="$1"; shift
  {
    echo
    echo "--- ${label} ---"
    "$@" 2>&1 || true
  } 2>/dev/null | redact >>"$REPORT" 2>/dev/null || true
}

# --- findings -----------------------------------------------------------------
#
# Each finding carries the key of the repair that answers it, so the repair step
# below is a lookup rather than a match on the wording of a sentence.

FINDING_KEYS=()
FINDING_SEVERITIES=()
FINDING_TITLES=()
FINDING_DETAILS=()
FIXES_APPLIED=()
FIXES_OFFERED=0

finding() { # finding <severity> <fix-key> <title> <detail>
  FINDING_SEVERITIES+=("$1")
  FINDING_KEYS+=("$2")
  FINDING_TITLES+=("$3")
  FINDING_DETAILS+=("$4")
}

has_finding() { # has_finding <fix-key>
  local key
  for key in ${FINDING_KEYS[@]+"${FINDING_KEYS[@]}"}; do
    [ "$key" = "$1" ] && return 0
  done
  return 1
}

confirm() {
  local answer
  read -r -p "     $1 [y/N]: " answer || return 1
  case "${answer,,}" in y|yes) return 0 ;; *) return 1 ;; esac
}

run_fix() { # run_fix <description> <function> [args...]
  local description="$1"; shift
  FIXES_OFFERED=$((FIXES_OFFERED + 1))

  if [ "$MODE" = check ]; then
    print step "Can be repaired: ${description}"
    return 1
  fi

  print step "${description}"
  if [ "$MODE" = ask ] && ! confirm "Do this now?"; then
    print plain "     Left alone."
    return 1
  fi

  if "$@"; then
    FIXES_APPLIED+=("$description")
    print success "Done."
    report "FIX APPLIED: ${description}"
    return 0
  fi

  print error "That did not work. The details are in ${REPORT}."
  report "FIX FAILED: ${description}"
  return 1
}

# --- facts --------------------------------------------------------------------

# MariaDB hosts do not always answer to "mysql", and a machine that once ran
# MySQL and now runs MariaDB can carry unit files for both.
mysql_unit_name() {
  local unit
  for unit in mysql mariadb mysqld; do
    # MariaDB installs mysql.service as an alias of mariadb.service. The journal
    # files entries under the real name only, so an alias reads as an empty log.
    if systemctl list-unit-files "${unit}.service" 2>/dev/null |
       awk -v u="${unit}.service" '$1 == u && $2 != "alias" { found = 1 } END { exit !found }'; then
      echo "$unit"; return 0
    fi
  done
  echo "mysql"
}

# `mysqladmin ping` reports the server alive even when the credentials are
# wrong, which is exactly the distinction this script has to draw.
#
# Bounded, because a server that is frozen rather than stopped accepts the
# connection and never answers, and an unbounded ping waits with it forever.
# A valid [client] option the tool does not know (max_allowed_packet is the
# usual one) makes mysqladmin exit before it connects, which would read as a
# dead server. So that refusal is retried without the option files.
db_answers() {
  local out rc=0
  out="$(timeout -k 3 10 mysqladmin --connect-timeout=5 ping 2>&1)" || rc=$?
  case "$out" in
    *"unknown variable"*|*"unknown option"*)
      timeout -k 3 10 mysqladmin --no-defaults --connect-timeout=5 ping >/dev/null 2>&1
      return
      ;;
  esac
  return "$rc"
}
mysql_is_up() { db_answers; }

MYSQL_CONFS="/etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/my.cnf /etc/my.cnf /etc/mysql/mariadb.conf.d/50-server.cnf"

# The reason for a refusal to start is written here. The journal almost always
# only says the control process exited with an error code.
mysql_error_log_path() {
  local candidate cnf
  for cnf in $MYSQL_CONFS; do
    [ -f "$cnf" ] || continue
    candidate="$(awk -F= '/^[[:space:]]*log[-_]error[[:space:]]*=/ {gsub(/[[:space:]]/,"",$2); print $2; exit}' "$cnf" 2>/dev/null || true)"
    if [ -n "$candidate" ] && [ -f "$candidate" ]; then echo "$candidate"; return 0; fi
  done
  for candidate in /var/log/mysql/error.log /var/log/mysqld.log \
                   /var/log/mariadb/mariadb.log /var/log/mysql/mysql.err; do
    [ -f "$candidate" ] && { echo "$candidate"; return 0; }
  done
  return 1
}

mysql_datadir() {
  local dir cnf
  for cnf in $MYSQL_CONFS; do
    [ -f "$cnf" ] || continue
    dir="$(awk -F= '/^[[:space:]]*datadir[[:space:]]*=/ {gsub(/[[:space:]]/,"",$2); print $2; exit}' "$cnf" 2>/dev/null || true)"
    [ -n "$dir" ] && [ -d "$dir" ] && { echo "$dir"; return 0; }
  done
  echo "/var/lib/mysql"
}

# Needed to test the credentials the app itself uses, which is a different
# question from whether the server is running.
find_install_path() {
  local p
  for p in /var/www/intelis /var/www/vlsm /var/www/html/intelis /var/www/html/vlsm; do
    [ -f "$p/configs/config.production.php" ] && [ -d "$p/public" ] && { echo "$p"; return 0; }
  done
  return 1
}

UNIT="$(mysql_unit_name)"
ERRLOG="$(mysql_error_log_path || true)"
DATADIR="$(mysql_datadir)"
INSTALL_PATH="$(find_install_path || true)"

FLAVOUR="MySQL"
if [ -d /etc/mysql/mariadb.conf.d ] || command -v mariadbd >/dev/null 2>&1; then
  FLAVOUR="MariaDB"
fi

# --- report -------------------------------------------------------------------

report "=== InteLIS fix-database: $(date +'%F %T') ==="
report "unit=${UNIT} flavour=${FLAVOUR} datadir=${DATADIR} errorlog=${ERRLOG:-none} install=${INSTALL_PATH:-none} mode=${MODE}"
report_cmd "host" uname -a
report_cmd "os" lsb_release -a
report_cmd "unit status" systemctl status "$UNIT" --no-pager -l
report_cmd "journal (120 lines)" journalctl -u "$UNIT" -n 120 --no-pager
report_cmd "memory" free -m
report_cmd "disk" df -h "$DATADIR" / /var/log
report_cmd "inodes" df -i "$DATADIR" / /var/log
report_cmd "datadir" ls -ld "$DATADIR"
# debian.cnf is credentials and nothing else, so it is excluded outright rather
# than relied on being redacted. The tuning worth reading is in the .cnf files.
report_cmd "config" grep -rhvE --include='*.cnf' --exclude='debian.cnf' '^[[:space:]]*(#|$)' /etc/mysql
report_cmd "listening sockets" ss -lntp
[ -n "$ERRLOG" ] && report_cmd "error log (120 lines)" tail -n 120 "$ERRLOG"

# Percona Toolkit is not a dependency and this script never installs it, but a
# machine that happens to have it can contribute its summaries to the report.
command -v pt-summary >/dev/null 2>&1 && report_cmd "pt-summary" pt-summary
if command -v pt-mysql-summary >/dev/null 2>&1 && mysql_is_up; then
  report_cmd "pt-mysql-summary" pt-mysql-summary
fi

# Inside intelis doctor this is one section of a longer run, so it takes a
# heading there and a title line only when run on its own.
EMBEDDED="${INTELIS_DOCTOR_EMBEDDED:-}"
if [ -n "$EMBEDDED" ]; then
  $QUIET || print header "Database"
else
  $QUIET || printf '\n%sInteLIS database doctor%s  ·  %s  ·  %s\n' "$C_BOLD" "$C_OFF" "$(hostname 2>/dev/null || echo this machine)" "$(date '+%d %b %Y %H:%M')"
fi

# --- is it installed at all ---------------------------------------------------

if ! command -v mysqld >/dev/null 2>&1 && ! command -v mariadbd >/dev/null 2>&1; then
  print error "There is no database server installed on this machine at all."
  print info  "If this machine is meant to run InteLIS, it was never set up, or the"
  print plain "     package was removed. Run setup.sh again rather than installing a"
  print plain "     database by hand."
  report "VERDICT: server not installed"
  exit 1
fi

SERVER_UP=false
mysql_is_up && SERVER_UP=true
# Running as far as systemd knows, and not answering: frozen rather than stopped.
SERVER_HUNG=false
if ! $SERVER_UP && systemctl is-active --quiet "$UNIT" 2>/dev/null; then SERVER_HUNG=true; fi



# --- checks -------------------------------------------------------------------

check_disk() {
  local target avail_kb inode_pct human seen=""
  for target in "$DATADIR" / /var/log; do
    [ -d "$target" ] || continue
    case " $seen " in *" $(stat -c %d "$target" 2>/dev/null || echo x) "*) continue ;; esac
    seen="$seen $(stat -c %d "$target" 2>/dev/null || echo x)"

    avail_kb="$(df -Pk "$target" 2>/dev/null | awk 'NR==2 {print $4}' || true)"
    if [ -n "$avail_kb" ] && [ "$avail_kb" -lt 524288 ] 2>/dev/null; then
      human="$(df -h "$target" 2>/dev/null | awk 'NR==2 {print $4" free out of "$2}' || true)"
      finding critical disk \
        "The disk is full (${human})." \
        "This is the most common reason a lab database stops. The server shuts itself down when it cannot write, and will not start again until there is room."
    fi

    inode_pct="$(df -Pi "$target" 2>/dev/null | awk 'NR==2 {gsub(/%/,"",$5); print $5}' || true)"
    if [ -n "$inode_pct" ] && [ "$inode_pct" -ge 95 ] 2>/dev/null; then
      finding critical disk \
        "The disk holding ${target} has room but cannot hold any more files (${inode_pct}% of its file slots used)." \
        "Usually a very large number of small files, such as old sessions or cache files. The database cannot create anything until some are removed."
    fi
  done
}

OOM_HITS=0
check_oom() {
  local hits
  hits="$(journalctl -k --since "-7 days" --no-pager 2>/dev/null |
    grep -iE 'oom.?kill|out of memory' | grep -ci -e mysql -e mariadb || true)"
  [ -n "$hits" ] && [ "$hits" -gt 0 ] 2>/dev/null || return 0
  OOM_HITS="$hits"
  finding critical none \
    "The machine ran out of memory and the system shut the database down to cope (${hits} time(s) in the last week)." \
    "There is not enough RAM for everything running here. It will keep happening until the database is told to reserve less, or something else on this machine is stopped."
}

check_buffer_pool() {
  local setting value bytes mem_kb mem_bytes
  # --include='*.cnf' matters: the repair below leaves mysqld.cnf.bak.<stamp>
  # files in this same directory, and a recursive grep that reads those would
  # keep reporting a setting that is no longer in effect anywhere.
  setting="$(grep -rhiE --include='*.cnf' '^[[:space:]]*innodb_buffer_pool_size' /etc/mysql 2>/dev/null | tail -n 1 || true)"
  [ -n "$setting" ] || return 0

  value="$(printf '%s' "$setting" | sed -E 's/.*=[[:space:]]*//; s/[[:space:]]*$//' || true)"
  case "$value" in
    *[Gg]) bytes=$(( ${value%[Gg]} * 1024 * 1024 * 1024 )) ;;
    *[Mm]) bytes=$(( ${value%[Mm]} * 1024 * 1024 )) ;;
    *[Kk]) bytes=$(( ${value%[Kk]} * 1024 )) ;;
    *[0-9]) bytes="$value" ;;
    *) return 0 ;;
  esac
  [ "$bytes" -gt 0 ] 2>/dev/null || return 0

  mem_kb="$(awk '/MemTotal/ {print $2}' /proc/meminfo 2>/dev/null || true)"
  [ -n "$mem_kb" ] || return 0
  mem_bytes=$(( mem_kb * 1024 ))

  [ "$bytes" -gt $(( mem_bytes * 70 / 100 )) ] || return 0
  finding critical bufferpool \
    "The database is set to reserve $(( bytes / 1024 / 1024 ))MB of memory on a machine that only has $(( mem_bytes / 1024 / 1024 ))MB." \
    "The setting is innodb_buffer_pool_size. At this size the server either refuses to start or is killed shortly after it does."
}

# The error log is never truncated, so a fault that was fixed last month is
# still in it and reads exactly like a fault happening now. Everything below
# looks only at the most recent start attempt, which is the one that matters.
error_log_recent() {
  [ -n "$ERRLOG" ] || return 0
  awk '
    /starting as process|ready for connections|Shutdown complete/ && /starting as process/ { buf = ""; }
    { buf = buf $0 "\n" }
    END { printf "%s", buf }
  ' "$ERRLOG" 2>/dev/null | tail -n 400 || true
}

# A config the server rejects outright is refused by the service's pre-start
# step, before mysqld is running and therefore before anything can be written to
# the error log. That log is empty in exactly the case an operator most needs it,
# so the journal has to be read as well. Both sources, most recent first.
#
# Only from the most recent start onwards, like the error log above: a setting
# that was refused and since corrected is still in the journal, and quoting it
# would name a fault that is already gone.
journal_recent() {
  journalctl -u "$UNIT" -n 400 --no-pager 2>/dev/null |
    awk '/systemd\[1\]: Starting / { buf = "" } { buf = buf $0 "\n" } END { printf "%s", buf }' || true
}

mysql_complaints() {
  { journal_recent; error_log_recent; } 2>/dev/null || true
}

# The journal's date, host and process prefix, so what is left is the
# database's own sentence.
strip_log_prefix() { sed -E 's/^[A-Z][a-z]{2} [ 0-9]{2} [0-9:]{8} [^ ]+ [^ ]+: //'; }

CONFIG_PRELUDE_FILE=""

# True when the file has an option line before its first [section] heading.
# !includedir and friends are directives rather than options and are legal
# there, as are comments and blank lines.
# The answer is carried in a variable rather than in `exit 0` / `exit 1` from the
# rules. An exit inside a main rule still runs END, so an END that exits with its
# own status overrides the one just given and the test always answers the same
# way regardless of the file.
config_has_prelude_option() { # config_has_prelude_option <file>
  awk '
    /^[[:space:]]*\[/ { exit }
    /^[[:space:]]*$/  { next }
    /^[[:space:]]*#/  { next }
    /^[[:space:]]*;/  { next }
    /^[[:space:]]*!/  { next }
                      { found = 1; exit }
    END               { exit (found ? 0 : 1) }
  ' "$1" 2>/dev/null
}

# Every option in a MySQL config has to sit under a [section] heading. One above
# the first heading makes the whole file invalid: the server stops at that line
# without reading further and without starting. It is what happens when settings
# are pasted at the top of a file.
#
# Deliberately checked by reading the files rather than by believing the journal.
# A message in the journal is evidence of a fault at the time it was written, not
# of one now, so trusting it would keep reporting a fault after it was repaired
# and would offer the repair again on every run. The file either has the problem
# or it does not, and that answer is true whether or not the server is running.
check_config_prelude() {
  local cnf found=""
  for cnf in $MYSQL_CONFS /etc/mysql/conf.d/*.cnf /etc/mysql/mysql.conf.d/*.cnf; do
    [ -f "$cnf" ] || continue
    config_has_prelude_option "$cnf" || continue
    found="$cnf"
    break
  done
  [ -n "$found" ] || return 0
  CONFIG_PRELUDE_FILE="$found"

  # While the server is up this is not why anything is broken today, but it is
  # why it will not come back after the next restart, which is worth saying.
  if $SERVER_UP; then
    finding warning configprelude \
      "Settings were added to ${found} above the first [section] heading." \
      "The database is running now, but it will refuse to start the next time it is restarted, because it cannot read a file whose first line is an option with no heading above it."
    return 0
  fi

  finding critical configprelude \
    "Settings were added to ${found} above the first [section] heading, and the database will not read that file at all." \
    "Every setting has to sit under a heading such as [mysqld]. The server stops at the first line and never starts."
}

# Every check above this one asks the question from the server's side. This one
# asks it from the client's, because a file can be perfectly acceptable to
# mysqld and still stop every client dead.
#
# The cause is a server setting that has ended up under a heading the clients
# read - [client], or [mysql], or the section a tool appended after them. mysqld
# never looks at those sections, so it starts happily and writes nothing to its
# log, while mysql and mysqldump read them, do not recognise a server variable,
# and refuse to run at all: "unknown variable 'innodb_file_per_table=1'". Two
# faults, one cause. The tuning was never in effect, and since mysqldump is one
# of the clients that stops working, the nightly backup has been failing for as
# long as the line has been there.
#
# It reaches a file two ways. Someone adds a [client] section at the foot of a
# file that ends in server settings, and everything below it changes meaning; or
# a script appends tuning to the end of a file whose last heading is already a
# client one.

CLIENT_LEAK_HITS=()      # "file<TAB>line<TAB>section<TAB>option"
CLIENT_LEAK_FILES=()
CLIENT_LEAK_BREAKS_CLIENTS=false

# Every file a client reads, which is a wider set than the server's: each user's
# own ~/.my.cnf counts, and a fault in one breaks that person's mysql while
# leaving everyone else's working.
client_config_files() {
  local f
  for f in /etc/my.cnf /etc/mysql/my.cnf \
           /etc/mysql/conf.d/*.cnf /etc/mysql/mysql.conf.d/*.cnf \
           /etc/mysql/mariadb.conf.d/*.cnf \
           /root/.my.cnf /home/*/.my.cnf; do
    [ -f "$f" ] && printf '%s\n' "$f"
  done
  # Never the status of the last [ -f ]: with errexit on, a final candidate that
  # does not exist would take the whole run down.
  return 0
}

# Deliberately narrow: the names this project's own scripts write, and their
# immediate neighbours. A guess in the other direction - flagging anything not
# on a list of known client options - would eventually condemn a legitimate
# setting on somebody's server, and this check exists to have a repair offered
# against it. Options valid for both, such as max_allowed_packet, port and
# socket, are not here and must not be.
SERVER_ONLY_OPTION_RE='^(innodb_|performance_schema|query_cache_|slow_query_log|long_query_time|log_queries_not_using_indexes|max_connections|max_connect_errors|thread_cache_size|table_open_cache|table_definition_cache|tmp_table_size|max_heap_table_size|join_buffer_size|sort_buffer_size|read_buffer_size|read_rnd_buffer_size|key_buffer_size|sql_mode|character_set_server|collation_server|default_authentication_plugin|datadir|bind_address|skip_name_resolve|log_error|log_bin|expire_logs_days|binlog_expire_logs_seconds|wait_timeout|interactive_timeout|open_files_limit|back_log)'

# Print "file<TAB>line<TAB>section<TAB>option" for every active option under a
# client-read heading that matches <regex>.
scan_client_sections() { # scan_client_sections <regex>
  local pattern="$1" file
  while IFS= read -r file; do
    awk -v file="$file" -v pattern="$pattern" '
      function norm(s) { gsub(/-/, "_", s); return tolower(s) }
      /^[[:space:]]*[#;!]/ { next }
      /^[[:space:]]*\[/ {
        s = $0
        sub(/^[[:space:]]*\[/, "", s)
        sub(/\].*$/, "", s)
        gsub(/[[:space:]]/, "", s)
        section = norm(s)
        # The groups a client reads. [client] is read by all of them, the rest
        # by the one program they are named for; any of them is enough to stop
        # that program.
        client_section = (section ~ /^(client|client_server|client_mariadb|mysql|mysqldump|mysqladmin|mysqlimport|mysqlshow|mysqlcheck|mysqlbinlog|mysqlslap|mysql_upgrade)$/)
        next
      }
      !client_section { next }
      {
        k = $0
        sub(/=.*$/, "", k)
        gsub(/[[:space:]]/, "", k)
        if (k == "") next
        if (norm(k) !~ pattern) next
        printf "%s\t%d\t%s\t%s\n", file, NR, section, k
      }
    ' "$file" 2>/dev/null
  done < <(client_config_files)
}

# Ground truth, and worth having even though the scan above usually finds the
# same line: it reports what is actually broken today rather than what ought to
# be, and it names options this script's own list has never heard of. mysqldump
# validates the options it reads from a file even for --version, so this needs
# no credentials and no running server. mysql is asked too because whether it
# also refuses varies by version, and the answer decides how serious this is.
#
# Only these two, and the choice matters. mysqlcheck and mysqladmin reject
# max_allowed_packet in [client] - a legitimate, common setting that mysql and
# mysqldump both accept - so probing with them would report a healthy server as
# broken and then offer to comment the setting out. A name is only taken from
# here when the program that rejected it is one whose failure is itself the
# fault being looked for: the client people type, and the one that takes the
# backup.
probe_client_rejection() {
  local client out name
  for client in mysqldump mysql; do
    command -v "$client" >/dev/null 2>&1 || continue
    out="$("$client" --version 2>&1 >/dev/null)" || true
    case "$out" in
      *"unknown variable"*|*"unknown option"*)
        # Kept inside a substitution rather than left as a bare pipeline: with
        # pipefail on, head closing the pipe early would count as a failure of
        # the whole function.
        name="$(printf '%s\n' "$out" | sed -nE "s/.*unknown (variable|option) '([^'=]+).*/\2/p" | head -n 1 || true)"
        [ -n "$name" ] || continue
        printf '%s\n' "$name"
        return 0
        ;;
    esac
  done
  return 1
}

check_client_option_leak() {
  local rejected hit file line

  # What the clients themselves say, first, so that a name this script's own
  # list does not know about is still found and still repaired.
  rejected="$(probe_client_rejection || true)"
  if [ -n "$rejected" ]; then
    CLIENT_LEAK_BREAKS_CLIENTS=true
  fi

  while IFS= read -r hit; do
    [ -n "$hit" ] || continue
    CLIENT_LEAK_HITS+=("$hit")
  done < <(
    {
      scan_client_sections "$SERVER_ONLY_OPTION_RE"
      # An exact search for the rejected name catches whatever the list above
      # does not cover. Duplicates are removed rather than avoided, because the
      # two searches overlap in the ordinary case.
      if [ -n "$rejected" ]; then
        scan_client_sections "^$(printf '%s' "$rejected" | tr 'A-Z-' 'a-z_')$"
      fi
    } | sort -t"$(printf '\t')" -k1,1 -k2,2n -u
  )

  if [ ${#CLIENT_LEAK_HITS[@]} -eq 0 ]; then
    # A client is refusing an option that is in none of the files searched here.
    # There is nothing to offer a repair against, but saying so is still better
    # than a clean bill of health for a machine where mysqldump will not run.
    if [ -n "$rejected" ]; then
      finding critical none \
        "The client programs are refusing an option in a configuration file, and mysqldump is one of them, so the backup cannot run." \
        "The option they will not accept is ${rejected}. It is not in any of the usual files, so it has to be found by hand: run  my_print_defaults client mysql mysqldump  to see what they are reading, and  mysql --no-defaults  to get a working connection meanwhile."
    fi
    return 0
  fi

  for hit in "${CLIENT_LEAK_HITS[@]}"; do
    file="${hit%%$'\t'*}"
    case " ${CLIENT_LEAK_FILES[*]-} " in
      *" ${file} "*) ;;
      *) CLIENT_LEAK_FILES+=("$file") ;;
    esac
  done

  line="$(printf '%s\n' "${CLIENT_LEAK_HITS[@]}" | awk -F'\t' '{printf "%s line %s (under [%s]): %s\n", $1, $2, $3, $4}')"
  report "Settings for the server found under client headings:"
  report "$line"

  if $CLIENT_LEAK_BREAKS_CLIENTS; then
    finding critical clientleak \
      "Settings meant for the database server were put under a heading that the client programs read, and those programs will no longer run." \
      "Typing mysql fails with \"unknown variable\". So does mysqldump, which is what takes the backup, so the backups have been failing too. The server itself never reads those sections, so it started normally and none of that tuning has ever been in use. Switching the lines off is safe; they are written back where they belong, sized for this machine, the next time the software is updated. $(printf '%s\n' "${CLIENT_LEAK_HITS[@]}" | awk -F'\t' '{ printf "%s%s line %s under [%s]: %s", (NR > 1 ? "; " : ""), $1, $2, $3, $4 }')"
    return 0
  fi

  finding warning clientleak \
    "Settings meant for the database server are sitting under a heading that the client programs read." \
    "This version of the client tolerates them, so nothing is failing today, but the server does not read those sections and never applied any of it. A different client, or the next upgrade, will refuse to start. $(printf '%s\n' "${CLIENT_LEAK_HITS[@]}" | awk -F'\t' '{ printf "%s%s line %s under [%s]: %s", (NR > 1 ? "; " : ""), $1, $2, $3, $4 }')"
}

# No equivalent direct test exists for a setting the server merely rejects: that
# takes running mysqld. So this reads the logs, and only while the server is
# down, when a refusal is by definition the current state of affairs.
check_config_refusal() {
  $SERVER_UP && return 0
  local refusal
  refusal="$(mysql_complaints | grep -iE "unknown variable|unknown option|error while setting value" | tail -n 1 | strip_log_prefix || true)"
  [ -n "$refusal" ] || return 0
  finding critical config \
    "The database is refusing a setting in its configuration file." \
    "Its own words: ${refusal}"
}

newest_config_backup() {
  local cnf="/etc/mysql/mysql.conf.d/mysqld.cnf" newest
  newest="$(ls -1t "${cnf}".bak.* 2>/dev/null | head -1 || true)"
  [ -n "$newest" ] && [ -f "$newest" ] && printf '%s' "$newest"
}

# No error-log guard: mysql_complaints reads the journal too, and a server that
# logs only there is the one most likely to be offered a restart below.
check_corruption() {
  local marker
  marker="$(mysql_complaints | grep -iE "corrupt|page.*checksum|cannot find tablespace|forcing recovery|Assertion failure" | tail -n 1 | strip_log_prefix || true)"
  [ -n "$marker" ] || return 0
  finding critical corruption \
    "The database reports damage in its own files." \
    "Its own words: ${marker}"
}

SOCKET_DIR=""

# An absent /run/mysqld is the normal state of a stopped server, not a fault.
# The unit declares RuntimeDirectory=mysqld, so systemd creates the folder at
# every start and deletes it again at every stop. Reporting it as the cause
# would name a non-cause on every machine whose database is simply stopped,
# which is worse than saying nothing: it sends the operator off to repair
# something that was never broken, and the real fault stays hidden.
#
# So it is only a fault when nothing on the machine is going to create it, or
# when the server has already said it could not.
check_socket_dir() {
  local sockpath sockdir runtime_dir
  sockpath="$(grep -rhiE --include='*.cnf' '^[[:space:]]*socket' /etc/mysql 2>/dev/null | head -1 |
    sed -E 's/.*=[[:space:]]*//; s/[[:space:]]*$//' || true)"
  [ -n "$sockpath" ] || sockpath="/run/mysqld/mysqld.sock"
  sockdir="$(dirname "$sockpath")"
  SOCKET_DIR="$sockdir"

  # The server's own complaint outranks any reasoning about who creates what.
  local complaint
  complaint="$(error_log_recent |
    grep -iE "can't create/write to file.*\.sock|could not create unix socket|failed to create.*socket|Can't start server.*socket" |
    tail -n 1 || true)"
  if [ -n "$complaint" ]; then
    finding critical socketdir \
      "The database cannot create the connection file it needs in ${sockdir}." \
      "Its own words: ${complaint}"
    return 0
  fi

  [ -d "$sockdir" ] && return 0

  # systemd's RuntimeDirectory= covers it, as does a tmpfiles rule.
  runtime_dir="$(systemctl show -p RuntimeDirectory --value "$UNIT" 2>/dev/null || true)"
  [ -n "$runtime_dir" ] && return 0
  grep -rqsE "^[[:space:]]*[dD][[:space:]]+${sockdir}([[:space:]]|$)" \
    /usr/lib/tmpfiles.d /etc/tmpfiles.d /run/tmpfiles.d 2>/dev/null && return 0

  finding critical socketdir \
    "Nothing on this machine creates the folder the database needs for its connection file (${sockdir})." \
    "That folder is emptied at every restart. Normally the database service recreates it, but this machine has no rule that does so, and the server gives up as soon as it starts."
}

check_datadir_ownership() {
  local owner
  owner="$(stat -c '%U:%G' "$DATADIR" 2>/dev/null || true)"
  [ -n "$owner" ] || return 0
  case "$owner" in mysql:mysql|mariadb:mariadb) return 0 ;; esac
  finding critical dataowner \
    "The database's own files belong to ${owner} instead of to the database." \
    "It runs under its own account and cannot open files owned by anyone else. This normally follows a copy or a restore that was done as the administrator."
}

check_unit_masked() {
  [ "$(systemctl is-enabled "$UNIT" 2>/dev/null || true)" = "masked" ] || return 0
  finding critical unmask \
    "The database service has been blocked from starting." \
    "Somebody turned it off deliberately at some point. Nothing will start it until that is undone."
}

check_port_conflict() {
  $SERVER_UP && return 0
  local holder
  holder="$(ss -lntp 2>/dev/null | awk '$4 ~ /:3306$/ {print $NF; exit}' || true)"
  [ -n "$holder" ] || return 0
  # A server that is hung rather than stopped still holds its own port. That is
  # this service, which the restart below deals with, not a second copy.
  local main_pid
  main_pid="$(systemctl show -p MainPID --value "$UNIT" 2>/dev/null || true)"
  if [ -n "$main_pid" ] && [ "$main_pid" != 0 ]; then
    case "$holder" in *"pid=${main_pid},"*) return 0 ;; esac
  fi
  finding warning none \
    "Something is already using the database's port: ${holder}" \
    "A second copy of the server may have been started by hand. Two cannot share the port or the data folder."
}

APP_CONN_OK=""
APP_DECODED_PASSWORD=""
APP_CONFIG=""

check_app_credentials() {
  $SERVER_UP || return 0
  [ -n "$INSTALL_PATH" ] || return 0
  command -v php >/dev/null 2>&1 || return 0

  local cfg="$INSTALL_PATH/configs/config.production.php"
  [ -f "$cfg" ] || return 0
  APP_CONFIG="$cfg"

  local user pass host db
  user="$(php -r "error_reporting(0); \$c=include '$cfg'; echo \$c['database']['username'] ?? '';" 2>/dev/null || true)"
  pass="$(php -r "error_reporting(0); \$c=include '$cfg'; echo \$c['database']['password'] ?? '';" 2>/dev/null || true)"
  host="$(php -r "error_reporting(0); \$c=include '$cfg'; echo \$c['database']['host'] ?? 'localhost';" 2>/dev/null || true)"
  db="$(php -r "error_reporting(0); \$c=include '$cfg'; echo \$c['database']['db'] ?? '';" 2>/dev/null || true)"
  [ -n "$user" ] || return 0

  if MYSQL_PWD="$pass" timeout -k 3 15 mysql --no-defaults --connect-timeout=5 -u "$user" -h "${host:-localhost}" \
      -e "USE \`${db}\`;" >/dev/null 2>&1; then
    APP_CONN_OK=yes
    return 0
  fi
  APP_CONN_OK=no

  # The app's input sanitizer is an HTML purifier, and it used to run over the
  # setup form's database fields too: a password of mko)(*&^ was saved as
  # mko)(*&amp;^. The server is healthy and the password is nearly right, but
  # from a browser it looks identical to a dead database.
  local decoded
  decoded="$(printf '%s' "$pass" | sed -e 's/&amp;/\&/g' -e 's/&lt;/</g' -e 's/&gt;/>/g' -e 's/&quot;/"/g' -e "s/&#0*39;/'/g" || true)"
  if [ "$decoded" != "$pass" ] &&
     MYSQL_PWD="$decoded" timeout -k 3 15 mysql --no-defaults --connect-timeout=5 -u "$user" -h "${host:-localhost}" \
       -e "USE \`${db}\`;" >/dev/null 2>&1; then
    APP_DECODED_PASSWORD="$decoded"
    finding critical password \
      "The database is fine. InteLIS has the password saved wrongly, so it cannot log in." \
      "Characters such as & were saved as &amp; when this instance was first set up. The corrected password has been tested and works."
    return 0
  fi

  finding critical none \
    "The database is running, but InteLIS cannot log in to it as '${user}'." \
    "The server is healthy, so this is about the username, password or permissions rather than an outage. They are set in ${cfg}."
}

check_connection_limit() {
  $SERVER_UP || return 0
  [ -n "$ERRLOG" ] || return 0
  local hits
  hits="$(error_log_recent | grep -ci "Too many connections" || true)"
  [ -n "$hits" ] && [ "$hits" -gt 0 ] 2>/dev/null || return 0
  finding warning none \
    "The database has been turning connections away because too many were open at once (${hits} time(s))." \
    "Staff see errors that come and go while the server itself stays up."
}

check_crashed_tables() {
  [ -n "$ERRLOG" ] || return 0
  local hits
  hits="$(error_log_recent | grep -iE "is marked as crashed|doesn't exist in engine" | tail -n 1 || true)"
  [ -n "$hits" ] || return 0
  finding warning none \
    "At least one table is damaged." \
    "Its own words: ${hits}"
}

run_checks() {
  # Every check below adds to this state, so a second pass starts it empty.
  FINDING_KEYS=(); FINDING_SEVERITIES=(); FINDING_TITLES=(); FINDING_DETAILS=()
  OOM_HITS=0
  CONFIG_PRELUDE_FILE=""
  CLIENT_LEAK_HITS=(); CLIENT_LEAK_FILES=(); CLIENT_LEAK_BREAKS_CLIENTS=false
  SOCKET_DIR=""
  APP_CONN_OK=""; APP_DECODED_PASSWORD=""
  check_disk
  check_oom
  check_buffer_pool
  check_config_prelude
  check_client_option_leak
  check_config_refusal
  check_corruption
  check_socket_dir
  check_datadir_ownership
  check_unit_masked
  check_port_conflict
  check_app_credentials
  check_connection_limit
  check_crashed_tables
}

run_checks

# --- verdict ------------------------------------------------------------------

render_checks() {
  local free
  if $SERVER_UP; then row ok "Server" "${FLAVOUR}, running"
  elif $SERVER_HUNG; then row bad "Server" "${FLAVOUR}, running but not answering"
  else row bad "Server" "${FLAVOUR}, stopped"
  fi

  free="$(df -h "$DATADIR" 2>/dev/null | awk 'NR==2 {print $4}' || true)"
  if has_finding disk; then row bad "Disk" "full, ${free:-?} free"
  else row ok "Disk" "${free:-?} free"
  fi

  if has_finding bufferpool; then row bad "Memory" "the database reserves more than there is"
  elif [ "$OOM_HITS" -gt 0 ] 2>/dev/null; then row bad "Memory" "ran out ${OOM_HITS} time(s) this week"
  else row ok "Memory" "enough"
  fi

  if has_finding config || has_finding configprelude || has_finding clientleak; then row bad "Settings" "a setting is refused"
  else row ok "Settings" "accepted"
  fi

  if has_finding corruption; then row bad "Data files" "damaged"
  elif has_finding dataowner; then row bad "Data files" "owned by the wrong account"
  else row ok "Data files" "${DATADIR}"
  fi

  if has_finding password; then row bad "InteLIS login" "password saved wrongly"
  elif [ "$APP_CONN_OK" = yes ]; then row ok "InteLIS login" "works"
  elif [ "$APP_CONN_OK" = no ]; then row bad "InteLIS login" "refused"
  else row skip "InteLIS login" "not checked"
  fi
}

report_findings

if [ ${#FINDING_TITLES[@]} -eq 0 ]; then
  if $SERVER_UP; then
    if [ "$APP_CONN_OK" = yes ]; then
      card ok "The database is working." "InteLIS can log in to it."
    else
      card ok "The database is working."
    fi
    report "VERDICT: healthy"
  elif $SERVER_HUNG; then
    card bad "The database is running but not answering." \
      "None of the known causes matched. Restarting it is the usual repair, offered below."
    report "VERDICT: hung, no known cause matched"
  else
    card bad "The database has stopped." \
      "None of the known causes matched. Most often it only needs starting again, which is offered below."
    report "VERDICT: down, no known cause matched"
  fi
else
  HEADLINE="$(headline_index)"
  MORE=""
  [ ${#FINDING_TITLES[@]} -gt 1 ] && MORE="Also wrong: $(( ${#FINDING_TITLES[@]} - 1 )) more, listed below."
  if [ "${FINDING_SEVERITIES[$HEADLINE]}" = critical ]; then TONE=bad; else TONE=warn; fi
  card "$TONE" "${FINDING_TITLES[$HEADLINE]}" "${FINDING_DETAILS[$HEADLINE]}" "$MORE"
fi
printf '\n'
render_checks
[ ${#FINDING_TITLES[@]} -gt 1 ] && render_other_problems "$HEADLINE"

# --- repairs ------------------------------------------------------------------

# Creating the folder by hand lasts until the next reboot, because the
# filesystem it sits on is emptied then. A machine that has already lost the
# rule which recreates it would come back up broken, on a morning when nobody
# is expecting it. So write the rule as well, and only then the folder.
fix_socket_dir() {
  local dir="${SOCKET_DIR:-/run/mysqld}" owner=mysql rule=/etc/tmpfiles.d/intelis-mysqld.conf

  id -u mysql >/dev/null 2>&1 || owner=mariadb
  id -u "$owner" >/dev/null 2>&1 || return 1

  cat >"$rule" <<EOF
# Written by intelis fix-database: this machine had no rule recreating the
# directory MySQL needs for its socket, so it came up broken after a restart.
d ${dir} 0755 ${owner} ${owner} -
EOF

  # Apply the rule now rather than creating the directory separately, so what
  # happens at the next boot is the same thing that just happened.
  if command -v systemd-tmpfiles >/dev/null 2>&1; then
    systemd-tmpfiles --create "$rule" >/dev/null 2>&1 || true
  fi

  [ -d "$dir" ] || mkdir -p "$dir" || return 1
  chown "${owner}:${owner}" "$dir" && chmod 755 "$dir"
}

fix_datadir_owner() { chown -R mysql:mysql "$DATADIR"; }

# Comment out every option sitting above the first [section] heading, which is
# the only part of the file that is invalid. Deliberately conservative: it does
# not try to guess which heading those settings were meant to go under. Putting
# them under [mysqld] would be a guess, and on this server most of what gets
# pasted there is an option MySQL 8 removed years ago, so the guess would trade
# this failure for "unknown variable" and the server still would not start.
# !includedir lines are left alone; they are directives, not options, and are
# legal exactly where they are.
fix_config_prelude() {
  local f="$CONFIG_PRELUDE_FILE" tmp
  [ -n "$f" ] && [ -f "$f" ] || return 1

  tmp="$(mktemp)" || return 1

  awk '
    seen              { print; next }
    /^[[:space:]]*\[/ { seen = 1; print; next }
    /^[[:space:]]*$/  { print; next }
    /^[[:space:]]*#/  { print; next }
    /^[[:space:]]*;/  { print; next }
    /^[[:space:]]*!/  { print; next }
    {
      print "# disabled by intelis fix-database: an option here, above the first"
      print "# [section] heading, stops MySQL reading this file at all."
      print "#" $0
    }
  ' "$f" >"$tmp" || { rm -f "$tmp"; return 1; }

  # Nothing to do means nothing done: no rewrite, and above all no backup file.
  # A repair that leaves a copy behind every time it is run turns re-running the
  # script into a way of filling /etc/mysql with near-identical files.
  if cmp -s "$f" "$tmp"; then
    rm -f "$tmp"
    return 0
  fi

  cp "$f" "${f}.bak.$(date +%Y%m%d%H%M%S)" || { rm -f "$tmp"; return 1; }

  # Written back through the original so the file keeps its ownership and mode.
  cat "$tmp" >"$f" || { rm -f "$tmp"; return 1; }
  rm -f "$tmp"
}

# Comment the stranded lines out where they are, rather than move them under
# [mysqld]. Moving them looks like the more helpful repair and is the more
# dangerous one: the server has been running without them all along, so putting
# them into effect here would change how much memory it takes on the strength of
# a number nobody has checked against this machine. Commenting them out changes
# nothing about how the server runs - it was already ignoring them - and gets
# the clients and the backup working again. The settings are written back where
# they belong, sized for this machine, the next time the software is updated.
fix_client_option_leak() {
  local file lines tmp changed=false

  [ ${#CLIENT_LEAK_HITS[@]} -gt 0 ] || return 1

  for file in ${CLIENT_LEAK_FILES[@]+"${CLIENT_LEAK_FILES[@]}"}; do
    lines="$(printf '%s\n' "${CLIENT_LEAK_HITS[@]}" | awk -F'\t' -v f="$file" '$1 == f { print $2 }' | sort -n | paste -sd, -)"
    [ -n "$lines" ] || continue

    tmp="$(mktemp)" || return 1
    awk -v want="$lines" '
      BEGIN { n = split(want, a, ","); for (i = 1; i <= n; i++) target[a[i] + 0] = 1 }
      target[FNR] {
        print "# disabled by intelis fix-database: a setting for the server, under a"
        print "# heading only the client programs read. The server never read it, and a"
        print "# client that does not recognise it refuses to run."
        print "#" $0
        next
      }
      { print }
    ' "$file" >"$tmp" || { rm -f "$tmp"; return 1; }

    # Nothing to do means nothing done, and above all no backup file left behind.
    if cmp -s "$file" "$tmp"; then
      rm -f "$tmp"
      continue
    fi

    cp "$file" "${file}.bak.$(date +%Y%m%d%H%M%S)" || { rm -f "$tmp"; return 1; }
    cat "$tmp" >"$file" || { rm -f "$tmp"; return 1; }
    rm -f "$tmp"
    changed=true
  done

  $changed
}

fix_unmask() { systemctl unmask "$UNIT" && systemctl enable "$UNIT"; }

fix_vacuum_journal() { journalctl --vacuum-size=100M >/dev/null 2>&1; }

# Roll the live config back to the newest backup this project's own scripts
# left behind, keeping the rejected one for inspection rather than deleting it.
fix_rollback_config() {
  local cnf="/etc/mysql/mysql.conf.d/mysqld.cnf" newest
  newest="$(newest_config_backup)" || return 1
  [ -n "$newest" ] || return 1
  cp "$cnf" "${cnf}.failed.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true
  cp "$newest" "$cnf"
}

# Comment the oversized setting out rather than guess a replacement. The
# server's own default suits a small machine, and a wrong guess here is simply
# the next outage.
fix_buffer_pool() {
  local f changed=false
  for f in /etc/mysql/mysql.conf.d/mysqld.cnf /etc/mysql/mariadb.conf.d/50-server.cnf; do
    [ -f "$f" ] || continue
    grep -qiE '^[[:space:]]*innodb_buffer_pool_size' "$f" || continue
    cp "$f" "${f}.bak.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true
    sed -i -E 's/^([[:space:]]*[Ii]nnodb_buffer_pool_size)/# \1  # too large for this machine/' "$f"
    changed=true
  done
  $changed
}

# Rewrite only the password, and only after the corrected one has been proven to
# work against the live server, so this cannot make a working instance worse.
fix_app_password() {
  [ -n "$APP_CONFIG" ] && [ -n "$APP_DECODED_PASSWORD" ] || return 1
  cp "$APP_CONFIG" "${APP_CONFIG}.bak.$(date +%Y%m%d%H%M%S)" || return 1
  APP_CONFIG="$APP_CONFIG" NEWPASS="$APP_DECODED_PASSWORD" php -r '
    error_reporting(0);
    $file = getenv("APP_CONFIG");
    $new  = getenv("NEWPASS");
    $src  = file_get_contents($file);
    if ($src === false) { exit(1); }
    $out = preg_replace_callback(
      "/([\"\x27]password[\"\x27]\s*=>\s*)([\"\x27])(.*?)\2/s",
      static fn (array $m): string => $m[1] . $m[2] . str_replace(["\\", $m[2]], ["\\\\", "\\" . $m[2]], $new) . $m[2],
      $src,
      1,
      $count
    );
    if ($count !== 1 || $out === null) { exit(1); }
    exit(file_put_contents($file, $out) === false ? 1 : 0);
  ' || return 1
}

if [ ${#FINDING_TITLES[@]} -gt 0 ]; then
  print header "Repairs"

  has_finding configprelude && { run_fix "Disable the settings that were added above the first heading in ${CONFIG_PRELUDE_FILE:-the configuration file}." fix_config_prelude || true; }
  has_finding clientleak && { run_fix "Disable the server settings that were put under a client heading in ${CLIENT_LEAK_FILES[0]:-the configuration file}, so that mysql and the backup can run again." fix_client_option_leak || true; }
  has_finding socketdir  && { run_fix "Recreate the folder the database needs, and make it come back after a restart." fix_socket_dir || true; }
  has_finding dataowner  && { run_fix "Give the database back its own files." fix_datadir_owner || true; }
  has_finding unmask     && { run_fix "Allow the database service to start again." fix_unmask || true; }
  has_finding bufferpool && { run_fix "Stop the database reserving more memory than this machine has." fix_buffer_pool || true; }
  has_finding password   && { run_fix "Correct the password InteLIS has saved." fix_app_password || true; }

  if has_finding config && [ -n "$(newest_config_backup || true)" ]; then
    run_fix "Put back the last configuration that worked." fix_rollback_config || true
  elif has_finding config; then
    print info  "Correct or remove the setting quoted above, in the file under /etc/mysql"
    print plain "     that holds it. Then run sudo intelis doctor again."
  fi

  if has_finding disk; then
    print header "What is filling the disk"
    du -h -d 1 /var/lib /var/log /var/www 2>/dev/null | sort -h | tail -n 12 || true
    if [ -n "$INSTALL_PATH" ] && [ -d "$INSTALL_PATH/backups/db" ]; then
      print info "Old database backups are usually the largest thing here:"
      ls -1sht "$INSTALL_PATH/backups/db" 2>/dev/null | head -n 10 || true
      print warning "Copy the newest one somewhere safe before deleting any of them."
    fi
    print plain ""
    run_fix "Trim the system logs to free some space straight away." fix_vacuum_journal || true
    print info "Deleting anything else is a decision for a person, so this script will not do it."
  fi

  # Damage to the data files is the one case with no safe automatic answer.
  if has_finding corruption; then
    print header "Needs a person"
    print warning "The database says its own files are damaged."
    print plain  "     There is a way to start it long enough to take a backup, but the wrong"
    print plain  "     setting makes the damage permanent, so it is not done automatically."
    print plain  "     Send ${REPORT} and ask for help."
    print plain  ""
    print plain  "     Whatever else happens, do not reinstall this machine. The data is still"
    print plain  "     in ${DATADIR} and can be recovered somewhere else."

    # Damaged files and a recent backup is a solvable situation, and the person
    # reading this will not know the backups exist unless they are shown.
    if [ -n "$INSTALL_PATH" ] && [ -d "$INSTALL_PATH/backups/db" ]; then
      newest_backup="$(ls -1t "$INSTALL_PATH/backups/db"/*.sql.* 2>/dev/null | head -1 || true)"
      if [ -n "$newest_backup" ]; then
        print plain ""
        print info  "There is a backup on this machine from $(date -r "$newest_backup" +'%d %B %Y at %H:%M' 2>/dev/null || echo 'an earlier date'):"
        print plain "       $(basename "$newest_backup")"
        print plain "     Putting that back would lose only the work done since then, and is"
        print plain "     usually faster than repairing damaged files. To do it: intelis restore"
      fi
    fi
  fi

  if [ "$FIXES_OFFERED" -eq 0 ] && { $SERVER_UP || has_finding corruption; }; then
    print info "There is nothing this script can safely do on its own. What is written above says what is needed."
  fi
fi

# --- start it again -----------------------------------------------------------
#
# A stopped database most often needs nothing more than starting. The system
# killed it for memory, or the power went and it never came back. So a start is
# offered even when no cause matched. Damaged files are the exception: every
# failed start can write more damage, so that case stays with a person.
#
# restart rather than start: a server that is running but no longer answering
# has to be stopped first, and restart does both.

START_TRIED=false
fix_start_database() {
  START_TRIED=true
  systemctl reset-failed "$UNIT" >/dev/null 2>&1 || true
  # Bounded: a server flushing a large buffer pool can take minutes to stop,
  # and a person is watching. The restart carries on in the background if this
  # gives up first, and the wait below still sees it come up.
  timeout 300 systemctl restart "$UNIT" >/dev/null 2>&1 || true
  for _ in $(seq 1 60); do mysql_is_up && return 0; sleep 1; done
  return 1
}

# A setting the server refuses makes every start fail the same way, so a start
# is only worth offering once a repair has changed something.
if ! $SERVER_UP && ! has_finding corruption && { ! has_finding config || [ ${#FIXES_APPLIED[@]} -gt 0 ]; }; then
  [ "$MODE" = check ] || [ ${#FINDING_TITLES[@]} -gt 0 ] || print header "Repairs"
  STARTED=false
  if [ ${#FIXES_APPLIED[@]} -gt 0 ]; then
    # The repairs above were agreed to so that it would start. Asking again
    # would be a second question for the same decision.
    print step "Starting the database..."
    fix_start_database && STARTED=true
  else
    if $SERVER_HUNG; then
      run_fix "Restart the database." fix_start_database && STARTED=true
    else
      run_fix "Start the database again." fix_start_database && STARTED=true
    fi
  fi
  $STARTED && report "VERDICT: running again after start (${#FIXES_APPLIED[@]} fix(es) applied)"
fi

# --- result -------------------------------------------------------------------
#
# Inside intelis doctor the closing card is that script's, after it has checked
# the site again, so one run ends on one verdict. The failure detail is printed
# either way, because only this script has it.

# Handed back to intelis doctor through the exit status, because its closing
# card stands in for this one and must not call a run recovered that is not.
DB_OUTCOME=ok
if [ ${#FINDING_TITLES[@]} -gt 0 ] || ! $SERVER_UP; then
  DB_OUTCOME=unresolved
  if [ "$MODE" = check ]; then
    [ -n "$EMBEDDED" ] || card warn "Nothing was changed." \
      "This run only looked. To let it repair what it can, run: sudo intelis doctor"
  elif mysql_is_up && [ ${#FIXES_APPLIED[@]} -eq 0 ]; then
    [ -n "$EMBEDDED" ] || card warn "The database is running, and the problems above are still there." \
      "Send the report below when asking for help."
    report "VERDICT: running, nothing repaired"
  elif mysql_is_up; then
    # Recovered means every check passes now, not that a repair ran. A declined
    # repair, a finding nothing here can repair, and a login that could not be
    # tested while the server was down all show up only on a second pass.
    SERVER_UP=true
    SERVER_HUNG=false
    run_checks
    report "--- after repairs ---"
    report_findings

    if [ ${#FINDING_TITLES[@]} -eq 0 ]; then
      DB_OUTCOME=ok
      [ -n "$EMBEDDED" ] || card ok "The database is working again." "Reload InteLIS in the browser."
    else
      print header "Still wrong"
      for i in "${!FINDING_TITLES[@]}"; do
        case "${FINDING_SEVERITIES[$i]}" in
          critical) print error   "${FINDING_TITLES[$i]}" ;;
          *)        print warning "${FINDING_TITLES[$i]}" ;;
        esac
        print_wrapped "${FINDING_DETAILS[$i]}"
      done
      # Damaged files are never repaired here, so nothing else makes it safe to
      # carry on working on them.
      if has_finding corruption; then
        [ -n "$EMBEDDED" ] || card bad "The database is running, and it reports damaged files." \
          "Do not carry on working in InteLIS yet. Send the report below when asking for help."
        report "VERDICT: running with damaged files"
      else
        [ -n "$EMBEDDED" ] || card warn "The database is running, and the problems above are still there." \
          "Send the report below when asking for help."
        report "VERDICT: running with problems left"
      fi
    fi
  else
    if $START_TRIED; then
      print header "Last errors the database wrote"
      mysql_complaints | grep -E '\[ERROR\]' | strip_log_prefix |
        awk '!seen[$0]++' | tail -n 4 | fold -s -w 70 | sed 's/^/     /' || true
    fi
    [ -n "$EMBEDDED" ] || card bad "The database still will not start." \
      "Send the report below when asking for help."
    report "VERDICT: still down"
  fi
fi

printf '\n'

# /var/log cannot be opened from the desktop, and the person who has to send
# this file is not going to copy it out with a terminal command. So put a copy
# where they can see it: their own folder, owned by them, named plainly and
# ending in .txt so it opens with a double click and attaches like any document.
HANDOFF=""
if [ -n "${SUDO_USER:-}" ] && [ "$SUDO_USER" != "root" ]; then
  USER_HOME="$(getent passwd "$SUDO_USER" 2>/dev/null | cut -d: -f6 || true)"
  if [ -n "$USER_HOME" ] && [ -d "$USER_HOME" ]; then
    for dir in "$USER_HOME/Desktop" "$USER_HOME"; do
      [ -d "$dir" ] || continue
      TARGET="${dir}/mysql-report.txt"

      # This is root writing into a directory the operator can write to, so the
      # destination is not to be trusted. A symlink left at that name would
      # otherwise have root copy over whatever it points at and then hand
      # ownership of it away. Only ever replace a plain file.
      if [ -L "$TARGET" ] || { [ -e "$TARGET" ] && [ ! -f "$TARGET" ]; }; then
        continue
      fi
      rm -f -- "$TARGET" 2>/dev/null || true

      # One step, so the file is never briefly readable by anyone else: it
      # carries this machine's logs and settings.
      if install -m 600 -o "$SUDO_USER" -- "$REPORT" "$TARGET" 2>/dev/null; then
        HANDOFF="$TARGET"
        break
      fi
    done

    # A run that lands in the home folder because the Desktop was unavailable,
    # or the other way round, must not leave the previous run's copy behind for
    # somebody to send by mistake. Only ever a plain file, never a symlink.
    for dir in "$USER_HOME/Desktop" "$USER_HOME"; do
      STALE="${dir}/mysql-report.txt"
      [ "$STALE" = "$HANDOFF" ] && continue
      [ -f "$STALE" ] && [ ! -L "$STALE" ] && rm -f -- "$STALE" 2>/dev/null
    done
  fi
fi

REPORT_LABEL="Report"
[ -n "$EMBEDDED" ] && REPORT_LABEL="Database report"
# Nothing to send when it is working, whether it was all along or is now.
if { [ ${#FINDING_TITLES[@]} -eq 0 ] && $SERVER_UP; } ||
   [ "$DB_OUTCOME" = ok ]; then
  print info "${REPORT_LABEL}: ${HANDOFF:-$REPORT}"
elif [ -n "$HANDOFF" ]; then
  print info "${REPORT_LABEL}: ${HANDOFF}"
  print_wrapped "Attach this file when asking for help. It is in the Files app, in the home folder or on the Desktop. Passwords have been removed from it."
else
  print info "${REPORT_LABEL}: ${REPORT}"
  print_wrapped "Send this file when asking for help. Passwords have been removed from it."
fi

# 10 tells intelis doctor that something here still needs a person.
if [ -n "$EMBEDDED" ] && [ "$MODE" != check ] && [ "$DB_OUTCOME" != ok ]; then
  exit 10
fi
exit 0
