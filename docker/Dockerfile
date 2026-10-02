ARG PHP=8.5
FROM php:${PHP}-cli-alpine

RUN apk update && apk add \
    zip libzip-dev icu-dev git postgresql-dev

# Graphviz and a font so `make deptrac-graph` can render build/deptrac.png.
RUN apk add --no-cache graphviz font-inter

RUN docker-php-ext-install zip intl pdo_mysql pdo_pgsql

RUN apk add --no-cache linux-headers autoconf build-base
RUN pecl install xdebug redis
RUN docker-php-ext-enable xdebug redis
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENTRYPOINT ["php"]
