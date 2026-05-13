FROM php:8.2-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    curl zip unzip git libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Seed the named volume mount point with www-data ownership so the queue
# worker (running as www-data) can write export files there. Without this,
# Docker would create the volume as root:root and the worker would get
# "Permission denied".
RUN mkdir -p storage/app/temp \
    && chown -R www-data:www-data storage/app/temp