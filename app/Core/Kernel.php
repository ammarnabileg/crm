<?php

declare(strict_types=1);

namespace HaHireAI\Core;

use HaHireAI\Core\Config\Environment;
use HaHireAI\Core\Config\Repository;
use HaHireAI\Core\Contracts\Container as ContainerContract;
use HaHireAI\Core\Errors\ErrorHandler;
use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Core\Health\Probes\PhpVersionProbe;
use HaHireAI\Core\Health\Probes\StorageWritableProbe;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Modules\ModuleRegistry;
use HaHireAI\Core\Providers\CoreServiceProvider;
use HaHireAI\Core\Providers\ServiceProvider;
use HaHireAI\Core\Routing\Dispatcher;
use HaHireAI\Core\Routing\Router;

/**
 * The Application Kernel — the project's single entry point. It loads the
 * environment and configuration, registers services and modules, wires routing,
 * and turns a Request into a Response. It holds NO business logic.
 *
 * See docs/BOOTSTRAP_FLOW.md and docs/ARCHITECTURE.md §6–§7.
 */
final class Kernel
{
    private static ?Kernel $instance = null;

    private bool $booted = false;

    /** @var list<class-string<ServiceProvider>> */
    private array $providers = [
        CoreServiceProvider::class,
    ];

    public function __construct(
        private readonly ContainerContract $container,
        private readonly string $basePath,
    ) {
        self::$instance = $this;
        $this->container->instance(ContainerContract::class, $container);
        $this->container->instance(self::class, $this);
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \RuntimeException('Kernel has not been constructed.');
    }

    public function container(): ContainerContract
    {
        return $this->container;
    }

    public function basePath(string $path = ''): string
    {
        return rtrim($this->basePath, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $this->loadEnvironment();
        $this->loadConfiguration();
        $this->registerProviders();
        $this->registerErrorHandling();
        $this->registerModules();
        $this->registerCoreHealthProbes();

        $this->booted = true;

        return $this;
    }

    public function handle(Request $request): Response
    {
        $this->boot();

        return $this->container->make(Dispatcher::class)
            ->dispatch($request, $this->container->make(Router::class));
    }

    public function run(): void
    {
        $this->handle(Request::capture())->send();
    }

    private function loadEnvironment(): void
    {
        $env = (new Environment())->load($this->basePath('.env'));
        $this->container->instance(Environment::class, $env);
    }

    private function loadConfiguration(): void
    {
        $config = (new Repository())->loadDirectory($this->basePath('config'));
        $config->set('path.base', $this->basePath());
        $config->set('path.storage', $this->basePath('storage'));
        $config->set('path.config', $this->basePath('config'));
        $this->container->instance(Repository::class, $config);
    }

    private function registerErrorHandling(): void
    {
        $this->container->make(ErrorHandler::class)->register();
    }

    private function registerProviders(): void
    {
        /** @var list<ServiceProvider> $booted */
        $booted = [];

        foreach ($this->providers as $providerClass) {
            $provider = new $providerClass($this->container);
            $provider->register();
            $booted[] = $provider;
        }

        foreach ($booted as $provider) {
            $provider->boot();
        }
    }

    private function registerModules(): void
    {
        $registry = $this->container->make(ModuleRegistry::class);
        $router = $this->container->make(Router::class);

        foreach ($registry->ordered() as $module) {
            $module->register($this->container);
        }

        foreach ($registry->ordered() as $module) {
            $module->boot($this->container);
            $module->routes($router);
        }

        $this->loadGlobalRoutes($router);
    }

    private function loadGlobalRoutes(Router $router): void
    {
        $file = $this->basePath('routes/web.php');

        if (is_file($file)) {
            (static function (Router $router) use ($file): void {
                require $file;
            })($router);
        }
    }

    private function registerCoreHealthProbes(): void
    {
        $health = $this->container->make(HealthChecker::class);
        $health->register(new PhpVersionProbe('8.3.0'));
        $health->register(new StorageWritableProbe($this->basePath('storage')));
    }
}
