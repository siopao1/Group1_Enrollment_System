#!/bin/sh
# Prepares Laravel with the settings of this environment, creates any missing
# tables, then starts Apache.
set -e
cd /var/www/html

php artisan config:cache
php artisan route:cache
php artisan view:cache

# The module's tables refer to the main app's tables (students, users), so the
# main schema must be imported first. A failure here is logged but does not
# stop the site from starting.
php artisan migrate --force || echo "WARNING: migrations failed - import backend/database/schema.sql first, then redeploy."

chown -R www-data:www-data storage bootstrap/cache
exec start-apache
