FROM php:8.4-fpm-alpine

RUN apk add --no-cache bash git supervisor su-exec fcgi icu-libs libzip libpq rabbitmq-c oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev rabbitmq-c-dev oniguruma-dev \
    && docker-php-ext-install -j$(nproc) intl zip bcmath pdo_pgsql pcntl mbstring \
    && pecl install redis amqp \
    && docker-php-ext-enable redis amqp \
    && apk del .build-deps

RUN git config --system --add safe.directory /semart

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /start.sh
COPY docker/opcache.ini /etc/opcache.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-semart.conf
RUN chmod +x /start.sh

WORKDIR /semart
EXPOSE 9000
CMD ["/start.sh"]
