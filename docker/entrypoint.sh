#!/bin/bash

# Replace placeholders with actual environment variables
#envsubst '${APACHE_PORT} ${DOMAIN} ${API_DOMAIN}' < /etc/apache2/sites-enabled/app.conf.template > /etc/apache2/sites-enabled/app.conf
#envsubst '${APACHE_PORT} ${DOMAIN} ${API_DOMAIN}' < /etc/apache2/sites-enabled/api.conf.template > /etc/apache2/sites-enabled/api.conf

# Start Apache in the foreground
exec apache2-foreground