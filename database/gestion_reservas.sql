-- Gestion de reservas: base de datos y datos de ejemplo para pruebas.

CREATE DATABASE IF NOT EXISTS `gestion_reservas` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestion_reservas`;

DROP TABLE IF EXISTS `reserva_evento`;
DROP TABLE IF EXISTS `reserva`;

CREATE TABLE `reserva` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_huesped` VARCHAR(150) NOT NULL,
  `email_huesped` VARCHAR(150) NOT NULL,
  `nombre_alojamiento` VARCHAR(150) NOT NULL,
  `fecha_entrada` DATE NOT NULL,
  `fecha_salida` DATE NOT NULL,
  `estado` ENUM('pendiente', 'confirmada', 'cancelada') NOT NULL DEFAULT 'pendiente',
  `importe` DECIMAL(10, 2) NOT NULL,
  `notas` TEXT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_estado` (`estado`),
  INDEX `idx_fechas` (`fecha_entrada`, `fecha_salida`),
  INDEX `idx_huesped` (`nombre_huesped`),
  CONSTRAINT `chk_fechas`  CHECK (`fecha_salida` > `fecha_entrada`),
  CONSTRAINT `chk_importe` CHECK (`importe` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reserva_evento` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reserva_id` INT NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `descripcion` TEXT NOT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reserva_fecha` (`reserva_id`, `fecha_creacion`),
  CONSTRAINT `fk_reserva_evento_reserva`
    FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de ejemplo: 5 confirmadas, 4 pendientes y 3 canceladas.

INSERT INTO `reserva` (`id`, `nombre_huesped`, `email_huesped`, `nombre_alojamiento`, `fecha_entrada`, `fecha_salida`, `estado`, `importe`, `notas`, `fecha_creacion`) VALUES
(1,  'Alba Pérez',        'alba.pz@email.com',          'Apartamento Parte Antigua',    '2026-05-10', '2026-05-15', 'confirmada', 450.00, 'Llegada tarde prevista a las 22:00', '2026-04-01 10:30:00'),
(2,  'Ana Gómez',         'ana.gomez@email.com',        'Apartamento Centro Histórico', '2026-12-01', '2026-12-07', 'pendiente',  620.50, 'Solicita cuna para bebé',             '2026-10-05 14:15:00'),
(3,  'Sara Durán',        'sara_d20@email.com',         'Apartamento Calle Caleros',    '2026-04-12', '2026-04-14', 'cancelada',  180.00, 'Cancelación por motivos de salud',    '2026-03-20 09:00:00'),
(4,  'Carlos Ruiz',       'carlos.ruiz@email.com',      'Apartamento Centro Histórico', '2026-11-05', '2026-11-08', 'pendiente',  310.00, NULL,                                  '2026-10-10 18:20:00'),
(5,  'Lucía Fernández',   'lucia.f@email.com',          'Apartamento Parte Antigua',    '2026-11-20', '2026-11-25', 'confirmada', 520.00, 'Viajan con un perro pequeño',         '2026-10-01 12:00:00'),
(6,  'Marta Ortega',      'marta.ortega@email.com',     'Apartamento Calle Caleros',    '2026-12-26', '2027-01-02', 'confirmada', 980.00, NULL,                                  '2026-09-15 09:45:00'),
(7,  'David O''Connor',   'david.oc@email.com',         'Apartamento Centro Histórico', '2026-10-30', '2026-11-02', 'cancelada',  270.00, 'Cambio de planes',                    '2026-09-28 16:10:00'),
(8,  'Ana Belén Ríos',    'anabelen.rios@email.com',    'Apartamento Calle Caleros',    '2026-12-05', '2026-12-09', 'pendiente',  400.00, 'Alergia a los frutos secos',          '2026-10-13 08:30:00'),
(9,  'Javier Molina',     'javier.molina@email.com',    'Apartamento Parte Antigua',    '2026-10-22', '2026-10-25', 'confirmada', 360.00, 'Check-in temprano si es posible',     '2026-10-08 19:05:00'),
(10, 'Elena Torres',      'elena.torres@email.com',     'Apartamento Calle Caleros',    '2026-09-18', '2026-09-21', 'confirmada', 330.00, NULL,                                  '2026-08-25 11:40:00'),
(11, 'Pablo Sánchez',     'pablo.sanchez@email.com',    'Apartamento Centro Histórico', '2027-01-10', '2027-01-14', 'pendiente',  540.00, NULL,                                  '2026-10-14 20:10:00'),
(12, 'Sofía Navarro',     'sofia.navarro@email.com',    'Apartamento Parte Antigua',    '2026-11-12', '2026-11-15', 'cancelada',  295.00, 'Vuelo cancelado',                     '2026-10-03 10:00:00');

INSERT INTO `reserva_evento` (`reserva_id`, `tipo`, `descripcion`, `fecha_creacion`) VALUES
(1,  'CREACION',      'Reserva creada en estado pendiente.', '2026-04-01 10:30:00'),
(1,  'CAMBIO_ESTADO', 'Estado actualizado a confirmada.',    '2026-04-02 11:00:00'),
(2,  'CREACION',      'Reserva creada en estado pendiente.', '2026-10-05 14:15:00'),
(3,  'CREACION',      'Reserva creada en estado pendiente.', '2026-03-20 09:00:00'),
(3,  'CAMBIO_ESTADO', 'Estado actualizado a cancelada.',     '2026-03-22 16:45:00'),
(4,  'CREACION',      'Reserva creada en estado pendiente.', '2026-10-10 18:20:00'),
(5,  'CREACION',      'Reserva creada en estado pendiente.', '2026-10-01 12:00:00'),
(5,  'CAMBIO_ESTADO', 'Estado actualizado a confirmada.',    '2026-10-01 13:00:00'),
(6,  'CREACION',      'Reserva creada en estado pendiente.', '2026-09-15 09:45:00'),
(6,  'CAMBIO_ESTADO', 'Estado actualizado a confirmada.',    '2026-09-16 10:00:00'),
(7,  'CREACION',      'Reserva creada en estado pendiente.', '2026-09-28 16:10:00'),
(7,  'CAMBIO_ESTADO', 'Estado actualizado a confirmada.',    '2026-09-29 09:20:00'),
(7,  'CAMBIO_ESTADO', 'Estado actualizado a cancelada.',     '2026-10-02 11:30:00'),
(8,  'CREACION',      'Reserva creada en estado pendiente.', '2026-10-13 08:30:00'),
(9,  'CREACION',      'Reserva creada en estado pendiente.', '2026-10-08 19:05:00'),
(9,  'CAMBIO_ESTADO', 'Estado actualizado a confirmada.',    '2026-10-09 09:15:00'),
(10, 'CREACION',      'Reserva creada en estado pendiente.', '2026-08-25 11:40:00'),
(10, 'CAMBIO_ESTADO', 'Estado actualizado a confirmada.',    '2026-08-26 08:50:00'),
(11, 'CREACION',      'Reserva creada en estado pendiente.', '2026-10-14 20:10:00'),
(12, 'CREACION',      'Reserva creada en estado pendiente.', '2026-10-03 10:00:00'),
(12, 'CAMBIO_ESTADO', 'Estado actualizado a confirmada.',    '2026-10-03 12:00:00'),
(12, 'CAMBIO_ESTADO', 'Estado actualizado a cancelada.',     '2026-10-11 17:25:00');