<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\InvitationInbox;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Memberships\Application\InvitationService;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Memberships\Presentation\InvitationController;
use HaHireAI\Modules\Memberships\Presentation\InvitesController;
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
        // The shared membership surface other modules depend on (ARCHITECTURE.md §4).
        $container->singleton(MemberDirectory::class, static fn (Container $c): MemberDirectory => $c->make(MembershipService::class));
        // Pending-invitation inbox, consumed by the context switcher / post-login funnel.
        $container->singleton(InvitationInbox::class, static fn (Container $c): InvitationInbox => $c->make(InvitationService::class));
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        $router->get('/members', [MembersController::class, 'index']);
        $router->post('/members/invite', [MembersController::class, 'invite']);
        $router->post('/members/invitations/{invitationId}/roles', [MembersController::class, 'updateInvitationRoles']);
        $router->post('/members/invitations/{invitationId}/revoke', [MembersController::class, 'revokeInvitation']);
        $router->post('/members/{membershipId}/suspend', [MembersController::class, 'suspend']);
        $router->post('/members/{membershipId}/activate', [MembersController::class, 'activate']);
        $router->post('/members/{membershipId}/remove', [MembersController::class, 'remove']);

        // Accept-first: a user reviews and accepts/declines their own pending
        // invitations in-app (surfaced by the context switcher); the workspace
        // appears only after they accept.
        $router->get('/invites', [InvitesController::class, 'index']);
        $router->post('/invites/{invitationId}/accept', [InvitesController::class, 'accept']);
        $router->post('/invites/{invitationId}/decline', [InvitesController::class, 'decline']);

        $router->get('/invitations/{code}', [InvitationController::class, 'showAccept']);
        $router->post('/invitations/{code}', [InvitationController::class, 'accept']);
    }
}
