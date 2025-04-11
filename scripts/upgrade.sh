#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/intelis-update https://raw.githubusercontent.com/deforay/vlsm/master/scripts/upgrade.sh && sudo chmod +x /usr/local/bin/intelis-update
# sudo intelis-update

# Define a unified print function that colors the entire message
print() {
    local type=$1
    local message=$2

    case $type in
    error)
        echo -e "\033[0;31mError: $message\033[0m"
        ;;
    success)
        echo -e "\033[0;32mSuccess: $message\033[0m"
        ;;
    warning)
        echo -e "\033[0;33mWarning: $message\033[0m"
        ;;
    info)
        # Changed from blue (\033[0;34m) to teal/turquoise (\033[0;36m)
        echo -e "\033[0;36mInfo: $message\033[0m"
        ;;
    debug)
        # Using a lighter cyan color for debug messages
        echo -e "\033[1;36mDebug: $message\033[0m"
        ;;
    header)
        # Changed from blue to a brighter cyan/teal
        echo -e "\033[1;36m==== $message ====\033[0m"
        ;;
    *)
        echo "$message"
        ;;
    esac
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print error "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi


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


# Initialize flags
skip_ubuntu_updates=false
skip_backup=false
lis_path=""

log_file="/tmp/intelis-upgrade-$(date +'%Y%m%d-%H%M%S').log"

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
    print error "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    log_action "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"

    # Check if the error is critical
    if [ "$last_error" -eq 1 ]; then # Adjust according to the error codes you consider critical
        print error "This error is critical, exiting..."
        exit 1
    else
        print warning "This error is not critical, continuing..."
    fi
}

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

# Function to get Ubuntu version
get_ubuntu_version() {
    local version=$(lsb_release -rs)
    echo "$version"
}

extract_mysql_password_from_config() {
    local config_file="$1"
    php -r "
        \$config = include '$config_file';
        echo isset(\$config['database']['password']) ? trim(\$config['database']['password']) : '';
    "
}

# Function to update configuration
update_configuration() {
    local mysql_root_password
    local mysql_root_password_confirm

    while :; do
        # Ask for MySQL root password
        read -sp "Please enter the MySQL root password: " mysql_root_password
        echo
        read -sp "Please confirm the MySQL root password: " mysql_root_password_confirm
        echo

        if [ "$mysql_root_password" == "$mysql_root_password_confirm" ]; then
            break
        else
            print error "Passwords do not match. Please try again."
        fi
    done

    # Escape special characters in password for sed
    escaped_mysql_root_password=$(perl -e 'print quotemeta $ARGV[0]' -- "${mysql_root_password}")

    # Update database configurations in config.production.php
    sed -i "s|\$systemConfig\['database'\]\['host'\]\s*=.*|\$systemConfig['database']['host'] = 'localhost';|" "${config_file}"
    sed -i "s|\$systemConfig\['database'\]\['username'\]\s*=.*|\$systemConfig['database']['username'] = 'root';|" "${config_file}"
    sed -i "s|\$systemConfig\['database'\]\['password'\]\s*=.*|\$systemConfig['database']['password'] = '$escaped_mysql_root_password';|" "${config_file}"

    # Prompt for Remote STS URL
    read -p "Please enter the Remote STS URL (can be blank if you choose so): " remote_sts_url

    # Update config.production.php with Remote STS URL if provided
    if [ ! -z "$remote_sts_url" ]; then
        sed -i "s|\$systemConfig\['remoteURL'\]\s*=\s*'.*';|\$systemConfig['remoteURL'] = '$remote_sts_url';|" "${config_file}"
    fi

    print info "Configuration file updated."
}

# Check if Ubuntu version is 20.04 or newer
min_version="20.04"
current_version=$(get_ubuntu_version)

if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
    print error "This script is not compatible with Ubuntu versions older than ${min_version}."
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
        print info "Using default path: $lis_path"
    else
        print info "LIS path is set to ${lis_path}"
    fi
else
    print info "LIS path is set to ${lis_path}"
fi

