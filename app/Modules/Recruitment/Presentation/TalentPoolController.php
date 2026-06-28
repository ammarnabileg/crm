<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\TalentPoolService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Talent pools — saved candidate lists for future roles (recruitment spec #14). */
final class TalentPoolController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly TalentPoolService $pools,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('talent.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'recruitment.talent.index', [
            'pools' => $this->pools->listPools((string) $this->context->workspaceId()),
            'smartLists' => $this->pools->smartLists((string) $this->context->workspaceId()),
            'canManage' => $this->context->can('talent.manage'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Bulk-add candidates (e.g. a whole smart list) to a pool. */
    public function bulkAdd(Request $request): Response
    {
        if (($r = $this->gate('talent.manage', $request)) !== null) {
            return $r;
        }

        $ids = $request->input('candidate_user_ids', []);
        $ids = is_array($ids) ? $ids : [];
        $n = $this->pools->addCandidates(
            (string) $this->context->workspaceId(),
            (string) $request->input('pool_id', ''),
            $ids,
            $this->context->userId(),
        );
        $this->session->flash('status', $n > 0 ? "Added {$n} candidate(s) to the pool." : 'Nothing added.');

        return Response::redirect('/talent-pool');
    }

    public function show(string $poolId): Response
    {
        if (($r = $this->gate('talent.view')) !== null) {
            return $r;
        }

        $pool = $this->pools->findPool((string) $this->context->workspaceId(), $poolId);
        if ($pool === null) {
            return Response::redirect('/talent-pool');
        }

        return $this->shell->render($this->context, 'recruitment.talent.show', [
            'pool' => $pool,
            'members' => $this->pools->members((string) $this->context->workspaceId(), $poolId),
            'canManage' => $this->context->can('talent.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($r = $this->gate('talent.manage', $request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            $id = $this->pools->createPool((string) $this->context->workspaceId(), $name, trim((string) $request->input('description', '')) ?: null, $this->context->userId());
            $this->audit->record('recruitment.talent_pool.created', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'talent_pool',
                'entity_id' => $id,
            ]);
            $this->session->flash('status', "Talent pool “{$name}” created.");
        }

        return Response::redirect('/talent-pool');
    }

    public function addCandidate(Request $request): Response
    {
        if (($r = $this->gate('talent.manage', $request)) !== null) {
            return $r;
        }

        $this->pools->addCandidate(
            (string) $this->context->workspaceId(),
            (string) $request->input('pool_id', ''),
            (string) $request->input('candidate_user_id', ''),
            $this->context->userId(),
            trim((string) $request->input('note', '')) ?: null,
        );
        $this->session->flash('status', 'Candidate saved to the talent pool.');

        return Response::redirect($this->safeRedirect((string) $request->input('redirect_to', '/talent-pool')));
    }

    public function removeCandidate(Request $request, string $poolId): Response
    {
        if (($r = $this->gate('talent.manage', $request)) !== null) {
            return $r;
        }

        $this->pools->removeCandidate((string) $this->context->workspaceId(), $poolId, (string) $request->input('candidate_user_id', ''));
        $this->session->flash('status', 'Candidate removed from the pool.');

        return Response::redirect($this->safeRedirect((string) $request->input('redirect_to', '/talent-pool/' . $poolId)));
    }

    private function safeRedirect(string $to): string
    {
        return (str_starts_with($to, '/') && ! str_contains($to, '://')) ? $to : '/talent-pool';
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
