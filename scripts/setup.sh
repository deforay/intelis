#!/bin/bash

# To use this script:
# cd ~;
# wget -O intelis-setup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh
# sudo chmod u+x intelis-setup.sh;
# sudo ./intelis-setup.sh;

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print error "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
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

log_file="/tmp/intelis-setup-$(date +'%Y%m%d-%H%M%S').log"

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

handle_database_setup_and_import() {
    db_exists=$(mysql -sse "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'vlsm';")
    db_not_empty=$(mysql -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'vlsm';")

    if [ "$db_exists" -eq 1 ] && [ "$db_not_empty" -gt 0 ]; then
        echo "Renaming existing LIS database..."
        log_action "Renaming existing LIS database..."
        local todays_date=$(date +%Y%m%d_%H%M%S)
        local new_db_name="vlsm_${todays_date}"
        mysql -e "CREATE DATABASE ${new_db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

        # Get the list of tables in the original database
        local tables=$(mysql -sse "SHOW TABLES IN vlsm;")

        # Rename tables
        for table in $tables; do
            mysql -e "RENAME TABLE vlsm.$table TO ${new_db_name}.$table;"
        done

        echo "Copying triggers..."
        log_action "Copying triggers..."
        local triggers=$(mysql -sse "SHOW TRIGGERS IN vlsm;")
        for trigger_name in $triggers; do
            local trigger_sql=$(mysql -sse "SHOW CREATE TRIGGER vlsm.$trigger_name\G" | sed -n 's/.*SQL: \(.*\)/\1/p')
            mysql -D ${new_db_name} -e "$trigger_sql"
        done

        echo "All tables and triggers moved to ${new_db_name}."
        log_action "All tables and triggers moved to ${new_db_name}."
    fi

    mysql -e "CREATE DATABASE IF NOT EXISTS vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    local sql_file="${1:-${lis_path}/sql/init.sql}"
    if [[ "$sql_file" == *".gz" ]]; then
        gunzip -c "$sql_file" | mysql vlsm
    elif [[ "$sql_file" == *".zip" ]]; then
        unzip -p "$sql_file" | mysql vlsm
    else
        mysql vlsm <"$sql_file"
    fi
    mysql vlsm <"${lis_path}/sql/audit-triggers.sql"
    mysql interfacing <"${lis_path}/sql/interface-init.sql"
}


# Save the current trap settings
current_trap=$(trap -p ERR)

# Disable the error trap temporarily
trap - ERR

echo "Enter the LIS installation path [press enter to select /var/www/intelis]: "
read -t 60 lis_path

# Check if read command timed out or no input was provided
if [ $? -ne 0 ] || [ -z "$lis_path" ]; then
    lis_path="/var/www/intelis"
    echo "Using default path: $lis_path"
else
    echo "LIS installation path is set to ${lis_path}."
fi

log_action "LIS installation path is set to ${lis_path}."

# Restore the previous error trap
eval "$current_trap"

# Initialize variable for database file path
intelis_sql_file=""

# Parse command-line arguments for --database or --db flag
for arg in "$@"; do
    case $arg in
    --database=* | --db=*)
        intelis_sql_file="${arg#*=}"
        shift # Remove --database or --db argument from processing
        ;;
    --database | --db)
        intelis_sql_file="$2"
        shift # Remove --database or --db argument
        shift # Remove its associated value
        ;;
    esac
done

