<?php

declare(strict_types=1);

namespace App\Core;

final class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $id, callable $resolver): void
    {
        $this->bindings[$id] = $resolver;
    }

    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            if (!class_exists($id)) {
                throw new \RuntimeException("Servicio no registrado: {$id}");
            }

            $reflection = new \ReflectionClass($id);
            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $dependencies = [];
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                        continue;
                    }
                    throw new \RuntimeException("No se puede resolver {$id}::\${$parameter->getName()}");
                }
                $dependencies[] = $this->get($type->getName());
            }

            return $reflection->newInstanceArgs($dependencies);
        }

        return ($this->bindings[$id])($this);
    }
}
