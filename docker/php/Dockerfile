FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev libbrotli-dev unzip git procps \
    && docker-php-ext-install pdo pdo_pgsql zip pcntl

RUN pecl install swoole redis && docker-php-ext-enable swoole redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY backend/ .
RUN composer install --no-interaction --optimize-autoloader

EXPOSE 8000
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]