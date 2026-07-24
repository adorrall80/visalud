<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\FamiliaService;
use PDO;
use PHPUnit\Framework\TestCase;

final class FamilyAccessTest extends TestCase
{
    private PDO $database;
    private FamiliaService $families;
    private string $candidateEmail;
    private int $candidateUserId;

    protected function setUp(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->families = new FamiliaService($connection);
        $this->database->beginTransaction();
        $unique = bin2hex(random_bytes(6));
        $this->candidateEmail = 'familiar-' . $unique . '@example.test';
        $candidate = $this->database->prepare(
            'INSERT INTO usuarios (nombre, email, google_sub, email_verificado_at)
             VALUES (:nombre, :email, :google_sub, UTC_TIMESTAMP())'
        );
        $candidate->execute([
            'nombre' => 'Familiar temporal',
            'email' => $this->candidateEmail,
            'google_sub' => 'familiar-temporal-' . $unique,
        ]);
        $this->candidateUserId = (int) $this->database->lastInsertId();
        $foreign = $this->database->prepare('INSERT INTO familias (nombre) VALUES (:nombre)');
        $foreign->execute(['nombre' => 'Familia ajena temporal ' . $unique]);
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    public function testAdministratorCanAssociateMemberWithoutCrossFamilyAccess(): void
    {
        $context = $this->database->query(
            "SELECT fu.familia_id, fu.usuario_id
             FROM familia_usuarios fu
             INNER JOIN usuarios u ON u.id = fu.usuario_id
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             INNER JOIN familias f ON f.id = fu.familia_id
             WHERE u.google_sub <> 'demo-google-sub-local'
               AND t.codigo = 'ADMINISTRADOR'
               AND f.archivada_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM familia_usuarios demo_fu
                   INNER JOIN usuarios demo_u ON demo_u.id = demo_fu.usuario_id
                   WHERE demo_fu.familia_id = fu.familia_id AND demo_u.email = 'demo@example.test'
               )
             ORDER BY fu.familia_id LIMIT 1"
        )->fetch();
        self::assertIsArray($context, 'Debe existir un administrador con una familia disponible.');
        $realUserId = (int) $context['usuario_id'];
        $familyId = (int) $context['familia_id'];
        self::assertNotNull($this->families->findForUser($familyId, $realUserId));

        $foreign = $this->database->prepare(
            'SELECT f.id FROM familias f
             WHERE f.id <> :familia_id
               AND NOT EXISTS (SELECT 1 FROM familia_usuarios fu WHERE fu.familia_id = f.id AND fu.usuario_id = :usuario_id)
             ORDER BY f.id LIMIT 1'
        );
        $foreign->execute(['familia_id' => $familyId, 'usuario_id' => $realUserId]);
        $foreignFamilyId = (int) $foreign->fetchColumn();
        self::assertNull($this->families->findForUser($foreignFamilyId, $realUserId));

        $membersBefore = count($this->families->members($familyId, $realUserId));
        $this->families->addMemberByEmail($familyId, $realUserId, $this->candidateEmail, 'FAMILIAR');
        self::assertCount($membersBefore + 1, $this->families->members($familyId, $realUserId));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('administrador');
        $this->families->addMemberByEmail($familyId, $this->candidateUserId, $this->candidateEmail, 'ADMINISTRADOR');
    }

