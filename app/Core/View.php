<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public function __construct(
        private readonly string $basePath,
        private readonly Session $session,
        private readonly ?\App\Services\PersonaContext $personContext = null,
    ) {
    }

    public function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $global = [];
        if ($this->personContext !== null) {
            $global = [
                'personasContexto' => $this->personContext->people(),
                'personaActiva' => $this->personContext->active(),
                'rutaActual' => (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
            ];
        }
        $content = $this->evaluate($view, [...$global, ...$data]);
        if ($layout === null) {
            return $content;
        }
        return $this->evaluate($layout, [...$global, ...$data, 'slot' => $content]);
    }

    public function component(string $component, array $data = [], ?callable $slot = null): string
    {
        $content = '';
        if ($slot !== null) {
            ob_start();
            $slot();
            $content = (string) ob_get_clean();
        }
        return $this->evaluate('components/' . $component, [...$data, 'slot' => $content]);
    }

    public function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . $this->escape($this->session->token()) . '">';
    }

    public function flash(string $key, mixed $default = null): mixed
    {
        return $this->session->flashed($key, $default);
    }

    private function evaluate(string $view, array $data): string
    {
        $path = $this->basePath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }
        extract($data, EXTR_SKIP);
        $view = $this;
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}
