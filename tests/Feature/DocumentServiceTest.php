<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\DocumentoService;
use PDO;
use PHPUnit\Framework\TestCase;

final class DocumentServiceTest extends TestCase
{
    private PDO $database;
    private DocumentoService $documents;
    private int $familyId;
    private int $personId;
    private int $userId;
    private int $typeId;
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->documents = new DocumentoService($connection);
        $context = $this->database->query(
            'SELECT p.id AS persona_id, p.familia_id, fu.usuario_id
             FROM personas p INNER JOIN familia_usuarios fu ON fu.familia_id = p.familia_id
             ORDER BY p.id DESC LIMIT 1'
        )->fetch();
        self::assertIsArray($context, 'Se necesita al menos una persona de prueba.');
        $this->familyId = (int) $context['familia_id'];
        $this->personId = (int) $context['persona_id'];
        $this->userId = (int) $context['usuario_id'];
        $this->typeId = (int) $this->database->query(
            "SELECT t.id FROM tipos t INNER JOIN procesos p ON p.id = t.proceso_id
             WHERE p.codigo = 'DOCUMENTO' ORDER BY t.id LIMIT 1"
        )->fetchColumn();
        self::assertGreaterThan(0, $this->typeId);
        $this->database->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testAcceptsRealImageAndRejectsDangerousOrMismatchedFiles(): void
    {
        $png = $this->temporaryFile(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));
        $result = $this->documents->analyzeFile($png, 'resultado.png', filesize($png));
        self::assertSame('image/png', $result['mime_type']);
        self::assertSame('png', $result['extension']);

        try {
            $this->documents->analyzeFile($png, 'resultado.php', filesize($png));
            self::fail('Debía rechazar una extensión peligrosa.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('no coincide', $exception->getMessage());
        }

        $text = $this->temporaryFile('<?php echo "peligro";');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Solo se permiten');
        $this->documents->analyzeFile($text, 'archivo.pdf', filesize($text));
    }

    public function testRejectsOversizedFile(): void
    {
        $file = $this->temporaryFile('%PDF-1.4');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tama');
        $this->documents->analyzeFile($file, 'informe.pdf', 50 * 1024 * 1024);
    }

    public function testDocumentAccessAndDeletionAreRestrictedByFamily(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/filesystems.php';
        $directory = rtrim($config['documents'], '/\\') . DIRECTORY_SEPARATOR . 'tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $absolutePath = $directory . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($absolutePath, '%PDF-1.4 test');
        $this->temporaryFiles[] = $absolutePath;
        $relativePath = 'tests/' . basename($absolutePath);

        $statement = $this->database->prepare(
            'INSERT INTO documentos
             (persona_id, tipo_id, subido_por_usuario_id, nombre, archivo_ruta, mime_type)
             VALUES (:persona_id, :tipo_id, :usuario_id, :nombre, :ruta, :mime)'
        );
        $statement->execute([
            'persona_id' => $this->personId,
            'tipo_id' => $this->typeId,
            'usuario_id' => $this->userId,
            'nombre' => 'Documento temporal',
            'ruta' => $relativePath,
            'mime' => 'application/pdf',
        ]);
        $id = (int) $this->database->lastInsertId();

        $download = $this->documents->downloadForFamily($id, $this->familyId);
        self::assertSame($absolutePath, $download['absolute_path']);
        self::assertSame('Documento temporal.pdf', $download['download_name']);
        $this->database->exec("INSERT INTO familias (nombre) VALUES ('Familia temporal documentos')");
        self::assertNull($this->documents->downloadForFamily($id, (int) $this->database->lastInsertId()));
        self::assertTrue($this->documents->delete($id, $this->familyId));
        self::assertFileDoesNotExist($absolutePath);
    }

    private function temporaryFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'portal-doc-');
        self::assertIsString($file);
        file_put_contents($file, $content);
        $this->temporaryFiles[] = $file;
        return $file;
    }
}
