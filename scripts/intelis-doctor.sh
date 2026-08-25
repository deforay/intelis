#!/bin/bash
set -Eeuo pipefail

# Works out why InteLIS will not open in the browser, says so in plain language,
# and offers to repair what is safe to repair.
#
# To use this script:
#   sudo intelis doctor
#
# That command does not exist on every machine, and a machine whose web server
# is broken is exactly the machine that cannot be updated to get it. So this
# runs on its own, straight from the internet, on a machine of any age:
#
#   sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
#
# or, where curl is installed instead of wget:
#
#   sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
#
# Note that is `bash -c "$(…)"`, not `… | bash`. Piping would hand this script
# its own text as the answers to the questions it asks below.
#
# Run in a terminal, it explains what it found and asks before changing
# anything. Run anywhere else (a cron line, a remote command) it only reports.
#
# This is the web side: the server, PHP, the files it serves and who may read
# them. The database has its own script, and this one fetches and runs it when
# the answer turns out to lie there, so that one command is enough to start from
# either symptom.
#
# Options:
#   --check     Only look and report. Never change anything, even when asked to
#   --yes       Apply every safe repair without asking. For unattended use
#   --quiet     Print the verdict and the report path, nothing else
#   --help      Show usage
#
# It always writes a full report to /var/log/intelis-doctor-<timestamp>.log.

# ------------------------------------------------------------------------------
# This runs on a machine that is already in trouble, so every probe below is
# individually guarded and none of them may abort the run. The ERR trap is for
# genuine bugs in this script, not for a grep that found nothing.
# ------------------------------------------------------------------------------

trap 'echo "intelis doctor stopped unexpectedly at line $LINENO (status $?). Please report this."' ERR

MODE=ask          # ask | check | yes
QUIET=false

RAW_BASE="https://raw.githubusercontent.com/deforay/intelis/master/scripts"

# Spelled out here rather than read back out of "$0", because the whole point of
# the invocation above is that there is no file on disk to read.
usage() {
  cat <<'USAGE'
Finds out why InteLIS will not open in the browser, and offers to repair it.

  sudo intelis doctor

Where that command does not exist:

  sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"

Options:
  --check   Only look and report. Change nothing
  --yes     Apply every safe repair without asking
  --quiet   Print the verdict and the report path, nothing else
  --help    Show this

If the trouble turns out to be the database, this fetches and runs the database
doctor itself. To go straight there: sudo intelis fix-database
A full report is always written to /var/log/intelis-doctor-<timestamp>.log.
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
  echo "This needs administrator rights. Run: sudo intelis doctor"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a

# --- shared helpers -----------------------------------------------------------
#
# print and the gum-aware prompt layer come from shared-functions.sh, so this
# script asks its questions the way setup.sh and the backup setup do.
#
# Unlike those, it may not insist on having them. A machine whose web server is
# broken is often a machine with no working network either, and a doctor that
# refuses to run without a download is a doctor that is absent exactly when it
# is wanted. So the fetch is best-effort and plain equivalents stand in.
#
# INTELIS_TRACK is pinned before sourcing: shared-functions.sh resolves the
# newest release tag at source time with an untimed `git ls-remote`, and nothing
# here upgrades anything, so that lookup would be pure latency on a bad link.
INTELIS_TRACK="${INTELIS_TRACK:-master}"

SHARED_FN_PATH="/usr/local/lib/intelis/shared-functions.sh"

if command -v wget >/dev/null 2>&1; then
  download_to() { wget -q -O "$1" "$2"; }
elif command -v curl >/dev/null 2>&1; then
  download_to() { curl -fsSL -o "$1" "$2"; }
else
  download_to() { return 1; }
fi

# Stage the download and swap it in only once it looks like the real thing:
# `wget -O` and `curl -o` both truncate the destination before transferring, so
# a network hiccup leaves a zero-byte file that exists, sources cleanly, and
# defines nothing.
fetch_shared_fn() {
  local dest="$1" url="$2" tmp
  mkdir -p "$(dirname "$dest")" 2>/dev/null || return 1
  tmp="$(mktemp "${dest}.XXXXXX" 2>/dev/null)" || return 1
  if download_to "$tmp" "$url" && [ -s "$tmp" ] && grep -q '^ask_choice()' "$tmp"; then
    chmod 0644 "$tmp"
    mv -f "$tmp" "$dest"
    return 0
  fi
  rm -f "$tmp"
  return 1
}

fetch_shared_fn "$SHARED_FN_PATH" "${RAW_BASE}/shared-functions.sh" || true

HAVE_SHARED=false
if [ -r "$SHARED_FN_PATH" ]; then
  # shellcheck disable=SC1090
  source "$SHARED_FN_PATH" 2>/dev/null || true
  # Present is not the same as usable — a truncated copy sources without error
  # and defines nothing.
  declare -F ask_yes_no >/dev/null 2>&1 && declare -F print >/dev/null 2>&1 && HAVE_SHARED=true
fi

if ! $HAVE_SHARED; then
  print() {
    local type=${1:-info}; shift || true
    local message=${1:-};  shift || true
    case "$type" in
      error)   printf "\033[1;91m❌ Error:\033[0m %s\n" "$message" ;;
      success) printf "\033[1;92m✅ Success:\033[0m %s\n" "$message" ;;
      warning) printf "\033[1;93m⚠️  Warning:\033[0m %s\n" "$message" ;;
      info)    printf "\033[1;96mℹ️  Info:\033[0m %s\n" "$message" ;;
      header)
        printf "\n\033[1;96m%s\033[0m\n" "$message"
        printf "\033[1;96m%s\033[0m\n" "${message//?/-}"
        ;;
      *)       printf "%s\n" "$message" ;;
    esac
  }
  ask_yes_no() {
    local prompt="$1" default="${2:-no}" answer
    [ -t 0 ] || { [ "$default" = yes ] && return 0 || return 1; }
    printf '%s (y/n) [default: %s]: ' "$prompt" "$default"
    read -r answer || { [ "$default" = yes ] && return 0 || return 1; }
    case "${answer,,}" in y|yes) return 0 ;; n|no) return 1 ;; *) [ "$default" = yes ] ;; esac
  }
  ui_renderer() { echo plain; }
  ensure_gum()  { return 1; }
