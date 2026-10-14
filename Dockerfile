FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-libs libzip oniguruma libxml2 sqlite-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev libxml2-dev sqlite-dev \
    && docker-php-ext-install intl mbstring pdo_sqlite zip dom xml \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod +x bin/render-demo-start

USER www-data
EXPOSE 10000
CMD ["sh", "bin/render-demo-start"]
