<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\AvatarService;
use HaHireAI\Modules\Recruitment\Application\InterviewInvitationService;
use HaHireAI\Modules\Recruitment\Application\JobContentService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

final class JobsController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly JobService $jobs,
        private readonly ApplicationService $applications,
        private readonly InterviewInvitationService $invitations,
        private readonly JobContentService $content,
        private readonly AvatarService $avatars,
        private readonly AiCapabilities $ai,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($r = $this->gate('job.view')) !== null) {
            return $r;
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
        ];

        return $this->shell->render($this->context, 'recruitment.jobs.index', [
            'jobs' => $this->jobs->listForWorkspace((string) $this->context->workspaceId(), $filters),
            'filters' => $filters,
            'canCreate' => $this->context->can('job.create'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function clone(Request $request, string $id): Response
    {
        if (($r = $this->gate('job.create', $request)) !== null) {
            return $r;
        }

        $newId = $this->jobs->clone((string) $this->context->workspaceId(), $id, (string) $this->context->userId());
        if ($newId === null) {
            return Response::redirect('/jobs');
        }
        $this->audit->record('recruitment.job.cloned', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'job',
            'entity_id' => $newId,
            'changes' => ['from' => $id],
        ]);
        $this->session->flash('status', 'Job cloned as a new draft.');

        return Response::redirect('/jobs/' . $newId);
    }

    public function create(): Response
    {
        if (($r = $this->gate('job.create')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'recruitment.jobs.create', [
            'error' => $this->session->pullFlash('error'),
            'avatars' => $this->avatars->listActive((string) $this->context->workspaceId()),
            'stages' => [],
            'aiStatus' => $this->ai->status((string) $this->context->workspaceId()),
        ]);
    }

    public function store(Request $request): Response
    {
        if (($r = $this->gate('job.create', $request)) !== null) {
            return $r;
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $this->session->flash('error', 'Job title is required.');

            return Response::redirect('/jobs/create');
        }

        $jobId = $this->jobs->create(
            (string) $this->context->workspaceId(),
            (string) $this->context->userId(),
            $title,
            (string) $request->input('description', ''),
            (string) $request->input('location', ''),
            (string) $request->input('employment_type', ''),
            [
                'seniority' => trim((string) $request->input('seniority', '')) ?: null,
                'salary_min' => ($v = trim((string) $request->input('salary_min', ''))) !== '' ? (int) $v : null,
                'salary_max' => ($v = trim((string) $request->input('salary_max', ''))) !== '' ? (int) $v : null,
                'currency' => trim((string) $request->input('currency', 'USD')) ?: 'USD',
            ],
        );

        // Apply the full hiring configuration (AI screening, interview behaviour,
        // scoring automation, avatar, deadline) on top of the new draft.
        $this->jobs->update((string) $this->context->workspaceId(), $jobId, $this->jobConfig($request));

        $this->audit->record('recruitment.job.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'job',
            'entity_id' => $jobId,
            'changes' => ['title' => $title],
        ]);

        $this->session->flash('status', 'Job created as a draft.');

        return Response::redirect('/jobs/' . $jobId);
    }

    public function show(string $id): Response
    {
        if (($r = $this->gate('job.view')) !== null) {
            return $r;
        }

        $job = $this->jobs->find((string) $this->context->workspaceId(), $id);
        if ($job === null) {
            return Response::redirect('/jobs');
        }

        $ws = (string) $this->context->workspaceId();
        $linkedAvatar = ! empty($job['avatar_id']) ? $this->avatars->find($ws, (string) $job['avatar_id']) : null;

        return $this->shell->render($this->context, 'recruitment.jobs.show', [
            'job' => $job,
            'stages' => $this->jobs->stagesForJob($id),
            'canPublish' => $this->context->can('job.publish'),
            'canEdit' => $this->context->can('job.update'),
            'canViewPipeline' => $this->context->can('pipeline.view'),
            'canInvite' => $this->context->can('interview.schedule'),
            'invitations' => $this->invitations->listForJob($ws, $id),
            'questions' => $this->content->questions($ws, $id),
            'criteria' => $this->content->criteria($ws, $id),
            'avatars' => $this->avatars->listActive($ws),
            'linkedAvatar' => $linkedAvatar,
            'aiStatus' => $this->ai->status($ws),
            'newLink' => $this->session->pullFlash('new_link'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function edit(string $id): Response
    {
        if (($r = $this->gate('job.update')) !== null) {
            return $r;
        }
        $job = $this->jobs->find((string) $this->context->workspaceId(), $id);
        if ($job === null) {
            return Response::redirect('/jobs');
        }

        $ws = (string) $this->context->workspaceId();

        return $this->shell->render($this->context, 'recruitment.jobs.edit', [
            'job' => $job,
            'error' => $this->session->pullFlash('error'),
            'avatars' => $this->avatars->listActive($ws),
            'stages' => $this->jobs->stagesForJob($id),
            'aiStatus' => $this->ai->status($ws),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        if (($r = $this->gate('job.update', $request)) !== null) {
            return $r;
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $this->session->flash('error', 'Job title is required.');

            return Response::redirect('/jobs/' . $id . '/edit');
        }

        $this->jobs->update((string) $this->context->workspaceId(), $id, array_merge([
            'title' => $title,
            'description' => (string) $request->input('description', ''),
            'location' => (string) $request->input('location', ''),
            'employment_type' => (string) $request->input('employment_type', ''),
            'seniority' => trim((string) $request->input('seniority', '')) ?: null,
            'salary_min' => ($v = trim((string) $request->input('salary_min', ''))) !== '' ? (int) $v : null,
            'salary_max' => ($v = trim((string) $request->input('salary_max', ''))) !== '' ? (int) $v : null,
            'currency' => trim((string) $request->input('currency', 'USD')) ?: 'USD',
        ], $this->jobConfig($request)));
        $this->audit->record('recruitment.job.updated', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'job',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Job updated.');

        return Response::redirect('/jobs/' . $id);
    }

    public function archive(Request $request, string $id): Response
    {
        if (($r = $this->gate('job.archive', $request)) !== null) {
            return $r;
        }
        $this->jobs->archive((string) $this->context->workspaceId(), $id);
        $this->audit->record('recruitment.job.archived', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'job',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Job archived.');

        return Response::redirect('/jobs');
    }

    public function addQuestion(Request $request, string $id): Response
    {
        if (($r = $this->gate('job.update', $request)) !== null) {
            return $r;
        }
        $text = trim((string) $request->input('text', ''));
        if ($text !== '') {
            $this->content->addQuestion((string) $this->context->workspaceId(), $id, $text);
            $this->session->flash('status', 'Question added to the bank.');
        }

        return Response::redirect('/jobs/' . $id);
    }

    public function removeQuestion(Request $request, string $id, string $questionId): Response
    {
        if (($r = $this->gate('job.update', $request)) !== null) {
            return $r;
        }
        $this->content->removeQuestion((string) $this->context->workspaceId(), $questionId);

        return Response::redirect('/jobs/' . $id);
    }

    public function addCriterion(Request $request, string $id): Response
    {
        if (($r = $this->gate('job.update', $request)) !== null) {
            return $r;
        }
        $label = trim((string) $request->input('label', ''));
        if ($label !== '') {
            $this->content->addCriterion((string) $this->context->workspaceId(), $id, $label, (int) $request->input('weight', 10));
            $this->session->flash('status', 'Criterion added to the rubric.');
        }

        return Response::redirect('/jobs/' . $id);
    }

    public function removeCriterion(Request $request, string $id, string $criterionId): Response
    {
        if (($r = $this->gate('job.update', $request)) !== null) {
            return $r;
        }
        $this->content->removeCriterion((string) $this->context->workspaceId(), $criterionId);

        return Response::redirect('/jobs/' . $id);
    }

    /** Generate a tokenized interview invitation link for this job (spec #5). */
    public function generateLink(Request $request, string $id): Response
    {
        if (($r = $this->gate('interview.schedule', $request)) !== null) {
            return $r;
        }

        $job = $this->jobs->find((string) $this->context->workspaceId(), $id);
        if ($job === null) {
            return Response::redirect('/jobs');
        }

        $invite = $this->invitations->create(
            (string) $this->context->workspaceId(),
            $id,
            null,
            trim((string) $request->input('candidate_email', '')) ?: null,
            $this->context->userId(),
        );
        $this->audit->record('recruitment.interview_link.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'interview_invitation',
            'entity_id' => $invite['id'],
        ]);

        $host = (string) ($request->server('HTTP_HOST') ?? 'localhost');
        $this->session->flash('new_link', 'https://' . $host . '/interview-link/' . $invite['token']);

        return Response::redirect('/jobs/' . $id);
    }

    public function publish(Request $request, string $id): Response
    {
        if (($r = $this->gate('job.publish', $request)) !== null) {
            return $r;
        }

        $this->jobs->publish((string) $this->context->workspaceId(), $id);
        $this->audit->record('recruitment.job.published', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'job',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Job published — the public link is now live.');

        return Response::redirect('/jobs/' . $id);
    }

    /** Link (or replace) the AI interviewer avatar on a job — sets the avatar persona + avatar mode. */
    public function linkAvatar(Request $request, string $id): Response
    {
        if (($r = $this->gate('job.update', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $avatarId = trim((string) $request->input('avatar_id', ''));
        $avatar = $avatarId !== '' ? $this->avatars->find($ws, $avatarId) : null;
        if ($avatar === null) {
            $this->session->flash('status', 'Choose an active avatar to link.');

            return Response::redirect('/jobs/' . $id);
        }
        $this->jobs->update($ws, $id, ['avatar_id' => $avatarId, 'interview_type' => 'avatar']);
        $this->audit->record('recruitment.job.avatar_linked', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'job',
            'entity_id' => $id,
            'changes' => ['avatar_id' => $avatarId],
        ]);
        $this->session->flash('status', 'Avatar linked — the AI interviewer now speaks as “' . (string) $avatar['name'] . '”.');

        return Response::redirect('/jobs/' . $id);
    }

    /** Remove the avatar link (X) — the AI reverts to its default strong-HR persona. */
    public function unlinkAvatar(Request $request, string $id): Response
    {
        if (($r = $this->gate('job.update', $request)) !== null) {
            return $r;
        }
        $ws = (string) $this->context->workspaceId();
        $this->jobs->update($ws, $id, ['avatar_id' => null, 'interview_type' => 'text']);
        $this->audit->record('recruitment.job.avatar_unlinked', [
            'workspace_id' => $ws,
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'job',
            'entity_id' => $id,
        ]);
        $this->session->flash('status', 'Avatar removed — the AI interviewer is back to its default behaviour.');

        return Response::redirect('/jobs/' . $id);
    }

    /**
     * Build the per-job hiring configuration from the request, normalising types.
     * Used by both store() and update() so the job is the single control surface
     * for AI screening, interview behaviour, scoring automation and the avatar.
     *
     * @return array<string, mixed>
     */
    private function jobConfig(Request $request): array
    {
        $int = static function (string $k) use ($request): ?int {
            $v = trim((string) $request->input($k, ''));

            return $v === '' ? null : max(0, (int) $v);
        };
        $str = static fn (string $k): ?string => trim((string) $request->input($k, '')) ?: null;
        $enum = static function (string $k, array $allowed, string $default) use ($request): string {
            $v = (string) $request->input($k, $default);

            return in_array($v, $allowed, true) ? $v : $default;
        };

        $passing = $int('passing_score');
        $reject = $int('auto_reject_score');
        $maxAttempts = $int('max_attempts');
        $minFi = $int('min_first_impression_score');

        return [
            'first_impression_enabled' => $request->input('first_impression_enabled') !== null ? 1 : 0,
            'min_first_impression_score' => $minFi !== null ? max(0, min(100, $minFi)) : 65,
            'ai_screening_enabled' => $request->input('ai_screening_enabled') !== null ? 1 : 0,
            'interview_required' => $request->input('interview_required') !== null ? 1 : 0,
            'interview_type' => $enum('interview_type', ['text', 'voice', 'avatar'], 'text'),
            'avatar_id' => $str('avatar_id'),
            'screening_keywords' => $str('screening_keywords'),
            'required_skills' => $str('required_skills'),
            'experience_min' => $int('experience_min'),
            'experience_max' => $int('experience_max'),
            'passing_score' => $passing !== null ? min(100, $passing) : null,
            'auto_reject_score' => $reject !== null ? min(100, $reject) : null,
            'auto_advance_stage_id' => $str('auto_advance_stage_id'),
            'interview_expiration_days' => $int('interview_expiration_days'),
            'max_attempts' => $maxAttempts !== null ? max(1, $maxAttempts) : 1,
            'interview_duration_minutes' => $int('interview_duration_minutes'),
            'questions_limit' => $int('questions_limit'),
            'interview_start_mode' => $enum('interview_start_mode', ['immediate', 'later', 'choice'], 'choice'),
            'deadline_at' => $this->normalizeDeadline((string) $request->input('deadline_at', '')),
        ];
    }

    /** Normalise a datetime-local input to a UTC 'Y-m-d H:i:s' string (or null). */
    private function normalizeDeadline(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : null;
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
