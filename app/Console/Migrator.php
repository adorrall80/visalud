<?php

declare(strict_types=1);

namespace App\Console;

use PDO;

final class Migrator
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $migrationPath,
    ) {
    }

    public function migrate(): array
    {
        $this->ensureRepository();
        $applied = $this->applied();
        $executed = [];

        foreach ($this->files() as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("No fue posible leer la migración {$name}");
            }

            $this->database->exec($sql);
            $statement = $this->database->prepare(
                'INSERT INTO migrations (migration, executed_at) VALUES (:migration, UTC_TIMESTAMP())'
            );
            $statement->execute(['migration' => $name]);
            $executed[] = $name;
        }

        return $executed;
    }

    public function status(): array
    {
        $this->ensureRepository();
        $applied = $this->applied();

        return array_map(
            static fn (string $file): array => [
                'migration' => basename($file),
                'applied' => isset($applied[basename($file)]),
            ],
            $this->files(),
        );
    }

    public function fresh(): void
    {
        $tables = $this->database->query(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->database->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                $safeTable = str_replace('`', '``', (string) $table);
                $this->database->exec("DROP TABLE `{$safeTable}`");
            }
        } finally {
            $this->database->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function ensureRepository(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function applied(): array
    {
        $names = $this->database->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        return array_fill_keys(array_map('strval', $names), true);
    }

    private function files(): array
    {
        $files = glob($this->migrationPath . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);
        return $files;
    }
}
