#!/bin/bash

# Point Apache's default host (000-default.conf) at an InteLIS installation.
#
# Run it either way. The ?v= cache buster matters: raw.githubusercontent serves a
# stale copy for a few minutes after a push, so without it you can easily run the
# previous version of this script. Keep the URL quoted so the shell doesn't treat
# the ? as a glob.
#   curl -fsSL "https://raw.githubusercontent.com/deforay/intelis/master/scripts/default-host-setup.sh?v=$(date +%s)" | sudo bash -s -- -p /var/www/intelis -n vlsm
#   wget -O intelis-host-setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/default-host-setup.sh?v=$(date +%s)" && sudo bash intelis-host-setup.sh
#
# Options:
#   -p PATH   InteLIS installation path (default: /var/www/intelis)
#   -n NAME   ServerName for the vhost, e.g. vlsm or intelis (default: intelis)
#   -y        Never prompt; take the defaults for anything not passed
#   -h        Show this help
#
# Options are optional in a piped run: with no terminal available the script
# takes the defaults instead of hanging on a prompt.

set -o pipefail

# Candidate install paths, tried in order. Older installations live at
# /var/www/vlsm, so fall back to it when /var/www/intelis holds nothing.
DEFAULT_LIS_PATHS=(/var/www/intelis /var/www/vlsm)
DEFAULT_SERVER_NAME="intelis"
# Names the vhost answers to in addition to its ServerName.
STANDARD_ALIASES=(intelis vlsm)

usage() {
    cat <<'USAGE'
Usage: default-host-setup.sh [-p PATH] [-n NAME] [-y]

  -p PATH   InteLIS installation path (default: /var/www/intelis,
            falling back to /var/www/vlsm on older installations)
  -n NAME   ServerName for the vhost, e.g. vlsm or intelis (default: intelis)
  -y        Never prompt; take the defaults for anything not passed
  -h        Show this help
USAGE
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Error: This script must be run as root. Please use sudo."
    exit 1
fi

# Fetch a URL to a file with whichever downloader the host has. A piped run may
# land on a box with only one of curl/wget, and a partial download must never
# overwrite a good copy of shared-functions.sh.
fetch_url() {
    local url=$1 dest=$2 tmp
    tmp="$(mktemp)"
    if command -v curl >/dev/null 2>&1 && curl -fsSL "$url" -o "$tmp"; then
        :
    elif command -v wget >/dev/null 2>&1 && wget -q -O "$tmp" "$url"; then
        :
    else
        rm -f "$tmp"
        return 1
    fi
    [ -s "$tmp" ] || { rm -f "$tmp"; return 1; }
    mv "$tmp" "$dest"
}

# Download and update shared-functions.sh
SHARED_FN_PATH="/usr/local/lib/intelis/shared-functions.sh"
SHARED_FN_URL="https://raw.githubusercontent.com/deforay/intelis/master/scripts/shared-functions.sh"

mkdir -p "$(dirname "$SHARED_FN_PATH")"

# Cache-bust: raw.githubusercontent serves stale copies for a few minutes.
if fetch_url "${SHARED_FN_URL}?v=$(date +%s)" "$SHARED_FN_PATH"; then
    chmod +x "$SHARED_FN_PATH"
    echo "Downloaded shared-functions.sh."
else
    echo "Failed to download shared-functions.sh."
    if [ ! -f "$SHARED_FN_PATH" ]; then
        echo "shared-functions.sh missing. Cannot proceed."
        exit 1
    fi
    echo "Using the existing copy at ${SHARED_FN_PATH}."
fi

# Source the shared functions
source "$SHARED_FN_PATH"

# Set up log file (log_action falls back to a dated file if this is unset)
log_file="/tmp/intelis-host-setup-$(date +'%Y%m%d-%H%M%S').log"
log_action "Starting default host setup"

# Parse command-line options. Everything that can fail on operator input is
# resolved before prepare_system, so a bad path doesn't cost an apt run first.
lis_path=""
server_name=""
assume_yes=0
while getopts ":p:n:yh" opt; do
    case $opt in
        p) lis_path="$OPTARG" ;;
        n) server_name="$OPTARG" ;;
        y) assume_yes=1 ;;
        h) usage; exit 0 ;;
        :) print error "Option -$OPTARG requires an argument."; exit 1 ;;
        \?) print error "Unknown option: -$OPTARG"; usage; exit 1 ;;
    esac
