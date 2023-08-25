#!/bin/bash


# To use this script:
# Save the above code into a file, for example, ubuntu-setup.sh.
# Make the script executable: chmod +x ubuntu-setup.sh.
# Run the script: ./ubuntu-setup.sh.


# START OF SCRIPT - BE CAREFUL OF WHAT YOU CHANGE BELOW THIS LINE

# Exit on error
set -e

# Initial Setup
echo "Updating software packages..."
sudo apt update && sudo apt upgrade -y && sudo apt autoremove -y

echo "Installing basic packages..."
sudo apt install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools

echo "Setting up locale..."
sudo locale-gen en_US en_US.UTF-8
export LANG=en_US.UTF-8
export LC_ALL=en_US.UTF-8
sudo update-locale

# Optional: Install VS Code
read -p "Do you want to install Visual Studio Code? (y/n): " install_vscode
if [ "$install_vscode" == "y" ]; then
    sudo snap install --classic code
fi

# Apache Setup
echo "Installing and configuring Apache..."
sudo apt install -y apache2
sudo a2enmod rewrite headers deflate env
sudo service apache2 restart
sudo setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www

# MySQL Setup
echo "Installing MySQL..."
sudo apt install -y mysql-server

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
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$mysql_root_password'; FLUSH PRIVILEGES;"
sudo mysql -e "CREATE DATABASE vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
sudo mysql -e "CREATE DATABASE interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

echo "Configuring MySQL..."
sudo sed -i '/skip-external-locking\|127.0.0.1/a sql_mode =' /etc/mysql/mysql.conf.d/mysqld.cnf
sudo service mysql restart


# PHP Setup
echo "Installing PHP 7.4..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php7.4 openssl php7.4-common php7.4-cli php7.4-json php7.4-mysql php7.4-zip php7.4-gd php7.4-mbstring php7.4-curl php7.4-xml php7.4-xmlrpc php7.4-bcmath php7.4-gmp php7.4-intl php7.4-imagick php-mime-type php7.4-apcu
sudo service apache2 restart

echo "Configuring PHP 7.4..."
sudo a2dismod $(ls /etc/apache2/mods-enabled | grep -oP '^php\d\.\d') -f
sudo a2enmod php7.4
sudo update-alternatives --set php /usr/bin/php7.4
echo "apc.enable_cli=1" | sudo tee -a /etc/php/7.4/cli/php.ini
sudo service apache2 restart

# phpMyAdmin Setup
echo "Downloading and setting up phpMyAdmin..."
wget https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.tar.gz
tar xzf phpMyAdmin-latest-all-languages.tar.gz
sudo mv phpMyAdmin-*-all-languages /var/www/phpmyadmin
rm phpMyAdmin-latest-all-languages.tar.gz

echo "Configuring Apache for phpMyAdmin..."
sudo sed -i '/<\/VirtualHost>/i \    Alias \/phpmyadmin \/var\/www\/phpmyadmin' /etc/apache2/sites-available/000-default.conf
sudo service apache2 restart

# Composer Setup
echo "Installing Composer..."
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer


# VLSM Setup
echo "Cloning VLSM repository..."
git clone https://github.com/deforay/vlsm.git /var/www/vlsm

echo "Running composer update in VLSM folder..."
cd /var/www/vlsm
composer update

# Import init.sql into the vlsm database
echo "Importing init.sql into the vlsm database..."
sudo mysql -u root -p"$mysql_root_password" vlsm < /var/www/vlsm/sql/init.sql

echo "Renaming config.production.dist.php to config.production.php..."
mv /var/www/vlsm/configs/config.production.dist.php /var/www/vlsm/configs/config.production.php

echo "Adding VLSM to hosts file..."
echo "127.0.0.1 vlsm" | sudo tee -a /etc/hosts

echo "Updating Apache configuration for VLSM..."
sudo sed -i '/DocumentRoot/c\    DocumentRoot "/var/www/vlsm/public"' /etc/apache2/sites-available/000-default.conf
sudo sed -i '/DocumentRoot/a\    ServerName vlsm\n\n    <Directory "/var/www/vlsm/public">\n        AddDefaultCharset UTF-8\n        Options -Indexes -MultiViews +FollowSymLinks\n        AllowOverride All\n        Order allow,deny\n        Allow from all\n    </Directory>' /etc/apache2/sites-available/000-default.conf

sudo service apache2 restart

echo "Adding cron job for VLSM..."
echo "* * * * * cd /var/www/vlsm/ && ./vendor/bin/crunz schedule:run" | sudo tee -a /var/spool/cron/crontabs/root


echo "Setup complete. Proceed to VLSM setup."
