#!/bin/bash

# To use this script:
# cd ~;
# wget -O setup.sh https://raw.githubusercontent.com/deforay/vlsm/master/docs/setup.sh
# sudo chmod u+x setup.sh;
# sudo ./setup.sh;

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root."
    exit 1
fi

# Error trap
trap 'echo "An error occurred. Exiting..."; exit 1' ERR

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

# Check for dependencies
for cmd in "apt"; do
    if ! command -v $cmd &>/dev/null; then
        echo "$cmd is not installed. Exiting..."
        exit 1
    fi
done

# Function to check if VLSM database has tables
has_vlsm_tables() {
    local table_count=$(mysql -u root -p"${mysql_root_password}" -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'vlsm';")
    if [ "$table_count" -gt 0 ]; then
        return 0 # True, tables exist
    else
        return 1 # False, no tables
    fi
}

# Function to rename the VLSM database
rename_vlsm_database() {
    local todays_date=$(date +%Y%m%d_%H%M%S)
    local new_db_name="vlsm_${todays_date}"
    echo "Creating new database ${new_db_name}..."
    mysql -u root -p"${mysql_root_password}" -e "CREATE DATABASE ${new_db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

    echo "Copying tables and triggers from vlsm to ${new_db_name}..."
    local tables=$(mysql -u root -p"${mysql_root_password}" -Nse "SHOW TABLES" vlsm)
    for table in $tables; do
        mysql -u root -p"${mysql_root_password}" -e "RENAME TABLE vlsm.${table} TO ${new_db_name}.${table};"
    done

    local triggers=$(mysql -u root -p"${mysql_root_password}" -Nse "SHOW TRIGGERS IN vlsm;")
    for trigger in $triggers; do
        local trigger_sql=$(mysql -u root -p"${mysql_root_password}" -Nse "SHOW CREATE TRIGGER vlsm.${trigger}\G" | sed -n 's/.*SQL: \(.*\)/\1/p')
        mysql -u root -p"${mysql_root_password}" ${new_db_name} -e "${trigger_sql}"
    done

    echo "All tables and triggers moved to ${new_db_name}."
}

# Function to check if VLSM database exists
database_exists() {
    local db_count=$(mysql -u root -p"${mysql_root_password}" -sse "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'vlsm';")
    if [ "$db_count" -gt 0 ]; then
        return 0 # True, database exists
    else
        return 1 # False, database does not exist
    fi
}

# Function to prompt for SQL file and import
prompt_and_import_sql_file() {
    while :; do
        echo "Do you want to:"
        echo "1) Import from an existing backup SQL file"
        echo "2) Import default init.sql"
        read -p "Enter your choice (1 or 2): " user_choice

        case $user_choice in
        1)
            read -p "Enter the path to your existing VLSM database SQL file: " vlsm_sql_file
            if [[ -f "$vlsm_sql_file" ]]; then
                import_sql_file "$vlsm_sql_file"
                break
            else
                echo "File not found: $vlsm_sql_file. Please check the path."
            fi
            ;;
        2)
            import_init_sql
            break
            ;;
        *)
            echo "Invalid choice. Please enter 1 or 2."
            ;;
        esac
    done
}

# Function to create VLSM database
create_vlsm_database() {
    echo "Creating VLSM database..."
    mysql -u root -p"${mysql_root_password}" -e "CREATE DATABASE IF NOT EXISTS vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
}

# Function to import SQL file
import_sql_file() {
    local sql_file=$1
    echo "Importing SQL file: $sql_file..."
    mysql -u root -p"${mysql_root_password}" vlsm <"$sql_file"
}

