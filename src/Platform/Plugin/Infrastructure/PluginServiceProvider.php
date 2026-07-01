<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure;

use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Container\Container;
use Nizam\Platform\Container\ServiceProvider;
use Nizam\Platform\Event\EventDispatcher;
use Nizam\Platform\Plugin\Infrastructure\Container\ContainerHealthCheckResolver;
use Nizam\Platform\Plugin\Infrastructure\Container\ContainerPluginInstantiator;
use Nizam\Platform\Plugin\Infrastructure\Event\DispatchingPluginEventPublisher;
use Nizam\Platform\Plugin\Infrastructure\Persistence\InMemory\InMemoryPluginRepository;
use Nizam\Platform\Plugin\Infrastructure\Persistence\Pdo\PdoPluginRepository;
use Nizam\Platform\Plugin\Infrastructure\Persistence\Pdo\PluginHydrator;
use Nizam\Platform\Plugin\Manager\PluginDiscoveryService;
use Nizam\Platform\Plugin\Manager\PluginInstaller;
use Nizam\Platform\Plugin\Manager\PluginLifecycleManager;
use Nizam\Platform\Plugin\Manager\PluginManager;
use Nizam\Platform\Plugin\Manager\PluginRegistry;
use Nizam\Platform\Plugin\Port\HealthCheckResolver;
use Nizam\Platform\Plugin\Port\PluginEventPublisher;
use Nizam\Platform\Plugin\Port\PluginInstantiator;
use Nizam\Platform\Plugin\Port\PluginRepository;
use Nizam\Platform\Plugin\Service\PluginDependencyResolver;
use Nizam\Platform\Plugin\Service\PluginHealthChecker;
use Nizam\Platform\Plugin\Service\PluginPermissionGate;
use Nizam\Platform\Plugin\Service\PluginSandbox;
use Nizam\Platform\Plugin\Service\PluginValidator;
use PDO;

/**
 * Wires the Plugin Platform into the platform {@see Container}.
 *
 * {@see self::register()} binds every plugin port to a concrete Infrastructure adapter and makes the
 * domain services, application services and the {@see PluginManager} facade resolvable. Persistence is
 * chosen explicitly by the {@see PersistenceDriver} passed at construction: {@see PersistenceDriver::Pdo}
 * binds the durable {@see PdoPluginRepository} against a shared {@see PDO} the host also binds in the
 * container, while the default {@see PersistenceDriver::InMemory} binds the {@see InMemoryPluginRepository}
 * so the module runs and is testable with no database. The instantiator, the health-check resolver and
 * the event publisher are bound to their container-backed / dispatching adapters. The repository, the
 * event publisher, the registry and the manager are singletons so their state (and any in-memory data)
 * is shared for the container's lifetime. This module exposes no command/query-bus handlers, so
 * {@see self::boot()} is left as the inherited no-op.
 */
final class PluginServiceProvider extends ServiceProvider
{
    /**
     * @param PersistenceDriver $driver Which persistence adapter to bind the plugin repository to.
     */
    public function __construct(
        private readonly PersistenceDriver $driver = PersistenceDriver::InMemory,
    ) {
    }

    /**
     * Bind the module's ports, adapters, domain services, application services and facade.
     */
    public function register(Container $container): void
    {
        $this->registerPersistence($container);
        $this->registerAdapters($container);
        $this->registerServices($container);
        $this->registerManager($container);
    }

    /**
     * Bind the plugin repository to the adapter selected by the configured {@see PersistenceDriver}.
     */
    private function registerPersistence(Container $container): void
    {
        $container->singleton(PluginHydrator::class, static fn (): PluginHydrator => new PluginHydrator());

        if ($this->driver === PersistenceDriver::Pdo) {
            $container->singleton(
                PluginRepository::class,
                static fn (Container $c): PluginRepository => new PdoPluginRepository(
                    $c->get(PDO::class),
                    $c->get(PluginHydrator::class),
                ),
            );

            return;
        }

        $container->singleton(
            PluginRepository::class,
            static fn (): PluginRepository => new InMemoryPluginRepository(),
        );
    }

    /**
     * Bind the instantiator, health-check resolver and event publisher ports to their adapters.
     */
    private function registerAdapters(Container $container): void
    {
        $container->singleton(
            PluginInstantiator::class,
            static fn (Container $c): PluginInstantiator => new ContainerPluginInstantiator($c),
        );
        $container->singleton(
            HealthCheckResolver::class,
            static fn (Container $c): HealthCheckResolver => new ContainerHealthCheckResolver($c),
        );
        $container->singleton(
            PluginEventPublisher::class,
            static fn (Container $c): PluginEventPublisher => new DispatchingPluginEventPublisher(
                $c->get(EventDispatcher::class),
            ),
        );
    }

    /**
     * Bind the stateless domain services and the application-layer plugin services.
     */
    private function registerServices(Container $container): void
    {
        $container->singleton(PluginValidator::class, static fn (): PluginValidator => new PluginValidator());
        $container->singleton(
            PluginDependencyResolver::class,
            static fn (): PluginDependencyResolver => new PluginDependencyResolver(),
        );
        $container->singleton(
            PluginPermissionGate::class,
            static fn (): PluginPermissionGate => new PluginPermissionGate(),
        );

        $container->singleton(
            PluginSandbox::class,
            static fn (Container $c): PluginSandbox => new PluginSandbox(
                $c->get(PluginEventPublisher::class),
                $c->get(Clock::class),
            ),
        );
        $container->singleton(
            PluginHealthChecker::class,
            static fn (Container $c): PluginHealthChecker => new PluginHealthChecker(
                $c->get(HealthCheckResolver::class),
                $c->get(Clock::class),
            ),
        );

        $container->singleton(
            PluginRegistry::class,
            static fn (Container $c): PluginRegistry => new PluginRegistry(
                $c->get(PluginRepository::class),
            ),
        );
        $container->singleton(
            PluginDiscoveryService::class,
            static fn (Container $c): PluginDiscoveryService => new PluginDiscoveryService(
                $c->get(PluginEventPublisher::class),
                $c->get(Clock::class),
            ),
        );
        $container->singleton(
            PluginInstaller::class,
            static fn (Container $c): PluginInstaller => new PluginInstaller(
                $c->get(PluginRegistry::class),
                $c->get(PluginValidator::class),
                $c->get(PluginDependencyResolver::class),
                $c->get(PluginEventPublisher::class),
                $c->get(Clock::class),
            ),
        );
        $container->singleton(
            PluginLifecycleManager::class,
            static fn (Container $c): PluginLifecycleManager => new PluginLifecycleManager(
                $c->get(PluginRegistry::class),
                $c->get(PluginInstantiator::class),
                $c->get(PluginSandbox::class),
                $c->get(PluginEventPublisher::class),
                $c->get(Clock::class),
            ),
        );
    }

    /**
     * Bind the {@see PluginManager} facade — the module's public surface — as a singleton.
     */
    private function registerManager(Container $container): void
    {
        $container->singleton(
            PluginManager::class,
            static fn (Container $c): PluginManager => new PluginManager(
                $c->get(PluginRegistry::class),
                $c->get(PluginDiscoveryService::class),
                $c->get(PluginInstaller::class),
                $c->get(PluginLifecycleManager::class),
                $c->get(PluginHealthChecker::class),
            ),
        );
    }
}
