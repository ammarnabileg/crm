<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Billing\Application\BillingService;
use HaHireAI\Modules\Billing\Application\Exceptions\BillingException;
use HaHireAI\Modules\Billing\Application\InvoiceService;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Billing\Application\SubscriptionService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Workspace billing: plan, status, invoices, and plan changes (docs/BILLING_PLATFORM.md §7). */
final class BillingController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly PlanService $plans,
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceService $invoices,
        private readonly BillingService $billing,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('billing.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'billing.index', [
            'subscription' => $this->subscriptions->findWithPlan($ws),
            'plans' => $this->plans->publicPlans(),
            'invoices' => $this->invoices->listForWorkspace($ws, 20),
            'canManage' => $this->context->can('billing.manage'),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function subscribe(Request $request): Response
    {
        if (($r = $this->gate('billing.manage', $request)) !== null) {
            return $r;
        }

        $planId = (string) $request->input('plan_id', '');
        $hadSubscription = $this->subscriptions->find((string) $this->context->workspaceId()) !== null;

        try {
            $action = $hadSubscription ? 'change' : 'subscribe';
            if ($hadSubscription) {
                $this->billing->changePlan((string) $this->context->workspaceId(), $planId);
            } else {
                $this->billing->subscribe((string) $this->context->workspaceId(), $planId);
            }
        } catch (BillingException $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/billing');
        }

        $this->audit->record('billing.subscription.' . $action, [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'subscription',
            'changes' => ['plan_id' => $planId],
        ]);
        $this->session->flash('status', 'Your plan has been updated.');

        return Response::redirect('/billing');
    }

    public function cancel(Request $request): Response
    {
        if (($r = $this->gate('billing.manage', $request)) !== null) {
            return $r;
        }

        try {
            $this->billing->cancel((string) $this->context->workspaceId());
        } catch (BillingException $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/billing');
        }

        $this->audit->record('billing.subscription.cancel', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'subscription',
        ]);
        $this->session->flash('status', 'Your subscription will cancel at the end of the period.');

        return Response::redirect('/billing');
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission)) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
