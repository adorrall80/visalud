<?php

declare(strict_types=1);

namespace App\Console;

use PDO;

final class ProductionCheck
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $rootPath,
        private readonly Migrator $migrator,
    ) {
    }

    public function run(bool $production = false): array
    {
        $checks = [];
        $this->add($checks, version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP 8.2 o superior');
        foreach (['pdo_mysql', 'openssl', 'mbstring', 'json', 'fileinfo'] as $extension) {
            $this->add($checks, extension_loaded($extension), "Extensión {$extension}");
        }
        foreach (['storage/cache', 'storage/logs', 'storage/documentos'] as $directory) {
            $path = $this->rootPath . '/' . $directory;
            $this->add($checks, is_dir($path) && is_writable($path), "Escritura en {$directory}");
        }
        $pending = array_filter($this->migrator->status(), static fn (array $row): bool => !$row['applied']);
        $this->add($checks, $pending === [], 'Migraciones aplicadas');
        $catalogs = (int) $this->database->query('SELECT COUNT(*) FROM procesos')->fetchColumn();
        $this->add($checks, $catalogs >= 4, 'Catálogos base disponibles');
        $this->add($checks, is_file($this->rootPath . '/vendor/autoload.php'), 'Dependencias instaladas');
        $this->add($checks, is_file($this->rootPath . '/public/index.php'), 'Entrada pública disponible');
        $this->add($checks, !is_file($this->rootPath . '/public/.env'), '.env fuera de public');
        $this->add($checks, !is_dir($this->rootPath . '/public/storage'), 'storage fuera de public');

        if ($production) {
            $appUrl = (string) env('APP_URL', '');
            $redirect = (string) env('GOOGLE_REDIRECT_URI', '');
            $this->add($checks, env('APP_ENV') === 'production', 'APP_ENV=production');
            $this->add($checks, !filter_var(env('APP_DEBUG', true), FILTER_VALIDATE_BOOL), 'APP_DEBUG=false');
            $this->add($checks, str_starts_with($appUrl, 'https://'), 'APP_URL usa HTTPS');
            $this->add($checks, filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL), 'FORCE_HTTPS=true');
            $this->add($checks, filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOL), 'SESSION_SECURE=true');
            $this->add($checks, str_starts_with($redirect, 'https://'), 'Callback Google usa HTTPS');
            $this->add($checks, (string) env('DB_USERNAME', 'root') !== 'root', 'Usuario MySQL exclusivo');
            $this->add($checks, trim((string) env('DB_PASSWORD', '')) !== '', 'Contraseña MySQL configurada');
        }
        return $checks;
    }

    public function hasFailures(array $checks): bool
    {
        return in_array(false, array_column($checks, 'passed'), true);
    }

    private function add(array &$checks, bool $passed, string $label): void
    {
        $checks[] = ['passed' => $passed, 'label' => $label];
    }
}
