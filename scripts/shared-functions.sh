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

# Spinner animation
spinner() {
    local pid=$1
    local frames=("⠋" "⠙" "⠹" "⠸" "⠼" "⠴" "⠦" "⠧" "⠇" "⠏")
    local delay=0.1
    local i=0
    local color="\033[1;36m" # Bold cyan
    local reset="\033[0m"

    tput civis # Hide cursor
    while kill -0 "$pid" 2>/dev/null; do
        printf "\r${color}%s${reset}" "${frames[i]}"
        i=$(((i + 1) % ${#frames[@]}))
        sleep "$delay"
    done
    printf "\r \r"
    tput cnorm # Show cursor
}

# Ubuntu version check
check_ubuntu_version() {
    local min_version=$1
    local current_version=$(lsb_release -rs)
    if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
        print error "This script requires Ubuntu ${min_version} or newer."
        exit 1
    fi
}

# Path validation for LIS installation
is_valid_application_path() {
    local path=$1
    if [ -f "$path/configs/config.production.php" ] && [ -d "$path/public" ]; then
        return 0
    else
        return 1
    fi
}

to_absolute_path() {
    local path=$1
    if [[ "$path" != /* ]]; then
        path="$(pwd)/$path"
    fi
    echo "$path"
}


# Function to get Ubuntu version
get_ubuntu_version() {
    local version=$(lsb_release -rs)
    echo "$version"
}


# Function to set permissions more efficiently
set_permissions() {
    local path=$1
    local mode=${2:-"full"} # Options: full, quick, critical

    print info "Setting permissions for ${path} (${mode} mode)..."

    case "$mode" in
    "full")
        # Full permission setting - all directories and files
        find "${path}" -type d -exec setfacl -m u:$USER:rwx,u:www-data:rwx {} \; 2>/dev/null
        find "${path}" -type f -print0 | xargs -0 -P $(nproc) -I{} setfacl -m u:$USER:rw,u:www-data:rw {} 2>/dev/null &
        ;;

    "quick")
        # Quick mode - only directories and php files
        find "${path}" -type d -exec setfacl -m u:$USER:rwx,u:www-data:rwx {} \; 2>/dev/null
        find "${path}" -type f -name "*.php" -print0 |
            xargs -0 -P $(nproc) -I{} setfacl -m u:$USER:rw,u:www-data:rw {} 2>/dev/null &
        ;;

    "minimal")
        # Minimal mode - only directories to ensure structure is accessible
        find "${path}" -type d -exec setfacl -m u:$USER:rwx,u:www-data:rwx {} \; 2>/dev/null
        ;;
    esac
}
