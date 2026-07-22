<?php

declare(strict_types=1);

namespace App\Core;

final class LogManager
{
    public function __construct(
        private readonly string $directory,
        private readonly int $retentionDays = 30,
    ) {
    }

    public function report(\Throwable $exception): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            return;
        }
        $message = $this->sanitize($exception->getMessage());
        $line = sprintf(
            "[%s] %s in %s:%d%s",
            gmdate('c'),
            $message,
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL,
        );
        error_log($line, 3, $this->directory . '/app-' . gmdate('Y-m-d') . '.log');
        $this->purgeExpired();
    }

    public function purgeExpired(): void
    {
        $threshold = time() - max(1, $this->retentionDays) * 86400;
        foreach (glob($this->directory . '/app-*.log') ?: [] as $file) {
            $modified = filemtime($file);
            if ($modified !== false && $modified < $threshold) {
                @unlink($file);
            }
        }
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace(
            '/(password|passwd|client_secret|access_token|refresh_token|authorization)\s*[=:]\s*[^\s,;]+/i',
            '$1=<redacted>',
            $message,
        ) ?? 'Error interno';
        return mb_substr(str_replace(["\r", "\n"], ' ', $message), 0, 2000);
    }
}