fi

# gum is a nicety here exactly as it is in setup.sh: every prompt below is
# already correct without it, so its absence is never fatal and never delays.
ensure_gum || true

using_gum() { [ "$(ui_renderer 2>/dev/null || echo plain)" = "gum" ]; }

say() { $QUIET || print "$@"; }

# Repairs that install packages are the slow part of any run, and their output is
# already sent to the report rather than the screen. Without a spinner that is a
# silent minute, which reads as a hang to the person waiting.
spin() { # spin <title> <command...>
  local title="$1"; shift
  if using_gum && [ "$QUIET" = false ]; then
    gum spin --spinner dot --title "$title" -- "$@"
  else
    say info "$title"
    "$@"
  fi
}

# --- report -------------------------------------------------------------------

REPORT="/var/log/intelis-doctor-$(date +%Y%m%d%H%M%S).log"
( umask 077 && : >"$REPORT" ) 2>/dev/null ||
  { REPORT="/tmp/intelis-doctor-$(date +%Y%m%d%H%M%S).log"; ( umask 077 && : >"$REPORT" ) 2>/dev/null || true; }
chmod 600 "$REPORT" 2>/dev/null || true

# One report on the machine, not a pile of them. The report exists to be sent
# and then to stop existing, so every earlier one goes now.
for old in /var/log/intelis-doctor-*.log /tmp/intelis-doctor-*.log; do
  [ -f "$old" ] || continue
  [ "$old" = "$REPORT" ] && continue
  rm -f -- "$old" 2>/dev/null || true
done

# The whole point of the report is that it gets sent somewhere: attached to an
# email, or photographed, or forwarded over WhatsApp from a lab. So nothing
# secret may reach it. The application config holds the database password and is
# swept up by any read of the install directory.
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

