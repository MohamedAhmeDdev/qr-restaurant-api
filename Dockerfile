FROM php:8.2-fpm-alpine

# Install system dependencies & PHP extensions required for Composer & Laravel
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    icui18n \
    icu-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath zip gd intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy manifest files first to leverage Docker layer caching
COPY composer.json composer.lock ./

# Pass COMPOSER_ALLOW_SUPERUSER and disable memory limit during install
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

# Copy the rest of the application files
COPY . .

# Finish autoloader and run scripts now that all files are copied
RUN composer dump-autoload --optimize

# Set permissions for Laravel storage and cache
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=80