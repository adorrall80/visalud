<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class AtencionService
{
    private readonly PDO $database;
    private readonly DateTimeZone $localTimezone;
    private readonly DateTimeZone $utcTimezone;

    public function __construct(Database $database)
    {
        $this->database = $database->connection();
        $this->localTimezone = new DateTimeZone((string) env('APP_TIMEZONE', 'America/Santiago'));
        $this->utcTimezone = new DateTimeZone('UTC');
    }

    public function allForFamily(int $familyId, array $filters = []): array
    {
        $where = ['p.familia_id = :familia_id'];
        $parameters = ['familia_id' => $familyId];
        foreach (['persona_id' => 'a.persona_id', 'tipo_id' => 'a.tipo_id', 'estado_id' => 'a.estado_id'] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $where[] = "{$column} = :{$filter}";
                $parameters[$filter] = (int) $filters[$filter];
            }
        }
        if (!empty($filters['desde'])) {
            $where[] = 'a.fecha_hora >= :desde';
            $parameters['desde'] = $this->startOfDayUtc((string) $filters['desde']);
        }
        if (!empty($filters['hasta'])) {
            $where[] = 'a.fecha_hora <= :hasta';
            $parameters['hasta'] = $this->endOfDayUtc((string) $filters['hasta']);
        }

        $statement = $this->database->prepare(
            'SELECT a.*, p.nombre AS persona_nombre, t.nombre AS tipo_nombre, t.codigo AS tipo_codigo,
                    e.nombre AS estado_nombre, e.codigo AS estado_codigo, e.color AS estado_color,
                    u.nombre AS registrado_por_nombre
             FROM atenciones a
             INNER JOIN personas p ON p.id = a.persona_id
             INNER JOIN tipos t ON t.id = a.tipo_id
             INNER JOIN estados e ON e.id = a.estado_id
             INNER JOIN usuarios u ON u.id = a.registrado_por_usuario_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.fecha_hora DESC, a.id DESC'
        );
        $statement->execute($parameters);
        return array_map(fn (array $row): array => $this->present($row), $statement->fetchAll());
    }

    public function findForFamily(int $id, int $familyId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT a.*, p.nombre AS persona_nombre, t.nombre AS tipo_nombre, t.codigo AS tipo_codigo,
                    e.nombre AS estado_nombre, e.codigo AS estado_codigo, e.color AS estado_color,
                    u.nombre AS registrado_por_nombre
             FROM atenciones a
             INNER JOIN personas p ON p.id = a.persona_id
             INNER JOIN tipos t ON t.id = a.tipo_id
             INNER JOIN estados e ON e.id = a.estado_id
             INNER JOIN usuarios u ON u.id = a.registrado_por_usuario_id
             WHERE a.id = :id AND p.familia_id = :familia_id'
        );
        $statement->execute(['id' => $id, 'familia_id' => $familyId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->present($row) : null;
    }

    public function upcomingForFamily(int $familyId, array $filters = [], int $limit = 5): array
    {
        $filters['desde'] = $filters['desde'] ?? date('Y-m-d');
        $items = array_reverse($this->allForFamily($familyId, $filters));
        return array_slice($items, 0, max(0, $limit));
    }

    public function create(int $familyId, int $userId, array $data): int
    {
        $this->validateRelations($familyId, $data);
        $statement = $this->database->prepare(
            'INSERT INTO atenciones
             (persona_id, tipo_id, estado_id, registrado_por_usuario_id, fecha_hora, profesional,
              especialidad, centro_medico, motivo, diagnostico_resultado, indicaciones,
              temas_abordados, acuerdos, proxima_fecha)
             VALUES
             (:persona_id, :tipo_id, :estado_id, :usuario_id, :fecha_hora, :profesional,
              :especialidad, :centro_medico, :motivo, :diagnostico_resultado, :indicaciones,
              :temas_abordados, :acuerdos, :proxima_fecha)'
        );
        $statement->execute($this->parameters($userId, $data));
        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, int $familyId, int $userId, array $data): void
    {
        if ($this->findForFamily($id, $familyId) === null) {
            throw new \RuntimeException('Atención no encontrada.');
        }
        $this->validateRelations($familyId, $data);
        $statement = $this->database->prepare(
            'UPDATE atenciones SET
                persona_id = :persona_id, tipo_id = :tipo_id, estado_id = :estado_id,
                registrado_por_usuario_id = :usuario_id, fecha_hora = :fecha_hora,
                profesional = :profesional, especialidad = :especialidad, centro_medico = :centro_medico,
                motivo = :motivo, diagnostico_resultado = :diagnostico_resultado,
                indicaciones = :indicaciones, temas_abordados = :temas_abordados,
                acuerdos = :acuerdos, proxima_fecha = :proxima_fecha
             WHERE id = :id'
        );
        $statement->execute([...$this->parameters($userId, $data), 'id' => $id]);
    }

    public function types(): array
    {
        return $this->catalog('tipos');
    }

    public function states(): array
    {
        return $this->catalog('estados', true);
    }

    private function catalog(string $table, bool $ordered = false): array
    {
        $order = $ordered ? 'c.orden, c.nombre' : 'c.nombre';
        $color = $table === 'estados' ? ', c.color' : '';
        return $this->database->query(
            "SELECT c.id, c.codigo, c.nombre{$color}
             FROM {$table} c INNER JOIN procesos p ON p.id = c.proceso_id
             WHERE p.codigo = 'ATENCION' AND c.activo = 1
             ORDER BY {$order}"
        )->fetchAll();
    }

    private function validateRelations(int $familyId, array $data): void
    {
        $person = $this->database->prepare('SELECT id FROM personas WHERE id = :id AND familia_id = :familia_id');
        $person->execute(['id' => $data['persona_id'], 'familia_id' => $familyId]);
        if ($person->fetchColumn() === false) {
            throw new \InvalidArgumentException('La persona seleccionada no pertenece a la familia.');
        }
        $this->requireCatalogValue('tipos', (int) $data['tipo_id']);
        $this->requireCatalogValue('estados', (int) $data['estado_id']);
        $attentionDate = substr((string) $data['fecha_hora'], 0, 10);
        if (!empty($data['proxima_fecha']) && (string) $data['proxima_fecha'] < $attentionDate) {
            throw new \InvalidArgumentException('La próxima fecha no puede ser anterior a la atención.');
        }
    }

    private function requireCatalogValue(string $table, int $id): void
    {
        $statement = $this->database->prepare(
            "SELECT c.id FROM {$table} c INNER JOIN procesos p ON p.id = c.proceso_id
             WHERE c.id = :id AND p.codigo = 'ATENCION' AND c.activo = 1"
        );
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) {
            throw new \InvalidArgumentException('El tipo o estado seleccionado no corresponde a una atención.');
        }
    }

    private function parameters(int $userId, array $data): array
    {
        return [
            'persona_id' => (int) $data['persona_id'],
            'tipo_id' => (int) $data['tipo_id'],
            'estado_id' => (int) $data['estado_id'],
            'usuario_id' => $userId,
            'fecha_hora' => $this->toUtc((string) $data['fecha_hora']),
            'profesional' => $this->nullable($data['profesional'] ?? null),
            'especialidad' => $this->nullable($data['especialidad'] ?? null),
            'centro_medico' => $this->nullable($data['centro_medico'] ?? null),
            'motivo' => $this->nullable($data['motivo'] ?? null),
            'diagnostico_resultado' => $this->nullable($data['diagnostico_resultado'] ?? null),
            'indicaciones' => $this->nullable($data['indicaciones'] ?? null),
            'temas_abordados' => $this->nullable($data['temas_abordados'] ?? null),
            'acuerdos' => $this->nullable($data['acuerdos'] ?? null),
            'proxima_fecha' => $this->nullable($data['proxima_fecha'] ?? null),
        ];
    }

    private function present(array $row): array
    {
        $date = new DateTimeImmutable((string) $row['fecha_hora'], $this->utcTimezone);
        $row['fecha_hora_local'] = $date->setTimezone($this->localTimezone)->format('Y-m-d\TH:i');
        $row['fecha_hora_formato'] = $date->setTimezone($this->localTimezone)->format('d-m-Y H:i');
        return $row;
    }

    private function toUtc(string $local): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, $this->localTimezone);
        if ($date === false) {
            throw new \InvalidArgumentException('La fecha y hora no son válidas.');
        }
        return $date->setTimezone($this->utcTimezone)->format('Y-m-d H:i:s');
    }

    private function startOfDayUtc(string $date): string
    {
        return (new DateTimeImmutable($date . ' 00:00:00', $this->localTimezone))->setTimezone($this->utcTimezone)->format('Y-m-d H:i:s');
    }

    private function endOfDayUtc(string $date): string
    {
        return (new DateTimeImmutable($date . ' 23:59:59', $this->localTimezone))->setTimezone($this->utcTimezone)->format('Y-m-d H:i:s');
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
