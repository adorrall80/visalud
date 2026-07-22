<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\DocumentoController;
use App\Services\AtencionService;
use App\Services\DocumentoService;
use App\Services\PersonaContext;
use App\Services\PersonaService;
use PDO;
use PHPUnit\Framework\TestCase;

final class DocumentFormOriginTest extends TestCase
{
    private PDO $database;
    private array $sessionBackup;
    private array $getBackup;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->getBackup = $_GET;
        $_SESSION = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        if (isset($this->database) && $this->database->inTransaction()) {
            $this->database->rollBack();
        }
        $_SESSION = $this->sessionBackup;
        $_GET = $this->getBackup;
    }

    public function testOptionalRelationshipBlockIsHiddenAndAttentionOriginIsAutomatic(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->database->beginTransaction();
        $source = $this->database->query(
            'SELECT p.id AS persona_id, p.familia_id, fu.usuario_id
             FROM personas p
             INNER JOIN familia_usuarios fu ON fu.familia_id = p.familia_id
             ORDER BY p.id DESC LIMIT 1'
        )->fetch();
        self::assertIsArray($source);
        $attentionId = $this->attention((int) $source['persona_id'], (int) $source['usuario_id']);

        $session = new Session();
        $session->put('user_id', (int) $source['usuario_id']);
        $session->put('family_id', (int) $source['familia_id']);
        $context = new PersonaContext(new PersonaService($connection), $session);
        $context->select((int) $source['persona_id']);
        $controller = new DocumentoController(
            new DocumentoService($connection),
            new AtencionService($connection),
            $session,
            new View(dirname(__DIR__, 2) . '/resources/views', $session, $context),
            new Validator(),
            $context,
        );

        $_GET = ['atencion_id' => (string) $attentionId];
        $fromAttention = $controller->create(new Request())->content();
        self::assertStringNotContainsString('Relación opcional', $fromAttention);
        self::assertStringNotContainsString('Definida por el selector superior', $fromAttention);
        self::assertStringNotContainsString('class="field context-person"', $fromAttention);
        self::assertStringNotContainsString('name="medicamento_id"', $fromAttention);
        self::assertStringContainsString(
            'name="atencion_id" value="' . $attentionId . '"',
            $fromAttention,
        );
        self::assertStringContainsString('name="fecha_documento" value="2026-07-19"', $fromAttention);

        $_GET = [];
        $direct = $controller->create(new Request())->content();
        self::assertStringNotContainsString('name="atencion_id"', $direct);
        $timezone = new \DateTimeZone((string) env('APP_TIMEZONE', 'America/Santiago'));
        $today = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d');
        self::assertStringContainsString('name="fecha_documento" value="' . $today . '"', $direct);
    }

    private function attention(int $personId, int $userId): int
    {
        $statement = $this->database->prepare(
            "INSERT INTO atenciones
             (persona_id, tipo_id, estado_id, registrado_por_usuario_id, fecha_hora, motivo)
             VALUES (:persona_id,
                     (SELECT t.id FROM tipos t INNER JOIN procesos p ON p.id=t.proceso_id WHERE p.codigo='ATENCION' AND t.codigo='CONSULTA_MEDICA' LIMIT 1),
                     (SELECT e.id FROM estados e INNER JOIN procesos p ON p.id=e.proceso_id WHERE p.codigo='ATENCION' AND e.codigo='REALIZADA' LIMIT 1),
                     :usuario_id, '2026-07-19 13:00:00', 'Origen temporal de documento')"
        );
        $statement->execute(['persona_id' => $personId, 'usuario_id' => $userId]);
        return (int) $this->database->lastInsertId();
    }
}
