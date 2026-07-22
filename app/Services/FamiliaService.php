<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class FamiliaService
{
    private readonly PDO $database;

    public function __construct(Database $database)
    {
        $this->database = $database->connection();
    }

    public function createForUser(int $userId, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('El nombre de la familia es obligatorio.');
        }

        $this->database->beginTransaction();
        try {
            $insert = $this->database->prepare('INSERT INTO familias (nombre) VALUES (:nombre)');
            $insert->execute(['nombre' => $name]);
            $familyId = (int) $this->database->lastInsertId();
            $roleId = $this->roleId('ADMINISTRADOR');

            $member = $this->database->prepare(
                'INSERT INTO familia_usuarios (familia_id, usuario_id, tipo_rol_id)
                 VALUES (:familia_id, :usuario_id, :tipo_rol_id)'
            );
            $member->execute([
                'familia_id' => $familyId,
                'usuario_id' => $userId,
                'tipo_rol_id' => $roleId,
            ]);
            $this->database->commit();
            return $familyId;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function familiesForUser(int $userId): array
    {
        return $this->familyRowsForUser($userId, false);
    }

    public function archivedForUser(int $userId): array
    {
        return $this->familyRowsForUser($userId, true);
    }

    private function familyRowsForUser(int $userId, bool $archived): array
    {
        $statement = $this->database->prepare(
            'SELECT f.id, f.nombre, f.archivada_at, f.archivada_por_usuario_id,
                    t.codigo AS rol_codigo, t.nombre AS rol_nombre,
                    (SELECT COUNT(*) FROM personas p WHERE p.familia_id = f.id) AS total_personas,
                    (SELECT COUNT(*) FROM atenciones a INNER JOIN personas p ON p.id = a.persona_id WHERE p.familia_id = f.id) AS total_atenciones,
                    (SELECT COUNT(*) FROM medicamentos m INNER JOIN personas p ON p.id = m.persona_id WHERE p.familia_id = f.id) AS total_medicamentos,
                    (SELECT COUNT(*) FROM documentos d INNER JOIN personas p ON p.id = d.persona_id WHERE p.familia_id = f.id) AS total_documentos
             FROM familia_usuarios fu
             INNER JOIN familias f ON f.id = fu.familia_id
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             WHERE fu.usuario_id = :usuario_id
               AND ' . ($archived ? 'f.archivada_at IS NOT NULL' : 'f.archivada_at IS NULL') . '
             ORDER BY f.nombre'
        );
        $statement->execute(['usuario_id' => $userId]);
        return $statement->fetchAll();
    }

    public function findForUser(int $familyId, int $userId, bool $includeArchived = false): ?array
    {
        $statement = $this->database->prepare(
            'SELECT f.id, f.nombre, f.archivada_at, f.archivada_por_usuario_id,
                    t.codigo AS rol_codigo, t.nombre AS rol_nombre
             FROM familia_usuarios fu
             INNER JOIN familias f ON f.id = fu.familia_id
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             WHERE fu.familia_id = :familia_id AND fu.usuario_id = :usuario_id' .
             ($includeArchived ? '' : ' AND f.archivada_at IS NULL')
        );
        $statement->execute(['familia_id' => $familyId, 'usuario_id' => $userId]);
        $family = $statement->fetch();
        return is_array($family) ? $family : null;
    }

    public function members(int $familyId, int $requesterId): array
    {
        $this->requireMembership($familyId, $requesterId);
        $statement = $this->database->prepare(
            'SELECT u.id, u.nombre, u.email, u.avatar_url, u.activo,
                    t.codigo AS rol_codigo, t.nombre AS rol_nombre, fu.created_at
             FROM familia_usuarios fu
             INNER JOIN usuarios u ON u.id = fu.usuario_id
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             WHERE fu.familia_id = :familia_id
             ORDER BY t.codigo, u.nombre'
        );
        $statement->execute(['familia_id' => $familyId]);
        return $statement->fetchAll();
    }

    public function addMemberByEmail(int $familyId, int $requesterId, string $email, string $roleCode): void
    {
        $this->requireAdministrator($familyId, $requesterId);
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Debes ingresar un correo válido.');
        }
        if (!in_array($roleCode, ['ADMINISTRADOR', 'FAMILIAR'], true)) {
            throw new \InvalidArgumentException('El rol seleccionado no es válido.');
        }

        $user = $this->database->prepare('SELECT id, activo FROM usuarios WHERE email = :email');
        $user->execute(['email' => $email]);
        $row = $user->fetch();
        if (!is_array($row) || !(bool) $row['activo']) {
            throw new \RuntimeException('La persona debe ingresar una vez con Google antes de ser asociada.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO familia_usuarios (familia_id, usuario_id, tipo_rol_id)
             VALUES (:familia_id, :usuario_id, :tipo_rol_id)
             ON DUPLICATE KEY UPDATE tipo_rol_id = VALUES(tipo_rol_id)'
        );
        $statement->execute([
            'familia_id' => $familyId,
            'usuario_id' => $row['id'],
            'tipo_rol_id' => $this->roleId($roleCode),
        ]);
    }

    public function requireMembership(int $familyId, int $userId): array
    {
        $family = $this->findForUser($familyId, $userId);
        if ($family === null) {
            throw new \RuntimeException('No tienes acceso a esta familia.');
        }
        return $family;
    }

    public function requireAdministrator(int $familyId, int $userId): array
    {
        $family = $this->requireMembership($familyId, $userId);
        if ($family['rol_codigo'] !== 'ADMINISTRADOR') {
            throw new \RuntimeException('Esta acción requiere el rol de administrador.');
        }
        return $family;
    }

    public function archive(int $familyId, int $userId): void
    {
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $this->requireAdministrator($familyId, $userId);
            $statement = $this->database->prepare(
                'UPDATE familias SET archivada_at = UTC_TIMESTAMP(), archivada_por_usuario_id = :usuario_id
                 WHERE id = :familia_id AND archivada_at IS NULL'
            );
            $statement->execute(['usuario_id' => $userId, 'familia_id' => $familyId]);
            if ($statement->rowCount() !== 1) {
                throw new \RuntimeException('La familia ya está archivada o no está disponible.');
            }
            $revokeInvitations = $this->database->prepare(
                "UPDATE familia_invitaciones fi
                 INNER JOIN estados actual ON actual.id = fi.estado_id
                 INNER JOIN procesos proceso ON proceso.id = actual.proceso_id AND proceso.codigo = 'INVITACION'
                 SET fi.estado_id = (
                     SELECT revocada.id FROM estados revocada
                     WHERE revocada.proceso_id = proceso.id AND revocada.codigo = 'REVOCADA' LIMIT 1
                 ), fi.revocada_at = UTC_TIMESTAMP()
                 WHERE fi.familia_id = :familia_id AND actual.codigo = 'PENDIENTE'"
            );
            $revokeInvitations->execute(['familia_id' => $familyId]);
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

    public function restore(int $familyId, int $userId): void
    {
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $family = $this->findForUser($familyId, $userId, true);
            if ($family === null || $family['rol_codigo'] !== 'ADMINISTRADOR') {
                throw new \RuntimeException('Esta acción requiere el rol de administrador.');
            }
            if ($family['archivada_at'] === null) {
                throw new \RuntimeException('La familia no está archivada.');
            }
            $statement = $this->database->prepare(
                'UPDATE familias SET archivada_at = NULL, archivada_por_usuario_id = NULL
                 WHERE id = :familia_id AND archivada_at IS NOT NULL'
            );
            $statement->execute(['familia_id' => $familyId]);
            if ($statement->rowCount() !== 1) {
                throw new \RuntimeException('No fue posible restaurar la familia.');
            }
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

    private function roleId(string $code): int
    {
        $statement = $this->database->prepare(
            "SELECT t.id
             FROM tipos t
             INNER JOIN procesos p ON p.id = t.proceso_id
             WHERE p.codigo = 'MIEMBRO_FAMILIA' AND t.codigo = :codigo AND t.activo = 1"
        );
        $statement->execute(['codigo' => $code]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("No existe el rol {$code}.");
        }
        return (int) $id;
    }
}
