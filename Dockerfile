FROM php:8.0.11-alpine3.14
ENV TIMEZONE=America/Los_Angeles
ENV COMPOSER_DISABLE_XDEBUG_WARN=1

SHELL ["/bin/ash", "-exo", "pipefail", "-c"]

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && sed -i -E 's/^expose_php = On/expose_php = Off/' $PHP_INI_DIR/php.ini-production \
    && sed -i -E "s|^.?date.timezone =.*|date.timezone = ${TIMEZONE}|g" $PHP_INI_DIR/php.ini* \
    && apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
    && echo $TIMEZONE >  /etc/timezone \
    && apk add --no-cache git \
    && apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug-3.1.1 \
    && docker-php-ext-enable xdebug \
    && apk del --purge $PHPIZE_DEPS \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app
COPY . /app

CMD ["php", "-a"]