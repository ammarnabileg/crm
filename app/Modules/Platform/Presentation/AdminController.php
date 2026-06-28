<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Platform\Application\AccountPlanService;
use HaHireAI\Modules\Platform\Application\PlatformAdminService;
use HaHireAI\Modules\Workspaces\Application\PlatformContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceLifecycleService;
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
        private readonly WorkspaceLifecycleService $lifecycle,
        private readonly AccountPlanService $accounts,
        private readonly PlanService $plans,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function workspaces(): Response
    {
        if (($r = $this->gate('system.workspaces.manage')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'admin.workspaces', [
            'workspaces' => $this->admin->workspaces(),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function users(Request $request): Response
    {
        if (($r = $this->gate('system.users.manage')) !== null) {
            return $r;
        }

        $q = trim((string) $request->input('q', ''));

        return $this->shell->render($this->context, 'admin.users', [
            'users' => $this->admin->users(200, $q),
            'plans' => $this->plans->allPlans(),
            'q' => $q,
            'currentUserId' => (string) $this->auth->id(),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function toggleWorkspaceCreation(Request $request, string $id): Response
    {
        if (($r = $this->gate('system.users.manage')) !== null) {
            return $r;
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        $allow = (string) $request->input('allow', '0') === '1';
        if ($this->admin->setCanCreateWorkspaces($id, $allow)) {
            $this->audit->record('platform.user.workspace_creation', [
                'actor_user_id' => $this->auth->id(), 'entity_type' => 'user', 'entity_id' => $id,
                'changes' => ['can_create_workspaces' => $allow],
            ]);
            $this->session->flash('status', $allow ? 'Account may create workspaces.' : 'Account blocked from creating workspaces.');
        } else {
            $this->session->flash('status', 'That account cannot be changed (System Owners are protected).');
        }

        return Response::redirect('/admin/users');
    }

    public function assignPlan(Request $request, string $id): Response
    {
        if (($r = $this->gate('system.subscriptions.manage')) !== null) {
            return $r;
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        $planId = trim((string) $request->input('plan_id', '')) ?: null;
        $this->accounts->assignPlan($id, $planId);
        $this->audit->record('platform.account.plan_assigned', [
            'actor_user_id' => $this->auth->id(), 'entity_type' => 'user', 'entity_id' => $id,
            'changes' => ['plan_id' => $planId],
        ]);
        $this->session->flash('status', $planId === null ? 'Account moved to the free tier.' : 'Plan assigned to the account.');

        return Response::redirect('/admin/users');
    }

    public function grantMonths(Request $request, string $id): Response
    {
        if (($r = $this->gate('system.subscriptions.manage')) !== null) {
            return $r;
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        $months = (int) $request->input('months', 0);
        if ($months > 0) {
            $this->accounts->grantMonths($id, $months);
            $this->audit->record('platform.account.months_granted', [
                'actor_user_id' => $this->auth->id(), 'entity_type' => 'user', 'entity_id' => $id,
                'changes' => ['months' => $months],
            ]);
            $this->session->flash('status', "Granted {$months} free month(s).");
        }

        return Response::redirect('/admin/users');
    }

    public function activateUser(Request $request, string $id): Response
    {
        return $this->userStatusAction($request, $id, 'active', 'User activated.');
    }

    public function deactivateUser(Request $request, string $id): Response
    {
        return $this->userStatusAction($request, $id, 'deactivated', 'User deactivated.');
    }

    private function userStatusAction(Request $request, string $id, string $status, string $ok): Response
    {
        if (($r = $this->gate('system.users.manage')) !== null) {
            return $r;
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }
        if ($id === (string) $this->auth->id()) {
            $this->session->flash('status', 'You cannot change your own account status here.');

            return Response::redirect('/admin/users');
        }

        if ($this->admin->setUserStatus($id, $status)) {
            $this->audit->record('platform.user.status_changed', [
                'actor_user_id' => $this->auth->id(),
                'entity_type' => 'user',
                'entity_id' => $id,
                'changes' => ['status' => $status],
            ]);
            $this->session->flash('status', $ok);
        } else {
            $this->session->flash('status', 'That account cannot be changed (System Owners are protected).');
        }

        return Response::redirect('/admin/users');
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

    /** System Owner workspace lifecycle actions. */
    public function suspendWorkspace(Request $request, string $id): Response
    {
        return $this->lifecycleAction($request, $id, 'suspend', 'Workspace suspended.');
    }

    public function resumeWorkspace(Request $request, string $id): Response
    {
        return $this->lifecycleAction($request, $id, 'resume', 'Workspace resumed.');
    }

    public function archiveWorkspace(Request $request, string $id): Response
    {
        return $this->lifecycleAction($request, $id, 'archive', 'Workspace archived.');
    }

    public function restoreWorkspace(Request $request, string $id): Response
    {
        return $this->lifecycleAction($request, $id, 'restore', 'Workspace restored.');
    }

    private function lifecycleAction(Request $request, string $id, string $action, string $ok): Response
    {
        if (($r = $this->gate('system.workspaces.manage')) !== null) {
            return $r;
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }
        $this->lifecycle->{$action}($id);
        $this->audit->record('platform.workspace.' . $action, [
            'workspace_id' => $id,
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'workspace',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', $ok);

        return Response::redirect('/admin/workspaces');
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
