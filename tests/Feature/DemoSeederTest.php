<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use Database\Seeders\DemoSeeder;
use PDO;
use PHPUnit\Framework\TestCase;

final class DemoSeederTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/database/seeders/DemoSeeder.php';
        $this->database = Database::connect(require dirname(__DIR__, 2) . '/config/database.php')->connection();
        $this->database->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }

    public function testDemoSeederIsIdempotentAndCreatesCompleteScenario(): void
    {
        $seeder = new DemoSeeder();
        $seeder->run($this->database);
        $first = $this->counts();
        $seeder->run($this->database);
        $second = $this->counts();

        self::assertSame(['personas' => 2, 'atenciones' => 4, 'medicamentos' => 3, 'documentos' => 2], $first);
        self::assertSame($first, $second);
    }

    private function counts(): array
    {
        $familyId = (int) $this->database->query(
            "SELECT id FROM familias WHERE nombre = 'Familia Demo Completa' ORDER BY id LIMIT 1"
        )->fetchColumn();
        self::assertGreaterThan(0, $familyId);
        $queries = [
            'personas' => "SELECT COUNT(*) FROM personas
                           WHERE familia_id = :familia_id
                             AND identificacion IN ('DEMO-ELENA', 'DEMO-MATEO')",
            'atenciones' => "SELECT COUNT(*) FROM atenciones r
                             INNER JOIN personas p ON p.id = r.persona_id
                             WHERE p.familia_id = :familia_id AND r.motivo LIKE '[DEMO:%'",
            'medicamentos' => "SELECT COUNT(*) FROM medicamentos r
                               INNER JOIN personas p ON p.id = r.persona_id
                               WHERE p.familia_id = :familia_id AND r.nombre LIKE '% Demo'",
            'documentos' => "SELECT COUNT(*) FROM documentos r
                             INNER JOIN personas p ON p.id = r.persona_id
                             WHERE p.familia_id = :familia_id AND r.archivo_ruta LIKE 'demo/%'",
        ];
        $counts = [];
        foreach ($queries as $table => $query) {
            $statement = $this->database->prepare($query);
            $statement->execute(['familia_id' => $familyId]);
            $counts[$table] = (int) $statement->fetchColumn();
        }
        return $counts;
    }
}
