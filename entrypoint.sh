#!/bin/bash
set -e

# Asegurar directorios y permisos para MariaDB
mkdir -p /var/run/mysqld
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
chmod 777 /var/run/mysqld

# Iniciar el servicio local de MariaDB
service mariadb start

# Esperar a que el servicio esté listo
for i in {1..30}; do
    if mysqladmin ping --silent; then
        break
    fi
    sleep 1
done

# Crear la base de datos y un usuario específico con contraseña para la app
mariadb -u root <<EOF
CREATE DATABASE IF NOT EXISTS misterstay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'misterstay'@'localhost' IDENTIFIED BY 'misterstay123';
CREATE USER IF NOT EXISTS 'misterstay'@'127.0.0.1' IDENTIFIED BY 'misterstay123';
GRANT ALL PRIVILEGES ON misterstay.* TO 'misterstay'@'localhost';
GRANT ALL PRIVILEGES ON misterstay.* TO 'misterstay'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF

# Importar tablas y datos de ejemplo
mariadb -u root misterstay < /var/www/html/database/reservas.sql 2>/dev/null || true

# Arrancar Apache en primer plano
exec apache2-foreground
