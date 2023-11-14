#!/bin/bash

# To use this script:
# Save the above code into a file, for example, setup.sh.
# Make the script executable: chmod +x setup.sh.
# Run the script: ./setup.sh.

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root."
    exit 1
fi

# Error trap
trap 'echo "An error occurred. Exiting..."; exit 1' ERR

# Check for dependencies
for cmd in "apt"; do
    if ! command -v $cmd &>/dev/null; then
        echo "$cmd is not installed. Exiting..."
        exit 1
    fi
done

# START OF SCRIPT - BE CAREFUL OF WHAT YOU CHANGE BELOW THIS LINE

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

# Initial Setup
echo "Updating software packages..."
apt update && apt upgrade -y && apt autoremove -y

echo "Installing basic packages..."
apt install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools sed mawk

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
    apt install -y apache2
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
    while [ -z "$mysql_root_password" ] || [ "$mysql_root_password" != "$mysql_root_password_confirm" ]; do
        read -sp "Please enter the MySQL root password (cannot be blank): " mysql_root_password
        echo
        read -sp "Please confirm the MySQL root password: " mysql_root_password_confirm
        echo

        if [ -z "$mysql_root_password" ]; then
            echo "Password cannot be blank."
        elif [ "$mysql_root_password" != "$mysql_root_password_confirm" ]; then
            echo "Passwords do not match. Please try again."
        fi
    done

    # MySQL Setup
    if command -v mysql &>/dev/null; then
        echo "MySQL is already installed. Verifying password..."
        if mysqladmin ping -u root -p"$mysql_root_password" &>/dev/null; then
            echo "Password verified."
            break # Exit the loop if the password is correct
        else
            echo "Password incorrect or MySQL server unreachable. Please try again."
            mysql_root_password="" # Reset password variables to prompt again
            mysql_root_password_confirm=""
        fi
    else
        echo "Installing MySQL..."
        apt install -y mysql-server

        # Set MySQL root password and create databases
        echo "Setting MySQL root password and creating databases..."
        mysql -e "CREATE DATABASE vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
        mysql -e "CREATE DATABASE interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
        mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$mysql_root_password'; FLUSH PRIVILEGES;"

        echo "Configuring MySQL..."
        desired_sql_mode="sql_mode ="
        desired_innodb_strict_mode="innodb_strict_mode = 0"
        config_file="/etc/mysql/mysql.conf.d/mysqld.cnf"

        awk -v dsm="$desired_sql_mode" -v dism="$desired_innodb_strict_mode" \
            'BEGIN { sql_mode_added=0; innodb_strict_mode_added=0; }
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
            /skip-external-locking|mysqlx-bind-address/ {
                print;
                if (sql_mode_added == 0) {print dsm; sql_mode_added=1;}
                if (innodb_strict_mode_added == 0) {print dism; innodb_strict_mode_added=1;}
                next;
            }
            { print; }' $config_file >tmpfile && mv tmpfile $config_file

        service mysql restart || {
            echo "Failed to restart MySQL. Exiting..."
            exit 1
        }
        break # Exit the loop after installing MySQL and setting the password
    fi
done

# PHP Setup
echo "Installing PHP 7.4..."
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php7.4 openssl php7.4-common php7.4-cli php7.4-json php7.4-mysql php7.4-zip php7.4-gd php7.4-mbstring php7.4-curl php7.4-xml php7.4-xmlrpc php7.4-bcmath php7.4-gmp php7.4-intl php7.4-imagick php-mime-type php7.4-apcu
service apache2 restart || {
    echo "Failed to restart Apache2. Exiting..."
    exit 1
}

echo "Configuring PHP 7.4..."
a2dismod $(ls /etc/apache2/mods-enabled | grep -oP '^php\d\.\d') -f
a2enmod php7.4
update-alternatives --set php /usr/bin/php7.4
echo "apc.enable_cli=1" | tee -a /etc/php/7.4/cli/php.ini
service apache2 restart || {
    echo "Failed to restart Apache2. Exiting..."
    exit 1
}

