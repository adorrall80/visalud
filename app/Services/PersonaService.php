<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class PersonaService
{
    private readonly PDO $database;

    public function __construct(Database $database)
    {
        $this->database = $database->connection();
    }

    public function allForFamily(int $familyId): array
    {
        $statement = $this->database->prepare(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM atenciones a WHERE a.persona_id = p.id) AS total_atenciones,
                    (SELECT COUNT(*) FROM medicamentos m WHERE m.persona_id = p.id) AS total_medicamentos
             FROM personas p
             WHERE p.familia_id = :familia_id
             ORDER BY p.nombre'
        );
        $statement->execute(['familia_id' => $familyId]);
        return $statement->fetchAll();
    }

    public function findForFamily(int $id, int $familyId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM atenciones a WHERE a.persona_id = p.id) AS total_atenciones,
                    (SELECT COUNT(*) FROM medicamentos m WHERE m.persona_id = p.id) AS total_medicamentos,
                    (SELECT COUNT(*) FROM documentos d WHERE d.persona_id = p.id) AS total_documentos
             FROM personas p
             WHERE p.id = :id AND p.familia_id = :familia_id'
        );
        $statement->execute(['id' => $id, 'familia_id' => $familyId]);
        $person = $statement->fetch();
        return is_array($person) ? $person : null;
    }

    public function create(int $familyId, array $data): int
    {
        $this->assertUniqueIdentification($familyId, $data['identificacion'] ?? null);
        $statement = $this->database->prepare(
            'INSERT INTO personas
             (familia_id, nombre, identificacion, fecha_nacimiento, grupo_sanguineo, alergias,
              enfermedades_cronicas, contacto_emergencia, telefono_emergencia, observaciones)
             VALUES
             (:familia_id, :nombre, :identificacion, :fecha_nacimiento, :grupo_sanguineo, :alergias,
              :enfermedades_cronicas, :contacto_emergencia, :telefono_emergencia, :observaciones)'
        );
        $statement->execute($this->parameters($familyId, $data));
        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, int $familyId, array $data): void
    {
        if ($this->findForFamily($id, $familyId) === null) {
            throw new \RuntimeException('Persona no encontrada.');
        }
        $this->assertUniqueIdentification($familyId, $data['identificacion'] ?? null, $id);
        $statement = $this->database->prepare(
            'UPDATE personas SET
                nombre = :nombre,
                identificacion = :identificacion,
                fecha_nacimiento = :fecha_nacimiento,
                grupo_sanguineo = :grupo_sanguineo,
                alergias = :alergias,
                enfermedades_cronicas = :enfermedades_cronicas,
                contacto_emergencia = :contacto_emergencia,
                telefono_emergencia = :telefono_emergencia,
                observaciones = :observaciones
             WHERE id = :id AND familia_id = :familia_id'
        );
        $statement->execute([...$this->parameters($familyId, $data), 'id' => $id]);
    }

    private function parameters(int $familyId, array $data): array
    {
        $fields = [
            'familia_id' => $familyId,
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'identificacion' => $this->nullable($data['identificacion'] ?? null),
            'fecha_nacimiento' => $this->nullable($data['fecha_nacimiento'] ?? null),
            'grupo_sanguineo' => $this->nullable($data['grupo_sanguineo'] ?? null),
            'alergias' => $this->nullable($data['alergias'] ?? null),
            'enfermedades_cronicas' => $this->nullable($data['enfermedades_cronicas'] ?? null),
            'contacto_emergencia' => $this->nullable($data['contacto_emergencia'] ?? null),
            'telefono_emergencia' => $this->nullable($data['telefono_emergencia'] ?? null),
            'observaciones' => $this->nullable($data['observaciones'] ?? null),
        ];
        return $fields;
    }

    private function assertUniqueIdentification(int $familyId, mixed $identification, ?int $exceptId = null): void
    {
        $identification = $this->nullable($identification);
        if ($identification === null) {
            return;
        }
        $sql = 'SELECT id FROM personas WHERE familia_id = :familia_id AND identificacion = :identificacion';
        $parameters = ['familia_id' => $familyId, 'identificacion' => $identification];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptId;
        }
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        if ($statement->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Ya existe una persona con esa identificación en la familia.');
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
