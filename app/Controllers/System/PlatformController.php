<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Platform\PlatformDirectory;
use Throwable;

/**
 * Super-Admin Platform Console (docs/47 RBAC bible — cross-tenant control panel).
 *
 * The platform owner manages every workspace and every user from here. Every
 * action is gated by `system.manage` — the super-admin permission already used
 * by the rest of System/* — so only a super admin (whose AccessControl bypass
 * grants it) reaches these. All cross-tenant reads/writes go through
 * PlatformDirectory, which speaks to the raw connection (not tenant scope), and
 * every mutation is written to activity_logs.
 *
 * Impersonation is implemented with the EXISTING AuthManager surface only:
 * loginById() switches the active session identity and the impersonator's id is
 * parked in a dedicated session key so it can be restored. No platform secret,
 * no AuthManager change.
 */
final class PlatformController extends Controller
{
    /** Session key holding the original (impersonator) user id during an impersonation. */
    private const IMPERSONATOR_KEY = 'platform_impersonator_id';

    // ---- Workspaces -------------------------------------------------------

    public function workspaces(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $keyword = trim((string) $request->query('q', ''));
        $directory = new PlatformDirectory();

        return $this->view('system.platform.workspaces', [
            'title'      => 'Workspaces',
            'workspaces' => $directory->workspaces($keyword !== '' ? ['keyword' => $keyword] : []),
            'stats'      => $directory->stats(),
            'keyword'    => $keyword,
            'cap'        => PlatformDirectory::LIST_CAP,
        ]);
    }

    public function showWorkspace(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $id = (int) $request->query('id', 0);
        $workspace = $id > 0 ? (new PlatformDirectory())->workspace($id) : null;

        if ($workspace === null) {
            abort(404, 'Workspace not found.');
        }

        return $this->view('system.platform.workspace', [
            'title'     => $workspace['name'] . ' · Workspace',
            'workspace' => $workspace,
        ]);
    }

    public function suspendWorkspace(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        return $this->changeWorkspaceStatus($request, 'suspended', 'Workspace suspended.');
    }

    public function activateWorkspace(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        return $this->changeWorkspaceStatus($request, 'active', 'Workspace activated.');
    }

    // ---- Users ------------------------------------------------------------

    public function users(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $keyword = trim((string) $request->query('q', ''));
        $directory = new PlatformDirectory();

        return $this->view('system.platform.users', [
            'title'        => 'Users',
            'users'        => $directory->users($keyword !== '' ? ['keyword' => $keyword] : []),
            'stats'        => $directory->stats(),
            'keyword'      => $keyword,
            'cap'          => PlatformDirectory::LIST_CAP,
            'currentId'    => auth()->id(),
            'impersonating' => $request->query('id') !== null ? false : $this->isImpersonating(),
        ]);
    }

    public function suspendUser(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        return $this->changeUserStatus($request, 'suspended', 'User suspended.', true);
    }

    public function activateUser(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        return $this->changeUserStatus($request, 'active', 'User activated.', false);
    }

    // ---- Impersonation ----------------------------------------------------

    /**
     * Begin acting as another user. Stores the impersonator's id in the session
     * and switches the active identity via AuthManager::loginById. Fully audited.
     * Guards: never impersonate yourself, an unknown user, or a suspended user.
     */
    public function impersonate(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $data = $this->validate($request, [
            'user_id' => 'required|integer',
        ]);
        $targetId = (int) $data['user_id'];

        $impersonatorId = auth()->id();
        if ($impersonatorId === null) {
            abort(403);
        }

        if ($targetId === $impersonatorId) {
            $this->withError('You are already signed in as that user.');

            return $this->redirect(url('system/platform/users'));
        }

        // Already impersonating? Require a stop first so the original identity is
        // never lost behind a second hop.
        if ($this->isImpersonating()) {
            $this->withError('You are already impersonating a user. Stop the current session first.');

            return $this->redirect(url('system/platform/users'));
        }

        $target = User::find($targetId);
        if ($target === null) {
            $this->withError('That user could not be found.');

            return $this->redirect(url('system/platform/users'));
        }

        if (! $target->isActive()) {
            $this->withError('You cannot impersonate a suspended or inactive user. Activate them first.');

            return $this->redirect(url('system/platform/users'));
        }

        // Park the original identity, then switch. loginById writes the session
        // auth key + refreshes the auth singleton; the parked key survives the
        // session-id regeneration (data is preserved, only the id rotates).
        session()->put(self::IMPERSONATOR_KEY, $impersonatorId);

        if (! auth()->loginById($targetId)) {
            session()->forget(self::IMPERSONATOR_KEY);
            $this->withError('Impersonation failed: the user could not be loaded.');

            return $this->redirect(url('system/platform/users'));
        }

        // The impersonated user belongs to different workspaces; drop the active
        // workspace so the tenant re-resolves on the next request, and clear the
        // RBAC decision cache so permissions reflect the new identity at once.
        session()->forget((string) config('auth.tenant_key', 'active_workspace_id'));
        $this->flushAccessCache();

        ActivityLog::record(
            action: 'platform.impersonation.started',
            workspaceId: null,
            userId: $impersonatorId,
            description: "Super admin #{$impersonatorId} started impersonating user #{$targetId}.",
            properties: ['impersonator_id' => $impersonatorId, 'target_id' => $targetId],
            subjectType: 'User',
            subjectId: $targetId,
        );

        $this->withSuccess('You are now impersonating ' . $target->getAttribute('name') . '. Use “Stop impersonating” to return.');

        return $this->redirect(url('dashboard'));
    }

