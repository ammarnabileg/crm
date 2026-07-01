<?php

declare(strict_types=1);

namespace Nizam\Platform\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * A small, dependency-free PSR-11 service container with constructor autowiring.
 *
 * The container is the composition root's registry. It supports:
 *   - transient bindings ({@see self::bind()}) — a fresh instance per resolution,
 *   - shared bindings ({@see self::singleton()}) — one instance cached for the container's life,
 *   - pre-built instances ({@see self::instance()}),
 *   - zero-config autowiring of concrete classes via reflection,
 *   - invoking callables/methods with their dependencies injected ({@see self::call()}).
 *
 * Circular dependencies are detected during resolution and surfaced as a {@see ContainerException}
 * rather than a fatal recursion. The container implements {@see ContainerInterface}, so any
 * PSR-11 consumer can depend on it.
 */
final class Container implements ContainerInterface
{
    /**
     * Registered bindings keyed by abstract id.
     *
     * @var array<string, array{concrete: Closure|string, shared: bool}>
     */
    private array $bindings = [];

    /**
     * Resolved shared instances keyed by abstract id.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Ids currently mid-resolution, used to detect circular dependencies.
     *
     * @var array<string, true>
     */
    private array $building = [];

    /**
     * Register a transient binding (a new instance is produced on each resolution).
     *
     * @param string               $id       The abstract id (usually an interface or class name).
     * @param Closure|string|null  $concrete A factory closure, a concrete class name, or null to
     *                                        bind the id to itself.
     */
    public function bind(string $id, Closure|string|null $concrete = null): void
    {
        $this->register($id, $concrete, false);
    }

