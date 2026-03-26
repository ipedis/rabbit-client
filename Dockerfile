FROM php:8.2-cli-alpine

RUN apk add --no-cache git unzip libxml2-dev oniguruma-dev rabbitmq-c-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install dom xml mbstring sockets \
    && pecl install amqp && docker-php-ext-enable amqp \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /app
