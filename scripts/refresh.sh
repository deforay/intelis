#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/intelis-refresh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/refresh.sh && sudo chmod +x /usr/local/bin/intelis-refresh
# sudo intelis-refresh

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Initialize flags
skip_ubuntu_updates=false
skip_backup=false
lis_path=""

log_file="/tmp/intelis-refresh-$(date +'%Y%m%d-%H%M%S').log"

# Parse command-line options
while getopts ":sbp:" opt; do
    case $opt in
    s) skip_ubuntu_updates=true ;;
    b) skip_backup=true ;;
    p) lis_path="$OPTARG" ;;
        # Ignore invalid options silently
    esac
done

# Function to check if the provided path is a valid application installation
is_valid_application_path() {
    local path=$1
    # Check for a specific file or directory that should exist in the application installation
    if [ -f "$path/configs/config.production.php" ] && [ -d "$path/public" ]; then
        return 0 # Path is valid
    else
        return 1 # Path is not valid
    fi
}

# Function to convert relative path to absolute path
to_absolute_path() {
    local path=$1
    if [[ "$path" != /* ]]; then
        # If the path is relative, convert it to an absolute path
        path="$(pwd)/$path"
    fi
    echo "$path"
}

# Function to log messages
log_action() {
    local message=$1
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $message" >>"$log_file"
}

error_handling() {
    local last_cmd=$1
    local last_line=$2
    local last_error=$3
    echo "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    log_action "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"

    # Check if the error is critical
    if [ "$last_error" -eq 1 ]; then # Adjust according to the error codes you consider critical
        echo "This error is critical, exiting..."
        exit 1
    else
        echo "This error is not critical, continuing..."
    fi
}

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

# Function to get Ubuntu version
get_ubuntu_version() {
    local version=$(lsb_release -rs)
    echo "$version"
}

# Check if Ubuntu version is 20.04 or newer
min_version="20.04"
current_version=$(get_ubuntu_version)

if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
    echo "This script is not compatible with Ubuntu versions older than ${min_version}."
    exit 1
fi

# Save the current trap settings
current_trap=$(trap -p ERR)

# Disable the error trap temporarily
trap - ERR

# Prompt for the LIS path if not provided via the command-line argument
if [ -z "$lis_path" ]; then
    echo "Enter the LIS installation path [press enter to select /var/www/vlsm]: "
    read -t 60 lis_path

    # Check if read command timed out or no input was provided
    if [ $? -ne 0 ] || [ -z "$lis_path" ]; then
        lis_path="/var/www/vlsm"
        echo "Using default path: $lis_path"
    else
        echo "LIS path is set to ${lis_path}"
    fi
else
    echo "LIS path is set to ${lis_path}"
fi

# Convert relative path to absolute path if necessary
if [[ "$lis_path" != /* ]]; then
    lis_path="$(realpath "$lis_path")"
    echo "Converted to absolute path: $lis_path"
fi

# Convert VLSM path to absolute path
lis_path=$(to_absolute_path "$lis_path")

# Check if the LIS path is valid
if ! is_valid_application_path "$lis_path"; then
    echo "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
    log_action "Invalid LIS path specified: $lis_path"
    exit 1
fi

log_action "LIS path is set to ${lis_path}"

# Restore the previous error trap
eval "$current_trap"

# # Check if LIS folder exists
# if [ ! -d "${lis_path}" ]; then
#     echo "LIS folder does not exist at ${lis_path}. Please first run the setup script."
#     log_action "LIS folder does not exist at ${lis_path}. Please first run the setup script."
#     exit 1
# fi

# Check for MySQL
if ! command -v mysql &>/dev/null; then
    echo "MySQL is not installed. Please first run the setup script."
    log_action "MySQL is not installed. Please first run the setup script."
    exit 1
fi

# Check for Apache
if ! command -v apache2ctl &>/dev/null; then
    echo "Apache is not installed. Please first run the setup script."
    log_action "Apache is not installed. Please first run the setup script."
    exit 1
fi

# Check for PHP
if ! command -v php &>/dev/null; then
    echo "PHP is not installed. Please first run the setup script."
    log_action "PHP is not installed. Please first run the setup script."
    exit 1
fi

ask_yes_no() {
    local timeout=15
    local default=${2:-"no"} # set default value from the argument, fallback to "no" if not provided
    local answer=""

    while true; do
        echo -n "$1 (y/n): "
        read -t $timeout answer
        if [ $? -ne 0 ]; then
            answer=$default
        fi

        answer=$(echo "$answer" | awk '{print tolower($0)}')
        case "$answer" in
        "yes" | "y") return 0 ;;
        "no" | "n") return 1 ;;
        *)
            if [ -z "$answer" ]; then
                # If no input is given and it times out, apply the default value
                if [ "$default" == "yes" ] || [ "$default" == "y" ]; then
                    return 0
                else
                    return 1
                fi
            else
                echo "Invalid response. Please answer 'yes/y' or 'no/n'."
            fi
            ;;
        esac
    done
}

# Check for PHP version 8.2.x
php_version=$(php -v | head -n 1 | grep -oP 'PHP \K([0-9]+\.[0-9]+)')
desired_php_version="8.2"

# Download and install switch-php script
wget https://raw.githubusercontent.com/deforay/utility-scripts/master/php/switch-php -O /usr/local/bin/switch-php
chmod u+x /usr/local/bin/switch-php

if [[ "${php_version}" != "${desired_php_version}" ]]; then
    echo "Current PHP version is ${php_version}. Switching to PHP ${desired_php_version}."

    # Switch to PHP 8.2
    switch-php ${desired_php_version}

    if [ $? -ne 0 ]; then
        echo "Failed to switch to PHP ${desired_php_version}. Please check your setup."
        exit 1
    fi
else
    echo "PHP version is already ${desired_php_version}."
fi

# Check for Composer
if ! command -v composer &>/dev/null; then
    echo "Composer is not installed. Please first run the setup script."
    log_action "Composer is not installed. Please first run the setup script."
    exit 1
fi

# Proceed with the rest of the script if all checks pass

echo "All system checks passed. Continuing with the refresh..."

setfacl -R -m u:$USER:rwx,u:www-data:rwx "${lis_path}"

spinner() {
    local pid=$!
    local delay=0.75
    local spinstr='|/-\'
    while kill -0 $pid 2>/dev/null; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

# Set proper permissions
setfacl -R -m u:$USER:rwx,u:www-data:rwx "${lis_path}"

# Run Composer Install as www-data
echo "Running composer install as www-data user..."
cd "${lis_path}"

sudo -u www-data composer config process-timeout 30000

sudo -u www-data composer clear-cache

sudo -u www-data composer install --no-dev &&
    sudo -u www-data composer dump-autoload -o

log_action "Composer install completed."

# Run the database migrations and other post-update tasks
echo "Running database migrations and other post-update tasks..."
sudo -u www-data composer post-update &
pid=$!
spinner "$pid"
wait $pid
log_action "Database migrations and post-update tasks completed."

if [ -f "${lis_path}/cache/CompiledContainer.php" ]; then
    rm "${lis_path}/cache/CompiledContainer.php"
fi

service apache2 restart

echo "Apache Restarted."
log_action "Apache Restarted."

chown -R $USER:www-data "${lis_path}"
setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www

echo "LIS refresh complete."
log_action "LIS refresh complete."
