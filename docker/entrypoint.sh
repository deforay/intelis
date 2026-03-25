#!/bin/bash

# Source shared functions (for escape_php_string_for_sed)
source /var/www/html/scripts/shared-functions.sh

# Replace placeholders with actual environment variables
envsubst '${APACHE_PORT} ${DOMAIN}' </etc/apache2/sites-enabled/000-default.conf >/etc/apache2/sites-enabled/000-default.conf.tmp
mv /etc/apache2/sites-enabled/000-default.conf.tmp /etc/apache2/sites-enabled/000-default.conf

# Add domain to /etc/hosts
echo "127.0.0.1 ${DOMAIN}" >>/etc/hosts

# Main DB config
main_db_host="db"
main_db_user="root"
main_db_password="${MYSQL_ROOT_PASSWORD:-default_password}"
main_db_name="${MYSQL_DATABASE:-vlsm}"

# Interfacing DB config — defaults to main DB container
iface_db_host="${INTERFACE_DB_HOST:-$main_db_host}"
iface_db_port="${INTERFACE_DB_PORT:-3306}"
iface_db_user="${INTERFACE_DB_USER:-$main_db_user}"
iface_db_password="${INTERFACE_DB_PASSWORD:-$main_db_password}"
iface_db_name="${INTERFACE_DB_NAME:-interfacing}"
interfacing_enabled="${INTERFACING_ENABLED:-true}"

# Wait for main MySQL to be ready
echo "Waiting for main MySQL to be ready..."
while ! mysqladmin ping -h"$main_db_host" --silent; do
    sleep 1
done
echo "Main MySQL is ready."

# Persist sql_mode='' to ensure it survives restarts (matches upgrade.sh behavior)
echo "Persisting sql_mode=''..."
mysql -h "$main_db_host" -u "$main_db_user" -p"$main_db_password" \
    -e "SET PERSIST sql_mode = '';" 2>/dev/null || echo "Warning: SET PERSIST sql_mode failed (non-fatal)"

# Import audit-triggers.sql if it exists
if [ -f /var/www/html/sql/audit-triggers.sql ]; then
    echo "Importing audit-triggers.sql..."
    mysql -h "$main_db_host" -u "$main_db_user" -p"$main_db_password" "$main_db_name" </var/www/html/sql/audit-triggers.sql 2>/dev/null || true
fi

# Set up interfacing database if enabled
if [ "$interfacing_enabled" = "true" ]; then
    # If interfacing DB is on a different host, wait for it separately
    if [ "$iface_db_host" != "$main_db_host" ]; then
        echo "Waiting for interfacing MySQL ($iface_db_host:$iface_db_port) to be ready..."
        while ! mysqladmin ping -h"$iface_db_host" -P"$iface_db_port" --silent; do
            sleep 1
        done
        echo "Interfacing MySQL is ready."
    fi

    # Create the interfacing database if it doesn't exist
    mysql -h "$iface_db_host" -P "$iface_db_port" -u "$iface_db_user" -p"$iface_db_password" \
        -e "CREATE DATABASE IF NOT EXISTS \`$iface_db_name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

    # Import interface-init.sql if the interfacing DB is empty
    iface_tables=$(mysql -h "$iface_db_host" -P "$iface_db_port" -u "$iface_db_user" -p"$iface_db_password" \
        -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$iface_db_name';")
    if [ "$iface_tables" -eq 0 ] && [ -f /var/www/html/sql/interface-init.sql ]; then
        echo "Importing interface-init.sql..."
        mysql -h "$iface_db_host" -P "$iface_db_port" -u "$iface_db_user" -p"$iface_db_password" \
            "$iface_db_name" </var/www/html/sql/interface-init.sql
    fi
fi

# Update config.production.php with database credentials
config_file="/var/www/html/configs/config.production.php"
source_file="/var/www/html/configs/config.production.dist.php"

if [ ! -e "$config_file" ]; then
    echo "Creating config.production.php from dist template..."
    cp "$source_file" "$config_file"
    chown www-data:www-data "$config_file"
else
    echo "File config.production.php already exists. Skipping."
fi

# Escape passwords for sed replacement and PHP single-quoted strings
escaped_main_password=$(escape_php_string_for_sed "$main_db_password")
escaped_iface_password=$(escape_php_string_for_sed "$iface_db_password")

# Update main database config
sed -i "s|\$systemConfig\['database'\]\['host'\]\s*=.*|\$systemConfig['database']['host'] = '$main_db_host';|" "$config_file"
sed -i "s|\$systemConfig\['database'\]\['username'\]\s*=.*|\$systemConfig['database']['username'] = '$main_db_user';|" "$config_file"
sed -i "s|\$systemConfig\['database'\]\['password'\]\s*=.*|\$systemConfig['database']['password'] = '$escaped_main_password';|" "$config_file"

# Update interfacing database config
sed -i "s|\$systemConfig\['interfacing'\]\['enabled'\]\s*=.*|\$systemConfig['interfacing']['enabled'] = $interfacing_enabled;|" "$config_file"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['host'\]\s*=.*|\$systemConfig['interfacing']['database']['host'] = '$iface_db_host';|" "$config_file"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['username'\]\s*=.*|\$systemConfig['interfacing']['database']['username'] = '$iface_db_user';|" "$config_file"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['password'\]\s*=.*|\$systemConfig['interfacing']['database']['password'] = '$escaped_iface_password';|" "$config_file"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['db'\]\s*=.*|\$systemConfig['interfacing']['database']['db'] = '$iface_db_name';|" "$config_file"
sed -i "s|\$systemConfig\['interfacing'\]\['database'\]\['port'\]\s*=.*|\$systemConfig['interfacing']['database']['port'] = $iface_db_port;|" "$config_file"

# Navigate to the application directory
cd /var/www/html/

# Clean up stale cache and compiled files (matches upgrade.sh cleanup)
rm -f var/cache/CompiledContainer.php 2>/dev/null || true
rm -f startup.php && touch startup.php 2>/dev/null || true
rm -f public/test.php 2>/dev/null || true

# Run composer post-update (superset of post-install: purge-cache + migrate + fixes)
composer post-update

# Run database repairs
composer db:repair 2>/dev/null || true

# Run any run-once scripts
if [ -d run-once ]; then
    for script in run-once/*.php; do
        [ -f "$script" ] || continue
        echo "Running run-once script: $script"
        php "$script" || echo "Warning: $script exited with status $?"
    done
fi

# Start the cron service
service cron start

# Start Apache in the foreground
exec apache2-foreground