run_fix() { # run_fix <description> <function> [args...]
  local description="$1"; shift
  FIXES_OFFERED=$((FIXES_OFFERED + 1))

  if [ "$MODE" = check ]; then
    print info "Can be repaired: ${description}"
    return 1
  fi

  print info "${description}"
  if [ "$MODE" = ask ] && ! ask_yes_no "Do this now?" no; then
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

find_install_path() {
  local p
  for p in /var/www/intelis /var/www/vlsm /var/www/html/intelis /var/www/html/vlsm; do
    [ -f "$p/configs/config.production.php" ] && [ -d "$p/public" ] && { echo "$p"; return 0; }
  done
  return 1
}

# Ubuntu 24.04 and older carry PHP 8.4. Ubuntu 26.04 dropped it and ships 8.5
# instead. Getting this wrong is its own failure mode: asking apt for a version
# the release does not have installs nothing and looks like the repair simply
# did not work.
default_php_version() {
  local release
  release="$(lsb_release -rs 2>/dev/null || echo "")"
  [ -z "$release" ] && { echo "8.4"; return; }
  if [ "$(printf '%s\n' "26.04" "$release" | sort -V | head -n1)" = "26.04" ]; then
    echo "8.5"
  else
    echo "8.4"
  fi
}

# What Apache is actually loaded with right now, which is the only version whose
# absence explains the symptom.
apache_php_module_version() {
  local f
  for f in /etc/apache2/mods-enabled/php*.load; do
    [ -e "$f" ] || continue
    basename "$f" .load | sed 's/^php//'
    return 0
  done
  return 1
}

cli_php_version() { php -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;' 2>/dev/null || return 1; }

active_mpm() {
  local f
  for f in /etc/apache2/mods-enabled/mpm_*.load; do
    [ -e "$f" ] || continue
    basename "$f" .load
    return 0
  done
  return 1
}

apache_doc_root() {
  local root
  root="$(grep -rhi '^[[:space:]]*DocumentRoot' /etc/apache2/sites-enabled/ 2>/dev/null |
          head -n1 | awk '{print $2}' | tr -d '"'"'" || true)"
  [ -n "$root" ] && { echo "$root"; return 0; }
  return 1
}

port_80_owner() {
  ss -lntpH 2>/dev/null | awk '$4 ~ /:80$/ {print $NF; exit}' |
    grep -oE 'users:\(\("[^"]+' | sed 's/.*"//' || true
}

# curl is not on every Ubuntu server install and wget is not on every desktop
# one, so neither may be assumed.
http_get() { # http_get <url> -> body on stdout
  if command -v curl >/dev/null 2>&1; then
    curl -sS --max-time 15 -o - "$1" 2>/dev/null
  elif command -v wget >/dev/null 2>&1; then
    wget -qO- --timeout=15 "$1" 2>/dev/null
  else
    return 2
  fi
}

http_status() { # http_status <url> -> three-digit code, or empty
  if command -v curl >/dev/null 2>&1; then
    curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$1" 2>/dev/null
  else
    return 2
  fi
}

web_user() {
  local u
  for u in www-data apache2 apache; do
    id -u "$u" >/dev/null 2>&1 && { echo "$u"; return 0; }
  done
  echo "www-data"
}

mysql_unit_name() {
  local unit
  for unit in mysql mariadb mysqld; do
    if systemctl list-unit-files "${unit}.service" 2>/dev/null | grep -q "^${unit}.service"; then
      echo "$unit"; return 0
    fi
  done
  echo "mysql"
}

APACHE_INSTALLED=false
command -v apache2 >/dev/null 2>&1 && APACHE_INSTALLED=true

INSTALL_PATH="$(find_install_path || true)"
DOC_ROOT="$(apache_doc_root || true)"
[ -z "$DOC_ROOT" ] && [ -n "$INSTALL_PATH" ] && DOC_ROOT="${INSTALL_PATH}/public"
WEB_USER="$(web_user)"
TARGET_PHP="$(apache_php_module_version || cli_php_version || default_php_version)"
MPM="$(active_mpm || echo none)"
DB_UNIT="$(mysql_unit_name)"

report "=== InteLIS doctor: $(date +'%F %T') ==="
report "install=${INSTALL_PATH:-none} docroot=${DOC_ROOT:-none} php=${TARGET_PHP} mpm=${MPM} webuser=${WEB_USER} mode=${MODE} gum=$(ui_renderer 2>/dev/null || echo plain)"
report_cmd "host" uname -a
report_cmd "os" lsb_release -a
report_cmd "php cli" php -v
report_cmd "apache modules" apache2ctl -M
report_cmd "apache vhosts" apache2ctl -S
report_cmd "apache config test" apache2ctl -t
report_cmd "apache status" systemctl status apache2 --no-pager -l
report_cmd "apache error log (120 lines)" tail -n 120 /var/log/apache2/error.log
report_cmd "mods-enabled" ls -l /etc/apache2/mods-enabled
report_cmd "conf-enabled" ls -l /etc/apache2/conf-enabled
report_cmd "sites-enabled" ls -l /etc/apache2/sites-enabled
report_cmd "php packages" dpkg -l "libapache2-mod-php*" "php*-cli"
report_cmd "php-switch log (40 lines)" tail -n 40 /var/log/php-switch.log
report_cmd "listening sockets" ss -lntp
report_cmd "database service" systemctl is-active "$DB_UNIT"
report_cmd "disk" df -h / /var /var/www
[ -n "$INSTALL_PATH" ] && report_cmd "install dir" ls -ld "$INSTALL_PATH" "$INSTALL_PATH/public" "$INSTALL_PATH/var"

$QUIET || print header "Checking the site"

# --- is there a web server at all ---------------------------------------------

if ! $APACHE_INSTALLED; then
  print error "There is no web server installed on this machine at all."
  print info  "If this machine is meant to run InteLIS, it was never set up, or the"
  print plain "     package was removed. Run setup.sh again rather than installing a"
  print plain "     web server by hand."
  report "VERDICT: apache not installed"
  exit 1
fi

if [ -z "$INSTALL_PATH" ]; then
  print warning "No InteLIS installation was found under /var/www."
  print plain   "     The checks below still apply to the web server itself."
  report "note: no install path found"
fi

APACHE_UP=false
systemctl is-active --quiet apache2 2>/dev/null && APACHE_UP=true

if $APACHE_UP; then
  say success "The web server is running."
else
  say error "The web server is not running."
fi

# --- checks -------------------------------------------------------------------

# The one test that settles the argument. Everything else below explains this
# answer; none of it replaces it.
PROBE_RESULT=unknown
PROBE_STATUS=""
check_probe() {
  local probe body

  if [ -z "$DOC_ROOT" ] || [ ! -d "$DOC_ROOT" ]; then
    PROBE_RESULT=no-docroot
    return
  fi

  probe="${DOC_ROOT}/.intelis-doctor-probe.php"
  # Never follow a symlink left at that name, and never leave the file behind.
  [ -L "$probe" ] && { PROBE_RESULT=unknown; return; }
  printf '%s' '<?php echo "INTELIS_PHP_OK";' >"$probe" 2>/dev/null || { PROBE_RESULT=unwritable; return; }
  chmod 644 "$probe" 2>/dev/null || true

  body="$(http_get "http://127.0.0.1/.intelis-doctor-probe.php" || true)"
  rm -f -- "$probe" 2>/dev/null || true

  report_cmd "probe response" printf '%s\n' "$body"

  if [ -z "$body" ]; then
    PROBE_RESULT=unreachable
  elif printf '%s' "$body" | grep -q 'INTELIS_PHP_OK'; then
    PROBE_RESULT=php-runs
  elif printf '%s' "$body" | grep -q '<?php'; then
    PROBE_RESULT=source-served
  else
    PROBE_RESULT=other
  fi

  PROBE_STATUS="$(http_status "http://127.0.0.1/" || true)"
  report "site status=${PROBE_STATUS:-unknown}"
}

check_php_handler() {
  local mods
  mods="$(apache2ctl -M 2>/dev/null || true)"

  if printf '%s' "$mods" | grep -q 'php[0-9._]*_module'; then
    say success "Apache knows how to run PHP."
    return
  fi

  # mod_php will not load under the event or worker MPM, and Ubuntu ships event
  # as the default. The .load file is not even created while one of those is
  # active, so enabling the PHP module first quietly does nothing — which is why
  # this is checked and repaired as one thing rather than two.
  if [ "$MPM" != "mpm_prefork" ]; then
    finding critical handler \
      "Apache is not able to run PHP, so it sends the page's code to the browser instead." \
      "The ${MPM} worker is in use, and PHP can only run under mpm_prefork. This is the usual state after an Ubuntu upgrade."
  else
    finding critical handler \
      "Apache is not able to run PHP, so it sends the page's code to the browser instead." \
      "The PHP module for Apache is not loaded."
  fi
}

check_php_packages() {
  local pkg="libapache2-mod-php${TARGET_PHP}" candidate

  dpkg -s "$pkg" >/dev/null 2>&1 && return 0

  candidate="$(apt-cache policy "$pkg" 2>/dev/null | awk '/Candidate:/ {print $2}' || true)"
  if [ -z "$candidate" ] || [ "$candidate" = "(none)" ]; then
    finding critical ppa \
      "The PHP packages this machine needs are not available to install." \
      "apt has no ${pkg}. An Ubuntu release upgrade disables the PHP repository, which leaves nothing to install and makes every repair look like it failed."
  else
    finding critical handler \
      "The PHP module for Apache is not installed." \
      "${pkg} is missing. It is available and can be installed now."
  fi
}

check_maintenance_marker() {
  local marker conf

  [ -n "$INSTALL_PATH" ] || return 0
  marker="${INSTALL_PATH}/public/.maintenance"

  if [ -f "$marker" ]; then
    finding critical maintenance \
      "The site is still showing the 'Upgrade in progress' page." \
      "An update was interrupted and left its marker behind, so every page is being turned away. Nothing is wrong with the machine; the marker just has to go."
  fi

  for conf in /etc/apache2/conf-enabled/intelis-maintenance-*.conf; do
    [ -e "$conf" ] || continue
    finding critical maintenance \
      "Apache is still configured to turn every visitor away for an update." \
      "$(basename "$conf") is enabled. An interrupted update left it in place."
    break
  done
}

check_config_valid() {
  local out
  out="$(apache2ctl -t 2>&1 || true)"
  if ! printf '%s' "$out" | grep -qi 'Syntax OK'; then
    finding critical config \
      "The web server's configuration has an error in it, so it will not start." \
      "$(printf '%s' "$out" | head -n 2 | tr '\n' ' ')"
  fi
}

check_foreign_server() {
  local owner
  owner="$(port_80_owner || true)"
  [ -z "$owner" ] && return 0
  [ "$owner" = "apache2" ] && return 0
  finding critical foreign \
    "Another program has taken the address the site is served on." \
    "Port 80 is held by ${owner}, not by Apache. While that is running, nothing Apache is configured to do reaches the browser."
}

check_permissions() {
  local dir
  [ -n "$INSTALL_PATH" ] || return 0

  if ! sudo -u "$WEB_USER" test -r "${INSTALL_PATH}/public/index.php" 2>/dev/null; then
    finding critical permissions \
      "The web server is not allowed to read the site's files." \
      "${WEB_USER} cannot read ${INSTALL_PATH}/public/index.php."
    return
  fi

  for dir in var public/uploads; do
    [ -d "${INSTALL_PATH}/${dir}" ] || continue
    if ! sudo -u "$WEB_USER" test -w "${INSTALL_PATH}/${dir}" 2>/dev/null; then
      finding warning permissions \
        "The web server cannot write to the folders it has to write to." \
        "${WEB_USER} cannot write to ${INSTALL_PATH}/${dir}. Pages load, but saving, caching and uploads fail."
      return
    fi
  done
}

check_disk() {
  local avail_kb target=/var
  avail_kb="$(df -Pk "$target" 2>/dev/null | awk 'NR==2 {print $4}' || true)"
  [ -z "$avail_kb" ] && return 0
  if [ "$avail_kb" -lt 262144 ]; then
    finding critical disk \
      "This machine has almost no disk space left." \
      "$(df -h "$target" 2>/dev/null | awk 'NR==2 {print $4}') free on ${target}. The web server and PHP both stop working correctly before it reaches zero."
  fi
}

# The database is somebody else's script to diagnose. All this decides is
# whether to go and get it.
check_database() {
  if ! systemctl is-active --quiet "$DB_UNIT" 2>/dev/null; then
    finding critical database \
      "The database is not running." \
      "InteLIS cannot show a single page without it, however healthy the web server is."
  fi
}

check_config_valid
check_foreign_server
check_php_packages
check_php_handler
check_maintenance_marker
check_permissions
check_disk
check_database
$APACHE_UP && check_probe

if ! $APACHE_UP && ! has_finding config; then
  finding critical start \
    "The web server is installed but not running." \
    "Nothing is listening for the browser to connect to."
fi

case "$PROBE_RESULT" in
  source-served)
    say error "Confirmed: the server is sending PHP code to the browser instead of running it."
    ;;
  php-runs)
    say success "PHP runs correctly on this machine."
    # PHP works and the site still fails: that is the application's own error,
    # and on these machines it is the database far more often than not.
    if [ -n "$PROBE_STATUS" ] && [ "$PROBE_STATUS" -ge 500 ] 2>/dev/null && ! has_finding database; then
      finding critical database \
        "The site runs, but InteLIS itself is returning an error." \
        "http://127.0.0.1/ answered ${PROBE_STATUS}. The web server is doing its job, so the fault is inside the application — most often the database."
    fi
    ;;
  unreachable)
    if ! has_finding foreign && ! has_finding start; then
      finding critical unreachable \
        "The web server is running but did not answer." \
        "A request to http://127.0.0.1/ got nothing back. A firewall or a broken virtual host will do this."
    fi
    ;;
  no-docroot)
    finding warning docroot \
      "Apache is not pointed at the InteLIS files." \
      "No usable DocumentRoot was found in the enabled sites. The site cannot be served until one names ${INSTALL_PATH:-the install}/public."
    ;;