    /**
     * End an impersonation session and restore the original super admin. Lives
     * OUTSIDE the system.manage gate (see the integration note) because the
     * currently-active identity is the impersonated user, who may not hold
     * system.manage — the right to stop is proven by the parked impersonator id,
     * not by a permission.
     */
    public function stopImpersonating(Request $request): Response
    {
        $impersonatorId = $this->impersonatorId();
        if ($impersonatorId === null) {
            // Nothing to restore — bounce somewhere safe.
            $this->withError('You are not impersonating anyone.');

            return $this->redirect(url('dashboard'));
        }

        $impersonatedId = auth()->id();

        session()->forget(self::IMPERSONATOR_KEY);

        if (! auth()->loginById($impersonatorId)) {
            // The original account vanished (deleted/suspended) — fail closed by
            // logging out rather than leaving a half-restored session.
            auth()->logout();
            $this->withError('Your original session could not be restored. Please sign in again.');

            return $this->redirect(url('login'));
        }

        session()->forget((string) config('auth.tenant_key', 'active_workspace_id'));
        $this->flushAccessCache();

        ActivityLog::record(
            action: 'platform.impersonation.stopped',
            workspaceId: null,
            userId: $impersonatorId,
            description: "Super admin #{$impersonatorId} stopped impersonating user #{$impersonatedId}.",
            properties: ['impersonator_id' => $impersonatorId, 'target_id' => $impersonatedId],
            subjectType: 'User',
            subjectId: $impersonatedId,
        );

        $this->withSuccess('Impersonation ended. You are back to your own account.');

        return $this->redirect(url('system/platform/users'));
    }

    // ---- internals --------------------------------------------------------

    private function changeWorkspaceStatus(Request $request, string $statusKey, string $message): Response
    {
        $data = $this->validate($request, ['id' => 'required|integer']);
        $id = (int) $data['id'];

        try {
            (new PlatformDirectory())->setWorkspaceStatus($id, $statusKey);
            $this->withSuccess($message);
        } catch (Throwable $e) {
            $this->withError('Could not update the workspace: ' . $e->getMessage());
        }

        return $this->redirect(url('system/platform/workspaces'));
    }

    private function changeUserStatus(Request $request, string $statusKey, string $message, bool $blockSelf): Response
    {
        $data = $this->validate($request, ['id' => 'required|integer']);
        $id = (int) $data['id'];

        // A super admin must never lock themselves out of their own account.
        if ($blockSelf && $id === auth()->id()) {
            $this->withError('You cannot suspend your own account.');

            return $this->redirect(url('system/platform/users'));
        }

        try {
            (new PlatformDirectory())->setUserStatus($id, $statusKey);
            $this->withSuccess($message);
        } catch (Throwable $e) {
            $this->withError('Could not update the user: ' . $e->getMessage());
        }

        return $this->redirect(url('system/platform/users'));
    }

    private function isImpersonating(): bool
    {
        return $this->impersonatorId() !== null;
    }

    private function impersonatorId(): ?int
    {
        $id = session()->get(self::IMPERSONATOR_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Clear the RBAC per-request decision cache after an identity switch so the
     * very next can()/permission check reflects the new user. Uses the public
     * flushCache() on the access singleton; a no-op if unavailable.
     */
    private function flushAccessCache(): void
    {
        $access = app('access');
        if (method_exists($access, 'flushCache')) {
            $access->flushCache();
        }
    }
}
