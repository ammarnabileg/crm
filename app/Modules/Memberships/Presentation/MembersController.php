<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Memberships\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\Contracts\AuditRecorder;
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
        private readonly AuditRecorder $audit,
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

        $workspace = $this->context->workspace();

        return $this->shell->render($this->context, 'members.index', [
            'members' => $this->members->membersForWorkspace((string) $this->context->workspaceId()),
            'roles' => $this->roles->rolesForWorkspace((string) $this->context->workspaceId()),
            'canInvite' => $this->context->can('member.invite'),
            'canSuspend' => $this->context->can('member.suspend'),
            'canReactivate' => $this->context->can('member.reactivate'),
            'canRemove' => $this->context->can('member.remove'),
            'ownerUserId' => (string) ($workspace['owner_user_id'] ?? ''),
            'currentUserId' => (string) $this->context->userId(),
            'code' => $this->session->pullFlash('code'),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function suspend(Request $request, string $membershipId): Response
    {
        return $this->changeStatus($request, $membershipId, 'member.suspend', 'suspended');
    }

    public function activate(Request $request, string $membershipId): Response
    {
        return $this->changeStatus($request, $membershipId, 'member.reactivate', 'active');
    }

    public function remove(Request $request, string $membershipId): Response
    {
        $guard = $this->guard($request, $membershipId, 'member.remove');
        if ($guard instanceof Response) {
            return $guard;
        }

        $this->members->remove((string) $this->context->workspaceId(), $membershipId);

        $this->audit->record('memberships.member.removed', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'membership',
            'entity_id' => $membershipId,
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => ['user_id' => $guard['user_id'] ?? null],
        ]);

        $this->session->flash('status', 'Member removed from the workspace.');

        return Response::redirect('/members');
    }

    private function changeStatus(Request $request, string $membershipId, string $permission, string $status): Response
    {
        $guard = $this->guard($request, $membershipId, $permission);
        if ($guard instanceof Response) {
            return $guard;
        }

        $this->members->setStatus((string) $this->context->workspaceId(), $membershipId, $status);

        $this->audit->record('memberships.member.status_changed', [
            'workspace_id' => $this->context->workspaceId(),
            'actor_user_id' => $this->context->userId(),
            'entity_type' => 'membership',
            'entity_id' => $membershipId,
            'ip' => $request->server('REMOTE_ADDR'),
            'changes' => ['status' => $status],
        ]);

        $this->session->flash('status', $status === 'suspended' ? 'Member suspended.' : 'Member reactivated.');

        return Response::redirect('/members');
    }

    /**
     * Shared precondition checks for member actions. Returns the target
     * membership row on success, or a Response to short-circuit on failure.
     *
     * @return array<string, mixed>|Response
     */
    private function guard(Request $request, string $membershipId, string $permission): array|Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission) || ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return $this->forbidden();
        }

        $membership = $this->members->findById($membershipId);
        if ($membership === null || (string) $membership['workspace_id'] !== (string) $this->context->workspaceId()) {
            $this->session->flash('error', 'Member not found in this workspace.');

            return Response::redirect('/members');
        }

        $workspace = $this->context->workspace();
        $ownerId = (string) ($workspace['owner_user_id'] ?? '');
        if ((string) $membership['user_id'] === $ownerId) {
            $this->session->flash('error', 'The workspace owner cannot be suspended or removed.');

            return Response::redirect('/members');
        }
        if ((string) $membership['user_id'] === (string) $this->context->userId()) {
            $this->session->flash('error', 'You cannot change your own membership here.');

            return Response::redirect('/members');
        }

        return $membership;
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
