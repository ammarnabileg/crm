<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment;

use HaHireAI\Core\Contracts\CandidateDirectory;
use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Contracts\RecruitmentActions;
use HaHireAI\Core\Contracts\RecruitmentSnapshot;
use HaHireAI\Core\Contracts\SocialProfileProbe;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Modules\Module;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\Recruitment\Application\CandidateDirectoryAdapter;
use HaHireAI\Modules\Recruitment\Application\DashboardService;
use HaHireAI\Modules\Recruitment\Application\FirstImpressionService;
use HaHireAI\Modules\Recruitment\Application\RecruitmentActionsAdapter;
use HaHireAI\Modules\Recruitment\Application\UserResumeService;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\ResumeAnalysisEngine;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeStructurer;
use HaHireAI\Modules\Recruitment\Infrastructure\Resume\ResumeParserManager;
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
        // The write-surface the Workflow Engine uses to act on applications.
        $container->singleton(RecruitmentActions::class, RecruitmentActionsAdapter::class);

        // --- First Impression Engine (zero-AI gate) ---------------------------
        // The résumé parsing layer (PDF/DOCX/TXT registry).
        $container->singleton(ResumeParserManager::class, static fn (): ResumeParserManager => new ResumeParserManager());
        // The candidate's GLOBAL CV library (bytes outside the web root).
        $container->singleton(UserResumeService::class, static fn (Container $c): UserResumeService => new UserResumeService(
            $c->make(Connection::class),
            $c->make(ResumeParserManager::class),
            storage_path('resumes'),
        ));
        // The orchestrator. Social probing is OPTIONAL: when the Integration
        // Platform is enabled it binds SocialProfileProbe; otherwise the gate runs
        // résumé-only (still fully functional, no penalty for missing social).
        $container->singleton(FirstImpressionService::class, static fn (Container $c): FirstImpressionService => new FirstImpressionService(
            $c->make(Connection::class),
            $c->make(ResumeStructurer::class),
            $c->make(ResumeAnalysisEngine::class),
            $c->make(UserResumeService::class),
            $c->make(EventDispatcher::class),
            $c->has(SocialProfileProbe::class) ? $c->make(SocialProfileProbe::class) : null,
        ));
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

        // Public interview-link page (tokenized, expiring, single-use). Named
        // /interview-link/* so a logged-in candidate's room can own /interview/{id}.
        $router->get('/interview-link/{token}', [PublicInterviewController::class, 'show']);
        $router->post('/interview-link/{token}/start', [PublicInterviewController::class, 'start']);
        $router->post('/interview-link/{token}/answer', [PublicInterviewController::class, 'answer']);
        $router->post('/interview-link/{token}/feedback', [PublicInterviewController::class, 'feedback']);

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
        $router->post('/jobs/{id}/avatar', [JobsController::class, 'linkAvatar']);
        $router->post('/jobs/{id}/avatar/remove', [JobsController::class, 'unlinkAvatar']);
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

        // Candidate experience — the applicant's view of a workspace (no role
        // required). Clean, unprefixed URLs (no /portal): the candidate is just
        // another context on the one unified surface. The candidate landing is
        // /dashboard (context-aware) → /my-applications.
        $router->post('/candidacy/{workspaceId}/switch', [CandidatePortalController::class, 'switchWorkspace']);
        $router->get('/open-jobs', [CandidatePortalController::class, 'jobs']);
        $router->get('/open-jobs/{jobId}/prepare', [CandidatePortalController::class, 'prepare']);
        $router->post('/open-jobs/{jobId}/apply', [CandidatePortalController::class, 'apply']);
        $router->get('/interview/{interviewId}', [CandidatePortalController::class, 'room']);
        $router->post('/interview/{interviewId}/cv', [CandidatePortalController::class, 'roomCv']);
        $router->post('/interview/{interviewId}/answer', [CandidatePortalController::class, 'roomAnswer']);
        $router->post('/interview/{interviewId}/transcribe', [CandidatePortalController::class, 'transcribe']);
        $router->get('/my-applications', [CandidatePortalController::class, 'applications']);
        $router->get('/my-applications/{applicationId}', [CandidatePortalController::class, 'applicationDetail']);
        $router->post('/my-applications/{applicationId}/withdraw', [CandidatePortalController::class, 'withdrawApplication']);
        $router->post('/my-applications/{applicationId}/counter-offer', [CandidatePortalController::class, 'counterOffer']);
        $router->post('/my-offers/{offerId}/accept', [CandidatePortalController::class, 'acceptOffer']);
        $router->post('/my-offers/{offerId}/decline', [CandidatePortalController::class, 'declineOffer']);
        $router->get('/my-profile', [CandidatePortalController::class, 'profile']);
        $router->post('/my-profile', [CandidatePortalController::class, 'updateProfile']);
        $router->post('/my-profile/cv', [CandidatePortalController::class, 'uploadCv']);
        // The candidate's own First Impression insights (read-only; refreshed on
        // each new application).
        $router->get('/my-insights', [CandidatePortalController::class, 'insights']);
        $router->get('/my-insights/{reportId}', [CandidatePortalController::class, 'insight']);

        // Talent pool.
        $router->get('/talent-pool', [TalentPoolController::class, 'index']);
        $router->post('/talent-pool', [TalentPoolController::class, 'create']);
        $router->get('/talent-pool/{poolId}', [TalentPoolController::class, 'show']);
        $router->post('/talent-pool/add', [TalentPoolController::class, 'addCandidate']);
        $router->post('/talent-pool/bulk-add', [TalentPoolController::class, 'bulkAdd']);
        $router->post('/talent-pool/{poolId}/remove', [TalentPoolController::class, 'removeCandidate']);
    }
}
