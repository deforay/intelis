# Ubuntu 22.04 Setup Guide

## Initial Setup

1. **Install Ubuntu 22.04**: Use a flash disk or CD-ROM.
2. **Update Software**: Open Terminal (`ctrl + alt + t`) and run:

    ```bash
    sudo apt update && sudo apt upgrade -y && sudo apt autoremove -y
    ```
3. **Install Basic Packages**:

    ```bash
    sudo apt install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools
    ```
4. **Locale Settings**:

    ```bash
	sudo locale-gen en_US en_US.UTF-8;
    export LANG=en_US.UTF-8;
    export LC_ALL=en_US.UTF-8;
    sudo update-locale;
    ```
5. **Optional: Install VS Code**:

    ```bash
    sudo snap install --classic code
    ```

## Apache Setup

* **Install and Configure Apache**:

    ```bash
    sudo apt install -y apache2;
    sudo a2enmod rewrite headers deflate env;
    sudo service apache2 restart;
    sudo setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www;
    ```

## MySQL Setup

1. **Install MySQL**:

    ```bash
    sudo apt install -y mysql-server
    ```
2. **Set Root Password**:

    Make sure you replace `<PASSWORD>` below with the actual password you want to set.

    ```bash
    sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '<PASSWORD>'; FLUSH PRIVILEGES;"
    ```
3. **MySQL Configuration**:

    ```bash
    sudo sed -i '/skip-external-locking\|127.0.0.1/a sql_mode =' /etc/mysql/mysql.conf.d/mysqld.cnf

    ```
4. **Restart MySQL**

    ```bash
    sudo service mysql restart
    ```

## PHP Setup

1. **Install PHP 7.4**:

    ```bash
    sudo add-apt-repository ppa:ondrej/php -y;
    sudo apt update;
    sudo apt -y install php7.4 openssl php7.4-common php7.4-cli \
    php7.4-json php7.4-common php7.4-mysql php7.4-zip php7.4-gd \
    php7.4-mbstring php7.4-curl php7.4-xml php7.4-xmlrpc php7.4-bcmath \
    php7.4-gmp php7.4-zip php7.4-intl php7.4-imagick php-mime-type php7.4-apcu;
    sudo service apache2 restart;
    ```
2. **Configure PHP 7.4**:

    ```bash
    # Disable all PHP versions
    sudo a2dismod $(ls /etc/apache2/mods-enabled | grep -oP '^php\d\.\d') -f
    sudo a2enmod php7.4;

    sudo update-alternatives --set php /usr/bin/php7.4;
    sudo update-alternatives --set phar /usr/bin/phar7.4;
    sudo update-alternatives --set phar.phar /usr/bin/phar.phar7.4;
    echo "apc.enable_cli=1" | sudo tee -a /etc/php/7.4/cli/php.ini;
    sudo service apache2 restart;

    ```

## Composer Setup

* **Install Composer**:

    ```bash
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
    sudo mv composer.phar /usr/local/bin/composer
    ```

Proceed to [VLSM setup](../README.md).
