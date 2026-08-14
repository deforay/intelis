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

print() {
  local type=${1:-info}; shift || true
  local message=${1:-};  shift || true
  case "$type" in
    error)   printf "\033[1;91m❌ %s\033[0m\n" "$message" ;;
    success) printf "\033[1;92m✅ %s\033[0m\n" "$message" ;;
    warning) printf "\033[1;93m⚠️  %s\033[0m\n" "$message" ;;
    info)    printf "\033[1;96mℹ️  %s\033[0m\n" "$message" ;;
    plain)   printf "%s\n" "$message" ;;
    header)
      printf "\n\033[1;96m%s\033[0m\n" "$message"
      printf "\033[1;96m%s\033[0m\n" "${message//?/-}"
      ;;
    *)       printf "%s\n" "$message" ;;
  esac
}

say() { $QUIET || print "$@"; }

REPORT="/var/log/intelis-mysql-doctor-$(date +%Y%m%d%H%M%S).log"
: >"$REPORT" 2>/dev/null || REPORT="/tmp/intelis-mysql-doctor-$(date +%Y%m%d%H%M%S).log"

report() { printf '%s\n' "$*" >>"$REPORT" 2>/dev/null || true; }

report_cmd() {
  local label="$1"; shift
  {
    echo
    echo "--- ${label} ---"
    "$@" 2>&1 || true
  } >>"$REPORT" 2>/dev/null || true
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
  read -r -p "  $1 [y/N]: " answer || return 1
  case "${answer,,}" in y|yes) return 0 ;; *) return 1 ;; esac
}

run_fix() { # run_fix <description> <function> [args...]
  local description="$1"; shift
  FIXES_OFFERED=$((FIXES_OFFERED + 1))

  if [ "$MODE" = check ]; then
    print info "Can be repaired: ${description}"
    return 1
  fi

  print info "${description}"
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
    if systemctl list-unit-files "${unit}.service" 2>/dev/null | grep -q "^${unit}.service"; then
      echo "$unit"; return 0
    fi
  done
  echo "mysql"
}

# `mysqladmin ping` reports the server alive even when the credentials are
# wrong, which is exactly the distinction this script has to draw.
mysql_is_up() { mysqladmin ping --silent >/dev/null 2>&1; }

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
report_cmd "config" grep -rhvE '^[[:space:]]*(#|$)' /etc/mysql
report_cmd "listening sockets" ss -lntp
[ -n "$ERRLOG" ] && report_cmd "error log (120 lines)" tail -n 120 "$ERRLOG"

# Percona Toolkit is not a dependency and this script never installs it, but a
# machine that happens to have it can contribute its summaries to the report.
command -v pt-summary >/dev/null 2>&1 && report_cmd "pt-summary" pt-summary
if command -v pt-mysql-summary >/dev/null 2>&1 && mysql_is_up; then
  report_cmd "pt-mysql-summary" pt-mysql-summary
fi

$QUIET || print header "Checking the database"
say info "This is ${FLAVOUR}, run by the ${UNIT} service. Data lives in ${DATADIR}."

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

if $SERVER_UP; then
  say success "The database server is running."
else
  say error "The database server is not running."
fi

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

check_oom() {
  local hits
  hits="$(journalctl -k --since "-7 days" --no-pager 2>/dev/null |
    grep -iE 'oom.?kill|out of memory' | grep -ci -e mysql -e mariadb || true)"
  [ -n "$hits" ] && [ "$hits" -gt 0 ] 2>/dev/null || return 0
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

