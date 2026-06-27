<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Memberships\Application\InvitationService;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use Throwable;

/** Accept a workspace invitation by code (docs/INVITATION_SYSTEM.md). */
final class InvitationController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly InvitationService $invitations,
        private readonly MembershipService $memberships,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function showAccept(string $code): Response
    {
        if (! $this->auth->check()) {
            $this->session->put('intended_invitation', $code);

            return Response::redirect('/login');
        }

        return Response::html($this->view->page('invitations.accept', [
            'code' => $code,
            'error' => $this->session->pullFlash('error'),
        ], 'layouts.guest', ['title' => 'Join workspace']));
    }

    public function accept(Request $request, string $code): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            $this->session->flash('error', 'Security check failed.');

            return Response::redirect('/invitations/' . $code);
        }

        try {
            $membershipId = $this->invitations->accept($code, (string) $this->auth->id());
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/dashboard');
        }

        $membership = $this->memberships->findById($membershipId);
        $workspaceId = $membership !== null ? (string) $membership['workspace_id'] : null;

        $this->audit->record('memberships.invitation.accepted', [
            'workspace_id' => $workspaceId,
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'membership',
            'entity_id' => $membershipId,
            'ip' => $request->server('REMOTE_ADDR'),
        ]);

        // Land the new member in the workspace they just joined.
        if ($workspaceId !== null) {
            $this->auth->setCurrentWorkspace($workspaceId);
        }

        return Response::redirect('/dashboard');
    }
}
