FROM php:8.2-apache

# Cài extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pgsql mysqli pdo pdo_mysql

# Bật rewrite nếu dùng .htaccess
RUN a2enmod rewrite

# Cho phép Apache dùng .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy source
COPY . /var/www/html/

# Cấp quyền
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80

