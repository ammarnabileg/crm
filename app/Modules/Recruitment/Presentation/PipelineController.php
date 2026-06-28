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
use Throwable;

/** The hiring pipeline (Kanban) for a job (docs/PIPELINE.md). */
final class PipelineController
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

    public function show(string $id): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('pipeline.view')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        $job = $this->jobs->find((string) $this->context->workspaceId(), $id);
        if ($job === null) {
            return Response::redirect('/jobs');
        }

        return $this->shell->render($this->context, 'recruitment.pipeline.index', [
            'job' => $job,
            'stages' => $this->jobs->stagesForJob($id),
            'byStage' => $this->applications->byStage((string) $this->context->workspaceId(), $id),
            'canManage' => $this->context->can('pipeline.manage'),
        ]);
    }

    public function move(Request $request, string $id): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('pipeline.manage') || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }

        try {
            $this->applications->moveStage(
                (string) $this->context->workspaceId(),
                (string) $request->input('application_id', ''),
                (string) $request->input('stage_id', ''),
                $this->context->userId(),
            );
            $this->audit->record('recruitment.application.stage_changed', [
                'workspace_id' => $this->context->workspaceId(),
                'actor_user_id' => $this->context->userId(),
                'entity_type' => 'application',
                'entity_id' => (string) $request->input('application_id', ''),
            ]);
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/jobs/' . $id . '/pipeline');
    }
}
