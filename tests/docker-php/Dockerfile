# Run ./run-tests.sh from the main folder.
ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache git curl unzip

WORKDIR /app
COPY . /app

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install

CMD ["vendor/bin/phpunit"]
