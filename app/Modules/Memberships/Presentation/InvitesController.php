<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\WorkspaceSeatGuard;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Memberships\Application\Exceptions\InvitationException;
use HaHireAI\Modules\Memberships\Application\InvitationService;
use HaHireAI\Modules\Navigation\Application\ContextSwitcher;

/**
 * Accept-first invitations: a signed-in user reviews the workspace invitations
 * addressed to their email and accepts or declines them in-app. The workspace
 * only appears (in the switcher) once they accept (docs/INVITATION_SYSTEM.md).
 */
final class InvitesController
{
    public function __construct(
        private readonly View $view,
        private readonly AuthContext $auth,
        private readonly InvitationService $invitations,
        private readonly ContextSwitcher $switcher,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
        private readonly WorkspaceSeatGuard $seatGuard,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        $user = $this->auth->user() ?? [];

        return Response::html($this->view->page('invites.index', [
            'invites' => $this->invitations->pendingForEmail((string) ($user['email'] ?? '')),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ], 'layouts.app', [
            'user' => $user,
            'sidebar' => [],
            'workspaceName' => null,
            'switcher' => $this->switcher->model($this->auth->contextType()),
        ]));
    }

    public function accept(Request $request, string $invitationId): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::redirect('/invites');
        }

        $user = $this->auth->user() ?? [];
        $email = (string) ($user['email'] ?? '');
        $invite = $this->findOwn($invitationId, $email);
        if ($invite === null) {
            $this->session->flash('error', 'That invitation is no longer available.');

            return Response::redirect('/invites');
        }

        // Joining as staff consumes a billable seat — enforce paid-seat limits so a
        // workspace cannot exceed what it funded (docs/WALLET_AND_BILLING.md §5).
        if (! $this->seatGuard->canAddBillableMember((string) $invite['workspace_id'])) {
            $this->session->flash('error', $this->seatGuard->denyReason((string) $invite['workspace_id']));

            return Response::redirect('/invites');
        }

        try {
            $this->invitations->acceptOwn($invitationId, (string) ($user['id'] ?? ''), $email);
        } catch (InvitationException $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/invites');
        }

        $this->audit->record('memberships.invitation.accepted', [
            'workspace_id' => $invite['workspace_id'],
            'actor_user_id' => $user['id'] ?? null,
            'entity_type' => 'invitation',
            'entity_id' => $invitationId,
            'ip' => $request->server('REMOTE_ADDR'),
        ]);

        // Drop the new member straight into the workspace they just joined.
        $this->auth->setCurrentWorkspace((string) $invite['workspace_id']);
        $this->auth->setContextType('staff');
        $this->session->flash('status', 'You have joined ' . (string) $invite['workspace_name'] . '.');

        return Response::redirect('/dashboard');
    }

    public function decline(Request $request, string $invitationId): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::redirect('/invites');
        }

        $user = $this->auth->user() ?? [];
        try {
            $this->invitations->declineOwn($invitationId, (string) ($user['email'] ?? ''));
            $this->session->flash('status', 'Invitation declined.');
        } catch (InvitationException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/invites');
    }

    /**
     * @return array{workspace_id: string, workspace_name: string}|null
     */
    private function findOwn(string $invitationId, string $email): ?array
    {
        foreach ($this->invitations->pendingForEmail($email) as $inv) {
            if ($inv['id'] === $invitationId) {
                return ['workspace_id' => $inv['workspace_id'], 'workspace_name' => $inv['workspace_name']];
            }
        }

        return null;
    }
}