done

# Ask on the terminal rather than on stdin, so prompting still works under
# `curl ... | bash` (where stdin is the script itself). With no terminal — cron,
# CI, a piped run on a headless box — fall back to the default and carry on.
prompt_with_default() {
    local message=$1 fallback=$2 answer=""
    if [ "$assume_yes" -eq 0 ] && [ -r /dev/tty ]; then
        print info "$message [press enter for ${fallback}]: " >&2
        read -r -t 60 answer </dev/tty || answer=""
    fi
    printf '%s' "${answer:-$fallback}"
}

# Pick the default install path: the first candidate that actually holds an
# installation, so a box that predates the intelis rename still resolves. Prefer
# a real installation over a bare directory — an empty /var/www/intelis left
# behind by a half-finished setup must not win over a working /var/www/vlsm.
detect_default_lis_path() {
    local candidate
    for candidate in "${DEFAULT_LIS_PATHS[@]}"; do
        is_valid_application_path "$candidate" && { printf '%s' "$candidate"; return; }
    done
    for candidate in "${DEFAULT_LIS_PATHS[@]}"; do
        [ -d "$candidate" ] && { printf '%s' "$candidate"; return; }
    done
    printf '%s' "${DEFAULT_LIS_PATHS[0]}"
}

[ -n "$lis_path" ] || lis_path=$(prompt_with_default "Enter the LIS installation path" "$(detect_default_lis_path)")
[ -n "$server_name" ] || server_name=$(prompt_with_default "Enter the ServerName for this site" "$DEFAULT_SERVER_NAME")

# A ServerName lands in both an Apache directive and /etc/hosts, so reject
# anything that isn't a plain hostname before it reaches either file.
if ! [[ $server_name =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]]; then
    print error "Invalid ServerName: '${server_name}'. Use letters, digits, dots and hyphens."
    log_action "Invalid ServerName specified: $server_name"
    exit 1
fi

# Convert to absolute path using function from shared_functions.sh
lis_path=$(to_absolute_path "$lis_path")

# Check if the LIS path is valid using function from shared_functions.sh
if ! is_valid_application_path "$lis_path"; then
    print error "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
    log_action "Invalid LIS path specified: $lis_path"
    exit 1
fi

print info "Using LIS installation path: $lis_path"
print info "Using ServerName: $server_name"
log_action "Using LIS path: $lis_path, ServerName: $server_name"

# Prepare the system
prepare_system

if ! command -v apache2ctl >/dev/null 2>&1; then
    print error "Apache is not installed on this host. Install it (or run setup.sh) first."
    log_action "Apache not installed; aborting"
    exit 1
fi

print header "Configuring Apache Virtual Host"

# Serve the chosen name plus the standard ones, so http://vlsm/ and
# http://intelis/ both reach the site whichever was picked as ServerName.
server_aliases=()
for alias in "${STANDARD_ALIASES[@]}"; do
    [ "$alias" = "$server_name" ] || server_aliases+=("$alias")
done

alias_directives=""
for alias in "${server_aliases[@]}"; do
    alias_directives+="    ServerAlias ${alias}"$'\n'
done

vhost_file="/etc/apache2/sites-available/000-default.conf"

# Only snapshot the pristine original once; a re-run must not overwrite the
# backup with a vhost this script already rewrote.
if [ -f "$vhost_file" ] && [ ! -f "${vhost_file}.bak" ]; then
    cp "$vhost_file" "${vhost_file}.bak"
    print info "Backed up original Apache configuration to ${vhost_file}.bak"
    log_action "Backed up original Apache configuration"
fi

