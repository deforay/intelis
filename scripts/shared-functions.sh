#!/bin/bash
# shared-functions.sh - Common functions for LIS scripts
# Unified print function for colored output
print() {
    local type=$1
    local message=$2
    local header_char="="

    case $type in
    error)
        printf "\033[1;91m❌ Error:\033[0m %s\n" "$message"
        ;;
    success)
        printf "\033[1;92m✅ Success:\033[0m %s\n" "$message"
        ;;
    warning)
        printf "\033[1;93m⚠️ Warning:\033[0m %s\n" "$message"
        ;;
    info)
        printf "\033[1;96mℹ️ Info:\033[0m %s\n" "$message"
        ;;
    debug)
        printf "\033[1;95m🐛 Debug:\033[0m %s\n" "$message"
        ;;
    header)
        local term_width
        term_width=$(tput cols 2>/dev/null || echo 80)
        local msg_length=${#message}
        local padding=$(((term_width - msg_length) / 2))
        ((padding < 0)) && padding=0
        local pad_str
        pad_str=$(printf '%*s' "$padding" '')
        printf "\n\033[1;96m%*s\033[0m\n" "$term_width" '' | tr ' ' "$header_char"
        printf "\033[1;96m%s%s\033[0m\n" "$pad_str" "$message"
        printf "\033[1;96m%*s\033[0m\n\n" "$term_width" '' | tr ' ' "$header_char"
        ;;
    *)
        printf "%s\n" "$message"
        ;;
    esac
}

# Install required packages
install_packages() {
    if ! command -v aria2c &>/dev/null; then
        apt-get update
        apt-get install -y aria2
        if ! command -v aria2c &>/dev/null; then
            print error "Failed to install required packages. Exiting."
            exit 1
        fi
    fi
}

prepare_system() {
    install_packages
    check_ubuntu_version "20.04"

    if ! command -v needrestart &>/dev/null; then
        print info "Installing needrestart..."
        apt-get install -y needrestart
    fi

    export NEEDRESTART_MODE=a # Auto-restart services non-interactively

    # Configure needrestart to non-interactive
    local conf_file="/etc/needrestart/needrestart.conf"
    if [ -f "$conf_file" ]; then
        sed -i "s/^\(\$nrconf{restart}\s*=\s*\).*/\1'a';/" "$conf_file" || echo "\$nrconf{restart} = 'a';" >>"$conf_file"
    else
        echo "\$nrconf{restart} = 'a';" >"$conf_file"
    fi

    print success "System preparation complete with non-interactive restarts configured."
}

