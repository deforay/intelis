#!/bin/bash

# To use this script:
# cd ~;
# wget -O upgrade.sh https://raw.githubusercontent.com/deforay/vlsm/master/docs/upgrade.sh
# sudo chmod u+x upgrade.sh;
# sudo ./upgrade.sh;

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Function to get Ubuntu version
get_ubuntu_version() {
    local version=$(lsb_release -rs)
    echo "$version"
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
            echo "Passwords do not match. Please try again."
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

    echo "Configuration file updated."
}

# Check if Ubuntu version is 20.04 or newer
min_version="20.04"
current_version=$(get_ubuntu_version)

if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
    echo "This script is not compatible with Ubuntu versions older than ${min_version}."
    exit 1
fi

# Ask user for VLSM installation path with a 15-second timeout and default path as fallback
echo "Enter the VLSM installation path [press enter to select /var/www/vlsm]: "
read -t 60 -p "" vlsm_path
vlsm_path="${vlsm_path:-/var/www/vlsm}"

# Check if VLSM folder exists
if [ ! -d "${vlsm_path}" ]; then
    echo "VLSM folder does not exist at ${vlsm_path}. Please first run the setup script."
    exit 1
fi

# Check for MySQL
if ! command -v mysql &>/dev/null; then
    echo "MySQL is not installed. Please first run the setup script."
    exit 1
fi

echo "Configuring MySQL..."
desired_sql_mode="sql_mode ="
desired_innodb_strict_mode="innodb_strict_mode = 0"
desired_charset="character-set-server=utf8mb4"
desired_collation="collation-server=utf8mb4_general_ci"
config_file="/etc/mysql/mysql.conf.d/mysqld.cnf"

awk -v dsm="${desired_sql_mode}" -v dism="${desired_innodb_strict_mode}" \
    -v dcharset="${desired_charset}" -v dcollation="${desired_collation}" \
    'BEGIN { sql_mode_added=0; innodb_strict_mode_added=0; charset_added=0; collation_added=0; }
                /sql_mode[[:space:]]*=/ {
                    if ($0 ~ dsm) {sql_mode_added=1;}
                    else {print ";" $0;}
                    next;
                }
                /innodb_strict_mode[[:space:]]*=/ {
                    if ($0 ~ dism) {innodb_strict_mode_added=1;}
                    else {print ";" $0;}
                    next;
                }
                /character-set-server[[:space:]]*=/ {
                    if ($0 ~ dcharset) {charset_added=1;}
                    else {print ";" $0;}
                    next;
                }
                /collation-server[[:space:]]*=/ {
                    if ($0 ~ dcollation) {collation_added=1;}
                    else {print ";" $0;}
                    next;
                }
                /skip-external-locking|mysqlx-bind-address/ {
                    print;
                    if (sql_mode_added == 0) {print dsm; sql_mode_added=1;}
                    if (innodb_strict_mode_added == 0) {print dism; innodb_strict_mode_added=1;}
                    if (charset_added == 0) {print dcharset; charset_added=1;}
                    if (collation_added == 0) {print dcollation; collation_added=1;}
                    next;
                }
                { print; }' ${config_file} >tmpfile && mv tmpfile ${config_file}

service mysql restart || {
    echo "Failed to restart MySQL. Exiting..."
    exit 1
}

# Check for Apache
if ! command -v apache2ctl &>/dev/null; then
    echo "Apache is not installed. Please first run the setup script."
    exit 1
fi

# Check for PHP
if ! command -v php &>/dev/null; then
    echo "PHP is not installed. Please first run the setup script."
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

if [[ "${php_version}" != "${desired_php_version}" ]]; then
    echo "Current PHP version is ${php_version}. Switching to PHP ${desired_php_version}."

    # Download and install switch-php script
    wget https://gist.githubusercontent.com/amitdugar/339470e36f6ad6c1910914e854384294/raw/switch-php -O /usr/local/bin/switch-php
    chmod u+x /usr/local/bin/switch-php

    # Switch to PHP 8.2
    switch-php ${desired_php_version}

    if [ $? -ne 0 ]; then
        echo "Failed to switch to PHP ${desired_php_version}. Please check your setup."
        exit 1
    fi
else
    echo "PHP version is already ${desired_php_version}."
fi

# Modify php.ini as needed
echo "Modifying PHP configurations..."

desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING"
desired_post_max_size="post_max_size = 1G"
desired_upload_max_filesize="upload_max_filesize = 1G"

for phpini in /etc/php/8.2/apache2/php.ini /etc/php/8.2/cli/php.ini; do
    awk -v er="$desired_error_reporting" -v pms="$desired_post_max_size" \
        -v umf="$desired_upload_max_filesize" \
        '{
        if ($0 ~ /^error_reporting[[:space:]]*=/) {print ";" $0 "\n" er; next}
        if ($0 ~ /^post_max_size[[:space:]]*=/) {print ";" $0 "\n" pms; next}
        if ($0 ~ /^upload_max_filesize[[:space:]]*=/) {print ";" $0 "\n" umf; next}
        print $0
    }' $phpini >temp.ini && mv temp.ini $phpini
done

