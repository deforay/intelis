# Ubuntu, not the official PHP image.
#
# The PHP images are Debian, and the difference is not cosmetic. upgrade.sh
# manages the operating system around the application — apt packages, the PHP
# version, systemd units, MySQL tuning — and refuses to run on anything that is
# not Ubuntu LTS. So a Debian container could never be upgraded by the same
# script the rest of the fleet uses, including remotely from the STS: the
# command was accepted, spent minutes installing packages into a layer the next
# rebuild discards, and stopped at "This script requires Ubuntu 20.04 or newer".
#
# Matching the fleet's operating system means the container exercises the same
# paths a lab does, rather than an approximation of them.
#
# UBUNTU_VERSION is overridable at build time:
#   docker build --build-arg UBUNTU_VERSION=26.04 --build-arg PHP_VERSION=8.5 .
# 24.04 ships PHP 8.3 and 26.04 no longer ships 8.4, so PHP comes from the
# ondrej/php PPA in both cases and the version is pinned explicitly.
ARG UBUNTU_VERSION=24.04
FROM ubuntu:${UBUNTU_VERSION} AS php-apache

ARG PHP_VERSION=8.4
ENV PHP_VERSION=${PHP_VERSION}
ENV DEBIAN_FRONTEND=noninteractive

# Apache's envvars are sourced by apache2ctl on a normal install; a container
# runs the binary directly, so the ones it needs are set here.
ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_PID_FILE=/var/run/apache2/apache2.pid \
    APACHE_LOCK_DIR=/var/lock/apache2 \
    APACHE_LOG_DIR=/var/log/apache2

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg lsb-release software-properties-common && \
    add-apt-repository -y ppa:ondrej/php && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        acl \
        apache2 \
        cron \
        default-mysql-client \
        fzf \
        gettext-base \
        git \
        jq \
        libapache2-mod-php${PHP_VERSION} \
        locales \
        openssl \
        php${PHP_VERSION} \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        rsync \
        sudo \
        unzip \
        vim \
        wget \
        zip && \
    sed -i 's/^# *en_US.UTF-8/en_US.UTF-8/' /etc/locale.gen && locale-gen && \
    a2enmod rewrite headers deflate env php${PHP_VERSION} && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHP and Apache configuration. On Ubuntu these live under /etc/php/<version>,
# not /usr/local/etc/php as they do in the official images, and both SAPIs need
# them: the CLI runs migrations and cron, Apache serves the application.
COPY ./docker/php-apache/custom-php.ini /etc/php/${PHP_VERSION}/apache2/conf.d/99-intelis.ini
COPY ./docker/php-apache/custom-php.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-intelis.ini
COPY ./docker/php-apache/app.conf /etc/apache2/sites-enabled/000-default.conf

# Second stage: web server
FROM php-apache AS php-web

COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY ./docker/php-apache/crontab /etc/cron.d/crontab
RUN chmod 0644 /etc/cron.d/crontab && \
    crontab /etc/cron.d/crontab

WORKDIR /var/www/html
RUN mkdir -p /var/run/apache2 /var/lock/apache2 && \
    setfacl -R -m u:www-data:rwx /var/www/html && \
    setfacl -dR -m u:www-data:rwx /var/www/html

USER www-data

COPY --chown=www-data:www-data . .

RUN composer install --no-dev --optimize-autoloader --no-progress && \
    composer dump-autoload --optimize

# Back to root for the entrypoint: it edits the Apache config, sets ownership,
# and starts cron. Apache's own workers still drop to www-data.
USER root

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
