FROM php:8.5-apache-trixie

ENV APACHE_DOCUMENT_ROOT=/var/www/www
COPY . /var/www
WORKDIR /var/www
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

RUN a2enmod rewrite
RUN docker-php-ext-install pdo pdo_mysql
RUN curl -sS https://getcomposer.org/installer | php \
        ; mv composer.phar /usr/local/bin/ \
        ; ln -s /usr/local/bin/composer.phar /usr/local/bin/composer
RUN apt-get update && apt-get install -y git unzip
RUN composer install --prefer-source --no-interaction
RUN rm -rf temp/cache/* \
        && mkdir -p temp/cache log www/uploads \
        && chown -R www-data:www-data temp log www/uploads \
        && chmod -R u+rwX,g+rwX temp log www/uploads
COPY docker/app-entrypoint.sh /usr/local/bin/lumio-entrypoint
RUN chmod +x /usr/local/bin/lumio-entrypoint
ENTRYPOINT ["/usr/local/bin/lumio-entrypoint"]
CMD ["apache2-foreground"]
