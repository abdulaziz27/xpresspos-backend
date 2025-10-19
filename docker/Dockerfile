# syntax=docker/dockerfile:1.7

FROM ghcr.io/roadrunner-server/roadrunner:2024.2 AS rr

FROM composer:2.7 AS vendor
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    --ignore-platform-req=ext-intl --ignore-platform-req=ext-gd
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    --ignore-platform-req=ext-intl --ignore-platform-req=ext-gd

FROM node:20 AS assets
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-cli AS runtime
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev zip libicu-dev g++ libpng-dev libjpeg62-turbo-dev libfreetype6-dev curl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl pcntl bcmath zip pdo_mysql opcache \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=rr /usr/bin/rr /usr/local/bin/rr
WORKDIR /var/www/html
COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /var/www/html/public/build /var/www/html/public/build
COPY .rr.yaml ./
RUN chown -R www-data:www-data storage bootstrap/cache
ENV APP_ENV=production \
    APP_DEBUG=false \
    OCTANE_SERVER=roadrunner \
    RR_NUM_WORKERS=4 \
    RR_MAX_MEMORY_MB=256 \
    RR_HTTP_MAX_REQUESTS=0
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD curl -fsS http://127.0.0.1:8080/healthz || exit 1
USER www-data
CMD ["rr", "serve", "-c", "/var/www/html/.rr.yaml"]
