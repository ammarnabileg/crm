<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceCreator;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;
use Throwable;

/** Create / switch workspaces (docs/WORKSPACE_MODEL.md). Any user may create one. */
final class WorkspaceController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly WorkspaceCreator $creator,
        private readonly MembershipService $memberships,
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    /** "My Workspaces" — every workspace the current user belongs to, any role. */
    public function myWorkspaces(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/workspaces/create');
        }

        $list = $this->memberships->workspacesForUserDetailed((string) $this->auth->id());
        $active = count(array_filter($list, static fn (array $w): bool => (string) $w['status'] === 'active'));
        $suspended = count(array_filter($list, static fn (array $w): bool => (string) ($w['sub_status'] ?? '') === 'suspended'));

        return $this->shell->render($this->context, 'workspace.my', [
            'workspaces' => $list,
            'currentId' => $this->context->workspaceId(),
            'stats' => ['total' => count($list), 'active' => $active, 'suspended' => $suspended],
        ]);
    }

    public function showCreate(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }

        return Response::html($this->view->page('workspace.create', [
            'user' => $this->auth->user(),
            'error' => $this->session->pullFlash('error'),
        ], 'layouts.guest', ['title' => 'Create a workspace']));
    }

    public function create(Request $request): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }

        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            $this->session->flash('error', 'Security check failed.');

            return Response::redirect('/workspaces/create');
        }

        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            $this->session->flash('error', 'Workspace name is required.');

            return Response::redirect('/workspaces/create');
        }

        try {
            $result = $this->creator->create((string) $this->auth->id(), $name, null, [
                'timezone' => (string) $request->input('timezone', 'UTC'),
                'locale' => (string) $request->input('locale', 'en'),
                'currency' => (string) $request->input('currency', 'USD'),
            ]);
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/workspaces/create');
        }

        $this->audit->record('workspaces.workspace.created', [
            'workspace_id' => $result['workspace_id'],
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'workspace',
            'entity_id' => $result['workspace_id'],
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => ['name' => $name],
        ]);

        $this->auth->setCurrentWorkspace($result['workspace_id']);

        return Response::redirect('/dashboard');
    }

    /** Switch the active workspace (multi-workspace UX). */
    public function switch(Request $request, string $id): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }

        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::redirect('/dashboard');
        }

        // Tenant guard: only switch to a workspace the user is a member of.
        if ($this->memberships->find($id, (string) $this->auth->id()) !== null) {
            $this->auth->setCurrentWorkspace($id);
        }

        return Response::redirect('/dashboard');
    }
}

