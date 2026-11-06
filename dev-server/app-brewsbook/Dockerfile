# Mirrors /opt/dev-brewsbook/app-brewsbook/Dockerfile
# DocumentRoot aligned to /var/www/html/web (matches runtime 000-default.conf + live content).
FROM php:8.3.36-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install intl mysqli pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . /var/www/html

RUN chown -R www-data:www-data web

EXPOSE 80
