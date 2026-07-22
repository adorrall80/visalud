<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\FamiliaService;
use App\Services\InvitacionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class InvitationServiceTest extends TestCase
{
    private Database $connection;
    private PDO $database;
    private InvitacionService $invitations;
    private array $administrator;

    protected function setUp(): void
    {
        $this->connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $this->connection->connection();
        $this->database->beginTransaction();
        $this->invitations = new InvitacionService($this->connection);
        $administrator = $this->database->query(
            "SELECT fu.familia_id, fu.usuario_id
             FROM familia_usuarios fu
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             INNER JOIN familias f ON f.id = fu.familia_id
             WHERE t.codigo = 'ADMINISTRADOR' AND f.archivada_at IS NULL
             ORDER BY fu.familia_id LIMIT 1"
        )->fetch();
        self::assertIsArray($administrator, 'Debe existir una familia activa con administrador.');
        $this->administrator = $administrator;
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    public function testAdministratorGeneratesOneTimeHashedFamiliarInvitation(): void
    {
        $invitation = $this->createInvitation();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $invitation['token']);
        $row = $this->invitationRow((int) $invitation['id']);
        self::assertSame(hash('sha256', $invitation['token']), $row['token_hash']);
        self::assertNotSame($invitation['token'], $row['token_hash']);
        self::assertSame('PENDIENTE', $row['estado_codigo']);
        self::assertSame('FAMILIAR', $row['rol_codigo']);

        $preview = $this->invitations->preview($invitation['token']);
        self::assertSame((int) $this->administrator['familia_id'], (int) $preview['familia_id']);
        self::assertSame('FAMILIAR', $preview['rol_codigo']);
    }

    public function testAcceptanceAssociatesSelectedGoogleUserAndConsumesToken(): void
    {
        $userId = $this->newUser('invitado');
        $invitation = $this->createInvitation();

        $result = $this->invitations->accept($invitation['token'], $userId);

        self::assertFalse($result['ya_era_integrante']);
        $membership = $this->database->prepare(
            'SELECT t.codigo FROM familia_usuarios fu
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             WHERE fu.familia_id = :familia_id AND fu.usuario_id = :usuario_id'
        );
        $membership->execute([
            'familia_id' => $this->administrator['familia_id'],
            'usuario_id' => $userId,
        ]);
        self::assertSame('FAMILIAR', $membership->fetchColumn());
        self::assertSame('ACEPTADA', $this->invitationRow((int) $invitation['id'])['estado_codigo']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('utilizada');
        $this->invitations->accept($invitation['token'], $this->newUser('segundo'));
    }

    public function testExistingAdministratorIsNeverDowngradedWhenAccepting(): void
    {
        $invitation = $this->createInvitation();
        $result = $this->invitations->accept($invitation['token'], (int) $this->administrator['usuario_id']);

        self::assertTrue($result['ya_era_integrante']);
        $statement = $this->database->prepare(
            'SELECT t.codigo FROM familia_usuarios fu
             INNER JOIN tipos t ON t.id = fu.tipo_rol_id
             WHERE fu.familia_id = :familia_id AND fu.usuario_id = :usuario_id'
        );
        $statement->execute($this->administrator);
        self::assertSame('ADMINISTRADOR', $statement->fetchColumn());
    }

    public function testRevokedAndExpiredInvitationsAreRejected(): void
    {
        $revoked = $this->createInvitation();
        $this->invitations->revoke(
            (int) $this->administrator['familia_id'],
            (int) $revoked['id'],
            (int) $this->administrator['usuario_id'],
        );
        try {
            $this->invitations->preview($revoked['token']);
            self::fail('Una invitaciÃ³n revocada no debe poder abrirse.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('revocada', $exception->getMessage());
        }

        $expired = $this->createInvitation();
        $statement = $this->database->prepare(
            "UPDATE familia_invitaciones SET expira_at = '2000-01-01 00:00:00' WHERE id = :id"
        );
        $statement->execute(['id' => $expired['id']]);
        try {
            $this->invitations->preview($expired['token']);
            self::fail('Una invitaciÃ³n vencida no debe poder abrirse.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('vencida', $exception->getMessage());
        }
        self::assertSame('VENCIDA', $this->invitationRow((int) $expired['id'])['estado_codigo']);
    }

    public function testOnlyAdministratorCanGenerateInvitation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('administrador');
        $this->invitations->create((int) $this->administrator['familia_id'], $this->newUser('ajeno'));
    }

    public function testArchivingFamilyRevokesPendingInvitations(): void
    {
        $invitation = $this->createInvitation();
        $families = new FamiliaService($this->connection);

        $families->archive(
            (int) $this->administrator['familia_id'],
            (int) $this->administrator['usuario_id'],
        );

        self::assertSame('REVOCADA', $this->invitationRow((int) $invitation['id'])['estado_codigo']);
        self::assertNotNull($this->invitationRow((int) $invitation['id'])['revocada_at']);
    }

    private function createInvitation(): array
    {
        return $this->invitations->create(
            (int) $this->administrator['familia_id'],
            (int) $this->administrator['usuario_id'],
        );
    }

    private function newUser(string $prefix): int
    {
        $unique = $prefix . '-' . bin2hex(random_bytes(6));
        $statement = $this->database->prepare(
            'INSERT INTO usuarios (nombre, email, google_sub, email_verificado_at)
             VALUES (:nombre, :email, :google_sub, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'nombre' => 'Usuario de prueba',
            'email' => $unique . '@example.test',
            'google_sub' => 'test-google-' . $unique,
        ]);
        return (int) $this->database->lastInsertId();
    }

    private function invitationRow(int $id): array
    {
        $statement = $this->database->prepare(
            'SELECT fi.token_hash, fi.revocada_at, e.codigo AS estado_codigo, t.codigo AS rol_codigo
             FROM familia_invitaciones fi
             INNER JOIN estados e ON e.id = fi.estado_id
             INNER JOIN tipos t ON t.id = fi.tipo_rol_id
             WHERE fi.id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        self::assertIsArray($row);
        return $row;
    }
}