# Modify php.ini as needed
echo "Modifying PHP configurations..."

# Get total RAM and calculate 75%
TOTAL_RAM=$(awk '/MemTotal/ {print $2}' /proc/meminfo) || exit 1
RAM_75_PERCENT=$((TOTAL_RAM * 3 / 4 / 1024))M || RAM_75_PERCENT=1G

desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE"
desired_post_max_size="post_max_size = 1G"
desired_upload_max_filesize="upload_max_filesize = 1G"
desired_memory_limit="memory_limit = $RAM_75_PERCENT"

for phpini in /etc/php/7.4/apache2/php.ini /etc/php/7.4/cli/php.ini; do
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
    # phpMyAdmin Setup
    echo "Downloading and setting up phpMyAdmin..."
    wget https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.tar.gz
    tar xzf phpMyAdmin-latest-all-languages.tar.gz
    DIR_NAME=$(tar tzf phpMyAdmin-latest-all-languages.tar.gz | head -1 | cut -f1 -d"/")
    mv $DIR_NAME /var/www/phpmyadmin
    rm phpMyAdmin-latest-all-languages.tar.gz

    echo "Configuring Apache for phpMyAdmin..."
    desired_alias="Alias /phpmyadmin /var/www/phpmyadmin"
    config_file="/etc/apache2/sites-available/000-default.conf"

    # Check if the desired alias already exists
    if ! grep -q "$desired_alias" $config_file; then
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
        { print }' $config_file >tmpfile && mv tmpfile $config_file
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
read -p "Enter the VLSM installation path [/var/www/vlsm]: " vlsm_path
vlsm_path="${vlsm_path:-/var/www/vlsm}"

# VLSM Setup
echo "Downloading VLSM..."
wget -O master.zip https://github.com/deforay/vlsm/archive/refs/heads/master.zip

# Unzip the file into a temporary directory
temp_dir=$(mktemp -d)
unzip master.zip -d "$temp_dir"

# backup old code if it exists
if [ -d "$vlsm_path" ]; then
    cp -R "$vlsm_path" "$vlsm_path"-$(date +%Y%m%d-%H%M%S)
else
    mkdir -p "$vlsm_path"
fi

# Copy the unzipped content to the /var/www/vlsm directory, overwriting any existing files
cp -R "$temp_dir/vlsm-master/"* "$vlsm_path"

# Remove the empty directory and the downloaded zip file
rm -rf "$temp_dir/vlsm-master/"
rm master.zip

# Set proper permissions
chown -R www-data:www-data "$vlsm_path"

# Run Composer Update as www-data
echo "Running composer update as www-data user..."
cd "$vlsm_path"
sudo -u www-data composer update

# Import init.sql into the vlsm database
echo "Importing init.sql into the vlsm database..."
mysql -u root -p"$mysql_root_password" vlsm <"$vlsm_path/sql/init.sql"

# Ask user for the hostname
read -p "Enter the hostname [vlsm]: " hostname
hostname="${hostname:-vlsm}"

# Check if the hostname entry is already in /etc/hosts
if ! grep -q "127.0.0.1 $hostname" /etc/hosts; then
    echo "Adding $hostname to hosts file..."
    echo "127.0.0.1 $hostname" | tee -a /etc/hosts
else
    echo "$hostname entry is already in the hosts file."
fi
# Define the desired configuration using the variable for VLSM installation path
vlsm_config_block="DocumentRoot \"$vlsm_path/public\"
ServerName $hostname
<Directory \"$vlsm_path/public\">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>"

# Path to the default Apache2 vhost file
apache_vhost_file="/etc/apache2/sites-available/000-default.conf"

# Make a backup of the current Apache2 vhost file
cp "$apache_vhost_file" "${apache_vhost_file}.bak"

# Convert newlines to a unique pattern for single-line pattern matching
pattern=$(echo "$vlsm_config_block" | tr '\n' '\a')

