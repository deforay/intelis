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
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '<PASSWORD>';";
    mysql -e "CREATE DATABASE vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
    mysql -e "CREATE DATABASE interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
    mysql -e "FLUSH PRIVILEGES;";

    ```
3. **MySQL Configuration**:

    ```bash
    sudo -s;
    echo "Configuring MySQL...";
    desired_sql_mode="sql_mode =";
    desired_innodb_strict_mode="innodb_strict_mode = 0";
    config_file="/etc/mysql/mysql.conf.d/mysqld.cnf";

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
    { print; }' $config_file > tmpfile && mv tmpfile $config_file;

    ```
4. **Restart MySQL**

    ```bash
    service mysql restart;

    ```

## PHP Setup

1. **Install PHP 8.2**:

    ```bash
    sudo -s;
    add-apt-repository ppa:ondrej/php -y;
    apt update;
    apt -y install php8.2 openssl php8.2-common php8.2-cli \
    php8.2-json php8.2-common php8.2-mysql php8.2-zip php8.2-gd \
    php8.2-mbstring php8.2-curl php8.2-xml php8.2-xmlrpc php8.2-bcmath \
    php8.2-gmp php8.2-zip php8.2-intl php8.2-imagick php-mime-type php8.2-apcu;
    service apache2 restart;

    ```
2. **Configure PHP 8.2**:

    ```bash
    # Disable all PHP versions
    sudo -s;
    a2dismod $(ls /etc/apache2/mods-enabled | grep -oP '^php\d\.\d') -f
    a2enmod php8.2;

    update-alternatives --set php /usr/bin/php8.2;
    update-alternatives --set phar /usr/bin/phar8.2;
    update-alternatives --set phar.phar /usr/bin/phar.phar8.2;
    echo "apc.enable_cli=1" | tee -a /etc/php/8.2/cli/php.ini;
    service apache2 restart;

    ```

    ```bash
    # Get total RAM and calculate 75%
    sudo -s;
    TOTAL_RAM=$(awk '/MemTotal/ {print $2}' /proc/meminfo) || exit 1
    RAM_75_PERCENT=$((TOTAL_RAM * 3 / 4 / 1024))M || RAM_75_PERCENT=1G

    desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE"
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

    ```

## phpMyAdmin Setup

* **Download Latest phpMyAdmin and place it in www folder**:

	```bash
    sudo -s;
	if [ ! -d "/var/www/phpmyadmin" ]; then
        # phpMyAdmin Setup
        echo "Downloading and setting up phpMyAdmin..."
        wget https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.tar.gz
        tar xzf phpMyAdmin-latest-all-languages.tar.gz
        DIR_NAME=$(tar tzf phpMyAdmin-latest-all-languages.tar.gz | head -1 | cut -f1 -d"/")
        mv $DIR_NAME /var/www/phpmyadmin
        rm phpMyAdmin-latest-all-languages.tar.gz
    fi

	```
* **Configuring Apache for phpMyAdmin**:

	```bash
    sudo -s;
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

	```

## Composer Setup

* **Install/Update Composer**:

    ```bash
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

    ```

## Exit Root

   ```bash
    exit;

   ```

Proceed to [VLSM setup](../README.md).