esac

report "probe=${PROBE_RESULT}"

# --- verdict ------------------------------------------------------------------

print header "What is wrong"

if [ ${#FINDING_TITLES[@]} -eq 0 ]; then
  if [ "$PROBE_RESULT" = php-runs ]; then
    print success "Nothing looks wrong with the web server, and InteLIS answered."
    report "VERDICT: healthy"
  else
    print warning "Nothing matched, and the site still did not answer as expected."
    print info    "The report has the full logs. Send it on and ask for help."
    report "VERDICT: no known cause matched"
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

fix_start_apache() {
  systemctl start apache2 >/dev/null 2>&1 || return 1
  sleep 2
  systemctl is-active --quiet apache2
}

fix_add_ppa() {
  command -v add-apt-repository >/dev/null 2>&1 ||
    spin "Installing the tool that manages repositories..." \
      apt-get install -y software-properties-common >/dev/null 2>&1 || true
  spin "Restoring the PHP repository..." add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || return 1
  spin "Refreshing the package list..." apt-get update -y >/dev/null 2>&1 || return 1
  TARGET_PHP="$(default_php_version)"
  return 0
}

# The order here is the whole repair. mpm_prefork has to be enabled before the
# PHP module, because the module's .load file is not created while the event or
# worker MPM is active — which is what makes the same commands in the other
# order appear to succeed and change nothing.
fix_php_handler() {
  spin "Installing PHP ${TARGET_PHP} for the web server..." \
    apt-get install -y "libapache2-mod-php${TARGET_PHP}" >/dev/null 2>&1 || true

  if [ "$(active_mpm || echo none)" != "mpm_prefork" ]; then
    a2dismod -f mpm_event mpm_worker >/dev/null 2>&1 || true
    a2enmod mpm_prefork >/dev/null 2>&1 || return 1
  fi

  a2enmod "php${TARGET_PHP}" >/dev/null 2>&1 || return 1
  update-alternatives --set php "/usr/bin/php${TARGET_PHP}" >/dev/null 2>&1 || true

  apache2ctl -t >/dev/null 2>&1 || return 1
  systemctl restart apache2 >/dev/null 2>&1 || return 1
  sleep 2
  apache2ctl -M 2>/dev/null | grep -q 'php[0-9._]*_module'
}

fix_maintenance() {
  local conf
  [ -n "$INSTALL_PATH" ] && rm -f -- \
    "${INSTALL_PATH}/public/.maintenance" \
    "${INSTALL_PATH}/public/.intelis-maintenance.html" 2>/dev/null || true

  for conf in /etc/apache2/conf-enabled/intelis-maintenance-*.conf; do
    [ -e "$conf" ] || continue
    a2disconf "$(basename "$conf" .conf)" >/dev/null 2>&1 || true
  done

  systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1 || true
  return 0
}

fix_permissions() {
  [ -n "$INSTALL_PATH" ] || return 1
  local dir
  for dir in var public/uploads; do
    [ -d "${INSTALL_PATH}/${dir}" ] || continue
    chown -R "${WEB_USER}:${WEB_USER}" "${INSTALL_PATH}/${dir}" 2>/dev/null || return 1
  done
  # The files themselves only have to be readable; ownership of the tree as a
  # whole is deliberately left alone, because updates run as a different account.
  chmod -R a+rX "${INSTALL_PATH}/public" 2>/dev/null || true
  return 0
}

fix_stop_foreign() {
  local owner
  owner="$(port_80_owner || true)"
  [ -z "$owner" ] && return 1
  systemctl stop "$owner" >/dev/null 2>&1 || return 1
  systemctl disable "$owner" >/dev/null 2>&1 || true
  systemctl restart apache2 >/dev/null 2>&1 || true
  return 0
}

# Fetch the database doctor rather than reimplementing any part of it. Same
# order the intelis command uses: the copy shipped with the install, then the
# one installed on the machine, then the internet.
locate_database_doctor() {
  local installed="/usr/local/bin/intelis-fix-database"

  if [ -n "$INSTALL_PATH" ] && [ -f "${INSTALL_PATH}/scripts/mysql-doctor.sh" ]; then
    echo "${INSTALL_PATH}/scripts/mysql-doctor.sh"; return 0
  fi
  if [ -x "$installed" ]; then
    echo "$installed"; return 0
  fi

  local tmp
  tmp="$(mktemp "${installed}.XXXXXX" 2>/dev/null)" || return 1
  if download_to "$tmp" "${RAW_BASE}/mysql-doctor.sh" && [ -s "$tmp" ] && head -n1 "$tmp" | grep -q '^#!'; then
    chmod 0755 "$tmp"
    mv -f "$tmp" "$installed"
    echo "$installed"; return 0
  fi
  rm -f "$tmp"
  return 1
}

run_database_doctor() {
  local script pass=()
  if ! script="$(locate_database_doctor)"; then
    print error "The database doctor could not be downloaded. Check this machine's internet connection."
    print info  "Once it is online: sudo intelis fix-database"
    return 1
  fi
  case "$MODE" in
    check) pass+=(--check) ;;
    yes)   pass+=(--yes) ;;
  esac
  $QUIET && pass+=(--quiet)
  report "handing over to the database doctor: ${script}"
  bash "$script" ${pass[@]+"${pass[@]}"}
}

