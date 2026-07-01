<?php

declare(strict_types=1);

namespace Nizam\Platform\Bootstrap;

use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Tenancy\TenantContext;
use Nizam\Platform\Config\Config;
use Nizam\Platform\Container\Container;
use Nizam\Platform\Container\ServiceProvider;
use Nizam\Platform\Event\EventDispatcher;
use Nizam\Platform\Event\ListenerProvider;
use Nizam\Platform\Logging\handlers\JsonLineHandler;
use Nizam\Platform\Logging\LogManager;
use Nizam\Platform\Support\SystemClock;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Registers the platform's always-on singletons into the {@see Container}.
 *
 * This is the one provider {@see Application} always installs. It binds, as shared instances, the
 * foundation spine every other provider builds upon:
 *   - the {@see Config} repository (from the array the application was configured with),
 *   - the {@see Clock} port (to {@see SystemClock}),
 *   - the PSR-14 {@see ListenerProvider} and {@see EventDispatcher},
 *   - the {@see LogManager} (writing JSON lines to the configured log path, or to stderr), and
 *   - the per-request {@see TenantContext}.
 *
 * PSR interface ids are aliased to the concrete singletons so consumers can depend on the
 * standard contracts.
 */
final class CoreServiceProvider extends ServiceProvider
{
    /**
     * @param array<string, mixed> $config The full configuration tree.
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * Bind the core singletons.
     */
    public function register(Container $container): void
    {
        $config = new Config($this->config);
        $container->instance(Config::class, $config);

        // Clock port -> system clock.
        $container->singleton(SystemClock::class, static fn (): SystemClock => new SystemClock(
            is_string($tz = $config->get('app.timezone', 'UTC')) ? $tz : 'UTC',
        ));
        $container->singleton(Clock::class, static fn (Container $c): Clock => $c->get(SystemClock::class));

        // PSR-14 event pipeline.
        $container->singleton(ListenerProvider::class, static fn (): ListenerProvider => new ListenerProvider());
        $container->singleton(
            ListenerProviderInterface::class,
            static fn (Container $c): ListenerProviderInterface => $c->get(ListenerProvider::class),
        );
        $container->singleton(
            EventDispatcher::class,
            static fn (Container $c): EventDispatcher => new EventDispatcher($c->get(ListenerProvider::class)),
        );
        $container->singleton(
            EventDispatcherInterface::class,
            static fn (Container $c): EventDispatcherInterface => $c->get(EventDispatcher::class),
        );

        // Logging.
        $container->singleton(LogManager::class, function () use ($container): LogManager {
            /** @var Clock $clock */
            $clock = $container->get(Clock::class);
            /** @var Config $config */
            $config = $container->get(Config::class);

            $target = $config->get('logging.path');
            $handler = new JsonLineHandler(is_string($target) && $target !== '' ? $target : STDERR);

            return new LogManager($clock, [$handler]);
        });

        // Tenancy.
        $container->singleton(TenantContext::class, static fn (): TenantContext => new TenantContext());

        // Expose the container itself under the PSR-11 id.
        $container->instance(ContainerInterface::class, $container);
        $container->instance(Container::class, $container);
    }
}
