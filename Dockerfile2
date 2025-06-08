# docker/production/Dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    iputils-ping \
    libfreetype6-dev \
    libzip-dev zip unzip curl git \
    libpng-dev libonig-dev libxml2-dev \
    netcat-openbsd \
    && docker-php-ext-install pdo pdo_mysql zip \
    && a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy app files
COPY . .

# Install Composer and dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && composer install --optimize-autoloader --no-dev \
    && composer dump-autoload --optimize \
    && php artisan optimize

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80

# Configuration Apache
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Lance Apache
CMD ["apache2-foreground"]