    /**
     * Register a shared binding (the same instance is returned on every resolution).
     *
     * @param Closure|string|null $concrete A factory closure, a concrete class name, or null.
     */
    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->register($id, $concrete, true);
    }

    /**
     * Register a pre-built object as a shared instance.
     */
    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Whether the container can return an entry for the given id.
     *
     * True for anything already resolved, explicitly bound, or an instantiable class name that
     * can be autowired.
     */
    public function has(string $id): bool
    {
        if (isset($this->instances[$id]) || isset($this->bindings[$id])) {
            return true;
        }

        return class_exists($id);
    }

    /**
     * Resolve an entry by its id (PSR-11).
     *
     * @throws NotFoundException  When the id is not bound and not an instantiable class.
     * @throws ContainerException When resolution fails (unresolvable/circular dependency).
     */
    public function get(string $id): mixed
    {
        return $this->resolve($id, []);
    }

    /**
     * Resolve an entry, optionally overriding constructor parameters by name.
     *
     * Unlike {@see self::get()}, a shared binding resolved through `make()` with explicit
     * parameters is not cached, because the overrides make it context-specific.
     *
     * @param array<string, mixed> $parameters Constructor parameter overrides keyed by name.
     *
     * @throws NotFoundException  When the id is not bound and not an instantiable class.
     * @throws ContainerException When resolution fails.
     */
    public function make(string $id, array $parameters = []): mixed
    {
        return $this->resolve($id, $parameters);
    }

    /**
     * Invoke a callable (closure, "Class::method", or [object|class, method]) with its
     * type-hinted dependencies resolved from the container.
     *
     * @param callable|array{0: object|string, 1: string}|string $callable
     * @param array<string, mixed>                               $parameters Overrides keyed by name.
     *
     * @throws ContainerException When a dependency cannot be resolved or the callable is invalid.
     */
    public function call(callable|array|string $callable, array $parameters = []): mixed
    {
        [$reflection, $invoker] = $this->reflectCallable($callable);

        $arguments = $this->resolveArguments($reflection, $parameters);

        return $invoker($arguments);
    }

    /**
     * Store a binding.
     */
    private function register(string $id, Closure|string|null $concrete, bool $shared): void
    {
        // Dropping a previously cached instance keeps re-binding predictable.
        unset($this->instances[$id]);

        $this->bindings[$id] = [
            'concrete' => $concrete ?? $id,
            'shared' => $shared,
        ];
    }

    /**
     * Core resolution routine shared by get()/make().
     *
     * @param array<string, mixed> $parameters
     */
    private function resolve(string $id, array $parameters): mixed
    {
        if (isset($this->instances[$id]) && $parameters === []) {
            return $this->instances[$id];
        }

        if (isset($this->building[$id])) {
            throw new ContainerException(sprintf(
                'Circular dependency detected while resolving "%s" (chain: %s).',
                $id,
                implode(' -> ', array_keys($this->building)) . ' -> ' . $id,
            ));
        }

        $binding = $this->bindings[$id] ?? null;
        $concrete = $binding['concrete'] ?? $id;

        $this->building[$id] = true;

        try {
            if ($concrete instanceof Closure) {
                $object = $concrete($this, $parameters);
            } else {
                $object = $this->build($concrete, $parameters);
            }
        } finally {
            unset($this->building[$id]);
        }

        // Cache shared resolutions only when no per-call overrides were supplied.
        if (($binding['shared'] ?? false) === true && $parameters === []) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    /**
     * Instantiate a concrete class, autowiring its constructor dependencies.
     *
     * @param array<string, mixed> $parameters
     *
     * @throws NotFoundException  When the class does not exist.
     * @throws ContainerException When the class is not instantiable or a dependency is unresolvable.
     */
    private function build(string $concrete, array $parameters): object
    {
        if (!class_exists($concrete)) {
            throw new NotFoundException(sprintf('No container entry or class found for "%s".', $concrete));
        }

        try {
            $reflection = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new ContainerException(sprintf('Unable to reflect class "%s": %s', $concrete, $e->getMessage()), 0, $e);
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(sprintf('Class "%s" is not instantiable.', $concrete));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = $this->resolveArguments($constructor, $parameters, $concrete);

        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * Resolve the ordered argument list for a function/method/constructor.
     *
     * @param array<string, mixed> $overrides Values keyed by parameter name that bypass resolution.
     *
     * @return array<int, mixed>
     *
     * @throws ContainerException When a parameter cannot be resolved.
     */
    private function resolveArguments(ReflectionFunctionAbstract $reflection, array $overrides, ?string $context = null): array
    {
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($parameter, $overrides, $context);
        }

        return $arguments;
    }

    /**
     * Resolve a single parameter from overrides, the container, or its default value.
     *
     * @param array<string, mixed> $overrides
     *
     * @throws ContainerException When the parameter is unresolvable.
     */
    private function resolveParameter(ReflectionParameter $parameter, array $overrides, ?string $context): mixed
    {
        $name = $parameter->getName();

        if (array_key_exists($name, $overrides)) {
            return $overrides[$name];
        }

        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();

            if ($this->has($className)) {
                return $this->resolve($className, []);
            }

            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($type->allowsNull()) {
                return null;
            }

            throw new ContainerException(sprintf(
                'Unable to resolve dependency "%s $%s"%s.',
                $className,
                $name,
                $context !== null ? ' for [' . $context . ']' : '',
            ));
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
            return null;
        }

        throw new ContainerException(sprintf(
            'Unable to resolve %s parameter "$%s"%s: no type hint, binding, or default value.',
            $type instanceof ReflectionNamedType ? 'scalar' : 'untyped',
            $name,
            $context !== null ? ' for [' . $context . ']' : '',
        ));
    }

    /**
     * Turn a callable spec into a reflection plus an invoker closure.
     *
     * @param callable|array{0: object|string, 1: string}|string $callable
     *
     * @return array{0: ReflectionFunctionAbstract, 1: Closure(array<int, mixed>): mixed}
     *
     * @throws ContainerException When the callable cannot be reflected.
     */
    private function reflectCallable(callable|array|string $callable): array
    {
        try {
            if (is_array($callable)) {
                /** @var object|string $target */
                $target = $callable[0];
                $method = (string) $callable[1];

                $object = is_string($target) ? $this->resolve($target, []) : $target;
                $reflection = new ReflectionMethod($object, $method);

                return [$reflection, static fn (array $args): mixed => $reflection->invokeArgs($object, $args)];
            }

            if (is_string($callable) && str_contains($callable, '::')) {
                [$class, $method] = explode('::', $callable, 2);
                $reflection = new ReflectionMethod($class, $method);

                if ($reflection->isStatic()) {
                    return [$reflection, static fn (array $args): mixed => $reflection->invokeArgs(null, $args)];
                }

                $object = $this->resolve($class, []);

                return [$reflection, static fn (array $args): mixed => $reflection->invokeArgs($object, $args)];
            }

            $reflection = new ReflectionFunction(Closure::fromCallable($callable));

            return [$reflection, static fn (array $args): mixed => $reflection->invokeArgs($args)];
        } catch (ReflectionException $e) {
            throw new ContainerException('Unable to reflect the given callable: ' . $e->getMessage(), 0, $e);
        }
    }
}
