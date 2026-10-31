FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    sqlite \
    sqlite-dev \
    curl \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath zip intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .

RUN sed -i 's/\r$//' /var/www/html/docker-entrypoint.sh && chmod +x /var/www/html/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
