<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\PersonaService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonServiceTest extends TestCase
{
    private PDO $database;
    private PersonaService $people;
    private int $familyId;

    protected function setUp(): void
    {
        $connection = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
        $this->database = $connection->connection();
        $this->people = new PersonaService($connection);
        $this->familyId = (int) $this->database->query(
            "SELECT fu.familia_id FROM familia_usuarios fu
             INNER JOIN usuarios u ON u.id = fu.usuario_id
             WHERE u.google_sub <> 'demo-google-sub-local' ORDER BY fu.familia_id DESC LIMIT 1"
        )->fetchColumn();
        $this->database->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    public function testCreateUpdateAndFamilyIsolation(): void
    {
        $id = $this->people->create($this->familyId, [
            'nombre' => 'Persona Temporal',
            'identificacion' => 'TEST-PERSONA-1',
            'fecha_nacimiento' => '1960-05-10',
            'grupo_sanguineo' => 'O+',
            'alergias' => 'Ninguna conocida',
        ]);

        $person = $this->people->findForFamily($id, $this->familyId);
        self::assertSame('Persona Temporal', $person['nombre']);

        $foreignFamilyId = (int) $this->database->query(
            "SELECT id FROM familias WHERE id <> {$this->familyId} ORDER BY id LIMIT 1"
        )->fetchColumn();
        self::assertNull($this->people->findForFamily($id, $foreignFamilyId));

        $this->people->update($id, $this->familyId, [
            'nombre' => 'Persona Actualizada',
            'identificacion' => 'TEST-PERSONA-1',
            'fecha_nacimiento' => '1960-05-10',
        ]);
        self::assertSame('Persona Actualizada', $this->people->findForFamily($id, $this->familyId)['nombre']);
    }

    public function testIdentificationCannotRepeatInsideFamily(): void
    {
        $data = ['nombre' => 'Primera', 'identificacion' => 'TEST-DUPLICADA'];
        $this->people->create($this->familyId, $data);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('identificación');
        $this->people->create($this->familyId, ['nombre' => 'Segunda', 'identificacion' => 'TEST-DUPLICADA']);
    }
}
