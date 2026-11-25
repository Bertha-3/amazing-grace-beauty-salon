#!/bin/sh
set -eu

port="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork
apache2ctl -M >/dev/null

mkdir -p /var/www/html/uploads
if [ ! -f /var/www/html/uploads/.htaccess ]; then
    cp /var/www/html/deploy/uploads.htaccess /var/www/html/uploads/.htaccess
fi
chown www-data:www-data /var/www/html/uploads

exec apache2-foreground