# Convert relative path to absolute path if necessary
if [[ "$lis_path" != /* ]]; then
    lis_path="$(realpath "$lis_path")"
    print info "Converted to absolute path: $lis_path"
fi

# Convert VLSM path to absolute path
lis_path=$(to_absolute_path "$lis_path")

# Check if the LIS path is valid
if ! is_valid_application_path "$lis_path"; then
    print error "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
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
    print error "MySQL is not installed. Please first run the setup script."
    log_action "MySQL is not installed. Please first run the setup script."
    exit 1
fi

print header "Configuring MySQL"
desired_sql_mode="sql_mode ="
desired_innodb_strict_mode="innodb_strict_mode = 0"
desired_charset="character-set-server=utf8mb4"
desired_collation="collation-server=utf8mb4_general_ci"
desired_auth_plugin="default_authentication_plugin=mysql_native_password"
config_file="/etc/mysql/mysql.conf.d/mysqld.cnf"

# Make a backup of the configuration file if we're going to modify it
backup_file="${config_file}.bak.$(date +%Y%m%d%H%M%S)"
cp "$config_file" "$backup_file"

# Check if the settings are already present and correctly set
sql_mode_set=$(grep -q "^${desired_sql_mode}" ${config_file} && echo "true" || echo "false")
innodb_strict_mode_set=$(grep -q "^${desired_innodb_strict_mode}" ${config_file} && echo "true" || echo "false")
charset_set=$(grep -q "^${desired_charset}" ${config_file} && echo "true" || echo "false")
collation_set=$(grep -q "^${desired_collation}" ${config_file} && echo "true" || echo "false")
auth_plugin_set=$(grep -q "^${desired_auth_plugin}" ${config_file} && echo "true" || echo "false")

# Check if any changes are needed
changes_needed=false

if [ "$sql_mode_set" = "false" ]; then
    print info "Need to set SQL mode"
    changes_needed=true
fi

if [ "$innodb_strict_mode_set" = "false" ]; then
    print info "Need to set InnoDB strict mode"
    changes_needed=true
fi

if [ "$charset_set" = "false" ]; then
    print info "Need to set character set"
    changes_needed=true
fi

if [ "$collation_set" = "false" ]; then
    print info "Need to set collation"
    changes_needed=true
fi

if [ "$auth_plugin_set" = "false" ]; then
    print info "Need to set authentication plugin"
    changes_needed=true
fi

if [ "$changes_needed" = "true" ]; then
    print info "Changes needed. Updating MySQL configuration..."

    # Create a temporary file for the new configuration
    temp_file=$(mktemp)

    # Process the configuration file line by line
    while IFS= read -r line; do
        # Check if the line should be commented out and replaced
        if [[ "$line" =~ ^[[:space:]]*sql_mode[[:space:]]*= && "$sql_mode_set" = "false" ]]; then
            echo "# $line" >>"$temp_file"
            echo "$desired_sql_mode" >>"$temp_file"
            sql_mode_set="true"
        elif [[ "$line" =~ ^[[:space:]]*innodb_strict_mode[[:space:]]*= && "$innodb_strict_mode_set" = "false" ]]; then
            echo "# $line" >>"$temp_file"
            echo "$desired_innodb_strict_mode" >>"$temp_file"
            innodb_strict_mode_set="true"
        elif [[ "$line" =~ ^[[:space:]]*character-set-server[[:space:]]*= && "$charset_set" = "false" ]]; then
            echo "# $line" >>"$temp_file"
            echo "$desired_charset" >>"$temp_file"
            charset_set="true"
        elif [[ "$line" =~ ^[[:space:]]*collation-server[[:space:]]*= && "$collation_set" = "false" ]]; then
            echo "# $line" >>"$temp_file"
            echo "$desired_collation" >>"$temp_file"
            collation_set="true"
        elif [[ "$line" =~ ^[[:space:]]*default_authentication_plugin[[:space:]]*= && "$auth_plugin_set" = "false" ]]; then
            echo "# $line" >>"$temp_file"
            echo "$desired_auth_plugin" >>"$temp_file"
            auth_plugin_set="true"
        else
            # If it's not one of the lines we're looking to replace, keep it as is
            echo "$line" >>"$temp_file"
        fi

        # If the line contains skip-external-locking or mysqlx-bind-address and we haven't added our settings yet,
        # add them after this line
        if [[ "$line" =~ skip-external-locking|mysqlx-bind-address ]]; then
            if [ "$sql_mode_set" = "false" ]; then
                echo "$desired_sql_mode" >>"$temp_file"
                sql_mode_set="true"
            fi
            if [ "$innodb_strict_mode_set" = "false" ]; then
                echo "$desired_innodb_strict_mode" >>"$temp_file"
                innodb_strict_mode_set="true"
            fi
            if [ "$charset_set" = "false" ]; then
                echo "$desired_charset" >>"$temp_file"
                charset_set="true"
            fi
            if [ "$collation_set" = "false" ]; then
                echo "$desired_collation" >>"$temp_file"
                collation_set="true"
            fi
            if [ "$auth_plugin_set" = "false" ]; then
                echo "$desired_auth_plugin" >>"$temp_file"
                auth_plugin_set="true"
            fi
        fi
    done <"$config_file"

    # If we reached the end of the file and still haven't added all our settings, add them at the end
    if [ "$sql_mode_set" = "false" ]; then
        echo "$desired_sql_mode" >>"$temp_file"
    fi
    if [ "$innodb_strict_mode_set" = "false" ]; then
        echo "$desired_innodb_strict_mode" >>"$temp_file"
    fi
    if [ "$charset_set" = "false" ]; then
        echo "$desired_charset" >>"$temp_file"
    fi
    if [ "$collation_set" = "false" ]; then
        echo "$desired_collation" >>"$temp_file"
    fi
    if [ "$auth_plugin_set" = "false" ]; then
        echo "$desired_auth_plugin" >>"$temp_file"
    fi

    # Move the temporary file to the configuration file
    mv "$temp_file" "$config_file"

    print info "Restarting MySQL service to apply changes..."
    service mysql restart || {
        mv "$backup_file" "$config_file"
        print error "Failed to restart MySQL. Exiting..."
        log_action "Failed to restart MySQL. Exiting..."
        exit 1
    }

    print success "MySQL configuration updated successfully."
    log_action "MySQL configuration updated successfully."
else
    print info "No MySQL configuration changes needed."
    log_action "No MySQL configuration changes needed."
    # Remove the backup since we didn't make any changes
    rm "$backup_file"
fi

if [ -f ${backup_file} ]; then
    print info "Removing backup file $backup_file"
    rm "$backup_file"
fi

print info "Applying SET PERSIST sql_mode='' to override MySQL defaults..."

# Determine which password to use
if [ -n "$mysql_root_password" ]; then
    mysql_pw="$mysql_root_password"
    print debug "Using user-provided MySQL root password"
elif [ -f "${lis_path}/configs/config.production.php" ]; then
    mysql_pw=$(extract_mysql_password_from_config "${lis_path}/configs/config.production.php")
    print debug "Extracted MySQL root password from config.production.php"
else
    print error "MySQL root password not provided and config.production.php not found."
    exit 1
fi

if [ -z "$mysql_pw" ]; then
    print warning "Password in config file is empty or missing. Prompting for manual entry..."
    read -sp "Please enter MySQL root password: " mysql_pw
    echo
fi

persist_result=$(MYSQL_PWD="${mysql_pw}" mysql -u root -e "SET PERSIST sql_mode = '';" 2>&1)
persist_status=$?

if [ $persist_status -eq 0 ]; then
    print success "Successfully persisted sql_mode=''"
    log_action "Applied SET PERSIST sql_mode = '';"
else
    print warning "SET PERSIST failed: $persist_result"
    log_action "SET PERSIST sql_mode failed: $persist_result"
fi

# Check for Apache
if ! command -v apache2ctl &>/dev/null; then
    print error "Apache is not installed. Please first run the setup script."
    log_action "Apache is not installed. Please first run the setup script."
    exit 1
fi

# Check for PHP
if ! command -v php &>/dev/null; then
    print error "PHP is not installed. Please first run the setup script."
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
    print info "Current PHP version is ${php_version}. Switching to PHP ${desired_php_version}."

    # Switch to PHP 8.2
    switch-php ${desired_php_version}

    if [ $? -ne 0 ]; then
        print error "Failed to switch to PHP ${desired_php_version}. Please check your setup."
        exit 1
    fi
else
    print success "PHP version is already ${desired_php_version}."
fi

# Modify php.ini as needed
print header "Configuring PHP"

desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING"
desired_post_max_size="post_max_size = 1G"
desired_upload_max_filesize="upload_max_filesize = 1G"
desired_strict_mode="session.use_strict_mode = 1"

# Function to modify PHP ini files with proper idempotency
update_php_ini() {
    local ini_file=$1
    local timestamp=$(date +%Y%m%d%H%M%S)
    local backup_file="${ini_file}.bak.${timestamp}"
    local changes_needed=false

    print info "Checking PHP settings in $ini_file..."

    # Check if settings are already correctly set
    er_set=$(grep -q "^${desired_error_reporting}$" "$ini_file" && echo "true" || echo "false")
    pms_set=$(grep -q "^${desired_post_max_size}$" "$ini_file" && echo "true" || echo "false")
    umf_set=$(grep -q "^${desired_upload_max_filesize}$" "$ini_file" && echo "true" || echo "false")
    sm_set=$(grep -q "^${desired_strict_mode}$" "$ini_file" && echo "true" || echo "false")

    # Determine if changes are needed
    if [ "$er_set" = "false" ] || [ "$pms_set" = "false" ] || [ "$umf_set" = "false" ] || [ "$sm_set" = "false" ]; then
        changes_needed=true
        cp "$ini_file" "$backup_file"
        print info "Changes needed. Backup created at $backup_file"
    fi

    if [ "$changes_needed" = "true" ]; then
        # Create a temporary file
        temp_file=$(mktemp)

        # Process the file line by line
        while IFS= read -r line; do
            # (line replacements...)
        done <"$ini_file"

        # Replace the original ini file
        mv "$temp_file" "$ini_file"
        print success "Updated PHP settings in $ini_file"

        # Now remove the backup file
        if [ -f "$backup_file" ]; then
            rm "$backup_file"
            print info "Removed backup file $backup_file"
        fi
    else
        print info "PHP settings are already correctly set in $ini_file"
    fi
}


# Apply changes to PHP configuration files
for phpini in /etc/php/${php_version}/apache2/php.ini /etc/php/${php_version}/cli/php.ini; do
    if [ -f "$phpini" ]; then
        update_php_ini "$phpini"
    else
        print warning "PHP configuration file not found: $phpini"
    fi
done

# Check for Composer
if ! command -v composer &>/dev/null; then
    echo "Composer is not installed. Please first run the setup script."
    log_action "Composer is not installed. Please first run the setup script."
    exit 1
fi

# Proceed with the rest of the script if all checks pass

print success "All system checks passed. Continuing with the update..."

# Update Ubuntu Packages
if [ "$skip_ubuntu_updates" = false ]; then
    print header "Updating Ubuntu packages"
    export DEBIAN_FRONTEND=noninteractive
    export NEEDRESTART_SUSPEND=1

    apt-get update
    apt-get -o Dpkg::Options::="--force-confdef" \
        -o Dpkg::Options::="--force-confold" \
        upgrade -y

    if ! grep -q "ondrej/apache2" /etc/apt/sources.list /etc/apt/sources.list.d/*; then
        add-apt-repository ppa:ondrej/apache2 -y
        apt-get upgrade apache2 -y
    fi

    print info "Configuring any partially installed packages..."
    export DEBIAN_FRONTEND=noninteractive
    dpkg --configure -a
fi

# Clean up
export DEBIAN_FRONTEND=noninteractive
apt-get -y autoremove

if [ "$skip_ubuntu_updates" = false ]; then
    print info "Installing basic packages..."
    apt-get install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools sed mawk magic-wormhole openssh-server libsodium-dev mosh
fi

# Check if SSH service is enabled
if ! systemctl is-enabled ssh >/dev/null 2>&1; then
    print info "Enabling SSH service..."
    systemctl enable ssh
else
    print success "SSH service is already enabled."
fi

# Check if SSH service is running
if ! systemctl is-active ssh >/dev/null 2>&1; then
    print info "Starting SSH service..."
    systemctl start ssh
else
    print success "SSH service is already running."
fi

log_action "Ubuntu packages updated/installed."

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

# set_permissions "${lis_path}" "quick"
set_permissions "${lis_path}/logs" "full"

spinner() {
    local pid=$1
    local delay=0.75
    local spinstr='|/-\'
    while kill -0 "$pid" 2>/dev/null; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

# Function to list databases and get the database list
get_databases() {
    print info "Fetching available databases..."
    local IFS=$'\n'
    # Exclude the databases you do not want to back up from the list
    databases=($(mysql -u root -p"${mysql_root_password}" -e "SHOW DATABASES;" | sed 1d | egrep -v 'information_schema|mysql|performance_schema|sys|phpmyadmin'))
    local -i cnt=1
    for db in "${databases[@]}"; do
        echo "$cnt) $db"
        let cnt++
    done
}

# Function to back up selected databases
backup_database() {
    local IFS=$'\n'
    # Now we use the 'databases' array from 'get_databases' function instead of querying again
    local db_list=("${databases[@]}")
    local timestamp=$(date +%Y%m%d-%H%M%S) # Adding timestamp with hours, minutes, and seconds
    for i in "$@"; do
        local db="${db_list[$i]}"
        print info "Backing up database: $db"
        mysqldump -u root -p"${mysql_root_password}" "$db" | gzip >"${backup_location}/${db}_${timestamp}.sql.gz"
        if [[ $? -eq 0 ]]; then
            print success "Backup of $db completed successfully."
            log_action "Backup of $db completed successfully."
        else
            print error "Failed to backup database: $db"
            log_action "Failed to backup database: $db"
        fi
    done
}
if [ "$skip_backup" = false ]; then

    # Ask the user if they want to backup the database
    if ask_yes_no "Do you want to backup the database" "no"; then
        # Ask for MySQL root password
        echo "Please enter your MySQL root password:"
        read -s mysql_root_password

        # Ask for the backup location and create it if it doesn't exist
        read -p "Enter the backup location [press enter to select /var/intelis-backup/db/]: " backup_location
        backup_location="${backup_location:-/var/intelis-backup/db/}"

        # Create the backup directory if it does not exist
        if [ ! -d "$backup_location" ]; then
            print info "Backup directory does not exist. Creating it now..."
            mkdir -p "$backup_location"
            if [ $? -ne 0 ]; then
                print error "Failed to create backup directory. Please check your permissions."
                exit 1
            fi
        fi

        # Change to the backup directory
        cd "$backup_location" || exit

        # List databases and ask for user choice
        get_databases
        echo "Enter the numbers of the databases you want to backup, separated by space or comma, or type 'all' for all databases:"
        read -r input_selections

        # Convert input selection to array indexes
        selected_indexes=()
        if [[ "$input_selections" == "all" ]]; then
            selected_indexes=("${!databases[@]}")
        else
            # Split input by space and comma
            IFS=', ' read -ra selections <<<"$input_selections"

            for selection in "${selections[@]}"; do
                if [[ "$selection" =~ ^[0-9]+$ ]]; then
                    # Subtract 1 to convert from human-readable number to zero-indexed array
                    selected_indexes+=($(($selection - 1)))
                else
                    echo "Invalid selection: $selection. Ignoring."
                fi
            done
        fi

        # Backup the selected databases
        backup_database "${selected_indexes[@]}"
        log_action "Database backup completed."
    else
        print info "Skipping database backup as per user request."
        log_action "Skipping database backup as per user request."
    fi

    # Ask the user if they want to backup the LIS folder
    if ask_yes_no "Do you want to backup the LIS folder before updating?" "no"; then
        # Backup Old LIS Folder
        print info "Backing up old LIS folder..."
        timestamp=$(date +%Y%m%d-%H%M%S) # Using this timestamp for consistency with database backup filenames
        backup_folder="/var/intelis-backup/www/intelis-backup-$timestamp"
        mkdir -p "${backup_folder}"
        rsync -a --delete --exclude "public/temporary/" --inplace --whole-file --info=progress2 "${lis_path}/" "${backup_folder}/" &
        rsync_pid=$!           # Save the process ID of the rsync command
        spinner "${rsync_pid}" # Start the spinner
        log_action "LIS folder backed up to ${backup_folder}"
    else
        print info "Skipping LIS folder backup as per user request."
        log_action "Skipping LIS folder backup as per user request."
    fi
fi

if [ -d "${lis_path}/run-once" ]; then
    rm -rf "${lis_path}/run-once"
fi

print info "Calculating checksums of current composer files..."
CURRENT_COMPOSER_JSON_CHECKSUM="none"
CURRENT_COMPOSER_LOCK_CHECKSUM="none"

if [ -f "${lis_path}/composer.json" ]; then
    CURRENT_COMPOSER_JSON_CHECKSUM=$(md5sum "${lis_path}/composer.json" | awk '{print $1}')
    print info "Current composer.json checksum: ${CURRENT_COMPOSER_JSON_CHECKSUM}"
fi

if [ -f "${lis_path}/composer.lock" ]; then
    CURRENT_COMPOSER_LOCK_CHECKSUM=$(md5sum "${lis_path}/composer.lock" | awk '{print $1}')
    print info "Current composer.lock checksum: ${CURRENT_COMPOSER_LOCK_CHECKSUM}"
fi

print header "Downloading LIS"

# Download the tar.gz file in background

# Example check before installing (can be placed before the main install block)
if ! command -v aria2c &>/dev/null; then
    apt-get update
    apt-get install -y aria2
    if ! command -v aria2c &>/dev/null; then
        print error "Failed to install required packages. Exiting."
        exit 1
    fi
fi

# Run aria2c in the background, capturing its PID
# Using options for potentially faster download (-x, -s)
# Using -o to specify the output filename
# Using --summary-interval=0 to minimize aria2c's own console output
# to avoid interfering with the shell spinner
aria2c -x 5 -s 5 --summary-interval=0 -o master.tar.gz \
    https://codeload.github.com/deforay/vlsm/tar.gz/refs/heads/master &
download_pid=$! # Save aria2c PID

# Show the spinner while the download runs
spinner "${download_pid}" # Spinner tracks download

# Wait for the download process to finish and capture its exit status
wait ${download_pid}
download_status=$? # Capture the exit status

# Check if the download was successful (exit code 0 means success)
# Keep the messages generic
if [ $download_status -ne 0 ]; then
    print error "Download failed with status ${download_status}"
    # Handle the error, maybe exit
    exit 1
else
    print success "Download completed successfully."
fi

# Extract the tar.gz file into temporary directory
temp_dir=$(mktemp -d)
print info "Extracting files from master.tar.gz..."

tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!           # Save tar PID
spinner "${tar_pid}" # Spinner tracks extraction
wait ${tar_pid}      # Wait for extraction to finish

# Copy the unzipped content to the /var/www/vlsm directory, overwriting any existing files
# Find all symlinks in the destination directory and create an exclude pattern
exclude_options=""
# Initialize symlinks_found to 0 before using it
symlinks_found=0
for symlink in $(find "$lis_path" -type l -not -path "*/\.*" 2>/dev/null); do
    # Extract the relative path from the full path
    rel_path=${symlink#"$lis_path/"}
    exclude_options="$exclude_options --exclude '$rel_path'"
    print debug "Detected symlink: $rel_path"
    symlinks_found=$((symlinks_found + 1))
done

print info "Found $symlinks_found symlinks that will be preserved."

# Use the dynamically generated exclude options in the rsync command
eval rsync -a --inplace --whole-file $exclude_options --info=progress2 "$temp_dir/vlsm-master/" "$lis_path/" &
rsync_pid=$!           # Save the process ID of the rsync command
spinner "${rsync_pid}" # Start the spinner
wait ${rsync_pid}      # Wait for the rsync process to finish
rsync_status=$?        # Capture the exit status after waiting

# Check if rsync command succeeded
if [ $rsync_status -ne 0 ]; then
    print error "Error occurred during rsync. Logging and continuing..."
    log_action "Error during rsync operation. Path was: $lis_path"
else
    print success "Files copied successfully, preserving symlinks where necessary."
    log_action "Files copied successfully."
fi

# Remove the empty directory and the downloaded tar file
# Remove the empty directory if it exists
if [ -d "$temp_dir/vlsm-master/" ]; then
    rm -rf "$temp_dir/vlsm-master/"
fi

# Remove the downloaded tar file if it exists
if [ -f master.tar.gz ]; then
    rm master.tar.gz
fi

print success "LIS copied to ${lis_path}."
log_action "LIS copied to ${lis_path}."

# Set proper permissions
set_permissions "${lis_path}" "quick"

# Check for config.production.php and its content
config_file="${lis_path}/configs/config.production.php"
dist_config_file="${lis_path}/configs/config.production.dist.php"

if [ -f "${config_file}" ]; then
    # Check if the file contains the required string
    if ! grep -q "\$systemConfig\['database'\]\['host'\]" "${config_file}"; then
        # Backup config.production.php
        mv "${config_file}" "${config_file}_backup_$(date +%Y%m%d_%H%M%S)"

        # Copy from config.production.dist.php to config.production.php
        cp "${dist_config_file}" "${config_file}"

        update_configuration
    else
        echo "Configuration file already contains required settings."
    fi
else
    echo "Configuration file does not exist. Creating a new one from the distribution file."
    cp "${dist_config_file}" "${config_file}"

    update_configuration
fi

# Check if the cache_di setting is set to true
if grep -q "\['cache_di'\] => false" "${config_file}"; then
    sed -i "s|\('cache_di' => \)false,|\1true,|" "${config_file}"
fi

# Run Composer Install as www-data
print header "Running composer operations"
cd "${lis_path}"

# Configure composer timeout regardless of installation path
sudo -u www-data composer config process-timeout 30000
sudo -u www-data composer clear-cache

# Replace the checksum comparison part with this improved version:

echo "Checking if composer dependencies need updating..."
NEED_FULL_INSTALL=false

# Check if the vendor directory exists
if [ ! -d "${lis_path}/vendor" ]; then
    print warning "Vendor directory doesn't exist. Full installation needed."
    NEED_FULL_INSTALL=true
else
    # Calculate new checksums
    NEW_COMPOSER_JSON_CHECKSUM="none"
    NEW_COMPOSER_LOCK_CHECKSUM="none"

    if [ -f "${lis_path}/composer.json" ]; then
        NEW_COMPOSER_JSON_CHECKSUM=$(md5sum "${lis_path}/composer.json" 2>/dev/null | awk '{print $1}')
        print info "New composer.json checksum: ${NEW_COMPOSER_JSON_CHECKSUM}"
    else
        print warning "Warning: composer.json is missing after extraction. Full installation needed."
        NEED_FULL_INSTALL=true
    fi

    if [ -f "${lis_path}/composer.lock" ] && [ "$NEED_FULL_INSTALL" = false ]; then
        NEW_COMPOSER_LOCK_CHECKSUM=$(md5sum "${lis_path}/composer.lock" 2>/dev/null | awk '{print $1}')
        print info "New composer.lock checksum: ${NEW_COMPOSER_LOCK_CHECKSUM}"
    else
        print warning "Warning: composer.lock is missing after extraction. Full installation needed."
        NEED_FULL_INSTALL=true
    fi

    # Only do checksum comparison if we haven't already determined we need a full install
    if [ "$NEED_FULL_INSTALL" = false ]; then
        # Compare checksums - only if both files existed before and after
        if [ "$CURRENT_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
            [ "$NEW_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$NEW_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
            [ "$CURRENT_COMPOSER_JSON_CHECKSUM" != "$NEW_COMPOSER_JSON_CHECKSUM" ] ||
            [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" != "$NEW_COMPOSER_LOCK_CHECKSUM" ]; then
            print warning "Composer files have changed or were missing. Full installation needed."
            NEED_FULL_INSTALL=true
        else
            print info "Composer files haven't changed. Skipping full installation."
            NEED_FULL_INSTALL=false
        fi
    fi
fi

# Download vendor.tar.gz if needed
if [ "$NEED_FULL_INSTALL" = true ]; then
    echo "Dependency update needed. Checking for vendor packages..."
    if curl --output /dev/null --silent --head --fail "https://github.com/deforay/vlsm/releases/download/vendor-latest/vendor.tar.gz"; then
        echo "Vendor package found. Downloading..."
        wget -c -q --show-progress --progress=dot:giga -O vendor.tar.gz https://github.com/deforay/vlsm/releases/download/vendor-latest/vendor.tar.gz || {
            echo "Failed to download vendor.tar.gz"
            exit 1
        }

        echo "Downloading checksum..."
        wget -c -q --show-progress --progress=dot:giga -O vendor.tar.gz.md5 https://github.com/deforay/vlsm/releases/download/vendor-latest/vendor.tar.gz.md5 || {
            echo "Failed to download vendor.tar.gz.md5"
            exit 1
        }

        echo "Verifying checksum..."
        md5sum -c vendor.tar.gz.md5 || {
            echo "Checksum verification failed"
            exit 1
        }

        echo "Extracting files from vendor.tar.gz..."
        tar -xzf vendor.tar.gz -C "${lis_path}" &
        vendor_tar_pid=$!
        spinner "${vendor_tar_pid}"
        wait ${vendor_tar_pid}
        vendor_tar_status=$?
        if [ $vendor_tar_status -ne 0 ]; then
            echo "Failed to extract vendor.tar.gz"
            exit 1
        fi

        rm vendor.tar.gz
        rm vendor.tar.gz.md5
        # Fix permissions on the vendor directory
        find "${lis_path}/vendor" -exec chown www-data:www-data {} \; 2>/dev/null || true
        chmod -R 755 "${lis_path}/vendor" 2>/dev/null || true

        echo "Vendor files successfully installed"

        # Update the composer.lock file to match the current state
        sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev
    else
        echo "Vendor package not found in GitHub releases. Proceeding with regular composer install."

        # Perform full install if vendor.tar.gz isn't available
        sudo -u www-data composer install --prefer-dist --no-dev
    fi
else
    print info "Dependencies are up to date. Skipping vendor download."
fi

# Always generate the optimized autoloader, regardless of install path
sudo -u www-data composer dump-autoload -o

print success "Composer operations completed."
log_action "Composer operations completed."

# Run the database migrations and other post-update tasks
print header "Running database migrations and other post-update tasks"
sudo -u www-data composer post-update &
pid=$!
spinner "$pid"
wait $pid

print success "Database migrations and post-update tasks completed."
log_action "Database migrations and post-update tasks completed."

if [ -d "${lis_path}/run-once" ]; then
    # Check if there are any PHP scripts in the run-once directory
    run_once_scripts=("${lis_path}/run-once/"*.php)

    if [ -e "${run_once_scripts[0]}" ]; then
        for script in "${run_once_scripts[@]}"; do
            php $script
        done
    else
        print error "No scripts found in the run-once directory."
        log_action "No scripts found in the run-once directory."
    fi
fi

# Ask User to Run 'maintenance' Scripts
if ask_yes_no "Do you want to run maintenance scripts?" "no"; then
    # List the files in maintenance directory
    echo "Available maintenance scripts to run:"
    files=("${lis_path}/maintenance/"*.php)
    for i in "${!files[@]}"; do
        filename=$(basename "${files[$i]}")
        echo "$((i + 1))) $filename"
    done

    # Ask which files to run
    echo "Enter the numbers of the scripts you want to run separated by commas (e.g., 1,2,4) or type 'all' to run them all."
    read -r files_to_run

    # Run selected files
    if [[ "$files_to_run" == "all" ]]; then
        for file in "${files[@]}"; do
            print info "Running $file..."
            sudo -u www-data php "$file"
        done
    else
        IFS=',' read -ra ADDR <<<"$files_to_run"
        for i in "${ADDR[@]}"; do
            # Remove any spaces in the input and correct the array index
            i=$(echo "$i" | xargs)
            file_index=$((i - 1))

            # Check if the selected index is within the range of available files
            if [[ $file_index -ge 0 ]] && [[ $file_index -lt ${#files[@]} ]]; then
                file="${files[$file_index]}"
                echo "Running $file..."
                sudo -u www-data php "$file"
            else
                print error "Invalid selection: $i. Please select a number between 1 and ${#files[@]}. Skipping."
            fi
        done
    fi
fi

# Run the PHP script for remote data sync
cd "${lis_path}"
echo "Running remote data sync script. Please wait..."
sudo -u www-data composer metadata-sync &
pid=$!
spinner "$pid"
wait $pid
print success "Remote data sync completed."
log_action "Remote data sync completed."

# The old startup.php file is no longer needed, but if it exists, make sure it is empty
if [ -f "${lis_path}/startup.php" ]; then
    rm "${lis_path}/startup.php"
    touch "${lis_path}/startup.php"
fi

if [ -f "${lis_path}/cache/CompiledContainer.php" ]; then
    rm "${lis_path}/cache/CompiledContainer.php"
fi

service apache2 restart

print success "Apache Restarted."
log_action "Apache Restarted."

# Set proper permissions
set_permissions "${lis_path}/logs" "full"
(print success "Setting final permissions in the background..." &&
    set_permissions "${lis_path}" "full" &&
    find "${lis_path}" -exec chown www-data:www-data {} \; 2>/dev/null || true) &
disown

print success "LIS update complete."
log_action "LIS update complete."