    public function testAdministratorCanArchiveAndRestoreWithoutLosingClinicalData(): void
    {
        $context = $this->database->query(
            "SELECT fu.familia_id, fu.usuario_id
             FROM familia_usuarios fu
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             INNER JOIN familias f ON f.id = fu.familia_id
             WHERE t.codigo = 'ADMINISTRADOR' AND f.archivada_at IS NULL
             ORDER BY fu.familia_id LIMIT 1"
        )->fetch();
        self::assertIsArray($context);
        $familyId = (int) $context['familia_id'];
        $userId = (int) $context['usuario_id'];
        $before = $this->clinicalCounts($familyId);

        $this->families->archive($familyId, $userId);

        self::assertNull($this->families->findForUser($familyId, $userId));
        self::assertNotNull($this->families->findForUser($familyId, $userId, true));
        self::assertContains($familyId, array_map(
            static fn (array $family): int => (int) $family['id'],
            $this->families->archivedForUser($userId),
        ));
        self::assertSame($before, $this->clinicalCounts($familyId));

        $this->families->restore($familyId, $userId);

        self::assertNotNull($this->families->findForUser($familyId, $userId));
        self::assertSame($before, $this->clinicalCounts($familyId));
    }

    public function testAdministratorCanChangeMemberRoleButCannotLeaveFamilyWithoutAdministrator(): void
    {
        $context = $this->database->query(
            "SELECT fu.familia_id, fu.usuario_id
             FROM familia_usuarios fu
             INNER JOIN usuarios u ON u.id = fu.usuario_id
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             INNER JOIN familias f ON f.id = fu.familia_id
             WHERE u.google_sub <> 'demo-google-sub-local'
               AND t.codigo = 'ADMINISTRADOR'
               AND f.archivada_at IS NULL
             ORDER BY fu.familia_id LIMIT 1"
        )->fetch();
        self::assertIsArray($context);
        $familyId = (int) $context['familia_id'];
        $adminUserId = (int) $context['usuario_id'];

        $this->families->addMemberByEmail($familyId, $adminUserId, $this->candidateEmail, 'FAMILIAR');
        self::assertSame('FAMILIAR', $this->memberRole($familyId, $this->candidateUserId));

        $this->families->updateMemberRole($familyId, $adminUserId, $this->candidateUserId, 'ADMINISTRADOR');
        self::assertSame('ADMINISTRADOR', $this->memberRole($familyId, $this->candidateUserId));

        $this->families->updateMemberRole($familyId, $adminUserId, $adminUserId, 'FAMILIAR');
        self::assertSame('FAMILIAR', $this->memberRole($familyId, $adminUserId));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('administrador');
        $this->families->updateMemberRole($familyId, $this->candidateUserId, $this->candidateUserId, 'FAMILIAR');
    }

    public function testArchiveRejectsUserWithoutMembership(): void
    {
        $familyId = (int) $this->database->query(
            'SELECT id FROM familias WHERE archivada_at IS NULL ORDER BY id LIMIT 1'
        )->fetchColumn();

        $this->expectException(\RuntimeException::class);
        $this->families->archive($familyId, 0);
    }

    private function memberRole(int $familyId, int $userId): string
    {
        $statement = $this->database->prepare(
            'SELECT t.codigo
             FROM familia_usuarios fu
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             WHERE fu.familia_id = :familia_id AND fu.usuario_id = :usuario_id'
        );
        $statement->execute(['familia_id' => $familyId, 'usuario_id' => $userId]);
        return (string) $statement->fetchColumn();
    }

    private function clinicalCounts(int $familyId): array
    {
        $counts = [];
        $queries = [
            'personas' => 'SELECT COUNT(*) FROM personas WHERE familia_id = :familia_id',
            'atenciones' => 'SELECT COUNT(*) FROM atenciones a INNER JOIN personas p ON p.id = a.persona_id WHERE p.familia_id = :familia_id',
            'medicamentos' => 'SELECT COUNT(*) FROM medicamentos m INNER JOIN personas p ON p.id = m.persona_id WHERE p.familia_id = :familia_id',
            'documentos' => 'SELECT COUNT(*) FROM documentos d INNER JOIN personas p ON p.id = d.persona_id WHERE p.familia_id = :familia_id',
        ];
        foreach ($queries as $name => $query) {
            $statement = $this->database->prepare($query);
            $statement->execute(['familia_id' => $familyId]);
            $counts[$name] = (int) $statement->fetchColumn();
        }
        return $counts;
    }
}
