FROM axllent/mailpit:v1.31.5 AS mailpit
FROM php:8.5.11-apache-trixie

# Extensions PHP natives pour PostgreSQL et les images JPEG/PNG.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Transport local de mail() vers Mailpit.
COPY --from=mailpit /mailpit /usr/local/bin/mailpit
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/camagru.ini

WORKDIR /var/www/html
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY tests/ tests/
COPY --chown=www-data:www-data storage/ storage/

RUN printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername
