FROM php:8.3.4-fpm

RUN apt-get update \
    && apt-get install -y libpq-dev libzip-dev zip \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*