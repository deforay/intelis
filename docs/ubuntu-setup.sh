#!/bin/bash

# To use this script:
# Save the above code into a file, for example, ubuntu-setup.sh.
# Make the script executable: chmod +x ubuntu-setup.sh.
# Run the script: ./ubuntu-setup.sh.

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

# MySQL Setup
if command -v mysql &>/dev/null; then
    echo "MySQL is already installed. Skipping installation..."
else
    echo "Installing MySQL..."
    apt install -y mysql-server

    # Prompt for MySQL root password and confirmation
    mysql_root_password=""
    mysql_root_password_confirm=""
    while [ -z "$mysql_root_password" ] || [ "$mysql_root_password" != "$mysql_root_password_confirm" ]; do
        read -sp "Please enter the MySQL root password you want to set (cannot be blank): " mysql_root_password
        echo
        read -sp "Please confirm the MySQL root password: " mysql_root_password_confirm
        echo

        if [ -z "$mysql_root_password" ]; then
            echo "Password cannot be blank."
        elif [ "$mysql_root_password" != "$mysql_root_password_confirm" ]; then
            echo "Passwords do not match. Please try again."
        fi
    done

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
fi

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
# Before adding `apc.enable_cli=1` to php.ini, check if it's not already there
grep -qF 'apc.enable_cli=1' /etc/php/7.4/cli/php.ini || echo "apc.enable_cli=1" | tee -a /etc/php/7.4/cli/php.ini
service apache2 restart || {
    echo "Failed to restart Apache2. Exiting..."
    exit 1
}

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

# VLSM Setup
echo "Downloading VLSM..."
wget https://github.com/deforay/vlsm/archive/refs/heads/master.zip

# Unzip the file into a temporary directory
temp_dir=$(mktemp -d)
unzip master.zip -d "$temp_dir"

# backup old code if it exists
if [ -d "/var/www/vlsm" ]; then
    cp -R /var/www/vlsm /var/www/vlsm-$(date +%Y-%m-%d)
else
    mkdir -p /var/www/vlsm/
fi

# Copy the unzipped content to the /var/www/vlsm directory, overwriting any existing files
cp -R "$temp_dir/vlsm-master/"* /var/www/vlsm/

# Remove the empty directory and the downloaded zip file
rm -rf /var/www/vlsm/vlsm-master
rm master.zip

echo "Running composer update in VLSM folder..."
cd /var/www/vlsm
composer update

# Import init.sql into the vlsm database
echo "Importing init.sql into the vlsm database..."
mysql -u root -p"$mysql_root_password" vlsm </var/www/vlsm/sql/init.sql

echo "Adding VLSM to hosts file..."
echo "127.0.0.1 vlsm" | tee -a /etc/hosts

echo "Updating Apache configuration for VLSM..."
config_file="/etc/apache2/sites-available/000-default.conf"
temp_file="/tmp/000-default.conf.tmp"
desired_document_root='    DocumentRoot "/var/www/vlsm/public"'
desired_server_name='    ServerName vlsm'
desired_directory_block=$(
    cat <<EOF
    <Directory "/var/www/vlsm/public">
        AddDefaultCharset UTF-8
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Order allow,deny
        Allow from all
    </Directory>
EOF
)

# Check if the desired configurations already exist
if ! grep -qF "$desired_document_root" $config_file ||
    ! grep -qF "$desired_server_name" $config_file ||
    ! grep -qF "$desired_directory_block" $config_file; then

    # Replace the DocumentRoot directive
    sed "/DocumentRoot/c\\
$desired_document_root" $config_file >$temp_file && mv $temp_file $config_file

    # Comment out any existing, incorrect ServerName and Directory block for /var/www/vlsm/public
    sed "/ServerName vlsm/,/<\/Directory>/s/^/#/" $config_file >$temp_file && mv $temp_file $config_file

    # Append the desired ServerName and Directory block after the DocumentRoot directive
    # As appending with sed can be tricky, especially with multi-line strings, use awk instead
    awk -v dr="$desired_document_root" -v sn="$desired_server_name" -v db="$desired_directory_block" '
        {print}
        $0 ~ dr {
            print sn "\n"
            print db
        }
    ' $config_file >$temp_file && mv $temp_file $config_file
fi

# Restart the Apache service, and exit if the restart fails
service apache2 restart || {
    echo "Failed to restart Apache2. Exiting..."
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

target_file="/var/www/vlsm/configs/config.production.php"
source_file="/var/www/vlsm/configs/config.production.dist.php"

if [ ! -e "$target_file" ]; then
    echo "Renaming config.production.dist.php to config.production.php..."
    mv "$source_file" "$target_file"
else
    echo "File config.production.php already exists. Skipping renaming."
fi

# Update VLSM config.production.php with database credentials
config_file="/var/www/vlsm/configs/config.production.php"

desired_db_host="\$systemConfig['database']['host'] = 'localhost';"
desired_db_username="\$systemConfig['database']['username'] = 'root';"
desired_db_password="\$systemConfig['database']['password'] = '$mysql_root_password';"

# Function to ensure idempotent configuration updates
function update_config {
    local pattern=$1
    local replacement=$2
    local file=$3

    grep -qF "$replacement" "$file" ||
        sed -i "s/$pattern/$replacement/g" "$file"
}

# Update the configurations if necessary
update_config "\$systemConfig\['database'\]\['host'\]\s*=\s*'';" "$desired_db_host" "$config_file"
update_config "\$systemConfig\['database'\]\['username'\]\s*=\s*'';" "$desired_db_username" "$config_file"
update_config "\$systemConfig\['database'\]\['password'\]\s*=\s*'';" "$desired_db_password" "$config_file"

# Prompt for Remote STS URL
read -p "Please enter the Remote STS URL (can be blank if you choose so): " remote_sts_url

# Define desired_sts_url
desired_sts_url="\$systemConfig['remoteURL'] = '$remote_sts_url';"

# Update VLSM config.production.php with Remote STS URL if provided
if [ ! -z "$remote_sts_url" ]; then
    update_config "\$systemConfig\['remoteURL'\]\s*=\s*'';" "$desired_sts_url" "$config_file"

    # Run the PHP script for remote data sync
    echo "Running remote data sync script. Please wait..."
    php /var/www/vlsm/app/scheduled-jobs/remote/commonDataSync.php &

    # Get the PID of the commonDataSync.php script
    pid=$!

    # Show a simple progress indicator
    while kill -0 $pid 2>/dev/null; do
        echo -n "."
        sleep 1
    done

    echo "Remote data sync script completed."
fi

# Run the PHP script for migrations
echo "Running migrations. Please wait..."
php /var/www/vlsm/app/system/migrate.php -y &

# Get the PID of the migrate.php script
pid=$!

# Show a simple progress indicator
while kill -0 $pid 2>/dev/null; do
    echo -n "."
    sleep 1
done

echo "Migration script completed."

echo "Setup complete. Proceed to VLSM setup."
