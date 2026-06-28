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

    public function index(): Response
    {
        if (($r = $this->gate('interview.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'recruitment.interviews.index', [
            'interviews' => $this->interviews->listForWorkspace((string) $this->context->workspaceId(), 'ai'),
            'status' => $this->session->pullFlash('status'),
        ]);
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
