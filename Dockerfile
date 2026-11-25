FROM php:8.3-apache-bookworm

RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers rewrite \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/salon.conf \
    && a2enconf salon \
    && printf 'upload_max_filesize=6M\npost_max_size=8M\n' > /usr/local/etc/php/conf.d/salon.ini

WORKDIR /var/www/html
COPY . /var/www/html/
COPY deploy/render-entrypoint.sh /usr/local/bin/render-entrypoint
RUN chmod 755 /usr/local/bin/render-entrypoint

EXPOSE 10000
ENTRYPOINT ["render-entrypoint"]
