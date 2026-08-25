FROM php:8.3-fpm AS base

ARG WWWUSER=1000
ARG WWWGROUP=1000

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        gnupg \
        chromium \
        fonts-liberation \
        libnss3 \
    && curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# https://github.com/mlocati/docker-php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        redis \
        zip \
        gd \
        bcmath \
        intl \
        opcache \
        pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupmod -o -g "${WWWGROUP}" www-data \
    && usermod -o -u "${WWWUSER}" -g "${WWWGROUP}" www-data

WORKDIR /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
