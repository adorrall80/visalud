ALTER TABLE familias
  ADD COLUMN archivada_at DATETIME NULL AFTER nombre,
  ADD COLUMN archivada_por_usuario_id BIGINT UNSIGNED NULL AFTER archivada_at,
  ADD KEY idx_familias_archivada_at (archivada_at),
  ADD KEY idx_familias_archivada_por (archivada_por_usuario_id),
  ADD CONSTRAINT fk_familias_archivada_por
    FOREIGN KEY (archivada_por_usuario_id) REFERENCES usuarios(id)
    ON UPDATE CASCADE ON DELETE RESTRICT;
