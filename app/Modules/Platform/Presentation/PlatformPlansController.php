<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Billing\Application\PlanService;
use HaHireAI\Modules\Workspaces\Application\PlatformContext;
use HaHireAI\Modules\Workspaces\Presentation\PlatformShell;

/** Subscription plan management (System Owner): create / edit / delete plans + workspace caps. */
final class PlatformPlansController
{
    public function __construct(
        private readonly PlatformShell $shell,
        private readonly PlatformContext $context,
        private readonly AuthContext $auth,
        private readonly PlanService $plans,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'admin.plans', [
            'plans' => $this->plans->allPlans(),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('error', 'Plan name is required.');

            return Response::redirect('/plans');
        }

        $id = $this->plans->create($this->input($request, $name));
        $this->audit->record('platform.plan.created', [
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'plan',
            'entity_id' => $id,
            'changes' => ['name' => $name],
        ]);
        $this->session->flash('status', "Plan “{$name}” created.");

        return Response::redirect('/plans');
    }

    public function update(Request $request, string $id): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }
        if ($this->plans->find($id) === null) {
            $this->session->flash('error', 'Plan not found.');

            return Response::redirect('/plans');
        }

        $this->plans->update($id, $this->input($request, trim((string) $request->input('name', 'Plan'))));
        $this->audit->record('platform.plan.updated', [
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'plan',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Plan updated.');

        return Response::redirect('/plans');
    }

    public function delete(Request $request, string $id): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        if (! $this->plans->delete($id)) {
            $this->session->flash('error', 'That plan is in use by an account or workspace and cannot be deleted.');

            return Response::redirect('/plans');
        }
        $this->audit->record('platform.plan.deleted', [
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'plan',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Plan deleted.');

        return Response::redirect('/plans');
    }

    /** @return array<string,mixed> */
    private function input(Request $request, string $name): array
    {
        return [
            'code' => trim((string) $request->input('code', '')) ?: null,
            'name' => $name,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'price_cents' => (int) round(((float) $request->input('price', 0)) * 100),
            'currency' => trim((string) $request->input('currency', 'USD')) ?: 'USD',
            'interval' => (string) $request->input('interval', 'month'),
            'trial_days' => (int) $request->input('trial_days', 0),
            'max_workspaces' => (int) $request->input('max_workspaces', 1),
            'features' => array_values((array) $request->input('features', [])),
            'is_public' => $request->input('is_public') !== null,
            'sort' => (int) $request->input('sort', 0),
        ];
    }

    private function gate(?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::html('<h1>403</h1><p>Platform access requires a System Owner.</p>', 403);
        }
        if (! $this->context->can('system.subscriptions.manage')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
