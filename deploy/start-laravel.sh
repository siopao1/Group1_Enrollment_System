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
php artisan migrate --force || echo "WARNING: migrations failed - start the main app first (it creates the main tables), then redeploy."

# Demo data (subjects, sections, students, enrollments) when RUN_SEEDERS=1.
# The seeders only add what is missing, so running them again is safe.
if [ "${RUN_SEEDERS:-0}" = "1" ]; then
    php artisan db:seed --force || echo "WARNING: seeding failed"
fi

chown -R www-data:www-data storage bootstrap/cache
exec start-apache
