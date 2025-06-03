# Utiliser l'image officielle PHP avec Apache, version compatible avec votre application
FROM php:8.2-apache

# Installer les extensions requises
RUN apt-get update && apt-get install -y \
    iputils-ping \
    libfreetype6-dev \
    libzip-dev \
    netcat-openbsd \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Activer le mod_rewrite d'Apache pour les jolies URLs de Laravel
RUN a2enmod rewrite

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers de l'application dans l'image
COPY . /var/www/html/

# Installer les dépendances Composer
COPY composer.lock composer.json /var/www/html/
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-scripts --no-autoloader --no-dev \
    && composer dump-autoload --optimize \
    && php artisan optimize

# Changer la propriété du dossier à www-data
RUN chown -R www-data:www-data /var/www/html

# Exposer le port 80
EXPOSE 80

# Configuration Apache
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
    && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf