#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/intelis-update https://raw.githubusercontent.com/deforay/intelis/master/scripts/upgrade.sh && sudo chmod +x /usr/local/bin/intelis-update
# sudo intelis-update

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

# Save the current trap settings
current_trap=$(trap -p ERR)

# Disable the error trap temporarily
trap - ERR

# Prompt for the LIS path if not provided via the command-line argument
if [ -z "$lis_path" ]; then
    echo "Enter the LIS installation path [press enter for /var/www/intelis]: "
    if read -t 60 lis_path && [ -n "$lis_path" ]; then
        : # user provided a value; resolver will honor it as-is
    else
        lis_path=""  # empty => resolver will auto-pick intelis, else vlsm
    fi
fi

# Resolve LIS path
lis_path="$(resolve_lis_path "$lis_path")"


print info "LIS path is set to ${lis_path}"
log_action "LIS path is set to ${lis_path}"

# Check if the LIS path is valid
if ! is_valid_application_path "$lis_path"; then
    print error "The specified path does not appear to be a valid LIS installation. Please check the path and try again."
    log_action "Invalid LIS path specified: $lis_path"
    exit 1
fi

# Restore the previous error trap
eval "$current_trap"

# Check for MySQL
if ! command -v mysql &>/dev/null; then
    print error "MySQL is not installed. Please first run the setup script."
    log_action "MySQL is not installed. Please first run the setup script."
    exit 1
fi

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

# Determine which password to use
if [ -n "$mysql_root_password" ]; then
    mysql_pw="$mysql_root_password"
    print info "Using user-provided MySQL root password"
elif [ -f "${lis_path}/configs/config.production.php" ]; then
    mysql_pw=$(extract_mysql_password_from_config "${lis_path}/configs/config.production.php")
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

persist_result=$(MYSQL_PWD="${mysql_pw}" mysql -u root -e "SET PERSIST sql_mode = '';" 2>&1)
persist_status=$?

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

# Check for PHP version 8.2.x
php_version=$(php -v | head -n 1 | grep -oP 'PHP \K([0-9]+\.[0-9]+)')
desired_php_version="8.2"

# Download and install switch-php script
download_file "/usr/local/bin/switch-php" "https://raw.githubusercontent.com/deforay/utility-scripts/master/php/switch-php"
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

php_version="${desired_php_version}"

# Modify php.ini as needed
print header "Configuring PHP"

desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING"
desired_post_max_size="post_max_size = 1G"
desired_upload_max_filesize="upload_max_filesize = 1G"
desired_strict_mode="session.use_strict_mode = 1"
desired_opcache_enable="opcache.enable=1"
desired_opcache_enable_cli="opcache.enable_cli=0"
desired_opcache_memory="opcache.memory_consumption=256"
desired_opcache_max_files="opcache.max_accelerated_files=40000"
desired_opcache_validate="opcache.validate_timestamps=0"
desired_opcache_jit="opcache.jit=disable"
desired_opcache_interned="opcache.interned_strings_buffer=16"
desired_opcache_override="opcache.enable_file_override=1"

