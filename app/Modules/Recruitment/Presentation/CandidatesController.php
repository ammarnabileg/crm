<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\Files\Application\FileService;
use HaHireAI\Modules\Recruitment\Application\AssessmentService;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\CandidateTimelineService;
use HaHireAI\Modules\Recruitment\Application\ComparisonService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Modules\Recruitment\Application\TalentPoolService;
use HaHireAI\Modules\Recruitment\Domain\ApplicationStatus;
use HaHireAI\Modules\Recruitment\Domain\SkillCatalog;
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
        private readonly OfferService $offers,
        private readonly InterviewService $interviews,
        private readonly CandidateTimelineService $timeline,
        private readonly AssessmentService $assessments,
        private readonly ComparisonService $comparison,
        private readonly TalentPoolService $talent,
        private readonly FileService $files,
        private readonly AiEngine $ai,
        private readonly Connection $connection,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('candidate.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $filters = [
            'min_score' => (int) $request->query('min_score', 0),
            'recommendation' => trim((string) $request->query('recommendation', '')),
            'skill' => trim((string) $request->query('skill', '')),
            'skill_min' => (int) $request->query('skill_min', 0),
        ];
        $searching = $filters['min_score'] > 0 || $filters['recommendation'] !== '' || $filters['skill'] !== '';

        if ($searching) {
            // Advanced search: rank candidates by their AI assessment.
            $candidates = array_map(static fn (array $a): array => [
                'user_id' => $a['candidate_user_id'],
                'name' => $a['name'],
                'email' => $a['email'],
                'fit_score' => (int) $a['fit_score'],
                'recommendation' => (string) $a['recommendation'],
            ], $this->assessments->search($ws, $filters));
        } else {
            $candidates = $this->connection->select(
                'SELECT cp.user_id, u.name, u.email,
                        (SELECT COUNT(*) FROM applications a WHERE a.workspace_id = cp.workspace_id AND a.user_id = cp.user_id AND a.deleted_at IS NULL) AS applications
                   FROM candidate_profiles cp JOIN users u ON u.id = cp.user_id
                  WHERE cp.workspace_id = ? ORDER BY u.name',
                [$ws],
            );
        }

        return $this->shell->render($this->context, 'recruitment.candidates.index', [
            'candidates' => $candidates,
            'searching' => $searching,
            'filters' => $filters,
            'skills' => SkillCatalog::SKILLS,
        ]);
    }

    /** Side-by-side comparison of selected candidates, with AI Q&A (spec #15). */
    public function compare(Request $request): Response
    {
        if (($r = $this->gate('candidate.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $ids = array_values(array_filter(array_map('strval', (array) $request->query('ids', []))));
        $question = trim((string) $request->query('q', ''));
        $answer = null;
        if ($question !== '' && $this->context->can('ai.run')) {
            $answer = $this->comparison->ask($ws, $ids, $question, $this->context->userId());
        }

        return $this->shell->render($this->context, 'recruitment.candidates.compare', [
            'candidates' => $this->comparison->gather($ws, $ids),
            'skillCatalog' => SkillCatalog::SKILLS,
            'question' => $question,
            'answer' => $answer,
            'ids' => $ids,
            'canAsk' => $this->context->can('ai.run'),
        ]);
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
            'offers' => $this->offers->forCandidate($workspaceId, $userId),
            'interviews' => $this->interviews->forCandidate($workspaceId, $userId),
            'score' => $this->interviews->averageScore($workspaceId, $userId),
            'files' => $this->files->listForEntity($workspaceId, 'candidate_profile', (string) $profile['profile_id']),
            'timeline' => $this->timeline->timeline($workspaceId, $userId, (string) $profile['profile_id']),
            'assessment' => $this->assessments->latestForCandidate($workspaceId, $userId),
            'skillCatalog' => SkillCatalog::SKILLS,
            'statuses' => ApplicationStatus::STATUSES,
            'canSetStatus' => $this->context->can('pipeline.manage'),
            'talentPools' => $this->talent->listPools($workspaceId),
            'candidatePools' => $this->talent->poolsForCandidate($workspaceId, $userId),
            'canManageTalent' => $this->context->can('talent.manage'),
            'canUploadFile' => $this->context->can('files.upload'),
            'canDeleteFile' => $this->context->can('files.delete'),
            'canViewFile' => $this->context->can('files.view'),
            'canNote' => $this->context->can('candidate.note'),
            'canTag' => $this->context->can('candidate.tag'),
            'canOffer' => $this->context->can('offer.create'),
            'canDecide' => $this->context->can('offer.send'),
            'canAi' => $this->context->can('ai.run'),
            'canScheduleInterview' => $this->context->can('interview.schedule'),
            'canRunAiInterview' => $this->context->can('interview.ai.run'),
            'canEvaluate' => $this->context->can('interview.evaluate'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Generate an AI summary for the candidate via the central AI Engine. */
    public function aiSummary(Request $request, string $userId): Response
    {
        if (($r = $this->gate('ai.run', $request)) !== null) {
            return $r;
        }

        $workspaceId = (string) $this->context->workspaceId();
        $profile = $this->candidates->profile($workspaceId, $userId);
        if ($profile === null) {
            return Response::redirect('/candidates');
        }

        $applications = $this->candidates->applications($workspaceId, $userId);
        $notes = $this->candidates->notes($workspaceId, (string) $profile['profile_id']);

        $result = $this->ai->run($workspaceId, 'summarize_candidate', [
            'name' => (string) $profile['name'],
            'email' => (string) $profile['email'],
            'applications' => implode('; ', array_map(static fn (array $a): string => (string) $a['job_title'] . ' (' . (string) ($a['stage'] ?? $a['status']) . ')', $applications)),
            'notes' => implode(' | ', array_map(static fn (array $n): string => (string) $n['body'], $notes)),
        ], $this->context->userId());

        // Persist the advisory summary as a note (human-reviewable).
        $this->candidates->addNote($workspaceId, (string) $profile['profile_id'], $this->context->userId(), 'AI summary (' . $result->provider . '): ' . $result->text);
        $this->audit->record('ai.capability.run', [
            'workspace_id' => $workspaceId,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'candidate_profile',
            'entity_id' => (string) $profile['profile_id'],
            'changes' => ['capability' => 'summarize_candidate', 'provider' => $result->provider],
        ]);
        $this->session->flash('status', 'AI summary generated by ' . $result->provider . '.');

        return Response::redirect('/candidates/' . $userId);
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
