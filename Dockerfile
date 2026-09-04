FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM node:22-alpine AS assets
WORKDIR /app
ARG VITE_APP_NAME
ARG VITE_BASE_PATH
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ARG VITE_FIREBASE_API_KEY
ARG VITE_FIREBASE_AUTH_DOMAIN
ARG VITE_FIREBASE_PROJECT_ID
ARG VITE_FIREBASE_MESSAGING_SENDER_ID
ARG VITE_FIREBASE_APP_ID
ARG VITE_FIREBASE_VAPID_KEY
ARG VITE_VAPID_PUBLIC_KEY
ARG VITE_GOOGLE_MAPS_API_KEY
ENV VITE_APP_NAME=$VITE_APP_NAME VITE_BASE_PATH=$VITE_BASE_PATH VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY VITE_REVERB_HOST=$VITE_REVERB_HOST VITE_REVERB_PORT=$VITE_REVERB_PORT VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME VITE_FIREBASE_API_KEY=$VITE_FIREBASE_API_KEY VITE_FIREBASE_AUTH_DOMAIN=$VITE_FIREBASE_AUTH_DOMAIN VITE_FIREBASE_PROJECT_ID=$VITE_FIREBASE_PROJECT_ID VITE_FIREBASE_MESSAGING_SENDER_ID=$VITE_FIREBASE_MESSAGING_SENDER_ID VITE_FIREBASE_APP_ID=$VITE_FIREBASE_APP_ID VITE_FIREBASE_VAPID_KEY=$VITE_FIREBASE_VAPID_KEY VITE_VAPID_PUBLIC_KEY=$VITE_VAPID_PUBLIC_KEY VITE_GOOGLE_MAPS_API_KEY=$VITE_GOOGLE_MAPS_API_KEY
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine
WORKDIR /var/www/html
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" intl mbstring opcache pcntl pdo_pgsql zip
RUN docker-php-ext-install -j1 bcmath
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 9000
CMD ["php-fpm"]
