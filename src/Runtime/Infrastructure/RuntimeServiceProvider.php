<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure;

use Nizam\Kernel\Domain\Clock;
use Nizam\Platform\Container\Container;
use Nizam\Platform\Container\ServiceProvider;
use Nizam\Platform\Event\EventDispatcher;
use Nizam\Platform\Support\SystemClock;
use Nizam\Runtime\Execution\Application\RetryEngine;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventPublisher;
use Nizam\Runtime\Execution\Domain\Port\ExecutionEventStore;
use Nizam\Runtime\Execution\Domain\Port\ExecutionLockManager;
use Nizam\Runtime\Execution\Domain\Port\ExecutionRepository;
use Nizam\Runtime\Infrastructure\Event\DispatchingExecutionEventPublisher;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionEventStore;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionLockManager;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionRepository;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionEventSerializer;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\ExecutionRowMapper;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionEventStore;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionLockManager;
use Nizam\Runtime\Infrastructure\Persistence\Pdo\PdoExecutionRepository;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryAutomationSelector;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryDepartmentResolver;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryManagerPluginResolver;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryPermissionProvider;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryTenantProvider;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryUserProvider;
use Nizam\Runtime\Infrastructure\Provider\InMemory\InMemoryWorkerPluginResolver;
use Nizam\Runtime\Orchestration\ConfidenceEvaluator;
use Nizam\Runtime\Orchestration\MasterOrchestrator;
use Nizam\Runtime\Orchestration\Port\AutomationSelector;
use Nizam\Runtime\Orchestration\Port\DepartmentResolver;
use Nizam\Runtime\Orchestration\Port\ExecutionEngineFactory;
use Nizam\Runtime\Orchestration\Port\ManagerAgent;
use Nizam\Runtime\Orchestration\Port\ManagerPluginResolver;
use Nizam\Runtime\Orchestration\Port\PermissionProvider;
use Nizam\Runtime\Orchestration\Port\TenantProvider;
use Nizam\Runtime\Orchestration\Port\UserProvider;
use Nizam\Runtime\Orchestration\Port\WorkerInvoker;
use Nizam\Runtime\Orchestration\Port\WorkerPluginResolver;
use Nizam\Runtime\Orchestration\ResultMerger;
use Nizam\Runtime\Orchestration\Testing\DeterministicManagerAgent;
use Nizam\Runtime\Orchestration\Testing\DeterministicWorkerInvoker;
use Nizam\Runtime\Orchestration\WorkerCoordinator;
use PDO;

/**
 * Wires the AI Runtime into the platform {@see Container}.
 *
 * {@see self::register()} binds every execution and orchestration port to a concrete adapter and makes
 * the {@see \Nizam\Runtime\Execution\Application\ExecutionEngine} (via its factory) and the single-entry
 * {@see MasterOrchestrator} resolvable. Persistence is chosen explicitly by the {@see PersistenceDriver}
 * passed at construction: {@see PersistenceDriver::Pdo} binds the durable PDO execution adapters against
 * a shared {@see PDO} the host also binds in the container, while the default
 * {@see PersistenceDriver::InMemory} binds the in-memory execution adapters so the Runtime runs and is
 * testable with no database. The orchestration provider ports (tenant/user/permission/department/
 * manager-plugin/worker-plugin/automation) are bound to their real, seedable in-memory adapters as safe
 * defaults until their production directory/registry adapters land; a host installing the Runtime for a
 * tenant re-binds these to the live sources. The manager-agent and worker-invoker seams are bound to
 * their deterministic default implementations. Every port is bound as a singleton so shared state (locks,
 * in-memory data, seeded providers) persists for the container's lifetime. The event publisher always
 * dispatches through the platform {@see EventDispatcher}.
 */
final class RuntimeServiceProvider extends ServiceProvider
{
    /**
     * @param PersistenceDriver $driver Which execution persistence adapters to bind the Runtime's ports to.
     */
    public function __construct(
        private readonly PersistenceDriver $driver = PersistenceDriver::InMemory,
    ) {
    }

    /**
     * Bind the Runtime's execution/orchestration ports, adapters, services, engine factory, and orchestrator.
     */
    public function register(Container $container): void
    {
        $this->registerClock($container);
        $this->registerExecutionPersistence($container);
        $this->registerEventPublisher($container);
        $this->registerEngineFactory($container);
        $this->registerOrchestrationProviders($container);
        $this->registerOrchestrationServices($container);
        $this->registerOrchestrator($container);
    }

    /**
     * Bind a default {@see Clock} only when the host has not already provided one.
     */
    private function registerClock(Container $container): void
    {
        if (!$container->has(Clock::class)) {
            $container->singleton(Clock::class, static fn (): Clock => new SystemClock());
        }
    }

