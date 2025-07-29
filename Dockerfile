# Sử dụng image PHP có Apache
FROM php:8.2-apache

# Cài đặt các extension PHP cần thiết
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy toàn bộ mã nguồn vào thư mục chứa mã nguồn web của Apache
COPY . /var/www/html/

# Mở cổng 80 để server hoạt động
EXPOSE 80
