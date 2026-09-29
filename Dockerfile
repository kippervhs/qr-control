FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json tsconfig.json vite.config.ts postcss.config.mjs ./
COPY frontend ./frontend
RUN npm ci && npm run build

FROM composer:2 AS php-dependencies
WORKDIR /app/backend
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libzip-dev \
    && docker-php-ext-install pdo_mysql gd zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/app/backend/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
WORKDIR /var/www/app
COPY backend ./backend
COPY database ./database
COPY --from=php-dependencies /app/backend/vendor ./backend/vendor
COPY --from=frontend /app/backend/public/app ./backend/public/app
RUN chown -R www-data:www-data /var/www/app
EXPOSE 80
