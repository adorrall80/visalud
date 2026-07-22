<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\FamiliaController;
use App\Services\AtencionService;
use App\Services\DocumentoService;
use App\Services\FamiliaService;
use App\Services\MedicamentoService;
use App\Services\InvitacionService;
use App\Services\PersonaContext;
use App\Services\PersonaService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ActivePersonContextTest extends TestCase
{
    private PDO $database;
    private Database $connection;
    private Session $session;
    private PersonaContext $context;
    private array $sessionBackup;
    private array $postBackup;
    private int $fixtureUserId;
    private array $fixtureFamilyIds = [];

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->postBackup = $_POST;
        $_SESSION = [];
        $_POST = [];
        $this->connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $this->connection->connection();
        $this->database->beginTransaction();
        $this->createTemporaryFamilyContext();
        $this->session = new Session();
        $this->context = new PersonaContext(new PersonaService($this->connection), $this->session);
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) { $this->database->rollBack(); }
        $_SESSION = $this->sessionBackup;
        $_POST = $this->postBackup;
    }

    public function testSelectsOnlyPeopleFromTheActiveFamily(): void
    {
        $family = $this->familyWithAtLeastTwoPeople();
        $this->session->put('family_id', $family['familia_id']);
        $selected = $this->context->select($family['persona_id']);
        self::assertSame($family['persona_id'], (int) $selected['id']);
        self::assertSame($family['persona_id'], (int) $this->context->active()['id']);

        $foreign = $this->database->query(
            'SELECT id FROM personas WHERE familia_id <> ' . (int) $family['familia_id'] . ' ORDER BY id LIMIT 1'
        )->fetchColumn();
        self::assertNotFalse($foreign);
        $this->expectException(\InvalidArgumentException::class);
        $this->context->select((int) $foreign);
    }

    public function testAutomaticallySelectsTheOnlyPersonAndCanClearContext(): void
    {
        $this->database->exec("INSERT INTO familias (nombre) VALUES ('Familia contexto temporal')");
        $familyId = (int) $this->database->lastInsertId();
        $statement = $this->database->prepare("INSERT INTO personas (familia_id, nombre, identificacion) VALUES (:familia, 'Única Temporal', :identificacion)");
        $statement->execute(['familia' => $familyId, 'identificacion' => 'CTX-' . bin2hex(random_bytes(4))]);
        $personId = (int) $this->database->lastInsertId();
        $this->session->put('family_id', $familyId);

        self::assertSame($personId, (int) $this->context->active()['id']);
        $this->context->clear();
        self::assertSame($personId, (int) $this->context->active()['id'], 'Una única persona se selecciona nuevamente de forma automática.');
    }

    public function testAttentionControllerIgnoresManipulatedPersonId(): void
    {
        $family = $this->familyWithAtLeastTwoPeople();
        $this->session->put('family_id', $family['familia_id']);
        $userId = (int) $this->database->query(
            'SELECT usuario_id FROM familia_usuarios WHERE familia_id = ' . (int) $family['familia_id'] . ' LIMIT 1'
        )->fetchColumn();
        $this->session->put('user_id', $userId);
        $this->context->select($family['persona_id']);
        $otherId = (int) $this->database->query(
            'SELECT id FROM personas WHERE familia_id = ' . (int) $family['familia_id'] . ' AND id <> ' . (int) $family['persona_id'] . ' LIMIT 1'
        )->fetchColumn();
        $typeId = $this->catalogId('tipos', 'ATENCION', 'CONSULTA_MEDICA');
        $stateId = $this->catalogId('estados', 'ATENCION', 'REALIZADA');
        $_POST = [
            'persona_id' => (string) $otherId,
            'tipo_id' => (string) $typeId,
            'estado_id' => (string) $stateId,
            'fecha_hora' => '2026-07-21T12:00',
            'motivo' => 'Prueba de contexto activo',
        ];
        $controller = new AtencionController(
            new AtencionService($this->connection), $this->session,
            new View(dirname(__DIR__, 2) . '/resources/views', $this->session, $this->context),
            new Validator(), $this->context, new MedicamentoService($this->connection),
            new DocumentoService($this->connection),
        );
        $response = $controller->store(new Request());
        self::assertSame(302, $response->status());
        $id = (int) basename((string) $response->header('Location'));
        $personId = (int) $this->database->query('SELECT persona_id FROM atenciones WHERE id = ' . $id)->fetchColumn();
        self::assertSame($family['persona_id'], $personId);
        self::assertNotSame($otherId, $personId);
    }

    public function testArchivingActiveFamilyClearsPersonAndSelectsAnotherFamily(): void
    {
        $familyId = $this->fixtureFamilyIds[0];
        $userId = $this->fixtureUserId;
        $personId = (int) $this->database->query(
            'SELECT id FROM personas WHERE familia_id = ' . $familyId . ' ORDER BY id LIMIT 1'
        )->fetchColumn();
        self::assertGreaterThan(0, $personId);
        $this->session->put('user_id', $userId);
        $this->session->put('family_id', $familyId);
        $this->context->select($personId);

        $service = new FamiliaService($this->connection);
        $controller = new FamiliaController(
            $service,
            $this->session,
            new View(dirname(__DIR__, 2) . '/resources/views', $this->session, $this->context),
            new Validator(),
            $this->context,
            new InvitacionService($this->connection),
        );
        $response = $controller->archive(new Request(), (string) $familyId);

        self::assertSame(302, $response->status());
        self::assertSame('/familias', $response->header('Location'));
        self::assertNotSame($familyId, (int) $this->session->get('family_id', 0));
        self::assertSame(0, (int) $this->session->get('persona_id_activa', 0));
        self::assertNull($service->findForUser($familyId, $userId));

        $controller->restore(new Request(), (string) $familyId);
        self::assertNotNull($service->findForUser($familyId, $userId));
    }

    private function familyWithAtLeastTwoPeople(): array
    {
        $row = $this->database->query(
            'SELECT p.familia_id, MIN(p.id) AS persona_id FROM personas p
             GROUP BY p.familia_id HAVING COUNT(*) >= 2 ORDER BY p.familia_id DESC LIMIT 1'
        )->fetch();
        self::assertIsArray($row);
        return ['familia_id' => (int) $row['familia_id'], 'persona_id' => (int) $row['persona_id']];
    }

    private function createTemporaryFamilyContext(): void
    {
        $unique = bin2hex(random_bytes(5));
        $user = $this->database->prepare(
            'INSERT INTO usuarios (nombre, email, google_sub, email_verificado_at)
             VALUES (:nombre, :email, :google_sub, UTC_TIMESTAMP())'
        );
        $user->execute([
            'nombre' => 'Administrador contexto',
            'email' => 'contexto-' . $unique . '@example.test',
            'google_sub' => 'contexto-google-' . $unique,
        ]);
        $userId = (int) $this->database->lastInsertId();
        $this->fixtureUserId = $userId;
        $roleId = $this->catalogId('tipos', 'MIEMBRO_FAMILIA', 'ADMINISTRADOR');
        $member = $this->database->prepare(
            'INSERT INTO familia_usuarios (familia_id, usuario_id, tipo_rol_id)
             VALUES (:familia_id, :usuario_id, :tipo_rol_id)'
        );
        $person = $this->database->prepare(
            'INSERT INTO personas (familia_id, nombre, identificacion)
             VALUES (:familia_id, :nombre, :identificacion)'
        );
        foreach ([1, 2] as $familyNumber) {
            $family = $this->database->prepare('INSERT INTO familias (nombre) VALUES (:nombre)');
            $family->execute(['nombre' => 'Familia contexto ' . $unique . '-' . $familyNumber]);
            $familyId = (int) $this->database->lastInsertId();
            $this->fixtureFamilyIds[] = $familyId;
            $member->execute(['familia_id' => $familyId, 'usuario_id' => $userId, 'tipo_rol_id' => $roleId]);
            $person->execute([
                'familia_id' => $familyId,
                'nombre' => 'Persona contexto ' . $familyNumber . '-1',
                'identificacion' => 'CTX-' . $unique . '-' . $familyNumber . '-1',
            ]);
            if ($familyNumber === 1) {
                $person->execute([
                    'familia_id' => $familyId,
                    'nombre' => 'Persona contexto 1-2',
                    'identificacion' => 'CTX-' . $unique . '-1-2',
                ]);
            }
        }
    }

    private function catalogId(string $table, string $process, string $code): int
    {
        $statement = $this->database->prepare(
            "SELECT c.id FROM {$table} c INNER JOIN procesos p ON p.id = c.proceso_id
             WHERE p.codigo = :proceso AND c.codigo = :codigo"
        );
        $statement->execute(['proceso' => $process, 'codigo' => $code]);
        return (int) $statement->fetchColumn();
    }
}
