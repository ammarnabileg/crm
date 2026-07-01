<?php

declare(strict_types=1);

namespace Nizam\Platform\Bootstrap;

use Nizam\Platform\Config\Config;
use Nizam\Platform\Config\Env;
use Nizam\Platform\Container\Container;
use Nizam\Platform\Container\ServiceProvider;
use Nizam\Platform\Event\EventDispatcher;
use Nizam\Platform\Exception\PlatformException;
use Nizam\Platform\Logging\LogManager;

/**
 * The composition root: builds the container, installs service providers, and boots the platform.
 *
 * Lifecycle:
 *   1. {@see self::configure()} — create the application for a base path, install the
 *      {@see CoreServiceProvider} (which registers the foundation singletons), and register any
 *      user providers.
 *   2. {@see self::register()} — add more providers (only valid before boot).
 *   3. {@see self::boot()} — run every provider's {@see ServiceProvider::boot()} once; idempotent.
 *
 * The application wraps, but does not extend, the {@see Container}; callers reach services through
 * {@see self::container()} or the typed accessors ({@see self::config()}, etc.).
 */
final class Application
{
    private readonly Container $container;

    private readonly Environment $environment;

    /**
     * Providers registered but not yet booted, in registration order.
     *
     * @var array<int, ServiceProvider>
     */
    private array $providers = [];

    private bool $booted = false;

    /**
     * @param string               $basePath    The application's root directory.
     * @param array<string, mixed> $config      The full configuration tree.
     * @param Environment          $environment The runtime environment.
     */
    private function __construct(
        private readonly string $basePath,
        array $config,
        Environment $environment,
    ) {
        $this->container = new Container();
        $this->environment = $environment;

        // The core provider is always installed first so its singletons exist for every other provider.
        $core = new CoreServiceProvider($config);
        $core->register($this->container);
        $this->providers[] = $core;
    }

    /**
     * Create and pre-wire an application for the given base path.
     *
     * The environment is read from the `APP_ENV` env var (defaulting to production), and any
     * caller-supplied configuration seeds the {@see Config} repository.
     *
     * @param array<string, mixed> $config Optional initial configuration tree.
     */
    public static function configure(string $basePath, array $config = []): self
    {
        $environment = Environment::fromString(Env::string('APP_ENV', Environment::Production->value));

        return new self(rtrim($basePath, '/\\'), $config, $environment);
    }

    /**
     * Register a service provider.
     *
     * The provider's {@see ServiceProvider::register()} runs immediately; its
     * {@see ServiceProvider::boot()} runs later during {@see self::boot()}.
     *
     * @throws PlatformException When called after the application has booted.
     */
    public function register(ServiceProvider $provider): self
    {
        if ($this->booted) {
            throw new PlatformException(sprintf(
                'Cannot register provider "%s" after the application has booted.',
                $provider::class,
            ));
        }

        $provider->register($this->container);
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * Boot every registered provider exactly once.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

        $this->booted = true;
    }

    /**
     * Whether the application has been booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * The underlying dependency-injection container.
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * The configuration repository singleton.
     */
    public function config(): Config
    {
        /** @var Config */
        return $this->container->get(Config::class);
    }

    /**
     * The event dispatcher singleton.
     */
    public function events(): EventDispatcher
    {
        /** @var EventDispatcher */
        return $this->container->get(EventDispatcher::class);
    }

    /**
     * The log manager singleton.
     */
    public function logs(): LogManager
    {
        /** @var LogManager */
        return $this->container->get(LogManager::class);
    }

    /**
     * The runtime environment.
     */
    public function environment(): Environment
    {
        return $this->environment;
    }

    /**
     * The application's root directory.
     */
    public function basePath(): string
    {
        return $this->basePath;
    }
}
