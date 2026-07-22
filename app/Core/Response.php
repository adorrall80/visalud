<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function download(string $path, string $downloadName, string $mimeType): self
    {
        if (!is_file($path) || !is_readable($path)) {
            return self::html('Archivo no encontrado', 404);
        }

        $safeName = str_replace(["\r", "\n", '"'], '', $downloadName);
        $asciiName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $safeName);
        $asciiName = is_string($asciiName) ? $asciiName : $safeName;
        $asciiName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $asciiName) ?: 'documento';
        return new self((string) file_get_contents($path), 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function inlineFile(string $path, string $displayName, string $mimeType): self
    {
        if (!is_file($path) || !is_readable($path)) {
            return self::html('Archivo no encontrado', 404);
        }

        $safeName = str_replace(["\r", "\n", '"'], '', $displayName);
        return new self((string) file_get_contents($path), 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'inline; filename*=UTF-8\'\'' . rawurlencode($safeName),
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
        foreach ($securityHeaders as $name => $value) {
            if (!array_key_exists($name, $this->headers)) {
                header($name . ': ' . $value, true);
            }
        }
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $this->content;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }
}
