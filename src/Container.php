<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function set(string $id, callable|object $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->bindings)) {
            throw new RuntimeException("Service {$id} is not registered");
        }

        $factory = $this->bindings[$id];
        $instance = $factory instanceof \Closure ? $factory($this) : $factory;
        $this->instances[$id] = $instance;

        return $instance;
    }
}
