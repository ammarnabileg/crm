<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * Candidate profiles — a single workspace-scoped view of a user (notes, tags,
 * this-workspace applications only). Never cross-workspace.
 */
final class CandidatesController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly CandidateProfileService $candidates,
        private readonly Connection $connection,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('candidate.view')) !== null) {
            return $r;
        }

        $rows = $this->connection->select(
            'SELECT cp.user_id, u.name, u.email,
                    (SELECT COUNT(*) FROM applications a WHERE a.workspace_id = cp.workspace_id AND a.user_id = cp.user_id AND a.deleted_at IS NULL) AS applications
               FROM candidate_profiles cp JOIN users u ON u.id = cp.user_id
              WHERE cp.workspace_id = ? ORDER BY u.name',
            [(string) $this->context->workspaceId()],
        );

        return $this->shell->render($this->context, 'recruitment.candidates.index', ['candidates' => $rows]);
    }

    public function show(string $userId): Response
    {
        if (($r = $this->gate('candidate.view')) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $profile = $this->candidates->profile($workspaceId, $userId);
        if ($profile === null) {
            return Response::redirect('/candidates');
        }

        return $this->shell->render($this->context, 'recruitment.candidates.show', [
            'profile' => $profile,
            'applications' => $this->candidates->applications($workspaceId, $userId),
            'notes' => $this->candidates->notes($workspaceId, (string) $profile['profile_id']),
            'tags' => $this->candidates->tags((string) $profile['profile_id']),
            'canNote' => $this->context->can('candidate.note'),
            'canTag' => $this->context->can('candidate.tag'),
        ]);
    }

    public function addNote(Request $request, string $userId): Response
    {
        if (($r = $this->gate('candidate.note', $request)) !== null) {
            return $r;
        }

        $body = trim((string) $request->input('body', ''));
        if ($body !== '') {
            $profileId = $this->candidates->getOrCreate((string) $this->context->workspaceId(), $userId);
            $this->candidates->addNote((string) $this->context->workspaceId(), $profileId, $this->context->userId(), $body);
            $this->audit->record('recruitment.candidate.noted', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'candidate_profile',
                'entity_id' => $profileId,
            ]);
        }

        return Response::redirect('/candidates/' . $userId);
    }

    public function addTag(Request $request, string $userId): Response
    {
        if (($r = $this->gate('candidate.tag', $request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('tag', ''));
        if ($name !== '') {
            $profileId = $this->candidates->getOrCreate((string) $this->context->workspaceId(), $userId);
            $this->candidates->addTag((string) $this->context->workspaceId(), $profileId, $name);
        }

        return Response::redirect('/candidates/' . $userId);
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
