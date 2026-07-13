<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Small PSR-11-flavoured dependency injection container.
 *
 * Supports explicit bindings (factories), singletons, and autowiring of
 * constructor dependencies via reflection. This is the single seam where
 * interfaces are mapped to concrete implementations (see config/services.php),
 * so providers (Mailer, AI, Zoho, Cache, Logger) swap with no core changes.
 */
final class Container
{
    /** @var array<string,Closure> */
    private array $bindings = [];
    /** @var array<string,object> */
    private array $instances = [];
    /** @var array<string,bool> */
    private array $shared = [];

    public function bind(string $id, Closure $factory, bool $shared = false): void
    {
        $this->bindings[$id] = $factory;
        $this->shared[$id] = $shared;
        unset($this->instances[$id]);
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->bind($id, $factory, true);
    }

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $object = ($this->bindings[$id])($this);
            if ($this->shared[$id] ?? false) {
                $this->instances[$id] = $object;
            }
            return $object;
        }

        // Autowire concrete classes.
        if (class_exists($id)) {
            $object = $this->build($id);
            return $object;
        }

        throw new RuntimeException("Container has no binding for [$id].");
    }

    /**
     * @param class-string $class
     */
    public function build(string $class): object
    {
        $reflector = new \ReflectionClass($class);
        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Class [$class] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $dependencies[] = null;
            } else {
                throw new RuntimeException(
                    "Cannot resolve parameter \${$param->getName()} for [$class]."
                );
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
