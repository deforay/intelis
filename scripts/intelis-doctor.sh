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

trap 'echo "intelis doctor stopped unexpectedly at ${BASH_SOURCE[0]:-intelis-doctor.sh} line $LINENO (status $?). Please report this."' ERR

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

# Every check below assumes an Ubuntu lab server (apt, systemd, Apache), and
# shared-functions.sh needs bash 4 for associative arrays. Anywhere else, such as
# a Mac with bash 3.2, sourcing it dies on `declare -A` before a word is said.
if [ "$(uname -s)" != Linux ] || [ "${BASH_VERSINFO[0]}" -lt 4 ]; then
  echo "intelis doctor checks Linux servers only, and this machine is $(uname -s) with bash ${BASH_VERSION}."
  exit 1
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
# ref labs follow at source time with an untimed `git ls-remote`, and nothing
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

# --check promises to change nothing, and shared-functions.sh is sourced by every
# InteLIS maintenance script -- refreshing it here would quietly re-version all of
# them during a report-only run. In check mode fetch to a throwaway copy instead,
# and fall back to whatever is already installed if that fails.
if [ "$MODE" = check ]; then
  SHARED_FN_TMP="$(mktemp 2>/dev/null || true)"
  if [ -n "${SHARED_FN_TMP:-}" ] && fetch_shared_fn "$SHARED_FN_TMP" "${RAW_BASE}/shared-functions.sh"; then
    trap 'rm -f "$SHARED_FN_TMP"' EXIT
    SHARED_FN_PATH="$SHARED_FN_TMP"
  elif [ -n "${SHARED_FN_TMP:-}" ]; then
    rm -f "$SHARED_FN_TMP"
  fi
else
  fetch_shared_fn "$SHARED_FN_PATH" "${RAW_BASE}/shared-functions.sh" || true
fi

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

# --- output -------------------------------------------------------------------
#
# One layout for both doctors, because the database doctor runs inside this
# one: a verdict card, a checklist, then the repairs. The card is drawn by hand
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
    print step "Can be repaired: ${description}"
    return 1
  fi

  print step "${description}"
  if [ "$MODE" = ask ] && ! ask_yes_no "     Do this now?" no; then
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

# What InteLIS cannot run without, as extension:package, where the package is
# apt's php<version>-<package>. "-" means the extension is built into PHP itself,
# so there is nothing to install when it is absent. The list is every ext-*
# required by composer.json and the production packages in composer.lock, plus
# the MySQL drivers. Keep it in step with both.
REQUIRED_EXTENSIONS=(
  mysqli:mysql pdo:common pdo_mysql:mysql mbstring:mbstring curl:curl gd:gd
  intl:intl zip:zip bcmath:bcmath xml:xml dom:xml simplexml:xml xmlreader:xml
  xmlwriter:xml gettext:common fileinfo:common sodium:common ctype:common
  iconv:common openssl:- json:- zlib:- filter:- hash:- libxml:-
)

active_mpm() {
  local f
  for f in /etc/apache2/mods-enabled/mpm_*.load; do
    [ -e "$f" ] || continue
    basename "$f" .load
    return 0
  done
  return 1
}

# Taking the FIRST DocumentRoot in sites-enabled picks whatever sorts first --
# usually 000-default.conf. On a box that also serves an unrelated default site
# that is the wrong vhost entirely, so the doctor probed a site it was never
# asked about. Prefer the vhost pointing at this install.
apache_doc_root() {
  local roots root
  roots="$(grep -rhi '^[[:space:]]*DocumentRoot' /etc/apache2/sites-enabled/ 2>/dev/null |
           awk '{print $2}' | tr -d '"'"'" || true)"
  [ -n "$roots" ] || return 1

  if [ -n "${INSTALL_PATH:-}" ]; then
    root="$(printf '%s\n' "$roots" | grep -Fx "${INSTALL_PATH}/public" | head -n1 || true)"
    [ -n "$root" ] && { echo "$root"; return 0; }
    root="$(printf '%s\n' "$roots" | grep -F "$INSTALL_PATH" | head -n1 || true)"
    [ -n "$root" ] && { echo "$root"; return 0; }
  fi

  root="$(printf '%s\n' "$roots" | head -n1)"
  [ -n "$root" ] && { echo "$root"; return 0; }
  return 1
}

