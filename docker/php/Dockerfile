FROM php:8.4-fpm-alpine

# Rendszer csomagok
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    mysql-client \
    bash \
    autoconf \
    g++ \
    make \
    linux-headers

# PHP kiterjesztések
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        intl \
        zip \
        xml \
        opcache

# Composer telepítése
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Munkakönyvtár
WORKDIR /var/www/html

# PHP konfiguráció
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "upload_max_filesize=64M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=120" >> /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 9000
CMD ["php-fpm"]
