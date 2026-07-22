<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public function __construct(
        private readonly string $name = 'portal_salud_session',
        private readonly bool $secure = false,
    ) {
    }

    public function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name($this->name);
            session_set_cookie_params([
                'httponly' => true,
                'secure' => $this->secure,
                'samesite' => 'Lax',
                'path' => '/',
            ]);
            session_start();
        }

        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public function flashed(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_old'][$key] ?? $default;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function token(): string
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_token'];

    }

    public function tokenIsValid(?string $token): bool
    {
        return is_string($token) && hash_equals($this->token(), $token);
    }
}
