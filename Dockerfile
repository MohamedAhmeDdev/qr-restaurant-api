FROM php:8.4-fpm-alpine

# 1. Install system dependencies & build packages
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    icu-libs \
    postgresql-dev \
    $PHPIZE_DEPS

# 2. Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql bcmath zip gd intl \
    && pecl install redis \
    && docker-php-ext-enable redis

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 4. Copy composer files
COPY composer.json composer.lock ./

ENV COMPOSER_ALLOW_SUPERUSER=1

# 5. Install dependencies (lock file now matches PHP 8.4)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# 6. Copy application
COPY . .

# 7. Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=80