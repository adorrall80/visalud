<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\MedicamentoService;
use PDO;
use PHPUnit\Framework\TestCase;

final class MedicationServiceTest extends TestCase
{
    private PDO $database;
    private MedicamentoService $medications;
    private int $familyId;
    private int $personId;
    private int $activeStateId;

    protected function setUp(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->medications = new MedicamentoService($connection);
        $person = $this->database->query('SELECT id, familia_id FROM personas ORDER BY id DESC LIMIT 1')->fetch();
        self::assertIsArray($person, 'Se necesita al menos una persona de prueba.');
        $this->familyId = (int) $person['familia_id'];
        $this->personId = (int) $person['id'];
        $this->activeStateId = $this->stateId('ACTIVO');
        $this->database->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    public function testCreateUpdateSchedulesAndFamilyIsolation(): void
    {
        $id = $this->medications->create($this->familyId, $this->validData());
        $medication = $this->medications->findForFamily($id, $this->familyId);

        self::assertSame('Medicamento temporal', $medication['nombre']);
        self::assertSame(['08:00', '20:00'], $medication['horarios']);
        self::assertCount(1, array_filter(
            $this->medications->activeForFamily($this->familyId, $this->personId),
            static fn (array $item): bool => (int) $item['id'] === $id,
        ));

        $this->database->exec("INSERT INTO familias (nombre) VALUES ('Familia temporal medicamentos')");
        self::assertNull($this->medications->findForFamily($id, (int) $this->database->lastInsertId()));

        $updated = $this->validData();
        $updated['estado_id'] = $this->stateId('FINALIZADO');
        $updated['horarios'] = ['09:30'];
        $this->medications->update($id, $this->familyId, $updated);
        $medication = $this->medications->findForFamily($id, $this->familyId);
        self::assertSame('FINALIZADO', $medication['estado_codigo']);
        self::assertSame(['09:30'], $medication['horarios']);
    }

    public function testRejectsRepeatedSchedules(): void
    {
        $data = $this->validData();
        $data['horarios'] = ['08:00', '08:00'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('repetir un horario');
        $this->medications->create($this->familyId, $data);
    }

    public function testRejectsWrongProcessStateAndInvalidDates(): void
    {
        $data = $this->validData();
        $data['estado_id'] = $this->catalogId('estados', 'REALIZADA');

        try {
            $this->medications->create($this->familyId, $data);
            self::fail('Debía rechazar un estado del proceso atención.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('no corresponde', $exception->getMessage());
        }

        $data = $this->validData();
        $data['fecha_termino'] = '2026-07-20';
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no puede ser anterior');
        $this->medications->create($this->familyId, $data);
    }

    public function testReturnsOneOrManyMedicationsAssociatedWithAttention(): void
    {
        $attentionId = $this->temporaryAttention();
        $first = $this->validData();
        $first['atencion_id'] = $attentionId;
        $firstId = $this->medications->create($this->familyId, $first);
        $second = $first;
        $second['nombre'] = 'Segundo medicamento temporal';
        $secondId = $this->medications->create($this->familyId, $second);

        $associated = $this->medications->allForFamily($this->familyId, [
            'persona_id' => $this->personId,
            'atencion_id' => $attentionId,
        ]);
        $ids = array_map(static fn (array $item): int => (int) $item['id'], $associated);

        self::assertCount(2, $associated);
        self::assertContains($firstId, $ids);
        self::assertContains($secondId, $ids);
        self::assertSame([$attentionId], array_values(array_unique(array_map(
            static fn (array $item): int => (int) $item['atencion_id'],
            $associated,
        ))));
    }

    private function validData(): array
    {
        return [
            'persona_id' => $this->personId,
            'atencion_id' => '',
            'estado_id' => $this->activeStateId,
            'nombre' => 'Medicamento temporal',
            'dosis' => '1 comprimido',
            'frecuencia' => 'Cada 12 horas',
            'fecha_inicio' => '2026-07-21',
            'fecha_termino' => '2026-08-21',
            'indicaciones' => 'Tomar con agua',
            'horarios' => ['20:00', '08:00', ''],
        ];
    }

    private function stateId(string $code): int
    {
        return $this->catalogId('estados', $code);
    }

    private function temporaryAttention(): int
    {
        $user = (int) $this->database->query(
            'SELECT usuario_id FROM familia_usuarios WHERE familia_id = ' . $this->familyId . ' LIMIT 1'
        )->fetchColumn();
        $statement = $this->database->prepare(
            "INSERT INTO atenciones
             (persona_id, tipo_id, estado_id, registrado_por_usuario_id, fecha_hora, motivo)
             VALUES (:persona, :tipo, :estado, :usuario, '2026-07-21 12:00:00', 'Atención temporal para medicamentos')"
        );
        $statement->execute([
            'persona' => $this->personId,
            'tipo' => $this->catalogId('tipos', 'CONSULTA_MEDICA'),
            'estado' => $this->catalogId('estados', 'REALIZADA'),
            'usuario' => $user,
        ]);
        return (int) $this->database->lastInsertId();
    }

    private function catalogId(string $table, string $code): int
    {
        $statement = $this->database->prepare("SELECT id FROM {$table} WHERE codigo = :codigo LIMIT 1");
        $statement->execute(['codigo' => $code]);
        $id = (int) $statement->fetchColumn();
        self::assertGreaterThan(0, $id, "No existe el catÃ¡logo {$code}.");
        return $id;
    }
}
