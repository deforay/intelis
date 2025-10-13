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
            term_width=$( [ -t 1 ] && tput cols 2>/dev/null || echo 80 )
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
    local required_pkgs=(aria2 wget lsb-release bc pigz gpg)
    local missing_pkgs=()
    for pkg in "${required_pkgs[@]}"; do
        if ! command -v "$pkg" &>/dev/null; then
            missing_pkgs+=("$pkg")
        fi
    done

    if [ "${#missing_pkgs[@]}" -gt 0 ]; then
        apt-get update
        apt-get install -y "${missing_pkgs[@]}"
        # Re-check all required packages
        for pkg in "${required_pkgs[@]}"; do
            if ! command -v "$pkg" &>/dev/null; then
                print error "Failed to install required package: $pkg. Exiting."
                exit 1
            fi
        done
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
    local ascii_frames=('|' '/' '-' '\')
    local delay=0.1
    local i=0
    local last_status=0

    # Colors (only when TTY)
    local blue="\033[1;36m"
    local green="\033[1;32m"
    local red="\033[1;31m"
    local reset="\033[0m"

    # TTY + tput detection
    local is_tty=0 has_tput=0
    [ -t 1 ] && is_tty=1
    command -v tput >/dev/null 2>&1 && has_tput=1

    # UTF-8 heuristic; disable animation if not a TTY
    local use_unicode=1
    printf '%s' "$LC_ALL$LC_CTYPE$LANG" | grep -qi 'utf-8' || use_unicode=0
    (( is_tty )) || use_unicode=0

    # Hide cursor if we can and restore on exit
    if (( is_tty && has_tput )); then
        tput civis 2>/dev/null || true
    fi
    cleanup() {
        if (( is_tty && has_tput )); then
            tput cnorm 2>/dev/null || true
        fi
    }
    trap cleanup EXIT INT TERM

    # Draw loop (only animate on TTY)
    if (( is_tty )); then
        while kill -0 "$pid" 2>/dev/null; do
            printf "\r\033[K"
            if (( use_unicode )); then
                printf "${blue}%s${reset} %s" "${frames[i]}" "$message"
                (( i = (i + 1) % ${#frames[@]} ))
            else
                printf "${blue}%s${reset} %s" "${ascii_frames[i]}" "$message"
                (( i = (i + 1) % ${#ascii_frames[@]} ))
            fi
            sleep "$delay"
        done
    fi

    wait "$pid"; last_status=$?

    if (( is_tty )); then
        if (( last_status == 0 )); then
            printf "\r\033[K${green}✅${reset} %s\n" "$message"
        else
            printf "\r\033[K${red}❌${reset} %s (failed with status %d)\n" "$message" "$last_status"
        fi
    else
        if (( last_status == 0 )); then
            printf "[OK] %s\n" "$message"
        else
            printf "[FAIL:%d] %s\n" "$last_status" "$message"
        fi
    fi

    return "$last_status"
}


download_file() {
    local output_file="$1"
    local url="$2"
    local default_msg="Downloading $(basename "$output_file")..."
    local message="${3:-$default_msg}"

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
    [ -f "$output_file" ] && rm -f "$output_file"

    print info "$message"

    local log_file
    log_file=$(mktemp)

    # Download with aria2c
    # --no-conf: don't load aria2.conf (prevents cache settings)
    # --conditional-get=false: always download, ignore cache headers
    # --remote-time=false: don't preserve remote file timestamps
    aria2c -x 5 -s 5 \
        --console-log-level=error \
        --summary-interval=0 \
        --allow-overwrite=true \
        --no-conf \
        --conditional-get=false \
        --remote-time=false \
        -d "$output_dir" \
        -o "$filename" \
        "$url" >"$log_file" 2>&1 &
    local download_pid=$!

    spinner "$download_pid" "$message"
    local download_status=$?

    if [ $download_status -ne 0 ]; then
        print error "Download failed for: $filename"
        print info "Detailed download logs:"
        cat "$log_file"
    else
        print success "Download completed: $filename"
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

    # empty → echo empty (caller decides fallback)
    [ -z "$p" ] && { echo ""; return 0; }

    # expand leading "~" → $HOME
    [[ "$p" == "~"* ]] && p="${p/#\~/$HOME}"

    if command -v realpath >/dev/null 2>&1; then
        # -m: canonicalize even if components don’t exist; "." works too
        realpath -m -- "$p"
        return $?
    fi

    # GNU readlink: prefer -m if available, else -f (requires existing path)
    if readlink -m / >/dev/null 2>&1; then
        readlink -m -- "$p"
        return $?
    fi

    case "$p" in
        /*) printf '%s\n' "$p" ;;
        *)  printf '%s\n' "$(pwd)/$p" ;;
    esac
}


# Set ACL-based permissions (async by default; pass third arg "sync" to wait)
set_permissions() {
    local path=$1
    local mode=${2:-"full"}          # full | quick | minimal
    local wait_mode=${3:-"async"}    # async | sync

    # Who to grant (robust under sudo/non-interactive)
    local who="${SUDO_USER:-${USER:-root}}"

    if ! command -v setfacl &>/dev/null; then
        print warning "setfacl not found. Falling back to chown/chmod..."
        chown -R "$who":www-data "$path"
        chmod -R u+rwX,g+rwX "$path"
        return
    fi

    # Tunables
    local PARALLEL=${PARALLEL:-$(nproc)}
    local ACL_TIMEOUT_SEC=${ACL_TIMEOUT_SEC:-3}      # per-file timeout
    local CPU_NICE="nice -n 10"
    local IO_NICE=""
    command -v ionice >/dev/null 2>&1 && IO_NICE="ionice -c3"
    command -v timeout >/dev/null 2>&1 || ACL_TIMEOUT_SEC=0  # if no timeout, disable

    print info "Setting permissions for ${path} (${mode}, ${wait_mode})..."

    # Export env so subshells (xargs sh -c) can use them
    export ACL_TIMEOUT_SEC CPU_NICE IO_NICE who

    # Helper executed in subshell (sh -c), single file per invocation
    _acl_apply_cmd='
        target="$1"; perms="$2";
        if [ -n "$ACL_TIMEOUT_SEC" ] && [ "$ACL_TIMEOUT_SEC" -gt 0 ]; then
            $CPU_NICE $IO_NICE timeout "${ACL_TIMEOUT_SEC}s" setfacl -m "$perms" "$target" 2>/dev/null \
            || printf "%s\t%s\n" "ACL_TIMEOUT_OR_FAIL" "$target" >>/tmp/acl_failures.log
        else
            $CPU_NICE $IO_NICE setfacl -m "$perms" "$target" 2>/dev/null \
            || printf "%s\t%s\n" "ACL_FAIL" "$target" >>/tmp/acl_failures.log
        fi
    '

    local pids=()

    case "$mode" in
        full)
            # Directories: rwx to user + www-data
            find "$path" -type d -not -path "*/.git*" -not -path "*/node_modules*" -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rwx,u:www-data:rwx" &
            pids+=($!)

            # Files: rw to user + www-data
            find "$path" -type f -not -path "*/.git*" -not -path "*/node_modules*" -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rw,u:www-data:rw" &
            pids+=($!)
        ;;
        quick)
            find "$path" -type d -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rwx,u:www-data:rwx" &
            pids+=($!)

            find "$path" -type f -name "*.php" -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rw,u:www-data:rw" &
            pids+=($!)
        ;;
        minimal)
            find "$path" -type d -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rwx,u:www-data:rwx" &
            pids+=($!)
        ;;
      *)
        print warning "Unknown mode '${mode}', using 'full'."
        "$FUNCNAME" "$path" full "$wait_mode"
        return
        ;;
    esac

    if [[ "$wait_mode" == "sync" ]]; then
        for pid in "${pids[@]}"; do wait "$pid"; done
        [[ -s /tmp/acl_failures.log ]] && print warning "Some ACL operations timed out/failed. See /tmp/acl_failures.log"
        print success "Permissions applied (sync)."
    else
        print info "ACLs applying in background (async)."
    fi
}

# Function to restart a service (MySQL or Apache)
restart_service() {
    local service_type=$1

    case "$service_type" in
        apache)
            if systemctl list-unit-files apache2.service >/dev/null 2>&1; then
                print info "Restarting Apache (apache2)..."
                log_action "Restarting apache2"
                systemctl restart apache2 || return 1
            elif systemctl list-unit-files httpd.service >/dev/null 2>&1; then
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

# Setup Scheduler (systemd timer replacement for cron)
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

    print info "Configuring Scheduler (systemd timer) for ${lis_path}..."
    log_action "Configuring Scheduler with path: $lis_path, environment: $application_env, service: $service_name"

    # Validate paths
    if [[ ! -f "${lis_path}/cron.sh" ]]; then
        print error "cron.sh not found at ${lis_path}/cron.sh"
        log_action "ERROR: cron.sh not found at ${lis_path}/cron.sh"
        return 1
    fi

    # Make cron.sh executable
    chmod +x "${lis_path}/cron.sh"

    # Track what actually changed
    local service_changed=0
    local timer_changed=0
    local cron_removed=0

    # Create/update systemd service
    local service_file="/etc/systemd/system/${service_name}.service"
    if write_if_different "$service_file" <<EOF
[Unit]
Description=Scheduler for ${lis_path}
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
Description=Run scheduled jobs every minute for ${lis_path}

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

    # Migrate from cron  - comment out matching lines
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

    # Enable timer
    if ! systemctl is-enabled --quiet "${service_name}.timer"; then
        systemctl enable "${service_name}.timer"
        print info "Enabled ${service_name}.timer"
        log_action "Enabled ${service_name}.timer"
    else
        print info "${service_name}.timer already enabled"
    fi

    # Start timer
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
        print success "✅ Scheduler configured for ${lis_path} ($changes_made changes made)"
    else
        print success "✅ Scheduler already configured correctly for ${lis_path}"
    fi

    print info "Monitor: journalctl -u ${service_name}.service -f"
    print info "Status: systemctl status ${service_name}.timer"
    log_action "Scheduler setup completed for $service_name (changes: $changes_made)"
}

# List active Intelis monitoring timers
list_timers() {
    print header "Intelis System Timers"

    local timers_found=false

    # Get timer info and filter for our timers
    while IFS= read -r line; do
        if [[ "$line" =~ (service-guard|resource-monitor|intelis) ]]; then
            echo "$line"
            timers_found=true
        fi
    done < <(systemctl list-timers --no-pager)

    if [[ "$timers_found" == "false" ]]; then
        print warning "No Intelis monitoring timers found"
    fi

    echo
    print info "To check logs: journalctl -u <service-name> -f"
    print info "To check status: systemctl status <timer-name>"
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

    systemctl list-unit-files 'intelis*.timer' --no-legend \
    | awk '{print $1}' | xargs -r systemctl disable --now 2>/dev/null || true
    find /etc/systemd/system -maxdepth 1 \( -name 'intelis*.timer' -o -name 'intelis*.service' \) -type f -exec rm -f {} +

    systemctl daemon-reload

    print success "All Intelis timers removed"
}

# Remove all monitoring timers (guard + resource-monitor only)
remove_all_monitoring() {
    print info "Removing all monitoring timers..."

    for timer in service-guard resource-monitor; do
        systemctl disable --now "${timer}.timer" 2>/dev/null || true
        rm -f "/etc/systemd/system/${timer}.timer" "/etc/systemd/system/${timer}.service"
    done

    systemctl daemon-reload

    print success "All monitoring timers removed"
}





# Setup Intelis cron job (classic crontab, idempotent)
setup_intelis_cron() {
    local lis_path="$1"
    local cron_job="* * * * * cd ${lis_path} && ./cron.sh"

    # Ensure cron.sh is executable
    chmod +x "${lis_path}/cron.sh"

    # Load current root crontab without failing if none exists
    local current_crontab
    current_crontab="$(crontab -l 2>/dev/null || true)"

    # Already present?
    if printf '%s\n' "$current_crontab" | grep -Fxq "$cron_job"; then
        print info "Cron job for LIS already active. Skipping."
        log_action "Cron job for LIS already active. Skipped."
        return 0
    fi

    # Remove any existing (active or commented) similar entry
    local updated_crontab
    updated_crontab="$(
        printf '%s\n' "$current_crontab" |
        sed -E "/^[[:space:]]*#?[[:space:]]*\*[[:space:]]\*[[:space:]]\*[[:space:]]\*[[:space:]]\*[[:space:]]+cd[[:space:]]+$(printf '%s' "${lis_path}" | sed 's|/|\\/|g')[[:space:]]+&&[[:space:]]+\\./cron\\.sh$/d"
    )"

    # Write back crontab with our job appended
    {
        printf '%s\n' "$updated_crontab"
        printf '%s\n' "$cron_job"
    } | crontab -

    print success "Cron job for LIS added/replaced in root's crontab."
    log_action "Cron job for LIS added/replaced in root's crontab."
}
