<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
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
        private readonly Session $session,
        private readonly AuditLogger $audit,
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
            'canViewPipeline' => $this->context->can('pipeline.view'),
            'status' => $this->session->pullFlash('status'),
        ]);
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
