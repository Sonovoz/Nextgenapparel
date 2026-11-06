# syntax=docker/dockerfile:1.7
#
# Imagen de NextGen Apparel para el servidor (D25, D69).
#   target «app»: PHP-FPM (Alpine) con la aplicación (también para la cola y las tareas).
#   target «web»: Nginx con los archivos públicos.

FROM composer:2 AS composer

FROM php:8.4-fpm-alpine AS base

# Certificado extra opcional (solo para construir detrás de un proxy que lo
# exija). En el servidor no se usa.
RUN --mount=type=secret,id=ca_extra,required=false \
    if [ -s /run/secrets/ca_extra ]; then \
        cp /run/secrets/ca_extra /usr/local/share/ca-certificates/ca_extra.crt && update-ca-certificates; \
    fi

RUN apk add --no-cache \
        git unzip mariadb-client \
        icu-libs libzip libpng libjpeg-turbo freetype libwebp imagemagick imagemagick-libs \
    && apk add --no-cache --virtual .compilacion $PHPIZE_DEPS linux-headers \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev imagemagick-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip bcmath gd exif opcache pcntl \
    && curl -fsSL -o /tmp/redis.tgz https://pecl.php.net/get/redis-6.1.0.tgz \
    && curl -fsSL -o /tmp/imagick.tgz https://pecl.php.net/get/imagick-3.8.0.tgz \
    && pecl install /tmp/redis.tgz /tmp/imagick.tgz \
    && docker-php-ext-enable redis imagick \
    && apk del .compilacion \
    && rm -rf /tmp/pear /tmp/*.tgz

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-nextgenapparel.ini
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS app

COPY --chown=www-data:www-data . .

RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist \
    && rm -f .env \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --chmod=0755 docker/php/entrypoint.sh /usr/local/bin/entrypoint

USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
# Los archivos públicos subidos viven en el volumen de storage.
RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage
