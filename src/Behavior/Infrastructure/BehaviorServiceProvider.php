<?php

declare(strict_types=1);

namespace Nizam\Behavior\Infrastructure;

use Nizam\Behavior\Application\Command\ApproveBehaviorChange;
use Nizam\Behavior\Application\Command\ApproveBehaviorChangeHandler;
use Nizam\Behavior\Application\Command\ArchiveBehaviorProfile;
use Nizam\Behavior\Application\Command\ArchiveBehaviorProfileHandler;
use Nizam\Behavior\Application\Command\DraftBehaviorProfile;
use Nizam\Behavior\Application\Command\DraftBehaviorProfileHandler;
use Nizam\Behavior\Application\Command\ProposeBehaviorChange;
use Nizam\Behavior\Application\Command\ProposeBehaviorChangeHandler;
use Nizam\Behavior\Application\Command\RejectBehaviorChange;
use Nizam\Behavior\Application\Command\RejectBehaviorChangeHandler;
use Nizam\Behavior\Application\Command\RollbackBehaviorProfile;
use Nizam\Behavior\Application\Command\RollbackBehaviorProfileHandler;
use Nizam\Behavior\Application\Query\ExplainBehaviorProfile;
use Nizam\Behavior\Application\Query\ExplainBehaviorProfileHandler;
use Nizam\Behavior\Application\Query\GetBehaviorProfile;
use Nizam\Behavior\Application\Query\GetBehaviorProfileHandler;
use Nizam\Behavior\Application\Query\GetBehaviorProfileHistory;
use Nizam\Behavior\Application\Query\GetBehaviorProfileHistoryHandler;
use Nizam\Behavior\Application\Query\ListPendingProposals;
use Nizam\Behavior\Application\Query\ListPendingProposalsHandler;
use Nizam\Behavior\Application\Service\BehaviorLearningService;
use Nizam\Behavior\Domain\Port\BehaviorChangeProposalRepository;
use Nizam\Behavior\Domain\Port\BehaviorEventPublisher;
use Nizam\Behavior\Domain\Port\BehaviorProfileRepository;
use Nizam\Behavior\Domain\Port\ObservationSource;
use Nizam\Behavior\Domain\Service\BehaviorProfileConsolidator;
use Nizam\Behavior\Domain\Service\BehaviorRecommendationService;
use Nizam\Behavior\Domain\Service\RiskTolerancePolicy;
use Nizam\Behavior\Infrastructure\Event\DispatchingBehaviorEventPublisher;
use Nizam\Behavior\Infrastructure\Persistence\InMemory\ConfigurableRiskTolerancePolicy;
use Nizam\Behavior\Infrastructure\Persistence\InMemory\InMemoryBehaviorChangeProposalRepository;
use Nizam\Behavior\Infrastructure\Persistence\InMemory\InMemoryBehaviorProfileRepository;
use Nizam\Behavior\Infrastructure\Persistence\InMemory\InMemoryObservationSource;
use Nizam\Behavior\Infrastructure\Persistence\Pdo\BehaviorMapper;
use Nizam\Behavior\Infrastructure\Persistence\Pdo\PdoBehaviorChangeProposalRepository;
use Nizam\Behavior\Infrastructure\Persistence\Pdo\PdoBehaviorProfileRepository;
use Nizam\Behavior\Infrastructure\Persistence\Pdo\PdoObservationSource;
use Nizam\Kernel\Application\CommandBus;
use Nizam\Kernel\Application\QueryBus;
use Nizam\Platform\Container\Container;
use Nizam\Platform\Container\ServiceProvider;
use Nizam\Platform\Event\EventDispatcher;
use PDO;

/**
 * Wires the Behavior module into the platform {@see Container}.
 *
 * {@see self::register()} binds every domain port to a concrete Infrastructure adapter and makes the
 * application command/query handlers and services resolvable. Persistence is chosen explicitly by the
 * {@see PersistenceDriver} passed at construction: {@see PersistenceDriver::Pdo} binds the durable PDO
 * adapters against a shared {@see PDO} the host also binds in the container, while the default
 * {@see PersistenceDriver::InMemory} binds in-memory adapters so the module runs and is testable with
 * no database. {@see self::boot()} resolves the platform command and query buses and registers each
 * handler against its message class, exposing the module's use cases through the buses. Repositories,
 * the event publisher, and the policy are bound as singletons so their state (and any in-memory data)
 * is shared for the lifetime of the container.
 */
final class BehaviorServiceProvider extends ServiceProvider
{
    /**
     * @param PersistenceDriver $driver Which persistence adapters to bind the module's ports to.
     */
    public function __construct(
        private readonly PersistenceDriver $driver = PersistenceDriver::InMemory,
    ) {
    }

