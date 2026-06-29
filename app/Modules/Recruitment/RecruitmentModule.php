<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment;

use HaHireAI\Core\Contracts\CandidateDirectory;
use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\RecruitmentSnapshot;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Recruitment\Application\CandidateDirectoryAdapter;
use HaHireAI\Modules\Recruitment\Application\DashboardService;
use HaHireAI\Modules\Recruitment\Presentation\AvatarController;
use HaHireAI\Modules\Recruitment\Presentation\CandidatePortalController;
use HaHireAI\Modules\Recruitment\Presentation\CandidatesController;
use HaHireAI\Modules\Recruitment\Presentation\HumanInterviewController;
use HaHireAI\Modules\Recruitment\Presentation\InterviewController;
use HaHireAI\Modules\Recruitment\Presentation\WorkspaceChooserController;
use HaHireAI\Modules\Recruitment\Presentation\JobsController;
use HaHireAI\Modules\Recruitment\Presentation\OffersController;
use HaHireAI\Modules\Recruitment\Presentation\PipelineController;
use HaHireAI\Modules\Recruitment\Presentation\PublicCareersController;
use HaHireAI\Modules\Recruitment\Presentation\PublicInterviewController;
use HaHireAI\Modules\Recruitment\Presentation\PublicJobController;
use HaHireAI\Modules\Recruitment\Presentation\ReportsController;
use HaHireAI\Modules\Recruitment\Presentation\TalentPoolController;

/** The Recruitment bounded context (Phase 10). */
final class RecruitmentModule implements Module
{
    public function name(): string
    {
        return 'Recruitment';
    }

    public function dependencies(): array
    {
        return ['Workspaces', 'Files'];
    }

    public function register(Container $container): void
    {
        // Expose candidacy to decoupled layers (the workspace chooser) via the
        // Core contract, so the Workspaces module need not depend on Recruitment.
        $container->singleton(CandidateDirectory::class, CandidateDirectoryAdapter::class);
        // Real Executive Dashboard KPIs for the Workspaces dashboard.
        $container->singleton(RecruitmentSnapshot::class, DashboardService::class);
    }

    public function boot(Container $container): void
    {
    }

