<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\Recruitment\Application\AssessmentService;
use HaHireAI\Modules\Recruitment\Application\InterviewFeedbackService;
use HaHireAI\Modules\Recruitment\Application\InterviewInvitationService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use Throwable;

/**
 * The public interview-link page (no login). Honors the link lifecycle: a valid
 * link shows the start page; an expired/invalid one shows "expired or invalid";
 * a used one shows "interview completed successfully" — single-use (spec #5).
 */
final class PublicInterviewController
{
    public function __construct(
        private readonly View $view,
        private readonly InterviewInvitationService $invitations,
        private readonly InterviewService $interviews,
        private readonly AssessmentService $assessments,
        private readonly InterviewFeedbackService $feedback,
        private readonly AiCapabilities $ai,
    ) {
    }

    public function show(string $token): Response
    {
        $resolved = $this->invitations->resolve($token);
        $inv = $resolved['invitation'];
        $showFeedback = $resolved['state'] === 'completed'
            && ! empty($inv['interview_id'])
            && ! $this->feedback->exists((string) $inv['interview_id']);

        return $this->page($resolved['state'], $token, $showFeedback);
    }

    public function start(Request $request, string $token): Response
    {
        $resolved = $this->invitations->resolve($token);
        if ($resolved['state'] !== 'valid') {
            return $this->page($resolved['state'], $token);
        }

        $invitation = (array) $resolved['invitation'];
        $interviewId = null;

        // If the link is tied to an application AND this workspace has its AI
        // configured (OpenAI key), run the AI interview now. Otherwise the link is
        // simply consumed — AI interviews are off without a key.
        if (! empty($invitation['application_id']) && $this->ai->interviewsEnabled((string) $invitation['workspace_id'])) {
            try {
                $interviewId = $this->interviews->schedule((string) $invitation['workspace_id'], (string) $invitation['application_id'], 'ai', []);
                $this->interviews->runAi((string) $invitation['workspace_id'], $interviewId, null);
                $this->assessments->assessFromInterview((string) $invitation['workspace_id'], $interviewId, null);
            } catch (Throwable) {
                // Best-effort; the link is still consumed below.
            }
        }

        $this->invitations->complete($token, $interviewId);

        return $this->page('done', $token, $interviewId !== null);
    }

    /** Candidate feedback on the interview experience (1–5 + comment). */
    public function feedback(Request $request, string $token): Response
    {
        $resolved = $this->invitations->resolve($token);
        $inv = $resolved['invitation'];

        if ($inv !== null && ! empty($inv['interview_id'])) {
            $this->feedback->record(
                (string) $inv['workspace_id'],
                (string) $inv['interview_id'],
                (int) $request->input('rating', 0),
                trim((string) $request->input('comment', '')) ?: null,
            );
        }

        return $this->page('done', $token, false);
    }

    private function page(string $state, string $token, bool $showFeedback = false): Response
    {
        return Response::html(
            $this->view->page('recruitment.interview_link', ['state' => $state, 'token' => $token, 'showFeedback' => $showFeedback], 'layouts.guest', ['title' => 'AI Interview']),
        );
    }
}
