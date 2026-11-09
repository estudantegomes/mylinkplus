FROM php:8.3.4-fpm

RUN apt-get update \
    && apt-get install -y libpq-dev libzip-dev zip libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql zip gd mbstring \
    && mkdir -p /var/lib/mylink/profile /var/lib/mylink/profile-testing \
    && chown -R www-data:www-data /var/lib/mylink \
    && chmod 2770 /var/lib/mylink/profile /var/lib/mylink/profile-testing \
    && rm -rf /var/lib/apt/lists/*
