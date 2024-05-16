# First stage: PHP with Apache
FROM php:8.2-apache AS php-apache

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libzip-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libpng-dev libicu-dev libcurl4-openssl-dev \
    git zip unzip rsync vim openssl curl acl gettext cron \
    default-mysql-client && \
    apt-get upgrade -y openssl apache2 curl libxml2 && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql zip mysqli mbstring \
    curl intl exif bcmath gd gettext && \
    a2enmod rewrite headers deflate env && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy custom PHP and Apache configuration
COPY ./docker/php-apache/custom-php.ini /usr/local/etc/php/conf.d/
COPY ./docker/php-apache/app.conf /etc/apache2/sites-enabled/000-default.conf

# Second stage: web server
FROM php-apache AS php-web

# Set working directory
WORKDIR /var/www/html

# Copy the application code to the container
COPY . .

# Install project dependencies using Composer
RUN composer install --no-dev --optimize-autoloader --no-progress && \
    composer dump-autoload --optimize

# Fix permissions
RUN setfacl -R -m u:www-data:rwx /var/www/html && \
    setfacl -dR -m u:www-data:rwx /var/www/html

# Use custom entrypoint script for the web server
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configure the cron job
COPY ./docker/php-apache/crontab /etc/cron.d/crontab
RUN chmod 0644 /etc/cron.d/crontab && \
    crontab /etc/cron.d/crontab

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
