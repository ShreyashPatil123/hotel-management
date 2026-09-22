FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
