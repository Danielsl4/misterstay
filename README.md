# MisterStay — Sistema de Gestión de Reservas de Alojamiento

Aplicación web desarrollada en **PHP nativo (sin frameworks)**, vistas con motor de plantillas **Twig**, persistencia en **MySQL/MariaDB** e interfaz interactiva con **Vanilla JavaScript** consumiendo una API REST propia.

Prueba técnica para desarrollador/a PHP — MisterPlan.

---

## 🚀 Despliegue en Producción

- **URL de la aplicación en vivo**: `[Inserta aquí la URL de tu despliegue, ej. https://misterstay.tu-hosting.com]`

---

## 🛠️ Tecnologías y Requisitos

- **PHP**: 8.1 o superior (probado en PHP 8.2).
- **Base de Datos**: MySQL 5.7+ o MariaDB 10.4+.
- **Extensiones PHP**: `pdo`, `pdo_mysql`, `mbstring`, `json`.
- **Gestor de dependencias**: Composer (para Twig y PHPUnit).
- **Servidor Web**: Apache (con módulo `mod_rewrite` habilitado) o el servidor embebido de PHP.

---

## 💻 Instalación y Puesta en Marcha en Local

### 1. Clonar el repositorio
```bash
git clone https://github.com/tu-usuario/misterstay.git
cd misterstay
```

### 2. Instalar dependencias de Composer
```bash
composer install
```

### 3. Crear y poblar la Base de Datos
El proyecto incluye el fichero DDL con tablas y datos de prueba en `database/reservas.sql`.

1. Abre tu gestor MySQL (phpMyAdmin, DBeaver, HeidiSQL o consola):
   ```sql
   CREATE DATABASE misterstay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Importa el fichero `database/reservas.sql`:
   ```bash
   mysql -u root -p misterstay < database/reservas.sql
   ```
   *(Si utilizas phpMyAdmin en XAMPP, accede a `http://localhost/phpmyadmin`, crea la base de datos `misterstay` y usa la pestaña **Importar** seleccionando `database/reservas.sql`)*.

### 4. Configurar conexión a la base de datos
Si tus credenciales de MySQL difieren de las por defecto de XAMPP (`root` sin contraseña en `127.0.0.1`), ajusta `src/Database.php`:
```php
$host = '127.0.0.1';
$database = 'misterstay';
$username = 'root';
$password = '';
```

### 5. Iniciar la aplicación

- **Opción A (Recomendada con XAMPP)**:
  Coloca la carpeta en `C:/xampp/htdocs/misterstay` e inicia Apache y MySQL desde el XAMPP Control Panel.
  Accede en tu navegador a:
  ```
  http://localhost/misterstay/public/reservas.php
  ```

- **Opción B (Servidor integrado de PHP)**:
  Ejecuta desde la raíz del proyecto:
  ```bash
  php -S 127.0.0.1:8000 -t public
  ```
  Accede a: `http://127.0.0.1:8000/reservas.php`.

---

## 🧪 Ejecución de Tests Unitarios

La suite de pruebas unitarias está implementada con **PHPUnit 10** en la carpeta `tests/`. Verifica las reglas de negocio y validación de `ReservaService` de forma aislada mediante dobles de prueba (mocks):

```bash
.\vendor\bin\phpunit
```

---

## 📐 Decisiones de Arquitectura y Diseño

Se ha seguido una arquitectura por capas sin framework, aplicando el Principio de Responsabilidad Única (SRP) y separación estricta de responsabilidades:

1. **`Database` (`src/Database.php`)**:
   - Centraliza la creación de la conexión PDO.
   - Configura `PDO::ERRMODE_EXCEPTION` para fallos inmediatos y seguros.
   - Deshabilita la emulación de sentencias preparadas (`PDO::ATTR_EMULATE_PREPARES => false`) para garantizar prepared statements reales en el motor de base de datos.

2. **`ReservaRepository` (`src/ReservaRepository.php`)**:
   - Encapsula exclusivamente el acceso a datos y sentencias SQL.
   - Utiliza **transacciones ACID** (`beginTransaction`, `commit`, `rollBack`) tanto en la creación como en el cambio de estado.
   - En el cambio de estado implementa **bloqueo pesimista** (`SELECT ... FOR UPDATE`) para evitar condiciones de carrera si dos peticiones intentan cancelar/confirmar la misma reserva concurrentemente.
   - Registra de forma atómica cada cambio en la tabla de auditoría `reserva_evento`.

3. **`ReservaService` (`src/ReservaService.php`)**:
   - Contiene la lógica de dominio y validación de entrada antes de llegar a la base de datos.
   - Valida coherencia de fechas (`fecha_salida > fecha_entrada`), formato estricto de fechas `Y-m-d`, validación RFC de correo electrónico, rangos de importes y longitudes de texto.
   - Al no depender de superglobales (`$_GET`, `$_POST`), es 100% testeable unitariamente sin base de datos.

