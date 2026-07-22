UPDATE documentos d
INNER JOIN medicamentos m ON m.id = d.medicamento_id
SET d.atencion_id = m.atencion_id
WHERE d.atencion_id IS NULL
  AND m.atencion_id IS NOT NULL;

ALTER TABLE documentos
  DROP FOREIGN KEY fk_documentos_medicamento,
  DROP INDEX idx_documentos_medicamento,
  DROP COLUMN medicamento_id;
