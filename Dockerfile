# Base image
FROM ubuntu:22.04

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive

# Add the Ondrej PPA for PHP
RUN apt-get update && \
    apt-get install -y software-properties-common && \
    add-apt-repository ppa:ondrej/php && \
    apt-get update && apt-get upgrade -y

RUN apt-get purge php8* -y && apt-get autoremove -y

RUN apt-get install -y --no-install-recommends \
    apache2 \
    libapache2-mod-php7.4 \
    php7.4 \
    php7.4-cli \
    php7.4-mysql \
    php7.4-mbstring \
    php7.4-curl \
    php7.4-xml \
    php7.4-zip \
    php7.4-gd \
    php7.4-bcmath \
    php7.4-intl \
    mysql-client \
    git zip unzip rsync vim openssl curl cron acl && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite headers deflate env;

# Update the Apache configuration
#COPY docker/php-mysql/000-default.conf /etc/apache2/sites-available/000-default.conf

# Set the default working directory
WORKDIR /var/www/html

# Copy your application code to the container
COPY . /var/www/html

RUN setfacl -R -m u:www-data:rwx /var/www;

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-progress

RUN a2enmod php7.4;

RUN update-alternatives --set php /usr/bin/php7.4;
RUN update-alternatives --set phar /usr/bin/phar7.4;
RUN update-alternatives --set phar.phar /usr/bin/phar.phar7.4;


# copy the crontab in a location where it will be parsed by the system
COPY ./crontab /etc/cron.d/crontab
# owner can read and write into the crontab, group and others can read it
RUN chmod 0644 /etc/cron.d/crontab
# running our crontab using the binary from the package we installed
RUN /usr/bin/crontab /etc/cron.d/crontab


# Start the Apache server
CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]