# Main application (native PHP): Dashboard, Students, Reports, Settings, REST API.
# Built from the repository root:  docker build -f deploy/main.Dockerfile .
# On Railway: service variable RAILWAY_DOCKERFILE_PATH=deploy/main.Dockerfile
FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite headers

# Serve only public/; its .htaccess files route /api/... to the API.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

COPY backend /var/www/html/backend
COPY public /var/www/html/public
COPY deploy/start-apache.sh /usr/local/bin/start-apache
RUN chmod +x /usr/local/bin/start-apache

# Settings come from environment variables (DB_HOST, DB_PORT, DB_USERNAME,
# DB_PASSWORD, DB_DATABASE, LARAVEL_URL, APP_DEBUG); no .env file is needed.
CMD ["start-apache"]
