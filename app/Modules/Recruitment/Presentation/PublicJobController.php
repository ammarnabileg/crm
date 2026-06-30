<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\ScreeningService;
use HaHireAI\Modules\Recruitment\Domain\CvScreening;
use Throwable;

/** The public job page + apply (no login to view; login required to apply). */
final class PublicJobController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly JobService $jobs,
        private readonly ApplicationService $applications,
        private readonly ScreeningService $screening,
        private readonly InterviewService $interviews,
        private readonly AiCapabilities $ai,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
        private readonly EventDispatcher $events,
    ) {
    }

    public function show(string $token): Response
    {
        $job = $this->jobs->findPublished($token);
        if ($job === null) {
            return Response::html('<h1>404</h1><p>This job is not available.</p>', 404);
        }

        return Response::html($this->view->page('recruitment.public_job', [
            'job' => $job,
            'token' => $token,
            'authenticated' => $this->auth->check(),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ], 'layouts.guest', ['title' => $job['title']]));
    }

    public function apply(Request $request, string $token): Response
    {
        $job = $this->jobs->findPublished($token);
        if ($job === null) {
            return Response::html('<h1>404</h1><p>This job is not available.</p>', 404);
        }

        if (! $this->auth->check()) {
            $this->session->put('intended', '/jobs/public/' . $token);

            return Response::redirect('/login');
        }

        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            $this->session->flash('error', 'Security check failed.');

            return Response::redirect('/jobs/public/' . $token);
        }

        // Closed for new applications once the deadline has passed.
        if (CvScreening::deadlinePassed($job['deadline_at'] ?? null)) {
            $this->session->flash('error', 'The application deadline for this role has passed.');

            return Response::redirect('/jobs/public/' . $token);
        }

        $coverNote = trim((string) $request->input('cover_note', ''));

        try {
            $applicationId = $this->applications->apply(
                (string) $job['workspace_id'],
                (string) $job['id'],
                (string) $this->auth->id(),
                $coverNote ?: null,
                trim((string) $request->input('available_from', '')) ?: null,
            );
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/jobs/public/' . $token);
        }

        $this->audit->record('recruitment.application.submitted', [
            'workspace_id' => $job['workspace_id'],
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'application',
            'entity_id' => $applicationId,
            'ip' => $request->server('REMOTE_ADDR'),
        ]);

        // Publish the domain event. The Workflow Engine (a reactor) listens and
        // runs matching automations; recruitment stays unaware of it.
        $applicant = $this->auth->user() ?? [];
        $this->events->dispatch('application.submitted', [
            'workspace_id' => (string) $job['workspace_id'],
            'application_id' => $applicationId,
            'job_id' => (string) $job['id'],
            'job_title' => (string) ($job['title'] ?? ''),
            'user_id' => (string) $this->auth->id(),
            'candidate_name' => (string) ($applicant['name'] ?? ''),
            'candidate_email' => (string) ($applicant['email'] ?? ''),
        ]);

        // The applicant is now a candidate in this workspace — make that their
        // active context so the unified shell shows the candidate experience.
        $this->auth->setContextType('candidate');
        $this->auth->setCurrentWorkspace((string) $job['workspace_id']);

        // Credit-saving gate: only run the AI interview when the per-job toggle,
        // the workspace AI key and the keyword pre-screen all agree.
        $interviewId = null;
        if ($this->screening->shouldRunAiInterview((string) $job['workspace_id'], $job, (string) $this->auth->id(), $coverNote)) {
            try {
                $mode = ((string) ($job['interview_type'] ?? 'text') === 'avatar' && $this->ai->videoEnabled((string) $job['workspace_id'])) ? 'video' : 'text';
                $interviewId = $this->interviews->schedule((string) $job['workspace_id'], $applicationId, 'ai', ['mode' => $mode, 'created_by' => (string) $this->auth->id()]);
            } catch (Throwable) {
                // Non-fatal: the application stands even if scheduling fails.
            }
        }

        // 'immediate' start mode drops the candidate straight into the room; every
        // other case sends them to their application to start before the deadline.
        if ($interviewId !== null && (string) ($job['interview_start_mode'] ?? 'choice') === 'immediate') {
            return Response::redirect('/interview/' . $interviewId);
        }
        $this->session->flash('status', $interviewId !== null
            ? 'Your application has been submitted — your AI interview is ready. Start now or anytime before the deadline.'
            : 'Your application has been submitted. Good luck!');

        return Response::redirect('/my-applications/' . $applicationId);
    }
}
