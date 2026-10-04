<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Tasks\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Tasks\Application\TaskService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Workspace tasks — list, create, complete/reopen, delete (Feature 14). */
final class TasksController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly TaskService $tasks,
        private readonly MemberDirectory $members,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('task.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $status = (string) $request->input('status', '');
        $filters = [
            'status' => in_array($status, ['open', 'done'], true) ? $status : '',
            'assignee_user_id' => (string) $request->input('assignee', '') === 'me' ? (string) $this->context->userId() : '',
            'q' => trim((string) $request->input('q', '')),
        ];

        return $this->shell->render($this->context, 'tasks.index', [
            'tasks' => $this->tasks->listForWorkspace($ws, $filters),
            'members' => $this->members->membersForWorkspace($ws),
            'filters' => ['status' => $filters['status'], 'assignee' => (string) $request->input('assignee', ''), 'q' => $filters['q']],
            'currentUserId' => (string) $this->context->userId(),
            'canManage' => $this->context->can('task.manage'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('task.manage', $request)) !== null) {
            return $r;
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $this->session->flash('status', 'A task title is required.');

            return Response::redirect('/tasks');
        }

        $ws = (string) $this->context->workspaceId();
        $id = $this->tasks->create($ws, $title, $this->context->userId(), [
            'description' => trim((string) $request->input('description', '')) ?: null,
            'assignee_user_id' => (string) $request->input('assignee_user_id', ''),
            'due_at' => trim((string) $request->input('due_at', '')) ?: null,
            'priority' => (string) $request->input('priority', 'normal'),
        ]);

        $this->audit->record('tasks.task.created', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'task',
            'entity_id' => $id,
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => ['title' => $title],
        ]);
        $this->session->flash('status', 'Task created.');

        return Response::redirect('/tasks');
    }

    public function complete(Request $request, string $id): Response
    {
        return $this->statusAction($request, $id, 'done', 'Task completed.');
    }

    public function reopen(Request $request, string $id): Response
    {
        return $this->statusAction($request, $id, 'open', 'Task reopened.');
    }

    public function delete(Request $request, string $id): Response
    {
        if (($r = $this->gate('task.manage', $request)) !== null) {
            return $r;
        }

        $this->tasks->delete((string) $this->context->workspaceId(), $id);
        $this->audit->record('tasks.task.deleted', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'task',
            'entity_id' => $id,
            'ip' => $request->server('REMOTE_ADDR'),
        ]);
        $this->session->flash('status', 'Task deleted.');

        return Response::redirect('/tasks');
    }

    private function statusAction(Request $request, string $id, string $status, string $ok): Response
    {
        if (($r = $this->gate('task.manage', $request)) !== null) {
            return $r;
        }

        if ($this->tasks->setStatus((string) $this->context->workspaceId(), $id, $status)) {
            $this->audit->record('tasks.task.status_changed', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'task',
                'entity_id' => $id,
                'changes' => ['status' => $status],
            ]);
            $this->session->flash('status', $ok);
        }

        return Response::redirect('/tasks');
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
