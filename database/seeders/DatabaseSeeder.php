<?php

declare(strict_types=1);

namespace Database\Seeders;

use PDO;

final class DatabaseSeeder
{
    public function run(PDO $database, bool $includeDemo = false): void
    {
        (new ProcesosSeeder())->run($database);
        (new EstadosSeeder())->run($database);
        (new TiposSeeder())->run($database);

        if ($includeDemo) {
            (new DemoSeeder())->run($database);
        }
    }
}
