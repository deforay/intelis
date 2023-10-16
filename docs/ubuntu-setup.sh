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
    if ! command -v $cmd &> /dev/null; then
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
if command -v apache2 &> /dev/null; then
    echo "Apache is already installed. Skipping installation..."
else
    echo "Installing and configuring Apache..."
    apt install -y apache2
    a2dismod mpm_event
    a2enmod rewrite headers deflate env mpm_prefork
    service apache2 restart || { echo "Failed to restart Apache2. Exiting..."; exit 1; }
    setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www
fi

# MySQL Setup
if command -v mysql &> /dev/null; then
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
    awk 'BEGIN {added=0} /skip-external-locking|mysqlx-bind-address/ { if (added == 0) { print; print "sql_mode ="; print "innodb_strict_mode = 0"; added=1; next; } } { print }' /etc/mysql/mysql.conf.d/mysqld.cnf > tmpfile && mv tmpfile /etc/mysql/mysql.conf.d/mysqld.cnf
    service mysql restart || { echo "Failed to restart MySQL. Exiting..."; exit 1; }
fi

# PHP Setup
echo "Installing PHP 7.4..."
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php7.4 openssl php7.4-common php7.4-cli php7.4-json php7.4-mysql php7.4-zip php7.4-gd php7.4-mbstring php7.4-curl php7.4-xml php7.4-xmlrpc php7.4-bcmath php7.4-gmp php7.4-intl php7.4-imagick php-mime-type php7.4-apcu
service apache2 restart || { echo "Failed to restart Apache2. Exiting..."; exit 1; }

echo "Configuring PHP 7.4..."
a2dismod $(ls /etc/apache2/mods-enabled | grep -oP '^php\d\.\d') -f
a2enmod php7.4
update-alternatives --set php /usr/bin/php7.4
# Before adding `apc.enable_cli=1` to php.ini, check if it's not already there
grep -qF 'apc.enable_cli=1' /etc/php/7.4/cli/php.ini || echo "apc.enable_cli=1" | tee -a /etc/php/7.4/cli/php.ini
service apache2 restart || { echo "Failed to restart Apache2. Exiting..."; exit 1; }


Alright, I will include the requested changes in your script.

Here's the modified part of the script:

Adjusting the php.ini settings:
error_reporting will be set as requested.
post_max_size and upload_max_filesize will both be set to 1G.
memory_limit will be set to 75% of system RAM.
Here's how the script is modified:

bash
Copy code
...

# PHP Setup
echo "Installing PHP 7.4..."
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php7.4 openssl php7.4-common php7.4-cli php7.4-json php7.4-mysql php7.4-zip php7.4-gd php7.4-mbstring php7.4-curl php7.4-xml php7.4-xmlrpc php7.4-bcmath php7.4-gmp php7.4-intl php7.4-imagick php-mime-type php7.4-apcu
service apache2 restart || { echo "Failed to restart Apache2. Exiting..."; exit 1; }

echo "Configuring PHP 7.4..."
a2dismod $(ls /etc/apache2/mods-enabled | grep -oP '^php\d\.\d') -f
a2enmod php7.4
update-alternatives --set php /usr/bin/php7.4
echo "apc.enable_cli=1" | tee -a /etc/php/7.4/cli/php.ini
service apache2 restart || { echo "Failed to restart Apache2. Exiting..."; exit 1; }

# Modify php.ini as needed
echo "Modifying PHP configurations..."

# Get total RAM and calculate 75%
TOTAL_RAM=$(awk '/MemTotal/ {print $2}' /proc/meminfo)
RAM_75_PERCENT=$((TOTAL_RAM*3/4/1024))M

for phpini in /etc/php/7.4/apache2/php.ini /etc/php/7.4/cli/php.ini; do
    grep -qE '^error_reporting = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING' $phpini || sed -i "s/^error_reporting = .*/error_reporting = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING/" $phpini
    sed -i "s/^post_max_size = .*/post_max_size = 1G/" $phpini
    sed -i "s/^upload_max_filesize = .*/upload_max_filesize = 1G/" $phpini
    sed -i "s/^memory_limit = .*/memory_limit = $RAM_75_PERCENT/" $phpini
done