spinner() {
    local pid=$1
    local message="${2:-Processing...}"
    local frames=("⠋" "⠙" "⠹" "⠸" "⠼" "⠴" "⠦" "⠧" "⠇" "⠏")
    local delay=0.1
    local i=0
    local blue="\033[1;36m"  # Bright cyan/blue
    local green="\033[1;32m" # Bright green
    local red="\033[1;31m"   # Bright red
    local reset="\033[0m"
    local success_symbol="✅"
    local failure_symbol="❌"
    local last_status=0

    # Save cursor position and hide it
    tput sc
    tput civis

    # Show spinner while the process is running
    while kill -0 "$pid" 2>/dev/null; do
        printf "\r${blue}%s${reset} %s" "${frames[i]}" "$message"
        i=$(((i + 1) % ${#frames[@]}))
        sleep "$delay"
    done

    # Get the exit status of the process
    wait "$pid"
    last_status=$?

    # Replace spinner with completion symbol and appropriate color
    if [ $last_status -eq 0 ]; then
        printf "\r${green}%s${reset} %s\n" "$success_symbol" "$message"
    else
        printf "\r${red}%s${reset} %s (failed with status $last_status)\n" "$failure_symbol" "$message"
    fi

    # Show cursor again
    tput cnorm

    # Return the process exit status
    return $last_status
}

download_file() {
    local output_file="$1"
    local url="$2"

    local message="Downloading $(basename "$output_file")..."

    # Get output directory and filename
    local output_dir
    output_dir=$(dirname "$output_file")
    local filename
    filename=$(basename "$output_file")

    # Create the directory if it doesn't exist
    if [ ! -d "$output_dir" ]; then
        mkdir -p "$output_dir" || {
            print error "Failed to create directory $output_dir"
            return 1
        }
    fi

    # Remove existing file if it exists
    if [ -f "$output_file" ]; then
        rm -f "$output_file"
    fi

    print info "$message"

    local log_file
    log_file=$(mktemp)

    # Correctly specify both download directory (-d) and output file (-o)
    aria2c -x 5 -s 5 --console-log-level=error --summary-interval=0 \
        --allow-overwrite=true -d "$output_dir" -o "$filename" "$url" >"$log_file" 2>&1 &
    local download_pid=$!

    spinner "$download_pid" "$message"
    wait $download_pid
    local download_status=$?

    if [ $download_status -ne 0 ]; then
        print error "Download failed"
        print info "Detailed download logs:"
        cat "$log_file"
    else
        print success "Download completed successfully"
    fi

    rm -f "$log_file"
    return $download_status
}

# Download a file only if the remote version has changed
download_if_changed() {
    local output_file="$1"
    local url="$2"

    local tmpfile
    tmpfile=$(mktemp)

    if ! wget -q -O "$tmpfile" "$url"; then
        print error "Failed to download $(basename "$output_file") from $url"
        rm -f "$tmpfile"
        return 1
    fi

    if [ -f "$output_file" ]; then
        local new_checksum old_checksum
        new_checksum=$(md5sum "$tmpfile" | awk '{print $1}')
        old_checksum=$(md5sum "$output_file" | awk '{print $1}')

        if [ "$new_checksum" = "$old_checksum" ]; then
            print info "$(basename "$output_file") is already up-to-date."
            rm -f "$tmpfile"
            return 0
        fi
    fi

    mv "$tmpfile" "$output_file"
    chmod +x "$output_file"
    print success "Downloaded and updated $(basename "$output_file")"
    return 0
}


error_handling() {
    local last_cmd=$1
    local last_line=$2
    local last_error=$3
    echo "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    log_action "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    exit 1
}

# Ubuntu version check
check_ubuntu_version() {
    local min_version=$1
    local current_version=$(lsb_release -rs)

    # Check if version is greater than or equal to min_version
    if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
        print error "This script requires Ubuntu ${min_version} or newer."
        exit 1
    fi

    # Check if it's an LTS release
    local description=$(lsb_release -d)
    if ! echo "$description" | grep -q "LTS"; then
        print error "This script requires an Ubuntu LTS release."
        exit 1
    fi

    print success "Ubuntu version check passed: Running Ubuntu ${current_version} LTS."
}

# Validate LIS application path
is_valid_application_path() {
    local path=$1
    if [ -f "$path/configs/config.production.php" ] && [ -d "$path/public" ]; then
        return 0
    else
        return 1
    fi
}

# Convert to absolute path
to_absolute_path() {
    local p="$1"
    # expand leading “~” → $HOME
    [[ "$p" == "~"* ]] && p="${p/#\~/$HOME}"
    # canonicalize, resolving ., .., and symlinks
    readlink -f -- "$p"
}

# Set ACL-based permissions
set_permissions() {
    local path=$1
    local mode=${2:-"full"}

    if ! command -v setfacl &>/dev/null; then
        print warning "setfacl not found. Falling back to chown/chmod..."
        chown -R "$USER":www-data "$path"
        chmod -R u+rwX,g+rwX "$path"
        return
    fi

    print info "Setting permissions for ${path} (${mode} mode)..."

    case "$mode" in
    full)
        find "$path" -type d -not -path "*/.git*" -not -path "*/node_modules*" -exec setfacl -m u:$USER:rwx,u:www-data:rwx {} \; 2>/dev/null
        find "$path" -type f -not -path "*/.git*" -not -path "*/node_modules*" -print0 | xargs -0 -P "$(nproc)" -I{} setfacl -m u:$USER:rw,u:www-data:rw {} 2>/dev/null &
        ;;
    quick)
        find "$path" -type d -exec setfacl -m u:$USER:rwx,u:www-data:rwx {} \; 2>/dev/null
        find "$path" -type f -name "*.php" -print0 | xargs -0 -P "$(nproc)" -I{} setfacl -m u:$USER:rw,u:www-data:rw {} 2>/dev/null &
        ;;
    minimal)
        find "$path" -type d -exec setfacl -m u:$USER:rwx,u:www-data:rwx {} \; 2>/dev/null
        ;;
    esac
}

# Function to restart a service
# Function to restart a service (MySQL or Apache)
restart_service() {
    local service_type=$1

    case "$service_type" in
    apache)
        if systemctl list-units --type=service | grep -q apache2; then
            print info "Restarting Apache (apache2)..."
            log_action "Restarting apache2"
            systemctl restart apache2 || return 1
        elif systemctl list-units --type=service | grep -q httpd; then
            print info "Restarting Apache (httpd)..."
            log_action "Restarting httpd"
            systemctl restart httpd || return 1
        else
            print warning "Apache/httpd service not found"
            log_action "Apache/httpd not found"
            return 1
        fi
        ;;

    mysql)
        print info "Restarting MySQL..."
        log_action "Restarting MySQL"
        systemctl restart mysql || return 1
        ;;

    *)
        print error "Unknown service type: $service_type"
        log_action "Unknown service type: $service_type"
        return 1
        ;;
    esac

    print success "$service_type restarted successfully"
    return 0
}

