# Brewsbook API — CodeIgniter 4 on PHP 8.2 Apache
FROM php:8.2-apache-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
  && apt-get install -y --no-install-recommends curl unzip libicu-dev \
  && docker-php-ext-install intl mysqli pdo_mysql \
  && a2enmod rewrite headers \
  && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
  && printf 'variables_order=EGPCS\n' > /usr/local/etc/php/conf.d/variables-order.ini \
  && printf 'date.timezone=UTC\n' > /usr/local/etc/php/conf.d/timezone.ini \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . /var/www/html
RUN composer dump-autoload --optimize --no-dev --no-interaction \
  && chmod +x /var/www/html/docker-entrypoint.sh \
  && chown -R www-data:www-data writable

COPY apache-health.conf /etc/apache2/conf-available/brewsbook.conf
RUN a2enconf brewsbook

EXPOSE 80
HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
  CMD curl -fsS http://127.0.0.1/health || exit 1

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
