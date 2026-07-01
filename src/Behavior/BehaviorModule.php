<?php

declare(strict_types=1);

namespace Nizam\Behavior;

use Nizam\Behavior\Infrastructure\BehaviorServiceProvider;
use Nizam\Behavior\Infrastructure\PersistenceDriver;
use Nizam\Platform\Container\Container;

/**
 * The single entry point that installs the Behavior bounded context into an application.
 *
 * A host application registers the module by calling {@see self::register()} once with its
 * {@see Container}; the module then wires its ports, adapters, domain services, and application
 * handlers through {@see BehaviorServiceProvider} and registers its use cases against the platform
 * command and query buses. This facade is the module's stable seam: the Interface layer (HTTP and
 * Console adapters) will be added on top of it once the HTTP/Console platform lands, without the rest
 * of the platform needing to know the module's internals.
 *
 * The module's PUBLIC SURFACE is:
 *   - the platform command bus (dispatch the Application `Command` DTOs), and
 *   - the platform query bus (dispatch the Application `Query` DTOs), and
 *   - the {@see \Nizam\Behavior\Application\Service\BehaviorLearningService} application service.
 * Everything else — aggregates, repositories, mappers, adapters — is internal.
 */
final class BehaviorModule
{
    /**
     * Install the module: bind its services and register its bus handlers.
     *
     * This runs the {@see BehaviorServiceProvider}'s `register()` (bindings) followed by its `boot()`
     * (bus handler registration), the order the platform's own bootstrap uses. It is idempotent per
     * container in the sense that a second call would attempt to re-register bus handlers; a host
     * should install each module exactly once.
     *
     * Persistence defaults to {@see PersistenceDriver::InMemory}; pass {@see PersistenceDriver::Pdo}
     * (having also bound a shared {@see \PDO} in the container) to use the durable PDO adapters.
     *
     * @param Container         $container The application container to install the module into.
     * @param PersistenceDriver $driver    Which persistence adapters the module should bind.
     */
    public static function register(
        Container $container,
        PersistenceDriver $driver = PersistenceDriver::InMemory,
    ): void {
        $provider = new BehaviorServiceProvider($driver);
        $provider->register($container);
        $provider->boot($container);
    }
}
