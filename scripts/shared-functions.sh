#!/bin/bash

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

    # Normalize default to lowercase
    default=$(echo "$default" | awk '{print tolower($0)}')
    [[ "$default" != "yes" && "$default" != "no" ]] && default="no"

    # If stdin is not a terminal (e.g., non-interactive mode), use default immediately
    if [ ! -t 0 ]; then
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    echo -n "$prompt (y/n) [default: $default, auto in ${timeout}s]: "

    read -t "$timeout" answer
    if [ $? -ne 0 ]; then
        print info "No input received in ${timeout} seconds. Using default: $default"
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    answer=$(echo "$answer" | awk '{print tolower($0)}')

    case "$answer" in
    y | yes) return 0 ;;
    n | no) return 1 ;;
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
