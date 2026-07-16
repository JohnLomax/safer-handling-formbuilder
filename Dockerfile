# -----------------------------------------------------------------------------
# Stage 1: build Laravel admin frontend assets (Vite)
# -----------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app/backend

COPY backend/package.json backend/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY backend/ ./
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: runtime — nginx + PHP-FPM (forms + Laravel via router.php)
# -----------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm

# Keep build independent of Coolify runtime env (e.g. APP_ENV=local)
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    DEBIAN_FRONTEND=noninteractive

# pdo_sqlite is already bundled in php:8.3-fpm — do not reinstall it (or sqlite3).
# Install remaining extensions one-by-one (no -j) to avoid phpize race failures.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        git \
        unzip \
        libsqlite3-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-install zip \
    && docker-php-ext-install intl \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install opcache \
    && docker-php-ext-install pcntl \
    && php -m | grep -qi pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# Minimal .env so artisan/composer scripts do not fail during image build
WORKDIR /var/www/html/backend
RUN printf '%s\n' \
      'APP_NAME="Safe Handler Admin"' \
      'APP_ENV=production' \
      'APP_KEY=' \
      'APP_DEBUG=false' \
      'APP_URL=http://localhost' \
      'DB_CONNECTION=sqlite' \
      'DB_DATABASE=/var/www/html/data/app.sqlite' \
      'APP_DATABASE_PATH=/var/www/html/data/app.sqlite' \
      'SESSION_DRIVER=file' \
      'CACHE_STORE=file' \
      'QUEUE_CONNECTION=sync' \
      'LOG_CHANNEL=stderr' \
      > .env \
    && mkdir -p \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      bootstrap/cache \
      /var/www/html/data \
    && touch /var/www/html/data/app.sqlite \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && composer install \
      --no-dev \
      --optimize-autoloader \
      --no-interaction \
      --prefer-dist \
      --no-scripts \
    && php artisan package:discover --ansi \
    && php artisan key:generate --force --no-interaction \
    && rm -f .env

# Built Vite assets
COPY --from=assets /app/backend/public/build /var/www/html/backend/public/build

WORKDIR /var/www/html

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p \
        /var/www/html/data \
        /var/www/html/data/booking-uploads \
        /var/www/html/backend/storage/framework/cache \
        /var/www/html/backend/storage/framework/sessions \
        /var/www/html/backend/storage/framework/views \
        /var/www/html/backend/storage/logs \
        /var/www/html/backend/bootstrap/cache \
    && chown -R www-data:www-data \
        /var/www/html/data \
        /var/www/html/backend/storage \
        /var/www/html/backend/bootstrap/cache \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && sed -i 's/^listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf \
    && grep -q '^clear_env' /usr/local/etc/php-fpm.d/www.conf \
        && sed -i 's/^clear_env = .*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf \
        || echo 'clear_env = no' >> /usr/local/etc/php-fpm.d/www.conf

ENV DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/data/app.sqlite \
    APP_DATABASE_PATH=/var/www/html/data/app.sqlite

# Coolify: set "Ports Exposes" to this same port (default 80).
ENV PORT=80
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
  CMD curl -fsS "http://127.0.0.1:${PORT:-80}/" >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
