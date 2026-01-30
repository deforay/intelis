#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/intelis-update https://raw.githubusercontent.com/deforay/intelis/master/scripts/upgrade.sh && sudo chmod +x /usr/local/bin/intelis-update
# sudo intelis-update
#
# Options:
#   -p PATH   Specify the LIS installation path (e.g., -p /var/www/intelis)
#   -A        Auto-detect and update ALL intelis installations in /var/www
#   -i        Interactive instance selection (use with -A to pick specific instances)
#   -s        Skip Ubuntu system updates
#   -b        Skip backup prompts
#
# Examples:
#   sudo intelis-update                      # Interactive single instance
#   sudo intelis-update -p /var/www/intelis  # Specific path
#   sudo intelis-update -A                   # Update all instances in /var/www
#   sudo intelis-update -A -i                # Detect instances, pick which to update
#   sudo intelis-update -A -s -b             # Non-interactive, update all instances

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Download and update shared-functions.sh
SHARED_FN_PATH="/usr/local/lib/intelis/shared-functions.sh"
SHARED_FN_URL="https://raw.githubusercontent.com/deforay/intelis/master/scripts/shared-functions.sh"

mkdir -p "$(dirname "$SHARED_FN_PATH")"

if wget -q -O "$SHARED_FN_PATH" "$SHARED_FN_URL"; then
    chmod +x "$SHARED_FN_PATH"
    echo "Downloaded shared-functions.sh."
else
    echo "Failed to download shared-functions.sh."
    if [ ! -f "$SHARED_FN_PATH" ]; then
        echo "shared-functions.sh missing. Cannot proceed."
        exit 1
    fi
fi

# Source the shared functions
source "$SHARED_FN_PATH"


prepare_system


DEFAULT_LIS_PATH_INTELIS="/var/www/intelis"
LEGACY_LIS_PATH_VLSM="/var/www/vlsm"

resolve_lis_path() {
    local provided="$1"

    # If user provided (-p or prompt) → always use that
    if [ -n "$provided" ]; then
        echo "$(to_absolute_path "$provided")"
        return 0
    fi

    # Otherwise: prefer new default, else fallback to legacy
    if [ -d "$DEFAULT_LIS_PATH_INTELIS" ]; then
        echo "$DEFAULT_LIS_PATH_INTELIS"
    elif [ -d "$LEGACY_LIS_PATH_VLSM" ]; then
        echo "$LEGACY_LIS_PATH_VLSM"
    else
        # Neither exists — still return new default, validation will catch it
        echo "$DEFAULT_LIS_PATH_INTELIS"
    fi
}

