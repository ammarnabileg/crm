<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Services\Rbac\MemberDirectory;

/**
 * Members (docs/47 RBAC) — the people in the current workspace and the tenant
 * roles they hold. Lists memberships joined to users, invites new members
 * (creating a user if needed), updates a member's roles, and toggles a member
 * active/suspended. Reads require members.view; each write checks the matching
 * members.* permission at the top of the action. Everything is tenant-scoped to
 * the active workspace; the heavy lifting lives in MemberDirectory.
 */
final class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('members.view'), 403);

        $directory = $this->directory();

        // Optional inline activity panel for one member (?activity=<membershipId>).
        $activity = [];
        $activeMember = null;
        $activityId = (int) $request->query('activity', 0);
        if ($activityId > 0) {
            $activeMember = $directory->find($activityId);
            if ($activeMember !== null) {
                $activity = $directory->activity((int) $activeMember->getAttribute('user_id'));
            }
        }

        return $this->view('app.members.index', [
            'title'        => 'Members',
            'members'      => $directory->members(),
            'roles'        => $directory->assignableRoles(),
            'canInvite'    => can('members.invite'),
            'canUpdate'    => can('members.update'),
            'canRemove'    => can('members.remove'),
            'activeMember' => $activeMember,
            'activity'     => $activity,
        ]);
    }

    public function invite(Request $request): Response
    {
        abort_unless(can('members.invite'), 403);

        $data = $this->validate($request, [
            'name'    => 'required|max:160',
            'email'   => 'required|email|max:190',
            'role_id' => 'nullable|integer',
            'title'   => 'nullable|max:120',
        ]);

        $directory = $this->directory();
        $directory->invite(
            trim((string) $data['name']),
            strtolower(trim((string) $data['email'])),
            isset($data['role_id']) && $data['role_id'] !== '' ? (int) $data['role_id'] : null,
            isset($data['title']) && $data['title'] !== '' ? trim((string) $data['title']) : null,
            (int) auth()->id(),
        );

        ActivityLog::record('members.invited', tenant()->id(), (int) auth()->id(), 'Invited ' . $data['email'] . ' to the workspace.');
        $this->withSuccess('Member invited.');

        return $this->redirect(url('members'));
    }

    public function updateRoles(Request $request): Response
    {
        abort_unless(can('members.update'), 403);

        $membershipId = (int) $request->input('membership_id');
        $member = $this->directory()->find($membershipId);
        abort_unless($member !== null, 404);

        $roleIds = (array) $request->input('roles', []);
        $this->directory()->syncRoles($membershipId, array_map('intval', $roleIds));

        ActivityLog::record('members.roles_updated', tenant()->id(), (int) auth()->id(), 'Updated roles for a member.', [], 'membership', $membershipId);
        $this->withSuccess('Member roles updated.');

        return $this->redirect(url('members'));
    }

    public function deactivate(Request $request): Response
    {
        return $this->toggleStatus($request, 'suspended', 'Member deactivated.');
    }

    public function reactivate(Request $request): Response
    {
        return $this->toggleStatus($request, 'active', 'Member reactivated.');
    }

    /**
     * Shared deactivate/reactivate path — both require members.remove and flip the
     * membership status, refusing to act on a member outside this workspace.
     */
    private function toggleStatus(Request $request, string $statusKey, string $message): Response
    {
        abort_unless(can('members.remove'), 403);

        $membershipId = (int) $request->input('membership_id');
        $member = $this->directory()->find($membershipId);
        abort_unless($member !== null, 404);

        $this->directory()->setStatus($membershipId, $statusKey);

        ActivityLog::record('members.status_changed', tenant()->id(), (int) auth()->id(), $message, ['status' => $statusKey], 'membership', $membershipId);
        $this->withSuccess($message);

        return $this->redirect(url('members'));
    }

    private function directory(): MemberDirectory
    {
        return new MemberDirectory((int) tenant()->id());
    }
}
