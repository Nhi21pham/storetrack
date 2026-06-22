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

# Node.js + Chromium + fonts for Browsershot (per-invoice PDF rendering).
# Distro chromium pulls in its own shared-lib dependencies, so we don't hand-list them.
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs chromium fonts-dejavu fonts-liberation \
    && rm -rf /var/lib/apt/lists/*

# Puppeteer lives in /opt, outside the ./backend bind mount that would otherwise
# shadow a node_modules under /var/www/html. It drives the distro chromium above,
# so skip Puppeteer's own Chromium download.
ENV PUPPETEER_SKIP_DOWNLOAD=true
RUN npm install --prefix /opt/puppeteer puppeteer \
    && chown -R www-data:www-data /opt/puppeteer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Seed the named volume mount points with www-data ownership so the queue
# worker and scheduler (running as www-data) can write export and backup
# files there. Without this, Docker would create the volumes as root:root
# and the workers would get "Permission denied".
RUN mkdir -p storage/app/temp storage/backups \
    && chown -R www-data:www-data storage/app/temp storage/backups

# www-data's home (/var/www) is where Chromium writes crashpad/profile data;
# the base image leaves it root-owned. Same fix as the storage chown above.
RUN chown www-data:www-data /var/www