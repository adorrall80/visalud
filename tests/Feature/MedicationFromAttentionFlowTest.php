<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\MedicamentoController;
use App\Services\AtencionService;
use App\Services\DocumentoService;
use App\Services\MedicamentoService;
use App\Services\PersonaContext;
use App\Services\PersonaService;
use PDO;
use PHPUnit\Framework\TestCase;

final class MedicationFromAttentionFlowTest extends TestCase
{
    private Database $connection;
    private PDO $database;
    private Session $session;
    private PersonaContext $context;
    private array $sessionBackup;
    private array $postBackup;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $this->postBackup = $_POST;
        $_SESSION = [];
        $_POST = [];
        $this->connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $this->connection->connection();
        $this->database->beginTransaction();
        $this->session = new Session();
        $this->context = new PersonaContext(new PersonaService($this->connection), $this->session);
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
        $_SESSION = $this->sessionBackup;
        $_POST = $this->postBackup;
    }

    public function testReturnsToAttentionAndShowsAddedMedicationModal(): void
    {
        $context = $this->database->query(
            'SELECT p.id persona_id, p.familia_id, fu.usuario_id
             FROM personas p INNER JOIN familia_usuarios fu ON fu.familia_id = p.familia_id
             ORDER BY p.id DESC LIMIT 1'
        )->fetch();
        self::assertIsArray($context);
        $personId = (int) $context['persona_id'];
        $familyId = (int) $context['familia_id'];
        $userId = (int) $context['usuario_id'];
        $attentionId = $this->attention($personId, $userId);
        $documentId = $this->document($personId, $attentionId, $userId);
        $this->session->put('user_id', $userId);
        $this->session->put('family_id', $familyId);
        $this->context->select($personId);
        $_POST = [
            'persona_id' => (string) $personId,
            'atencion_id' => (string) $attentionId,
            'estado_id' => (string) $this->catalogId('estados', 'ACTIVO'),
            'nombre' => 'Medicamento de flujo temporal',
            'dosis' => '25 mg, 1 comprimido',
            'frecuencia' => 'Una vez al día',
            'fecha_inicio' => '2026-07-22',
            'fecha_termino' => '',
            'indicaciones' => 'Prueba temporal',
            'horarios' => ['09:00'],
        ];
        $view = new View(dirname(__DIR__, 2) . '/resources/views', $this->session, $this->context);
        $medications = new MedicamentoService($this->connection);
        $attentions = new AtencionService($this->connection);
        $controller = new MedicamentoController(
            $medications, $attentions, $this->session, $view, new Validator(), $this->context,
        );

        $response = $controller->store(new Request());

        self::assertSame(302, $response->status());
        self::assertSame('/atenciones/' . $attentionId, $response->header('Location'));
        $flash = $_SESSION['_flash_new']['medicamento_agregado'] ?? null;
        self::assertIsArray($flash);
        self::assertSame('Medicamento de flujo temporal', $flash['nombre']);
        self::assertSame('25 mg, 1 comprimido', $flash['dosis']);
        self::assertSame($attentionId, (int) $medications->findForFamily((int) $flash['id'], $familyId)['atencion_id']);

        $_SESSION['_flash_old'] = $_SESSION['_flash_new'];
        $_SESSION['_flash_new'] = [];
        $attentionController = new AtencionController(
            $attentions, $this->session, $view, new Validator(), $this->context, $medications,
            new DocumentoService($this->connection),
        );
        $html = $attentionController->show(new Request(), (string) $attentionId)->content();
        self::assertStringContainsString('data-auto-open-modal', $html);
        self::assertStringContainsString('Medicamento de flujo temporal', $html);
        self::assertStringContainsString('25 mg, 1 comprimido', $html);
        self::assertStringContainsString('Documentos de esta atención (1)', $html);
        self::assertStringContainsString('Informe asociado temporal.png', $html);
        self::assertStringContainsString('data-appointment-modal="pdf-note-modal"', $html);
        self::assertStringContainsString('Info adicional', $html);
        self::assertStringContainsString('Texto copiado o escrito', $html);
        self::assertStringContainsString('name="texto_final"', $html);
        self::assertStringContainsString('data-appointment-modal="ai-prompt-modal"', $html);
        self::assertStringContainsString('Actúa como asistente de salud familiar', $html);
        self::assertStringContainsString('Medicamento de flujo temporal', $html);
        self::assertStringContainsString('Doc adjuntos:', $html);
        self::assertStringContainsString('Informe asociado temporal.png', $html);
        self::assertStringContainsString('/documentos/' . $documentId . '/edit', $html);
        self::assertStringContainsString('/documentos/' . $documentId . '/download', $html);
        self::assertStringContainsString('action="/documentos/' . $documentId . '"', $html);
        self::assertStringContainsString('/documentos/create?atencion_id=' . $attentionId, $html);
        self::assertStringContainsString('data-appointment-modal="document-preview-' . $documentId . '"', $html);
        self::assertStringContainsString('id="document-preview-' . $documentId . '"', $html);

        $_SESSION['_flash_old'] = ['documento_agregado' => [
            'id' => $documentId,
            'nombre' => 'Informe asociado temporal.png',
            'tipo_nombre' => 'Informe',
            'mime_type' => 'image/png',
            'formato' => 'Imagen PNG',
        ]];
        $documentHtml = $attentionController->show(new Request(), (string) $attentionId)->content();
        self::assertStringContainsString('Documento agregado', $documentHtml);
        self::assertStringContainsString('Tipo: Informe', $documentHtml);
        self::assertStringContainsString('Formato: Imagen PNG', $documentHtml);
        self::assertStringContainsString('data-appointment-modal="document-preview-' . $documentId . '"', $documentHtml);
    }

    public function testAttentionDocumentActionsHideDeleteForNonUploaderFamilyMember(): void
    {
        $context = $this->database->query(
            'SELECT p.id persona_id, p.familia_id, fu.usuario_id
             FROM personas p INNER JOIN familia_usuarios fu ON fu.familia_id = p.familia_id
             ORDER BY p.id DESC LIMIT 1'
        )->fetch();
        self::assertIsArray($context);
        $personId = (int) $context['persona_id'];
        $familyId = (int) $context['familia_id'];
        $uploaderId = (int) $context['usuario_id'];
        $memberId = $this->familyMember($familyId);
        $attentionId = $this->attention($personId, $uploaderId);
        $documentId = $this->document($personId, $attentionId, $uploaderId);
        $this->session->put('user_id', $memberId);
        $this->session->put('family_id', $familyId);
        $this->context->select($personId);
        $view = new View(dirname(__DIR__, 2) . '/resources/views', $this->session, $this->context);
        $controller = new AtencionController(
            new AtencionService($this->connection), $this->session, $view, new Validator(), $this->context,
            new MedicamentoService($this->connection), new DocumentoService($this->connection),
        );

        $html = $controller->show(new Request(), (string) $attentionId)->content();

        self::assertStringContainsString('/documentos/' . $documentId . '/edit', $html);
        self::assertStringContainsString('/documentos/' . $documentId . '/download', $html);
        self::assertStringNotContainsString('action="/documentos/' . $documentId . '"', $html);
    }

    private function attention(int $personId, int $userId): int
    {
        $statement = $this->database->prepare(
            "INSERT INTO atenciones
             (persona_id, tipo_id, estado_id, registrado_por_usuario_id, fecha_hora, motivo)
             VALUES (:persona, :tipo, :estado, :usuario, '2026-07-22 13:00:00', 'Atención temporal de flujo')"
        );
        $statement->execute([
            'persona' => $personId,
            'tipo' => $this->catalogId('tipos', 'CONSULTA_MEDICA'),
            'estado' => $this->catalogId('estados', 'REALIZADA'),
            'usuario' => $userId,
        ]);
        return (int) $this->database->lastInsertId();
    }

    private function document(int $personId, int $attentionId, int $userId): int
    {
        $statement = $this->database->prepare(
            "INSERT INTO documentos
             (persona_id, atencion_id, tipo_id, subido_por_usuario_id, nombre, archivo_ruta, mime_type)
             VALUES (:persona_id, :atencion_id, :tipo_id, :usuario_id, :nombre, :ruta, 'image/png')"
        );
        $statement->execute([
            'persona_id' => $personId,
            'atencion_id' => $attentionId,
            'tipo_id' => $this->catalogId('tipos', 'INFORME'),
            'usuario_id' => $userId,
            'nombre' => 'Informe asociado temporal.png',
            'ruta' => 'tests/' . bin2hex(random_bytes(12)) . '.png',
        ]);
        return (int) $this->database->lastInsertId();
    }

    private function familyMember(int $familyId): int
    {
        $unique = bin2hex(random_bytes(6));
        $statement = $this->database->prepare(
            'INSERT INTO usuarios (nombre, email, google_sub, email_verificado_at)
             VALUES (:nombre, :email, :google_sub, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'nombre' => 'Familiar temporal',
            'email' => 'familiar-temporal-' . $unique . '@example.test',
            'google_sub' => 'familiar-temporal-' . $unique,
        ]);
        $memberId = (int) $this->database->lastInsertId();
        $this->database->prepare(
            "INSERT INTO familia_usuarios (familia_id, usuario_id, tipo_rol_id)
             VALUES (:familia_id, :usuario_id,
                     (SELECT t.id FROM tipos t INNER JOIN procesos p ON p.id = t.proceso_id WHERE p.codigo = 'MIEMBRO_FAMILIA' AND t.codigo = 'FAMILIAR' LIMIT 1))"
        )->execute(['familia_id' => $familyId, 'usuario_id' => $memberId]);
        return $memberId;
    }

    private function catalogId(string $table, string $code): int
    {
        $statement = $this->database->prepare("SELECT id FROM {$table} WHERE codigo = :codigo LIMIT 1");
        $statement->execute(['codigo' => $code]);
        return (int) $statement->fetchColumn();
    }
}
