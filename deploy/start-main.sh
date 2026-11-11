#!/bin/sh
# Main app container start: prepare the database (first start only), make sure
# the demo accounts exist, then start Apache. Database problems are logged but
# do not stop the site from starting.
php /usr/local/lib/init-db.php || echo "WARNING: database initialisation failed - check DB_* variables"
php /var/www/html/backend/database/seed_users.php || echo "WARNING: could not create the demo accounts"
exec start-apache
