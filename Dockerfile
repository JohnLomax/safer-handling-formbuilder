# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Stage 1: build Laravel admin frontend assets (Vite)
# -----------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app/backend

COPY backend/package.json backend/package-lock.json* ./
RUN npm ci

COPY backend/ ./
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: runtime — nginx + PHP-FPM (forms + Laravel via router.php)
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        git \
        unzip \
        libsqlite3-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        sqlite3 \
        zip \
        intl \
        mbstring \
        bcmath \
        opcache \
        pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# App source (exclude heavy/dev paths via .dockerignore)
COPY . .

# Composer deps for Laravel admin
WORKDIR /var/www/html/backend
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist \
    && php artisan package:discover --ansi || true

# Built Vite assets
COPY --from=assets /app/backend/public/build /var/www/html/backend/public/build

WORKDIR /var/www/html

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p \
        /var/www/html/data \
        /var/www/html/data/booking-uploads \
        /var/www/html/backend/storage/framework/{cache,sessions,views} \
        /var/www/html/backend/storage/logs \
        /var/www/html/backend/bootstrap/cache \
    && chown -R www-data:www-data \
        /var/www/html/data \
        /var/www/html/backend/storage \
        /var/www/html/backend/bootstrap/cache \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/data/app.sqlite \
    APP_DATABASE_PATH=/var/www/html/data/app.sqlite \
    LOG_CHANNEL=stderr

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
