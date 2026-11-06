# Brewsbook API stub — PHP 8.2 Apache
# Replace with real CodeIgniter app when source lands.
FROM php:8.2-apache-bookworm

RUN apt-get update \
  && apt-get install -y --no-install-recommends curl \
  && rm -rf /var/lib/apt/lists/* \
  && a2enmod rewrite headers \
  && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY public/ /var/www/html/public/
COPY apache-health.conf /etc/apache2/conf-available/brewsbook.conf
RUN a2enconf brewsbook

EXPOSE 80
HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
  CMD curl -fsS http://127.0.0.1/health || exit 1