# Ask user yes/no
ask_yes_no() {
    local prompt="$1"
    local default="${2:-no}"
    local timeout=15
    local answer

    # Normalize default
    default=$(echo "$default" | awk '{print tolower($0)}')
    [[ "$default" != "yes" && "$default" != "no" ]] && default="no"

    # If stdin is not a terminal, fallback to default
    if [ ! -t 0 ]; then
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    echo -n "$prompt (y/n) [default: $default, auto in ${timeout}s]: "

    read -t "$timeout" answer
    if [ $? -ne 0 ]; then
        print info "No input received in ${timeout} seconds. Using default: $default"
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    # Treat empty input (Enter) as choosing default
    answer=$(echo "$answer" | awk '{print tolower($0)}')
    if [ -z "$answer" ]; then
        print info "Using default: $default"
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    case "$answer" in
        y | yes) return 0 ;;
        n | no)  return 1 ;;
        *)
            print warning "Invalid input. Using default: $default"
            [[ "$default" == "yes" ]] && return 0 || return 1
            ;;
    esac
}


# Extract MySQL root password from config file
extract_mysql_password_from_config() {
    local config_file="$1"
    if [ ! -f "$config_file" ]; then
        print error "Config file not found: $config_file"
        return 1
    fi
    php -r "
        error_reporting(0);
        \$config = include '$config_file';
        echo isset(\$config['database']['password']) ? trim(\$config['database']['password']) : '';
    "
}

# Log action to log file
log_action() {
    local message=$1
    local logfile="${log_file:-/tmp/intelis-$(date +'%Y%m%d').log}"

    # Rotate if larger than 10MB
    if [ -f "$logfile" ] && [ $(stat -c %s "$logfile") -gt 10485760 ]; then
        mv "$logfile" "${logfile}.old"
    fi

    echo "$(date +'%Y-%m-%d %H:%M:%S') - $message" >>"$logfile"
}

