
USE misterstay;

CREATE TABLE reserva (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_huesped VARCHAR(120) NOT NULL,
    email_huesped VARCHAR(254) NOT NULL,
    nombre_alojamiento VARCHAR(150) NOT NULL,
    fecha_entrada DATE NOT NULL,
    fecha_salida DATE NOT NULL,
    estado ENUM('pendiente', 'confirmada', 'cancelada')
        NOT NULL DEFAULT 'pendiente',
    importe DECIMAL(10, 2) NOT NULL,
    notas TEXT NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_fechas_reserva
        CHECK (fecha_salida > fecha_entrada),

    CONSTRAINT chk_importe_reserva
        CHECK (importe >= 0),

    INDEX idx_reserva_estado (estado),
    INDEX idx_reserva_fechas (fecha_entrada, fecha_salida),
    INDEX idx_reserva_huesped (nombre_huesped)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reserva_evento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_evento_reserva
        FOREIGN KEY (reserva_id)
        REFERENCES reserva(id)
        ON DELETE RESTRICT,

    INDEX idx_evento_reserva_fecha (reserva_id, fecha_creacion)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT INTO reserva (
    nombre_huesped, email_huesped, nombre_alojamiento,
    fecha_entrada, fecha_salida, estado, importe, notas
) VALUES
(
    'Ana García', 'ana@example.com', 'Hotel Plaza',
    '2026-11-05', '2026-11-08', 'confirmada', 285.00,
    'Habitación doble'
),
(
    'Carlos Martín', 'carlos@example.com', 'Apartamentos Sol',
    '2026-11-12', '2026-11-15', 'pendiente', 210.50,
    'Llegada por la tarde'
),
(
    'Lucía Fernández', 'lucia@example.com', 'Casa Rural El Olivo',
    '2026-12-01', '2026-12-04', 'cancelada', 320.00,
    NULL
),
(
    'Javier López', 'javier@example.com', 'Hotel Plaza',
    '2026-12-20', '2026-12-23', 'confirmada', 450.00,
    'Cama supletoria'
),
(
    'Ana Martínez', 'ana.martinez@example.com', 'Hostal Central',
    '2027-01-10', '2027-01-12', 'pendiente', 120.00,
    NULL
);

INSERT INTO reserva_evento (
    reserva_id, tipo, descripcion
) VALUES
(1, 'creacion', 'Reserva creada'),
(1, 'cambio_estado', 'Reserva confirmada'),
(2, 'creacion', 'Reserva creada'),
(3, 'creacion', 'Reserva creada'),
(3, 'cambio_estado', 'Reserva cancelada'),
(4, 'creacion', 'Reserva creada'),
(4, 'cambio_estado', 'Reserva confirmada'),
(5, 'creacion', 'Reserva creada');
