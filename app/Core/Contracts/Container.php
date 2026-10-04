<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * Dependency-injection container contract.
 *
 * Business code depends on this interface, never on a concrete container.
 * See docs/SERVICE_CONTAINER.md.
 */
interface Container
{
    /** Register a transient binding (a new instance per resolution). */
    public function bind(string $id, callable|string|null $concrete = null): void;

    /** Register a shared binding (one instance for the container's lifetime). */
    public function singleton(string $id, callable|string|null $concrete = null): void;

    /** Register an already-constructed instance as a shared binding. */
    public function instance(string $id, object $instance): object;

    /** Resolve an entry, autowiring constructor dependencies as needed. */
    public function make(string $id, array $parameters = []): object;

    /** True if the id has an explicit binding or instance. */
    public function has(string $id): bool;

    /** Invoke a callable, resolving its parameters from the container. */
    public function call(callable $callback, array $parameters = []): mixed;
}