    /**
     * Bind the module's ports, adapters, domain services, handlers and application services.
     */
    public function register(Container $container): void
    {
        $this->registerPersistence($container);
        $this->registerDomainServices($container);
        $this->registerEventPublisher($container);
        $this->registerHandlers($container);
    }

    /**
     * Register the module's command and query handlers against the platform buses.
     *
     * Handlers are resolved from the container (with their dependencies autowired) and registered by
     * message class. Each is wrapped in a closure because a {@see \Nizam\Kernel\Application\CommandHandler}
     * is not itself callable. The buses are only wired when both are present in the container, so a
     * container that omits them (e.g. a pure persistence test) still boots cleanly.
     */
    public function boot(Container $container): void
    {
        if ($container->has(CommandBus::class)) {
            /** @var CommandBus $commandBus */
            $commandBus = $container->get(CommandBus::class);
            $this->registerCommandHandlers($container, $commandBus);
        }

        if ($container->has(QueryBus::class)) {
            /** @var QueryBus $queryBus */
            $queryBus = $container->get(QueryBus::class);
            $this->registerQueryHandlers($container, $queryBus);
        }
    }

    /**
     * Bind the persistence ports to the adapters selected by the configured {@see PersistenceDriver}.
     */
    private function registerPersistence(Container $container): void
    {
        $container->singleton(BehaviorMapper::class, static fn (): BehaviorMapper => new BehaviorMapper());

        if ($this->driver === PersistenceDriver::Pdo) {
            $container->singleton(
                BehaviorProfileRepository::class,
                static fn (Container $c): BehaviorProfileRepository => new PdoBehaviorProfileRepository(
                    $c->get(PDO::class),
                    $c->get(BehaviorMapper::class),
                ),
            );
            $container->singleton(
                BehaviorChangeProposalRepository::class,
                static fn (Container $c): BehaviorChangeProposalRepository => new PdoBehaviorChangeProposalRepository(
                    $c->get(PDO::class),
                    $c->get(BehaviorMapper::class),
                ),
            );
            $container->singleton(
                ObservationSource::class,
                static fn (Container $c): ObservationSource => new PdoObservationSource(
                    $c->get(PDO::class),
                    $c->get(BehaviorMapper::class),
                ),
            );

            return;
        }

        $container->singleton(
            BehaviorProfileRepository::class,
            static fn (): BehaviorProfileRepository => new InMemoryBehaviorProfileRepository(),
        );
        $container->singleton(
            BehaviorChangeProposalRepository::class,
            static fn (): BehaviorChangeProposalRepository => new InMemoryBehaviorChangeProposalRepository(),
        );
        $container->singleton(
            ObservationSource::class,
            static fn (): ObservationSource => new InMemoryObservationSource(),
        );
    }

    /**
     * Bind the stateless domain services and the risk-tolerance policy port.
     */
    private function registerDomainServices(Container $container): void
    {
        $container->singleton(
            BehaviorProfileConsolidator::class,
            static fn (): BehaviorProfileConsolidator => new BehaviorProfileConsolidator(),
        );
        $container->singleton(
            BehaviorRecommendationService::class,
            static fn (Container $c): BehaviorRecommendationService => new BehaviorRecommendationService(
                $c->get(BehaviorProfileConsolidator::class),
            ),
        );
        $container->singleton(
            RiskTolerancePolicy::class,
            static fn (): RiskTolerancePolicy => new ConfigurableRiskTolerancePolicy(),
        );
    }

    /**
     * Bind the domain event publisher port to the dispatching adapter.
     */
    private function registerEventPublisher(Container $container): void
    {
        $container->singleton(
            BehaviorEventPublisher::class,
            static fn (Container $c): BehaviorEventPublisher => new DispatchingBehaviorEventPublisher(
                $c->get(EventDispatcher::class),
            ),
        );
    }

