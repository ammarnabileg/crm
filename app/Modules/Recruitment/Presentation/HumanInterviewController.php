<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * Human (panel) interviews — the second, human-in-the-loop stage (spec #12).
 * Search / view / schedule (online or onsite) / reschedule / archive, plus the
 * structured 1–5 evaluation form. AI suggestions are advisory; the human wins.
 */
final class HumanInterviewController
{
    /** The structured evaluation dimensions, key => human label (spec #12). */
    public const DIMENSIONS = [
        'technical_depth' => 'Technical depth',
        'problem_solving' => 'Problem solving',
        'communication' => 'Communication',
        'culture_fit' => 'Culture fit',
        'takes_ownership' => 'Takes ownership',
        'seniority_fit' => 'Seniority fit',
    ];

    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly InterviewService $interviews,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('interview.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $q = trim((string) $request->query('q', ''));
        $rows = $this->interviews->listForWorkspace($ws, 'human');
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = array_values(array_filter($rows, static function (array $iv) use ($needle): bool {
                return str_contains(mb_strtolower((string) ($iv['candidate_name'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($iv['job_title'] ?? '')), $needle);
            }));
        }

        return $this->shell->render($this->context, 'recruitment.human_interviews.index', [
            'interviews' => $rows,
            'applications' => $this->interviews->schedulableApplications($ws),
            'canSchedule' => $this->context->can('interview.schedule'),
            'query' => $q,
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function show(string $interviewId): Response
    {
        if (($r = $this->gate('interview.view')) !== null) {
            return $r;
        }

        $interview = $this->interviews->findDetailed((string) $this->context->workspaceId(), $interviewId);
        if ($interview === null) {
            return Response::redirect('/human-interviews');
        }

        return $this->shell->render($this->context, 'recruitment.human_interviews.show', [
            'interview' => $interview,
            'dimensions' => self::DIMENSIONS,
            'canEvaluate' => $this->context->can('interview.evaluate'),
            'canSchedule' => $this->context->can('interview.schedule'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function schedule(Request $request): Response
    {
        if (($r = $this->gate('interview.schedule', $request)) !== null) {
            return $r;
        }

        $applicationId = trim((string) $request->input('application_id', ''));
        if ($applicationId === '') {
            $this->session->flash('status', 'Pick an applicant to schedule.');

            return Response::redirect('/human-interviews');
        }

        // Online or onsite; an online interview carries a meeting link.
        $mode = (string) $request->input('mode', 'online');
        $mode = in_array($mode, ['online', 'onsite'], true) ? $mode : 'online';

        try {
            $id = $this->interviews->schedule(
                (string) $this->context->workspaceId(),
                $applicationId,
                'human',
                [
                    'mode' => $mode,
                    'meeting_link' => $mode === 'online' ? (trim((string) $request->input('meeting_link', '')) ?: null) : null,
                    'interviewer_user_id' => $this->context->userId(),
                    'scheduled_at' => trim((string) $request->input('scheduled_at', '')) ?: null,
                    'created_by' => $this->context->userId(),
                ],
            );
        } catch (ApplicationException $e) {
            $this->session->flash('status', $e->getMessage());

            return Response::redirect('/human-interviews');
        }

        $this->audit->record('recruitment.human_interview.scheduled', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'interview',
            'entity_id' => $id,
            'changes' => ['mode' => $mode],
        ]);
        $this->session->flash('status', 'Human interview scheduled.');

        return Response::redirect('/human-interviews/' . $id);
    }

    public function reschedule(Request $request, string $interviewId): Response
    {
        if (($r = $this->gate('interview.schedule', $request)) !== null) {
            return $r;
        }

        if ($this->interviews->find((string) $this->context->workspaceId(), $interviewId) === null) {
            return Response::redirect('/human-interviews');
        }

        $mode = (string) $request->input('mode', 'online');
        $mode = in_array($mode, ['online', 'onsite'], true) ? $mode : 'online';
        $this->interviews->reschedule((string) $this->context->workspaceId(), $interviewId, [
            'mode' => $mode,
            'meeting_link' => $mode === 'online' ? (trim((string) $request->input('meeting_link', '')) ?: null) : null,
            'scheduled_at' => trim((string) $request->input('scheduled_at', '')) ?: null,
            'interviewer_user_id' => trim((string) $request->input('interviewer_user_id', '')) ?: $this->context->userId(),
        ]);
        $this->audit->record('recruitment.human_interview.rescheduled', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'interview',
            'entity_id' => $interviewId,
        ]);
        $this->session->flash('status', 'Interview updated.');

        return Response::redirect('/human-interviews/' . $interviewId);
    }

    public function evaluate(Request $request, string $interviewId): Response
    {
        if (($r = $this->gate('interview.evaluate', $request)) !== null) {
            return $r;
        }

        $interview = $this->interviews->find((string) $this->context->workspaceId(), $interviewId);
        if ($interview === null) {
            return Response::redirect('/human-interviews');
        }

        $ratings = [];
        foreach (array_keys(self::DIMENSIONS) as $dim) {
            $ratings[$dim] = (int) $request->input('rating_' . $dim, 0);
        }

        $this->interviews->submitHumanEvaluation(
            (string) $this->context->workspaceId(),
            $interviewId,
            $this->context->userId(),
            $ratings,
            (int) $request->input('overall', 3),
            (string) $request->input('recommendation', 'hold'),
            trim((string) $request->input('strengths', '')) ?: null,
            trim((string) $request->input('weaknesses', '')) ?: null,
            trim((string) $request->input('notes', '')) ?: null,
        );
        $this->audit->record('recruitment.human_interview.evaluated', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'interview',
            'entity_id' => $interviewId,
            'changes' => ['recommendation' => (string) $request->input('recommendation', 'hold')],
        ]);
        $this->session->flash('status', 'Evaluation recorded.');

        return Response::redirect('/human-interviews/' . $interviewId);
    }

    public function archive(Request $request, string $interviewId): Response
    {
        if (($r = $this->gate('interview.schedule', $request)) !== null) {
            return $r;
        }

        $this->interviews->archive((string) $this->context->workspaceId(), $interviewId);
        $this->audit->record('recruitment.human_interview.archived', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'interview',
            'entity_id' => $interviewId,
        ]);
        $this->session->flash('status', 'Interview archived.');

        return Response::redirect('/human-interviews');
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
