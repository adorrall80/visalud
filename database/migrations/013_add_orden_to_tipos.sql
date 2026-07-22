ALTER TABLE tipos
  ADD COLUMN orden SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER nombre,
  ADD KEY idx_tipos_proceso_orden (proceso_id, orden, nombre);
