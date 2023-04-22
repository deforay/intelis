FROM php:7.4-apache

# Install system dependencies
RUN apt-get update && \
    apt-get install -y --no-install-recommends software-properties-common \
    libzip-dev libjpeg62-turbo-dev \
    libfreetype6-dev libonig-dev libpng-dev libicu-dev libcurl3-openssl-dev \
    git zip unzip rsync vim openssl curl cron acl

# Install required PHP extensions
RUN docker-php-ext-install pdo_mysql zip mysqli mbstring curl json intl \
    zip exif bcmath gd gettext    

RUN a2enmod rewrite headers deflate env;

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy custom PHP configuration
COPY ./docker/php-apache/custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Copy Apache configuration
COPY ./docker/php-apache/app.conf /etc/apache2/sites-enabled/app.conf
COPY ./docker/php-apache/app.conf /etc/apache2/sites-enabled/000-default.conf
#COPY ./docker/php-apache/api.conf /etc/apache2/sites-enabled/api.conf

# Set working directory
WORKDIR /var/www/html

# Copy the application code to the container
COPY . /var/www/html/


# Install project dependencies using composer
#RUN composer install --no-interaction --prefer-dist --optimize-autoloader
RUN composer install --no-dev --optimize-autoloader --no-progress


# Fix permissions
#RUN chown -R www-data:www-data /var/www/html/
RUN setfacl -R -m u:www-data:rwx /var/www;


# copy the crontab in a location where it will be parsed by the system
COPY ./docker/php-apache/crontab /etc/cron.d/crontab
# owner can read and write into the crontab, group and others can read it
RUN chmod 0644 /etc/cron.d/crontab
# running our crontab using the binary from the package we installed
RUN /usr/bin/crontab /etc/cron.d/crontab

CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]

COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh