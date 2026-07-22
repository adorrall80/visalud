<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    public function __construct(private readonly PDO $connection)
    {
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function connection(): PDO
    {
        return $this->connection;
    }

    public static function connect(array $config): self
    {
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');

        if ($database === '') {
            throw new \InvalidArgumentException('DB_DATABASE es obligatorio.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        $pdo = new PDO(
            $dsn,
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        return new self($pdo);
    }
}
