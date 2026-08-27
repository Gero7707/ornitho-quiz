
FROM  php:8.3.23-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN pecl install mongodb && docker-php-ext-enable mongodb


RUN a2enmod rewrite


COPY apache.conf /etc/apache2/sites-available/000-default.conf


WORKDIR /var/www/html


COPY . .


RUN chown -R www-data:www-data /var/www/html