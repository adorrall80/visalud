<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class InvitacionService
{
    private readonly PDO $database;

    public function __construct(Database $database)
    {
        $this->database = $database->connection();
    }

    public function create(int $familyId, int $userId, int $validHours = 24): array
    {
        $family = $this->requireAdministrator($familyId, $userId);
        $validHours = max(1, min(168, $validHours));
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($validHours * 3600));
        $statement = $this->database->prepare(
            'INSERT INTO familia_invitaciones
             (familia_id, tipo_rol_id, estado_id, token_hash, expira_at, creada_por_usuario_id)
             VALUES (:familia_id, :rol_id, :estado_id, :token_hash, :expira_at, :usuario_id)'
        );
        $statement->execute([
            'familia_id' => $familyId,
            'rol_id' => $this->roleId('FAMILIAR'),
            'estado_id' => $this->stateId('PENDIENTE'),
            'token_hash' => hash('sha256', $token),
            'expira_at' => $expiresAt,
            'usuario_id' => $userId,
        ]);
        return [
            'id' => (int) $this->database->lastInsertId(),
            'token' => $token,
            'expira_at' => $expiresAt,
            'familia_nombre' => $family['nombre'],
        ];
    }

    public function allForFamily(int $familyId, int $userId): array
    {
        $this->requireAdministrator($familyId, $userId);
        $this->expirePending($familyId);
        $statement = $this->database->prepare(
            'SELECT fi.id, fi.familia_id, fi.expira_at, fi.aceptada_at, fi.revocada_at, fi.created_at,
                    e.codigo AS estado_codigo, e.nombre AS estado_nombre, e.color AS estado_color,
                    t.nombre AS rol_nombre, creador.nombre AS creador_nombre,
                    aceptada.nombre AS aceptada_por_nombre, aceptada.email AS aceptada_por_email
             FROM familia_invitaciones fi
             INNER JOIN estados e ON e.id = fi.estado_id
             INNER JOIN tipos t ON t.id = fi.tipo_rol_id
             INNER JOIN usuarios creador ON creador.id = fi.creada_por_usuario_id
             LEFT JOIN usuarios aceptada ON aceptada.id = fi.aceptada_por_usuario_id
             WHERE fi.familia_id = :familia_id
             ORDER BY fi.id DESC'
        );
        $statement->execute(['familia_id' => $familyId]);
        return $statement->fetchAll();
    }

    public function preview(string $token): array
    {
        $invitation = $this->findByToken($token);
        $this->assertPending($invitation);
        return $invitation;
    }

    public function accept(string $token, int $userId): array
    {
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }
        try {
            $hash = $this->tokenHash($token);
            $statement = $this->database->prepare(
                'SELECT fi.*, f.nombre AS familia_nombre, f.archivada_at,
                        e.codigo AS estado_codigo, t.codigo AS rol_codigo
                 FROM familia_invitaciones fi
                 INNER JOIN familias f ON f.id = fi.familia_id
                 INNER JOIN estados e ON e.id = fi.estado_id
                 INNER JOIN tipos t ON t.id = fi.tipo_rol_id
                 WHERE fi.token_hash = :token_hash FOR UPDATE'
            );
            $statement->execute(['token_hash' => $hash]);
            $invitation = $statement->fetch();
            if (!is_array($invitation)) {
                throw new \InvalidArgumentException('La invitación no existe o el enlace está incompleto.');
            }
            $this->assertPending($invitation);
            if ($invitation['rol_codigo'] !== 'FAMILIAR') {
                throw new \RuntimeException('El enlace no contiene un rol permitido.');
            }
            $existing = $this->database->prepare(
                'SELECT 1 FROM familia_usuarios WHERE familia_id = :familia_id AND usuario_id = :usuario_id'
            );
            $existing->execute(['familia_id' => $invitation['familia_id'], 'usuario_id' => $userId]);
            $alreadyMember = $existing->fetchColumn() !== false;
            if (!$alreadyMember) {
                $member = $this->database->prepare(
                    'INSERT INTO familia_usuarios (familia_id, usuario_id, tipo_rol_id)
                     VALUES (:familia_id, :usuario_id, :rol_id)'
                );
                $member->execute([
                    'familia_id' => $invitation['familia_id'],
                    'usuario_id' => $userId,
                    'rol_id' => $invitation['tipo_rol_id'],
                ]);
            }
            $update = $this->database->prepare(
                'UPDATE familia_invitaciones
                 SET estado_id = :estado_id, aceptada_por_usuario_id = :usuario_id,
                     aceptada_at = UTC_TIMESTAMP()
                 WHERE id = :id AND estado_id = :pendiente_id'
            );
            $update->execute([
                'estado_id' => $this->stateId('ACEPTADA'),
                'usuario_id' => $userId,
                'id' => $invitation['id'],
                'pendiente_id' => $this->stateId('PENDIENTE'),
            ]);
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('La invitación ya fue utilizada.');
            }
            if ($ownsTransaction) {
                $this->database->commit();
            }
            return [
                'familia_id' => (int) $invitation['familia_id'],
                'familia_nombre' => $invitation['familia_nombre'],
                'ya_era_integrante' => $alreadyMember,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function revoke(int $familyId, int $invitationId, int $userId): void
    {
        $this->requireAdministrator($familyId, $userId);
        $statement = $this->database->prepare(
            'UPDATE familia_invitaciones SET estado_id = :revocada, revocada_at = UTC_TIMESTAMP()
             WHERE id = :id AND familia_id = :familia_id AND estado_id = :pendiente'
        );
        $statement->execute([
            'revocada' => $this->stateId('REVOCADA'),
            'id' => $invitationId,
            'familia_id' => $familyId,
            'pendiente' => $this->stateId('PENDIENTE'),
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('La invitación no está pendiente o no pertenece a esta familia.');
        }
    }

    public function user(int $userId): array
    {
        $statement = $this->database->prepare('SELECT id, nombre, email FROM usuarios WHERE id = :id AND activo = 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            throw new \RuntimeException('La cuenta ya no está disponible.');
        }
        return $user;
    }

    private function findByToken(string $token): array
    {
        $statement = $this->database->prepare(
            'SELECT fi.id, fi.familia_id, fi.expira_at, fi.aceptada_at, fi.revocada_at,
                    f.nombre AS familia_nombre, f.archivada_at,
                    e.codigo AS estado_codigo, e.nombre AS estado_nombre,
                    t.codigo AS rol_codigo, t.nombre AS rol_nombre
             FROM familia_invitaciones fi
             INNER JOIN familias f ON f.id = fi.familia_id
             INNER JOIN estados e ON e.id = fi.estado_id
             INNER JOIN tipos t ON t.id = fi.tipo_rol_id
             WHERE fi.token_hash = :token_hash LIMIT 1'
        );
        $statement->execute(['token_hash' => $this->tokenHash($token)]);
        $invitation = $statement->fetch();
        if (!is_array($invitation)) {
            throw new \InvalidArgumentException('La invitación no existe o el enlace está incompleto.');
        }
        return $invitation;
    }

    private function assertPending(array $invitation): void
    {
        if ($invitation['archivada_at'] !== null) {
            throw new \RuntimeException('La familia de esta invitación está archivada.');
        }
        if ($invitation['estado_codigo'] !== 'PENDIENTE') {
            $messages = [
                'ACEPTADA' => 'Esta invitación ya fue utilizada.',
                'REVOCADA' => 'Esta invitación fue revocada.',
                'VENCIDA' => 'Esta invitación está vencida.',
            ];
            throw new \RuntimeException($messages[$invitation['estado_codigo']] ?? 'La invitación no está disponible.');
        }
        if ((string) $invitation['expira_at'] <= gmdate('Y-m-d H:i:s')) {
            $update = $this->database->prepare(
                'UPDATE familia_invitaciones SET estado_id = :vencida
                 WHERE id = :id AND estado_id = :pendiente'
            );
            $update->execute([
                'vencida' => $this->stateId('VENCIDA'),
                'id' => $invitation['id'],
                'pendiente' => $this->stateId('PENDIENTE'),
            ]);
            throw new \RuntimeException('Esta invitación está vencida.');
        }
    }

    private function expirePending(int $familyId): void
    {
        $statement = $this->database->prepare(
            'UPDATE familia_invitaciones SET estado_id = :vencida
             WHERE familia_id = :familia_id AND estado_id = :pendiente AND expira_at <= UTC_TIMESTAMP()'
        );
        $statement->execute([
            'vencida' => $this->stateId('VENCIDA'),
            'familia_id' => $familyId,
            'pendiente' => $this->stateId('PENDIENTE'),
        ]);
    }

    private function requireAdministrator(int $familyId, int $userId): array
    {
        $statement = $this->database->prepare(
            "SELECT f.id, f.nombre FROM familia_usuarios fu
             INNER JOIN familias f ON f.id = fu.familia_id
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             WHERE fu.familia_id = :familia_id AND fu.usuario_id = :usuario_id
               AND t.codigo = 'ADMINISTRADOR' AND f.archivada_at IS NULL"
        );
        $statement->execute(['familia_id' => $familyId, 'usuario_id' => $userId]);
        $family = $statement->fetch();
        if (!is_array($family)) {
            throw new \RuntimeException('Esta acción requiere el rol de administrador.');
        }
        return $family;
    }

    private function roleId(string $code): int
    {
        return $this->catalogId('tipos', 'MIEMBRO_FAMILIA', $code);
    }

    private function stateId(string $code): int
    {
        return $this->catalogId('estados', 'INVITACION', $code);
    }

    private function catalogId(string $table, string $process, string $code): int
    {
        $statement = $this->database->prepare(
            "SELECT c.id FROM {$table} c INNER JOIN procesos p ON p.id = c.proceso_id
             WHERE p.codigo = :proceso AND c.codigo = :codigo AND c.activo = 1 LIMIT 1"
        );
        $statement->execute(['proceso' => $process, 'codigo' => $code]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException("No existe el catálogo {$process}:{$code}.");
        }
        return (int) $id;
    }

    private function tokenHash(string $token): string
    {
        $token = trim($token);
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            throw new \InvalidArgumentException('La invitación no existe o el enlace está incompleto.');
        }
        return hash('sha256', $token);
    }
}