# Check if the pattern already exists in the file
if ! grep -qza "$pattern" "$apache_vhost_file"; then
    # The pattern doesn't exist, so we insert/update the configuration

    # Replace the existing DocumentRoot line with the desired configuration
    # Restore newlines from the unique pattern before using awk
    vlsm_config_block=$(echo "$vlsm_config_block" | tr '\a' '\n')
    awk -v vlsm_config_block="$vlsm_config_block" \
        'BEGIN {printed=0}
    /DocumentRoot/ && !printed {
        print vlsm_config_block;
        printed=1;
        next;
    }
    {print}' "$apache_vhost_file" >temp_vhost && mv temp_vhost "$apache_vhost_file"

    # No need to check for ServerName and <Directory> separately as they are included in the block
    echo "Apache configuration has been updated."
else
    echo "Apache configuration is already set as desired."
fi

# Restart Apache to apply changes
service apache2 restart || {
    echo "Failed to restart Apache. Please check the configuration."
    exit 1
}

# cron job
cron_job="* * * * * cd /var/www/vlsm/ && ./vendor/bin/crunz schedule:run"

# Check if the cron job already exists
if ! crontab -l | grep -qF "$cron_job"; then
    echo "Adding cron job for VLSM..."
    (
        crontab -l
        echo "$cron_job"
    ) | crontab -
else
    echo "Cron job for VLSM already exists. Skipping."
fi

# Update VLSM config.production.php with database credentials
config_file="/var/www/vlsm/configs/config.production.php"
source_file="/var/www/vlsm/configs/config.production.dist.php"

if [ ! -e "$config_file" ]; then
    echo "Renaming config.production.dist.php to config.production.php..."
    mv "$source_file" "$config_file"
else
    echo "File config.production.php already exists. Skipping renaming."
fi

# Escape special characters in password for sed
# This uses Perl's quotemeta which is more reliable when dealing with many special characters
escaped_mysql_root_password=$(perl -e 'print quotemeta $ARGV[0]' -- "$mysql_root_password")

# Use sed to update database configurations, using | as a delimiter instead of /
sed -i "s|\$systemConfig\['database'\]\['host'\]\s*=.*|\$systemConfig['database']['host'] = 'localhost';|" "$config_file"
sed -i "s|\$systemConfig\['database'\]\['username'\]\s*=.*|\$systemConfig['database']['username'] = 'root';|" "$config_file"
sed -i "s|\$systemConfig\['database'\]\['password'\]\s*=.*|\$systemConfig['database']['password'] = '$escaped_mysql_root_password';|" "$config_file"

# Run Migrations
echo "Running database migrations..."
php "$vlsm_path/app/system/migrate.php" -yq &
spinner

# Get the PID of the migrate.php script
pid=$!

# Show a simple progress indicator
while kill -0 $pid 2>/dev/null; do
    echo -n "."
    sleep 1
done

echo "Migration script completed."

# Prompt for Remote STS URL
read -p "Please enter the Remote STS URL (can be blank if you choose so): " remote_sts_url

# Define desired_sts_url
desired_sts_url="\$systemConfig['remoteURL'] = '$remote_sts_url';"

# Update VLSM config.production.php with Remote STS URL if provided
if [ ! -z "$remote_sts_url" ]; then
    update_config "\$systemConfig\['remoteURL'\]\s*=\s*'';" "$desired_sts_url" "$config_file"

    # Run the PHP script for remote data sync
    echo "Running remote data sync script. Please wait..."
    php "$vlsm_path/app/scheduled-jobs/remote/commonDataSync.php" &

    # Get the PID of the commonDataSync.php script
    pid=$!

    # Show a simple progress indicator
    while kill -0 $pid 2>/dev/null; do
        echo -n "."
        sleep 1
    done

    echo "Remote data sync script completed."
fi

# Ask User to Run 'run-once' Scripts
echo "Do you want to run scripts from $vlsm_path/run-once/? (yes/no)"
read -r run_once_answer

if [[ "$run_once_answer" =~ ^[Yy][Ee][Ss]$ ]]; then
    # List the files in run-once directory
    echo "Available scripts to run:"
    files=("$vlsm_path/run-once/"*.php)
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

service apache2 restart

echo "Setup complete. Proceed to VLSM setup."
