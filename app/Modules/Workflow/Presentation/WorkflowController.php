<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workflow\Application\WorkflowEngine;
use HaHireAI\Modules\Workflow\Application\WorkflowService;
use HaHireAI\Modules\Workflow\Domain\NodeCatalog;
use HaHireAI\Modules\Workflow\Domain\WorkflowGraph;
use HaHireAI\Modules\Workflow\Domain\WorkflowTemplates;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

final class WorkflowController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly WorkflowService $workflows,
        private readonly WorkflowEngine $engine,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('workflow.view')) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'workflow.index', [
            'workflows' => $this->workflows->listForWorkspace($workspaceId),
            'executions' => $this->workflows->executionsForWorkspace($workspaceId, 25),
            'canCreate' => $this->context->can('workflow.create'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** The visual no-code builder, for a new workflow or editing an existing one. */
    public function builder(?string $id = null): Response
    {
        if (($r = $this->gate('workflow.create')) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $workflow = $id !== null ? $this->workflows->find($workspaceId, $id) : null;
        if ($id !== null && $workflow === null) {
            return Response::redirect('/workflows');
        }

        return $this->shell->render($this->context, 'workflow.builder', [
            'catalog' => NodeCatalog::nodes(),
            'categories' => NodeCatalog::CATEGORIES,
            'workflow' => $workflow,
            'csrf' => $this->session->csrfToken(),
        ], ['fullBleed' => true]);
    }

    public function save(Request $request): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $id = trim((string) $request->input('id', '')) ?: null;
        $name = trim((string) $request->input('name', '')) ?: 'Untitled workflow';

        $graph = json_decode((string) $request->input('graph', '{}'), true);
        $graph = is_array($graph) ? $graph : [];
        $graph['nodes'] = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $graph['edges'] = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];

        $steps = WorkflowGraph::compile($graph);
        $triggerEvent = $this->triggerEventFor($graph);
        $enabled = $request->input('enabled', '1') !== '0';

        $savedId = $this->workflows->save(
            $workspaceId,
            $id,
            $name,
            $triggerEvent,
            ['nodes' => $graph['nodes'], 'edges' => $graph['edges'], 'steps' => $steps],
            $enabled,
            $this->context->userId(),
        );

        $this->audit->record($id === null ? 'workflows.workflow.created' : 'workflows.workflow.updated', [
            'workspace_id' => $workspaceId,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'workflow',
            'entity_id' => $savedId,
            'changes' => ['name' => $name, 'steps' => count($steps)],
        ]);
        $this->session->flash('status', "Workflow “{$name}” saved with " . count($steps) . ' step(s).');

        return Response::redirect('/workflows');
    }

    /** Gallery of ready-made templates the user can clone with one click. */
    public function templates(): Response
    {
        if (($r = $this->gate('workflow.create')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'workflow.templates', [
            'templates' => WorkflowTemplates::all(),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Clone a template into a new (disabled) workflow and open it in the builder. */
    public function useTemplate(Request $request, string $key): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $tpl = WorkflowTemplates::find($key);
        if ($tpl === null) {
            return Response::redirect('/workflows/templates');
        }

        $graph = ['nodes' => $tpl['nodes'], 'edges' => $tpl['edges']];
        $id = $this->workflows->save(
            (string) $this->context->workspaceId(),
            null,
            $tpl['name'],
            $this->triggerEventFor($graph),
            ['nodes' => $tpl['nodes'], 'edges' => $tpl['edges'], 'steps' => WorkflowGraph::compile($graph)],
            false, // start disabled — the user reviews, then enables
            $this->context->userId(),
        );

        $this->audit->record('workflows.workflow.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'workflow',
            'entity_id' => $id,
            'changes' => ['template' => $key, 'name' => $tpl['name']],
        ]);
        $this->session->flash('status', "Created “{$tpl['name']}” from a template — review the steps, then enable it.");

        return Response::redirect('/workflows/' . $id . '/edit');
    }

    public function run(Request $request, string $id): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $workflow = $this->workflows->find($workspaceId, $id);
        if ($workflow === null) {
            return Response::redirect('/workflows');
        }

        // A small sample payload so AI/action nodes have something to work with.
        $ran = $this->engine->runById($workspaceId, $id, [
            'candidate_name' => 'Sample Candidate',
            'candidate_email' => 'sample@example.com',
            'job_title' => 'Sample Role',
            'source' => 'manual',
        ], $this->context->userId());

        $this->session->flash('status', $ran ? "Ran “{$workflow['name']}” once — see Recent executions." : 'Workflow could not be run.');

        return Response::redirect('/workflows');
    }

    public function toggle(Request $request, string $id): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $workflow = $this->workflows->find($workspaceId, $id);
        if ($workflow === null) {
            return Response::redirect('/workflows');
        }

        $now = (int) $workflow['enabled'] === 1;
        $this->workflows->setEnabled($workspaceId, $id, ! $now);
        $this->session->flash('status', "“{$workflow['name']}” " . ($now ? 'disabled' : 'enabled') . '.');

        return Response::redirect('/workflows');
    }

    /** Version history for a workflow, with rollback. */
    public function versions(string $id): Response
    {
        if (($r = $this->gate('workflow.view')) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $workflow = $this->workflows->find($workspaceId, $id);
        if ($workflow === null) {
            return Response::redirect('/workflows');
        }

        return $this->shell->render($this->context, 'workflow.versions', [
            'workflow' => $workflow,
            'versions' => $this->workflows->listVersions($workspaceId, $id),
            'canRestore' => $this->context->can('workflow.create'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Roll a workflow back to a previous version (saved as a new version). */
    public function restoreVersion(Request $request, string $id, string $versionId): Response
    {
        if (($r = $this->gate('workflow.create', $request)) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $version = $this->workflows->findVersion($workspaceId, $versionId);
        $workflow = $this->workflows->find($workspaceId, $id);
        if ($version === null || $workflow === null) {
            return Response::redirect('/workflows');
        }

        $graph = ['nodes' => $version['nodes'], 'edges' => $version['edges']];
        $this->workflows->save(
            $workspaceId,
            $id,
            (string) $workflow['name'],
            $this->triggerEventFor($graph),
            ['nodes' => $version['nodes'], 'edges' => $version['edges'], 'steps' => WorkflowGraph::compile($graph)],
            (int) $workflow['enabled'] === 1,
            $this->context->userId(),
        );

        $this->session->flash('status', 'Rolled back to version ' . $version['version'] . '.');

        return Response::redirect('/workflows/' . $id . '/edit');
    }

    /** A single execution with its per-step log. */
    public function execution(string $id, string $executionId): Response
    {
        if (($r = $this->gate('workflow.view')) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $execution = $this->workflows->findExecution($workspaceId, $executionId);
        if ($execution === null) {
            return Response::redirect('/workflows');
        }

        return $this->shell->render($this->context, 'workflow.execution', [
            'execution' => $execution,
            'steps' => $this->workflows->stepsForExecution($workspaceId, $executionId),
        ]);
    }

    /**
     * Derive the trigger event from the graph's trigger node, via the catalog.
     *
     * @param  array<string,mixed>  $graph
     */
    private function triggerEventFor(array $graph): string
    {
        $catalog = NodeCatalog::byType();
        foreach (($graph['nodes'] ?? []) as $node) {
            $type = (string) ($node['type'] ?? '');
            if (str_starts_with($type, 'trigger.')) {
                $event = (string) ($catalog[$type]['event'] ?? '');

                return $event !== '' ? $event : $type;
            }
        }

        return 'workflow.manual';
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
