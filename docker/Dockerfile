ARG PHP=8.5
FROM php:${PHP}-cli-alpine

RUN apk update && apk add \
    zip libzip-dev icu-dev git postgresql-dev

# Graphviz and a font so `make deptrac-graph` can render build/deptrac.png.
RUN apk add --no-cache graphviz font-inter

RUN docker-php-ext-install zip intl pdo_mysql pdo_pgsql

RUN apk add --no-cache linux-headers autoconf build-base
RUN pecl install pcov redis
RUN docker-php-ext-enable pcov redis

COPY opcache.ini /usr/local/etc/php/conf.d/opcache-cli.ini
COPY pcov.ini /usr/local/etc/php/conf.d/pcov.ini
RUN mkdir -p /var/cache/opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["php"]
