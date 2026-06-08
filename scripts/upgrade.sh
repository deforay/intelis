#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/intelis-update https://raw.githubusercontent.com/deforay/intelis/master/scripts/upgrade.sh && sudo chmod +x /usr/local/bin/intelis-update
# sudo intelis-update
#
# Options:
#   -p PATH            Specify the LIS installation path (e.g., -p /var/www/intelis)
#   -A                 Auto-detect and update ALL intelis installations in /var/www
#   -i                 Interactive instance selection (use with -A to pick specific instances)
#   -s                 Skip Ubuntu system updates
#   -P, --prepare-only Run only the prepare phase: download + extract + validate into a
#                      staging directory, print the path, and exit 0. Safe to run
#                      anytime; no downtime, no apply.
#   -a, --apply-prepared <dir>
#                      Skip prepare and apply from an existing staging dir (must contain
#                      a READY sentinel). Ubuntu package updates are implicitly skipped
#                      in this mode since system_prep was presumably already done during
#                      the prepare invocation. MySQL/PHP/Apache checks still run.
#   -k, --keep-snapshots N
#                      Number of rollback snapshot generations to retain under
#                      /var/intelis-rollback/ (default: 3). Older snapshots are
#                      pruned after a successful apply run.
#   -M, --maintenance  Show a 503 "upgrade in progress" page to users during the
#                      apply window. Default is silent (no page shown), which is
#                      usually fine because most upgrades are small PHP/template
#                      changes that users don't notice. Use this flag when the
#                      upgrade changes composer.lock or runs DB migrations.
#
#   --prepare-only and --apply-prepared are mutually exclusive.
#
# Examples:
#   sudo intelis-update                          # Interactive single instance (prepare+apply)
#   sudo intelis-update -p /var/www/intelis      # Specific path
#   sudo intelis-update -A                       # Update all instances in /var/www
#   sudo intelis-update -A -i                    # Detect instances, pick which to update
#   sudo intelis-update -A -s -b                 # Non-interactive, update all instances
#   sudo intelis-update --prepare-only           # Stage the update now; apply later
#   sudo intelis-update --apply-prepared /var/intelis-staging/20260422-120000-1234

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
auto_detect=false
interactive_select=false
prepare_only=false
apply_prepared_dir=""
# Maintenance mode is OFF by default — most upgrades are small enough that users
# notice nothing. Pass --maintenance / -M to show a 503 page during the apply
# window (recommended when the upgrade changes composer.lock or runs migrations).
show_maintenance=false
lis_path=""
declare -a lis_paths=()

log_file="/tmp/intelis-upgrade-$(date +'%Y%m%d-%H%M%S').log"

# Pre-process long options into short equivalents so getopts can handle them.
# Supported long options:
#   --prepare-only        -> -P
#   --apply-prepared DIR  -> -a DIR
declare -a _rewritten_args=()
while [ $# -gt 0 ]; do
    case "$1" in
        --prepare-only)
            _rewritten_args+=("-P")
            shift
            ;;
        --apply-prepared)
            _rewritten_args+=("-a")
            if [ $# -lt 2 ]; then
                echo "Error: --apply-prepared requires a directory argument" >&2
                exit 2
            fi
            _rewritten_args+=("$2")
            shift 2
            ;;
        --apply-prepared=*)
            _rewritten_args+=("-a")
            _rewritten_args+=("${1#--apply-prepared=}")
            shift
            ;;
        --keep-snapshots)
            _rewritten_args+=("-k")
            if [ $# -lt 2 ]; then
                echo "Error: --keep-snapshots requires a number argument" >&2
                exit 2
            fi
            _rewritten_args+=("$2")
            shift 2
            ;;
        --keep-snapshots=*)
            _rewritten_args+=("-k")
            _rewritten_args+=("${1#--keep-snapshots=}")
            shift
            ;;
        --maintenance)
            _rewritten_args+=("-M")
            shift
            ;;
        --)
            _rewritten_args+=("$@")
            break
            ;;
        *)
            _rewritten_args+=("$1")
            shift
            ;;
    esac
done
set -- "${_rewritten_args[@]}"

# Parse command-line options
while getopts ":sAiPp:a:k:M" opt; do
    case $opt in
    s) skip_ubuntu_updates=true ;;
    A) auto_detect=true ;;
    i) interactive_select=true ;;
    p) lis_path="$OPTARG" ;;
    P) prepare_only=true ;;
    a) apply_prepared_dir="$OPTARG" ;;
    k)
        if ! [[ "$OPTARG" =~ ^[0-9]+$ ]]; then
            echo "Error: --keep-snapshots must be a non-negative integer (got '${OPTARG}')" >&2
            exit 2
        fi
        ROLLBACK_KEEP="$OPTARG"
        ;;
    M) show_maintenance=true ;;
        # Ignore invalid options silently
    esac
done

# Mutually exclusive check
if [ "$prepare_only" = true ] && [ -n "$apply_prepared_dir" ]; then
    echo "Error: --prepare-only and --apply-prepared are mutually exclusive." >&2
    exit 2
fi

# --apply-prepared implies skipping Ubuntu system updates (system_prep already ran
# during the prepare invocation). MySQL/PHP/Apache checks still execute.
if [ -n "$apply_prepared_dir" ]; then
    skip_ubuntu_updates=true
fi

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
    echo ""
    print info "Found ${#detected_paths[@]} installation(s):"
    for i in "${!detected_paths[@]}"; do
        echo "  $((i+1))) ${detected_paths[$i]}"
    done
    echo ""

    if [ "$interactive_select" = true ]; then
        # Interactive mode: let user pick which instances to update
        printf "Enter instance numbers to update (e.g., 1,2,3) or press Enter for all: "
        read -r selection < /dev/tty

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

