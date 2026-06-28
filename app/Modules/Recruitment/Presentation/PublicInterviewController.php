<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Recruitment\Application\AssessmentService;
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
    ) {
    }

    public function show(string $token): Response
    {
        $resolved = $this->invitations->resolve($token);

        return $this->page($resolved['state'], $token);
    }

    public function start(Request $request, string $token): Response
    {
        $resolved = $this->invitations->resolve($token);
        if ($resolved['state'] !== 'valid') {
            return $this->page($resolved['state'], $token);
        }

        $invitation = (array) $resolved['invitation'];
        $interviewId = null;

        // If the link is tied to an application, run the AI interview now.
        if (! empty($invitation['application_id'])) {
            try {
                $interviewId = $this->interviews->schedule((string) $invitation['workspace_id'], (string) $invitation['application_id'], 'ai', []);
                $this->interviews->runAi((string) $invitation['workspace_id'], $interviewId, null);
                $this->assessments->assessFromInterview((string) $invitation['workspace_id'], $interviewId, null);
            } catch (Throwable) {
                // Best-effort; the link is still consumed below.
            }
        }

        $this->invitations->complete($token, $interviewId);

        return $this->page('done', $token);
    }

    private function page(string $state, string $token): Response
    {
        return Response::html(
            $this->view->page('recruitment.interview_link', ['state' => $state, 'token' => $token], 'layouts.guest', ['title' => 'AI Interview']),
        );
    }
}
