<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Migrator;
use App\Console\ProductionCheck;
use App\Core\Database;
use App\Core\LogManager;
use PHPUnit\Framework\TestCase;

final class OperationsReadinessTest extends TestCase
{
    private string $logDirectory;

    protected function setUp(): void
    {
        $this->logDirectory = dirname(__DIR__, 2) . '/storage/cache/log-test-' . bin2hex(random_bytes(6));
        mkdir($this->logDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDirectory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->logDirectory)) {
            rmdir($this->logDirectory);
        }
    }

    public function testDailyLogRedactsSecretsAndRemovesExpiredFiles(): void
    {
        $expired = $this->logDirectory . '/app-2020-01-01.log';
        file_put_contents($expired, 'antiguo');
        touch($expired, time() - 40 * 86400);
        $logger = new LogManager($this->logDirectory, 30);
        $logger->report(new \RuntimeException("password=secreto-super-privado\nsegunda linea"));

        $current = $this->logDirectory . '/app-' . gmdate('Y-m-d') . '.log';
        self::assertFileExists($current);
        $content = (string) file_get_contents($current);
        self::assertStringContainsString('password=<redacted>', $content);
        self::assertStringNotContainsString('secreto-super-privado', $content);
        self::assertStringNotContainsString("\nsegunda", $content);
        self::assertFileDoesNotExist($expired);
    }

    public function testLocalReadinessCheckPassesWithoutExposingConfigurationValues(): void
    {
        $root = dirname(__DIR__, 2);
        $connection = Database::connect(require $root . '/config/database.php');
        $checker = new ProductionCheck(
            $connection->connection(),
            $root,
            new Migrator($connection->connection(), $root . '/database/migrations'),
        );
        $checks = $checker->run(false);

        self::assertFalse($checker->hasFailures($checks));
        $labels = implode(' ', array_column($checks, 'label'));
        $googleSecret = trim((string) env('GOOGLE_CLIENT_SECRET', ''));
        self::assertNotSame('', $googleSecret);
        self::assertStringNotContainsString($googleSecret, $labels);
    }
}
