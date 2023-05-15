#!/bin/bash

# Replace placeholders with actual environment variables
envsubst '${APACHE_PORT} ${DOMAIN}' < /etc/apache2/sites-enabled/000-default.conf > /etc/apache2/sites-enabled/000-default.conf.tmp
mv /etc/apache2/sites-enabled/000-default.conf.tmp /etc/apache2/sites-enabled/000-default.conf

# Add domain to /etc/hosts
echo "127.0.0.1 ${DOMAIN}" >> /etc/hosts

# Start Apache in the foreground
exec apache2-foreground