# Function to import init.sql
import_init_sql() {
    echo "Importing init.sql into the VLSM database..."
    mysql -u root -p"${mysql_root_password}" vlsm <"${vlsm_path}/sql/init.sql"
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

# Initial Setup
echo "Updating software packages..."
apt-get update && apt-get upgrade -y && apt-get autoremove -y

echo "Installing basic packages..."
apt-get install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools sed mawk

echo "Setting up locale..."
locale-gen en_US en_US.UTF-8
export LANG=en_US.UTF-8
export LC_ALL=en_US.UTF-8
update-locale

# Apache Setup
if command -v apache2 &>/dev/null; then
    echo "Apache is already installed. Skipping installation..."
else
    echo "Installing and configuring Apache..."
    apt-get install -y apache2
    a2dismod mpm_event
    a2enmod rewrite headers deflate env mpm_prefork

    service apache2 restart || {
        echo "Failed to restart Apache2. Exiting..."
        exit 1
    }
    setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www
fi

# Prompt for MySQL root password and confirmation
mysql_root_password=""
mysql_root_password_confirm=""
while :; do # Infinite loop to keep asking until a correct password is provided
    while [ -z "${mysql_root_password}" ] || [ "${mysql_root_password}" != "${mysql_root_password_confirm}" ]; do
        read -sp "Please enter the MySQL root password (cannot be blank): " mysql_root_password
        echo
        read -sp "Please confirm the MySQL root password: " mysql_root_password_confirm
        echo

        if [ -z "${mysql_root_password}" ]; then
            echo "Password cannot be blank."
        elif [ "${mysql_root_password}" != "${mysql_root_password_confirm}" ]; then
            echo "Passwords do not match. Please try again."
        fi
    done

    # MySQL Setup
    if command -v mysql &>/dev/null; then
        echo "MySQL is already installed. Verifying password..."
        if mysqladmin ping -u root -p"${mysql_root_password}" &>/dev/null; then
            echo "Password verified."
            break # Exit the loop if the password is correct
        else
            echo "Password incorrect or MySQL server unreachable. Please try again."
            mysql_root_password="" # Reset password variables to prompt again
            mysql_root_password_confirm=""
        fi
    else
        echo "Installing MySQL..."
        apt-get install -y mysql-server

        # Set MySQL root password and create databases
        echo "Setting MySQL root password and creating databases..."
        mysql -e "CREATE DATABASE IF NOT EXISTS vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
        mysql -e "CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
        mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${mysql_root_password}'; FLUSH PRIVILEGES;"

        service mysql restart || {
            echo "Failed to restart MySQL. Exiting..."
            exit 1
        }
        break # Exit the loop after installing MySQL and setting the password
    fi
done

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

# PHP Setup
echo "Installing PHP 8.2..."
add-apt-repository ppa:ondrej/php -y
apt-get update
apt-get install -y php8.2 openssl php8.2-common php8.2-cli php8.2-mysql php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-xmlrpc php8.2-bcmath php8.2-gmp php8.2-intl php8.2-imagick php-mime-type php8.2-apcu
service apache2 restart || {
    echo "Failed to restart Apache2. Exiting..."
    exit 1
}

echo "Configuring PHP 8.2..."
a2dismod $(ls /etc/apache2/mods-enabled | grep -oP '^php\d\.\d') -f
a2enmod php8.2
update-alternatives --set php /usr/bin/php8.2
CLI_PHP_INI="/etc/php/8.2/cli/php.ini"
if ! grep -q "apc.enable_cli=1" "$CLI_PHP_INI"; then
    echo "apc.enable_cli=1" | sudo tee -a "$CLI_PHP_INI"
fi

sudo update-alternatives --set php "/usr/bin/php8.2"
sudo update-alternatives --set phar "/usr/bin/phar8.2"
sudo update-alternatives --set phar.phar "/usr/bin/phar.phar8.2"

service apache2 restart || {
    echo "Failed to restart Apache2. Exiting..."
    exit 1
}

# Modify php.ini as needed
echo "Modifying PHP configurations..."

# Get total RAM and calculate 75%
TOTAL_RAM=$(awk '/MemTotal/ {print $2}' /proc/meminfo) || exit 1
RAM_75_PERCENT=$((TOTAL_RAM * 3 / 4 / 1024))M || RAM_75_PERCENT=1G

desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING"
desired_post_max_size="post_max_size = 1G"
desired_upload_max_filesize="upload_max_filesize = 1G"
desired_memory_limit="memory_limit = $RAM_75_PERCENT"

for phpini in /etc/php/8.2/apache2/php.ini /etc/php/8.2/cli/php.ini; do
    awk -v er="$desired_error_reporting" -v pms="$desired_post_max_size" \
        -v umf="$desired_upload_max_filesize" -v ml="$desired_memory_limit" \
        '{
        if ($0 ~ /^error_reporting[[:space:]]*=/) {print ";" $0 "\n" er; next}
        if ($0 ~ /^post_max_size[[:space:]]*=/) {print ";" $0 "\n" pms; next}
        if ($0 ~ /^upload_max_filesize[[:space:]]*=/) {print ";" $0 "\n" umf; next}
        if ($0 ~ /^memory_limit[[:space:]]*=/) {print ";" $0 "\n" ml; next}
        print $0
    }' $phpini >temp.ini && mv temp.ini $phpini
done

