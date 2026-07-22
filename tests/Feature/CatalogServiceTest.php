<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\MantenedorService;
use PDO;
use PHPUnit\Framework\TestCase;

final class CatalogServiceTest extends TestCase
{
    private PDO $database;
    private MantenedorService $catalogs;
    private int $attentionProcessId;

    protected function setUp(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->catalogs = new MantenedorService($connection);
        $this->attentionProcessId = (int) $this->database->query(
            "SELECT id FROM procesos WHERE codigo = 'ATENCION'"
        )->fetchColumn();
        $this->database->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    public function testCreateUpdateOrderToggleAndDeleteCustomValue(): void
    {
        $id = $this->catalogs->create('tipos', [
            'proceso_id' => $this->attentionProcessId,
            'codigo' => 'control especial',
            'nombre' => 'Control especial temporal',
            'orden' => 45,
            'activo' => 1,
        ]);
        $value = $this->catalogs->find('tipos', $id);
        self::assertSame('CONTROL_ESPECIAL', $value['codigo']);
        self::assertSame(45, (int) $value['orden']);
        self::assertTrue((bool) $value['activo']);

        self::assertFalse($this->catalogs->toggle('tipos', $id));
        $this->catalogs->update('tipos', $id, [
            'proceso_id' => $this->attentionProcessId,
            'codigo' => 'CONTROL_ESPECIAL',
            'nombre' => 'Control actualizado',
            'orden' => 20,
            'activo' => 1,
        ]);
        self::assertSame('Control actualizado', $this->catalogs->find('tipos', $id)['nombre']);
        self::assertSame(20, (int) $this->catalogs->find('tipos', $id)['orden']);

        $this->catalogs->delete('tipos', $id);
        self::assertNull($this->catalogs->find('tipos', $id));
    }

    public function testRejectsDuplicateCodeOrNameInsideProcess(): void
    {
        $this->catalogs->create('estados', [
            'proceso_id' => $this->attentionProcessId,
            'codigo' => 'EN_REVISION_TEMP',
            'nombre' => 'En revisión temporal',
            'orden' => 40,
            'activo' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ya existe');
        $this->catalogs->create('estados', [
            'proceso_id' => $this->attentionProcessId,
            'codigo' => 'EN_REVISION_TEMP',
            'nombre' => 'Otro nombre',
            'orden' => 50,
            'activo' => 1,
        ]);
    }

    public function testStoresAndValidatesStateColor(): void
    {
        $id = $this->catalogs->create('estados', [
            'proceso_id' => $this->attentionProcessId,
            'codigo' => 'REPROGRAMADA_TEMP',
            'nombre' => 'Reprogramada temporal',
            'color' => '#7654ab',
            'orden' => 35,
            'activo' => 1,
        ]);
        self::assertSame('#7654AB', $this->catalogs->find('estados', $id)['color']);
        $this->catalogs->delete('estados', $id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('formato hexadecimal');
        $this->catalogs->create('estados', [
            'proceso_id' => $this->attentionProcessId,
            'codigo' => 'COLOR_INVALIDO_TEMP',
            'nombre' => 'Color inválido temporal',
            'color' => 'rojo',
            'orden' => 36,
            'activo' => 1,
        ]);
    }

    public function testProtectsBaseAndUsedValuesFromDeletion(): void
    {
        $baseId = (int) $this->database->query(
            "SELECT t.id FROM tipos t INNER JOIN procesos p ON p.id = t.proceso_id
             WHERE p.codigo = 'ATENCION' AND t.codigo = 'CONSULTA_MEDICA'"
        )->fetchColumn();
        try {
            $this->catalogs->delete('tipos', $baseId);
            self::fail('Debía proteger el valor base.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('base', $exception->getMessage());
        }

        $customId = $this->catalogs->create('tipos', [
            'proceso_id' => (int) $this->database->query("SELECT id FROM procesos WHERE codigo = 'DOCUMENTO'")->fetchColumn(),
            'codigo' => 'ARCHIVO_TEMPORAL',
            'nombre' => 'Archivo temporal',
            'orden' => 90,
            'activo' => 1,
        ]);
        $context = $this->database->query(
            'SELECT p.id AS persona_id, fu.usuario_id FROM personas p
             INNER JOIN familia_usuarios fu ON fu.familia_id = p.familia_id ORDER BY p.id DESC LIMIT 1'
        )->fetch();
        $statement = $this->database->prepare(
            "INSERT INTO documentos
             (persona_id, tipo_id, subido_por_usuario_id, nombre, archivo_ruta, mime_type)
             VALUES (:persona, :tipo, :usuario, 'Temporal', :ruta, 'application/pdf')"
        );
        $statement->execute([
            'persona' => $context['persona_id'],
            'tipo' => $customId,
            'usuario' => $context['usuario_id'],
            'ruta' => 'tests/catalog-' . bin2hex(random_bytes(8)) . '.pdf',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('utilizado');
        $this->catalogs->delete('tipos', $customId);
    }
}
