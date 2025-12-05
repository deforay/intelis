#!/bin/bash

# Replace placeholders with actual environment variables
envsubst '${APACHE_PORT} ${DOMAIN}' </etc/apache2/sites-enabled/000-default.conf >/etc/apache2/sites-enabled/000-default.conf.tmp
mv /etc/apache2/sites-enabled/000-default.conf.tmp /etc/apache2/sites-enabled/000-default.conf

# Add domain to /etc/hosts
echo "127.0.0.1 ${DOMAIN}" >>/etc/hosts

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
while ! mysqladmin ping -h"db" --silent; do
    sleep 1
done
echo "MySQL is ready."

# Update VLSM config.production.php with database credentials
config_file="/var/www/html/configs/config.production.php"
source_file="/var/www/html/configs/config.production.dist.php"

if [ ! -e "$config_file" ]; then
    echo "Renaming config.production.dist.php to config.production.php..."
    mv "$source_file" "$config_file"
else
    echo "File config.production.php already exists. Skipping renaming."
fi

# Read MySQL password from environment variable
mysql_root_password=${MYSQL_ROOT_PASSWORD:-default_password}

# Escape password for sed replacement and PHP single-quoted strings
escaped_mysql_root_password=$(escape_php_string_for_sed "$mysql_root_password")

# Use sed to update database configurations
sed -i "s|\$systemConfig\['database'\]\['host'\]\s*=.*|\$systemConfig['database']['host'] = 'db';|" "$config_file"
sed -i "s|\$systemConfig\['database'\]\['username'\]\s*=.*|\$systemConfig['database']['username'] = 'root';|" "$config_file"
sed -i "s|\$systemConfig\['database'\]\['password'\]\s*=.*|\$systemConfig['database']['password'] = '$escaped_mysql_root_password';|" "$config_file"

# Modify php.ini as needed
echo "Modifying PHP configurations..."

# Get total RAM and calculate 75%
TOTAL_RAM=$(awk '/MemTotal/ {print $2}' /proc/meminfo) || exit 1
RAM_75_PERCENT=$((TOTAL_RAM * 3 / 4 / 1024))M || RAM_75_PERCENT=1G

desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING"
desired_post_max_size="post_max_size = 1G"
desired_upload_max_filesize="upload_max_filesize = 1G"
desired_memory_limit="memory_limit = $RAM_75_PERCENT"

for phpini in /usr/local/etc/php/php.ini; do
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

# Navigate to the application directory and run composer post-install scripts
cd /var/www/html/
composer post-install

# Start the cron service
service cron start

# Start Apache in the foreground
exec apache2-foreground
