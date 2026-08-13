FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine
WORKDIR /var/www/html
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" intl mbstring opcache pdo_pgsql zip
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 9000
CMD ["php-fpm"]
