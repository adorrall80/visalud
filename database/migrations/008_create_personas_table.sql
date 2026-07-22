CREATE TABLE personas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  familia_id BIGINT UNSIGNED NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  identificacion VARCHAR(30) NULL,
  fecha_nacimiento DATE NULL,
  grupo_sanguineo VARCHAR(10) NULL,
  alergias TEXT NULL,
  enfermedades_cronicas TEXT NULL,
  contacto_emergencia VARCHAR(150) NULL,
  telefono_emergencia VARCHAR(30) NULL,
  observaciones TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uk_personas_familia_identificacion UNIQUE (familia_id, identificacion),
  CONSTRAINT fk_personas_familia FOREIGN KEY (familia_id) REFERENCES familias(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
