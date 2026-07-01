<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\Recruitment\Application\AssessmentService;
use HaHireAI\Modules\Recruitment\Application\ResumeParser;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\CandidateHealthService;
use HaHireAI\Modules\Recruitment\Application\CandidateTimelineService;
use HaHireAI\Modules\Recruitment\Application\ComparisonService;
use HaHireAI\Modules\Recruitment\Application\FirstImpressionReportService;
use HaHireAI\Modules\Recruitment\Application\FirstImpressionService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
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
        private readonly ApplicationService $applications,
        private readonly OfferService $offers,
        private readonly InterviewService $interviews,
        private readonly CandidateTimelineService $timeline,
        private readonly AssessmentService $assessments,
        private readonly ComparisonService $comparison,
        private readonly TalentPoolService $talent,
        private readonly FileStorage $files,
        private readonly AiEngine $aiEngine,
        private readonly ResumeParser $resumeParser,
        private readonly AiCapabilities $ai,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
        private readonly FirstImpressionReportService $fiReports,
        private readonly FirstImpressionService $firstImpression,
        private readonly JobService $jobs,
        private readonly CandidateHealthService $health,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('candidate.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'min_score' => (int) $request->query('min_score', 0),
            'recommendation' => trim((string) $request->query('recommendation', '')),
            'skill' => trim((string) $request->query('skill', '')),
            'skill_min' => (int) $request->query('skill_min', 0),
        ];
        $aiSearching = $filters['min_score'] > 0 || $filters['recommendation'] !== '' || $filters['skill'] !== '';

        if ($aiSearching) {
            // Advanced search: rank candidates by their AI assessment.
            $candidates = array_map(static fn (array $a): array => [
                'user_id' => $a['candidate_user_id'],
                'name' => $a['name'],
                'email' => $a['email'],
                'fit_score' => (int) $a['fit_score'],
                'recommendation' => (string) $a['recommendation'],
            ], $this->assessments->search($ws, $filters));
        } elseif ($filters['q'] !== '') {
            // Plain ATS keyword search over CV-derived data — no AI required.
            $candidates = $this->candidates->searchByKeyword($ws, $filters['q']);
        } else {
            $candidates = $this->candidates->listForWorkspace($ws);
        }

        return $this->shell->render($this->context, 'recruitment.candidates.index', [
            'candidates' => $candidates,
            'searching' => $aiSearching || $filters['q'] !== '',
            'filters' => $filters,
            'skills' => SkillCatalog::SKILLS,
            'aiSearch' => $this->ai->cvAnalysisEnabled($ws),
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

        $applications = $this->candidates->applications($workspaceId, $userId);
        $latestAppId = $applications[0]['id'] ?? null;

        // First Impression report (zero-AI gate) — the most recent for this candidate.
        $fiReport = $this->fiReports->latestForCandidate($workspaceId, $userId);
        $fiFull = $fiReport !== null ? $this->fiReports->full($workspaceId, (string) $fiReport['id']) : null;

        return $this->shell->render($this->context, 'recruitment.candidates.show', [
            'firstImpression' => $fiFull,
            'canOverrideFi' => $this->context->can('pipeline.manage'),
            'profile' => $profile,
            'details' => $this->candidates->details($workspaceId, $userId),
            'statusHistory' => $latestAppId !== null ? $this->applications->statusHistory($workspaceId, (string) $latestAppId) : [],
            'applications' => $applications,
            'notes' => $this->candidates->notes($workspaceId, (string) $profile['profile_id']),
            'tags' => $this->candidates->tags((string) $profile['profile_id']),
            'offers' => $this->offers->forCandidate($workspaceId, $userId),
            'interviews' => $this->interviews->forCandidate($workspaceId, $userId),
            'score' => $this->interviews->averageScore($workspaceId, $userId),
            'files' => $this->files->listForEntity($workspaceId, 'candidate_profile', (string) $profile['profile_id']),
            'timeline' => $this->timeline->timeline($workspaceId, $userId, (string) $profile['profile_id']),
            'health' => $this->health->forCandidate($workspaceId, $userId),
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

    /**
     * HR override of a "Filtered Before AI" first-impression decision: the report
     * is preserved (flagged overridden), the application is un-filtered, and — when
     * the workspace has an AI key — an AI interview is scheduled so the candidate
     * can proceed manually (spec: HR can override and allow the AI interview).
     */
    public function overrideFirstImpression(Request $request, string $reportId): Response
    {
        if (($r = $this->gate('pipeline.manage', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $report = $this->fiReports->find($ws, $reportId);
        if ($report === null) {
            return Response::redirect('/candidates');
        }

        $this->firstImpression->override($ws, $reportId, (string) $this->context->userId());

        $appId = (string) ($report['application_id'] ?? '');
        $candidateUserId = (string) ($report['candidate_user_id'] ?? '');
        if ($appId !== '') {
            // Un-filter so it re-enters the normal flow.
            $this->applications->setStatus($ws, $appId, 'applied', (string) $this->context->userId());
            $job = $this->jobs->find($ws, (string) ($report['job_id'] ?? ''));
            if ($job !== null && $this->ai->interviewsEnabled($ws)) {
                try {
                    $mode = ((string) ($job['interview_type'] ?? 'text') === 'avatar' && $this->ai->videoEnabled($ws)) ? 'video' : 'text';
                    $this->interviews->schedule($ws, $appId, 'ai', ['mode' => $mode, 'created_by' => (string) $this->context->userId()]);
                } catch (\Throwable) {
                    // Non-fatal: the override stands even if scheduling fails.
                }
            }
        }

        $this->audit->record('recruitment.first_impression.overridden', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'application',
            'entity_id' => $appId !== '' ? $appId : $reportId,
        ]);
        $this->session->flash('status', 'First impression overridden — the candidate can now take the AI interview.');

        return Response::redirect($candidateUserId !== '' ? '/candidates/' . $candidateUserId : '/candidates');
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

        // AI summary / CV analysis needs this workspace's OpenAI key.
        if (! $this->ai->cvAnalysisEnabled($workspaceId)) {
            $this->session->flash('status', 'AI analysis is off — add an OpenAI key in AI settings to enable it.');

            return Response::redirect('/candidates/' . $userId);
        }

        $applications = $this->candidates->applications($workspaceId, $userId);
        $notes = $this->candidates->notes($workspaceId, (string) $profile['profile_id']);

        $result = $this->aiEngine->run($workspaceId, 'summarize_candidate', [
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

    /** Log a manual Timeline entry (mini-CRM): a call, message, meeting, note or update. */
    public function addTimelineEntry(Request $request, string $userId): Response
    {
        if (($r = $this->gate('candidate.note', $request)) !== null) {
            return $r;
        }

        $body = trim((string) $request->input('body', ''));
        if ($body !== '') {
            $ws = (string) $this->context->workspaceId();
            $this->candidates->getOrCreate($ws, $userId); // ensure a profile exists
            $occurredAt = trim((string) $request->input('occurred_at', ''));
            $entryId = $this->timeline->addEntry(
                $ws,
                $userId,
                $body,
                (string) $request->input('kind', 'update'),
                $occurredAt !== '' ? str_replace('T', ' ', $occurredAt) . ':00' : null,
                $this->context->userId(),
            );
            $this->audit->record('recruitment.candidate.timeline_logged', [
                'workspace_id' => $ws,
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'candidate_timeline_entry',
                'entity_id' => $entryId,
            ]);
            $this->session->flash('status', 'Timeline updated.');
        }

        return Response::redirect('/candidates/' . $userId . '#timeline');
    }

    /** Parse pasted CV text into structured profile fields (merged, non-destructive). */
    public function parseCv(Request $request, string $userId): Response
    {
        if (($r = $this->gate('candidate.note', $request)) !== null) {
            return $r;
        }

        $text = trim((string) $request->input('cv_text', ''));
        if ($text === '') {
            $this->session->flash('status', 'Paste some CV text to extract from.');

            return Response::redirect('/candidates/' . $userId);
        }

        $ws = (string) $this->context->workspaceId();
        $parsed = $this->resumeParser->parse($text);
        if ($parsed === []) {
            $this->session->flash('status', 'No structured fields could be extracted from that text.');

            return Response::redirect('/candidates/' . $userId);
        }

        // Merge non-destructively: keep any value the recruiter already set.
        $existing = $this->candidates->details($ws, $userId);
        $merged = $existing;
        foreach ($parsed as $key => $value) {
            if (! array_key_exists($key, $merged) || $merged[$key] === '' || $merged[$key] === [] || $merged[$key] === null) {
                $merged[$key] = $value;
            }
        }
        $this->candidates->saveDetails($ws, $userId, $merged);

        $this->audit->record('recruitment.candidate.cv_parsed', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'candidate_profile',
            'entity_id' => $userId,
            'changes' => ['fields' => array_keys($parsed)],
        ]);
        $this->session->flash('status', 'Extracted ' . count($parsed) . ' field(s) from the CV: ' . implode(', ', array_keys($parsed)) . '.');

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
