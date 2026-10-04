<?php

declare(strict_types=1);

namespace HaHireAI\Core\Providers;

use HaHireAI\Core\Config\Repository;
use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EventDispatcher as EventDispatcherContract;
use HaHireAI\Core\Contracts\Logger as LoggerContract;
use HaHireAI\Core\Errors\ErrorHandler;
use HaHireAI\Core\Events\Dispatcher as EventsDispatcher;
use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Core\Logging\Logger;
use HaHireAI\Core\Modules\ModuleRegistry;
use HaHireAI\Core\Routing\Dispatcher as RouteDispatcher;
use HaHireAI\Core\Routing\Router;

/**
 * Registers the Core Kernel's services into the container. This is the only
 * provider the kernel loads by default; modules add their own. See
 * docs/SERVICE_CONTAINER.md, docs/BOOTSTRAP_FLOW.md.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $c = $this->container;
        $config = $c->make(Repository::class);

        $c->singleton(EventDispatcherContract::class, static fn (): EventsDispatcher => new EventsDispatcher());

        $c->singleton(LoggerContract::class, static fn (): Logger => new Logger(
            (string) $config->get('path.storage') . '/logs',
            (string) $config->get('app.log_level', 'info'),
        ));

        $c->singleton(Router::class, Router::class);
        $c->singleton(RouteDispatcher::class, RouteDispatcher::class);
        $c->singleton(HealthChecker::class, HealthChecker::class);
        $c->singleton(ModuleRegistry::class, ModuleRegistry::class);

        // Session subsystem: config-driven cookies/lifetime + a driver-based store
        // (file now; redis/database can be registered on this shared factory).
        $c->singleton(
            \HaHireAI\Core\Http\Session\SessionStoreFactory::class,
            static fn (): \HaHireAI\Core\Http\Session\SessionStoreFactory => new \HaHireAI\Core\Http\Session\SessionStoreFactory(),
        );
        $c->singleton(\HaHireAI\Core\Http\Session::class, static fn (Container $container): \HaHireAI\Core\Http\Session => new \HaHireAI\Core\Http\Session(
            \HaHireAI\Core\Http\Session\SessionConfig::fromArray((array) $config->get('session', [])),
            $container->make(\HaHireAI\Core\Http\Session\SessionStoreFactory::class),
        ));
        $c->singleton(\HaHireAI\Core\View\View::class, static fn (): \HaHireAI\Core\View\View => new \HaHireAI\Core\View\View(
            (string) $config->get('path.base') . '/resources/views',
        ));

        $c->singleton(ErrorHandler::class, static fn (Container $container): ErrorHandler => new ErrorHandler(
            (bool) $config->get('app.debug', false),
            $container->make(LoggerContract::class),
            $container->make(EventDispatcherContract::class),
        ));

        // Permissive default; the Billing module overrides this with a real resolver.
        $c->singleton(
            \HaHireAI\Core\Contracts\EntitlementResolver::class,
            \HaHireAI\Core\Billing\NullEntitlementResolver::class,
        );

        // Permissive default; the Billing module overrides this with a seat-aware guard.
        $c->singleton(
            \HaHireAI\Core\Contracts\WorkspaceSeatGuard::class,
            \HaHireAI\Core\Billing\NullWorkspaceSeatGuard::class,
        );

        // Empty default; the Recruitment module overrides this with a real directory.
        $c->singleton(
            \HaHireAI\Core\Contracts\CandidateDirectory::class,
            \HaHireAI\Core\Recruitment\NullCandidateDirectory::class,
        );

        // Empty dashboard snapshot; Recruitment overrides with real KPIs.
        $c->singleton(
            \HaHireAI\Core\Contracts\RecruitmentSnapshot::class,
            \HaHireAI\Core\Recruitment\NullRecruitmentSnapshot::class,
        );

        // No-op write surfaces for the Workflow Engine's actions. The owning
        // modules (Tasks, Notifications, Recruitment) override these with thin
        // adapters over their existing services; until then automations degrade
        // gracefully instead of failing (ARCHITECTURE.md §4).
        $c->singleton(
            \HaHireAI\Core\Contracts\TaskWriter::class,
            \HaHireAI\Core\Workflow\NullTaskWriter::class,
        );
        $c->singleton(
            \HaHireAI\Core\Contracts\NotificationWriter::class,
            \HaHireAI\Core\Workflow\NullNotificationWriter::class,
        );
        $c->singleton(
            \HaHireAI\Core\Contracts\RecruitmentActions::class,
            \HaHireAI\Core\Workflow\NullRecruitmentActions::class,
        );
        $c->singleton(
            \HaHireAI\Core\Contracts\LearningCatalog::class,
            \HaHireAI\Core\Learning\NullLearningCatalog::class,
        );
    }
}
