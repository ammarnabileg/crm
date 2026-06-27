<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Workspaces\Application\WorkspaceCreator;
use Throwable;

/** Create a workspace (docs/WORKSPACE_MODEL.md). Any user may create one. */
final class WorkspaceController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly WorkspaceCreator $creator,
        private readonly Session $session,
    ) {
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

        $this->auth->setCurrentWorkspace($result['workspace_id']);

        return Response::redirect('/dashboard');
    }
}
