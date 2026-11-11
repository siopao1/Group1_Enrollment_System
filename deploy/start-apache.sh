#!/bin/sh
# Starts Apache on the port the hosting service provides ($PORT, default 80).
set -e
PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
# Apache may load only one MPM, and mod_php needs prefork. Some build
# environments leave mpm_event enabled too ("More than one MPM loaded").
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then
    a2enmod mpm_prefork >/dev/null
fi
exec apache2-foreground
