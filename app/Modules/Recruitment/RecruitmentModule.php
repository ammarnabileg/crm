<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Recruitment\Presentation\CandidatesController;
use HaHireAI\Modules\Recruitment\Presentation\InterviewController;
use HaHireAI\Modules\Recruitment\Presentation\JobsController;
use HaHireAI\Modules\Recruitment\Presentation\OffersController;
use HaHireAI\Modules\Recruitment\Presentation\PipelineController;
use HaHireAI\Modules\Recruitment\Presentation\PublicJobController;

/** The Recruitment bounded context (Phase 10). */
final class RecruitmentModule implements Module
{
    public function name(): string
    {
        return 'Recruitment';
    }

    public function dependencies(): array
    {
        return ['Workspaces'];
    }

    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        // Public (no login to view) — register before the generic /jobs/{id}.
        $router->get('/jobs/public/{token}', [PublicJobController::class, 'show']);
        $router->post('/jobs/public/{token}/apply', [PublicJobController::class, 'apply']);

        // Workspace-scoped job management.
        $router->get('/jobs', [JobsController::class, 'index']);
        $router->get('/jobs/create', [JobsController::class, 'create']);
        $router->post('/jobs', [JobsController::class, 'store']);
        $router->get('/jobs/{id}', [JobsController::class, 'show']);
        $router->post('/jobs/{id}/publish', [JobsController::class, 'publish']);
        $router->get('/jobs/{id}/pipeline', [PipelineController::class, 'show']);
        $router->post('/jobs/{id}/pipeline/move', [PipelineController::class, 'move']);

        // Candidate profiles (workspace-scoped views).
        $router->get('/candidates', [CandidatesController::class, 'index']);
        $router->get('/candidates/{userId}', [CandidatesController::class, 'show']);
        $router->post('/candidates/{userId}/notes', [CandidatesController::class, 'addNote']);
        $router->post('/candidates/{userId}/tags', [CandidatesController::class, 'addTag']);
        $router->post('/candidates/{userId}/ai-summary', [CandidatesController::class, 'aiSummary']);
        $router->post('/candidates/{userId}/offer', [OffersController::class, 'make']);
        $router->post('/offers/{offerId}/accept', [OffersController::class, 'accept']);

        // Interviews (AI + human) — workspace-scoped, advisory.
        $router->get('/interviews', [InterviewController::class, 'index']);
        $router->post('/candidates/{userId}/interviews', [InterviewController::class, 'schedule']);
        $router->post('/interviews/{interviewId}/ai-run', [InterviewController::class, 'runAi']);
        $router->post('/interviews/{interviewId}/evaluate', [InterviewController::class, 'evaluate']);
    }
}
