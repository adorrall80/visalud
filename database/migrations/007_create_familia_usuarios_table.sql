CREATE TABLE familia_usuarios (
  familia_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  tipo_rol_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (familia_id, usuario_id),
  KEY idx_familia_usuarios_usuario (usuario_id),
  KEY idx_familia_usuarios_rol (tipo_rol_id),
  CONSTRAINT fk_familia_usuarios_familia FOREIGN KEY (familia_id) REFERENCES familias(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_familia_usuarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_familia_usuarios_rol FOREIGN KEY (tipo_rol_id) REFERENCES tipos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
