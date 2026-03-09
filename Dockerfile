FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql && docker-php-ext-enable pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy project files into container
COPY . /var/www/html/

# Set permissions for the uploads directory
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 755 /var/www/html/uploads

EXPOSE 80
