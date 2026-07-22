<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class MedicamentoService
{
    private readonly PDO $database;

    public function __construct(Database $database)
    {
        $this->database = $database->connection();
    }

    public function allForFamily(int $familyId, array $filters = []): array
    {
        $where = ['p.familia_id = :familia_id'];
        $parameters = ['familia_id' => $familyId];
        foreach (['persona_id' => 'm.persona_id', 'estado_id' => 'm.estado_id', 'atencion_id' => 'm.atencion_id'] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $where[] = "{$column} = :{$filter}";
                $parameters[$filter] = (int) $filters[$filter];
            }
        }

        $statement = $this->database->prepare(
            'SELECT m.*, p.nombre AS persona_nombre, e.nombre AS estado_nombre, e.codigo AS estado_codigo,
                    a.fecha_hora AS atencion_fecha,
                    (SELECT GROUP_CONCAT(DATE_FORMAT(mh.hora, \'%H:%i\') ORDER BY mh.hora SEPARATOR \', \')
                     FROM medicamento_horarios mh WHERE mh.medicamento_id = m.id) AS horarios_texto
             FROM medicamentos m
             INNER JOIN personas p ON p.id = m.persona_id
             INNER JOIN estados e ON e.id = m.estado_id
             LEFT JOIN atenciones a ON a.id = m.atencion_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY (e.codigo = \'ACTIVO\') DESC, m.fecha_inicio DESC, m.id DESC'
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function activeForFamily(int $familyId, ?int $personId = null): array
    {
        $active = array_values(array_filter(
            $this->allForFamily($familyId, $personId ? ['persona_id' => $personId] : []),
            static fn (array $item): bool => $item['estado_codigo'] === 'ACTIVO',
        ));
        return $active;
    }

    public function findForFamily(int $id, int $familyId): ?array
    {
        $items = $this->allForFamily($familyId);
        foreach ($items as $item) {
            if ((int) $item['id'] === $id) {
                $item['horarios'] = $this->hours($id);
                return $item;
            }
        }
        return null;
    }

    public function create(int $familyId, array $data): int
    {
        $data['horarios'] = $this->validate($familyId, $data);
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $statement = $this->database->prepare(
                'INSERT INTO medicamentos
                 (persona_id, atencion_id, estado_id, nombre, dosis, frecuencia, fecha_inicio, fecha_termino, indicaciones)
                 VALUES (:persona_id, :atencion_id, :estado_id, :nombre, :dosis, :frecuencia, :fecha_inicio, :fecha_termino, :indicaciones)'
            );
            $statement->execute($this->parameters($data));
            $id = (int) $this->database->lastInsertId();
            $this->replaceHours($id, $data['horarios']);
            if ($ownsTransaction) {
                $this->database->commit();
            }
            return $id;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $id, int $familyId, array $data): void
    {
        if ($this->findForFamily($id, $familyId) === null) {
            throw new \InvalidArgumentException('Medicamento no encontrado.');
        }
        $data['horarios'] = $this->validate($familyId, $data);
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $statement = $this->database->prepare(
                'UPDATE medicamentos SET persona_id = :persona_id, atencion_id = :atencion_id,
                    estado_id = :estado_id, nombre = :nombre, dosis = :dosis, frecuencia = :frecuencia,
                    fecha_inicio = :fecha_inicio, fecha_termino = :fecha_termino, indicaciones = :indicaciones
                 WHERE id = :id'
            );
            $statement->execute([...$this->parameters($data), 'id' => $id]);
            $this->replaceHours($id, $data['horarios']);
            if ($ownsTransaction) {
                $this->database->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function states(): array
    {
        return $this->database->query(
            "SELECT e.id, e.codigo, e.nombre FROM estados e
             INNER JOIN procesos p ON p.id = e.proceso_id
             WHERE p.codigo = 'MEDICAMENTO' AND e.activo = 1 ORDER BY e.orden, e.nombre"
        )->fetchAll();
    }

    private function validate(int $familyId, array $data): array
    {
        $person = $this->database->prepare('SELECT id FROM personas WHERE id = :id AND familia_id = :familia_id');
        $person->execute(['id' => $data['persona_id'], 'familia_id' => $familyId]);
        if ($person->fetchColumn() === false) {
            throw new \InvalidArgumentException('La persona seleccionada no pertenece a la familia.');
        }
        $state = $this->database->prepare(
            "SELECT e.id FROM estados e INNER JOIN procesos p ON p.id = e.proceso_id
             WHERE e.id = :id AND p.codigo = 'MEDICAMENTO' AND e.activo = 1"
        );
        $state->execute(['id' => $data['estado_id']]);
        if ($state->fetchColumn() === false) {
            throw new \InvalidArgumentException('El estado seleccionado no corresponde a un medicamento.');
        }
        if (!empty($data['fecha_termino']) && (string) $data['fecha_termino'] < (string) $data['fecha_inicio']) {
            throw new \InvalidArgumentException('La fecha de tÃ©rmino no puede ser anterior a la fecha de inicio.');
        }
        if (!empty($data['atencion_id'])) {
            $attention = $this->database->prepare(
                'SELECT a.id FROM atenciones a INNER JOIN personas p ON p.id = a.persona_id
                 WHERE a.id = :id AND a.persona_id = :persona_id AND p.familia_id = :familia_id'
            );
            $attention->execute([
                'id' => $data['atencion_id'],
                'persona_id' => $data['persona_id'],
                'familia_id' => $familyId,
            ]);
            if ($attention->fetchColumn() === false) {
                throw new \InvalidArgumentException('La atenciÃ³n seleccionada no corresponde a la persona.');
            }
        }
        return $this->normalizeHours($data['horarios'] ?? []);
    }

    private function normalizeHours(mixed $hours): array
    {
        if (!is_array($hours)) {
            throw new \InvalidArgumentException('Los horarios no son vÃ¡lidos.');
        }
        $normalized = [];
        foreach ($hours as $hour) {
            $hour = trim((string) $hour);
            if ($hour === '') {
                continue;
            }
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hour)) {
                throw new \InvalidArgumentException('Uno de los horarios no es vÃ¡lido.');
            }
            if (in_array($hour, $normalized, true)) {
                throw new \InvalidArgumentException('No se puede repetir un horario.');
            }
            $normalized[] = $hour;
        }
        sort($normalized);
        return $normalized;
    }

    private function replaceHours(int $medicationId, array $hours): void
    {
        $delete = $this->database->prepare('DELETE FROM medicamento_horarios WHERE medicamento_id = :id');
        $delete->execute(['id' => $medicationId]);
        $insert = $this->database->prepare('INSERT INTO medicamento_horarios (medicamento_id, hora) VALUES (:id, :hora)');
        foreach ($hours as $hour) {
            $insert->execute(['id' => $medicationId, 'hora' => $hour . ':00']);
        }
    }

    private function hours(int $medicationId): array
    {
        $statement = $this->database->prepare(
            "SELECT DATE_FORMAT(hora, '%H:%i') FROM medicamento_horarios WHERE medicamento_id = :id ORDER BY hora"
        );
        $statement->execute(['id' => $medicationId]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function parameters(array $data): array
    {
        return [
            'persona_id' => (int) $data['persona_id'],
            'atencion_id' => empty($data['atencion_id']) ? null : (int) $data['atencion_id'],
            'estado_id' => (int) $data['estado_id'],
            'nombre' => trim((string) $data['nombre']),
            'dosis' => trim((string) $data['dosis']),
            'frecuencia' => $this->nullable($data['frecuencia'] ?? null),
            'fecha_inicio' => (string) $data['fecha_inicio'],
            'fecha_termino' => $this->nullable($data['fecha_termino'] ?? null),
            'indicaciones' => $this->nullable($data['indicaciones'] ?? null),
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
