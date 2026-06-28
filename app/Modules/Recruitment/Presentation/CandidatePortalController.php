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
use HaHireAI\Modules\Recruitment\Application\InterviewRoomService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use HaHireAI\Modules\Recruitment\Application\JobService;
use HaHireAI\Modules\Recruitment\Application\OfferService;
use HaHireAI\Core\Contracts\UserDirectory;
use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\AiEngine\Contracts\SpeechToText;
use HaHireAI\Modules\Recruitment\Domain\ApplicationStatus;

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
    ) {
    }

    /** Page 1 — Candidate Portal overview (status, interviews, offers, latest jobs). */
    public function index(): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $uid = (string) $this->context->userId();

        return $this->shell->render($this->context, 'portal.index', [
            'overview' => $this->candidacy->overview($ws, $uid),
            'workspaces' => $this->context->workspaces(),
            'currentWorkspaceId' => $ws,
            'workspaceName' => $this->context->workspace()['name'] ?? '',
            'user' => $this->auth->user(),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

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

            return Response::redirect('/portal/jobs');
        }

        try {
            $appId = $this->applications->apply($ws, $jobId, $uid, trim((string) $request->input('cover_note', '')) ?: null);
        } catch (ApplicationException $e) {
            $this->session->flash('status', $e->getMessage());

            return Response::redirect('/portal/jobs');
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

        // Schedule the AI screening interview only when this workspace has its AI
        // configured (OpenAI key). Without it, AI interviews are off and the
        // application simply proceeds for the team to handle manually.
        $interviewId = null;
        if ($this->ai->interviewsEnabled($ws)) {
            try {
                $interviewId = $this->interviews->schedule($ws, $appId, 'ai', ['mode' => 'text', 'created_by' => $uid]);
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

        if ($interviewId !== null) {
            $this->session->flash('status', 'Application submitted. Your AI interview is ready — start now or later.');

            return Response::redirect('/portal/interview/' . $interviewId);
        }
        $this->session->flash('status', 'Application submitted. Track it here.');

        return Response::redirect('/portal/applications/' . $appId);
    }

    /** The AI interview room (start or resume). */
    public function room(string $interviewId): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        $iv = $this->ownedInterview($interviewId);
        if ($iv === null) {
            return Response::redirect('/portal/applications');
        }

        $state = (string) $iv['status'] === 'completed'
            ? $this->room->state((string) $this->context->workspaceId(), $interviewId)
            : $this->room->begin((string) $this->context->workspaceId(), $interviewId, (string) $this->context->userId());

        return $this->shell->render($this->context, 'portal.interview', [
            'interview' => $iv,
            'state' => $state,
            'applicationId' => (string) $iv['application_id'],
            'workspaceName' => $this->context->workspace()['name'] ?? '',
        ]);
    }

    public function roomAnswer(Request $request, string $interviewId): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $iv = $this->ownedInterview($interviewId);
        if ($iv === null) {
            return Response::redirect('/portal/applications');
        }

        $this->room->answer(
            (string) $this->context->workspaceId(),
            $interviewId,
            (string) $request->input('answer', ''),
            (string) $this->context->userId(),
        );

        // Post/Redirect/Get — the room page renders the updated conversation.
        return Response::redirect('/portal/interview/' . $interviewId);
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

        return Response::redirect('/portal/applications/' . $applicationId);
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
            return Response::redirect('/portal/applications');
        }

        $assessment = $this->assessments->latestForCandidate($ws, $uid);

        return $this->shell->render($this->context, 'portal.application', [
            'application' => $application,
            'stages' => ApplicationStatus::STATUSES,
            'currentStatus' => (string) $application['status'],
            'nextStep' => $this->nextStep((string) $application['status']),
            'interviews' => $this->interviews->forApplication($ws, $applicationId),
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

        return Response::redirect('/portal/profile');
    }

    public function updateProfile(Request $request): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('status', 'Your name is required.');

            return Response::redirect('/portal/profile');
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

        return Response::redirect('/portal/profile');
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

        return Response::redirect('/portal/applications');
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

        return Response::redirect('/portal/applications');
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

        return Response::redirect('/portal/applications/' . $applicationId);
    }

    /** Switch which candidate workspace the portal is showing. */
    public function switchWorkspace(Request $request, string $workspaceId): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::redirect('/portal');
        }
        $this->context->useWorkspace($workspaceId);

        return Response::redirect('/portal');
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
