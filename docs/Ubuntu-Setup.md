# Ubuntu 22.04 Setup Guide

## Initial Setup

1. **Install Ubuntu 22.04**: Use a flash disk or CD-ROM.
2. **Update Software**: Open Terminal (`ctrl + alt + t`) and run:

    ```bash
    sudo -s;
    apt update && apt upgrade -y && autoremove -y;

    ```
3. **Install Basic Packages**:

    ```bash
    apt install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools sed mawk;

    ```
4. **Locale Settings**:

    ```bash
	locale-gen en_US en_US.UTF-8;
    export LANG=en_US.UTF-8;
    export LC_ALL=en_US.UTF-8;
    update-locale;

    ```
5. **Optional: Install VS Code**:

    ```bash
    snap install --classic code;

    ```

## Apache Setup

* **Install and Configure Apache**:

    ```bash
    apt install -y apache2;
    a2dismod mpm_event;
    a2enmod rewrite headers deflate env mpm_prefork;
    service apache2 restart;
    setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www;

    ```

## MySQL Setup

1. **Install MySQL**:

    ```bash
    apt install -y mysql-server;

    ```
2. **Set Root Password**:

    Make sure you replace `<PASSWORD>` below with the actual password you want to set.

    ```bash
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '<PASSWORD>'; FLUSH PRIVILEGES;";

    ```
3. **MySQL Configuration**:

    ```bash
    awk 'BEGIN {added=0} /skip-external-locking|mysqlx-bind-address/ { if (added == 0) { print; print "sql_mode ="; print "innodb_strict_mode = 0"; added=1; next; } } { print }' /etc/mysql/mysql.conf.d/mysqld.cnf > tmpfile && mv tmpfile /etc/mysql/mysql.conf.d/mysqld.cnf;

    ```
4. **Restart MySQL**

    ```bash
    service mysql restart;

    ```

## PHP Setup

1. **Install PHP 7.4**:

    ```bash
    add-apt-repository ppa:ondrej/php -y;
    apt update;
    apt -y install php7.4 openssl php7.4-common php7.4-cli \
    php7.4-json php7.4-common php7.4-mysql php7.4-zip php7.4-gd \
    php7.4-mbstring php7.4-curl php7.4-xml php7.4-xmlrpc php7.4-bcmath \
    php7.4-gmp php7.4-zip php7.4-intl php7.4-imagick php-mime-type php7.4-apcu;
    service apache2 restart;

    ```
2. **Configure PHP 7.4**:

    ```bash
    # Disable all PHP versions
    a2dismod $(ls /etc/apache2/mods-enabled | grep -oP '^php\d\.\d') -f
    a2enmod php7.4;

    update-alternatives --set php /usr/bin/php7.4;
    update-alternatives --set phar /usr/bin/phar7.4;
    update-alternatives --set phar.phar /usr/bin/phar.phar7.4;
    echo "apc.enable_cli=1" | tee -a /etc/php/7.4/cli/php.ini;
    service apache2 restart;

    ```

    ```bash
    # Get total RAM and calculate 75%
    TOTAL_RAM=$(awk '/MemTotal/ {print $2}' /proc/meminfo) || exit 1
    RAM_75_PERCENT=$((TOTAL_RAM*3/4/1024))M || RAM_75_PERCENT=1G

    for phpini in /etc/php/7.4/apache2/php.ini /etc/php/7.4/cli/php.ini; do
        grep -qE '^error_reporting[[:space:]=].*' $phpini || sed -i "s/^error_reporting[[:space:]=].*/error_reporting = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING/" $phpini
        sed -i "s/^post_max_size[[:space:]=].*/post_max_size = 1G/" $phpini
        sed -i "s/^upload_max_filesize[[:space:]=].*/upload_max_filesize = 1G/" $phpini
        sed -i "s/^memory_limit[[:space:]=].*/memory_limit = $RAM_75_PERCENT/" $phpini
    done

    ```

## phpMyAdmin Setup

* **Download Latest phpMyAdmin and place it in www folder**:

	```bash
	echo "Downloading and setting up phpMyAdmin..."
    wget https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.tar.gz
    tar xzf phpMyAdmin-latest-all-languages.tar.gz
    DIR_NAME=$(tar tzf phpMyAdmin-latest-all-languages.tar.gz | head -1 | cut -f1 -d"/") # Get the directory name from the tar file.
    mv $DIR_NAME /var/www/phpmyadmin # Move using the determined directory name
    rm phpMyAdmin-latest-all-languages.tar.gz

	```
* **Configuring Apache for phpMyAdmin**:

	```bash
	awk 'BEGIN {added=0} /ServerAdmin|DocumentRoot/ { if (added == 0) { print; print "Alias /phpmyadmin /var/www/phpmyadmin"; added=1; next; } } { print }' /etc/apache2/sites-available/000-default.conf > tmpfile && mv tmpfile /etc/apache2/sites-available/000-default.conf;
	service apache2 restart;

	```

## Composer Setup

* **Install Composer**:

    ```bash
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
    mv composer.phar /usr/local/bin/composer;

    ```

## Exit Root

   ```bash
    exit;

   ```

Proceed to [VLSM setup](../README.md).
