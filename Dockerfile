FROM php:8.2-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    curl zip unzip git libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# MySQL 8 client for mysqldump (default-mysql-client is MariaDB, which mis-dumps MySQL 8 generated columns); repo trusted via HTTPS so there's no GPG key to expire.
RUN echo "deb [trusted=yes] https://repo.mysql.com/apt/debian bookworm mysql-8.0" > /etc/apt/sources.list.d/mysql.list \
    && apt-get update \
    && apt-get install -y mysql-community-client \
    && rm -rf /var/lib/apt/lists/*

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Seed the named volume mount points with www-data ownership so the queue
# worker and scheduler (running as www-data) can write export and backup
# files there. Without this, Docker would create the volumes as root:root
# and the workers would get "Permission denied".
RUN mkdir -p storage/app/temp storage/backups \
    && chown -R www-data:www-data storage/app/temp storage/backups

# The base image leaves www-data's home (/var/www) root-owned.
RUN chown www-data:www-data /var/www