    /**
     * Bind the execution repository, append-only event store, and lock manager to the selected driver.
     */
    private function registerExecutionPersistence(Container $container): void
    {
        $container->singleton(
            ExecutionEventSerializer::class,
            static fn (): ExecutionEventSerializer => new ExecutionEventSerializer(),
        );
        $container->singleton(ExecutionRowMapper::class, static fn (): ExecutionRowMapper => new ExecutionRowMapper());
        $container->singleton(RetryEngine::class, static fn (): RetryEngine => new RetryEngine());

        if ($this->driver === PersistenceDriver::Pdo) {
            $container->singleton(
                ExecutionEventStore::class,
                static fn (Container $c): ExecutionEventStore => new PdoExecutionEventStore(
                    $c->get(PDO::class),
                    $c->get(ExecutionEventSerializer::class),
                ),
            );
            $container->singleton(
                ExecutionRepository::class,
                static fn (Container $c): ExecutionRepository => new PdoExecutionRepository(
                    $c->get(PDO::class),
                    $c->get(ExecutionEventStore::class),
                    $c->get(ExecutionRowMapper::class),
                ),
            );
            $container->singleton(
                ExecutionLockManager::class,
                static fn (Container $c): ExecutionLockManager => new PdoExecutionLockManager(
                    $c->get(PDO::class),
                    $c->get(Clock::class),
                ),
            );

            return;
        }

        $container->singleton(
            ExecutionEventStore::class,
            static fn (): ExecutionEventStore => new InMemoryExecutionEventStore(),
        );
        $container->singleton(
            ExecutionRepository::class,
            static fn (): ExecutionRepository => new InMemoryExecutionRepository(),
        );
        $container->singleton(
            ExecutionLockManager::class,
            static fn (Container $c): ExecutionLockManager => new InMemoryExecutionLockManager($c->get(Clock::class)),
        );
    }

    /**
     * Bind the execution event publisher port to the dispatching adapter.
     */
    private function registerEventPublisher(Container $container): void
    {
        $container->singleton(
            ExecutionEventPublisher::class,
            static fn (Container $c): ExecutionEventPublisher => new DispatchingExecutionEventPublisher(
                $c->get(EventDispatcher::class),
            ),
        );
    }

    /**
     * Bind the request-scoped execution engine factory over the shared persistence collaborators.
     */
    private function registerEngineFactory(Container $container): void
    {
        $container->singleton(
            ExecutionEngineFactory::class,
            static fn (Container $c): ExecutionEngineFactory => new RuntimeExecutionEngineFactory(
                $c->get(ExecutionRepository::class),
                $c->get(ExecutionEventStore::class),
                $c->get(ExecutionLockManager::class),
                $c->get(ExecutionEventPublisher::class),
                $c->get(RetryEngine::class),
                $c->get(Clock::class),
            ),
        );
    }

    /**
     * Bind the orchestration provider ports to their real, seedable in-memory safe-default adapters.
     */
    private function registerOrchestrationProviders(Container $container): void
    {
        $container->singleton(TenantProvider::class, static fn (): TenantProvider => new InMemoryTenantProvider());
        $container->singleton(UserProvider::class, static fn (): UserProvider => new InMemoryUserProvider());
        $container->singleton(
            PermissionProvider::class,
            static fn (): PermissionProvider => new InMemoryPermissionProvider(),
        );
        $container->singleton(
            DepartmentResolver::class,
            static fn (): DepartmentResolver => new InMemoryDepartmentResolver(),
        );
        $container->singleton(
            ManagerPluginResolver::class,
            static fn (): ManagerPluginResolver => new InMemoryManagerPluginResolver(),
        );
        $container->singleton(
            WorkerPluginResolver::class,
            static fn (): WorkerPluginResolver => new InMemoryWorkerPluginResolver(),
        );
        $container->singleton(
            AutomationSelector::class,
            static fn (): AutomationSelector => new InMemoryAutomationSelector(),
        );
    }

    /**
     * Bind the orchestration domain services and the manager-agent/worker-invoker seams.
     */
    private function registerOrchestrationServices(Container $container): void
    {
        $container->singleton(ResultMerger::class, static fn (): ResultMerger => new ResultMerger());
        $container->singleton(ConfidenceEvaluator::class, static fn (): ConfidenceEvaluator => new ConfidenceEvaluator());
        $container->singleton(ManagerAgent::class, static fn (): ManagerAgent => new DeterministicManagerAgent());
        $container->singleton(WorkerInvoker::class, static fn (): WorkerInvoker => new DeterministicWorkerInvoker());
        $container->singleton(
            WorkerCoordinator::class,
            static fn (Container $c): WorkerCoordinator => new WorkerCoordinator($c->get(WorkerInvoker::class)),
        );
    }

    /**
     * Bind the single-entry {@see MasterOrchestrator} over its resolved collaborators.
     */
    private function registerOrchestrator(Container $container): void
    {
        $container->singleton(
            MasterOrchestrator::class,
            static fn (Container $c): MasterOrchestrator => new MasterOrchestrator(
                tenants: $c->get(TenantProvider::class),
                users: $c->get(UserProvider::class),
                permissions: $c->get(PermissionProvider::class),
                departments: $c->get(DepartmentResolver::class),
                managerPlugins: $c->get(ManagerPluginResolver::class),
                workerPlugins: $c->get(WorkerPluginResolver::class),
                managerAgent: $c->get(ManagerAgent::class),
                coordinator: $c->get(WorkerCoordinator::class),
                merger: $c->get(ResultMerger::class),
                evaluator: $c->get(ConfidenceEvaluator::class),
                engineFactory: $c->get(ExecutionEngineFactory::class),
                automations: $c->get(AutomationSelector::class),
                clock: $c->get(Clock::class),
            ),
        );
    }
}
