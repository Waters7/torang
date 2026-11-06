# syntax=docker/dockerfile:1

# Dependensi PHP tanpa skrip: artisan baru bisa dijalankan setelah seluruh kode tersalin.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# Aset frontend. Tema Filament mengimpor CSS dari vendor dan memindai app/ serta resources/views.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# Aplikasi: PHP-FPM, juga dipakai worker queue dan scheduler.
FROM php:8.4-fpm-bookworm AS app

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ping untuk monitoring, mariadb-client untuk backup, LibreOffice + font untuk PDF laporan UKOM.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        iputils-ping mariadb-client libreoffice-writer fonts-liberation fonts-crosextra-carlito \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions bcmath gd intl opcache pcntl pdo_mysql zip

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-torang.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-torang.conf
COPY docker/entrypoint.sh /usr/local/bin/torang-entrypoint

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN chmod +x /usr/local/bin/torang-entrypoint \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/fonts storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-scripts --no-interaction \
    && php artisan package:discover --ansi \
    && php artisan filament:assets --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["torang-entrypoint"]
CMD ["php-fpm"]

# Server web: berkas statis; PHP diteruskan ke kontainer app. HTTPS ditangani Nginx host.
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
