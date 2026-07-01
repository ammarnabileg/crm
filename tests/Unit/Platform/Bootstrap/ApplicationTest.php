<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Bootstrap;

use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Tenancy\TenantContext;
use Nizam\Platform\Bootstrap\Application;
use Nizam\Platform\Bootstrap\CoreServiceProvider;
use Nizam\Platform\Bootstrap\Environment;
use Nizam\Platform\Config\Config;
use Nizam\Platform\Container\Container;
use Nizam\Platform\Container\ServiceProvider;
use Nizam\Platform\Event\EventDispatcher;
use Nizam\Platform\Exception\PlatformException;
use Nizam\Platform\Logging\LogManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[CoversClass(CoreServiceProvider::class)]
#[CoversClass(Environment::class)]
final class ApplicationTest extends TestCase
{
    public function testConfigureBindsCoreSingletons(): void
    {
        $app = Application::configure(__DIR__, ['app' => ['timezone' => 'UTC']]);

        self::assertInstanceOf(Config::class, $app->config());
        self::assertSame('UTC', $app->config()->get('app.timezone'));
        self::assertInstanceOf(EventDispatcher::class, $app->events());
        self::assertInstanceOf(LogManager::class, $app->logs());
        self::assertInstanceOf(Clock::class, $app->container()->get(Clock::class));
        self::assertInstanceOf(TenantContext::class, $app->container()->get(TenantContext::class));
    }

    public function testCoreSingletonsAreShared(): void
    {
        $app = Application::configure(__DIR__);
        $container = $app->container();

        self::assertSame($container->get(Clock::class), $container->get(Clock::class));
        self::assertSame($container->get(EventDispatcher::class), $container->get(EventDispatcher::class));
    }

    public function testBootRunsProvidersOnceAndIsIdempotent(): void
    {
        $app = Application::configure(__DIR__);
        $provider = new CountingProvider();
        $app->register($provider);

        self::assertFalse($app->isBooted());

        $app->boot();
        $app->boot();

        self::assertTrue($app->isBooted());
        self::assertSame(1, $provider->registerCount);
        self::assertSame(1, $provider->bootCount);
    }

    public function testRegisterAfterBootThrows(): void
    {
        $app = Application::configure(__DIR__);
        $app->boot();

        $this->expectException(PlatformException::class);
        $app->register(new CountingProvider());
    }

    public function testEnvironmentDefaultsToProduction(): void
    {
        $app = Application::configure(__DIR__);

        self::assertSame(Environment::Production, $app->environment());
    }

    public function testEnvironmentFromStringHandlesAliasesAndUnknowns(): void
    {
        self::assertSame(Environment::Development, Environment::fromString('dev'));
        self::assertSame(Environment::Testing, Environment::fromString('TEST'));
        self::assertSame(Environment::Staging, Environment::fromString('stage'));
        self::assertSame(Environment::Production, Environment::fromString('anything-else'));
    }

    public function testContainerIsExposedUnderPsrId(): void
    {
        $app = Application::configure(__DIR__);

        self::assertSame($app->container(), $app->container()->get(Container::class));
    }
}

final class CountingProvider extends ServiceProvider
{
    public int $registerCount = 0;

    public int $bootCount = 0;

    public function register(Container $container): void
    {
        $this->registerCount++;
    }

    public function boot(Container $container): void
    {
        $this->bootCount++;
    }
}
