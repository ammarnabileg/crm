<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Small service container with singleton + factory bindings.
 *
 * Deliberately minimal: enough to wire core services (db, session, config,
 * auth, tenant manager) without pulling in a DI framework.
 */
final class Container
{
    private static ?Container $instance = null;

    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $shared = [];

    public static function getInstance(): Container
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(Container $container): void
    {
        self::$instance = $container;
    }

    public function bind(string $key, Closure $resolver, bool $shared = false): void
    {
        $this->bindings[$key] = $resolver;
        $this->shared[$key] = $shared;
        unset($this->instances[$key]);
    }

    public function singleton(string $key, Closure $resolver): void
    {
        $this->bind($key, $resolver, true);
    }

    public function instance(string $key, mixed $object): void
    {
        $this->instances[$key] = $object;
        $this->shared[$key] = true;
    }

    public function make(string $key): mixed
    {
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        if (! isset($this->bindings[$key])) {
            throw new RuntimeException("Service [{$key}] is not bound in the container.");
        }

        $object = ($this->bindings[$key])($this);

        if ($this->shared[$key] ?? false) {
            $this->instances[$key] = $object;
        }

        return $object;
    }

    public function has(string $key): bool
    {
        return isset($this->bindings[$key]) || array_key_exists($key, $this->instances);
    }
}
