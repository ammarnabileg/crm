<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Service container with binding, singletons, and reflection-based autowiring.
 *
 * Enterprise rule (docs/47): callers depend on interfaces and receive
 * collaborators via constructor injection — they never `new` a concrete service.
 * This container resolves those dependency graphs: bind an interface to a
 * concrete (closure or class-string), and `make()` autowires constructor
 * parameters by type-hint, recursively.
 */
final class Container
{
    private static ?Container $instance = null;

    /** @var array<string, array{concrete: Closure|string, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public static function getInstance(): Container
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(Container $container): void
    {
        self::$instance = $container;
    }

    /**
     * Bind an abstract (key/interface/class) to a concrete (closure or
     * class-string). When $concrete is null the abstract resolves itself.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared'   => $shared,
        ];
        unset($this->instances[$abstract]);
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $object): void
    {
        $this->instances[$abstract] = $object;
    }

    /**
     * Resolve a service. Resolution order: existing shared instance → explicit
     * binding → autowire (if it is an instantiable class).
     *
     * @param array<string,mixed> $parameters Named overrides for constructor params.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $object = $this->build($binding['concrete'], $parameters);

            if ($binding['shared']) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        // No binding: try to autowire a concrete class.
        if (class_exists($abstract)) {
            return $this->resolve($abstract, $parameters);
        }

        throw new RuntimeException("Service [{$abstract}] is not bound and cannot be resolved.");
    }

    private function build(Closure|string $concrete, array $parameters): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        return $this->resolve($concrete, $parameters);
    }

    /**
     * Instantiate a class, autowiring its constructor dependencies by type-hint.
     *
     * @param array<string,mixed> $parameters
     */
    private function resolve(string $class, array $parameters = []): object
    {
        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            throw new RuntimeException("Class [{$class}] is not instantiable.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $arguments[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new RuntimeException(
                "Cannot resolve parameter \${$name} of {$class}::__construct()."
            );
        }

        return $reflection->newInstanceArgs($arguments);
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || array_key_exists($abstract, $this->instances);
    }

    public function bound(string $abstract): bool
    {
        return $this->has($abstract);
    }
}
