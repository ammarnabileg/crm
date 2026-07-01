<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Search;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Search\Presentation\SearchController;

final class SearchModule implements Module
{
    public function name(): string
    {
        return 'Search';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/search', [SearchController::class, 'index']);
    }
}