# Detect all valid intelis installations in a directory
detect_intelis_installations() {
    local search_dir="${1:-/var/www}"
    local found_paths=()

    for dir in "$search_dir"/*/; do
        [ -d "$dir" ] || continue
        # Remove trailing slash for cleaner paths
        dir="${dir%/}"
        if is_valid_application_path "$dir"; then
            found_paths+=("$dir")
        fi
    done

    printf '%s\n' "${found_paths[@]}"
}

# Initialize flags
skip_ubuntu_updates=false
skip_backup=false
auto_detect=false
interactive_select=false
lis_path=""
declare -a lis_paths=()

log_file="/tmp/intelis-upgrade-$(date +'%Y%m%d-%H%M%S').log"

# Parse command-line options
while getopts ":sbAip:" opt; do
    case $opt in
    s) skip_ubuntu_updates=true ;;
    b) skip_backup=true ;;
    A) auto_detect=true ;;
    i) interactive_select=true ;;
    p) lis_path="$OPTARG" ;;
        # Ignore invalid options silently
    esac
done

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

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

    # Escape password for sed replacement and PHP single-quoted strings
    escaped_mysql_root_password=$(escape_php_string_for_sed "${mysql_root_password}")

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

ensure_cache_di_true() {
    local file="$1"
    [ -f "$file" ] || { echo "config not found: $file" >&2; return 1; }

    # Ask PHP what the current value is (or if it's missing)
    local state
    state=$(php -r "
        \$c = require '$file';
        if (!is_array(\$c)) { echo 'ERR'; exit(0); }
        if (!isset(\$c['system']['cache_di'])) { echo 'MISSING'; exit(0); }
        echo (\$c['system']['cache_di'] ? 'TRUE' : 'FALSE');
    " 2>/dev/null)

    case "$state" in
        TRUE)
            echo "cache_di already true"
            ;;
        FALSE)
            echo "Setting cache_di=true (was false)"
            cp "$file" "$file.bak.$(date +%Y%m%d%H%M%S)"
            # Flip only the specific assignment; tolerate whitespace
            sed -i -E "s|(\['system'\]\['cache_di'\]\s*=\s*)false\s*;|\1true;|g" "$file"
            ;;
        MISSING)
            echo "Adding cache_di=true (missing)"
            cp "$file" "$file.bak.$(date +%Y%m%d%H%M%S)"
            # Insert just before 'return $systemConfig;'. If not found, append.
            awk -v ins="\$systemConfig['system']['cache_di'] = true;" '
                BEGIN{done=0}
                /^\s*return\s+\$systemConfig\s*;/{ if(!done){print ins; done=1} }
                { print }
                END{ if(!done) print ins }
            ' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
            ;;
        *)
            echo "Could not evaluate config via PHP; skipping change" >&2
            return 2
            ;;
    esac
}


# If source exists → rsync its contents to destination, then delete the source.
# If source doesn't exist → silently skip.
move_dir_fully() {
    local src="$1"
    local dst="$2"

    [ -d "$src" ] || return 0  # silently skip if missing

    mkdir -p "$dst"

    # Copy everything safely (preserve perms/ownerships)
    rsync -a "$src"/ "$dst"/ >/dev/null 2>&1

    # Remove the original directory entirely
    rm -rf "$src"

    # Ensure destination stays tracked
    touch "$dst/.hgkeep" 2>/dev/null || true
    chown -R www-data:www-data "$dst" 2>/dev/null || true
    chmod -R u=rwX,g=rX,o= "$dst" 2>/dev/null || true
}



# Save the current trap settings
current_trap=$(trap -p ERR)

# Disable the error trap temporarily
trap - ERR

# Handle path resolution based on mode
if [ "$auto_detect" = true ]; then
    # Warn if -p was also specified
    if [ -n "$lis_path" ]; then
        print warning "-A flag specified, ignoring -p ${lis_path}"
    fi
    # Auto-detect mode: scan /var/www for all valid installations
    print info "Scanning /var/www for intelis installations..."
    mapfile -t detected_paths < <(detect_intelis_installations /var/www)

    if [ ${#detected_paths[@]} -eq 0 ]; then
        print error "No valid intelis installations found in /var/www"
        log_action "Auto-detect found no valid installations"
        exit 1
    fi

    # Show numbered list
    print info "Found ${#detected_paths[@]} installation(s):"
    for i in "${!detected_paths[@]}"; do
        echo "$((i+1))) ${detected_paths[$i]}"
    done

    if [ "$interactive_select" = true ]; then
        # Interactive mode: let user pick which instances to update
        echo ""
        echo "Enter instance numbers to update (e.g., 1,2,3) or press Enter for all:"
        read -r selection

        if [ -z "$selection" ]; then
            lis_paths=("${detected_paths[@]}")
        else
            IFS=',' read -ra selected_nums <<< "$selection"
            for num in "${selected_nums[@]}"; do
                num=$(echo "$num" | xargs)  # trim whitespace
                idx=$((num - 1))
                if [[ $idx -ge 0 ]] && [[ $idx -lt ${#detected_paths[@]} ]]; then
                    lis_paths+=("${detected_paths[$idx]}")
                fi
            done
        fi

        if [ ${#lis_paths[@]} -eq 0 ]; then
            print error "No valid instances selected"
            exit 1
        fi
    else
        # Non-interactive: use all detected instances
        lis_paths=("${detected_paths[@]}")
    fi

    print info "Will update ${#lis_paths[@]} instance(s):"
    for p in "${lis_paths[@]}"; do
        print info "  - $p"
    done
    log_action "Selected ${#lis_paths[@]} installations for update: ${lis_paths[*]}"
elif [ -n "$lis_path" ]; then
    # Single path provided via -p flag
    lis_path="$(resolve_lis_path "$lis_path")"
    if ! is_valid_application_path "$lis_path"; then
        print error "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
        log_action "Invalid LIS path specified: $lis_path"
        exit 1
    fi
    lis_paths=("$lis_path")
    print info "LIS path is set to ${lis_path}"
    log_action "LIS path is set to ${lis_path}"
else
    # Interactive prompt for path (existing behavior)
    echo "Enter the LIS installation path [press enter for /var/www/intelis]: "
    if read -t 60 lis_path && [ -n "$lis_path" ]; then
        : # user provided a value; resolver will honor it as-is
    else
        lis_path=""  # empty => resolver will auto-pick intelis, else vlsm
    fi
    lis_path="$(resolve_lis_path "$lis_path")"
    if ! is_valid_application_path "$lis_path"; then
        print error "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
        log_action "Invalid LIS path specified: $lis_path"
        exit 1
    fi
    lis_paths=("$lis_path")
    print info "LIS path is set to ${lis_path}"
    log_action "LIS path is set to ${lis_path}"
fi

# For single-instance mode, set lis_path for backward compatibility with existing code
if [ ${#lis_paths[@]} -eq 1 ]; then
    lis_path="${lis_paths[0]}"
fi

# Restore the previous error trap
eval "$current_trap"

# Check for MySQL
if ! command -v mysql &>/dev/null; then
    print error "MySQL is not installed. Please first run the setup script."
    log_action "MySQL is not installed. Please first run the setup script."
    exit 1
fi

# Clean up vim swap files and setup MySQL config (use first instance for config)
first_lis_path="${lis_paths[0]}"
for p in "${lis_paths[@]}"; do
    find "$p" -name ".*.swp" -delete 2>/dev/null || true
done
setup_mysql_config "${first_lis_path}/configs/config.production.php" && print info "MySQL config ready"

MYSQL_CONFIG_FILE="/etc/mysql/mysql.conf.d/mysqld.cnf"
backup_timestamp=$(date +%Y%m%d%H%M%S)
# Calculate total system memory in MB
total_mem_kb=$(grep MemTotal /proc/meminfo | awk '{print $2}')
total_mem_mb=$((total_mem_kb / 1024))
total_mem_gb=$((total_mem_mb / 1024))

# Calculate buffer pool size (70% of total RAM)
buffer_pool_size_gb=$((total_mem_gb * 70 / 100))

# Safety check for small RAM systems
if [ "$buffer_pool_size_gb" -lt 1 ]; then
    buffer_pool_size="512M"
else
    buffer_pool_size="${buffer_pool_size_gb}G"
fi

# Calculate other memory-related settings
# Scale these settings based on available memory
if [ $total_mem_gb -lt 8 ]; then
    # Low memory server
    join_buffer="1M"
    sort_buffer="2M"
    read_rnd_buffer="2M"
    read_buffer="1M"
    tmp_table="32M"
    max_heap="32M"
    log_file_size="256M"
    log_buffer="8M"
elif [ $total_mem_gb -lt 16 ]; then
    # Medium memory server
    join_buffer="2M"
    sort_buffer="2M"
    read_rnd_buffer="4M"
    read_buffer="1M"
    tmp_table="64M"
    max_heap="64M"
    log_file_size="512M"
    log_buffer="16M"
elif [ $total_mem_gb -lt 32 ]; then
    # High memory server
    join_buffer="4M"
    sort_buffer="4M"
    read_rnd_buffer="8M"
    read_buffer="2M"
    tmp_table="128M"
    max_heap="128M"
    log_file_size="1G"
    log_buffer="32M"
else
    # Very high memory server
    join_buffer="8M"
    sort_buffer="8M"
    read_rnd_buffer="16M"
    read_buffer="4M"
    tmp_table="256M"
    max_heap="256M"
    log_file_size="2G"
    log_buffer="64M"
fi

# Calculate max connections based on memory
# A rough estimate: 1GB = 100 connections
max_connections=$((total_mem_gb * 100))
# Cap maximum connections at 1000 for safety
if [ $max_connections -gt 1000 ]; then
    max_connections=1000
fi

# Calculate I/O capacity based on storage type
# Check if we're using SSD
if [ -d "/sys/block" ]; then
    # Detect if there's an SSD in the system
    ssd_detected=false
    for device in /sys/block/*/queue/rotational; do
        if [ -e "$device" ] && [ "$(cat "$device")" = "0" ]; then
            ssd_detected=true
            break
        fi
    done

    if [ "$ssd_detected" = true ]; then
        io_capacity=2000  # Higher for SSD
    else
        io_capacity=500   # Lower for HDD
    fi
else
    # Default value if we can't detect
    io_capacity=1000
fi

# Create directory for slow query logs
mkdir -p /var/log/mysql
touch /var/log/mysql/mysql-slow.log
chown mysql:mysql /var/log/mysql/mysql-slow.log

# Detect MySQL version for version-specific settings
mysql_version=$(mysql -V | grep -oP '\d+\.\d+' | head -1 | tr -d '\n')
print info "MySQL version detected: ${mysql_version}"

# Determine appropriate collation based on MySQL version
if [[ $(echo "$mysql_version >= 8.0" | bc -l) -eq 1 ]]; then
    # MySQL 8.0+ supports the newer and better utf8mb4_0900_ai_ci collation
    mysql_collation="utf8mb4_0900_ai_ci"
    print info "Using MySQL 8.0+ optimized collation: utf8mb4_0900_ai_ci"
else
    # For MySQL 5.x, use the older utf8mb4_unicode_ci collation
    mysql_collation="utf8mb4_unicode_ci"
    print info "Using MySQL 5.x compatible collation: utf8mb4_unicode_ci"
fi

# --- define what we want ---
declare -A mysql_settings=(
    ["sql_mode"]=""
    ["innodb_strict_mode"]="0"
    ["character-set-server"]="utf8mb4"
    ["collation-server"]="${mysql_collation}"
    ["default_authentication_plugin"]="mysql_native_password"
    ["max_connect_errors"]="10000"
    ["innodb_buffer_pool_size"]="${buffer_pool_size}"
    ["innodb_file_per_table"]="1"
    ["innodb_flush_method"]="O_DIRECT"
    ["innodb_log_file_size"]="${log_file_size}"
    ["innodb_log_buffer_size"]="${log_buffer}"
    ["innodb_flush_log_at_trx_commit"]="2"
    ["innodb_io_capacity"]="${io_capacity}"
    ["join_buffer_size"]="${join_buffer}"
    ["sort_buffer_size"]="${sort_buffer}"
    ["read_rnd_buffer_size"]="${read_rnd_buffer}"
    ["read_buffer_size"]="${read_buffer}"
    ["tmp_table_size"]="${tmp_table}"
    ["max_heap_table_size"]="${max_heap}"
    ["max_connections"]="${max_connections}"
    ["thread_cache_size"]="16"
    ["slow_query_log"]="1"
    ["slow_query_log_file"]="/var/log/mysql/mysql-slow.log"
    ["long_query_time"]="2"
)

# MySQL version-specific settings
if [[ $(echo "$mysql_version < 8.0" | bc -l) -eq 1 ]]; then
    # MySQL 5.x settings
    mysql_settings["query_cache_type"]="0"
    mysql_settings["query_cache_size"]="0"

    # Additional settings for large workloads in MySQL 5.x
    mysql_settings["innodb_buffer_pool_instances"]="8"
    mysql_settings["innodb_read_io_threads"]="8"
    mysql_settings["innodb_write_io_threads"]="8"
else
    # MySQL 8.0+ settings
    # Query cache is removed in 8.0+
    mysql_settings["innodb_dedicated_server"]="1"  # Auto-tunes several parameters in MySQL 8+

    # Additional settings for large workloads in MySQL 8.0+
    mysql_settings["innodb_buffer_pool_instances"]="16"
    mysql_settings["innodb_read_io_threads"]="16"
    mysql_settings["innodb_write_io_threads"]="16"
    mysql_settings["innodb_adaptive_hash_index"]="1"

    # Performance schema settings for monitoring
    mysql_settings["performance_schema"]="1"
    mysql_settings["performance_schema_max_table_instances"]="1000"
fi

print info "RAM detected: ${total_mem_gb}GB - Configuring MySQL with buffer pool: ${buffer_pool_size}"

changes_needed=false

# --- dry-run check first ---
for setting in "${!mysql_settings[@]}"; do
    if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$MYSQL_CONFIG_FILE"; then
        changes_needed=true
        break
    fi
done

if [ "$changes_needed" = true ]; then
    print info "Changes needed. Backing up and updating MySQL config..."
    cp "$MYSQL_CONFIG_FILE" "${MYSQL_CONFIG_FILE}.bak.${backup_timestamp}"

    for setting in "${!mysql_settings[@]}"; do
        if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$MYSQL_CONFIG_FILE"; then
            # Comment existing wrong setting if found
            if grep -qE "^[[:space:]]*$setting[[:space:]]*=" "$MYSQL_CONFIG_FILE"; then
                sed -i "/^[[:space:]]*$setting[[:space:]]*=.*/s/^/#/" "$MYSQL_CONFIG_FILE"
            fi
            echo "$setting = ${mysql_settings[$setting]}" >>"$MYSQL_CONFIG_FILE"
        fi
    done

    print info "Restarting MySQL service to apply changes..."
    restart_service mysql || {
        print error "Failed to restart MySQL. Restoring backup and exiting..."
        mv "${MYSQL_CONFIG_FILE}.bak.${backup_timestamp}" "$MYSQL_CONFIG_FILE"
        restart_service mysql
        exit 1
    }

    print success "MySQL configuration updated successfully."

else
    print success "MySQL configuration already correct. No changes needed."
fi

# --- Always clean up old .bak files ---
find "$(dirname "$MYSQL_CONFIG_FILE")" -maxdepth 1 -type f -name "$(basename "$MYSQL_CONFIG_FILE").bak.*" -exec rm -f {} \;
print info "Removed all MySQL backup files matching *.bak.*"

print info "Applying SET PERSIST sql_mode='' to override MySQL defaults..."

# Determine which password to use (use first instance's config for multi-instance)
if [ -n "$mysql_root_password" ]; then
    mysql_pw="$mysql_root_password"
    print info "Using user-provided MySQL root password"
elif [ -f "${first_lis_path}/configs/config.production.php" ]; then
    mysql_pw=$(extract_mysql_password_from_config "${first_lis_path}/configs/config.production.php")
    print info "Extracted MySQL root password from config.production.php"
else
    print error "MySQL root password not provided and config.production.php not found."
    exit 1
fi

if [ -z "$mysql_pw" ]; then
    print warning "Password in config file is empty or missing. Prompting for manual entry..."
    read -r -sp "Please enter MySQL root password: " mysql_pw
    echo
fi

if persist_result=$(MYSQL_PWD="${mysql_pw}" mysql -u root -e "SET PERSIST sql_mode = '';" 2>&1); then
    persist_status=0
else
    persist_status=$?
fi

if [ $persist_status -eq 0 ]; then
    print success "Successfully persisted sql_mode=''"
    log_action "Applied SET PERSIST sql_mode = '';"
else
    print warning "SET PERSIST failed: $persist_result"
    log_action "SET PERSIST sql_mode failed: $persist_result"
fi

chmod 644 "$MYSQL_CONFIG_FILE"


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

# Check for PHP version 8.4.x
php_version=$(php -v | head -n 1 | grep -oP 'PHP \K([0-9]+\.[0-9]+)')
desired_php_version="8.4"

# Download and install switch-php script
ensure_switch_php

if [[ "${php_version}" != "${desired_php_version}" ]]; then
    print info "Current PHP version is ${php_version}. Switching to PHP ${desired_php_version}."

    # Switch to PHP 8.4
    switch-php ${desired_php_version} --fast

    if [ $? -ne 0 ]; then
        print error "Failed to switch to PHP ${desired_php_version}. Please check your setup."
        exit 1
    fi
else
    print success "PHP version is already ${desired_php_version}."
fi

php_version="${desired_php_version}"

# # --- Ensure SQLite extensions (pdo_sqlite + sqlite3) are present ---
# need_sqlite_fix=false
# php -m | grep -qi '^pdo_sqlite$' || need_sqlite_fix=true
# php -m | grep -qi '^sqlite3$'    || need_sqlite_fix=true

# if [ "$need_sqlite_fix" = true ]; then
#     print info "SQLite PHP extensions missing. Installing for PHP ${desired_php_version}..."

#     # First try your switcher (it installs the common extensions for that PHP)
#     if command -v switch-php >/dev/null 2>&1; then
#         switch-php "${desired_php_version}" || true
#     fi

#     # Re-check; if still missing, install the distro package that provides both
#     php -m | grep -qi '^pdo_sqlite$' && php -m | grep -qi '^sqlite3$' || {
#         apt-get update -y
#         apt-get install -y "php${desired_php_version}-sqlite3" || apt-get install -y php-sqlite3
#         # Enable for all SAPIs we care about
#         phpenmod -v "${desired_php_version}" -s ALL sqlite3 2>/dev/null || phpenmod sqlite3 2>/dev/null || true
#     }

#     # Final verification
#     if php -m | grep -qi '^pdo_sqlite$' && php -m | grep -qi '^sqlite3$'; then
#         print success "SQLite extensions are installed and enabled."
#     else
#         print error "Failed to install/enable SQLite extensions for PHP ${desired_php_version}."
#         #exit 1
#     fi

#     # Reload Apache so mod_php picks up the module
#     apache2ctl -k graceful || systemctl reload apache2 || systemctl restart apache2
#     else
#     print success "SQLite extensions already present."
# fi


# Ensure OPCache is installed and enabled
ensure_opcache

# Ensure Composer is installed
ensure_composer

# Configure PHP INI settings (uses shared function)
configure_php_ini "${php_version}"

# Validate Apache config and reload to apply PHP INI changes
if apache2ctl -t; then
    grep -q '^ServerName localhost$' /etc/apache2/conf-available/servername.conf 2>/dev/null || \
        { echo 'ServerName localhost' >/etc/apache2/conf-available/servername.conf && a2enconf -q servername; }

    systemctl reload apache2 || systemctl restart apache2
else
    print warning "apache2 config test failed; NOT reloading. Please fix and reload manually."
fi

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

    apt-get update --allow-releaseinfo-change
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

    # Clean up
    apt-get -y autoremove
    apt-get -y autoclean

    print info "Installing basic packages..."
    apt-get install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools sed mawk magic-wormhole openssh-server libsodium-dev mosh pigz gnupg
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

# Set initial log permissions for all instances
for p in "${lis_paths[@]}"; do
    set_permissions "$p/var/logs" "full"
done

# Function to list databases and get the database list
get_databases() {
    print info "Fetching available databases..."
    local IFS=$'\n'
    # Exclude the databases you do not want to back up from the list
    databases=($(mysql -u root -p"${mysql_root_password}" -e "SHOW DATABASES;" | sed 1d | egrep -v 'information_schema|mysql|performance_schema|sys|phpmyadmin'))
    local -i cnt=1
    for db in "${databases[@]}"; do
        echo "$cnt) $db"
        ((cnt++))
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
        read -r -s mysql_root_password

        # Ask for the backup location and create it if it doesn't exist
        read -r -p "Enter the backup location [press enter to select /var/intelis-backup/db/]: " backup_location
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

    # Ask the user if they want to backup the LIS folder(s)
    local backup_prompt="Do you want to backup the LIS folder before updating?"
    if [ ${#lis_paths[@]} -gt 1 ]; then
        backup_prompt="Do you want to backup all ${#lis_paths[@]} LIS folders before updating?"
    fi

    if ask_yes_no "$backup_prompt" "no"; then
        timestamp=$(date +%Y%m%d-%H%M%S)
        for p in "${lis_paths[@]}"; do
            local folder_name=$(basename "$p")
            local backup_folder="/var/intelis-backup/www/${folder_name}-backup-$timestamp"
            print info "Backing up $p..."
            mkdir -p "${backup_folder}"
            rsync -a --delete --exclude "public/temporary/" --inplace --whole-file --info=progress2 "$p/" "${backup_folder}/" &
            rsync_pid=$!
            spinner "${rsync_pid}"
            log_action "LIS folder $p backed up to ${backup_folder}"
        done
    else
        print info "Skipping LIS folder backup as per user request."
        log_action "Skipping LIS folder backup as per user request."
    fi
fi

# Download LIS package ONCE (shared across all instances)
print header "Downloading LIS"

download_file "master.tar.gz" "https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master" "Downloading LIS package..." || {
    print error "LIS download failed - cannot continue with update"
    log_action "LIS download failed - update aborted"
    exit 1
}

# Extract the tar.gz file into temporary directory ONCE
temp_dir=$(mktemp -d)
print info "Extracting files from master.tar.gz..."

tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!
spinner "${tar_pid}"
wait ${tar_pid}

print success "LIS package ready for deployment to ${#lis_paths[@]} instance(s)."

# Track which instances were updated for summary
declare -a updated_instances=()
declare -a failed_instances=()

# Function to upgrade a single instance
upgrade_instance() {
    local lis_path="$1"
    local instance_num="$2"
    local total_instances="$3"
    local temp_dir="$4"

    print header "Upgrading instance ${instance_num}/${total_instances}: ${lis_path}"
    log_action "Starting upgrade for instance: ${lis_path}"

    # Remove old run-once directory
    if [ -d "${lis_path}/run-once" ]; then
        rm -rf "${lis_path}/run-once"
    fi

    # Calculate checksums of current composer files for this instance
    local CURRENT_COMPOSER_JSON_CHECKSUM="none"
    local CURRENT_COMPOSER_LOCK_CHECKSUM="none"

    if [ -f "${lis_path}/composer.json" ]; then
        CURRENT_COMPOSER_JSON_CHECKSUM=$(md5sum "${lis_path}/composer.json" | awk '{print $1}')
    fi

    if [ -f "${lis_path}/composer.lock" ]; then
        CURRENT_COMPOSER_LOCK_CHECKSUM=$(md5sum "${lis_path}/composer.lock" | awk '{print $1}')
    fi

    # Find all symlinks in the destination directory and create an exclude pattern
    local exclude_options=""
    local symlinks_found=0
    for symlink in $(find "$lis_path" -type l -not -path "*/\.*" 2>/dev/null); do
        local rel_path=${symlink#"$lis_path/"}
        exclude_options="$exclude_options --exclude '$rel_path'"
        symlinks_found=$((symlinks_found + 1))
    done

    if [ $symlinks_found -gt 0 ]; then
        print info "Preserving $symlinks_found symlinks."
    fi

    # Rsync from temp to this instance
    eval rsync -a --inplace --whole-file $exclude_options --info=progress2 "$temp_dir/intelis-master/" "$lis_path/" &
    local rsync_pid=$!
    spinner "${rsync_pid}"
    wait ${rsync_pid}
    local rsync_status=$?

    if [ $rsync_status -ne 0 ]; then
        print error "Error occurred during rsync for $lis_path"
        log_action "Error during rsync operation. Path was: $lis_path"
        return 1
    fi

    print success "Files copied to ${lis_path}."
    log_action "Files copied to ${lis_path}."

    # Migrate directories to var/ structure
    print info "Migrating directories to var/ structure..."

    # Ensure var/* root exists
    mkdir -p "${lis_path}/var" 2>/dev/null || true
    chown www-data:www-data "${lis_path}/var" 2>/dev/null || true

    # Define directories to migrate
    declare -A dir_migrations=(
        ["${lis_path}/logs"]="logs"
        ["${lis_path}/audit-trail"]="audit-trail"
        ["${lis_path}/cache"]="cache"
        ["${lis_path}/metadata"]="metadata"
        ["${lis_path}/public/uploads/track-api"]="track-api"
    )

    declare -a dir_migration_pids=()
    declare -A dir_migration_labels=()

    # Migrate each directory with progress indication
    for src_dir in "${!dir_migrations[@]}"; do
        local dest_name="${dir_migrations[$src_dir]}"
        local dest_dir="${lis_path}/var/${dest_name}"

        if [ -d "$src_dir" ]; then
            (
                move_dir_fully "$src_dir" "$dest_dir"
            ) &
            local migration_pid=$!
            dir_migration_pids+=("${migration_pid}")
            dir_migration_labels["${migration_pid}"]="${dest_name}"
        fi
    done

    # Set proper permissions
    set_permissions "${lis_path}" "quick"

    # Make intelis command globally accessible (only for first instance)
    if [ "$instance_num" -eq 1 ]; then
        print info "Setting up intelis command..."
        local TARGET="/usr/local/bin/intelis"
        local SOURCE="${lis_path}/intelis"

        if [ -f "${SOURCE}" ]; then
            rm -f "${TARGET}" /usr/bin/intelis 2>/dev/null || true
            chmod 755 "${SOURCE}"
            ln -sf "${SOURCE}" "${TARGET}"
            print success "intelis command installed globally at ${TARGET}"
        fi
    fi

    # Check for config.production.php and its content
    local config_file="${lis_path}/configs/config.production.php"
    local dist_config_file="${lis_path}/configs/config.production.dist.php"

    if [ -f "${config_file}" ]; then
        if ! grep -q "\$systemConfig\['database'\]\['host'\]" "${config_file}"; then
            mv "${config_file}" "${config_file}_backup_$(date +%Y%m%d_%H%M%S)"
            cp "${dist_config_file}" "${config_file}"
            update_configuration
        fi
    else
        if [ -f "${dist_config_file}" ]; then
            cp "${dist_config_file}" "${config_file}"
            update_configuration
        fi
    fi

    # Check if the cache_di setting is set to true
    ensure_cache_di_true "${config_file}"

    # Run Composer Install as www-data
    print info "Running composer operations..."
    cd "${lis_path}"

    sudo -u www-data composer config process-timeout 30000 --no-interaction
    sudo -u www-data composer clear-cache --no-interaction

    local NEED_FULL_INSTALL=false

    # Check if the vendor directory exists
    if [ ! -d "${lis_path}/vendor" ]; then
        NEED_FULL_INSTALL=true
    else
        # Calculate new checksums
        local NEW_COMPOSER_JSON_CHECKSUM="none"
        local NEW_COMPOSER_LOCK_CHECKSUM="none"

        if [ -f "${lis_path}/composer.json" ]; then
            NEW_COMPOSER_JSON_CHECKSUM=$(md5sum "${lis_path}/composer.json" 2>/dev/null | awk '{print $1}')
        else
            NEED_FULL_INSTALL=true
        fi

        if [ -f "${lis_path}/composer.lock" ] && [ "$NEED_FULL_INSTALL" = false ]; then
            NEW_COMPOSER_LOCK_CHECKSUM=$(md5sum "${lis_path}/composer.lock" 2>/dev/null | awk '{print $1}')
        else
            NEED_FULL_INSTALL=true
        fi

        if [ "$NEED_FULL_INSTALL" = false ]; then
            if [ "$CURRENT_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
                [ "$NEW_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$NEW_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
                [ "$CURRENT_COMPOSER_JSON_CHECKSUM" != "$NEW_COMPOSER_JSON_CHECKSUM" ] ||
                [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" != "$NEW_COMPOSER_LOCK_CHECKSUM" ]; then
                NEED_FULL_INSTALL=true
            fi
        fi
    fi

    # Download and install vendor if needed
    if [ "$NEED_FULL_INSTALL" = true ]; then
        print info "Installing dependencies..."
        if curl --output /dev/null --silent --head --fail "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz"; then
            # Check if vendor.tar.gz already downloaded (shared across instances)
            if [ ! -f "/tmp/vendor.tar.gz" ]; then
                download_file "/tmp/vendor.tar.gz" "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz" "Downloading vendor packages..."
                download_file "/tmp/vendor.tar.gz.md5" "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz.md5" "Downloading checksum..."
            fi

            # Verify checksum (cd to /tmp so md5sum finds the file by name)
            if (cd /tmp && md5sum -c vendor.tar.gz.md5 2>/dev/null); then
                tar -xzf /tmp/vendor.tar.gz -C "${lis_path}" &
                local vendor_tar_pid=$!
                spinner "${vendor_tar_pid}"
                wait ${vendor_tar_pid}

                chown -R www-data:www-data "${lis_path}/vendor" 2>/dev/null || true
                chmod -R 755 "${lis_path}/vendor" 2>/dev/null || true

                sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev --no-interaction
            else
                sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
            fi
        else
            sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
        fi
    fi

    sudo -u www-data composer dump-autoload -o --no-interaction
    print success "Composer operations completed."

    # Database connectivity and migrations
    print info "Checking database connectivity..."
    php "${lis_path}/vendor/bin/db-tools" db:test --all

    print info "Running database migrations..."
    sudo -u www-data composer post-update

    print info "Running database repairs..."
    sudo -u www-data composer db:repair

    # Wait for directory migrations
    if [ "${#dir_migration_pids[@]}" -gt 0 ]; then
        for pid in "${dir_migration_pids[@]}"; do
            wait "$pid" 2>/dev/null || true
        done
    fi

    # Run run-once scripts
    if [ -d "${lis_path}/run-once" ]; then
        local run_once_scripts=("${lis_path}/run-once/"*.php)
        if [ -e "${run_once_scripts[0]}" ]; then
            for script_path in "${run_once_scripts[@]}"; do
                php "$script_path" || print warning "Run-once script $script_path exited with status $?"
            done
        fi
    fi

    # Cleanup
    if [ -f "${lis_path}/startup.php" ]; then
        sudo rm "${lis_path}/startup.php"
        sudo touch "${lis_path}/startup.php"
    fi

    if [ -f "${lis_path}/var/cache/CompiledContainer.php" ]; then
        sudo rm "${lis_path}/var/cache/CompiledContainer.php"
    fi

    if [ -f "${lis_path}/public/test.php" ]; then
        sudo rm "${lis_path}/public/test.php"
    fi

    # Cron job setup
    setup_intelis_cron "${lis_path}"

    # Set final permissions in background
    (intelis-refresh -p "${lis_path}" -m full >/dev/null 2>&1 &&
        chown -R www-data:www-data "${lis_path}" 2>/dev/null || true) &
    disown

    print success "Instance ${lis_path} updated successfully."
    log_action "Instance ${lis_path} update complete."

    return 0
}

# Download and setup intelis-refresh script (once)
download_file "/usr/local/bin/intelis-refresh" https://raw.githubusercontent.com/deforay/intelis/master/scripts/refresh.sh
chmod +x /usr/local/bin/intelis-refresh

# Process each instance
total_instances=${#lis_paths[@]}
for i in "${!lis_paths[@]}"; do
    if upgrade_instance "${lis_paths[$i]}" "$((i+1))" "$total_instances" "$temp_dir"; then
        updated_instances+=("${lis_paths[$i]}")
    else
        failed_instances+=("${lis_paths[$i]}")
    fi
done

# Maintenance scripts prompt (only for single instance to avoid tedious multi-prompts)
if [ ${#lis_paths[@]} -eq 1 ]; then
    lis_path="${lis_paths[0]}"
    echo ""
    if ask_yes_no "Do you want to run maintenance scripts?" "no"; then
        echo "Available maintenance scripts to run:"
        files=("${lis_path}/maintenance/"*.php)
        for i in "${!files[@]}"; do
            filename=$(basename "${files[$i]}")
            echo "$((i + 1))) $filename"
        done

        echo "Enter the numbers of the scripts you want to run separated by commas (e.g., 1,2,4) or type 'all' to run them all."
        read -r files_to_run

        if [[ "$files_to_run" == "all" ]]; then
            for file in "${files[@]}"; do
                print info "Running $file..."
                sudo -u www-data php "$file"
            done
        else
            IFS=',' read -ra ADDR <<<"$files_to_run"
            for i in "${ADDR[@]}"; do
                i=$(echo "$i" | xargs)
                file_index=$((i - 1))
                if [[ $file_index -ge 0 ]] && [[ $file_index -lt ${#files[@]} ]]; then
                    file="${files[$file_index]}"
                    print info "Running $file..."
                    sudo -u www-data php "$file"
                fi
            done
        fi
    fi
fi

# Cleanup temp files
if [ -d "$temp_dir" ]; then
    rm -rf "$temp_dir"
fi
if [ -f master.tar.gz ]; then
    rm master.tar.gz
fi
if [ -f "/tmp/vendor.tar.gz" ]; then
    rm /tmp/vendor.tar.gz
fi
if [ -f "/tmp/vendor.tar.gz.md5" ]; then
    rm /tmp/vendor.tar.gz.md5
fi

# Reload Apache
apache2ctl -k graceful || systemctl reload apache2 || systemctl restart apache2

# Print summary
print header "Upgrade Summary"
if [ ${#updated_instances[@]} -gt 0 ]; then
    print success "Successfully updated ${#updated_instances[@]} instance(s):"
    for p in "${updated_instances[@]}"; do
        print info "  ✓ $p"
    done
fi
if [ ${#failed_instances[@]} -gt 0 ]; then
    print error "Failed to update ${#failed_instances[@]} instance(s):"
    for p in "${failed_instances[@]}"; do
        print error "  ✗ $p"
    done
fi

log_action "Upgrade complete. Updated: ${#updated_instances[@]}, Failed: ${#failed_instances[@]}"