    /**
     * Bind the application command/query handlers and services as resolvable services.
     */
    private function registerHandlers(Container $container): void
    {
        $container->singleton(
            BehaviorLearningService::class,
            static fn (Container $c): BehaviorLearningService => new BehaviorLearningService(
                $c->get(BehaviorProfileRepository::class),
                $c->get(BehaviorChangeProposalRepository::class),
                $c->get(ObservationSource::class),
                $c->get(BehaviorProfileConsolidator::class),
                $c->get(BehaviorRecommendationService::class),
                $c->get(BehaviorEventPublisher::class),
                $c->get(\Nizam\Kernel\Domain\Clock::class),
            ),
        );

        $container->bind(
            DraftBehaviorProfileHandler::class,
            static fn (Container $c): DraftBehaviorProfileHandler => new DraftBehaviorProfileHandler(
                $c->get(BehaviorProfileRepository::class),
                $c->get(BehaviorEventPublisher::class),
                $c->get(\Nizam\Kernel\Domain\Clock::class),
            ),
        );
        $container->bind(
            ProposeBehaviorChangeHandler::class,
            static fn (Container $c): ProposeBehaviorChangeHandler => new ProposeBehaviorChangeHandler(
                $c->get(BehaviorLearningService::class),
            ),
        );
        $container->bind(
            ApproveBehaviorChangeHandler::class,
            static fn (Container $c): ApproveBehaviorChangeHandler => new ApproveBehaviorChangeHandler(
                $c->get(BehaviorChangeProposalRepository::class),
                $c->get(BehaviorProfileRepository::class),
                $c->get(BehaviorEventPublisher::class),
                $c->get(\Nizam\Kernel\Domain\Clock::class),
            ),
        );
        $container->bind(
            RejectBehaviorChangeHandler::class,
            static fn (Container $c): RejectBehaviorChangeHandler => new RejectBehaviorChangeHandler(
                $c->get(BehaviorChangeProposalRepository::class),
                $c->get(BehaviorEventPublisher::class),
                $c->get(\Nizam\Kernel\Domain\Clock::class),
            ),
        );
        $container->bind(
            RollbackBehaviorProfileHandler::class,
            static fn (Container $c): RollbackBehaviorProfileHandler => new RollbackBehaviorProfileHandler(
                $c->get(BehaviorProfileRepository::class),
                $c->get(BehaviorEventPublisher::class),
                $c->get(\Nizam\Kernel\Domain\Clock::class),
            ),
        );
        $container->bind(
            ArchiveBehaviorProfileHandler::class,
            static fn (Container $c): ArchiveBehaviorProfileHandler => new ArchiveBehaviorProfileHandler(
                $c->get(BehaviorProfileRepository::class),
                $c->get(BehaviorEventPublisher::class),
                $c->get(\Nizam\Kernel\Domain\Clock::class),
            ),
        );

        $container->bind(
            GetBehaviorProfileHandler::class,
            static fn (Container $c): GetBehaviorProfileHandler => new GetBehaviorProfileHandler(
                $c->get(BehaviorProfileRepository::class),
            ),
        );
        $container->bind(
            GetBehaviorProfileHistoryHandler::class,
            static fn (Container $c): GetBehaviorProfileHistoryHandler => new GetBehaviorProfileHistoryHandler(
                $c->get(BehaviorProfileRepository::class),
            ),
        );
        $container->bind(
            ListPendingProposalsHandler::class,
            static fn (Container $c): ListPendingProposalsHandler => new ListPendingProposalsHandler(
                $c->get(BehaviorChangeProposalRepository::class),
            ),
        );
        $container->bind(
            ExplainBehaviorProfileHandler::class,
            static fn (Container $c): ExplainBehaviorProfileHandler => new ExplainBehaviorProfileHandler(
                $c->get(BehaviorProfileRepository::class),
                $c->get(ObservationSource::class),
                $c->get(BehaviorRecommendationService::class),
            ),
        );
    }

    /**
     * Register every command handler against its command class on the command bus.
     */
    private function registerCommandHandlers(Container $container, CommandBus $commandBus): void
    {
        /** @var array<class-string, class-string> $map */
        $map = [
            DraftBehaviorProfile::class => DraftBehaviorProfileHandler::class,
            ProposeBehaviorChange::class => ProposeBehaviorChangeHandler::class,
            ApproveBehaviorChange::class => ApproveBehaviorChangeHandler::class,
            RejectBehaviorChange::class => RejectBehaviorChangeHandler::class,
            RollbackBehaviorProfile::class => RollbackBehaviorProfileHandler::class,
            ArchiveBehaviorProfile::class => ArchiveBehaviorProfileHandler::class,
        ];

        foreach ($map as $command => $handler) {
            $commandBus->register(
                $command,
                static fn (object $message): mixed => $container->get($handler)->handle($message),
            );
        }
    }

    /**
     * Register every query handler against its query class on the query bus.
     */
    private function registerQueryHandlers(Container $container, QueryBus $queryBus): void
    {
        /** @var array<class-string, class-string> $map */
        $map = [
            GetBehaviorProfile::class => GetBehaviorProfileHandler::class,
            GetBehaviorProfileHistory::class => GetBehaviorProfileHistoryHandler::class,
            ListPendingProposals::class => ListPendingProposalsHandler::class,
            ExplainBehaviorProfile::class => ExplainBehaviorProfileHandler::class,
        ];

        foreach ($map as $query => $handler) {
            $queryBus->register(
                $query,
                static fn (object $message): mixed => $container->get($handler)->handle($message),
            );
        }
    }
}
