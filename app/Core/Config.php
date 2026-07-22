<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    public function __construct(private array $items = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }
}
