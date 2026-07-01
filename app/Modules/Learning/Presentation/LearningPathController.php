<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Presentation;

use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Learning\Application\LearningPathService;
use HaHireAI\Modules\Learning\Application\ProgramService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * Learning paths (tracks): an ordered sequence of programs. Gated by the same
 * paid `learning` feature and the learning.* permissions. Tenant-scoped.
 */
final class LearningPathController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly LearningPathService $paths,
        private readonly ProgramService $programs,
        private readonly Session $session,
        private readonly EntitlementResolver $entitlements,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('learning.view')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'learning.paths_index', [
            'paths' => $this->paths->listForWorkspace($ws),
            'canManage' => $this->context->can('learning.manage'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $id = $this->paths->create($ws, (string) $this->context->userId(), (string) $request->input('title', ''), (string) $request->input('description', '') ?: null);
        $this->session->flash('status', 'Path created — add programs to it.');

        return Response::redirect('/learning-paths/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.view')) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $path = $this->paths->find($ws, $id);
        if ($path === null) {
            return Response::html('<h1>404</h1><p>Path not found.</p>', 404);
        }
        $inPath = array_map(static fn (array $p): string => (string) $p['program_id'], $this->paths->programsFor($ws, $id));

        return $this->shell->render($this->context, 'learning.paths_show', [
            'path' => $path,
            'programs' => $this->paths->programsFor($ws, $id),
            'progress' => $this->paths->progressFor($ws, $id, (string) $this->context->userId()),
            'available' => array_values(array_filter(
                $this->programs->listForWorkspace($ws, [], 200),
                static fn (array $p): bool => ! in_array((string) $p['id'], $inPath, true),
            )),
            'canManage' => $this->context->can('learning.manage'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function setStatus(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $this->paths->setStatus((string) $this->context->workspaceId(), $id, (string) $request->input('status', 'draft'));
        $this->session->flash('status', 'Path updated.');

        return Response::redirect('/learning-paths/' . $id);
    }

    public function delete(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $this->paths->delete((string) $this->context->workspaceId(), $id);
        $this->session->flash('status', 'Path deleted.');

        return Response::redirect('/learning-paths');
    }

    public function addProgram(Request $request, string $id): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $pid = (string) $request->input('program_id', '');
        if ($pid !== '') {
            $this->paths->addProgram((string) $this->context->workspaceId(), $id, $pid);
            $this->session->flash('status', 'Program added to the path.');
        }

        return Response::redirect('/learning-paths/' . $id);
    }

    public function removeProgram(Request $request, string $id, string $programId): Response
    {
        if (($r = $this->gate('learning.manage', $request)) !== null) {
            return $r;
        }
        $this->paths->removeProgram((string) $this->context->workspaceId(), $id, $programId);
        $this->session->flash('status', 'Program removed from the path.');

        return Response::redirect('/learning-paths/' . $id);
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (($gate = FeatureGate::check($this->entitlements, (string) $this->context->workspaceId())) !== null) {
            return $gate;
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
