<?php

declare(strict_types=1);

namespace Orin\Support;

use RuntimeException;

/**
 * Tiny service container used to wire the application together.
 */
final class Container
{
    /** @var array<string, mixed> */
    private array $bindings = [];

    public function set(string $id, mixed $value): void
    {
        $this->bindings[$id] = $value;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings);
    }

    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->bindings)) {
            throw new RuntimeException(sprintf('Service "%s" is not registered in the container.', $id));
        }

        return $this->bindings[$id];
    }
}
