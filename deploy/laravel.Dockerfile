# Laravel enrollment module (enrollment-module/).
# Built from the repository root:  docker build -f deploy/laravel.Dockerfile .
# On Railway: service variable RAILWAY_DOCKERFILE_PATH=deploy/laravel.Dockerfile

# 1. Install the PHP libraries with Composer.
FROM composer:2 AS vendor
WORKDIR /app
COPY enrollment-module/composer.json enrollment-module/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY enrollment-module/ ./
RUN composer dump-autoload --optimize --no-dev --no-scripts

# 2. Run it with PHP 8.2 and Apache.
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

COPY --from=vendor /app /var/www/html
RUN mkdir -p /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views /var/www/html/storage/logs /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY deploy/start-apache.sh /usr/local/bin/start-apache
COPY deploy/start-laravel.sh /usr/local/bin/start-laravel
COPY deploy/repair-migrations.php /usr/local/lib/repair-migrations.php
RUN chmod +x /usr/local/bin/start-apache /usr/local/bin/start-laravel

# Settings come from environment variables (APP_KEY, APP_URL, MAIN_APP_URL,
# DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, ...).
CMD ["start-laravel"]
