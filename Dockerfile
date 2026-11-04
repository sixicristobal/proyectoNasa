FROM php:8.5-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl pkg-config libcurl4-openssl-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libonig-dev libxml2-dev \
    libicu-dev libsqlite3-dev python3 python3-numpy python3-h5py \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
       pdo_mysql mbstring xml curl zip bcmath gd intl pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts

RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