# phpMyAdmin Setup
if [ ! -d "/var/www/phpmyadmin" ]; then
    echo "Downloading and setting up phpMyAdmin..."

    # Create the directory if it does not exist
    mkdir -p /var/www/phpmyadmin

    # Download the ZIP file
    # Replace the URL with the latest ZIP file URL from the phpMyAdmin website
    wget -q --show-progress --progress=dot:giga https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.zip

    # Extract directly into the /var/www/phpmyadmin directory
    unzip -q phpMyAdmin-latest-all-languages.zip -d /var/www/phpmyadmin || {
        echo "Extraction failed"
        exit 1
    }

    # Clean up the downloaded ZIP file
    rm phpMyAdmin-latest-all-languages.zip

    # The unzip command extracts the files into a subdirectory. We need to move them up one level.
    PHPMYADMIN_DIR=$(ls /var/www/phpmyadmin)
    mv /var/www/phpmyadmin/$PHPMYADMIN_DIR/* /var/www/phpmyadmin/
    mv /var/www/phpmyadmin/$PHPMYADMIN_DIR/.[!.]* /var/www/phpmyadmin/ 2>/dev/null
    rmdir /var/www/phpmyadmin/$PHPMYADMIN_DIR

    echo "Configuring Apache for phpMyAdmin..."
    desired_alias="Alias /phpmyadmin /var/www/phpmyadmin"
    config_file="/etc/apache2/sites-available/000-default.conf"

    # Check if the desired alias already exists
    if ! grep -q "$desired_alias" ${config_file}; then
        awk -v da="$desired_alias" \
            'BEGIN {added=0; alias_added=0}
        /Alias \/phpmyadmin[[:space:]]/ {
            if ($0 !~ da) {print ";" $0} else {alias_added=1; print $0}
            next;
        }
        /ServerAdmin|DocumentRoot/ {
            print;
            if (added == 0 && alias_added == 0) {
                print da;
                added=1;
            }
            next;
        }
        { print }' ${config_file} >tmpfile && mv tmpfile ${config_file}
    fi

    service apache2 restart
fi

# Composer Setup
echo "Checking for Composer..."
if command -v composer &>/dev/null; then
    echo "Composer is already installed. Updating..."
    composer self-update
else
    echo "Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    HASH=$(wget -q -O - https://composer.github.io/installer.sig)
    echo "Installer hash: $HASH"
    php -r "if (hash('SHA384', file_get_contents('composer-setup.php')) !== '$HASH') { unlink('composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }"
    php composer-setup.php
    if [ $? -ne 0 ]; then
        echo "Failed to install Composer."
    fi
    php -r "unlink('composer-setup.php');"
    mv composer.phar /usr/local/bin/composer
fi

# Ask user for VLSM installation path
read -p "Enter the VLSM installation path [press enter to select /var/www/vlsm]: " vlsm_path
vlsm_path="${vlsm_path:-/var/www/vlsm}"

# VLSM Setup
echo "Downloading VLSM..."
wget -q --show-progress --progress=dot:giga -O master.zip https://github.com/deforay/vlsm/archive/refs/heads/master.zip

# Unzip the file into a temporary directory
temp_dir=$(mktemp -d)
unzip master.zip -d "$temp_dir"

# backup old code if it exists
if [ -d "${vlsm_path}" ]; then
    cp -R "${vlsm_path}" "${vlsm_path}"-$(date +%Y%m%d-%H%M%S)
else
    mkdir -p "${vlsm_path}"
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

# Copy the unzipped content to the /var/www/vlsm directory, overwriting any existing files
cp -R "$temp_dir/vlsm-master/"* "${vlsm_path}"

# Remove the empty directory and the downloaded zip file
rm -rf "$temp_dir/vlsm-master/"
rm master.zip

# Set proper permissions
chown -R www-data:www-data "${vlsm_path}"

# Run Composer Update as www-data
echo "Running composer update as www-data user..."
cd "${vlsm_path}"

sudo -u www-data composer config process-timeout 30000

sudo -u www-data composer update --no-dev &&
    sudo -u www-data composer dump-autoload -o

# Check if VLSM database and tables exist
if has_vlsm_tables; then
    echo "VLSM database and tables already exist. No further action required."
else
    create_vlsm_database
    prompt_and_import_sql_file
fi

# Ask user for the hostname
read -p "Enter domain name (press enter to select 'vlsm' -- ONLY for local lab machines): " hostname
hostname="${hostname:-vlsm}"

# Check if the hostname entry is already in /etc/hosts
if ! grep -q "127.0.0.1 ${hostname}" /etc/hosts; then
    echo "Adding ${hostname} to hosts file..."
    echo "127.0.0.1 ${hostname}" | tee -a /etc/hosts
else
    echo "${hostname} entry is already in the hosts file."
fi

# Define the desired configuration using the variable for VLSM installation path
vlsm_config_block="DocumentRoot \"${vlsm_path}/public\"
    ServerName ${hostname}
    <Directory \"${vlsm_path}/public\">
        AddDefaultCharset UTF-8
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>"

# Path to the default Apache2 vhost file
apache_vhost_file="/etc/apache2/sites-available/000-default.conf"

# Make a backup of the current Apache2 vhost file
cp "${apache_vhost_file}" "${apache_vhost_file}.bak"

# Convert newlines to a unique pattern for single-line pattern matching
pattern=$(echo "${vlsm_config_block}" | tr '\n' '\a')

# Check if the pattern already exists in the file
if ! grep -qza "${pattern}" "${apache_vhost_file}"; then
    # The pattern doesn't exist, so we insert/update the configuration

    # Replace the existing DocumentRoot line with the desired configuration
    # Restore newlines from the unique pattern before using awk
    vlsm_config_block=$(echo "${vlsm_config_block}" | tr '\a' '\n')
    awk -v vlsm_config_block="${vlsm_config_block}" \
        'BEGIN {printed=0}
        /DocumentRoot/ && !printed {
            print vlsm_config_block;
            printed=1;
            next;
        }
        {print}' "${apache_vhost_file}" >temp_vhost && mv temp_vhost "${apache_vhost_file}"

    # No need to check for ServerName and <Directory> separately as they are included in the block
    echo "Apache configuration has been updated."
else
    echo "Apache configuration is already set as desired."
fi

# Create and configure ${hostname}.conf
vhost_file="/etc/apache2/sites-available/${hostname}.conf"

# Check if the file exists. If yes, delete it.
if [ -f "${vhost_file}" ]; then
    echo "Existing ${vhost_file} found. Deleting old file..."
    rm "${vhost_file}"
fi

echo "Creating ${vhost_file} with VLSM configuration..."
{
    echo "<VirtualHost *:8080>"
    echo "${vlsm_config_block}"
    echo "</VirtualHost>"
} >"${vhost_file}"

# Enable the new site
a2ensite "${hostname}.conf"

# Restart Apache to apply changes
service apache2 restart || {
    echo "Failed to restart Apache. Please check the configuration."
    exit 1
}

# cron job

chmod +x ${vlsm_path}/cron.sh

cron_job="* * * * * cd ${vlsm_path} && ./cron.sh"

# Check if the cron job already exists
if ! crontab -l | grep -qF "${cron_job}"; then
    echo "Adding cron job for VLSM..."
    (
        crontab -l
        echo "${cron_job}"
    ) | crontab -
else
    echo "Cron job for VLSM already exists. Skipping."
fi

# Update VLSM config.production.php with database credentials
config_file="${vlsm_path}/configs/config.production.php"
source_file="${vlsm_path}/configs/config.production.dist.php"

if [ ! -e "${config_file}" ]; then
    echo "Renaming config.production.dist.php to config.production.php..."
    mv "${source_file}" "${config_file}"
else
    echo "File config.production.php already exists. Skipping renaming."
fi

# Escape special characters in password for sed
# This uses Perl's quotemeta which is more reliable when dealing with many special characters
escaped_mysql_root_password=$(perl -e 'print quotemeta $ARGV[0]' -- "${mysql_root_password}")

# Use sed to update database configurations, using | as a delimiter instead of /
sed -i "s|\$systemConfig\['database'\]\['host'\]\s*=.*|\$systemConfig['database']['host'] = 'localhost';|" "${config_file}"
sed -i "s|\$systemConfig\['database'\]\['username'\]\s*=.*|\$systemConfig['database']['username'] = 'root';|" "${config_file}"
sed -i "s|\$systemConfig\['database'\]\['password'\]\s*=.*|\$systemConfig['database']['password'] = '$escaped_mysql_root_password';|" "${config_file}"

# Prompt for Remote STS URL
read -p "Please enter the Remote STS URL (can be blank if you choose so): " remote_sts_url

# Update VLSM config.production.php with Remote STS URL if provided
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

# Run the database migrations and other post-install tasks
cd "${vlsm_path}"
echo "Running database migrations and other post-install tasks..."
sudo -u www-data composer post-install &
pid=$!
spinner "$pid"
wait $pid

if ask_yes_no "Do you want to run scripts from ${vlsm_path}/run-once/?"; then
    # List the files in run-once directory
    echo "Available scripts to run:"
    files=("${vlsm_path}/run-once/"*.php)
    for i in "${!files[@]}"; do
        filename=$(basename "${files[$i]}")
        echo "$((i + 1))) $filename"
    done

    # Ask which files to run
    echo "Enter the numbers of the scripts you want to run separated by commas (e.g., 1,3,6) or type 'all' to run them all."
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

if [ -f "${vlsm_path}/cache/CompiledContainer.php" ]; then
    rm "${vlsm_path}/cache/CompiledContainer.php"
fi

service apache2 restart

echo "Setup complete. Proceed to VLSM setup."
