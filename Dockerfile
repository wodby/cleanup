FROM php:7-alpine

RUN apk add --no-cache bash curl

COPY composer.json composer.lock ./

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    composer install -n

COPY cleanup.php ./

ENTRYPOINT ["php", "cleanup.php"]