    public function routes(Router $router): void
    {
        // Public company careers page (no login to browse) at /view/{slug}.
        $router->get('/view/{slug}', [PublicCareersController::class, 'show']);
        $router->get('/view/{slug}/logo', [PublicCareersController::class, 'logo']);

        // Public (no login to view) — register before the generic /jobs/{id}.
        $router->get('/jobs/public/{token}', [PublicJobController::class, 'show']);
        $router->post('/jobs/public/{token}/apply', [PublicJobController::class, 'apply']);

        // Public interview-link page (tokenized, expiring, single-use).
        $router->get('/interview/{token}', [PublicInterviewController::class, 'show']);
        $router->post('/interview/{token}/start', [PublicInterviewController::class, 'start']);
        $router->post('/interview/{token}/feedback', [PublicInterviewController::class, 'feedback']);

        // Workspace-scoped job management.
        $router->get('/jobs', [JobsController::class, 'index']);
        $router->get('/jobs/create', [JobsController::class, 'create']);
        $router->post('/jobs', [JobsController::class, 'store']);
        $router->get('/jobs/{id}/edit', [JobsController::class, 'edit']);
        $router->post('/jobs/{id}/edit', [JobsController::class, 'update']);
        $router->post('/jobs/{id}/archive', [JobsController::class, 'archive']);
        $router->post('/jobs/{id}/clone', [JobsController::class, 'clone']);
        $router->get('/jobs/{id}', [JobsController::class, 'show']);
        $router->post('/jobs/{id}/publish', [JobsController::class, 'publish']);
        $router->post('/jobs/{id}/interview-link', [JobsController::class, 'generateLink']);
        $router->post('/jobs/{id}/questions', [JobsController::class, 'addQuestion']);
        $router->post('/jobs/{id}/questions/{questionId}/delete', [JobsController::class, 'removeQuestion']);
        $router->post('/jobs/{id}/criteria', [JobsController::class, 'addCriterion']);
        $router->post('/jobs/{id}/criteria/{criterionId}/delete', [JobsController::class, 'removeCriterion']);
        $router->get('/pipeline', [PipelineController::class, 'board']);
        $router->post('/pipeline/bulk-status', [PipelineController::class, 'bulkStatus']);
        $router->post('/applications/{applicationId}/status', [PipelineController::class, 'setStatus']);
        $router->get('/jobs/{id}/pipeline', [PipelineController::class, 'show']);
        $router->post('/jobs/{id}/pipeline/move', [PipelineController::class, 'move']);

        // Candidate profiles (workspace-scoped views).
        $router->get('/candidates', [CandidatesController::class, 'index']);
        $router->get('/candidates/compare', [CandidatesController::class, 'compare']);
        $router->get('/candidates/{userId}', [CandidatesController::class, 'show']);
        $router->post('/candidates/{userId}/notes', [CandidatesController::class, 'addNote']);
        $router->post('/candidates/{userId}/tags', [CandidatesController::class, 'addTag']);
        $router->post('/candidates/{userId}/parse-cv', [CandidatesController::class, 'parseCv']);
        $router->post('/candidates/{userId}/ai-summary', [CandidatesController::class, 'aiSummary']);
        $router->post('/candidates/{userId}/offer', [OffersController::class, 'make']);
        $router->get('/offers', [OffersController::class, 'index']);
        $router->get('/offers/{offerId}/print', [OffersController::class, 'print']);
        $router->post('/offers/{offerId}/accept', [OffersController::class, 'accept']);
        $router->post('/offers/{offerId}/send', [OffersController::class, 'send']);
        $router->post('/offers/{offerId}/decline', [OffersController::class, 'decline']);
        $router->post('/offers/{offerId}/withdraw', [OffersController::class, 'withdraw']);

        // AI interviews — workspace-scoped, advisory.
        $router->get('/interviews', [InterviewController::class, 'index']);
        $router->get('/interviews/export', [InterviewController::class, 'export']);
        $router->get('/interviews/{interviewId}', [InterviewController::class, 'show']);
        $router->post('/candidates/{userId}/interviews', [InterviewController::class, 'schedule']);
        $router->post('/interviews/{interviewId}/ai-run', [InterviewController::class, 'runAi']);
        $router->post('/interviews/{interviewId}/evaluate', [InterviewController::class, 'evaluate']);

        // Human (panel) interviews — second stage, with the structured 1–5 evaluation.
        $router->get('/human-interviews', [HumanInterviewController::class, 'index']);
        $router->post('/human-interviews', [HumanInterviewController::class, 'schedule']);
        $router->get('/human-interviews/{interviewId}', [HumanInterviewController::class, 'show']);
        $router->post('/human-interviews/{interviewId}/reschedule', [HumanInterviewController::class, 'reschedule']);
        $router->post('/human-interviews/{interviewId}/evaluate', [HumanInterviewController::class, 'evaluate']);
        $router->post('/human-interviews/{interviewId}/archive', [HumanInterviewController::class, 'archive']);

        // Recruitment analytics.
        $router->get('/reports', [ReportsController::class, 'index']);
        $router->get('/reports/print', [ReportsController::class, 'print']);
        $router->get('/reports/export', [ReportsController::class, 'export']);

        // AI interviewer avatars.
        $router->get('/avatars', [AvatarController::class, 'index']);
        $router->post('/avatars', [AvatarController::class, 'create']);
        $router->get('/avatars/{id}/preview', [AvatarController::class, 'preview']);
        $router->get('/avatars/{id}/image', [AvatarController::class, 'image']);
        $router->post('/avatars/{id}/status', [AvatarController::class, 'toggleStatus']);
        $router->post('/avatars/{id}/delete', [AvatarController::class, 'delete']);
        $router->post('/avatars/{id}', [AvatarController::class, 'update']);

        // Workspace chooser — "Choose a workspace to enter" (member or candidate).
        $router->get('/workspaces/select', [WorkspaceChooserController::class, 'select']);

        // Candidate Portal — the applicant's view of a workspace (no role required).
        $router->get('/portal', [CandidatePortalController::class, 'index']);
        $router->post('/portal/switch/{workspaceId}', [CandidatePortalController::class, 'switchWorkspace']);
        $router->get('/portal/jobs', [CandidatePortalController::class, 'jobs']);
        $router->post('/portal/jobs/{jobId}/apply', [CandidatePortalController::class, 'apply']);
        $router->get('/portal/interview/{interviewId}', [CandidatePortalController::class, 'room']);
        $router->post('/portal/interview/{interviewId}/answer', [CandidatePortalController::class, 'roomAnswer']);
        $router->post('/portal/interview/{interviewId}/transcribe', [CandidatePortalController::class, 'transcribe']);
        $router->get('/portal/applications', [CandidatePortalController::class, 'applications']);
        $router->get('/portal/applications/{applicationId}', [CandidatePortalController::class, 'applicationDetail']);
        $router->post('/portal/applications/{applicationId}/withdraw', [CandidatePortalController::class, 'withdrawApplication']);
        $router->post('/portal/applications/{applicationId}/counter-offer', [CandidatePortalController::class, 'counterOffer']);
        $router->post('/portal/offers/{offerId}/accept', [CandidatePortalController::class, 'acceptOffer']);
        $router->post('/portal/offers/{offerId}/decline', [CandidatePortalController::class, 'declineOffer']);
        $router->get('/portal/profile', [CandidatePortalController::class, 'profile']);
        $router->post('/portal/profile', [CandidatePortalController::class, 'updateProfile']);
        $router->post('/portal/cv', [CandidatePortalController::class, 'uploadCv']);

        // Talent pool.
        $router->get('/talent-pool', [TalentPoolController::class, 'index']);
        $router->post('/talent-pool', [TalentPoolController::class, 'create']);
        $router->get('/talent-pool/{poolId}', [TalentPoolController::class, 'show']);
        $router->post('/talent-pool/add', [TalentPoolController::class, 'addCandidate']);
        $router->post('/talent-pool/bulk-add', [TalentPoolController::class, 'bulkAdd']);
        $router->post('/talent-pool/{poolId}/remove', [TalentPoolController::class, 'removeCandidate']);
    }
}
