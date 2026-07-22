CREATE TABLE medicamento_horarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medicamento_id BIGINT UNSIGNED NOT NULL,
  hora TIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_medicamento_horarios UNIQUE (medicamento_id, hora),
  CONSTRAINT fk_medicamento_horarios_medicamento FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
