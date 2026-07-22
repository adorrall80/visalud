ALTER TABLE estados
  ADD COLUMN color CHAR(7) NOT NULL DEFAULT '#64748B' AFTER nombre;

UPDATE estados e
INNER JOIN procesos p ON p.id = e.proceso_id
SET e.color = CASE
  WHEN p.codigo = 'ATENCION' AND e.codigo = 'PROGRAMADA' THEN '#3478C8'
  WHEN p.codigo = 'ATENCION' AND e.codigo = 'REALIZADA' THEN '#2F806B'
  WHEN p.codigo = 'ATENCION' AND e.codigo = 'CANCELADA' THEN '#C64B4B'
  WHEN p.codigo = 'MEDICAMENTO' AND e.codigo = 'ACTIVO' THEN '#2F806B'
  WHEN p.codigo = 'MEDICAMENTO' AND e.codigo = 'SUSPENDIDO' THEN '#C48738'
  WHEN p.codigo = 'MEDICAMENTO' AND e.codigo = 'FINALIZADO' THEN '#64748B'
  ELSE e.color
END;
