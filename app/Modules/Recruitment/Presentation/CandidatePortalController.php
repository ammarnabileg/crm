<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Recruitment\Application\ApplicationService;
use HaHireAI\Modules\Recruitment\Application\AssessmentService;
use HaHireAI\Modules\Recruitment\Application\CandidacyService;
use HaHireAI\Modules\Recruitment\Application\CandidateContext;
use HaHireAI\Modules\Recruitment\Application\CandidateProfileService;
use HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException;
use HaHireAI\Modules\Recruitment\Application\FirstImpressionReportService;
use HaHireAI\Modules\Recruitment\Application\FirstImpressionService;
use HaHireAI\Modules\Recruitment\Application\InterviewRoomService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Modules\Recruitment\Application\ScreeningService;
use HaHireAI\Modules\Recruitment\Application\UserResumeService;
use HaHireAI\Modules\Recruitment\Application\UserSocialProfileService;
use HaHireAI\Core\Contracts\UserDirectory;
use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\AiEngine\Contracts\SpeechToText;
use HaHireAI\Modules\Recruitment\Domain\ApplicationStatus;
use HaHireAI\Modules\Recruitment\Domain\CvScreening;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\SocialLink;

/**
 * The Candidate Portal — the User's view of a Workspace *as an applicant* (the
 * candidate side of the sidebar). Available to anyone who has applied to the
 * workspace and does not hold a role there. Everything is workspace-scoped: a
 * candidate only ever sees their own data in this workspace (privacy by design).
 */
final class CandidatePortalController
{
    public function __construct(
        private readonly CandidateShell $shell,
        private readonly CandidateContext $context,
        private readonly AuthContext $auth,
        private readonly CandidacyService $candidacy,
        private readonly JobService $jobs,
        private readonly ApplicationService $applications,
        private readonly OfferService $offers,
        private readonly ScreeningService $screening,
        private readonly InterviewService $interviews,
        private readonly InterviewRoomService $room,
        private readonly AssessmentService $assessments,
        private readonly UserDirectory $users,
        private readonly FileStorage $files,
        private readonly CandidateProfileService $profiles,
        private readonly SpeechToText $speech,
        private readonly AiCapabilities $ai,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
        private readonly FirstImpressionService $firstImpression,
        private readonly FirstImpressionReportService $fiReports,
        private readonly UserResumeService $userResumes,
        private readonly UserSocialProfileService $userSocial,
    ) {
    }

