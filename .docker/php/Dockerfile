# PHP for developing the bundle: the extensions Sulu needs, both database drivers and Composer.
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions @composer apcu exif gd intl pdo_mysql pdo_pgsql zip

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN git config --system --add safe.directory '*'

WORKDIR /app
