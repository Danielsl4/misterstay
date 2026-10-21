FROM php:8.2-apache

# Instalar dependencias del sistema, extensiones PHP y servidor MariaDB
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    mariadb-server \
    && docker-php-ext-install zip pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configuración optimizada de bajo consumo de memoria para MariaDB (ideal para el plan Free de Render)
RUN echo "[mysqld]\nperformance_schema = off\ninnodb_buffer_pool_size = 32M\nkey_buffer_size = 16M" >> /etc/mysql/mariadb.conf.d/50-server.cnf

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

# Copiar script de arranque, corregir posibles saltos CRLF de Windows y dar permisos
COPY entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh \
    && chmod +x /entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