    /** Page 1 — Candidate Portal overview (status, interviews, offers, latest jobs). */
    /** Page 2 — Available Jobs in this workspace. */
    public function jobs(Request $request): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'employment_type' => trim((string) $request->input('employment_type', '')),
            'seniority' => trim((string) $request->input('seniority', '')),
            'location' => trim((string) $request->input('location', '')),
        ];

        return $this->shell->render($this->context, 'portal.jobs', [
            'jobs' => $this->jobs->listPublished($ws, $uid, $filters),
            'facets' => $this->jobs->publishedFacets($ws),
            'filters' => $filters,
            'workspaceName' => $this->context->workspace()['name'] ?? '',
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /**
     * Application Preparation (spec) — shown for jobs with the First Impression
     * filter enabled, BEFORE the application is created. Auto-fills the
     * candidate's saved social links and lists their global CV library so they
     * can select an existing CV or upload a new one. Jobs without the filter keep
     * the original one-step apply, untouched.
     */
    public function prepare(Request $request, string $jobId): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();
        $job = $this->jobs->find($ws, $jobId);
        if ($job === null || (string) $job['status'] !== 'published') {
            $this->session->flash('status', 'That job is no longer open.');

            return Response::redirect('/open-jobs');
        }
        if (CvScreening::deadlinePassed($job['deadline_at'] ?? null)) {
            $this->session->flash('status', 'The application deadline for this role has passed.');

            return Response::redirect('/open-jobs');
        }

        return $this->shell->render($this->context, 'portal.prepare', [
            'job' => $job,
            'socialFields' => SocialLink::FIELDS,
            'savedLinks' => $this->userSocial->list($uid),
            'resumes' => $this->userResumes->list($uid),
            'workspaceName' => $this->context->workspace()['name'] ?? '',
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Apply to a job from inside the portal. */
    public function apply(Request $request, string $jobId): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();
        $job = $this->jobs->find($ws, $jobId);
        if ($job === null || (string) $job['status'] !== 'published') {
            $this->session->flash('status', 'That job is no longer open.');

            return Response::redirect('/open-jobs');
        }

        // Closed for new applications once the deadline has passed.
        if (CvScreening::deadlinePassed($job['deadline_at'] ?? null)) {
            $this->session->flash('status', 'The application deadline for this role has passed.');

            return Response::redirect('/open-jobs');
        }

        $coverNote = trim((string) $request->input('cover_note', ''));

        // First Impression gate (opt-in per job): the zero-AI engine decides
        // whether this application reaches the (paid) AI interview at all.
        if ((int) ($job['first_impression_enabled'] ?? 0) === 1) {
            return $this->applyWithFirstImpression($request, $ws, $uid, $job, $jobId, $coverNote);
        }

        try {
            $appId = $this->applications->apply($ws, $jobId, $uid, $coverNote ?: null, trim((string) $request->input('available_from', '')) ?: null);
        } catch (ApplicationException $e) {
            $this->session->flash('status', $e->getMessage());

            return Response::redirect('/open-jobs');
        }

        // Attach a freshly uploaded CV to the application (spec: choose or upload).
        $cv = $request->file('cv');
        if ($cv !== null && ($cv['tmp_name'] ?? '') !== '') {
            try {
                $this->files->store($ws, $uid, 'application', $appId, $cv['tmp_name'], $cv['name'], $cv['size'] ?? null);
            } catch (\Throwable) {
                // Non-fatal — the application stands without the attachment.
            }
        }

        // Credit-saving gate: schedule the AI interview only when the per-job
        // toggle, the workspace AI key and the keyword pre-screen all agree.
        $interviewId = null;
        if ($this->screening->shouldRunAiInterview($ws, $job, $uid, $coverNote)) {
            try {
                $interviewId = $this->interviews->schedule($ws, $appId, 'ai', ['mode' => $this->interviewMode($ws, $job), 'created_by' => $uid]);
            } catch (ApplicationException) {
                // Non-fatal: the application stands even if scheduling fails.
            }
        }

        $this->audit->record('recruitment.application.submitted', [
            'workspace_id' => $ws,
            'actor_user_id' => $uid,
            'entity_type' => 'application',
            'entity_id' => $appId,
        ]);

        return $this->afterApply($job, $appId, $interviewId);
    }

    /**
     * The First-Impression-gated apply path. The application + candidate + CV +
     * report are ALWAYS created (nothing is thrown away). The zero-AI engine then
     * decides: pass → continue to the existing AI-screening gate; fail → the
     * application is saved as "Filtered Before AI" and NO AI credits are spent.
     *
     * @param  array<string,mixed>  $job
     */
    private function applyWithFirstImpression(Request $request, string $ws, string $uid, array $job, string $jobId, string $coverNote): Response
    {
        // Résumé is mandatory: a chosen library CV or a fresh upload.
        $resumeId = $this->resolveResume($request, $uid);
        if ($resumeId === null) {
            $this->session->flash('status', 'Please select an existing CV or upload one to continue.');

            return Response::redirect('/open-jobs/' . $jobId . '/prepare');
        }

        // Social links are optional; save them onto the candidate's global profile.
        $links = $this->userSocial->save($uid, $this->collectLinks($request));

        try {
            $appId = $this->applications->apply($ws, $jobId, $uid, $coverNote ?: null, trim((string) $request->input('available_from', '')) ?: null);
        } catch (ApplicationException $e) {
            $this->session->flash('status', $e->getMessage());

            return Response::redirect('/open-jobs');
        }

        // Make the evaluated CV available to the recruiter on this application.
        $this->attachResumeToApplication($ws, $uid, $appId, $resumeId);

        $this->audit->record('recruitment.application.submitted', [
            'workspace_id' => $ws, 'actor_user_id' => $uid, 'entity_type' => 'application', 'entity_id' => $appId,
        ]);

        // Run the zero-AI First Impression Engine (no provider, no credits).
        $outcome = $this->firstImpression->run($ws, $job, $appId, $uid, $resumeId, $links, $coverNote);

        if (! $outcome['passed']) {
            // Saved as a candidate, visible in the pipeline, but NOT sent to AI.
            $this->applications->setStatus($ws, $appId, 'filtered_pre_ai', $uid);
            $this->session->flash('status', sprintf(
                'Application received. A first-impression review scored %d%% (the role asks for %d%%), so it will not proceed to the AI interview now — the hiring team can still review your full report.',
                (int) $outcome['overall'],
                (int) $outcome['threshold'],
            ));

            return Response::redirect('/my-applications/' . $appId);
        }

        // Passed the gate → the existing AI-screening gate decides the interview.
        $interviewId = null;
        if ($this->screening->shouldRunAiInterview($ws, $job, $uid, $coverNote)) {
            try {
                $interviewId = $this->interviews->schedule($ws, $appId, 'ai', ['mode' => $this->interviewMode($ws, $job), 'created_by' => $uid]);
            } catch (ApplicationException) {
                // Non-fatal.
            }
        }

        return $this->afterApply($job, $appId, $interviewId);
    }

    /**
     * Resolve the chosen résumé to a global CV-library id: a freshly uploaded
     * file is stored into the library first; otherwise a picked library id is
     * validated. Returns null when neither is available.
     */
    private function resolveResume(Request $request, string $uid): ?string
    {
        $cv = $request->file('cv');
        if ($cv !== null && ($cv['tmp_name'] ?? '') !== '') {
            try {
                return $this->userResumes->store($uid, $cv['tmp_name'], (string) $cv['name'], $cv['type'] ?? null);
            } catch (\Throwable $e) {
                $this->session->flash('status', $e->getMessage());

                return null;
            }
        }
        $resumeId = trim((string) $request->input('resume_id', ''));

        return ($resumeId !== '' && $this->userResumes->find($uid, $resumeId) !== null) ? $resumeId : null;
    }

    /** Copy the evaluated library CV onto the application so the recruiter can read it. */
    private function attachResumeToApplication(string $ws, string $uid, string $appId, string $resumeId): void
    {
        $row = $this->userResumes->find($uid, $resumeId);
        $bytes = $row !== null ? $this->userResumes->readBytes($uid, $resumeId) : null;
        if ($row === null || $bytes === null) {
            return;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'cv');
        if ($tmp === false) {
            return;
        }
        try {
            file_put_contents($tmp, $bytes);
            $this->files->store($ws, $uid, 'application', $appId, $tmp, (string) $row['original_name'], (int) ($row['size_bytes'] ?? null), false);
        } catch (\Throwable) {
            // Non-fatal.
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Collect the candidate's social links from the Preparation form: the named
     * platform fields plus any free-form extra links.
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

    /**
     * The candidate's OWN First Impression insights — read-only, across every
     * workspace they applied to. They can view but never edit it, and it refreshes
     * with each new application (spec).
     */
    public function insights(): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'portal.insights', [
            'reports' => $this->fiReports->forCandidateGlobal((string) $this->context->userId()),
            'workspaceName' => $this->context->workspace()['name'] ?? '',
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** One of the candidate's own reports in full — read-only. */
    public function insight(string $reportId): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }
        $full = $this->fiReports->fullForCandidate((string) $this->context->userId(), $reportId);
        if ($full === null) {
            return Response::redirect('/my-insights');
        }

        return $this->shell->render($this->context, 'portal.insight', [
            'full' => $full,
            'workspaceName' => $this->context->workspace()['name'] ?? '',
        ]);
    }

    /**
     * Where the candidate lands after applying, honouring the job's start mode:
     * 'immediate' goes straight into the room; 'later'/'choice' send them to their
     * application, from where they can start the AI interview anytime before the
     * deadline. With no AI interview they just track the application.
     *
     * @param array<string,mixed> $job
     */
    private function afterApply(array $job, string $appId, ?string $interviewId): Response
    {
        if ($interviewId === null) {
            $this->session->flash('status', 'Application submitted. Track it here.');

            return Response::redirect('/my-applications/' . $appId);
        }

        if ((string) ($job['interview_start_mode'] ?? 'choice') === 'immediate') {
            $this->session->flash('status', 'Application submitted — your AI interview starts now.');

            return Response::redirect('/interview/' . $interviewId);
        }

        $this->session->flash('status', 'Application submitted. Your AI interview is ready — start now or anytime before the deadline.');

        return Response::redirect('/my-applications/' . $appId);
    }

    /** The interview mode to schedule, with avatar→video only when HeyGen is configured (else text). */
    private function interviewMode(string $workspaceId, array $job): string
    {
        if ((string) ($job['interview_type'] ?? 'text') === 'avatar' && $this->ai->videoEnabled($workspaceId)) {
            return 'video';
        }

        return 'text';
    }

    /** The AI interview room (start or resume). */
    public function room(string $interviewId): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        $iv = $this->ownedInterview($interviewId);
        if ($iv === null) {
            return Response::redirect('/my-applications');
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();

        // Deadline guard: a not-yet-started interview cannot be entered after the
        // job's application deadline (resuming an in-progress one still works).
        if ((string) $iv['status'] !== 'completed' && empty($iv['started_at'])) {
            $job = $this->jobs->find($ws, (string) $iv['job_id']);
            if ($job !== null && CvScreening::deadlinePassed($job['deadline_at'] ?? null)) {
                $this->session->flash('status', 'The deadline for this role has passed — the interview is now closed.');

                return Response::redirect('/my-applications/' . (string) $iv['application_id']);
            }
        }

        // CV gate (spec): a CV must be on record before the live interview — the
        // candidate either picks one from their library or uploads a new one.
        if ($this->room->needsCv($ws, $interviewId)) {
            return $this->shell->render($this->context, 'portal.interview_cv', [
                'interview' => $iv,
                'cvs' => $this->files->listForEntity($ws, 'cv', $uid),
                'applicationId' => (string) $iv['application_id'],
                'workspaceName' => $this->context->workspace()['name'] ?? '',
                'status' => $this->session->pullFlash('status'),
            ]);
        }

        $state = (string) $iv['status'] === 'completed'
            ? $this->room->state($ws, $interviewId)
            : $this->room->begin($ws, $interviewId, $uid);

        return $this->shell->render($this->context, 'portal.interview', [
            'interview' => $iv,
            'state' => $state,
            'applicationId' => (string) $iv['application_id'],
            'workspaceName' => $this->context->workspace()['name'] ?? '',
        ]);
    }

    /**
     * CV gate submission: record the chosen library CV or a freshly uploaded one
     * against the interview, then enter the room. Stays on the gate (with a hint)
     * until a real CV is provided.
     */
    public function roomCv(Request $request, string $interviewId): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $iv = $this->ownedInterview($interviewId);
        if ($iv === null) {
            return Response::redirect('/my-applications');
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();
        $fileId = trim((string) $request->input('cv_file_id', ''));

        // A freshly uploaded CV joins the candidate's library and is used here.
        $cv = $request->file('cv');
        if ($cv !== null && ($cv['tmp_name'] ?? '') !== '') {
            try {
                $fileId = $this->files->store($ws, $uid, 'cv', $uid, $cv['tmp_name'], $cv['name'], $cv['size'] ?? null);
            } catch (\Throwable $e) {
                $this->session->flash('status', $e->getMessage());

                return Response::redirect('/interview/' . $interviewId);
            }
        }

        // Must end up with a real CV — a picked library file or a new upload.
        if ($fileId === '' || $this->files->find($ws, $fileId) === null) {
            $this->session->flash('status', 'Please choose one of your CVs or upload a new one to begin.');

            return Response::redirect('/interview/' . $interviewId);
        }

        $this->room->attachCv($ws, $interviewId, $fileId);

        return Response::redirect('/interview/' . $interviewId);
    }

    public function roomAnswer(Request $request, string $interviewId): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $iv = $this->ownedInterview($interviewId);
        if ($iv === null) {
            return Response::redirect('/my-applications');
        }

        $this->room->answer(
            (string) $this->context->workspaceId(),
            $interviewId,
            (string) $request->input('answer', ''),
            (string) $this->context->userId(),
            (string) $request->input('action', '') === 'change', // "ask me a different question"
        );

        // Post/Redirect/Get — the room page renders the updated conversation.
        return Response::redirect('/interview/' . $interviewId);
    }

    /**
     * Voice mode (B): transcribe an uploaded audio clip server-side via Whisper,
     * using this workspace's own OpenAI key. Returns JSON {text} or {text:null}
     * so the client can fall back to on-device recognition.
     */
    public function transcribe(Request $request, string $interviewId): Response
    {
        if (! $this->auth->check()) {
            return Response::json(['text' => null, 'error' => 'unauthenticated'], 401);
        }
        if (! $this->context->resolve() || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::json(['text' => null, 'error' => 'forbidden'], 403);
        }
        if ($this->ownedInterview($interviewId) === null) {
            return Response::json(['text' => null, 'error' => 'not_found'], 404);
        }

        $audio = $request->file('audio');
        if ($audio === null) {
            return Response::json(['text' => null, 'error' => 'no_audio'], 422);
        }

        $text = $this->speech->transcribe(
            (string) $this->context->workspaceId(),
            $audio['tmp_name'],
            $audio['name'] !== '' ? $audio['name'] : 'answer.webm',
            $audio['type'] !== '' ? $audio['type'] : 'audio/webm',
        );

        return Response::json([
            'text' => $text,
            'error' => $text === null ? 'transcription_unavailable' : null,
        ]);
    }

    /** @return array<string,mixed>|null an AI interview owned by the current candidate */
    private function ownedInterview(string $interviewId): ?array
    {
        $iv = $this->interviews->find((string) $this->context->workspaceId(), $interviewId);
        if ($iv === null || (string) $iv['candidate_user_id'] !== (string) $this->context->userId() || (string) $iv['type'] !== 'ai') {
            return null;
        }

        return $iv;
    }

    /** Page 3 — My Applications (with offers, accept/reject/counter). */
    public function applications(): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();

        return $this->shell->render($this->context, 'portal.applications', [
            'applications' => $this->candidacy->overview($ws, $uid)['applications'],
            'offers' => $this->offers->forCandidate($ws, $uid),
            'workspaceName' => $this->context->workspace()['name'] ?? '',
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Candidate withdraws their own application. */
    public function withdrawApplication(Request $request, string $applicationId): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();

        if ($this->applications->withdraw($ws, $applicationId, $uid)) {
            $this->audit->record('recruitment.application.withdrawn', [
                'workspace_id' => $ws,
                'actor_user_id' => $uid,
                'entity_type' => 'application',
                'entity_id' => $applicationId,
                'ip' => $request->server('REMOTE_ADDR'),
            ]);
            $this->session->flash('status', 'Your application has been withdrawn.');
        } else {
            $this->session->flash('status', 'This application can no longer be withdrawn.');
        }

        return Response::redirect('/my-applications/' . $applicationId);
    }

    /** Page 3b — one application: stage map, what the AI noted, next step, offers. */
    public function applicationDetail(string $applicationId): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();
        $application = $this->applications->findForCandidate($ws, $applicationId, $uid);
        if ($application === null) {
            return Response::redirect('/my-applications');
        }

        $assessment = $this->assessments->latestForCandidate($ws, $uid);
        $interviews = $this->interviews->forApplication($ws, $applicationId);
        $pendingInterview = null;
        foreach ($interviews as $iv) {
            if ((string) $iv['type'] === 'ai' && (string) $iv['status'] !== 'completed') {
                $pendingInterview = $iv;
                break;
            }
        }

        return $this->shell->render($this->context, 'portal.application', [
            'application' => $application,
            'stages' => ApplicationStatus::STATUSES,
            'currentStatus' => (string) $application['status'],
            'nextStep' => $this->nextStep((string) $application['status']),
            'interviews' => $interviews,
            'pendingInterview' => $pendingInterview,
            'deadlinePassed' => CvScreening::deadlinePassed($application['deadline_at'] ?? null),
            'offers' => $this->offers->forApplication($ws, $applicationId),
            'assessment' => $assessment,
            'workspaceName' => $this->context->workspace()['name'] ?? '',
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Page 4 — My Profile (edit personal data). */
    public function profile(): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'portal.profile', [
            'user' => $this->auth->user(),
            'cvs' => $this->files->listForEntity((string) $this->context->workspaceId(), 'cv', (string) $this->context->userId()),
            'details' => $this->profiles->details((string) $this->context->workspaceId(), (string) $this->context->userId()),
            'workspaceName' => $this->context->workspace()['name'] ?? '',
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    /** Upload a CV to the candidate's library (reusable across applications). */
    public function uploadCv(Request $request): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $cv = $request->file('cv');
        if ($cv !== null && ($cv['tmp_name'] ?? '') !== '') {
            try {
                $this->files->store((string) $this->context->workspaceId(), (string) $this->context->userId(), 'cv', (string) $this->context->userId(), $cv['tmp_name'], $cv['name'], $cv['size'] ?? null);
                $this->session->flash('status', 'CV uploaded.');
            } catch (\Throwable $e) {
                $this->session->flash('status', $e->getMessage());
            }
        } else {
            $this->session->flash('status', 'Choose a PDF or Word file to upload.');
        }

        return Response::redirect('/my-profile');
    }

    public function updateProfile(Request $request): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('status', 'Your name is required.');

            return Response::redirect('/my-profile');
        }

        $this->users->updatePersonal(
            (string) $this->auth->id(),
            $name,
            trim((string) $request->input('phone', '')) ?: null,
            $this->intOrNull($request->input('years_experience')),
            $this->intOrNull($request->input('target_salary')),
        );

        // Structured CV data shown on the recruiter's Decision Center.
        $this->profiles->saveDetails((string) $this->context->workspaceId(), (string) $this->context->userId(), [
            'education' => trim((string) $request->input('education', '')),
            'languages' => trim((string) $request->input('languages', '')),
            'skills' => trim((string) $request->input('skills', '')),
            'certifications' => trim((string) $request->input('certifications', '')),
            'current_salary' => $this->intOrNull($request->input('current_salary')),
            'expected_salary' => $this->intOrNull($request->input('target_salary')),
            'availability' => trim((string) $request->input('availability', '')),
            'location' => trim((string) $request->input('location', '')),
        ]);
        $this->session->flash('status', 'Profile updated.');

        return Response::redirect('/my-profile');
    }

    /** Accept a company offer. */
    public function acceptOffer(Request $request, string $offerId): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        try {
            $this->offers->acceptAsCandidate((string) $this->context->workspaceId(), $offerId, (string) $this->context->userId());
            $this->session->flash('status', 'Offer accepted. Welcome aboard!');
        } catch (ApplicationException $e) {
            $this->session->flash('status', $e->getMessage());
        }

        return Response::redirect('/my-applications');
    }

    /** Decline a company offer. */
    public function declineOffer(Request $request, string $offerId): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        try {
            $this->offers->declineAsCandidate((string) $this->context->workspaceId(), $offerId, (string) $this->context->userId());
            $this->session->flash('status', 'Offer declined.');
        } catch (ApplicationException $e) {
            $this->session->flash('status', $e->getMessage());
        }

        return Response::redirect('/my-applications');
    }

    /** Propose a counter-offer back to the company, with an explanatory note. */
    public function counterOffer(Request $request, string $applicationId): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $title = trim((string) $request->input('title', '')) ?: 'Counter-proposal';
        try {
            $this->offers->counter(
                (string) $this->context->workspaceId(),
                $applicationId,
                (string) $this->context->userId(),
                $title,
                $this->intOrNull($request->input('salary')),
                trim((string) $request->input('currency', 'USD')) ?: 'USD',
                trim((string) $request->input('note', '')) ?: null,
            );
            $this->session->flash('status', 'Your counter-proposal was sent to the company.');
        } catch (ApplicationException $e) {
            $this->session->flash('status', $e->getMessage());
        }

        return Response::redirect('/my-applications/' . $applicationId);
    }

    /** Switch which candidate workspace the portal is showing. */
    public function switchWorkspace(Request $request, string $workspaceId): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::redirect('/my-applications');
        }
        $this->auth->setContextType('candidate');
        $this->context->useWorkspace($workspaceId);

        return Response::redirect('/my-applications');
    }

    private function nextStep(string $status): string
    {
        return [
            'applied' => 'Your application has been received. Watch for an interview invitation.',
            'ai_screening' => 'An AI screening interview is the next step.',
            'qualified' => 'You’ve been qualified — expect a next-round interview soon.',
            'disqualified' => 'This application didn’t move forward this time.',
            'tech_interview' => 'A technical interview is the next step.',
            'manager_interview' => 'A manager interview is the next step.',
            'final_review' => 'Your application is in final review.',
            'offer' => 'An offer is on the table — review it below.',
            'hired' => 'Congratulations — you’ve been hired!',
            'rejected' => 'This application has been closed.',
            'withdrawn' => 'You withdrew this application.',
        ][$status] ?? 'We’ll keep you posted on the next step.';
    }

    private function intOrNull(mixed $value): ?int
    {
        $s = trim((string) $value);

        return $s === '' ? null : max(0, (int) $s);
    }

    /** Candidate gate: authenticated + a resolvable candidate workspace. */
    private function gate(?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/workspaces/select');
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
