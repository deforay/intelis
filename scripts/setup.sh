#!/bin/bash

# To use this script:
# cd ~;
# wget -O intelis-setup.sh https://raw.githubusercontent.com/deforay/vlsm/master/scripts/setup.sh
# sudo chmod u+x intelis-setup.sh;
# sudo ./intelis-setup.sh;

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Function to log messages
log_action() {
    local message=$1
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $message" >>~/logsetup.log
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

    mysql -e "CREATE DATABASE IF NOT EXISTS vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
    mysql -e "CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

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

spinner() {
    local pid=$!
    local delay=0.1
    local spinstr='|/-\'
    while [ "$(ps a | awk '{print $1}' | grep $pid)" ]; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

# Check if Ubuntu version is 22.04 or newer
min_version="22.04"
current_version=$(lsb_release -rs)

if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
    echo "This script is not compatible with Ubuntu versions older than ${min_version}."
    log_action "This script is not compatible with Ubuntu versions older than ${min_version}."
    exit 1
fi

# Save the current trap settings
current_trap=$(trap -p ERR)

# Disable the error trap temporarily
trap - ERR

echo "Enter the LIS installation path [press enter to select /var/www/vlsm]: "
read -t 60 lis_path

# Check if read command timed out or no input was provided
if [ $? -ne 0 ] || [ -z "$lis_path" ]; then
    lis_path="/var/www/vlsm"
    echo "Using default path: $lis_path"
else
    echo "LIS installation path is set to ${lis_path}."
fi

log_action "LIS installation path is set to ${lis_path}."

# Restore the previous error trap
eval "$current_trap"

# Initialize variable for database file path
vlsm_sql_file=""

# Parse command-line arguments for --database or --db flag
for arg in "$@"; do
    case $arg in
    --database=* | --db=*)
        vlsm_sql_file="${arg#*=}"
        shift # Remove --database or --db argument from processing
        ;;
    --database | --db)
        vlsm_sql_file="$2"
        shift # Remove --database or --db argument
        shift # Remove its associated value
        ;;
    esac
done

