# insureTech Skills LMS - production image (PHP 8.3 + Apache).
# Built on the VPS by deploy/remote-deploy.sh; nothing here is specific to one server.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --ignore-platform-reqs

FROM php:8.3-apache

COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd intl zip pgsql soap exif opcache apcu \
    && apt-get update && apt-get install -y --no-install-recommends ghostscript \
    && rm -rf /var/lib/apt/lists/*

COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-lms.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && printf '<Location "/">\n  FallbackResource /r.php\n</Location>\nServerTokens Prod\nServerSignature Off\n' \
       > /etc/apache2/conf-enabled/lms.conf \
    && a2enmod rewrite headers

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --chown=www-data:www-data deploy/config.php /var/www/html/config.php

RUN mkdir -p /var/www/lmsdata && chown www-data:www-data /var/www/lmsdata
VOLUME /var/www/lmsdata
