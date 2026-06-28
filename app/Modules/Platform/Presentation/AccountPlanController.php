<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\PaymentSettings;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Platform\Application\AccountPlanService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * Self-service account plan (workspace capacity), distinct from a workspace's
 * feature subscription. Each plan grants a number of workspaces the account may
 * run. When the System Owner has payments switched off, any account may pick or
 * upgrade to any plan free; otherwise paid plans are arranged with support.
 */
final class AccountPlanController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly AccountPlanService $accounts,
        private readonly PlanService $plans,
        private readonly PaymentSettings $payments,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/workspaces/create');
        }

        $uid = (string) $this->auth->id();
        $current = $this->accounts->forUser($uid);

        return $this->shell->render($this->context, 'account.plan', [
            'plans' => $this->plans->publicPlans(),
            'currentPlanId' => $current['plan_id'] ?? null,
            'maxWorkspaces' => $this->accounts->maxWorkspaces($uid),
            'activeWorkspaces' => $this->accounts->activeWorkspaceCount($uid),
            'expiresAt' => $current['expires_at'] ?? null,
            'paymentsEnabled' => $this->payments->paymentsEnabled(),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ], ['bypassGate' => true]);
    }

    public function choose(Request $request): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }

        // Paid mode: account-plan changes aren't self-serve (no per-account
        // checkout) — they're arranged with support / by the System Owner.
        if ($this->payments->paymentsEnabled()) {
            $this->session->flash('error', 'Paid plan changes are arranged with support. Please contact us to upgrade your account plan.');

            return Response::redirect('/account/plan');
        }

        $uid = (string) $this->auth->id();
        $planId = trim((string) $request->input('plan_id', '')) ?: null;

        // Only allow plans that are public (or the free tier).
        if ($planId !== null && $this->plans->find($planId) === null) {
            $this->session->flash('error', 'That plan is not available.');

            return Response::redirect('/account/plan');
        }

        $this->accounts->assignPlan($uid, $planId);
        $this->audit->record('platform.account.self_upgrade', [
            'actor_user_id' => $uid,
            'entity_type' => 'account_plan',
            'entity_id' => $uid,
            'changes' => ['plan_id' => $planId],
        ]);
        $this->session->flash('status', $planId === null ? 'Switched to the free tier.' : 'Your plan has been updated — free for a limited time.');

        return Response::redirect('/account/plan');
    }
}