# Function to modify PHP ini files with proper idempotency
update_php_ini() {
    local ini_file=$1
    local timestamp
    timestamp=$(date +%Y%m%d%H%M%S)
    local backup_file="${ini_file}.bak.${timestamp}"
    local changes_needed=false

    print info "Checking PHP settings in $ini_file..."

    # Evaluate which desired lines are already present exactly
    local er_set pms_set umf_set sm_set
    local opcache_enable_set opcache_enable_cli_set opcache_memory_set opcache_max_files_set opcache_validate_set opcache_jit_set

    er_set=$(grep -q "^${desired_error_reporting}$" "$ini_file" && echo true || echo false)
    pms_set=$(grep -q "^${desired_post_max_size}$" "$ini_file" && echo true || echo false)
    umf_set=$(grep -q "^${desired_upload_max_filesize}$" "$ini_file" && echo true || echo false)
    sm_set=$(grep -q "^${desired_strict_mode}$" "$ini_file" && echo true || echo false)
    opcache_enable_set=$(grep -q "^${desired_opcache_enable}$" "$ini_file" && echo true || echo false)
    opcache_enable_cli_set=$(grep -q "^${desired_opcache_enable_cli}$" "$ini_file" && echo true || echo false)
    opcache_memory_set=$(grep -q "^${desired_opcache_memory}$" "$ini_file" && echo true || echo false)
    opcache_max_files_set=$(grep -q "^${desired_opcache_max_files}$" "$ini_file" && echo true || echo false)
    opcache_validate_set=$(grep -q "^${desired_opcache_validate}$" "$ini_file" && echo true || echo false)
    opcache_jit_set=$(grep -q "^${desired_opcache_jit}$" "$ini_file" && echo true || echo false)
    opcache_interned_set=$(grep -q "^${desired_opcache_interned}$" "$ini_file" && echo true || echo false)
    opcache_override_set=$(grep -q "^${desired_opcache_override}$" "$ini_file" && echo true || echo false)

    # If ANY are missing, we need to rewrite
    if [ "$er_set" = false ] || [ "$pms_set" = false ] || [ "$umf_set" = false ] || [ "$sm_set" = false ] \
        || [ "$opcache_enable_set" = false ] || [ "$opcache_enable_cli_set" = false ] || [ "$opcache_memory_set" = false ] \
        || [ "$opcache_max_files_set" = false ] || [ "$opcache_validate_set" = false ] || [ "$opcache_jit_set" = false ] \
        || [ "$opcache_interned_set" = false ] || [ "$opcache_override_set" = false ]; then
        changes_needed=true
        cp "$ini_file" "$backup_file"
        print info "Changes needed. Backup created at $backup_file"
    fi

    if [ "$changes_needed" = true ]; then
        local temp_file
        temp_file=$(mktemp)

        # Rewrite file, commenting old keys and inserting desired ones once
        while IFS= read -r line; do
            if [[ "$line" =~ ^[[:space:]]*error_reporting[[:space:]]*= ]] && [ "$er_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_error_reporting" >>"$temp_file"; er_set=true
            elif [[ "$line" =~ ^[[:space:]]*post_max_size[[:space:]]*= ]] && [ "$pms_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_post_max_size" >>"$temp_file"; pms_set=true
            elif [[ "$line" =~ ^[[:space:]]*upload_max_filesize[[:space:]]*= ]] && [ "$umf_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_upload_max_filesize" >>"$temp_file"; umf_set=true
            elif [[ "$line" =~ ^[[:space:]]*session\.use_strict_mode[[:space:]]*= ]] && [ "$sm_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_strict_mode" >>"$temp_file"; sm_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.enable[[:space:]]*= ]] && [ "$opcache_enable_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_enable" >>"$temp_file"; opcache_enable_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.enable_cli[[:space:]]*= ]] && [ "$opcache_enable_cli_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_enable_cli" >>"$temp_file"; opcache_enable_cli_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.memory_consumption[[:space:]]*= ]] && [ "$opcache_memory_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_memory" >>"$temp_file"; opcache_memory_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.max_accelerated_files[[:space:]]*= ]] && [ "$opcache_max_files_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_max_files" >>"$temp_file"; opcache_max_files_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.validate_timestamps[[:space:]]*= ]] && [ "$opcache_validate_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_validate" >>"$temp_file"; opcache_validate_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.jit[[:space:]]*= ]] && [ "$opcache_jit_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_jit" >>"$temp_file"; opcache_jit_set=true
            elif  [[ "$line" =~ ^[[:space:]]*opcache\.interned_strings_buffer[[:space:]]*= ]] && [ "$opcache_interned_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_interned" >>"$temp_file"; opcache_interned_set=true
            elif  [[ "$line" =~ ^[[:space:]]*opcache\.enable_file_override[[:space:]]*= ]] && [ "$opcache_override_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_override" >>"$temp_file"; opcache_override_set=true
            else
                echo "$line" >>"$temp_file"
            fi
        done <"$ini_file"

        # Append any directives that were entirely missing
        [ "$er_set" = true ] || echo "$desired_error_reporting" >>"$temp_file"
        [ "$pms_set" = true ] || echo "$desired_post_max_size" >>"$temp_file"
        [ "$umf_set" = true ] || echo "$desired_upload_max_filesize" >>"$temp_file"
        [ "$sm_set" = true ] || echo "$desired_strict_mode" >>"$temp_file"
        [ "$opcache_enable_set" = true ] || echo "$desired_opcache_enable" >>"$temp_file"
        [ "$opcache_enable_cli_set" = true ] || echo "$desired_opcache_enable_cli" >>"$temp_file"
        [ "$opcache_memory_set" = true ] || echo "$desired_opcache_memory" >>"$temp_file"
        [ "$opcache_max_files_set" = true ] || echo "$desired_opcache_max_files" >>"$temp_file"
        [ "$opcache_validate_set" = true ] || echo "$desired_opcache_validate" >>"$temp_file"
        [ "$opcache_jit_set" = true ] || echo "$desired_opcache_jit" >>"$temp_file"
        [ "$opcache_interned_set" = true ] || echo "$desired_opcache_interned" >>"$temp_file"
        [ "$opcache_override_set" = true ] || echo "$desired_opcache_override" >>"$temp_file"


        mv "$temp_file" "$ini_file"
        print success "Updated PHP settings in $ini_file"

        # Remove backup once successful (as you intended)
        if [ -f "$backup_file" ]; then
            rm "$backup_file"
            print info "Removed backup file $backup_file"
        fi
    else
        print info "PHP settings are already correctly set in $ini_file"
    fi
}

# Ensure opcache is present & enabled for mod_php
if ! php -m | grep -qi '^opcache$'; then
    print info "Installing/enabling OPcache for PHP ${desired_php_version} (mod_php)..."
    apt-get update -y
    apt-get install -y "php${desired_php_version}-opcache" || true
    phpenmod -v "${desired_php_version}" -s ALL opcache 2>/dev/null || phpenmod opcache 2>/dev/null || true
fi


# Apply changes to PHP configuration files
for phpini in /etc/php/${php_version}/apache2/php.ini /etc/php/${php_version}/cli/php.ini; do
    if [ -f "$phpini" ]; then
        update_php_ini "$phpini"
    else
        print warning "PHP configuration file not found: $phpini"
    fi
done

# Validate Apache config and reload to apply PHP INI changes
if apache2ctl -t; then
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

# set_permissions "${lis_path}" "quick"
set_permissions "${lis_path}/logs" "full"

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

download_file "master.tar.gz" "https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master" "Downloading LIS package..." || {
    print error "LIS download failed - cannot continue with update"
    log_action "LIS download failed - update aborted"
    exit 1
}

# Extract the tar.gz file into temporary directory
temp_dir=$(mktemp -d)
print info "Extracting files from master.tar.gz..."

tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!           # Save tar PID
spinner "${tar_pid}" # Spinner tracks extraction
wait ${tar_pid}      # Wait for extraction to finish

# Copy the unzipped content to the LIS PATH, overwriting any existing files
# Find all symlinks in the destination directory and create an exclude pattern
exclude_options=""
# Initialize symlinks_found to 0 before using it
symlinks_found=0
for symlink in $(find "$lis_path" -type l -not -path "*/\.*" 2>/dev/null); do
    # Extract the relative path from the full path
    rel_path=${symlink#"$lis_path/"}
    exclude_options="$exclude_options --exclude '$rel_path'"
    print info "Detected symlink: $rel_path"
    symlinks_found=$((symlinks_found + 1))
done

print info "Found $symlinks_found symlinks that will be preserved."

# Use the dynamically generated exclude options in the rsync command
eval rsync -a --inplace --whole-file $exclude_options --info=progress2 "$temp_dir/intelis-master/" "$lis_path/" &
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
if [ -d "$temp_dir/intelis-master/" ]; then
    rm -rf "$temp_dir/intelis-master/"
fi
if [ -d "$temp_dir" ]; then
    rm -rf "$temp_dir"
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
    print info "Vendor directory doesn't exist. Full installation needed."
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
            print info "Composer files have changed or were missing. Full installation needed."
            NEED_FULL_INSTALL=true
        else
            print info "Composer files haven't changed. Skipping full installation."
            NEED_FULL_INSTALL=false
        fi
    fi
fi

# Download vendor.tar.gz if needed
if [ "$NEED_FULL_INSTALL" = true ]; then
    print info "Dependency update needed. Checking for vendor packages..."

    # Check if the vendor package exists
    if curl --output /dev/null --silent --head --fail "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz"; then
        # Download the vendor archive
        download_file "vendor.tar.gz" "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz" "Downloading vendor packages..."
        if [ $? -ne 0 ]; then
            print error "Failed to download vendor.tar.gz"
            exit 1
        fi

        # Download the checksum file
        download_file "vendor.tar.gz.md5" "https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz.md5" "Downloading checksum file..."
        if [ $? -ne 0 ]; then
            print error "Failed to download vendor.tar.gz.md5"
            exit 1
        fi

        print info "Verifying checksum..."
        if ! md5sum -c vendor.tar.gz.md5; then
            print error "Checksum verification failed"
            exit 1
        fi
        print success "Checksum verification passed"

        print info "Extracting files from vendor.tar.gz..."
        tar -xzf vendor.tar.gz -C "${lis_path}" &
        vendor_tar_pid=$!
        spinner "${vendor_tar_pid}" "Extracting vendor files..."
        wait ${vendor_tar_pid}
        vendor_tar_status=$?

        if [ $vendor_tar_status -ne 0 ]; then
            print error "Failed to extract vendor.tar.gz"
            exit 1
        fi

        # Clean up downloaded files
        rm vendor.tar.gz
        rm vendor.tar.gz.md5

        # Fix permissions on the vendor directory
        print info "Setting permissions on vendor directory..."
        find "${lis_path}/vendor" -exec chown www-data:www-data {} \; 2>/dev/null || true
        chmod -R 755 "${lis_path}/vendor" 2>/dev/null || true

        print success "Vendor files successfully installed"

        # Update the composer.lock file to match the current state
        print info "Finalizing composer installation..."
        sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev
    else
        print warning "Vendor package not found in GitHub releases. Proceeding with regular composer install."

        # Perform full install if vendor.tar.gz isn't available
        print info "Running full composer install (this may take a while)..."
        sudo -u www-data composer install --prefer-dist --no-dev
    fi
else
    print info "Dependencies are up to date. Skipping vendor download."
fi

# Always generate the optimized autoloader, regardless of install path
sudo -u www-data composer dump-autoload -o

print success "Composer operations completed."
log_action "Composer operations completed."

apache2ctl -k graceful || systemctl reload apache2

# Run the database migrations and other post-update tasks
print header "Running database migrations and other post-update tasks"
sudo -u www-data composer post-update &
pid=$!
spinner "$pid"
wait $pid

print success "Database migrations and post-update tasks completed."
log_action "Database migrations and post-update tasks completed."

# Make the intelis script executable
chmod +x "${lis_path}/intelis" 2>/dev/null
sudo rm /usr/local/bin/intelis 2>/dev/null || true
sudo ln -s "${lis_path}/intelis" /usr/local/bin/intelis 2>/dev/null

# Show success message only if symlink was created successfully
if [ -L "/usr/local/bin/intelis" ]; then
    echo "✅ intelis command installed successfully!"
    echo "You can now use: intelis interface, intelis token, etc."
fi

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
    sudo rm "${lis_path}/startup.php"
    sudo touch "${lis_path}/startup.php"
fi

if [ -f "${lis_path}/cache/CompiledContainer.php" ]; then
    sudo rm "${lis_path}/cache/CompiledContainer.php"
fi

# Cron job setup
setup_intelis_cron "${lis_path}"

# Set proper permissions
download_file "/usr/local/bin/intelis-refresh" https://raw.githubusercontent.com/deforay/intelis/master/scripts/refresh.sh
chmod +x /usr/local/bin/intelis-refresh
(print success "Setting final permissions in the background..." &&
    intelis-refresh -p "${lis_path}" -m full >/dev/null 2>&1 &&
    find "${lis_path}" -exec chown www-data:www-data {} \; 2>/dev/null || true) &
disown

print success "LIS update complete."
log_action "LIS update complete."
