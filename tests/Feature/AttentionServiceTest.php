<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\AtencionService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AttentionServiceTest extends TestCase
{
    private PDO $database;
    private AtencionService $attentions;
    private int $familyId;
    private int $personId;
    private int $userId;
    private int $typeId;
    private int $stateId;

    protected function setUp(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->attentions = new AtencionService($connection);

        $context = $this->database->query(
            'SELECT p.familia_id, p.id AS persona_id, fu.usuario_id
             FROM personas p
             INNER JOIN familia_usuarios fu ON fu.familia_id = p.familia_id
             ORDER BY p.id DESC LIMIT 1'
        )->fetch();
        self::assertIsArray($context, 'Se necesita al menos una persona de prueba.');
        $this->familyId = (int) $context['familia_id'];
        $this->personId = (int) $context['persona_id'];
        $this->userId = (int) $context['usuario_id'];
        $this->typeId = $this->catalogId('tipos', 'CONSULTA_MEDICA');
        $this->stateId = $this->catalogId('estados', 'REALIZADA');
        $this->database->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    public function testCreateFilterUpdateAndFamilyIsolation(): void
    {
        $id = $this->attentions->create($this->familyId, $this->userId, $this->validData());

        $attention = $this->attentions->findForFamily($id, $this->familyId);
        self::assertSame('Control temporal', $attention['motivo']);
        self::assertSame('CONSULTA_MEDICA', $attention['tipo_codigo']);
        self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $attention['estado_color']);
        self::assertSame('21-07-2026 10:30', $attention['fecha_hora_formato']);
        self::assertCount(1, array_filter(
            $this->attentions->allForFamily($this->familyId, ['persona_id' => $this->personId, 'desde' => '2026-07-21', 'hasta' => '2026-07-21']),
            static fn (array $item): bool => (int) $item['id'] === $id,
        ));

        $foreignFamilyId = $this->createForeignFamily();
        self::assertNull($this->attentions->findForFamily($id, $foreignFamilyId));

        $updated = $this->validData();
        $updated['diagnostico_resultado'] = 'Resultado actualizado';
        $this->attentions->update($id, $this->familyId, $this->userId, $updated);
        self::assertSame('Resultado actualizado', $this->attentions->findForFamily($id, $this->familyId)['diagnostico_resultado']);
    }

    public function testRejectsCatalogFromAnotherProcess(): void
    {
        $data = $this->validData();
        $data['tipo_id'] = $this->catalogId('tipos', 'RECETA');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no corresponde');
        $this->attentions->create($this->familyId, $this->userId, $data);
    }

    public function testRejectsNextDateBeforeAttention(): void
    {
        $data = $this->validData();
        $data['proxima_fecha'] = '2026-07-20';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no puede ser anterior');
        $this->attentions->create($this->familyId, $this->userId, $data);
    }

    public function testDateRangeUsesLocalTimezoneAtMonthBoundary(): void
    {
        $data = $this->validData();
        $data['fecha_hora'] = '2026-07-31T23:30';
        $data['proxima_fecha'] = '';
        $id = $this->attentions->create($this->familyId, $this->userId, $data);

        $july = $this->attentions->allForFamily($this->familyId, [
            'persona_id' => $this->personId,
            'desde' => '2026-07-01',
            'hasta' => '2026-07-31',
        ]);
        $august = $this->attentions->allForFamily($this->familyId, [
            'persona_id' => $this->personId,
            'desde' => '2026-08-01',
            'hasta' => '2026-08-31',
        ]);

        self::assertContains($id, array_map(static fn (array $item): int => (int) $item['id'], $july));
        self::assertNotContains($id, array_map(static fn (array $item): int => (int) $item['id'], $august));
    }

    private function validData(): array
    {
        return [
            'persona_id' => $this->personId,
            'tipo_id' => $this->typeId,
            'estado_id' => $this->stateId,
            'fecha_hora' => '2026-07-21T10:30',
            'profesional' => 'Profesional temporal',
            'especialidad' => 'Medicina general',
            'centro_medico' => 'Centro temporal',
            'motivo' => 'Control temporal',
            'diagnostico_resultado' => 'Sin hallazgos',
            'indicaciones' => 'Seguimiento',
            'temas_abordados' => '',
            'acuerdos' => '',
            'proxima_fecha' => '2026-08-21',
        ];
    }

    private function catalogId(string $table, string $code): int
    {
        $statement = $this->database->prepare("SELECT id FROM {$table} WHERE codigo = :codigo LIMIT 1");
        $statement->execute(['codigo' => $code]);
        $id = (int) $statement->fetchColumn();
        self::assertGreaterThan(0, $id, "No existe el catÃ¡logo {$code}.");
        return $id;
    }

    private function createForeignFamily(): int
    {
        $this->database->exec("INSERT INTO familias (nombre) VALUES ('Familia temporal de aislamiento')");
        return (int) $this->database->lastInsertId();
    }
}