DB_DOCTOR_RAN=false

if [ ${#FINDING_TITLES[@]} -gt 0 ]; then
  print header "What can be done about it"

  has_finding maintenance && { run_fix "Take the site out of update mode." fix_maintenance || true; }
  has_finding ppa         && { run_fix "Restore the PHP repository so the packages can be installed." fix_add_ppa || true; }
  has_finding handler     && { run_fix "Make Apache able to run PHP again." fix_php_handler || true; }
  has_finding permissions && { run_fix "Give the web server access to the folders it needs." fix_permissions || true; }
  has_finding start       && { run_fix "Start the web server." fix_start_apache || true; }

  if has_finding foreign; then
    print warning "Stopping another program that holds port 80 is a decision for a person."
    print plain   "     If it is a web server that was installed by mistake, it is safe to stop."
    print plain   "     If it was installed on purpose, stopping it will break whatever uses it."
    run_fix "Stop it and let Apache have the address." fix_stop_foreign || true
  fi

  if has_finding config; then
    print header "This one needs a person"
    print warning "The web server's own configuration is broken, and guessing at an edit would"
    print plain   "     make it worse. This says which file and which line:"
    print plain   ""
    apache2ctl -t 2>&1 | head -n 5 || true
    print plain   ""
    print plain   "     Send ${REPORT} and ask for help."
  fi

  if has_finding docroot; then
    print info  "Apache is not pointed at the InteLIS files."
    print plain "     Re-running setup.sh writes that virtual host correctly. Editing it by"
    print plain "     hand is possible but easy to get wrong, so it is not done automatically."
  fi

  if has_finding disk; then
    print header "What is filling the disk"
    du -h -d 1 /var/log /var/www 2>/dev/null | sort -h | tail -n 10 || true
    print info "Deleting anything is a decision for a person, so this script will not do it."
  fi

  if [ "$FIXES_OFFERED" -eq 0 ] && ! has_finding database; then
    print info "There is nothing this script can safely do on its own. What is written above says what is needed."
  fi
fi

# --- did it work --------------------------------------------------------------

if [ ${#FIXES_APPLIED[@]} -gt 0 ]; then
  print header "Checking again"
  systemctl is-active --quiet apache2 2>/dev/null || systemctl start apache2 >/dev/null 2>&1 || true
  APACHE_UP=true
  DOC_ROOT="$(apache_doc_root || true)"
  [ -z "$DOC_ROOT" ] && [ -n "$INSTALL_PATH" ] && DOC_ROOT="${INSTALL_PATH}/public"
  check_probe
  report "probe after fixes=${PROBE_RESULT}"

  case "$PROBE_RESULT" in
    php-runs)
      print success "The web server is working again."
      ;;
    source-served)
      print error "The server is still sending PHP code to the browser."
      print info  "Send ${REPORT} on. It records everything that was changed just now."
      report "VERDICT: still broken after fixes"
      ;;
    *)
      print warning "The site did not answer as expected yet."
      print info    "Send ${REPORT} on. It records everything that was changed just now."
      report "VERDICT: unresolved after fixes"
      ;;
  esac
fi

# --- the database ---------------------------------------------------------------
#
# Run last, and only once the web side is settled: its own findings are far
# easier to read when they are not interleaved with Apache's.

if has_finding database || { [ "$PROBE_RESULT" = php-runs ] && [ -n "$PROBE_STATUS" ] && [ "$PROBE_STATUS" -ge 500 ] 2>/dev/null; }; then
  print header "The database"
  print info "The web server is not the whole answer here, so the database doctor runs next."
  print plain "     It asks its own questions and writes its own report."
  print plain ""
  if [ "$MODE" = check ] || [ "$MODE" = yes ] || ask_yes_no "Check the database now?" yes; then
    DB_DOCTOR_RAN=true
    run_database_doctor || true
  else
    print info "Skipped. To do it later: sudo intelis fix-database"
  fi
fi

if [ ${#FIXES_APPLIED[@]} -gt 0 ] && [ "$PROBE_RESULT" = php-runs ]; then
  print success "Open InteLIS in the browser."
  report "VERDICT: recovered after ${#FIXES_APPLIED[@]} fix(es)"
elif [ "$MODE" = check ] && [ ${#FINDING_TITLES[@]} -gt 0 ]; then
  print header "Next step"
  print info  "This run only looked; nothing was changed."
  print plain "     To let it repair what it can: sudo intelis doctor"
fi

# The database doctor has just printed its own report path and put its own copy
# in the operator's folder. Printing a second set of instructions on top of that
# is how somebody ends up sending the wrong file.
if $DB_DOCTOR_RAN; then
  print info "This script's own report is at ${REPORT}"
  exit 0
fi

print header "Report"

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
      TARGET="${dir}/site-report.txt"

      # This is root writing into a directory the operator can write to, so the
      # destination is not to be trusted. A symlink left at that name would
      # otherwise have root copy over whatever it points at and then hand
      # ownership of it away. Only ever replace a plain file.
      if [ -L "$TARGET" ] || { [ -e "$TARGET" ] && [ ! -f "$TARGET" ]; }; then
        continue
      fi
      rm -f -- "$TARGET" 2>/dev/null || true

      if install -m 600 -o "$SUDO_USER" -- "$REPORT" "$TARGET" 2>/dev/null; then
        HANDOFF="$TARGET"
        break
      fi
    done

    for dir in "$USER_HOME/Desktop" "$USER_HOME"; do
      STALE="${dir}/site-report.txt"
      [ "$STALE" = "$HANDOFF" ] && continue
      [ -f "$STALE" ] && [ ! -L "$STALE" ] && rm -f -- "$STALE" 2>/dev/null
    done
  fi
fi

if [ -n "$HANDOFF" ]; then
  print info  "A copy has been put in your own folder, ready to send:"
  print plain "       ${HANDOFF}"
  print plain ""
  print plain "     Open Files, find site-report.txt, and attach it to a message. It"
  print plain "     holds the web server's log, its settings and this machine's disk"
  print plain "     and version details. Passwords have been removed from it."
else
  print info  "Written to ${REPORT}"
  print plain "     Send that file when asking for help. Passwords have been removed from it."
fi

exit 0
