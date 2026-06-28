<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workflow\Application\WorkflowService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

final class WorkflowController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly WorkflowService $workflows,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('workflow.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'workflow.index', [
            'workflows' => $this->workflows->listForWorkspace((string) $this->context->workspaceId()),
            'executions' => $this->workflows->executionsForWorkspace((string) $this->context->workspaceId(), 25),
            'canCreate' => $this->context->can('workflow.create'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('status', 'Workflow name is required.');

            return Response::redirect('/workflows');
        }

        // Build a simple, useful workflow: audit + AI screening on application.
        $steps = [
            ['action' => 'audit', 'params' => ['message' => 'Application received — automation started']],
            ['action' => 'run_ai', 'params' => ['capability' => (string) $request->input('capability', 'summarize_candidate')]],
        ];

        $id = $this->workflows->create(
            (string) $this->context->workspaceId(),
            $name,
            (string) $request->input('trigger_event', 'application.submitted'),
            $steps,
            $this->context->userId(),
        );

        $this->audit->record('workflows.workflow.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'workflow',
            'entity_id' => $id,
            'changes' => ['name' => $name],
        ]);
        $this->session->flash('status', "Workflow “{$name}” created and enabled.");

        return Response::redirect('/workflows');
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