4. **`ReservaController` (`src/ReservaController.php`)**:
   - Punto de entrada HTTP de la API REST.
   - Gestiona códigos de estado coherentes:
     - `200 OK` para consultas y actualizaciones exitosas.
     - `201 Created` al registrar una reserva.
     - `400 Bad Request` ante errores de validación de datos.
     - `404 Not Found` si no existe la reserva o endpoint.
     - `409 Conflict` ante reglas de dominio violadas (ej. intentar cambiar el estado de una reserva cancelada).
     - `500 Internal Server Error` ante fallos imprevistos, registrando el trace en logs sin exponer detalles sensibles al cliente.

5. **Por qué MySQL/MariaDB**:
   - En un core de reservas de alojamiento, la integridad referencial y las transacciones ACID son críticas: no puede registrarse una reserva sin su evento correspondiente ni quedar estados inconsistentes.
   - Los índices compuestos (`idx_reserva_fechas`, `idx_reserva_estado`) y restricciones a nivel de motor (`CHECK (fecha_salida > fecha_entrada)`) garantizan consistencia incluso ante consultas concurrentes.

---

## 🖥️ Vistas Twig y Frontend Interactivo

- **Twig**: Se utiliza herencia de plantillas (`base.twig` -> `listado.twig`, `detalle.twig`, `error.twig`) con autoescaping activo por defecto. Formato de fechas y moneda localizado mediante filtros nativos (`|date`, `|number_format`).
- **JavaScript (Vanilla)**:
  - **Filtrado reactivo**: Escucha los filtros de estado, fechas y texto del huésped (con función *debounce* de 300ms) y consulta `GET /api/reservas` repintando el DOM sin recargar la página.
  - **Creación en modal**: Validación client-side previa a la llamada `POST /api/reservas` y pintado de errores devueltos por el servidor.
  - **Cancelación y confirmación**: Confirmación modal antes de enviar `PATCH /api/reservas/{id}/estado`.
  - **Feedback visual**: Spinners de carga durante peticiones asíncronas y sistema de notificaciones toast para informar al usuario de cualquier incidencia o éxito.

---

## 🔮 Qué haríamos diferente con más tiempo

1. **Paginación en la API y vistas**: Actualmente `GET /api/reservas` retorna todas las coincidencias. Con miles de reservas se implementaría paginación por cursor o por límite/desplazamiento (`?page=1&limit=20`) con metadatos de total.
2. **Autenticación y Autorización**: Proteger la API mediante tokens JWT o sesiones seguras, permitiendo diferentes roles (ej. solo administradores pueden cancelar).
3. **DTOs / Request Objects**: Crear clases fuertemente tipadas (ej. `CreateReservaDTO`) para deserializar y validar el payload de la petición con mayor expresividad.
4. **Validación de disponibilidad en calendario**: Comprobar solapamiento de fechas para que una misma habitación/alojamiento no se reserve dos veces en el mismo intervalo.

---

## 📈 Escalabilidad: Si recibiera 1.000 reservas/hora

Un volumen de 1.000 reservas/hora equivale a ~0.3 reservas/segundo de media con picos de 5–10 escrituras/segundo y cientos de consultas de lectura por segundo. Para asegurar alta disponibilidad y rendimiento:

1. **Separación de Lecturas y Escrituras (Read Replicas)**:
   - Configurar replicación primaria-secundaria en MySQL. Las búsquedas y filtros del panel leen de réplicas de lectura, liberando al nodo primario exclusivamente para `INSERT` y `UPDATE` transaccionales.
2. **Caché con Redis**:
   - Cachear el catálogo de alojamientos y la disponibilidad de fechas en Redis con invalidación orientada a eventos.
3. **Procesamiento Asíncrono de Tareas**:
   - Acciones complementarias a la reserva (envío de emails de confirmación al huésped, notificaciones al hotelero, analítica) no deben bloquear la respuesta HTTP. Se publicarían en una cola de mensajes (RabbitMQ o Redis Streams) procesada por workers en segundo plano.
4. **Control de Concurrencia**:
   - Para prevenir *overbooking* ante alta concurrencia de reservas simultáneas para el mismo alojamiento, usar bloqueo pesimista a nivel de rango de fechas o bloqueos distribuidos con Redis (*Redlock*).
5. **Infraestructura y Contenedores**:
   - Empaquetar la aplicación en contenedores Docker y desplegarla tras un balanceador de carga (Nginx / AWS ALB) con auto-escalado horizontal de pods PHP-FPM según uso de CPU/memoria.
