FROM php:8.2-apache

# Instalar dependencias del sistema requeridas por Composer (git, unzip, libzip) y extensiones PHP
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache para soportar .htaccess y rutas limpias de la API
RUN a2enmod rewrite

# Configurar el DocumentRoot de Apache para que apunte a /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copiar Composer desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar el código de la aplicación
WORKDIR /var/www/html
COPY . /var/www/html

# Instalar dependencias de producción de Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Asignar permisos al usuario www-data de Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
