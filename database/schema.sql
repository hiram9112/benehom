CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL,
  email VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reset_token_hash VARCHAR(255) NULL,
  reset_token_expires_at DATETIME NULL,
  email_verificado_en DATETIME NULL,
  email_verification_token_hash VARCHAR(255) NULL,
  email_verification_expires_at DATETIME NULL
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

CREATE TABLE numa_uso (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  fecha DATE NOT NULL,
  cantidad_confirmada INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY numa_uso_usuario_fecha_unique (usuario_id, fecha),
  KEY numa_uso_fecha_idx (fecha),
  CONSTRAINT numa_uso_usuario_fk
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE numa_reservas (
  id CHAR(36) PRIMARY KEY,
  usuario_id INT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('pendiente','confirmada','revertida','expirada') NOT NULL DEFAULT 'pendiente',
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY numa_reservas_usuario_fecha_estado_expires_idx (usuario_id, fecha, estado, expires_at),
  KEY numa_reservas_expires_at_idx (expires_at),
  CONSTRAINT numa_reservas_usuario_fk
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE numa_uso_proveedor (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  llamadas INT NOT NULL DEFAULT 0,
  input_tokens INT NOT NULL DEFAULT 0,
  output_tokens INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY numa_uso_proveedor_fecha_unique (fecha),
  KEY numa_uso_proveedor_fecha_idx (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE numa_conocimiento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fragmento_id VARCHAR(191) NOT NULL,
  documento VARCHAR(120) NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  seccion VARCHAR(220) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  contenido TEXT NOT NULL,
  hash CHAR(64) NOT NULL,
  embedding JSON NOT NULL,
  dimensiones INT UNSIGNED NOT NULL,
  firma_embedding VARCHAR(500) NOT NULL,
  indexed_at DATETIME NOT NULL,
  UNIQUE KEY numa_conocimiento_fragmento_id_unique (fragmento_id),
  KEY numa_conocimiento_documento_idx (documento),
  KEY numa_conocimiento_hash_idx (hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