# Wait for mysqld's socket to come back after a (re)start. A freshly restarted
# server can take a few seconds to recreate its unix socket; connecting in that
# window fails with "No such file or directory". `mysqladmin ping` reports the
# server alive even on auth errors, so it's a pure reachability probe and needs
# no credentials.
wait_for_mysql() {
    local tries="${1:-30}" i
    for ((i = 1; i <= tries; i++)); do
        if mysqladmin ping --silent >/dev/null 2>&1; then
            return 0
        fi
        sleep 1
    done
    return 1
}

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

    # Don't proceed into the instance loop until the socket is back, or the
    # first instance's DB steps race the restart and fail with ENOENT.
    if ! wait_for_mysql 30; then
        print error "MySQL did not become reachable after restart. Aborting."
        exit 1
    fi

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
ensure_path
ensure_switch_php

if [[ "${php_version}" != "${desired_php_version}" ]]; then
    print info "Current PHP version is ${php_version}. Switching to PHP ${desired_php_version}."

    # Switch to PHP 8.4
    # WHY: switch-php can exit non-zero even after doing useful work; don't let the ERR trap abort the upgrade.
    previous_err_trap="$(trap -p ERR || true)"
    trap - ERR
    if ! switch_out=$(switch-php "${desired_php_version}" --fast 2>&1); then
        print warning "switch-php --fast failed; retrying without --fast."
        log_action "switch-php --fast failed: ${switch_out}"
        if ! switch_out=$(switch-php "${desired_php_version}" 2>&1); then
            print warning "Failed to switch to PHP ${desired_php_version}: ${switch_out}"
            log_action "switch-php failed: ${switch_out}"
        fi
    fi
    if [ -n "${previous_err_trap}" ]; then
        eval "${previous_err_trap}"
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

# WHY: Older global Composer versions can emit PHP deprecations or fail newer package flows.
# Keep Composer current during upgrade, but do not block the entire upgrade if self-update fails.
print info "Updating Composer if a newer version is available..."
if COMPOSER_ALLOW_SUPERUSER=1 composer self-update --stable --clean-backups; then
    print success "Composer is up to date."
else
    print warning "Composer self-update failed. Continuing with the existing Composer version."
fi

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

# ---------------------------------------------------------------------------
# Phase split helpers: prepare_phase, maintenance mode, rollback snapshot.
# prepare_phase() downloads + extracts + validates into a staging dir and is
# safe to run with the live LIS still serving traffic.
# ---------------------------------------------------------------------------

MASTER_TARBALL_URL="https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master"
VENDOR_TARBALL_URL="https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz"
VENDOR_TARBALL_MD5_URL="https://github.com/deforay/intelis/releases/download/vendor-latest/vendor.tar.gz.md5"

# Persistent shallow git mirror of master. After the first clone, prepare_phase
# advances it with DELTA fetches (only the changed objects, usually tens of KB)
# instead of re-downloading the full ~20MB codeload tarball every upgrade — the
# difference between seconds and minutes on a slow link. The tarball URL above
# stays as the last-resort fallback when git is unavailable.
MASTER_GIT_URL="https://github.com/deforay/intelis.git"
INTELIS_SRC_DIR="${INTELIS_SRC_DIR:-/usr/local/lib/intelis/src}"

# git network ops get a generous wall-clock backstop so a hung connection can't
# stall the whole upgrade, plus a low-speed abort (below 1KB/s for 60s) that
# fails a truly dead link fast. The cap is large on purpose: it only ever bounds
# a transfer that is still making progress — the low-speed abort already handles
# dead links — so a legitimately slow-but-working link on a remote lab survives.
git_timeout=2400
git_timeout_cmd=""
command -v timeout >/dev/null 2>&1 && git_timeout_cmd="timeout --kill-after=15 ${git_timeout}"

# safe.directory='*' avoids git's "dubious ownership" refusal if the mirror's
# owner ever differs from root.
run_git() {
    $git_timeout_cmd git -c safe.directory='*' -c http.lowSpeedLimit=1000 -c http.lowSpeedTime=60 "$@"
}

STAGING_BASE_DIR="/var/intelis-staging"
ROLLBACK_BASE_DIR="/var/intelis-rollback"

# Default number of rollback snapshot generations to retain. Override with
# --keep-snapshots N. Older snapshots are pruned after a successful apply run.
ROLLBACK_KEEP="${ROLLBACK_KEEP:-3}"