# Write a canonical default vhost rather than editing the existing one in place.
# This script owns the default host, so a known-good file is both simpler and
# idempotent — no DocumentRoot/Directory surgery, no stale blocks left behind.
# 000-default.conf stays the filename on purpose: it sorts first in
# sites-enabled, which is what makes this the default host.
if write_if_different "$vhost_file" <<EOF; then
<VirtualHost *:80>
    ServerName ${server_name}
${alias_directives}    DocumentRoot ${lis_path}/public

    <Directory ${lis_path}/public>
        AddDefaultCharset UTF-8
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF
    apache_changed=1
    print info "Default host now serves ${lis_path}/public as ${server_name}"
    log_action "Wrote default vhost for ${lis_path} as ${server_name}"
else
    apache_changed=0
    print info "Default host already serves ${lis_path}/public as ${server_name}"
fi

# Resolve the vhost's names locally so they work from the machine itself
for name in "$server_name" "${server_aliases[@]}"; do
    if grep -qE "^[[:space:]]*127\.0\.0\.1[[:space:]]+${name}([[:space:]]|\$)" /etc/hosts; then
        continue
    fi
    echo "127.0.0.1 ${name}" >>/etc/hosts
    print info "Added ${name} to /etc/hosts"
    log_action "Added ${name} to hosts file"
done

# Enable the Apache modules the application relies on
for module in rewrite headers deflate; do
    if command -v a2query >/dev/null 2>&1 && a2query -m "$module" >/dev/null 2>&1; then
        continue
    fi
    a2enmod "$module" >/dev/null && apache_changed=1
    print info "Enabled Apache module: $module"
    log_action "Enabled Apache module: $module"
done

# Make sure the site is actually enabled — a2dissite may have been run by hand.
if [ ! -e /etc/apache2/sites-enabled/000-default.conf ]; then
    a2ensite 000-default.conf >/dev/null && apache_changed=1
    print info "Enabled the default site"
    log_action "Enabled the default site"
fi

# Requests that arrive by bare IP carry no matching Host header, so Apache hands
# them to the default vhost: the first one it loads, i.e. the alphabetically
# first file in sites-enabled. 000-default.conf normally wins that, which is why
# this script keeps the name. Warn if another site sorts ahead of it and would
# swallow IP traffic instead.
first_site=$(LC_ALL=C ls /etc/apache2/sites-enabled/*.conf 2>/dev/null | head -n 1)
if [ -n "$first_site" ] && [ "$(basename "$first_site")" != "000-default.conf" ]; then
    print warning "$(basename "$first_site") loads before 000-default.conf and will answer requests made by IP address."
    print warning "Rename or disable it if this site should be the one reachable at http://$(hostname -I | awk '{print $1}')/"
    log_action "Default-host conflict: $(basename "$first_site") sorts before 000-default.conf"
fi

restore_vhost_and_exit() {
    print error "$1"
    log_action "$1"
    if [ -f "${vhost_file}.bak" ]; then
        cp "${vhost_file}.bak" "$vhost_file"
        print warning "Restored original Apache configuration"
        log_action "Restored original Apache configuration"
        restart_service apache
    fi
    exit 1
}

if [ "$apache_changed" -eq 0 ]; then
    print info "Apache configuration unchanged; skipping restart"
else
    # Validate before bouncing Apache — a failed config test leaves the running
    # server untouched, whereas a restart on a bad config takes the site down.
    if ! apache2ctl -t; then
        restore_vhost_and_exit "Apache configuration test failed."
    fi

    if ! restart_service apache; then
        restore_vhost_and_exit "Failed to restart Apache. Please check the configuration."
    fi

    print success "Apache configuration updated successfully"
    log_action "Apache configuration updated successfully"
fi

# Set proper permissions on LIS path
print header "Setting Permissions"
set_permissions "$lis_path" "quick"
print success "Permissions set for $lis_path"
log_action "Permissions set for $lis_path"

print header "Setup Complete"
print success "Default host configuration completed successfully"
log_action "Default host configuration completed successfully"

# Final message
print info "Your LIS application is now accessible at:"
print info "  http://${server_name}/"
print info "  http://$(hostname -I | awk '{print $1}')/"
print info "Log file: $log_file"
