<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class MantenedorService
{
    private readonly PDO $database;

    private const BASE_CODES = [
        'estados' => ['ATENCION:PROGRAMADA', 'ATENCION:REALIZADA', 'ATENCION:CANCELADA', 'MEDICAMENTO:ACTIVO', 'MEDICAMENTO:SUSPENDIDO', 'MEDICAMENTO:FINALIZADO'],
        'tipos' => ['MIEMBRO_FAMILIA:ADMINISTRADOR', 'MIEMBRO_FAMILIA:FAMILIAR', 'ATENCION:CONSULTA_MEDICA', 'ATENCION:TERAPIA', 'ATENCION:EXAMEN', 'DOCUMENTO:ORDEN_MEDICA', 'DOCUMENTO:RECETA', 'DOCUMENTO:RESULTADO_EXAMEN', 'DOCUMENTO:INFORME', 'DOCUMENTO:CERTIFICADO', 'DOCUMENTO:OTRO'],
    ];

    public function __construct(Database $database)
    {
        $this->database = $database->connection();
    }

    public function processes(): array
    {
        return $this->database->query(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM estados e WHERE e.proceso_id = p.id) AS total_estados,
                    (SELECT COUNT(*) FROM tipos t WHERE t.proceso_id = p.id) AS total_tipos
             FROM procesos p ORDER BY p.nombre'
        )->fetchAll();
    }

    public function process(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM procesos WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function values(string $kind, int $processId): array
    {
        $table = $this->table($kind);
        $statement = $this->database->prepare(
            "SELECT c.*, p.nombre AS proceso_nombre, p.codigo AS proceso_codigo
             FROM {$table} c INNER JOIN procesos p ON p.id = c.proceso_id
             WHERE c.proceso_id = :proceso_id ORDER BY c.orden, c.nombre"
        );
        $statement->execute(['proceso_id' => $processId]);
        return $statement->fetchAll();
    }

    public function find(string $kind, int $id): ?array
    {
        $table = $this->table($kind);
        $statement = $this->database->prepare(
            "SELECT c.*, p.nombre AS proceso_nombre, p.codigo AS proceso_codigo FROM {$table} c
             INNER JOIN procesos p ON p.id = c.proceso_id WHERE c.id = :id"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(string $kind, array $data): int
    {
        $table = $this->table($kind);
        $prepared = $this->validate($table, $data);
        $statement = $table === 'estados'
            ? $this->database->prepare(
                'INSERT INTO estados (proceso_id, codigo, nombre, color, orden, activo)
                 VALUES (:proceso_id, :codigo, :nombre, :color, :orden, :activo)'
            )
            : $this->database->prepare(
                'INSERT INTO tipos (proceso_id, codigo, nombre, orden, activo)
                 VALUES (:proceso_id, :codigo, :nombre, :orden, :activo)'
            );
        try {
            $statement->execute($prepared);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new \InvalidArgumentException('Ya existe un código o nombre igual dentro del proceso.');
            }
            throw $exception;
        }
        return (int) $this->database->lastInsertId();
    }

    public function update(string $kind, int $id, array $data): void
    {
        $table = $this->table($kind);
        $current = $this->find($table, $id);
        if ($current === null) {
            throw new \InvalidArgumentException('Valor de catálogo no encontrado.');
        }
        $prepared = $this->validate($table, $data, $id);
        if ((int) $current['proceso_id'] !== $prepared['proceso_id'] && $this->isUsed($table, $id)) {
            throw new \InvalidArgumentException('No se puede cambiar de proceso un valor que ya está utilizado.');
        }
        try {
            $statement = $table === 'estados'
                ? $this->database->prepare(
                    'UPDATE estados SET proceso_id = :proceso_id, codigo = :codigo, nombre = :nombre,
                     color = :color, orden = :orden, activo = :activo WHERE id = :id'
                )
                : $this->database->prepare(
                    'UPDATE tipos SET proceso_id = :proceso_id, codigo = :codigo, nombre = :nombre,
                     orden = :orden, activo = :activo WHERE id = :id'
                );
            $statement->execute([...$prepared, 'id' => $id]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new \InvalidArgumentException('Ya existe un código o nombre igual dentro del proceso.');
            }
            throw $exception;
        }
    }

    public function toggle(string $kind, int $id): bool
    {
        $table = $this->table($kind);
        $statement = $this->database->prepare("UPDATE {$table} SET activo = NOT activo WHERE id = :id");
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) {
            throw new \InvalidArgumentException('Valor de catálogo no encontrado.');
        }
        return (bool) $this->find($table, $id)['activo'];
    }

    public function delete(string $kind, int $id): void
    {
        $table = $this->table($kind);
        $value = $this->find($table, $id);
        if ($value === null) {
            throw new \InvalidArgumentException('Valor de catálogo no encontrado.');
        }
        if (in_array($value['proceso_codigo'] . ':' . $value['codigo'], self::BASE_CODES[$table], true)) {
            throw new \InvalidArgumentException('Los valores base del sistema no se pueden eliminar.');
        }
        if ($this->isUsed($table, $id)) {
            throw new \InvalidArgumentException('No se puede eliminar un valor que ya está utilizado.');
        }
        $statement = $this->database->prepare("DELETE FROM {$table} WHERE id = :id");
        $statement->execute(['id' => $id]);
    }

    public function isUsed(string $kind, int $id): bool
    {
        $table = $this->table($kind);
        $references = $table === 'estados'
            ? [['atenciones', 'estado_id'], ['medicamentos', 'estado_id']]
            : [['familia_usuarios', 'tipo_rol_id'], ['atenciones', 'tipo_id'], ['documentos', 'tipo_id']];
        foreach ($references as [$referenceTable, $column]) {
            $statement = $this->database->prepare("SELECT 1 FROM {$referenceTable} WHERE {$column} = :id LIMIT 1");
            $statement->execute(['id' => $id]);
            if ($statement->fetchColumn() !== false) {
                return true;
            }
        }
        return false;
    }

    private function validate(string $table, array $data, ?int $exceptId = null): array
    {
        $processId = (int) ($data['proceso_id'] ?? 0);
        if ($this->process($processId) === null) {
            throw new \InvalidArgumentException('El proceso seleccionado no existe.');
        }
        $code = strtoupper(trim((string) ($data['codigo'] ?? '')));
        $code = str_replace([' ', '-'], '_', $code);
        if ($code === '' || !preg_match('/^[A-Z0-9_]+$/', $code)) {
            throw new \InvalidArgumentException('El código debe usar letras mayúsculas, números o guion bajo.');
        }
        $name = trim((string) ($data['nombre'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100 || strlen($code) > 50) {
            throw new \InvalidArgumentException('El nombre y código son obligatorios y deben respetar su largo máximo.');
        }
        $duplicate = $this->database->prepare(
            "SELECT id FROM {$table} WHERE proceso_id = :proceso_id
             AND (codigo = :codigo OR nombre = :nombre)" . ($exceptId ? ' AND id <> :id' : '') . ' LIMIT 1'
        );
        $parameters = ['proceso_id' => $processId, 'codigo' => $code, 'nombre' => $name];
        if ($exceptId) {
            $parameters['id'] = $exceptId;
        }
        $duplicate->execute($parameters);
        if ($duplicate->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Ya existe un código o nombre igual dentro del proceso.');
        }
        $prepared = [
            'proceso_id' => $processId,
            'codigo' => $code,
            'nombre' => $name,
            'orden' => max(0, min(65535, (int) ($data['orden'] ?? 0))),
            'activo' => !empty($data['activo']) ? 1 : 0,
        ];
        if ($table === 'estados') {
            $color = strtoupper(trim((string) ($data['color'] ?? '#64748B')));
            if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
                throw new \InvalidArgumentException('El color debe usar el formato hexadecimal #RRGGBB.');
            }
            $prepared['color'] = $color;
        }
        return $prepared;
    }

    private function table(string $kind): string
    {
        if (!in_array($kind, ['estados', 'tipos'], true)) {
            throw new \InvalidArgumentException('Catálogo no válido.');
        }
        return $kind;
    }
}