# prepare_phase — download + extract + verify the master tarball, then the
# vendor tarball ONLY if at least one target instance actually needs it (its
# vendor/ is missing/incomplete, or its committed composer.lock differs from the
# new master's). Vendor contents are fully determined by composer.lock, so when
# every instance is already in sync we skip the (large) vendor download and the
# apply phase's per-instance "composer install" fallback becomes a fast no-op.
# All network I/O still happens here (live + retryable), keeping the apply window
# offline-safe — which matters for unattended / remote-triggered upgrades.
# Echoes the staging dir on stdout (captured by caller). All informational
# output is sent to stderr so stdout is clean.
prepare_phase() {
    local staging_dir="${STAGING_BASE_DIR}/$(date +%Y%m%d-%H%M%S)-$$"

    # Disk-space preflight. Staging + extracted master + extracted vendor
    # weighs roughly 1.2GB today; require 2GB free to leave headroom for
    # verification and simultaneous master+vendor extraction. Fail here
    # with a clear message instead of getting halfway through a download
    # and running out mid-extract.
    mkdir -p "$STAGING_BASE_DIR"
    local free_mb
    free_mb=$(df -Pm "$STAGING_BASE_DIR" | awk 'NR==2 {print $4}')
    if [ -n "$free_mb" ] && [ "$free_mb" -lt 2048 ]; then
        print error "Insufficient free space at ${STAGING_BASE_DIR}: ${free_mb}MB free, need at least 2GB." >&2
        log_action "Prepare aborted — only ${free_mb}MB free at ${STAGING_BASE_DIR}"
        return 1
    fi

    mkdir -p "$staging_dir"

    print header "Prepare: staging update at ${staging_dir}" >&2
    log_action "Prepare phase starting at ${staging_dir}"

    local master_log="${staging_dir}/master.log"
    local vendor_log="${staging_dir}/vendor.log"
    local master_tar="${staging_dir}/master.tar.gz"
    local master_extract_dir="${staging_dir}/intelis-master"
    local vendor_tar="${staging_dir}/vendor.tar.gz"
    local vendor_md5="${staging_dir}/vendor.tar.gz.md5"
    local vendor_extract_dir="${staging_dir}/vendor"

    # ---- master worker ----
    # Acquire the master tree via a PERSISTENT shallow git mirror at
    # $INTELIS_SRC_DIR plus DELTA fetches: after the first clone each run
    # transfers only the changed objects instead of the full codeload tarball.
    # The mirror's working tree is rsynced (sans .git) into intelis-master/, so
    # the rest of the pipeline (vendor gating, apply rsync, READY sentinel) is
    # unchanged. Fallbacks: a fresh shallow clone, then the codeload tarball.
    _prepare_master_worker() {
        set -e
        if [ -d "$master_extract_dir" ] && [ -f "$master_extract_dir/composer.json" ]; then
            echo "master: already extracted, skipping"
            return 0
        fi

        local src_ready=false

        # Attempt 1: delta-fetch the persistent mirror (cheap — changed objects only).
        if command -v git >/dev/null 2>&1 && [ -d "$INTELIS_SRC_DIR/.git" ]; then
            echo "master: updating source mirror (delta fetch — only changed files)"
            if run_git -C "$INTELIS_SRC_DIR" fetch --depth 1 origin master &&
                git -c safe.directory='*' -C "$INTELIS_SRC_DIR" reset --hard FETCH_HEAD &&
                git -c safe.directory='*' -C "$INTELIS_SRC_DIR" clean -fd; then
                # Shallow fetch/reset orphans the previous tip's objects; --prune=now
                # sweeps them immediately so the mirror doesn't bloat over many runs.
                git -c safe.directory='*' -C "$INTELIS_SRC_DIR" gc --prune=now --quiet 2>/dev/null || true
                src_ready=true
                echo "master: mirror updated via delta fetch"
            else
                echo "master: delta fetch failed; will re-clone the mirror"
                rm -rf "$INTELIS_SRC_DIR"
            fi
        fi

        # Attempt 2: fresh shallow clone into the mirror.
        if [ "$src_ready" = false ] && command -v git >/dev/null 2>&1; then
            echo "master: cloning master into source mirror (shallow)"
            local attempt
            for attempt in 1 2 3; do
                rm -rf "$INTELIS_SRC_DIR"
                if run_git clone --depth 1 --single-branch --branch master \
                    "$MASTER_GIT_URL" "$INTELIS_SRC_DIR"; then
                    src_ready=true
                    echo "master: cloned (attempt ${attempt}); future updates will be delta-only"
                    break
                fi
                echo "master: clone attempt ${attempt}/3 failed"
                sleep 3
            done
        fi

        if [ "$src_ready" = true ]; then
            # Stage the working tree (minus .git, which must never reach an
            # instance) into intelis-master/.
            echo "master: staging tree from mirror"
            rm -rf "$master_extract_dir"
            mkdir -p "$master_extract_dir"
            rsync -a --exclude='.git' --exclude='.git/' "$INTELIS_SRC_DIR/" "$master_extract_dir/"

            # Commit SHA straight from the mirror — exact, and free of GitHub API
            # rate limits / the tarball's API-vs-content race. Read at runtime by
            # CommonService::getCommitSha() as the "what's deployed" stamp.
            local _master_sha
            _master_sha=$(git -c safe.directory='*' -C "$INTELIS_SRC_DIR" rev-parse HEAD 2>/dev/null || true)
            if [ -n "$_master_sha" ]; then
                printf '%s\n' "$_master_sha" > "$master_extract_dir/VERSION.txt"
                echo "master: commit SHA $_master_sha captured"
            fi
        else
            # Attempt 3: tarball fallback (git missing/unreachable). No mirror is
            # created, so this run gets no future deltas — next run retries git.
            echo "master: git unavailable; falling back to codeload tarball"
            if [ ! -f "$master_tar" ]; then
                echo "master: downloading from $MASTER_TARBALL_URL"
                download_file "$master_tar" "$MASTER_TARBALL_URL" "master: downloading tarball"
            else
                echo "master: tarball already present, skipping download"
            fi
            echo "master: extracting"
            rm -rf "$master_extract_dir"
            mkdir -p "$master_extract_dir"
            # GitHub codeload tarballs wrap contents in intelis-master/, matching
            # our extract dir name — nothing else to strip.
            tar -xzf "$master_tar" -C "$staging_dir"

            # No local git to rev-parse; capture HEAD SHA via the GitHub API
            # (best-effort). Tiny race: API HEAD vs. tarball content can differ
            # by a commit if someone pushes between the two requests.
            local _sha_response _master_sha
            _sha_response=$(curl -sS --max-time 10 \
                "https://api.github.com/repos/deforay/intelis/commits/master" 2>/dev/null || true)
            _master_sha=$(printf '%s' "$_sha_response" \
                | grep -oE '"sha"[[:space:]]*:[[:space:]]*"[0-9a-f]{40}"' \
                | head -1 \
                | grep -oE '[0-9a-f]{40}')
            if [ -n "$_master_sha" ]; then
                printf '%s\n' "$_master_sha" > "$master_extract_dir/VERSION.txt"
                echo "master: commit SHA $_master_sha captured"
            else
                echo "master: commit SHA lookup skipped (no network or rate-limited)"
            fi
        fi

        if [ ! -f "$master_extract_dir/composer.json" ]; then
            echo "master: composer.json missing after staging" >&2
            return 1
        fi

        echo "master: ready"
    }

    # ---- vendor worker ----
    _prepare_vendor_worker() {
        set -e
        # If vendor release isn't published we silently accept — apply_phase
        # will fall back to composer install.
        if ! curl --output /dev/null --silent --head --fail "$VENDOR_TARBALL_URL"; then
            echo "vendor: release URL not available; apply phase will composer install instead"
            return 0
        fi
        if [ -d "$vendor_extract_dir" ] && [ -d "$vendor_extract_dir/composer" ]; then
            echo "vendor: already extracted, skipping"
            return 0
        fi
        if [ ! -f "$vendor_tar" ] || [ ! -f "$vendor_md5" ]; then
            echo "vendor: downloading tarball"
            download_file "$vendor_tar" "$VENDOR_TARBALL_URL" "vendor: downloading tarball"
            echo "vendor: downloading checksum"
            download_file "$vendor_md5" "$VENDOR_TARBALL_MD5_URL" "vendor: downloading checksum"
        else
            echo "vendor: tarball + checksum already present"
        fi
        echo "vendor: verifying checksum"
        # Normalise filename in md5 file so md5sum finds it alongside the tarball.
        local expected
        expected=$(awk '{print $1}' "$vendor_md5")
        local actual
        actual=$(md5sum "$vendor_tar" | awk '{print $1}')
        if [ "$expected" != "$actual" ]; then
            echo "vendor: checksum mismatch (expected $expected, got $actual)" >&2
            return 1
        fi
        echo "vendor: extracting"
        rm -rf "$vendor_extract_dir"
        mkdir -p "$vendor_extract_dir"
        # vendor.tar.gz typically expands into a vendor/ directory; we want
        # the *contents* inside $vendor_extract_dir so apply can rsync directly.
        local tmp_extract="${staging_dir}/.vendor-extract"
        rm -rf "$tmp_extract"
        mkdir -p "$tmp_extract"
        tar -xzf "$vendor_tar" -C "$tmp_extract"
        if [ -d "$tmp_extract/vendor" ]; then
            rsync -a "$tmp_extract/vendor/" "$vendor_extract_dir/"
        else
            rsync -a "$tmp_extract/" "$vendor_extract_dir/"
        fi
        rm -rf "$tmp_extract"
        echo "vendor: ready"
    }

    # ---- Step 1: master first ----
    # Vendor is gated on master's composer.lock, so master must land before we
    # can decide whether any instance actually needs a fresh vendor tarball.
    #
    # The worker's own progress goes to $master_log (it runs in a backgrounded
    # subshell), so announce the acquisition mode here on the terminal — without
    # this the section is silent. This is a prediction of the path the worker
    # will take; the exact outcome (incl. any fallback) lands in $master_log and
    # is confirmed by the "Master ready" line below.
    if command -v git >/dev/null 2>&1 && [ -d "$INTELIS_SRC_DIR/.git" ]; then
        print info "Updating source mirror (delta fetch — only changed files)..." >&2
    elif command -v git >/dev/null 2>&1; then
        print info "Cloning source mirror (first run; shallow clone — future runs are delta-only)..." >&2
    else
        print info "Downloading master tarball (git unavailable)..." >&2
    fi
    ( _prepare_master_worker ) >"$master_log" 2>&1 &
    local master_pid=$!

    local master_status=0
    wait "$master_pid" || master_status=$?

    if [ "$master_status" -ne 0 ]; then
        print error "Master download/extract failed. See ${master_log}" >&2
        log_action "Prepare: master failed (status $master_status)"
        return 1
    fi
    print success "Master ready at ${master_extract_dir}" >&2

    # ---- Step 2: decide whether any instance needs a fresh vendor ----
    # vendor/ is fully determined by composer.lock, so the precise "outdated"
    # signal is: vendor/ absent or incomplete, OR the instance's committed
    # composer.lock differs from the new master's lock. We inspect each target
    # instance's CURRENT on-disk state (apply hasn't rsync'd master over them
    # yet), so this compares old-vs-new exactly. First mismatch wins — we only
    # need one outdated instance to justify the download, and a single staged
    # vendor is reused across every instance in the apply phase.
    local master_lock_md5=""
    if [ -f "$master_extract_dir/composer.lock" ]; then
        master_lock_md5=$(md5sum "$master_extract_dir/composer.lock" | awk '{print $1}')
    fi

    local vendor_needed=false
    local _gate_reason=""
    if [ -z "$master_lock_md5" ]; then
        # No lock in master (unexpected) — be safe and fetch vendor.
        vendor_needed=true
        _gate_reason="master tarball has no composer.lock"
    else
        local _lp _inst_lock_md5
        for _lp in "${lis_paths[@]}"; do
            if [ ! -d "${_lp}/vendor" ] || [ ! -d "${_lp}/vendor/composer" ]; then
                vendor_needed=true
                _gate_reason="${_lp}: vendor/ missing or incomplete"
                break
            fi
            _inst_lock_md5=""
            [ -f "${_lp}/composer.lock" ] && _inst_lock_md5=$(md5sum "${_lp}/composer.lock" | awk '{print $1}')
            if [ "$_inst_lock_md5" != "$master_lock_md5" ]; then
                vendor_needed=true
                _gate_reason="${_lp}: composer.lock differs from new master"
                break
            fi
        done
    fi

    # ---- Step 3: download vendor only if needed ----
    local vendor_status=0
    if [ "$vendor_needed" = true ]; then
        print info "Vendor download needed (${_gate_reason}); fetching vendor tarball" >&2
        log_action "Prepare: vendor needed (${_gate_reason})"
        ( _prepare_vendor_worker ) >"$vendor_log" 2>&1 &
        local vendor_pid=$!
        wait "$vendor_pid" || vendor_status=$?

        if [ "$vendor_status" -ne 0 ]; then
            print error "Vendor download/extract failed. See ${vendor_log}" >&2
            log_action "Prepare: vendor failed (status $vendor_status)"
            return 1
        fi
        if [ -d "$vendor_extract_dir" ] && [ -d "$vendor_extract_dir/composer" ]; then
            print success "Vendor ready at ${vendor_extract_dir}" >&2
        else
            print warning "Vendor staging not populated; apply will fall back to composer install" >&2
        fi
    else
        print success "All target instance(s) already match the new composer.lock — skipping vendor download" >&2
        log_action "Prepare: vendor skipped — all instances in sync with master composer.lock"
    fi

    # Sanity-check composer.json
    if command -v composer >/dev/null 2>&1; then
        if ! (cd "$master_extract_dir" && COMPOSER_ALLOW_SUPERUSER=1 composer validate --no-check-publish --no-check-all --no-interaction >/dev/null 2>&1); then
            print warning "composer validate flagged issues in staged composer.json (continuing)" >&2
            log_action "Prepare: composer validate flagged issues (non-fatal)"
        fi
    fi

    # Capture version for the READY sentinel.
    local staged_version="unknown"
    if [ -f "$master_extract_dir/VERSION" ]; then
        staged_version=$(head -n1 "$master_extract_dir/VERSION" | tr -d '\r\n')
    elif [ -f "$master_extract_dir/composer.json" ] && command -v php >/dev/null 2>&1; then
        staged_version=$(php -r "
            \$c = json_decode(file_get_contents('$master_extract_dir/composer.json'), true);
            echo (\$c['version'] ?? 'unknown');
        " 2>/dev/null || echo "unknown")
    fi
    [ -n "$staged_version" ] || staged_version="unknown"

    # Drop READY sentinel last.
    printf 'version=%s\nprepared_at=%s\n' "$staged_version" "$(date -Iseconds)" > "$staging_dir/READY"

    print success "Staging ready (version ${staged_version})" >&2
    log_action "Prepare phase complete at ${staging_dir} (version ${staged_version})"

    # Caller captures the staging dir from stdout.
    echo "$staging_dir"
    return 0
}

# maintenance_conf_path — echoes the Apache conf path for a given lis_path.
maintenance_conf_path() {
    local lp="$1"
    echo "/etc/apache2/conf-available/intelis-maintenance-$(basename "$lp").conf"
}

# enable_maintenance_mode — install + enable an Apache conf that serves 503 for
# all requests into this instance while the apply runs. Best-effort; falls back
# to a .maintenance marker file under public/ if Apache can't be configured.
# No-op unless --maintenance / -M was passed; most upgrades are small and silent.
enable_maintenance_mode() {
    local lp="$1"
    if [ "${show_maintenance:-false}" != "true" ]; then
        return 0
    fi
    local conf_file
    conf_file=$(maintenance_conf_path "$lp")
    local conf_basename
    conf_basename=$(basename "$conf_file" .conf)
    local doc_root="${lp}/public"

    # Write a tiny static maintenance page next to the app.
    local maint_page="${doc_root}/.intelis-maintenance.html"
    cat > "$maint_page" <<'HTML'
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Upgrade in progress</title>
<meta http-equiv="refresh" content="15"></head>
<body style="font-family:sans-serif;max-width:640px;margin:3em auto;padding:1em;">
<h1>Upgrade in progress</h1>
<p>This Intelis instance is being updated. Please retry in a moment.</p>
</body></html>
HTML
    chown www-data:www-data "$maint_page" 2>/dev/null || true

    if command -v a2enconf >/dev/null 2>&1 && [ -d /etc/apache2/conf-available ]; then
        cat > "$conf_file" <<EOF
# Auto-generated by intelis-update — Apache maintenance drop-in for ${lp}
# Matches requests under the docroot ${doc_root} and returns 503 until the
# apply phase finishes and disables this conf.
<Directory "${doc_root}">
    ErrorDocument 503 /.intelis-maintenance.html
    RewriteEngine On
    # Let the static maintenance page itself through so we don't 503 our own 503 page
    RewriteCond %{REQUEST_URI} !^/\.intelis-maintenance\.html$
    RewriteRule ^ - [R=503,L]
    Header always set Retry-After "120"
    Header always set Cache-Control "no-store"
</Directory>
EOF
        a2enconf -q "$conf_basename" >/dev/null 2>&1 || true
        # Best-effort reload; if it fails we still proceed — the marker file is a backstop.
        if apache2ctl -t >/dev/null 2>&1; then
            apache2ctl -k graceful >/dev/null 2>&1 || systemctl reload apache2 >/dev/null 2>&1 || true
            print info "Maintenance mode enabled (Apache 503) for ${lp}"
        else
            print warning "Apache config test failed; maintenance conf left disabled for ${lp}"
            a2disconf -q "$conf_basename" >/dev/null 2>&1 || true
        fi
    else
        print warning "a2enconf not available; skipping Apache-level maintenance mode for ${lp}"
    fi

    # Also drop a simple marker file as a cheap backstop for app-level checks.
    touch "${doc_root}/.maintenance" 2>/dev/null || true
    return 0
}

# disable_maintenance_mode — undo what enable_maintenance_mode did.
# No-op unless --maintenance / -M was passed. Also removes any stale marker file
# from a prior interrupted run so users aren't left looking at a 503 forever.
disable_maintenance_mode() {
    local lp="$1"
    # Always remove marker files in case a previous run left them behind.
    rm -f "${lp}/public/.maintenance" "${lp}/public/.intelis-maintenance.html" 2>/dev/null || true
    if [ "${show_maintenance:-false}" != "true" ]; then
        return 0
    fi
    local conf_file
    conf_file=$(maintenance_conf_path "$lp")
    local conf_basename
    conf_basename=$(basename "$conf_file" .conf)

    if command -v a2disconf >/dev/null 2>&1; then
        a2disconf -q "$conf_basename" >/dev/null 2>&1 || true
    fi
    if [ -f "$conf_file" ]; then
        rm -f "$conf_file"
    fi
    rm -f "${lp}/public/.maintenance" 2>/dev/null || true
    rm -f "${lp}/public/.intelis-maintenance.html" 2>/dev/null || true

    if command -v apache2ctl >/dev/null 2>&1 && apache2ctl -t >/dev/null 2>&1; then
        apache2ctl -k graceful >/dev/null 2>&1 || systemctl reload apache2 >/dev/null 2>&1 || true
    fi
    print info "Maintenance mode disabled for ${lp}"
}

# Paths excluded from rollback snapshot/restore. User-data and ephemeral dirs
# aren't touched by the apply phase, so snapshotting them just burns stat()
# time on big installs — and restoring with --delete over them would nuke
# uploads created between snapshot and failure.
ROLLBACK_EXCLUDES=(
    --exclude 'public/temporary/'
    --exclude 'public/files/'
    --exclude 'var/'
    --exclude 'vendor/'
)

# create_rollback_snapshot — hardlink snapshot of $lp. Echoes snapshot path on
# stdout; all informational output goes to stderr so callers can capture cleanly.
create_rollback_snapshot() {
    local lp="$1"
    local ts="$2"   # shared timestamp so snapshots for same run cluster together
    local snap_dir="${ROLLBACK_BASE_DIR}/${ts}/$(basename "$lp")"
    mkdir -p "$snap_dir" >&2
    # --link-dest makes this near-zero-disk: unchanged files become hardlinks.
    if rsync -a "${ROLLBACK_EXCLUDES[@]}" --link-dest="$lp" "$lp/" "$snap_dir/" >/dev/null 2>&1; then
        print info "Rollback snapshot created at ${snap_dir}" >&2
        echo "$snap_dir"
        return 0
    fi
    print warning "Rollback snapshot failed for ${lp} (continuing without rollback safety net)" >&2
    return 1
}

# restore_rollback_snapshot — rsync a snapshot back over lis_path. Uses --delete
# so files added by the failed apply get removed. Excludes must match the
# snapshot so --delete doesn't wipe user-data dirs that were never snapshotted.
restore_rollback_snapshot() {
    local lp="$1"
    local snap="$2"
    if [ -z "$snap" ] || [ ! -d "$snap" ]; then
        print error "No usable snapshot to restore for ${lp}"
        return 1
    fi
    print warning "Restoring ${lp} from snapshot ${snap}"
    log_action "Restoring ${lp} from rollback snapshot ${snap}"
    rsync -a --delete "${ROLLBACK_EXCLUDES[@]}" "$snap/" "$lp/" >/dev/null 2>&1 || {
        print error "Rollback rsync failed for ${lp}"
        return 1
    }
    chown -R www-data:www-data "$lp" 2>/dev/null || true

    # vendor/ is excluded from snapshot to save disk/stat time. Rebuild it from
    # the restored (old) composer.lock so PHP autoloading matches the rolled-back
    # code. Wipe first so leftover new-version packages can't shadow old ones.
    if [ -f "${lp}/composer.lock" ]; then
        print info "Rebuilding vendor/ from snapshot composer.lock..."
        rm -rf "${lp}/vendor"
        if (cd "$lp" && COMPOSER_ALLOW_SUPERUSER=1 sudo -u www-data composer install --prefer-dist --no-dev --no-interaction); then
            (cd "$lp" && COMPOSER_ALLOW_SUPERUSER=1 sudo -u www-data composer dump-autoload -o --no-interaction) || true
            chown -R www-data:www-data "${lp}/vendor" 2>/dev/null || true
            print success "vendor/ rebuilt from snapshot composer.lock"
        else
            print error "composer install failed during rollback; vendor/ is missing"
            log_action "Rollback composer install failed for ${lp}"
            return 1
        fi
    fi

    print success "Rollback complete for ${lp}"
    return 0
}

# prune_rollback_snapshots — delete all but the ROLLBACK_KEEP most recent
# timestamped snapshot dirs under ROLLBACK_BASE_DIR. Called after a successful
# apply run so operators still have the latest N generations (including the
# one just created) available for manual recovery.
prune_rollback_snapshots() {
    local keep="${ROLLBACK_KEEP:-3}"
    [ -d "$ROLLBACK_BASE_DIR" ] || return 0

    local -a snap_dirs=()
    local d
    while IFS= read -r d; do
        [ -d "$d" ] || continue
        local base
        base="$(basename "$d")"
        # Only prune dirs matching the timestamp format used by apply_run_ts
        [[ "$base" =~ ^[0-9]{8}-[0-9]{6}$ ]] || continue
        snap_dirs+=("$d")
    done < <(find "$ROLLBACK_BASE_DIR" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | sort -r)

    local total="${#snap_dirs[@]}"
    if [ "$total" -le "$keep" ]; then
        return 0
    fi

    local pruned=0
    local i
    for (( i=keep; i<total; i++ )); do
        if rm -rf "${snap_dirs[$i]}" 2>/dev/null; then
            pruned=$((pruned + 1))
        fi
    done

    if [ "$pruned" -gt 0 ]; then
        print info "Pruned ${pruned} old rollback snapshot(s); kept the ${keep} most recent"
        log_action "Pruned ${pruned} rollback snapshots under ${ROLLBACK_BASE_DIR} (kept ${keep})"
    fi
}

# smoke_check — cheap post-apply sanity check. Prefers a smoke.php endpoint if
# present, otherwise checks that public/index.php parses.
smoke_check() {
    local lp="$1"
    if [ -f "${lp}/public/smoke.php" ] && command -v curl >/dev/null 2>&1; then
        local code
        code=$(curl -fsS -o /dev/null -w "%{http_code}" "http://localhost/smoke.php" 2>/dev/null || echo "000")
        if [ "$code" = "200" ]; then
            return 0
        fi
        print warning "Smoke HTTP check returned ${code}; falling back to PHP lint"
    fi
    if [ -f "${lp}/public/index.php" ]; then
        if php -l "${lp}/public/index.php" >/dev/null 2>&1; then
            return 0
        fi
        print error "Smoke check: public/index.php failed PHP lint"
        return 1
    fi
    print warning "Smoke check: no public/index.php to lint; skipping"
    return 0
}

# ---------------------------------------------------------------------------
# Resolve staging dir: either validate the caller-supplied one, or run the
# prepare phase now. prepare_only exits here.
# ---------------------------------------------------------------------------
if [ -n "$apply_prepared_dir" ]; then
    # Normalise to absolute path
    if [ "${apply_prepared_dir:0:1}" != "/" ]; then
        apply_prepared_dir="$(pwd)/${apply_prepared_dir}"
    fi
    if [ ! -d "$apply_prepared_dir" ]; then
        print error "--apply-prepared dir does not exist: ${apply_prepared_dir}"
        exit 1
    fi
    if [ ! -f "${apply_prepared_dir}/READY" ]; then
        print error "Staging dir ${apply_prepared_dir} is missing the READY sentinel. Aborting."
        exit 1
    fi
    if [ ! -d "${apply_prepared_dir}/intelis-master" ] || [ ! -f "${apply_prepared_dir}/intelis-master/composer.json" ]; then
        print error "Staging dir ${apply_prepared_dir} is missing intelis-master/ contents. Aborting."
        exit 1
    fi
    staging_dir="$apply_prepared_dir"
    print info "Applying from pre-prepared staging dir: ${staging_dir}"
    log_action "Applying from pre-prepared staging dir: ${staging_dir}"
else
    print header "Downloading LIS"
    # WHY: prepare_phase does its own error reporting (with log file paths).
    # Suspend the generic ERR trap so a network failure inside $(...) surfaces
    # as a friendly message instead of the stack-trace-ish "Error on or near
    # line N; command executed was 'staging_dir=\"\$(prepare_phase)\"'".
    previous_err_trap="$(trap -p ERR || true)"
    trap - ERR
    prepare_rc=0
    staging_dir="$(prepare_phase)" || prepare_rc=$?
    if [ -n "${previous_err_trap}" ]; then
        eval "${previous_err_trap}"
    fi

    if [ "${prepare_rc}" -ne 0 ] || [ -z "$staging_dir" ] || [ ! -f "${staging_dir}/READY" ]; then
        echo
        print error "Could not download the LIS update package."
        print info "This is almost always a network problem on this server."
        print info "Things to check:"
        print info "  • Is the server online?  ping -c2 codeload.github.com"
        print info "  • Can it reach GitHub?   curl -IL https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master"
        print info "  • Is a proxy/firewall blocking outbound HTTPS?"
        if [ -n "$staging_dir" ] && [ -d "$staging_dir" ]; then
            print info ""
            print info "Detailed logs (while staging is kept):"
            [ -f "${staging_dir}/master.log" ] && print info "  ${staging_dir}/master.log"
            [ -f "${staging_dir}/vendor.log" ] && print info "  ${staging_dir}/vendor.log"
        fi
        print info ""
        print info "Once the network is working, just run the upgrade again."
        log_action "Prepare phase failed (rc=${prepare_rc}) - update aborted; likely network issue"
        # Keep the staging dir so the user can inspect the logs. A later rerun
        # will create a new timestamped staging dir; old ones can be cleaned up
        # manually from ${STAGING_BASE_DIR} when the upgrade has succeeded.
        exit 1
    fi

    if [ "$prepare_only" = true ]; then
        echo
        print success "Prepared at ${staging_dir}"
        echo "Apply later with: sudo intelis-update --apply-prepared ${staging_dir}"
        log_action "Prepare-only mode complete at ${staging_dir}"
        exit 0
    fi
fi

# temp_dir points at the extracted master tree, matching the old contract that
# upgrade_instance() expects ($temp_dir/intelis-master/ is the source root).
temp_dir="$staging_dir"

print success "LIS package ready for deployment to ${#lis_paths[@]} instance(s)."

# Track which instances were updated for summary
declare -a updated_instances=()
declare -a failed_instances=()

# Shared timestamp for rollback snapshots across all instances in this run.
apply_run_ts="$(date +%Y%m%d-%H%M%S)"

# Function to upgrade a single instance
upgrade_instance() {
    local lis_path="$1"
    local instance_num="$2"
    local total_instances="$3"
    local temp_dir="$4"

    print header "Upgrading instance ${instance_num}/${total_instances}: ${lis_path}"
    log_action "Starting upgrade for instance: ${lis_path}"

    # --- Rollback snapshot (before any destructive change) ---
    local _snapshot_dir=""
    _snapshot_dir="$(create_rollback_snapshot "$lis_path" "$apply_run_ts" || true)"

    # --- Maintenance mode (503 via Apache) ---
    enable_maintenance_mode "$lis_path"

    # Helper: called on any failure below. Disables maintenance and restores snapshot.
    _apply_failure() {
        local reason="$1"
        print error "Apply failed for ${lis_path}: ${reason}"
        log_action "Apply failed for ${lis_path}: ${reason}"
        if [ -n "$_snapshot_dir" ]; then
            restore_rollback_snapshot "$lis_path" "$_snapshot_dir" || true
        fi
        disable_maintenance_mode "$lis_path"
    }

    # Like _apply_failure but WITHOUT restoring the snapshot — the new files stay
    # in place for debugging, and maintenance mode is left ON (503). Used for
    # DB-step failures: the instance can't safely serve traffic against an
    # un-migrated schema, so keep it dark and surface the failure loudly rather
    # than reverting code or (worse) declaring success.
    _apply_failure_no_rollback() {
        local reason="$1"
        print error "Apply failed for ${lis_path}: ${reason}"
        print error "  Left in MAINTENANCE mode (503) pending manual fix; code NOT rolled back."
        log_action "Apply failed (no rollback) for ${lis_path}: ${reason}; left in maintenance mode"
    }

    # Remove old run-once directory
    if [ -d "${lis_path}/run-once" ]; then
        rm -rf "${lis_path}/run-once"
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
        log_action "Error during rsync operation. Path was: $lis_path"
        _apply_failure "master rsync failed"
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
    set_permissions "${lis_path}" "quick" "sync"

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

    # Ensure composer files are writable by www-data before running composer commands
    chown www-data:www-data "${lis_path}/composer.json" "${lis_path}/composer.lock" 2>/dev/null || true

    sudo -u www-data composer config process-timeout 30000 --no-interaction
    sudo -u www-data composer clear-cache --no-interaction

    # Install vendor from the pre-staged directory if available; otherwise fall
    # back to composer install. Prepare phase already downloaded + verified the
    # vendor tarball into "$temp_dir/vendor/" (if the release was published).
    local staged_vendor="${temp_dir}/vendor"
    if [ -d "$staged_vendor" ] && [ -d "$staged_vendor/composer" ]; then
        print info "Installing dependencies from staged vendor directory..."
        rsync -a --delete "$staged_vendor/" "${lis_path}/vendor/" &
        local vendor_sync_pid=$!
        spinner "${vendor_sync_pid}"
        wait ${vendor_sync_pid}

        chown -R www-data:www-data "${lis_path}/vendor" 2>/dev/null || true
        chmod -R 755 "${lis_path}/vendor" 2>/dev/null || true

        # Reconcile the staged vendor against this instance's composer.lock. The
        # staged dir seeds vendor/ (so there's little/nothing to download), but
        # we still run install — no scripts, no autoloader (dump-autoload runs
        # below) — so a vendor-latest release that lags master's lock can't leave
        # the instance with mismatched dependencies. A fast no-op when they match.
        print info "Reconciling staged vendor against composer.lock..."
        sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev --no-interaction
    else
        print info "Staged vendor not available; running composer install..."
        sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
    fi

    sudo -u www-data composer dump-autoload -o --no-interaction
    print success "Composer operations completed."

    # Database connectivity, migrations and repairs. These mutate the schema, so
    # a failure here means the instance is NOT safely upgraded — surface it
    # instead of falling through to a "success" report (the old code ignored
    # every exit code and declared success even when migrations never ran).
    #
    # Connectivity is retried with backoff: during multi-instance runs MySQL can
    # be briefly unreachable just after a config reload / restart (socket being
    # recreated), and a transient blip shouldn't fail a healthy instance. Probe
    # as www-data so we exercise the same credentials/socket the migrations use.
    print info "Checking database connectivity..."
    local db_ok=0 db_try
    for db_try in 1 2 3 4 5; do
        if sudo -u www-data php "${lis_path}/vendor/bin/db-tools" db:test --all >/dev/null 2>&1; then
            db_ok=1
            break
        fi
        print warning "Database not reachable (attempt ${db_try}/5); retrying in $((db_try * 3))s..."
        sleep $((db_try * 3))
    done
    if [ "$db_ok" -ne 1 ]; then
        _apply_failure_no_rollback "database connectivity check failed after 5 attempts"
        return 1
    fi

    print info "Running database migrations..."
    if ! sudo -u www-data composer post-update; then
        _apply_failure_no_rollback "database migrations (composer post-update) failed"
        return 1
    fi

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

    # Smoke check BEFORE we disable maintenance so users never see a broken app.
    if ! smoke_check "${lis_path}"; then
        _apply_failure "smoke check failed"
        return 1
    fi

    # Apply succeeded — remove maintenance mode.
    disable_maintenance_mode "${lis_path}"

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

# Install or refresh the remote command runner (root-owned, systemd-timed).
# Idempotent — the installer overwrites the binary + unit files and reloads
# systemd. If any lab doesn't want remote commands, it can simply set
# global_config.remote_commands_enabled = 'no' (or leave it unset, the default);
# the courier then never drops markers and the runner is a 60s no-op.
#
# Best-effort: if the first lis_path doesn't contain the installer files yet
# (e.g. fresh install mid-upgrade), we skip gracefully. A subsequent upgrade
# will finish the install.
runner_installer="${first_lis_path:-${lis_paths[0]}}/scripts/install-runner.sh"
if [ -f "$runner_installer" ]; then
    print header "Installing remote command runner"
    if bash "$runner_installer" --source-dir "$(dirname "$runner_installer")" >>"$log_file" 2>&1; then
        print success "Remote command runner installed / refreshed."
        log_action "intelis-runner installed via $runner_installer"
    else
        print warning "Remote command runner install failed; see $log_file. Upgrade will continue."
        log_action "intelis-runner install failed"
    fi
else
    log_action "Skipping runner install (installer not found at $runner_installer)"
fi

# Process each instance
total_instances=${#lis_paths[@]}

# Initialize status tracking
declare -a instance_statuses
for i in "${!lis_paths[@]}"; do
    instance_statuses[$i]="pending"
done

# Show initial status board for multi-instance runs
if [ "$total_instances" -gt 1 ]; then
    print_instance_status lis_paths instance_statuses
fi

for i in "${!lis_paths[@]}"; do
    # Mark current as running and show updated board
    if [ "$total_instances" -gt 1 ]; then
        instance_statuses[$i]="running"
        print_instance_status lis_paths instance_statuses
    fi

    if upgrade_instance "${lis_paths[$i]}" "$((i+1))" "$total_instances" "$temp_dir"; then
        updated_instances+=("${lis_paths[$i]}")
        instance_statuses[$i]="done"
    else
        failed_instances+=("${lis_paths[$i]}")
        instance_statuses[$i]="failed"
    fi

    # Show status after completion
    if [ "$total_instances" -gt 1 ]; then
        print_instance_status lis_paths instance_statuses
    fi
done

# Prune old rollback snapshots, keeping the N most recent (including this run's).
prune_rollback_snapshots

# Maintenance scripts prompt (only for single instance to avoid tedious multi-prompts).
# Skipped under --apply-prepared since operator context (and TTY) may differ from
# the original prepare invocation; running extra interactive prompts at that point
# would hang automated apply flows.
if [ ${#lis_paths[@]} -eq 1 ] && [ -z "$apply_prepared_dir" ]; then
    lis_path="${lis_paths[0]}"
    echo ""
    files=()
    for f in "${lis_path}/maintenance/"*.php; do
        [ -f "$f" ] && files+=("$f")
    done

    if [ ${#files[@]} -gt 0 ] && ask_yes_no "Do you want to run maintenance scripts?" "no"; then
        echo "Available maintenance scripts to run:"
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

# Cleanup temp files.
# When running via --apply-prepared we leave the staging dir alone: the operator
# (or the remote-runner that prepared it) is responsible for its lifecycle.
# When we prepared it in this run, remove it only if every instance succeeded.
if [ -z "$apply_prepared_dir" ]; then
    if [ ${#failed_instances[@]} -eq 0 ] && [ -n "$staging_dir" ] && [ -d "$staging_dir" ]; then
        rm -rf "$staging_dir"
    elif [ ${#failed_instances[@]} -gt 0 ]; then
        print info "Leaving staging dir in place for debugging: ${staging_dir}"
    fi
fi
# Legacy paths from the pre-refactor script — remove if they happen to exist
# (e.g. an older in-flight run crashed before cleanup).
[ -f master.tar.gz ] && rm master.tar.gz
[ -f "/tmp/vendor.tar.gz" ] && rm /tmp/vendor.tar.gz
[ -f "/tmp/vendor.tar.gz.md5" ] && rm /tmp/vendor.tar.gz.md5
true  # absorb nonzero exit from the tests above

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
