<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\Recruitment\Application\AssessmentService;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Shared\XlsxWriter;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Interviews (AI + human), workspace-scoped and advisory. */
final class InterviewController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly InterviewService $interviews,
        private readonly AssessmentService $assessments,
        private readonly WorkspacePreferences $preferences,
        private readonly AiSettingsService $aiSettings,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('interview.view')) !== null) {
            return $r;
        }

        $q = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $rows = $this->filter($this->interviews->listForWorkspace((string) $this->context->workspaceId(), 'ai'), $q, $statusFilter);

        return $this->shell->render($this->context, 'recruitment.interviews.index', [
            'interviews' => $rows,
            'query' => $q,
            'statusFilter' => $statusFilter,
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** AI interview report — transcript, score, recommendation, provider. */
    public function show(string $interviewId): Response
    {
        if (($r = $this->gate('interview.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $interview = $this->interviews->findDetailed($ws, $interviewId);
        if ($interview === null) {
            return Response::redirect('/interviews');
        }

        return $this->shell->render($this->context, 'recruitment.interviews.show', [
            'interview' => $interview,
            'messages' => $this->interviews->messages($ws, $interviewId),
            'assessment' => $this->assessments->latestForCandidate($ws, (string) $interview['candidate_user_id']),
        ]);
    }

    /** Native Excel (.xlsx) export of the AI interviews list. */
    public function export(): Response
    {
        if (($r = $this->gate('interview.view')) !== null) {
            return $r;
        }

        $rows = [['Candidate', 'Job', 'Status', 'Mode', 'Score', 'Recommendation', 'Provider', 'Scheduled at']];
        foreach ($this->interviews->listForWorkspace((string) $this->context->workspaceId(), 'ai') as $iv) {
            $score = $iv['score'] ?? null;
            $rows[] = [
                (string) ($iv['candidate_name'] ?? ''), (string) ($iv['job_title'] ?? ''),
                (string) ($iv['status'] ?? ''), (string) ($iv['mode'] ?? ''),
                $score !== null && $score !== '' ? (int) $score : '', (string) ($iv['recommendation'] ?? ''),
                (string) ($iv['ai_provider'] ?? ''), (string) ($iv['scheduled_at'] ?? ''),
            ];
        }

        return Response::make(XlsxWriter::fromRows($rows, 'AI Interviews'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="ai-interviews.xlsx"',
        ]);
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return list<array<string,mixed>>
     */
    private function filter(array $rows, string $q, string $statusFilter): array
    {
        $needle = mb_strtolower($q);

        return array_values(array_filter($rows, static function (array $iv) use ($needle, $statusFilter): bool {
            if ($statusFilter !== '' && (string) ($iv['status'] ?? '') !== $statusFilter) {
                return false;
            }
            if ($needle === '') {
                return true;
            }

            return str_contains(mb_strtolower((string) ($iv['candidate_name'] ?? '')), $needle)
                || str_contains(mb_strtolower((string) ($iv['job_title'] ?? '')), $needle);
        }));
    }

    public function schedule(Request $request, string $userId): Response
    {
        if (($r = $this->gate('interview.schedule', $request)) !== null) {
            return $r;
        }

        $applicationId = trim((string) $request->input('application_id', ''));
        if ($applicationId === '') {
            $this->session->flash('status', 'Pick an application to interview for.');

            return Response::redirect('/candidates/' . $userId);
        }

        $type = (string) $request->input('type', 'human');
        $ws = (string) $this->context->workspaceId();

        // AI interviews follow the workspace's mode: live video (HeyGen) only when
        // the owner enabled it AND a HeyGen key is present; otherwise text.
        $mode = trim((string) $request->input('mode', '')) ?: null;
        if ($type === 'ai') {
            $videoReady = $this->preferences->bool($ws, 'ai.interview_video') && $this->aiSettings->getKey($ws, 'heygen') !== null;
            $mode = $videoReady ? 'video' : 'text';
        }

        try {
            $id = $this->interviews->schedule(
                $ws,
                $applicationId,
                $type,
                [
                    'mode' => $mode,
                    'interviewer_user_id' => $type === 'human' ? $this->context->userId() : null,
                    'scheduled_at' => trim((string) $request->input('scheduled_at', '')) ?: null,
                    'created_by' => $this->context->userId(),
                ],
            );
        } catch (ApplicationException $e) {
            $this->session->flash('status', $e->getMessage());

            return Response::redirect('/candidates/' . $userId);
        }

        $this->audit->record('recruitment.interview.scheduled', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'interview',
            'entity_id' => $id,
            'changes' => ['type' => (string) $request->input('type', 'human')],
        ]);
        $this->session->flash('status', 'Interview scheduled.');

        return Response::redirect('/candidates/' . $userId);
    }

    public function runAi(Request $request, string $interviewId): Response
    {
        if (($r = $this->gate('interview.ai.run', $request)) !== null) {
            return $r;
        }

        $interview = $this->interviews->find((string) $this->context->workspaceId(), $interviewId);
        if ($interview === null) {
            return Response::redirect('/interviews');
        }

        $result = $this->interviews->runAi((string) $this->context->workspaceId(), $interviewId, $this->context->userId());

        // Produce the advisory AI assessment (skills, behaviour, red flags, fit).
        try {
            $this->assessments->assessFromInterview((string) $this->context->workspaceId(), $interviewId, $this->context->userId());
        } catch (\Throwable) {
            // Assessment is best-effort; the interview result still stands.
        }

        $this->audit->record('recruitment.interview.ai_run', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'interview',
            'entity_id' => $interviewId,
            'changes' => ['provider' => $result['ai_provider'] ?? null, 'score' => $result['score'] ?? null],
        ]);
        $this->session->flash('status', 'AI interview completed (advisory) — score ' . ($result['score'] ?? '—') . '.');

        return Response::redirect('/candidates/' . (string) $interview['candidate_user_id']);
    }

    public function evaluate(Request $request, string $interviewId): Response
    {
        if (($r = $this->gate('interview.evaluate', $request)) !== null) {
            return $r;
        }

        $interview = $this->interviews->find((string) $this->context->workspaceId(), $interviewId);
        if ($interview === null) {
            return Response::redirect('/interviews');
        }

        $this->interviews->submitEvaluation(
            (string) $this->context->workspaceId(),
            $interviewId,
            $this->context->userId(),
            (int) $request->input('score', 0),
            (string) $request->input('recommendation', 'hold'),
            trim((string) $request->input('summary', '')),
        );
        $this->audit->record('recruitment.interview.evaluated', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'interview',
            'entity_id' => $interviewId,
        ]);
        $this->session->flash('status', 'Evaluation submitted.');

        return Response::redirect('/candidates/' . (string) $interview['candidate_user_id']);
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
