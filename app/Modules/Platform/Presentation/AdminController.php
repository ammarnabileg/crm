<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Presentation;

use HaHireAI\Core\Http\Response;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Platform\Application\PlatformAdminService;
use HaHireAI\Modules\Workspaces\Application\PlatformContext;
use HaHireAI\Modules\Workspaces\Presentation\PlatformShell;

/**
 * Platform-context admin screens (System Owner): all workspaces/companies, all
 * users, all subscriptions, and the platform audit trail. Same single dynamic
 * sidebar in the 'platform' context (docs/PERMISSION_MODEL.md §6).
 */
final class AdminController
{
    public function __construct(
        private readonly PlatformShell $shell,
        private readonly PlatformContext $context,
        private readonly AuthContext $auth,
        private readonly PlatformAdminService $admin,
    ) {
    }

    public function workspaces(): Response
    {
        if (($r = $this->gate('system.workspaces.manage')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'admin.workspaces', [
            'workspaces' => $this->admin->workspaces(),
        ]);
    }

    public function users(): Response
    {
        if (($r = $this->gate('system.users.manage')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'admin.users', [
            'users' => $this->admin->users(),
        ]);
    }

    public function subscriptions(): Response
    {
        if (($r = $this->gate('system.subscriptions.manage')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'admin.subscriptions', [
            'subscriptions' => $this->admin->subscriptions(),
        ]);
    }

    public function audit(): Response
    {
        if (($r = $this->gate('system.audit.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'admin.audit', [
            'entries' => $this->admin->audit(),
        ]);
    }

    private function gate(string $permission): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::html('<h1>403</h1><p>Platform access requires a System Owner.</p>', 403);
        }
        if (! $this->context->can($permission)) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        return null;
    }
}
