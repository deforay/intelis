# First stage: PHP with Apache
FROM php:8.4-apache AS php-apache

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    acl \
    cron \
    curl \
    default-mysql-client \
    gettext \
    git \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    openssl \
    rsync \
    unzip \
    vim \
    zip && \
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

# Folder Permissions
RUN setfacl -R -m u:www-data:rwx /var/www/html && \
    setfacl -dR -m u:www-data:rwx /var/www/html

# Set working directory
WORKDIR /var/www/html

# Switch to www-data user for web tasks
USER www-data

# Copy the application code to the container
COPY . .

# Install project dependencies using Composer
RUN composer install --no-dev --optimize-autoloader --no-progress && \
    composer dump-autoload --optimize

# Use custom entrypoint script for the web server
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configure the cron job
COPY ./docker/php-apache/crontab /etc/cron.d/crontab
RUN chmod 0644 /etc/cron.d/crontab && \
    crontab /etc/cron.d/crontab

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
