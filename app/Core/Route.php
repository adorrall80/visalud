<?php

declare(strict_types=1);

namespace App\Core;

final class Route
{
    private array $middleware = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $handler,
    ) {
    }

    public function middleware(array $middleware): self
    {
        $this->middleware = array_values(array_unique(array_merge($this->middleware, $middleware)));
        return $this;
    }

    public function middlewareNames(): array
    {
        return $this->middleware;
    }
}
