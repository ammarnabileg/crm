<?php

declare(strict_types=1);

namespace Nizam\Runtime;

use Nizam\Platform\Container\Container;
use Nizam\Runtime\Infrastructure\PersistenceDriver;
use Nizam\Runtime\Infrastructure\RuntimeServiceProvider;
use Nizam\Runtime\Orchestration\MasterOrchestrator;

/**
 * The single entry point that installs the AI Runtime into an application.
 *
 * A host application registers the Runtime by calling {@see self::register()} once with its
 * {@see Container}; the module then wires its execution and orchestration ports, adapters, services, the
 * execution engine factory, and the single-entry {@see MasterOrchestrator} through the
 * {@see RuntimeServiceProvider}. This facade is the Runtime's stable seam: the Interface layer (HTTP and
 * Console adapters) will be added on top of it once those platforms land, without the rest of the system
 * needing to know the Runtime's internals.
 *
 * The Runtime's PUBLIC SURFACE is the {@see MasterOrchestrator}: nothing executes work except by passing
 * an {@see \Nizam\Runtime\Orchestration\ValueObject\OrchestrationRequest} to its `handle()`. Everything
 * else — the execution aggregate, repositories, event store, lock manager, coordinator, mappers — is
 * internal. Resolve the orchestrator from the container after registering, via {@see self::orchestrator()}.
 *
 * Persistence defaults to {@see PersistenceDriver::InMemory}; pass {@see PersistenceDriver::Pdo} (having
 * also bound a shared {@see \PDO} in the container) to use the durable PDO execution adapters.
 */
final class RuntimeModule
{
    /**
     * Install the Runtime: bind its execution/orchestration services and the single-entry orchestrator.
     *
     * A host should install the Runtime exactly once per container.
     *
     * @param Container         $container The application container to install the Runtime into.
     * @param PersistenceDriver $driver    Which execution persistence adapters the Runtime should bind.
     */
    public static function register(
        Container $container,
        PersistenceDriver $driver = PersistenceDriver::InMemory,
    ): void {
        $provider = new RuntimeServiceProvider($driver);
        $provider->register($container);
        $provider->boot($container);
    }

    /**
     * Resolve the single-entry {@see MasterOrchestrator} from a container the Runtime is installed into.
     *
     * @param Container $container The application container the Runtime was registered into.
     */
    public static function orchestrator(Container $container): MasterOrchestrator
    {
        /** @var MasterOrchestrator $orchestrator */
        $orchestrator = $container->get(MasterOrchestrator::class);

        return $orchestrator;
    }
}