# Check for Composer
if ! command -v composer &>/dev/null; then
    echo "Composer is not installed. Please first run the setup script."
    exit 1
fi

# Proceed with the rest of the script if all checks pass

echo "All system checks passed. Continuing with the update..."

# Update Ubuntu Packages
echo "Updating Ubuntu packages..."
apt-get update && apt-get upgrade -y

# Configure any packages that were not fully installed
echo "Configuring any partially installed packages..."
sudo dpkg --configure -a

# Clean up
apt-get autoremove -y

echo "Installing basic packages..."
apt-get install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools sed mawk magic-wormhole

setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www

spinner() {
    local pid=$!
    local delay=0.75
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

# Function to list databases and get the database list
get_databases() {
    echo "Fetching available databases..."
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
        echo "Backing up database: $db"
        mysqldump -u root -p"${mysql_root_password}" "$db" | gzip >"${backup_location}/${db}_${timestamp}.sql.gz"
        if [[ $? -eq 0 ]]; then
            echo "Backup of $db completed successfully."
        else
            echo "Failed to backup database: $db"
        fi
    done
}

# Ask the user if they want to backup the database
if ask_yes_no "Do you want to backup the database" "no"; then
    # Ask for MySQL root password
    echo "Please enter your MySQL root password:"
    read -s mysql_root_password

    # Ask for the backup location and create it if it doesn't exist
    read -p "Enter the backup location [press enter to select /var/vlsm-backup/db/]: " backup_location
    backup_location="${backup_location:-/var/vlsm-backup/db/}"

    # Create the backup directory if it does not exist
    if [ ! -d "$backup_location" ]; then
        echo "Backup directory does not exist. Creating it now..."
        mkdir -p "$backup_location"
        if [ $? -ne 0 ]; then
            echo "Failed to create backup directory. Please check your permissions."
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

else
    echo "Skipping database backup as per user request."
fi

# Ask the user if they want to backup the VLSM folder
if ask_yes_no "Do you want to backup the VLSM folder before updating?" "no"; then
    # Backup Old VLSM Folder
    echo "Backing up old VLSM folder..."
    timestamp=$(date +%Y%m%d-%H%M%S) # Using this timestamp for consistency with database backup filenames
    backup_folder="/var/vlsm-backup/www/vlsm-backup-$timestamp"
    mkdir -p "${backup_folder}"
    rsync -a --delete --exclude "public/temporary/" "${vlsm_path}/" "${backup_folder}/" &
    spinner # This will show the spinner until the above process is completed
else
    echo "Skipping VLSM folder backup as per user request."
fi

rm -rf "${vlsm_path}/run-once"

echo "Downloading VLSM..."
wget -q --show-progress --progress=dot:giga -O master.zip https://github.com/deforay/vlsm/archive/refs/heads/master.zip
download_pid=$!           # Save the process ID of the wget command
spinner "${download_pid}" # Start the spinner
wait ${download_pid}      # Wait for the download to finish

# Unzip the file into a temporary directory
temp_dir=$(mktemp -d)
unzip master.zip -d "$temp_dir" &
unzip_pid=$!           # Save the process ID of the unzip command
spinner "${unzip_pid}" # Start the spinner
wait ${unzip_pid}      # Wait for the unzip process to finish

# Copy the unzipped content to the /var/www/vlsm directory, overwriting any existing files
cp -R "$temp_dir/vlsm-master/"* "${vlsm_path}"
cp_pid=$!           # Save the process ID of the cp command
spinner "${cp_pid}" # Start the spinner
wait ${cp_pid}      # Wait for the copy process to finish

# Remove the empty directory and the downloaded zip file
rm -rf "$temp_dir/vlsm-master/"
rm master.zip

# Set proper permissions
setfacl -R -m u:$USER:rwx,u:www-data:rwx "${vlsm_path}"

# Run Composer Update as www-data
echo "Running composer update as www-data user..."
cd "${vlsm_path}"

sudo composer self-update

sudo -u www-data composer config process-timeout 30000

sudo -u www-data composer update --no-dev &&
    sudo -u www-data composer dump-autoload -o

# Check for config.production.php and its content
config_file="${vlsm_path}/configs/config.production.php"
dist_config_file="${vlsm_path}/configs/config.production.dist.php"

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

# Run the database migrations and other post-update tasks
echo "Running database migrations and other post-update tasks..."
sudo -u www-data composer post-update &
pid=$!
spinner "$pid"
wait $pid

for script in "${vlsm_path}/run-once/*.php"; do
    php $script
done

# Ask User to Run 'maintenance' Scripts
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
            fi
        done
    fi
fi

# Run the PHP script for remote data sync
cd "${vlsm_path}"
echo "Running remote data sync script. Please wait..."
sudo -u www-data composer metadata-sync &
pid=$!
spinner "$pid"
wait $pid
echo "Remote data sync completed."

# The old startup.php file is no longer needed, but if it exists, make sure it is empty
if [ -f "${vlsm_path}/startup.php" ]; then
    rm "${vlsm_path}/startup.php"
    touch "${vlsm_path}/startup.php"
fi

service apache2 restart

setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www

echo "VLSM update complete."
