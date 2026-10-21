#!/bin/bash
set -e

# Iniciar el servicio local de MariaDB
service mariadb start

# Esperar brevemente a que el socket de MariaDB responda
until mysqladmin ping --silent; do
    sleep 1
done

# Crear la base de datos e importar datos iniciales si no existen
mariadb -e "CREATE DATABASE IF NOT EXISTS misterstay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb misterstay < /var/www/html/database/reservas.sql 2>/dev/null || true

# Arrancar Apache en primer plano para mantener vivo el contenedor
exec apache2-foreground
