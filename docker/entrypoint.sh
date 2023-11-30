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

# Run migrations
echo "Running migrations..."
php /var/www/html/app/system/migrate.php -yq

# Start the cron service
service cron start

# Start Apache in the foreground
exec apache2-foreground
