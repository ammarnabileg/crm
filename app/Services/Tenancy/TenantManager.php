<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Workspace;
use App\Models\User;

/**
 * Holds the active tenant (workspace) for the current request and is the single
 * authority the Model layer consults to scope every tenant-bound query.
 *
 * Isolation guarantee: a tenant-scoped model can only ever read/write rows for
 * the id this manager returns. Cross-tenant access requires the explicit
 * withoutTenantScope() escape hatch, which is confined to super-admin/system
 * code paths — there is no implicit way for one tenant to see another's data.
 */
final class TenantManager
{
    private ?int $id = null;
    private ?Workspace $workspace = null;
    private bool $booted = false;

    public function id(): ?int
    {
        return $this->id;
    }

    public function workspace(): ?Workspace
    {
        if ($this->workspace === null && $this->id !== null) {
            $this->workspace = Workspace::find($this->id);
        }

        return $this->workspace;
    }

    public function hasTenant(): bool
    {
        return $this->id !== null;
    }

    /**
     * Tenant scoping is always in force for tenant-bound models. Code that
     * legitimately needs to cross tenants must opt out via withoutTenantScope().
     */
    public function shouldScope(): bool
    {
        return true;
    }

    public function setTenant(Workspace $workspace): void
    {
        $this->id = (int) $workspace->getKey();
        $this->workspace = $workspace;
        session()->put((string) config('auth.tenant_key', 'active_workspace_id'), $this->id);
    }

    public function setById(int $workspaceId): bool
    {
        $workspace = Workspace::find($workspaceId);
        if ($workspace === null) {
            return false;
        }

        $this->setTenant($workspace);

        return true;
    }

    public function clear(): void
    {
        $this->id = null;
        $this->workspace = null;
        session()->forget((string) config('auth.tenant_key', 'active_workspace_id'));
    }

    /**
     * Establish the active tenant for an authenticated user.
     *
     * Preference order: the workspace stored in the session (if the user still
     * has an active membership there) -> the user's most recent workspace. A user
     * with no workspaces (e.g. a fresh super admin) simply has no active tenant
     * and operates platform-wide via global roles.
     */
    public function bootFor(User $user): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $sessionKey = (string) config('auth.tenant_key', 'active_workspace_id');
        $stored = session()->get($sessionKey);

        if (is_numeric($stored) && $this->userBelongsTo($user, (int) $stored)) {
            $this->setById((int) $stored);

            return;
        }

        $workspaces = $user->workspaces();
        if ($workspaces !== []) {
            $this->setById((int) $workspaces[0]['id']);
        }
    }

    public function userBelongsTo(User $user, int $workspaceId): bool
    {
        $membership = $user->membershipFor($workspaceId);

        return $membership !== null && ($membership->status ?? '') === 'active';
    }
}
