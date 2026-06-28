<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
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
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('job.view')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'recruitment.jobs.index', [
            'jobs' => $this->jobs->listForWorkspace((string) $this->context->workspaceId()),
            'canCreate' => $this->context->can('job.create'),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function create(): Response
    {
        if (($r = $this->gate('job.create')) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'recruitment.jobs.create', [
            'error' => $this->session->pullFlash('error'),
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

        return $this->shell->render($this->context, 'recruitment.jobs.show', [
            'job' => $job,
            'stages' => $this->jobs->stagesForJob($id),
            'canPublish' => $this->context->can('job.publish'),
            'canEdit' => $this->context->can('job.update'),
            'canViewPipeline' => $this->context->can('pipeline.view'),
            'canInvite' => $this->context->can('interview.schedule'),
            'invitations' => $this->invitations->listForJob((string) $this->context->workspaceId(), $id),
            'questions' => $this->content->questions((string) $this->context->workspaceId(), $id),
            'criteria' => $this->content->criteria((string) $this->context->workspaceId(), $id),
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

        return $this->shell->render($this->context, 'recruitment.jobs.edit', [
            'job' => $job,
            'error' => $this->session->pullFlash('error'),
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

        $this->jobs->update((string) $this->context->workspaceId(), $id, [
            'title' => $title,
            'description' => (string) $request->input('description', ''),
            'location' => (string) $request->input('location', ''),
            'employment_type' => (string) $request->input('employment_type', ''),
            'seniority' => trim((string) $request->input('seniority', '')) ?: null,
            'salary_min' => ($v = trim((string) $request->input('salary_min', ''))) !== '' ? (int) $v : null,
            'salary_max' => ($v = trim((string) $request->input('salary_max', ''))) !== '' ? (int) $v : null,
            'currency' => trim((string) $request->input('currency', 'USD')) ?: 'USD',
        ]);
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
        $this->session->flash('new_link', 'https://' . $host . '/interview/' . $invite['token']);

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