# Helper for idempotent file writing
write_if_different() {
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

# Setup Intelis Scheduler (systemd timer replacement for cron)
setup_intelis_scheduler() {
    local lis_path="$1"
    local application_env="${2:-production}"

    # Create unique service name based on installation path
    local base_name="$(basename "$lis_path")"
    if [[ "$base_name" == "vlsm" || "$base_name" == "intelis" ]]; then
        local service_name="intelis"
    else
        local service_name="intelis-$base_name"
    fi

    print info "Configuring Intelis Scheduler (systemd timer) for $(basename "$lis_path")..."
    log_action "Configuring Intelis Scheduler with path: $lis_path, environment: $application_env, service: $service_name"

    # Validate paths
    if [[ ! -f "${lis_path}/cron.sh" ]]; then
        print error "cron.sh not found at ${lis_path}/cron.sh"
        log_action "ERROR: cron.sh not found at ${lis_path}/cron.sh"
        return 1
    fi

    # Make cron.sh executable (idempotent)
    chmod +x "${lis_path}/cron.sh"

    # Track what actually changed
    local service_changed=0
    local timer_changed=0
    local cron_removed=0

    # Create/update systemd service
    local service_file="/etc/systemd/system/${service_name}.service"
    if write_if_different "$service_file" <<EOF
[Unit]
Description=Intelis Scheduler for $(basename "$lis_path") (crunzphp jobs)
After=network-online.target mysql.service apache2.service
Wants=network-online.target

[Service]
Type=oneshot
User=www-data
Group=www-data
Environment=APPLICATION_ENV=${application_env}
WorkingDirectory=${lis_path}
ExecStart=${lis_path}/cron.sh ${application_env}

# Prevent multiple instances
RemainAfterExit=no

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=${service_name}
EOF
    then
        service_changed=1
        print info "Updated ${service_name}.service"
        log_action "Updated ${service_name}.service"
    else
        print info "${service_name}.service already up to date"
    fi

    # Create/update systemd timer
    local timer_file="/etc/systemd/system/${service_name}.timer"
    if write_if_different "$timer_file" <<EOF
[Unit]
Description=Run Intelis scheduled jobs every minute for $(basename "$lis_path")

[Timer]
OnBootSec=120s
OnUnitActiveSec=60s
AccuracySec=5s
Unit=${service_name}.service
Persistent=true

[Install]
WantedBy=timers.target
EOF
    then
        timer_changed=1
        print info "Updated ${service_name}.timer"
        log_action "Updated ${service_name}.timer"
    else
        print info "${service_name}.timer already up to date"
    fi

    # Only reload systemd if files actually changed
    if [[ "$service_changed" == "1" || "$timer_changed" == "1" ]]; then
        systemctl daemon-reload
        print info "Reloaded systemd configuration"
        log_action "Reloaded systemd due to timer/service changes"
    fi

    # Migrate from cron (idempotent) - comment out matching lines
    local current_crontab
    current_crontab=$(crontab -l 2>/dev/null || echo "")

    # Only comment if there's an uncommented line with both lis_path and cron.sh
    if echo "$current_crontab" | grep -v "^#" | grep -q "${lis_path}" && echo "$current_crontab" | grep -v "^#" | grep -q "cron.sh"; then
        print info "Commenting out old cron job..."
        log_action "Commenting out cron job for $lis_path"
        # Comment out any uncommented line containing both lis_path and cron.sh
        updated_crontab=$(echo "$current_crontab" | sed "s|^\([^#].*${lis_path//\//\\\/}.*cron\.sh.*\)|#\1|")
        echo "$updated_crontab" | crontab -
        cron_removed=1
        print success "Commented out old cron job"
        log_action "Successfully commented out cron job"
    else
        print info "No active cron job found to comment"
    fi

    # Clean up old generic intelis timer if it exists
    if systemctl list-unit-files | grep -q "^intelis\.timer"; then
        print info "Removing old generic intelis timer..."
        systemctl disable --now intelis.timer 2>/dev/null || true
        rm -f /etc/systemd/system/intelis.timer
        rm -f /etc/systemd/system/intelis.service
        systemctl daemon-reload
        print success "Cleaned up old generic intelis timer"
        log_action "Removed old generic intelis timer"
    fi

    # Clean up old intelis-scheduler if it exists
    if systemctl list-unit-files | grep -q "intelis-scheduler.timer"; then
        print info "Removing old intelis-scheduler timer..."
        systemctl disable --now intelis-scheduler.timer 2>/dev/null || true
        rm -f /etc/systemd/system/intelis-scheduler.timer
        rm -f /etc/systemd/system/intelis-scheduler.service
        systemctl daemon-reload
        print success "Cleaned up old intelis-scheduler"
        log_action "Removed old intelis-scheduler timer"
    fi

    # Enable timer (idempotent)
    if ! systemctl is-enabled --quiet "${service_name}.timer"; then
        systemctl enable "${service_name}.timer"
        print info "Enabled ${service_name}.timer"
        log_action "Enabled ${service_name}.timer"
    else
        print info "${service_name}.timer already enabled"
    fi

    # Start timer (idempotent)
    if ! systemctl is-active --quiet "${service_name}.timer"; then
        systemctl start "${service_name}.timer"
        print info "Started ${service_name}.timer"
        log_action "Started ${service_name}.timer"
    else
        print info "${service_name}.timer already running"
    fi

    # Summary of what happened
    local changes_made=0
    [[ "$service_changed" == "1" ]] && ((changes_made++))
    [[ "$timer_changed" == "1" ]] && ((changes_made++))
    [[ "$cron_removed" == "1" ]] && ((changes_made++))

    if [[ "$changes_made" -gt 0 ]]; then
        print success "✅ Intelis Scheduler configured for $(basename "$lis_path") ($changes_made changes made)"
    else
        print success "✅ Intelis Scheduler already configured correctly for $(basename "$lis_path")"
    fi

    print info "Monitor: journalctl -u ${service_name}.service -f"
    print info "Status: systemctl status ${service_name}.timer"
    log_action "Intelis Scheduler setup completed for $service_name (changes: $changes_made)"
}


# Remove timer and service by name
remove_timer() {
    local timer_name="$1"

    if [[ -z "$timer_name" ]]; then
        print error "Usage: remove_timer <timer-name>"
        print info "Example: remove_timer intelis-vlsm"
        return 1
    fi

    print info "Removing ${timer_name} timer..."

    systemctl disable --now "${timer_name}.timer" 2>/dev/null || true
    rm -f "/etc/systemd/system/${timer_name}.timer"
    rm -f "/etc/systemd/system/${timer_name}.service"
    systemctl daemon-reload

    print success "${timer_name} timer removed"
}

# Remove all intelis timers
remove_all_intelis_timers() {
    print info "Removing all Intelis timers..."

    systemctl disable --now intelis*.timer 2>/dev/null || true
    rm -f /etc/systemd/system/intelis*.timer
    rm -f /etc/systemd/system/intelis*.service
    systemctl daemon-reload

    print success "All Intelis timers removed"
}

# Remove all monitoring timers
remove_all_monitoring() {
    print info "Removing all monitoring timers..."

    for timer in service-guard resource-monitor intelis*; do
        systemctl disable --now "${timer}.timer" 2>/dev/null || true
    done

    rm -f /etc/systemd/system/service-guard.*
    rm -f /etc/systemd/system/resource-monitor.*
    rm -f /etc/systemd/system/intelis*.*
    systemctl daemon-reload

    print success "All monitoring timers removed"
}