# Check if the specified SQL file exists
if [[ -n "$intelis_sql_file" ]]; then
    # Check if the file path is absolute or relative
    if [[ "$intelis_sql_file" != /* ]]; then
        # File path is relative, check in the current directory
        intelis_sql_file="$(pwd)/$intelis_sql_file"
    fi

    if [[ ! -f "$intelis_sql_file" ]]; then
        echo "SQL file not found: $intelis_sql_file. Please check the path."
        log_action "SQL file not found: $intelis_sql_file. Please check the path."
        exit 1
    fi
fi

PHP_VERSION=8.2

# Download and install lamp-setup script
download_file  "lamp-setup.sh" "https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh" "Downloading lamp-setup.sh..." || {
    print error "LAMP Setup file download failed - cannot continue with update"
    log_action "LAMP Setup file download failed - update aborted"
    exit 1
}

chmod u+x ./lamp-setup.sh

./lamp-setup.sh $PHP_VERSION

rm -f ./lamp-setup.sh

echo "Calculating checksums of current composer files..."
CURRENT_COMPOSER_JSON_CHECKSUM="none"
CURRENT_COMPOSER_LOCK_CHECKSUM="none"

if [ -f "${lis_path}/composer.json" ]; then
    CURRENT_COMPOSER_JSON_CHECKSUM=$(md5sum "${lis_path}/composer.json" | awk '{print $1}')
    echo "Current composer.json checksum: ${CURRENT_COMPOSER_JSON_CHECKSUM}"
fi

if [ -f "${lis_path}/composer.lock" ]; then
    CURRENT_COMPOSER_LOCK_CHECKSUM=$(md5sum "${lis_path}/composer.lock" | awk '{print $1}')
    echo "Current composer.lock checksum: ${CURRENT_COMPOSER_LOCK_CHECKSUM}"
fi

# LIS Setup
print header "Downloading LIS"

download_file "master.tar.gz" "https://codeload.github.com/deforay/intelis/tar.gz/refs/heads/master" "Downloading LIS package..." || {
    print error "LIS download failed - cannot continue with setup"
    log_action "LIS download failed - setup aborted"
    exit 1
}

# Extract the tar.gz file into temporary directory
temp_dir=$(mktemp -d)
print info "Extracting files from master.tar.gz..."

tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!           # Save tar PID
spinner "${tar_pid}" # Spinner tracks extraction
wait ${tar_pid}      # Wait for extraction to finish

log_action "LIS downloaded."

# backup old code if it exists
if [ -d "${lis_path}" ]; then
    cp -R "${lis_path}" "${lis_path}"-$(date +%Y%m%d-%H%M%S)
else
    mkdir -p "${lis_path}"
fi

# Copy the unzipped content to the LIS PATH, overwriting any existing files
# cp -R "$temp_dir/intelis-master/"* "${lis_path}"
rsync -a --info=progress2 "$temp_dir/intelis-master/" "$lis_path/"

# Remove the empty directory and the downloaded zip file
rm -rf "$temp_dir/intelis-master/"
rm master.tar.gz

log_action "LIS copied to ${lis_path}."

# Set proper permissions
set_permissions "${lis_path}" "quick"
find "${lis_path}" -exec chown www-data:www-data {} \; 2>/dev/null || true

# Run Composer Install as www-data
print header "Running composer operations"
cd "${lis_path}"

# Configure composer timeout regardless of installation path
sudo -u www-data composer config process-timeout 30000
sudo -u www-data composer clear-cache

echo "Checking if composer dependencies need updating..."
NEED_FULL_INSTALL=false

# Check if the vendor directory exists
if [ ! -d "${lis_path}/vendor" ]; then
    echo "Vendor directory doesn't exist. Full installation needed."
    NEED_FULL_INSTALL=true
else
    # Calculate new checksums
    NEW_COMPOSER_JSON_CHECKSUM="none"
    NEW_COMPOSER_LOCK_CHECKSUM="none"

    if [ -f "${lis_path}/composer.json" ]; then
        NEW_COMPOSER_JSON_CHECKSUM=$(md5sum "${lis_path}/composer.json" 2>/dev/null | awk '{print $1}')
        echo "New composer.json checksum: ${NEW_COMPOSER_JSON_CHECKSUM}"
    else
        echo "Warning: composer.json is missing after extraction. Full installation needed."
        NEED_FULL_INSTALL=true
    fi

    if [ -f "${lis_path}/composer.lock" ] && [ "$NEED_FULL_INSTALL" = false ]; then
        NEW_COMPOSER_LOCK_CHECKSUM=$(md5sum "${lis_path}/composer.lock" 2>/dev/null | awk '{print $1}')
        echo "New composer.lock checksum: ${NEW_COMPOSER_LOCK_CHECKSUM}"
    else
        echo "Warning: composer.lock is missing after extraction. Full installation needed."
        NEED_FULL_INSTALL=true
    fi

    # Only do checksum comparison if we haven't already determined we need a full install
    if [ "$NEED_FULL_INSTALL" = false ]; then
        # Compare checksums - only if both files existed before and after
        if [ "$CURRENT_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
            [ "$NEW_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$NEW_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
            [ "$CURRENT_COMPOSER_JSON_CHECKSUM" != "$NEW_COMPOSER_JSON_CHECKSUM" ] ||
            [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" != "$NEW_COMPOSER_LOCK_CHECKSUM" ]; then
            echo "Composer files have changed or were missing. Full installation needed."
            NEED_FULL_INSTALL=true
        else
            echo "Composer files haven't changed. Skipping full installation."
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

log_action "Composer operations completed."

# Function to configure Apache Virtual Host
configure_vhost() {
    local vhost_file=$1
    local document_root="${lis_path}/public"
    local directory_block="<Directory ${lis_path}/public>\n\
        AddDefaultCharset UTF-8\n\
        Options -Indexes -MultiViews +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>"

    # Replace the DocumentRoot line
    sed -i "s|DocumentRoot .*|DocumentRoot ${document_root}|" "$vhost_file"

    # Check if any Directory block exists
    if grep -q "<Directory" "$vhost_file"; then
        # Replace existing Directory block
        sed -i "/<Directory/,/<\/Directory>/c\\$directory_block" "$vhost_file"
    else
        # Insert Directory block after DocumentRoot line
        sed -i "/DocumentRoot/a\\$directory_block" "$vhost_file"
    fi
}

# Ask user for the hostname
read -p "Enter domain name (press enter to use 'intelis'): " hostname

# Clean up the hostname: remove protocol and trailing slashes
if [[ -n "$hostname" ]]; then
    # Remove http:// or https:// if present
    hostname=$(echo "$hostname" | sed -E 's|^https?://||i')

    # Remove trailing slashes
    hostname=$(echo "$hostname" | sed -E 's|/*$||')

    # Remove any port number if present
    hostname=$(echo "$hostname" | sed -E 's|:[0-9]+$||')

    # Remove any path components
    hostname=$(echo "$hostname" | cut -d'/' -f1)

    # If user entered something that became empty after cleanup, use default
    if [[ -z "$hostname" ]]; then
        hostname="intelis"
        print info "Using default hostname: $hostname"
    else
        print info "Using cleaned hostname: $hostname"
    fi
else
    hostname="intelis"
    print info "Using default hostname: $hostname"
fi

log_action "Hostname: $hostname"

# Check if the hostname entry is already in /etc/hosts
if ! grep -q "127.0.0.1 ${hostname}" /etc/hosts; then
    print info "Adding ${hostname} to hosts file..."
    echo "127.0.0.1 ${hostname}" | tee -a /etc/hosts
    log_action "${hostname} entry added to hosts file."
else
    print info "${hostname} entry is already in the hosts file."
    log_action "${hostname} entry is already in the hosts file."
fi

# Ask user if they're installing LIS or STS
read -p "Is this an LIS or STS installation? [LIS/STS] (press enter for default: LIS): " installation_type
# Default to LIS if empty
installation_type="${installation_type:-LIS}"
# Convert to lowercase first character for case-insensitive comparison
first_char=$(echo "$installation_type" | cut -c1 | tr '[:upper:]' '[:lower:]')

if [[ "$first_char" == "l" ]]; then
    echo "Installing InteLIS as the default host..."
    log_action "Installing InteLIS as the default host..."
    apache_vhost_file="/etc/apache2/sites-available/000-default.conf"
    cp "$apache_vhost_file" "${apache_vhost_file}.bak"
    configure_vhost "$apache_vhost_file"
elif [[ "$first_char" == "s" ]]; then
    echo "Installing InteLIS alongside other apps..."
    log_action "Installing InteLIS alongside other apps..."
    vhost_file="/etc/apache2/sites-available/${hostname}.conf"
    echo "<VirtualHost *:80>
    ServerName ${hostname}
    DocumentRoot ${lis_path}/public
    <Directory ${lis_path}/public>
        AddDefaultCharset UTF-8
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>" >"$vhost_file"
    a2ensite "${hostname}.conf"
else
    echo "Invalid installation type '$installation_type'. Defaulting Intelis to LIS installation..."
    log_action "Invalid installation type '$installation_type'. Defaulting Intelis to LIS installation..."
    apache_vhost_file="/etc/apache2/sites-available/000-default.conf"
    cp "$apache_vhost_file" "${apache_vhost_file}.bak"
    configure_vhost "$apache_vhost_file"
fi

# Restart Apache to apply changes
restart_service apache || {
    print error "Failed to restart Apache. Please check the configuration."
    log_action "Failed to restart Apache. Please check the configuration."
    exit 1
}

# Cron job setup
setup_intelis_cron "${lis_path}"


# Update LIS config.production.php with database credentials
config_file="${lis_path}/configs/config.production.php"
source_file="${lis_path}/configs/config.production.dist.php"

if [ ! -e "${config_file}" ]; then
    print info  "Renaming config.production.dist.php to config.production.php..."
    log_action "Renaming config.production.dist.php to config.production.php..."
    mv "${source_file}" "${config_file}"
else
    echo "File config.production.php already exists. Skipping renaming."
    log_action "File config.production.php already exists. Skipping renaming."
fi

# Extract MySQL root password or create ~/.my.cnf if missing

if [ -f ~/.my.cnf ]; then
    # Extract password from .my.cnf
    mysql_root_password=$(awk -F= '/password/ {print $2}' ~/.my.cnf | xargs)
    echo "MySQL root password extracted"
else
    # Prompt user for MySQL root password
    echo "Warning: mysql password not found. Please provide the MySQL root password to create one."
    while true; do
        read -sp "Enter MySQL root password: " mysql_root_password
        echo
        read -sp "Confirm MySQL root password: " mysql_root_password_confirm
        echo

        if [ "$mysql_root_password" != "$mysql_root_password_confirm" ]; then
            print error "Passwords do not match. Please try again."
        elif [ -z "$mysql_root_password" ]; then
            print error "Password cannot be empty. Please try again."
        else
            break
        fi
    done

    # Verify the password
    echo "Verifying MySQL root password..."
    if ! mysqladmin ping -u root -p"$mysql_root_password" &>/dev/null; then
        print error "Unable to verify the password. Please check and try again."
        exit 1
    fi

    # Create ~/.my.cnf
    echo "Storing MySQL password for secure login..."
    cat <<EOF >~/.my.cnf
[client]
user=root
password=${mysql_root_password}
host=localhost
EOF
    chmod 600 ~/.my.cnf

    echo "MySQL credentials saved in secure file."
fi

# Escape special characters in password for sed
# This uses Perl's quotemeta which is more reliable when dealing with many special characters
escaped_mysql_root_password=$(perl -e 'print quotemeta $ARGV[0]' -- "${mysql_root_password}")

# Use sed to update database configurations, using | as a delimiter instead of /
sed -i "s|\$systemConfig\['database'\]\['host'\]\s*=.*|\$systemConfig['database']['host'] = 'localhost';|" "${config_file}"
sed -i "s|\$systemConfig\['database'\]\['username'\]\s*=.*|\$systemConfig['database']['username'] = 'root';|" "${config_file}"
sed -i "s|\$systemConfig\['database'\]\['password'\]\s*=.*|\$systemConfig['database']['password'] = '$escaped_mysql_root_password';|" "${config_file}"

sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['host'\]\s*=.*|\$systemConfig['interfacing']['database']['host'] = 'localhost';|" "${config_file}"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['username'\]\s*=.*|\$systemConfig['interfacing']['database']['username'] = 'root';|" "${config_file}"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['password'\]\s*=.*|\$systemConfig['interfacing']['database']['password'] = '$escaped_mysql_root_password';|" "${config_file}"

# Handle database setup and SQL file import
if [[ -n "$intelis_sql_file" && -f "$intelis_sql_file" ]]; then
    handle_database_setup_and_import "$intelis_sql_file"
elif [[ -n "$intelis_sql_file" ]]; then
    print error "SQL file not found: $intelis_sql_file. Please check the path."
    exit 1
else
    handle_database_setup_and_import # Default to init.sql
fi


config_file="/etc/mysql/mysql.conf.d/mysqld.cnf"
backup_timestamp=$(date +%Y%m%d%H%M%S)

# --- define what we want ---
declare -A mysql_settings=(
    ["sql_mode"]=""
    ["innodb_strict_mode"]="0"
    ["character-set-server"]="utf8mb4"
    ["collation-server"]="utf8mb4_general_ci"
    ["default_authentication_plugin"]="mysql_native_password"
    ["max_connect_errors"]="10000"
)

changes_needed=false

# --- dry-run check first ---
for setting in "${!mysql_settings[@]}"; do
    if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$config_file"; then
        changes_needed=true
        break
    fi
done

if [ "$changes_needed" = true ]; then
    print info "Changes needed. Backing up and updating MySQL config..."
    cp "$config_file" "${config_file}.bak.${backup_timestamp}"

    for setting in "${!mysql_settings[@]}"; do
        if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$config_file"; then
            # Comment existing wrong setting if found
            if grep -qE "^[[:space:]]*$setting[[:space:]]*=" "$config_file"; then
                sed -i "/^[[:space:]]*$setting[[:space:]]*=.*/s/^/#/" "$config_file"
            fi
            echo "$setting = ${mysql_settings[$setting]}" >>"$config_file"
        fi
    done

    print info "Restarting MySQL service to apply changes..."
    restart_service mysql || {
        print error "Failed to restart MySQL. Restoring backup and exiting..."
        mv "${config_file}.bak.${backup_timestamp}" "$config_file"
        restart_service mysql
        exit 1
    }

    print success "MySQL configuration updated successfully."

else
    print success "MySQL configuration already correct. No changes needed."
fi

# --- Always clean up old .bak files ---
find "$(dirname "$config_file")" -maxdepth 1 -type f -name "$(basename "$config_file").bak.*" -exec rm -f {} \;
print info "Removed all MySQL backup files matching *.bak.*"


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

chmod 644 /etc/mysql/mysql.conf.d/mysqld.cnf
restart_service mysql


# Prompt for Remote STS URL
while true; do
    read -p "Please enter the Remote STS URL (or press Enter to skip): " remote_sts_url
    log_action "Remote STS URL entered: $remote_sts_url"

    if [ -z "$remote_sts_url" ]; then
        echo "No STS URL provided. Skipping validation."
        log_action "No STS URL provided. Skipping validation."
        break
    fi

    echo "Validating the provided STS URL..."
    response_code=$(curl -s -o /dev/null -w "%{http_code}" "$remote_sts_url/api/version.php")

    if [ "$response_code" -eq 200 ]; then
        print success "STS URL validation successful."
        log_action "STS URL validation successful."

        # Define desired_sts_url
        desired_sts_url="\$systemConfig['remoteURL'] = '$remote_sts_url';"

        config_file="${lis_path}/configs/config.production.php"

        # Check if the desired configuration already exists in the file
        if ! grep -qF "$desired_sts_url" "${config_file}"; then
            # The desired configuration does not exist, so update the file
            sed -i "s|\$systemConfig\['remoteURL'\]\s*=\s*'.*';|$desired_sts_url|" "${config_file}"
            print info "Remote STS URL updated in the configuration file."
        else
            print info "Remote STS URL is already set as desired in the configuration file."
        fi
        break
    else
        print error "Failed to validate the provided STS URL (HTTP response code: $response_code). Please try again."
        log_action "STS URL validation failed with response code $response_code."
    fi
done

if grep -q "\['cache_di'\] => false" "${config_file}"; then
    sed -i "s|\('cache_di' => \)false,|\1true,|" "${config_file}"
fi

# Set ACLs
set_permissions "${lis_path}" "quick"

print header "Running database migrations and other post-install tasks"
cd "${lis_path}"
sudo -u www-data composer post-install &
pid=$!
spinner "$pid"
wait $pid

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
            echo "Running $file..."
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
                echo "Invalid selection: $i. Please select a number between 1 and ${#files[@]}. Skipping."
                log_action "Invalid selection: $i. Please select a number between 1 and ${#files[@]}. Skipping."
            fi
        done
    fi
fi

# Make the intelis script executable
chmod +x "${lis_path}/intelis" 2>/dev/null
sudo rm /usr/local/bin/intelis 2>/dev/null
sudo ln -s "${lis_path}/intelis" /usr/local/bin/intelis 2>/dev/null

# Show success message only if symlink was created successfully
if [ -L "/usr/local/bin/intelis" ]; then
    echo "✓ intelis command installed successfully!"
    echo "You can now use: intelis interface, intelis token, etc."
fi

if [ -f "${lis_path}/cache/CompiledContainer.php" ]; then
    rm "${lis_path}/cache/CompiledContainer.php"
fi

# Set proper permissions
# Set proper permissions
download_file "/usr/local/bin/intelis-refresh" https://raw.githubusercontent.com/deforay/intelis/master/scripts/refresh.sh
chmod +x /usr/local/bin/intelis-refresh
(print success "Setting final permissions in the background..." &&
    intelis-refresh -p "${lis_path}" -m full >/dev/null 2>&1 &&
    find "${lis_path}" -exec chown www-data:www-data {} \; 2>/dev/null || true) &
disown

restart_service apache

print success "Setup complete. Proceed to LIS setup."
log_action "Setup complete. Proceed to LIS setup."
