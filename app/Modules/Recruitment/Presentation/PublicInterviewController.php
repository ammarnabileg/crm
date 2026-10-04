<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\Recruitment\Application\InterviewFeedbackService;
use HaHireAI\Modules\Recruitment\Application\InterviewInvitationService;
use HaHireAI\Modules\Recruitment\Application\InterviewRoomService;
use HaHireAI\Modules\Recruitment\Application\InterviewService;
use Throwable;

/**
 * The public interview-link page (no login). Honors the link lifecycle and runs the
 * SAME turn-by-turn AI interview room as the logged-in portal: the candidate is
 * asked one question at a time and answers in their own words; only when the
 * conversation finishes is the interview scored and assessed. The link is
 * single-use and stays valid until the interview is actually completed (spec #5).
 */
final class PublicInterviewController
{
    public function __construct(
        private readonly View $view,
        private readonly InterviewInvitationService $invitations,
        private readonly InterviewService $interviews,
        private readonly InterviewRoomService $rooms,
        private readonly InterviewFeedbackService $feedback,
        private readonly AiCapabilities $ai,
    ) {
    }

    public function show(string $token): Response
    {
        $resolved = $this->invitations->resolve($token);
        $inv = $resolved['invitation'];

        // Resume an interview already in progress on this link.
        if ($resolved['state'] === 'valid' && ! empty($inv['interview_id'])) {
            return $this->renderRoom((string) $inv['workspace_id'], (string) $inv['interview_id'], $token);
        }

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

        // Already started? Resume the conversation instead of restarting.
        if (! empty($invitation['interview_id'])) {
            return $this->renderRoom((string) $invitation['workspace_id'], (string) $invitation['interview_id'], $token);
        }

        // AI interviews need a configured provider AND an application to assess.
        // Without them, the link is simply consumed — no fabricated assessment.
        if (empty($invitation['application_id']) || ! $this->ai->interviewsEnabled((string) $invitation['workspace_id'])) {
            $this->invitations->complete($token, null);

            return $this->page('done', $token, false);
        }

        try {
            $interviewId = $this->interviews->schedule((string) $invitation['workspace_id'], (string) $invitation['application_id'], 'ai', []);
            $this->invitations->attach($token, $interviewId);
            // Begin the room: plan questions, greet, ask the first question. NO
            // assessment yet — that happens only when the candidate finishes.
            $this->rooms->begin((string) $invitation['workspace_id'], $interviewId, null);

            return $this->renderRoom((string) $invitation['workspace_id'], $interviewId, $token);
        } catch (Throwable) {
            $this->invitations->complete($token, null);

            return $this->page('done', $token, false);
        }
    }

    /** Record one answer and advance the conversation (single-use, no login). */
    public function answer(Request $request, string $token): Response
    {
        $resolved = $this->invitations->resolve($token);
        $inv = $resolved['invitation'];
        if ($resolved['state'] !== 'valid' || empty($inv['interview_id'])) {
            return $this->page($resolved['state'] === 'valid' ? 'done' : $resolved['state'], $token);
        }

        $workspaceId = (string) $inv['workspace_id'];
        $interviewId = (string) $inv['interview_id'];
        $state = $this->rooms->answer($workspaceId, $interviewId, (string) $request->input('answer', ''), null);

        if (! empty($state['done'])) {
            $this->invitations->complete($token, $interviewId);

            return $this->page('done', $token, ! $this->feedback->exists($interviewId));
        }

        return $this->renderRoom($workspaceId, $interviewId, $token, $state);
    }

    /**
     * Render the conversational interview room. Closes the link if the interview
     * already finished (e.g. the clock ran out between requests).
     *
     * @param  array<string,mixed>|null  $state
     */
    private function renderRoom(string $workspaceId, string $interviewId, string $token, ?array $state = null): Response
    {
        try {
            $state ??= $this->rooms->state($workspaceId, $interviewId);
        } catch (Throwable) {
            return $this->page('done', $token, false);
        }

        if (! empty($state['done'])) {
            $this->invitations->complete($token, $interviewId);

            return $this->page('done', $token, ! $this->feedback->exists($interviewId));
        }

        return Response::html(
            $this->view->page('recruitment.interview_link', ['state' => 'room', 'token' => $token, 'showFeedback' => false, 'room' => $state], 'layouts.guest', ['title' => 'AI Interview']),
        );
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
