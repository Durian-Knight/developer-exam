# One image = Apache 2.4 + PHP 8.3 serving both the Laravel API (/api) and the built Vue SPA (/).

# ---- 1. Build the SPA -------------------------------------------------------
FROM node:22-alpine AS spa
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ .
ARG VITE_API_URL=/api
ENV VITE_API_URL=${VITE_API_URL}
RUN npm run build

# ---- 2. Install PHP dependencies -------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY backend/ .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- 3. Runtime ------------------------------------------------------------
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --from=vendor /app /var/www/backend
COPY --from=spa /app/dist /var/www/spa
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data /var/www/backend/storage /var/www/backend/bootstrap/cache

WORKDIR /var/www/backend
EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
