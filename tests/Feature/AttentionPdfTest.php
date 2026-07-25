<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Http\Controllers\AtencionController;
use App\Services\AtencionService;
use App\Services\DocumentoService;
use App\Services\MedicamentoService;
use App\Services\PersonaContext;
use App\Services\PersonaService;
use PDO;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;

final class AttentionPdfTest extends TestCase
{
    private PDO $database;
    private array $sessionBackup;
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (isset($this->database) && $this->database->inTransaction()) {
            $this->database->rollBack();
        }
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $_SESSION = $this->sessionBackup;
    }

    public function testAttentionDetailCanGeneratePdfRecord(): void
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
        $controller = new AtencionController(
            new AtencionService($connection),
            $session,
            new View(dirname(__DIR__, 2) . '/resources/views', $session, $context),
            new Validator(),
            $context,
            new MedicamentoService($connection),
            new DocumentoService($connection),
        );

        $html = $controller->show(new Request(), (string) $attentionId)->content();
        self::assertStringContainsString('/atenciones/' . $attentionId . '/ficha-pdf', $html);

        $response = $controller->fichaPdf(new Request(), (string) $attentionId);

        self::assertSame(200, $response->status());
        self::assertSame('application/pdf', $response->header('Content-Type'));
        self::assertStringContainsString('ficha-atencion-', (string) $response->header('Content-Disposition'));
        self::assertStringContainsString('-0900-id' . $attentionId . '.pdf', (string) $response->header('Content-Disposition'));
        self::assertStringStartsWith('%PDF', $response->content());
    }

    public function testPdfRecordCanIncludeImageAndReferencePdfDocuments(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->database->beginTransaction();
        $source = $this->source();
        $attentionId = $this->attention((int) $source['persona_id'], (int) $source['usuario_id']);
        $this->document((int) $source['persona_id'], $attentionId, (int) $source['usuario_id'], 'Imagen de respaldo.png', 'image/png');
        $this->document((int) $source['persona_id'], $attentionId, (int) $source['usuario_id'], 'Informe adjunto.pdf', 'application/pdf');

        $response = $this->controller($connection, $source)->fichaPdf(new Request(), (string) $attentionId);

        self::assertSame(200, $response->status());
        self::assertSame('application/pdf', $response->header('Content-Type'));
        self::assertStringStartsWith('%PDF', $response->content());
        self::assertGreaterThan(1000, strlen($response->content()));
        self::assertGreaterThanOrEqual(4, $this->pdfPageCount($response->content()));
    }

    public function testPdfRecordRejectsAttentionFromAnotherFamily(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->database->beginTransaction();
        $source = $this->source();
        $this->database->exec("INSERT INTO familias (nombre) VALUES ('Familia temporal ficha pdf')");
        $otherFamilyId = (int) $this->database->lastInsertId();
        $this->database->prepare("INSERT INTO personas (familia_id, nombre) VALUES (:familia_id, 'Persona externa ficha')")
            ->execute(['familia_id' => $otherFamilyId]);
        $otherPersonId = (int) $this->database->lastInsertId();
        $attentionId = $this->attention($otherPersonId, (int) $source['usuario_id']);

        $response = $this->controller($connection, $source)->fichaPdf(new Request(), (string) $attentionId);

        self::assertSame(404, $response->status());
    }

    public function testPdfRecordRejectsAttentionFromInactivePersonContext(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->database->beginTransaction();
        $source = $this->source();
        $this->database->prepare("INSERT INTO personas (familia_id, nombre) VALUES (:familia_id, 'Otra persona ficha')")
            ->execute(['familia_id' => (int) $source['familia_id']]);
        $otherPersonId = (int) $this->database->lastInsertId();
        $attentionId = $this->attention($otherPersonId, (int) $source['usuario_id']);

        $response = $this->controller($connection, $source)->fichaPdf(new Request(), (string) $attentionId);

        self::assertSame(302, $response->status());
        self::assertSame('/atenciones', $response->header('Location'));
    }

    private function source(): array
    {
        $source = $this->database->query(
            'SELECT p.id AS persona_id, p.familia_id, fu.usuario_id
             FROM personas p
             INNER JOIN familia_usuarios fu ON fu.familia_id = p.familia_id
             ORDER BY p.id DESC LIMIT 1'
        )->fetch();
        self::assertIsArray($source);
        return $source;
    }

    private function controller(Database $connection, array $source): AtencionController
    {
        $session = new Session();
        $session->put('user_id', (int) $source['usuario_id']);
        $session->put('family_id', (int) $source['familia_id']);
        $context = new PersonaContext(new PersonaService($connection), $session);
        $context->select((int) $source['persona_id']);
        return new AtencionController(
            new AtencionService($connection),
            $session,
            new View(dirname(__DIR__, 2) . '/resources/views', $session, $context),
            new Validator(),
            $context,
            new MedicamentoService($connection),
            new DocumentoService($connection),
        );
    }

    private function attention(int $personId, int $userId): int
    {
        $statement = $this->database->prepare(
            "INSERT INTO atenciones
             (persona_id, tipo_id, estado_id, registrado_por_usuario_id, fecha_hora, motivo, diagnostico_resultado, indicaciones)
             VALUES (:persona_id,
                     (SELECT t.id FROM tipos t INNER JOIN procesos p ON p.id=t.proceso_id WHERE p.codigo='ATENCION' AND t.codigo='CONSULTA_MEDICA' LIMIT 1),
                     (SELECT e.id FROM estados e INNER JOIN procesos p ON p.id=e.proceso_id WHERE p.codigo='ATENCION' AND e.codigo='REALIZADA' LIMIT 1),
                     :usuario_id, '2026-07-19 13:00:00', 'Control médico de prueba', 'Evolución estable', 'Mantener indicaciones')"
        );
        $statement->execute(['persona_id' => $personId, 'usuario_id' => $userId]);
        return (int) $this->database->lastInsertId();
    }

    private function document(int $personId, int $attentionId, int $userId, string $name, string $mimeType): void
    {
        $config = require dirname(__DIR__, 2) . '/config/filesystems.php';
        $directory = rtrim($config['documents'], '/\\') . DIRECTORY_SEPARATOR . 'tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $extension = $mimeType === 'application/pdf' ? 'pdf' : 'png';
        $absolutePath = $directory . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.' . $extension;
        if ($mimeType === 'application/pdf') {
            $pdf = new \FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'PDF adjunto de prueba');
            file_put_contents($absolutePath, $pdf->Output('S'));
        } else {
            file_put_contents($absolutePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        }
        $this->temporaryFiles[] = $absolutePath;

        $statement = $this->database->prepare(
            'INSERT INTO documentos
             (persona_id, atencion_id, tipo_id, subido_por_usuario_id, nombre, archivo_ruta, mime_type, fecha_documento, descripcion)
             VALUES (:persona_id, :atencion_id,
                     (SELECT t.id FROM tipos t INNER JOIN procesos p ON p.id = t.proceso_id WHERE p.codigo = \'DOCUMENTO\' ORDER BY t.id LIMIT 1),
                     :usuario_id, :nombre, :ruta, :mime, \'2026-07-19\', \'Adjunto de prueba para ficha\')'
        );
        $statement->execute([
            'persona_id' => $personId,
            'atencion_id' => $attentionId,
            'usuario_id' => $userId,
            'nombre' => $name,
            'ruta' => 'tests/' . basename($absolutePath),
            'mime' => $mimeType,
        ]);
    }

    private function pdfPageCount(string $content): int
    {
        $path = tempnam(sys_get_temp_dir(), 'visalud-pdf-test-');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return (new Fpdi())->setSourceFile($path);
    }
}