check_config_refusal() {
  [ -n "$ERRLOG" ] || return 0
  local refusal
  refusal="$(error_log_recent | grep -iE "unknown variable|unknown option|error while setting value" | tail -n 1 || true)"
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

check_corruption() {
  [ -n "$ERRLOG" ] || return 0
  local marker
  marker="$(error_log_recent | grep -iE "corrupt|page.*checksum|cannot find tablespace|forcing recovery|Assertion failure" | tail -n 1 || true)"
  [ -n "$marker" ] || return 0
  finding critical corruption \
    "The database reports damage in its own files." \
    "Its own words: ${marker}"
}

check_socket_dir() {
  local sockpath sockdir
  sockpath="$(grep -rhiE '^[[:space:]]*socket' /etc/mysql 2>/dev/null | head -1 |
    sed -E 's/.*=[[:space:]]*//; s/[[:space:]]*$//' || true)"
  [ -n "$sockpath" ] || sockpath="/var/run/mysqld/mysqld.sock"
  sockdir="$(dirname "$sockpath")"
  [ -d "$sockdir" ] && return 0
  finding critical socketdir \
    "The folder the database needs for its connection file (${sockdir}) is missing." \
    "That folder is emptied at every restart and normally recreated automatically. While it is missing the server starts and immediately gives up."
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

  if MYSQL_PWD="$pass" mysql --no-defaults -u "$user" -h "${host:-localhost}" \
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
     MYSQL_PWD="$decoded" mysql --no-defaults -u "$user" -h "${host:-localhost}" \
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

check_disk
check_oom
check_buffer_pool
check_config_refusal
check_corruption
check_socket_dir
check_datadir_ownership
check_unit_masked
check_port_conflict
check_app_credentials
check_connection_limit
check_crashed_tables

# --- verdict ------------------------------------------------------------------

print header "What is wrong"

if [ ${#FINDING_TITLES[@]} -eq 0 ]; then
  if $SERVER_UP; then
    print success "Nothing looks wrong with the database."
    [ "$APP_CONN_OK" = "yes" ] && print success "InteLIS can log in to it."
    report "VERDICT: healthy"
  else
    print error "The database is not running, and none of the usual causes matched."
    print info  "The report has the full logs. Send it on and ask for help."
    report "VERDICT: down, no known cause matched"
  fi
else
  i=0
  while [ $i -lt ${#FINDING_TITLES[@]} ]; do
    n=$((i + 1))
    case "${FINDING_SEVERITIES[$i]}" in
      critical) print error   "${n}. ${FINDING_TITLES[$i]}" ;;
      *)        print warning "${n}. ${FINDING_TITLES[$i]}" ;;
    esac
    print plain "     ${FINDING_DETAILS[$i]}"
    report "FINDING [${FINDING_SEVERITIES[$i]}/${FINDING_KEYS[$i]}] ${FINDING_TITLES[$i]} :: ${FINDING_DETAILS[$i]}"
    i=$((i + 1))
  done
fi

# --- repairs ------------------------------------------------------------------

fix_socket_dir() {
  local dir="/var/run/mysqld"
  mkdir -p "$dir" && chown mysql:mysql "$dir" && chmod 755 "$dir"
}

fix_datadir_owner() { chown -R mysql:mysql "$DATADIR"; }

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
  print header "What can be done about it"

  has_finding socketdir  && { run_fix "Recreate the missing folder and give it to the database." fix_socket_dir || true; }
  has_finding dataowner  && { run_fix "Give the database back its own files." fix_datadir_owner || true; }
  has_finding unmask     && { run_fix "Allow the database service to start again." fix_unmask || true; }
  has_finding bufferpool && { run_fix "Stop the database reserving more memory than this machine has." fix_buffer_pool || true; }
  has_finding password   && { run_fix "Correct the password InteLIS has saved." fix_app_password || true; }

  if has_finding config && [ -n "$(newest_config_backup || true)" ]; then
    run_fix "Put back the last configuration that worked." fix_rollback_config || true
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
    print header "This one needs a person"
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

  if [ "$FIXES_OFFERED" -eq 0 ]; then
    print info "There is nothing this script can safely do on its own. What is written above says what is needed."
  fi
fi

# --- start it again -----------------------------------------------------------

if ! $SERVER_UP; then
  if [ ${#FIXES_APPLIED[@]} -gt 0 ]; then
    print header "Starting the database"
    systemctl start "$UNIT" >/dev/null 2>&1 || true
    for _ in $(seq 1 30); do mysql_is_up && break; sleep 1; done
    if mysql_is_up; then
      print success "The database is running again."
      report "VERDICT: recovered after ${#FIXES_APPLIED[@]} fix(es)"
    else
      print error "It still will not start."
      print info  "Send ${REPORT} on. It also records everything that was changed just now."
      report "VERDICT: still down after fixes"
    fi
  elif [ "$MODE" = check ]; then
    print header "Next step"
    print info "This run only looked; nothing was changed."
    print plain "     To let it repair what it can: sudo intelis fix-database"
  fi
fi

print header "Report"
print info "Written to ${REPORT}"
print plain "     Send that file when asking for help. It holds the service log, the"
print plain "     database error log, memory, disk and configuration."

exit 0
