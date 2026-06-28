CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL,
  email VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reset_token_hash VARCHAR(255) NULL,
  reset_token_expires_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE ingresos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  categoria VARCHAR(50) NOT NULL,
  cantidad DECIMAL(10,2) NOT NULL,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ingresos_usuario_fk
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE gastos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo ENUM('esencial','flexible') NOT NULL,
  categoria VARCHAR(50) NOT NULL,
  cantidad DECIMAL(10,2) NOT NULL,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT gastos_usuario_fk
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE metas_ahorro (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  importe_objetivo DECIMAL(10,2) NOT NULL,
  aportacion_mensual DECIMAL(10,2) NOT NULL DEFAULT 0,
  fecha_objetivo DATE NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT metas_ahorro_usuario_fk
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE escenarios_inversion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  capital_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
  aportacion_mensual DECIMAL(10,2) NOT NULL DEFAULT 0,
  rentabilidad_anual DECIMAL(5,2) NOT NULL DEFAULT 0,
  plazo_anios INT NOT NULL,
  frecuencia_reinversion ENUM('mensual','trimestral','semestral','anual') NOT NULL DEFAULT 'mensual',
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT escenarios_inversion_usuario_fk
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE proyecciones_inflacion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  cantidad_inicial DECIMAL(10,2) NOT NULL,
  inflacion_anual DECIMAL(5,2) NOT NULL,
  plazo_anios INT NOT NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT proyecciones_inflacion_usuario_fk
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE calculadoras_hipoteca (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  precio_inmueble DECIMAL(10,2) NOT NULL,
  porcentaje_financiacion DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  importe_prestamo DECIMAL(10,2) NOT NULL,
  interes_anual DECIMAL(5,2) NOT NULL,
  plazo_anios INT NOT NULL,
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT calculadoras_hipoteca_usuario_fk
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE intentos_acceso (
  id INT AUTO_INCREMENT PRIMARY KEY,
  accion VARCHAR(40) NOT NULL,
  clave_hash CHAR(64) NOT NULL,
  intentos INT NOT NULL DEFAULT 0,
  primer_intento DATETIME NOT NULL,
  ultimo_intento DATETIME NOT NULL,
  bloqueado_hasta DATETIME NULL,
  UNIQUE KEY intentos_acceso_accion_clave_unique (accion, clave_hash),
  KEY intentos_acceso_bloqueado_hasta_idx (bloqueado_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
