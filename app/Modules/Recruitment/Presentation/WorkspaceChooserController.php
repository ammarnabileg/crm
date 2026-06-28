<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Recruitment\Application\CandidacyService;

/**
 * "Choose a workspace to enter." A User → Memberships / Applications → Current
 * Workspace decision point: lists the workspaces where the user is a member (→
 * staff dashboard) and the workspaces where they are a candidate (→ portal),
 * plus the option to create their own. Lives in Recruitment because it needs
 * candidacy data; the Workspaces module must not depend on Recruitment.
 */
final class WorkspaceChooserController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly MembershipService $memberships,
        private readonly CandidacyService $candidacy,
    ) {
    }

    public function select(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }

        $userId = (string) $this->auth->id();
        $member = $this->memberships->workspacesForUserDetailed($userId);

        // Candidate workspaces, excluding any where the user is also a staff member
        // (those already appear under "member" and lead to the dashboard).
        $memberIds = array_flip(array_map(static fn (array $w): string => (string) $w['id'], $member));
        $candidate = array_values(array_filter(
            $this->candidacy->workspacesForCandidate($userId),
            static fn (array $w): bool => ! isset($memberIds[(string) $w['id']]),
        ));

        return Response::html($this->view->page('workspace.select', [
            'user' => $this->auth->user(),
            'member' => $member,
            'candidate' => $candidate,
        ], 'layouts.guest', ['title' => 'Choose a workspace']));
    }
}
