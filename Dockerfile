# First stage: PHP with Apache
FROM php:7.4-apache AS php-apache

# Install system dependencies
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libzip-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libpng-dev libicu-dev libcurl4-openssl-dev \
    git zip unzip rsync vim openssl curl acl gettext cron && \
    rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql zip mysqli mbstring \
    curl json intl exif bcmath gd gettext

RUN a2enmod rewrite headers deflate env;

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy custom PHP configuration
COPY ./docker/php-apache/custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Copy Apache configuration
COPY ./docker/php-apache/app.conf /etc/apache2/sites-enabled/000-default.conf

# Second stage: web server
FROM php-apache AS php-web

# Set working directory
WORKDIR /var/www/html

# Copy the application code to the container
COPY . /var/www/html/

# Install project dependencies using composer
RUN composer install --no-dev --optimize-autoloader --no-progress

# Fix permissions
RUN setfacl -R -m u:www-data:rwx /var/www/html;

# Use custom entrypoint script for the web server
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]

# Third stage: PHP with CLI for Cron
FROM php-apache AS php-cron

# Configure the cron job
COPY ./docker/php-apache/crontab /etc/cron.d/crontab
RUN chmod 0644 /etc/cron.d/crontab && \
    crontab /etc/cron.d/crontab

# Use custom entrypoint script
COPY ./docker/cron-entrypoint.sh /usr/local/bin/cron-entrypoint.sh
RUN chmod +x /usr/local/bin/cron-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/cron-entrypoint.sh"]
CMD ["cron", "-f"]
