<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Audit\Application\AuditLogger;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Memberships\Application\InvitationService;
use HaHireAI\Modules\Memberships\Application\MembershipService;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** Member directory + invitations (docs/MEMBERSHIP_ENGINE.md). */
final class MembersController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly MembershipService $members,
        private readonly RoleService $roles,
        private readonly InvitationService $invitations,
        private readonly Session $session,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('member.view')) {
            return $this->forbidden();
        }

        return $this->shell->render($this->context, 'members.index', [
            'members' => $this->members->membersForWorkspace((string) $this->context->workspaceId()),
            'roles' => $this->roles->rolesForWorkspace((string) $this->context->workspaceId()),
            'canInvite' => $this->context->can('member.invite'),
            'code' => $this->session->pullFlash('code'),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function invite(Request $request): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can('member.invite') || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return $this->forbidden();
        }

        $email = trim((string) $request->input('email', ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'A valid email is required.');

            return Response::redirect('/members');
        }

        $roleId = (string) $request->input('role_id', '');
        $invite = $this->invitations->invite(
            (string) $this->context->workspaceId(),
            $email,
            $roleId !== '' ? [$roleId] : [],
            $this->context->userId(),
        );

        $this->audit->record('memberships.invitation.created', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'invitation',
            'entity_id' => $invite['id'],
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => ['email' => $email],
        ]);

        $this->session->flash('code', $invite['code']);
        $this->session->flash('status', "Invitation created for {$email}.");

        return Response::redirect('/members');
    }

    private function forbidden(): Response
    {
        return Response::html('<h1>403</h1><p>You do not have permission to view this page.</p>', 403);
    }
}
