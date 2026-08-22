FROM php:8.2-fpm-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache nginx libpng-dev libzip-dev zip unzip oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath zip gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP packages
RUN composer install --no-dev --optimize-autoloader

# Set permissions for Laravel storage and cache
RUN chown -R www-data:www-data storage bootstrap/cache

# Copy Nginx config (or handle via startup script)
EXPOSE 80

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=80