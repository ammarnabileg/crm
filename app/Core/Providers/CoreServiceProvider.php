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
        $c->singleton(\HaHireAI\Core\Http\Session::class, \HaHireAI\Core\Http\Session::class);
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
    }
}
