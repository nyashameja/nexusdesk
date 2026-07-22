<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

use RuntimeException;

/**
 * Very small dependency-injection container.
 *
 * Supports singleton bindings via closures. Deliberately minimal — the goal is
 * to avoid global state and enable testing, not to reimplement a full IoC
 * framework.
 */
final class Container
{
    private static ?Container $instance = null;

    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $resolved = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?Container $container): void
    {
        self::$instance = $container;
    }

    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->resolved[$id]);
    }

    public function instanceValue(string $id, mixed $value): void
    {
        $this->resolved[$id] = $value;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || array_key_exists($id, $this->resolved);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new RuntimeException("No container binding for '{$id}'.");
        }

        return $this->resolved[$id] = ($this->bindings[$id])($this);
    }
}