# phpMyAdmin Setup
echo "Downloading and setting up phpMyAdmin..."
wget https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.tar.gz
tar xzf phpMyAdmin-latest-all-languages.tar.gz
DIR_NAME=$(tar tzf phpMyAdmin-latest-all-languages.tar.gz | head -1 | cut -f1 -d"/") # Get the directory name from the tar file.
mv $DIR_NAME /var/www/phpmyadmin # Move using the determined directory name
rm phpMyAdmin-latest-all-languages.tar.gz

echo "Configuring Apache for phpMyAdmin..."
awk 'BEGIN {added=0} /ServerAdmin|DocumentRoot/ { if (added == 0) { print; print "Alias /phpmyadmin /var/www/phpmyadmin"; added=1; next; } } { print }' /etc/apache2/sites-available/000-default.conf > tmpfile && mv tmpfile /etc/apache2/sites-available/000-default.conf
service apache2 restart || { echo "Failed to restart Apache2. Exiting..."; exit 1; }

# Composer Setup
echo "Checking for Composer..."
if command -v composer &> /dev/null; then
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
        exit 1
    fi
    php -r "unlink('composer-setup.php');"
    mv composer.phar /usr/local/bin/composer
fi

# VLSM Setup
echo "Cloning VLSM repository..."
git clone https://github.com/deforay/vlsm.git /var/www/vlsm

echo "Running composer update in VLSM folder..."
cd /var/www/vlsm
composer update

# Import init.sql into the vlsm database
echo "Importing init.sql into the vlsm database..."
mysql -u root -p"$mysql_root_password" vlsm < /var/www/vlsm/sql/init.sql

echo "Adding VLSM to hosts file..."
echo "127.0.0.1 vlsm" | tee -a /etc/hosts

echo "Updating Apache configuration for VLSM..."
sed -i '/DocumentRoot/c\    DocumentRoot "/var/www/vlsm/public"' /etc/apache2/sites-available/000-default.conf
sed -i '/DocumentRoot/a\    ServerName vlsm\n\n    <Directory "/var/www/vlsm/public">\n        AddDefaultCharset UTF-8\n        Options -Indexes -MultiViews +FollowSymLinks\n        AllowOverride All\n        Order allow,deny\n        Allow from all\n    </Directory>' /etc/apache2/sites-available/000-default.conf

service apache2 restart || { echo "Failed to restart Apache2. Exiting..."; exit 1; }

echo "Adding cron job for VLSM..."
echo "* * * * * cd /var/www/vlsm/ && ./vendor/bin/crunz schedule:run" | tee -a /var/spool/cron/crontabs/root

echo "Renaming config.production.dist.php to config.production.php..."
mv /var/www/vlsm/configs/config.production.dist.php /var/www/vlsm/configs/config.production.php

# Update VLSM config.production.php with database credentials
sed -i "s/\$systemConfig\['database'\]\['host'\]\s*=\s*'';/\$systemConfig\['database'\]\['host'\] = 'localhost';/g" /var/www/vlsm/configs/config.production.php
sed -i "s/\$systemConfig\['database'\]\['username'\]\s*=\s*'';/\$systemConfig\['database'\]\['username'\] = 'root';/g" /var/www/vlsm/configs/config.production.php
sed -i "s/\$systemConfig\['database'\]\['password'\]\s*=\s*'';/\$systemConfig\['database'\]\['password'\] = '$mysql_root_password';/g" /var/www/vlsm/configs/config.production.php

# Prompt for Remote STS URL
read -p "Please enter the Remote STS URL (can be blank if you choose so): " remote_sts_url

# Update VLSM config.production.php with Remote STS URL if provided
if [ ! -z "$remote_sts_url" ]; then
    sed -i "s|\$systemConfig\['remoteURL'\]\s*=\s*'';|\$systemConfig\['remoteURL'\] = '$remote_sts_url';|g" /var/www/vlsm/configs/config.production.php

    # Run the PHP script for remote data sync
    echo "Running remote data sync script. Please wait..."
    php /var/www/vlsm/app/scheduled-jobs/remote/commonDataSync.php &

    # Get the PID of the last background command (the PHP script)
    pid=$!

    # Show a simple progress indicator
    while kill -0 $pid 2>/dev/null; do
        echo -n "."
        sleep 1
    done

    echo "Remote data sync script completed."
fi


echo "Setup complete. Proceed to VLSM setup."
