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
    db_exists=$(mysql -u root --login-path=rootuser -sse "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'vlsm';")
    db_not_empty=$(mysql -u root --login-path=rootuser -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'vlsm';")

    if [ "$db_exists" -eq 1 ] && [ "$db_not_empty" -gt 0 ]; then
        echo "Renaming existing LIS database..."
        log_action "Renaming existing LIS database..."
        local todays_date=$(date +%Y%m%d_%H%M%S)
        local new_db_name="vlsm_${todays_date}"
        mysql -u root --login-path=rootuser -e "CREATE DATABASE ${new_db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

        # Get the list of tables in the original database
        local tables=$(mysql -u root --login-path=rootuser -sse "SHOW TABLES IN vlsm;")

        # Rename tables
        for table in $tables; do
            mysql -u root --login-path=rootuser -e "RENAME TABLE vlsm.$table TO ${new_db_name}.$table;"
        done

        echo "Copying triggers..."
        log_action "Copying triggers..."
        local triggers=$(mysql -u root --login-path=rootuser -sse "SHOW TRIGGERS IN vlsm;")
        for trigger_name in $triggers; do
            local trigger_sql=$(mysql -u root --login-path=rootuser -sse "SHOW CREATE TRIGGER vlsm.$trigger_name\G" | sed -n 's/.*SQL: \(.*\)/\1/p')
            mysql -u root --login-path=rootuser -D ${new_db_name} -e "$trigger_sql"
        done

        echo "All tables and triggers moved to ${new_db_name}."
        log_action "All tables and triggers moved to ${new_db_name}."
    fi

    mysql -u root --login-path=rootuser -e "CREATE DATABASE IF NOT EXISTS vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
    mysql -u root --login-path=rootuser -e "CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

    local sql_file="${1:-${vlsm_path}/sql/init.sql}"
    if [[ "$sql_file" == *".gz" ]]; then
        gunzip -c "$sql_file" | mysql -u root --login-path=rootuser vlsm
    elif [[ "$sql_file" == *".zip" ]]; then
        unzip -p "$sql_file" | mysql -u root --login-path=rootuser vlsm
    else
        mysql -u root --login-path=rootuser vlsm <"$sql_file"
    fi
    mysql -u root --login-path=rootuser vlsm <"${vlsm_path}/sql/audit-triggers.sql"
    mysql -u root --login-path=rootuser interfacing <"${vlsm_path}/sql/interface-init.sql"
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
read -t 60 vlsm_path

# Check if read command timed out or no input was provided
if [ $? -ne 0 ] || [ -z "$vlsm_path" ]; then
    vlsm_path="/var/www/vlsm"
    echo "Using default path: $vlsm_path"
else
    echo "LIS installation path is set to ${vlsm_path}."
fi

log_action "LIS installation path is set to ${vlsm_path}."

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
wget https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh
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
if [ -d "${vlsm_path}" ]; then
    cp -R "${vlsm_path}" "${vlsm_path}"-$(date +%Y%m%d-%H%M%S)
else
    mkdir -p "${vlsm_path}"
fi

# Copy the unzipped content to the /var/www/vlsm directory, overwriting any existing files
# cp -R "$temp_dir/vlsm-master/"* "${vlsm_path}"
rsync -av "$temp_dir/vlsm-master/" "$vlsm_path/"

# Remove the empty directory and the downloaded zip file
rm -rf "$temp_dir/vlsm-master/"
rm master.zip

log_action "LIS copied to ${vlsm_path}."

# Set proper permissions
chown -R www-data:www-data "${vlsm_path}"

# Run Composer install as www-data
echo "Running composer install as www-data user..."
cd "${vlsm_path}"

sudo -u www-data composer config process-timeout 30000

sudo -u www-data composer clear-cache

sudo -u www-data composer install --no-dev &&
    sudo -u www-data composer dump-autoload -o

# Function to configure Apache Virtual Host
configure_vhost() {
    local vhost_file=$1
    local document_root="${vlsm_path}/public"
    local directory_block="<Directory ${vlsm_path}/public>\n\
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
    DocumentRoot ${vlsm_path}/public
    <Directory ${vlsm_path}/public>
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

chmod +x ${vlsm_path}/cron.sh

cron_job="* * * * * cd ${vlsm_path} && ./cron.sh"

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
config_file="${vlsm_path}/configs/config.production.php"
source_file="${vlsm_path}/configs/config.production.dist.php"

if [ ! -e "${config_file}" ]; then
    echo "Renaming config.production.dist.php to config.production.php..."
    log_action "Renaming config.production.dist.php to config.production.php..."
    mv "${source_file}" "${config_file}"
else
    echo "File config.production.php already exists. Skipping renaming."
    log_action "File config.production.php already exists. Skipping renaming."
fi

while :; do
    # Check if the `rootuser` login path works
    echo "Verifying MySQL root password..."
    if mysqladmin ping --login-path=rootuser &>/dev/null; then
        echo "MySQL root password verified successfully."
        # Fetch the password from login path and use it directly
        mysql_root_password=$(mysql_config_editor print --login-path=rootuser | awk '/password/{print $3}')
        break
    else
        echo "Unable to login to MySQL. Please enter the MySQL root password to reconfigure."

        # Prompt the user for a new password
        read -sp "MySQL root password: " mysql_root_password
        echo

        # Attempt to store the new password in the login path
        echo "Configuring MySQL login..."
        echo "$mysql_root_password" | mysql_config_editor set --login-path=rootuser --host=localhost --user=root --password

        # Verify the updated login path
        if mysqladmin ping --login-path=rootuser &>/dev/null; then
            echo "MySQL login reconfigured successfully."
            break
        else
            echo "Failed to configure MySQL login. Please try again."
        fi
    fi
done


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
read -p "Please enter the Remote STS URL (can be blank if you choose so): " remote_sts_url
log_action "Remote STS URL: $remote_sts_url"
# Update LIS config.production.php with Remote STS URL if provided
if [ ! -z "$remote_sts_url" ]; then

    # Define desired_sts_url
    desired_sts_url="\$systemConfig['remoteURL'] = '$remote_sts_url';"

    config_file="${vlsm_path}/configs/config.production.php"

    # Check if the desired configuration already exists in the file
    if ! grep -qF "$desired_sts_url" "${config_file}"; then
        # The desired configuration does not exist, so update the file
        sed -i "s|\$systemConfig\['remoteURL'\]\s*=\s*'.*';|$desired_sts_url|" "${config_file}"
        echo "Remote STS URL updated in the configuration file."
    else
        # The configuration already exists as desired
        echo "Remote STS URL is already set as desired in the configuration file."
    fi
fi

if grep -q "\['cache_di'\] => false" "${config_file}"; then
    sed -i "s|\('cache_di' => \)false,|\1true,|" "${config_file}"
fi

setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www

# Run the database migrations and other post-install tasks
cd "${vlsm_path}"
echo "Running database migrations and other post-install tasks..."
sudo -u www-data composer post-install &
pid=$!
spinner "$pid"
wait $pid

if ask_yes_no "Do you want to run maintenance scripts?" "no"; then
    # List the files in maintenance directory
    echo "Available maintenance scripts to run:"
    files=("${vlsm_path}/maintenance/"*.php)
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

if [ -f "${vlsm_path}/cache/CompiledContainer.php" ]; then
    rm "${vlsm_path}/cache/CompiledContainer.php"
fi

service apache2 restart

echo "Setup complete. Proceed to LIS setup."
log_action "Setup complete. Proceed to LIS setup."
