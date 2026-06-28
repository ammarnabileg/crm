<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Memberships\Presentation\InvitationController;
use HaHireAI\Modules\Memberships\Presentation\MembersController;

final class MembershipsModule implements Module
{
    public function name(): string
    {
        return 'Memberships';
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
        $router->get('/members', [MembersController::class, 'index']);
        $router->post('/members/invite', [MembersController::class, 'invite']);
        $router->post('/members/{id}/suspend', [MembersController::class, 'suspend']);
        $router->post('/members/{id}/activate', [MembersController::class, 'activate']);
        $router->post('/members/{id}/remove', [MembersController::class, 'remove']);
        $router->get('/invitations/{code}', [InvitationController::class, 'showAccept']);
        $router->post('/invitations/{code}', [InvitationController::class, 'accept']);
    }
}
