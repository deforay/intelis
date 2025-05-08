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
    check_ubuntu_version "22.04"
    if ! command -v needrestart &>/dev/null; then
        print info "needrestart not found. Installing it..."
        apt-get install -y needrestart
    fi

    # Force needrestart to always auto-restart services (non-interactive)
    export NEEDRESTART_MODE=a

    # Make needrestart non-interactive
    if [ -f /etc/needrestart/needrestart.conf ]; then
        if grep -q "^\$nrconf{restart}" /etc/needrestart/needrestart.conf; then
            sed -i "s/^\(\$nrconf{restart}\s*=\s*\).*/\1'a';/" /etc/needrestart/needrestart.conf
        else
            echo "\$nrconf{restart} = 'a';" >>/etc/needrestart/needrestart.conf
        fi
    else
        print warning "needrestart.conf not found. Skipping non-interactive restart config."
    fi
}

spinner() {
    local pid=$1
    local frames=("⠋" "⠙" "⠹" "⠸" "⠼" "⠴" "⠦" "⠧" "⠇" "⠏")
    local delay=0.1
    local i=0
    local color="\033[1;36m"
    local reset="\033[0m"

    tput civis
    while kill -0 "$pid" 2>/dev/null; do
        printf "\r${color}%s${reset}" "${frames[i]}"
        i=$(((i + 1) % ${#frames[@]}))
        sleep "$delay"
    done
    printf "\r \r"
    tput cnorm
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
    local path=$1
    if [[ "$path" != /* ]]; then
        path="$(pwd)/$path"
    fi
    echo "$path"
}

# Set ACL-based permissions
set_permissions() {
    local path=$1
    local mode=${2:-"full"}

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
    local answer

    while true; do
        echo -n "$prompt (y/n): "
        read -t 15 answer
        [ $? -ne 0 ] && answer="$default"
        answer=$(echo "$answer" | awk '{print tolower($0)}')
        case "$answer" in
        y | yes) return 0 ;;
        n | no) return 1 ;;
        *)
            if [ -z "$answer" ]; then
                [ "$default" = "yes" ] || [ "$default" = "y" ] && return 0 || return 1
            else
                echo "Invalid response. Please answer yes/y or no/n."
            fi
            ;;
        esac
    done
}

# Extract MySQL root password from config file
extract_mysql_password_from_config() {
    local config_file="$1"
    php -r "
        \$config = include '$config_file';
        echo isset(\$config['database']['password']) ? trim(\$config['database']['password']) : '';
    "
}

# Log action to log file
log_action() {
    local message=$1
    local logfile="${log_file:-/tmp/intelis-$(date +'%Y%m%d').log}"
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $message" >>"$logfile"
}
