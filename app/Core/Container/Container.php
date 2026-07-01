<?php

declare(strict_types=1);

namespace HaHireAI\Core\Container;

use Closure;
use HaHireAI\Core\Contracts\Container as ContainerContract;
use HaHireAI\Core\Container\Exceptions\ContainerException;
use HaHireAI\Core\Container\Exceptions\NotFoundException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * A small, bespoke dependency-injection container.
 *
 * Supports exactly what the project needs: interface→implementation bindings,
 * singleton vs transient lifetimes, constructor autowiring, instance
 * registration, and callable invocation with parameter resolution.
 * Deliberately no facades, no service-locator usage inside business logic.
 *
 * See docs/SERVICE_CONTAINER.md.
 */
final class Container implements ContainerContract
{
    /** @var array<string, array{concrete: Closure|string, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, true> guards against circular resolution */
    private array $building = [];

    public function bind(string $id, callable|string|null $concrete = null): void
    {
        $this->register($id, $concrete, shared: false);
    }

    public function singleton(string $id, callable|string|null $concrete = null): void
    {
        $this->register($id, $concrete, shared: true);
    }

    public function instance(string $id, object $instance): object
    {
        $this->instances[$id] = $instance;

        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function make(string $id, array $parameters = []): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $binding = $this->bindings[$id] ?? null;
        $concrete = $binding['concrete'] ?? $id;

        $object = $concrete instanceof Closure
            ? $concrete($this, $parameters)
            : $this->build($concrete, $parameters);

        if (! is_object($object)) {
            throw new ContainerException("Binding [{$id}] did not resolve to an object.");
        }

        if (($binding['shared'] ?? false) === true) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    public function call(callable $callback, array $parameters = []): mixed
    {
        $reflection = $this->reflectCallable($callback);
        $args = $this->resolveParameters($reflection->getParameters(), $parameters);

        return $callback(...$args);
    }

    private function register(string $id, callable|string|null $concrete, bool $shared): void
    {
        $concrete ??= $id;

        if (is_string($concrete)) {
            $this->bindings[$id] = ['concrete' => $concrete, 'shared' => $shared];

            return;
        }

        // Normalise any callable to a Closure.
        $this->bindings[$id] = ['concrete' => Closure::fromCallable($concrete), 'shared' => $shared];
    }

    /**
     * @param  class-string|string  $class
     */
    private function build(string $class, array $parameters = []): object
    {
        if (! class_exists($class)) {
            throw new NotFoundException("Class or binding [{$class}] is not resolvable.");
        }

        if (isset($this->building[$class])) {
            throw new ContainerException("Circular dependency while resolving [{$class}].");
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            throw new ContainerException("[{$class}] is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $this->building[$class] = true;

        try {
            $args = $this->resolveParameters($constructor->getParameters(), $parameters);
        } finally {
            unset($this->building[$class]);
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @param  array<int, \ReflectionParameter>  $params
     * @return list<mixed>
     */
    private function resolveParameters(array $params, array $overrides): array
    {
        $resolved = [];

        foreach ($params as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $overrides)) {
                $resolved[] = $overrides[$name];

                continue;
            }

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $resolved[] = $this->make($type->getName());

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $resolved[] = $param->getDefaultValue();

                continue;
            }

            if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                $resolved[] = null;

                continue;
            }

            throw new ContainerException(
                "Unable to resolve parameter \${$name}" .
                ($param->getDeclaringClass() !== null ? " of [{$param->getDeclaringClass()->getName()}]" : '') . '.'
            );
        }

        return $resolved;
    }

    private function reflectCallable(callable $callback): \ReflectionFunctionAbstract
    {
        if (is_array($callback)) {
            return new \ReflectionMethod($callback[0], $callback[1]);
        }

        if (is_object($callback) && ! $callback instanceof Closure) {
            return new \ReflectionMethod($callback, '__invoke');
        }

        return new \ReflectionFunction($callback);
    }
}
