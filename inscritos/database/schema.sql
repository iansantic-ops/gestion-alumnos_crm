-- ============================================================
-- CRM Universitario — Esquema de Base de Datos
-- Base de datos: inscritos
-- ============================================================

CREATE DATABASE IF NOT EXISTS inscritos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inscritos;

-- -----------------------------------------------------------
-- Tabla: usuarios (administradores del sistema)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario   INT AUTO_INCREMENT PRIMARY KEY,
    usuario      VARCHAR(100) NOT NULL UNIQUE,
    contrasena   VARCHAR(255) NOT NULL,   -- bcrypt hash, NUNCA texto plano
    nombre       VARCHAR(150) NOT NULL DEFAULT 'Admin',
    creado_en    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEGURIDAD: contraseña almacenada como hash bcrypt generado con
-- password_hash('admin123', PASSWORD_BCRYPT). NO es texto plano.
-- Para cambiarla después: usar el modal de Configuración en el dashboard.
INSERT IGNORE INTO usuarios (usuario, contrasena, nombre)
VALUES ('admin', '$2b$10$s19KyReA7/Aow7P0Q43xs.lY36Erx/CYswNNP4pHvht1cDn55uh/O', 'Administrador');

-- -----------------------------------------------------------
-- Tabla: carreras
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS carreras (
    id_carrera  INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    activa      TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO carreras (nombre) VALUES
('Contaduría'),
('Ingeniería Civil'),
('Psicología'),
('Administración de Empresas'),
('Derecho'),
('Medicina'),
('Arquitectura'),
('Sistemas Computacionales');

-- -----------------------------------------------------------
-- Tabla: becas
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS becas (
    id_beca     INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    descuento   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    activa      TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO becas (nombre, descuento) VALUES
('Beca Excelencia', 50.00),
('Beca Deportiva',  30.00),
('Beca Económica',  40.00),
('Beca Hermanos',   20.00);

-- -----------------------------------------------------------
-- Tabla: aspirantes
-- etapa: 'Contacto' | 'Interesado' | 'Inscrito'
-- UNIQUE en email para evitar duplicados
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS aspirantes (
    id_aspirante       INT AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(150) NOT NULL,
    email              VARCHAR(200) NOT NULL,
    telefono           VARCHAR(20),
    id_carrera         INT,
    etapa              ENUM('Contacto','Interesado','Inscrito','No Interesado') DEFAULT 'Contacto',
    origen             ENUM('Web','Redes sociales','Feria','Referido','Llamada','Otro') DEFAULT 'Otro',
    id_beca            INT DEFAULT NULL,
    descuento_aplicado DECIMAL(5,2) DEFAULT 0.00,
    notas              TEXT,
    carreras_interes   TEXT DEFAULT NULL,
    eliminado_en       DATETIME DEFAULT NULL,
    creado_en          DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email),
    FOREIGN KEY (id_carrera) REFERENCES carreras(id_carrera) ON DELETE SET NULL,
    FOREIGN KEY (id_beca)    REFERENCES becas(id_beca)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO aspirantes (nombre, email, telefono, id_carrera, etapa) VALUES
('Ana Pérez',     'ana.perez@gmail.com',     '937-333-557', 1, 'Inscrito'),
('Juan García',   'juangarcia@gmail.com',    '389-553-563', 2, 'Interesado'),
('Luis Martínez', 'luis.martinez@gmail.com', '457-301-555', 3, 'Contacto');

-- -----------------------------------------------------------
-- Tabla: historial_interacciones
-- tipo: 'llamada' | 'correo' | 'visita' | 'nota'
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS historial_interacciones (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_aspirante INT NOT NULL,
    tipo         ENUM('llamada','correo','visita','nota') DEFAULT 'nota',
    descripcion  TEXT NOT NULL,
    fecha        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_aspirante) REFERENCES aspirantes(id_aspirante) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO historial_interacciones (id_aspirante, tipo, descripcion, fecha) VALUES
(2, 'nota',   'Se acordó seguimiento para el 23 de abril de 2024', '2024-04-22 10:00:00'),
(2, 'correo', 'Enviar correo con información de la carrera',        '2024-04-25 09:00:00');

-- -----------------------------------------------------------
-- Tabla: agenda (recordatorios y tareas)
-- tipo: 'llamada' | 'correo' | 'reunion' | 'tarea'
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS agenda (
    id_agenda    INT AUTO_INCREMENT PRIMARY KEY,
    id_aspirante INT DEFAULT NULL,
    tipo         ENUM('llamada','correo','reunion','tarea') DEFAULT 'tarea',
    titulo       VARCHAR(200) NOT NULL,
    fecha_hora   DATETIME NOT NULL,
    completado   TINYINT(1) DEFAULT 0,
    creado_en    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_aspirante) REFERENCES aspirantes(id_aspirante) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO agenda (id_aspirante, tipo, titulo, fecha_hora) VALUES
(NULL, 'tarea',   'Revisión semanal de aspirantes',  NOW()),
(2,    'llamada', 'Llamar a Juan García',             DATE_ADD(NOW(), INTERVAL 1 DAY)),
(2,    'correo',  'Enviar correo con información',    DATE_ADD(NOW(), INTERVAL 2 DAY));

-- -----------------------------------------------------------
-- Migración v4: agregar etapa 'No Interesado'
-- Ejecutar en instalaciones existentes (v3 → v4):
-- -----------------------------------------------------------
-- ALTER TABLE aspirantes
--     MODIFY COLUMN etapa
--     ENUM('Contacto','Interesado','Inscrito','No Interesado')
--     DEFAULT 'Contacto';

-- ===========================================================
-- Migración v5: origen, eliminado_en
-- Para instalaciones NUEVAS: ya incluido arriba (buscar ALTER).
-- Para actualizar base existente (v4 → v5), ejecutar:
-- ===========================================================
-- ALTER TABLE aspirantes
--     ADD COLUMN origen       ENUM('Web','Redes sociales','Feria','Referido','Llamada','Otro') DEFAULT 'Otro' AFTER etapa,
--     ADD COLUMN eliminado_en DATETIME DEFAULT NULL;
-- CREATE INDEX idx_asp_eliminado ON aspirantes (eliminado_en);

-- -----------------------------------------------------------
-- Tabla: configuracion (v8 — configuración global del sistema)
-- Almacena pares clave-valor compartidos por todos los admins.
-- Ejemplo: mensajes_whatsapp, etc.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracion (
    clave      VARCHAR(100) NOT NULL PRIMARY KEY,
    valor      TEXT         NOT NULL,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores por defecto de mensajes WhatsApp
INSERT IGNORE INTO configuracion (clave, valor) VALUES
('wa_msg_contacto',      'Hola {nombre}, te contactamos desde la universidad 👋 Nos gustaría darte información sobre nuestras carreras. ¿Tienes un momento?'),
('wa_msg_interesado',    'Hola {nombre}! Vimos que estás interesado en nuestra oferta educativa 🎓 Con gusto te orientamos en el proceso de admisión. ¿Cuándo podemos hablar?'),
('wa_msg_inscrito',      '¡Hola {nombre}! Felicitaciones por tu inscripción 🎉 Aquí tienes los próximos pasos para iniciar tu proceso de bienvenida.'),
('wa_msg_no_interesado', 'Hola {nombre}, entendemos tu decisión. Si en el futuro reconsideras o necesitas información, estamos aquí. ¡Mucho éxito! 🙌');
