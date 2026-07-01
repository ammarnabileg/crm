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
use HaHireAI\Modules\Recruitment\Application\FirstImpressionService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\ScreeningService;
use HaHireAI\Modules\Recruitment\Application\UserResumeService;
use HaHireAI\Modules\Recruitment\Application\UserSocialProfileService;
use HaHireAI\Modules\Recruitment\Domain\CvScreening;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\SocialLink;
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
        private readonly FirstImpressionService $firstImpression,
        private readonly UserResumeService $userResumes,
        private readonly UserSocialProfileService $userSocial,
    ) {
    }

    public function show(string $token): Response
    {
        $job = $this->jobs->findPublished($token);
        if ($job === null) {
            return Response::html('<h1>404</h1><p>This job is not available.</p>', 404);
        }

        // For First-Impression jobs, an authenticated applicant gets the inline
        // preparation fields (CV library + social auto-fill) on the public page.
        $fiEnabled = (int) ($job['first_impression_enabled'] ?? 0) === 1;
        $uid = (string) $this->auth->id();

        return Response::html($this->view->page('recruitment.public_job', [
            'job' => $job,
            'token' => $token,
            'authenticated' => $this->auth->check(),
            'firstImpression' => $fiEnabled,
            'socialFields' => $fiEnabled ? SocialLink::FIELDS : [],
            'savedLinks' => $fiEnabled && $uid !== '' ? $this->userSocial->list($uid) : [],
            'resumes' => $fiEnabled && $uid !== '' ? $this->userResumes->list($uid) : [],
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
        $ws = (string) $job['workspace_id'];
        $uid = (string) $this->auth->id();

        // First Impression gate (opt-in per job): resolve the mandatory CV first,
        // so a missing résumé sends the applicant back before anything is created.
        $fiEnabled = (int) ($job['first_impression_enabled'] ?? 0) === 1;
        $resumeId = null;
        if ($fiEnabled) {
            $resumeId = $this->resolveResume($request, $uid);
            if ($resumeId === null) {
                $this->session->flash('error', 'Please select an existing CV or upload one to continue.');

                return Response::redirect('/jobs/public/' . $token);
            }
        }

        try {
            $applicationId = $this->applications->apply(
                $ws,
                (string) $job['id'],
                $uid,
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
            'workspace_id' => $ws,
            'application_id' => $applicationId,
            'job_id' => (string) $job['id'],
            'job_title' => (string) ($job['title'] ?? ''),
            'user_id' => $uid,
            'candidate_name' => (string) ($applicant['name'] ?? ''),
            'candidate_email' => (string) ($applicant['email'] ?? ''),
        ]);

        // The applicant is now a candidate in this workspace — make that their
        // active context so the unified shell shows the candidate experience.
        $this->auth->setContextType('candidate');
        $this->auth->setCurrentWorkspace($ws);

        // Run the zero-AI First Impression Engine. A fail saves the application as
        // "Filtered Before AI" and spends NO AI credits.
        if ($fiEnabled && $resumeId !== null) {
            $links = $this->userSocial->save($uid, $this->collectLinks($request));
            $outcome = $this->firstImpression->run($ws, $job, $applicationId, $uid, $resumeId, $links, $coverNote);
            if (! $outcome['passed']) {
                $this->applications->setStatus($ws, $applicationId, 'filtered_pre_ai', $uid);
                $this->session->flash('status', sprintf(
                    'Application received. A first-impression review scored %d%% (this role asks for %d%%), so it will not proceed to the AI interview now — the hiring team can still review your full report.',
                    (int) $outcome['overall'],
                    (int) $outcome['threshold'],
                ));

                return Response::redirect('/my-applications/' . $applicationId);
            }
        }

        // Credit-saving gate: only run the AI interview when the per-job toggle,
        // the workspace AI key and the keyword pre-screen all agree.
        $interviewId = null;
        if ($this->screening->shouldRunAiInterview($ws, $job, $uid, $coverNote)) {
            try {
                $mode = ((string) ($job['interview_type'] ?? 'text') === 'avatar' && $this->ai->videoEnabled($ws)) ? 'video' : 'text';
                $interviewId = $this->interviews->schedule($ws, $applicationId, 'ai', ['mode' => $mode, 'created_by' => $uid]);
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

    /** Resolve the chosen résumé to a global CV-library id (upload wins over a picked id). */
    private function resolveResume(Request $request, string $uid): ?string
    {
        $cv = $request->file('cv');
        if ($cv !== null && ($cv['tmp_name'] ?? '') !== '') {
            try {
                return $this->userResumes->store($uid, $cv['tmp_name'], (string) $cv['name'], $cv['type'] ?? null);
            } catch (Throwable $e) {
                $this->session->flash('error', $e->getMessage());

                return null;
            }
        }
        $resumeId = trim((string) $request->input('resume_id', ''));

        return ($resumeId !== '' && $this->userResumes->find($uid, $resumeId) !== null) ? $resumeId : null;
    }

    /**
     * Collect social links from the inline preparation fields.
     *
     * @return list<string>
     */
    private function collectLinks(Request $request): array
    {
        $links = [];
        foreach (array_keys(SocialLink::FIELDS) as $field) {
            $val = trim((string) $request->input('social_' . $field, ''));
            if ($val !== '') {
                $links[] = $val;
            }
        }
        $extra = $request->input('social_extra', '');
        if (is_string($extra) && trim($extra) !== '') {
            foreach (preg_split('/[\s,]+/', $extra) ?: [] as $u) {
                if (trim($u) !== '') {
                    $links[] = trim($u);
                }
            }
        }

        return $links;
    }
}