# Check if the specified SQL file exists
if [[ -n "$vlsm_sql_file" ]]; then
    # Check if the file path is absolute or relative
    if [[ "$vlsm_sql_file" != /* ]]; then
        # File path is relative, check in the current directory
        vlsm_sql_file="$(pwd)/$vlsm_sql_file"
    fi

    if [[ ! -f "$vlsm_sql_file" ]]; then
        echo "SQL file not found: $vlsm_sql_file. Please check the path."
        log_action "SQL file not found: $vlsm_sql_file. Please check the path."
        exit 1
    fi
fi

PHP_VERSION=8.2

# Download and install lamp-setup script
wget -q -O lamp-setup.sh https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh
chmod u+x ./lamp-setup.sh

./lamp-setup.sh $PHP_VERSION

rm -f ./lamp-setup.sh

# LIS Setup
echo "Downloading LIS..."
wget -q --show-progress --progress=dot:giga -O master.zip https://github.com/deforay/vlsm/archive/refs/heads/master.zip

# Unzip the file into a temporary directory
temp_dir=$(mktemp -d)
unzip master.zip -d "$temp_dir"

log_action "LIS downloaded."

# backup old code if it exists
if [ -d "${lis_path}" ]; then
    cp -R "${lis_path}" "${lis_path}"-$(date +%Y%m%d-%H%M%S)
else
    mkdir -p "${lis_path}"
fi

# Copy the unzipped content to the /var/www/vlsm directory, overwriting any existing files
# cp -R "$temp_dir/vlsm-master/"* "${lis_path}"
rsync -av "$temp_dir/vlsm-master/" "$lis_path/"

# Remove the empty directory and the downloaded zip file
rm -rf "$temp_dir/vlsm-master/"
rm master.zip

log_action "LIS copied to ${lis_path}."

# Set proper permissions
chown -R www-data:www-data "${lis_path}"

# Run Composer Install as www-data
echo "Running composer operations as www-data user..."
cd "${lis_path}"

# Check if the vendor directory exists and if the lock file exists
if [ ! -d "${lis_path}/vendor" ] || [ ! -f "${lis_path}/composer.lock" ]; then
    echo "Vendor directory or composer.lock missing. Full installation needed."
    NEED_FULL_INSTALL=true
else
    # Use composer's status check to see if dependencies are up to date
    echo "Checking if composer dependencies are in sync with lock file..."
    OUT=$(sudo -u www-data composer status -n 2>&1)
    STATUS=$?

    if [ $STATUS -ne 0 ]; then
        echo "Composer dependencies are out of date or modified: $OUT"
        NEED_FULL_INSTALL=true
    else
        echo "Composer dependencies are in sync with lock file."
        NEED_FULL_INSTALL=false
    fi

    # Also check if composer.lock is outdated compared to composer.json
    echo "Checking if composer.lock is in sync with composer.json..."
    sudo -u www-data composer validate --no-check-all --no-check-publish
    if [ $? -ne 0 ]; then
        echo "composer.lock is out of sync with composer.json. Update needed."
        NEED_FULL_INSTALL=true
    fi
fi

# Configure composer timeout regardless of installation path
sudo -u www-data composer config process-timeout 30000
sudo -u www-data composer clear-cache

# Download vendor.zip if needed
if [ "$NEED_FULL_INSTALL" = true ]; then
    echo "Dependency update needed. Checking for vendor packages..."
    if curl --output /dev/null --silent --head --fail "https://github.com/deforay/vlsm/releases/download/vendor-latest/vendor.zip"; then
        echo "Vendor package found. Downloading..."
        wget -c -q --show-progress --progress=dot:giga -O vendor.zip https://github.com/deforay/vlsm/releases/download/vendor-latest/vendor.zip || {
            echo "Failed to download vendor.zip"
            exit 1
        }

        echo "Downloading checksum..."
        wget -c -q --show-progress --progress=dot:giga -O vendor.zip.md5 https://github.com/deforay/vlsm/releases/download/vendor-latest/vendor.zip.md5 || {
            echo "Failed to download vendor.zip.md5"
            exit 1
        }

        echo "Verifying checksum..."
        md5sum -c vendor.zip.md5 || {
            echo "Checksum verification failed"
            exit 1
        }

        echo "Extracting files..."
        unzip -o vendor.zip || {
            echo "Failed to extract vendor.zip"
            exit 1
        }

        # Fix permissions on the vendor directory
        chown -R www-data:www-data "${lis_path}/vendor"
        chmod -R 755 "${lis_path}/vendor"

        echo "Vendor files successfully installed"

        # Update the composer.lock file to match the current state
        sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev
    else
        echo "Vendor package not found in GitHub releases. Proceeding with regular composer install."

        # Perform full install if vendor.zip isn't available
        sudo -u www-data composer install --prefer-dist --no-dev
    fi
else
    echo "Dependencies are up to date. Skipping vendor download."
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
read -p "Enter domain name (press enter to use 'vlsm'): " hostname
hostname="${hostname:-vlsm}"

log_action "Hostname: $hostname"

# Check if the hostname entry is already in /etc/hosts
if ! grep -q "127.0.0.1 ${hostname}" /etc/hosts; then
    echo "Adding ${hostname} to hosts file..."
    echo "127.0.0.1 ${hostname}" | tee -a /etc/hosts
    log_action "${hostname} entry added to hosts file."
else
    echo "${hostname} entry is already in the hosts file."
    log_action "${hostname} entry is already in the hosts file."
fi

# Ask user if they want to install LIS as the default host or along with other apps
read -p "Install LIS as the default host? (yes for default, no for alongside other apps) [yes/no]: " install_as_default
install_as_default="${install_as_default:-yes}"

if [ "$install_as_default" = "yes" ]; then
    echo "Installing LIS as the default host..."
    apache_vhost_file="/etc/apache2/sites-available/000-default.conf"
    cp "$apache_vhost_file" "${apache_vhost_file}.bak"
    configure_vhost "$apache_vhost_file"
else
    echo "Installing LIS alongside other apps..."
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
fi

# Restart Apache to apply changes
service apache2 restart || {
    echo "Failed to restart Apache. Please check the configuration."
    log_action "Failed to restart Apache. Please check the configuration."
    exit 1
}

# Restart Apache to apply changes
service apache2 restart

# cron job

chmod +x ${lis_path}/cron.sh

cron_job="* * * * * cd ${lis_path} && ./cron.sh"

# Check if the cron job already exists
if ! crontab -l | grep -qF "${cron_job}"; then
    echo "Adding cron job for LIS..."
    log_action "Adding cron job for LIS..."
    (
        crontab -l
        echo "${cron_job}"
    ) | crontab -
else
    echo "Cron job for LIS already exists. Skipping."
    log_action "Cron job for LIS already exists. Skipping."
fi

# Update LIS config.production.php with database credentials
config_file="${lis_path}/configs/config.production.php"
source_file="${lis_path}/configs/config.production.dist.php"

if [ ! -e "${config_file}" ]; then
    echo "Renaming config.production.dist.php to config.production.php..."
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
            echo "Passwords do not match. Please try again."
        elif [ -z "$mysql_root_password" ]; then
            echo "Password cannot be empty. Please try again."
        else
            break
        fi
    done

    # Verify the password
    echo "Verifying MySQL root password..."
    if ! mysqladmin ping -u root -p"$mysql_root_password" &>/dev/null; then
        echo "Error: Unable to verify the password. Please check and try again."
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
if [[ -n "$vlsm_sql_file" && -f "$vlsm_sql_file" ]]; then
    handle_database_setup_and_import "$vlsm_sql_file"
elif [[ -n "$vlsm_sql_file" ]]; then
    echo "SQL file not found: $vlsm_sql_file. Please check the path."
    exit 1
else
    handle_database_setup_and_import # Default to init.sql
fi

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
        echo "STS URL validation successful."
        log_action "STS URL validation successful."

        # Define desired_sts_url
        desired_sts_url="\$systemConfig['remoteURL'] = '$remote_sts_url';"

        config_file="${lis_path}/configs/config.production.php"

        # Check if the desired configuration already exists in the file
        if ! grep -qF "$desired_sts_url" "${config_file}"; then
            # The desired configuration does not exist, so update the file
            sed -i "s|\$systemConfig\['remoteURL'\]\s*=\s*'.*';|$desired_sts_url|" "${config_file}"
            echo "Remote STS URL updated in the configuration file."
        else
            echo "Remote STS URL is already set as desired in the configuration file."
        fi
        break
    else
        echo "Error: Failed to validate the provided STS URL (HTTP response code: $response_code). Please try again."
        log_action "STS URL validation failed with response code $response_code."
    fi
done


if grep -q "\['cache_di'\] => false" "${config_file}"; then
    sed -i "s|\('cache_di' => \)false,|\1true,|" "${config_file}"
fi

setfacl -R -m u:$USER:rwx,u:www-data:rwx "${lis_path}"

# Run the database migrations and other post-install tasks
cd "${lis_path}"
echo "Running database migrations and other post-install tasks..."
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

if [ -f "${lis_path}/cache/CompiledContainer.php" ]; then
    rm "${lis_path}/cache/CompiledContainer.php"
fi

service apache2 restart


# Set proper permissions
setfacl -R -m u:$USER:rwx,u:www-data:rwx "${lis_path}"
chown -R www-data:www-data "${lis_path}"


echo "Setup complete. Proceed to LIS setup."
log_action "Setup complete. Proceed to LIS setup."
