FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json webpack.config.js ./
COPY assets ./assets
RUN npm ci
RUN npm run build

FROM php:8.5-cli AS vendor
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

FROM php:8.5-apache

ENV APACHE_DOCUMENT_ROOT=/app/public
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    libzip-dev \
    libsqlite3-dev \
    pkg-config \
    unzip \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite intl \
    && a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /app/var/data /app/var/log /app/var/cache \
    && chown -R www-data:www-data /app/var

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
