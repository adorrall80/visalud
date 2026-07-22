<?php

declare(strict_types=1);

namespace App\Console;

use PDO;

final class Seeder
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $rootPath,
    ) {
    }

    public function run(bool $includeDemo = false): void
    {
        require_once $this->rootPath . '/database/seeders/ProcesosSeeder.php';
        require_once $this->rootPath . '/database/seeders/EstadosSeeder.php';
        require_once $this->rootPath . '/database/seeders/TiposSeeder.php';
        require_once $this->rootPath . '/database/seeders/DemoSeeder.php';
        require_once $this->rootPath . '/database/seeders/DatabaseSeeder.php';

        (new \Database\Seeders\DatabaseSeeder())->run($this->database, $includeDemo);
    }
}