# The conf file whose DocumentRoot is $1, and the ServerName it declares. Needed
# because requests to 127.0.0.1 land on the DEFAULT vhost: without a matching
# Host header, a probe file written into the InteLIS docroot is fetched from
# some other site, which reads as a broken InteLIS when it is nothing of the kind.
apache_vhost_file_for_root() {
  local root="$1" f
  [ -n "$root" ] || return 1
  for f in /etc/apache2/sites-enabled/*; do
    [ -f "$f" ] || continue
    if grep -hi '^[[:space:]]*DocumentRoot' "$f" 2>/dev/null |
         awk '{print $2}' | tr -d '"'"'" | grep -qFx "$root"; then
      echo "$f"; return 0
    fi
  done
  return 1
}

apache_server_name() {
  local f name
  f="$(apache_vhost_file_for_root "$1" || true)"
  [ -n "$f" ] || return 1
  name="$(grep -i '^[[:space:]]*ServerName' "$f" 2>/dev/null |
          head -n1 | awk '{print $2}' | tr -d '"'"'" || true)"
  [ -n "$name" ] && { echo "$name"; return 0; }
  return 1
}

port_80_owner() {
  ss -lntpH 2>/dev/null | awk '$4 ~ /:80$/ {print $NF; exit}' |
    grep -oE 'users:\(\("[^"]+' | sed 's/.*"//' || true
}

# curl is not on every Ubuntu server install and wget is not on every desktop
# one, so neither may be assumed.
http_get() { # http_get <url> [host] -> body on stdout
  local url="$1" host="${2:-}"
  if command -v curl >/dev/null 2>&1; then
    if [ -n "$host" ]; then
      curl -sS --max-time 15 -H "Host: ${host}" -o - "$url" 2>/dev/null
    else
      curl -sS --max-time 15 -o - "$url" 2>/dev/null
    fi
  elif command -v wget >/dev/null 2>&1; then
    if [ -n "$host" ]; then
      wget -qO- --timeout=15 --header="Host: ${host}" "$url" 2>/dev/null
    else
      wget -qO- --timeout=15 "$url" 2>/dev/null
    fi
  else
    return 2
  fi
}

http_status() { # http_status <url> [host] -> three-digit code, or empty
  local url="$1" host="${2:-}"
  if command -v curl >/dev/null 2>&1; then
    if [ -n "$host" ]; then
      curl -sS -o /dev/null -w '%{http_code}' --max-time 15 -H "Host: ${host}" "$url" 2>/dev/null
    else
      curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$url" 2>/dev/null
    fi
  elif command -v wget >/dev/null 2>&1; then
    # wget has no --write-out. With -S it prints the status line to stderr, and
    # it exits non-zero on 4xx/5xx -- piping discards that exit status, which is
    # what we want, since an error code is the answer here rather than a failure.
    # Without this branch a wget-only machine got an empty status, so InteLIS
    # returning HTTP 500 read exactly like a healthy site.
    if [ -n "$host" ]; then
      wget -S -O /dev/null --timeout=15 --header="Host: ${host}" "$url" 2>&1
    else
      wget -S -O /dev/null --timeout=15 "$url" 2>&1
    fi | awk '/^[[:space:]]*HTTP\//{code=$2} END{if (code) print code}'
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
    # MariaDB installs mysql.service as an alias of mariadb.service. The journal
    # files entries under the real name only, so an alias reads as an empty log.
    if systemctl list-unit-files "${unit}.service" 2>/dev/null |
       awk -v u="${unit}.service" '$1 == u && $2 != "alias" { found = 1 } END { exit !found }'; then
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

$QUIET || printf '\n%sInteLIS doctor%s  ·  %s  ·  %s\n' "$C_BOLD" "$C_OFF" "$(hostname 2>/dev/null || echo this machine)" "$(date '+%d %b %Y %H:%M')"

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


# --- checks -------------------------------------------------------------------

# The one test that settles the argument. Everything else below explains this
# answer; none of it replaces it.
#
# The probe also says which PHP the web server is running and which required
# extensions it failed to load. Asking the CLI would not do: Apache has its own
# conf.d, and after a PHP update its workers can be running the old core, which
# then cannot load the new extension files. Either way PHP itself runs, the
# site fails, and only the web server's own answer shows why.
PROBE_RESULT=unknown
PROBE_STATUS=""
PROBE_PHP_VERSION=""
PROBE_MISSING=""
check_probe() {
  local probe body line exts=() entry

  PROBE_PHP_VERSION=""
  PROBE_MISSING=""

  if [ -z "$DOC_ROOT" ] || [ ! -d "$DOC_ROOT" ]; then
    PROBE_RESULT=no-docroot
    return
  fi

  for entry in "${REQUIRED_EXTENSIONS[@]}"; do exts+=("'${entry%%:*}'"); done

  probe="${DOC_ROOT}/.intelis-doctor-probe.php"
  # Never follow a symlink left at that name, and never leave the file behind.
  [ -L "$probe" ] && { PROBE_RESULT=unknown; return; }
  printf '<?php $m = []; foreach ([%s] as $e) { if (!extension_loaded($e)) { $m[] = $e; } } echo "INTELIS_PHP_OK|", PHP_VERSION, "|", implode(",", $m), "|";' \
    "$(IFS=,; printf '%s' "${exts[*]}")" >"$probe" 2>/dev/null || { PROBE_RESULT=unwritable; return; }
  chmod 644 "$probe" 2>/dev/null || true

  # 127.0.0.1 reaches the DEFAULT vhost. Send the InteLIS vhost's ServerName so
  # the probe is answered by the site being diagnosed rather than by whatever
  # else this machine happens to serve.
  local host
  host="$(apache_server_name "$DOC_ROOT" || true)"
  report "probe host=${host:-<default vhost>}"

  body="$(http_get "http://127.0.0.1/.intelis-doctor-probe.php" "$host" || true)"
  rm -f -- "$probe" 2>/dev/null || true

  report_cmd "probe response" printf '%s\n' "$body"

  if [ -z "$body" ]; then
    PROBE_RESULT=unreachable
  elif printf '%s' "$body" | grep -q 'INTELIS_PHP_OK'; then
    PROBE_RESULT=php-runs
    line="$(printf '%s' "$body" | grep -o 'INTELIS_PHP_OK|[^|]*|[^|]*|' | head -n 1 || true)"
    PROBE_PHP_VERSION="$(printf '%s' "$line" | cut -d'|' -f2)"
    PROBE_MISSING="$(printf '%s' "$line" | cut -d'|' -f3)"
    report "probe php=${PROBE_PHP_VERSION:-unknown} missing=${PROBE_MISSING:-none}"
  elif printf '%s' "$body" | grep -q '<?php'; then
    PROBE_RESULT=source-served
  else
    PROBE_RESULT=other
  fi

  PROBE_STATUS="$(http_status "http://127.0.0.1/" "$host" || true)"
  report "site status=${PROBE_STATUS:-unknown}"
}

check_php_handler() {
  local mods
  mods="$(apache2ctl -M 2>/dev/null || true)"

  if printf '%s' "$mods" | grep -q 'php[0-9._]*_module'; then
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
      "The site is still showing the update page." \
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

  if ! sudo -n -u "$WEB_USER" test -r "${INSTALL_PATH}/public/index.php" </dev/null 2>/dev/null; then
    finding critical permissions \
      "The web server is not allowed to read the site's files." \
      "${WEB_USER} cannot read ${INSTALL_PATH}/public/index.php."
    return
  fi

  for dir in var public/uploads; do
    [ -d "${INSTALL_PATH}/${dir}" ] || continue
    if ! sudo -n -u "$WEB_USER" test -w "${INSTALL_PATH}/${dir}" </dev/null 2>/dev/null; then
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

# Runs on the probe's answer, so only once PHP is known to run at all. A web
# server still on the PHP it started with, after the packages under it were
# updated, is the one case where a restart is the whole repair; apt does not
# restart Apache, and mod_php workers spawned afterwards fail to load the new
# extension files.
php_fault() { has_finding stale || has_finding extensions || has_finding builtin; }

check_php_extensions() {
  local installed shown entry ext builtin="" installable=""
  # -n skips every ini file, so a broken conf.d cannot print a startup warning
  # into the version and make it look like an update.
  installed="$("/usr/bin/php${TARGET_PHP}" -n -r 'echo PHP_VERSION;' 2>/dev/null || true)"
  report "installed php=${installed:-unknown}"

  if [ -n "$installed" ] && [ -n "$PROBE_PHP_VERSION" ] && [ "$installed" != "$PROBE_PHP_VERSION" ]; then
    finding critical stale \
      "PHP was updated, but the web server is still running the old copy." \
      "The web server runs PHP ${PROBE_PHP_VERSION} and the installed PHP is ${installed}. Until it restarts, parts of PHP fail to load and pages stop working at random. A restart is all it needs."
    return
  fi

  [ -n "$PROBE_MISSING" ] || return 0

  # An extension built into PHP has no package, so installing cannot bring it
  # back. Offering that repair would only end in "That did not work".
  for entry in "${REQUIRED_EXTENSIONS[@]}"; do
    ext="${entry%%:*}"
    case ",${PROBE_MISSING}," in *",${ext},"*) ;; *) continue ;; esac
    if [ "${entry##*:}" = "-" ]; then
      builtin="${builtin:+${builtin}, }${ext}"
    else
      installable="${installable:+${installable}, }${ext}"
    fi
  done
  if [ -n "$builtin" ]; then
    finding critical builtin \
      "This PHP was built without parts InteLIS needs." \
      "The web server's PHP ${PROBE_PHP_VERSION} has no ${builtin}. These are part of PHP itself, so the PHP packages have to be reinstalled by a person."
  fi
  [ -n "$installable" ] || return 0

  shown="The web server's PHP ${PROBE_PHP_VERSION} has not loaded: ${installable}."
  case ",${PROBE_MISSING}," in
    *,mysqli,*) shown="${shown} Without mysqli, neither InteLIS nor phpMyAdmin can reach the database, however healthy the database is." ;;
  esac
  finding critical extensions \
    "PHP is missing parts InteLIS needs." \
    "$shown"
}

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

# The database is somebody else's script to diagnose. All this decides is
# whether to go and get it.
# --- updates ------------------------------------------------------------------
#
# Labs follow the stable branch, and a release is a commit: the version number
# moves only with schema changes, so two installs can share one and differ by
# weeks of fixes. The commit in VERSION.txt is compared with the tip of stable.
# People see a version and dates. The hashes go to the report only.

UPDATE_STATE=unknown   # current | behind | ahead | diverged | differs | offline | unstamped
UPDATE_BEHIND_BY=""
INSTALLED_SHA=""
STABLE_SHA=""
INSTALLED_VERSION=""
INSTALLED_DATE=""
STABLE_DATE=""

check_update() {
  local cmp parsed
  [ -n "$INSTALL_PATH" ] || { UPDATE_STATE=unstamped; return 0; }
  INSTALLED_VERSION="$(php -r '$j = json_decode((string) @file_get_contents($argv[1]), true); echo $j["version"] ?? "";' \
    "${INSTALL_PATH}/composer.json" 2>/dev/null || true)"

  INSTALLED_SHA="$(head -n 1 "${INSTALL_PATH}/VERSION.txt" 2>/dev/null | tr -d '\r\n ' || true)"
  [[ "$INSTALLED_SHA" =~ ^[0-9a-f]{40}$ ]] || { UPDATE_STATE=unstamped; return 0; }

  # Anonymous and never interactive, as resolve_intelis_ref does it: a stored
  # credential that has expired would otherwise prompt on the terminal.
  if command -v git >/dev/null 2>&1; then
    STABLE_SHA="$(GIT_TERMINAL_PROMPT=0 timeout -k 2 8 git -c credential.helper= \
      ls-remote https://github.com/deforay/intelis.git refs/heads/stable 2>/dev/null | awk '{print $1; exit}' || true)"
  fi
  [[ "$STABLE_SHA" =~ ^[0-9a-f]{40}$ ]] || { UPDATE_STATE=offline; return 0; }

  # Different is not behind: a lab hotfixed from master is ahead of stable.
  # GitHub's compare says which, how far, and the dates people can read.
  # Without it the direction is unknown, and nothing is offered: updating a
  # hotfixed lab to stable would remove the hotfix.
  cmp="$(http_get "https://api.github.com/repos/deforay/intelis/compare/${INSTALLED_SHA}...${STABLE_SHA}" || true)"
  parsed="$(printf '%s' "$cmp" | php -r '
    $j = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($j) || !isset($j["status"])) { exit; }
    $day = static fn ($d) => $d ? date("j M Y", strtotime($d)) : "";
    $commits = $j["commits"] ?? [];
    $last = $commits ? end($commits) : null;
    echo $j["status"], "|", $j["ahead_by"] ?? "", "|",
      $day($j["base_commit"]["commit"]["committer"]["date"] ?? ""), "|",
      $day($last["commit"]["committer"]["date"] ?? ""), "|";
  ' 2>/dev/null || true)"

  if [ "$INSTALLED_SHA" = "$STABLE_SHA" ]; then
    UPDATE_STATE=current
  else
    case "$(printf '%s' "$parsed" | cut -d'|' -f1)" in
      ahead) UPDATE_STATE=behind ;;
      # Both sides have commits the other lacks: a hotfix taken before stable
      # moved on. An update would drop the hotfix, so that is a person's call.
      diverged) UPDATE_STATE=diverged ;;
      behind) UPDATE_STATE=ahead ;;
      identical) UPDATE_STATE=current ;;
      *) UPDATE_STATE=differs ;;
    esac
  fi
  UPDATE_BEHIND_BY="$(printf '%s' "$parsed" | cut -d'|' -f2)"
  INSTALLED_DATE="$(printf '%s' "$parsed" | cut -d'|' -f3)"
  STABLE_DATE="$(printf '%s' "$parsed" | cut -d'|' -f4)"
  report "update: state=${UPDATE_STATE} installed=${INSTALLED_SHA} (${INSTALLED_VERSION:-?}, ${INSTALLED_DATE:-?}) stable=${STABLE_SHA} (${STABLE_DATE:-?}) behind_by=${UPDATE_BEHIND_BY:-?}"
}

has_dispatcher() {
  local cmd
  cmd="$(command -v intelis 2>/dev/null || true)"
  [ -n "$cmd" ] && grep -q '^cmd_update()' "$cmd" 2>/dev/null
}

# What to tell a person to type. Where `intelis` is still the Composer wrapper,
# `intelis update` would run composer, so the commands are brought up to date
# first, as the update guide does it.
print_update_advice() {
  if has_dispatcher; then
    print info "To install it, run: intelis update"
  else
    print info "To install it, first bring the InteLIS commands up to date:"
    printf '       sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"\n'
    print plain "     Then run: intelis update"
  fi
}

update_row() {
  local v="${INSTALLED_VERSION:-version unknown}"
  case "$UPDATE_STATE" in
    current) row ok "InteLIS" "${v}, up to date" ;;
    ahead)   row ok "InteLIS" "${v}, newer than the current release" ;;
    behind)
      if [ -n "$STABLE_DATE" ] && [ "$INSTALLED_DATE" = "$STABLE_DATE" ]; then
        row warn "InteLIS" "${v}, a newer update from ${STABLE_DATE} is available"
      elif [ -n "$INSTALLED_DATE" ] && [ -n "$STABLE_DATE" ]; then
        row warn "InteLIS" "${v} from ${INSTALLED_DATE}, an update from ${STABLE_DATE} is available"
      else
        row warn "InteLIS" "${v}, an update is available"
      fi ;;
    diverged) row warn "InteLIS" "${v}, has changes the current release does not, so it is not updated here" ;;
    differs) row skip "InteLIS" "${v}, could not compare with the current release" ;;
    offline) row skip "InteLIS" "${v}, update check needs an internet connection" ;;
    *)       row skip "InteLIS" "${v}, update status not recorded" ;;
  esac
}

DB_STATE=unknown
check_database() {
  if ! systemctl is-active --quiet "$DB_UNIT" 2>/dev/null; then
    DB_STATE=stopped
    finding critical database \
      "The database is not running." \
      "InteLIS cannot show a single page without it, however healthy the web server is."
    return
  fi
  # systemd says active for a server that is running and no longer answering.
  # mysqladmin ping answers for a live server even with wrong credentials, so a
  # failure here means the server itself is stuck.
  if command -v mysqladmin >/dev/null 2>&1 && ! db_answers; then
    DB_STATE=hung
    finding critical database \
      "The database is running but not answering." \
      "It needs restarting. InteLIS cannot show a single page until it answers."
    return
  fi
  DB_STATE=running
}

# The database doctor answers the database finding further down, so on its own
# that finding leaves nothing to list under the repairs.
only_database_finding() { [ ${#FINDING_KEYS[@]} -eq 1 ] && has_finding database; }

check_config_valid
check_foreign_server
check_php_packages
check_php_handler
check_maintenance_marker
check_permissions
check_disk
check_database
$APACHE_UP && check_probe
check_update

if ! $APACHE_UP && ! has_finding config; then
  finding critical start \
    "The web server is installed but not running." \
    "Nothing is listening for the browser to connect to."
fi

case "$PROBE_RESULT" in
  source-served)
    # apache2ctl -M reads the configuration on disk, not what the running server
    # actually loaded. A server that was never restarted after the configuration
    # was corrected passes every check above and still serves source — which is
    # the state a real lab was left in after switch-php had already done its job.
    # The probe is the only thing that can tell the two apart.
    if ! has_finding handler && ! has_finding ppa; then
      finding critical stale \
        "Apache is set up correctly but has not picked it up yet." \
        "The configuration on disk is right and the running server is still working from the old one. It only needs restarting."
    fi
    ;;
  php-runs)
    check_php_extensions
    # PHP works and the site still fails: that is the application's own error,
    # and on these machines it is the database far more often than not. Unless
    # PHP cannot reach it at all, in which case the database is not the fault
    # and its doctor would only report that it is healthy.
    if [ -n "$PROBE_STATUS" ] && [ "$PROBE_STATUS" -ge 500 ] 2>/dev/null && ! has_finding database && ! php_fault; then
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

render_checks() {
  local free
  if has_finding config; then row bad "Web server" "its configuration has an error"
  elif has_finding foreign; then row bad "Web server" "another program holds port 80"
  elif $APACHE_UP; then row ok "Web server" "running"
  else row bad "Web server" "stopped"
  fi

  if has_finding ppa || has_finding handler; then row bad "PHP" "the web server cannot run it"
  elif [ "$PROBE_RESULT" = source-served ]; then row bad "PHP" "shown as code instead of run"
  elif has_finding stale; then row bad "PHP" "updated, the web server still runs the old copy"
  elif [ -n "$PROBE_MISSING" ]; then row bad "PHP" "missing ${PROBE_MISSING//,/, }"
  elif [ "$PROBE_RESULT" = php-runs ]; then row ok "PHP" "${PROBE_PHP_VERSION}, all ${#REQUIRED_EXTENSIONS[@]} extensions loaded"
  else row skip "PHP" "not checked"
  fi

  if has_finding permissions; then row bad "Files" "the web server cannot use them"
  elif has_finding maintenance; then row warn "Files" "the update page is still on"
  elif has_finding docroot; then row warn "Files" "the web server is not pointed at them"
  elif [ -z "$INSTALL_PATH" ]; then row warn "Files" "no InteLIS install under /var/www"
  else row ok "Files" "readable and writable"
  fi

  free="$(df -h /var 2>/dev/null | awk 'NR==2 {print $4}' || true)"
  if has_finding disk; then row bad "Disk" "only ${free:-?} free"
  else row ok "Disk" "${free:-?} free"
  fi

  case "$DB_STATE" in
    running) row ok  "Database" "running" ;;
    stopped) row bad "Database" "stopped" ;;
    hung)    row bad "Database" "running, not answering" ;;
    *)       row skip "Database" "not checked" ;;
  esac

  if [ -z "$PROBE_STATUS" ] || [ "$PROBE_STATUS" = 000 ]; then
    if $APACHE_UP; then row bad "Site" "no answer"; else row skip "Site" "not checked"; fi
  elif [ "$PROBE_STATUS" -ge 500 ] 2>/dev/null; then row bad "Site" "error ${PROBE_STATUS}"
  else row ok "Site" "opens"
  fi

  update_row
}

report_findings

if ! $QUIET || [ ${#FINDING_TITLES[@]} -gt 0 ]; then
  if [ ${#FINDING_TITLES[@]} -eq 0 ]; then
    if [ "$PROBE_RESULT" = php-runs ]; then
      card ok "InteLIS is working." "The web server, PHP and the database all answered."
      report "VERDICT: healthy"
    else
      card warn "The site did not answer as expected." \
        "None of the known causes matched. Send the report below when asking for help."
      report "VERDICT: no known cause matched"
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
  if [ ${#FINDING_TITLES[@]} -eq 0 ] && [ "$UPDATE_STATE" = behind ]; then
    printf '\n'
    print info "An update is available."
    print_update_advice
  fi
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

# Install what is absent, then switch each module on for every SAPI. The switch
# is the half that is usually lost: a module's mods-available/<name>.ini is
# created through ucf, which remembers a deletion and will not recreate it on a
# reinstall, so it is copied back from the package's own template. Built-in
# extensions have no package, and a PHP missing one of those is not something
# installing can repair.
fix_php_extensions() {
  local entry ext pkg ini template pkgs=() modules=()

  for entry in "${REQUIRED_EXTENSIONS[@]}"; do
    ext="${entry%%:*}"; pkg="${entry##*:}"
    case ",${PROBE_MISSING}," in *",${ext},"*) ;; *) continue ;; esac
    [ "$pkg" = "-" ] && continue
    pkgs+=("php${TARGET_PHP}-${pkg}")
    modules+=("$ext")
  done
  [ ${#pkgs[@]} -gt 0 ] || return 1

  spin "Installing the missing parts of PHP..." \
    apt-get install -y "${pkgs[@]}" >/dev/null 2>&1 || return 1

  for ext in "${modules[@]}"; do
    ini="/etc/php/${TARGET_PHP}/mods-available/${ext}.ini"
    if [ ! -f "$ini" ]; then
      template="$(find /usr/share/php${TARGET_PHP}-* -name "${ext}.ini" -type f 2>/dev/null | head -n 1 || true)"
      [ -n "$template" ] && cp "$template" "$ini" 2>/dev/null
    fi
    [ -f "$ini" ] && phpenmod -v "$TARGET_PHP" -s ALL "$ext" >/dev/null 2>&1
  done

  apache2ctl -t >/dev/null 2>&1 || return 1
  systemctl restart apache2 >/dev/null 2>&1 || return 1
  sleep 2
  systemctl is-active --quiet apache2
}

# Deliberately a restart and not a reload: a reload keeps existing worker
# processes, and those are the ones running without PHP.
fix_stale() {
  systemctl restart apache2 >/dev/null 2>&1 || return 1
  sleep 2
  systemctl is-active --quiet apache2
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
DB_DOCTOR_RC=0

if [ ${#FINDING_TITLES[@]} -gt 0 ] && ! only_database_finding; then
  print header "Repairs"

  has_finding maintenance && { run_fix "Take the site out of update mode." fix_maintenance || true; }
  has_finding stale       && { run_fix "Restart the web server so it picks up its settings." fix_stale || true; }
  has_finding ppa         && { run_fix "Restore the PHP repository so the packages can be installed." fix_add_ppa || true; }
  has_finding handler     && { run_fix "Make Apache able to run PHP again." fix_php_handler || true; }
  has_finding extensions  && { run_fix "Install and switch on the parts of PHP that are missing, then restart the web server." fix_php_extensions || true; }
  has_finding permissions && { run_fix "Give the web server access to the folders it needs." fix_permissions || true; }
  has_finding start       && { run_fix "Start the web server." fix_start_apache || true; }

  if has_finding foreign; then
    print warning "Stopping another program that holds port 80 is a decision for a person."
    print plain   "     If it is a web server that was installed by mistake, it is safe to stop."
    print plain   "     If it was installed on purpose, stopping it will break whatever uses it."
    # That decision cannot be made unattended. run_fix only prompts in ask mode,
    # so under --yes this would stop AND disable whatever holds port 80 -- and
    # `disable` does not come back on reboot. A machine deliberately serving
    # something else on port 80 would be taken offline by a tool the operator
    # ran to diagnose a different problem. Report it and move on instead.
    if [ "$MODE" = yes ]; then
      print warning "Left alone: unattended mode will not stop another program. Re-run without --yes to decide."
      report "SKIPPED (unattended): stop the program holding port 80"
    else
      run_fix "Stop it and let Apache have the address." fix_stop_foreign || true
    fi
  fi

  if has_finding config; then
    print header "Needs a person"
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

  if has_finding builtin; then
    print info  "PHP itself is incomplete, and installing extensions cannot repair that."
    print plain "     Reinstall PHP with: sudo switch-php ${TARGET_PHP} --force"
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

# Only looks. Starting the web server is a repair of its own, offered and
# agreed to above, and a re-check must not do it behind a declined question.
recheck_site() {
  APACHE_UP=false
  systemctl is-active --quiet apache2 2>/dev/null && APACHE_UP=true
  PROBE_RESULT=unknown
  PROBE_STATUS=""
  $APACHE_UP || { report "recheck: web server not running"; return 0; }
  DOC_ROOT="$(apache_doc_root || true)"
  [ -z "$DOC_ROOT" ] && [ -n "$INSTALL_PATH" ] && DOC_ROOT="${INSTALL_PATH}/public"
  check_probe
  report "probe after fixes=${PROBE_RESULT} status=${PROBE_STATUS:-none}"
}

[ ${#FIXES_APPLIED[@]} -gt 0 ] && recheck_site

# --- the database ---------------------------------------------------------------
#
# Run last, and only once the web side is settled: its own findings are far
# easier to read when they are not interleaved with Apache's.

if has_finding database ||
   { [ "$PROBE_RESULT" = php-runs ] && [ -z "$PROBE_MISSING" ] && { [ ${#FIXES_APPLIED[@]} -gt 0 ] || ! php_fault; } &&
     [ -n "$PROBE_STATUS" ] && [ "$PROBE_STATUS" -ge 500 ] 2>/dev/null; }; then
  # One doctor to the person running it: the database half asks its own
  # questions before it changes anything, so there is no question here.
  DB_DOCTOR_RAN=true
  INTELIS_DOCTOR_EMBEDDED=1 run_database_doctor || DB_DOCTOR_RC=$?
  [ "$MODE" = check ] || recheck_site
fi

RECOVERED=false
site_opens() {
  [ "$PROBE_RESULT" = php-runs ] && [ -z "$PROBE_MISSING" ] &&
    [ -n "$PROBE_STATUS" ] && [ "$PROBE_STATUS" != 000 ] && [ "$PROBE_STATUS" -lt 500 ] 2>/dev/null
}

# --- update -------------------------------------------------------------------
#
# Offered only where an update is a plausible fix: the web server, PHP and the
# database all work and InteLIS itself still answers with an error, which is a
# bug or an unfinished migration. With any of those broken the update needs
# what is missing and stops part way, which is how labs end up stranded
# mid-migration. Never unattended: it changes far more than any repair above.

# On older machines `intelis` is still the Composer wrapper, where "update"
# means composer update. Only the dispatcher that has an update command of its
# own is trusted with it, and otherwise the updater is run directly. The
# dispatcher updates the install it is run from, so it runs from this one.
run_update() {
  if has_dispatcher && [ -n "$INSTALL_PATH" ]; then
    ( cd "$INSTALL_PATH" && "$(command -v intelis)" update )
  elif [ -x /usr/local/bin/intelis-update ] && [ -n "$INSTALL_PATH" ]; then
    bash /usr/local/bin/intelis-update -p "$INSTALL_PATH"
  elif [ -n "$INSTALL_PATH" ] && [ -f "${INSTALL_PATH}/scripts/upgrade.sh" ]; then
    bash "${INSTALL_PATH}/scripts/upgrade.sh" -p "$INSTALL_PATH"
  else
    return 1
  fi
}

if [ "$UPDATE_STATE" = behind ] && [ "$MODE" != check ] &&
   [ "$PROBE_RESULT" = php-runs ] && [ -z "$PROBE_MISSING" ] && { [ ${#FIXES_APPLIED[@]} -gt 0 ] || ! php_fault; } &&
   ! has_finding disk &&
   [ -n "$PROBE_STATUS" ] && [ "$PROBE_STATUS" -ge 500 ] 2>/dev/null && db_answers &&
   ! { $DB_DOCTOR_RAN && [ "$DB_DOCTOR_RC" -ne 0 ]; }; then
  # A ping proves the server is alive, not that InteLIS can log in. The database
  # doctor's result says that, and anything it left unresolved comes first.
  print header "Update"
  print_wrapped "The web server, PHP and the database are working, and InteLIS still returns an error. An update often fixes errors inside InteLIS itself."
  if [ "$MODE" = yes ]; then
    print info "Left alone: unattended runs do not install updates."
    print_update_advice
    report "SKIPPED (unattended): update offered"
  else
    FIXES_OFFERED=$((FIXES_OFFERED + 1))
    print step "Update InteLIS to the current release."
    if ask_yes_no "     Do this now?" no; then
      if run_update; then
        FIXES_APPLIED+=("Update InteLIS")
        report "FIX APPLIED: update"
      else
        print error "The update did not finish. Its own messages above say why."
        report "FIX FAILED: update"
      fi
      recheck_site
    else
      print plain "     Left alone."
    fi
  fi
fi



if [ ${#FINDING_TITLES[@]} -gt 0 ]; then
  if [ "$MODE" = check ]; then
    card warn "Nothing was changed." "This run only looked. To let it repair what it can, run: sudo intelis doctor"
  elif [ ${#FIXES_APPLIED[@]} -eq 0 ] && ! $DB_DOCTOR_RAN; then
    card bad "Nothing was repaired." "Send the report below when asking for help."
    report "VERDICT: nothing repaired"
  elif $DB_DOCTOR_RAN && [ "$DB_DOCTOR_RC" -ne 0 ] && db_answers; then
    card bad "The database still needs attention." \
      "The database section above says what is wrong. Send both reports below when asking for help."
    report "VERDICT: database unresolved (${DB_DOCTOR_RC})"
  elif $DB_DOCTOR_RAN && ! db_answers; then
    card bad "The database still will not start." \
      "The database section above says why. Send both reports below when asking for help."
    report "VERDICT: database still down"
  elif site_opens; then
    card ok "InteLIS is working again." "Reload the page in the browser."
    RECOVERED=true
    report "VERDICT: recovered"
  elif [ -n "$PROBE_MISSING" ] && has_finding stale && ! has_finding extensions; then
    # The restart was the only repair on offer, because the old PHP could not be
    # told apart from a missing extension. Now it can.
    card warn "The restart worked. PHP still has not loaded: ${PROBE_MISSING//,/, }" \
      "Run sudo intelis doctor again to repair that."
    report "VERDICT: extensions missing after restart, rerun offered"
  else
    case "$PROBE_RESULT" in
      source-served) card bad "The server still shows PHP code instead of running it." \
                       "Send the report below when asking for help." ;;
      php-runs)      if [ -n "$PROBE_MISSING" ]; then
                       card bad "PHP still has not loaded: ${PROBE_MISSING//,/, }" \
                         "Send the report below when asking for help."
                     else
                       card bad "InteLIS still does not open (error ${PROBE_STATUS:-unknown})." \
                         "Send the report below when asking for help."
                     fi ;;
      *)             card bad "The site still does not answer." \
                       "Send the report below when asking for help." ;;
    esac
    report "VERDICT: unresolved after fixes"
  fi
fi

# The database doctor has just printed its own report path and put its own copy
# in the operator's folder. Printing a second set of instructions on top of that
# is how somebody ends up sending the wrong file.
if $DB_DOCTOR_RAN; then
  printf '\n'
  print info "Site report: ${REPORT}"
  exit 0
fi

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

printf '\n'
# A healthy run has nothing to send, so the instructions for sending stay out.
if [ ${#FINDING_TITLES[@]} -eq 0 ] || $RECOVERED; then
  print info "Report: ${HANDOFF:-$REPORT}"
elif [ -n "$HANDOFF" ]; then
  print info "Report: ${HANDOFF}"
  print_wrapped "Attach this file when asking for help. It is in the Files app, in the home folder or on the Desktop. Passwords have been removed from it."
else
  print info "Report: ${REPORT}"
  print_wrapped "Send this file when asking for help. Passwords have been removed from it."
fi

exit 0
