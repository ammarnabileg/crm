<?php

declare(strict_types=1);

namespace Nizam\Platform\Container;

/**
 * Base class for the units that wire a slice of the platform into the {@see Container}.
 *
 * Registration happens in two phases so that boot-time logic can safely depend on services any
 * provider registered:
 *   1. {@see self::register()} — bind services; MUST NOT resolve other services.
 *   2. {@see self::boot()} — runs after every provider has registered; may resolve and configure
 *      services. The default implementation is a no-op.
 *
 * Providers are collected and driven by {@see \Nizam\Platform\Bootstrap\Application}.
 */
abstract class ServiceProvider
{
    /**
     * Bind this provider's services into the container.
     *
     * Implementations must only register bindings here, never resolve them, because other
     * providers may not have registered their services yet.
     */
    abstract public function register(Container $container): void;

    /**
     * Perform any wiring that must run after all providers have registered.
     *
     * Safe to resolve services here. Override when needed; the default does nothing.
     */
    public function boot(Container $container): void
    {
    }
}
