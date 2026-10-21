<?php
declare(strict_types=1);

class Database
{
  public static function connect(): PDO
  {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'misterstay';

    $isLinux = PHP_OS_FAMILY === 'Linux';
    $envHost = getenv('DB_HOST');
    $envUser = getenv('DB_USER');

    // En el contenedor Docker (Linux), si el host es local, forzar siempre el usuario 'misterstay'
    // para evitar el bloqueo del plugin unix_socket que Debian aplica sobre 'root'
    if ($isLinux && (!$envHost || $envHost === '127.0.0.1' || $envHost === 'localhost')) {
      $username = ($envUser && $envUser !== 'root') ? $envUser : 'misterstay';
      $password = (getenv('DB_PASSWORD') !== false && getenv('DB_PASSWORD') !== '') ? getenv('DB_PASSWORD') : 'misterstay123';
      
      $dsn = file_exists('/var/run/mysqld/mysqld.sock')
        ? "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$database;charset=utf8mb4"
        : "mysql:host=127.0.0.1;port=$port;dbname=$database;charset=utf8mb4";
    } else {
      // Entorno Windows (XAMPP local) o base de datos externa explícita
      $username = $envUser ?: 'root';
      $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
      $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    }

    $opciones = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($host !== '127.0.0.1' && $host !== 'localhost') {
      $opciones[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    return new PDO($dsn, $username, $password, $opciones);
  }
}
